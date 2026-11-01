<?php

namespace Tests\Feature\Lot2;

use App\Domaine\Alimentation;
use App\Domaine\LotsVolailles;
use App\Domaine\Pesees;
use App\Domaine\Pilotage;
use App\Domaine\Soins;
use App\Exceptions\RegleMetier;
use App\Support\Empreinte;
use Illuminate\Support\Facades\DB;

/** REC-VOL, REC-POI, REC-ALI, REC-SAV, REC-PIL */
class ElevageTest extends VolaillesTestCase
{
    private function mvt(int $lot, string $type, int $q, array $extra = []): ?int
    {
        return LotsVolailles::mouvement($lot, $extra + ['type_mouvement' => $type, 'quantite' => $q, 'date_mouvement' => today()->toDateString(), 'commentaire' => 'test']);
    }

    public function test_rec_vol_001_effectif_et_vente_a_completer(): void
    {
        $lot = $this->lotVolaille(100);
        $this->mvt($lot, 'mortalité', 3);
        $vac = $this->mvt($lot, 'vente', 7);
        $this->assertSame(90, (int) $this->ligne('modLotVol_lots_volailles', $lot)->effectif_en_cours);
        $v = $this->ligne('modVente_Ventes_A_Completer', $vac);
        $this->assertSame([7, 'attente', $lot], [(int) $v->quantite, $v->statut, (int) $v->id_lot]);
        // La finalisation commerciale (lot 3) ne doit pas débiter à nouveau : simulée ici par le changement de statut.
        DB::table('modVente_Ventes_A_Completer')->where('id', $vac)->update(['statut' => 'traite']);
        $this->assertSame(90, (int) $this->ligne('modLotVol_lots_volailles', $lot)->effectif_en_cours);
    }

    public function test_sortie_superieure_a_l_effectif_et_motif_obligatoire(): void
    {
        $lot = $this->lotVolaille(5);
        try {
            $this->mvt($lot, 'sortie', 6);
            $this->fail('Sortie > effectif acceptée');
        } catch (RegleMetier) {
        }
        $this->expectExceptionMessage('motif');
        LotsVolailles::mouvement($lot, ['type_mouvement' => 'mortalité', 'quantite' => 1, 'date_mouvement' => today()->toDateString(), 'commentaire' => ' ']);
    }

    public function test_rec_vol_002_modifier_le_nombre_initial_ne_recalcule_pas(): void
    {
        $lot = $this->lotVolaille(100);
        $this->mvt($lot, 'mortalité', 4);
        $l = $this->ligne('modLotVol_lots_volailles', $lot);
        LotsVolailles::modifier($lot, ['nom_lot' => $l->nom_lot, 'espece' => 'poulet', 'race' => 'Cobb 500', 'nombre_initial' => 105, 'date_entree' => $l->date_entree], Empreinte::de($l));
        $l = $this->ligne('modLotVol_lots_volailles', $lot);
        $this->assertSame([105, 96], [(int) $l->nombre_initial, (int) $l->effectif_en_cours]);
    }

    public function test_rec_vol_003_programme_absent_aucune_creation_partielle(): void
    {
        $avant = DB::table('modLotVol_lots_volailles')->count();
        try {
            LotsVolailles::creer(['nom_lot' => 'Sans programme', 'espece' => 'pintade', 'race' => 'X', 'nombre_initial' => 50, 'date_entree' => today()->toDateString()], true);
            $this->fail('Création sans programme acceptée');
        } catch (RegleMetier $e) {
            $this->assertStringContainsString('programme', $e->getMessage());
        }
        $this->assertSame($avant, DB::table('modLotVol_lots_volailles')->count());
    }

    public function test_creation_avec_programme_et_28_reglages_thermiques(): void
    {
        $soin = DB::table('modSoinVacc_catalogue')->insertGetId(['nom' => 'Gumboro', 'type' => 'vaccin', 'produit_utilise' => 'Vac G', 'dose' => '1 goutte', 'voie_administration' => 'oculaire']);
        DB::table('modSoinVacc_programme_poussins')->insert([['espece' => 'poulet', 'jour_post_eclosion' => 7, 'id_soin' => $soin], ['espece' => 'poulet', 'jour_post_eclosion' => 14, 'id_soin' => $soin]]);
        $lot = LotsVolailles::creer(['nom_lot' => 'Avec programme', 'espece' => 'poulet', 'race' => 'Cobb', 'nombre_initial' => 50, 'date_entree' => '2026-06-01'], true);
        $this->assertSame(['2026-06-08', '2026-06-15'], DB::table('modSoinVacc_soins')->where('id_lot', $lot)->orderBy('date_prevue')->pluck('date_prevue')->all());
        $thermo = DB::table('modSoinVacc_Thermo_reglages')->where('id_lot', $lot)->orderBy('date_prevue')->get();
        $this->assertCount(28, $thermo);
        $this->assertSame(['34.0', '31.0', '29.0', '27.0'], [$thermo[0]->temperature_cible, $thermo[7]->temperature_cible, $thermo[14]->temperature_cible, $thermo[27]->temperature_cible]);
    }

