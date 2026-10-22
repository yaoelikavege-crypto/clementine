<?php
/**
 * Schéma de la base de données et données initiales.
 * Source de vérité unique : `tools/build_sql.php` génère `sql/install.sql`
 * à partir de ce fichier, et l'assistant d'installation l'exécute via PDO.
 *
 * Cible : MySQL 5.7+ / MariaDB 10.2+ (InfinityFree), encodage utf8mb4.
 * Aucun identifiant ni mot de passe réel n'est présent ici.
 */

return [

    'tables' => [

        'admins' => <<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

        'site_settings' => <<<'SQL'
CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(80) NOT NULL,
  `setting_value` TEXT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

        'visitor_credentials' => <<<'SQL'
CREATE TABLE IF NOT EXISTS `visitor_credentials` (
  `id` TINYINT UNSIGNED NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `password_updated_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

        'media' => <<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

        'memories' => <<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

        'playlists' => <<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

        'playlist_tracks' => <<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

        'love_letter' => <<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

        'wishes' => <<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

        'countdown_messages' => <<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

        'login_attempts' => <<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

        'audit_log' => <<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

    ],

    /*
     * Données initiales.
     * - `unique` : colonne soumise à une contrainte d'unicité : INSERT IGNORE,
     *   les valeurs modifiées par l'administrateur ne seront jamais écrasées.
     * - `only_if_empty` : ligne(s) insérée(s) uniquement si la table est vide.
     */
    'seeds' => [

        'playlists' => [
            'columns' => ['slug', 'name', 'description', 'sort_order', 'is_active'],
            'unique'  => 'slug',
            'rows'    => [
                ['countdown', "L'attente", "Ambiance douce de la période d'attente", 1, 1],
                ['memories', 'Souvenirs', 'Accompagne la galerie de photos et vidéos', 2, 1],
                ['letter', 'Lettre & vœux', 'Pour la lecture de la lettre', 3, 1],
                ['finale', 'Finale', 'Pour la bougie et le grand moment', 4, 1],
            ],
        ],

        'site_settings' => [
            'columns' => ['setting_key', 'setting_value'],
            'unique'  => 'setting_key',
            'rows'    => [
                ['site_title', 'Clémentine · 21 ans'],
                ['hero_kicker', '23 novembre 2026 · 21 ans'],
                ['hero_title', 'Clémentine'],
                ['hero_message', "Il y a des personnes qui rendent le monde plus doux simplement en y étant. Toi, tu es de celles-là."],
                ['hero_submessage', "Un petit coin d'internet s'illumine rien que pour toi. Chaque seconde nous rapproche d'un moment qui mérite d'être inoubliable."],
                ['countdown_title', 'Le grand jour approche'],
                ['countdown_note', 'Reviens le 23 novembre à minuit. Le reste, c\'est une surprise.'],
                ['reveal_title', 'Minuit a sonné.'],
                ['reveal_message', "L'attente prend fin : ton album de souvenirs est ouvert. Prends ton temps, tout est là pour toi."],
                ['gallery_intro', 'Des instants, des sourires, des fragments de nous. Chaque image est une petite histoire.'],
                ['gallery_end_cta', 'Et si tu découvrais maintenant quelques mots qui viennent du cœur ?'],
                ['gallery_categories', 'Nos beaux moments, Souvenirs précieux, Moments de complicité, Petites histoires de nous'],
                ['letter_intro', 'Quelques mots posés avec le cœur, pour celle qui compte plus que tout.'],
                ['finale_invite', 'Ferme les yeux, fais un vœu… puis souffle sur la bougie.'],
                ['finale_message', 'Clémentine'],
                ['finale_note', "21 ans de lumière, et ce n'est que le début."],
                ['footer_signature', 'Avec tout mon amour, Joël'],
                ['maintenance_mode', '0'],
                ['maintenance_message', 'Le site est en préparation. Reviens très vite.'],
                ['visitor_hint', 'Le mot de passe te sera communiqué par Joël.'],
                ['installed', '0'],
                ['installed_at', ''],
            ],
        ],

        'love_letter' => [
            'columns' => ['id', 'title', 'body', 'signature', 'is_published'],
            'only_if_empty' => true,
            'rows' => [
                [
                    1,
                    'Pour toi, Clémentine',
                    "Ma Clémentine,\n\nAujourd'hui, le calendrier affiche une date que j'aime déjà : le 23 novembre. Le jour où le monde a gagné une personne lumineuse, et où ma vie a gagné celle qui compte le plus.\n\nVingt et un ans. Te voilà à un âge où les portes commencent à s'ouvrir toutes grandes, où les rêves prennent une forme un peu plus concrète. Je te regarde avancer, et je suis admiratif de la façon dont tu transformes les choses : un tissu devient un vêtement, une idée devient un projet, une journée ordinaire devient un souvenir. Tu as ce don rare de rendre beau ce que tu touches, et de rendre plus doux ce qui t'entoure.\n\nMerci d'être toi. Merci pour ta patience, ta douceur, ton sérieux quand il faut, et cet éclat dans les yeux quand tu ris. Merci pour tout ce que tu apportes sans même t'en rendre compte. J'ai la chance de le voir de près, et je ne le prends pas pour acquis.\n\nAujourd'hui, je te souhaite une année à ton image : créative, sincère, remplie de petites joies qui font les grands bonheurs. Je te souhaite des projets qui te font vibrer, des mains qui continuent de créer, un cœur toujours aussi généreux, et la certitude d'être aimée exactement comme tu es, avec tes forces et ta fragilité.\n\nJoyeux anniversaire, mon cœur. 21 ans te vont déjà si bien.\n\nJe t'aime,",
                    'Joël',
                    1,
                ],
            ],
        ],

        'wishes' => [
            'columns' => ['author', 'message', 'sort_order', 'is_published'],
            'only_if_empty' => true,
            'rows' => [
                ['Joël', "21 ans, et déjà tant de lumière autour de toi. Joyeux anniversaire, Clémentine. Que cette année te soit douce, créative et pleine de fous rires.", 1, 1],
                ["Tous ceux qui t'aiment", "Que tes mains continuent de créer de belles choses, et que la vie te rende tout le soin et la tendresse que tu offres autour de toi.", 2, 1],
                ['Tes camarades', "Joyeux anniversaire ! On te souhaite une année pétillante, pleine de projets et de souvenirs à collectionner.", 3, 1],
            ],
        ],

        'countdown_messages' => [
            'columns' => ['title', 'message', 'reveal_at', 'is_active', 'sort_order'],
            'only_if_empty' => true,
            'rows' => [
                ['Un secret bien gardé', "Il y a, quelque part, un album qui n'attend que toi.", '2026-10-26 00:00:00', 1, 1],
                ['Une personne exceptionnelle', "Une personne exceptionnelle mérite un jour qui lui ressemble : lumineux, doux, inoubliable.", '2026-11-09 00:00:00', 1, 2],
                ['Presque minuit', 'Dans quelques jours, minuit changera tout. Reviens à ce moment-là…', '2026-11-20 00:00:00', 1, 3],
            ],
        ],

    ],
];
