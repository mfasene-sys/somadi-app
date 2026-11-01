<?php

namespace App\Domaine\Ovins;

use App\Exceptions\RegleMetier;
use App\Support\Empreinte;
use App\Support\Journal;
use App\Support\Lieux;
use App\Support\Montant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Registre individuel des ovins (OVA). Statut, site et lot ne changent que par transfert ou sortie (EC18). */
class Animaux
{
    public const AGES = ['agneau' => 'Agneau (< 6 mois)', 'jeune' => 'Jeune (6 à 12 mois)', 'adulte' => 'Adulte (≥ 12 mois)', 'inconnu' => 'Âge inconnu'];

    public const REPROS = ['gestante' => 'Gestante', 'a_agneler' => 'Agnelage sous 30 jours', 'retard' => 'Agnelage en retard', 'reproducteur' => 'Reproducteur', 'non' => 'Hors reproduction'];

    public static function statut(?string $s): string
    {
        return config('somadi.ovins.statuts')[$s] ?? ucfirst((string) $s);
    }

    public static function categorieAge(?string $naissance, ?string $reference = null): string
    {
        if (! $naissance || $naissance <= '0000-00-00') {
            return 'inconnu';
        }
        $mois = Carbon::parse($naissance)->diffInMonths(Carbon::parse($reference ?? today()->toDateString()));

        return $mois < 6 ? 'agneau' : ($mois < 12 ? 'jeune' : 'adulte');
    }

    public static function age(?string $naissance): string
    {
        if (! $naissance || $naissance <= '0000-00-00') {
            return '—';
        }
        $d = Carbon::parse($naissance)->diff(today());

        return $d->y ? $d->y.' an'.($d->y > 1 ? 's' : '').($d->m ? ' '.$d->m.' mois' : '') : ($d->m ? $d->m.' mois' : $d->d.' j');
    }

    /** RG-OVA-003 : liste filtrée (sites, lot, propriétaires, sexes, statuts, âges, reproduction). */
    public static function requete(array $f)
    {
        $today = today()->toDateString();
        $gest = DB::table('OVINS_repro')->whereNull('date_agnelage_reel')->whereNotNull('date_agnelage_prevu')
            ->groupBy('id_mere')->select('id_mere', DB::raw('MAX(date_agnelage_prevu) prevu'));
        $q = DB::table('OVINS_animaux as a')
            ->leftJoin('OVINS_sites as s', 's.id_site', '=', 'a.id_site')
            ->leftJoin('OVINS_lots as l', 'l.id_lot', '=', 'a.id_lot')
            ->leftJoin('OVINS_proprietaires as p', 'p.id_proprietaire', '=', 'a.id_proprietaire')
            ->leftJoinSub($gest, 'g', 'g.id_mere', '=', 'a.id_animal')
            ->select('a.*', 's.nom_site', 'l.nom_lot', 'p.nom_proprietaire', 'p.couleur_hex', 'g.prevu');
        foreach (['sites' => 'a.id_site', 'proprietaires' => 'a.id_proprietaire'] as $k => $col) {
            if (! empty($f[$k])) {
                $q->whereIn($col, array_map('intval', (array) $f[$k]));
            }
        }
        if (! empty($f['lot'])) {
            $q->where('a.id_lot', (int) $f['lot']);
        }
        if (! empty($f['sexes'])) {
            $q->whereIn('a.sexe', array_intersect((array) $f['sexes'], ['M', 'F']));
        }
        $statuts = (array) ($f['statuts'] ?? ['en_ferme']);
        if ($statuts && ! in_array('tous', $statuts, true)) {
            $q->whereIn('a.statut', $statuts);
        }
        if (! empty($f['q'])) {
            $t = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($f['q'])).'%';
            $q->where(fn ($w) => $w->where('a.code_animal', 'like', $t)->orWhere('a.nom_animal', 'like', $t)->orWhere('a.race', 'like', $t));
        }
        if (! empty($f['ages'])) {
            $q->where(function ($w) use ($f) {
                foreach ((array) $f['ages'] as $age) {
                    match ($age) {
                        'agneau' => $w->orWhere('a.date_naissance', '>', today()->subMonths(6)->toDateString()),
                        'jeune' => $w->orWhereBetween('a.date_naissance', [today()->subMonths(12)->addDay()->toDateString(), today()->subMonths(6)->toDateString()]),
                        'adulte' => $w->orWhere('a.date_naissance', '<=', today()->subMonths(12)->toDateString()),
                        'inconnu' => $w->orWhereNull('a.date_naissance')->orWhere('a.date_naissance', '<=', '0000-00-00'),
                        default => null,
                    };
                }
            });
        }
        if (! empty($f['repros'])) {
            $q->where(function ($w) use ($f, $today) {
                foreach ((array) $f['repros'] as $r) {
                    match ($r) {
                        'gestante' => $w->orWhere('g.prevu', '>', today()->addDays(30)->toDateString()),
                        'a_agneler' => $w->orWhereBetween('g.prevu', [$today, today()->addDays(30)->toDateString()]),
                        'retard' => $w->orWhere('g.prevu', '<', $today),
                        'reproducteur' => $w->orWhere('a.est_reproducteur', 1),
                        'non' => $w->orWhere(fn ($x) => $x->where('a.est_reproducteur', 0)->whereNull('g.prevu')),
                        default => null,
                    };
                }
            });
        }

