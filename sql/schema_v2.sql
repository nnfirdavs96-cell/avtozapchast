-- АвтоЗапчасть v2 — Schema extensions
-- Run AFTER schema.sql

SET NAMES utf8mb4;
USE `avtozapchast`;

-- --------------------------------------------------------
-- Table: wishlist
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wishlist` (
  `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`  INT UNSIGNED NOT NULL,
  `part_id`  INT UNSIGNED NOT NULL,
  `added_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_part` (`user_id`, `part_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_wl_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wl_part` FOREIGN KEY (`part_id`) REFERENCES `parts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: currencies
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `currencies` (
  `id`         INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `code`       VARCHAR(3)     NOT NULL UNIQUE,
  `name_ru`    VARCHAR(80)    NOT NULL,
  `name_tg`    VARCHAR(80)    NOT NULL,
  `name_en`    VARCHAR(80)    NOT NULL,
  `symbol`     VARCHAR(10)    NOT NULL,
  `rate`       DECIMAL(12,6)  NOT NULL DEFAULT 1.000000 COMMENT 'rate relative to RUB',
  `is_active`  TINYINT(1)     NOT NULL DEFAULT 1,
  `is_default` TINYINT(1)     NOT NULL DEFAULT 0,
  `updated_at` DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `currencies` (`code`,`name_ru`,`name_tg`,`name_en`,`symbol`,`rate`,`is_active`,`is_default`) VALUES
('RUB', 'Российский рубль', 'Рубли русӣ',    'Russian Ruble',   '₽',  1.000000, 1, 1),
('USD', 'Доллар США',       'Доллари Амрико', 'US Dollar',       '$',  0.011000, 1, 0),
('TJS', 'Таджикский сомони','Сомони',          'Tajik Somoni',    'SM', 0.120000, 1, 0);

-- --------------------------------------------------------
-- Table: languages
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `languages` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`       VARCHAR(5)   NOT NULL UNIQUE,
  `name`       VARCHAR(60)  NOT NULL,
  `flag`       VARCHAR(10)  NOT NULL DEFAULT '🌐',
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `is_default` TINYINT(1)   NOT NULL DEFAULT 0,
  `sort_order` INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `languages` (`code`,`name`,`flag`,`is_active`,`is_default`,`sort_order`) VALUES
('ru', 'Русский',    '🇷🇺', 1, 1, 1),
('tg', 'Тоҷикӣ',    '🇹🇯', 1, 0, 2),
('en', 'English',   '🇬🇧', 1, 0, 3);

-- --------------------------------------------------------
-- Table: blog_posts
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `blog_posts` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `slug`        VARCHAR(220)  NOT NULL UNIQUE,
  `title_ru`    VARCHAR(255)  NOT NULL,
  `title_tg`    VARCHAR(255)  NOT NULL DEFAULT '',
  `title_en`    VARCHAR(255)  NOT NULL DEFAULT '',
  `excerpt_ru`  TEXT          DEFAULT NULL,
  `excerpt_tg`  TEXT          DEFAULT NULL,
  `excerpt_en`  TEXT          DEFAULT NULL,
  `body_ru`     LONGTEXT      DEFAULT NULL,
  `body_tg`     LONGTEXT      DEFAULT NULL,
  `body_en`     LONGTEXT      DEFAULT NULL,
  `image_path`  VARCHAR(255)  DEFAULT NULL,
  `author_id`   INT UNSIGNED  DEFAULT NULL,
  `is_published` TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_slug`      (`slug`),
  KEY `idx_published` (`is_published`),
  CONSTRAINT `fk_blog_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `blog_posts` (`slug`,`title_ru`,`title_tg`,`title_en`,`excerpt_ru`,`excerpt_tg`,`excerpt_en`,`is_published`) VALUES
('kak-vybrat-tormoznye-kolodki',
 'Как выбрать тормозные колодки для вашего автомобиля',
 'Чӣ тавр барои мошинатон лавҳаҳои тормоз интихоб кардан мумкин аст',
 'How to Choose Brake Pads for Your Car',
 'Подробный гид по выбору тормозных колодок. Разбираемся в типах, производителях и технических характеристиках.',
 'Роҳнамои муфассал оид ба интихоби лавҳаҳои тормоз.',
 'A detailed guide to choosing brake pads. We look at types, manufacturers and specifications.',
 1),
('zamena-remnya-grm',
 'Замена ремня ГРМ: когда и как это делать правильно',
 'Иваз кардани тасмаи ГРМ: кай ва чӣ гуна дуруст анҷом додан',
 'Timing Belt Replacement: When and How to Do It Right',
 'Ремень ГРМ — критически важная деталь двигателя. Узнайте, когда его нужно менять и как не допустить ошибок.',
 'Тасмаи ГРМ як қисми муҳими муҳаррик аст.',
 'The timing belt is a critical engine component. Find out when to replace it and how to avoid mistakes.',
 1),
('top-10-brendov-zapchastei',
 'Топ-10 брендов автозапчастей в 2024 году',
 'Беҳтарин 10 бренди эҳтиёт қисмҳои мошин дар соли 2024',
 'Top 10 Auto Parts Brands in 2024',
 'Обзор лучших мировых брендов автозапчастей: качество, ресурс и соотношение цена/качество.',
 'Шарҳи беҳтарин брендҳои ҷаҳонии эҳтиёт қисмҳо.',
 'A review of the world best auto parts brands: quality, lifespan, and value for money.',
 1);

-- --------------------------------------------------------
-- Table: warehouse_api_log
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `warehouse_api_log` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `action`      VARCHAR(80)  NOT NULL,
  `request`     TEXT         DEFAULT NULL,
  `response`    TEXT         DEFAULT NULL,
  `status_code` SMALLINT     DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: parts_i18n (translated names/descriptions)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `parts_i18n` (
  `part_id`     INT UNSIGNED NOT NULL,
  `lang`        VARCHAR(5)   NOT NULL,
  `name`        VARCHAR(220) NOT NULL DEFAULT '',
  `description` TEXT         DEFAULT NULL,
  PRIMARY KEY (`part_id`, `lang`),
  CONSTRAINT `fk_i18n_part` FOREIGN KEY (`part_id`) REFERENCES `parts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: categories_i18n
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories_i18n` (
  `category_id` INT UNSIGNED NOT NULL,
  `lang`        VARCHAR(5)   NOT NULL,
  `name`        VARCHAR(120) NOT NULL DEFAULT '',
  `description` TEXT         DEFAULT NULL,
  PRIMARY KEY (`category_id`, `lang`),
  CONSTRAINT `fk_i18n_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert English/Tajik translations for categories
INSERT INTO `categories_i18n` (`category_id`,`lang`,`name`) VALUES
(1,'en','Engine'),(1,'tg','Муҳаррик'),
(2,'en','Brake System'),(2,'tg','Системаи тормоз'),
(3,'en','Suspension'),(3,'tg','Тормозбанд'),
(4,'en','Electrics'),(4,'tg','Барқ'),
(5,'en','Body'),(5,'tg','Бадана'),
(6,'en','Transmission'),(6,'tg','Трансмиссия'),
(7,'en','Filters'),(7,'tg','Филтрҳо'),
(8,'en','Belts & Chains'),(8,'tg','Тасмаҳо ва занҷирҳо'),
(9,'en','Spark Plugs'),(9,'tg','Шамъҳо'),
(10,'en','Shock Absorbers'),(10,'tg','Амортизаторҳо');

-- --------------------------------------------------------
-- Extend site_settings with new keys
-- --------------------------------------------------------
INSERT IGNORE INTO `site_settings` (`key`, `value`) VALUES
('warehouse_api_url',    'https://api.encar.com/search/car/list/mobile'),
('warehouse_api_key',    ''),
('warehouse_api_enabled','0'),
('default_lang',         'ru'),
('default_currency',     'RUB'),
('show_language_switcher','1'),
('show_currency_switcher','1'),
('site_phone2',          ''),
('site_telegram',        ''),
('site_whatsapp',        ''),
('meta_description',     'АвтоЗапчасть — профессиональные автозапчасти с доставкой по всей России'),
('meta_keywords',        'автозапчасти, запчасти, авто, детали, двигатель, тормоза');
