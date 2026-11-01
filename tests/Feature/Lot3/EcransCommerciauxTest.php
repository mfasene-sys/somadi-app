<?php

namespace Tests\Feature\Lot3;

use App\Domaine\Exercices;
use App\Domaine\Incubation;
use App\Domaine\LotsVolailles;
use App\Domaine\Ponte;
use App\Domaine\Reservations;
use App\Domaine\StockOeufs;
use App\Domaine\Tresorerie;
use App\Domaine\Ventes;
use App\Support\Empreinte;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Lot2\VolaillesTestCase;

/** Écrans du lot 3 : affichage, formulaires de bout en bout et droits par rôle. */
class EcransCommerciauxTest extends VolaillesTestCase
{
    private function vente(array $d = []): int
    {
        return Ventes::creer($d + ['date_vente' => today()->toDateString(), 'client_nom' => 'Awa Ndiaye', 'client_tel' => '771234567',
            'produit' => 'poulet_chair', 'quantite' => 10, 'prix_unitaire' => '3500']);
    }

    private function donnees(): array
    {
        $v = $this->vente();
        $op = Tresorerie::encaisser($v, ['mode' => 'partiel', 'montant' => '10000', 'date_op' => today()->toDateString(), 'moyen_paiement' => 'Wave']);
        $lot = $this->lotVolaille(50);
        LotsVolailles::mouvement($lot, ['type_mouvement' => 'vente', 'quantite' => 5, 'date_mouvement' => today()->toDateString(), 'commentaire' => 'Marché']);
        $b = $this->bande(200);
        $p = Ponte::saisir(['id_bande' => $b, 'date' => today()->toDateString(), 'ramassage_matin' => 100, 'ramassage_apres_midi' => 0]);
        $s = Ponte::valider($p, 0, Empreinte::de($this->ligne('suivi_ponte', $p)));
        StockOeufs::affecter($s, 'vendre', 40, Empreinte::de($this->ligne('stock_oeufs_ramasses', $s)));
        $lo = StockOeufs::affecter($s, 'incuber', 60, Empreinte::de($this->ligne('stock_oeufs_ramasses', $s)));
        $inc = Incubation::charger([$lo], $this->equipement('Inc '.Str::random(4), 300), today()->toDateString(), null);
        $r = Reservations::reserver(['id_lot_prevu' => $inc, 'nom_client' => 'Moussa', 'telephone' => '781112233', 'quantite' => 20, 'prix_unitaire' => '600', 'acompte' => '2000']);
        DB::table('commandes_vente')->insert(['type_produit' => 'poussins', 'quantite' => 10, 'prix_unitaire' => 500, 'prix_total' => 5000, 'acompte_verse' => 1000, 'solde_restant' => 4000, 'date_reservation' => '2024-03-01', 'nom_client' => 'Ancien']);

        return compact('v', 'op', 'lot', 'inc', 'r');
    }

