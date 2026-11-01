<?php

namespace Tests\Feature\Lot2;

use App\Domaine\Incubation;
use App\Domaine\Ponte;
use App\Domaine\StockOeufs;
use App\Exceptions\RegleMetier;
use App\Support\Empreinte;
use Illuminate\Support\Facades\DB;

/** REC-INC-001..005 et parcours INT01 */
class IncubationTest extends VolaillesTestCase
{
    /** Bande + ramassage validé + lot À incuber de $n œufs, collecté à la date courante. */
    private function lotAIncuber(int $n, ?int $bande = null): array
    {
        $b = $bande ?? $this->bande(max(200, $n));
        $id = Ponte::saisir(['id_bande' => $b, 'date' => today()->toDateString(), 'ramassage_matin' => $n, 'ramassage_apres_midi' => 0]);
        $s = Ponte::valider($id, 0, Empreinte::de($this->ligne('suivi_ponte', $id)));
        $lot = StockOeufs::affecter($s, 'incuber', $n, Empreinte::de($this->ligne('stock_oeufs_ramasses', $s)));

        return [$b, $lot, $s];
    }

    private function inc(int $id): object
    {
        return $this->ligne('lots_incubation', $id);
    }

    public function test_rec_inc_001_prevision_ajustement_et_mirage_trop_tot(): void
    {
        $this->travelTo('2026-06-01 08:00');
        [, $lot] = $this->lotAIncuber(100);
        $i = $this->equipement('Incub-A', 500);
        $id = Incubation::charger([$lot], $i, '2026-06-01', null);
        $this->assertSame('2026-06-22', $this->inc($id)->date_eclosion_previsionnelle);
        $this->assertSame('charge', $this->ligne('lots_oeufs_ramasses', $lot)->statut);
        $this->assertSame(100, (int) $this->ligne('resa_lots_previsionnels', $id, 'id_lot')->nombre_prevu);

        $this->travelTo('2026-06-05 08:00');
        Incubation::ajuster($id, 95, Empreinte::de($this->inc($id)));
        $this->assertSame(95, (int) $this->inc($id)->nombre_oeufs);
        $this->assertSame(100, (int) $this->inc($id)->nombre_oeufs_initial);

        $this->travelTo('2026-06-10 08:00'); // J+9
        $this->expectExceptionMessage('J+10');
        Incubation::mirer($id, 80, '2026-06-10', Empreinte::de($this->inc($id)));
    }

    public function test_caille_17_jours_et_capacite(): void
    {
        $this->travelTo('2026-06-01 08:00');
        [, $lot] = $this->lotAIncuber(50, $this->bande(200, 20, 'caille'));
        $petit = $this->equipement('Petit', 40);
        try {
            Incubation::charger([$lot], $petit, '2026-06-01', null);
            $this->fail('Capacité dépassée acceptée');
        } catch (RegleMetier $e) {
            $this->assertStringContainsString('capacité', $e->getMessage());
        }
        $id = Incubation::charger([$lot], $this->equipement('Grand', 60), '2026-06-01', null);
        $this->assertSame('2026-06-18', $this->inc($id)->date_eclosion_previsionnelle);
    }

    public function test_date_proposee_jeudi_suivant(): void
    {
        // Collectes du jeudi 4 au mercredi 10 juin 2026 → proposition jeudi 11 juin.
        $this->assertSame('2026-06-11', Incubation::dateProposee(['2026-06-04', '2026-06-08', '2026-06-10']));
        $this->assertNull(Incubation::dateProposee(['2026-06-03', '2026-06-04']));
    }

    public function test_rec_inc_002_mesure_fertilite_et_eclosion(): void
    {
        $this->travelTo('2026-06-01 08:00');
        [, $lot] = $this->lotAIncuber(100);
        $id = Incubation::charger([$lot], $this->equipement('Inc', 500), '2026-06-01', null);
        $this->travelTo('2026-06-12 08:00');
        Incubation::mirer($id, 80, '2026-06-12', Empreinte::de($this->inc($id)));
        $this->assertSame('80', $this->inc($id)->taux_fertilite);
        $this->travelTo('2026-06-19 08:00');
        Incubation::transferer($id, $this->equipement('Ecl', 500, 'eclosoir'), null, '2026-06-19', Empreinte::de($this->inc($id)));
        $this->travelTo('2026-06-22 08:00');
        $lv = Incubation::eclore($id, ['date_eclosion' => '2026-06-22', 'nombre_eclos' => 64, 'methode' => 'mesure', 'race' => 'Locale'], Empreinte::de($this->inc($id)));
        $i = $this->inc($id);
        $this->assertSame('80', $i->taux_eclosion);
        $this->assertSame('80', $i->taux_fertilite);
        $lotVol = $this->ligne('modLotVol_lots_volailles', $lv);
        $this->assertSame(64, (int) $lotVol->effectif_en_cours);
        $this->assertSame($id, (int) $lotVol->id_lot_incubation_source);
        $this->assertSame(1, DB::table('stock_poussins_jour')->where('id_lot', $id)->count());
    }

