<?php

namespace Tests\Feature\Lot4;

use App\Domaine\Exercices;
use App\Domaine\Ovins\Animaux;
use App\Domaine\Ovins\Inventaires;
use App\Domaine\Ovins\Medias;
use App\Domaine\Ovins\Mouvements;
use App\Domaine\Ovins\Referentiels;
use App\Domaine\Ovins\Reproduction;
use App\Domaine\Ovins\Soins;
use App\Domaine\Tresorerie;
use App\Exceptions\RegleMetier;
use App\Support\Empreinte;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** REC-OVA, REC-OVR, REC-OVS, REC-OVM, REC-OVI et parcours INT05. */
class OvinsTest extends OvinsTestCase
{
    private function refus(callable $f, string $extrait = ''): void
    {
        try {
            $f();
            $this->fail('Action acceptée à tort');
        } catch (RegleMetier $e) {
            if ($extrait) {
                $this->assertStringContainsString($extrait, $e->getMessage());
            }
        }
    }

    public function test_rec_ova_001_arbre_meme_si_le_pere_est_vendu(): void
    {
        $s = $this->site();
        $l = $this->lot($s);
        $gm = $this->animal($s, $l, 'F', ['date_naissance' => '2018-01-01']);
        $mere = $this->animal($s, $l, 'F', ['date_naissance' => '2020-01-01', 'id_mere' => $gm]);
        $pere = $this->animal($s, $l, 'M', ['date_naissance' => '2019-01-01']);
        $agneau = $this->animal($s, $l, 'M', ['date_naissance' => '2024-01-01', 'id_mere' => $mere, 'id_pere' => $pere]);
        Mouvements::sortir($pere, ['type_sortie' => 'vente', 'date_sortie' => today()->toDateString(), 'prix' => '150000', 'client_nom' => 'Ali', 'client_contact' => '771234567']);
        $arbre = Animaux::arbre($agneau);
        $this->assertSame($mere, (int) $arbre['racine']->mere->id_animal);
        $this->assertSame($pere, (int) $arbre['racine']->pere->id_animal);
        $this->assertSame('vendu', $arbre['racine']->pere->statut);
        $this->assertSame($gm, (int) $arbre['racine']->mere->mere->id_animal);
        // Un descendant ne peut pas devenir parent de son ascendant.
        $this->refus(fn () => Animaux::modifier($mere, ['sexe' => 'F', 'id_mere' => $agneau] + (array) $this->a($mere), Empreinte::de($this->a($mere))));
    }

    public function test_rec_ova_002_medias_jugés_sur_le_contenu(): void
    {
        config(['somadi.ovins.medias.dossier' => sys_get_temp_dir().'/medias-test-'.Str::random(6)]);
        $s = $this->site();
        $id = $this->animal($s, null);
        $png = UploadedFile::fake()->image('photo.png', 20, 20);
        $m = Medias::ajouter($id, $png, 'adulte', 'Profil');
        $this->assertNotNull(Medias::chemin(DB::table('OVINS_animaux_medias')->where('id_media', $m)->first()));
        $this->assertSame($m, Medias::photo($id));
        // Fichier texte déguisé en .jpg → refusé sur le type réel.
        $faux = UploadedFile::fake()->createWithContent('photo.jpg', 'ceci n’est pas une image');
        $this->refus(fn () => Medias::ajouter($id, $faux, 'adulte', null), 'text/plain');
        // Image au-delà de 5 Mio → refusée.
        config(['somadi.ovins.medias.max_image' => 50]);
        $this->refus(fn () => Medias::ajouter($id, UploadedFile::fake()->image('grande.png', 200, 200), 'adulte', null), 'limite');
        Medias::supprimer($id, $m);
        $this->assertSame(0, DB::table('OVINS_animaux_medias')->where('id_animal', $id)->count());
    }

