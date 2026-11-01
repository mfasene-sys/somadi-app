<?php

namespace App\Domaine\Ovins;

use App\Exceptions\RegleMetier;
use App\Support\Format;
use App\Support\Journal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Saillies, agnelages, agnelles prêtes et calendrier de reproduction par lot (OVR). */
class Reproduction
{
    public const FENETRES = ['post_mise_bas' => 'Après mise bas (J+7 à J+21)', 'pre_lutte' => 'Avant la lutte suivante (J−21 à J0)'];

    private static function date(?string $v, string $lib): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        try {
            $d = Carbon::createFromFormat('!Y-m-d', $v);
        } catch (\Throwable) {
            $d = null;
        }
        if (! $d || $d->format('Y-m-d') !== $v) {
            throw new RegleMetier("Date de $lib invalide.");
        }

        return $v;
    }

    private static function entier(array $d, string $k): int
    {
        $v = $d[$k] ?? '';
        if ($v === '' || $v === null) {
            return 0;
        }
        if (! ctype_digit((string) $v) || (int) $v > 100) {
            throw new RegleMetier('Les nombres d’agneaux doivent être des entiers de 0 à 100.');
        }

        return (int) $v;
    }

    public static function naissancesLiees(int $idRepro): int
    {
        return DB::table('OVINS_mouvements')->where('type_mvt', 'naissance')->where('source_table', 'OVINS_repro')->where('source_id', $idRepro)->count();
    }

    /**
     * RG-OVR-001 à 006 : création ou mise à jour d'une reproduction ; à l'agnelage réel, création des agneaux vivants
     * (fiches, filiation, mouvements de naissance, effectif du lot d'accueil), une seule fois.
     *
     * @return array{id:int, agneaux:list<int>}
     */
    public static function enregistrer(?int $idRepro, array $d): array
    {
        $idMere = (int) ($d['id_mere'] ?? 0);
        $idPere = ($d['id_pere'] ?? '') !== '' ? (int) $d['id_pere'] : null;
        $saillie = self::date($d['date_saillie'] ?? null, 'saillie');
        $prevu = self::date($d['date_agnelage_prevu'] ?? null, 'agnelage prévu');
        $reel = self::date($d['date_agnelage_reel'] ?? null, 'agnelage réel');
        [$vivants, $morts, $males, $femelles] = [self::entier($d, 'nb_agneaux_vivants'), self::entier($d, 'nb_agneaux_morts'), self::entier($d, 'nb_agneaux_males'), self::entier($d, 'nb_agneaux_femelles')];
        $t = today()->toDateString();
        if (($saillie && $saillie > $t) || ($reel && $reel > $t)) {
            throw new RegleMetier('Une saillie ou un agnelage réel ne peut pas être futur.');
        }
        if ($reel && $saillie && $reel < $saillie) {
            throw new RegleMetier('L’agnelage ne peut pas précéder la saillie.');
        }
        if (! $reel && ($vivants || $morts || $males || $femelles)) {
            throw new RegleMetier('Renseignez la date réelle de l’agnelage avant les naissances.');
        }
        if ($reel && $vivants !== $males + $femelles) {
            throw new RegleMetier('Le total mâles + femelles doit correspondre aux agneaux vivants.');
        }
        if (! $prevu && $saillie) {
            $prevu = Carbon::parse($saillie)->addDays((int) config('somadi.ovins.calendrier.gestation'))->toDateString();
        }

        return DB::transaction(function () use ($idRepro, $d, $idMere, $idPere, $saillie, $prevu, $reel, $vivants, $morts, $males, $femelles) {
            $mere = DB::table('OVINS_animaux')->where('id_animal', $idMere)->lockForUpdate()->first();
            if (! $mere || $mere->sexe !== 'F') {
                throw new RegleMetier('La mère doit être une femelle existante.');
            }
            if ($idPere) {
                $pere = DB::table('OVINS_animaux')->where('id_animal', $idPere)->first();
                if (! $pere || $pere->sexe !== 'M' || $idPere === $idMere) {
                    throw new RegleMetier('Le père doit être un mâle distinct de la mère.');
                }
            }
            $v = [
                'id_mere' => $idMere, 'id_pere' => $idPere, 'date_saillie' => $saillie, 'type_saillie' => ($d['type_saillie'] ?? null) ?: null,
                'date_agnelage_prevu' => $prevu, 'date_agnelage_reel' => $reel, 'nb_agneaux_vivants' => $vivants, 'nb_agneaux_males' => $reel ? $males : null,
                'nb_agneaux_femelles' => $reel ? $femelles : null, 'nb_agneaux_morts' => $morts, 'difficulte_agnelage' => empty($d['difficulte_agnelage']) ? 0 : 1,
                'commentaire' => ($d['commentaire'] ?? null) ?: null,
            ];
            $avant = null;
            if ($idRepro) {
                $avant = DB::table('OVINS_repro')->where('id_repro', $idRepro)->lockForUpdate()->first() ?? throw new RegleMetier('Reproduction introuvable.');
                if ((int) $avant->id_mere !== $idMere) {
                    throw new RegleMetier('La mère de cet enregistrement ne peut pas être changée.');
                }
                if (self::naissancesLiees($idRepro) > 0) {
                    foreach (['id_pere', 'date_agnelage_reel', 'nb_agneaux_vivants', 'nb_agneaux_morts', 'nb_agneaux_males', 'nb_agneaux_femelles'] as $k) {
                        if ((string) $avant->$k !== (string) $v[$k]) {
                            throw new RegleMetier('Des naissances sont déjà liées : les parents, la date et les effectifs ne peuvent plus être modifiés ici.');
                        }
                    }
                }
                DB::table('OVINS_repro')->where('id_repro', $idRepro)->update($v);
            } else {
                if ($mere->statut !== 'en_ferme') {
                    throw new RegleMetier('La mère n’est plus en ferme.');
                }
                $idRepro = DB::table('OVINS_repro')->insertGetId($v);
            }
            $agneaux = [];
            if ($reel && $vivants > 0 && self::naissancesLiees($idRepro) === 0) {
                $agneaux = self::creerAgneaux($idRepro, $mere, $idPere, $reel, $males, $femelles, ($d['id_lot_agneaux'] ?? '') !== '' ? (int) $d['id_lot_agneaux'] : null);
            }
            Journal::enregistrer($avant ? 'repro.modification' : 'repro.creation', 'OVR', 'repro', $idRepro, $avant ? (array) $avant : null, $v + ['agneaux' => $agneaux]);

            return ['id' => $idRepro, 'agneaux' => $agneaux];
        });
    }

    /** @return list<int> */
    private static function creerAgneaux(int $idRepro, object $mere, ?int $idPere, string $date, int $males, int $femelles, ?int $idLot): array
    {
        if ($mere->statut !== 'en_ferme') {
            throw new RegleMetier('La mère n’est plus en ferme : les agneaux ne peuvent pas être créés automatiquement.');
        }
        $idLot ??= $mere->id_lot ? (int) $mere->id_lot : null;
        $lot = null;
        if ($idLot) {
            $lot = DB::table('OVINS_lots')->where('id_lot', $idLot)->lockForUpdate()->first();
            if (! $lot || $lot->statut !== 'actif' || (int) $lot->id_site !== (int) $mere->id_site) {
                throw new RegleMetier('Le lot d’accueil des agneaux doit être actif et sur le site de la mère.');
            }
        }
        $prefixe = 'AG'.Carbon::parse($date)->format('y').'-';
        $ids = [];
        foreach (array_merge(array_fill(0, $males, 'M'), array_fill(0, $femelles, 'F')) as $sexe) {
            $code = Animaux::genererCode($prefixe);
            $id = DB::table('OVINS_animaux')->insertGetId([
                'code_animal' => $code, 'sexe' => $sexe, 'race' => $mere->race, 'date_naissance' => $date, 'date_entree' => $date, 'origine' => 'Naissance',
                'id_lot' => $lot?->id_lot, 'id_site' => $mere->id_site, 'id_proprietaire' => $mere->id_proprietaire, 'id_mere' => $mere->id_animal, 'id_pere' => $idPere,
                'statut' => 'en_ferme', 'est_reproducteur' => 0,
            ]);
            Mouvements::inscrire(['date_mvt' => $date, 'type_mvt' => 'naissance', 'id_lot' => $lot?->id_lot, 'id_animal' => $id, 'quantite' => 1,
                'id_site_destination' => $mere->id_site, 'source_table' => 'OVINS_repro', 'source_id' => $idRepro,
                'commentaire' => 'Naissance, mère '.($mere->code_animal ?: '#'.$mere->id_animal)]);
            $ids[] = $id;
        }
        if ($lot && $ids) {
            DB::table('OVINS_lots')->where('id_lot', $lot->id_lot)->increment('effectif_encours', count($ids));
        }

        return $ids;
    }

    public static function historique(int $idMere)
    {
        return DB::table('OVINS_repro as r')->leftJoin('OVINS_animaux as p', 'p.id_animal', '=', 'r.id_pere')
            ->where('r.id_mere', $idMere)->orderByDesc(DB::raw('COALESCE(r.date_saillie, r.date_agnelage_reel)'))->orderByDesc('r.id_repro')
            ->get(['r.*', 'p.code_animal as code_pere', 'p.nom_animal as nom_pere'])
            ->each(fn ($r) => $r->naissances = self::naissancesLiees((int) $r->id_repro));
    }

    /** RG-OVR-012 : agnelages prévus sous 30 jours et prévisions dépassées sans date réelle. */
    public static function alertes()
    {
        return DB::table('OVINS_repro as r')->join('OVINS_animaux as a', 'a.id_animal', '=', 'r.id_mere')
            ->leftJoin('OVINS_sites as s', 's.id_site', '=', 'a.id_site')
            ->whereNull('r.date_agnelage_reel')->whereNotNull('r.date_agnelage_prevu')->where('a.statut', 'en_ferme')
            ->where('r.date_agnelage_prevu', '<=', today()->addDays(30)->toDateString())
            ->orderBy('r.date_agnelage_prevu')->get(['r.*', 'a.code_animal', 'a.nom_animal', 's.nom_site']);
    }

    /** RG-OVR-007 : femelles en ferme d'un lot « agnelles », nées depuis au moins 270 jours. */
    public static function agnellesPretes()
    {
        return DB::table('OVINS_animaux as a')->join('OVINS_lots as l', 'l.id_lot', '=', 'a.id_lot')->leftJoin('OVINS_sites as s', 's.id_site', '=', 'a.id_site')
            ->where('a.sexe', 'F')->where('a.statut', 'en_ferme')->whereNull('a.date_sortie')->where('l.type_lot', 'agnelles')
            ->whereNotNull('a.date_naissance')->where('a.date_naissance', '>', '0000-00-00')
            ->where('a.date_naissance', '<=', today()->subDays((int) config('somadi.ovins.age_agnelle_jours'))->toDateString())
            ->orderBy('a.date_naissance')->get(['a.*', 'l.nom_lot', 's.nom_site']);
    }

    // ----- Calendrier par lot (RG-OVR-008 à 011)

    /** @return list<array<string,mixed>> */
    public static function calculerCycles(string $premiereLutte, array $p): array
    {
        $nb = intdiv((int) $p['horizon_ans'] * 12, max(1, (int) $p['cycle_mois']));
        $out = [];
        $lutte = Carbon::parse($premiereLutte);
        for ($i = 1; $i <= $nb; $i++) {
            $agnelage = $lutte->copy()->addDays((int) $p['gestation']);
            $finAllait = $agnelage->copy()->addDays((int) $p['allaitement']);
            $prochaine = $lutte->copy()->addMonthsNoOverflow((int) $p['cycle_mois']);
            $out[] = [
                'num_cycle' => $i, 'date_lutte' => $lutte->toDateString(), 'date_agnelage_prevu' => $agnelage->toDateString(),
                'date_fin_allaitement' => $finAllait->toDateString(), 'date_prepa' => $finAllait->copy()->addDays((int) $p['preparation'])->toDateString(),
                'date_prochaine_lutte' => $prochaine->toDateString(),
                'post_mb_deb' => $agnelage->copy()->addDays(7)->toDateString(), 'post_mb_fin' => $agnelage->copy()->addDays(21)->toDateString(),
                'pre_lutte_deb' => $prochaine->copy()->subDays(21)->toDateString(), 'pre_lutte_fin' => $prochaine->toDateString(),
                'duree_gestation_jours' => (int) $p['gestation'], 'duree_allaitement_jours' => (int) $p['allaitement'], 'duree_prepa_jours' => (int) $p['preparation'],
                'duree_cycle_mois' => (int) $p['cycle_mois'], 'nb_ans_horizon' => (int) $p['horizon_ans'],
            ];
            $lutte = $prochaine;
        }

        return $out;
    }

    /** Génère (ou régénère) le calendrier théorique d'un lot ; refus si des actions ont déjà été suivies. Aucune naissance n'est créée. */
    public static function genererCalendrier(int $idLot, string $premiereLutte, array $p): int
    {
        foreach (['gestation' => [100, 200], 'allaitement' => [0, 365], 'preparation' => [0, 365], 'cycle_mois' => [6, 24], 'horizon_ans' => [1, 10]] as $k => [$min, $max]) {
            if (! isset($p[$k]) || (int) $p[$k] < $min || (int) $p[$k] > $max) {
                throw new RegleMetier("Paramètre « $k » hors limites ($min à $max).");
            }
        }
        self::date($premiereLutte, 'première lutte');

        return DB::transaction(function () use ($idLot, $premiereLutte, $p) {
            $lot = DB::table('OVINS_lots')->where('id_lot', $idLot)->lockForUpdate()->first() ?? throw new RegleMetier('Lot introuvable.');
            $suivies = DB::table('OVINS_lot_soins_actions')->where('id_lot', $idLot)->where('statut', '<>', 'planifie')->count()
                + DB::table('OVINS_soins_realises as r')->join('OVINS_lot_soins_actions as a', 'a.id_action', '=', 'r.id_action')->where('a.id_lot', $idLot)->count();
            if ($suivies > 0) {
                throw new RegleMetier('Des actions de ce calendrier ont déjà été suivies ou ont donné lieu à des soins : il ne peut plus être régénéré.');
            }
            $plans = DB::table('OVINS_lot_soins_planifies')->where('id_lot', $idLot)->pluck('id_soin_plan');
            DB::table('OVINS_lot_soins_actions')->whereIn('id_soin_plan', $plans)->delete();
            DB::table('OVINS_lot_soins_planifies')->where('id_lot', $idLot)->delete();
            $anciens = DB::table('OVINS_lot_cycles')->where('id_lot', $idLot)->count();
            DB::table('OVINS_lot_cycles')->where('id_lot', $idLot)->delete();
            $u = mb_substr(Format::auteur(), 0, 100);
            $cycles = self::calculerCycles($premiereLutte, $p);
            foreach ($cycles as $c) {
                $idc = DB::table('OVINS_lot_cycles')->insertGetId($c + ['id_lot' => $idLot, 'date_generation' => now(), 'utilisateur' => $u]);
                foreach (['post_mise_bas' => ['post_mb_deb', 'post_mb_fin', 'Soins après mise bas'], 'pre_lutte' => ['pre_lutte_deb', 'pre_lutte_fin', 'Préparation à la lutte (flushing, déparasitage)']] as $type => [$deb, $fin, $lib]) {
                    DB::table('OVINS_lot_soins_planifies')->insert(['id_cycle' => $idc, 'id_lot' => $idLot, 'num_cycle' => $c['num_cycle'], 'type_fenetre' => $type,
                        'date_debut' => $c[$deb], 'date_fin' => $c[$fin], 'libelle_prophylaxie' => $lib, 'statut' => 'planifie', 'date_generation' => now(), 'utilisateur' => $u]);
                }
            }
            Journal::enregistrer('calendrier_ovin.generation', 'OVR', 'lot_ovin', $idLot, ['cycles' => $anciens], ['cycles' => count($cycles), 'premiere_lutte' => $premiereLutte, 'parametres' => $p]);

            return count($cycles);
        });
    }

    public static function calendrier(int $idLot): array
    {
        $cycles = DB::table('OVINS_lot_cycles')->where('id_lot', $idLot)->orderBy('num_cycle')->get();
        $fenetres = DB::table('OVINS_lot_soins_planifies')->where('id_lot', $idLot)->orderBy('date_debut')->get()->groupBy('id_cycle');
        $actions = DB::table('OVINS_lot_soins_actions')->where('id_lot', $idLot)->orderBy('date_soin')->get()->groupBy('id_soin_plan');

        return compact('cycles', 'fenetres', 'actions');
    }

    /** RG-OVS-007 : action dans une fenêtre ; avec un soin du catalogue, elle crée le soin collectif planifié (traçé par id_action). */
    public static function ajouterAction(int $idPlan, array $d): int
    {
        return DB::transaction(function () use ($idPlan, $d) {
            $f = DB::table('OVINS_lot_soins_planifies')->where('id_soin_plan', $idPlan)->first() ?? throw new RegleMetier('Fenêtre introuvable.');
            $date = self::date($d['date_soin'] ?? null, 'soin') ?? throw new RegleMetier('Indiquez la date de l’action.');
            if ($date < $f->date_debut || $date > $f->date_fin) {
                throw new RegleMetier('La date doit être comprise dans la fenêtre ('.Format::date($f->date_debut).' – '.Format::date($f->date_fin).').');
            }
            $type = ($d['id_type_soin'] ?? '') !== '' ? DB::table('OVINS_soins_types')->where('id_type_soin', (int) $d['id_type_soin'])->first() : null;
            $libelle = trim((string) ($d['libelle_action'] ?? '')) ?: ($type?->nom_soin ?? '');
            if ($libelle === '') {
                throw new RegleMetier('Indiquez l’action ou choisissez un soin.');
            }
            $u = mb_substr(Format::auteur(), 0, 100);
            $id = DB::table('OVINS_lot_soins_actions')->insertGetId(['id_soin_plan' => $idPlan, 'id_lot' => $f->id_lot, 'num_cycle' => $f->num_cycle, 'type_fenetre' => $f->type_fenetre,
                'date_soin' => $date, 'libelle_action' => mb_substr($libelle, 0, 255), 'commentaire' => ($d['commentaire'] ?? null) ?: null, 'statut' => 'planifie', 'date_generation' => now(), 'utilisateur' => $u]);
            if ($type) {
                $lot = DB::table('OVINS_lots')->where('id_lot', $f->id_lot)->first();
                DB::table('OVINS_soins_realises')->insert(['cible' => 'lot', 'id_lot' => $f->id_lot, 'id_site' => $lot->id_site, 'id_action' => $id, 'id_type_soin' => $type->id_type_soin,
                    'produit' => mb_substr($type->nom_soin, 0, 30), 'dose' => mb_substr((string) ($d['dose'] ?? ''), 0, 30) ?: null, 'statut' => 'planifie', 'date_soin' => $date,
                    'type_soin' => mb_substr((string) $type->categorie, 0, 20) ?: null, 'commentaire' => 'Cycle '.$f->num_cycle.' · '.(self::FENETRES[$f->type_fenetre] ?? $f->type_fenetre), 'utilisateur' => $u]);
            }
            Journal::enregistrer('calendrier_ovin.action', 'OVR', 'lot_ovin', $f->id_lot, null, ['action' => $id, 'libelle' => $libelle, 'date' => $date, 'soin' => $type?->id_type_soin]);

            return $id;
        });
    }
}