    public function test_toutes_les_pages_du_lot_3_s_affichent(): void
    {
        $d = $this->donnees();
        $annee = today()->year;
        $pages = [
            '/', '/commercial/ventes', '/commercial/ventes?statut=A+encaisser&q=Awa', '/commercial/ventes/nouvelle', '/commercial/ventes/oeufs', '/commercial/ventes/oeufs?produit=oeufs_caille',
            '/commercial/ventes/a-completer', "/commercial/ventes/{$d['v']}",
            '/commercial/tresorerie', '/commercial/tresorerie?invalides=1&type=recette', '/commercial/tresorerie/recapitulatif', '/commercial/tresorerie/a-encaisser',
            '/commercial/exercices', '/commercial/exercices?annee='.($annee - 1),
            '/commercial/rapprochements', '/commercial/rapprochements?tous=1', "/commercial/rapprochements/ventes/{$d['v']}",
            '/commercial/reservations', '/commercial/reservations?statut=toutes', '/commercial/reservations/nouvelle', "/commercial/reservations/{$d['r']}",
            '/commercial/commandes',
        ];
        foreach ($pages as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/')->assertSee('Ventes et caisse')->assertSee('Reste à encaisser');
        $this->get("/commercial/ventes/{$d['v']}")->assertSee('Awa Ndiaye')->assertSee("25\u{202F}000");
        $this->get('/commercial/ventes/a-completer')->assertSee('Marché');
        $this->get('/commercial/commandes')->assertSee('Ancien');
    }

    public function test_parcours_vente_encaissement_invalidation_par_formulaires(): void
    {
        $this->post('/commercial/ventes', ['client_nom' => 'Fatou', 'client_tel' => '77 000 11 22', 'produit' => 'goliath_chair', 'date_vente' => today()->toDateString(),
            'quantite' => 4, 'prix_unitaire' => '7 500', 'encaissement' => 'partiel', 'montant_encaisse' => '10 000', 'moyen_paiement' => 'Espèces', '_cle' => (string) Str::uuid()])
            ->assertRedirect()->assertSessionHas('succes');
        $v = DB::table('modVente_Ventes')->where('client_nom', 'Fatou')->first();
        $this->assertSame(['30000.00', '20000.00'], [$v->montant_total, $v->montant_reste_encaisser]);

        $this->from("/commercial/ventes/{$v->id}")->post("/commercial/ventes/{$v->id}/encaissements", ['mode' => 'total', 'date_op' => today()->toDateString(), 'moyen_paiement' => 'Orange Money', '_cle' => (string) Str::uuid()])
            ->assertSessionHas('succes');
        $this->assertSame('Encaissé', DB::table('modVente_Ventes')->where('id', $v->id)->value('statut'));

        $op = DB::table('modCompta_operations')->where('source_id', $v->id)->orderByDesc('id')->first();
        $this->from("/commercial/ventes/{$v->id}")->post("/commercial/tresorerie/operations/{$op->id}/invalidation", ['motif' => 'Erreur'])->assertSessionHasErrors('confirmation');
        $this->from("/commercial/ventes/{$v->id}")->post("/commercial/tresorerie/operations/{$op->id}/invalidation", ['motif' => 'Erreur de saisie', 'confirmation' => '1'])->assertSessionHas('succes');
        $this->assertSame('20000.00', DB::table('modVente_Ventes')->where('id', $v->id)->value('montant_reste_encaisser'));

        // Opération autonome ; double soumission refusée.
        $cle = (string) Str::uuid();
        $op = ['type_op' => 'depense', 'date_op' => today()->toDateString(), 'categorie' => 'Aliments', 'montant' => '12 500', 'libelle' => 'Sac démarrage', 'moyen_paiement' => 'Espèces', '_cle' => $cle];
        $this->from('/commercial/tresorerie')->post('/commercial/tresorerie/operations', $op)->assertSessionHas('succes');
        $this->from('/commercial/tresorerie')->post('/commercial/tresorerie/operations', $op)->assertSessionHas('erreur');
        $this->assertSame(1, DB::table('modCompta_operations')->where('libelle', 'Sac démarrage')->count());
        $this->get('/commercial/tresorerie')->assertSee('Sac démarrage')->assertSee("12\u{202F}500");
    }

    public function test_finalisation_d_une_sortie_par_formulaire(): void
    {
        $lot = $this->lotVolaille(30);
        LotsVolailles::mouvement($lot, ['type_mouvement' => 'vente', 'quantite' => 6, 'date_mouvement' => today()->toDateString(), 'commentaire' => 'Tabaski']);
        $s = DB::table('modVente_Ventes_A_Completer')->where('id_lot', $lot)->first();
        $this->from('/commercial/ventes/a-completer')->post("/commercial/ventes/a-completer/{$s->id}", ['client_nom' => 'Ibou', 'client_tel' => '770000000', 'produit' => 'poulet_chair', 'quantite' => 5, 'prix_unitaire' => '3000'])
            ->assertSessionHas('erreur');
        $this->post("/commercial/ventes/a-completer/{$s->id}", ['client_nom' => 'Ibou', 'client_tel' => '770000000', 'produit' => 'poulet_chair', 'quantite' => 6, 'prix_unitaire' => '3000', 'encaissement' => 'aucun'])
            ->assertRedirect()->assertSessionHas('succes');
        $this->assertSame(24, (int) DB::table('modLotVol_lots_volailles')->where('id', $lot)->value('effectif_en_cours'));
    }

    public function test_parcours_rapprochement_par_formulaires(): void
    {
        $v = $this->vente();
        $op = Tresorerie::creerOperation(['type_op' => 'recette', 'date_op' => today()->toDateString(), 'categorie' => 'Vente poulets abattus', 'montant' => '35000', 'libelle' => 'Reçu Awa', 'moyen_paiement' => 'Espèces']);
        $this->get("/commercial/rapprochements/ventes/$v")->assertSee('Reçu Awa');
        $r = $this->post("/commercial/rapprochements/ventes/$v/apercu", ['operations' => [$op], 'motif' => 'Reçu n° 12 présenté par la cliente']);
        $r->assertRedirect();
        $url = $r->headers->get('Location');
        $this->get($url)->assertOk()->assertSee('Encaissé');
        $this->post($url, ['confirmation' => '1'])->assertSessionHas('erreur');
        $this->post($url, ['confirmation' => '1', 'historique_complet' => '1'])->assertRedirect("/commercial/rapprochements/ventes/$v");
        $this->assertSame('0.00', DB::table('modVente_Ventes')->where('id', $v)->value('montant_reste_encaisser'));
        $this->get("/commercial/rapprochements/ventes/$v")->assertSee('Reçu n° 12');
    }

    public function test_parcours_reservation_par_formulaires(): void
    {
        $d = $this->donnees();
        $this->post('/commercial/reservations', ['id_lot_prevu' => $d['inc'], 'nom_client' => 'Khady', 'telephone' => '76 555 44 33', 'quantite' => 10, 'prix_unitaire' => '600', '_cle' => (string) Str::uuid()])
            ->assertRedirect()->assertSessionHas('succes');
        $r = DB::table('resa_reservations')->where('nom_client', 'Khady')->first();
        $this->get("/commercial/reservations/{$r->id_reservation}")->assertSee('bon de réservation')->assertSee('Annuler la réservation');
        $this->from("/commercial/reservations/{$r->id_reservation}")->post("/commercial/reservations/{$r->id_reservation}/annulation", ['motif' => 'Client absent'])->assertSessionHas('succes');
        $this->assertSame('annulé', DB::table('resa_reservations')->where('id_reservation', $r->id_reservation)->value('statut'));
    }

    public function test_cloture_par_les_ecrans(): void
    {
        $annee = (int) gmdate('Y') - 1;
        $v = $this->vente(['date_vente' => "$annee-11-15"]);
        $this->get("/commercial/exercices?annee=$annee")->assertSee('Préparer la clôture');
        $this->get("/commercial/exercices/$annee/cloture")->assertRedirect();
        $this->post("/commercial/exercices/$annee/apercu")->assertRedirect("/commercial/exercices/$annee/cloture");
        $page = $this->get("/commercial/exercices/$annee/cloture")->assertOk()->assertSee('Clôturer '.$annee);
        preg_match('/name="revision" value="([^"]+)"/', $page->getContent(), $rev);
        preg_match('/name="empreinte" value="([^"]+)"/', $page->getContent(), $emp);
        $this->post("/commercial/exercices/$annee/cloture", ['commentaire' => 'Clôture validée en réunion', 'revision' => $rev[1], 'empreinte' => $emp[1], 'confirmation' => '1'])
            ->assertRedirect("/commercial/exercices/$annee/archive");
        $this->get("/commercial/exercices/$annee/archive")->assertSee('Archive intègre')->assertSee('Clôture validée en réunion');
        $pdf = $this->get("/commercial/exercices/$annee/archive.pdf")->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->get("/commercial/ventes/$v")->assertSee('exercice clôturé');
    }

    public function test_droits_par_role(): void
    {
        $d = $this->donnees();
        $annee = (int) gmdate('Y') - 1;

        $this->actingAs($this->utilisateur('ouvrier'));
        foreach (['/commercial/ventes', '/commercial/tresorerie', '/commercial/exercices', '/commercial/rapprochements', '/commercial/reservations', '/commercial/commandes'] as $url) {
            $this->get($url)->assertForbidden();
        }

        // Vendeur : vend, encaisse, réserve ; pas de saisie libre en trésorerie, ni d'invalidation, ni de rapprochement.
        $this->actingAs($this->utilisateur('vendeur'));
        $this->get('/commercial/ventes/nouvelle')->assertOk();
        $this->get("/commercial/ventes/{$d['v']}")->assertSee('Encaisser')->assertDontSee('Invalider');
        $this->get('/commercial/tresorerie')->assertOk()->assertDontSee('Nouvelle opération');
        $this->post('/commercial/tresorerie/operations', ['type_op' => 'depense'])->assertForbidden();
        $this->post("/commercial/tresorerie/operations/{$d['op']}/invalidation", ['motif' => 'xxx', 'confirmation' => '1'])->assertForbidden();
        $this->get('/commercial/rapprochements')->assertForbidden();
        $this->get('/commercial/exercices')->assertForbidden();
        $this->get('/commercial/reservations/nouvelle')->assertOk();

        // Gestionnaire : comptabilité et rapprochements (EC03), pas la clôture.
        $this->actingAs($this->utilisateur('gestionnaire'));
        $this->get('/commercial/tresorerie')->assertSee('Nouvelle opération');
        $this->get('/commercial/rapprochements')->assertOk();
        $this->get("/commercial/exercices?annee=$annee")->assertOk()->assertDontSee('Préparer la clôture');
        $this->post("/commercial/exercices/$annee/apercu")->assertForbidden();
    }
}
