<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CodeConnexion;
use App\Support\Connexion;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Seconde étape de la connexion par mot de passe : saisie du code PIN reçu par e-mail.
 * La connexion en attente est gardée en session ; elle expire avec le code.
 */
class CodeConnexionController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $user = $this->enAttente($request);
        if (! $user) {
            return $this->recommencer($request, 'Votre demande de connexion a expiré. Saisissez à nouveau votre mot de passe.');
        }

        return view('auth.code', [
            'email' => CodeConnexion::emailMasque((string) $user->email),
            'minutes' => CodeConnexion::duree(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->enAttente($request);
        if (! $user) {
            return $this->recommencer($request, 'Votre demande de connexion a expiré. Saisissez à nouveau votre mot de passe.');
        }

        $saisi = preg_replace('/\D/', '', (string) $request->input('code'));
        if (strlen($saisi) !== 6) {
            return back()->withErrors(['code' => 'Saisissez les 6 chiffres reçus par e-mail.']);
        }

        $attente = $request->session()->get(Connexion::ATTENTE);
        $resultat = CodeConnexion::verifier((int) $attente['code_id'], $user->id, $saisi);

        if ($resultat === 'ok') {
            Connexion::ouvrir($request, $user, 'mot_de_passe+code');

            return redirect()->intended(route('accueil'))->with('proposer_empreinte', true);
        }

        Journal::enregistrer('connexion.code_refuse', 'ACC', 'utilisateur', $user->id, apres: ['motif' => $resultat], auteur: $user);

        return match ($resultat) {
            'faux' => back()->withErrors(['code' => 'Code incorrect. Vérifiez le dernier e-mail reçu.']),
            'epuise' => $this->recommencer($request, 'Trop d’essais avec ce code. Saisissez à nouveau votre mot de passe pour en recevoir un nouveau.'),
            default => back()->withErrors(['code' => 'Ce code a expiré. Demandez-en un nouveau.']),
        };
    }

    public function renvoyer(Request $request): RedirectResponse
    {
        $user = $this->enAttente($request);
        if (! $user) {
            return $this->recommencer($request, 'Votre demande de connexion a expiré. Saisissez à nouveau votre mot de passe.');
        }

        $cle = 'code-renvoi:'.$user->id;
        $max = (int) config('somadi.authentification.pin_renvois', 3);
        if (RateLimiter::tooManyAttempts($cle, $max)) {
            $minutes = (int) ceil(RateLimiter::availableIn($cle) / 60);

            return back()->withErrors(['code' => "Trop de codes demandés. Patientez $minutes min ou vérifiez vos courriers indésirables."]);
        }
        if ($attente = CodeConnexion::attenteAvantEmission($user)) {
            return back()->withErrors(['code' => "Trop de codes demandés pour ce compte. Réessayez dans $attente min."]);
        }
        RateLimiter::hit($cle, 15 * 60);

        $codeId = CodeConnexion::emettre($user);
        if (! $codeId) {
            return back()->withErrors(['code' => 'Le code n’a pas pu être envoyé. Réessayez dans un instant ou prévenez un administrateur.']);
        }
        $request->session()->put(Connexion::ATTENTE, ['user_id' => $user->id, 'code_id' => $codeId, 'depuis' => now()->timestamp]);

        return back()->with('statut', 'Un nouveau code vient de vous être envoyé.');
    }

    public function annuler(Request $request): RedirectResponse
    {
        $attente = $request->session()->get(Connexion::ATTENTE);
        if ($attente) {
            CodeConnexion::invaliderTout((int) $attente['user_id']);
        }
        $request->session()->forget(Connexion::ATTENTE);

        return redirect()->route('login');
    }

    /** Compte dont le mot de passe a été vérifié il y a moins que la durée du code, et toujours actif. */
    private function enAttente(Request $request): ?User
    {
        $attente = $request->session()->get(Connexion::ATTENTE);
        if (! is_array($attente) || now()->timestamp - (int) ($attente['depuis'] ?? 0) > CodeConnexion::duree() * 60) {
            return null;
        }

        return Connexion::compteUtilisable((int) $attente['user_id']);
    }

    private function recommencer(Request $request, string $message): RedirectResponse
    {
        $request->session()->forget(Connexion::ATTENTE);

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
