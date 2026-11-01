<?php

namespace App\Domaine\Ovins;

use App\Domaine\Exercices;
use App\Domaine\Tresorerie;
use App\Domaine\Ventes;
use App\Exceptions\RegleMetier;
use App\Support\Empreinte;
use App\Support\Format;
use App\Support\Journal;
use App\Support\Lieux;
use App\Support\Montant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Transferts, sorties, ventes ovines et rapprochement historique (OVM). */
class Mouvements
{
    public const LIBELLES = ['entree' => 'Entrée', 'naissance' => 'Naissance', 'transfert' => 'Transfert', 'vente' => 'Vente', 'mortalite' => 'Mortalité',
        'reforme' => 'Réforme', 'autre' => 'Autre sortie', 'ajustement' => 'Correction d’effectif'];

    public static function libelle(?string $t): string
    {
        return self::LIBELLES[mb_strtolower(trim((string) $t))] ?? ucfirst(str_replace('_', ' ', (string) $t));
    }

    public static function inscrire(array $m): int
    {
        $m += ['utilisateur' => mb_substr(Format::auteur(), 0, 150)];

        return Lieux::executer(fn () => DB::table('OVINS_mouvements')->insertGetId($m));
    }

    private static function animalEnFerme(int $id): object
    {
        $a = DB::table('OVINS_animaux')->where('id_animal', $id)->lockForUpdate()->first();
        if (! $a || $a->statut !== 'en_ferme' || $a->date_sortie !== null) {
            throw new RegleMetier('Cet animal est introuvable ou déjà sorti. Rechargez sa fiche.');
        }

        return $a;
    }

    /** RG-OVM-004 (harmonisation) : date réelle, non future, ni avant la naissance ni avant l'entrée. */
    private static function dateMouvement(object $a, string $date, string $quoi): Carbon
    {
        try {
            $d = Carbon::createFromFormat('!Y-m-d', $date);
        } catch (\Throwable) {
            throw new RegleMetier("Date de $quoi invalide.");
        }
        if (! $d || $d->format('Y-m-d') !== $date || $d->isAfter(today())) {
            throw new RegleMetier("La date de $quoi doit être une date réelle, non future.");
        }
        foreach (['date_naissance' => 'la naissance', 'date_entree' => 'l’entrée'] as $champ => $lib) {
            if ($a->$champ && $a->$champ > '0000-00-00' && $date < $a->$champ) {
                throw new RegleMetier("La date de $quoi ne peut pas précéder $lib de l’animal.");
            }
        }

        return $d;
    }

