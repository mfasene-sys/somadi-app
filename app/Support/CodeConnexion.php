<?php

namespace App\Support;

use App\Mail\CodeConnexionMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Code PIN de connexion envoyé par e-mail (second facteur après le mot de passe).
 * Six chiffres, usage unique, durée et nombre d'essais limités. Seule une empreinte HMAC du code est stockée.
 */
class CodeConnexion
{
    /** Plafond d'émissions par compte et par heure : borne le nombre total d'essais possibles, même avec le bon mot de passe. */
    public const EMISSIONS_PAR_HEURE = 10;

    /** Minutes d'attente avant de pouvoir émettre un nouveau code, 0 si c'est possible tout de suite. */
    public static function attenteAvantEmission(User $user): int
    {
        $cle = 'code-emission:'.$user->id;

        return RateLimiter::tooManyAttempts($cle, self::EMISSIONS_PAR_HEURE) ? (int) ceil(RateLimiter::availableIn($cle) / 60) : 0;
    }

    /** Crée un nouveau code (les précédents du compte deviennent inutilisables) et l'envoie. Renvoie l'id du code, ou null si l'envoi a échoué. */
    public static function emettre(User $user): ?int
    {
        // En production, un envoi « log » ou « array » n'atteindrait personne : on le signale plutôt que d'attendre un code qui n'arrivera pas.
        if (! self::envoiConfigure()) {
            Log::error('Code de connexion : aucun envoi d\'e-mail configuré (MAIL_MAILER='.config('mail.default').').');
            Journal::enregistrer('connexion.code_non_envoye', 'ACC', 'utilisateur', $user->id, apres: ['erreur' => 'envoi d\'e-mails non configuré'], auteur: $user);

            return null;
        }
        RateLimiter::hit('code-emission:'.$user->id, 3600);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $maintenant = now();

        $id = DB::transaction(function () use ($user, $code, $maintenant) {
            self::invaliderTout($user->id);

            return DB::table('app_codes_connexion')->insertGetId([
                'user_id' => $user->id,
                'code_hash' => self::empreinte($code),
                'cree_le' => $maintenant,
                'expire_le' => $maintenant->copy()->addMinutes(self::duree()),
                'tentatives' => 0,
                'ip' => request()?->ip(),
            ]);
        });

        try {
            Mail::to($user->email)->send(new CodeConnexionMail($user, $code, self::duree()));
        } catch (\Throwable $e) {
            Log::error('Envoi du code de connexion impossible', ['user_id' => $user->id, 'erreur' => $e->getMessage()]);
            DB::table('app_codes_connexion')->where('id', $id)->update(['utilise_le' => $maintenant]);
            Journal::enregistrer('connexion.code_non_envoye', 'ACC', 'utilisateur', $user->id, apres: ['erreur' => mb_substr($e->getMessage(), 0, 200)], auteur: $user);

            return null;
        }

        Journal::enregistrer('connexion.code_envoye', 'ACC', 'utilisateur', $user->id, auteur: $user);

        return $id;
    }

    /**
     * Vérifie le code saisi. Résultat : 'ok', 'faux' (essai décompté), 'epuise' (trop d'essais) ou 'expire'.
     * Le code est relu sous verrou pour que deux envois simultanés ne comptent pas un seul essai.
     */
    public static function verifier(int $codeId, int $userId, string $saisi): string
    {
        return DB::transaction(function () use ($codeId, $userId, $saisi) {
            $ligne = DB::table('app_codes_connexion')->where('id', $codeId)->where('user_id', $userId)->lockForUpdate()->first();
            if (! $ligne || $ligne->utilise_le !== null || now()->greaterThan($ligne->expire_le)) {
                return 'expire';
            }
            if ($ligne->tentatives >= self::tentativesMax()) {
                return 'epuise';
            }
            if (hash_equals($ligne->code_hash, self::empreinte($saisi))) {
                DB::table('app_codes_connexion')->where('id', $codeId)->update(['utilise_le' => now()]);

                return 'ok';
            }
            $tentatives = $ligne->tentatives + 1;
            DB::table('app_codes_connexion')->where('id', $codeId)->update(['tentatives' => $tentatives]);

            return $tentatives >= self::tentativesMax() ? 'epuise' : 'faux';
        });
    }

    public static function envoiConfigure(): bool
    {
        return ! (app()->isProduction() && in_array(config('mail.default'), ['log', 'array'], true));
    }

    public static function invaliderTout(int $userId): void
    {
        DB::table('app_codes_connexion')->where('user_id', $userId)->whereNull('utilise_le')->update(['utilise_le' => now()]);
    }

    /** Adresse partiellement masquée, pour indiquer où le code est parti sans l'exposer entièrement. */
    public static function emailMasque(string $email): string
    {
        [$local, $domaine] = array_pad(explode('@', $email, 2), 2, '');
        $visible = mb_substr($local, 0, min(2, max(1, mb_strlen($local) - 1)));

        return $visible.str_repeat('•', max(1, mb_strlen($local) - mb_strlen($visible))).'@'.$domaine;
    }

    public static function duree(): int
    {
        return (int) config('somadi.authentification.pin_duree_minutes', 10);
    }

    public static function tentativesMax(): int
    {
        return (int) config('somadi.authentification.pin_tentatives', 5);
    }

    private static function empreinte(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
