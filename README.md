# SOMADI Farming — nouvelle application

Refonte de l'application de gestion de la ferme SOMADI, compatible PC et mobile (application installable, ou PWA).
Elle fonctionne sur **la base MySQL existante**, sans en modifier les tables : les deux applications peuvent tourner en parallèle pendant la transition.

## Technologies

| Élément | Choix | Pourquoi |
|---|---|---|
| Langage | PHP 8.3 ou plus (8.4 en production) | Disponible sur l'hébergement OVH Pro |
| Cadre | Laravel 13 | Sécurité intégrée (CSRF, sessions, validation), structure maintenable |
| Interactivité | Livewire 4 | Écrans dynamiques sans application JavaScript séparée |
| Styles | Tailwind CSS 4 (CSS compilé fourni) | Interface responsive PC et mobile |
| Base | MySQL (celle de la production) | Données conservées, aucune migration de données |
| Mobile | PWA (manifeste et service worker) | Installation sur l'écran d'accueil, consultation hors connexion |

## Lots de livraison

| Lot | Contenu | État |
|---|---|---|
| 1 | Socle : connexion, rôles et droits par action, utilisateurs, journal, profil, PWA | **Livré** |
| 2 | Volailles : reproducteurs, ponte, œufs, incubation, lots, pesées et rentabilité, alimentation, soins, tableau de bord | **Livré** |
| 3 | Commercial et finance : réservations, ventes, trésorerie, clôture, rapprochements | **Livré** |
| 4 | Ovins : animaux, reproduction, soins, transferts, inventaires | **Livré** |
| 5 | Infrastructures, planning, activités, RH, marketing WhatsApp | **Livré** |

## Principes

- **Structure de la base intacte** : la nouvelle application crée uniquement des tables préfixées `app_` (`app_permissions`, `app_journal`, `app_requetes`, `app_lots_options`, `app_infra_planning`, `app_migrations`). Elle écrit dans les tables métier existantes exactement comme l'ancienne application, et ses écritures passent par les mêmes déclencheurs MySQL.
- **Mots de passe compatibles** : hachage `password_hash()` identique à l'ancienne application ; les comptes existants se connectent sans changement.
- **Droits par action (EC02)** : matrice rôle × action vérifiée côté serveur à chaque requête. Le menu en découle. L'administrateur a tous les droits. La clôture comptable et la gestion de la matrice sont réservées à l'administrateur.
- **Journal unique (EC19)** : chaque action sensible enregistre l'auteur, l'objet, les valeurs avant et après, la date et l'adresse IP.
- **Hors connexion (EC21)** : les pages déjà consultées restent lisibles avec leur date. Les saisies sont bloquées avec un message, jamais perdues en silence.

## Déploiement

Voir [`docs/DEPLOIEMENT_OVH.md`](docs/DEPLOIEMENT_OVH.md).

## Développement

```bash
cp .env.example .env    # puis renseigner une base MySQL locale contenant le schéma de production
php artisan key:generate
mysql somadi_dev < database/legacy/schema_production.sql   # schéma reconstruit : 89 tables, 50 déclencheurs
php artisan somadi:installer
php artisan serve
```

Styles : `tailwindcss -i resources/css/app.css -o public/css/app.css --minify` (exécutable autonome Tailwind 4).

Tests : `php vendor/bin/phpunit`. La suite recrée une base `somadi_test` à partir du schéma de production, puis exécute chaque test dans une transaction annulée.

## Commandes

| Commande | Rôle |
|---|---|
| `php artisan somadi:installer --force` | Crée les tables `app_*` et la matrice par défaut, puis vérifie la base |
| `php artisan somadi:verifier` | Contrôle en lecture seule : tables, InnoDB (EC23), déclencheurs, rôles, administrateurs |
| `php artisan somadi:creer-admin email` | Crée ou réactive un administrateur (mot de passe saisi de façon masquée) |

## Organisation du code

```
app/Support/Droits.php        matrice des droits (EC02)
app/Support/Journal.php       journal métier unique (EC19)
app/Support/Roles.php         rôles et alias de l'ancien code (EC03)
app/Support/Menu.php          menu construit à partir des droits
app/Support/Lieux.php         dialogue avec les déclencheurs d'emplacements (@sf117_*)
app/Support/Empreinte.php     verrou optimiste : formulaire périmé refusé
app/Support/Requete.php       anti double envoi des formulaires de création
app/Domaine/                  règles métier par module (Ponte, Incubation, LotsVolailles…)
config/somadi.php             catalogue des modules, actions et matrice par défaut
database/legacy/              schéma de production reconstruit (référence et tests)
public/sw.js                  service worker (EC21)
```
