<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Ouverture de session commune aux trois chemins de connexion :
 * mot de passe seul (si le code PIN est désactivé), mot de passe + code PIN, empreinte.
 */
class Connexion
{
    /** Clé de session de la connexion en attente du code PIN (mot de passe déjà vérifié). */
    public const ATTENTE = 'connexion_attente';

    public static function ouvrir(Request $request, User $user, string $methode): void
    {
        $request->session()->forget(self::ATTENTE);
        Auth::login($user);
        $request->session()->regenerate();
        // Compatibilité avec les clés de session de l'ancienne application (RG-ACC-002).
        $request->session()->put(['id' => $user->id, 'nom' => $user->nom, 'role' => $user->role]);

        Journal::enregistrer('connexion.reussie', 'ACC', 'utilisateur', $user->id, apres: ['methode' => $methode], auteur: $user);
    }

    /** Compte actif retrouvé par son identifiant, avec un rôle connu ; null sinon. */
    public static function compteUtilisable(?int $id): ?User
    {
        if (! $id) {
            return null;
        }
        $user = User::query()->where('id', $id)->where('actif', 1)->first();

        return $user && Roles::existe($user->roleCanonique()) ? $user : null;
    }
}
