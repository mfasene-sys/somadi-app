<?php

namespace Tests\Feature\Lot4;

use App\Domaine\Ovins\Inventaires;
use App\Domaine\Ovins\Mouvements;
use App\Domaine\Ovins\Reproduction;
use App\Domaine\Ovins\Soins;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Écrans du lot 4 : affichage, formulaires de bout en bout et droits par rôle. */
class EcransOvinsTest extends OvinsTestCase
{
    private function donnees(): array
    {
        $s = $this->site('Keur');
        $repro = $this->lot($s, 'reproductrices', 0);
        $agn = $this->lot($s, 'agnelles');
        $mere = $this->animal($s, $repro, 'F', ['nom_animal' => 'Bella']);
        $pere = $this->animal($s, $repro, 'M', ['nom_animal' => 'Samba']);
        $ag = $this->animal($s, $agn, 'F', ['date_naissance' => today()->subDays(300)->toDateString(), 'date_entree' => today()->subDays(200)->toDateString(), 'id_mere' => $mere, 'id_pere' => $pere]);
        Reproduction::enregistrer(null, ['id_mere' => $mere, 'id_pere' => $pere, 'date_saillie' => today()->subDays(130)->toDateString()]);
        $type = Soins::enregistrerType(null, ['nom_soin' => 'Déparasitage']);
        $modele = Soins::enregistrerModele(null, ['nom_modele' => 'Brebis', 'categorie' => 'brebis_'.Str::random(4), 'actif' => 1], [['jour_offset' => 0, 'id_type_soin' => $type], ['jour_offset' => 10, 'id_type_soin' => $type]]);
        Soins::appliquer($modele, 'lot', $repro, today()->subDays(2)->toDateString());
        Reproduction::genererCalendrier($repro, today()->subMonth()->toDateString(), config('somadi.ovins.calendrier'));
        $vendu = $this->animal($s, $repro, 'M');
        $sortie = Mouvements::sortir($vendu, ['type_sortie' => 'vente', 'date_sortie' => today()->toDateString(), 'prix' => '200000', 'client_nom' => 'Ali', 'client_contact' => '771234567']);
        $inv = Inventaires::ouvrir(['titre' => 'Test', 'site' => $s], (string) Str::uuid());

        return compact('s', 'repro', 'agn', 'mere', 'pere', 'ag', 'type', 'modele', 'vendu', 'sortie', 'inv');
    }

