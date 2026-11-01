<?php

namespace App\Domaine\Ovins;

use App\Exceptions\RegleMetier;
use App\Support\Empreinte;
use App\Support\Format;
use App\Support\Journal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Soins ovins : catalogue, modèles, application et suivi (OVS). */
class Soins
{
    public const STATUTS = ['planifie' => ['Planifié', 'attente'], 'realise' => ['Réalisé', 'ok'], 'annule' => ['Annulé', 'neutre']];

    // ----- Catalogue (RG-OVS-001)

    public static function enregistrerType(?int $id, array $d): int
    {
        $v = [
            'nom_soin' => trim((string) $d['nom_soin']), 'categorie' => ($d['categorie'] ?? null) ?: null, 'espece' => 'ovin',
            'voie' => ($d['voie'] ?? null) ?: null, 'delai_attente_jours' => ($d['delai_attente_jours'] ?? '') === '' ? null : max(0, (int) $d['delai_attente_jours']),
            'commentaire' => ($d['commentaire'] ?? null) ?: null,
        ];
        if ($v['nom_soin'] === '' || mb_strlen($v['nom_soin']) > 100) {
            throw new RegleMetier('Le nom du soin est obligatoire (100 caractères maximum).');
        }
        if ($id) {
            $avant = DB::table('OVINS_soins_types')->where('id_type_soin', $id)->first() ?? throw new RegleMetier('Soin introuvable.');
            DB::table('OVINS_soins_types')->where('id_type_soin', $id)->update($v);
            Journal::enregistrer('soin_ovin_type.modification', 'OVS', 'soin_type', $id, (array) $avant, $v);

            return $id;
        }
        $id = DB::table('OVINS_soins_types')->insertGetId($v);
        Journal::enregistrer('soin_ovin_type.creation', 'OVS', 'soin_type', $id, apres: $v);

        return $id;
    }

    // ----- Modèles (RG-OVS-002)

    /**
     * La base n'admet qu'un modèle actif et un modèle inactif par catégorie (clé unique categorie + actif) :
     * la catégorie désigne l'usage du modèle (entrée en reproduction, agneaux…).
     */
    public static function enregistrerModele(?int $id, array $d, array $lignes): int
    {
        $v = [
            'nom_modele' => trim((string) $d['nom_modele']), 'categorie' => trim((string) ($d['categorie'] ?? '')) ?: 'general',
            'cible' => in_array($d['cible'] ?? 'tous', ['tous', 'lot', 'animal'], true) ? ($d['cible'] ?? 'tous') : 'tous',
            'actif' => empty($d['actif']) ? 0 : 1, 'description' => ($d['description'] ?? null) ?: null,
        ];
        if ($v['nom_modele'] === '') {
            throw new RegleMetier('Le nom du modèle est obligatoire.');
        }
        $propres = [];
        foreach ($lignes as $i => $l) {
            if (($l['id_type_soin'] ?? '') === '') {
                continue;
            }
            if (! DB::table('OVINS_soins_types')->where('id_type_soin', (int) $l['id_type_soin'])->exists()) {
                throw new RegleMetier('Soin inconnu à la ligne '.($i + 1).'.');
            }
            $off = filter_var($l['jour_offset'] ?? 0, FILTER_VALIDATE_INT);
            if ($off === false || abs($off) > 3650) {
                throw new RegleMetier('Décalage invalide à la ligne '.($i + 1).' (en jours, ±3650).');
            }
            $propres[] = ['jour_offset' => $off, 'id_type_soin' => (int) $l['id_type_soin'], 'dose' => ($l['dose'] ?? null) ?: null, 'unite' => ($l['unite'] ?? null) ?: null,
                'commentaire' => ($l['commentaire'] ?? null) ?: null, 'obligatoire' => empty($l['obligatoire']) ? 0 : 1, 'ordre' => count($propres)];
        }
        if (! $propres) {
            throw new RegleMetier('Ajoutez au moins une ligne de soin au modèle.');
        }

        return DB::transaction(function () use ($id, $v, $propres) {
            $conflit = DB::table('OVINS_modeles_soins')->where('categorie', $v['categorie'])->where('actif', $v['actif'])->when($id, fn ($q) => $q->where('id_modele', '<>', $id))->value('nom_modele');
            if ($conflit) {
                throw new RegleMetier("Un modèle ".($v['actif'] ? 'actif' : 'inactif')." existe déjà pour la catégorie « {$v['categorie']} » ($conflit). La base n’en admet qu’un : ".($v['actif'] ? 'désactivez-le' : 'supprimez ou réactivez-le').' d’abord, ou choisissez une autre catégorie.');
            }
            $avant = null;
            if ($id) {
                $avant = DB::table('OVINS_modeles_soins')->where('id_modele', $id)->lockForUpdate()->first() ?? throw new RegleMetier('Modèle introuvable.');
                DB::table('OVINS_modeles_soins')->where('id_modele', $id)->update($v);
                DB::table('OVINS_modeles_soins_lignes')->where('id_modele', $id)->delete();
            } else {
                $id = DB::table('OVINS_modeles_soins')->insertGetId($v);
            }
            DB::table('OVINS_modeles_soins_lignes')->insert(array_map(fn ($l) => $l + ['id_modele' => $id], $propres));
            Journal::enregistrer($avant ? 'modele_ovin.modification' : 'modele_ovin.creation', 'OVS', 'modele_soins', $id, $avant ? (array) $avant : null, $v + ['lignes' => $propres]);

            return $id;
        });
    }

