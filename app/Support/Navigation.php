<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\Route as RouteObjet;
use Illuminate\Support\Facades\Route;

/**
 * Hiérarchie des pages : page « générale » (parente) de chaque page, et fil d'Ariane.
 *
 * Règle par défaut : on remonte l'adresse segment par segment jusqu'à une page existante
 * (ovins/animaux/12/modifier → ovins/animaux/12 → ovins/animaux). Une page d'entrée de module
 * remonte à l'accueil. Les exceptions (onglets rangés ailleurs que leur adresse ne le dit) sont listées ici.
 *
 * Le bouton « Retour » vise d'abord la page d'où l'on vient (mémorisée par onglet dans le navigateur,
 * public/js/app.js) ; la page parente ne sert que si l'on est arrivé directement sur la page.
 */
class Navigation
{
    /** Parents explicites : la page n'est pas rangée sous son adresse. */
    private const PARENTS = [
        'equipements.index' => 'incubation.index',
        'references.index' => 'rentabilite.index',
        'organisation.repartition' => 'organisation.lieux.index',
        'organisation.lieux.journal' => 'organisation.lieux.index',
        'ovins.lots.index' => 'ovins.animaux.index',
        'ovins.sites' => 'ovins.animaux.index',
        'ovins.proprietaires' => 'ovins.animaux.index',
        'ovins.rapprochements.index' => 'ovins.mouvements.index',
        'ovins.soins.modele' => 'ovins.soins.catalogue',
        'ovins.soins.modele.create' => 'ovins.soins.catalogue',
        'profil.edit' => 'accueil',
        'identite.index' => 'accueil',
        'module.a_venir' => 'accueil',
    ];

    /** Libellés des pages quand elles apparaissent comme destination (retour, fil d'Ariane). */
    private const LIBELLES = [
        'accueil' => 'Accueil',
        'profil.edit' => 'Mon profil',
        'identite.index' => 'Changer d’identité',
        // Volailles
        'reproducteurs.index' => 'Reproducteurs',
        'reproducteurs.show' => 'Fiche de la bande',
        'ponte.index' => 'Ponte',
        'oeufs.index' => 'Stock des œufs',
        'incubation.index' => 'Incubation',
        'incubation.show' => 'Incubation',
        'equipements.index' => 'Équipements',
        'lots.index' => 'Lots de volailles',
        'lots.show' => 'Fiche du lot',
        'rentabilite.index' => 'Pesées et rentabilité',
        'references.index' => 'Coûts de référence',
        'alimentation.index' => 'Alimentation',
        'alimentation.plans' => 'Plans d’alimentation',
        'soins.index' => 'Soins volailles',
        // Commercial et finance
        'ventes.index' => 'Ventes',
        'ventes.show' => 'Vente',
        'tresorerie.index' => 'Trésorerie',
        'exercices.index' => 'Exercices comptables',
        'rapprochements.index' => 'Rapprochements',
        'reservations.index' => 'Réservations de poussins',
        'reservations.show' => 'Réservation',
        'commandes.index' => 'Commandes (historique)',
        'marketing.index' => 'Marketing WhatsApp',
        // Ovins
        'ovins.animaux.index' => 'Troupeau ovin',
        'ovins.animaux.show' => 'Fiche de l’animal',
        'ovins.lots.index' => 'Lots ovins',
        'ovins.lots.show' => 'Fiche du lot ovin',
        'ovins.reproduction.index' => 'Reproduction ovine',
        'ovins.soins.index' => 'Soins ovins',
        'ovins.soins.catalogue' => 'Catalogue et modèles',
        'ovins.mouvements.index' => 'Transferts et sorties',
        'ovins.rapprochements.index' => 'Ventes ovines à rapprocher',
        'ovins.inventaires.index' => 'Inventaires ovins',
        'ovins.inventaires.show' => 'Inventaire',
        // Organisation
        'organisation.lieux.index' => 'Bâtiments et parcs',
        'organisation.lieux.show' => 'Emplacement',
        'organisation.planning.index' => 'Planning des emplacements',
        'activites.index' => 'Activités',
        'activites.taches.index' => 'Tâches',
        'activites.taches.show' => 'Tâche',
        'activites.routines.index' => 'Routines',
        'rh.employes.index' => 'Personnel et congés',
        'rh.employes.show' => 'Fiche de l’employé',
        // Administration
        'utilisateurs.index' => 'Utilisateurs',
    ];

