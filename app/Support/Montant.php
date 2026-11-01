<?php

namespace App\Support;

use App\Exceptions\RegleMetier;

/**
 * Montants en centimes entiers (RG-VEN-004) : aucun calcul en virgule flottante.
 * Les colonnes decimal(10,2) sont lues et écrites sous forme de texte « 50000.00 ».
 */
class Montant
{
    /** 99 999 999,99 FCFA */
    public const MAX = 9_999_999_999;

    /** Saisie utilisateur (« 50 000 », « 1 250,50 », « 1250.5 ») → centimes. */
    public static function saisie(mixed $valeur, string $champ = 'Le montant'): int
    {
        $t = str_replace([' ', "\u{202F}", "\u{00A0}"], '', trim((string) $valeur));
        $t = str_replace(',', '.', $t);
        if (! preg_match('/^\d{1,8}(\.\d{1,2})?$/', $t)) {
            throw new RegleMetier("$champ doit être un nombre positif avec au plus deux décimales (99 999 999,99 maximum).");
        }

        return self::base($t);
    }

    /** Valeur de base de données (« 50000.00 ») → centimes. */
    public static function base(mixed $valeur): int
    {
        $t = trim((string) $valeur);
        if ($t === '') {
            return 0;
        }
        $negatif = str_starts_with($t, '-');
        $t = ltrim($t, '+-');
        [$ent, $dec] = array_pad(explode('.', $t, 2), 2, '');
        $c = (int) $ent * 100 + (int) str_pad(substr($dec, 0, 2), 2, '0');

        return $negatif ? -$c : $c;
    }

    /** Centimes → valeur de base de données. */
    public static function versBase(int $centimes): string
    {
        $signe = $centimes < 0 ? '-' : '';
        $c = abs($centimes);

        return $signe.intdiv($c, 100).'.'.str_pad((string) ($c % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function produit(int $quantite, int $prixCentimes): int
    {
        if ($quantite > 0 && $prixCentimes > intdiv(self::MAX, $quantite)) {
            throw new RegleMetier('Le montant total dépasse la capacité autorisée (99 999 999,99 FCFA).');
        }

        return $quantite * $prixCentimes;
    }

    /** Affichage « 50 000 FCFA » (centimes affichés seulement s'il y en a). */
    public static function fcfa(?int $centimes): string
    {
        if ($centimes === null) {
            return '—';
        }
        $dec = $centimes % 100 !== 0 ? 2 : 0;

        return number_format($centimes / 100, $dec, ',', "\u{202F}")."\u{202F}FCFA";
    }

    /** Valeur pré-remplie dans un champ de formulaire. */
    public static function champ(?int $centimes): string
    {
        if ($centimes === null) {
            return '';
        }

        return $centimes % 100 === 0 ? (string) intdiv($centimes, 100) : str_replace('.', ',', self::versBase($centimes));
    }
}