    public function test_rec_inc_003_estimation_sans_mirage(): void
    {
        $this->travelTo('2026-06-01 08:00');
        [, $lot] = $this->lotAIncuber(100);
        $id = Incubation::charger([$lot], $this->equipement('Inc', 500), '2026-06-01', null);
        Incubation::transferer($id, $this->equipement('Ecl', 500, 'eclosoir'), null, '2026-06-01', Empreinte::de($this->inc($id)));
        $this->travelTo('2026-06-22 08:00');
        try {
            Incubation::eclore($id, ['date_eclosion' => '2026-06-22', 'nombre_eclos' => 81, 'methode' => 'mesure', 'race' => 'X'], Empreinte::de($this->inc($id)));
            $this->fail('Mesure sans mirage acceptée');
        } catch (RegleMetier) {
        }
        Incubation::eclore($id, ['date_eclosion' => '2026-06-22', 'nombre_eclos' => 81, 'methode' => 'estimation', 'race' => 'X'], Empreinte::de($this->inc($id)));
        $this->assertSame('90', $this->inc($id)->taux_eclosion);
        $this->assertSame('90', $this->inc($id)->taux_fertilite);
    }

    public function test_rec_inc_004_scission_refusee_si_reservation(): void
    {
        $this->travelTo('2026-06-01 08:00');
        [, $lot] = $this->lotAIncuber(100);
        $id = Incubation::charger([$lot], $this->equipement('Inc', 500), '2026-06-01', null);
        DB::table('resa_reservations')->insert(['produit' => 'poussin', 'id_lot_prevu' => $id, 'nom_client' => 'Client', 'quantite' => 10]);
        $e1 = $this->equipement('E60', 60, 'eclosoir');
        $e2 = $this->equipement('E40', 40, 'eclosoir');
        $this->expectExceptionMessage('réservations');
        Incubation::transferer($id, $e1, $e2, '2026-06-01', Empreinte::de($this->inc($id)));
    }

    public function test_scission_conserve_les_totaux(): void
    {
        $this->travelTo('2026-06-01 08:00');
        [, $lot] = $this->lotAIncuber(100);
        $id = Incubation::charger([$lot], $this->equipement('Inc', 500), '2026-06-01', null);
        $idB = Incubation::transferer($id, $this->equipement('E60', 60, 'eclosoir'), $this->equipement('E40', 40, 'eclosoir'), '2026-06-01', Empreinte::de($this->inc($id)));
        $a = $this->inc($id);
        $b = $this->inc($idB);
        $this->assertSame([60, 40], [(int) $a->nombre_oeufs, (int) $b->nombre_oeufs]);
        $this->assertSame(100, (int) $a->nombre_oeufs_initial + (int) $b->nombre_oeufs_initial);
        $this->assertSame(['E60', 'E40'], [$a->incubateur, $b->incubateur]);
        $this->assertSame(100, (int) DB::table('resa_lots_previsionnels')->whereIn('id_lot', [$id, $idB])->sum('nombre_prevu'));
        $this->assertStringEndsWith(' A', $a->nom_lot);
    }

    public function test_rec_inc_005_eclosion_a_zero_apres_mirage_nul(): void
    {
        $this->travelTo('2026-06-01 08:00');
        [, $lot] = $this->lotAIncuber(30);
        $id = Incubation::charger([$lot], $this->equipement('Inc', 500), '2026-06-01', null);
        $this->travelTo('2026-06-11 08:00');
        Incubation::mirer($id, 0, '2026-06-11', Empreinte::de($this->inc($id)));
        $this->travelTo('2026-06-22 08:00');
        $lv = Incubation::eclore($id, ['date_eclosion' => '2026-06-22', 'nombre_eclos' => 0, 'methode' => 'mesure'], Empreinte::de($this->inc($id)));
        $this->assertNull($lv);
        $this->assertSame('2026-06-22', $this->inc($id)->date_eclosion);
        $this->assertSame(0, DB::table('modLotVol_lots_volailles')->where('id_lot_incubation_source', $id)->count());
        $this->assertSame(0, DB::table('stock_poussins_jour')->where('id_lot', $id)->count());
    }

