<?php

namespace Tests\Feature\Lot2;

use App\Domaine\Ponte;
use App\Domaine\Reproducteurs;
use App\Domaine\StockOeufs;
use App\Exceptions\RegleMetier;
use App\Support\Empreinte;
use Illuminate\Support\Facades\DB;

/** REC-REP-001..003, REC-PON-001..003, REC-OEU-001..002 */
class OeufsTest extends VolaillesTestCase
{
    public function test_rec_rep_001_retrait_superieur_a_l_effectif_refuse(): void
    {
        $b = $this->bande(5);
        try {
            Reproducteurs::mouvement($b, ['type_mouvement' => 'retrait', 'sexe' => 'femelle', 'quantite' => 6, 'date_mouvement' => today()->toDateString(), 'commentaire' => 'test']);
            $this->fail('Retrait accepté');
        } catch (RegleMetier $e) {
            $this->assertStringContainsString('effectif actuel', $e->getMessage());
        }
        $this->assertSame(5, (int) $this->ligne('bandes_parentales', $b)->nb_femelles);
        $this->assertSame(0, DB::table('modRepro_mouvements')->where('id_bande', $b)->count());
    }

    public function test_rec_rep_002_entree_de_males(): void
    {
        $b = $this->bande(80, 10);
        Reproducteurs::mouvement($b, ['type_mouvement' => 'entrée', 'sexe' => 'mâle', 'quantite' => 3, 'date_mouvement' => today()->toDateString(), 'commentaire' => 'achat']);
        $this->assertSame(13, (int) $this->ligne('bandes_parentales', $b)->nb_males);
        $m = DB::table('modRepro_mouvements')->where('id_bande', $b)->first();
        $this->assertNotNull($m->created_at);
        $this->assertSame('mâle', $m->sexe);
    }

    public function test_mouvement_date_future_refuse(): void
    {
        $this->expectException(RegleMetier::class);
        Reproducteurs::mouvement($this->bande(), ['type_mouvement' => 'entrée', 'sexe' => 'mâle', 'quantite' => 1, 'date_mouvement' => today()->addDay()->toDateString(), 'commentaire' => 'x']);
    }

    public function test_rec_rep_003_bande_reformee_indisponible_pour_la_ponte(): void
    {
        $b = $this->bande(80, 10, 'poulet', ['reformee' => 1]);
        $this->expectExceptionMessage('bandes actives');
        Ponte::saisir(['id_bande' => $b, 'date' => today()->toDateString(), 'ramassage_matin' => 10, 'ramassage_apres_midi' => 5]);
    }

    public function test_rec_pon_001_taux_puis_validation_et_stock(): void
    {
        $b = $this->bande(80);
        $id = Ponte::saisir(['id_bande' => $b, 'date' => today()->toDateString(), 'ramassage_matin' => 35, 'ramassage_apres_midi' => 25]);
        $l = $this->ligne('suivi_ponte', $id);
        $this->assertSame('75.00', $l->taux_ponte);
        $this->assertSame('attente', Ponte::etat($l));

        $idStock = Ponte::valider($id, 4, Empreinte::de($l));
        $stock = $this->ligne('stock_oeufs_ramasses', $idStock);
        $this->assertSame(56, (int) $stock->total_oeufs);
        $this->assertSame('attente', $stock->statut);
        $this->assertSame('valide', Ponte::etat($this->ligne('suivi_ponte', $id)));
    }

    public function test_rec_pon_002_double_validation_refusee(): void
    {
        $b = $this->bande(80);
        $id = Ponte::saisir(['id_bande' => $b, 'date' => today()->toDateString(), 'ramassage_matin' => 30, 'ramassage_apres_midi' => 0]);
        $empreinte = Empreinte::de($this->ligne('suivi_ponte', $id));
        Ponte::valider($id, 0, $empreinte);
        try {
            Ponte::valider($id, 0, $empreinte);
            $this->fail('Seconde validation acceptée');
        } catch (RegleMetier) {
        }
        $this->assertSame(1, DB::table('stock_oeufs_ramasses')->where('id_bande', $b)->count());
    }

