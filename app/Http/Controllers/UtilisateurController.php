<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Journal;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UtilisateurController extends Controller
{
    public function index(): View
    {
        return view('utilisateurs.index');
    }

    public function create(): View
    {
        return view('utilisateurs.form', ['utilisateur' => new User(['actif' => true, 'role' => 'ouvrier']), 'roles' => Roles::tous()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $donnees = $this->valider($request, null);
        $user = DB::transaction(function () use ($donnees) {
            $user = new User([
                'nom' => $donnees['nom'],
                'prenom' => $donnees['prenom'] ?? null,
                'email' => $donnees['email'],
                'role' => $donnees['role'],
                'actif' => true,
            ]);
            $user->definirMotDePasse($donnees['mot_de_passe']);
            $user->save();
            Journal::enregistrer('utilisateur.creation', 'ACC', 'utilisateur', $user->id, apres: $this->instantane($user));

            return $user;
        });

        return redirect()->route('utilisateurs.index')->with('succes', "Le compte de {$user->nomComplet()} est créé.");
    }

    public function edit(User $utilisateur): View
    {
        return view('utilisateurs.form', [
            'utilisateur' => $utilisateur,
            'roles' => Roles::tous(),
            'empreintes' => DB::table('app_empreintes')->where('user_id', $utilisateur->id)->orderByDesc('cree_le')->get(),
        ]);
    }

    public function update(Request $request, User $utilisateur): RedirectResponse
    {
        $donnees = $this->valider($request, $utilisateur);
        $actif = $request->boolean('actif');
        $moi = $request->user();

        // Garde-fous : ne pas se verrouiller soi-même, ne jamais laisser l'application sans administrateur actif.
        if ($utilisateur->id === $moi->id && (! $actif || Roles::canonique($donnees['role']) !== $moi->roleCanonique())) {
            return back()->withInput()->withErrors(['role' => 'Vous ne pouvez pas modifier votre propre rôle ni désactiver votre propre compte.']);
        }
        if ($utilisateur->estAdmin() && (! $actif || Roles::canonique($donnees['role']) !== 'admin') && $this->nbAdminsActifs() <= 1) {
            return back()->withInput()->withErrors(['role' => 'Il doit rester au moins un administrateur actif.']);
        }

        DB::transaction(function () use ($utilisateur, $donnees, $actif) {
            $avant = $this->instantane($utilisateur);
            $utilisateur->fill([
                'nom' => $donnees['nom'],
                'prenom' => $donnees['prenom'] ?? null,
                'email' => $donnees['email'],
                'role' => $donnees['role'],
                'actif' => $actif,
            ]);
            $mdp = ! empty($donnees['mot_de_passe']);
            if ($mdp) {
                $utilisateur->definirMotDePasse($donnees['mot_de_passe']);
            }
            $utilisateur->save();
            $apres = $this->instantane($utilisateur) + ($mdp ? ['mot_de_passe' => 'réinitialisé'] : []);
            Journal::enregistrer('utilisateur.modification', 'ACC', 'utilisateur', $utilisateur->id, $avant, $apres);
        });

        return redirect()->route('utilisateurs.index')->with('succes', "Le compte de {$utilisateur->nomComplet()} est mis à jour.");
    }

    /**
     * E-mail d'essai envoyé depuis le site lui-même : chez OVH, la console SSH n'a pas le droit d'envoyer,
     * seul l'environnement web le peut. Destinataire : l'administrateur connecté.
     */
    public function emailEssai(Request $request): RedirectResponse
    {
        $moi = $request->user();
        try {
            \Illuminate\Support\Facades\Mail::to($moi->email)->send(new \App\Mail\CodeConnexionMail($moi, '000000', 10));
        } catch (\Throwable $e) {
            Journal::enregistrer('email.essai_echec', 'ACC', 'utilisateur', $moi->id, apres: ['envoi' => config('mail.default'), 'erreur' => mb_substr($e->getMessage(), 0, 200)]);

            return back()->with('erreur', 'Envoi impossible (« '.config('mail.default').' ») : '.$e->getMessage());
        }
        Journal::enregistrer('email.essai', 'ACC', 'utilisateur', $moi->id, apres: ['envoi' => config('mail.default')]);

        return back()->with('succes', 'E-mail d’essai envoyé à '.$moi->email.' (code fictif 000000, envoi « '.config('mail.default').' »). Vérifiez sa réception, y compris dans les courriers indésirables.');
    }

    private function valider(Request $request, ?User $user): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', Rule::unique('utilisateurs', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in(array_keys(Roles::tous()))],
            'mot_de_passe' => [$user ? 'nullable' : 'required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [], [
            'nom' => 'nom', 'prenom' => 'prénom', 'email' => 'adresse e-mail', 'role' => 'rôle', 'mot_de_passe' => 'mot de passe',
        ]);
    }

    private function nbAdminsActifs(): int
    {
        return User::query()->where('actif', 1)->whereIn('role', ['admin'])->count();
    }

    private function instantane(User $u): array
    {
        return ['nom' => $u->nom, 'prenom' => $u->prenom, 'email' => $u->email, 'role' => $u->role, 'actif' => (bool) $u->actif];
    }
}
