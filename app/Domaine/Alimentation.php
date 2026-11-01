<?php

namespace App\Domaine;

use App\Exceptions\RegleMetier;
use App\Support\Format;
use App\Support\Journal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Alimentation (ALI). Unités : entrées de stock en kg (quantite_kg), distributions en grammes (quantite_totale).
 * EC11 : seul le générateur fondé sur les plans hebdomadaires est conservé.
 * EC12 : les quantités d'un même aliment s'additionnent par lot et par date ; une correction est une écriture tracée.
 */
class Alimentation
{
    /** RG-ALI-004 */
    public static function semaine(int $ageJours): int
    {
        return max(1, intdiv(max(0, $ageJours), 7) + 1);
    }

    /**
     * Besoins d'un lot pour une date, cumulés par aliment, à partir de son plan (RG-ALI-004, 006, 007).
     *
     * @return array{semaine:?int, besoins:array<int, array{grammes:float, detail:list<array>}>, raison:?string}
     */
    public static function besoins(object $lot, string $date): array
    {
        if (! $lot->date_entree || ! $lot->id_plan || (int) $lot->effectif_en_cours <= 0) {
            return ['semaine' => null, 'besoins' => [], 'raison' => ! $lot->id_plan ? 'aucun plan' : (! $lot->date_entree ? 'date d’entrée absente' : 'effectif nul')];
        }
        $age = (int) Carbon::parse($lot->date_entree)->diffInDays(Carbon::parse($date), false);
        if ($age < 0) {
            return ['semaine' => null, 'besoins' => [], 'raison' => 'lot pas encore entré'];
        }
        $voulue = self::semaine($age);
        $lignes = DB::table('modAlim_plan_lignes')->where('id_plan', $lot->id_plan)->get();
        $semaines = $lignes->pluck('semaine')->map(fn ($s) => (int) $s)->unique()->sort()->values();
        $retenue = $semaines->filter(fn ($s) => $s <= $voulue)->last();
        if ($retenue === null) {
            return ['semaine' => $voulue, 'besoins' => [], 'raison' => 'aucune ligne de plan pour cette semaine'];
        }
        $effectif = (int) $lot->effectif_en_cours;
        $besoins = [];
        $ajouter = function (int $aliment, float $g, array $detail) use (&$besoins) {
            $besoins[$aliment]['grammes'] = round(($besoins[$aliment]['grammes'] ?? 0) + $g, 3);
            $besoins[$aliment]['detail'][] = $detail;
        };
        foreach ($lignes->where('semaine', $retenue) as $l) {
            $ration = (float) $l->qte_par_tete;
            if ($ration <= 0) {
                continue;
            }
            if ($l->is_transition) {
                $ps = (float) $l->pct_source;
                $pc = (float) $l->pct_cible;
                if (! $l->id_aliment_source || ! $l->id_aliment_cible || round($ps + $pc) != 100) {
                    continue;
                }
                $total = $ration * $effectif;
                $ajouter((int) $l->id_aliment_source, round($total * $ps / 100, 3), ['semaine' => $retenue, 'ration_g' => $ration, 'effectif' => $effectif, 'transition' => "source $ps %"]);
                $ajouter((int) $l->id_aliment_cible, round($total * $pc / 100, 3), ['semaine' => $retenue, 'ration_g' => $ration, 'effectif' => $effectif, 'transition' => "cible $pc %"]);
            } else {
                $ajouter((int) $l->id_aliment, round($ration * $effectif, 3), ['semaine' => $retenue, 'ration_g' => $ration, 'effectif' => $effectif]);
            }
        }

        return ['semaine' => $retenue, 'besoins' => $besoins, 'raison' => $besoins ? null : 'plan sans ration exploitable'];
    }

