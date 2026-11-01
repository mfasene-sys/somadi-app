<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Contrôles de santé de la base (lecture seule) : connexion, tables attendues, moteur InnoDB (EC23),
 * rôles des utilisateurs (EC03) et présence d'au moins un administrateur actif.
 */
class Verifier extends Command
{
    protected $signature = 'somadi:verifier';

    protected $description = 'Vérifie la base de données sans rien modifier';

    /** Tables de production indispensables au socle. */
    private const TABLES_SOCLE = ['utilisateurs', 'permissions_modules'];

    public function handle(): int
    {
        $ok = true;
        try {
            $version = DB::selectOne('SELECT VERSION() AS v')->v;
            $this->info("Connexion MySQL : OK ($version)");
        } catch (\Throwable $e) {
            $this->error('Connexion MySQL impossible : '.$e->getMessage());

            return self::FAILURE;
        }

        $base = DB::getDatabaseName();
        $tables = collect(DB::select('SELECT TABLE_NAME AS n, ENGINE AS e FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = "BASE TABLE"', [$base]));
        $noms = $tables->pluck('n')->all();
        $this->line(count($noms).' tables trouvées dans « '.$base.' ».');

        foreach (array_merge(self::TABLES_SOCLE, ['app_permissions', 'app_journal']) as $t) {
            if (! in_array($t, $noms, true)) {
                $this->error("Table manquante : $t");
                $ok = false;
            }
        }

        $nonInnodb = $tables->filter(fn ($t) => strtoupper((string) $t->e) !== 'INNODB')->pluck('n');
        if ($nonInnodb->isEmpty()) {
            $this->info('Moteur InnoDB : toutes les tables (EC23 conforme).');
        } else {
            $this->warn('Tables hors InnoDB (EC23, transactions non garanties) : '.$nonInnodb->implode(', '));
            $this->line('  → À convertir après sauvegarde : ALTER TABLE `nom` ENGINE=InnoDB;');
        }

        // Lignes de verrou lues par les déclencheurs (emplacements V1.17, clôture comptable V1.16).
        foreach (['modInfra_verrou' => 'emplacements', 'modCompta_exercice_lock' => 'clôture comptable'] as $t => $role) {
            if (in_array($t, $noms, true) && ! DB::table($t)->where('id', 1)->exists()) {
                $this->error("Ligne de verrou absente dans $t (id = 1) : les contrôles de $role ne fonctionnent pas.");
                $ok = false;
            }
        }
        $cloture = in_array('modCompta_exercice_lock', $noms, true) ? DB::table('modCompta_exercice_lock')->where('id', 1)->value('closed_through') : null;
        $this->line('Période comptable clôturée jusqu’au : '.($cloture ?: 'aucune clôture'));

        $triggers = DB::selectOne('SELECT COUNT(*) AS n FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ?', [$base])->n;
        $this->line("Déclencheurs présents : $triggers (50 attendus d'après le dossier).");

        if (in_array('utilisateurs', $noms, true)) {
            $roles = array_keys(config('somadi.roles'));
            $inconnus = DB::table('utilisateurs')->whereNotIn('role', $roles)->count();
            if ($inconnus) {
                $this->warn("$inconnus utilisateur(s) avec un rôle inconnu : ils ne pourront pas se connecter.");
            }
            $admins = DB::table('utilisateurs')->where('role', 'admin')->where('actif', 1)->count();
            if ($admins === 0) {
                $this->error('Aucun administrateur actif. Utilisez : php artisan somadi:creer-admin');
                $ok = false;
            } else {
                $this->info("Administrateurs actifs : $admins");
            }
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
