<?php

namespace Tests;

use App\Models\User;
use App\Support\Droits;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Droits::viderCache();
        $this->withoutVite();
    }

    /** Crée un compte comme le ferait l'ancienne application (password_hash). */
    protected function utilisateur(string $role = 'ouvrier', array $attrs = [], string $mdp = 'Secret123'): User
    {
        static $n = 0;
        $n++;
        $id = DB::table('utilisateurs')->insertGetId(array_merge([
            'nom' => 'Test'.$n,
            'prenom' => 'Prénom'.$n,
            'email' => "u{$n}_".uniqid().'@somadi.test',
            'mot_de_passe' => password_hash($mdp, PASSWORD_DEFAULT),
            'role' => $role,
            'actif' => 1,
        ], $attrs));

        return User::findOrFail($id);
    }

    protected function chargerMatriceParDefaut(): void
    {
        DB::table('app_permissions')->delete();
        foreach (Droits::matriceParDefaut() as $role => $perms) {
            foreach ($perms as $p) {
                DB::table('app_permissions')->insert(['role' => $role, 'permission' => $p, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        Droits::viderCache();
    }
}