    /**
     * Génère les distributions du jour. Une distribution déjà présente pour lot–date–aliment n'est jamais
     * remplacée ni cumulée par une relance : elle est signalée « déjà enregistrée » (REC-ALI-003).
     *
     * @return array{creees:int, deja:int, ignores:list<string>}
     */
    public static function generer(string $date, ?array $idsLots = null): array
    {
        if (Carbon::parse($date)->isAfter(today())) {
            throw new RegleMetier('La génération ne peut pas porter sur une date future.');
        }

        return DB::transaction(function () use ($date, $idsLots) {
            $lots = DB::table('modLotVol_lots_volailles')->where('statut', 'actif')
                ->when($idsLots, fn ($q) => $q->whereIn('id', $idsLots))->orderBy('id')->lockForUpdate()->get();
            $res = ['creees' => 0, 'deja' => 0, 'ignores' => []];
            foreach ($lots as $lot) {
                $b = self::besoins($lot, $date);
                if (! $b['besoins']) {
                    $res['ignores'][] = "{$lot->nom_lot} : {$b['raison']}";

                    continue;
                }
                foreach ($b['besoins'] as $aliment => $besoin) {
                    $existe = DB::table('modAlim_distributions')->where(['id_lot' => $lot->id, 'date_distribution' => $date, 'id_type_aliment' => $aliment])->lockForUpdate()->exists();
                    if ($existe) {
                        $res['deja']++;

                        continue;
                    }
                    DB::table('modAlim_distributions')->insert([
                        'id_lot' => $lot->id, 'date_distribution' => $date, 'id_type_aliment' => $aliment, 'quantite_totale' => $besoin['grammes'],
                        'remarque' => 'Généré : '.json_encode($besoin['detail'], JSON_UNESCAPED_UNICODE), 'utilisateur' => Format::auteur(),
                    ]);
                    $res['creees']++;
                }
            }
            Journal::enregistrer('alimentation.generation', 'ALI', 'distribution', $date, apres: $res);

            return $res;
        });
    }