    /**
     * RG-OVM-001 à 003 : transfert d'un animal vers un autre lot (et le site de ce lot).
     * En mode intégration, la destination doit être un lot de reproductrices : l'animal devient reproducteur
     * et le modèle choisi génère ses soins d'entrée (RG-OVS-006).
     */
    public static function transferer(int $idAnimal, int $idLotDest, string $date, bool $integration = false, ?int $idModele = null, string $commentaire = ''): void
    {
        DB::transaction(function () use ($idAnimal, $idLotDest, $date, $integration, $idModele, $commentaire) {
            $a = self::animalEnFerme($idAnimal);
            $d = self::dateMouvement($a, $date, 'transfert');
            $dest = DB::table('OVINS_lots')->where('id_lot', $idLotDest)->lockForUpdate()->first();
            if (! $dest || $dest->statut !== 'actif') {
                throw new RegleMetier('Lot de destination introuvable ou clos.');
            }
            if ((int) $a->id_lot === $idLotDest) {
                throw new RegleMetier('L’animal est déjà dans ce lot.');
            }
            if ($integration && $dest->type_lot !== 'reproductrices') {
                throw new RegleMetier('Une intégration en reproduction se fait vers un lot de type « Reproductrices ».');
            }
            Referentiels::siteActif((int) $dest->id_site);
            $source = $a->id_lot ? DB::table('OVINS_lots')->where('id_lot', $a->id_lot)->lockForUpdate()->first() : null;
            if ($source && (int) $source->effectif_encours < 1) {
                throw new RegleMetier("L’effectif du lot « {$source->nom_lot} » est déjà à zéro : corrigez-le avant le transfert.");
            }
            $motif = ($integration ? 'Intégration en reproduction' : 'Transfert').' vers '.$dest->nom_lot;
            Lieux::preparer(Lieux::OVIN, $idAnimal, null, $motif);
            $maj = ['id_lot' => $idLotDest, 'id_site' => $dest->id_site] + ($integration ? ['est_reproducteur' => 1] : []);
            Lieux::executer(fn () => DB::table('OVINS_animaux')->where('id_animal', $idAnimal)->update($maj));
            Lieux::reinitialiser();
            $texte = trim($commentaire) !== '' ? ' — '.trim($commentaire) : '';
            if ($source) {
                DB::table('OVINS_lots')->where('id_lot', $source->id_lot)->decrement('effectif_encours');
                self::inscrire(['date_mvt' => $d->toDateString(), 'type_mvt' => 'transfert', 'id_lot' => $source->id_lot, 'id_animal' => $idAnimal, 'quantite' => -1,
                    'id_site_source' => $a->id_site, 'id_site_destination' => $dest->id_site, 'commentaire' => "Sortie vers {$dest->nom_lot}$texte"]);
            }
            DB::table('OVINS_lots')->where('id_lot', $idLotDest)->increment('effectif_encours');
            self::inscrire(['date_mvt' => $d->toDateString(), 'type_mvt' => 'transfert', 'id_lot' => $idLotDest, 'id_animal' => $idAnimal, 'quantite' => 1,
                'id_site_source' => $a->id_site, 'id_site_destination' => $dest->id_site, 'commentaire' => 'Entrée'.($source ? " depuis {$source->nom_lot}" : '').$texte]);
            if ($integration && $idModele) {
                Soins::appliquer($idModele, 'animal', $idAnimal, $d->toDateString());
            }
            Journal::enregistrer($integration ? 'ovin.integration' : 'ovin.transfert', 'OVM', 'ovin', $idAnimal,
                ['id_lot' => $a->id_lot, 'id_site' => $a->id_site], $maj, trim($commentaire) ?: null);
        });
    }

