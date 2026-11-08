<?php

namespace Tests\Feature;

use App\Mail\CodeConnexionMail;
use App\Models\User;
use App\Support\Authentificateur;
use App\Support\QrCode;
use App\Support\Totp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Application d'authentification (TOTP) et codes de secours.
 */
class AuthentificateurTest extends TestCase
{
    private const CLE = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    protected function setUp(): void
    {
        parent::setUp();
        $this->chargerMatriceParDefaut();
        Mail::fake();
        config(['somadi.authentification.pin_email' => true, 'somadi.authentification.authentificateur' => true]);
    }

    /** Compte avec l'application déjà activée ; renvoie ses codes de secours. */
    private function avecApplication(User $u): array
    {
        return Authentificateur::activer($u, self::CLE, Totp::pas() - 10);
    }

    private function motDePasse(User $u)
    {
        return $this->post('/connexion', ['email' => $u->email, 'mot_de_passe' => 'Secret123']);
    }

    // ---------- Calculs ----------

    public function test_vecteurs_officiels_rfc_6238(): void
    {
        $cle = Totp::base32('12345678901234567890');
        $this->assertSame('94287082', Totp::code($cle, Totp::pas(59), 8));
        $this->assertSame('14050471', Totp::code($cle, Totp::pas(1111111111), 8));
        $this->assertSame('65353130', Totp::code($cle, Totp::pas(20000000000), 8));
        $this->assertSame('287082', Totp::code($cle, 1));
    }

    public function test_qr_code_svg(): void
    {
        $svg = QrCode::svg(Totp::uri(self::CLE, 'a@somadi.test', 'SOMADI Farming'));
        $this->assertStringStartsWith('<svg', $svg);
        $this->assertCount(45, QrCode::matrice(str_repeat('x', 120))); // version 7
        $this->assertCount(57, QrCode::matrice(str_repeat('x', 213))); // version 10, maximum
    }

    // ---------- Activation ----------

    public function test_activation_par_qr_code_puis_codes_de_secours(): void
    {
        $u = $this->utilisateur('technicien');
        $this->actingAs($u)->get('/mon-profil/authentificateur')->assertOk()->assertSee('<svg', false);
        $cle = session('authentificateur_cle');
        $this->assertSame(32, strlen($cle));

        // Mauvais mot de passe, puis mauvais code : rien n'est activé.
        $this->post('/mon-profil/authentificateur', ['code' => Totp::code($cle, Totp::pas()), 'mot_de_passe' => 'faux'])->assertSessionHasErrors('mot_de_passe');
        $this->post('/mon-profil/authentificateur', ['code' => '000000', 'mot_de_passe' => 'Secret123'])->assertSessionHasErrors('code');
        $this->assertFalse(Authentificateur::actif($u));

        $this->post('/mon-profil/authentificateur', ['code' => Totp::code($cle, Totp::pas()), 'mot_de_passe' => 'Secret123'])
            ->assertRedirect('/mon-profil/authentificateur/codes');
        $this->assertTrue(Authentificateur::actif($u));
        $this->assertSame(10, Authentificateur::codesSecoursRestants($u));
        $codes = session('codes_secours');
        $this->assertCount(10, $codes);
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{5}-[A-Z2-9]{5}$/', $codes[0]);

        // La clé est chiffrée, les codes de secours ne sont pas stockés en clair.
        $this->assertNotSame($cle, DB::table('app_authentificateurs')->where('user_id', $u->id)->value('cle_chiffree'));
        $this->assertDatabaseMissing('app_codes_secours', ['code_hash' => str_replace('-', '', $codes[0])]);
        $this->assertDatabaseHas('app_journal', ['action' => 'authentificateur.activation', 'user_id' => $u->id]);

        $this->get('/mon-profil/authentificateur/codes')->assertOk()->assertSee($codes[0]);
    }

    // ---------- Connexion ----------

    public function test_connexion_avec_l_application_sans_e_mail(): void
    {
        $u = $this->utilisateur('gestionnaire');
        $this->avecApplication($u);

        $this->motDePasse($u)->assertRedirect('/connexion/code');
        Mail::assertNothingSent();
        $this->get('/connexion/code')->assertOk()->assertSee('Code de votre application')->assertSee('Recevoir plutôt un code par e-mail');
        $this->assertGuest();

        $this->post('/connexion/code', ['code' => Totp::code(self::CLE, Totp::pas())])->assertRedirect('/');
        $this->assertAuthenticatedAs($u);
        $this->assertDatabaseHas('app_journal', ['action' => 'connexion.reussie', 'user_id' => $u->id]);
    }

    public function test_un_code_de_l_application_ne_sert_qu_une_fois(): void
    {
        $u = $this->utilisateur('gestionnaire');
        $this->avecApplication($u);
        $code = Totp::code(self::CLE, Totp::pas());
        $this->assertTrue(Authentificateur::verifierCode($u, $code));
        $this->assertFalse(Authentificateur::verifierCode($u, $code));
    }

