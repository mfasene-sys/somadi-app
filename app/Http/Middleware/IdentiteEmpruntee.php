<?php

namespace App\Http\Middleware;

use App\Support\Connexion;
use App\Support\Identite;
use App\Support\Journal;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garde-fous du changement d'identité, à chaque requête :
 * - l'administrateur réel doit rester un administrateur actif, sinon la session est fermée ;
 * - si le compte utilisé est désactivé entre-temps, on revient automatiquement au compte de l'administrateur ;
 * - les réglages personnels du compte utilisé et la gestion des comptes et des droits sont bloqués.
 */
class IdentiteEmpruntee
{
    public function handle(Request $request, Closure $next): Response
    {
        $infos = Identite::infos($request);
        if (! $infos) {
            return $next($request);
        }

        $admin = Connexion::compteUtilisable((int) $infos['id']);
        if (! $admin || ! $admin->estAdmin()) {
            Journal::enregistrer('identite.interrompue', 'ACC', 'utilisateur', $infos['id'], apres: ['motif' => 'administrateur inactif ou sans droits']);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Session fermée : le compte administrateur n’est plus actif.'])->with('vider_cache_hors_ligne', true);
        }

        $user = $request->user();
        if (! $user || ! $user->actif || Identite::refus($admin, $user) !== null) {
            Identite::revenir($request);

            return redirect()->route('identite.index')->with('erreur', 'Le compte utilisé n’est plus disponible : vous êtes revenu à votre propre compte.');
        }

        if ($request->route()?->named(...Identite::ROUTES_BLOQUEES)) {
            $message = 'Action impossible sous une autre identité. Revenez à votre propre compte pour la faire.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }

            return $request->isMethod('GET')
                ? redirect()->route('accueil')->with('erreur', $message)
                : back()->with('erreur', $message);
        }

        return $next($request);
    }
}