    /**
     * RG-OVM-005 à 009, 014 : sortie individuelle. Une vente crée la vente générale (quantité 1, reste intégral)
     * et sa liaison unique ; aucun encaissement n'est inventé.
     *
     * @return array{sortie:int, vente:?int}
     */
    public static function sortir(int $idAnimal, array $d): array
    {
        $types = config('somadi.ovins.sorties');
        $type = (string) ($d['type_sortie'] ?? '');
        if (! isset($types[$type])) {
            throw new RegleMetier('Type de sortie invalide.');
        }
        $prix = null;
        if ($type === 'vente') {
            $prix = Montant::saisie($d['prix'] ?? '', 'Le prix de vente');
            if ($prix <= 0) {
                throw new RegleMetier('Renseignez un prix de vente positif.');
            }
            if ($prix > 9_999_999_999) {
                throw new RegleMetier('Prix invalide (maximum 99 999 999,99 FCFA).');
            }
            if (trim((string) ($d['client_nom'] ?? '')) === '') {
                throw new RegleMetier('Indiquez le client de cette vente.');
            }
        }
        foreach (['motif' => 100, 'commentaire' => 10000] as $k => $max) {
            if (mb_strlen((string) ($d[$k] ?? '')) > $max) {
                throw new RegleMetier("Champ trop long : $k.");
            }
        }

        return DB::transaction(function () use ($idAnimal, $d, $type, $types, $prix) {
            $a = self::animalEnFerme($idAnimal);
            $date = self::dateMouvement($a, (string) $d['date_sortie'], 'sortie')->toDateString();
            if ($type === 'vente' && DB::table('modVente_ovins_liaisons')->where('id_animal', $idAnimal)->exists()) {
                throw new RegleMetier('Une vente est déjà liée à cet animal.');
            }
            $lot = null;
            if ($a->id_lot) {
                $lot = DB::table('OVINS_lots')->where('id_lot', $a->id_lot)->lockForUpdate()->first();
                if (! $lot || (int) $lot->effectif_encours < 1 || (int) $lot->id_site !== (int) $a->id_site) {
                    throw new RegleMetier('Effectif ou site du lot incohérent. Vérifiez le lot avant la sortie.');
                }
            }
            $prixBase = $prix === null ? null : Montant::versBase($prix);
            $client = $type === 'vente' ? mb_substr(trim($d['client_nom']), 0, 100) : null;
            $contact = $type === 'vente' ? Ventes::telephone((string) ($d['client_contact'] ?? '')) : null;
            $sortie = Lieux::executer(fn () => DB::table('OVINS_sorties')->insertGetId([
                'cible' => 'animal', 'id_lot' => $a->id_lot, 'id_animal' => $idAnimal, 'id_site' => $a->id_site, 'type_sortie' => $type,
                'date_sortie' => $date, 'quantite' => 1, 'prix_unitaire' => $prixBase, 'montant_total' => $prixBase,
                'client_nom' => $client, 'client_contact' => $contact, 'motif' => ($d['motif'] ?? null) ?: null,
                'commentaire' => ($d['commentaire'] ?? null) ?: null, 'utilisateur' => mb_substr(Format::auteur(), 0, 100),
            ]));
            self::inscrire(['date_mvt' => $date, 'type_mvt' => $type, 'id_lot' => $a->id_lot, 'id_animal' => $idAnimal, 'quantite' => 1,
                'id_site_source' => $a->id_site, 'prix_unitaire' => $prixBase, 'montant_total' => $prixBase,
                'source_table' => 'OVINS_sorties', 'source_id' => $sortie, 'commentaire' => $types[$type][0].(($d['motif'] ?? '') ? ' — '.$d['motif'] : '')]);
            Lieux::preparer(Lieux::OVIN, $idAnimal, null, 'Sortie : '.$types[$type][0]);
            Lieux::executer(fn () => DB::table('OVINS_animaux')->where('id_animal', $idAnimal)->update([
                'statut' => $types[$type][1], 'date_sortie' => $date, 'motif_sortie' => mb_substr(($d['motif'] ?? '') ?: $types[$type][0], 0, 100), 'prix_vente' => $prixBase,
            ]));
            Lieux::reinitialiser();
            if ($lot) {
                DB::table('OVINS_lots')->where('id_lot', $lot->id_lot)->decrement('effectif_encours');
            }
            $idVente = null;
            if ($type === 'vente') {
                $vente = Ventes::creerOvin(['date_vente' => $date, 'client_nom' => $client, 'client_tel' => $contact, 'prix_unitaire' => number_format($prix / 100, 2, ',', ''),
                    'commentaire' => 'Vente de l’ovin '.($a->code_animal ?: '#'.$idAnimal)]);
                $idVente = (int) $vente->id;
                self::lier($sortie, $idAnimal, $idVente, (string) $a->code_animal, 'sortie', '', []);
            }
            Journal::enregistrer('ovin.sortie', 'OVM', 'ovin', $idAnimal, ['statut' => $a->statut, 'id_lot' => $a->id_lot],
                ['statut' => $types[$type][1], 'date_sortie' => $date, 'sortie' => $sortie, 'vente' => $idVente], ($d['motif'] ?? null) ?: null);

            return ['sortie' => $sortie, 'vente' => $idVente];
        });
    }