    public function test_mauvais_codes_puis_trop_d_essais(): void
    {
        $u = $this->utilisateur('gestionnaire');
        $this->avecApplication($u);
        $this->motDePasse($u);
        $faux = Totp::code(self::CLE, Totp::pas()) === '123456' ? '654321' : '123456';
        for ($i = 0; $i < 4; $i++) {
            $this->from('/connexion/code')->post('/connexion/code', ['code' => $faux])->assertRedirect('/connexion/code')->assertSessionHasErrors('code');
        }
        $this->from('/connexion/code')->post('/connexion/code', ['code' => $faux])->assertRedirect('/connexion');
        $this->assertGuest();
        $this->assertDatabaseHas('app_journal', ['action' => 'connexion.code_refuse', 'user_id' => $u->id]);
    }

    public function test_repli_sur_l_e_mail_puis_retour_a_l_application(): void
    {
        $u = $this->utilisateur('gestionnaire');
        $this->avecApplication($u);
        $this->motDePasse($u);

        $this->from('/connexion/code')->post('/connexion/code/renvoyer')->assertRedirect('/connexion/code');
        Mail::assertSent(CodeConnexionMail::class, fn ($m) => $m->hasTo($u->email));
        $this->get('/connexion/code')->assertSee('Vérifiez vos e-mails')->assertSee('Utiliser l');

        $this->post('/connexion/code/application')->assertRedirect('/connexion/code');
        $this->get('/connexion/code')->assertSee('Code de votre application');
        $this->post('/connexion/code', ['code' => Totp::code(self::CLE, Totp::pas())])->assertRedirect('/');
        $this->assertAuthenticatedAs($u);
    }

    public function test_code_de_secours_a_usage_unique(): void
    {
        $u = $this->utilisateur('gestionnaire');
        $codes = $this->avecApplication($u);

        $this->motDePasse($u);
        $this->get('/connexion/code')->assertSee('Utiliser un code de secours');
        $this->post('/connexion/code', ['code' => strtolower($codes[3])])->assertRedirect('/');
        $this->assertAuthenticatedAs($u);
        $this->assertSame(9, Authentificateur::codesSecoursRestants($u));
        $this->assertDatabaseHas('app_journal', ['action' => 'connexion.code_secours', 'user_id' => $u->id]);

        // Déjà utilisé.
        $this->post('/deconnexion');
        $this->motDePasse($u);
        $this->from('/connexion/code')->post('/connexion/code', ['code' => $codes[3]])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_compte_sans_application_inchange(): void
    {
        $u = $this->utilisateur('gestionnaire');
        $this->motDePasse($u)->assertRedirect('/connexion/code');
        Mail::assertSent(CodeConnexionMail::class);
        $this->get('/connexion/code')->assertSee('Vérifiez vos e-mails')->assertDontSee('Utiliser un code de secours')->assertDontSee('Utiliser l');
    }

    public function test_application_exigee_meme_si_l_e_mail_est_coupe(): void
    {
        config(['somadi.authentification.pin_email' => false]);
        $u = $this->utilisateur('gestionnaire');
        $this->avecApplication($u);
        $this->motDePasse($u)->assertRedirect('/connexion/code');
        $this->assertGuest();
        $this->get('/connexion/code')->assertDontSee('Recevoir plutôt un code par e-mail');
    }

    // ---------- Gestion ----------

    public function test_nouveaux_codes_et_desactivation_avec_mot_de_passe(): void
    {
        $u = $this->utilisateur('gestionnaire');
        $anciens = $this->avecApplication($u);
        $this->actingAs($u);

        $this->post('/mon-profil/authentificateur/codes', ['mot_de_passe' => 'faux'])->assertSessionHasErrors('mot_de_passe_authentificateur');
        $this->post('/mon-profil/authentificateur/codes', ['mot_de_passe' => 'Secret123'])->assertRedirect('/mon-profil/authentificateur/codes');
        $this->assertFalse(Authentificateur::utiliserCodeSecours($u, $anciens[0]));
        $this->assertTrue(Authentificateur::utiliserCodeSecours($u, session('codes_secours')[0]));

        $this->delete('/mon-profil/authentificateur', ['mot_de_passe' => 'Secret123'])->assertRedirect('/mon-profil');
        $this->assertFalse(Authentificateur::actif($u));
        $this->assertSame(0, Authentificateur::codesSecoursRestants($u));
    }

    public function test_retrait_par_l_administrateur(): void
    {
        $admin = $this->utilisateur('admin');
        $u = $this->utilisateur('ouvrier');
        $this->avecApplication($u);

        $this->actingAs($admin)->get("/administration/utilisateurs/{$u->id}")->assertSee('Retirer l');
        $this->actingAs($admin)->delete("/administration/utilisateurs/{$u->id}/authentificateur")->assertSessionHas('succes');
        $this->assertFalse(Authentificateur::actif($u));
        $this->assertDatabaseHas('app_journal', ['action' => 'authentificateur.retrait_admin', 'objet_id' => (string) $u->id]);

        $this->actingAs($u)->delete("/administration/utilisateurs/{$admin->id}/authentificateur")->assertForbidden();
    }

    public function test_bloque_sous_une_autre_identite(): void
    {
        $admin = $this->utilisateur('admin');
        $u = $this->utilisateur('ouvrier');
        $this->actingAs($admin)->post("/identite/{$u->id}");
        $this->get('/mon-profil/authentificateur')->assertRedirect('/');
        $this->assertFalse(Authentificateur::actif($u));
    }
}