    public function test_rec_ova_003_ecart_fiches_effectif_et_ec18(): void
    {
        $s = $this->site();
        $l = $this->lot($s, 'engraissement', 10);
        $this->animal($s, $l, 'M', [], false);
        $a = $this->animal($s, $l, 'M');
        $this->assertSame(11, $this->effectif($l));
        $ligne = Referentiels::ecartsLots()->firstWhere('id_lot', $l);
        $this->assertSame([2, 11], [(int) $ligne->fiches, (int) $ligne->effectif_encours]);
        Referentiels::ajusterEffectif($l, 2, 'Comptage physique du 1er novembre', today()->toDateString());
        $this->assertSame(2, $this->effectif($l));
        $this->assertSame(-9, (int) DB::table('OVINS_mouvements')->where('id_lot', $l)->where('type_mvt', 'ajustement')->value('quantite'));
        // EC18 : la modification de fiche ne change ni lot ni site ni statut.
        $autre = $this->lot($this->site());
        Animaux::modifier($a, ['sexe' => 'M', 'code_animal' => 'NOUVEAU1', 'id_lot' => $autre, 'statut' => 'vendu'], Empreinte::de($this->a($a)));
        $this->assertSame([$l, 'en_ferme', 'NOUVEAU1'], [(int) $this->a($a)->id_lot, $this->a($a)->statut, $this->a($a)->code_animal]);
        // Numéro de boucle unique.
        $this->refus(fn () => $this->animal($s, $l, 'F', ['code_animal' => 'NOUVEAU1']), 'déjà utilisé');
    }

    public function test_rec_ovr_001_a_003_saillie_agnelage_et_naissances_uniques(): void
    {
        $this->travelTo('2026-06-10 09:00');
        $s = $this->site();
        $l = $this->lot($s);
        $agx = $this->lot($s, 'agneaux');
        $mere = $this->animal($s, $l, 'F');
        $pere = $this->animal($s, $l, 'M');
        $r = Reproduction::enregistrer(null, ['id_mere' => $mere, 'id_pere' => $pere, 'date_saillie' => '2026-01-01']);
        $this->assertSame('2026-05-31', DB::table('OVINS_repro')->where('id_repro', $r['id'])->value('date_agnelage_prevu'));
        $d = ['id_mere' => $mere, 'id_pere' => $pere, 'date_saillie' => '2026-01-01', 'date_agnelage_reel' => '2026-06-01', 'nb_agneaux_vivants' => 3, 'id_lot_agneaux' => $agx];
        $this->refus(fn () => Reproduction::enregistrer($r['id'], $d + ['nb_agneaux_males' => 1, 'nb_agneaux_femelles' => 1]), 'mâles + femelles');
        $res = Reproduction::enregistrer($r['id'], $d + ['nb_agneaux_males' => 1, 'nb_agneaux_femelles' => 2]);
        $this->assertCount(3, $res['agneaux']);
        $this->assertSame(3, $this->effectif($agx));
        $ag = $this->a($res['agneaux'][0]);
        $this->assertSame([$mere, $pere, 'M', '2026-06-01'], [(int) $ag->id_mere, (int) $ag->id_pere, $ag->sexe, $ag->date_naissance]);
        // REC-OVR-003 : réenvoi → aucun second jeu ; les effectifs liés ne sont plus modifiables.
        $this->assertSame([], Reproduction::enregistrer($r['id'], $d + ['nb_agneaux_males' => 1, 'nb_agneaux_femelles' => 2])['agneaux']);
        $this->assertSame(3, DB::table('OVINS_animaux')->where('id_mere', $mere)->count());
        $this->refus(fn () => Reproduction::enregistrer($r['id'], ['nb_agneaux_vivants' => 4, 'nb_agneaux_males' => 2, 'nb_agneaux_femelles' => 2] + $d), 'déjà liées');
        $this->refus(fn () => Reproduction::enregistrer(null, ['id_mere' => $pere]), 'femelle');
        $this->refus(fn () => Reproduction::enregistrer(null, ['id_mere' => $mere, 'date_saillie' => '2026-07-01']), 'futur');
    }

