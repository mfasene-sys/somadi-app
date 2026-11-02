<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Droits;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bibliothèque WebAuthn (empreinte) livrée par mise à jour différentielle dans vendor/lbuchs/webauthn,
        // sans régénérer le chargeur de Composer. Après un « composer install », Composer la charge lui-même
        // et ce chargeur de secours n'est jamais sollicité.
        if (! class_exists(\lbuchs\WebAuthn\WebAuthn::class)) {
            spl_autoload_register(function (string $classe): void {
                if (str_starts_with($classe, 'lbuchs\\WebAuthn\\')) {
                    $fichier = base_path('vendor/lbuchs/webauthn/src/'.str_replace('\\', '/', substr($classe, strlen('lbuchs\\WebAuthn\\'))).'.php');
                    if (is_file($fichier)) {
                        require $fichier;
                    }
                }
            });
        }
    }

    public function boot(): void
    {
        // La base est partagée avec l'ancienne application : migrate:fresh, migrate:reset, db:wipe… sont interdits en production.
        DB::prohibitDestructiveCommands($this->app->isProduction());

        // Toute capacité de la forme « CODE.action » est tranchée par la matrice des droits.
        Gate::before(function (?User $user, string $ability) {
            if (str_contains($ability, '.') && Droits::existe($ability)) {
                return Droits::autorise($user, $ability);
            }

            return null;
        });

        RateLimiter::for('connexion', function (Request $request) {
            $email = mb_strtolower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by('email:'.$email.'|'.$request->ip()),
                Limit::perMinute(20)->by('ip:'.$request->ip()),
            ];
        });

        // Saisie du code PIN : au plus 10 envois par minute et par session (chaque code n'accepte de toute façon que 5 essais).
        RateLimiter::for('code_connexion', fn (Request $request) => Limit::perMinute(10)->by('code:'.$request->session()->getId().'|'.$request->ip()));

        // Empreinte : connexion, et activation (qui redemande le mot de passe).
        RateLimiter::for('empreinte', fn (Request $request) => Limit::perMinute(10)->by('empreinte:'.($request->user()?->id ?? $request->ip())));

        Paginator::useTailwind();

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
