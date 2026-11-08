<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Application d'authentification (Google Authenticator, Microsoft Authenticator…) et codes de secours.
 * Second facteur de la connexion par mot de passe, à la place du code reçu par e-mail pour les comptes
 * qui l'ont activée. Les codes de secours servent quand ni le téléphone ni l'e-mail ne sont disponibles.
 */
class Authentificateur
{
    public const NB_CODES_SECOURS = 10;

    /** Lettres et chiffres sans ambiguïté (ni 0/O, ni 1/I/L). */
    private const ALPHABET_SECOURS = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function disponible(): bool
    {
        return (bool) config('somadi.authentification.authentificateur', true);
    }

    public static function actif(User $user): bool
    {
        return self::disponible() && DB::table('app_authentificateurs')->where('user_id', $user->id)->exists();
    }

    public static function infos(User $user): ?object
    {
        return DB::table('app_authentificateurs')->where('user_id', $user->id)->first(['active_le', 'utilise_le']);
    }

    public static function emetteur(): string
    {
        return (string) config('somadi.authentification.authentificateur_emetteur', 'SOMADI Farming');
    }

    /**
     * Enregistre la clé confirmée par un premier code et renvoie les codes de secours (en clair, à afficher une fois).
     *
     * @return list<string>
     */
    public static function activer(User $user, string $cle, int $pas): array
    {
        return DB::transaction(function () use ($user, $cle, $pas) {
            DB::table('app_authentificateurs')->where('user_id', $user->id)->delete();
            DB::table('app_authentificateurs')->insert([
                'user_id' => $user->id,
                'cle_chiffree' => Crypt::encryptString($cle),
                'active_le' => now(),
                'dernier_pas' => $pas,
            ]);

            return self::genererCodesSecours($user);
        });
    }

    public static function desactiver(User $user): bool
    {
        return DB::transaction(function () use ($user) {
            DB::table('app_codes_secours')->where('user_id', $user->id)->delete();

            return DB::table('app_authentificateurs')->where('user_id', $user->id)->delete() > 0;
        });
    }

    /** Vérifie un code de l'application ; un même code n'est accepté qu'une fois. */
    public static function verifierCode(User $user, string $saisi): bool
    {
        return DB::transaction(function () use ($user, $saisi) {
            $ligne = DB::table('app_authentificateurs')->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $ligne) {
                return false;
            }
            try {
                $cle = Crypt::decryptString($ligne->cle_chiffree);
            } catch (\Throwable) {
                return false; // clé de l'application changée : l'utilisateur doit réactiver
            }
            $pas = Totp::pasValide($cle, $saisi, $ligne->dernier_pas !== null ? (int) $ligne->dernier_pas : null);
            if ($pas === null) {
                return false;
            }
            DB::table('app_authentificateurs')->where('id', $ligne->id)->update(['dernier_pas' => $pas, 'utilise_le' => now()]);

            return true;
        });
    }

    /** @return list<string> nouveaux codes (les anciens sont supprimés) */
    public static function genererCodesSecours(User $user): array
    {
        DB::table('app_codes_secours')->where('user_id', $user->id)->delete();
        $codes = [];
        $maintenant = now();
        for ($i = 0; $i < self::NB_CODES_SECOURS; $i++) {
            $brut = '';
            for ($j = 0; $j < 10; $j++) {
                $brut .= self::ALPHABET_SECOURS[random_int(0, strlen(self::ALPHABET_SECOURS) - 1)];
            }
            $codes[] = substr($brut, 0, 5).'-'.substr($brut, 5);
            DB::table('app_codes_secours')->insert([
                'user_id' => $user->id,
                'code_hash' => self::empreinte($brut),
                'cree_le' => $maintenant,
            ]);
        }

        return $codes;
    }

    /** Utilise un code de secours (une seule fois). */
    public static function utiliserCodeSecours(User $user, string $saisi): bool
    {
        $brut = self::normaliserSecours($saisi);
        if ($brut === null) {
            return false;
        }

        return DB::transaction(function () use ($user, $brut) {
            $ligne = DB::table('app_codes_secours')->where('user_id', $user->id)->whereNull('utilise_le')
                ->where('code_hash', self::empreinte($brut))->lockForUpdate()->first();
            if (! $ligne) {
                return false;
            }
            DB::table('app_codes_secours')->where('id', $ligne->id)->update(['utilise_le' => now()]);

            return true;
        });
    }

    public static function codesSecoursRestants(User $user): int
    {
        return DB::table('app_codes_secours')->where('user_id', $user->id)->whereNull('utilise_le')->count();
    }

    /** Saisie ressemblant à un code de secours (10 caractères de l'alphabet, tirets et espaces ignorés) ; null sinon. */
    public static function normaliserSecours(string $saisi): ?string
    {
        $brut = strtoupper(preg_replace('/[\s-]/', '', $saisi));

        return preg_match('/^['.self::ALPHABET_SECOURS.']{10}$/', $brut) ? $brut : null;
    }

    private static function empreinte(string $brut): string
    {
        return hash_hmac('sha256', 'secours:'.$brut, (string) config('app.key'));
    }
}
