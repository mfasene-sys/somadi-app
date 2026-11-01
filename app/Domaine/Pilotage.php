<?php

namespace App\Domaine;

use App\Models\User;
use App\Support\Droits;
use Illuminate\Support\Facades\DB;

/**
 * Tableau de bord (PIL). Chaque indicateur est calculé séparément : une source en erreur rend
 * l'indicateur « indisponible » sans bloquer les autres (RG-PIL-009, REC-PIL-002). Aucune écriture.
 */
class Pilotage
{
    public static function indicateurs(User $user): array
    {
        $aujourdhui = today()->toDateString();
        $out = [];
        $mesure = function (string $cle, string $permission, callable $calcul) use (&$out, $user) {
            if (! Droits::autorise($user, $permission)) {
                return;
            }
            try {
                $out[$cle] = $calcul();
            } catch (\Throwable $e) {
                report($e);
                $out[$cle] = null;
            }
        };

        // RG-PIL-003 : soins du jour (planifiés aujourd'hui) et retards (planifiés avant aujourd'hui).
        $mesure('soins_jour', 'SAV.voir', fn () => DB::table('modSoinVacc_soins')->where('etat', 'planifie')->where('date_prevue', $aujourdhui)->count());
        $mesure('soins_retard', 'SAV.voir', fn () => DB::table('modSoinVacc_soins')->where('etat', 'planifie')->where('date_prevue', '<', $aujourdhui)->count());
        // RG-PIL-004 : nombre de saisies du jour (0 saisie ≠ 0 œuf) et ramassages à valider toutes dates.
        $mesure('ramassages_jour', 'PON.voir', fn () => DB::table('suivi_ponte')->where('date', $aujourdhui)->count());
        $mesure('ramassages_a_valider', 'PON.voir', fn () => DB::table('suivi_ponte')->whereRaw('LOWER(TRIM(statut)) = ?', ['attente'])
            ->whereNull('total_oeufs_valides')->whereNull('nb_oeufs_defectueux')->count());
        $mesure('oeufs_en_stock', 'OEU.voir', fn () => (int) DB::table('stock_oeufs_ramasses')->where('statut', 'attente')->where('total_oeufs', '>', 0)->sum('total_oeufs'));
        // RG-PIL-005 : file de chargement = lots À incuber au statut nouveau.
        $mesure('file_chargement', 'INC.voir', fn () => (int) DB::table('lots_oeufs_ramasses')->where('type_lot', 'incuber')->where('statut', 'nouveau')->sum('quantite'));
        // RG-PIL-006 : incubations en attente d'éclosion.
        $mesure('incubations_en_cours', 'INC.voir', fn () => DB::table('lots_incubation')->whereNull('date_eclosion')->whereRaw('COALESCE(nombre_eclos,0) = 0')->count());
        // EC16 : éclosions prévues dans les 3 prochains jours (affichage, pas d'envoi).
        $mesure('eclosions_proches', 'INC.voir', fn () => DB::table('lots_incubation')->whereNull('date_eclosion')->whereRaw('COALESCE(nombre_eclos,0) = 0')
            ->whereNotNull('date_eclosion_previsionnelle')->whereBetween('date_eclosion_previsionnelle', [$aujourdhui, today()->addDays(3)->toDateString()])
            ->orderBy('date_eclosion_previsionnelle')->get(['id', 'nom_lot', 'espece', 'incubateur', 'date_eclosion_previsionnelle', 'nombre_oeufs', 'nombre_fertiles', 'date_mirage']));
        $mesure('incubations_sans_prevision', 'INC.voir', fn () => DB::table('lots_incubation')->whereNull('date_eclosion')->whereRaw('COALESCE(nombre_eclos,0) = 0')->whereNull('date_eclosion_previsionnelle')->count());
        // RG-PIL-007 : effectifs, reproducteurs comptés séparément des lots volailles.
        $mesure('effectifs_volailles', 'VOL.voir', fn () => DB::table('modLotVol_lots_volailles')->where('statut', 'actif')
            ->groupBy('espece')->orderBy('espece')->select('espece', DB::raw('SUM(effectif_en_cours) total'), DB::raw('COUNT(*) lots'))->get());
        $mesure('reproducteurs', 'REP.voir', fn () => DB::table('bandes_parentales')->where(fn ($q) => $q->where('reformee', 0)->orWhereNull('reformee'))
            ->groupBy('espece')->select('espece', DB::raw('SUM(nb_males) males'), DB::raw('SUM(nb_femelles) femelles'))->get());
        $mesure('soins_a_venir', 'SAV.voir', fn () => DB::table('modSoinVacc_soins as s')->join('modLotVol_lots_volailles as l', 'l.id', '=', 's.id_lot')
            ->where('s.etat', 'planifie')->whereBetween('s.date_prevue', [today()->addDay()->toDateString(), today()->addDays(7)->toDateString()])
            ->orderBy('s.date_prevue')->limit(20)->get(['s.id', 's.date_prevue', 's.produit', 's.type', 'l.nom_lot', 'l.id as id_lot']));

        // Lot 4 : troupeau ovin, agnelages attendus et soins ovins.
        $mesure('ovins_en_ferme', 'OVA.voir', fn () => DB::table('OVINS_animaux')->where('statut', 'en_ferme')->whereNull('date_sortie')->count());
        $mesure('agnelages_proches', 'OVR.voir', fn () => DB::table('OVINS_repro as r')->join('OVINS_animaux as a', 'a.id_animal', '=', 'r.id_mere')->where('a.statut', 'en_ferme')
            ->whereNull('r.date_agnelage_reel')->whereNotNull('r.date_agnelage_prevu')->where('r.date_agnelage_prevu', '<=', today()->addDays(30)->toDateString())->count());
        $mesure('soins_ovins_retard', 'OVS.voir', fn () => DB::table('OVINS_soins_realises')->where('statut', 'planifie')->where('date_soin', '<', $aujourdhui)->count());
        $mesure('soins_ovins_jour', 'OVS.voir', fn () => DB::table('OVINS_soins_realises')->where('statut', 'planifie')->where('date_soin', $aujourdhui)->count());

        // Lot 5 : tâches du jour de l'utilisateur, absences à valider, alertes de planning.
        $mesure('mes_taches_jour', 'ACT.voir', function () {
            $u = \Illuminate\Support\Facades\Auth::user();
            $equipes = DB::table('modAct_equipe_membres')->where('id_user', $u->id)->pluck('id_equipe')->all();

            return DB::table('modAct_taches as t')->whereIn('t.statut', ['planifie', 'en_cours', 'reporte'])->where('t.date_debut_prevue', '<', today()->addDay())
                ->whereExists(fn ($e) => $e->select(DB::raw(1))->from('modAct_tache_affectations as a')->whereColumn('a.id_tache', 't.id')->where('a.role_affectation', '<>', 'observateur')
                    ->where(fn ($w) => $w->where(fn ($x) => $x->where('a.type_cible', 'user')->where('a.id_user', $u->id))->orWhere(fn ($x) => $x->where('a.type_cible', 'equipe')->whereIn('a.id_equipe', $equipes ?: [0]))))
                ->count();
        });
        $mesure('absences_a_valider', 'RH.gerer', fn () => DB::table('rh_absences')->where('statut', 'soumise')->count());
        $mesure('alertes_planning', 'IFP.voir', fn () => count(\App\Domaine\Organisation\Planning::alertes()));

        // RG-PIL-008 : ventes du mois sur date_vente, recettes du mois sur date_op valides, reste global des soldes stockés.
        $debutMois = today()->startOfMonth()->toDateString();
        $mesure('ventes_mois', 'VEN.voir', fn () => \App\Support\Montant::base(DB::table('modVente_Ventes')->whereBetween('date_vente', [$debutMois, $aujourdhui])->sum('montant_total')));
        $mesure('recettes_mois', 'CPT.voir', fn () => \App\Support\Montant::base(DB::table('modCompta_operations')->where('type_op', 'recette')->where('est_valide', 1)->whereBetween('date_op', [$debutMois, $aujourdhui])->sum('montant')));
        $mesure('reste_a_encaisser', 'VEN.voir', fn () => \App\Support\Montant::base(DB::table('modVente_Ventes')->sum('montant_reste_encaisser')));
        $mesure('ventes_a_completer', 'VEN.voir', fn () => DB::table('modVente_Ventes_A_Completer')->whereRaw("LOWER(TRIM(statut)) = 'attente'")->count());

        return $out;
    }
}
