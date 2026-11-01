# Lot 4 — Ovins

Modules livrés (menu « Ovins ») : Animaux (troupeau, lots, sites, propriétaires), Reproduction, Soins, Transferts et sorties, Inventaires. Le tableau de bord affiche le nombre d'ovins en ferme, les agnelages attendus sous 30 jours et les soins ovins du jour ou en retard.

## Qui fait quoi (matrice par défaut)

| Action | Admin | Gestionnaire | Technicien | Associé ovin |
|---|---|---|---|---|
| Consulter le troupeau, la reproduction, les soins, les inventaires | oui | oui | oui | oui |
| Créer et modifier les fiches, lots, sites, propriétaires | oui | oui | oui | non |
| Saillies, agnelages, calendrier | oui | oui | oui | non |
| Soins (planifier, marquer réalisé, annuler) | oui | oui | oui | non |
| Remettre « à faire » un soin réalisé | oui | non | oui | non |
| Transferts et sorties | oui | oui | oui | non |
| Vendre un animal (crée la vente) | oui | oui | non (droit Ventes) | non |
| Pointer un inventaire | oui | oui | oui | non |
| Clôturer un inventaire | oui | non | oui | non |
| Rapprocher une ancienne vente ovine | **admin seul** | non | non | non |

## Troupeau (EC18)

- La fiche porte l'identité (numéro de boucle, nom, sexe, race, dates, origine, propriétaire), la filiation, les poids, le prix d'achat et les observations. **Le lot, le site et le statut ne se modifient pas depuis la fiche** : ils changent par un transfert ou une sortie, qui enregistrent le mouvement et ajustent les effectifs.
- Le numéro de boucle doit être unique ; laissé vide, il est attribué automatiquement (`OV26-001`…).
- À la création, la case « Animal qui arrive » ajoute l'animal à l'effectif du lot. Décochez-la si l'animal était déjà compté et que vous créez seulement sa fiche.
- Les filtres de la liste (sites, propriétaires, sexe, statut, âge, reproduction) sont mémorisés pendant la session.
- Généalogie : trois générations d'ascendants et la descendance directe, même quand un parent a été vendu ; un descendant ne peut pas être déclaré parent.
- Photos et vidéos : le type est vérifié sur le contenu réel du fichier. Images JPEG, PNG, WebP, GIF jusqu'à 5 Mo ; vidéos MP4, WebM, Ogg, MOV jusqu'à 50 Mo.

## Lots

L'effectif d'un lot et le nombre de fiches individuelles sont deux niveaux différents : la page Lots affiche les deux et signale l'écart sans le masquer. « Corriger l'effectif » enregistre un mouvement « Correction d'effectif » avec son motif.

## Reproduction

- Saillie sans date prévue : l'agnelage est prévu à saillie + 150 jours.
- À l'agnelage réel, vivants = mâles + femelles. Une fiche est créée pour chaque agneau vivant (filiation, lot d'accueil, mouvement « Naissance ») **une seule fois** ; ensuite, le père, la date et les effectifs sont figés.
- Agnelles prêtes : femelles des lots « Agnelles » âgées d'au moins 270 jours. « Intégrer » les transfère vers un lot de reproductrices, les marque reproductrices et peut planifier un modèle de soins d'entrée.
- Calendrier par lot : gestation 150 j, allaitement 90 j, préparation 30 j, cycle de 9 mois, horizon 3 ans (modifiables à la génération), avec les fenêtres « après mise bas » (J+7 à J+21) et « avant la lutte suivante » (J−21 à J0). Il est théorique : il ne crée aucune naissance. Il ne peut plus être régénéré une fois qu'une de ses actions a été suivie.

## Soins

- Catalogue de soins et modèles (J0, J+7…). La base n'admet qu'**un modèle actif par catégorie** (et un inactif) : la catégorie désigne l'usage du modèle (entrée en reproduction, embouche…).
- Un modèle appliqué à un lot crée des soins collectifs ; appliqué à un animal, des soins individuels. Appliquer deux fois le même modèle à la même date ne crée pas de doublon.
- « Réalisé » ne s'applique qu'à un soin planifié ; un soin planifié devenu inutile est annulé avec un motif (jamais supprimé).
- Le délai d'attente d'un produit est affiché pour information ; il ne bloque pas une vente.

## Transferts et sorties

- Transfert : vers un autre lot actif ; l'animal prend le site du lot. Lot d'origine −1, lot de destination +1, deux mouvements « Transfert ».
- Sortie : vente → vendu, mortalité → mort, réforme → réformé, autre → sorti. Date réelle, non future, ni avant la naissance ou l'entrée.
- Une vente crée la vente générale (produit « Ovin », quantité 1, reste intégral à encaisser) et la liaison unique sortie–animal–vente ; l'encaissement se fait ensuite depuis la vente. Un animal ne peut pas être vendu deux fois.
- Les ventes ovines enregistrées par l'ancienne application sans liaison sont listées pour l'administrateur : on les lie à une vente existante strictement concordante (même client, date, prix, quantité 1), ou on crée la vente manquante en y rattachant les recettes déjà saisies. Ces ventes non liées bloquent la clôture de l'exercice.

## Inventaires

1. Ouvrir sur un périmètre (site, lot, propriétaire) : la liste attendue est figée. Imprimer la feuille de comptage (PDF) si besoin.
2. Pointer : cocher les animaux vus puis « Marquer présents » (50 au plus à la fois), ou pointer une ligne (présent, anomalie, absent ; commentaire obligatoire pour anomalie ou absence).
3. Un animal vu mais non attendu s'ajoute comme observation supplémentaire, sans créer de fiche. Un numéro déjà présent renvoie vers sa ligne.
4. Les mouvements intervenus depuis l'ouverture (vente, transfert…) sont affichés pour expliquer les écarts ; la liste d'ouverture n'est jamais réécrite.
5. Clôture (admin, technicien) : toutes les lignes attendues pointées et un bilan. Les absents ne deviennent pas automatiquement des sorties : à traiter sur la fiche de l'animal si l'absence est confirmée.

## Hors connexion

Comme pour les autres modules : consultation des dernières pages vues, saisies bloquées sans réseau (EC21).
