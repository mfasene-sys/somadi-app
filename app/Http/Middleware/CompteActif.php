<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Déconnecte immédiatement un compte désactivé ou au rôle inconnu, même avec une session ouverte. */
class CompteActif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && (! $user->actif || ! \App\Support\Roles::existe($user->roleCanonique()))) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Votre compte est inactif. Contactez un administrateur.']);
        }

        return $next($request);
    }
}
