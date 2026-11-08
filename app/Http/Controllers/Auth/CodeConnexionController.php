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
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Seconde étape de la connexion par mot de passe, au choix :
 * - code de l'application d'authentification (comptes qui l'ont activée : mode « appli ») ;
 * - code PIN reçu par e-mail (mode « email », ou sur demande depuis le mode « appli ») ;
 * - code de secours à usage unique (comptes ayant activé l'application), dans les deux modes.
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

        $appli = Authentificateur::actif($user);

        return view('auth.code', [
            'mode' => $appli ? ($request->session()->get(Connexion::ATTENTE)['mode'] ?? 'email') : 'email',
            'appli' => $appli,
            'secours' => $appli && Authentificateur::codesSecoursRestants($user) > 0,
            'emailPossible' => (bool) config('somadi.authentification.pin_email'),
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

        $attente = $request->session()->get(Connexion::ATTENTE);
        $mode = $attente['mode'] ?? 'email';
        $brut = (string) $request->input('code');

        // Limite par compte, toutes demandes de connexion confondues (application et codes de secours).
        $cleEssais = 'second-facteur:'.$user->id;
        if (RateLimiter::tooManyAttempts($cleEssais, 10)) {
            $minutes = (int) ceil(RateLimiter::availableIn($cleEssais) / 60);

            return $this->recommencer($request, "Trop de codes incorrects pour ce compte. Réessayez dans $minutes min.");
        }

        // Code de secours (10 caractères), accepté dans les deux modes.
        if (Authentificateur::normaliserSecours($brut) !== null) {
            if (Authentificateur::actif($user) && Authentificateur::utiliserCodeSecours($user, $brut)) {
                RateLimiter::clear($cleEssais);
                $reste = Authentificateur::codesSecoursRestants($user);
                Journal::enregistrer('connexion.code_secours', 'ACC', 'utilisateur', $user->id, apres: ['restants' => $reste], auteur: $user);
                CodeConnexion::invaliderTout($user->id);
                Connexion::ouvrir($request, $user, 'mot_de_passe+code_secours');

                return redirect()->intended(route('accueil'))->with($reste <= 3 ? 'erreur' : 'succes',
                    "Code de secours utilisé. Il vous en reste $reste.".($reste <= 3 ? ' Générez-en de nouveaux dans Mon profil.' : ''));
            }

            return $this->refuser($request, $user, $cleEssais, 'secours', 'Code de secours incorrect ou déjà utilisé.');
        }

        $saisi = preg_replace('/\D/', '', $brut);
        if ($mode === 'appli') {
            if (strlen($saisi) !== 6) {
                return back()->withErrors(['code' => 'Saisissez les 6 chiffres affichés par votre application.']);
            }
            if (Authentificateur::verifierCode($user, $saisi)) {
                RateLimiter::clear($cleEssais);
                Connexion::ouvrir($request, $user, 'mot_de_passe+application');

                return redirect()->intended(route('accueil'))->with('proposer_empreinte', true);
            }

            return $this->refuser($request, $user, $cleEssais, 'application', 'Code incorrect. Saisissez le code affiché en ce moment (il change toutes les 30 secondes) et vérifiez l’heure du téléphone.');
        }

        if (strlen($saisi) !== 6) {
            return back()->withErrors(['code' => 'Saisissez les 6 chiffres reçus par e-mail.']);
        }

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

        if (! config('somadi.authentification.pin_email')) {
            return back()->withErrors(['code' => 'L’envoi de codes par e-mail est désactivé pour le moment.']);
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
        $precedente = $request->session()->get(Connexion::ATTENTE);
        $request->session()->put(Connexion::ATTENTE, [
            'user_id' => $user->id, 'code_id' => $codeId, 'depuis' => now()->timestamp,
            'mode' => 'email', 'essais' => (int) ($precedente['essais'] ?? 0),
        ]);

        return back()->with('statut', ($precedente['mode'] ?? 'email') === 'appli' ? 'Un code vient de vous être envoyé par e-mail.' : 'Un nouveau code vient de vous être envoyé.');
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

    /** Revenir au code de l'application après avoir demandé un e-mail. */
    public function application(Request $request): RedirectResponse
    {
        $user = $this->enAttente($request);
        if (! $user) {
            return $this->recommencer($request, 'Votre demande de connexion a expiré. Saisissez à nouveau votre mot de passe.');
        }
        if (Authentificateur::actif($user)) {
            $attente = $request->session()->get(Connexion::ATTENTE);
            $request->session()->put(Connexion::ATTENTE, ['mode' => 'appli'] + $attente);
        }

        return redirect()->route('login.code');
    }

    /** Code de l'application ou de secours refusé : essai décompté dans la demande et pour le compte. */
    private function refuser(Request $request, User $user, string $cleEssais, string $type, string $message): RedirectResponse
    {
        RateLimiter::hit($cleEssais, 15 * 60);
        Journal::enregistrer('connexion.code_refuse', 'ACC', 'utilisateur', $user->id, apres: ['motif' => 'faux', 'type' => $type], auteur: $user);

        $attente = $request->session()->get(Connexion::ATTENTE);
        $attente['essais'] = (int) ($attente['essais'] ?? 0) + 1;
        if ($attente['essais'] >= CodeConnexion::tentativesMax()) {
            return $this->recommencer($request, 'Trop d’essais. Saisissez à nouveau votre mot de passe.');
        }
        $request->session()->put(Connexion::ATTENTE, $attente);

        return back()->withErrors(['code' => $message]);
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
