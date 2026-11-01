<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** REC-ACC-001, RG-ACC-001, RG-ACC-002 */
class ConnexionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->chargerMatriceParDefaut();
    }

    public function test_la_page_de_connexion_s_affiche_en_francais(): void
    {
        $this->get('/connexion')->assertOk()->assertSee('Adresse e-mail')->assertSee('Se connecter');
    }

    public function test_un_visiteur_est_redirige_vers_la_connexion(): void
    {
        $this->get('/')->assertRedirect('/connexion');
        $this->get('/administration/utilisateurs')->assertRedirect('/connexion');
    }

    public function test_compte_actif_et_mot_de_passe_hache_par_l_ancienne_application(): void
    {
        $u = $this->utilisateur('ouvrier');
        $this->post('/connexion', ['email' => $u->email, 'mot_de_passe' => 'Secret123'])->assertRedirect('/');
        $this->assertAuthenticatedAs($u);
        // Clés de session de l'ancienne application conservées (RG-ACC-002).
        $this->assertSame($u->id, session('id'));
        $this->assertSame('ouvrier', session('role'));
        $this->assertDatabaseHas('app_journal', ['action' => 'connexion.reussie', 'user_id' => $u->id]);
    }

    public function test_le_hachage_n_est_pas_reecrit_a_la_connexion(): void
    {
        $u = $this->utilisateur();
        $avant = DB::table('utilisateurs')->where('id', $u->id)->value('mot_de_passe');
        $this->post('/connexion', ['email' => $u->email, 'mot_de_passe' => 'Secret123']);
        $this->assertSame($avant, DB::table('utilisateurs')->where('id', $u->id)->value('mot_de_passe'));
    }

    public function test_mauvais_mot_de_passe_et_compte_inconnu_donnent_le_meme_message(): void
    {
        $u = $this->utilisateur();
        $msg = 'Adresse e-mail ou mot de passe incorrect, ou compte inactif.';

        $this->from('/connexion')->post('/connexion', ['email' => $u->email, 'mot_de_passe' => 'mauvais'])
            ->assertRedirect('/connexion')->assertSessionHasErrors(['email' => $msg]);
        $this->from('/connexion')->post('/connexion', ['email' => 'personne@somadi.test', 'mot_de_passe' => 'mauvais'])
            ->assertSessionHasErrors(['email' => $msg]);
        $this->assertGuest();
        $this->assertDatabaseHas('app_journal', ['action' => 'connexion.echec']);
    }

    public function test_compte_inactif_refuse_avec_le_message_generique(): void
    {
        $u = $this->utilisateur('admin', ['actif' => 0]);
        $this->post('/connexion', ['email' => $u->email, 'mot_de_passe' => 'Secret123'])
            ->assertSessionHasErrors(['email' => 'Adresse e-mail ou mot de passe incorrect, ou compte inactif.']);
        $this->assertGuest();
    }

    public function test_champs_obligatoires_et_email_valide(): void
    {
        $this->post('/connexion', ['email' => 'pas-un-email', 'mot_de_passe' => ''])
            ->assertSessionHasErrors(['email', 'mot_de_passe']);
    }

    public function test_compte_desactive_pendant_la_session_est_deconnecte(): void
    {
        $u = $this->utilisateur('gestionnaire');
        $this->actingAs($u)->get('/')->assertOk();
        DB::table('utilisateurs')->where('id', $u->id)->update(['actif' => 0]);
        $this->actingAs($u->fresh())->get('/')->assertRedirect('/connexion');
    }

    public function test_limitation_des_tentatives(): void
    {
        $u = $this->utilisateur();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/connexion', ['email' => $u->email, 'mot_de_passe' => 'faux'.$i]);
        }
        $this->post('/connexion', ['email' => $u->email, 'mot_de_passe' => 'Secret123'])->assertStatus(429);
    }

    public function test_deconnexion(): void
    {
        $u = $this->utilisateur();
        $this->actingAs($u)->post('/deconnexion')->assertRedirect('/connexion');
        $this->assertGuest();
    }

    public function test_entetes_de_securite(): void
    {
        $this->get('/connexion')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
