SET FOREIGN_KEY_CHECKS=0;
SET NAMES utf8mb4;

CREATE TABLE `bandes_parentales` (
  `id` int NOT NULL,
  `nom_bande` varchar(100) NOT NULL,
  `espece` enum('poulet','caille') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `nb_males` int NOT NULL,
  `nb_femelles` int NOT NULL,
  `date_mise_en_place` date DEFAULT NULL,
  `observations` text,
  `reformee` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `batiments_engraissement` (
  `id` int NOT NULL,
  `nom_batiment` varchar(100) DEFAULT NULL,
  `capacite` int DEFAULT NULL,
  `actif` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `catalogue_produits` (
  `id` int NOT NULL,
  `nom_produit` varchar(100) DEFAULT NULL,
  `prix` decimal(10,2) DEFAULT NULL,
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `commandes` (
  `id` int NOT NULL,
  `nom_client` varchar(100) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `produit` varchar(100) DEFAULT NULL,
  `quantite` int DEFAULT NULL,
  `date_commande` date DEFAULT NULL,
  `statut` enum('en attente','confirmée','livrée') DEFAULT 'en attente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `commandes_vente` (
  `id` int NOT NULL,
  `type_produit` varchar(50) NOT NULL,
  `quantite` int NOT NULL,
  `prix_unitaire` decimal(10,2) NOT NULL,
  `prix_total` decimal(10,2) NOT NULL,
  `acompte_verse` decimal(10,2) NOT NULL DEFAULT '0.00',
  `solde_restant` decimal(10,2) NOT NULL DEFAULT '0.00',
  `date_reservation` date NOT NULL,
  `date_livraison_prevue` date DEFAULT NULL,
  `statut` enum('réservé','livré','annulé') DEFAULT 'réservé',
  `remarque` text,
  `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `date_livraison_effective` date DEFAULT NULL,
  `nom_client` varchar(100) DEFAULT NULL,
  `telephone_client` varchar(20) DEFAULT NULL,
  `statut_commande` enum('pre-enregistree','confirmee','en_livraison','livree','annulee') DEFAULT 'pre-enregistree'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `historique_actions` (
  `id` int NOT NULL,
  `type_action` varchar(50) DEFAULT NULL,
  `description` text,
  `date_action` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `incubateurs` (
  `id` int NOT NULL,
  `nom` varchar(100) DEFAULT NULL,
  `capacite` int DEFAULT NULL,
  `type` enum('incubateur','eclosoir') NOT NULL DEFAULT 'incubateur',
  `date_mise_service` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `lots_incubation` (
  `id` int NOT NULL,
  `nom_lot` varchar(50) DEFAULT NULL,
  `date_entree` date DEFAULT NULL,
  `espece` enum('poulet','caille') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `type_oeuf` enum('produit','achete') NOT NULL,
  `nombre_oeufs` int DEFAULT NULL,
  `incubateur` varchar(50) DEFAULT NULL,
  `transfere` tinyint(1) DEFAULT '0',
  `date_transfert` date DEFAULT NULL,
  `nombre_eclos` int DEFAULT '0',
  `utilisateur_transfert` varchar(100) DEFAULT NULL,
  `utilisateur_eclosion` varchar(100) DEFAULT NULL,
  `observations` text,
  `id_bande` int DEFAULT NULL,
  `date_mirage` date DEFAULT NULL,
  `nombre_fertiles` int DEFAULT '0',
  `utilisateur_mirage` varchar(100) DEFAULT NULL,
  `date_eclosion` date DEFAULT NULL,
  `taux_fertilite` decimal(10,0) DEFAULT NULL,
  `taux_eclosion` decimal(10,0) DEFAULT NULL,
  `date_eclosion_previsionnelle` date DEFAULT NULL,
  `nombre_oeufs_initial` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `lots_oeufs_ramasses` (
  `id` int NOT NULL,
  `date_creation` date DEFAULT NULL,
  `id_bande` int DEFAULT NULL,
  `id_stock_oeufs` int DEFAULT NULL,
  `type_lot` enum('incuber','vendre') DEFAULT 'incuber',
  `quantite` int DEFAULT NULL,
  `statut` enum('nouveau','charge','vendu') DEFAULT 'nouveau',
  `utilisateur` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modAct_categories` (
  `id` int NOT NULL,
  `libelle` varchar(100) NOT NULL,
  `couleur` varchar(20) DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modAct_equipes` (
  `id` int NOT NULL,
  `nom` varchar(120) NOT NULL,
  `description` text,
  `couleur` varchar(20) DEFAULT '#2d89ef',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modAct_equipe_membres` (
  `id` int NOT NULL,
  `id_equipe` int NOT NULL,
  `id_user` int NOT NULL,
  `date_ajout` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modAct_journal_v112` (
  `id` bigint NOT NULL,
  `id_tache` bigint NOT NULL,
  `id_user` int NOT NULL,
  `action` varchar(30) NOT NULL,
  `motif` text NOT NULL,
  `avant_json` longtext,
  `apres_json` longtext NOT NULL,
  `request_key` varchar(64) NOT NULL,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modAct_occupations` (
  `id` bigint NOT NULL,
  `id_user` int NOT NULL,
  `titre` varchar(200) NOT NULL,
  `debut` datetime NOT NULL,
  `fin` datetime NOT NULL,
  `type` enum('reunion','absence','mission','autre') NOT NULL DEFAULT 'autre',
  `commentaire` text,
  `cree_par` int DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modAct_occurrences_origine` (
  `id_tache` bigint NOT NULL,
  `id_routine_tache` int NOT NULL,
  `debut_origine` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modAct_projets` (
  `id` int NOT NULL,
  `code` varchar(30) DEFAULT NULL,
  `titre` varchar(200) NOT NULL,
  `description` text,
  `date_debut` date DEFAULT NULL,
  `date_cible` date DEFAULT NULL,
  `statut` enum('planifie','en_cours','termine','annule') NOT NULL DEFAULT 'planifie',
  `priorite` enum('basse','normale','haute','critique') NOT NULL DEFAULT 'normale',
  `id_responsable` int DEFAULT NULL,
  `id_equipe` int DEFAULT NULL,
  `cree_par` int DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `maj_le` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modAct_routines` (
  `id` int NOT NULL,
  `titre` varchar(200) NOT NULL,
  `description` text,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `id_equipe` int DEFAULT NULL,
  `id_responsable` int DEFAULT NULL,
  `cree_par` int DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `maj_le` datetime DEFAULT NULL,
  `couleur_fond` varchar(7) NOT NULL DEFAULT '#EAF4FF'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modAct_routine_taches` (
  `id` int NOT NULL,
  `id_routine` int NOT NULL,
  `nom` varchar(200) NOT NULL,
  `description` text,
  `id_categorie` int DEFAULT NULL,
  `priorite` enum('basse','normale','haute','critique') NOT NULL DEFAULT 'normale',
  `charge_jh` decimal(6,2) DEFAULT NULL,
  `duree_estimee_min` int DEFAULT NULL,
  `horaire_debut` time DEFAULT NULL,
  `horaire_fin` time DEFAULT NULL,
  `delai_echeance_min` int DEFAULT NULL,
  `type_recurrence` enum('quotidien','hebdo','mensuel','intervalle','manuel','annuel') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'quotidien',
  `jours_semaine` set('lun','mar','mer','jeu','ven','sam','dim') DEFAULT NULL,
  `jour_mois` tinyint DEFAULT NULL,
  `jours_mois` varchar(120) DEFAULT NULL,
  `intervalle_min` int DEFAULT NULL,
  `intervalle_semaines` int DEFAULT NULL,
  `mois_annee` varchar(50) DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modAct_taches` (
  `id` bigint NOT NULL,
  `source_type` enum('projet','routine') NOT NULL,
  `id_projet` int DEFAULT NULL,
  `id_routine` int DEFAULT NULL,
  `id_routine_tache` int DEFAULT NULL,
  `nom` varchar(200) NOT NULL,
  `description` text,
  `id_categorie` int DEFAULT NULL,
  `priorite` enum('basse','normale','haute','critique') NOT NULL DEFAULT 'normale',
  `statut` enum('planifie','en_cours','realise','annule','reporte') NOT NULL DEFAULT 'planifie',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_debut_prevue` datetime DEFAULT NULL,
  `date_fin_prevue` datetime DEFAULT NULL,
  `date_echeance` datetime DEFAULT NULL,
  `date_realisation` datetime DEFAULT NULL,
  `charge_jh` decimal(6,2) DEFAULT NULL,
  `duree_estimee_min` int DEFAULT NULL,
  `duree_reelle_min` int DEFAULT NULL,
  `cree_par` int DEFAULT NULL,
  `maj_le` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modAct_tache_affectations` (
  `id` bigint NOT NULL,
  `id_tache` bigint NOT NULL,
  `type_cible` enum('user','equipe') NOT NULL,
  `id_user` int DEFAULT NULL,
  `id_equipe` int DEFAULT NULL,
  `role_affectation` enum('responsable','executeur','observateur') NOT NULL DEFAULT 'executeur'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modAct_tache_pointages` (
  `id` bigint NOT NULL,
  `id_tache` bigint NOT NULL,
  `id_user` int NOT NULL,
  `action` enum('demarrer','pause','reprendre','terminer','commenter') NOT NULL,
  `ts_action` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `commentaire` text,
  `url_preuve` varchar(255) DEFAULT NULL,
  `duree_delta_min` int DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modAlim_distributions` (
  `id` int NOT NULL,
  `id_lot` int NOT NULL,
  `date_distribution` date NOT NULL,
  `id_type_aliment` int NOT NULL,
  `quantite_totale` float NOT NULL,
  `remarque` text,
  `utilisateur` varchar(100) DEFAULT NULL,
  `date_enregistrement` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modAlim_plans` (
  `id` int NOT NULL,
  `espece` varchar(100) NOT NULL,
  `nom_plan` varchar(100) NOT NULL,
  `commentaire` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modAlim_plan_lignes` (
  `id` int NOT NULL,
  `id_plan` int NOT NULL,
  `semaine` int NOT NULL,
  `id_aliment` int NOT NULL,
  `qte_par_tete` float NOT NULL,
  `is_transition` tinyint(1) NOT NULL DEFAULT '0',
  `id_aliment_source` int DEFAULT NULL,
  `id_aliment_cible` int DEFAULT NULL,
  `pct_source` decimal(5,2) DEFAULT NULL,
  `pct_cible` decimal(5,2) DEFAULT NULL,
  `repartition_jour` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modAlim_stocks` (
  `id` int NOT NULL,
  `id_type` int NOT NULL,
  `date_entree` date NOT NULL,
  `quantite_kg` decimal(10,2) NOT NULL,
  `seuil_alerte` decimal(10,2) DEFAULT NULL,
  `commentaire` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modAlim_types` (
  `id` int NOT NULL,
  `nom_aliment` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modCompta_exercices` (
  `annee` smallint NOT NULL,
  `cutoff` date NOT NULL,
  `closed_at` datetime NOT NULL,
  `author_id` int NOT NULL,
  `author_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `commentaire` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `snapshot_json` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `snapshot_sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pdf_blob` longblob NOT NULL,
  `pdf_sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version_application` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `modCompta_exercice_lock` (
  `id` tinyint NOT NULL,
  `schema_version` int NOT NULL,
  `revision` bigint UNSIGNED NOT NULL DEFAULT '0',
  `closed_through` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `modCompta_operations` (
  `id` int NOT NULL,
  `date_op` date NOT NULL,
  `type_op` enum('recette','depense') NOT NULL,
  `categorie` varchar(100) NOT NULL,
  `utilisateur` varchar(100) DEFAULT NULL,
  `libelle` varchar(255) NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `moyen_paiement` varchar(100) DEFAULT NULL,
  `observations` text,
  `date_enregistrement` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `source_table` varchar(100) DEFAULT NULL,
  `source_id` int DEFAULT NULL,
  `est_valide` tinyint DEFAULT '1',
  `invalidated_at` datetime DEFAULT NULL,
  `date_vente` date DEFAULT NULL,
  `invalidated_comment` text NOT NULL,
  `invalidated_by` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `id_lot` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modCompta_rapprochements` (
  `id` bigint UNSIGNED NOT NULL,
  `request_token` char(64) NOT NULL,
  `id_vente` int NOT NULL,
  `created_at` datetime NOT NULL,
  `author_id` int NOT NULL,
  `author_name` varchar(150) NOT NULL,
  `motif` text NOT NULL,
  `avant_json` longtext NOT NULL,
  `apres_json` longtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modInfra_emplacements` (
  `id_batiment` int NOT NULL,
  `code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_site` int DEFAULT NULL,
  `type_lieu` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'batiment',
  `famille` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'a_configurer',
  `espece` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `stade` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `surface_m2` decimal(12,2) DEFAULT NULL,
  `etat` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'disponible',
  `disponible_le` date DEFAULT NULL,
  `observations` text COLLATE utf8mb4_unicode_ci,
  `version` int NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `modInfra_journal` (
  `id` bigint NOT NULL,
  `cree_le` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `acteur` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `famille` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_source` int DEFAULT NULL,
  `id_batiment` int DEFAULT NULL,
  `avant_qte` int DEFAULT NULL,
  `apres_qte` int DEFAULT NULL,
  `motif` text COLLATE utf8mb4_unicode_ci,
  `details` longtext COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `modInfra_positions` (
  `famille` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_source` int NOT NULL,
  `id_batiment` int NOT NULL,
  `quantite` int NOT NULL,
  `maj_le` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `modInfra_requetes` (
  `cle` char(64) NOT NULL,
  `cree_le` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `utilisateur` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modInfra_verrou` (
  `id` tinyint NOT NULL,
  `version_schema` int NOT NULL,
  `revision` bigint NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modLotVol_lots_volailles` (
  `id` int NOT NULL,
  `nom_lot` varchar(100) NOT NULL,
  `espece` varchar(50) DEFAULT NULL,
  `race` varchar(50) DEFAULT NULL,
  `nombre_initial` int NOT NULL,
  `date_entree` date DEFAULT NULL,
  `id_plan` int DEFAULT NULL,
  `origine` varchar(100) DEFAULT NULL,
  `remarques` text,
  `statut` enum('actif','archive') DEFAULT 'actif',
  `date_enregistrement` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `effectif_en_cours` int DEFAULT NULL,
  `id_lot_incubation_source` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modLotVol_mouvements` (
  `id` int NOT NULL,
  `id_lot` int NOT NULL,
  `date_mouvement` date NOT NULL,
  `type_mouvement` enum('entrée','vente','sortie','mortalité') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `quantite` int DEFAULT NULL,
  `commentaire` text,
  `utilisateur` varchar(100) DEFAULT NULL,
  `date_enregistrement` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modLotVol_suivi_poids` (
  `id` int NOT NULL,
  `id_lot` int NOT NULL,
  `date_suivi` date NOT NULL,
  `poids_moyen` decimal(7,2) NOT NULL,
  `utilisateur` varchar(100) DEFAULT NULL,
  `date_enregistrement` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modLot_Vol_ref_cout_age_poulet` (
  `age_jours` int NOT NULL,
  `espece` varchar(30) NOT NULL DEFAULT 'poulet',
  `cout_cumule_par_tete` decimal(12,2) NOT NULL,
  `marge_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `prix_min_profitable` decimal(12,2) GENERATED ALWAYS AS ((`cout_cumule_par_tete` * (1 + (`marge_pct` / 100)))) STORED,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `commentaire` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modMkt_blacklist` (
  `id` int NOT NULL,
  `client_tel_norm` varchar(20) NOT NULL,
  `motif` varchar(150) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modMkt_campagnes` (
  `id` int NOT NULL,
  `titre` varchar(150) NOT NULL,
  `id_template` int NOT NULL,
  `statut` enum('brouillon','en_cours','terminee') NOT NULL DEFAULT 'brouillon',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modMkt_logs` (
  `id` int NOT NULL,
  `id_campagne` int NOT NULL,
  `client_nom` varchar(150) DEFAULT NULL,
  `client_tel_raw` varchar(50) DEFAULT NULL,
  `client_tel_norm` varchar(20) NOT NULL,
  `action` enum('OPEN_WA','MARQUE_ENVOYE','IGNORE') NOT NULL,
  `user_login` varchar(80) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modMkt_templates` (
  `id` int NOT NULL,
  `titre` varchar(120) NOT NULL,
  `message` text NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `is_actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modProphylaxie_plans` (
  `id` int NOT NULL,
  `nom_plan` varchar(100) NOT NULL,
  `espece` varchar(100) NOT NULL,
  `recurrence_type` enum('quotidienne','hebdomadaire','mensuelle','annuelle') NOT NULL,
  `id_soin` int NOT NULL,
  `soin_type` varchar(100) DEFAULT NULL,
  `produit` varchar(255) DEFAULT NULL,
  `dose` varchar(100) DEFAULT NULL,
  `voie` varchar(100) DEFAULT NULL,
  `date_debut` date NOT NULL,
  `fin_type` enum('date','occurrences','illimite') DEFAULT 'date',
  `date_fin` date DEFAULT NULL,
  `nb_occurrences` int DEFAULT NULL,
  `rappel` tinyint(1) DEFAULT '0',
  `rappel_jours` int DEFAULT NULL,
  `jours_intervalle` int DEFAULT NULL,
  `semaines_intervalle` int DEFAULT NULL,
  `jours_semaine` text,
  `jour_mois` int DEFAULT NULL,
  `jour_annee` int DEFAULT NULL,
  `mois_annee` varchar(20) DEFAULT NULL,
  `date_enregistrement` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modRef_volailles` (
  `id` int NOT NULL,
  `espece` varchar(50) NOT NULL,
  `race` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modRepro_mouvements` (
  `id` int NOT NULL,
  `id_bande` int NOT NULL,
  `date_mouvement` date NOT NULL,
  `type_mouvement` enum('entrée','mortalité','retrait') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `sexe` enum('mâle','femelle') NOT NULL,
  `quantite` int NOT NULL,
  `commentaire` text,
  `utilisateur` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modSoinVacc_catalogue` (
  `id` int NOT NULL,
  `nom` varchar(100) NOT NULL,
  `type` enum('soin','vaccin') NOT NULL,
  `produit_utilise` varchar(100) DEFAULT NULL,
  `dose` varchar(50) DEFAULT NULL,
  `voie_administration` varchar(50) DEFAULT NULL,
  `espece_cible` varchar(50) DEFAULT NULL,
  `observation` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modSoinVacc_programme_poussins` (
  `id` int NOT NULL,
  `espece` varchar(50) NOT NULL,
  `jour_post_eclosion` int NOT NULL,
  `commentaire` text,
  `id_soin` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modSoinVacc_soins` (
  `id` int NOT NULL,
  `id_lot` int NOT NULL,
  `date_prevue` date NOT NULL,
  `id_soin` int NOT NULL,
  `type` varchar(100) DEFAULT NULL,
  `produit` varchar(255) DEFAULT NULL,
  `dose` varchar(100) DEFAULT NULL,
  `voie` varchar(100) DEFAULT NULL,
  `origine_plan_id` int DEFAULT NULL,
  `etat` enum('planifie','realise','annule') DEFAULT 'planifie',
  `date_generation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `date_changement_etat` datetime DEFAULT NULL,
  `observation` text,
  `origine` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modSoinVacc_Thermo_reglages` (
  `id` int NOT NULL,
  `id_lot` int NOT NULL,
  `date_prevue` date NOT NULL,
  `temperature_cible` decimal(4,1) NOT NULL,
  `tolerance` decimal(3,1) DEFAULT '1.0',
  `commentaire` varchar(255) DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modVente_oeufs_affectations` (
  `id` int NOT NULL,
  `id_vente` int NOT NULL,
  `id_lot_oeufs` int NOT NULL,
  `quantite` int NOT NULL,
  `utilisateur` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `modVente_ovins_liaisons` (
  `id` int NOT NULL,
  `id_sortie` int NOT NULL,
  `id_animal` int NOT NULL,
  `id_vente` int NOT NULL,
  `code_animal` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mode_liaison` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `motif` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `avant_json` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `utilisateur` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `modVente_Ventes` (
  `id` int NOT NULL,
  `date_vente` date NOT NULL,
  `client_nom` varchar(100) NOT NULL,
  `client_tel` varchar(20) NOT NULL,
  `espece` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantite` int NOT NULL,
  `prix_unitaire` decimal(10,2) NOT NULL,
  `montant_total` decimal(10,2) NOT NULL,
  `commentaire` text,
  `utilisateur` varchar(100) DEFAULT NULL,
  `montant_reste_encaisser` decimal(10,2) NOT NULL DEFAULT '0.00',
  `statut` enum('A encaisser','Encaissé') NOT NULL DEFAULT 'A encaisser',
  `id_lot` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `modVente_Ventes_A_Completer` (
  `id` int NOT NULL,
  `date_vente` date NOT NULL,
  `quantite` int NOT NULL,
  `nom_lot` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `commentaire` text,
  `utilisateur` varchar(100) DEFAULT NULL,
  `statut` varchar(50) NOT NULL DEFAULT 'attente',
  `date_traitement` date DEFAULT NULL,
  `id_lot` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `OVINS_animaux` (
  `id_animal` int NOT NULL,
  `code_animal` varchar(100) DEFAULT NULL,
  `nom_animal` varchar(50) DEFAULT NULL,
  `sexe` enum('M','F') NOT NULL,
  `race` varchar(100) DEFAULT NULL,
  `date_naissance` date DEFAULT NULL,
  `date_entree` date DEFAULT NULL,
  `origine` varchar(100) DEFAULT NULL,
  `id_lot` int DEFAULT NULL,
  `id_site` int NOT NULL,
  `id_proprietaire` int DEFAULT NULL,
  `id_mere` int DEFAULT NULL,
  `id_pere` int DEFAULT NULL,
  `statut` varchar(30) DEFAULT 'en_ferme',
  `date_sortie` date DEFAULT NULL,
  `motif_sortie` varchar(100) DEFAULT NULL,
  `poids_naissance` decimal(5,2) DEFAULT NULL,
  `poids_dernier` decimal(6,2) DEFAULT NULL,
  `prix_achat` decimal(10,2) DEFAULT NULL,
  `prix_vente` decimal(10,2) DEFAULT NULL,
  `observations` text,
  `est_reproducteur` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `OVINS_animaux_medias` (
  `id_media` int NOT NULL,
  `id_animal` int NOT NULL,
  `type_media` enum('image','video') NOT NULL,
  `stade_age` enum('naissance','adolescence','adulte') NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `nom_fichier_original` varchar(255) DEFAULT NULL,
  `nom_fichier_stocke` varchar(255) NOT NULL,
  `chemin_fichier` varchar(500) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `taille_octets` bigint DEFAULT NULL,
  `date_ajout` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `utilisateur` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `OVINS_inventaires` (
  `id` int NOT NULL,
  `titre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `site_id` int DEFAULT NULL,
  `lot_id` int DEFAULT NULL,
  `proprietaire_id` int DEFAULT NULL,
  `perimetre` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ouvert_le` datetime NOT NULL,
  `ouvert_par` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ouvert',
  `version` int NOT NULL DEFAULT '1',
  `total_attendu` int NOT NULL,
  `dernier_mouvement_id` int NOT NULL DEFAULT '0',
  `request_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cloture_le` datetime DEFAULT NULL,
  `cloture_par` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bilan` text COLLATE utf8mb4_unicode_ci,
  `rapprochement_json` mediumtext COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `OVINS_inventaire_journal` (
  `id` int NOT NULL,
  `inventaire_id` int NOT NULL,
  `ligne_id` int DEFAULT NULL,
  `action` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resume` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `avant_json` mediumtext COLLATE utf8mb4_unicode_ci,
  `apres_json` mediumtext COLLATE utf8mb4_unicode_ci,
  `auteur` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cree_le` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `OVINS_inventaire_lignes` (
  `id` int NOT NULL,
  `inventaire_id` int NOT NULL,
  `numero` int NOT NULL,
  `nature` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'attendu',
  `animal_id` int DEFAULT NULL,
  `code_animal` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom_animal` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `site_id` int DEFAULT NULL,
  `lot_id` int DEFAULT NULL,
  `proprietaire_id` int DEFAULT NULL,
  `nom_site` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom_lot` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom_proprietaire` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sexe` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_naissance` date DEFAULT NULL,
  `statut_animal` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pointage` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'non_pointe',
  `site_constate` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `lot_constate` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `date_controle` date DEFAULT NULL,
  `controleur` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `commentaire` text COLLATE utf8mb4_unicode_ci,
  `version` int NOT NULL DEFAULT '1',
  `modifie_le` datetime DEFAULT NULL,
  `modifie_par` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_key` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `OVINS_lots` (
  `id_lot` int NOT NULL,
  `nom_lot` varchar(150) NOT NULL,
  `type_lot` varchar(50) NOT NULL,
  `race` varchar(100) DEFAULT NULL,
  `id_site` int NOT NULL,
  `date_entree` date NOT NULL,
  `effectif_initial` int DEFAULT '0',
  `effectif_encours` int DEFAULT '0',
  `provenance` varchar(150) DEFAULT NULL,
  `valeur_unitaire_achat` decimal(10,2) DEFAULT NULL,
  `valeur_totale_achat` decimal(10,2) DEFAULT NULL,
  `statut` varchar(30) DEFAULT 'actif',
  `observations` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `OVINS_lot_cycles` (
  `id_cycle` int NOT NULL,
  `id_lot` int NOT NULL,
  `num_cycle` int NOT NULL,
  `date_lutte` date NOT NULL,
  `date_agnelage_prevu` date NOT NULL,
  `date_fin_allaitement` date NOT NULL,
  `date_prepa` date NOT NULL,
  `date_prochaine_lutte` date NOT NULL,
  `post_mb_deb` date NOT NULL,
  `post_mb_fin` date NOT NULL,
  `pre_lutte_deb` date NOT NULL,
  `pre_lutte_fin` date NOT NULL,
  `duree_gestation_jours` int NOT NULL,
  `duree_allaitement_jours` int NOT NULL,
  `duree_prepa_jours` int NOT NULL,
  `duree_cycle_mois` int NOT NULL,
  `nb_ans_horizon` int NOT NULL,
  `date_generation` datetime NOT NULL,
  `utilisateur` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `OVINS_lot_soins_actions` (
  `id_action` int NOT NULL,
  `id_soin_plan` int NOT NULL,
  `id_lot` int NOT NULL,
  `num_cycle` int NOT NULL,
  `type_fenetre` varchar(30) NOT NULL,
  `date_soin` date NOT NULL,
  `libelle_action` varchar(255) NOT NULL,
  `commentaire` text,
  `statut` varchar(20) NOT NULL DEFAULT 'planifie',
  `date_generation` datetime NOT NULL,
  `utilisateur` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `OVINS_lot_soins_planifies` (
  `id_soin_plan` int NOT NULL,
  `id_cycle` int NOT NULL,
  `id_lot` int NOT NULL,
  `num_cycle` int NOT NULL,
  `type_fenetre` varchar(30) NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `libelle_prophylaxie` varchar(255) DEFAULT NULL,
  `commentaire` text,
  `statut` varchar(20) NOT NULL DEFAULT 'planifie',
  `date_generation` datetime NOT NULL,
  `utilisateur` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `OVINS_modeles_soins` (
  `id_modele` int NOT NULL,
  `nom_modele` varchar(150) NOT NULL,
  `categorie` varchar(50) NOT NULL DEFAULT 'general',
  `cible` varchar(30) NOT NULL DEFAULT 'tous',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `description` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `OVINS_modeles_soins_lignes` (
  `id_ligne` int NOT NULL,
  `id_modele` int NOT NULL,
  `jour_offset` int NOT NULL DEFAULT '0',
  `id_type_soin` int NOT NULL,
  `dose` varchar(80) DEFAULT NULL,
  `unite` varchar(30) DEFAULT NULL,
  `commentaire` varchar(255) DEFAULT NULL,
  `obligatoire` tinyint(1) NOT NULL DEFAULT '1',
  `ordre` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `OVINS_mouvements` (
  `id_mvt` int NOT NULL,
  `date_mvt` date NOT NULL,
  `type_mvt` varchar(50) NOT NULL,
  `id_lot` int DEFAULT NULL,
  `id_animal` int DEFAULT NULL,
  `quantite` int DEFAULT NULL,
  `id_site_source` int DEFAULT NULL,
  `id_site_destination` int DEFAULT NULL,
  `prix_unitaire` decimal(10,2) DEFAULT NULL,
  `montant_total` decimal(10,2) DEFAULT NULL,
  `source_table` varchar(100) DEFAULT NULL,
  `source_id` int DEFAULT NULL,
  `utilisateur` varchar(150) DEFAULT NULL,
  `commentaire` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `OVINS_proprietaires` (
  `id_proprietaire` int NOT NULL,
  `nom_proprietaire` varchar(120) NOT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `couleur_hex` varchar(7) DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `observations` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `OVINS_repro` (
  `id_repro` int NOT NULL,
  `id_mere` int NOT NULL,
  `id_pere` int DEFAULT NULL,
  `date_saillie` date DEFAULT NULL,
  `type_saillie` varchar(50) DEFAULT NULL,
  `date_agnelage_prevu` date DEFAULT NULL,
  `date_agnelage_reel` date DEFAULT NULL,
  `nb_agneaux_vivants` int DEFAULT '0',
  `nb_agneaux_males` int DEFAULT NULL,
  `nb_agneaux_femelles` int DEFAULT NULL,
  `nb_agneaux_morts` int DEFAULT '0',
  `difficulte_agnelage` tinyint(1) DEFAULT '0',
  `commentaire` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `OVINS_sites` (
  `id_site` int NOT NULL,
  `code_site` varchar(50) NOT NULL,
  `nom_site` varchar(150) NOT NULL,
  `localisation` varchar(255) DEFAULT NULL,
  `capacite_ovins` int DEFAULT '0',
  `type_site` varchar(50) DEFAULT NULL,
  `actif` tinyint(1) DEFAULT '1',
  `observations` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `OVINS_soins_realises` (
  `id_soin` int NOT NULL,
  `id_lot` int DEFAULT NULL,
  `id_animal` int DEFAULT NULL,
  `id_action` int DEFAULT NULL,
  `date_saisie` timestamp NULL DEFAULT NULL,
  `id_site` int DEFAULT NULL,
  `produit` varchar(30) DEFAULT NULL,
  `dose` varchar(30) DEFAULT NULL,
  `statut` varchar(20) NOT NULL DEFAULT 'planifie',
  `id_type_soin` int DEFAULT NULL,
  `date_soin` date NOT NULL,
  `cible` varchar(10) NOT NULL DEFAULT 'lot',
  `commentaire` text,
  `utilisateur` varchar(100) DEFAULT NULL,
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `type_soin` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `OVINS_soins_types` (
  `id_type_soin` int NOT NULL,
  `nom_soin` varchar(100) NOT NULL,
  `categorie` varchar(50) DEFAULT NULL,
  `espece` varchar(20) NOT NULL DEFAULT 'ovin',
  `voie` varchar(50) DEFAULT NULL,
  `delai_attente_jours` int DEFAULT NULL,
  `commentaire` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `OVINS_sorties` (
  `id_sortie` int NOT NULL,
  `cible` varchar(10) NOT NULL DEFAULT 'lot',
  `id_lot` int DEFAULT NULL,
  `id_animal` int DEFAULT NULL,
  `id_site` int DEFAULT NULL,
  `type_sortie` varchar(20) NOT NULL,
  `date_sortie` date NOT NULL,
  `quantite` int NOT NULL DEFAULT '1',
  `prix_unitaire` decimal(10,2) DEFAULT NULL,
  `montant_total` decimal(10,2) DEFAULT NULL,
  `client_nom` varchar(150) DEFAULT NULL,
  `client_contact` varchar(100) DEFAULT NULL,
  `motif` varchar(255) DEFAULT NULL,
  `commentaire` text,
  `utilisateur` varchar(100) DEFAULT NULL,
  `date_saisie` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `paiements_commandes` (
  `id` int NOT NULL,
  `id_commande` int DEFAULT NULL,
  `date_paiement` date DEFAULT NULL,
  `montant` decimal(12,2) DEFAULT NULL,
  `mode_paiement` varchar(50) DEFAULT NULL,
  `utilisateur` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `permissions_modules` (
  `id` int NOT NULL,
  `nom_module` varchar(100) DEFAULT NULL,
  `role` enum('admin','gestionnaire','vendeur','ouvrier','technicien','associe ovin') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `Personne` (
  `Nom` text NOT NULL,
  `Prenom` text NOT NULL,
  `Age` int NOT NULL,
  `Fonction` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `prix_poussins_engraisses` (
  `espece` varchar(50) NOT NULL,
  `prix` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `prophylaxie` (
  `id_prophylaxie` int UNSIGNED NOT NULL,
  `id_lot` int UNSIGNED NOT NULL,
  `type_vaccin` varchar(100) NOT NULL,
  `jour_admin` int UNSIGNED NOT NULL,
  `date_admin` date NOT NULL,
  `commentaire` text,
  `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `relances_commandes` (
  `id` int NOT NULL,
  `id_commande` int DEFAULT NULL,
  `canal` varchar(20) DEFAULT NULL,
  `date_heure` datetime DEFAULT CURRENT_TIMESTAMP,
  `utilisateur` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `resa_lots_previsionnels` (
  `id_lot` int NOT NULL,
  `espece` varchar(50) DEFAULT NULL,
  `date_eclosion_prevue` date DEFAULT NULL,
  `nombre_prevu` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `resa_reservations` (
  `id_reservation` int NOT NULL,
  `produit` varchar(50) DEFAULT NULL,
  `id_lot_prevu` int DEFAULT NULL,
  `nom_client` varchar(100) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `quantite` int DEFAULT NULL,
  `prix_unitaire` int DEFAULT NULL,
  `quantite_servie` int DEFAULT '0',
  `date_reservation` date DEFAULT NULL,
  `statut` varchar(20) DEFAULT 'pré-réservé',
  `commentaire` text,
  `acompte` int DEFAULT NULL,
  `date_livraison` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `rh_absences` (
  `id` int NOT NULL,
  `employe_id` int NOT NULL,
  `type` enum('conge','absence','maladie','sans_solde','autre') NOT NULL DEFAULT 'conge',
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `motif` varchar(255) DEFAULT NULL,
  `statut` enum('brouillon','soumise','validee','refusee','annulee') NOT NULL DEFAULT 'soumise',
  `jours_imputes` int NOT NULL DEFAULT '0',
  `cree_par` varchar(80) DEFAULT NULL,
  `valide_par` varchar(80) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `validated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `rh_conges_droits` (
  `id` int NOT NULL,
  `employe_id` int NOT NULL,
  `annee` int NOT NULL,
  `quota_libre` int NOT NULL DEFAULT '30',
  `bonus` int NOT NULL DEFAULT '0',
  `quota_impose` int NOT NULL DEFAULT '2',
  `note_bonus` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `rh_conges_mouvements` (
  `id` int NOT NULL,
  `employe_id` int NOT NULL,
  `annee` int NOT NULL,
  `date_mvt` date NOT NULL,
  `type_mvt` enum('PRISE','BONUS','PAIEMENT','RETENUE') NOT NULL,
  `jours` int NOT NULL DEFAULT '0',
  `taux_journalier` decimal(12,2) DEFAULT NULL,
  `montant` decimal(12,2) DEFAULT NULL,
  `reference_table` varchar(50) DEFAULT NULL,
  `reference_id` int DEFAULT NULL,
  `mois_application` date DEFAULT NULL,
  `created_by` varchar(80) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `rh_employes` (
  `id` int NOT NULL,
  `matricule` varchar(30) DEFAULT NULL,
  `nom` varchar(80) NOT NULL,
  `prenom` varchar(80) DEFAULT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `poste` varchar(80) DEFAULT NULL,
  `site_id` int DEFAULT NULL,
  `date_entree` date DEFAULT NULL,
  `salaire_mensuel` decimal(12,2) NOT NULL DEFAULT '0.00',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `rh_jours_imposes` (
  `id` int NOT NULL,
  `annee` int NOT NULL,
  `date_jour` date NOT NULL,
  `libelle` enum('Korité','Tabaski','Autre') NOT NULL DEFAULT 'Autre',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `stock_oeufs_ramasses` (
  `id` int NOT NULL,
  `date_ramassage` date DEFAULT NULL,
  `id_bande` int DEFAULT NULL,
  `total_oeufs` int DEFAULT NULL,
  `statut` enum('attente','affecte') DEFAULT 'attente',
  `utilisateur` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `stock_poussins_jour` (
  `id` int NOT NULL,
  `date_eclosion` date NOT NULL,
  `espece` varchar(50) NOT NULL,
  `nombre` int NOT NULL,
  `id_lot` int UNSIGNED NOT NULL,
  `utilisateur` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `date_entree_engraissement` date DEFAULT NULL,
  `statut` enum('nouveau','vendable','engraissement') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'nouveau',
  `vendus` int DEFAULT NULL,
  `engraisses` int DEFAULT NULL,
  `morts` int DEFAULT '0',
  `batiment_affecte` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `suivi_ponte` (
  `id` int NOT NULL,
  `id_bande` int DEFAULT NULL,
  `date` date DEFAULT NULL,
  `ramassage_matin` int DEFAULT NULL,
  `ramassage_apres_midi` int DEFAULT NULL,
  `utilisateur` varchar(100) DEFAULT NULL,
  `taux_ponte` decimal(5,2) DEFAULT NULL,
  `statut` varchar(100) DEFAULT NULL,
  `total_oeufs_valides` int DEFAULT NULL,
  `nb_oeufs_defectueux` int DEFAULT NULL,
  `temoin` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `utilisateurs` (
  `id` int NOT NULL,
  `nom` varchar(100) DEFAULT NULL,
  `prenom` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `mot_de_passe` varchar(255) DEFAULT NULL,
  `role` enum('admin','gestionnaire','vendeur','ouvrier','technicien','gerant_equipe','associe ovin') NOT NULL,
  `actif` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `bandes_parentales`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `bandes_parentales`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
ALTER TABLE `batiments_engraissement`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `batiments_engraissement`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
ALTER TABLE `catalogue_produits`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `catalogue_produits`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;
ALTER TABLE `commandes`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `commandes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
ALTER TABLE `commandes_vente`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `commandes_vente`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
ALTER TABLE `historique_actions`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `historique_actions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;
ALTER TABLE `incubateurs`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `incubateurs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
ALTER TABLE `lots_incubation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_bande` (`id_bande`);
ALTER TABLE `lots_incubation`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=119;
ALTER TABLE `lots_oeufs_ramasses`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `lots_oeufs_ramasses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=678;
ALTER TABLE `modAct_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_cat_libelle` (`libelle`);
ALTER TABLE `modAct_categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
ALTER TABLE `modAct_equipes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_eq_nom` (`nom`);
ALTER TABLE `modAct_equipes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
ALTER TABLE `modAct_equipe_membres`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_membre` (`id_equipe`,`id_user`),
  ADD KEY `k_user` (`id_user`);
ALTER TABLE `modAct_equipe_membres`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;
ALTER TABLE `modAct_journal_v112`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_act112_request` (`request_key`),
  ADD KEY `k_act112_tache` (`id_tache`,`id`);
ALTER TABLE `modAct_journal_v112`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;
ALTER TABLE `modAct_occupations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `k_user` (`id_user`),
  ADD KEY `k_debut` (`debut`),
  ADD KEY `fk_occ_cree` (`cree_par`);
ALTER TABLE `modAct_occupations`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
ALTER TABLE `modAct_occurrences_origine`
  ADD PRIMARY KEY (`id_tache`),
  ADD UNIQUE KEY `uq_act112_occurrence` (`id_routine_tache`,`debut_origine`);
ALTER TABLE `modAct_projets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `k_statut` (`statut`),
  ADD KEY `k_dates` (`date_debut`,`date_cible`),
  ADD KEY `k_resp` (`id_responsable`),
  ADD KEY `k_eq` (`id_equipe`),
  ADD KEY `fk_proj_cree` (`cree_par`);
ALTER TABLE `modAct_projets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
ALTER TABLE `modAct_routines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `k_actif` (`actif`),
  ADD KEY `fk_rout_eq` (`id_equipe`),
  ADD KEY `fk_rout_resp` (`id_responsable`),
  ADD KEY `fk_rout_cree` (`cree_par`);
ALTER TABLE `modAct_routines`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;
ALTER TABLE `modAct_routine_taches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `k_routine` (`id_routine`),
  ADD KEY `k_cat` (`id_categorie`);
ALTER TABLE `modAct_routine_taches`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;
ALTER TABLE `modAct_taches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_occurrence` (`id_routine_tache`,`date_debut_prevue`),
  ADD KEY `k_statut` (`statut`),
  ADD KEY `k_echeance` (`date_echeance`),
  ADD KEY `k_planning` (`date_debut_prevue`,`date_fin_prevue`),
  ADD KEY `k_source` (`source_type`,`id_projet`,`id_routine`),
  ADD KEY `k_cat` (`id_categorie`),
  ADD KEY `fk_tache_projet` (`id_projet`),
  ADD KEY `fk_tache_routine` (`id_routine`),
  ADD KEY `fk_tache_cree` (`cree_par`);
ALTER TABLE `modAct_taches`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19471;
ALTER TABLE `modAct_tache_affectations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_aff` (`id_tache`,`type_cible`,`id_user`,`id_equipe`),
  ADD KEY `k_tache` (`id_tache`),
  ADD KEY `k_user` (`id_user`),
  ADD KEY `k_eq` (`id_equipe`);
ALTER TABLE `modAct_tache_affectations`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38922;
ALTER TABLE `modAct_tache_pointages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `k_tache` (`id_tache`),
  ADD KEY `k_user` (`id_user`),
  ADD KEY `k_ts` (`ts_action`);
ALTER TABLE `modAct_tache_pointages`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=192;
ALTER TABLE `modAlim_distributions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_lot_date_aliment` (`id_lot`,`date_distribution`,`id_type_aliment`),
  ADD KEY `id_type_aliment` (`id_type_aliment`);
ALTER TABLE `modAlim_distributions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=844;
ALTER TABLE `modAlim_plans`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `modAlim_plans`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;
ALTER TABLE `modAlim_plan_lignes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_plan_semaine_transition` (`id_plan`,`semaine`,`is_transition`),
  ADD KEY `id_aliment` (`id_aliment`) USING BTREE;
ALTER TABLE `modAlim_plan_lignes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=336;
ALTER TABLE `modAlim_stocks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_type` (`id_type`);
ALTER TABLE `modAlim_stocks`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
ALTER TABLE `modAlim_types`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `modAlim_types`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;
ALTER TABLE `modCompta_exercices`
  ADD PRIMARY KEY (`annee`),
  ADD UNIQUE KEY `cutoff` (`cutoff`);
ALTER TABLE `modCompta_exercice_lock`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `modCompta_operations`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `modCompta_operations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1211;
ALTER TABLE `modCompta_rapprochements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sf120_request` (`request_token`),
  ADD KEY `idx_sf120_vente` (`id_vente`,`id`);
ALTER TABLE `modCompta_rapprochements`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `modInfra_emplacements`
  ADD PRIMARY KEY (`id_batiment`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `infra_site_fk` (`id_site`);
ALTER TABLE `modInfra_journal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `infra_journal_source` (`famille`,`id_source`),
  ADD KEY `infra_journal_bat` (`id_batiment`);
ALTER TABLE `modInfra_journal`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `modInfra_positions`
  ADD PRIMARY KEY (`famille`,`id_source`,`id_batiment`),
  ADD KEY `infra_position_bat` (`id_batiment`);
ALTER TABLE `modInfra_requetes`
  ADD PRIMARY KEY (`cle`);
ALTER TABLE `modInfra_verrou`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `modLotVol_lots_volailles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_lotvol_incubation_source` (`id_lot_incubation_source`),
  ADD KEY `idx_lots_id_plan` (`id_plan`);
ALTER TABLE `modLotVol_lots_volailles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=141;
ALTER TABLE `modLotVol_mouvements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_lot` (`id_lot`);
ALTER TABLE `modLotVol_mouvements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=793;
ALTER TABLE `modLotVol_suivi_poids`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_lot` (`id_lot`);
ALTER TABLE `modLotVol_suivi_poids`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;
ALTER TABLE `modLot_Vol_ref_cout_age_poulet`
  ADD PRIMARY KEY (`espece`,`age_jours`);
ALTER TABLE `modMkt_blacklist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `client_tel_norm` (`client_tel_norm`);
ALTER TABLE `modMkt_blacklist`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
ALTER TABLE `modMkt_campagnes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_template` (`id_template`);
ALTER TABLE `modMkt_campagnes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
ALTER TABLE `modMkt_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_campagne` (`id_campagne`),
  ADD KEY `client_tel_norm` (`client_tel_norm`);
ALTER TABLE `modMkt_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;
ALTER TABLE `modMkt_templates`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `modMkt_templates`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `modProphylaxie_plans`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `modProphylaxie_plans`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;
ALTER TABLE `modRef_volailles`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `modRef_volailles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
ALTER TABLE `modRepro_mouvements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_bande` (`id_bande`);
ALTER TABLE `modRepro_mouvements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=124;
ALTER TABLE `modSoinVacc_catalogue`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `modSoinVacc_catalogue`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;
ALTER TABLE `modSoinVacc_programme_poussins`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `modSoinVacc_programme_poussins`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=124;
ALTER TABLE `modSoinVacc_soins`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `modSoinVacc_soins`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24947;
ALTER TABLE `modSoinVacc_Thermo_reglages`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `modSoinVacc_Thermo_reglages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1625;
ALTER TABLE `modVente_oeufs_affectations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sf18_vente_lot` (`id_vente`,`id_lot_oeufs`),
  ADD KEY `ix_sf18_lot` (`id_lot_oeufs`);
ALTER TABLE `modVente_oeufs_affectations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
ALTER TABLE `modVente_ovins_liaisons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sf111_sortie` (`id_sortie`),
  ADD UNIQUE KEY `uq_sf111_animal` (`id_animal`),
  ADD UNIQUE KEY `uq_sf111_vente` (`id_vente`);
ALTER TABLE `modVente_ovins_liaisons`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;
ALTER TABLE `modVente_Ventes`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `modVente_Ventes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=382;
ALTER TABLE `modVente_Ventes_A_Completer`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `modVente_Ventes_A_Completer`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=295;
ALTER TABLE `OVINS_animaux`
  ADD PRIMARY KEY (`id_animal`),
  ADD KEY `id_lot` (`id_lot`),
  ADD KEY `id_site` (`id_site`),
  ADD KEY `idx_ovins_animaux_prop` (`id_proprietaire`);
ALTER TABLE `OVINS_animaux`
  MODIFY `id_animal` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=205;
ALTER TABLE `OVINS_animaux_medias`
  ADD PRIMARY KEY (`id_media`),
  ADD KEY `idx_animal` (`id_animal`);
ALTER TABLE `OVINS_animaux_medias`
  MODIFY `id_media` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=254;
ALTER TABLE `OVINS_inventaires`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_inv_request` (`request_key`);
ALTER TABLE `OVINS_inventaires`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
ALTER TABLE `OVINS_inventaire_journal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_inv_journal` (`inventaire_id`,`id`);
ALTER TABLE `OVINS_inventaire_journal`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
ALTER TABLE `OVINS_inventaire_lignes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_inv_numero` (`inventaire_id`,`numero`),
  ADD UNIQUE KEY `uq_inv_animal` (`inventaire_id`,`animal_id`),
  ADD UNIQUE KEY `uq_inv_extra_request` (`request_key`),
  ADD KEY `ix_inv_pointage` (`inventaire_id`,`pointage`);
ALTER TABLE `OVINS_inventaire_lignes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;
ALTER TABLE `OVINS_lots`
  ADD PRIMARY KEY (`id_lot`),
  ADD KEY `id_site` (`id_site`);
ALTER TABLE `OVINS_lots`
  MODIFY `id_lot` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;
ALTER TABLE `OVINS_lot_cycles`
  ADD PRIMARY KEY (`id_cycle`),
  ADD KEY `idx_lot_cycles_lot` (`id_lot`);
ALTER TABLE `OVINS_lot_cycles`
  MODIFY `id_cycle` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;
ALTER TABLE `OVINS_lot_soins_actions`
  ADD PRIMARY KEY (`id_action`),
  ADD KEY `idx_actions_plan` (`id_soin_plan`),
  ADD KEY `idx_actions_lot` (`id_lot`);
ALTER TABLE `OVINS_lot_soins_actions`
  MODIFY `id_action` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=199;
ALTER TABLE `OVINS_lot_soins_planifies`
  ADD PRIMARY KEY (`id_soin_plan`),
  ADD KEY `idx_soins_plan_lot` (`id_lot`),
  ADD KEY `idx_soins_plan_cycle` (`id_cycle`);
ALTER TABLE `OVINS_lot_soins_planifies`
  MODIFY `id_soin_plan` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;
ALTER TABLE `OVINS_modeles_soins`
  ADD PRIMARY KEY (`id_modele`),
  ADD UNIQUE KEY `uk_modele_categorie_actif` (`categorie`,`actif`),
  ADD KEY `idx_ovins_modele_cat_cible` (`categorie`,`cible`,`actif`);
ALTER TABLE `OVINS_modeles_soins`
  MODIFY `id_modele` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
ALTER TABLE `OVINS_modeles_soins_lignes`
  ADD PRIMARY KEY (`id_ligne`),
  ADD KEY `idx_ovins_modele_lignes_modele` (`id_modele`,`ordre`,`jour_offset`),
  ADD KEY `idx_modele_ordre` (`id_modele`,`ordre`,`jour_offset`);
ALTER TABLE `OVINS_modeles_soins_lignes`
  MODIFY `id_ligne` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;
ALTER TABLE `OVINS_mouvements`
  ADD PRIMARY KEY (`id_mvt`),
  ADD KEY `id_lot` (`id_lot`),
  ADD KEY `id_animal` (`id_animal`),
  ADD KEY `id_site_source` (`id_site_source`),
  ADD KEY `id_site_destination` (`id_site_destination`);
ALTER TABLE `OVINS_mouvements`
  MODIFY `id_mvt` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=304;
ALTER TABLE `OVINS_proprietaires`
  ADD PRIMARY KEY (`id_proprietaire`);
ALTER TABLE `OVINS_proprietaires`
  MODIFY `id_proprietaire` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
ALTER TABLE `OVINS_repro`
  ADD PRIMARY KEY (`id_repro`),
  ADD KEY `id_mere` (`id_mere`),
  ADD KEY `id_pere` (`id_pere`);
ALTER TABLE `OVINS_repro`
  MODIFY `id_repro` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;
ALTER TABLE `OVINS_sites`
  ADD PRIMARY KEY (`id_site`),
  ADD UNIQUE KEY `code_site` (`code_site`);
ALTER TABLE `OVINS_sites`
  MODIFY `id_site` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
ALTER TABLE `OVINS_soins_realises`
  ADD PRIMARY KEY (`id_soin`),
  ADD KEY `idx_lot` (`id_lot`),
  ADD KEY `idx_animal` (`id_animal`),
  ADD KEY `idx_site` (`id_site`),
  ADD KEY `fk_ovins_soins_type` (`id_type_soin`),
  ADD KEY `idx_soins_date_statut` (`date_soin`,`statut`),
  ADD KEY `idx_soins_site_date` (`id_site`,`date_soin`),
  ADD KEY `idx_soins_lot_date` (`id_lot`,`date_soin`),
  ADD KEY `idx_soins_animal_date` (`id_animal`,`date_soin`),
  ADD KEY `idx_soins_action` (`id_action`);
ALTER TABLE `OVINS_soins_realises`
  MODIFY `id_soin` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=135;
ALTER TABLE `OVINS_soins_types`
  ADD PRIMARY KEY (`id_type_soin`);
ALTER TABLE `OVINS_soins_types`
  MODIFY `id_type_soin` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;
ALTER TABLE `OVINS_sorties`
  ADD PRIMARY KEY (`id_sortie`),
  ADD KEY `idx_lot` (`id_lot`),
  ADD KEY `idx_site` (`id_site`),
  ADD KEY `idx_type_date` (`type_sortie`,`date_sortie`),
  ADD KEY `fk_ovins_sorties_animal` (`id_animal`);
ALTER TABLE `OVINS_sorties`
  MODIFY `id_sortie` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;
ALTER TABLE `paiements_commandes`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `paiements_commandes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
ALTER TABLE `permissions_modules`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `permissions_modules`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7337;
ALTER TABLE `prix_poussins_engraisses`
  ADD PRIMARY KEY (`espece`);
ALTER TABLE `prophylaxie`
  ADD PRIMARY KEY (`id_prophylaxie`);
ALTER TABLE `prophylaxie`
  MODIFY `id_prophylaxie` int UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `relances_commandes`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `relances_commandes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;
ALTER TABLE `resa_lots_previsionnels`
  ADD PRIMARY KEY (`id_lot`);
ALTER TABLE `resa_reservations`
  ADD PRIMARY KEY (`id_reservation`);
ALTER TABLE `resa_reservations`
  MODIFY `id_reservation` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;
ALTER TABLE `rh_absences`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rh_absences_emp` (`employe_id`),
  ADD KEY `idx_rh_absences_dates` (`date_debut`,`date_fin`),
  ADD KEY `idx_rh_absences_statut` (`statut`);
ALTER TABLE `rh_absences`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;
ALTER TABLE `rh_conges_droits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_rh_conges_droits` (`employe_id`,`annee`),
  ADD KEY `idx_rh_conges_droits_annee` (`annee`);
ALTER TABLE `rh_conges_droits`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;
ALTER TABLE `rh_conges_mouvements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_rh_mvt_ref_type` (`reference_table`,`reference_id`,`type_mvt`),
  ADD KEY `idx_rh_mvt_emp_annee` (`employe_id`,`annee`),
  ADD KEY `idx_rh_mvt_type` (`type_mvt`),
  ADD KEY `idx_rh_mvt_mois_app` (`mois_application`);
ALTER TABLE `rh_conges_mouvements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;
ALTER TABLE `rh_employes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_rh_employes_matricule` (`matricule`),
  ADD KEY `idx_rh_employes_site` (`site_id`),
  ADD KEY `idx_rh_employes_actif` (`actif`);
ALTER TABLE `rh_employes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
ALTER TABLE `rh_jours_imposes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_rh_jours_imposes` (`annee`,`date_jour`),
  ADD KEY `idx_rh_jours_imposes_annee` (`annee`),
  ADD KEY `idx_rh_jours_imposes_date` (`date_jour`);
ALTER TABLE `rh_jours_imposes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
ALTER TABLE `stock_oeufs_ramasses`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `stock_oeufs_ramasses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=717;
ALTER TABLE `stock_poussins_jour`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `stock_poussins_jour`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=92;
ALTER TABLE `suivi_ponte`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_bande` (`id_bande`);
ALTER TABLE `suivi_ponte`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=812;
ALTER TABLE `utilisateurs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);
ALTER TABLE `utilisateurs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;
ALTER TABLE `lots_incubation`
  ADD CONSTRAINT `lots_incubation_ibfk_1` FOREIGN KEY (`id_bande`) REFERENCES `bandes_parentales` (`id`);
ALTER TABLE `modAct_equipe_membres`
  ADD CONSTRAINT `fk_membres_equipe` FOREIGN KEY (`id_equipe`) REFERENCES `modAct_equipes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_membres_user` FOREIGN KEY (`id_user`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;
ALTER TABLE `modAct_journal_v112`
  ADD CONSTRAINT `fk_act112_journal_tache` FOREIGN KEY (`id_tache`) REFERENCES `modAct_taches` (`id`);
ALTER TABLE `modAct_occupations`
  ADD CONSTRAINT `fk_occ_cree` FOREIGN KEY (`cree_par`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_occ_user` FOREIGN KEY (`id_user`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;
ALTER TABLE `modAct_occurrences_origine`
  ADD CONSTRAINT `fk_act112_origine_tache` FOREIGN KEY (`id_tache`) REFERENCES `modAct_taches` (`id`);
ALTER TABLE `modAct_projets`
  ADD CONSTRAINT `fk_proj_cree` FOREIGN KEY (`cree_par`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_proj_eq` FOREIGN KEY (`id_equipe`) REFERENCES `modAct_equipes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_proj_resp` FOREIGN KEY (`id_responsable`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL;
ALTER TABLE `modAct_routines`
  ADD CONSTRAINT `fk_rout_cree` FOREIGN KEY (`cree_par`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_rout_eq` FOREIGN KEY (`id_equipe`) REFERENCES `modAct_equipes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_rout_resp` FOREIGN KEY (`id_responsable`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL;
ALTER TABLE `modAct_routine_taches`
  ADD CONSTRAINT `fk_rt_cat` FOREIGN KEY (`id_categorie`) REFERENCES `modAct_categories` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_rt_routine` FOREIGN KEY (`id_routine`) REFERENCES `modAct_routines` (`id`) ON DELETE CASCADE;
ALTER TABLE `modAct_taches`
  ADD CONSTRAINT `fk_tache_cat` FOREIGN KEY (`id_categorie`) REFERENCES `modAct_categories` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_tache_cree` FOREIGN KEY (`cree_par`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tache_projet` FOREIGN KEY (`id_projet`) REFERENCES `modAct_projets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tache_routetache` FOREIGN KEY (`id_routine_tache`) REFERENCES `modAct_routine_taches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tache_routine` FOREIGN KEY (`id_routine`) REFERENCES `modAct_routines` (`id`) ON DELETE CASCADE;
ALTER TABLE `modAct_tache_affectations`
  ADD CONSTRAINT `fk_aff_eq` FOREIGN KEY (`id_equipe`) REFERENCES `modAct_equipes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_aff_tache` FOREIGN KEY (`id_tache`) REFERENCES `modAct_taches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_aff_user` FOREIGN KEY (`id_user`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;
ALTER TABLE `modAct_tache_pointages`
  ADD CONSTRAINT `fk_pt_tache` FOREIGN KEY (`id_tache`) REFERENCES `modAct_taches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pt_user` FOREIGN KEY (`id_user`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;
ALTER TABLE `modAlim_distributions`
  ADD CONSTRAINT `modAlim_distributions_ibfk_1` FOREIGN KEY (`id_type_aliment`) REFERENCES `modAlim_types` (`id`);
ALTER TABLE `modAlim_plan_lignes`
  ADD CONSTRAINT `modAlim_plan_lignes_ibfk_1` FOREIGN KEY (`id_plan`) REFERENCES `modAlim_plans` (`id`),
  ADD CONSTRAINT `modAlim_plan_lignes_ibfk_2` FOREIGN KEY (`id_aliment`) REFERENCES `modAlim_types` (`id`);
ALTER TABLE `modAlim_stocks`
  ADD CONSTRAINT `modAlim_stocks_ibfk_1` FOREIGN KEY (`id_type`) REFERENCES `modAlim_types` (`id`);
ALTER TABLE `modInfra_emplacements`
  ADD CONSTRAINT `infra_bat_fk` FOREIGN KEY (`id_batiment`) REFERENCES `batiments_engraissement` (`id`),
  ADD CONSTRAINT `infra_site_fk` FOREIGN KEY (`id_site`) REFERENCES `OVINS_sites` (`id_site`);
ALTER TABLE `modInfra_positions`
  ADD CONSTRAINT `infra_position_fk` FOREIGN KEY (`id_batiment`) REFERENCES `modInfra_emplacements` (`id_batiment`);
ALTER TABLE `modLotVol_mouvements`
  ADD CONSTRAINT `modLotVol_mouvements_ibfk_1` FOREIGN KEY (`id_lot`) REFERENCES `modLotVol_lots_volailles` (`id`) ON DELETE CASCADE;
ALTER TABLE `modLotVol_suivi_poids`
  ADD CONSTRAINT `modLotVol_suivi_poids_ibfk_1` FOREIGN KEY (`id_lot`) REFERENCES `modLotVol_lots_volailles` (`id`) ON DELETE CASCADE;
ALTER TABLE `modMkt_campagnes`
  ADD CONSTRAINT `modMkt_campagnes_ibfk_1` FOREIGN KEY (`id_template`) REFERENCES `modMkt_templates` (`id`);
ALTER TABLE `modMkt_logs`
  ADD CONSTRAINT `modMkt_logs_ibfk_1` FOREIGN KEY (`id_campagne`) REFERENCES `modMkt_campagnes` (`id`);
ALTER TABLE `modRepro_mouvements`
  ADD CONSTRAINT `modRepro_mouvements_ibfk_1` FOREIGN KEY (`id_bande`) REFERENCES `bandes_parentales` (`id`);
ALTER TABLE `modVente_oeufs_affectations`
  ADD CONSTRAINT `fk_sf18_lot` FOREIGN KEY (`id_lot_oeufs`) REFERENCES `lots_oeufs_ramasses` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `fk_sf18_vente` FOREIGN KEY (`id_vente`) REFERENCES `modVente_Ventes` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `modVente_ovins_liaisons`
  ADD CONSTRAINT `fk_sf111_animal` FOREIGN KEY (`id_animal`) REFERENCES `OVINS_animaux` (`id_animal`),
  ADD CONSTRAINT `fk_sf111_sortie` FOREIGN KEY (`id_sortie`) REFERENCES `OVINS_sorties` (`id_sortie`),
  ADD CONSTRAINT `fk_sf111_vente` FOREIGN KEY (`id_vente`) REFERENCES `modVente_Ventes` (`id`);
ALTER TABLE `OVINS_animaux`
  ADD CONSTRAINT `OVINS_animaux_ibfk_1` FOREIGN KEY (`id_lot`) REFERENCES `OVINS_lots` (`id_lot`),
  ADD CONSTRAINT `OVINS_animaux_ibfk_2` FOREIGN KEY (`id_site`) REFERENCES `OVINS_sites` (`id_site`);
ALTER TABLE `OVINS_animaux_medias`
  ADD CONSTRAINT `fk_ovins_media_animal` FOREIGN KEY (`id_animal`) REFERENCES `OVINS_animaux` (`id_animal`) ON DELETE CASCADE;
ALTER TABLE `OVINS_inventaire_journal`
  ADD CONSTRAINT `fk_inv_journal` FOREIGN KEY (`inventaire_id`) REFERENCES `OVINS_inventaires` (`id`) ON DELETE RESTRICT;
ALTER TABLE `OVINS_inventaire_lignes`
  ADD CONSTRAINT `fk_inv_ligne` FOREIGN KEY (`inventaire_id`) REFERENCES `OVINS_inventaires` (`id`) ON DELETE RESTRICT;
ALTER TABLE `OVINS_lots`
  ADD CONSTRAINT `OVINS_lots_ibfk_1` FOREIGN KEY (`id_site`) REFERENCES `OVINS_sites` (`id_site`);
ALTER TABLE `OVINS_lot_cycles`
  ADD CONSTRAINT `fk_lot_cycles_lot` FOREIGN KEY (`id_lot`) REFERENCES `OVINS_lots` (`id_lot`);
ALTER TABLE `OVINS_lot_soins_actions`
  ADD CONSTRAINT `fk_actions_soin_plan` FOREIGN KEY (`id_soin_plan`) REFERENCES `OVINS_lot_soins_planifies` (`id_soin_plan`);
ALTER TABLE `OVINS_lot_soins_planifies`
  ADD CONSTRAINT `fk_soins_plan_cycle` FOREIGN KEY (`id_cycle`) REFERENCES `OVINS_lot_cycles` (`id_cycle`);
ALTER TABLE `OVINS_modeles_soins_lignes`
  ADD CONSTRAINT `fk_ovins_modele_lignes_modele` FOREIGN KEY (`id_modele`) REFERENCES `OVINS_modeles_soins` (`id_modele`) ON DELETE CASCADE;
ALTER TABLE `OVINS_mouvements`
  ADD CONSTRAINT `OVINS_mouvements_ibfk_1` FOREIGN KEY (`id_lot`) REFERENCES `OVINS_lots` (`id_lot`),
  ADD CONSTRAINT `OVINS_mouvements_ibfk_2` FOREIGN KEY (`id_animal`) REFERENCES `OVINS_animaux` (`id_animal`),
  ADD CONSTRAINT `OVINS_mouvements_ibfk_3` FOREIGN KEY (`id_site_source`) REFERENCES `OVINS_sites` (`id_site`),
  ADD CONSTRAINT `OVINS_mouvements_ibfk_4` FOREIGN KEY (`id_site_destination`) REFERENCES `OVINS_sites` (`id_site`);
ALTER TABLE `OVINS_repro`
  ADD CONSTRAINT `OVINS_repro_ibfk_1` FOREIGN KEY (`id_mere`) REFERENCES `OVINS_animaux` (`id_animal`),
  ADD CONSTRAINT `OVINS_repro_ibfk_2` FOREIGN KEY (`id_pere`) REFERENCES `OVINS_animaux` (`id_animal`);
ALTER TABLE `OVINS_soins_realises`
  ADD CONSTRAINT `fk_ovins_soins_animal` FOREIGN KEY (`id_animal`) REFERENCES `OVINS_animaux` (`id_animal`),
  ADD CONSTRAINT `fk_ovins_soins_lot` FOREIGN KEY (`id_lot`) REFERENCES `OVINS_lots` (`id_lot`),
  ADD CONSTRAINT `fk_ovins_soins_site` FOREIGN KEY (`id_site`) REFERENCES `OVINS_sites` (`id_site`),
  ADD CONSTRAINT `fk_ovins_soins_type` FOREIGN KEY (`id_type_soin`) REFERENCES `OVINS_soins_types` (`id_type_soin`);
ALTER TABLE `OVINS_sorties`
  ADD CONSTRAINT `fk_ovins_sorties_animal` FOREIGN KEY (`id_animal`) REFERENCES `OVINS_animaux` (`id_animal`),
  ADD CONSTRAINT `fk_ovins_sorties_lot` FOREIGN KEY (`id_lot`) REFERENCES `OVINS_lots` (`id_lot`),
  ADD CONSTRAINT `fk_ovins_sorties_site` FOREIGN KEY (`id_site`) REFERENCES `OVINS_sites` (`id_site`);
ALTER TABLE `rh_absences`
  ADD CONSTRAINT `fk_rh_absences_emp` FOREIGN KEY (`employe_id`) REFERENCES `rh_employes` (`id`) ON DELETE CASCADE;
ALTER TABLE `rh_conges_droits`
  ADD CONSTRAINT `fk_rh_conges_droits_emp` FOREIGN KEY (`employe_id`) REFERENCES `rh_employes` (`id`) ON DELETE CASCADE;
ALTER TABLE `rh_conges_mouvements`
  ADD CONSTRAINT `fk_rh_mvt_emp` FOREIGN KEY (`employe_id`) REFERENCES `rh_employes` (`id`) ON DELETE CASCADE;
ALTER TABLE `suivi_ponte`
  ADD CONSTRAINT `suivi_ponte_ibfk_1` FOREIGN KEY (`id_bande`) REFERENCES `bandes_parentales` (`id`);

SET FOREIGN_KEY_CHECKS=1;
DELIMITER $$
CREATE TRIGGER `sf117_del_id_rep` BEFORE DELETE ON `bandes_parentales` FOR EACH ROW BEGIN
DECLARE v_n INT;
UPDATE modInfra_verrou SET revision=revision+1 WHERE id=1;
SELECT COUNT(*) INTO v_n FROM modInfra_positions WHERE famille IN ('repro_m','repro_f') AND id_source=OLD.id FOR UPDATE;
IF v_n>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI lieux: liberer les emplacements avant suppression'; END IF;
END$$
CREATE TRIGGER `sf117_repro_bu` BEFORE UPDATE ON `bandes_parentales` FOR EACH ROW BEGIN
DECLARE v_old BIGINT; DECLARE v_new BIGINT; DECLARE v_delta BIGINT; DECLARE v_sum BIGINT; DECLARE v_n INT; DECLARE v_bat INT; DECLARE v_q INT;
UPDATE modInfra_verrou SET revision=revision+1 WHERE id=1;
SELECT COUNT(*) INTO v_n FROM modInfra_positions WHERE famille IN ('repro_m','repro_f') AND id_source=OLD.id FOR UPDATE;
IF v_n>0 AND (NEW.id<>OLD.id OR NOT(BINARY NEW.espece<=>BINARY OLD.espece) OR NOT(NEW.reformee<=>0)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI lieux: liberer les emplacements avant reforme ou changement espece'; END IF;

 SET v_old=OLD.nb_males; SET v_new=NEW.nb_males;
 IF v_new IS NULL OR v_new<0 THEN
   SELECT COUNT(*) INTO v_n FROM modInfra_positions WHERE famille='repro_m' AND id_source=OLD.id FOR UPDATE;
   IF v_n>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI lieux: effectif invalide pour une source affectee'; END IF;
 ELSEIF v_old IS NOT NULL AND v_new<v_old THEN
   SET v_delta=v_old-v_new;
   SELECT COALESCE(SUM(quantite),0),COUNT(*),MIN(id_batiment) INTO v_sum,v_n,v_bat
     FROM modInfra_positions WHERE famille='repro_m' AND id_source=OLD.id FOR UPDATE;
   IF v_sum>v_old THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI lieux: repartition superieure a effectif, diagnostic requis'; END IF;
   IF @sf117_family='repro_m' AND @sf117_entity=OLD.id AND @sf117_from IS NOT NULL THEN
     SET v_bat=@sf117_from;
   ELSE
     IF v_n+IF(v_old>v_sum,1,0)>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI lieux: choisir emplacement de sortie dans le formulaire'; END IF;
     IF v_sum=0 THEN SET v_bat=0; END IF;
   END IF;
   IF v_bat=0 THEN
     IF v_delta>v_old-v_sum THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI lieux: quantite non affectee insuffisante'; END IF;
   ELSE
     SET v_q=NULL;
     SELECT quantite INTO v_q FROM modInfra_positions WHERE famille='repro_m' AND id_source=OLD.id AND id_batiment=v_bat FOR UPDATE;
     IF v_q IS NULL OR v_q<v_delta THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI lieux: effectif insuffisant dans emplacement choisi'; END IF;
     IF v_q=v_delta THEN
       DELETE FROM modInfra_positions WHERE famille='repro_m' AND id_source=OLD.id AND id_batiment=v_bat;
     ELSE
       UPDATE modInfra_positions SET quantite=quantite-v_delta WHERE famille='repro_m' AND id_source=OLD.id AND id_batiment=v_bat;
     END IF;
   END IF;
 END IF;

 SET v_old=OLD.nb_femelles; SET v_new=NEW.nb_femelles;
 IF v_new IS NULL OR v_new<0 THEN
   SELECT COUNT(*) INTO v_n FROM modInfra_positions WHERE famille='repro_f' AND id_source=OLD.id FOR UPDATE;
   IF v_n>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI lieux: effectif invalide pour une source affectee'; END IF;
 ELSEIF v_old IS NOT NULL AND v_new<v_old THEN
   SET v_delta=v_old-v_new;
   SELECT COALESCE(SUM(quantite),0),COUNT(*),MIN(id_batiment) INTO v_sum,v_n,v_bat
     FROM modInfra_positions WHERE famille='repro_f' AND id_source=OLD.id FOR UPDATE;
   IF v_sum>v_old THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI lieux: repartition superieure a effectif, diagnostic requis'; END IF;
   IF @sf117_family='repro_f' AND @sf117_entity=OLD.id AND @sf117_from IS NOT NULL THEN
     SET v_bat=@sf117_from;
   ELSE
     IF v_n+IF(v_old>v_sum,1,0)>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI lieux: choisir emplacement de sortie dans le formulaire'; END IF;
     IF v_sum=0 THEN SET v_bat=0; END IF;
   END IF;
   IF v_bat=0 THEN
     IF v_delta>v_old-v_sum THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI lieux: quantite non affectee insuffisante'; END IF;
   ELSE
     SET v_q=NULL;
     SELECT quantite INTO v_q FROM modInfra_positions WHERE famille='repro_f' AND id_source=OLD.id AND id_batiment=v_bat FOR UPDATE;
     IF v_q IS NULL OR v_q<v_delta THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI lieux: effectif insuffisant dans emplacement choisi'; END IF;
     IF v_q=v_delta THEN
       DELETE FROM modInfra_positions WHERE famille='repro_f' AND id_source=OLD.id AND id_batiment=v_bat;
     ELSE
       UPDATE modInfra_positions SET quantite=quantite-v_delta WHERE famille='repro_f' AND id_source=OLD.id AND id_batiment=v_bat;
     END IF;
   END IF;
 END IF;
END$$
CREATE TRIGGER `sf117_bat_bu` BEFORE UPDATE ON `batiments_engraissement` FOR EACH ROW BEGIN
DECLARE v_sum BIGINT;
UPDATE modInfra_verrou SET revision=revision+1 WHERE id=1;
SELECT COALESCE(SUM(quantite),0) INTO v_sum FROM modInfra_positions WHERE id_batiment=OLD.id FOR UPDATE;
IF v_sum>0 AND (NEW.id<>OLD.id OR COALESCE(NEW.actif,0)<>1 OR NEW.capacite IS NULL OR NEW.capacite<v_sum) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI lieux: capacite ou statut incompatible avec occupation'; END IF;
END$$
CREATE TRIGGER `sf116_t6_bd` BEFORE DELETE ON `commandes_vente` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 IF v_cut IS NOT NULL AND ((OLD.date_reservation IS NULL OR OLD.date_reservation<='0000-00-00' OR OLD.date_reservation<=v_cut)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
END$$
CREATE TRIGGER `sf116_t6_bi` BEFORE INSERT ON `commandes_vente` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 IF v_cut IS NOT NULL AND ((NEW.date_reservation IS NULL OR NEW.date_reservation<='0000-00-00' OR NEW.date_reservation<=v_cut)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
END$$
CREATE TRIGGER `sf116_t6_bu` BEFORE UPDATE ON `commandes_vente` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 IF v_cut IS NOT NULL AND ((OLD.date_reservation IS NULL OR OLD.date_reservation<='0000-00-00' OR OLD.date_reservation<=v_cut) OR (NEW.date_reservation IS NULL OR NEW.date_reservation<='0000-00-00' OR NEW.date_reservation<=v_cut)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
END$$
CREATE TRIGGER `sf116_archive_ai` AFTER INSERT ON `modCompta_exercices` FOR EACH ROW BEGIN
 UPDATE modCompta_exercice_lock SET closed_through=NEW.cutoff,revision=revision+1 WHERE id=1;
END$$
CREATE TRIGGER `sf116_archive_bd` BEFORE DELETE ON `modCompta_exercices` FOR EACH ROW BEGIN
 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: archive definitive non modifiable';
END$$
CREATE TRIGGER `sf116_archive_bi` BEFORE INSERT ON `modCompta_exercices` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 IF NEW.annee<1900 OR NEW.annee>=YEAR(UTC_DATE()) OR NEW.cutoff<>STR_TO_DATE(CONCAT(NEW.annee,'-12-31'),'%Y-%m-%d') OR (v_cut IS NOT NULL AND NEW.cutoff<=v_cut) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: annee de cloture interdite'; END IF;
 IF JSON_VALID(NEW.snapshot_json)=0 OR NEW.snapshot_sha256<>SHA2(NEW.snapshot_json,256) OR NEW.pdf_sha256<>SHA2(NEW.pdf_blob,256) OR LEFT(NEW.pdf_blob,5)<>'%PDF-' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: archive incomplete'; END IF;
END$$
CREATE TRIGGER `sf116_archive_bu` BEFORE UPDATE ON `modCompta_exercices` FOR EACH ROW BEGIN
 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: archive definitive non modifiable';
END$$
CREATE TRIGGER `sf116_lock_bd` BEFORE DELETE ON `modCompta_exercice_lock` FOR EACH ROW BEGIN
 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: borne de cloture non supprimable';
END$$
CREATE TRIGGER `sf116_lock_bi` BEFORE INSERT ON `modCompta_exercice_lock` FOR EACH ROW BEGIN
 IF NEW.id<>1 OR NEW.schema_version<>116 OR NEW.closed_through IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: initialisation de borne invalide'; END IF;
END$$
CREATE TRIGGER `sf116_lock_bu` BEFORE UPDATE ON `modCompta_exercice_lock` FOR EACH ROW BEGIN
 IF NEW.id<>OLD.id OR NEW.schema_version<>OLD.schema_version OR NEW.revision<OLD.revision OR (OLD.closed_through IS NOT NULL AND (NEW.closed_through IS NULL OR NEW.closed_through<OLD.closed_through)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: borne de cloture non reversible'; END IF;
END$$
CREATE TRIGGER `sf116_t1_bd` BEFORE DELETE ON `modCompta_operations` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 IF v_cut IS NOT NULL AND ((OLD.date_op IS NULL OR OLD.date_op<='0000-00-00' OR OLD.date_op<=v_cut)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
END$$
CREATE TRIGGER `sf116_t1_bi` BEFORE INSERT ON `modCompta_operations` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 IF v_cut IS NOT NULL AND ((NEW.date_op IS NULL OR NEW.date_op<='0000-00-00' OR NEW.date_op<=v_cut)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
END$$
CREATE TRIGGER `sf116_t1_bu` BEFORE UPDATE ON `modCompta_operations` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 IF v_cut IS NOT NULL AND ((OLD.date_op IS NULL OR OLD.date_op<='0000-00-00' OR OLD.date_op<=v_cut) OR (NEW.date_op IS NULL OR NEW.date_op<='0000-00-00' OR NEW.date_op<=v_cut)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
END$$
CREATE TRIGGER `sf117_params_bu` BEFORE UPDATE ON `modInfra_emplacements` FOR EACH ROW BEGIN
DECLARE v_sum BIGINT;
UPDATE modInfra_verrou SET revision=revision+1 WHERE id=1;
SELECT COALESCE(SUM(quantite),0) INTO v_sum FROM modInfra_positions WHERE id_batiment=OLD.id_batiment FOR UPDATE;
IF v_sum>0 AND (NEW.id_batiment<>OLD.id_batiment OR NOT(NEW.id_site<=>OLD.id_site) OR NOT(BINARY NEW.famille<=>BINARY OLD.famille) OR NOT(BINARY NEW.espece<=>BINARY OLD.espece) OR NOT(BINARY NEW.stade<=>BINARY OLD.stade) OR NEW.etat<>'disponible' OR NEW.disponible_le IS NOT NULL) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI lieux: vider emplacement avant changement affectant disponibilite'; END IF;
END$$
CREATE TRIGGER `sf117_pos_ad` AFTER DELETE ON `modInfra_positions` FOR EACH ROW BEGIN
INSERT INTO modInfra_journal(acteur,action,famille,id_source,id_batiment,avant_qte,apres_qte,motif) VALUES(COALESCE(@sf117_user,CURRENT_USER()),'position_delete',OLD.famille,OLD.id_source,OLD.id_batiment,OLD.quantite,0,COALESCE(@sf117_reason,'Synchronisation avec le registre elevage'));
END$$
CREATE TRIGGER `sf117_pos_ai` AFTER INSERT ON `modInfra_positions` FOR EACH ROW BEGIN
INSERT INTO modInfra_journal(acteur,action,famille,id_source,id_batiment,avant_qte,apres_qte,motif) VALUES(COALESCE(@sf117_user,CURRENT_USER()),'position_insert',NEW.famille,NEW.id_source,NEW.id_batiment,0,NEW.quantite,COALESCE(@sf117_reason,'Synchronisation avec le registre elevage'));
END$$
CREATE TRIGGER `sf117_pos_au` AFTER UPDATE ON `modInfra_positions` FOR EACH ROW BEGIN
INSERT INTO modInfra_journal(acteur,action,famille,id_source,id_batiment,avant_qte,apres_qte,motif) VALUES(COALESCE(@sf117_user,CURRENT_USER()),'position_update',NEW.famille,NEW.id_source,NEW.id_batiment,OLD.quantite,NEW.quantite,COALESCE(@sf117_reason,'Synchronisation avec le registre elevage'));
END$$
CREATE TRIGGER `sf116_lot_bd` BEFORE DELETE ON `modLotVol_lots_volailles` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 DECLARE v_n BIGINT DEFAULT 0;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 SELECT COUNT(*) INTO v_n FROM modLotVol_mouvements WHERE id_lot=OLD.id AND type_mouvement='vente' AND date_mouvement<=v_cut FOR UPDATE;
 IF v_cut IS NOT NULL AND (v_n>0) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
END$$
CREATE TRIGGER `sf116_lot_bu` BEFORE UPDATE ON `modLotVol_lots_volailles` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 DECLARE v_n BIGINT DEFAULT 0;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 IF NEW.id<>OLD.id THEN
 SELECT COUNT(*) INTO v_n FROM modLotVol_mouvements WHERE id_lot=OLD.id AND type_mouvement='vente' AND date_mouvement<=v_cut FOR UPDATE;
 IF v_cut IS NOT NULL AND (v_n>0) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
 END IF;
END$$
CREATE TRIGGER `sf117_del_id_vol` BEFORE DELETE ON `modLotVol_lots_volailles` FOR EACH ROW BEGIN
DECLARE v_n INT;
UPDATE modInfra_verrou SET revision=revision+1 WHERE id=1;
SELECT COUNT(*) INTO v_n FROM modInfra_positions WHERE famille IN ('volaille') AND id_source=OLD.id FOR UPDATE;
IF v_n>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI lieux: liberer les emplacements avant suppression'; END IF;
END$$
CREATE TRIGGER `sf117_vol_bu` BEFORE UPDATE ON `modLotVol_lots_volailles` FOR EACH ROW BEGIN
DECLARE v_old BIGINT; DECLARE v_new BIGINT; DECLARE v_delta BIGINT; DECLARE v_sum BIGINT; DECLARE v_n INT; DECLARE v_bat INT; DECLARE v_q INT;
UPDATE modInfra_verrou SET revision=revision+1 WHERE id=1;
SELECT COUNT(*) INTO v_n FROM modInfra_positions WHERE famille='volaille' AND id_source=OLD.id FOR UPDATE;
IF v_n>0 AND (NEW.id<>OLD.id OR NOT(BINARY NEW.espece<=>BINARY OLD.espece) OR NOT(NEW.statut<=>'actif')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI lieux: liberer les emplacements avant archivage ou changement espece'; END IF;

 SET v_old=OLD.effectif_en_cours; SET v_new=NEW.effectif_en_cours;
 IF v_new IS NULL OR v_new<0 THEN
   SELECT COUNT(*) INTO v_n FROM modInfra_positions WHERE famille='volaille' AND id_source=OLD.id FOR UPDATE;
   IF v_n>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI lieux: effectif invalide pour une source affectee'; END IF;
 ELSEIF v_old IS NOT NULL AND v_new<v_old THEN
   SET v_delta=v_old-v_new;
   SELECT COALESCE(SUM(quantite),0),COUNT(*),MIN(id_batiment) INTO v_sum,v_n,v_bat
     FROM modInfra_positions WHERE famille='volaille' AND id_source=OLD.id FOR UPDATE;
   IF v_sum>v_old THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI lieux: repartition superieure a effectif, diagnostic requis'; END IF;
   IF @sf117_family='volaille' AND @sf117_entity=OLD.id AND @sf117_from IS NOT NULL THEN
     SET v_bat=@sf117_from;
   ELSE
     IF v_n+IF(v_old>v_sum,1,0)>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI lieux: choisir emplacement de sortie dans le formulaire'; END IF;
     IF v_sum=0 THEN SET v_bat=0; END IF;
   END IF;
   IF v_bat=0 THEN
     IF v_delta>v_old-v_sum THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI lieux: quantite non affectee insuffisante'; END IF;
   ELSE
     SET v_q=NULL;
     SELECT quantite INTO v_q FROM modInfra_positions WHERE famille='volaille' AND id_source=OLD.id AND id_batiment=v_bat FOR UPDATE;
     IF v_q IS NULL OR v_q<v_delta THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI lieux: effectif insuffisant dans emplacement choisi'; END IF;
     IF v_q=v_delta THEN
       DELETE FROM modInfra_positions WHERE famille='volaille' AND id_source=OLD.id AND id_batiment=v_bat;
     ELSE
       UPDATE modInfra_positions SET quantite=quantite-v_delta WHERE famille='volaille' AND id_source=OLD.id AND id_batiment=v_bat;
     END IF;
   END IF;
 END IF;
END$$
CREATE TRIGGER `sf116_t4_bd` BEFORE DELETE ON `modLotVol_mouvements` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 IF v_cut IS NOT NULL AND ((LOWER(TRIM(OLD.type_mouvement))='vente' AND (OLD.date_mouvement IS NULL OR OLD.date_mouvement<='0000-00-00' OR OLD.date_mouvement<=v_cut))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
END$$
CREATE TRIGGER `sf116_t4_bi` BEFORE INSERT ON `modLotVol_mouvements` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 IF v_cut IS NOT NULL AND ((LOWER(TRIM(NEW.type_mouvement))='vente' AND (NEW.date_mouvement IS NULL OR NEW.date_mouvement<='0000-00-00' OR NEW.date_mouvement<=v_cut))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
END$$
CREATE TRIGGER `sf116_t4_bu` BEFORE UPDATE ON `modLotVol_mouvements` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 IF v_cut IS NOT NULL AND ((LOWER(TRIM(OLD.type_mouvement))='vente' AND (OLD.date_mouvement IS NULL OR OLD.date_mouvement<='0000-00-00' OR OLD.date_mouvement<=v_cut)) OR (LOWER(TRIM(NEW.type_mouvement))='vente' AND (NEW.date_mouvement IS NULL OR NEW.date_mouvement<='0000-00-00' OR NEW.date_mouvement<=v_cut))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
END$$
CREATE TRIGGER `sf116_eggs_bd` BEFORE DELETE ON `modVente_oeufs_affectations` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 DECLARE v_date DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 SET v_date=NULL;
 SELECT date_vente INTO v_date FROM modVente_Ventes WHERE id=OLD.id_vente FOR UPDATE;
 IF v_cut IS NOT NULL AND (v_date IS NULL OR v_date<=v_cut) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
END$$
CREATE TRIGGER `sf116_eggs_bi` BEFORE INSERT ON `modVente_oeufs_affectations` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 DECLARE v_date DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 SET v_date=NULL;
 SELECT date_vente INTO v_date FROM modVente_Ventes WHERE id=NEW.id_vente FOR UPDATE;
 IF v_cut IS NOT NULL AND (v_date IS NULL OR v_date<=v_cut) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
END$$
CREATE TRIGGER `sf116_eggs_bu` BEFORE UPDATE ON `modVente_oeufs_affectations` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 DECLARE v_date DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 SET v_date=NULL;
 SELECT date_vente INTO v_date FROM modVente_Ventes WHERE id=OLD.id_vente FOR UPDATE;
 IF v_cut IS NOT NULL AND (v_date IS NULL OR v_date<=v_cut) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
 SET v_date=NULL;
 SELECT date_vente INTO v_date FROM modVente_Ventes WHERE id=NEW.id_vente FOR UPDATE;
 IF v_cut IS NOT NULL AND (v_date IS NULL OR v_date<=v_cut) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
END$$
CREATE TRIGGER `sf116_ovlinks_bd` BEFORE DELETE ON `modVente_ovins_liaisons` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 DECLARE v_date DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 SET v_date=NULL;
 SELECT date_vente INTO v_date FROM modVente_Ventes WHERE id=OLD.id_vente FOR UPDATE;
 IF v_cut IS NOT NULL AND (v_date IS NULL OR v_date<=v_cut) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
 SET v_date=NULL;
 SELECT date_sortie INTO v_date FROM OVINS_sorties WHERE id_sortie=OLD.id_sortie FOR UPDATE;
 IF v_cut IS NOT NULL AND (v_date IS NULL OR v_date<=v_cut) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: liaison de sortie cloturee refusee'; END IF;
END$$
CREATE TRIGGER `sf116_ovlinks_bi` BEFORE INSERT ON `modVente_ovins_liaisons` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 DECLARE v_date DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 SET v_date=NULL;
 SELECT date_vente INTO v_date FROM modVente_Ventes WHERE id=NEW.id_vente FOR UPDATE;
 IF v_cut IS NOT NULL AND (v_date IS NULL OR v_date<=v_cut) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
 SET v_date=NULL;
 SELECT date_sortie INTO v_date FROM OVINS_sorties WHERE id_sortie=NEW.id_sortie FOR UPDATE;
 IF v_cut IS NOT NULL AND (v_date IS NULL OR v_date<=v_cut) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: liaison de sortie cloturee refusee'; END IF;
END$$
CREATE TRIGGER `sf116_ovlinks_bu` BEFORE UPDATE ON `modVente_ovins_liaisons` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 DECLARE v_date DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 SET v_date=NULL;
 SELECT date_vente INTO v_date FROM modVente_Ventes WHERE id=OLD.id_vente FOR UPDATE;
 IF v_cut IS NOT NULL AND (v_date IS NULL OR v_date<=v_cut) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
 SET v_date=NULL;
 SELECT date_vente INTO v_date FROM modVente_Ventes WHERE id=NEW.id_vente FOR UPDATE;
 IF v_cut IS NOT NULL AND (v_date IS NULL OR v_date<=v_cut) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
 SET v_date=NULL;
 SELECT date_sortie INTO v_date FROM OVINS_sorties WHERE id_sortie=OLD.id_sortie FOR UPDATE;
 IF v_cut IS NOT NULL AND (v_date IS NULL OR v_date<=v_cut) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: liaison de sortie cloturee refusee'; END IF;
 SET v_date=NULL;
 SELECT date_sortie INTO v_date FROM OVINS_sorties WHERE id_sortie=NEW.id_sortie FOR UPDATE;
 IF v_cut IS NOT NULL AND (v_date IS NULL OR v_date<=v_cut) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: liaison de sortie cloturee refusee'; END IF;
END$$
CREATE TRIGGER `sf116_t0_bd` BEFORE DELETE ON `modVente_Ventes` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 IF v_cut IS NOT NULL AND ((OLD.date_vente IS NULL OR OLD.date_vente<='0000-00-00' OR OLD.date_vente<=v_cut)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
END$$
CREATE TRIGGER `sf116_t0_bi` BEFORE INSERT ON `modVente_Ventes` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 IF v_cut IS NOT NULL AND ((NEW.date_vente IS NULL OR NEW.date_vente<='0000-00-00' OR NEW.date_vente<=v_cut)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
END$$
CREATE TRIGGER `sf116_t0_bu` BEFORE UPDATE ON `modVente_Ventes` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 DECLARE v_paid DECIMAL(24,2);
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 IF v_cut IS NOT NULL THEN
 IF NEW.date_vente IS NULL OR NEW.date_vente<='0000-00-00' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: date de vente invalide'; END IF;
 IF OLD.date_vente<=v_cut OR OLD.date_vente IS NULL OR OLD.date_vente<='0000-00-00' THEN
 IF NOT (BINARY NEW.`id` <=> BINARY OLD.`id`) OR NOT (BINARY NEW.`date_vente` <=> BINARY OLD.`date_vente`) OR NOT (BINARY NEW.`client_nom` <=> BINARY OLD.`client_nom`) OR NOT (BINARY NEW.`client_tel` <=> BINARY OLD.`client_tel`) OR NOT (BINARY NEW.`espece` <=> BINARY OLD.`espece`) OR NOT (BINARY NEW.`quantite` <=> BINARY OLD.`quantite`) OR NOT (BINARY NEW.`prix_unitaire` <=> BINARY OLD.`prix_unitaire`) OR NOT (BINARY NEW.`montant_total` <=> BINARY OLD.`montant_total`) OR NOT (BINARY NEW.`commentaire` <=> BINARY OLD.`commentaire`) OR NOT (BINARY NEW.`utilisateur` <=> BINARY OLD.`utilisateur`) OR NOT (BINARY NEW.`id_lot` <=> BINARY OLD.`id_lot`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: vente cloturee, informations figees'; END IF;
 SELECT COALESCE(SUM(CASE WHEN type_op='recette' THEN montant ELSE -montant END),0) INTO v_paid FROM modCompta_operations WHERE LOWER(TRIM(source_table))='modvente_ventes' AND source_id=OLD.id AND est_valide=1 FOR UPDATE;
 IF NEW.montant_reste_encaisser IS NULL OR NEW.montant_reste_encaisser<>OLD.montant_total-v_paid OR NEW.montant_reste_encaisser<0 OR NEW.montant_reste_encaisser>OLD.montant_total OR NEW.statut<>IF(NEW.montant_reste_encaisser=0,'Encaissé','A encaisser') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: solde courant incoherent'; END IF;
 ELSEIF NEW.date_vente<=v_cut THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: deplacement vers periode cloturee refuse'; END IF;
 END IF;
END$$
CREATE TRIGGER `sf116_t2_bd` BEFORE DELETE ON `modVente_Ventes_A_Completer` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 IF v_cut IS NOT NULL AND ((OLD.date_vente IS NULL OR OLD.date_vente<='0000-00-00' OR OLD.date_vente<=v_cut)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
END$$
CREATE TRIGGER `sf116_t2_bi` BEFORE INSERT ON `modVente_Ventes_A_Completer` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 IF v_cut IS NOT NULL AND ((NEW.date_vente IS NULL OR NEW.date_vente<='0000-00-00' OR NEW.date_vente<=v_cut)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
END$$
CREATE TRIGGER `sf116_t2_bu` BEFORE UPDATE ON `modVente_Ventes_A_Completer` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 IF v_cut IS NOT NULL AND ((OLD.date_vente IS NULL OR OLD.date_vente<='0000-00-00' OR OLD.date_vente<=v_cut) OR (NEW.date_vente IS NULL OR NEW.date_vente<='0000-00-00' OR NEW.date_vente<=v_cut)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
END$$
CREATE TRIGGER `sf117_del_id_animal_ovi` BEFORE DELETE ON `OVINS_animaux` FOR EACH ROW BEGIN
DECLARE v_n INT;
UPDATE modInfra_verrou SET revision=revision+1 WHERE id=1;
SELECT COUNT(*) INTO v_n FROM modInfra_positions WHERE famille IN ('ovin') AND id_source=OLD.id_animal FOR UPDATE;
IF v_n>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI lieux: liberer les emplacements avant suppression'; END IF;
END$$
CREATE TRIGGER `sf117_ovin_bu` BEFORE UPDATE ON `OVINS_animaux` FOR EACH ROW BEGIN
UPDATE modInfra_verrou SET revision=revision+1 WHERE id=1;
IF NEW.id_animal<>OLD.id_animal THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI lieux: identifiant animal non modifiable'; END IF;
IF NOT(NEW.id_site<=>OLD.id_site) OR NOT(NEW.statut<=>'en_ferme') OR NEW.date_sortie IS NOT NULL THEN
DELETE FROM modInfra_positions WHERE famille='ovin' AND id_source=OLD.id_animal;
END IF;
END$$
CREATE TRIGGER `sf116_t5_bd` BEFORE DELETE ON `OVINS_mouvements` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 IF v_cut IS NOT NULL AND ((LOWER(TRIM(OLD.type_mvt))='vente' AND (OLD.date_mvt IS NULL OR OLD.date_mvt<='0000-00-00' OR OLD.date_mvt<=v_cut))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
END$$
CREATE TRIGGER `sf116_t5_bi` BEFORE INSERT ON `OVINS_mouvements` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 IF v_cut IS NOT NULL AND ((LOWER(TRIM(NEW.type_mvt))='vente' AND (NEW.date_mvt IS NULL OR NEW.date_mvt<='0000-00-00' OR NEW.date_mvt<=v_cut))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
END$$
CREATE TRIGGER `sf116_t5_bu` BEFORE UPDATE ON `OVINS_mouvements` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 IF v_cut IS NOT NULL AND ((LOWER(TRIM(OLD.type_mvt))='vente' AND (OLD.date_mvt IS NULL OR OLD.date_mvt<='0000-00-00' OR OLD.date_mvt<=v_cut)) OR (LOWER(TRIM(NEW.type_mvt))='vente' AND (NEW.date_mvt IS NULL OR NEW.date_mvt<='0000-00-00' OR NEW.date_mvt<=v_cut))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
END$$
CREATE TRIGGER `sf116_t3_bd` BEFORE DELETE ON `OVINS_sorties` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 IF v_cut IS NOT NULL AND ((LOWER(TRIM(OLD.type_sortie))='vente' AND (OLD.date_sortie IS NULL OR OLD.date_sortie<='0000-00-00' OR OLD.date_sortie<=v_cut))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
END$$
CREATE TRIGGER `sf116_t3_bi` BEFORE INSERT ON `OVINS_sorties` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 IF v_cut IS NOT NULL AND ((LOWER(TRIM(NEW.type_sortie))='vente' AND (NEW.date_sortie IS NULL OR NEW.date_sortie<='0000-00-00' OR NEW.date_sortie<=v_cut))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
END$$
CREATE TRIGGER `sf116_t3_bu` BEFORE UPDATE ON `OVINS_sorties` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 IF v_cut IS NOT NULL AND ((LOWER(TRIM(OLD.type_sortie))='vente' AND (OLD.date_sortie IS NULL OR OLD.date_sortie<='0000-00-00' OR OLD.date_sortie<=v_cut)) OR (LOWER(TRIM(NEW.type_sortie))='vente' AND (NEW.date_sortie IS NULL OR NEW.date_sortie<='0000-00-00' OR NEW.date_sortie<=v_cut))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
END$$
CREATE TRIGGER `sf116_t7_bd` BEFORE DELETE ON `paiements_commandes` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 IF v_cut IS NOT NULL AND ((OLD.date_paiement IS NULL OR OLD.date_paiement<='0000-00-00' OR OLD.date_paiement<=v_cut)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
END$$
CREATE TRIGGER `sf116_t7_bi` BEFORE INSERT ON `paiements_commandes` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 IF v_cut IS NOT NULL AND ((NEW.date_paiement IS NULL OR NEW.date_paiement<='0000-00-00' OR NEW.date_paiement<=v_cut)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
END$$
CREATE TRIGGER `sf116_t7_bu` BEFORE UPDATE ON `paiements_commandes` FOR EACH ROW BEGIN
 DECLARE v_cut DATE;
 UPDATE modCompta_exercice_lock SET revision=revision+1 WHERE id=1;
 SELECT closed_through INTO v_cut FROM modCompta_exercice_lock WHERE id=1 FOR UPDATE;
 IF v_cut IS NOT NULL AND ((OLD.date_paiement IS NULL OR OLD.date_paiement<='0000-00-00' OR OLD.date_paiement<=v_cut) OR (NEW.date_paiement IS NULL OR NEW.date_paiement<='0000-00-00' OR NEW.date_paiement<=v_cut)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SOMADI: periode cloturee, ecriture refusee'; END IF;
END$$
DELIMITER ;