    public function test_rec_ovr_004_agnelles_pretes_a_270_jours(): void
    {
        $s = $this->site();
        $l = $this->lot($s, 'agnelles');
        $a269 = $this->animal($s, $l, 'F', ['date_naissance' => today()->subDays(269)->toDateString(), 'date_entree' => today()->subDays(200)->toDateString()]);
        $a270 = $this->animal($s, $l, 'F', ['date_naissance' => today()->subDays(270)->toDateString(), 'date_entree' => today()->subDays(200)->toDateString()]);
        $ids = Reproduction::agnellesPretes()->pluck('id_animal')->map(fn ($x) => (int) $x)->all();
        $this->assertContains($a270, $ids);
        $this->assertNotContains($a269, $ids);
        // Intégration : destination reproductrices obligatoire, modèle d'entrée appliqué.
        $type = Soins::enregistrerType(null, ['nom_soin' => 'Déparasitage '.Str::random(3)]);
        $modele = Soins::enregistrerModele(null, ['nom_modele' => 'Entrée repro', 'categorie' => 'entree_'.Str::random(5), 'cible' => 'animal', 'actif' => 1], [['jour_offset' => 0, 'id_type_soin' => $type], ['jour_offset' => 14, 'id_type_soin' => $type]]);
        $this->refus(fn () => Mouvements::transferer($a270, $this->lot($s, 'engraissement'), today()->toDateString(), true), 'Reproductrices');
        Mouvements::transferer($a270, $this->lot($s, 'reproductrices'), today()->toDateString(), true, $modele);
        $this->assertSame(1, (int) $this->a($a270)->est_reproducteur);
        $this->assertSame(2, DB::table('OVINS_soins_realises')->where('id_animal', $a270)->where('statut', 'planifie')->count());
    }

    public function test_calendrier_de_lot(): void
    {
        $cycles = Reproduction::calculerCycles('2026-01-01', config('somadi.ovins.calendrier'));
        $this->assertCount(4, $cycles); // 3 ans × 12 / 9 mois
        $this->assertSame(['2026-05-31', '2026-08-29', '2026-09-28', '2026-10-01', '2026-06-07', '2026-06-21', '2026-09-10'],
            [$cycles[0]['date_agnelage_prevu'], $cycles[0]['date_fin_allaitement'], $cycles[0]['date_prepa'], $cycles[0]['date_prochaine_lutte'], $cycles[0]['post_mb_deb'], $cycles[0]['post_mb_fin'], $cycles[0]['pre_lutte_deb']]);
        $s = $this->site();
        $l = $this->lot($s);
        $this->assertSame(4, Reproduction::genererCalendrier($l, '2026-01-01', config('somadi.ovins.calendrier')));
        $this->assertSame(8, DB::table('OVINS_lot_soins_planifies')->where('id_lot', $l)->count());
        $plan = DB::table('OVINS_lot_soins_planifies')->where('id_lot', $l)->orderBy('date_debut')->first();
        $type = Soins::enregistrerType(null, ['nom_soin' => 'Vitamines']);
        $action = Reproduction::ajouterAction($plan->id_soin_plan, ['date_soin' => $plan->date_debut, 'id_type_soin' => $type]);
        $soin = DB::table('OVINS_soins_realises')->where('id_action', $action)->first();
        Soins::marquerRealise($soin->id_soin, Empreinte::de($soin), $plan->date_debut <= today()->toDateString() ? $plan->date_debut : today()->toDateString());
        $this->assertSame('realise', DB::table('OVINS_lot_soins_actions')->where('id_action', $action)->value('statut'));
        // RG-OVR-011 : plus de régénération une fois des actions suivies ; aucune naissance créée.
        $this->refus(fn () => Reproduction::genererCalendrier($l, '2026-02-01', config('somadi.ovins.calendrier')), 'régénéré');
        $this->assertSame(0, DB::table('OVINS_mouvements')->where('id_lot', $l)->where('type_mvt', 'naissance')->count());
    }

