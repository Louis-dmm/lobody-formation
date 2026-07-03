-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost:3306
-- Généré le : ven. 03 juil. 2026 à 18:36
-- Version du serveur : 11.8.8-MariaDB-ubu2204
-- Version de PHP : 8.4.20

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `formation`
--

CREATE DATABASE IF NOT EXISTS `formation` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `formation`;

-- --------------------------------------------------------

--
-- Structure de la table `Admins`
--

CREATE TABLE `Admins` (
  `id_admin` int(10) UNSIGNED NOT NULL,
  `nom` varchar(255) NOT NULL,
  `prenom` varchar(255) NOT NULL,
  `mail` varchar(255) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Déchargement des données de la table `Admins`
--

INSERT INTO `Admins` (`id_admin`, `nom`, `prenom`, `mail`, `mot_de_passe`, `date_creation`) VALUES
(1, 'Demo', 'Admin', 'admin@example.com', '$2y$12$cY2w/equjPj4QMZmr44gde3iGM1rCXO82gI34kt.HKeJ48Up/wed6', '2026-01-01 00:00:00');

-- --------------------------------------------------------

--
-- Structure de la table `Configuration_Site`
--

CREATE TABLE `Configuration_Site` (
  `id` int(11) NOT NULL DEFAULT 1,
  `nom_societe` varchar(255) NOT NULL,
  `siret` varchar(20) NOT NULL,
  `nom_en_charge` varchar(255) NOT NULL,
  `url_site` varchar(255) NOT NULL,
  `email_contact` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Déchargement des données de la table `Configuration_Site`
--

INSERT INTO `Configuration_Site` (`id`, `nom_societe`, `siret`, `nom_en_charge`, `url_site`, `email_contact`) VALUES
(1, 'Lobody Perfect', '12345678900000', 'Nom', 'http://localhost:8000', 'contact@example.com');

-- --------------------------------------------------------

--
-- Structure de la table `Email_Templates`
--

CREATE TABLE `Email_Templates` (
  `id_template` int(11) NOT NULL,
  `code_contexte` varchar(50) NOT NULL,
  `nom_affichage` varchar(100) NOT NULL,
  `sujet` varchar(255) NOT NULL,
  `contenu_html` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `Email_Templates`
--

INSERT INTO `Email_Templates` (`id_template`, `code_contexte`, `nom_affichage`, `sujet`, `contenu_html`) VALUES
(1, 'invitation_client', 'Invitation (Nouveau client)', 'Votre mot de passe - Lobody Formation', 'Bonjour <strong>{{prenom}}</strong>,<br><br>Cliquez ici pour configurer votre compte : <a href=\"{{lien_creation}}\">Choisir mon mot de passe</a>'),
(2, 'mise_a_jour_email', 'Modification d\'e-mail client', 'Mise à jour de votre accès formation', 'Bonjour <strong>{{prenom}}</strong>,<br><br>L\'administrateur a mis à jour votre adresse email.<br>Veuillez créer un nouveau mot de passe pour accéder à votre espace :<br><br><a href=\"{{lien_creation}}\">Créer mon mot de passe</a><br><br><em>(Ce lien est valide 48h)</em><br><br>À très vite !'),
(3, 'dossier_complet', 'Notification dossier complet', 'Bonne nouvelle : Votre dossier est complet !', 'Bonjour <strong>{{prenom}}</strong>,<br><br>Excellente nouvelle ! L\'administrateur a validé l\'intégralité de vos documents.<br>Votre dossier est désormais <strong>complet et finalisé</strong>.<br><br>Vous pouvez dès à présent profiter pleinement de votre espace de formation.<br><br>À très vite !<br><em>L\'équipe Lobody Formation</em>'),
(4, 'mdp_oublie', 'Mot de passe oublié', 'Réinitialisation de votre mot de passe', 'Bonjour <strong>{{prenom}}</strong>,<br><br>Vous avez demandé à réinitialiser votre mot de passe.<br><br><a href=\"{{lien_reinitialisation}}\">Réinitialiser mon mot de passe</a><br><br><em>(Ce lien est sécurisé et n\'est valide que pendant 3 heures)</em><br><br>Si vous n\'avez pas fait cette demande, vous pouvez ignorer cet email en toute sécurité.<br><br>À très vite !'),
(5, 'dossier_incomplet', '⚠️ Dossier Incomplet (Documents refusés)', 'Action requise : Des documents de votre dossier ont été refusés', 'Bonjour <strong>{{prenom}}</strong>,<br><br>Après vérification de votre dossier de formation, certains documents n\'ont pas pu être validés.<br><br><strong>Voici le détail des pièces à corriger :</strong><br>{{liste_refus}}<br><br>Veuillez vous connecter à votre espace pour renvoyer ces documents dès que possible.<br><br>À très vite !<br><em>L\'équipe Lobody Formation</em>');

-- --------------------------------------------------------

--
-- Structure de la table `Formation`
--

CREATE TABLE `Formation` (
  `id_formation` int(10) UNSIGNED NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Déchargement des données de la table `Formation`
--

INSERT INTO `Formation` (`id_formation`, `titre`, `description`) VALUES
(1, 'Yoga', 'Formation complète de Yoga'),
(2, 'Marche Nordique', 'Initiation à la marche nordique'),
(15, 'Pilates', NULL),
(16, 'Stretching', NULL),
(17, 'Nutrition', NULL),
(18, 'SST', NULL),
(19, 'Geste & posture', NULL),
(20, 'Reformer', NULL),
(21, 'Sport & perinée', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `Formation_Requirements`
--

CREATE TABLE `Formation_Requirements` (
  `id_requirement` int(10) UNSIGNED NOT NULL,
  `id_formation` int(10) UNSIGNED NOT NULL,
  `nom_document` varchar(255) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;

--
-- Déchargement des données de la table `Formation_Requirements`
--

INSERT INTO `Formation_Requirements` (`id_requirement`, `id_formation`, `nom_document`, `description`) VALUES
(9, 2, 'CNI', 'Carte d\'identité'),
(18, 15, 'CNI', 'Carte d\'identité'),
(19, 1, 'CNI', 'Carte d\'identité'),
(20, 16, 'CNI', 'Carte d\'identité'),
(22, 21, 'CNI', 'Carte d\'identité'),
(24, 19, 'CNI', 'Carte d\'identité'),
(25, 20, 'CNI', 'Carte d\'identité'),
(26, 15, 'PSC1 ou SST à jour', 'Prévention et Secours Civiques de niveau 1 ou Service de santé au travail'),
(27, 16, 'PSC1 ou SST à jour', 'Prévention et Secours Civiques de niveau 1 ou Service de santé au travail'),
(28, 20, 'PSC1 ou SST à jour', 'Prévention et Secours Civiques de niveau 1 ou Service de santé au travail');

-- --------------------------------------------------------

--
-- Structure de la table `inscription`
--

CREATE TABLE `inscription` (
  `id_inscription` int(10) UNSIGNED NOT NULL,
  `id_utilisateur` int(10) UNSIGNED NOT NULL,
  `id_session` int(10) UNSIGNED DEFAULT NULL,
  `statut_etape` enum('en_attente_docs','dossier_complet','termine') NOT NULL DEFAULT 'en_attente_docs',
  `date_debut_formation` date NOT NULL,
  `date_fin_formation` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `lieux`
--

CREATE TABLE `lieux` (
  `id_lieu` int(10) UNSIGNED NOT NULL,
  `ville` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Déchargement des données de la table `lieux`
--

INSERT INTO `lieux` (`id_lieu`, `ville`) VALUES
(1, 'Grenoble'),
(2, 'Annecy'),
(3, 'Chambery'),
(4, 'Lyon');

-- --------------------------------------------------------

--
-- Structure de la table `session`
--

CREATE TABLE `session` (
  `id_session` int(10) UNSIGNED NOT NULL,
  `id_formation` int(10) UNSIGNED NOT NULL,
  `id_lieu` int(10) UNSIGNED NOT NULL,
  `date_passage_examen` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `Users`
--

CREATE TABLE `Users` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(255) NOT NULL,
  `prenom` varchar(255) NOT NULL,
  `adresse` varchar(255) DEFAULT NULL,
  `code_postal` varchar(255) DEFAULT NULL,
  `mail` varchar(255) NOT NULL,
  `date_de_naissance` date DEFAULT NULL,
  `telephone` varchar(255) DEFAULT NULL,
  `date_d_inscription` datetime DEFAULT current_timestamp(),
  `etape_en_cours` varchar(255) DEFAULT NULL,
  `mot_de_passe` varchar(255) DEFAULT NULL,
  `ville` varchar(100) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `id_formation` int(10) UNSIGNED DEFAULT NULL,
  `note_qcm` tinyint(3) UNSIGNED DEFAULT NULL,
  `chemin_diplome` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `User_Documents`
--

CREATE TABLE `User_Documents` (
  `id_doc` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_requirement` int(11) NOT NULL,
  `chemin_fichier` varchar(255) NOT NULL,
  `statut` enum('en_attente','valide','refuse') DEFAULT 'en_attente',
  `motif_refus` text DEFAULT NULL,
  `notifie_refus` tinyint(1) NOT NULL DEFAULT 0,
  `date_upload` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `Admins`
--
ALTER TABLE `Admins`
  ADD PRIMARY KEY (`id_admin`),
  ADD UNIQUE KEY `mail_UNIQUE` (`mail`);

--
-- Index pour la table `Configuration_Site`
--
ALTER TABLE `Configuration_Site`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `Email_Templates`
--
ALTER TABLE `Email_Templates`
  ADD PRIMARY KEY (`id_template`),
  ADD UNIQUE KEY `code_contexte` (`code_contexte`);

--
-- Index pour la table `Formation`
--
ALTER TABLE `Formation`
  ADD PRIMARY KEY (`id_formation`);

--
-- Index pour la table `Formation_Requirements`
--
ALTER TABLE `Formation_Requirements`
  ADD PRIMARY KEY (`id_requirement`),
  ADD KEY `fk_req_formation` (`id_formation`);

--
-- Index pour la table `inscription`
--
ALTER TABLE `inscription`
  ADD PRIMARY KEY (`id_inscription`),
  ADD KEY `id_utilisateur_idx` (`id_utilisateur`),
  ADD KEY `id_session_idx` (`id_session`);

--
-- Index pour la table `lieux`
--
ALTER TABLE `lieux`
  ADD PRIMARY KEY (`id_lieu`);

--
-- Index pour la table `session`
--
ALTER TABLE `session`
  ADD PRIMARY KEY (`id_session`),
  ADD KEY `id_formation_idx` (`id_formation`),
  ADD KEY `id_lieu_idx` (`id_lieu`);

--
-- Index pour la table `Users`
--
ALTER TABLE `Users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mail_UNIQUE` (`mail`),
  ADD KEY `fk_user_formation` (`id_formation`);

--
-- Index pour la table `User_Documents`
--
ALTER TABLE `User_Documents`
  ADD PRIMARY KEY (`id_doc`),
  ADD UNIQUE KEY `unique_user_req` (`id_user`,`id_requirement`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `Admins`
--
ALTER TABLE `Admins`
  MODIFY `id_admin` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `Email_Templates`
--
ALTER TABLE `Email_Templates`
  MODIFY `id_template` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `Formation`
--
ALTER TABLE `Formation`
  MODIFY `id_formation` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT pour la table `Formation_Requirements`
--
ALTER TABLE `Formation_Requirements`
  MODIFY `id_requirement` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT pour la table `inscription`
--
ALTER TABLE `inscription`
  MODIFY `id_inscription` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `lieux`
--
ALTER TABLE `lieux`
  MODIFY `id_lieu` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `session`
--
ALTER TABLE `session`
  MODIFY `id_session` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `Users`
--
ALTER TABLE `Users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `User_Documents`
--
ALTER TABLE `User_Documents`
  MODIFY `id_doc` int(11) NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `Formation_Requirements`
--
ALTER TABLE `Formation_Requirements`
  ADD CONSTRAINT `fk_req_formation` FOREIGN KEY (`id_formation`) REFERENCES `Formation` (`id_formation`) ON DELETE CASCADE;

--
-- Contraintes pour la table `inscription`
--
ALTER TABLE `inscription`
  ADD CONSTRAINT `fk_inscription_session` FOREIGN KEY (`id_session`) REFERENCES `session` (`id_session`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inscription_user` FOREIGN KEY (`id_utilisateur`) REFERENCES `Users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `session`
--
ALTER TABLE `session`
  ADD CONSTRAINT `fk_session_formation` FOREIGN KEY (`id_formation`) REFERENCES `Formation` (`id_formation`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_session_lieu` FOREIGN KEY (`id_lieu`) REFERENCES `lieux` (`id_lieu`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Contraintes pour la table `Users`
--
ALTER TABLE `Users`
  ADD CONSTRAINT `fk_user_formation` FOREIGN KEY (`id_formation`) REFERENCES `Formation` (`id_formation`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