    private static function lier(int $sortie, int $animal, int $vente, string $code, string $mode, string $motif, array $avant): void
    {
        try {
            Lieux::executer(fn () => DB::table('modVente_ovins_liaisons')->insert([
                'id_sortie' => $sortie, 'id_animal' => $animal, 'id_vente' => $vente, 'code_animal' => $code !== '' ? $code : "#$animal",
                'mode_liaison' => $mode, 'motif' => $motif, 'avant_json' => json_encode($avant, JSON_UNESCAPED_UNICODE), 'utilisateur' => mb_substr(Format::auteur(), 0, 100),
            ]));
        } catch (\Illuminate\Database\QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw new RegleMetier('Cette sortie, cet animal ou cette vente est déjà lié : aucune seconde liaison.');
            }
            throw $e;
        }
    }

    // ----- RG-OVM-010 à 013 : rapprochement historique des ventes ovines (admin)

    /** Sorties « vente » sans liaison avec le registre général. */
    public static function sortiesNonLiees()
    {
        return DB::table('OVINS_sorties as s')->leftJoin('modVente_ovins_liaisons as l', 'l.id_sortie', '=', 's.id_sortie')
            ->leftJoin('OVINS_animaux as a', 'a.id_animal', '=', 's.id_animal')
            ->whereRaw("LOWER(TRIM(s.type_sortie)) = 'vente'")->whereNull('l.id')->whereNotNull('s.id_animal')
            ->orderByDesc('s.date_sortie')->get(['s.*', 'a.code_animal', 'a.nom_animal']);
    }

    public static function estProduitOvin(?string $p): bool
    {
        return in_array(mb_strtolower(trim((string) $p)), config('somadi.ovins.variantes_produit'), true);
    }

    private static function normaliserNom(?string $n): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $n)));
    }

    /** RG-OVM-011 : ventes existantes exactement concordantes. */
    public static function ventesConcordantes(object $s)
    {
        return DB::table('modVente_Ventes as v')->leftJoin('modVente_ovins_liaisons as l', 'l.id_vente', '=', 'v.id')
            ->whereNull('l.id')->where('v.quantite', 1)->whereNull('v.id_lot')->where('v.date_vente', $s->date_sortie)
            ->where('v.prix_unitaire', $s->prix_unitaire)->get(['v.*'])
            ->filter(fn ($v) => self::estProduitOvin($v->espece) && self::normaliserNom($v->client_nom) === self::normaliserNom($s->client_nom)
                && Tresorerie::audit($v)['ok'])->values();
    }

    /** RG-OVM-012 : recettes admissibles pour une vente à créer. */
    public static function recettesAdmissibles(object $s)
    {
        return DB::table('modCompta_operations')->where('type_op', 'recette')->where('est_valide', 1)->where('date_op', '>=', $s->date_sortie)
            ->whereNull('id_lot')->where(fn ($q) => $q->whereNull('source_table')->orWhere('source_table', ''))
            ->when(Exercices::borneCloture(), fn ($q, $b) => $q->where('date_op', '>', $b))
            ->orderBy('date_op')->limit(200)->get();
    }

    /** Plan de rapprochement (aperçu) ; mode « existante » (id_vente) ou « creation » (recettes, attestation). */
    public static function plan(int $idSortie, array $d): array
    {
        $s = DB::table('OVINS_sorties')->where('id_sortie', $idSortie)->first() ?? throw new RegleMetier('Sortie introuvable.');
        if (mb_strtolower(trim($s->type_sortie)) !== 'vente' || ! $s->id_animal) {
            throw new RegleMetier('Seule une sortie de vente individuelle se rapproche d’une vente.');
        }
        if (DB::table('modVente_ovins_liaisons')->where('id_sortie', $idSortie)->orWhere('id_animal', $s->id_animal)->exists()) {
            throw new RegleMetier('Cette sortie ou cet animal est déjà lié à une vente.');
        }
        $borne = Exercices::borneCloture();
        if ($borne && $s->date_sortie <= $borne) {
            throw new RegleMetier('Cette sortie appartient à une période clôturée : consultation seulement.');
        }
        $motif = trim((string) ($d['motif'] ?? ''));
        if (mb_strlen($motif) < 10) {
            throw new RegleMetier('Décrivez les justificatifs (10 caractères au moins).');
        }
        if (($d['mode'] ?? '') === 'existante') {
            $v = self::ventesConcordantes($s)->firstWhere('id', (int) ($d['id_vente'] ?? 0));
            if (! $v) {
                throw new RegleMetier('Cette vente ne concorde pas exactement (produit ovin, quantité 1, même client, même date, même prix, solde cohérent, sans liaison).');
            }

            return ['mode' => 'existante', 'sortie' => $idSortie, 'vente' => (int) $v->id, 'motif' => $motif, 'empreinte' => Empreinte::de(['s' => $s, 'v' => $v])];
        }
        if (! (float) $s->prix_unitaire || ! trim((string) $s->client_nom)) {
            throw new RegleMetier('La sortie n’a pas de prix ou de client : impossible de créer la vente.');
        }
        $ids = array_values(array_unique(array_map('intval', $d['recettes'] ?? [])));
        $admissibles = self::recettesAdmissibles($s)->keyBy('id');
        $cumul = 0;
        foreach ($ids as $id) {
            $o = $admissibles->get($id) ?? throw new RegleMetier("La recette n° $id n’est pas admissible (valide, autonome, postérieure à la vente, hors période clôturée).");
            $cumul += Montant::base($o->montant);
        }
        $prix = Montant::base($s->prix_unitaire);
        if ($cumul > $prix) {
            throw new RegleMetier('Le cumul des recettes dépasse le prix de la sortie.');
        }
        if (! $ids && empty($d['aucun_paiement'])) {
            throw new RegleMetier('Sans recette sélectionnée, attestez qu’aucun paiement n’a été reçu auparavant.');
        }
        if (DB::table('modVente_Ventes')->where('date_vente', $s->date_sortie)->where('quantite', 1)->where('prix_unitaire', $s->prix_unitaire)->get()
            ->contains(fn ($v) => self::estProduitOvin($v->espece) && self::normaliserNom($v->client_nom) === self::normaliserNom($s->client_nom))) {
            throw new RegleMetier('Une vente semblable existe déjà : liez-la plutôt que d’en créer une seconde.');
        }

        return ['mode' => 'creation', 'sortie' => $idSortie, 'recettes' => $ids, 'cumul' => $cumul, 'prix' => $prix, 'motif' => $motif,
            'empreinte' => Empreinte::de(['s' => $s, 'o' => $admissibles->only($ids)->values()->all()])];
    }

    /** RG-OVM-013 : confirmation sur un aperçu inchangé. */
    public static function rapprocher(int $idSortie, array $d, string $empreinte): int
    {
        return DB::transaction(function () use ($idSortie, $d, $empreinte) {
            DB::table('OVINS_sorties')->where('id_sortie', $idSortie)->lockForUpdate()->first();
            $p = self::plan($idSortie, $d);
            if (! hash_equals($p['empreinte'], $empreinte)) {
                throw new RegleMetier('Les données ont changé depuis l’aperçu : vérifiez-le de nouveau.');
            }
            $s = DB::table('OVINS_sorties')->where('id_sortie', $idSortie)->first();
            $code = (string) DB::table('OVINS_animaux')->where('id_animal', $s->id_animal)->value('code_animal');
            if ($p['mode'] === 'existante') {
                self::lier($idSortie, (int) $s->id_animal, $p['vente'], $code, 'existante', $p['motif'], ['sortie' => (array) $s]);
                Journal::enregistrer('ovin.rapprochement', 'OVM', 'sortie_ovine', $idSortie, null, ['vente' => $p['vente'], 'mode' => 'existante'], $p['motif']);

                return $p['vente'];
            }
            $prix = Montant::base($s->prix_unitaire);
            $vente = Ventes::creerOvin(['date_vente' => $s->date_sortie, 'client_nom' => $s->client_nom,
                'client_tel' => Ventes::telephone((string) (($d['client_tel'] ?? '') ?: $s->client_contact)),
                'prix_unitaire' => number_format($prix / 100, 2, ',', ''), 'commentaire' => "Vente reconstituée pour la sortie n° $idSortie ($code)"]);
            foreach ($p['recettes'] as $id) {
                Lieux::executer(fn () => DB::table('modCompta_operations')->where('id', $id)->update([
                    'source_table' => Tresorerie::SOURCE_VENTE, 'source_id' => $vente->id, 'date_vente' => $s->date_sortie,
                ]));
            }
            $reste = $prix - $p['cumul'];
            Lieux::executer(fn () => DB::table('modVente_Ventes')->where('id', $vente->id)->update([
                'montant_reste_encaisser' => Montant::versBase($reste), 'statut' => $reste === 0 ? 'Encaissé' : 'A encaisser',
            ]));
            self::lier($idSortie, (int) $s->id_animal, (int) $vente->id, $code, 'creation', $p['motif'], ['sortie' => (array) $s, 'recettes' => $p['recettes']]);
            Journal::enregistrer('ovin.rapprochement', 'OVM', 'sortie_ovine', $idSortie, null, ['vente' => $vente->id, 'mode' => 'creation', 'recettes' => $p['recettes']], $p['motif']);

            return (int) $vente->id;
        });
    }
}