    public function test_archivage(): void
    {
        $lot = $this->lotVolaille(10);
        LotsVolailles::changerStatut($lot, 'archive');
        $this->expectExceptionMessage('archivé');
        $this->mvt($lot, 'mortalité', 1);
    }

    public function test_sortie_avec_emplacements_multiples_exige_l_origine(): void
    {
        $lot = $this->lotVolaille(100);
        foreach ([1 => 'Bâtiment 1', 2 => 'Bâtiment 2'] as $id => $nom) {
            DB::table('batiments_engraissement')->insert(['id' => $id, 'nom_batiment' => $nom, 'capacite' => 500, 'actif' => 1]);
            DB::table('modInfra_emplacements')->insert(['id_batiment' => $id, 'code' => "B$id"]);
        }
        DB::table('modInfra_positions')->insert([['famille' => 'volaille', 'id_source' => $lot, 'id_batiment' => 1, 'quantite' => 60], ['famille' => 'volaille', 'id_source' => $lot, 'id_batiment' => 2, 'quantite' => 40]]);
        try {
            $this->mvt($lot, 'mortalité', 5);
            $this->fail('Sortie sans emplacement acceptée');
        } catch (RegleMetier $e) {
            $this->assertStringContainsString('emplacement', $e->getMessage());
        }
        $this->assertSame(100, (int) $this->ligne('modLotVol_lots_volailles', $lot)->effectif_en_cours);
        $this->assertSame(0, DB::table('modLotVol_mouvements')->where('id_lot', $lot)->count(), 'Le mouvement doit être annulé avec le reste');
        $this->mvt($lot, 'mortalité', 5, ['origine' => 2]);
        $this->assertSame(35, (int) DB::table('modInfra_positions')->where(['id_source' => $lot, 'id_batiment' => 2])->value('quantite'));
        $this->assertSame(95, (int) $this->ligne('modLotVol_lots_volailles', $lot)->effectif_en_cours);
    }

    public function test_periode_cloturee_bloque_une_vente_anterieure(): void
    {
        $lot = $this->lotVolaille(10);
        DB::table('modCompta_exercice_lock')->where('id', 1)->update(['closed_through' => today()->subDay()->toDateString()]);
        $this->expectExceptionMessage('clôturée');
        LotsVolailles::mouvement($lot, ['type_mouvement' => 'vente', 'quantite' => 1, 'date_mouvement' => today()->subDays(2)->toDateString(), 'commentaire' => 'vente']);
    }

    public function test_rec_poi_001_gain_quotidien(): void
    {
        $lot = $this->lotVolaille(10);
        Pesees::ajouter($lot, '2026-06-01', '1000');
        Pesees::ajouter($lot, '2026-06-08', '1 280,00');
        $g = Pesees::gain(Pesees::serie($lot));
        $this->assertEqualsWithDelta(40.0, $g['g_jour'], 0.0001);
        $this->expectException(RegleMetier::class);
        Pesees::ajouter($lot, '2026-06-09', '1,234');
    }

