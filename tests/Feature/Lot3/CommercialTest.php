<?php

namespace Tests\Feature\Lot3;

use App\Domaine\Exercices;
use App\Domaine\Incubation;
use App\Domaine\LotsVolailles;
use App\Domaine\Ponte;
use App\Domaine\Rapprochements;
use App\Domaine\Reservations;
use App\Domaine\StockOeufs;
use App\Domaine\Tresorerie;
use App\Domaine\Ventes;
use App\Exceptions\RegleMetier;
use App\Support\Empreinte;
use App\Support\Montant;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Lot2\VolaillesTestCase;

/** REC-VEN, REC-CPT, REC-RES, REC-RAP, REC-EXE et parcours INT02, INT03, INT04, INT07 */
class CommercialTest extends VolaillesTestCase
{
    private function vente(array $d = []): int
    {
        return Ventes::creer($d + ['date_vente' => today()->toDateString(), 'client_nom' => 'Client Test', 'client_tel' => '+221 77 123 45 67',
            'produit' => 'poulet_chair', 'quantite' => 10, 'prix_unitaire' => '5 000']);
    }

    private function v(int $id): object
    {
        return DB::table('modVente_Ventes')->where('id', $id)->first();
    }

    private function payer(int $id, string $mode, ?string $montant = null, ?string $date = null): int
    {
        return Tresorerie::encaisser($id, ['mode' => $mode, 'montant' => $montant, 'date_op' => $date ?? today()->toDateString(), 'moyen_paiement' => 'Espèces']);
    }

    public function test_montants_en_centimes(): void
    {
        $this->assertSame(125050, Montant::saisie('1 250,50'));
        $this->assertSame('1250.50', Montant::versBase(125050));
        $this->assertSame(500000000, Montant::produit(10, Montant::saisie('500000')));
        $this->expectException(RegleMetier::class);
        Montant::saisie('12,345');
    }

    public function test_rec_ven_001_creance_initiale(): void
    {
        $v = $this->v($this->vente());
        $this->assertSame(['50000.00', '50000.00', 'A encaisser'], [$v->montant_total, $v->montant_reste_encaisser, $v->statut]);
    }

    public function test_formulaire_general_refuse_oeufs_et_ovins_et_telephone_invalide(): void
    {
        foreach (['oeufs_table', 'ovin'] as $p) {
            try {
                $this->vente(['produit' => $p]);
                $this->fail("$p accepté");
            } catch (RegleMetier) {
            }
        }
        $this->expectExceptionMessage('Téléphone');
        $this->vente(['client_tel' => '12ab']);
    }

    public function test_ec06_vente_liee_a_un_lot_baisse_l_effectif_sans_vente_a_completer(): void
    {
        $lot = $this->lotVolaille(100);
        $this->vente(['id_lot' => $lot, 'quantite' => 7]);
        $this->assertSame(93, (int) DB::table('modLotVol_lots_volailles')->where('id', $lot)->value('effectif_en_cours'));
        $this->assertSame(1, DB::table('modLotVol_mouvements')->where('id_lot', $lot)->where('type_mouvement', 'vente')->count());
        $this->assertSame(0, DB::table('modVente_Ventes_A_Completer')->where('id_lot', $lot)->count());
    }

    public function test_int03_et_rec_ven_003_finalisation_d_une_sortie(): void
    {
        $lot = $this->lotVolaille(100);
        $sortie = LotsVolailles::mouvement($lot, ['type_mouvement' => 'vente', 'quantite' => 10, 'date_mouvement' => today()->toDateString(), 'commentaire' => 'vente']);
        $d = ['client_nom' => 'Restaurant', 'client_tel' => '771234567', 'produit' => 'poulet_chair', 'prix_unitaire' => '3000'];
        try {
            Ventes::finaliser($sortie, $d + ['quantite' => 11]);
            $this->fail('Quantité 11 acceptée');
        } catch (RegleMetier) {
        }
        $id = Ventes::finaliser($sortie, $d + ['quantite' => 10, 'encaissement' => 'partiel', 'montant_encaisse' => '10000', 'moyen_paiement' => 'Wave']);
        $this->assertSame(90, (int) DB::table('modLotVol_lots_volailles')->where('id', $lot)->value('effectif_en_cours'));
        $this->assertSame('completee', DB::table('modVente_Ventes_A_Completer')->where('id', $sortie)->value('statut'));
        $this->assertSame(1, DB::table('modVente_Ventes')->where('id_lot', $lot)->count());
        $this->assertSame('20000.00', $this->v($id)->montant_reste_encaisser);
        $this->expectExceptionMessage('déjà été traitée');
        Ventes::finaliser($sortie, $d + ['quantite' => 10]);
    }

