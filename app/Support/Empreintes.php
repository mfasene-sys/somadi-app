<?php

namespace App\Support;

use App\Exceptions\RegleMetier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use lbuchs\WebAuthn\Binary\ByteBuffer;
use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;

/**
 * Connexion par empreinte digitale (norme WebAuthn, « clés d'accès »).
 *
 * Le téléphone crée une paire de clés liée au domaine de SOMADI. La clé privée reste dans sa puce sécurisée et
 * n'est utilisable qu'après la vérification de l'empreinte (ou du code de verrouillage) par le téléphone lui-même.
 * Le serveur ne conserve que la clé publique et vérifie, à chaque connexion, la signature d'un défi aléatoire.
 */
class Empreintes
{
    private const DEFI = 'empreinte_defi';

    private const DUREE_DEFI = 120; // secondes

    public static function active(): bool
    {
        return (bool) config('somadi.authentification.empreinte');
    }

    public static function domaine(): string
    {
        return (string) config('somadi.authentification.webauthn_domaine');
    }

    /** Options de création d'une clé pour l'utilisateur connecté (à passer à navigator.credentials.create). */
    public static function optionsActivation(Request $request, User $user): object
    {
        $webauthn = self::serveur();
        $existantes = DB::table('app_empreintes')->where('user_id', $user->id)->pluck('credential_id')
            ->map(fn ($id) => ByteBuffer::fromBase64Url($id))->all();

        $args = $webauthn->getCreateArgs(
            self::identifiantUtilisateur($user),
            (string) $user->email,
            $user->nomComplet(),
            timeout: 120,
            requireResidentKey: true, // clé « découvrable » : la connexion se fait sans saisir l'e-mail
            requireUserVerification: true, // le téléphone doit vérifier l'empreinte (ou son code de verrouillage)
            crossPlatformAttachment: false, // capteur intégré au téléphone uniquement
            excludeCredentialIds: $existantes,
        );
        unset($args->publicKey->extensions);
        self::memoriserDefi($request, $webauthn, 'activation', $user->id);

        return $args;
    }

    /** Vérifie la réponse du téléphone et enregistre la clé publique. */
    public static function enregistrer(Request $request, User $user, array $reponse): int
    {
        $defi = self::reprendreDefi($request, 'activation', $user->id);
        self::verifierOrigine((string) ($reponse['clientDataJSON'] ?? ''));

        try {
            $donnees = self::serveur()->processCreate(
                self::binaire($reponse['clientDataJSON'] ?? null),
                self::binaire($reponse['attestationObject'] ?? null),
                $defi,
                requireUserVerification: true,
                requireUserPresent: true,
                failIfRootMismatch: false,
            );
        } catch (WebAuthnException $e) {
            throw new RegleMetier('Le téléphone n’a pas pu activer l’empreinte ('.$e->getMessage().').');
        }

        $credentialId = self::base64Url($donnees->credentialId);
        if (DB::table('app_empreintes')->where('credential_id', $credentialId)->exists()) {
            throw new RegleMetier('Cette empreinte est déjà activée.');
        }

        return DB::table('app_empreintes')->insertGetId([
            'user_id' => $user->id,
            'credential_id' => $credentialId,
            'cle_publique' => $donnees->credentialPublicKey,
            'compteur' => (int) ($donnees->signatureCounter ?? 0),
            'appareil' => self::nomAppareil((string) $request->userAgent()),
            'cree_le' => now(),
        ]);
    }

    /** Options de connexion (navigator.credentials.get) : aucune clé n'est listée, le téléphone propose les siennes. */
    public static function optionsConnexion(Request $request): object
    {
        $webauthn = self::serveur();
        $args = $webauthn->getGetArgs([], timeout: 120, requireUserVerification: true);
        self::memoriserDefi($request, $webauthn, 'connexion', null);

        return $args;
    }

    /**
     * Vérifie la signature du téléphone et renvoie le compte correspondant (actif), ou lève RegleMetier.
     * Le compteur de signatures est mis à jour pour détecter une clé clonée.
     */
    public static function authentifier(Request $request, array $reponse): User
    {
        $defi = self::reprendreDefi($request, 'connexion', null);
        self::verifierOrigine((string) ($reponse['clientDataJSON'] ?? ''));

        $credentialId = (string) ($reponse['id'] ?? '');
        $cle = $credentialId !== '' ? DB::table('app_empreintes')->where('credential_id', $credentialId)->first() : null;
        if (! $cle) {
            throw new RegleMetier('Cette empreinte n’est pas (ou plus) reconnue. Connectez-vous avec votre mot de passe, puis réactivez l’empreinte dans Mon profil.');
        }

        // Le téléphone renvoie l'identifiant d'utilisateur fixé à l'activation : il doit correspondre au propriétaire de la clé.
        $handle = $reponse['userHandle'] ?? null;
        $user = User::query()->find($cle->user_id);
        if ($handle !== null && $handle !== '' && $user && ! hash_equals(self::identifiantUtilisateur($user), self::binaire($handle))) {
            throw new RegleMetier('Empreinte non reconnue.');
        }

        $webauthn = self::serveur();
        try {
            $webauthn->processGet(
                self::binaire($reponse['clientDataJSON'] ?? null),
                self::binaire($reponse['authenticatorData'] ?? null),
                self::binaire($reponse['signature'] ?? null),
                $cle->cle_publique,
                $defi,
                prevSignatureCnt: (int) $cle->compteur,
                requireUserVerification: true,
                requireUserPresent: true,
            );
        } catch (WebAuthnException $e) {
            Journal::enregistrer('connexion.empreinte_refusee', 'ACC', 'utilisateur', $cle->user_id, apres: ['motif' => $e->getMessage()]);
            throw new RegleMetier('Empreinte non reconnue. Réessayez ou connectez-vous avec votre mot de passe.');
        }

        $compte = Connexion::compteUtilisable((int) $cle->user_id);
        if (! $compte) {
            throw new RegleMetier('Votre compte est inactif. Contactez un administrateur.');
        }

        DB::table('app_empreintes')->where('id', $cle->id)->update([
            'compteur' => (int) ($webauthn->getSignatureCounter() ?? $cle->compteur),
            'utilise_le' => now(),
        ]);

        return $compte;
    }

