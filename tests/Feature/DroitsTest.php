<?php

namespace Tests\Feature;

use App\Support\Droits;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** EC02, EC03, REC-ACC-002 : matrice rôle × action vérifiée côté serveur. */
class DroitsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->chargerMatriceParDefaut();
    }

    public function test_appel_direct_d_une_action_administrative_par_un_role_d_execution_est_refuse(): void
    {
        $ouvrier = $this->utilisateur('ouvrier');
        $this->actingAs($ouvrier);
        $this->get('/administration/utilisateurs')->assertForbidden();
        $this->get('/administration/utilisateurs/nouveau')->assertForbidden();
        $this->post('/administration/utilisateurs', ['nom' => 'X', 'email' => 'x@x.test', 'role' => 'admin'])->assertForbidden();
        $this->get('/administration/droits')->assertForbidden();
        $this->put('/administration/droits', ['droits' => ['ouvrier' => ['ACC.gerer']]])->assertForbidden();
        $this->get('/administration/journal')->assertForbidden();
        $this->assertFalse(Droits::autorise($ouvrier, 'ACC.gerer'));
    }

    public function test_l_admin_a_tous_les_droits_y_compris_les_actions_reservees(): void
    {
        $admin = $this->utilisateur('admin');
        foreach (array_keys(Droits::catalogue()) as $p) {
            $this->assertTrue(Droits::autorise($admin, $p), $p);
        }
    }

    public function test_les_actions_reservees_a_l_admin_ne_peuvent_pas_etre_accordees(): void
    {
        $gest = $this->utilisateur('gestionnaire');
        DB::table('app_permissions')->insert(['role' => 'gestionnaire', 'permission' => 'EXE.cloturer', 'created_at' => now(), 'updated_at' => now()]);
        Droits::viderCache();
        $this->assertFalse(Droits::autorise($gest, 'EXE.cloturer'), 'Clôture comptable : admin uniquement (RG EXE)');

        [$ajout] = Droits::remplacer('gestionnaire', ['EXE.cloturer', 'ACC.droits', 'PIL.voir']);
        $this->assertNotContains('EXE.cloturer', $ajout);
        $this->assertNotContains('ACC.droits', $ajout);
    }

    public function test_le_menu_suit_la_matrice(): void
    {
        $ovin = $this->utilisateur('associe ovin');
        $this->actingAs($ovin)->get('/')->assertOk()->assertSee('Animaux')->assertDontSee('Utilisateurs et droits')->assertDontSee('Trésorerie');
    }

    public function test_ec03_la_compta_est_ouverte_au_gestionnaire_sauf_la_cloture(): void
    {
        $g = $this->utilisateur('gestionnaire');
        foreach (['CPT.voir', 'CPT.gerer', 'CPT.encaisser', 'EXE.voir', 'RAP.voir', 'RAP.gerer'] as $p) {
            $this->assertTrue(Droits::autorise($g, $p), $p);
        }
        $this->assertFalse(Droits::autorise($g, 'EXE.cloturer'));
    }

    public function test_gerant_equipe_existe_et_peut_se_connecter(): void
    {
        $g = $this->utilisateur('gerant_equipe');
        $this->actingAs($g)->get('/')->assertOk();
        $this->assertTrue(Droits::autorise($g, 'ACT.cloturer'));
    }

    public function test_alias_de_l_ancien_code_rabattus(): void
    {
        $this->assertSame('admin', \App\Support\Roles::canonique('administrateur'));
        $this->assertSame('gestionnaire', \App\Support\Roles::canonique('manager'));
    }

    public function test_un_module_non_autorise_reste_inaccessible_meme_par_url(): void
    {
        $vendeur = $this->utilisateur('vendeur');
        $this->actingAs($vendeur)->get('/modules/ven')->assertOk();
        $this->actingAs($vendeur)->get('/modules/ova')->assertForbidden();
        $this->actingAs($vendeur)->get('/modules/zzz')->assertNotFound();
    }

    public function test_l_admin_modifie_la_matrice_et_c_est_journalise(): void
    {
        $admin = $this->utilisateur('admin');
        $droits = Droits::matriceParDefaut();
        $droits['ouvrier'][] = 'VOL.gerer';
        $droits['vendeur'] = array_values(array_diff($droits['vendeur'], ['MKT.gerer']));

        $this->actingAs($admin)->put('/administration/droits', ['droits' => $droits])->assertRedirect('/administration/droits');

        $this->assertDatabaseHas('app_permissions', ['role' => 'ouvrier', 'permission' => 'VOL.gerer']);
        $this->assertDatabaseMissing('app_permissions', ['role' => 'vendeur', 'permission' => 'MKT.gerer']);
        $this->assertDatabaseHas('app_journal', ['action' => 'droits.modification', 'objet_id' => 'ouvrier']);
        $this->assertDatabaseHas('app_journal', ['action' => 'droits.modification', 'objet_id' => 'vendeur']);
    }

    public function test_la_table_permissions_modules_de_l_ancienne_application_n_est_pas_modifiee(): void
    {
        $avant = DB::table('permissions_modules')->count();
        $admin = $this->utilisateur('admin');
        $this->actingAs($admin)->put('/administration/droits', ['droits' => ['ouvrier' => ['PIL.voir']]]);
        $this->assertSame($avant, DB::table('permissions_modules')->count());
    }
}
