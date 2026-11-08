<?php

/*
|--------------------------------------------------------------------------
| Référentiel fonctionnel SOMADI
|--------------------------------------------------------------------------
| Rôles, modules et actions autorisables. La matrice rôle × action est stockée
| dans la table app_permissions (EC02) ; ce fichier fournit le catalogue et
| la matrice proposée par défaut, modifiable ensuite par l'administrateur.
*/

return [

    // EC03 : les 7 rôles de la table utilisateurs, et les alias rencontrés dans l'ancien code.
    'roles' => [
        'admin' => 'Administrateur',
        'gestionnaire' => 'Gestionnaire',
        'vendeur' => 'Vendeur',
        'ouvrier' => 'Ouvrier',
        'technicien' => 'Technicien',
        'gerant_equipe' => "Gérant d'équipe",
        'associe ovin' => 'Associé ovin',
    ],

    'role_aliases' => [
        'administrateur' => 'admin',
        'manager' => 'gestionnaire',
    ],

    // Groupes du menu, dans l'ordre d'affichage.
    'groups' => [
        'pilotage' => 'Pilotage',
        'volailles' => 'Volailles',
        'commercial' => 'Commercial et finance',
        'ovins' => 'Ovins',
        'organisation' => 'Organisation',
        'administration' => 'Administration',
    ],

    /*
    | Modules (codes du dossier de spécifications). « lot » indique le lot de livraison ;
    | un module dont le lot n'est pas encore livré s'affiche comme « bientôt disponible ».
    | Chaque module expose au minimum l'action « voir » ; « gerer » couvre la saisie courante.
    */
    'modules' => [
        'PIL' => ['court' => 'Accueil', 'titre' => 'Tableau de bord', 'groupe' => 'pilotage', 'lot' => 1, 'icone' => 'home', 'route' => 'accueil', 'actions' => ['voir' => 'Consulter le tableau de bord']],

        'REP' => ['route' => 'reproducteurs.index', 'titre' => 'Reproducteurs', 'groupe' => 'volailles', 'lot' => 2, 'icone' => 'users', 'actions' => ['voir' => 'Consulter', 'gerer' => 'Gérer les bandes']],
        'PON' => ['route' => 'ponte.index', 'court' => 'Ponte', 'titre' => 'Ponte', 'groupe' => 'volailles', 'lot' => 2, 'icone' => 'egg', 'actions' => ['voir' => 'Consulter', 'gerer' => 'Saisir les ramassages', 'valider' => 'Valider les ramassages']],
        'OEU' => ['route' => 'oeufs.index', 'titre' => 'Stock des œufs', 'groupe' => 'volailles', 'lot' => 2, 'icone' => 'box', 'actions' => ['voir' => 'Consulter', 'gerer' => 'Affecter les œufs']],
        'INC' => ['route' => 'incubation.index', 'titre' => 'Incubation', 'groupe' => 'volailles', 'lot' => 2, 'icone' => 'sun', 'actions' => ['voir' => 'Consulter', 'gerer' => 'Charger, mirer, transférer, éclore']],
        'VOL' => ['route' => 'lots.index', 'court' => 'Lots', 'titre' => 'Lots de volailles', 'groupe' => 'volailles', 'lot' => 2, 'icone' => 'layers', 'actions' => ['voir' => 'Consulter', 'gerer' => 'Gérer lots et mouvements']],
        'POI' => ['route' => 'rentabilite.index', 'titre' => 'Pesées et rentabilité', 'groupe' => 'volailles', 'lot' => 2, 'icone' => 'scale', 'actions' => ['voir' => 'Consulter', 'gerer' => 'Saisir les pesées']],
        'ALI' => ['route' => 'alimentation.index', 'titre' => 'Alimentation', 'groupe' => 'volailles', 'lot' => 2, 'icone' => 'wheat', 'actions' => ['voir' => 'Consulter', 'gerer' => 'Stocks et plans', 'distribuer' => 'Enregistrer les distributions']],
        'SAV' => ['route' => 'soins.index', 'titre' => 'Soins volailles', 'groupe' => 'volailles', 'lot' => 2, 'icone' => 'heart', 'actions' => ['voir' => 'Consulter', 'gerer' => 'Planifier et catalogue', 'realiser' => 'Marquer un soin réalisé']],

        'RES' => ['route' => 'reservations.index', 'titre' => 'Réservations de poussins', 'groupe' => 'commercial', 'lot' => 3, 'icone' => 'calendar', 'actions' => ['voir' => 'Consulter', 'gerer' => 'Réserver et affecter']],
        'CMD' => ['route' => 'commandes.index', 'titre' => 'Commandes (historique)', 'groupe' => 'commercial', 'lot' => 3, 'icone' => 'archive', 'actions' => ['voir' => 'Consulter']],
        'VEN' => ['route' => 'ventes.index', 'court' => 'Ventes', 'titre' => 'Ventes', 'groupe' => 'commercial', 'lot' => 3, 'icone' => 'cart', 'actions' => ['voir' => 'Consulter', 'gerer' => 'Enregistrer les ventes']],
        'CPT' => ['route' => 'tresorerie.index', 'titre' => 'Trésorerie', 'groupe' => 'commercial', 'lot' => 3, 'icone' => 'wallet', 'actions' => ['voir' => 'Consulter', 'gerer' => 'Saisir recettes et dépenses, invalider une opération', 'encaisser' => 'Encaisser une vente']],
        'EXE' => ['route' => 'exercices.index', 'titre' => 'Bilan et clôture', 'groupe' => 'commercial', 'lot' => 3, 'icone' => 'lock', 'actions' => ['voir' => 'Consulter les bilans', 'cloturer' => 'Clôturer un exercice']],
        'RAP' => ['route' => 'rapprochements.index', 'titre' => 'Rapprochements', 'groupe' => 'commercial', 'lot' => 3, 'icone' => 'link', 'actions' => ['voir' => 'Consulter', 'gerer' => 'Corriger les liaisons']],
        'MKT' => ['route' => 'marketing.index', 'titre' => 'Marketing WhatsApp', 'groupe' => 'commercial', 'lot' => 5, 'icone' => 'chat', 'actions' => ['voir' => 'Consulter', 'gerer' => 'Préparer et envoyer']],

        'OVA' => ['route' => 'ovins.animaux.index', 'court' => 'Troupeau', 'titre' => 'Animaux', 'groupe' => 'ovins', 'lot' => 4, 'icone' => 'tag', 'actions' => ['voir' => 'Consulter', 'gerer' => 'Gérer les fiches']],
        'OVR' => ['route' => 'ovins.reproduction.index', 'titre' => 'Reproduction ovine', 'groupe' => 'ovins', 'lot' => 4, 'icone' => 'heart', 'actions' => ['voir' => 'Consulter', 'gerer' => 'Saillies et agnelages']],
        'OVS' => ['route' => 'ovins.soins.index', 'titre' => 'Soins ovins', 'groupe' => 'ovins', 'lot' => 4, 'icone' => 'heart', 'actions' => ['voir' => 'Consulter', 'gerer' => 'Modèles et soins']],
        'OVM' => ['route' => 'ovins.mouvements.index', 'titre' => 'Transferts et sorties', 'groupe' => 'ovins', 'lot' => 4, 'icone' => 'move', 'actions' => ['voir' => 'Consulter', 'gerer' => 'Transférer et sortir']],
        'OVI' => ['route' => 'ovins.inventaires.index', 'titre' => 'Inventaires ovins', 'groupe' => 'ovins', 'lot' => 4, 'icone' => 'clipboard', 'actions' => ['voir' => 'Consulter', 'gerer' => 'Pointer', 'cloturer' => 'Clôturer un inventaire']],

        'INF' => ['route' => 'organisation.lieux.index', 'titre' => 'Bâtiments et parcs', 'groupe' => 'organisation', 'lot' => 5, 'icone' => 'building', 'actions' => ['voir' => 'Consulter', 'gerer' => 'Affecter les emplacements']],
        'IFP' => ['route' => 'organisation.planning.index', 'titre' => 'Planning des emplacements', 'groupe' => 'organisation', 'lot' => 5, 'icone' => 'calendar', 'actions' => ['voir' => 'Consulter', 'gerer' => 'Planifier']],
        'ACT' => ['route' => 'activites.index', 'titre' => 'Activités et équipes', 'groupe' => 'organisation', 'lot' => 5, 'icone' => 'check', 'actions' => ['voir' => 'Consulter', 'gerer' => 'Planifier et affecter', 'cloturer' => 'Clôturer une tâche']],
        'RH' => ['route' => 'rh.employes.index', 'titre' => 'Personnel et congés', 'groupe' => 'organisation', 'lot' => 5, 'icone' => 'id', 'actions' => ['voir' => 'Consulter', 'gerer' => 'Gérer absences et congés', 'cloturer' => 'Clôture annuelle des congés']],

        'ACC' => ['titre' => 'Utilisateurs et droits', 'groupe' => 'administration', 'lot' => 1, 'icone' => 'shield', 'route' => 'utilisateurs.index', 'actions' => ['voir' => 'Consulter les utilisateurs', 'gerer' => 'Créer et modifier les utilisateurs', 'droits' => 'Modifier la matrice des droits', 'journal' => 'Consulter le journal', 'identite' => 'Prendre l’identité d’un utilisateur']],
    ],

    /*
    | Matrice proposée par défaut (l'administrateur a tous les droits, sans exception).
    | Clés : rôle => liste de « CODE.action », ou « CODE.* » pour toutes les actions du module.
    */
    'default_matrix' => [
        'gestionnaire' => ['PIL.*', 'REP.*', 'PON.*', 'OEU.*', 'INC.*', 'VOL.*', 'POI.*', 'ALI.*', 'SAV.*', 'RES.*', 'CMD.*', 'VEN.*', 'CPT.*', 'EXE.voir', 'RAP.*', 'MKT.*', 'OVA.*', 'OVR.*', 'OVS.*', 'OVM.*', 'OVI.voir', 'OVI.gerer', 'INF.*', 'IFP.*', 'ACT.*', 'RH.voir', 'RH.gerer', 'ACC.voir'],
        'vendeur' => ['PIL.voir', 'OEU.voir', 'VOL.voir', 'RES.*', 'CMD.voir', 'VEN.*', 'CPT.voir', 'CPT.encaisser', 'MKT.*'],
        'ouvrier' => ['PIL.voir', 'PON.voir', 'PON.gerer', 'VOL.voir', 'ALI.voir', 'ALI.distribuer', 'SAV.voir', 'SAV.realiser', 'ACT.voir'],
        'technicien' => ['PIL.voir', 'REP.*', 'PON.*', 'OEU.*', 'INC.*', 'VOL.*', 'POI.*', 'ALI.*', 'SAV.*', 'OVA.*', 'OVR.*', 'OVS.*', 'OVM.*', 'OVI.*', 'INF.*', 'IFP.*', 'ACT.voir', 'ACT.gerer'],
        'gerant_equipe' => ['PIL.voir', 'PON.voir', 'VOL.voir', 'ALI.voir', 'SAV.voir', 'ACT.voir', 'ACT.cloturer', 'INF.voir'],
        'associe ovin' => ['PIL.voir', 'OVA.voir', 'OVR.voir', 'OVS.voir', 'OVM.voir', 'OVI.voir'],
    ],

    /*
    | Référentiel commercial (EC24). Codes de produit enregistrés dans modVente_Ventes.espece.
    | « general » : vendable par le formulaire de vente générale (les œufs passent par leurs lots, les ovins par une sortie animale).
    | « categorie » : catégorie de recette proposée par défaut à l'encaissement.
    */
    'produits' => [
        'poulet_chair' => ['libelle' => 'Poulet de chair', 'general' => true, 'categorie' => 'Vente poulets abattus'],
        'goliath_chair' => ['libelle' => 'Goliath de chair', 'general' => true, 'categorie' => 'Vente poulets abattus'],
        'poussin_goliath' => ['libelle' => 'Poussins Goliath', 'general' => true, 'categorie' => 'Vente poussins'],
        'poussin_chair' => ['libelle' => 'Poussins d’un jour', 'general' => true, 'categorie' => 'Vente poussins'],
        'pret_a_pondre' => ['libelle' => 'Poulettes prêtes à pondre', 'general' => true, 'categorie' => 'Vente reproducteurs'],
        'coq_reproducteurs' => ['libelle' => 'Coqs reproducteurs', 'general' => true, 'categorie' => 'Vente reproducteurs'],
        'oeufs_feconds' => ['libelle' => 'Œufs fécondés', 'general' => false, 'espece' => 'poulet', 'categorie' => 'Vente œufs fécondés'],
        'oeufs_table' => ['libelle' => 'Œufs de table', 'general' => false, 'espece' => 'poulet', 'categorie' => 'Autres recettes'],
        'oeufs_caille' => ['libelle' => 'Œufs de caille', 'general' => false, 'espece' => 'caille', 'categorie' => 'Autres recettes'],
        'ovin' => ['libelle' => 'Ovin', 'general' => false, 'categorie' => 'Vente ovins (Tabaski)'],
    ],

    // RG-CPT-002
    'categories' => [
        'recette' => ['Vente poussins', 'Vente poulets abattus', 'Vente œufs fécondés', 'Vente reproducteurs', 'Vente ovins (Tabaski)', 'Provision Caisse', 'Autres recettes'],
        'depense' => ['Aliments', 'Médicaments', 'Salaires', 'Transport', 'Matériel', 'Entretien', 'Eau / Électricité', 'Autres dépenses'],
    ],

    // RG-CPT-003
    'moyens_paiement' => ['Espèces', 'Orange Money', 'Wave', 'Transfert Orange Money', 'Transfert Wave', 'Free Money', 'E-Money', 'Chèque', 'Virement bancaire', 'Autre'],

    // Lot 4 — ovins. Listes fermées pour les nouvelles saisies ; les anciennes valeurs libres restent lisibles (EC24).
    'ovins' => [
        'types_lot' => ['reproductrices' => 'Reproductrices', 'agnelles' => 'Agnelles', 'beliers' => 'Béliers', 'agneaux' => 'Agneaux', 'engraissement' => 'Engraissement', 'autre' => 'Autre'],
        'statuts' => ['en_ferme' => 'En ferme', 'vendu' => 'Vendu', 'mort' => 'Mort', 'reforme' => 'Réformé', 'sorti' => 'Sorti'],
        // RG-OVM-014 : type de sortie → statut de l'animal.
        'sorties' => ['vente' => ['Vente', 'vendu'], 'mortalite' => ['Mortalité', 'mort'], 'reforme' => ['Réforme', 'reforme'], 'autre' => ['Autre sortie', 'sorti']],
        'variantes_produit' => ['ovin', 'ovins', 'mouton', 'moutons', 'belier', 'bélier', 'brebis', 'agneau', 'agneaux', 'tabaski'],
        // RG-OVR-008 : paramètres modifiables du calendrier de reproduction.
        'calendrier' => ['gestation' => 150, 'allaitement' => 90, 'preparation' => 30, 'cycle_mois' => 9, 'horizon_ans' => 3],
        'age_agnelle_jours' => 270,
        // RG-OVA-010 / 014
        'medias' => [
            'images' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'],
            'videos' => ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/ogg' => 'ogv', 'video/quicktime' => 'mov'],
            'max_image' => 5 * 1024 * 1024, 'max_video' => 50 * 1024 * 1024,
            // Dossier où la nouvelle application range les médias qu'elle reçoit, et racine de l'ancienne application
            // pour relire les médias existants (chemin_fichier est relatif à cette racine).
            'dossier' => env('SOMADI_MEDIAS_OVINS', storage_path('app/medias-ovins')),
            'racine_ancienne' => env('SOMADI_RACINE_ANCIENNE', '/home/somadix/www'),
        ],
    ],

    // Version applicative inscrite dans les archives de clôture.
    'version' => 'somadi-5.0.0',

    // Authentification renforcée.
    // - Après le mot de passe, un code PIN à 6 chiffres est envoyé par e-mail (PC de bureau, ou téléphone sans empreinte).
    // - Sur téléphone, l'empreinte digitale (clé d'accès WebAuthn) remplace mot de passe et code.
    'authentification' => [
        // Interrupteur de secours : false = retour au mot de passe seul (par exemple si l'envoi d'e-mails est en panne).
        'pin_email' => (bool) env('SOMADI_PIN_EMAIL', true),
        'pin_duree_minutes' => 10,
        'pin_tentatives' => 5,
        // Renvois de code autorisés par compte sur 15 minutes.
        'pin_renvois' => 3,
        'empreinte' => (bool) env('SOMADI_EMPREINTE', true),
        // Domaine auquel les empreintes sont rattachées (par défaut : celui d'APP_URL). Le changer invalide les empreintes.
        'webauthn_domaine' => env('SOMADI_WEBAUTHN_DOMAINE') ?: parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST),
    ],

    // Lot actuellement livré : les modules d'un lot supérieur sont affichés « bientôt disponible ».
    'lot_livre' => (int) env('SOMADI_LOT_LIVRE', 5),
];
