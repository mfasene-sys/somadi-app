# Lot 5 — Organisation, personnel et marketing

Modules livrés (menus « Organisation » et « Commercial et finance ») : Bâtiments et parcs, Planning des emplacements, Activités et équipes, Personnel et congés, Marketing WhatsApp. Le tableau de bord affiche en plus vos tâches du jour, les absences à valider et les alertes de planning.

## Bâtiments et parcs (INF)

- Un emplacement associe un bâtiment à un code, un site, un type (bâtiment, parc, cage, compartiment), une famille (volailles, reproducteurs, ovins), une espèce et un stade facultatifs, une capacité et un état (disponible, nettoyage, vide sanitaire, maintenance, inactif).
- **Répartition des sujets** : pour un lot de volailles, une bande (mâles ou femelles) ou un ovin, on voit les quantités placées et non affectées, puis on déplace une quantité d'un emplacement à un autre, ou depuis/vers « non affectés ». Contrôles : quantité disponible à l'origine, capacité de la destination, état disponible, famille et espèce compatibles ; un ovin va dans un parc de son propre site.
- Quand un effectif diminue ailleurs (vente, mortalité, sortie d'un ovin), la base retire elle-même les sujets des emplacements, comme avec l'ancienne application. Le journal garde chaque mouvement physique.
- Un emplacement occupé ne peut pas changer de site, de famille, d'espèce, de stade ni devenir indisponible avant d'être vidé.

## Planning des emplacements (IFP, EC01)

- **Réservations** (places prévues) et **interventions** (nettoyage, vide sanitaire, maintenance), datées du premier au dernier jour inclus, sur 731 jours au plus, à partir d'aujourd'hui.
- Plusieurs réservations peuvent se chevaucher si le total simultané ne dépasse pas la capacité ; une intervention ne chevauche rien.
- Une réservation se clôture à partir de son premier jour et **ne place aucun animal**.
- Une intervention se **démarre** à la main, pendant sa période, sur un emplacement vide : l'emplacement passe dans l'état de l'intervention jusqu'au lendemain de la fin. Elle se **termine** à partir de ce lendemain, ce qui le remet disponible. Si l'état a été changé ailleurs, seul l'administrateur peut faire une clôture administrative.
- Alertes : périodes échues, interventions à démarrer ou à terminer, sujets à évacuer avant une intervention, réservation qui dépasserait la capacité compte tenu de l'occupation actuelle.
- Les données sont dans la nouvelle table `app_infra_planning` (les tables de l'ancien code n'existent pas dans la base).

## Activités et équipes (ACT)

- **Aujourd'hui** : vos tâches des 7 prochains jours et les tâches du jour. **Planning** : la semaine, personne par personne, avec les occupations (réunion, mission, absence). Ces occupations sont indépendantes des congés RH.
- Une tâche a un titre, une catégorie, une priorité, des dates et au moins une affectation : responsable, exécutants (personnes ou équipes), observateurs.
- Qui fait quoi :
  - créer, modifier, reporter : administrateur, gestionnaire, technicien ;
  - démarrer et commenter : toute personne affectée (sauf observateur), y compris par son équipe ;
  - terminer : gestionnaire, ou gérant d'équipe et technicien affectés ;
  - annuler : gestionnaire (avec motif).
- Une tâche réalisée ou annulée ne se modifie plus mais reste commentable. Chaque action est journalisée et protégée contre le double envoi.
- **Routines** : tâches récurrentes (chaque jour, certains jours de la semaine toutes les N semaines, certains jours du mois, certains mois, plusieurs fois par jour, ou manuel). La génération montre d'abord un aperçu ; une occurrence déjà créée, même déplacée, n'est jamais recréée. Les jours 29 à 31 absents d'un mois sont rabattus sur la fin du mois (février : 30 → 27, 31 → 28).

## Personnel et congés (RH, EC20)

- Employés (distincts des comptes de connexion), droits annuels (30 jours libres, bonus, 2 jours imposés par défaut), jours imposés (Korité, Tabaski…).
- Demande d'absence → validation, refus ou annulation. Un congé validé décompte les jours du début à la fin, **hors dimanches et jours imposés** ; les autres types ne décomptent rien. Pas de chevauchement ; une absence sur deux années se saisit en deux demandes.
- **Retenues** : le dépassement non encore retenu × salaire/30, appliqué le mois suivant ; relancer ne prélève pas deux fois.
- **Clôture annuelle** (droit « Clôture annuelle des congés ») : paiement des jours non pris, **une seule fois** par employé et par année. Une erreur se corrige par une **écriture de correction** (positive ou négative, avec motif), jamais en effaçant.
- Ces montants sont des informations de paie : aucune opération de trésorerie n'est créée.

## Marketing WhatsApp (MKT)

- Modèles de message avec variables `{client_nom}`, `{client_tel}`, `{date_vente}`, `{espece}`, `{montant_total}`, `{id_vente}` et image facultative.
- Campagne : clients tirés des ventes (période, produit, montant minimum), regroupés par téléphone sénégalais normalisé (+221…) ; la liste d'exclusion est appliquée par défaut. Affichage par 50 ou un par un.
- « Ouvrir WhatsApp » prépare le message personnalisé ; **vous l'envoyez vous-même**. « Marquer envoyé » est une déclaration, sans accusé de WhatsApp. L'image se joint à part dans WhatsApp.
- Chaque action est journalisée par campagne.

## Non repris

- Surveillance et anciennes « Mes tâches » (EC17) : leurs tables n'existent pas ; remplacées par Activités.
- Interface de programmation `ACT_api` de l'ancienne application mobile : les écrans de la nouvelle application s'utilisent directement sur téléphone.