    public function test_eclosion_unique_et_formulaire_perime(): void
    {
        $this->travelTo('2026-06-01 08:00');
        [, $lot] = $this->lotAIncuber(20);
        $id = Incubation::charger([$lot], $this->equipement('Inc', 500), '2026-06-01', null);
        $vieille = Empreinte::de($this->inc($id));
        Incubation::transferer($id, $this->equipement('Ecl', 500, 'eclosoir'), null, '2026-06-01', $vieille);
        $this->travelTo('2026-06-22 08:00');
        try {
            Incubation::eclore($id, ['date_eclosion' => '2026-06-22', 'nombre_eclos' => 5, 'methode' => 'estimation', 'race' => 'X'], $vieille);
            $this->fail('Formulaire périmé accepté');
        } catch (RegleMetier $e) {
            $this->assertStringContainsString('changé', $e->getMessage());
        }
        $e = Empreinte::de($this->inc($id));
        Incubation::eclore($id, ['date_eclosion' => '2026-06-22', 'nombre_eclos' => 5, 'methode' => 'estimation', 'race' => 'X'], $e);
        $this->expectExceptionMessage('déjà enregistrée');
        Incubation::eclore($id, ['date_eclosion' => '2026-06-22', 'nombre_eclos' => 5, 'methode' => 'estimation', 'race' => 'X'], Empreinte::de($this->inc($id)));
    }

    public function test_int01_des_oeufs_aux_poussins(): void
    {
        $this->travelTo('2026-06-01 08:00');
        $b = $this->bande(80);
        $p = Ponte::saisir(['id_bande' => $b, 'date' => '2026-06-01', 'ramassage_matin' => 35, 'ramassage_apres_midi' => 25]);
        $s = Ponte::valider($p, 4, Empreinte::de($this->ligne('suivi_ponte', $p)));
        $inc = StockOeufs::affecter($s, 'incuber', 40, Empreinte::de($this->ligne('stock_oeufs_ramasses', $s)));
        StockOeufs::affecter($s, 'vendre', 16, Empreinte::de($this->ligne('stock_oeufs_ramasses', $s)));
        $this->assertTrue(StockOeufs::audit($this->ligne('stock_oeufs_ramasses', $s))['ok']);

        $id = Incubation::charger([$inc], $this->equipement('Inc', 500), '2026-06-01', null);
        $this->travelTo('2026-06-11 08:00');
        Incubation::mirer($id, 32, '2026-06-11', Empreinte::de($this->inc($id)));
        Incubation::transferer($id, $this->equipement('Ecl', 500, 'eclosoir'), null, '2026-06-11', Empreinte::de($this->inc($id)));
        $this->travelTo('2026-06-22 08:00');
        $lv = Incubation::eclore($id, ['date_eclosion' => '2026-06-22', 'nombre_eclos' => 24, 'methode' => 'mesure', 'race' => 'Locale'], Empreinte::de($this->inc($id)));

        // Conservation 56 = 40 + 16 ; lot volaille de 24 ; stock et lot non dupliqués (EC05).
        $this->assertSame(56, (int) DB::table('lots_oeufs_ramasses')->where('id_stock_oeufs', $s)->sum('quantite'));
        $this->assertSame(24, (int) $this->ligne('modLotVol_lots_volailles', $lv)->effectif_en_cours);
        $this->assertSame(1, DB::table('modLotVol_lots_volailles')->where('id_lot_incubation_source', $id)->count());
        $this->assertSame(1, DB::table('stock_poussins_jour')->where('id_lot', $id)->count());
        $this->assertSame('75', $this->inc($id)->taux_eclosion); // 24 / 32
        $this->assertSame('80', $this->inc($id)->taux_fertilite); // 32 / 40
    }

    public function test_ec22_renommage_refuse_si_des_lots_portent_le_nom(): void
    {
        $this->travelTo('2026-06-01 08:00');
        [, $lot] = $this->lotAIncuber(10);
        $i = $this->equipement('Historique', 100);
        Incubation::charger([$lot], $i, '2026-06-01', null);
        $this->expectExceptionMessage('renommé');
        Incubation::enregistrerEquipement($i, ['nom' => 'Nouveau nom', 'capacite' => 100, 'type' => 'incubateur']);
    }
}
