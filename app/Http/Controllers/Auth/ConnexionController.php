<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Authentificateur;
use App\Support\CodeConnexion;
use App\Support\Connexion;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * RG-ACC-001 / RG-ACC-002 : compte actif retrouvé de façon unique par e-mail, mot de passe vérifié
 * contre le hachage stocké, message d'échec qui ne révèle pas l'existence du compte, session renouvelée.
 * Authentification renforcée : le mot de passe correct déclenche l'envoi d'un code PIN par e-mail
 * (voir CodeConnexionController) ; sur téléphone, l'empreinte remplace les deux (EmpreinteController).
 */
class ConnexionController extends Controller
{
    public function create(): View
    {
        return view('auth.connexion');
    }

    public function store(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'email' => ['required', 'string', 'email', 'max:100'],
            'mot_de_passe' => ['required', 'string', 'max:200'],
        ], [
            'email.required' => 'Renseignez votre adresse e-mail et votre mot de passe.',
            'email.email' => 'Renseignez votre adresse e-mail et votre mot de passe.',
            'mot_de_passe.required' => 'Renseignez votre adresse e-mail et votre mot de passe.',
        ]);

        $comptes = User::query()
            ->where('email', $donnees['email'])
            ->where('actif', 1)
            ->limit(2)
            ->get();

        $user = $comptes->count() === 1 ? $comptes->first() : null;

        if (! $user || ! $user->verifierMotDePasse($donnees['mot_de_passe'])) {
            Journal::enregistrer('connexion.echec', 'ACC', 'utilisateur', $user?->id, apres: ['email' => $donnees['email']]);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Adresse e-mail ou mot de passe incorrect, ou compte inactif.']);
        }

        // Second facteur par l'application d'authentification, si le compte l'a activée (pas d'e-mail envoyé).
        if (Authentificateur::actif($user)) {
            $request->session()->regenerate();
            $request->session()->put(Connexion::ATTENTE, ['user_id' => $user->id, 'code_id' => null, 'depuis' => now()->timestamp, 'mode' => 'appli', 'essais' => 0]);

            return redirect()->route('login.code');
        }

        // Second facteur : code PIN envoyé par e-mail. La session ne s'ouvre qu'après le code.
        if (config('somadi.authentification.pin_email')) {
            if ($attente = CodeConnexion::attenteAvantEmission($user)) {
                Journal::enregistrer('connexion.code_bloque', 'ACC', 'utilisateur', $user->id, auteur: $user);

                return back()->withInput($request->only('email'))
                    ->withErrors(['email' => "Trop de codes de connexion demandés pour ce compte. Réessayez dans $attente min."]);
            }
            $codeId = CodeConnexion::emettre($user);
            if (! $codeId) {
                return back()->withInput($request->only('email'))
                    ->withErrors(['email' => 'Le code de connexion n’a pas pu être envoyé par e-mail. Réessayez dans un instant ou prévenez un administrateur.']);
            }
            $request->session()->regenerate();
            $request->session()->put(Connexion::ATTENTE, ['user_id' => $user->id, 'code_id' => $codeId, 'depuis' => now()->timestamp, 'mode' => 'email', 'essais' => 0]);

            return redirect()->route('login.code');
        }

        Connexion::ouvrir($request, $user, 'mot_de_passe');

        return redirect()->intended(route('accueil'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user) {
            Journal::enregistrer('deconnexion', 'ACC', 'utilisateur', $user->id, auteur: $user);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('statut', 'Vous êtes déconnecté.')->with('vider_cache_hors_ligne', true);
    }
}
