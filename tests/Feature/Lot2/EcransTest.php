<?php

namespace Tests\Feature\Lot2;

use App\Domaine\Incubation;
use App\Domaine\Ponte;
use App\Domaine\StockOeufs;
use App\Support\Empreinte;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Écrans du lot 2 : affichage, formulaires de bout en bout et droits par rôle. */
class EcransTest extends VolaillesTestCase
{
    private function donnees(): array
    {
        $b = $this->bande(80);
        $p = Ponte::saisir(['id_bande' => $b, 'date' => today()->toDateString(), 'ramassage_matin' => 30, 'ramassage_apres_midi' => 20]);
        $s = Ponte::valider($p, 2, Empreinte::de($this->ligne('suivi_ponte', $p)));
        $lo = StockOeufs::affecter($s, 'incuber', 20, Empreinte::de($this->ligne('stock_oeufs_ramasses', $s)));
        $inc = Incubation::charger([$lo], $this->equipement('Inc '.Str::random(4), 300), today()->toDateString(), null);
        $this->equipement('Ecl '.Str::random(4), 300, 'eclosoir');
        $lot = $this->lotVolaille(50);
        DB::table('modLotVol_suivi_poids')->insert([['id_lot' => $lot, 'date_suivi' => today()->subDays(7)->toDateString(), 'poids_moyen' => 500], ['id_lot' => $lot, 'date_suivi' => today()->toDateString(), 'poids_moyen' => 800]]);
        $alim = DB::table('modAlim_types')->insertGetId(['nom_aliment' => 'Démarrage']);
        $plan = DB::table('modAlim_plans')->insertGetId(['espece' => 'poulet', 'nom_plan' => 'Chair']);
        DB::table('modAlim_plan_lignes')->insert(['id_plan' => $plan, 'semaine' => 1, 'id_aliment' => $alim, 'qte_par_tete' => 50]);
        $cat = DB::table('modSoinVacc_catalogue')->insertGetId(['nom' => 'Newcastle', 'type' => 'vaccin']);
        DB::table('modSoinVacc_soins')->insert(['id_lot' => $lot, 'date_prevue' => today()->toDateString(), 'id_soin' => $cat, 'etat' => 'planifie', 'origine' => 'Soin Occasionnel']);

        return compact('b', 's', 'inc', 'lot', 'plan', 'cat');
    }

