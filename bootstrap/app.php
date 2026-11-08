<?php

use App\Http\Middleware\CompteActif;
use App\Exceptions\RegleMetier;
use App\Http\Middleware\EntetesSecurite;
use App\Http\Middleware\IdentiteEmpruntee;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [EntetesSecurite::class, IdentiteEmpruntee::class]);
        $middleware->alias(['compte.actif' => CompteActif::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('accueil'));
        // OVH place l'application derrière ses frontaux : on fait confiance aux en-têtes X-Forwarded-*.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // Règle de gestion refusée : la transaction est annulée, le formulaire est réaffiché avec le message.
        $exceptions->render(function (RegleMetier $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withInput()->with('erreur', $e->getMessage());
        });
        $exceptions->dontReport([RegleMetier::class]);
        // Formulaire expiré (jeton CSRF) : message clair plutôt qu'une page 419.
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if (! $request->expectsJson()) {
                return back()->withInput($request->except(['mot_de_passe', 'mot_de_passe_confirmation', 'actuel', 'nouveau', 'nouveau_confirmation', '_token']))
                    ->with('erreur', 'Votre formulaire a expiré. Veuillez réessayer.');
            }
        });
    })->create();