    public function test_rec_ovs_001_a_003_modeles_cibles_et_annulation(): void
    {
        $s = $this->site();
        $l = $this->lot($s, 'engraissement', 5);
        $a = $this->animal($s, $l, 'F');
        $t1 = Soins::enregistrerType(null, ['nom_soin' => 'Vaccin PPR']);
        $t2 = Soins::enregistrerType(null, ['nom_soin' => 'Rappel PPR']);
        $cat = 'agneaux_'.Str::random(4);
        $m = Soins::enregistrerModele(null, ['nom_modele' => 'Agneaux', 'categorie' => $cat, 'cible' => 'tous', 'actif' => 1], [['jour_offset' => 0, 'id_type_soin' => $t1], ['jour_offset' => 7, 'id_type_soin' => $t2, 'dose' => '2', 'unite' => 'ml']]);
        $this->refus(fn () => Soins::enregistrerModele(null, ['nom_modele' => 'Doublon', 'categorie' => $cat, 'actif' => 1], [['jour_offset' => 0, 'id_type_soin' => $t1]]), 'existe déjà');
        $this->assertSame(['crees' => 2, 'ignores' => 0], Soins::appliquer($m, 'lot', $l, '2026-06-01'));
        $this->assertSame(['2026-06-01', '2026-06-08'], DB::table('OVINS_soins_realises')->where('id_lot', $l)->orderBy('date_soin')->pluck('date_soin')->all());
        $this->assertSame(['crees' => 0, 'ignores' => 2], Soins::appliquer($m, 'lot', $l, '2026-06-01'));
        Soins::appliquer($m, 'animal', $a, '2026-06-01');
        // REC-OVS-002 : cibles distinctes, aucun doublon individuel implicite.
        $this->assertSame(2, DB::table('OVINS_soins_realises')->where('cible', 'lot')->where('id_lot', $l)->whereNull('id_animal')->count());
        $this->assertSame(2, DB::table('OVINS_soins_realises')->where('cible', 'animal')->where('id_animal', $a)->whereNull('id_lot')->count());
        $s1 = DB::table('OVINS_soins_realises')->where('id_lot', $l)->orderBy('date_soin')->first();
        Soins::marquerRealise($s1->id_soin, Empreinte::de($s1), '2026-06-01');
        $this->refus(fn () => Soins::marquerRealise($s1->id_soin, Empreinte::de(DB::table('OVINS_soins_realises')->where('id_soin', $s1->id_soin)->first())), 'plus planifié');
        Soins::annulerRealisation($s1->id_soin, 'Erreur de lot');
        $this->assertSame('planifie', DB::table('OVINS_soins_realises')->where('id_soin', $s1->id_soin)->value('statut'));
    }

    public function test_rec_ovm_001_transfert(): void
    {
        $sa = $this->site('A');
        $sb = $this->site('B');
        $la = $this->lot($sa, 'engraissement');
        $lb = $this->lot($sb, 'engraissement');
        $a = $this->animal($sa, $la, 'M');
        Mouvements::transferer($a, $lb, today()->toDateString());
        $this->assertSame([0, 1, $sb, $lb], [$this->effectif($la), $this->effectif($lb), (int) $this->a($a)->id_site, (int) $this->a($a)->id_lot]);
        $this->assertSame(2, DB::table('OVINS_mouvements')->where('id_animal', $a)->where('type_mvt', 'transfert')->count());
        $this->refus(fn () => Mouvements::transferer($a, $lb, today()->toDateString()), 'déjà dans ce lot');
        $this->refus(fn () => Mouvements::transferer($a, $la, today()->addDay()->toDateString()), 'future');
    }