    public function test_rec_cpt_001_002_003_encaissements_et_invalidation(): void
    {
        $id = $this->vente();
        try {
            $this->payer($id, 'partiel', '60000');
            $this->fail('Paiement supérieur accepté');
        } catch (RegleMetier) {
        }
        $this->assertSame(0, DB::table('modCompta_operations')->where('source_id', $id)->count());
        $op1 = $this->payer($id, 'partiel', '20000');
        $op2 = $this->payer($id, 'total');
        $this->assertSame('30000.00', DB::table('modCompta_operations')->where('id', $op2)->value('montant'));
        $v = $this->v($id);
        $this->assertSame(['0.00', 'Encaissé'], [$v->montant_reste_encaisser, $v->statut]);
        try {
            $this->payer($id, 'total');
            $this->fail('Vente soldée encaissée');
        } catch (RegleMetier $e) {
            $this->assertStringContainsString('soldée', $e->getMessage());
        }
        Tresorerie::invalider($op1, 'erreur de saisie');
        $v = $this->v($id);
        $this->assertSame(['20000.00', 'A encaisser'], [$v->montant_reste_encaisser, $v->statut]);
        $this->assertSame(1, DB::table('modCompta_operations')->where('source_id', $id)->where('est_valide', 1)->count());
        $this->assertSame(2, DB::table('modCompta_operations')->where('source_id', $id)->count(), 'Aucune suppression physique');
    }

    public function test_paiement_anterieur_a_la_vente_refuse_et_audit_bloquant(): void
    {
        $id = $this->vente(['date_vente' => today()->subDays(2)->toDateString()]);
        try {
            $this->payer($id, 'total', null, today()->subDays(3)->toDateString());
            $this->fail('Paiement antérieur accepté');
        } catch (RegleMetier) {
        }
        DB::table('modVente_Ventes')->where('id', $id)->update(['montant_reste_encaisser' => '1.00']);
        $this->expectExceptionMessage('rapprochement');
        $this->payer($id, 'total');
    }

    public function test_int04_vente_avec_paiement_partiel_puis_invalidation(): void
    {
        $lot = $this->lotVolaille(50);
        $id = $this->vente(['id_lot' => $lot, 'quantite' => 5, 'prix_unitaire' => '4000', 'encaissement' => 'partiel', 'montant_encaisse' => '8000', 'moyen_paiement' => 'Orange Money']);
        $this->assertSame(45, (int) DB::table('modLotVol_lots_volailles')->where('id', $lot)->value('effectif_en_cours'));
        $op = DB::table('modCompta_operations')->where('source_id', $id)->value('id');
        Tresorerie::invalider($op, 'paiement refusé');
        $this->assertSame('20000.00', $this->v($id)->montant_reste_encaisser);
        $this->assertSame(45, (int) DB::table('modLotVol_lots_volailles')->where('id', $lot)->value('effectif_en_cours'), 'Pas de retour automatique en stock');
    }

    private function lotOeufsAVendre(int $n, string $date): int
    {
        $b = $this->bande(500);
        $p = Ponte::saisir(['id_bande' => $b, 'date' => $date, 'ramassage_matin' => $n, 'ramassage_apres_midi' => 0]);
        $s = Ponte::valider($p, 0, Empreinte::de(DB::table('suivi_ponte')->where('id', $p)->first()));

        return StockOeufs::affecter($s, 'vendre', $n, Empreinte::de(DB::table('stock_oeufs_ramasses')->where('id', $s)->first()));
    }

