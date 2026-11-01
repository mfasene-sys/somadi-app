# Lot 3 — Commercial et finance

Modules livrés (menu « Commercial et finance ») : Réservations de poussins, Commandes (historique), Ventes, Trésorerie, Bilan et clôture, Rapprochements. Le tableau de bord affiche en plus les ventes et recettes du mois, le reste à encaisser et les sorties de volailles à compléter.

## Qui fait quoi (matrice par défaut)

| Action | Admin | Gestionnaire | Vendeur |
|---|---|---|---|
| Enregistrer une vente, réserver | oui | oui | oui |
| Encaisser une vente | oui | oui | oui |
| Saisir une recette ou une dépense libre, invalider une opération | oui | oui | non |
| Rapprochements | oui | oui (décision EC03) | non |
| Consulter les bilans | oui | oui | non |
| Clôturer un exercice | **admin seul** | non | non |

Les droits se modifient dans **Administration → Droits**, sauf la clôture qui reste réservée à l'administrateur.

## Ventes

- **Nouvelle vente** : volailles et autres produits. Si un lot est choisi, l'effectif du lot baisse immédiatement, sans créer de « vente à compléter » (EC06).
- **Vente d'œufs** : les œufs sont pris sur les lots « À vendre » cochés, du plus ancien au plus récent. Un lot épuisé passe au statut vendu.
- **À compléter** : les sorties « vente » saisies depuis un lot de volailles attendent ici le client et le prix. La quantité saisie doit être celle de la sortie.
- Un paiement peut être reçu au moment de la vente (totalité ou une partie) ou plus tard, depuis la fiche de la vente. Le reste à encaisser est toujours **total − règlements valides liés**.
- Téléphone : 8 à 15 chiffres, `+` accepté au début, espaces ignorés (EC07).

## Trésorerie

- **Journal** : toutes les recettes et dépenses, avec filtres. Les paiements de ventes y apparaissent avec un lien vers la vente.
- **Invalider** une opération ne la supprime pas : elle reste visible, barrée, avec le motif et l'auteur, et n'est plus comptée. Si elle réglait une vente, le montant est remis dans le reste à encaisser.
- **Récapitulatif** : recettes et dépenses par mois et par catégorie. **À encaisser** : ventes non soldées, de la plus ancienne à la plus récente.

## Réservations de poussins

1. On réserve sur un lot en incubation : la quantité est retirée du disponible du lot.
2. À l'éclosion, **Affecter les poussins** sert les réservations dans l'ordre d'arrivée. Une réservation servie en partie devient « Partiellement confirmé ». Relancer l'affectation ne sert jamais deux fois.
3. **Livrer** : peut créer la vente correspondante (et sortir les poussins du lot) et, si la case est cochée, enregistrer l'acompte comme premier paiement. Sans la case, l'acompte reste une simple mention sur la réservation.

Le statut « Partiellement confirmé » est enregistré sous la forme `confirmé partiel`, car la colonne de la base est limitée à 20 caractères ; les anciennes variantes sont lues correctement.

## Rapprochements

Pour les ventes dont le reste ne correspond pas aux règlements, ou pour relier une recette saisie sans lien (« Virement client X ») à la bonne vente. On coche les recettes, on décrit les justificatifs, on voit un **aperçu avant/après**, puis on confirme. Chaque rapprochement est journalisé et ne peut pas être validé deux fois. Les ventes d'un exercice clôturé sont en consultation seulement.

## Bilan et clôture

- Le bilan de l'année en cours est provisoire. Pour une année passée, les **contrôles** listent ce qui bloque la clôture (soldes incohérents, sorties à compléter, ventes d'œufs non affectées…).
- **Préparer la clôture** calcule un aperçu. Si une donnée change avant la validation, l'aperçu est refusé et doit être recalculé.
- La clôture, réservée à l'administrateur, enregistre une archive définitive (données + PDF) protégée par empreintes SHA-256. Ensuite, plus aucune vente ni opération ne peut être créée, modifiée ou supprimée jusqu'au 31/12 de l'année close. Un client qui paie l'an suivant une vente de l'année close : le paiement est daté de l'année ouverte, l'archive ne change pas.

## Commandes (historique)

Les registres `commandes`, `commandes_vente`, paiements et relances de l'ancienne application, en lecture seule (EC04). Une mention « écart » signale un solde enregistré différent de total − acompte − paiements.

## Produits vendables (EC24)

Poulet de chair, Goliath de chair, Poussins Goliath, Poussins d'un jour (code `poussin_chair`, ajouté), Poulettes prêtes à pondre, Coqs reproducteurs ; œufs fécondés, de table et de caille par la vente d'œufs ; ovins au lot 4. La liste se trouve dans `config/somadi.php`.