    public static function lignesModele(int $id)
    {
        return DB::table('OVINS_modeles_soins_lignes as l')->leftJoin('OVINS_soins_types as t', 't.id_type_soin', '=', 'l.id_type_soin')
            ->where('l.id_modele', $id)->orderBy('l.ordre')->orderBy('l.jour_offset')->get(['l.*', 't.nom_soin', 't.categorie']);
    }

    // ----- Application (RG-OVS-003 à 005)

    /**
     * Génère les soins planifiés d'un modèle pour un lot (soin collectif) ou un animal (soin individuel).
     * Un soin identique déjà planifié (même cible, même soin, même date) n'est pas recréé.
     *
     * @return array{crees:int, ignores:int}
     */
    public static function appliquer(int $idModele, string $cible, int $idCible, string $dateReference): array
    {
        if (! in_array($cible, ['lot', 'animal'], true)) {
            throw new RegleMetier('Cible invalide.');
        }
        $m = DB::table('OVINS_modeles_soins')->where('id_modele', $idModele)->first() ?? throw new RegleMetier('Modèle introuvable.');
        if (! $m->actif) {
            throw new RegleMetier('Ce modèle est inactif.');
        }
        if ($m->cible !== 'tous' && $m->cible !== $cible) {
            throw new RegleMetier('Ce modèle est réservé aux '.($m->cible === 'lot' ? 'lots' : 'animaux').'.');
        }
        $ref = Carbon::parse($dateReference);
        if ($cible === 'lot') {
            $c = DB::table('OVINS_lots')->where('id_lot', $idCible)->first();
            if (! $c || $c->statut !== 'actif') {
                throw new RegleMetier('Lot introuvable ou clos.');
            }
        } else {
            $c = DB::table('OVINS_animaux')->where('id_animal', $idCible)->first();
            if (! $c || $c->statut !== 'en_ferme') {
                throw new RegleMetier('Animal introuvable ou sorti.');
            }
        }

        return DB::transaction(function () use ($m, $cible, $idCible, $c, $ref) {
            $crees = $ignores = 0;
            foreach (self::lignesModele((int) $m->id_modele) as $l) {
                $date = $ref->copy()->addDays((int) $l->jour_offset)->toDateString();
                $existe = DB::table('OVINS_soins_realises')->where('cible', $cible)->where($cible === 'lot' ? 'id_lot' : 'id_animal', $idCible)
                    ->where('id_type_soin', $l->id_type_soin)->where('date_soin', $date)->where('statut', 'planifie')->exists();
                if ($existe) {
                    $ignores++;

                    continue;
                }
                DB::table('OVINS_soins_realises')->insert([
                    'cible' => $cible, 'id_lot' => $cible === 'lot' ? $idCible : null, 'id_animal' => $cible === 'animal' ? $idCible : null,
                    'id_site' => $c->id_site, 'id_type_soin' => $l->id_type_soin, 'produit' => mb_substr((string) $l->nom_soin, 0, 30),
                    'dose' => mb_substr(trim(($l->dose ?? '').' '.($l->unite ?? '')), 0, 30) ?: null, 'statut' => 'planifie', 'date_soin' => $date,
                    'type_soin' => mb_substr((string) ($l->categorie ?? ''), 0, 20) ?: null,
                    'commentaire' => trim('Modèle « '.$m->nom_modele.' »'.($l->commentaire ? ' — '.$l->commentaire : '')), 'utilisateur' => mb_substr(Format::auteur(), 0, 100),
                ]);
                $crees++;
            }
            Journal::enregistrer('modele_ovin.application', 'OVS', $cible === 'lot' ? 'lot_ovin' : 'ovin', $idCible, null,
                ['modele' => $m->id_modele, 'reference' => $ref->toDateString(), 'crees' => $crees, 'ignores' => $ignores]);

            return ['crees' => $crees, 'ignores' => $ignores];
        });
    }