    public function test_rec_ovm_002_003_vente_et_seconde_vente_refusee(): void
    {
        $s = $this->site();
        $l = $this->lot($s, 'engraissement');
        $a = $this->animal($s, $l, 'M');
        $r = Mouvements::sortir($a, ['type_sortie' => 'vente', 'date_sortie' => today()->toDateString(), 'prix' => '150 000', 'client_nom' => 'Moussa', 'client_contact' => '77 111 22 33']);
        $v = DB::table('modVente_Ventes')->where('id', $r['vente'])->first();
        $this->assertSame(['ovin', 1, '150000.00', '150000.00', null], [$v->espece, (int) $v->quantite, $v->montant_total, $v->montant_reste_encaisser, $v->id_lot]);
        $this->assertSame(1, DB::table('modVente_ovins_liaisons')->where('id_animal', $a)->count());
        $this->assertSame(1, DB::table('OVINS_mouvements')->where('id_animal', $a)->where('type_mvt', 'vente')->count());
        $this->assertSame(['vendu', 0], [$this->a($a)->statut, $this->effectif($l)]);
        $this->assertSame(0, DB::table('modCompta_operations')->where('source_id', $r['vente'])->count());
        $this->refus(fn () => Mouvements::sortir($a, ['type_sortie' => 'vente', 'date_sortie' => today()->toDateString(), 'prix' => '1000', 'client_nom' => 'X', 'client_contact' => '771234567']), 'déjà sorti');
        $this->assertSame(0, $this->effectif($l));
        // RG-OVM-014 : mortalité → mort, prix ignoré.
        $b = $this->animal($s, $l, 'F');
        Mouvements::sortir($b, ['type_sortie' => 'mortalite', 'date_sortie' => today()->toDateString(), 'prix' => '5000', 'motif' => 'Maladie']);
        $this->assertSame(['mort', null], [$this->a($b)->statut, DB::table('OVINS_sorties')->where('id_animal', $b)->value('prix_unitaire')]);
    }

    public function test_rec_ovm_004_rapprochement_historique(): void
    {
        $s = $this->site();
        $a = $this->animal($s, null, 'M');
        // Sortie historique sans liaison (saisie par l'ancienne application).
        $date = today()->subDays(3)->toDateString();
        DB::table('OVINS_animaux')->where('id_animal', $a)->update(['statut' => 'vendu', 'date_sortie' => $date]);
        $sortie = DB::table('OVINS_sorties')->insertGetId(['cible' => 'animal', 'id_animal' => $a, 'id_site' => $s, 'type_sortie' => 'vente', 'date_sortie' => $date, 'quantite' => 1, 'prix_unitaire' => 120000, 'montant_total' => 120000, 'client_nom' => 'Awa Diop', 'client_contact' => '776543210']);
        $autre = DB::table('modVente_Ventes')->insertGetId(['date_vente' => $date, 'client_nom' => 'Autre client', 'client_tel' => '770000000', 'espece' => 'mouton', 'quantite' => 1, 'prix_unitaire' => 120000, 'montant_total' => 120000, 'montant_reste_encaisser' => 120000, 'statut' => 'A encaisser']);
        $this->refus(fn () => Mouvements::plan($sortie, ['mode' => 'existante', 'id_vente' => $autre, 'motif' => 'Même montant, autre client']), 'ne concorde pas');
        $bonne = DB::table('modVente_Ventes')->insertGetId(['date_vente' => $date, 'client_nom' => ' awa  diop', 'client_tel' => '776543210', 'espece' => 'Mouton', 'quantite' => 1, 'prix_unitaire' => 120000, 'montant_total' => 120000, 'montant_reste_encaisser' => 120000, 'statut' => 'A encaisser']);
        $p = Mouvements::plan($sortie, ['mode' => 'existante', 'id_vente' => $bonne, 'motif' => 'Reçu n° 45 retrouvé']);
        $this->assertSame($bonne, Mouvements::rapprocher($sortie, ['mode' => 'existante', 'id_vente' => $bonne, 'motif' => 'Reçu n° 45 retrouvé'], $p['empreinte']));
        $this->assertSame($bonne, (int) DB::table('modVente_ovins_liaisons')->where('id_sortie', $sortie)->value('id_vente'));

        // Création de la vente manquante avec une recette autonome rattachée.
        $b = $this->animal($s, null, 'M');
        DB::table('OVINS_animaux')->where('id_animal', $b)->update(['statut' => 'vendu', 'date_sortie' => $date]);
        $s2 = DB::table('OVINS_sorties')->insertGetId(['cible' => 'animal', 'id_animal' => $b, 'id_site' => $s, 'type_sortie' => 'vente', 'date_sortie' => $date, 'quantite' => 1, 'prix_unitaire' => 90000, 'montant_total' => 90000, 'client_nom' => 'Ibou', 'client_contact' => '781112233']);
        $op = Tresorerie::creerOperation(['type_op' => 'recette', 'date_op' => today()->toDateString(), 'categorie' => 'Vente ovins (Tabaski)', 'montant' => '50000', 'libelle' => 'Acompte Ibou', 'moyen_paiement' => 'Wave']);
        $d = ['mode' => 'creation', 'recettes' => [$op], 'motif' => 'Vente Tabaski non saisie au registre'];
        $p = Mouvements::plan($s2, $d);
        $vente = Mouvements::rapprocher($s2, $d, $p['empreinte']);
        $v = DB::table('modVente_Ventes')->where('id', $vente)->first();
        $this->assertSame(['ovin', '90000.00', '40000.00'], [$v->espece, $v->montant_total, $v->montant_reste_encaisser]);
        $this->assertTrue(Tresorerie::audit($v)['ok']);
        $this->refus(fn () => Mouvements::plan($s2, $d), 'déjà lié');
        $this->assertNotContains($vente, Exercices::controles(today()->toDateString(), (int) today()->year)['bloquants']['ovins']['ids']);
    }

