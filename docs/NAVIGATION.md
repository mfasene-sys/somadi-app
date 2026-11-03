# Bouton « Retour » et fil d'Ariane

Mise à jour du 3 novembre 2026.

- **Toutes les pages sauf l'accueil** ont un bouton « ← » au-dessus du titre. Sur téléphone, il affiche sa destination (« ← Lots de volailles ») ; sur PC, « ← Retour », avec la destination en info-bulle.
- **Destination du bouton** : la page d'où l'on vient dans l'onglet, avec ses filtres et sa page de résultats. Si l'on est arrivé directement (lien reçu, favori, nouvel onglet), c'est la page générale de la page courante.
- **Sur PC, un fil d'Ariane** (« Accueil › Lots de volailles › Lot 27 › Modifier ») permet de remonter plusieurs niveaux d'un coup.
- **Formulaires** : une fois quittés (enregistrés ou abandonnés), ils ne restent pas dans le parcours. Après « Enregistrer », le retour ne ramène jamais sur le formulaire.
- **Onglets d'une même rubrique** (Trésorerie, Récapitulatif, À encaisser…) : ils se remplacent au lieu de s'empiler.
- Chaque onglet du navigateur a son propre parcours, mémorisé dans le navigateur (`sessionStorage`), effacé à la déconnexion. Rien n'est stocké sur le serveur.

## Où c'est défini

- `app/Support/Navigation.php` : page générale de chaque page. Par défaut, on remonte l'adresse (`ovins/animaux/12/modifier` → `ovins/animaux/12` → `ovins/animaux`) ; une page d'entrée de module remonte à l'accueil. Les exceptions (`PARENTS`) et les libellés (`LIBELLES`) sont listés en tête du fichier. **Une nouvelle page de liste qui sert de destination doit y recevoir son libellé** : le test `NavigationTest` le vérifie pour toutes les pages.
- `resources/views/components/navigation.blade.php` : bouton et fil d'Ariane, inclus par le gabarit des pages.
- `public/js/app.js`, section « Parcours de navigation » : mémorisation du parcours par onglet.