    public function test_int02_et_rec_ven_002_vente_d_oeufs_fifo(): void
    {
        $a = $this->lotOeufsAVendre(12, today()->subDays(2)->toDateString());
        $b = $this->lotOeufsAVendre(20, today()->subDay()->toDateString());
        $id = Ventes::vendreOeufs(['lots' => [$b, $a], 'quantite' => 25, 'produit' => 'oeufs_table', 'date_vente' => today()->toDateString(),
            'client_nom' => 'Boutique', 'client_tel' => '701112233', 'prix_unitaire' => '100']);
        $aff = DB::table('modVente_oeufs_affectations')->where('id_vente', $id)->pluck('quantite', 'id_lot_oeufs');
        $this->assertSame([12, 13], [(int) $aff[$a], (int) $aff[$b]]);
        $this->assertSame('vendu', DB::table('lots_oeufs_ramasses')->where('id', $a)->value('statut'));
        $lotB = DB::table('lots_oeufs_ramasses')->where('id', $b)->first();
        $this->assertSame(['nouveau', 20], [$lotB->statut, (int) $lotB->quantite], 'Quantité d’origine conservée');
        $this->assertSame(7, Ventes::disponibleOeufs($lotB)['disponible']);
        $this->payer($id, 'partiel', '1000');
        $this->payer($id, 'total');
        $this->assertSame('Encaissé', $this->v($id)->statut);
        try {
            Ventes::vendreOeufs(['lots' => [$b], 'quantite' => 8, 'produit' => 'oeufs_table', 'date_vente' => today()->toDateString(), 'client_nom' => 'X', 'client_tel' => '701112233', 'prix_unitaire' => '100']);
            $this->fail('8 sur 7 accepté');
        } catch (RegleMetier) {
        }
        $this->expectExceptionMessage('caille');
        Ventes::vendreOeufs(['lots' => [$b], 'quantite' => 1, 'produit' => 'oeufs_caille', 'date_vente' => today()->toDateString(), 'client_nom' => 'X', 'client_tel' => '701112233', 'prix_unitaire' => '100']);
    }

    public function test_rec_ven_004_lot_vendu_avant_liaison_reste_indisponible(): void
    {
        $l = $this->lotOeufsAVendre(10, today()->toDateString());
        DB::table('lots_oeufs_ramasses')->where('id', $l)->update(['statut' => 'vendu']);
        $this->assertSame(0, Ventes::disponibleOeufs(DB::table('lots_oeufs_ramasses')->where('id', $l)->first())['disponible']);
    }

