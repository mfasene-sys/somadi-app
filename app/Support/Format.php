<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/** Mise en forme française des nombres et des dates. */
class Format
{
    public static function nombre(int|float|string|null $n, int $decimales = 0): string
    {
        if ($n === null || $n === '') {
            return '—';
        }

        return number_format((float) $n, $decimales, ',', "\u{202F}");
    }

    public static function pourcent(int|float|string|null $n, int $decimales = 0): string
    {
        return $n === null ? '—' : self::nombre($n, $decimales).' %';
    }

    public static function fcfa(int|float|string|null $n): string
    {
        return $n === null ? '—' : self::nombre($n, 0)."\u{202F}FCFA";
    }

    public static function kg(int|float|string|null $grammes, int $decimales = 2): string
    {
        return $grammes === null ? '—' : self::nombre(((float) $grammes) / 1000, $decimales).' kg';
    }

    public static function date(?string $d): string
    {
        if (! $d || str_starts_with($d, '0000')) {
            return '—';
        }

        return Carbon::parse($d)->format('d/m/Y');
    }

    public static function dateLongue(?string $d): string
    {
        return $d ? ucfirst(Carbon::parse($d)->locale('fr')->isoFormat('ddd D MMM YYYY')) : '—';
    }

    public static function auteur(): string
    {
        return mb_substr(auth()->user()?->nomComplet() ?? 'application', 0, 100);
    }
}
