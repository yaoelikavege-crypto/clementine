-- =============================================================================
--  Clémentine · 21 ans — structure MySQL / MariaDB
--  Généré automatiquement depuis includes/schema.php
--  Date de génération : 2026-10-21 16:56:45
--
--  IMPORTANT :
--   - Importer dans la base créée sur InfinityFree (phpMyAdmin).
--   - Aucun compte administrateur n'est créé par ce script : utilisez
--     /setup/index.php ou créez les comptes via l'assistant.
--   - Ne jamais publier ce fichier après import (SQL/.htaccess le protège).
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- Table : admins
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `display_name` VARCHAR(100) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table : site_settings
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(80) NOT NULL,
  `setting_value` TEXT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table : visitor_credentials
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `visitor_credentials` (
  `id` TINYINT UNSIGNED NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `password_updated_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table : media
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `media` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kind` ENUM('image','video','audio') NOT NULL,
  `source` ENUM('local','external') NOT NULL DEFAULT 'local',
  `file_path` VARCHAR(255) NULL DEFAULT NULL,
  `external_url` VARCHAR(500) NULL DEFAULT NULL,
  `thumbnail_path` VARCHAR(255) NULL DEFAULT NULL,
  `title` VARCHAR(150) NOT NULL,
  `description` TEXT NULL,
  `artist` VARCHAR(120) NULL DEFAULT NULL,
  `mime_type` VARCHAR(100) NULL DEFAULT NULL,
  `file_size` BIGINT UNSIGNED NULL DEFAULT NULL,
  `width` INT UNSIGNED NULL DEFAULT NULL,
  `height` INT UNSIGNED NULL DEFAULT NULL,
  `duration_seconds` INT UNSIGNED NULL DEFAULT NULL,
  `is_protected` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_media_kind` (`kind`),
  KEY `idx_media_source` (`source`),
  KEY `idx_media_created` (`created_at`),
  CONSTRAINT `fk_media_admin` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table : memories
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `memories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(150) NOT NULL,
  `description` TEXT NULL,
  `category` VARCHAR(80) NULL DEFAULT NULL,
  `memory_date` DATE NULL DEFAULT NULL,
  `media_id` INT UNSIGNED NULL DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_memories_public` (`is_published`, `sort_order`, `id`),
  KEY `idx_memories_category` (`category`),
  KEY `idx_memories_media` (`media_id`),
  CONSTRAINT `fk_memories_media` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table : playlists
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `playlists` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(40) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_playlists_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table : playlist_tracks
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `playlist_tracks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `playlist_id` INT UNSIGNED NOT NULL,
  `media_id` INT UNSIGNED NOT NULL,
  `position` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_playlist_media` (`playlist_id`, `media_id`),
  KEY `idx_tracks_order` (`playlist_id`, `position`),
  KEY `idx_tracks_media` (`media_id`),
  CONSTRAINT `fk_tracks_playlist` FOREIGN KEY (`playlist_id`) REFERENCES `playlists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tracks_media` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table : love_letter
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `love_letter` (
  `id` TINYINT UNSIGNED NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `body` LONGTEXT NOT NULL,
  `signature` VARCHAR(100) NOT NULL DEFAULT 'Joël',
  `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_by` INT UNSIGNED NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_letter_admin` FOREIGN KEY (`updated_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table : wishes
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wishes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `author` VARCHAR(100) NOT NULL DEFAULT 'Un proche',
  `message` TEXT NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_wishes_public` (`is_published`, `sort_order`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table : countdown_messages
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `countdown_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(120) NULL DEFAULT NULL,
  `message` TEXT NOT NULL,
  `reveal_at` DATETIME NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_countdown_visible` (`is_active`, `reveal_at`),
  KEY `idx_countdown_order` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table : login_attempts
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `context` VARCHAR(20) NOT NULL,
  `ip_hash` CHAR(64) NOT NULL,
  `username_hash` CHAR(64) NULL DEFAULT NULL,
  `success` TINYINT(1) NOT NULL DEFAULT 0,
  `attempted_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_attempts_rate` (`context`, `ip_hash`, `attempted_at`),
  KEY `idx_attempts_time` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table : audit_log
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` INT UNSIGNED NULL DEFAULT NULL,
  `action` VARCHAR(80) NOT NULL,
  `details` VARCHAR(500) NULL DEFAULT NULL,
  `ip_hash` CHAR(64) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_created` (`created_at`),
  KEY `idx_audit_admin` (`admin_id`),
  CONSTRAINT `fk_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  Données initiales
-- =============================================================================

-- playlists
INSERT IGNORE INTO `playlists` (`slug`, `name`, `description`, `sort_order`, `is_active`) VALUES ('countdown', 'L''attente', 'Ambiance douce de la période d''attente', 1, 1);
INSERT IGNORE INTO `playlists` (`slug`, `name`, `description`, `sort_order`, `is_active`) VALUES ('memories', 'Souvenirs', 'Accompagne la galerie de photos et vidéos', 2, 1);
INSERT IGNORE INTO `playlists` (`slug`, `name`, `description`, `sort_order`, `is_active`) VALUES ('letter', 'Lettre & vœux', 'Pour la lecture de la lettre', 3, 1);
INSERT IGNORE INTO `playlists` (`slug`, `name`, `description`, `sort_order`, `is_active`) VALUES ('finale', 'Finale', 'Pour la bougie et le grand moment', 4, 1);

-- site_settings
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES ('site_title', 'Clémentine · 21 ans');
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES ('hero_kicker', '23 novembre 2026 · 21 ans');
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES ('hero_title', 'Clémentine');
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES ('hero_message', 'Il y a des personnes qui rendent le monde plus doux simplement en y étant. Toi, tu es de celles-là.');
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES ('hero_submessage', 'Un petit coin d''internet s''illumine rien que pour toi. Chaque seconde nous rapproche d''un moment qui mérite d''être inoubliable.');
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES ('countdown_title', 'Le grand jour approche');
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES ('countdown_note', 'Reviens le 23 novembre à minuit. Le reste, c''est une surprise.');
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES ('reveal_title', 'Minuit a sonné.');
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES ('reveal_message', 'L''attente prend fin : ton album de souvenirs est ouvert. Prends ton temps, tout est là pour toi.');
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES ('gallery_intro', 'Des instants, des sourires, des fragments de nous. Chaque image est une petite histoire.');
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES ('gallery_end_cta', 'Et si tu découvrais maintenant quelques mots qui viennent du cœur ?');
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES ('gallery_categories', 'Nos beaux moments, Souvenirs précieux, Moments de complicité, Petites histoires de nous');
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES ('letter_intro', 'Quelques mots posés avec le cœur, pour celle qui compte plus que tout.');
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES ('finale_invite', 'Ferme les yeux, fais un vœu… puis souffle sur la bougie.');
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES ('finale_message', 'Clémentine');
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES ('finale_note', '21 ans de lumière, et ce n''est que le début.');
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES ('footer_signature', 'Avec tout mon amour, Joël');
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES ('maintenance_mode', '0');
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES ('maintenance_message', 'Le site est en préparation. Reviens très vite.');
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES ('visitor_hint', 'Le mot de passe te sera communiqué par Joël.');
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES ('installed', '0');
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES ('installed_at', '');

-- love_letter
SET @seed_love_letter := (SELECT COUNT(*) = 0 FROM `love_letter`);
INSERT INTO `love_letter` (`id`, `title`, `body`, `signature`, `is_published`) SELECT 1, 'Pour toi, Clémentine', 'Ma Clémentine,

Aujourd''hui, le calendrier affiche une date que j''aime déjà : le 23 novembre. Le jour où le monde a gagné une personne lumineuse, et où ma vie a gagné celle qui compte le plus.

Vingt et un ans. Te voilà à un âge où les portes commencent à s''ouvrir toutes grandes, où les rêves prennent une forme un peu plus concrète. Je te regarde avancer, et je suis admiratif de la façon dont tu transformes les choses : un tissu devient un vêtement, une idée devient un projet, une journée ordinaire devient un souvenir. Tu as ce don rare de rendre beau ce que tu touches, et de rendre plus doux ce qui t''entoure.

Merci d''être toi. Merci pour ta patience, ta douceur, ton sérieux quand il faut, et cet éclat dans les yeux quand tu ris. Merci pour tout ce que tu apportes sans même t''en rendre compte. J''ai la chance de le voir de près, et je ne le prends pas pour acquis.

Aujourd''hui, je te souhaite une année à ton image : créative, sincère, remplie de petites joies qui font les grands bonheurs. Je te souhaite des projets qui te font vibrer, des mains qui continuent de créer, un cœur toujours aussi généreux, et la certitude d''être aimée exactement comme tu es, avec tes forces et ta fragilité.

Joyeux anniversaire, mon cœur. 21 ans te vont déjà si bien.

Je t''aime,', 'Joël', 1 WHERE @seed_love_letter;

-- wishes
SET @seed_wishes := (SELECT COUNT(*) = 0 FROM `wishes`);
INSERT INTO `wishes` (`author`, `message`, `sort_order`, `is_published`) SELECT 'Joël', '21 ans, et déjà tant de lumière autour de toi. Joyeux anniversaire, Clémentine. Que cette année te soit douce, créative et pleine de fous rires.', 1, 1 WHERE @seed_wishes;
INSERT INTO `wishes` (`author`, `message`, `sort_order`, `is_published`) SELECT 'Tous ceux qui t''aiment', 'Que tes mains continuent de créer de belles choses, et que la vie te rende tout le soin et la tendresse que tu offres autour de toi.', 2, 1 WHERE @seed_wishes;
INSERT INTO `wishes` (`author`, `message`, `sort_order`, `is_published`) SELECT 'Tes camarades', 'Joyeux anniversaire ! On te souhaite une année pétillante, pleine de projets et de souvenirs à collectionner.', 3, 1 WHERE @seed_wishes;

-- countdown_messages
SET @seed_countdown_messages := (SELECT COUNT(*) = 0 FROM `countdown_messages`);
INSERT INTO `countdown_messages` (`title`, `message`, `reveal_at`, `is_active`, `sort_order`) SELECT 'Un secret bien gardé', 'Il y a, quelque part, un album qui n''attend que toi.', '2026-10-26 00:00:00', 1, 1 WHERE @seed_countdown_messages;
INSERT INTO `countdown_messages` (`title`, `message`, `reveal_at`, `is_active`, `sort_order`) SELECT 'Une personne exceptionnelle', 'Une personne exceptionnelle mérite un jour qui lui ressemble : lumineux, doux, inoubliable.', '2026-11-09 00:00:00', 1, 2 WHERE @seed_countdown_messages;
INSERT INTO `countdown_messages` (`title`, `message`, `reveal_at`, `is_active`, `sort_order`) SELECT 'Presque minuit', 'Dans quelques jours, minuit changera tout. Reviens à ce moment-là…', '2026-11-20 00:00:00', 1, 3 WHERE @seed_countdown_messages;

-- Fin du script.
