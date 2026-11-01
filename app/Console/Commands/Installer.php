<?php

namespace App\Console\Commands;

use App\Support\Droits;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Installe ou met à jour la nouvelle application sur la base existante :
 * crée uniquement les tables app_* et charge la matrice des droits par défaut si elle est vide.
 */
class Installer extends Command
{
    protected $signature = 'somadi:installer {--force : Exécuter sans confirmation (production)}';

    protected $description = 'Crée les tables app_* et la matrice des droits par défaut, sans toucher aux tables existantes';

    public function handle(): int
    {
        $this->info('Tables de la nouvelle application (préfixe app_)…');
        $this->call('migrate', ['--force' => (bool) $this->option('force') || ! $this->laravel->isProduction()]);

        if (DB::table('app_permissions')->count() === 0) {
            $now = now();
            $total = 0;
            foreach (Droits::matriceParDefaut() as $role => $perms) {
                foreach ($perms as $p) {
                    DB::table('app_permissions')->insert(['role' => $role, 'permission' => $p, 'created_at' => $now, 'updated_at' => $now]);
                    $total++;
                }
            }
            $this->info("Matrice des droits par défaut chargée ($total autorisations).");
        } else {
            $this->line('Matrice des droits déjà présente : conservée telle quelle.');
        }

        $this->newLine();
        $this->call('somadi:verifier');

        return self::SUCCESS;
    }
}
