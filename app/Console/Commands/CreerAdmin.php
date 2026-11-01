<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Journal;
use Illuminate\Console\Command;

class CreerAdmin extends Command
{
    protected $signature = 'somadi:creer-admin {email} {--nom=Administrateur} {--prenom=}';

    protected $description = 'Crée un compte administrateur (ou réactive et promeut un compte existant), mot de passe demandé de façon masquée';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Adresse e-mail invalide.');

            return self::FAILURE;
        }
        $mdp = (string) $this->secret('Mot de passe (8 caractères minimum, lettres et chiffres)');
        if (mb_strlen($mdp) < 8 || ! preg_match('/\pL/u', $mdp) || ! preg_match('/\d/', $mdp)) {
            $this->error('Mot de passe trop faible.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first() ?? new User(['email' => $email, 'nom' => $this->option('nom'), 'prenom' => $this->option('prenom') ?: null]);
        $avant = $user->exists ? ['role' => $user->role, 'actif' => (bool) $user->actif] : null;
        $user->role = 'admin';
        $user->actif = true;
        $user->definirMotDePasse($mdp);
        $user->save();
        Journal::enregistrer('utilisateur.admin_console', 'ACC', 'utilisateur', $user->id, $avant, ['role' => 'admin', 'actif' => true], 'Commande somadi:creer-admin');

        $this->info("Administrateur prêt : $email");

        return self::SUCCESS;
    }
}