    public function test_rec_poi_002_a_004_rentabilite(): void
    {
        DB::table('modLot_Vol_ref_cout_age_poulet')->insert(['espece' => 'poulet', 'age_jours' => 20, 'cout_cumule_par_tete' => 2000, 'marge_pct' => 25]);
        $this->assertSame('2500.00', DB::table('modLot_Vol_ref_cout_age_poulet')->where('age_jours', 20)->value('prix_min_profitable'));

        $lot = $this->lotVolaille(10); // entré il y a 20 jours
        DB::table('modVente_Ventes')->insert(['date_vente' => today()->toDateString(), 'client_nom' => 'C', 'client_tel' => '770000000', 'quantite' => 2, 'prix_unitaire' => 3000, 'montant_total' => 6000, 'montant_reste_encaisser' => 6000, 'id_lot' => $lot]);
        DB::table('modLotVol_lots_volailles')->where('id', $lot)->update(['effectif_en_cours' => 8]);
        $r = Pesees::rentabilite($this->ligne('modLotVol_lots_volailles', $lot));
        $this->assertEqualsWithDelta(6000, $r['ventes_facturees'], 0.01); // REC-POI-003 : vente non encaissée comptée en ventes facturées
        $this->assertEqualsWithDelta(0, $r['encaisse'], 0.01);           // … mais pas en encaissé (EC08)
        $this->assertEqualsWithDelta(2000, $r['profit_realise'], 0.01);  // 6000 − 2 × 2000
        $this->assertTrue($r['complet']);

        // REC-POI-004 : une mortalité à un âge sans référence rend la marge non certifiée (EC09).
        DB::table('modLotVol_mouvements')->insert(['id_lot' => $lot, 'date_mouvement' => today()->subDays(5)->toDateString(), 'type_mouvement' => 'mortalité', 'quantite' => 1]);
        $r = Pesees::rentabilite($this->ligne('modLotVol_lots_volailles', $lot));
        $this->assertFalse($r['complet']);
        $this->assertSame([15], $r['ages_sans_reference']);
    }

    public function test_rec_ali_001_002_003_generation_transition_et_relance(): void
    {
        $a = DB::table('modAlim_types')->insertGetId(['nom_aliment' => 'Démarrage']);
        $b = DB::table('modAlim_types')->insertGetId(['nom_aliment' => 'Croissance']);
        $plan = DB::table('modAlim_plans')->insertGetId(['espece' => 'poulet', 'nom_plan' => 'Chair']);
        DB::table('modAlim_plan_lignes')->insert(['id_plan' => $plan, 'semaine' => 1, 'id_aliment' => $a, 'qte_par_tete' => 80]);
        DB::table('modAlim_plan_lignes')->insert(['id_plan' => $plan, 'semaine' => 3, 'id_aliment' => $a, 'qte_par_tete' => 80, 'is_transition' => 1, 'id_aliment_source' => $a, 'id_aliment_cible' => $b, 'pct_source' => 60, 'pct_cible' => 40]);

        $l1 = $this->lotVolaille(200, ['id_plan' => $plan, 'date_entree' => today()->subDays(3)->toDateString()]);
        $l2 = $this->lotVolaille(200, ['id_plan' => $plan, 'date_entree' => today()->subDays(20)->toDateString()]);
        $r = Alimentation::generer(today()->toDateString(), [$l1, $l2]);
        $this->assertSame(3, $r['creees']);
        $this->assertEqualsWithDelta(16000, (float) DB::table('modAlim_distributions')->where('id_lot', $l1)->value('quantite_totale'), 0.01);
        $q2 = DB::table('modAlim_distributions')->where('id_lot', $l2)->pluck('quantite_totale', 'id_type_aliment');
        $this->assertEqualsWithDelta(9600, (float) $q2[$a], 0.01);
        $this->assertEqualsWithDelta(6400, (float) $q2[$b], 0.01);

        $r = Alimentation::generer(today()->toDateString(), [$l1, $l2]);
        $this->assertSame([0, 3], [$r['creees'], $r['deja']]);
        $this->assertEqualsWithDelta(16000, (float) DB::table('modAlim_distributions')->where('id_lot', $l1)->value('quantite_totale'), 0.01);
    }

    public function test_ec12_saisie_manuelle_cumulee_et_correction_tracee(): void
    {
        $a = DB::table('modAlim_types')->insertGetId(['nom_aliment' => 'Finition']);
        $lot = $this->lotVolaille(50);
        Alimentation::distribuer(['id_lot' => $lot, 'id_type_aliment' => $a, 'date_distribution' => today()->toDateString(), 'quantite_kg' => '2,5', 'remarque' => null]);
        Alimentation::distribuer(['id_lot' => $lot, 'id_type_aliment' => $a, 'date_distribution' => today()->toDateString(), 'quantite_kg' => '1.25', 'remarque' => 'complément']);
        $d = DB::table('modAlim_distributions')->where('id_lot', $lot)->first();
        $this->assertEqualsWithDelta(3750, (float) $d->quantite_totale, 0.01);
        Alimentation::corriger($d->id, '3', 'erreur de pesée');
        $this->assertEqualsWithDelta(3000, (float) DB::table('modAlim_distributions')->where('id', $d->id)->value('quantite_totale'), 0.01);
        $this->assertDatabaseHas('app_journal', ['action' => 'alimentation.correction', 'motif' => 'erreur de pesée']);
    }