    public function test_toutes_les_pages_du_lot_2_s_affichent(): void
    {
        $d = $this->donnees();
        $pages = [
            '/', '/volailles/reproducteurs', '/volailles/reproducteurs?vue=reformees', '/volailles/reproducteurs/nouvelle', "/volailles/reproducteurs/{$d['b']}", "/volailles/reproducteurs/{$d['b']}/modifier",
            '/volailles/ponte', "/volailles/ponte?bande={$d['b']}", '/volailles/oeufs', '/volailles/oeufs?statut=charge',
            '/volailles/incubation', '/volailles/incubation?vue=terminees', '/volailles/incubation/chargement', "/volailles/incubation/{$d['inc']}", '/volailles/equipements',
            '/volailles/lots', '/volailles/lots?vue=archives', '/volailles/lots/nouveau', "/volailles/lots/{$d['lot']}", "/volailles/lots/{$d['lot']}/modifier",
            '/volailles/rentabilite', "/volailles/rentabilite/{$d['lot']}?hypothese=7", '/volailles/references-couts',
            '/volailles/alimentation', '/volailles/alimentation/stocks', '/volailles/alimentation/plans', "/volailles/alimentation/plans/{$d['plan']}", '/volailles/alimentation/consommation',
            '/volailles/soins', '/volailles/soins/catalogue', '/volailles/soins/programme',
        ];
        foreach ($pages as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/')->assertSee('Aujourd’hui')->assertSee('Soins du jour');
    }

    public function test_parcours_ponte_par_formulaires(): void
    {
        $b = $this->bande(80);
        $this->post('/volailles/ponte', ['id_bande' => $b, 'date' => today()->toDateString(), 'ramassage_matin' => 35, 'ramassage_apres_midi' => 25])
            ->assertRedirect()->assertSessionHas('succes');
        $l = DB::table('suivi_ponte')->where('id_bande', $b)->first();
        $this->get('/volailles/ponte')->assertSee(Empreinte::de($l));
        $this->from('/volailles/ponte')->post("/volailles/ponte/{$l->id}/validation", ['nb_oeufs_defectueux' => 4, 'empreinte' => Empreinte::de($l)])->assertSessionHas('succes');
        $this->assertSame(56, (int) DB::table('stock_oeufs_ramasses')->where('id_bande', $b)->value('total_oeufs'));
        // Le refus métier revient sur le formulaire avec un message, sans erreur serveur.
        $this->from('/volailles/ponte')->post("/volailles/ponte/{$l->id}/validation", ['nb_oeufs_defectueux' => 0, 'empreinte' => Empreinte::de($l)])
            ->assertRedirect('/volailles/ponte')->assertSessionHas('erreur');
    }

    public function test_double_envoi_d_un_formulaire_de_creation(): void
    {
        $cle = (string) Str::uuid();
        $donnees = ['_cle' => $cle, 'nom_lot' => 'Lot double', 'espece' => 'poulet', 'race' => 'Cobb', 'nombre_initial' => 10, 'date_entree' => today()->toDateString()];
        $this->post('/volailles/lots', $donnees)->assertRedirect();
        $this->from('/volailles/lots/nouveau')->post('/volailles/lots', $donnees)->assertSessionHas('erreur');
        $this->assertSame(1, DB::table('modLotVol_lots_volailles')->where('nom_lot', 'Lot double')->count());
    }

    public function test_droits_ouvrier_sur_le_terrain(): void
    {
        $d = $this->donnees();
        $this->actingAs($this->utilisateur('ouvrier'));
        $this->get('/volailles/ponte')->assertOk();
        $this->get('/volailles/soins')->assertOk();
        $this->get('/volailles/alimentation')->assertOk();
        $this->get('/volailles/incubation')->assertForbidden();
        $this->get('/volailles/rentabilite')->assertForbidden();
        $this->get('/volailles/lots/nouveau')->assertForbidden();
        $this->post("/volailles/lots/{$d['lot']}/mouvements", ['type_mouvement' => 'mortalité', 'quantite' => 1, 'date_mouvement' => today()->toDateString(), 'commentaire' => 'x'])->assertForbidden();
        $this->post('/volailles/ponte/1/validation', ['nb_oeufs_defectueux' => 0, 'empreinte' => 'x'])->assertForbidden();
        // L'ouvrier peut marquer un soin réalisé, pas l'annuler.
        $s = DB::table('modSoinVacc_soins')->where('id_lot', $d['lot'])->first();
        $this->post("/volailles/soins/{$s->id}/etat", ['etat' => 'annule', 'empreinte' => Empreinte::de($s)])->assertForbidden();
        $this->from('/volailles/soins')->post("/volailles/soins/{$s->id}/etat", ['etat' => 'realise', 'empreinte' => Empreinte::de($s)])->assertSessionHas('succes');
    }

    public function test_les_empreintes_des_pages_correspondent_au_serveur(): void
    {
        $b = $this->bande(80);
        $p = Ponte::saisir(['id_bande' => $b, 'date' => today()->toDateString(), 'ramassage_matin' => 30, 'ramassage_apres_midi' => 0]);
        $s = Ponte::valider($p, 0, Empreinte::de($this->ligne('suivi_ponte', $p)));
        $this->get('/volailles/oeufs')->assertSee(Empreinte::de($this->ligne('stock_oeufs_ramasses', $s)));

        $lot = $this->lotVolaille(10);
        $cat = DB::table('modSoinVacc_catalogue')->insertGetId(['nom' => 'Vitamine', 'type' => 'soin']);
        $id = DB::table('modSoinVacc_soins')->insertGetId(['id_lot' => $lot, 'date_prevue' => today()->toDateString(), 'id_soin' => $cat, 'etat' => 'planifie', 'origine' => 'Soin Occasionnel']);
        $empreinte = Empreinte::de($this->ligne('modSoinVacc_soins', $id));
        $this->get('/volailles/soins')->assertSee($empreinte);
        $this->get("/volailles/lots/{$lot}")->assertSee($empreinte);
        $inc = $this->equipement('X', 10);
        $this->get('/volailles/incubation/chargement')->assertOk();
    }

    public function test_courbe_de_ponte(): void
    {
        $b = $this->bande(80);
        $hier = today()->subDay()->toDateString();
        $p = Ponte::saisir(['id_bande' => $b, 'date' => $hier, 'ramassage_matin' => 35, 'ramassage_apres_midi' => 25]);
        Ponte::valider($p, 6, Empreinte::de($this->ligne('suivi_ponte', $p)));
        Ponte::saisir(['id_bande' => $b, 'date' => today()->toDateString(), 'ramassage_matin' => 40, 'ramassage_apres_midi' => 0]);

        $r = $this->get("/volailles/ponte/courbe?bande={$b}&jours=14")->assertOk()
            ->assertSee('Taux de ponte par jour')->assertSee('Qualité : part d’œufs défectueux');
        $series = $r->viewData('series');
        $this->assertSame([$hier => 75.0, today()->toDateString() => 50.0], $series[0]['points']);
        $this->assertSame([$hier => 10.0], $r->viewData('qualite')[0]['points']); // 6 / 60, seulement sur le validé
        $this->assertCount(14, $r->viewData('dates'));
        $this->assertSame(100, $r->viewData('synthese')['oeufs']);
        $this->get('/volailles/ponte/courbe')->assertOk();
        $this->get('/volailles/ponte/courbe?jours=7')->assertSessionHasErrors('jours');
        $this->actingAs($this->utilisateur('associe ovin'))->get('/volailles/ponte/courbe')->assertForbidden();
    }

    public function test_menu_du_lot_2_disponible(): void
    {
        $this->get('/')->assertSee(route('ponte.index'))->assertSee(route('lots.index'))->assertDontSee('/modules/pon');
    }
}
