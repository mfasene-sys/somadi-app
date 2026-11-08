<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Authentificateur;
use App\Support\Journal;
use App\Support\QrCode;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Mon profil → application d'authentification : activation par QR code (confirmée par un premier code
 * et le mot de passe), codes de secours, désactivation. Retrait par l'administrateur (téléphone perdu).
 */
class AuthentificateurController extends Controller
{
    /** Clé en cours d'activation, gardée en session jusqu'à la confirmation (le QR code reste le même si on recharge). */
    private const CLE_EN_COURS = 'authentificateur_cle';

    public function create(Request $request): View|RedirectResponse
    {
        abort_unless(Authentificateur::disponible(), 404);
        $user = $request->user();
        if (Authentificateur::actif($user)) {
            return redirect()->route('profil.edit')->withFragment('authentificateur');
        }
        $cle = $request->session()->get(self::CLE_EN_COURS);
        if (! is_string($cle) || strlen($cle) !== 32) {
            $cle = Totp::nouvelleCle();
            $request->session()->put(self::CLE_EN_COURS, $cle);
        }

        return view('profil.authentificateur', [
            'qr' => QrCode::svg(Totp::uri($cle, (string) $user->email, Authentificateur::emetteur())),
            'cle' => Totp::cleLisible($cle),
        ]);
    }

    public function activer(Request $request): RedirectResponse
    {
        abort_unless(Authentificateur::disponible(), 404);
        $user = $request->user();
        $d = $request->validate([
            'code' => ['required', 'string', 'max:10'],
            'mot_de_passe' => ['required', 'string', 'max:200'],
        ], [], ['code' => 'code', 'mot_de_passe' => 'mot de passe']);

        $cle = $request->session()->get(self::CLE_EN_COURS);
        if (! is_string($cle)) {
            return redirect()->route('profil.authentificateur.create')->with('erreur', 'La page a expiré : scannez le nouveau QR code.');
        }
        if (! $user->verifierMotDePasse($d['mot_de_passe'])) {
            return back()->withErrors(['mot_de_passe' => 'Mot de passe incorrect.']);
        }
        $pas = Totp::pasValide($cle, preg_replace('/\D/', '', $d['code']));
        if ($pas === null) {
            return back()->withErrors(['code' => 'Code incorrect. Saisissez le code affiché en ce moment par l’application, et vérifiez que l’heure du téléphone est automatique.']);
        }

        $codes = Authentificateur::activer($user, $cle, $pas);
        $request->session()->forget(self::CLE_EN_COURS);
        Journal::enregistrer('authentificateur.activation', 'ACC', 'utilisateur', $user->id);

        return redirect()->route('profil.authentificateur.codes')->with('codes_secours', $codes)
            ->with('succes', 'Application d’authentification activée. À la prochaine connexion, elle remplacera le code reçu par e-mail.');
    }

    /** Codes de secours : affichés une seule fois, juste après leur création. */
    public function codes(Request $request): View|RedirectResponse
    {
        $codes = $request->session()->get('codes_secours');
        if (! is_array($codes) || $codes === []) {
            return redirect()->route('profil.edit')->withFragment('authentificateur');
        }

        return view('profil.codes-secours', ['codes' => $codes, 'user' => $request->user()]);
    }

    public function regenerer(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($refus = $this->verifierMotDePasse($request, $user)) {
            return $refus;
        }
        if (! Authentificateur::actif($user)) {
            return redirect()->route('profil.edit');
        }
        $codes = Authentificateur::genererCodesSecours($user);
        Journal::enregistrer('authentificateur.codes_secours', 'ACC', 'utilisateur', $user->id, apres: ['nombre' => count($codes)]);

        return redirect()->route('profil.authentificateur.codes')->with('codes_secours', $codes)
            ->with('succes', 'Nouveaux codes de secours créés : les anciens ne fonctionnent plus.');
    }

    public function desactiver(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($refus = $this->verifierMotDePasse($request, $user)) {
            return $refus;
        }
        if (Authentificateur::desactiver($user)) {
            Journal::enregistrer('authentificateur.desactivation', 'ACC', 'utilisateur', $user->id);
        }

        return redirect()->route('profil.edit')->with('succes', 'Application d’authentification désactivée : le code sera de nouveau envoyé par e-mail.');
    }

    /** Administrateur : retrait pour un téléphone perdu ou un départ. */
    public function retirerAdmin(User $utilisateur): RedirectResponse
    {
        if (! Authentificateur::desactiver($utilisateur)) {
            return back()->with('succes', 'Aucune application d’authentification à retirer.');
        }
        Journal::enregistrer('authentificateur.retrait_admin', 'ACC', 'utilisateur', $utilisateur->id);

        return back()->with('succes', "Application d’authentification et codes de secours retirés pour {$utilisateur->nomComplet()}. Le code lui sera de nouveau envoyé par e-mail.");
    }

    private function verifierMotDePasse(Request $request, User $user): ?RedirectResponse
    {
        $d = $request->validate(['mot_de_passe' => ['required', 'string', 'max:200']], [], ['mot_de_passe' => 'mot de passe']);
        if (! $user->verifierMotDePasse($d['mot_de_passe'])) {
            return redirect()->to(route('profil.edit').'#authentificateur')->withErrors(['mot_de_passe_authentificateur' => 'Mot de passe incorrect.']);
        }

        return null;
    }
}