    /** Soin ponctuel, planifié ou déjà réalisé. */
    public static function saisir(array $d): int
    {
        $cible = $d['cible'] ?? '';
        $type = DB::table('OVINS_soins_types')->where('id_type_soin', (int) ($d['id_type_soin'] ?? 0))->first() ?? throw new RegleMetier('Choisissez un soin du catalogue.');
        $c = $cible === 'lot' ? DB::table('OVINS_lots')->where('id_lot', (int) $d['id_cible'])->first()
            : ($cible === 'animal' ? DB::table('OVINS_animaux')->where('id_animal', (int) $d['id_cible'])->first() : null);
        if (! $c) {
            throw new RegleMetier('Choisissez le lot ou l’animal concerné.');
        }
        $realise = ! empty($d['realise']);
        if ($realise && $d['date_soin'] > today()->toDateString()) {
            throw new RegleMetier('Un soin réalisé ne peut pas être daté dans le futur.');
        }
        $v = [
            'cible' => $cible, 'id_lot' => $cible === 'lot' ? $c->id_lot : null, 'id_animal' => $cible === 'animal' ? $c->id_animal : null, 'id_site' => $c->id_site,
            'id_type_soin' => $type->id_type_soin, 'produit' => mb_substr($type->nom_soin, 0, 30), 'dose' => mb_substr((string) ($d['dose'] ?? ''), 0, 30) ?: null,
            'statut' => $realise ? 'realise' : 'planifie', 'date_soin' => $d['date_soin'], 'date_saisie' => $realise ? now() : null,
            'type_soin' => mb_substr((string) $type->categorie, 0, 20) ?: null, 'commentaire' => ($d['commentaire'] ?? null) ?: null, 'utilisateur' => mb_substr(Format::auteur(), 0, 100),
        ];
        $id = DB::table('OVINS_soins_realises')->insertGetId($v);
        Journal::enregistrer('soin_ovin.saisie', 'OVS', 'soin_ovin', $id, apres: $v);

        return $id;
    }

    public static function requete(array $f)
    {
        return DB::table('OVINS_soins_realises as r')
            ->leftJoin('OVINS_soins_types as t', 't.id_type_soin', '=', 'r.id_type_soin')
            ->leftJoin('OVINS_lots as l', 'l.id_lot', '=', 'r.id_lot')
            ->leftJoin('OVINS_animaux as a', 'a.id_animal', '=', 'r.id_animal')
            ->leftJoin('OVINS_sites as s', 's.id_site', '=', 'r.id_site')
            ->leftJoin('OVINS_lot_soins_actions as ac', 'ac.id_action', '=', 'r.id_action')
            ->when($f['site'] ?? null, fn ($q, $v) => $q->where('r.id_site', $v))
            ->when($f['lot'] ?? null, fn ($q, $v) => $q->where('r.id_lot', $v))
            ->when($f['animal'] ?? null, fn ($q, $v) => $q->where('r.id_animal', $v))
            ->when($f['statut'] ?? null, fn ($q, $v) => $q->where('r.statut', $v))
            ->select('r.*', 't.nom_soin', 't.categorie', 't.delai_attente_jours', 'l.nom_lot', 'a.code_animal', 'a.nom_animal', 's.nom_site', 'ac.type_fenetre', 'ac.num_cycle');
    }

