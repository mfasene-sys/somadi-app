<?php

namespace App\Domaine\Organisation;

use App\Exceptions\RegleMetier;
use Illuminate\Support\Carbon;

/** Calcul des occurrences d'une tâche de routine (RG-ACT-010 à 013 ; EC13). */
class Recurrence
{
    public const TYPES = ['quotidien' => 'Chaque jour', 'hebdo' => 'Chaque semaine', 'mensuel' => 'Chaque mois', 'annuel' => 'Chaque année', 'intervalle' => 'Plusieurs fois par jour', 'manuel' => 'Manuel (pas de génération)'];

    public const JOURS = ['lun' => 'Lun', 'mar' => 'Mar', 'mer' => 'Mer', 'jeu' => 'Jeu', 'ven' => 'Ven', 'sam' => 'Sam', 'dim' => 'Dim'];

    public const MAX_JOURS = 366;

    public const MAX_OCCURRENCES = 1000;

    /** @return list<int> */
    public static function liste(?string $s, int $min, int $max): array
    {
        $out = [];
        foreach (preg_split('/[\s,;]+/', (string) $s, -1, PREG_SPLIT_NO_EMPTY) as $x) {
            if (ctype_digit($x) && (int) $x >= $min && (int) $x <= $max) {
                $out[] = (int) $x;
            }
        }
        $out = array_values(array_unique($out));
        sort($out);

        return $out;
    }

    /**
     * RG-ACT-012 : un jour au-delà de la fin du mois est rabattu depuis le 31 (février 28 j : 30 → 27, 31 → 28) ; doublons supprimés.
     *
     * @return list<int>
     */
    public static function joursDuMois(array $jours, int $annee, int $mois): array
    {
        $dernier = Carbon::create($annee, $mois, 1)->daysInMonth;
        $out = [];
        foreach ($jours as $j) {
            $out[] = $j <= $dernier ? $j : $dernier - (31 - $j);
        }
        $out = array_values(array_unique(array_filter($out, fn ($j) => $j >= 1)));
        sort($out);

        return $out;
    }

    /**
     * Occurrences [début, fin, échéance] entre deux dates incluses.
     *
     * @return list<array{0:Carbon,1:Carbon,2:Carbon}>
     */
    public static function occurrences(object $rt, object $routine, string $du, string $au): array
    {
        $deb = Carbon::parse($du)->startOfDay();
        $fin = Carbon::parse($au)->startOfDay();
        if ($fin->lt($deb)) {
            throw new RegleMetier('La fin de période précède son début.');
        }
        if ($deb->diffInDays($fin) + 1 > self::MAX_JOURS) {
            throw new RegleMetier('La génération porte sur '.self::MAX_JOURS.' jours au plus.');
        }
        if ($routine->date_debut && $deb->lt(Carbon::parse($routine->date_debut))) {
            $deb = Carbon::parse($routine->date_debut);
        }
        if ($routine->date_fin && $fin->gt(Carbon::parse($routine->date_fin))) {
            $fin = Carbon::parse($routine->date_fin);
        }
        if ($rt->type_recurrence === 'manuel' || $fin->lt($deb)) {
            return [];
        }
        $h = $rt->horaire_debut ?: '08:00:00';
        $duree = max(1, (int) ($rt->duree_estimee_min ?: 60));
        $delai = (int) ($rt->delai_echeance_min ?? 0);
        $jours = array_filter(explode(',', (string) $rt->jours_semaine));
        $mapJours = ['mon' => 'lun', 'tue' => 'mar', 'wed' => 'mer', 'thu' => 'jeu', 'fri' => 'ven', 'sat' => 'sam', 'sun' => 'dim'];
        $joursMois = self::liste($rt->jours_mois ?: ($rt->jour_mois ? (string) $rt->jour_mois : ''), 1, 31);
        $moisAnnee = self::liste($rt->mois_annee, 1, 12);
        $ancre = Carbon::parse($routine->date_debut ?: $du)->startOfWeek();
        $pasSemaines = max(1, (int) ($rt->intervalle_semaines ?: 1));
        $out = [];
        $examinees = 0;
        $creer = function (Carbon $jour, string $heure, ?string $finPlage = null) use (&$out, $duree, $delai) {
            $d = Carbon::parse($jour->toDateString().' '.$heure);
            $f = $d->copy()->addMinutes($duree);
            if ($finPlage) {
                $f = $f->min(Carbon::parse($jour->toDateString().' '.$finPlage));
            }
            $out[] = [$d, $f, $delai > 0 ? $d->copy()->addMinutes($delai) : $f->copy()];
        };
        for ($j = $deb->copy(); $j->lte($fin); $j->addDay()) {
            if (++$examinees > self::MAX_OCCURRENCES) {
                break;
            }
            $ok = match ($rt->type_recurrence) {
                'quotidien', 'intervalle' => true,
                'hebdo' => in_array($mapJours[strtolower($j->format('D'))], $jours, true) && intdiv((int) $ancre->diffInDays($j->copy()->startOfWeek()), 7) % $pasSemaines === 0,
                'mensuel' => in_array($j->day, self::joursDuMois($joursMois, $j->year, $j->month), true),
                'annuel' => in_array($j->month, $moisAnnee, true) && in_array($j->day, self::joursDuMois($joursMois ?: [1], $j->year, $j->month), true),
                default => false,
            };
            if (! $ok) {
                continue;
            }
            if ($rt->type_recurrence === 'intervalle') {
                $pas = (int) $rt->intervalle_min;
                $hf = (string) $rt->horaire_fin;
                if ($pas <= 0 || ! $hf || $hf <= $h) {
                    throw new RegleMetier("La tâche « {$rt->nom} » : plage horaire et intervalle invalides.");
                }
                for ($t = Carbon::parse($j->toDateString().' '.$h); $t->lt(Carbon::parse($j->toDateString().' '.$hf)); $t->addMinutes($pas)) {
                    $creer($j, $t->format('H:i:s'), $hf);
                    if (count($out) >= self::MAX_OCCURRENCES) {
                        break 2;
                    }
                }
            } else {
                $creer($j, $h);
            }
            if (count($out) >= self::MAX_OCCURRENCES) {
                break;
            }
        }

        return $out;
    }
}
