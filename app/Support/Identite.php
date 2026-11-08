<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Changement d'identité : un administrateur agit sous le compte d'un autre utilisateur (voir, dépanner,
 * occuper un autre rôle), puis revient à son propre compte sans se reconnecter.
 *
 * - L'utilisateur connecté devient réellement le compte choisi : droits, menus, saisies (actions réelles).
 * - L'administrateur réel est gardé en session ; chaque ligne du journal porte les deux noms.
 * - On ne prend jamais l'identité d'un administrateur, d'un compte inactif ou au rôle inconnu.
 * - Pendant ce temps, les réglages personnels du compte (mot de passe, empreinte, profil) et la gestion
 *   des comptes et des droits sont bloqués (middleware IdentiteEmpruntee).
 */
class Identite
{
    /** Clé de session : ['id' => administrateur réel, 'nom' => son nom, 'depuis' => horodatage]. */
    public const SESSION = 'identite_reelle';

    /** Pages bloquées tant qu'on agit sous une autre identité. */
    public const ROUTES_BLOQUEES = [
        'profil.update', 'profil.mot_de_passe',
        'profil.empreintes.options', 'profil.empreintes.activer', 'profil.empreintes.retirer',
        'profil.authentificateur.create', 'profil.authentificateur.activer', 'profil.authentificateur.desactiver',
        'profil.authentificateur.codes', 'profil.authentificateur.regenerer', 'utilisateurs.authentificateur.retirer',
        'utilisateurs.create', 'utilisateurs.store', 'utilisateurs.update', 'utilisateurs.empreintes.retirer', 'utilisateurs.email_essai',
        'droits.edit', 'droits.update',
    ];

    public static function enCours(?Request $request = null): bool
    {
        $request ??= request();

        return $request && $request->hasSession() && is_array($request->session()->get(self::SESSION));
    }

    /** @return array{id:int, nom:string, depuis:int}|null */
    public static function infos(?Request $request = null): ?array
    {
        return self::enCours($request) ? ($request ?? request())->session()->get(self::SESSION) : null;
    }

    /** Administrateur réellement connecté : celui gardé en session, sinon l'utilisateur courant. */
    public static function administrateurReel(Request $request): ?User
    {
        $infos = self::infos($request);
        if ($infos) {
            $admin = Connexion::compteUtilisable((int) $infos['id']);

            return $admin && $admin->estAdmin() ? $admin : null;
        }
        $user = $request->user();

        return $user && $user->estAdmin() ? $user : null;
    }

    /** Peut-on prendre l'identité de ce compte ? Renvoie le motif du refus, ou null. */
    public static function refus(User $admin, User $cible): ?string
    {
        if ($cible->id === $admin->id) {
            return 'Il s’agit de votre propre compte.';
        }
        if (! $cible->actif) {
            return 'Ce compte est inactif.';
        }
        if (! Roles::existe($cible->roleCanonique())) {
            return 'Ce compte n’a pas de rôle reconnu.';
        }
        if ($cible->estAdmin()) {
            return 'On ne peut pas prendre l’identité d’un autre administrateur.';
        }

        return null;
    }

    /** Comptes dont l'administrateur peut prendre l'identité, triés par rôle puis par nom. */
    public static function cibles(User $admin): Collection
    {
        $ordre = array_flip(array_keys(Roles::tous()));

        return User::query()->where('actif', 1)->where('id', '!=', $admin->id)->get()
            ->filter(fn (User $u) => self::refus($admin, $u) === null)
            ->sortBy(fn (User $u) => sprintf('%03d %s', $ordre[$u->roleCanonique()] ?? 999, mb_strtolower($u->nomComplet())))
            ->values();
    }

    public static function prendre(Request $request, User $admin, User $cible): void
    {
        $precedente = self::infos($request) ? $request->user() : null;
        if ($precedente) {
            Journal::enregistrer('identite.fin', 'ACC', 'utilisateur', $precedente->id, apres: ['en_tant_que' => $precedente->nomComplet(), 'suite' => 'changement'], auteur: $precedente);
        }

        self::basculer($request, $cible);
        $request->session()->put(self::SESSION, ['id' => $admin->id, 'nom' => mb_substr($admin->nomComplet(), 0, 150), 'depuis' => now()->timestamp]);

        Journal::enregistrer('identite.debut', 'ACC', 'utilisateur', $cible->id, apres: [
            'en_tant_que' => $cible->nomComplet(),
            'role' => $cible->roleCanonique(),
        ], auteur: $cible);
    }

    /** Retour au compte de l'administrateur. Renvoie l'administrateur, ou null s'il n'est plus utilisable. */
    public static function revenir(Request $request): ?User
    {
        $infos = self::infos($request);
        if (! $infos) {
            return null;
        }
        $empruntee = $request->user();
        if ($empruntee) {
            Journal::enregistrer('identite.fin', 'ACC', 'utilisateur', $empruntee->id, apres: [
                'en_tant_que' => $empruntee->nomComplet(),
                'duree_min' => (int) round((now()->timestamp - (int) $infos['depuis']) / 60),
            ], auteur: $empruntee);
        }
        $request->session()->forget(self::SESSION);

        $admin = Connexion::compteUtilisable((int) $infos['id']);
        if (! $admin || ! $admin->estAdmin()) {
            return null;
        }
        self::basculer($request, $admin);

        return $admin;
    }

    private static function basculer(Request $request, User $user): void
    {
        Auth::login($user);
        $request->session()->regenerate();
        // Les formulaires restés ouverts dans d'autres onglets ne doivent pas partir sous la nouvelle identité.
        $request->session()->regenerateToken();
        $request->session()->forget('url.intended');
        // Compatibilité avec les clés de session de l'ancienne application (RG-ACC-002).
        $request->session()->put(['id' => $user->id, 'nom' => $user->nom, 'role' => $user->role]);
        Droits::viderCache();
    }
}