    public function test_toutes_les_pages_du_lot_4_s_affichent(): void
    {
        $d = $this->donnees();
        $pages = [
            '/', '/ovins/animaux', '/ovins/animaux?sexes[]=F&ages[]=adulte&repros[]=gestante&statuts[]=tous', '/ovins/animaux?reset=1', '/ovins/animaux/nouveau',
            "/ovins/animaux/{$d['mere']}", "/ovins/animaux/{$d['vendu']}", "/ovins/animaux/{$d['ag']}/modifier", "/ovins/animaux/{$d['ag']}/arbre",
            '/ovins/lots', '/ovins/lots/nouveau', "/ovins/lots/{$d['repro']}", "/ovins/lots/{$d['repro']}/modifier", "/ovins/lots/{$d['repro']}/calendrier", '/ovins/sites', '/ovins/proprietaires',
            '/ovins/reproduction', "/ovins/reproduction/femelles/{$d['mere']}",
            '/ovins/soins', '/ovins/soins?vue=historique', '/ovins/soins/catalogue', '/ovins/soins/modeles/nouveau', "/ovins/soins/modeles/{$d['modele']}",
            '/ovins/mouvements', '/ovins/rapprochements', '/ovins/inventaires', '/ovins/inventaires/nouveau?site='.$d['s'], "/ovins/inventaires/{$d['inv']}",
        ];
        foreach ($pages as $url) {
            $this->get($url)->assertOk();
        }
        $this->get("/ovins/inventaires/{$d['inv']}/feuille.pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get("/ovins/animaux/{$d['ag']}/arbre")->assertSee('Bella')->assertSee('Samba');
        $this->get('/ovins/reproduction')->assertSee('Agnelles prêtes');
        $this->get('/')->assertSee('Ovins en ferme');
    }

    public function test_parcours_creation_transfert_sortie_par_formulaires(): void
    {
        $d = $this->donnees();
        $this->post('/ovins/animaux', ['_cle' => (string) Str::uuid(), 'sexe' => 'M', 'id_site' => $d['s'], 'id_lot' => $d['agn'], 'compter' => '1', 'race' => 'Ladoum', 'date_naissance' => '2025-01-10'])
            ->assertRedirect()->assertSessionHas('succes');
        $id = (int) DB::table('OVINS_animaux')->where('race', 'Ladoum')->value('id_animal');
        $this->assertStringStartsWith('OV', DB::table('OVINS_animaux')->where('id_animal', $id)->value('code_animal'));
        $this->post("/ovins/animaux/$id/transfert", ['id_lot' => $d['repro'], 'date' => today()->toDateString()])->assertRedirect("/ovins/animaux/$id");
        $this->assertSame($d['repro'], (int) DB::table('OVINS_animaux')->where('id_animal', $id)->value('id_lot'));
        $r = $this->post("/ovins/animaux/$id/sortie", ['_cle' => (string) Str::uuid(), 'type_sortie' => 'vente', 'date_sortie' => today()->toDateString(), 'prix' => '250 000', 'client_nom' => 'Tabaski SARL', 'client_contact' => '781112233']);
        $vente = (int) DB::table('modVente_ovins_liaisons')->where('id_animal', $id)->value('id_vente');
        $r->assertRedirect("/commercial/ventes/$vente");
        $this->get("/ovins/animaux/$id")->assertSee('vente n° '.$vente);
    }

    public function test_media_televerse_puis_affiche(): void
    {
        config(['somadi.ovins.medias.dossier' => sys_get_temp_dir().'/medias-ecran-'.Str::random(6)]);
        $d = $this->donnees();
        $this->post("/ovins/animaux/{$d['mere']}/medias", ['fichiers' => [UploadedFile::fake()->image('a.jpg', 30, 30)], 'stade_age' => 'adulte'])->assertSessionHas('succes');
        $m = DB::table('OVINS_animaux_medias')->where('id_animal', $d['mere'])->first();
        $this->get("/ovins/medias/{$m->id_media}")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->post("/ovins/animaux/{$d['mere']}/medias", ['fichiers' => [UploadedFile::fake()->createWithContent('x.png', '<?php echo 1;')], 'stade_age' => 'adulte'])->assertSessionHas('erreur');
    }

    public function test_reproduction_soins_et_inventaire_par_formulaires(): void
    {
        $d = $this->donnees();
        $r = DB::table('OVINS_repro')->where('id_mere', $d['mere'])->first();
        $this->post("/ovins/reproduction/{$r->id_repro}", ['_cle' => (string) Str::uuid(), 'id_mere' => $d['mere'], 'id_pere' => $d['pere'], 'date_saillie' => $r->date_saillie,
            'date_agnelage_reel' => today()->toDateString(), 'nb_agneaux_males' => '1', 'nb_agneaux_femelles' => '1', 'nb_agneaux_vivants' => '2', 'id_lot_agneaux' => $d['repro']])
            ->assertSessionHas('succes', '2 agneau(x) créé(s) avec leur filiation.');
        // Soins : marquer réalisé avec l'empreinte affichée.
        $page = $this->get('/ovins/soins')->assertOk()->getContent();
        $s = DB::table('OVINS_soins_realises')->where('id_lot', $d['repro'])->where('date_soin', '<=', today()->toDateString())->orderBy('date_soin')->first();
        preg_match('#/ovins/soins/'.$s->id_soin.'/realise"><input type="hidden" name="_token" value="[^"]+"(?:\s*autocomplete="off")?><input type="hidden" name="empreinte" value="([^"]+)"#', $page, $m);
        $this->assertNotEmpty($m, 'empreinte absente');
        $this->from('/ovins/soins')->post("/ovins/soins/{$s->id_soin}/realise", ['empreinte' => $m[1]])->assertSessionHas('succes');
        // Inventaire : pointage groupé puis clôture.
        $lignes = DB::table('OVINS_inventaire_lignes')->where('inventaire_id', $d['inv'])->get();
        $this->from("/ovins/inventaires/{$d['inv']}")->post("/ovins/inventaires/{$d['inv']}/groupe", ['lignes' => $lignes->mapWithKeys(fn ($l) => [$l->id => $l->version])->all(),
            'controleur' => 'Fatou', 'date_controle' => today()->toDateString()])->assertSessionHas('succes');
        $v = DB::table('OVINS_inventaires')->where('id', $d['inv'])->value('version');
        $this->from("/ovins/inventaires/{$d['inv']}")->post("/ovins/inventaires/{$d['inv']}/cloture", ['version' => $v, 'bilan' => 'Tout le monde est là.', 'confirmation' => '1'])->assertSessionHas('succes');
        $this->assertSame('cloture', DB::table('OVINS_inventaires')->where('id', $d['inv'])->value('statut'));
    }

    public function test_droits_par_role(): void
    {
        $d = $this->donnees();
        $this->actingAs($this->utilisateur('associe ovin'));
        $this->get('/ovins/animaux')->assertOk()->assertDontSee('Nouvel animal');
        $this->get("/ovins/animaux/{$d['mere']}")->assertOk()->assertDontSee('Transférer');
        $this->get('/ovins/inventaires')->assertOk();
        $this->post("/ovins/animaux/{$d['mere']}/transfert", ['id_lot' => $d['agn'], 'date' => today()->toDateString()])->assertForbidden();
        $this->get('/ovins/rapprochements')->assertForbidden();

        $this->actingAs($this->utilisateur('ouvrier'));
        $this->get('/ovins/animaux')->assertForbidden();

        // Technicien : gère le troupeau, clôture l'inventaire, peut remettre un soin à faire ; pas de vente sans droit de vente.
        $this->actingAs($this->utilisateur('technicien'));
        $this->get("/ovins/inventaires/{$d['inv']}")->assertSee('Clôturer');
        $this->post("/ovins/animaux/{$d['ag']}/sortie", ['_cle' => (string) Str::uuid(), 'type_sortie' => 'vente', 'date_sortie' => today()->toDateString(), 'prix' => '1000', 'client_nom' => 'X', 'client_contact' => '771234567'])->assertForbidden();

        // Gestionnaire : pointe mais ne clôture pas un inventaire ; pas de remise à faire d'un soin réalisé.
        $this->actingAs($this->utilisateur('gestionnaire'));
        $this->get("/ovins/inventaires/{$d['inv']}")->assertDontSee('Je clôture cet inventaire');
        $this->post("/ovins/inventaires/{$d['inv']}/cloture", ['version' => 1, 'bilan' => 'xxxxxxxxxxxx', 'confirmation' => '1'])->assertForbidden();
        $s = DB::table('OVINS_soins_realises')->first();
        $this->post("/ovins/soins/{$s->id_soin}/retour", ['motif' => 'test'])->assertForbidden();
    }
}
