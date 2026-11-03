<?php

namespace Tests\Feature;

use App\Mail\CodeConnexionMail;
use App\Models\User;
use App\Support\CodeConnexion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\AuthentificateurLogiciel;
use Tests\TestCase;

/**
 * Authentification renforcée : mot de passe + code PIN par e-mail (PC), empreinte WebAuthn (téléphone).
 */
class AuthentificationRenforceeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->chargerMatriceParDefaut();
        Mail::fake();
        config([
            'somadi.authentification.pin_email' => true,
            'somadi.authentification.empreinte' => true,
            'somadi.authentification.webauthn_domaine' => 'somadi.test',
        ]);
    }

    // ================= Code PIN par e-mail =================

    private function motDePasse(User $u, string $mdp = 'Secret123')
    {
        return $this->post('/connexion', ['email' => $u->email, 'mot_de_passe' => $mdp]);
    }

    private function dernierCode(User $u): string
    {
        $code = null;
        Mail::assertSent(CodeConnexionMail::class, function (CodeConnexionMail $m) use ($u, &$code) {
            if ($m->hasTo($u->email)) {
                $code = $m->code;
            }

            return true;
        });

        return (string) $code;
    }

    public function test_le_mot_de_passe_seul_n_ouvre_pas_la_session_et_un_code_est_envoye(): void
    {
        $u = $this->utilisateur('gestionnaire');
        $this->motDePasse($u)->assertRedirect('/connexion/code');
        $this->assertGuest();
        Mail::assertSent(CodeConnexionMail::class, fn ($m) => $m->hasTo($u->email) && preg_match('/^\d{6}$/', $m->code));
        $this->assertDatabaseHas('app_journal', ['action' => 'connexion.code_envoye', 'user_id' => $u->id]);
        // Le code en clair n'est jamais stocké.
        $this->assertDatabaseMissing('app_codes_connexion', ['code_hash' => $this->dernierCode($u)]);

        $this->get('/connexion/code')->assertOk()->assertSee('Vérifiez vos e-mails')->assertDontSee($u->email);
        $this->get('/')->assertRedirect('/connexion');
    }

    public function test_le_bon_code_ouvre_la_session(): void
    {
        $u = $this->utilisateur('ouvrier');
        $this->motDePasse($u);
        $this->post('/connexion/code', ['code' => $this->dernierCode($u)])->assertRedirect('/')->assertSessionHas('proposer_empreinte');
        $this->assertAuthenticatedAs($u);
        $this->assertSame($u->id, session('id'));
        $this->assertSame('ouvrier', session('role'));
        $journal = DB::table('app_journal')->where('action', 'connexion.reussie')->where('user_id', $u->id)->first();
        $this->assertStringContainsString('mot_de_passe+code', $journal->apres);
    }

    public function test_un_code_ne_sert_qu_une_fois(): void
    {
        $u = $this->utilisateur();
        $this->motDePasse($u);
        $code = $this->dernierCode($u);
        $this->post('/connexion/code', ['code' => $code]);
        $this->post('/deconnexion');
        $this->assertGuest();
        // Sans nouveau mot de passe, l'ancien code ne sert plus à rien.
        $this->post('/connexion/code', ['code' => $code])->assertRedirect('/connexion');
        $this->assertGuest();
    }

    public function test_mauvais_code_puis_epuisement_des_essais(): void
    {
        $u = $this->utilisateur();
        $this->motDePasse($u);
        $bon = $this->dernierCode($u);
        $faux = $bon === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 4; $i++) {
            $this->from('/connexion/code')->post('/connexion/code', ['code' => $faux])
                ->assertRedirect('/connexion/code')->assertSessionHasErrors(['code' => 'Code incorrect. Vérifiez le dernier e-mail reçu.']);
        }
        // 5e erreur : il faut recommencer avec le mot de passe ; le bon code ne fonctionne plus.
        $this->post('/connexion/code', ['code' => $faux])->assertRedirect('/connexion');
        $this->post('/connexion/code', ['code' => $bon])->assertRedirect('/connexion');
        $this->assertGuest();
        $this->assertDatabaseHas('app_journal', ['action' => 'connexion.code_refuse', 'user_id' => $u->id]);
    }

    public function test_code_expire(): void
    {
        $u = $this->utilisateur();
        $this->motDePasse($u);
        $code = $this->dernierCode($u);
        $this->travel(11)->minutes();
        $this->post('/connexion/code', ['code' => $code])->assertRedirect('/connexion');
        $this->assertGuest();
    }

    public function test_format_du_code(): void
    {
        $u = $this->utilisateur();
        $this->motDePasse($u);
        $this->from('/connexion/code')->post('/connexion/code', ['code' => '12'])->assertSessionHasErrors(['code' => 'Saisissez les 6 chiffres reçus par e-mail.']);
        // Les espaces éventuels (copier-coller) sont ignorés.
        $code = $this->dernierCode($u);
        $this->post('/connexion/code', ['code' => substr($code, 0, 3).' '.substr($code, 3)])->assertRedirect('/');
        $this->assertAuthenticatedAs($u);
    }

    public function test_renvoi_d_un_code_invalide_le_precedent_et_est_limite(): void
    {
        $u = $this->utilisateur();
        $this->motDePasse($u);
        $premier = $this->dernierCode($u);
        $this->from('/connexion/code')->post('/connexion/code/renvoyer')->assertRedirect('/connexion/code')->assertSessionHas('statut');
        $second = $this->dernierCode($u);
        Mail::assertSentCount(2);
        if ($premier !== $second) {
            $this->from('/connexion/code')->post('/connexion/code', ['code' => $premier])->assertSessionHasErrors('code');
            $this->assertGuest();
        }
        $this->post('/connexion/code/renvoyer');
        $this->post('/connexion/code/renvoyer');
        $this->from('/connexion/code')->post('/connexion/code/renvoyer')->assertSessionHasErrors('code');
        Mail::assertSentCount(4);
    }

    public function test_plafond_horaire_d_emission_des_codes(): void
    {
        $u = $this->utilisateur();
        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit('code-emission:'.$u->id, 3600);
        }
        $this->from('/connexion')->post('/connexion', ['email' => $u->email, 'mot_de_passe' => 'Secret123'])
            ->assertRedirect('/connexion')->assertSessionHasErrors('email');
        Mail::assertNothingSent();
        RateLimiter::clear('code-emission:'.$u->id);
    }

    public function test_page_du_code_sans_mot_de_passe_prealable(): void
    {
        $this->get('/connexion/code')->assertRedirect('/connexion');
        $this->post('/connexion/code', ['code' => '123456'])->assertRedirect('/connexion');
    }

    public function test_retour_annule_la_demande(): void
    {
        $u = $this->utilisateur();
        $this->motDePasse($u);
        $code = $this->dernierCode($u);
        $this->post('/connexion/code/annuler')->assertRedirect('/connexion');
        $this->assertNull(session('connexion_attente'));
        $this->assertSame(0, DB::table('app_codes_connexion')->where('user_id', $u->id)->whereNull('utilise_le')->count());
        $this->post('/connexion/code', ['code' => $code])->assertRedirect('/connexion');
        $this->assertGuest();
    }

    public function test_compte_desactive_entre_le_mot_de_passe_et_le_code(): void
    {
        $u = $this->utilisateur();
        $this->motDePasse($u);
        DB::table('utilisateurs')->where('id', $u->id)->update(['actif' => 0]);
        $this->post('/connexion/code', ['code' => $this->dernierCode($u)])->assertRedirect('/connexion');
        $this->assertGuest();
    }

    public function test_interrupteur_de_secours_mot_de_passe_seul(): void
    {
        config(['somadi.authentification.pin_email' => false]);
        $u = $this->utilisateur();
        $this->motDePasse($u)->assertRedirect('/');
        $this->assertAuthenticatedAs($u);
        Mail::assertNothingSent();
    }

    public function test_envoi_non_configure_en_production(): void
    {
        $u = $this->utilisateur();
        config(['mail.default' => 'log']);
        $this->assertTrue(CodeConnexion::envoiConfigure()); // hors production, « log » est accepté
        $this->app['env'] = 'production';
        try {
            $this->assertFalse(CodeConnexion::envoiConfigure());
            $this->assertNull(CodeConnexion::emettre($u));
        } finally {
            $this->app['env'] = 'testing';
        }
        Mail::assertNothingSent();
        $this->assertSame(0, DB::table('app_codes_connexion')->where('user_id', $u->id)->count());
        $this->assertDatabaseHas('app_journal', ['action' => 'connexion.code_non_envoye', 'user_id' => $u->id]);
    }

    public function test_echec_d_envoi_du_mail(): void
    {
        $u = $this->utilisateur();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP indisponible'));
        $this->from('/connexion')->motDePasse($u)->assertRedirect('/connexion')
            ->assertSessionHasErrors(['email' => 'Le code de connexion n’a pas pu être envoyé par e-mail. Réessayez dans un instant ou prévenez un administrateur.']);
        $this->assertGuest();
    }

    // ================= Empreinte (WebAuthn) =================

    /** Active une empreinte pour $u avec un authentificateur logiciel, et renvoie celui-ci. */
    private function activerEmpreinte(User $u, ?AuthentificateurLogiciel $a = null): AuthentificateurLogiciel
    {
        $a ??= new AuthentificateurLogiciel();
        $options = $this->actingAs($u)->postJson('/mon-profil/empreintes/options', ['mot_de_passe' => 'Secret123'])->assertOk()->json();
        $this->assertSame('somadi.test', $options['publicKey']['rp']['id']);
        $this->assertSame('required', $options['publicKey']['authenticatorSelection']['userVerification']);
        $this->assertSame('platform', $options['publicKey']['authenticatorSelection']['authenticatorAttachment']);
        $this->postJson('/mon-profil/empreintes', ['reponse' => $a->creer($options)])->assertOk()->assertJson(['redirection' => route('profil.edit')]);

        return $a;
    }

    private function handle(array $options): string
    {
        return AuthentificateurLogiciel::deB64u($options['publicKey']['user']['id']);
    }

    private function connexionEmpreinte(AuthentificateurLogiciel $a, User $u, bool $verifie = true)
    {
        $options = $this->postJson('/connexion/empreinte/options')->assertOk()->json();
        $handle = hash_hmac('sha256', 'somadi-utilisateur:'.$u->id, (string) config('app.key'), true);

        return $this->postJson('/connexion/empreinte', $a->signer($options, $handle, $verifie));
    }

    public function test_activation_puis_connexion_par_empreinte_sans_mot_de_passe_ni_code(): void
    {
        $u = $this->utilisateur('technicien');
        $a = $this->activerEmpreinte($u);
        $cle = DB::table('app_empreintes')->where('user_id', $u->id)->first();
        $this->assertNotNull($cle);
        $this->assertStringContainsString('BEGIN PUBLIC KEY', $cle->cle_publique);
        $this->assertDatabaseHas('app_journal', ['action' => 'empreinte.activation', 'user_id' => $u->id]);

        $this->post('/deconnexion');
        $this->assertGuest();

        $this->connexionEmpreinte($a, $u)->assertOk()->assertJson(['redirection' => url('/')]);
        $this->assertAuthenticatedAs($u);
        $this->assertSame('technicien', session('role'));
        Mail::assertNothingSent();
        $journal = DB::table('app_journal')->where('action', 'connexion.reussie')->where('user_id', $u->id)->orderByDesc('id')->first();
        $this->assertStringContainsString('empreinte', $journal->apres);
        $this->assertNotNull(DB::table('app_empreintes')->where('id', $cle->id)->value('utilise_le'));
    }

    public function test_activation_redemande_le_mot_de_passe(): void
    {
        $u = $this->utilisateur();
        $this->actingAs($u)->postJson('/mon-profil/empreintes/options', ['mot_de_passe' => 'mauvais'])
            ->assertStatus(422)->assertJson(['message' => 'Mot de passe incorrect.']);
        // Sans options valides, aucune réponse ne peut être enregistrée.
        $this->postJson('/mon-profil/empreintes', ['reponse' => (new AuthentificateurLogiciel())->creer(['publicKey' => ['challenge' => 'abc']])])
            ->assertStatus(422);
        $this->assertSame(0, DB::table('app_empreintes')->count());
    }

    public function test_identifiant_transmis_au_telephone_sans_donnee_personnelle(): void
    {
        $u = $this->utilisateur();
        $options = $this->actingAs($u)->postJson('/mon-profil/empreintes/options', ['mot_de_passe' => 'Secret123'])->json();
        $this->assertSame(32, strlen($this->handle($options)));
        $this->assertStringNotContainsString((string) $u->id, $options['publicKey']['user']['id']);
    }

    public function test_empreinte_non_verifiee_par_le_telephone_refusee(): void
    {
        $u = $this->utilisateur();
        $a = $this->activerEmpreinte($u);
        $this->post('/deconnexion');
        $this->connexionEmpreinte($a, $u, verifie: false)->assertStatus(422);
        $this->assertGuest();
    }

    public function test_defi_a_usage_unique(): void
    {
        $u = $this->utilisateur();
        $a = $this->activerEmpreinte($u);
        $this->post('/deconnexion');
        $options = $this->postJson('/connexion/empreinte/options')->json();
        $handle = hash_hmac('sha256', 'somadi-utilisateur:'.$u->id, (string) config('app.key'), true);
        $reponse = $a->signer($options, $handle);
        $this->postJson('/connexion/empreinte', $reponse)->assertOk();
        $this->post('/deconnexion');
        // Rejouer la même réponse signée ne rouvre pas de session.
        $this->postJson('/connexion/empreinte', $reponse)->assertStatus(422);
        $this->assertGuest();
    }

    public function test_cle_clonee_detectee_par_le_compteur(): void
    {
        $u = $this->utilisateur();
        $a = $this->activerEmpreinte($u);
        $this->post('/deconnexion');
        $this->connexionEmpreinte($a, $u)->assertOk();
        $this->post('/deconnexion');
        $a->compteur = 0; // une copie de la clé repartirait d'un compteur antérieur
        $this->connexionEmpreinte($a, $u)->assertStatus(422);
        $this->assertGuest();
    }

    public function test_autre_origine_refusee(): void
    {
        $u = $this->utilisateur();
        $a = $this->activerEmpreinte($u);
        $this->post('/deconnexion');
        $a->origine = 'https://faux-somadi.test';
        $this->connexionEmpreinte($a, $u)->assertStatus(422);
        $this->assertGuest();
    }

    public function test_cle_inconnue_ou_d_un_autre_telephone(): void
    {
        $u = $this->utilisateur();
        $this->activerEmpreinte($u);
        $this->post('/deconnexion');
        $this->connexionEmpreinte(new AuthentificateurLogiciel(), $u)->assertStatus(422)->assertJsonFragment(['message' => 'Cette empreinte n’est pas (ou plus) reconnue. Connectez-vous avec votre mot de passe, puis réactivez l’empreinte dans Mon profil.']);
        $this->assertGuest();
    }

    public function test_compte_inactif_refuse_meme_avec_empreinte(): void
    {
        $u = $this->utilisateur();
        $a = $this->activerEmpreinte($u);
        $this->post('/deconnexion');
        DB::table('utilisateurs')->where('id', $u->id)->update(['actif' => 0]);
        $this->connexionEmpreinte($a, $u)->assertStatus(422);
        $this->assertGuest();
    }

    public function test_retrait_par_l_utilisateur_puis_connexion_impossible(): void
    {
        $u = $this->utilisateur();
        $a = $this->activerEmpreinte($u);
        $id = DB::table('app_empreintes')->where('user_id', $u->id)->value('id');
        $autre = $this->utilisateur();
        $this->actingAs($autre)->delete("/mon-profil/empreintes/$id")->assertNotFound();

        $this->actingAs($u)->from('/mon-profil')->delete("/mon-profil/empreintes/$id")->assertRedirect('/mon-profil');
        $this->assertDatabaseMissing('app_empreintes', ['id' => $id]);
        $this->assertDatabaseHas('app_journal', ['action' => 'empreinte.retrait', 'user_id' => $u->id]);
        $this->post('/deconnexion');
        $this->connexionEmpreinte($a, $u)->assertStatus(422);
        $this->assertGuest();
    }

    public function test_retrait_de_toutes_les_empreintes_par_un_administrateur(): void
    {
        $u = $this->utilisateur('vendeur');
        $this->activerEmpreinte($u);
        $this->activerEmpreinte($u);
        $this->assertSame(2, DB::table('app_empreintes')->where('user_id', $u->id)->count());

        $this->actingAs($u)->delete("/administration/utilisateurs/{$u->id}/empreintes")->assertForbidden();

        $admin = $this->utilisateur('admin');
        $this->actingAs($admin)->get("/administration/utilisateurs/{$u->id}")->assertOk()->assertSee('Retirer toutes les empreintes');
        $this->from("/administration/utilisateurs/{$u->id}")->delete("/administration/utilisateurs/{$u->id}/empreintes")
            ->assertRedirect("/administration/utilisateurs/{$u->id}")->assertSessionHas('succes');
        $this->assertSame(0, DB::table('app_empreintes')->where('user_id', $u->id)->count());
        $this->assertDatabaseHas('app_journal', ['action' => 'empreinte.retrait_admin', 'objet_id' => (string) $u->id]);
    }

    public function test_profil_affiche_les_appareils(): void
    {
        $u = $this->utilisateur();
        $this->actingAs($u)->get('/mon-profil')->assertOk()->assertSee('Connexion par empreinte')->assertSee('Aucun appareil');
        $this->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/130.0 Mobile Safari/537.36');
        $this->activerEmpreinte($u);
        $this->get('/mon-profil')->assertOk()->assertSee('Android · Chrome')->assertSee('pas encore utilisée');
    }

    public function test_empreinte_desactivee(): void
    {
        config(['somadi.authentification.empreinte' => false]);
        $this->postJson('/connexion/empreinte/options')->assertNotFound();
        $this->get('/connexion')->assertOk()->assertDontSee('Se connecter avec mon empreinte');
    }

    public function test_page_de_connexion_propose_l_empreinte_et_annonce_le_code(): void
    {
        $this->get('/connexion')->assertOk()->assertSee('Se connecter avec mon empreinte')->assertSee('Un code de confirmation vous sera envoyé par e-mail.');
    }

    public function test_email_d_essai_envoye_depuis_le_site_par_un_administrateur(): void
    {
        $admin = $this->utilisateur('admin');
        $this->actingAs($admin)->from('/administration/utilisateurs')->post('/administration/email-essai')
            ->assertRedirect('/administration/utilisateurs')->assertSessionHas('succes');
        Mail::assertSent(CodeConnexionMail::class, fn ($m) => $m->hasTo($admin->email) && $m->code === '000000');
        $this->assertDatabaseHas('app_journal', ['action' => 'email.essai', 'user_id' => $admin->id]);

        $this->actingAs($this->utilisateur('ouvrier'))->post('/administration/email-essai')->assertForbidden();
    }

    public function test_transport_mail_php_enregistre(): void
    {
        // Mail::fake() enveloppe le gestionnaire réel : on interroge celui-ci.
        $reel = (fn () => $this->manager)->call(Mail::getFacadeRoot());
        $this->assertInstanceOf(\App\Support\TransportMailPhp::class, $reel->mailer('phpmail')->getSymfonyTransport());
    }

    public function test_nom_appareil(): void
    {
        $this->assertSame('iPhone · Safari', \App\Support\Empreintes::nomAppareil('Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1'));
        $this->assertSame('Android · Samsung Internet', \App\Support\Empreintes::nomAppareil('Mozilla/5.0 (Linux; Android 14; SM-A546B) AppleWebKit/537.36 SamsungBrowser/25.0 Chrome/121.0 Mobile Safari/537.36'));
    }
}