    public function test_rec_ovi_001_a_004_inventaire(): void
    {
        $s = $this->site();
        $l = $this->lot($s, 'engraissement');
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = $this->animal($s, $l, $i % 2 ? 'M' : 'F', ['code_animal' => 'INV'.$i.Str::random(4)]);
        }
        $inv = Inventaires::ouvrir(['titre' => 'Comptage', 'site' => $s], $cle = (string) Str::uuid());
        $this->refus(fn () => Inventaires::ouvrir(['titre' => 'Comptage', 'site' => $s], $cle), 'double envoi');
        $this->assertSame(5, (int) DB::table('OVINS_inventaires')->where('id', $inv)->value('total_attendu'));
        // REC-OVI-001 : une vente après l'ouverture ne modifie pas la liste ; le rapprochement l'explique.
        Mouvements::sortir($ids[0], ['type_sortie' => 'vente', 'date_sortie' => today()->toDateString(), 'prix' => '100000', 'client_nom' => 'Client', 'client_contact' => '771234567']);
        $this->assertSame(5, DB::table('OVINS_inventaire_lignes')->where('inventaire_id', $inv)->count());
        $invRow = DB::table('OVINS_inventaires')->where('id', $inv)->first();
        $this->assertSame(['vente'], Inventaires::rapprochement($invRow)->pluck('type_mvt')->all());

