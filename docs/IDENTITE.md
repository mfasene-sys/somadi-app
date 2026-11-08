# Changement d'identité (administrateur)

Mise à jour du 8 novembre 2026.

Un administrateur peut, sans se déconnecter, **agir sous le compte d'un autre utilisateur** puis **revenir au sien** en un clic. Usages : voir l'application comme un rôle donné, dépanner quelqu'un, ou tenir un autre rôle au quotidien (par exemple faire les saisies d'un technicien).

## Utilisation

- **Menu → « Changer d'identité »** (en bas du menu, au-dessus de « Se déconnecter ») : liste des comptes classés par rôle, bouton « Prendre cette identité ».
- Ou depuis la **fiche d'un utilisateur** (Administration → Utilisateurs → la personne) : bouton « Prendre son identité ».
- Tant que dure le changement, un **bandeau jaune** reste en haut de chaque page : « Vous agissez en tant que … », avec **« Revenir à mon compte »** et « Changer » (passer directement à une autre personne). L'avatar du menu est entouré de jaune.
- La connexion ne change pas : chacun garde son adresse e-mail, son mot de passe, son code et son empreinte. Aucun compte supplémentaire ni adresse partagée n'est nécessaire.

## Règles

- **Réservé à l'administrateur** (action `ACC.identite`, réservée comme la clôture d'exercice : elle ne peut pas être accordée à un autre rôle dans la matrice des droits).
- **Comptes possibles** : tous les utilisateurs actifs, avec un rôle reconnu, **sauf les administrateurs**.
- **Actions réelles** : sous l'identité choisie, on a exactement ses menus et ses droits (ses tâches, son équipe…), et les saisies sont enregistrées à son nom dans les tables habituelles, comme si elle les avait faites.
- **Traçabilité** : chaque ligne du journal porte le compte utilisé **et** l'administrateur réel (colonnes `agi_par_id`, `agi_par_nom` de `app_journal`). Le journal affiche « par Awa Chef, sous cette identité ». Les débuts et fins sont tracés (`identite.debut`, `identite.fin`, avec la durée). Le filtre du journal par utilisateur retrouve aussi ce qu'un administrateur a fait sous l'identité d'un autre.
- **Bloqué sous une autre identité** : modifier le profil, le mot de passe ou les empreintes du compte utilisé ; créer ou modifier des comptes ; modifier la matrice des droits ; l'e-mail d'essai. Il faut revenir à son compte pour cela.
- **Sécurité de la session** : à chaque changement, la session et le jeton des formulaires sont renouvelés (un formulaire resté ouvert dans un autre onglet ne part pas sous la nouvelle identité : il affiche « formulaire expiré »), les pages gardées hors connexion et le parcours du bouton Retour sont effacés.
- Si le compte utilisé est désactivé ou passe administrateur pendant ce temps, on revient automatiquement au compte de l'administrateur. Si c'est l'administrateur qui est désactivé, la session est fermée.
- « Se déconnecter » sous une autre identité ferme toute la session (y compris celle de l'administrateur).

## Limites

- Les tables métier gardent seulement le compte utilisé (par exemple l'auteur d'une vente). C'est le journal qui dit qu'un administrateur l'a fait à sa place.
- L'ancienne application n'est pas concernée : elle ne connaît pas ce mécanisme.

## Où c'est défini

- `app/Support/Identite.php` : règles, changement et retour, pages bloquées (`ROUTES_BLOQUEES`).
- `app/Http/Middleware/IdentiteEmpruntee.php` : contrôles à chaque requête.
- `app/Http/Controllers/IdentiteController.php`, vue `resources/views/identite/index.blade.php`, bandeau dans `resources/views/components/layouts/app.blade.php`.
- `app/Support/Journal.php` et la migration `2026_11_08_000001_add_identite_app_journal.php` (deux colonnes ajoutées à `app_journal`, table propre à la nouvelle application).
- Tests : `tests/Feature/IdentiteTest.php`.