    public function test_rec_res_001_002_003_reservations(): void
    {
        $this->travelTo('2026-06-01 08:00');
        $b = $this->bande(200);
        $p = Ponte::saisir(['id_bande' => $b, 'date' => '2026-06-01', 'ramassage_matin' => 100, 'ramassage_apres_midi' => 0]);
        $s = Ponte::valider($p, 0, Empreinte::de(DB::table('suivi_ponte')->where('id', $p)->first()));
        $lo = StockOeufs::affecter($s, 'incuber', 100, Empreinte::de(DB::table('stock_oeufs_ramasses')->where('id', $s)->first()));
        $inc = Incubation::charger([$lo], $this->equipement('I', 500), '2026-06-01', null);
        $r1 = Reservations::reserver(['id_lot_prevu' => $inc, 'nom_client' => 'A', 'telephone' => '771111111', 'quantite' => 50, 'prix_unitaire' => '500', 'acompte' => '5000']);
        $this->travelTo('2026-06-02 08:00');
        $r2 = Reservations::reserver(['id_lot_prevu' => $inc, 'nom_client' => 'B', 'telephone' => '772222222', 'quantite' => 40, 'prix_unitaire' => '500', 'acompte' => '0']);
        $this->assertSame(10, (int) DB::table('resa_lots_previsionnels')->where('id_lot', $inc)->value('nombre_prevu'));
        try {
            Reservations::reserver(['id_lot_prevu' => $inc, 'nom_client' => 'C', 'telephone' => '773333333', 'quantite' => 11, 'prix_unitaire' => '500']);
            $this->fail('Dépassement du disponible accepté');
        } catch (RegleMetier) {
        }
        Incubation::transferer($inc, $this->equipement('E', 500, 'eclosoir'), null, '2026-06-02', Empreinte::de(DB::table('lots_incubation')->where('id', $inc)->first()));
        $this->travelTo('2026-06-22 08:00');
        Incubation::eclore($inc, ['date_eclosion' => '2026-06-22', 'nombre_eclos' => 70, 'methode' => 'estimation', 'race' => 'Locale'], Empreinte::de(DB::table('lots_incubation')->where('id', $inc)->first()));

        $res = Reservations::traiter($inc);
        $this->assertSame([70, 1, 1], [$res['servis'], $res['confirmees'], $res['partielles']]);
        $this->assertSame([50, 'confirmé'], [(int) DB::table('resa_reservations')->where('id_reservation', $r1)->value('quantite_servie'), DB::table('resa_reservations')->where('id_reservation', $r1)->value('statut')]);
        $this->assertSame([20, 'confirmé partiel'], [(int) DB::table('resa_reservations')->where('id_reservation', $r2)->value('quantite_servie'), DB::table('resa_reservations')->where('id_reservation', $r2)->value('statut')]);
        // REC-RES-002 : relancer ne sert rien de plus.
        $this->assertSame(0, Reservations::traiter($inc)['servis']);
        $this->assertSame(70, (int) DB::table('stock_poussins_jour')->where('id_lot', $inc)->value('vendus'));

        // REC-RES-003 : la livraison seule ne crée aucune recette.
        $avant = DB::table('modCompta_operations')->count();
        Reservations::livrer($r2, ['date_livraison' => '2026-06-22']);
        $this->assertSame($avant, DB::table('modCompta_operations')->count());
        // Livraison avec création de la vente et de la sortie du lot de poussins, acompte enregistré une seule fois.
        $lot = DB::table('modLotVol_lots_volailles')->where('id_lot_incubation_source', $inc)->first();
        $idVente = Reservations::livrer($r1, ['date_livraison' => '2026-06-22', 'creer_vente' => 1, 'produit' => 'poussin_chair', 'encaisser_acompte' => 1, 'moyen_paiement' => 'Espèces']);
        $v = $this->v($idVente);
        $this->assertSame(['25000.00', '20000.00'], [$v->montant_total, $v->montant_reste_encaisser]);
        $this->assertSame(20, (int) DB::table('modLotVol_lots_volailles')->where('id', $lot->id)->value('effectif_en_cours'));
    }

    public function test_rec_rap_001_002_003_rapprochement(): void
    {
        $id = $this->vente();
        $this->payer($id, 'partiel', '20000');
        $autonome = Tresorerie::creerOperation(['type_op' => 'recette', 'date_op' => today()->toDateString(), 'categorie' => 'Autres recettes', 'montant' => '10000', 'libelle' => 'Versement client', 'moyen_paiement' => 'Wave']);
        $trop = Tresorerie::creerOperation(['type_op' => 'recette', 'date_op' => today()->toDateString(), 'categorie' => 'Autres recettes', 'montant' => '40000', 'libelle' => 'Gros versement', 'moyen_paiement' => 'Wave']);
        try {
            Rapprochements::plan($id, [$trop], 'Reçu manuscrit n° 12 retrouvé');
            $this->fail('Dépassement accepté');
        } catch (RegleMetier $e) {
            $this->assertStringContainsString('dépasse', $e->getMessage());
        }
        $nb = DB::table('modCompta_operations')->count();
        $jeton = Rapprochements::preparer($id, [$autonome], 'Reçu manuscrit n° 12 retrouvé');
        Rapprochements::confirmer($jeton, true, true);
        $this->assertSame('20000.00', $this->v($id)->montant_reste_encaisser);
        $this->assertSame($nb, DB::table('modCompta_operations')->count(), 'Aucune recette créée');
        $this->assertSame('modVente_Ventes', DB::table('modCompta_operations')->where('id', $autonome)->value('source_table'));
        $this->assertSame(1, DB::table('modCompta_rapprochements')->where('id_vente', $id)->count());
        // Double confirmation du même ticket : refusée, sans second effet.
        try {
            Rapprochements::confirmer($jeton, true, true);
            $this->fail('Double confirmation acceptée');
        } catch (RegleMetier) {
        }
        $this->assertSame(1, DB::table('modCompta_rapprochements')->where('id_vente', $id)->count());
    }