    /** RG-ALI-001, 002 et EC12 : saisie manuelle en kg, ajoutée à la ligne du jour si elle existe. */
    public static function distribuer(array $d): void
    {
        $kg = str_replace([' ', ','], ['', '.'], (string) $d['quantite_kg']);
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $kg) || (float) $kg <= 0) {
            throw new RegleMetier('Quantité invalide : nombre de kg positif, deux décimales au plus.');
        }
        if (Carbon::parse($d['date_distribution'])->isAfter(today())) {
            throw new RegleMetier('La date de distribution ne peut pas être future.');
        }
        $grammes = round((float) $kg * 1000, 3);

        DB::transaction(function () use ($d, $grammes, $kg) {
            $lot = LotsVolailles::verrouiller((int) $d['id_lot']);
            if ($lot->statut !== 'actif') {
                throw new RegleMetier('Choisissez un lot actif.');
            }
            if (! DB::table('modAlim_types')->where('id', $d['id_type_aliment'])->exists()) {
                throw new RegleMetier('Choisissez un aliment existant.');
            }
            $cle = ['id_lot' => $lot->id, 'date_distribution' => Carbon::parse($d['date_distribution'])->toDateString(), 'id_type_aliment' => (int) $d['id_type_aliment']];
            $ligne = DB::table('modAlim_distributions')->where($cle)->lockForUpdate()->first();
            $note = 'Saisie manuelle '.str_replace('.', ',', $kg).' kg par '.Format::auteur().($d['remarque'] ? ' : '.$d['remarque'] : '');
            if ($ligne) {
                DB::table('modAlim_distributions')->where('id', $ligne->id)->update([
                    'quantite_totale' => round((float) $ligne->quantite_totale + $grammes, 3),
                    'remarque' => trim(($ligne->remarque ? $ligne->remarque."\n" : '').$note),
                ]);
                Journal::enregistrer('alimentation.ajout', 'ALI', 'distribution', $ligne->id, ['g' => (float) $ligne->quantite_totale], ['g' => (float) $ligne->quantite_totale + $grammes]);
            } else {
                $id = DB::table('modAlim_distributions')->insertGetId($cle + ['quantite_totale' => $grammes, 'remarque' => $note, 'utilisateur' => Format::auteur()]);
                Journal::enregistrer('alimentation.distribution', 'ALI', 'distribution', $id, apres: $cle + ['g' => $grammes]);
            }
        });
    }

    /** EC12 : correction explicite et tracée d'une distribution. */
    public static function corriger(int $id, string $quantiteKg, string $motif): void
    {
        $kg = str_replace([' ', ','], ['', '.'], $quantiteKg);
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $kg)) {
            throw new RegleMetier('Quantité invalide.');
        }
        if (trim($motif) === '') {
            throw new RegleMetier('Le motif de la correction est obligatoire.');
        }
        DB::transaction(function () use ($id, $kg, $motif) {
            $l = DB::table('modAlim_distributions')->where('id', $id)->lockForUpdate()->first() ?? throw new RegleMetier('Distribution introuvable.');
            $g = round((float) $kg * 1000, 3);
            DB::table('modAlim_distributions')->where('id', $id)->update([
                'quantite_totale' => $g,
                'remarque' => trim(($l->remarque ? $l->remarque."\n" : '').'Correction '.Format::kg($l->quantite_totale).' → '.Format::kg($g).' par '.Format::auteur().' : '.$motif),
            ]);
            Journal::enregistrer('alimentation.correction', 'ALI', 'distribution', $id, ['g' => (float) $l->quantite_totale], ['g' => $g], $motif);
        });
    }

    /** RG-ALI-012 : stock disponible par aliment (entrées kg − distributions g / 1000). */
    public static function stocks(): Collection
    {
        $entrees = DB::table('modAlim_stocks')->groupBy('id_type')->select('id_type', DB::raw('SUM(quantite_kg) kg'))->pluck('kg', 'id_type');
        $sorties = DB::table('modAlim_distributions')->groupBy('id_type_aliment')->select('id_type_aliment', DB::raw('SUM(quantite_totale) g'))->pluck('g', 'id_type_aliment');
        $seuils = DB::table('modAlim_stocks')->whereNotNull('seuil_alerte')->orderBy('date_entree')->orderBy('id')->get()->pluck('seuil_alerte', 'id_type');

        return DB::table('modAlim_types')->orderBy('nom_aliment')->get()->map(function ($t) use ($entrees, $sorties, $seuils) {
            $t->entrees_kg = (float) ($entrees[$t->id] ?? 0);
            $t->sorties_kg = (float) ($sorties[$t->id] ?? 0) / 1000;
            $t->disponible_kg = $t->entrees_kg - $t->sorties_kg;
            $t->seuil_kg = isset($seuils[$t->id]) ? (float) $seuils[$t->id] : null;
            $t->alerte = $t->seuil_kg !== null && $t->disponible_kg <= $t->seuil_kg;

            return $t;
        });
    }

    /**
     * RG-ALI-010, 011 : consommation d'un lot sur une période.
     */
    public static function consommation(object $lot, string $du, string $au): array
    {
        $kg = (float) DB::table('modAlim_distributions')->where('id_lot', $lot->id)->whereBetween('date_distribution', [$du, $au])->sum('quantite_totale') / 1000;
        $jours = (int) Carbon::parse($du)->diffInDays(Carbon::parse($au)) + 1;
        $effectif = (int) $lot->effectif_en_cours;
        $poids = DB::table('modLotVol_suivi_poids')->where('id_lot', $lot->id)->whereBetween('date_suivi', [$du, $au])->orderBy('date_suivi')->orderBy('id')->get();
        $gainKg = $poids->count() >= 2 ? ((float) $poids->last()->poids_moyen - (float) $poids->first()->poids_moyen) / 1000 : null;

        return [
            'kg' => $kg,
            'jours' => $jours,
            'effectif' => $effectif,
            'cma_g' => $effectif > 0 && $jours > 0 ? 1000 * $kg / ($jours * $effectif) : null,
            'gain_kg' => $gainKg,
            'indice' => self::indice($kg, $effectif, $gainKg),
        ];
    }

    public static function indice(float $kg, int $effectif, ?float $gainKg): ?float
    {
        return $effectif > 0 && $gainKg !== null && $gainKg > 0 ? $kg / ($gainKg * $effectif) : null;
    }
}