    /** RG-OVS-011 et 008 : seul un soin planifié passe à réalisé ; l'action de fenêtre liée suit. */
    public static function marquerRealise(int $id, string $empreinte, ?string $date = null): void
    {
        DB::transaction(function () use ($id, $empreinte, $date) {
            $s = DB::table('OVINS_soins_realises')->where('id_soin', $id)->lockForUpdate()->first() ?? throw new RegleMetier('Soin introuvable.');
            Empreinte::verifier($s, $empreinte);
            if ($s->statut !== 'planifie') {
                throw new RegleMetier('Ce soin n’est plus planifié.');
            }
            $date = $date ?: min($s->date_soin, today()->toDateString());
            if ($date > today()->toDateString()) {
                throw new RegleMetier('Un soin ne peut pas être marqué réalisé à une date future.');
            }
            $v = ['statut' => 'realise', 'date_soin' => $date, 'date_saisie' => now(), 'utilisateur' => mb_substr(Format::auteur(), 0, 100)];
            DB::table('OVINS_soins_realises')->where('id_soin', $id)->update($v);
            if ($s->id_action) {
                DB::table('OVINS_lot_soins_actions')->where('id_action', $s->id_action)->update(['statut' => 'realise']);
            }
            Journal::enregistrer('soin_ovin.realise', 'OVS', 'soin_ovin', $id, ['statut' => $s->statut, 'date_soin' => $s->date_soin], $v);
        });
    }

    /** RG-OVS-011 : retour réalisé → planifié réservé à l'administrateur et au technicien (contrôlé par l'appelant). */
    public static function annulerRealisation(int $id, string $motif): void
    {
        if (mb_strlen(trim($motif)) < 3) {
            throw new RegleMetier('Indiquez le motif.');
        }
        DB::transaction(function () use ($id, $motif) {
            $s = DB::table('OVINS_soins_realises')->where('id_soin', $id)->lockForUpdate()->first() ?? throw new RegleMetier('Soin introuvable.');
            if ($s->statut !== 'realise') {
                throw new RegleMetier('Ce soin n’est pas marqué réalisé.');
            }
            DB::table('OVINS_soins_realises')->where('id_soin', $id)->update(['statut' => 'planifie', 'date_saisie' => null]);
            if ($s->id_action) {
                DB::table('OVINS_lot_soins_actions')->where('id_action', $s->id_action)->update(['statut' => 'planifie']);
            }
            Journal::enregistrer('soin_ovin.annulation_realisation', 'OVS', 'soin_ovin', $id, ['statut' => 'realise', 'utilisateur' => $s->utilisateur, 'date_saisie' => $s->date_saisie], ['statut' => 'planifie'], trim($motif));
        });
    }

    /** Un soin planifié devenu sans objet est annulé (jamais supprimé). */
    public static function annuler(int $id, string $motif): void
    {
        if (mb_strlen(trim($motif)) < 3) {
            throw new RegleMetier('Indiquez le motif.');
        }
        DB::transaction(function () use ($id, $motif) {
            $s = DB::table('OVINS_soins_realises')->where('id_soin', $id)->lockForUpdate()->first() ?? throw new RegleMetier('Soin introuvable.');
            if ($s->statut !== 'planifie') {
                throw new RegleMetier('Seul un soin planifié peut être annulé.');
            }
            $com = trim(($s->commentaire ? $s->commentaire.' — ' : '').'Annulé : '.trim($motif));
            DB::table('OVINS_soins_realises')->where('id_soin', $id)->update(['statut' => 'annule', 'commentaire' => $com, 'utilisateur' => mb_substr(Format::auteur(), 0, 100)]);
            if ($s->id_action) {
                DB::table('OVINS_lot_soins_actions')->where('id_action', $s->id_action)->update(['statut' => 'annule']);
            }
            Journal::enregistrer('soin_ovin.annulation', 'OVS', 'soin_ovin', $id, ['statut' => 'planifie'], ['statut' => 'annule'], trim($motif));
        });
    }
}
