<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Droits;
use App\Support\Identite;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Changement d'identité : un administrateur agit sous le compte d'un autre utilisateur, puis revient au sien.
 */
class IdentiteTest extends TestCase
{
    private User $admin;

    private User $ouvrier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chargerMatriceParDefaut();
        $this->admin = $this->utilisateur('admin', ['nom' => 'Chef', 'prenom' => 'Awa']);
        $this->ouvrier = $this->utilisateur('ouvrier', ['nom' => 'Diop', 'prenom' => 'Modou']);
    }

    private function prendre(User $cible): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->post("/identite/{$cible->id}");
    }

    public function test_l_admin_prend_l_identite_et_voit_l_application_comme_l_utilisateur(): void
    {
        $this->prendre($this->ouvrier)->assertRedirect('/')->assertSessionHas(Identite::SESSION);
        $this->assertAuthenticatedAs($this->ouvrier);

        // Droits de l'ouvrier, et non de l'administrateur.
        $this->get('/volailles/ponte')->assertOk();
        $this->get('/administration/utilisateurs')->assertForbidden();
        $this->get('/')->assertOk()->assertSee('Vous agissez en tant que')->assertSee('Modou Diop')->assertSee('Revenir à mon compte');

        $j = DB::table('app_journal')->where('action', 'identite.debut')->first();
        $this->assertSame($this->ouvrier->id, (int) $j->user_id);
        $this->assertSame($this->admin->id, (int) $j->agi_par_id);
        $this->assertSame('Awa Chef', $j->agi_par_nom);
        // Clés de session de l'ancienne application alignées sur l'identité utilisée.
        $this->assertSame($this->ouvrier->id, session('id'));
        $this->assertSame('ouvrier', session('role'));
    }

    public function test_les_actions_sous_l_identite_sont_tracees_avec_l_admin_reel(): void
    {
        $this->prendre($this->ouvrier);
        $this->post('/deconnexion')->assertRedirect('/connexion');

        $j = DB::table('app_journal')->where('action', 'deconnexion')->first();
        $this->assertSame($this->ouvrier->id, (int) $j->user_id);
        $this->assertSame($this->admin->id, (int) $j->agi_par_id);
        $this->assertGuest();
    }

    public function test_retour_au_compte_de_l_admin(): void
    {
        $this->prendre($this->ouvrier);
        $this->post('/identite/revenir')->assertRedirect('/identite')->assertSessionMissing(Identite::SESSION);
        $this->assertAuthenticatedAs($this->admin);
        $this->get('/administration/utilisateurs')->assertOk();
        $this->get('/')->assertDontSee('Vous agissez en tant que');

        $fin = DB::table('app_journal')->where('action', 'identite.fin')->first();
        $this->assertSame($this->ouvrier->id, (int) $fin->user_id);
        $this->assertSame($this->admin->id, (int) $fin->agi_par_id);
    }

    public function test_changement_direct_d_une_identite_a_une_autre(): void
    {
        $vendeur = $this->utilisateur('vendeur');
        $this->prendre($this->ouvrier);
        $this->post("/identite/{$vendeur->id}")->assertRedirect('/');
        $this->assertAuthenticatedAs($vendeur);
        $this->assertSame($this->admin->id, session(Identite::SESSION)['id']);
        $this->assertDatabaseHas('app_journal', ['action' => 'identite.fin', 'user_id' => $this->ouvrier->id, 'agi_par_id' => $this->admin->id]);
        $this->assertDatabaseHas('app_journal', ['action' => 'identite.debut', 'user_id' => $vendeur->id, 'agi_par_id' => $this->admin->id]);

        // Choisir son propre compte revient à « Revenir ».
        $this->post("/identite/{$this->admin->id}")->assertRedirect('/identite');
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_comptes_interdits(): void
    {
        $autreAdmin = $this->utilisateur('admin');
        $inactif = $this->utilisateur('ouvrier', ['actif' => 0]);

        foreach ([$autreAdmin, $inactif, $this->admin] as $cible) {
            $this->actingAs($this->admin)->from('/identite')->post("/identite/{$cible->id}");
            $this->assertAuthenticatedAs($this->admin);
            $this->assertNull(session(Identite::SESSION));
        }

        $this->actingAs($this->admin)->get('/identite')->assertOk()
            ->assertSee($this->ouvrier->nomComplet())
            ->assertDontSee($autreAdmin->nomComplet())
            ->assertDontSee($inactif->nomComplet());
    }

    public function test_reserve_a_l_administrateur(): void
    {
        $gestionnaire = $this->utilisateur('gestionnaire');
        $this->actingAs($gestionnaire)->get('/identite')->assertForbidden();
        $this->actingAs($gestionnaire)->post("/identite/{$this->ouvrier->id}")->assertForbidden();
        $this->assertAuthenticatedAs($gestionnaire);

        // L'action ne peut pas être accordée à un autre rôle par la matrice.
        Droits::remplacer('gestionnaire', ['ACC.voir', 'ACC.identite']);
        $this->assertFalse(Droits::autorise($gestionnaire->fresh(), 'ACC.identite'));
    }

    public function test_reglages_personnels_et_gestion_des_comptes_bloques_sous_une_autre_identite(): void
    {
        $hash = DB::table('utilisateurs')->where('id', $this->ouvrier->id)->value('mot_de_passe');
        $this->prendre($this->ouvrier);

        $this->from('/mon-profil')->put('/mon-profil/mot-de-passe', [
            'actuel' => 'Secret123', 'nouveau' => 'Nouveau123', 'nouveau_confirmation' => 'Nouveau123',
        ])->assertRedirect('/mon-profil')->assertSessionHas('erreur');
        $this->from('/mon-profil')->put('/mon-profil', ['nom' => 'Pirate'])->assertSessionHas('erreur');

        $this->assertSame($hash, DB::table('utilisateurs')->where('id', $this->ouvrier->id)->value('mot_de_passe'));
        $this->assertSame('Diop', DB::table('utilisateurs')->where('id', $this->ouvrier->id)->value('nom'));
        $this->assertAuthenticatedAs($this->ouvrier);
    }

    public function test_compte_utilise_desactive_pendant_la_session_retour_automatique(): void
    {
        $this->prendre($this->ouvrier);
        DB::table('utilisateurs')->where('id', $this->ouvrier->id)->update(['actif' => 0]);

        $this->get('/volailles/ponte')->assertRedirect('/identite');
        $this->assertAuthenticatedAs($this->admin);
        $this->assertNull(session(Identite::SESSION));
    }

    public function test_admin_desactive_pendant_la_session_deconnexion(): void
    {
        $this->prendre($this->ouvrier);
        DB::table('utilisateurs')->where('id', $this->admin->id)->update(['actif' => 0]);

        $this->get('/volailles/ponte')->assertRedirect('/connexion');
        $this->assertGuest();
    }

    public function test_bouton_sur_la_fiche_utilisateur(): void
    {
        $this->actingAs($this->admin)->get("/administration/utilisateurs/{$this->ouvrier->id}")
            ->assertOk()->assertSee('Prendre son identité');
        $this->actingAs($this->admin)->get("/administration/utilisateurs/{$this->admin->id}")
            ->assertOk()->assertDontSee('Prendre son identité');
    }

    public function test_journal_filtre_par_utilisateur_inclut_ce_qui_est_fait_sous_une_autre_identite(): void
    {
        $this->prendre($this->ouvrier);
        $this->post('/identite/revenir');

        $this->get('/administration/journal?utilisateur='.$this->admin->id)->assertOk()
            ->assertSee('identite.debut')->assertSee('sous cette identité');
    }
}