    /** Pages qui ne sont pas des pages à part entière (fichiers, images, PDF) : jamais destination d'un retour. */
    private const HORS_NAVIGATION = ['exercices.pdf', 'ovins.inventaires.feuille', 'marketing.modeles.image', 'ovins.medias.show', 'login', 'login.code', 'hors_ligne'];

    /**
     * Fil d'Ariane de la page courante, de l'accueil jusqu'à la page parente (la page courante n'y est pas).
     *
     * @return list<array{url:string, titre:string, chemin:string}>
     */
    public static function fil(Request $request): array
    {
        $route = $request->route();
        if (! $route instanceof RouteObjet || $route->getName() === 'accueil' || in_array($route->getName(), self::HORS_NAVIGATION, true)) {
            return [];
        }

        $fil = [];
        $courante = $route->getName();
        $params = $route->parameters();
        for ($i = 0; $i < 8 && ($parent = self::parent($courante)); $i++) {
            $url = self::url($parent, $params);
            if ($url === null) {
                break;
            }
            array_unshift($fil, ['url' => $url, 'titre' => self::libelle($parent), 'chemin' => (string) parse_url($url, PHP_URL_PATH) ?: '/']);
            $courante = $parent;
        }

        return $fil;
    }

    /** Page parente : la dernière étape du fil (null pour l'accueil). */
    public static function parentDe(Request $request): ?array
    {
        $fil = self::fil($request);

        return $fil ? end($fil) : null;
    }

    /** Page de formulaire (création, modification) : elle ne reste pas dans le parcours une fois quittée. */
    public static function estFormulaire(Request $request): bool
    {
        $nom = (string) $request->route()?->getName();

        return (bool) preg_match('/\.create$/', $nom)
            || in_array($nom, ['utilisateurs.edit', 'exercices.cloture'], true)
            || (bool) preg_match('#/(nouveau|nouvelle|modifier|chargement)$#', $request->path());
    }

    /** Page sans paramètre (liste, onglet) : deux onglets d'un même module se remplacent dans le parcours. */
    public static function estStatique(Request $request): bool
    {
        return $request->route() instanceof RouteObjet && $request->route()->parameterNames() === [];
    }

    public static function parent(string $nom): ?string
    {
        if ($nom === 'accueil') {
            return null;
        }
        if (isset(self::PARENTS[$nom])) {
            return self::PARENTS[$nom];
        }
        if (in_array($nom, self::racinesDeModules(), true)) {
            return 'accueil';
        }

        $route = Route::getRoutes()->getByName($nom);
        if (! $route) {
            return 'accueil';
        }

        // On remonte l'adresse jusqu'à une page existante.
        $segments = explode('/', trim($route->uri(), '/'));
        while (count($segments) > 1) {
            array_pop($segments);
            $prefixe = implode('/', $segments);
            if ($candidat = self::pageA($prefixe)) {
                return $candidat;
            }
            // Préfixe de groupe sans page (rh, administration…) : l'unique module rangé dessous, s'il n'y en a qu'un.
            $modules = array_values(array_filter(self::racinesDeModules(), fn ($r) => ($u = Route::getRoutes()->getByName($r)) && str_starts_with($u->uri().'/', $prefixe.'/')));
            if (count($modules) === 1 && $modules[0] !== $nom) {
                return $modules[0];
            }
        }

        return 'accueil';
    }

    public static function libelle(string $nom): string
    {
        return self::LIBELLES[$nom] ?? 'Page précédente';
    }

    private static function pageA(string $uri): ?string
    {
        foreach (Route::getRoutes() as $r) {
            if ($r->uri() === $uri && in_array('GET', $r->methods(), true) && $r->getName() && ! in_array($r->getName(), self::HORS_NAVIGATION, true)) {
                return $r->getName();
            }
        }

        return null;
    }

    /** URL d'une page parente, avec les paramètres de la page courante qu'elle utilise (null s'il en manque). */
    private static function url(string $nom, array $params): ?string
    {
        $route = Route::getRoutes()->getByName($nom);
        if (! $route) {
            return null;
        }
        $utiles = [];
        foreach ($route->parameterNames() as $p) {
            if (! array_key_exists($p, $params)) {
                return null;
            }
            $v = $params[$p];
            $utiles[$p] = is_object($v) && method_exists($v, 'getRouteKey') ? $v->getRouteKey() : $v;
        }

        return route($nom, $utiles);
    }

    /** @return list<string> */
    private static function racinesDeModules(): array
    {
        static $racines = null;

        return $racines ??= array_values(array_filter(array_map(fn ($m) => $m['route'] ?? null, config('somadi.modules', []))));
    }
}
