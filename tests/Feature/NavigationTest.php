<?php

namespace Tests\Feature;

use App\Support\Navigation;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Bouton « Retour » et fil d'Ariane : page parente de chaque page. */
class NavigationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->chargerMatriceParDefaut();
    }

    public function test_chaque_page_remonte_jusqu_a_l_accueil_avec_des_libelles(): void
    {
        $exclues = ['exercices.pdf', 'ovins.inventaires.feuille', 'marketing.modeles.image', 'ovins.medias.show', 'login', 'login.code', 'hors_ligne'];
        $n = 0;
        foreach (Route::getRoutes() as $r) {
            $nom = $r->getName();
            if (! $nom || ! in_array('GET', $r->methods(), true) || in_array($nom, $exclues, true) || $nom === 'accueil' || str_starts_with($r->uri(), 'livewire') || in_array($r->uri(), ['up', 'storage/{path}'], true)) {
                continue;
            }
            $chaine = [$nom];
            while (($p = Navigation::parent(end($chaine))) !== null) {
                $this->assertNotContains($p, $chaine, "Boucle dans la hiérarchie : $nom");
                $this->assertTrue(Route::has($p), "Parent inconnu pour $nom : $p");
                $chaine[] = $p;
                $this->assertLessThan(8, count($chaine), "Hiérarchie trop profonde : $nom");
            }
            $this->assertSame('accueil', end($chaine), "$nom ne remonte pas à l'accueil");
            // Toute page qui sert de destination a un vrai libellé.
            foreach (array_slice($chaine, 1) as $ancetre) {
                $this->assertNotSame('Page précédente', Navigation::libelle($ancetre), "Libellé manquant pour $ancetre (parent de $nom)");
            }
            $n++;
        }
        $this->assertGreaterThan(90, $n);
    }

    public function test_hierarchies_attendues(): void
    {
        $this->assertSame('lots.show', Navigation::parent('lots.edit'));
        $this->assertSame('lots.index', Navigation::parent('lots.show'));
        $this->assertSame('accueil', Navigation::parent('lots.index'));
        $this->assertSame('ovins.animaux.show', Navigation::parent('ovins.animaux.arbre'));
        $this->assertSame('ovins.animaux.index', Navigation::parent('ovins.lots.index'));
        $this->assertSame('rh.employes.index', Navigation::parent('rh.absences.index'));
        $this->assertSame('utilisateurs.index', Navigation::parent('journal.index'));
        $this->assertSame('incubation.index', Navigation::parent('equipements.index'));
        $this->assertSame('ovins.soins.catalogue', Navigation::parent('ovins.soins.modele'));
        $this->assertSame('alimentation.plans', Navigation::parent('alimentation.plan'));
        $this->assertNull(Navigation::parent('accueil'));
    }

    public function test_page_de_detail_affiche_retour_et_fil_d_ariane(): void
    {
        $admin = $this->utilisateur('admin');
        $autre = $this->utilisateur('vendeur');
        $this->actingAs($admin)->get("/administration/utilisateurs/{$autre->id}")
            ->assertOk()
            ->assertSee('data-retour', false)
            ->assertSee('href="'.route('utilisateurs.index').'"', false)
            ->assertSeeInOrder(['data-fil', 'Accueil', 'Utilisateurs', $autre->nomComplet()], false)
            ->assertSee('<meta name="somadi-titre" content="'.e($autre->nomComplet()).'">', false)
            ->assertSee('<meta name="somadi-parent" content="/administration/utilisateurs">', false)
            ->assertSee('<meta name="somadi-formulaire" content="1">', false)
            ->assertSee('<meta name="somadi-statique" content="0">', false);
    }

    public function test_liste_remonte_a_l_accueil_et_l_accueil_n_a_pas_de_retour(): void
    {
        $admin = $this->utilisateur('admin');
        $this->actingAs($admin)->get('/administration/utilisateurs')->assertOk()
            ->assertSee('href="'.route('accueil').'"', false)->assertSee('data-retour', false)
            ->assertSee('<meta name="somadi-statique" content="1">', false);
        $this->get('/')->assertOk()->assertDontSee('data-retour', false)
            ->assertSee('<meta name="somadi-titre" content="Accueil">', false);
    }

    public function test_onglet_du_journal_remonte_aux_utilisateurs(): void
    {
        $admin = $this->utilisateur('admin');
        $this->actingAs($admin)->get('/administration/journal')->assertOk()
            ->assertSeeInOrder(['data-fil', 'Accueil', 'Utilisateurs', 'Journal'], false);
    }

    public function test_formulaires_reconnus(): void
    {
        $admin = $this->utilisateur('admin');
        $this->actingAs($admin)->get('/administration/utilisateurs/nouveau')->assertSee('<meta name="somadi-formulaire" content="1">', false);
        $this->get('/mon-profil')->assertSee('<meta name="somadi-formulaire" content="0">', false)
            ->assertSee('href="'.route('accueil').'"', false);
    }
}
