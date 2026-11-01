<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * RG-ACC-001 / RG-ACC-002 : compte actif retrouvé de façon unique par e-mail, mot de passe vérifié
 * contre le hachage stocké, message d'échec qui ne révèle pas l'existence du compte, session renouvelée.
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

        Auth::login($user);
        $request->session()->regenerate();
        // Compatibilité avec les clés de session de l'ancienne application (RG-ACC-002).
        $request->session()->put(['id' => $user->id, 'nom' => $user->nom, 'role' => $user->role]);

        Journal::enregistrer('connexion.reussie', 'ACC', 'utilisateur', $user->id, auteur: $user);

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