    public function test_rec_ali_004_indice_de_consommation(): void
    {
        $this->assertEqualsWithDelta(2.0, Alimentation::indice(20, 10, 1.0), 0.0001);
        $this->assertNull(Alimentation::indice(20, 10, 0.0));
    }

    private function soin(int $lot, string $etat = 'planifie', ?string $change = null, ?string $date = null): int
    {
        $s = DB::table('modSoinVacc_catalogue')->insertGetId(['nom' => 'Vitamines', 'type' => 'soin']);

        return DB::table('modSoinVacc_soins')->insertGetId(['id_lot' => $lot, 'date_prevue' => $date ?? today()->toDateString(), 'id_soin' => $s, 'etat' => $etat, 'date_changement_etat' => $change, 'origine' => 'Soin Occasionnel']);
    }

    public function test_rec_sav_001_verrou_24h(): void
    {
        $lot = $this->lotVolaille(10);
        $ancien = $this->soin($lot, 'realise', now()->subHours(25)->toDateTimeString());
        try {
            Soins::changerEtat($ancien, 'annule', null, Empreinte::de($this->ligne('modSoinVacc_soins', $ancien)));
            $this->fail('Changement après 24 h accepté');
        } catch (RegleMetier $e) {
            $this->assertStringContainsString('24 heures', $e->getMessage());
        }
        $planifie = $this->soin($lot);
        Soins::changerEtat($planifie, 'realise', 'fait', Empreinte::de($this->ligne('modSoinVacc_soins', $planifie)));
        $s = $this->ligne('modSoinVacc_soins', $planifie);
        $this->assertSame('realise', $s->etat);
        $this->assertStringContainsString('Planifié → Réalisé', $s->observation);
    }

    public function test_rec_sav_002_pas_de_doublon_occasionnel(): void
    {
        $lot = $this->lotVolaille(10);
        $cat = DB::table('modSoinVacc_catalogue')->insertGetId(['nom' => 'Coccidiostatique', 'type' => 'soin', 'produit_utilise' => 'Amprolium']);
        Soins::ajouterOccasionnel($lot, $cat, today()->toDateString(), null);
        try {
            Soins::ajouterOccasionnel($lot, $cat, today()->toDateString(), null);
            $this->fail('Doublon accepté');
        } catch (RegleMetier) {
        }
        $this->assertSame(1, DB::table('modSoinVacc_soins')->where(['id_lot' => $lot, 'id_soin' => $cat])->count());
        $this->assertSame('Amprolium', DB::table('modSoinVacc_soins')->where('id_lot', $lot)->value('produit'));
    }

    public function test_rec_sav_003_formulaire_ancien_refuse(): void
    {
        $lot = $this->lotVolaille(10);
        $id = $this->soin($lot);
        $empreinte = Empreinte::de($this->ligne('modSoinVacc_soins', $id));
        Soins::changerEtat($id, 'realise', null, $empreinte);
        $this->expectExceptionMessage('a changé');
        Soins::changerEtat($id, 'annule', null, $empreinte);
    }

    public function test_rec_pil_001_retards_et_eclosions_proches(): void
    {
        $lot = $this->lotVolaille(10);
        $this->soin($lot, 'planifie', null, today()->subDay()->toDateString());
        $this->soin($lot, 'realise', now()->toDateTimeString(), today()->subDay()->toDateString());
        $this->soin($lot, 'planifie', null, today()->toDateString());
        $i = Pilotage::indicateurs(auth()->user());
        $this->assertSame(1, $i['soins_retard']);
        $this->assertSame(1, $i['soins_jour']);
        $this->assertArrayHasKey('eclosions_proches', $i);
    }

    public function test_rec_pil_002_un_indicateur_vide_n_empeche_pas_les_autres_et_respecte_les_droits(): void
    {
        $ovin = $this->utilisateur('associe ovin');
        $i = Pilotage::indicateurs($ovin);
        $this->assertArrayNotHasKey('soins_jour', $i);
        $this->actingAs($ovin)->get('/')->assertOk();
    }
}
