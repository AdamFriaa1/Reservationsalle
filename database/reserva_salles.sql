-- =====================================================================
--  ReservaSalles — base de données complète (schéma + données de démo)
-- ---------------------------------------------------------------------
--  Import :  phpMyAdmin → Importer → choisir ce fichier.
--  Le script commence par DROP DATABASE : il peut être réimporté à
--  volonté pour repartir d'un jeu de données propre.
--
--  Comptes de démonstration (mot de passe commun : 123456)
--    admin@reserva.tn         — Administrateur Bâtiments
--    gestionnaire@reserva.tn  — Gestionnaire de Réservations
--    yassine@reserva.tn       — Utilisateur
--    ines@reserva.tn          — Utilisateur
-- =====================================================================

DROP DATABASE IF EXISTS `reserva_salles`;
CREATE DATABASE `reserva_salles` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `reserva_salles`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
--  Schéma
-- ---------------------------------------------------------------------
CREATE TABLE `batiment` (
  `id_batiment`   int(11)      NOT NULL AUTO_INCREMENT,
  `nom`           varchar(100) NOT NULL,
  `code_batiment` varchar(20)  NOT NULL,
  `adresse`       varchar(200) NOT NULL,
  `ville`         varchar(80)  NOT NULL,
  `description`   text         DEFAULT NULL,
  `image`         varchar(255) DEFAULT NULL,
  `date_creation` datetime     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_batiment`),
  UNIQUE KEY `uk_code_batiment` (`code_batiment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `utilisateur` (
  `id_utilisateur` int(11)      NOT NULL AUTO_INCREMENT,
  `nom`            varchar(60)  NOT NULL,
  `prenom`         varchar(60)  NOT NULL,
  `email`          varchar(120) NOT NULL,
  `mot_de_passe`   varchar(255) NOT NULL,
  `telephone`      varchar(20)  DEFAULT NULL,
  `departement`    varchar(80)  DEFAULT NULL,
  `role`           enum('admin','gestionnaire','utilisateur') NOT NULL DEFAULT 'utilisateur',
  `statut`         enum('actif','inactif') NOT NULL DEFAULT 'actif',
  `date_creation`  datetime     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_utilisateur`),
  UNIQUE KEY `uk_email` (`email`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `etage` (
  `id_etage`       int(11)     NOT NULL AUTO_INCREMENT,
  `id_batiment`    int(11)     NOT NULL,
  `numero_etage`   int(11)     NOT NULL,
  `nom_etage`      varchar(80) NOT NULL,
  `accessible_pmr` tinyint(1)  NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_etage`),
  UNIQUE KEY `uk_batiment_etage` (`id_batiment`,`numero_etage`),
  CONSTRAINT `fk_etage_batiment` FOREIGN KEY (`id_batiment`)
      REFERENCES `batiment` (`id_batiment`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `salle` (
  `id_salle`         int(11)     NOT NULL AUTO_INCREMENT,
  `id_etage`         int(11)     NOT NULL,
  `code_salle`       varchar(20) NOT NULL,
  `nom`              varchar(100) NOT NULL,
  `capacite`         int(11)     NOT NULL,
  `type_salle`       enum('reunion','conference','formation','visio','coworking') NOT NULL DEFAULT 'reunion',
  `equipements`      varchar(255) DEFAULT NULL,
  `localisation`     varchar(150) DEFAULT NULL,
  `etat`             enum('disponible','maintenance','indisponible') NOT NULL DEFAULT 'disponible',
  `heure_ouverture`  time        NOT NULL DEFAULT '08:00:00',
  `heure_fermeture`  time        NOT NULL DEFAULT '19:00:00',
  `delai_annulation` int(11)     NOT NULL DEFAULT 24 COMMENT 'Nombre d heures avant le debut',
  `image`            varchar(255) DEFAULT NULL,
  `date_creation`    datetime    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_salle`),
  UNIQUE KEY `uk_code_salle` (`code_salle`),
  KEY `idx_salle_etage` (`id_etage`),
  CONSTRAINT `fk_salle_etage` FOREIGN KEY (`id_etage`)
      REFERENCES `etage` (`id_etage`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `reservation` (
  `id_reservation`  int(11)      NOT NULL AUTO_INCREMENT,
  `id_salle`        int(11)      NOT NULL,
  `id_utilisateur`  int(11)      NOT NULL,
  `titre`           varchar(150) NOT NULL,
  `description`     text         DEFAULT NULL,
  `date_debut`      datetime     NOT NULL,
  `date_fin`        datetime     NOT NULL,
  `nb_participants` int(11)      NOT NULL DEFAULT 1,
  `statut`          enum('en_attente','validee','refusee','annulee','terminee') NOT NULL DEFAULT 'en_attente',
  `motif_refus`     text         DEFAULT NULL,
  `id_validateur`   int(11)      DEFAULT NULL,
  `date_validation` datetime     DEFAULT NULL,
  `date_creation`   datetime     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_reservation`),
  KEY `idx_res_salle` (`id_salle`),
  KEY `idx_res_user` (`id_utilisateur`),
  KEY `idx_res_statut` (`statut`),
  KEY `idx_res_dates` (`date_debut`,`date_fin`),
  KEY `fk_res_validateur` (`id_validateur`),
  CONSTRAINT `fk_res_salle` FOREIGN KEY (`id_salle`)
      REFERENCES `salle` (`id_salle`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_res_user` FOREIGN KEY (`id_utilisateur`)
      REFERENCES `utilisateur` (`id_utilisateur`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_res_validateur` FOREIGN KEY (`id_validateur`)
      REFERENCES `utilisateur` (`id_utilisateur`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notification` (
  `id_notification` int(11)      NOT NULL AUTO_INCREMENT,
  `id_utilisateur`  int(11)      DEFAULT NULL,
  `id_reservation`  int(11)      DEFAULT NULL,
  `destinataire`    varchar(120) NOT NULL,
  `sujet`           varchar(200) NOT NULL,
  `message`         text         NOT NULL,
  `type`            enum('demande','validation','refus','annulation','deplacement','rappel') NOT NULL,
  `envoye`          tinyint(1)   NOT NULL DEFAULT 0,
  `date_envoi`      datetime     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_notification`),
  KEY `idx_notif_user` (`id_utilisateur`),
  KEY `idx_notif_res` (`id_reservation`),
  CONSTRAINT `fk_notif_res` FOREIGN KEY (`id_reservation`)
      REFERENCES `reservation` (`id_reservation`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`id_utilisateur`)
      REFERENCES `utilisateur` (`id_utilisateur`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Données de démonstration
-- ---------------------------------------------------------------------

-- 2 bâtiments (avec image de démonstration : assets/uploads/batiments/)
INSERT INTO `batiment` (`id_batiment`,`nom`,`code_batiment`,`adresse`,`ville`,`description`,`image`,`date_creation`) VALUES
(1,'Siège Social','BAT-A','Rue du Lac Léman, Les Berges du Lac','Tunis','Bâtiment principal : direction et services centraux.','bat-a.svg','2026-09-01 09:00:00'),
(2,'Centre Innovation','BAT-B','Technopôle El Ghazala','Ariana','Équipes techniques et ateliers de créativité.','bat-b.svg','2026-09-01 09:00:00');

-- 4 utilisateurs — un par rôle + un second utilisateur (mot de passe : 123456)
INSERT INTO `utilisateur` (`id_utilisateur`,`nom`,`prenom`,`email`,`mot_de_passe`,`telephone`,`departement`,`role`,`statut`,`date_creation`) VALUES
(1,'Ben Salah','Karim','admin@reserva.tn','$2y$10$6sTXorUlFGHGjz/ckDrlRO4BnviwDiQuC0pai5WQ4oti5OLt6XOOC','21620100100','Direction Générale','admin','actif','2026-09-01 09:00:00'),
(2,'Trabelsi','Sonia','gestionnaire@reserva.tn','$2y$10$6sTXorUlFGHGjz/ckDrlRO4BnviwDiQuC0pai5WQ4oti5OLt6XOOC','21620100200','Services Généraux','gestionnaire','actif','2026-09-01 09:00:00'),
(3,'Mejri','Yassine','yassine@reserva.tn','$2y$10$6sTXorUlFGHGjz/ckDrlRO4BnviwDiQuC0pai5WQ4oti5OLt6XOOC','21620100300','Informatique','utilisateur','actif','2026-09-01 09:00:00'),
(4,'Gharbi','Ines','ines@reserva.tn','$2y$10$6sTXorUlFGHGjz/ckDrlRO4BnviwDiQuC0pai5WQ4oti5OLt6XOOC','21620100400','Marketing','utilisateur','actif','2026-09-01 09:00:00');

-- 4 étages (2 par bâtiment)
INSERT INTO `etage` (`id_etage`,`id_batiment`,`numero_etage`,`nom_etage`,`accessible_pmr`) VALUES
(1,1,0,'Rez-de-chaussée',1),
(2,1,1,'1er étage',1),
(3,2,0,'Rez-de-chaussée',1),
(4,2,1,'1er étage',0);

-- 4 salles (image de démonstration : assets/uploads/salles/)
INSERT INTO `salle`
(`id_salle`,`id_etage`,`code_salle`,`nom`,`capacite`,`type_salle`,`equipements`,`localisation`,`etat`,`heure_ouverture`,`heure_fermeture`,`delai_annulation`,`image`,`date_creation`) VALUES
(1,1,'A-RDC-01','Carthage',12,'reunion','Vidéoprojecteur, Tableau blanc, Wifi, Climatisation','Aile Est, face à l''accueil','disponible','08:00:00','19:00:00',24,'salle-carthage.svg','2026-09-01 09:30:00'),
(2,2,'A-101','Medina',40,'conference','Sonorisation, Vidéoprojecteur, Micros, Estrade','Aile Nord','disponible','08:00:00','20:00:00',48,'salle-medina.svg','2026-09-01 09:30:00'),
(3,3,'B-RDC-01','Utique',8,'coworking','Wifi, Écran TV, Prises multiples','Open space du rez-de-chaussée','disponible','07:30:00','19:30:00',12,'salle-utique.svg','2026-09-01 09:30:00'),
(4,4,'B-101','Kairouan',25,'formation','Vidéoprojecteur, Paperboard, Wifi, Écran tactile','Salle modulable','maintenance','08:00:00','18:00:00',24,'salle-kairouan.svg','2026-09-01 09:30:00');

-- 8 réservations : tous les statuts représentés
INSERT INTO `reservation`
(`id_reservation`,`id_salle`,`id_utilisateur`,`titre`,`description`,`date_debut`,`date_fin`,`nb_participants`,`statut`,`motif_refus`,`id_validateur`,`date_validation`,`date_creation`) VALUES
(1,1,3,'Comité de pilotage Q4','Revue des jalons et des risques du trimestre.','2026-09-11 09:00:00','2026-09-11 10:30:00',9,'validee',NULL,2,'2026-09-09 14:20:00','2026-09-08 11:15:00'),
(2,1,4,'Atelier persona marketing','Cadrage des cibles pour la campagne de rentrée.','2026-09-11 14:00:00','2026-09-11 15:30:00',6,'en_attente',NULL,NULL,NULL,'2026-09-09 13:05:00'),
(3,2,3,'Séminaire technique PDO / MVC','Formation interne architecture et bonnes pratiques.','2026-09-12 09:00:00','2026-09-12 12:00:00',28,'validee',NULL,2,'2026-09-09 15:00:00','2026-09-07 16:40:00'),
(4,3,4,'Point hebdo design','Suivi hebdomadaire de l''équipe produit.','2026-09-14 09:30:00','2026-09-14 10:00:00',5,'en_attente',NULL,NULL,NULL,'2026-09-09 17:30:00'),
(5,1,4,'Revue budgétaire','Arbitrages budget T4.','2026-09-04 15:00:00','2026-09-04 16:00:00',7,'terminee',NULL,2,'2026-09-02 10:00:00','2026-09-01 09:50:00'),
(6,2,3,'Formation initiale outils','Prise en main des outils internes pour les nouveaux arrivants.','2026-09-08 14:00:00','2026-09-08 17:00:00',22,'terminee',NULL,2,'2026-09-05 09:30:00','2026-09-03 14:10:00'),
(7,1,3,'Entretien annuel','Entretien individuel.','2026-09-16 11:00:00','2026-09-16 12:00:00',2,'refusee','Créneau réservé pour un événement de la Direction.',2,'2026-09-09 16:10:00','2026-09-09 10:25:00'),
(8,3,4,'Créneau annulé','Réunion finalement reportée par le demandeur.','2026-09-18 10:00:00','2026-09-18 10:30:00',4,'annulee',NULL,NULL,NULL,'2026-09-09 12:00:00');

-- Journal des notifications (extrait — la table est aussi alimentée à l'exécution)
INSERT INTO `notification`
(`id_notification`,`id_utilisateur`,`id_reservation`,`destinataire`,`sujet`,`message`,`type`,`envoye`,`date_envoi`) VALUES
(1,4,2,'ines@reserva.tn','Demande de réservation enregistrée — Carthage','Votre demande « Atelier persona marketing » a bien été enregistrée et attend la validation d''un gestionnaire.','demande',1,'2026-09-09 13:05:00'),
(2,3,1,'yassine@reserva.tn','Réservation confirmée — Carthage','Votre réservation « Comité de pilotage Q4 » du 11/09/2026 09:00 a été confirmée.','validation',1,'2026-09-09 14:20:00'),
(3,3,3,'yassine@reserva.tn','Réservation confirmée — Medina','Votre réservation « Séminaire technique PDO / MVC » du 12/09/2026 09:00 a été confirmée.','validation',1,'2026-09-09 15:00:00'),
(4,3,7,'yassine@reserva.tn','Réservation refusée — Carthage','Votre demande « Entretien annuel » a été refusée. Motif : Créneau réservé pour un événement de la Direction.','refus',1,'2026-09-09 16:10:00');

SET FOREIGN_KEY_CHECKS = 1;

-- Fin du script.