    public static function pour(User $user): \Illuminate\Support\Collection
    {
        return DB::table('app_empreintes')->where('user_id', $user->id)->orderByDesc('cree_le')->get();
    }

    // ---------------------------------------------------------------------------------------------

    private static function serveur(): WebAuthn
    {
        // Seul le format « none » est demandé : on ne cherche pas à identifier le modèle de téléphone.
        return new WebAuthn('SOMADI Farming', self::domaine(), ['none'], true);
    }

    /** Identifiant opaque (non devinable, sans donnée personnelle) transmis au téléphone pour ce compte. */
    private static function identifiantUtilisateur(User $user): string
    {
        return hash_hmac('sha256', 'somadi-utilisateur:'.$user->id, (string) config('app.key'), true);
    }

    private static function memoriserDefi(Request $request, WebAuthn $webauthn, string $usage, ?int $userId): void
    {
        $request->session()->put(self::DEFI, [
            'defi' => self::base64Url($webauthn->getChallenge()->getBinaryString()),
            'usage' => $usage,
            'user_id' => $userId,
            'le' => now()->timestamp,
        ]);
    }

    /** Défi à usage unique : retiré de la session dès sa lecture. */
    private static function reprendreDefi(Request $request, string $usage, ?int $userId): ByteBuffer
    {
        $d = $request->session()->pull(self::DEFI);
        if (! is_array($d) || $d['usage'] !== $usage || $d['user_id'] !== $userId || now()->timestamp - (int) $d['le'] > self::DUREE_DEFI) {
            throw new RegleMetier('La demande a expiré. Recommencez.');
        }

        return ByteBuffer::fromBase64Url($d['defi']);
    }

    /** L'origine signée par le navigateur doit être exactement le domaine de SOMADI (https, sauf en local). */
    private static function verifierOrigine(string $clientDataB64): void
    {
        $client = json_decode(self::binaire($clientDataB64), true);
        $origine = is_array($client) ? (string) ($client['origin'] ?? '') : '';
        $hote = (string) parse_url($origine, PHP_URL_HOST);
        $schema = parse_url($origine, PHP_URL_SCHEME);
        $domaine = self::domaine();
        $local = in_array($domaine, ['localhost', '127.0.0.1'], true);

        if (($schema !== 'https' && ! $local) || ($hote !== $domaine && ! str_ends_with($hote, '.'.$domaine))) {
            throw new RegleMetier('Origine de la demande non reconnue.');
        }
    }

    private static function binaire(mixed $b64): string
    {
        if (! is_string($b64) || $b64 === '' || ! preg_match('/^[A-Za-z0-9_-]+={0,2}$/', $b64)) {
            throw new RegleMetier('Réponse du téléphone incomplète.');
        }

        return ByteBuffer::fromBase64Url($b64)->getBinaryString();
    }

    private static function base64Url(string $binaire): string
    {
        return rtrim(strtr(base64_encode($binaire), '+/', '-_'), '=');
    }

    /** Libellé lisible de l'appareil, pour que l'utilisateur reconnaisse ses téléphones dans la liste. */
    public static function nomAppareil(string $ua): string
    {
        $systeme = match (true) {
            (bool) preg_match('/iPhone/i', $ua) => 'iPhone',
            (bool) preg_match('/iPad/i', $ua) => 'iPad',
            (bool) preg_match('/Android/i', $ua) => 'Android',
            (bool) preg_match('/Windows/i', $ua) => 'Windows',
            (bool) preg_match('/Macintosh|Mac OS/i', $ua) => 'Mac',
            default => 'Appareil',
        };
        $navigateur = match (true) {
            (bool) preg_match('/SamsungBrowser/i', $ua) => 'Samsung Internet',
            (bool) preg_match('/EdgA?\//i', $ua) => 'Edge',
            (bool) preg_match('/Firefox|FxiOS/i', $ua) => 'Firefox',
            (bool) preg_match('/Chrome|CriOS/i', $ua) => 'Chrome',
            (bool) preg_match('/Safari/i', $ua) => 'Safari',
            default => null,
        };

        return mb_substr($systeme.($navigateur ? ' · '.$navigateur : ''), 0, 100);
    }
}
