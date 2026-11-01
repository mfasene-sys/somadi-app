<?php

namespace App\Domaine;

use App\Exceptions\RegleMetier;
use App\Support\Format;
use App\Support\Journal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Pesées et rentabilité des volailles (POI). */
class Pesees
{
    public const HYPOTHESES = [0, 3, 7, 14, 30, 60];

    public const SEUIL_MORTALITE = 3.0;

    /** RG-POI-001, 002, 013 */
    public static function ajouter(int $idLot, string $date, string $poids): int
    {
        $p = str_replace([' ', "\u{202F}", ','], ['', '', '.'], trim($poids));
        if (! preg_match('/^\d{1,5}(\.\d{1,2})?$/', $p) || (float) $p <= 0) {
            throw new RegleMetier('Poids moyen invalide : grammes, strictement positif, deux décimales au plus, 99 999,99 g maximum.');
        }

        return DB::transaction(function () use ($idLot, $date, $p) {
            LotsVolailles::verrouiller($idLot);
            $id = DB::table('modLotVol_suivi_poids')->insertGetId([
                'id_lot' => $idLot, 'date_suivi' => Carbon::parse($date)->toDateString(), 'poids_moyen' => $p, 'utilisateur' => Format::auteur(),
            ]);
            Journal::enregistrer('pesee.ajout', 'POI', 'lot', $idLot, apres: ['date' => $date, 'poids_g' => $p]);

            return $id;
        });
    }

    /** @return Collection<int, object> série chronologique (date puis id) */
    public static function serie(int $idLot): Collection
    {
        return DB::table('modLotVol_suivi_poids')->where('id_lot', $idLot)->orderBy('date_suivi')->orderBy('id')->get();
    }

    /** RG-POI-003 : gain moyen quotidien entre le dernier point et le précédent de date strictement antérieure. */
    public static function gain(Collection $serie): ?array
    {
        $dernier = $serie->last();
        if (! $dernier) {
            return null;
        }
        $precedent = $serie->filter(fn ($p) => $p->date_suivi < $dernier->date_suivi)->last();
        if (! $precedent) {
            return null;
        }
        $jours = Carbon::parse($precedent->date_suivi)->diffInDays(Carbon::parse($dernier->date_suivi));

        return ['g_jour' => ((float) $dernier->poids_moyen - (float) $precedent->poids_moyen) / $jours, 'jours' => (int) $jours, 'de' => $precedent, 'a' => $dernier];
    }

    /** Références de coût actives d'une espèce, indexées par âge. */
    public static function references(?string $espece): Collection
    {
        return DB::table('modLot_Vol_ref_cout_age_poulet')->whereRaw('LOWER(espece) = ?', [mb_strtolower((string) $espece)])
            ->where('actif', 1)->orderBy('age_jours')->get()->keyBy('age_jours');
    }

    /**
     * RG-POI-005 à 011 et EC08/EC09 : rentabilité d'un lot par coût de référence d'âge.
     */
    public static function rentabilite(object $lot, int $hypothese = 0): array
    {
        $refs = self::references($lot->espece);
        $manquantes = [];
        $coutA = function (?int $age) use ($refs, &$manquantes) {
            if ($age === null || ! $refs->has($age)) {
                $manquantes[] = $age;

                return null;
            }

            return (float) $refs[$age]->cout_cumule_par_tete;
        };

        $ventes = DB::table('modVente_Ventes')->where('id_lot', $lot->id)->orderBy('date_vente')->get();
        $recettesVendues = 0.0;
        $coutsVendus = 0.0;
        $vendus = 0;
        foreach ($ventes as $v) {
            $recettesVendues += (int) $v->quantite * (float) $v->prix_unitaire;
            $vendus += (int) $v->quantite;
            $c = $coutA(LotsVolailles::age($lot, $v->date_vente));
            $coutsVendus += $c === null ? 0 : $c * (int) $v->quantite;
        }
        $morts = DB::table('modLotVol_mouvements')->where('id_lot', $lot->id)->where('type_mouvement', 'mortalité')->get();
        $nbMorts = (int) $morts->sum('quantite');
        $coutsMorts = 0.0;
        foreach ($morts as $m) {
            $c = $coutA(LotsVolailles::age($lot, $m->date_mouvement));
            $coutsMorts += $c === null ? 0 : $c * (int) $m->quantite;
        }
        $idsVentes = $ventes->pluck('id')->all();
        $encaisse = (float) DB::table('modCompta_operations')->where('type_op', 'recette')->where('est_valide', 1)
            ->where(fn ($q) => $q->where('id_lot', $lot->id)->orWhere(fn ($w) => $w->where('source_table', 'modVente_Ventes')->whereIn('source_id', $idsVentes ?: [0])))
            ->sum('montant');

        $restant = max(0, (int) $lot->effectif_en_cours);
        $ageCible = (LotsVolailles::age($lot) ?? 0) + $hypothese;
        $refCible = $refs->filter(fn ($r, $age) => $age <= $ageCible)->last();
        $profitRealise = $recettesVendues - $coutsVendus - $coutsMorts;
        $complet = $manquantes === [] && $refCible !== null;

        return [
            'ventes_facturees' => $recettesVendues,
            'encaisse' => $encaisse,
            'vendus' => $vendus,
            'morts' => $nbMorts,
            'restant' => $restant,
            'couts_vendus' => $coutsVendus,
            'couts_morts' => $coutsMorts,
            'profit_realise' => $profitRealise,
            'age_cible' => $ageCible,
            'ref_cible' => $refCible,
            'profit_projete' => $refCible ? $profitRealise + $restant * ((float) $refCible->prix_min_profitable - (float) $refCible->cout_cumule_par_tete) : null,
            'prix_requis' => $refCible ? ($coutsVendus + $coutsMorts + $restant * (float) $refCible->cout_cumule_par_tete - $recettesVendues) / max(1, $restant) : null,
            'mortalite_pct' => (int) $lot->nombre_initial > 0 ? 100 * $nbMorts / (int) $lot->nombre_initial : null,
            'ecart' => (int) $lot->nombre_initial - $vendus - $nbMorts - $restant,
            'ages_sans_reference' => collect($manquantes)->filter(fn ($a) => $a !== null)->unique()->sort()->values()->all(),
            'complet' => $complet,
        ];
    }

    public static function enregistrerReference(array $d): void
    {
        DB::transaction(function () use ($d) {
            $cle = ['espece' => mb_strtolower(trim($d['espece'])), 'age_jours' => (int) $d['age_jours']];
            $avant = DB::table('modLot_Vol_ref_cout_age_poulet')->where($cle)->lockForUpdate()->first();
            $valeurs = ['cout_cumule_par_tete' => $d['cout_cumule_par_tete'], 'marge_pct' => $d['marge_pct'], 'actif' => ! empty($d['actif']) ? 1 : 0, 'commentaire' => $d['commentaire'] ?? null];
            DB::table('modLot_Vol_ref_cout_age_poulet')->updateOrInsert($cle, $valeurs);
            Journal::enregistrer('reference_cout.enregistrement', 'POI', 'reference_cout', $cle['espece'].'@'.$cle['age_jours'], $avant ? (array) $avant : null, $valeurs);
        });
    }
}
