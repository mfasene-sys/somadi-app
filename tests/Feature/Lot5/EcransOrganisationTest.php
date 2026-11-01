<?php

namespace Tests\Feature\Lot5;

use App\Domaine\LotsVolailles;
use App\Domaine\Organisation\Activites;
use App\Domaine\Organisation\Infrastructures;
use App\Domaine\Organisation\Marketing;
use App\Domaine\Organisation\Planning;
use App\Domaine\Organisation\Rh;
use App\Domaine\Ovins\Referentiels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Écrans du lot 5 : affichage, formulaires et droits. */
class EcransOrganisationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->chargerMatriceParDefaut();
        $this->actingAs($this->utilisateur('admin'));
    }

    private function donnees(): array
    {
        $site = Referentiels::enregistrerSite(null, ['code_site' => Str::upper(Str::random(6)), 'nom_site' => 'Keur', 'actif' => 1]);
        $lieu = Infrastructures::enregistrer(null, ['code' => 'B'.Str::upper(Str::random(5)), 'nom_batiment' => 'Poulailler 1', 'id_site' => $site, 'type_lieu' => 'batiment', 'famille' => 'volaille', 'etat' => 'disponible', 'capacite' => 500, 'actif' => 1]);
        $lot = LotsVolailles::creer(['nom_lot' => 'Lot écran', 'espece' => 'poulet', 'race' => 'Cobb', 'nombre_initial' => 200, 'date_entree' => today()->subDays(5)->toDateString()]);
        Infrastructures::deplacer('volaille', $lot, 0, $lieu, 150, 'Mise en place', Infrastructures::empreinte('volaille', $lot));
        $periode = Planning::creer(['id_batiment' => $lieu, 'nature' => 'reservation', 'date_debut' => today()->addDays(5)->toDateString(), 'date_fin' => today()->addDays(9)->toDateString(), 'quantite' => 100]);
        $ouvrier = $this->utilisateur('ouvrier');
        $equipe = Activites::enregistrerEquipe(null, ['nom' => 'Équipe '.Str::random(4), 'actif' => 1], [$ouvrier->id]);
        $tache = Activites::creer(['nom' => 'Réparer la clôture', 'priorite' => 'haute', 'date_debut_prevue' => now()->format('Y-m-d').'T09:00', 'equipes' => [$equipe]], (string) Str::uuid());
        $routine = Activites::enregistrerRoutine(null, ['titre' => 'Routine', 'actif' => 1, 'id_equipe' => $equipe]);
        Activites::enregistrerTacheRoutine($routine, null, ['nom' => 'Abreuvoirs', 'type_recurrence' => 'quotidien', 'horaire_debut' => '07:00', 'actif' => 1]);
        $projet = Activites::enregistrerProjet(null, ['titre' => 'Nouveau bâtiment']);
        $emp = Rh::enregistrerEmploye(null, ['nom' => 'Diop', 'prenom' => 'Awa', 'salaire_mensuel' => '120000', 'actif' => 1]);
        Rh::demander(['employe_id' => $emp, 'type' => 'conge', 'date_debut' => today()->addDays(10)->toDateString(), 'date_fin' => today()->addDays(12)->toDateString()]);
        $modele = Marketing::enregistrerModele(null, ['titre' => 'Promo', 'message' => 'Bonjour {client_nom}', 'is_actif' => 1]);
        $campagne = Marketing::creerCampagne('Promo novembre', $modele);
        DB::table('modVente_Ventes')->insert(['date_vente' => today()->toDateString(), 'client_nom' => 'Client WA', 'client_tel' => '771234567', 'espece' => 'poulet_chair', 'quantite' => 2, 'prix_unitaire' => 3000, 'montant_total' => 6000, 'montant_reste_encaisser' => 0, 'statut' => 'Encaissé']);

        return compact('site', 'lieu', 'lot', 'periode', 'ouvrier', 'equipe', 'tache', 'routine', 'projet', 'emp', 'modele', 'campagne');
    }

    public function test_toutes_les_pages_du_lot_5_s_affichent(): void
    {
        $d = $this->donnees();
        foreach (['/', '/organisation/lieux', "/organisation/lieux/{$d['lieu']}", '/organisation/repartition', "/organisation/repartition?famille=volaille&source={$d['lot']}",
            '/organisation/repartition?famille=ovin', '/organisation/repartition?famille=repro_f', '/organisation/lieux/journal', '/organisation/planning',
            '/activites', '/activites/taches', '/activites/taches?statut=planifie', '/activites/taches/nouvelle', "/activites/taches/{$d['tache']}", "/activites/taches/{$d['tache']}/modifier",
            '/activites/planning', '/activites/routines', "/activites/routines/{$d['routine']}", "/activites/routines/{$d['routine']}?du=".today()->toDateString().'&au='.today()->addDays(6)->toDateString(),
            '/activites/equipes', '/activites/projets',
            '/rh/employes', '/rh/employes/nouveau', "/rh/employes/{$d['emp']}", "/rh/employes/{$d['emp']}/modifier", '/rh/absences', '/rh/absences?statut=soumise', '/rh/jours-imposes', '/rh/retenues', '/rh/cloture',
            '/marketing', "/marketing/campagnes/{$d['campagne']}", "/marketing/campagnes/{$d['campagne']}?mode=suivant", '/marketing/modeles', '/marketing/exclusions', '/marketing/journal',
        ] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get("/marketing/campagnes/{$d['campagne']}")->assertSee('Client WA')->assertSee('+221771234567');
        $this->get('/')->assertSee('Absences à valider');
    }

    public function test_parcours_par_formulaires(): void
    {
        $d = $this->donnees();
        // Répartition : retirer 50 du poulailler.
        $page = $this->get("/organisation/repartition?famille=volaille&source={$d['lot']}")->getContent();
        preg_match('/name="empreinte" value="([^"]+)"/', $page, $m);
        $this->from('/organisation/repartition')->post('/organisation/repartition', ['_cle' => (string) Str::uuid(), 'famille' => 'volaille', 'source' => $d['lot'], 'origine' => $d['lieu'], 'destination' => 0, 'quantite' => 50, 'motif' => 'Sortie vers parcours', 'empreinte' => $m[1]])
            ->assertSessionHas('succes');
        $this->assertSame(100, (int) Infrastructures::lieu($d['lieu'])->occupation);
        // Tâche : démarrer par l'ouvrier membre de l'équipe, puis terminer refusé.
        $this->actingAs($d['ouvrier']);
        $this->get('/activites')->assertSee('Réparer la clôture');
        $e = Activites::empreinte($d['tache']);
        $this->from("/activites/taches/{$d['tache']}")->post("/activites/taches/{$d['tache']}/demarrer", ['_cle' => (string) Str::uuid(), 'empreinte' => $e])->assertSessionHas('succes');
        $this->from("/activites/taches/{$d['tache']}")->post("/activites/taches/{$d['tache']}/terminer", ['_cle' => (string) Str::uuid(), 'empreinte' => Activites::empreinte($d['tache'])])->assertSessionHas('erreur');
        $this->get("/activites/taches/{$d['tache']}")->assertDontSee('Terminer</button>', false);
        $this->get('/activites/taches/nouvelle')->assertForbidden();
        // RH : validation de la demande.
        $this->actingAs($this->utilisateur('gestionnaire'));
        $a = DB::table('rh_absences')->where('employe_id', $d['emp'])->first();
        $this->from('/rh/absences')->post("/rh/absences/{$a->id}/decision", ['decision' => 'validee'])->assertSessionHas('succes');
        $this->assertSame('validee', DB::table('rh_absences')->where('id', $a->id)->value('statut'));
        $this->post('/rh/cloture', ['annee' => 2025, 'confirmation' => '1'])->assertForbidden();
        // Marketing : ouvrir WhatsApp redirige vers wa.me et journalise.
        $this->actingAs($this->utilisateur('vendeur'));
        $this->post("/marketing/campagnes/{$d['campagne']}/action", ['action' => 'OPEN_WA', 'tel' => '771234567', 'nom' => 'Client WA', 'texte' => 'Bonjour Client WA'])
            ->assertRedirect('https://wa.me/221771234567?text=Bonjour%20Client%20WA');
        $this->assertSame(1, DB::table('modMkt_logs')->where('id_campagne', $d['campagne'])->where('action', 'OPEN_WA')->count());
        // Planning : démarrer une réservation est impossible ; annulation avec motif.
        $this->actingAs($this->utilisateur('technicien'));
        $this->from('/organisation/planning')->post("/organisation/planning/{$d['periode']}/annuler", ['motif' => 'Arrivée reportée'])->assertSessionHas('succes');
        $this->post("/organisation/planning/{$d['periode']}/administrative", ['motif' => 'xxxxxxxxxxxx'])->assertForbidden();
    }

    public function test_droits_par_role(): void
    {
        $this->donnees();
        $this->actingAs($this->utilisateur('vendeur'));
        foreach (['/organisation/lieux', '/rh/employes', '/activites'] as $u) {
            $this->get($u)->assertForbidden();
        }
        $this->get('/marketing')->assertOk();
        $this->actingAs($this->utilisateur('ouvrier'));
        $this->get('/activites')->assertOk();
        $this->get('/marketing')->assertForbidden();
        $this->get('/rh/employes')->assertForbidden();
        $this->actingAs($this->utilisateur('gerant_equipe'));
        $this->get('/activites/planning')->assertOk();
        $this->get('/organisation/lieux')->assertOk();
        $this->post('/organisation/lieux', ['_cle' => (string) Str::uuid()])->assertForbidden();
    }
}