        $lignes = DB::table('OVINS_inventaire_lignes')->where('inventaire_id', $inv)->orderBy('numero')->get();
        $ctrl = ['controleur' => 'Fatou', 'date_controle' => today()->toDateString()];
        $this->refus(fn () => Inventaires::pointer($inv, $lignes[0]->id, 'absent', 1, $ctrl), 'commentaire');
        Inventaires::pointer($inv, $lignes[0]->id, 'absent', 1, $ctrl + ['commentaire' => 'Vendu ce matin']);
        $this->refus(fn () => Inventaires::pointer($inv, $lignes[0]->id, 'present', 1, $ctrl), 'modifiée');
        // Groupe : une ligne déjà pointée annule tout.
        $this->refus(fn () => Inventaires::pointerGroupe($inv, [$lignes[0]->id => 2, $lignes[1]->id => 1], $ctrl), 'rien n’a été enregistré');
        $this->assertSame('non_pointe', DB::table('OVINS_inventaire_lignes')->where('id', $lignes[1]->id)->value('pointage'));
        $this->assertSame(3, Inventaires::pointerGroupe($inv, [$lignes[1]->id => 1, $lignes[2]->id => 1, $lignes[3]->id => 1], $ctrl));
        // REC-OVI-003 : un code déjà présent renvoie vers sa ligne.
        $r = Inventaires::ajouter($inv, ['code_animal' => $lignes[2]->code_animal, 'site_constate' => 'Parc 2', 'commentaire' => 'Vu deux fois'] + $ctrl, (string) Str::uuid());
        $this->assertTrue($r['existante']);
        $r = Inventaires::ajouter($inv, ['code_animal' => 'INCONNU-77', 'site_constate' => 'Parc 2', 'commentaire' => 'Boucle inconnue'] + $ctrl, (string) Str::uuid());
        $this->assertFalse($r['existante']);
        $this->assertSame(0, DB::table('OVINS_animaux')->where('code_animal', 'INCONNU-77')->count());
        // REC-OVI-002 : clôture refusée avec une ligne non pointée, réservée au rôle habilité (contrôlé par la route).
        $version = (int) DB::table('OVINS_inventaires')->where('id', $inv)->value('version');
        $this->refus(fn () => Inventaires::cloturer($inv, $version, 'Inventaire de novembre complet.'), 'pas pointées');
        Inventaires::pointer($inv, $lignes[4]->id, 'present', 1, $ctrl);
        $this->refus(fn () => Inventaires::cloturer($inv, $version, 'Inventaire de novembre complet.'), 'a changé');
        Inventaires::cloturer($inv, $version + 1, 'Inventaire de novembre complet.');
        // REC-OVI-004 : l'absent n'est pas devenu mort.
        $this->assertSame('vendu', $this->a($ids[0])->statut);
        $this->assertSame('en_ferme', $this->a($ids[1])->statut);
        $this->refus(fn () => Inventaires::pointer($inv, $lignes[1]->id, 'absent', 2, $ctrl + ['commentaire' => 'x']), 'clôturé');
    }

    public function test_int05_naissance_transfert_vente(): void
    {
        $s = $this->site();
        $repro = $this->lot($s, 'reproductrices');
        $agneaux = $this->lot($s, 'agneaux');
        $engr = $this->lot($s, 'engraissement');
        $mere = $this->animal($s, $repro, 'F');
        $pere = $this->animal($s, $repro, 'M');
        $r = Reproduction::enregistrer(null, ['id_mere' => $mere, 'id_pere' => $pere, 'date_saillie' => today()->subDays(150)->toDateString(),
            'date_agnelage_reel' => today()->subDays(2)->toDateString(), 'nb_agneaux_vivants' => 2, 'nb_agneaux_males' => 1, 'nb_agneaux_femelles' => 1, 'id_lot_agneaux' => $agneaux]);
        [$m, $f] = $r['agneaux'];
        $this->assertSame(2, $this->effectif($agneaux));
        Mouvements::transferer($m, $engr, today()->toDateString());
        $this->assertSame([1, 1], [$this->effectif($agneaux), $this->effectif($engr)]);
        $res = Mouvements::sortir($m, ['type_sortie' => 'vente', 'date_sortie' => today()->toDateString(), 'prix' => '75000', 'client_nom' => 'Boucherie', 'client_contact' => '771234567']);
        $this->assertSame([0, 1], [$this->effectif($engr), DB::table('modVente_ovins_liaisons')->where('id_vente', $res['vente'])->count()]);
        $this->assertSame([$mere, $pere], [(int) $this->a($m)->id_mere, (int) $this->a($m)->id_pere]);
        $this->assertSame(['naissance', 'transfert', 'transfert', 'vente'], DB::table('OVINS_mouvements')->where('id_animal', $m)->orderBy('id_mvt')->pluck('type_mvt')->all());
    }
}
