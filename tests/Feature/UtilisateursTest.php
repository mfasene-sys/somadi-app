<?php

namespace Tests\Feature;

use App\Livewire\ListeUtilisateurs;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class UtilisateursTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chargerMatriceParDefaut();
        $this->admin = $this->utilisateur('admin', ['nom' => 'Chef']);
    }

    public function test_creation_d_un_compte_lisible_par_l_ancienne_application(): void
    {
        $this->actingAs($this->admin)->post('/administration/utilisateurs', [
            'nom' => 'Ndour', 'prenom' => 'Khady', 'email' => 'khady@somadi.test', 'role' => 'vendeur',
            'mot_de_passe' => 'Vente2026', 'mot_de_passe_confirmation' => 'Vente2026',
        ])->assertRedirect('/administration/utilisateurs');

        $ligne = DB::table('utilisateurs')->where('email', 'khady@somadi.test')->first();
        $this->assertSame('vendeur', $ligne->role);
        $this->assertSame(1, (int) $ligne->actif);
        // L'ancienne application vérifie avec password_verify : le hachage doit lui convenir.
        $this->assertTrue(password_verify('Vente2026', $ligne->mot_de_passe));
        $this->assertDatabaseHas('app_journal', ['action' => 'utilisateur.creation', 'objet_id' => (string) $ligne->id]);
        $this->assertStringNotContainsString('Vente2026', (string) DB::table('app_journal')->where('objet_id', $ligne->id)->value('apres'));
    }

    public function test_email_unique_et_mot_de_passe_robuste(): void
    {
        $existant = $this->utilisateur('ouvrier');
        $this->actingAs($this->admin)->post('/administration/utilisateurs', [
            'nom' => 'X', 'email' => $existant->email, 'role' => 'ouvrier', 'mot_de_passe' => 'court', 'mot_de_passe_confirmation' => 'court',
        ])->assertSessionHasErrors(['email', 'mot_de_passe']);
    }

    public function test_role_inconnu_refuse(): void
    {
        $this->actingAs($this->admin)->post('/administration/utilisateurs', [
            'nom' => 'X', 'email' => 'x@somadi.test', 'role' => 'comptable', 'mot_de_passe' => 'Abcdef12', 'mot_de_passe_confirmation' => 'Abcdef12',
        ])->assertSessionHasErrors('role');
    }

    public function test_modification_et_desactivation_journalisees(): void
    {
        $u = $this->utilisateur('ouvrier');
        $this->actingAs($this->admin)->put("/administration/utilisateurs/{$u->id}", [
            'nom' => 'Nouveau', 'prenom' => $u->prenom, 'email' => $u->email, 'role' => 'technicien', 'actif' => '0',
        ])->assertRedirect('/administration/utilisateurs');

        $u->refresh();
        $this->assertSame('technicien', $u->role);
        $this->assertFalse($u->actif);
        $j = DB::table('app_journal')->where('action', 'utilisateur.modification')->where('objet_id', $u->id)->first();
        $this->assertStringContainsString('ouvrier', $j->avant);
        $this->assertStringContainsString('technicien', $j->apres);
    }

    public function test_reinitialisation_du_mot_de_passe_par_l_admin(): void
    {
        $u = $this->utilisateur('ouvrier');
        $this->actingAs($this->admin)->put("/administration/utilisateurs/{$u->id}", [
            'nom' => $u->nom, 'email' => $u->email, 'role' => 'ouvrier', 'actif' => '1',
            'mot_de_passe' => 'Nouveau99', 'mot_de_passe_confirmation' => 'Nouveau99',
        ]);
        $this->assertTrue(password_verify('Nouveau99', DB::table('utilisateurs')->where('id', $u->id)->value('mot_de_passe')));
    }

    public function test_un_admin_ne_peut_pas_se_retirer_ses_propres_droits(): void
    {
        $this->actingAs($this->admin)->put("/administration/utilisateurs/{$this->admin->id}", [
            'nom' => 'Chef', 'email' => $this->admin->email, 'role' => 'ouvrier', 'actif' => '1',
        ])->assertSessionHasErrors('role');
        $this->assertSame('admin', $this->admin->fresh()->role);
    }

    public function test_il_reste_toujours_un_administrateur_actif(): void
    {
        DB::table('utilisateurs')->where('role', 'admin')->where('id', '!=', $this->admin->id)->update(['actif' => 0]);
        $autre = $this->utilisateur('gestionnaire');
        // Un gestionnaire promu admin peut gérer, mais le dernier admin ne peut être rétrogradé par un tiers.
        $chef2 = $this->utilisateur('admin');
        $this->actingAs($chef2)->put("/administration/utilisateurs/{$this->admin->id}", [
            'nom' => 'Chef', 'email' => $this->admin->email, 'role' => 'admin', 'actif' => '0',
        ])->assertRedirect();
        $this->assertFalse($this->admin->fresh()->actif);
        $this->actingAs($this->admin->fresh())->get('/')->assertRedirect('/connexion');
        $this->actingAs($chef2)->put("/administration/utilisateurs/{$chef2->id}", [
            'nom' => $chef2->nom, 'email' => $chef2->email, 'role' => 'admin', 'actif' => '0',
        ])->assertSessionHasErrors('role');
        $this->assertNotNull($autre);
    }

    public function test_recherche_instantanee_livewire(): void
    {
        $this->utilisateur('vendeur', ['nom' => 'Zidane', 'prenom' => 'Recherche']);
        $this->actingAs($this->admin);
        Livewire::test(ListeUtilisateurs::class)
            ->set('recherche', 'Zidane')->assertSee('Zidane')->assertDontSee('Chef')
            ->set('recherche', '')->set('role', 'admin')->assertSee('Chef')->assertDontSee('Zidane');
    }

    public function test_profil_changement_de_mot_de_passe_exige_l_actuel(): void
    {
        $u = $this->utilisateur('ouvrier');
        $this->actingAs($u)->put('/mon-profil/mot-de-passe', ['actuel' => 'faux', 'nouveau' => 'Autre1234', 'nouveau_confirmation' => 'Autre1234'])
            ->assertSessionHasErrors('actuel');
        $this->actingAs($u)->put('/mon-profil/mot-de-passe', ['actuel' => 'Secret123', 'nouveau' => 'Autre1234', 'nouveau_confirmation' => 'Autre1234'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(password_verify('Autre1234', DB::table('utilisateurs')->where('id', $u->id)->value('mot_de_passe')));
    }

    public function test_pages_principales_s_affichent(): void
    {
        $this->actingAs($this->admin);
        foreach (['/', '/administration/utilisateurs', '/administration/utilisateurs/nouveau', "/administration/utilisateurs/{$this->admin->id}", '/administration/droits', '/administration/journal', '/mon-profil', '/modules/pon', '/hors-ligne'] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