    public function test_rec_pon_003_collecte_superieure_aux_femelles_et_validation_a_zero(): void
    {
        $b = $this->bande(80);
        try {
            Ponte::saisir(['id_bande' => $b, 'date' => today()->toDateString(), 'ramassage_matin' => 41, 'ramassage_apres_midi' => 40]);
            $this->fail('81 œufs acceptés');
        } catch (RegleMetier $e) {
            $this->assertStringContainsString('dépasse', $e->getMessage());
        }
        $id = Ponte::saisir(['id_bande' => $b, 'date' => today()->toDateString(), 'ramassage_matin' => 2, 'ramassage_apres_midi' => 1]);
        $idStock = Ponte::valider($id, 3, Empreinte::de($this->ligne('suivi_ponte', $id)));
        $stock = $this->ligne('stock_oeufs_ramasses', $idStock);
        $this->assertSame(0, (int) $stock->total_oeufs);
        $this->assertSame('affecte', $stock->statut);
    }

    public function test_saisie_modifiable_tant_qu_en_attente_puis_bloquee(): void
    {
        $b = $this->bande(80);
        $id = Ponte::saisir(['id_bande' => $b, 'date' => today()->toDateString(), 'ramassage_matin' => 10, 'ramassage_apres_midi' => 10]);
        $l = $this->ligne('suivi_ponte', $id);
        Ponte::saisir(['id_bande' => $b, 'date' => today()->toDateString(), 'ramassage_matin' => 12, 'ramassage_apres_midi' => 10, 'empreinte' => Empreinte::de($l)]);
        $this->assertSame(1, DB::table('suivi_ponte')->where('id_bande', $b)->count());
        $this->assertSame(12, (int) $this->ligne('suivi_ponte', $id)->ramassage_matin);
        // Formulaire périmé (ancienne empreinte) : refusé.
        $this->expectExceptionMessage('a changé');
        Ponte::saisir(['id_bande' => $b, 'date' => today()->toDateString(), 'ramassage_matin' => 1, 'ramassage_apres_midi' => 1, 'empreinte' => Empreinte::de($l)]);
    }

    private function stockValide(int $valides): int
    {
        $b = $this->bande(200);
        $id = Ponte::saisir(['id_bande' => $b, 'date' => today()->toDateString(), 'ramassage_matin' => $valides, 'ramassage_apres_midi' => 0]);

        return Ponte::valider($id, 0, Empreinte::de($this->ligne('suivi_ponte', $id)));
    }

    public function test_rec_oeu_001_et_002_affectations_et_conservation(): void
    {
        $s = $this->stockValide(56);
        StockOeufs::affecter($s, 'incuber', 40, Empreinte::de($this->ligne('stock_oeufs_ramasses', $s)));
        try {
            StockOeufs::affecter($s, 'vendre', 17, Empreinte::de($this->ligne('stock_oeufs_ramasses', $s)));
            $this->fail('17 sur 16 accepté');
        } catch (RegleMetier $e) {
            $this->assertStringContainsString('dépasse', $e->getMessage());
        }
        $this->assertSame(1, DB::table('lots_oeufs_ramasses')->where('id_stock_oeufs', $s)->count());
        StockOeufs::affecter($s, 'vendre', 16, Empreinte::de($this->ligne('stock_oeufs_ramasses', $s)));
        $stock = $this->ligne('stock_oeufs_ramasses', $s);
        $this->assertSame(0, (int) $stock->total_oeufs);
        $this->assertSame('affecte', $stock->statut);
        $audit = StockOeufs::audit($stock);
        $this->assertTrue($audit['ok']);
        $this->assertSame(56, $audit['valides']);
    }

    public function test_affectation_bloquee_si_conservation_rompue(): void
    {
        $s = $this->stockValide(30);
        DB::table('stock_oeufs_ramasses')->where('id', $s)->update(['total_oeufs' => 31]);
        $this->expectExceptionMessage('Conservation');
        StockOeufs::affecter($s, 'vendre', 1, Empreinte::de($this->ligne('stock_oeufs_ramasses', $s)));
    }
}