    public function test_int12_ticket_perime_si_une_source_change(): void
    {
        $id = $this->vente();
        $autonome = Tresorerie::creerOperation(['type_op' => 'recette', 'date_op' => today()->toDateString(), 'categorie' => 'Autres recettes', 'montant' => '10000', 'libelle' => 'Versement', 'moyen_paiement' => 'Wave']);
        $jeton = Rapprochements::preparer($id, [$autonome], 'Bordereau de versement du jour');
        $this->payer($id, 'partiel', '5000'); // modification par une autre session
        $this->expectExceptionMessage('changé');
        Rapprochements::confirmer($jeton, true, true);
    }

    public function test_int07_et_rec_exe_cloture_puis_reglement_ulterieur(): void
    {
        $annee = (int) gmdate('Y') - 1;
        $id = $this->vente(['date_vente' => "$annee-12-30"]);
        $this->payer($id, 'partiel', '20000', "$annee-12-31");
        $ap = Exercices::apercu($annee);
        $this->assertFalse($ap['rapport']['controles']['bloque'], json_encode($ap['rapport']['controles']['bloquants']));

        // REC-EXE-001 : une écriture après l'aperçu impose un nouvel aperçu.
        Tresorerie::creerOperation(['type_op' => 'depense', 'date_op' => today()->toDateString(), 'categorie' => 'Aliments', 'montant' => '1000', 'libelle' => 'Son', 'moyen_paiement' => 'Espèces']);
        try {
            Exercices::cloturer($annee, 'Clôture de test complète', $ap['revision'], $ap['empreinte']);
            $this->fail('Aperçu périmé accepté');
        } catch (RegleMetier $e) {
            $this->assertStringContainsString('nouvel aperçu', $e->getMessage());
        }
        $ap = Exercices::apercu($annee);
        Exercices::cloturer($annee, 'Clôture de test complète', $ap['revision'], $ap['empreinte']);
        $this->assertSame("$annee-12-31", Exercices::borneCloture());
        $archive = Exercices::archive($annee);
        $this->assertTrue($archive['integre']);
        $this->assertStringStartsWith('%PDF-', $archive['ligne']->pdf_blob);
        $this->assertSame(3000000, $archive['contenu']['bilan']['creances_total']);

        // REC-EXE-002 : une vente de l'année clôturée ne peut être ni créée, ni antidatée, ni supprimée.
        foreach ([fn () => $this->vente(['date_vente' => "$annee-06-01"]), fn () => DB::table('modVente_Ventes')->where('id', $id)->delete()] as $action) {
            try {
                \App\Support\Lieux::executer($action);
                $this->fail('Écriture en période clôturée acceptée');
            } catch (RegleMetier $e) {
                $this->assertStringContainsString('clôturée', $e->getMessage());
            }
        }
        // INT07 / REC-EXE-003 : le solde reçu en N+1 diminue la créance courante, l'archive N est inchangée.
        $this->payer($id, 'total');
        $this->assertSame('0.00', $this->v($id)->montant_reste_encaisser);
        $this->assertSame(3000000, Exercices::archive($annee)['contenu']['bilan']['creances_total']);
        $this->expectException(RegleMetier::class);
        Exercices::apercu($annee);
    }

    public function test_cloture_bloquee_par_une_anomalie_et_reservee_a_l_admin(): void
    {
        $annee = (int) gmdate('Y') - 1;
        $id = $this->vente(['date_vente' => "$annee-05-05"]);
        DB::table('modVente_Ventes')->where('id', $id)->update(['montant_reste_encaisser' => '1.00']);
        $ap = Exercices::apercu($annee);
        $this->assertTrue($ap['rapport']['controles']['bloque']);
        $this->assertSame(1, $ap['rapport']['controles']['bloquants']['soldes']['nombre']);
        try {
            Exercices::cloturer($annee, 'Tentative malgré anomalie', $ap['revision'], $ap['empreinte']);
            $this->fail('Clôture avec anomalie acceptée');
        } catch (RegleMetier) {
        }
        $this->actingAs($this->utilisateur('gestionnaire'));
        $this->expectExceptionMessage('administrateur');
        Exercices::apercu($annee);
    }
}
