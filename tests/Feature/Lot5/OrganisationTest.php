<?php

namespace Tests\Feature\Lot5;

use App\Domaine\LotsVolailles;
use App\Domaine\Organisation\Activites;
use App\Domaine\Organisation\Infrastructures;
use App\Domaine\Organisation\Marketing;
use App\Domaine\Organisation\Planning;
use App\Domaine\Organisation\Recurrence;
use App\Domaine\Organisation\Rh;
use App\Domaine\Ovins\Animaux;
use App\Domaine\Ovins\Referentiels;
use App\Exceptions\RegleMetier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** REC-INF, REC-IFP, REC-ACT, REC-RH, REC-MKT et parcours INT08 à INT10. */
class OrganisationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->chargerMatriceParDefaut();
        $this->actingAs($this->utilisateur('admin'));
    }

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

    private function site(): int
    {
        return Referentiels::enregistrerSite(null, ['code_site' => Str::upper(Str::random(6)), 'nom_site' => 'Site '.Str::random(3), 'actif' => 1]);
    }

    private function lieu(int $site, string $famille = 'volaille', int $capacite = 100, string $espece = ''): int
    {
        return Infrastructures::enregistrer(null, ['code' => 'L'.Str::upper(Str::random(6)), 'nom_batiment' => 'Lieu '.Str::random(3), 'id_site' => $site, 'type_lieu' => 'batiment',
            'famille' => $famille, 'espece' => $espece, 'etat' => 'disponible', 'capacite' => $capacite, 'actif' => 1]);
    }

    private function lot(int $effectif): int
    {
        return LotsVolailles::creer(['nom_lot' => 'Lot '.Str::random(4), 'espece' => 'poulet', 'race' => 'Cobb', 'nombre_initial' => $effectif, 'date_entree' => today()->subDays(10)->toDateString()]);
    }

    private function deplacer(string $f, int $src, int $o, int $d, int $q): void
    {
        Infrastructures::deplacer($f, $src, $o, $d, $q, 'Test', Infrastructures::empreinte($f, $src));
    }

    public function test_rec_inf_001_002_capacite_et_non_affectes(): void
    {
        $s = $this->site();
        $l = $this->lieu($s);
        $a = $this->lot(85);
        $this->deplacer('volaille', $a, 0, $l, 85);
        $b = $this->lot(100);
        $this->refus(fn () => $this->deplacer('volaille', $b, 0, $l, 16), 'Capacité dépassée');
        $this->deplacer('volaille', $b, 0, $l, 15);
        $this->assertSame(100, (int) Infrastructures::lieu($l)->occupation);
        // REC-INF-002 : 100 répartis 60/30 → 10 non affectés ; 11 refusés.
        $c = $this->lot(100);
        [$l1, $l2] = [$this->lieu($s), $this->lieu($s)];
        $this->deplacer('volaille', $c, 0, $l1, 60);
        $this->deplacer('volaille', $c, 0, $l2, 30);
        $this->assertSame(10, Infrastructures::source('volaille', $c)->non_affecte);
        $this->refus(fn () => $this->deplacer('volaille', $c, 0, $l2, 11), 'supérieure');
        // Déplacement entre emplacements et empreinte périmée.
        $e = Infrastructures::empreinte('volaille', $c);
        $this->deplacer('volaille', $c, $l1, $l2, 20);
        $this->refus(fn () => Infrastructures::deplacer('volaille', $c, $l1, $l2, 5, 'Contrôle', $e), 'changé');
        // Un lieu occupé ne change pas de famille (déclencheur).
        $this->refus(fn () => Infrastructures::enregistrer($l1, ['code' => Infrastructures::lieu($l1)->code, 'nom_batiment' => 'X', 'id_site' => $s, 'type_lieu' => 'parc', 'famille' => 'ovin', 'etat' => 'disponible', 'capacite' => 100, 'actif' => 1]), 'Videz');
    }

    public function test_rec_inf_003_ovin_dans_un_parc_de_son_site(): void
    {
        [$sa, $sb] = [$this->site(), $this->site()];
        $parcB = $this->lieu($sb, 'ovin', 10);
        $parcA = $this->lieu($sa, 'ovin', 10);
        $a = Animaux::creer(['sexe' => 'F', 'id_site' => $sa, 'code_animal' => 'P'.Str::random(5)]);
        $this->refus(fn () => $this->deplacer('ovin', $a, 0, $parcB, 1), 'site de l’animal');
        $this->deplacer('ovin', $a, 0, $parcA, 1);
        $this->assertSame(1, (int) Infrastructures::lieu($parcA)->occupation);
    }

    public function test_int08_maintenance_et_planning(): void
    {
        $s = $this->site();
        $l = $this->lieu($s, 'volaille', 60);
        // REC-IFP-001/002 : pic exact des réservations.
        Planning::creer(['id_batiment' => $l, 'nature' => 'reservation', 'date_debut' => today()->addDays(10)->toDateString(), 'date_fin' => today()->addDays(20)->toDateString(), 'quantite' => 40]);
        Planning::creer(['id_batiment' => $l, 'nature' => 'reservation', 'date_debut' => today()->addDays(21)->toDateString(), 'date_fin' => today()->addDays(30)->toDateString(), 'quantite' => 60]);
        $this->refus(fn () => Planning::creer(['id_batiment' => $l, 'nature' => 'reservation', 'date_debut' => today()->addDays(15)->toDateString(), 'date_fin' => today()->addDays(16)->toDateString(), 'quantite' => 40]), 'Capacité');
        $this->refus(fn () => Planning::creer(['id_batiment' => $l, 'nature' => 'maintenance', 'date_debut' => today()->addDays(18)->toDateString(), 'date_fin' => today()->addDays(19)->toDateString()]), 'Conflit');
        $this->assertSame(60, Planning::pic([['2026-01-01', '2026-01-10', 40], ['2026-01-11', '2026-01-20', 60]]));

        // INT08 : occupation, maintenance, démarrage refusé tant que le lieu n'est pas vide.
        $lot = $this->lot(30);
        $this->deplacer('volaille', $lot, 0, $l, 30);
        $m = Planning::creer(['id_batiment' => $l, 'nature' => 'maintenance', 'date_debut' => today()->toDateString(), 'date_fin' => today()->addDays(2)->toDateString(), 'libelle' => 'Réfection']);
        $this->assertNotEmpty(collect(Planning::alertes())->filter(fn ($a) => str_contains($a[2], 'évacuer')));
        $this->refus(fn () => Planning::demarrer($m), 'pas vide');
        $this->deplacer('volaille', $lot, $l, 0, 30);
        Planning::demarrer($m);
        $this->assertSame(['maintenance', today()->addDays(3)->toDateString()], [Infrastructures::lieu($l)->etat, Infrastructures::lieu($l)->disponible_le]);
        $this->refus(fn () => $this->deplacer('volaille', $lot, 0, $l, 1), 'indisponible');
        // REC-IFP-003 : fin normale au plus tôt J+1 après le dernier jour.
        $this->refus(fn () => Planning::terminer($m), 'à partir du');
        $this->travelTo(today()->addDays(3)->setTime(9, 0));
        Planning::terminer($m);
        $this->assertSame(['disponible', null], [Infrastructures::lieu($l)->etat, Infrastructures::lieu($l)->disponible_le]);
        // REC-IFP-004 : une réservation clôturée ne crée aucune position.
        $r = DB::table('app_infra_planning')->where('id_batiment', $l)->where('nature', 'reservation')->orderBy('date_debut')->first();
        $this->travelTo(Carbon::parse($r->date_debut)->setTime(9, 0));
        $avant = DB::table('modInfra_positions')->count();
        Planning::cloturerReservation($r->id);
        $this->assertSame($avant, DB::table('modInfra_positions')->count());
    }

    public function test_rec_act_003_recurrence_mensuelle_et_hebdo(): void
    {
        $this->assertSame([27, 28], Recurrence::joursDuMois([30, 31], 2026, 2));
        $this->assertSame([30], Recurrence::joursDuMois([30, 31], 2026, 4));
        $rt = (object) ['nom' => 'T', 'type_recurrence' => 'mensuel', 'jours_mois' => '30,31', 'jour_mois' => null, 'horaire_debut' => '08:00:00', 'horaire_fin' => null,
            'duree_estimee_min' => 30, 'delai_echeance_min' => null, 'jours_semaine' => null, 'mois_annee' => null, 'intervalle_min' => null, 'intervalle_semaines' => 1];
        $r = (object) ['date_debut' => null, 'date_fin' => null];
        $occ = array_map(fn ($o) => $o[0]->format('Y-m-d H:i'), Recurrence::occurrences($rt, $r, '2026-02-01', '2026-02-28'));
        $this->assertSame(['2026-02-27 08:00', '2026-02-28 08:00'], $occ);
        $rt->type_recurrence = 'hebdo';
        $rt->jours_semaine = 'lun,jeu';
        $rt->intervalle_semaines = 2;
        $r->date_debut = '2026-03-02';
        $occ = array_map(fn ($o) => $o[0]->format('Y-m-d'), Recurrence::occurrences($rt, $r, '2026-03-01', '2026-03-31'));
        $this->assertSame(['2026-03-02', '2026-03-05', '2026-03-16', '2026-03-19', '2026-03-30'], $occ);
        $rt->type_recurrence = 'intervalle';
        $rt->horaire_debut = '08:00:00';
        $rt->horaire_fin = '10:00:00';
        $rt->intervalle_min = 45;
        $rt->duree_estimee_min = 30;
        $occ = array_map(fn ($o) => $o[0]->format('H:i').'-'.$o[1]->format('H:i'), Recurrence::occurrences($rt, (object) ['date_debut' => null, 'date_fin' => null], '2026-03-02', '2026-03-02'));
        $this->assertSame(['08:00-08:30', '08:45-09:15', '09:30-10:00'], $occ);
    }

    public function test_int09_routine_report_et_droits(): void
    {
        $gerant = $this->utilisateur('gerant_equipe');
        $ouvrier = $this->utilisateur('ouvrier');
        $autre = $this->utilisateur('ouvrier');
        $equipe = Activites::enregistrerEquipe(null, ['nom' => 'Équipe '.Str::random(4), 'actif' => 1], [$gerant->id, $ouvrier->id]);
        $routine = Activites::enregistrerRoutine(null, ['titre' => 'Nettoyage', 'actif' => 1, 'id_equipe' => $equipe, 'date_debut' => '2026-03-01']);
        Activites::enregistrerTacheRoutine($routine, null, ['nom' => 'Nettoyer les abreuvoirs', 'type_recurrence' => 'quotidien', 'horaire_debut' => '07:00', 'duree_estimee_min' => 30, 'actif' => 1]);
        $a = Activites::apercu($routine, '2026-03-01', '2026-03-07');
        $this->assertSame(7, $a['nouvelles']);
        $this->assertSame(7, Activites::generer($routine, '2026-03-01', '2026-03-07', $a['empreinte'], (string) Str::uuid()));
        // REC-ACT-004 : occurrence déplacée, régénération sans doublon à l'ancien horaire.
        $t = DB::table('modAct_taches')->where('id_routine', $routine)->orderBy('date_debut_prevue')->first();
        Activites::agir($t->id, 'reporter', ['debut' => '2026-03-01T15:00', 'duree_min' => 30, 'motif' => 'Pluie'], Activites::empreinte($t->id), (string) Str::uuid());
        $a = Activites::apercu($routine, '2026-03-01', '2026-03-07');
        $this->assertSame(0, $a['nouvelles']);
        $this->assertSame(0, Activites::generer($routine, '2026-03-01', '2026-03-07', $a['empreinte'], (string) Str::uuid()));
        $this->assertSame(7, DB::table('modAct_taches')->where('id_routine', $routine)->count());
        // REC-ACT-005 : report vers un début égal → refus.
        $this->refus(fn () => Activites::agir($t->id, 'reporter', ['debut' => '2026-03-01T15:00', 'duree_min' => 30, 'motif' => 'Encore la pluie'], Activites::empreinte($t->id), (string) Str::uuid()), 'strictement');
        // REC-ACT-001 : ouvrier non affecté → hors périmètre ; membre de l'équipe → accès.
        $this->actingAs($autre);
        $this->refus(fn () => Activites::tache($t->id), 'périmètre');
        $this->actingAs($ouvrier);
        Activites::agir($t->id, 'demarrer', [], Activites::empreinte($t->id), (string) Str::uuid());
        $this->refus(fn () => Activites::agir($t->id, 'terminer', [], Activites::empreinte($t->id), (string) Str::uuid()), 'gérant');
        // INT09 : le gérant d'équipe affecté termine.
        $this->actingAs($gerant);
        Activites::agir($t->id, 'terminer', ['duree_reelle_min' => 25], Activites::empreinte($t->id), (string) Str::uuid());
        $this->assertSame('realise', DB::table('modAct_taches')->where('id', $t->id)->value('statut'));
        // REC-ACT-002 : tâche réalisée → plus de dates, commentaire possible.
        $this->actingAs($this->utilisateur('admin'));
        $this->refus(fn () => Activites::modifier($t->id, ['nom' => 'X', 'equipes' => [$equipe]], Activites::empreinte($t->id), (string) Str::uuid()), 'réalisée');
        Activites::agir($t->id, 'commenter', ['motif' => 'RAS'], '', (string) Str::uuid());
        $this->assertSame(5, DB::table('modAct_journal_v112')->where('id_tache', $t->id)->count());
        $cle = (string) Str::uuid();
        Activites::agir($t->id, 'commenter', ['motif' => 'Deux fois'], '', $cle);
        $this->refus(fn () => Activites::agir($t->id, 'commenter', ['motif' => 'Deux fois'], '', $cle), 'double');
    }

    public function test_rec_rh_001_a_004_et_int10(): void
    {
        $e = Rh::enregistrerEmploye(null, ['nom' => 'Ndiaye', 'prenom' => 'Ali', 'matricule' => 'M'.Str::random(5), 'salaire_mensuel' => '90 000', 'actif' => 1]);
        // REC-RH-001 : lundi → dimanche sans fête = 6 ; fête le mardi = 5.
        $this->assertSame(6, Rh::joursImputes('2026-03-02', '2026-03-08'));
        Rh::ajouterJourImpose('2026-03-03', 'Autre');
        $this->assertSame(5, Rh::joursImputes('2026-03-02', '2026-03-08'));
        // INT10 : demandes successives jusqu'au dépassement (quota 30).
        $valider = fn ($d, $f) => Rh::traiter(Rh::demander(['employe_id' => $e, 'type' => 'conge', 'date_debut' => $d, 'date_fin' => $f]), 'validee');
        $valider('2026-04-01', '2026-04-30'); // 30 jours − 4 dimanches = 26
        $valider('2026-06-01', '2026-06-08'); // 7 jours (1 dimanche) → 33
        $this->assertSame(33, Rh::solde($e, 2026)['pris']);
        $this->refus(fn () => Rh::demander(['employe_id' => $e, 'type' => 'conge', 'date_debut' => '2026-06-05', 'date_fin' => '2026-06-06']), 'chevauche');
        $this->refus(fn () => Rh::demander(['employe_id' => $e, 'type' => 'conge', 'date_debut' => '2026-12-30', 'date_fin' => '2027-01-02']), 'deux années');
        // REC-RH-002/003 : retenue de 3 jours à 3 000 FCFA = 9 000 ; puis seulement la nouvelle prise.
        $r = Rh::retenues(2026, true);
        $this->assertSame([3, 900000], [$r[0]['jours'], $r[0]['montant']]);
        $valider('2026-07-06', '2026-07-07');
        $r = Rh::retenues(2026, true);
        $this->assertSame(2, $r[0]['jours']);
        $this->assertSame([], Rh::retenues(2026, true));
        // REC-RH-004 : clôture payée une seule fois.
        $f = Rh::enregistrerEmploye(null, ['nom' => 'Sow', 'salaire_mensuel' => '60000', 'actif' => 1]);
        Rh::traiter(Rh::demander(['employe_id' => $f, 'type' => 'conge', 'date_debut' => '2026-05-04', 'date_fin' => '2026-05-09']), 'validee');
        $c = collect(Rh::cloture(2026, true))->firstWhere('employe.id', $f);
        $this->assertSame([24, false], [$c['jours'], $c['deja']]);
        $c = collect(Rh::cloture(2026, true))->firstWhere('employe.id', $f);
        $this->assertTrue($c['deja']);
        $this->assertSame(1, DB::table('rh_conges_mouvements')->where('employe_id', $f)->where('type_mvt', 'PAIEMENT')->count());
        // EC20 : correction tracée.
        Rh::corriger($f, 2026, 'PAIEMENT', -2, 'Deux jours payés à tort, accord du salarié');
        $this->assertSame(22, Rh::solde($f, 2026)['payes']);
        // Refus et absence non congé.
        $id = Rh::demander(['employe_id' => $f, 'type' => 'maladie', 'date_debut' => '2026-08-03', 'date_fin' => '2026-08-05']);
        Rh::traiter($id, 'validee');
        $this->assertSame(0, (int) DB::table('rh_absences')->where('id', $id)->value('jours_imputes'));
        $this->refus(fn () => Rh::traiter($id, 'refusee'), 'déjà été traitée');
    }

    public function test_rec_mkt_001_a_003(): void
    {
        $this->assertSame(['+221771234567', '+221771234567', '+221771234567', null], [Marketing::normaliser('77 123 45 67'), Marketing::normaliser('221771234567'), Marketing::normaliser('00221771234567'), Marketing::normaliser('0612345678')]);
        $t = Marketing::enregistrerModele(null, ['titre' => 'Tabaski', 'message' => 'Bonjour {client_nom}, merci pour votre achat de {espece} ({montant_total}).', 'is_actif' => 1]);
        $c1 = Marketing::creerCampagne('Tabaski 2026', $t);
        $c2 = Marketing::creerCampagne('Relance', $t);
        foreach ([['Awa', '77 123 45 67'], ['Awa bis', '221771234567'], ['Moussa', '781112233'], ['Etranger', '+33 6 12 34 56 78']] as $i => [$n, $tel]) {
            DB::table('modVente_Ventes')->insert(['date_vente' => today()->subDays($i)->toDateString(), 'client_nom' => $n, 'client_tel' => $tel, 'espece' => 'ovin', 'quantite' => 1,
                'prix_unitaire' => 150000, 'montant_total' => 150000, 'montant_reste_encaisser' => 0, 'statut' => 'Encaissé']);
        }
        $cl = Marketing::clients(['du' => today()->subDays(10)->toDateString()], $c1);
        $this->assertSame(['+221771234567', '+221781112233'], $cl->pluck('tel_norm')->all());
        $this->assertSame(2, $cl->first()->nb_achats);
        $this->assertStringContainsString('Bonjour Awa, merci pour votre achat de Ovin', Marketing::personnaliser(DB::table('modMkt_templates')->where('id', $t)->value('message'), $cl->first()));
        $this->assertStringStartsWith('https://wa.me/221771234567?text=Bonjour', Marketing::lienWhatsapp('+221771234567', 'Bonjour Awa'));
        // REC-MKT-002 : ouvrir ≠ envoyé ; journaux par campagne.
        Marketing::journaliser($c1, 'OPEN_WA', '77 123 45 67', 'Awa');
        $this->assertSame('OPEN_WA', Marketing::clients([], $c1)->firstWhere('tel_norm', '+221771234567')->etat);
        Marketing::journaliser($c1, 'MARQUE_ENVOYE', '77 123 45 67', 'Awa');
        $this->assertSame('MARQUE_ENVOYE', Marketing::clients([], $c1)->firstWhere('tel_norm', '+221771234567')->etat);
        $this->assertNull(Marketing::clients([], $c2)->firstWhere('tel_norm', '+221771234567')->etat);
        // REC-MKT-003 : liste d'exclusion.
        Marketing::blacklister('781112233', 'Ne souhaite pas être contacté');
        $this->assertNull(Marketing::clients([], $c1)->firstWhere('tel_norm', '+221781112233'));
        $this->assertNotNull(Marketing::clients(['exclure' => '0'], $c1)->firstWhere('tel_norm', '+221781112233'));
    }
}
