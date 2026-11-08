<?php

namespace App\Http\Controllers;

use App\Support\Authentificateur;
use App\Support\Empreintes;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Mon profil : consultation, nom/prénom et changement de mot de passe (avec le mot de passe actuel). */
class ProfilController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profil.edit', [
            'user' => $request->user(),
            'empreintes' => Empreintes::active() ? Empreintes::pour($request->user()) : collect(),
            'authentificateur' => Authentificateur::disponible() ? Authentificateur::infos($request->user()) : null,
            'secoursRestants' => Authentificateur::disponible() ? Authentificateur::codesSecoursRestants($request->user()) : 0,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $d = $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['nullable', 'string', 'max:100'],
        ]);
        $avant = ['nom' => $user->nom, 'prenom' => $user->prenom];
        $user->fill($d)->save();
        Journal::enregistrer('profil.modification', 'ACC', 'utilisateur', $user->id, $avant, $d);

        return back()->with('succes', 'Profil mis à jour.');
    }

    public function motDePasse(Request $request): RedirectResponse
    {
        $user = $request->user();
        $d = $request->validate([
            'actuel' => ['required', 'string'],
            'nouveau' => ['required', 'string', 'confirmed', 'different:actuel', Password::min(8)->letters()->numbers()],
        ], [], ['actuel' => 'mot de passe actuel', 'nouveau' => 'nouveau mot de passe']);

        if (! $user->verifierMotDePasse($d['actuel'])) {
            return back()->withErrors(['actuel' => 'Le mot de passe actuel est incorrect.']);
        }
        $user->definirMotDePasse($d['nouveau']);
        $user->save();
        $request->session()->regenerate();
        Journal::enregistrer('profil.mot_de_passe', 'ACC', 'utilisateur', $user->id);

        return back()->with('succes', 'Mot de passe modifié.');
    }
}
