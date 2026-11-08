<?php

namespace App\Support;

/**
 * Codes à usage unique basés sur l'heure (TOTP, RFC 6238), compatibles avec Google Authenticator,
 * Microsoft Authenticator, etc. : HMAC-SHA1, 6 chiffres, pas de 30 secondes.
 * Calculs purs, sans base de données (voir Authentificateur pour l'enregistrement).
 */
class Totp
{
    public const PERIODE = 30;

    public const CHIFFRES = 6;

    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Nouvelle clé secrète : 20 octets aléatoires (160 bits), en base32 (32 caractères). */
    public static function nouvelleCle(): string
    {
        return self::base32(random_bytes(20));
    }

    /** Numéro du pas de 30 secondes pour un horodatage Unix. */
    public static function pas(?int $horodatage = null): int
    {
        return intdiv($horodatage ?? time(), self::PERIODE);
    }

    /** Code à 6 chiffres pour une clé (base32) et un pas donnés. */
    public static function code(string $cle, int $pas, int $chiffres = self::CHIFFRES): string
    {
        $hmac = hash_hmac('sha1', pack('J', $pas), self::debase32($cle), true);
        $decalage = ord($hmac[19]) & 0x0F;
        $valeur = ((ord($hmac[$decalage]) & 0x7F) << 24)
            | (ord($hmac[$decalage + 1]) << 16)
            | (ord($hmac[$decalage + 2]) << 8)
            | ord($hmac[$decalage + 3]);

        return str_pad((string) ($valeur % (10 ** $chiffres)), $chiffres, '0', STR_PAD_LEFT);
    }

    /**
     * Pas auquel correspond le code saisi, en tolérant un pas d'écart (horloge du téléphone en avance ou en retard
     * de 30 s), ou null. Les pas inférieurs ou égaux à $dernierPas sont refusés (un code ne sert qu'une fois).
     */
    public static function pasValide(string $cle, string $saisi, ?int $dernierPas = null, ?int $horodatage = null): ?int
    {
        if (! preg_match('/^\d{'.self::CHIFFRES.'}$/', $saisi)) {
            return null;
        }
        $maintenant = self::pas($horodatage);
        foreach ([$maintenant, $maintenant - 1, $maintenant + 1] as $p) {
            if (($dernierPas === null || $p > $dernierPas) && hash_equals(self::code($cle, $p), $saisi)) {
                return $p;
            }
        }

        return null;
    }

    /** Adresse otpauth:// lue par les applications (contenu du QR code). */
    public static function uri(string $cle, string $compte, string $emetteur): string
    {
        $libelle = rawurlencode($emetteur).':'.rawurlencode($compte);

        return 'otpauth://totp/'.$libelle.'?secret='.$cle.'&issuer='.rawurlencode($emetteur)
            .'&algorithm=SHA1&digits='.self::CHIFFRES.'&period='.self::PERIODE;
    }

    /** Clé présentée par groupes de 4 pour la saisie à la main. */
    public static function cleLisible(string $cle): string
    {
        return trim(chunk_split($cle, 4, ' '));
    }

    public static function base32(string $octets): string
    {
        $bits = '';
        foreach (str_split($octets) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $sortie = '';
        foreach (str_split($bits, 5) as $groupe) {
            $sortie .= self::ALPHABET[bindec(str_pad($groupe, 5, '0'))];
        }

        return $sortie;
    }

    public static function debase32(string $texte): string
    {
        $texte = strtoupper(preg_replace('/[\s=-]/', '', $texte));
        $bits = '';
        foreach (str_split($texte) as $c) {
            $i = strpos(self::ALPHABET, $c);
            if ($i === false) {
                throw new \InvalidArgumentException('Clé base32 invalide.');
            }
            $bits .= str_pad(decbin($i), 5, '0', STR_PAD_LEFT);
        }
        $octets = '';
        foreach (str_split($bits, 8) as $octet) {
            if (strlen($octet) === 8) {
                $octets .= chr(bindec($octet));
            }
        }

        return $octets;
    }
}