        return $q;
    }

    public static function etatRepro(object $a): ?string
    {
        if (empty($a->prevu)) {
            return null;
        }
        $t = today()->toDateString();

        return $a->prevu < $t ? 'retard' : ($a->prevu <= today()->addDays(30)->toDateString() ? 'a_agneler' : 'gestante');
    }

    /** RG-OVA-002 : code utilisé comme numéro de boucle ; unicité contrôlée à la saisie. */
    public static function codeLibre(string $code, ?int $sauf = null): bool
    {
        return ! DB::table('OVINS_animaux')->where('code_animal', $code)->when($sauf, fn ($q) => $q->where('id_animal', '<>', $sauf))->exists();
    }

    public static function genererCode(string $prefixe): string
    {
        $codes = DB::table('OVINS_animaux')->where('code_animal', 'like', $prefixe.'%')->pluck('code_animal');
        $max = 0;
        foreach ($codes as $c) {
            if (preg_match('/^'.preg_quote($prefixe, '/').'(\d+)$/', $c, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $prefixe.str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    private static function identite(array $d, ?object $avant = null): array
    {
        $sexe = $d['sexe'] ?? '';
        if (! in_array($sexe, ['M', 'F'], true)) {
            throw new RegleMetier('Le sexe est obligatoire (M ou F).');
        }
        $code = trim((string) ($d['code_animal'] ?? ''));
        if (mb_strlen($code) > 100 || mb_strlen((string) ($d['nom_animal'] ?? '')) > 50) {
            throw new RegleMetier('Code (100) ou nom (50 caractères) trop long.');
        }
        foreach (['date_naissance', 'date_entree'] as $c) {
            if (! empty($d[$c]) && $d[$c] > today()->toDateString()) {
                throw new RegleMetier('Les dates de naissance et d’entrée ne peuvent pas être futures.');
            }
        }
        if (! empty($d['date_naissance']) && ! empty($d['date_entree']) && $d['date_entree'] < $d['date_naissance']) {
            throw new RegleMetier('L’entrée ne peut pas précéder la naissance.');
        }
        $id = $avant?->id_animal;
        foreach (['id_mere' => ['F', 'La mère doit être une femelle'], 'id_pere' => ['M', 'Le père doit être un mâle']] as $champ => [$s, $msg]) {
            if (! empty($d[$champ])) {
                $p = DB::table('OVINS_animaux')->where('id_animal', (int) $d[$champ])->first();
                if (! $p || $p->sexe !== $s || (int) $p->id_animal === (int) $id) {
                    throw new RegleMetier("$msg existant(e), distinct(e) de l’animal.");
                }
                if (! empty($d['date_naissance']) && $p->date_naissance && $p->date_naissance >= $d['date_naissance']) {
                    throw new RegleMetier('Un parent doit être né avant son descendant.');
                }
                if ($id && in_array((int) $p->id_animal, self::descendants((int) $id), true)) {
                    throw new RegleMetier('Un descendant ne peut pas être déclaré parent.');
                }
            }
        }
        if (! empty($d['id_proprietaire']) && ! DB::table('OVINS_proprietaires')->where('id_proprietaire', (int) $d['id_proprietaire'])->exists()) {
            throw new RegleMetier('Propriétaire introuvable.');
        }
        $montant = fn ($k) => isset($d[$k]) && $d[$k] !== '' ? Montant::versBase(Montant::saisie($d[$k], 'Le prix')) : null;
        $poids = function ($k, $max) use ($d) {
            if (! isset($d[$k]) || $d[$k] === '') {
                return null;
            }
            $v = (float) str_replace(',', '.', (string) $d[$k]);
            if ($v <= 0 || $v > $max) {
                throw new RegleMetier("Poids invalide (0 à $max kg).");
            }

            return round($v, 2);
        };

        return [
            'code_animal' => $code ?: null, 'nom_animal' => ($d['nom_animal'] ?? null) ?: null, 'sexe' => $sexe, 'race' => ($d['race'] ?? null) ?: null,
            'date_naissance' => ($d['date_naissance'] ?? null) ?: null, 'date_entree' => ($d['date_entree'] ?? null) ?: null, 'origine' => ($d['origine'] ?? null) ?: null,
            'id_proprietaire' => ($d['id_proprietaire'] ?? null) ?: null, 'id_mere' => ($d['id_mere'] ?? null) ?: null, 'id_pere' => ($d['id_pere'] ?? null) ?: null,
            'poids_naissance' => $poids('poids_naissance', 999), 'poids_dernier' => $poids('poids_dernier', 9999),
            'prix_achat' => $montant('prix_achat'), 'observations' => ($d['observations'] ?? null) ?: null, 'est_reproducteur' => empty($d['est_reproducteur']) ? 0 : 1,
        ];
    }

    /**
     * Nouvelle fiche. « compter » : l'animal arrive physiquement et augmente l'effectif de son lot (mouvement d'entrée) ;
     * sinon la fiche décrit un animal déjà compté dans le lot.
     */
    public static function creer(array $d, bool $compter = true): int
    {
        $v = self::identite($d);
        $v['code_animal'] ??= self::genererCode('OV'.today()->format('y').'-');
        if (! self::codeLibre($v['code_animal'])) {
            throw new RegleMetier("Le numéro de boucle « {$v['code_animal']} » est déjà utilisé.");
        }

        return DB::transaction(function () use ($v, $d, $compter) {
            $site = Referentiels::siteActif((int) ($d['id_site'] ?? 0));
            $lot = null;
            if (! empty($d['id_lot'])) {
                $lot = DB::table('OVINS_lots')->where('id_lot', (int) $d['id_lot'])->lockForUpdate()->first();
                if (! $lot || $lot->statut !== 'actif' || (int) $lot->id_site !== (int) $site->id_site) {
                    throw new RegleMetier('Le lot choisi doit être actif et appartenir au site choisi.');
                }
            }
            $v['date_entree'] ??= today()->toDateString();
            $v += ['id_site' => $site->id_site, 'id_lot' => $lot?->id_lot, 'statut' => 'en_ferme'];
            $id = DB::table('OVINS_animaux')->insertGetId($v);
            if ($lot && $compter) {
                DB::table('OVINS_lots')->where('id_lot', $lot->id_lot)->increment('effectif_encours');
                Mouvements::inscrire(['date_mvt' => $v['date_entree'], 'type_mvt' => 'entree', 'id_lot' => $lot->id_lot, 'id_animal' => $id, 'quantite' => 1,
                    'id_site_destination' => $site->id_site, 'commentaire' => trim('Entrée '.($v['origine'] ? '— '.$v['origine'] : ''))]);
            }
            Journal::enregistrer('ovin.creation', 'OVA', 'ovin', $id, apres: $v + ['compte_dans_lot' => $compter]);

            return $id;
        });
    }

    /** EC18 : la modification ne touche ni statut, ni site, ni lot. */
    public static function modifier(int $id, array $d, string $empreinte): void
    {
        DB::transaction(function () use ($id, $d, $empreinte) {
            $avant = DB::table('OVINS_animaux')->where('id_animal', $id)->lockForUpdate()->first() ?? throw new RegleMetier('Animal introuvable.');
            Empreinte::verifier($avant, $empreinte);
            $v = self::identite($d, $avant);
            $v['code_animal'] ??= $avant->code_animal;
            if ($v['code_animal'] && ! self::codeLibre($v['code_animal'], $id)) {
                throw new RegleMetier("Le numéro de boucle « {$v['code_animal']} » est déjà utilisé.");
            }
            if ($v['sexe'] !== $avant->sexe && (DB::table('OVINS_animaux')->where('id_mere', $id)->orWhere('id_pere', $id)->exists()
                || DB::table('OVINS_repro')->where('id_mere', $id)->orWhere('id_pere', $id)->exists())) {
                throw new RegleMetier('Le sexe ne peut plus être modifié : cet animal a une descendance ou un historique de reproduction.');
            }
            Lieux::executer(fn () => DB::table('OVINS_animaux')->where('id_animal', $id)->update($v));
            Journal::enregistrer('ovin.modification', 'OVA', 'ovin', $id, array_intersect_key((array) $avant, $v), $v);
        });
    }

    /** @return list<int> */
    public static function descendants(int $id, int $profondeur = 8): array
    {
        $out = [];
        $niveau = [$id];
        for ($i = 0; $i < $profondeur && $niveau; $i++) {
            $niveau = DB::table('OVINS_animaux')->whereIn('id_mere', $niveau)->orWhereIn('id_pere', $niveau)->pluck('id_animal')->map(fn ($x) => (int) $x)->diff($out)->values()->all();
            $out = array_merge($out, $niveau);
        }

        return $out;
    }

    public static function fiche(int $id): object
    {
        return DB::table('OVINS_animaux as a')
            ->leftJoin('OVINS_lots as l', 'l.id_lot', '=', 'a.id_lot')->leftJoin('OVINS_sites as s', 's.id_site', '=', 'a.id_site')
            ->leftJoin('OVINS_animaux as m', 'm.id_animal', '=', 'a.id_mere')->leftJoin('OVINS_animaux as p', 'p.id_animal', '=', 'a.id_pere')
            ->leftJoin('OVINS_proprietaires as pr', 'pr.id_proprietaire', '=', 'a.id_proprietaire')
            ->where('a.id_animal', $id)
            ->first(['a.*', 'l.nom_lot', 'l.type_lot', 's.nom_site', 'm.code_animal as code_mere', 'm.nom_animal as nom_mere',
                'p.code_animal as code_pere', 'p.nom_animal as nom_pere', 'pr.nom_proprietaire', 'pr.telephone as tel_proprietaire', 'pr.couleur_hex'])
            ?? throw new RegleMetier('Animal introuvable.');
    }

    /** RG-OVA-008 : arbre remontant mère et père sur trois générations, même si un parent est sorti. */
    public static function arbre(int $id, int $generations = 3): array
    {
        $noeud = function (?int $idA, int $niveau) use (&$noeud, $generations) {
            if (! $idA) {
                return null;
            }
            $a = DB::table('OVINS_animaux')->where('id_animal', $idA)->first(['id_animal', 'code_animal', 'nom_animal', 'sexe', 'race', 'date_naissance', 'statut', 'id_mere', 'id_pere']);
            if (! $a) {
                return null;
            }
            $a->photo = Medias::photo((int) $a->id_animal);
            $a->mere = $niveau < $generations ? $noeud($a->id_mere ? (int) $a->id_mere : null, $niveau + 1) : null;
            $a->pere = $niveau < $generations ? $noeud($a->id_pere ? (int) $a->id_pere : null, $niveau + 1) : null;

            return $a;
        };
        $racine = $noeud($id, 0) ?? throw new RegleMetier('Animal introuvable.');
        $enfants = DB::table('OVINS_animaux')->where('id_mere', $id)->orWhere('id_pere', $id)->orderBy('date_naissance')
            ->get(['id_animal', 'code_animal', 'nom_animal', 'sexe', 'date_naissance', 'statut']);

        return ['racine' => $racine, 'enfants' => $enfants];
    }

    /** RG-OVA-012 : chronologie reconstituée (restitution, pas une source). */
    public static function chronologie(int $id): array
    {
        $e = [];
        foreach (DB::table('OVINS_mouvements as m')->leftJoin('OVINS_lots as l', 'l.id_lot', '=', 'm.id_lot')->where('m.id_animal', $id)->get(['m.*', 'l.nom_lot']) as $m) {
            $e[] = ['date' => $m->date_mvt, 'type' => 'mouvement', 'titre' => Mouvements::libelle($m->type_mvt).($m->nom_lot ? ' · '.$m->nom_lot : ''), 'detail' => $m->commentaire, 'auteur' => $m->utilisateur];
        }
        foreach (DB::table('OVINS_repro')->where('id_mere', $id)->orWhere('id_pere', $id)->get() as $r) {
            if ($r->date_saillie) {
                $e[] = ['date' => $r->date_saillie, 'type' => 'repro', 'titre' => 'Saillie'.($r->type_saillie ? ' ('.$r->type_saillie.')' : ''), 'detail' => $r->date_agnelage_prevu ? 'Agnelage prévu le '.\App\Support\Format::date($r->date_agnelage_prevu) : null, 'auteur' => null];
            }
            if ($r->date_agnelage_reel) {
                $e[] = ['date' => $r->date_agnelage_reel, 'type' => 'repro', 'titre' => 'Agnelage', 'detail' => "{$r->nb_agneaux_vivants} vivant(s), {$r->nb_agneaux_morts} mort(s)", 'auteur' => null];
            }
        }
        foreach (DB::table('OVINS_soins_realises as r')->leftJoin('OVINS_soins_types as t', 't.id_type_soin', '=', 'r.id_type_soin')->where('r.id_animal', $id)->get(['r.*', 't.nom_soin']) as $s) {
            $e[] = ['date' => $s->date_soin, 'type' => 'soin', 'titre' => ($s->nom_soin ?: $s->produit ?: 'Soin').' · '.($s->statut === 'realise' ? 'réalisé' : $s->statut), 'detail' => $s->dose, 'auteur' => $s->utilisateur];
        }
        foreach (DB::table('OVINS_animaux_medias')->where('id_animal', $id)->get() as $m) {
            $e[] = ['date' => substr((string) $m->date_ajout, 0, 10), 'type' => 'media', 'titre' => ($m->type_media === 'image' ? 'Photo' : 'Vidéo').' · '.$m->stade_age, 'detail' => $m->description, 'auteur' => $m->utilisateur];
        }
        usort($e, fn ($a, $b) => strcmp((string) $b['date'], (string) $a['date']));

        return $e;
    }
}
