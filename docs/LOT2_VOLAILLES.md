# Lot 2 — Volailles

Livré le 1er novembre 2026. Ce lot remplace, dans la nouvelle application, les écrans volailles de l'ancienne : reproducteurs, ponte, stock des œufs, incubation, lots, pesées et rentabilité, alimentation, soins, ainsi que le tableau de bord.

## Ce que chaque module permet

| Module | Fonctions | Qui (matrice par défaut) |
|---|---|---|
| Tableau de bord | Actions du jour (soins du jour et en retard, ramassages à valider, œufs à répartir et à charger), éclosions des 3 prochains jours, effectifs, soins de la semaine | Tous les rôles, selon leurs droits |
| Reproducteurs | Bandes poulet/caille, mouvements par sexe (entrée, retrait, mortalité), réforme | Gestionnaire, technicien |
| Ponte | Saisie matin/après-midi, taux de ponte, validation avec défectueux, historique avec état « À vérifier », **courbe du taux de ponte et de la part de défectueux** (par bande ou toutes bandes, de 14 jours à 1 an) | Saisie : ouvrier, technicien, gestionnaire · validation : technicien, gestionnaire |
| Stock des œufs | Répartition « À incuber » / « À vendre », contrôle de conservation avant chaque affectation | Technicien, gestionnaire |
| Incubation | Chargement (date du jeudi proposée), ajustement avant J+10, mirage dès J+10, transfert vers 1 ou 2 éclosoirs (scission A/B), éclosion avec création du lot de poussins, équipements | Technicien, gestionnaire |
| Lots de volailles | Création (programme de soins et 28 réglages de température en option), mouvements, ventes à compléter, archivage, fiche complète | Technicien, gestionnaire |
| Pesées et rentabilité | Pesées, courbe et gain quotidien, rentabilité par coût de référence d'âge, hypothèses de vente de 0 à 60 jours | Technicien, gestionnaire |
| Alimentation | Distributions du jour générées depuis les plans, saisie manuelle, correction tracée, stocks et seuils, plans avec transitions, consommation et indice | Distribution : ouvrier · gestion : technicien, gestionnaire |
| Soins | Planning (retards, jour, période), soins occasionnels, réalisé/annulé, catalogue, programme poussins | Réaliser : ouvrier · planifier et annuler : technicien, gestionnaire |

## Décisions appliquées

- **EC05** : après l'éclosion, seul le lot de volailles porte l'effectif ; le stock de poussins du jour n'est jamais additionné.
- **EC07** : mêmes contrôles sur PC et sur mobile (dates réelles, au plus tard aujourd'hui pour les saisies ; montants à 2 décimales).
- **EC08** : « Ventes facturées » et « Encaissé » sont deux lignes distinctes de la rentabilité.
- **EC09** : sans coût de référence pour l'âge exact d'une vente ou d'une mortalité, la marge est affichée « Non calculable », avec la liste des âges manquants.
- **EC10** : les lots 27 et 9 sont exclus des analyses par une option modifiable, et non plus par le code.
- **EC11** : seul le générateur fondé sur les plans hebdomadaires est conservé.
- **EC12** : un même aliment s'additionne par lot et par date ; une nouvelle génération ne remplace jamais une distribution existante ; une correction exige un motif et est journalisée.
- **EC13** : les plans récurrents de prophylaxie gardent leur règle (jours plafonnés au 28), indiquée à l'écran.
- **EC16** : pas d'envoi automatique ; les éclosions des 3 prochains jours s'affichent sur l'accueil.
- **EC22** : un incubateur dont le nom figure dans des lots ne peut pas être renommé ; deux équipements ne peuvent pas porter le même nom.

## Protections communes

- **Transactions** : chaque opération est entièrement enregistrée ou entièrement annulée (par exemple, mouvement et effectif ensemble).
- **Formulaire périmé** : si un collègue modifie la même fiche entre-temps, l'envoi est refusé avec une demande de rechargement.
- **Double envoi** : une création envoyée deux fois (double appui, réseau instable) n'est enregistrée qu'une fois.
- **Déclencheurs existants** : les sorties d'animaux répartis sur plusieurs emplacements demandent l'emplacement de sortie ; les ventes sur une période comptable clôturée sont refusées, avec un message en français.
- **Journal** : chaque écriture est tracée (auteur, avant, après).

## Points d'attention

1. **Coûts de référence** : la rentabilité cherche un coût pour l'âge *exact* de chaque vente et de chaque mortalité, comme l'ancienne fiche. Avec des références hebdomadaires seulement (7, 14, 21 j…), la marge restera presque toujours « Non calculable ». Deux options : saisir des références quotidiennes, ou décider d'utiliser la dernière référence connue (interpolation) dans un prochain lot.
2. **Plans récurrents de prophylaxie** : ils sont consultables. Leur génération automatique reste dans l'ancienne application jusqu'à décision.
3. **Ventes** : une sortie « vente » crée une vente à compléter. Sa finalisation (client, prix, encaissement) arrive au lot 3.
4. **Lots 27 et 9** : merci d'indiquer pourquoi l'ancienne fiche les excluait, pour confirmer ou retirer l'exclusion.

## Vérification

- 77 tests automatisés (366 vérifications) sur MariaDB 11.4 avec le schéma de production reconstruit et ses 50 déclencheurs. Ils couvrent les recettes REC-REP, PON, OEU, INC, VOL, POI, ALI et SAV du dossier, REC-PIL-001 et 002 (la 003 concerne les ventes, lot 3), ainsi que le parcours INT01 (œufs → poussins).
- Parcours navigateur sur PC et sur mobile avec un jeu de démonstration.
