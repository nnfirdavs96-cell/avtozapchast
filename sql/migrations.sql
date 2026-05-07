-- ============================================================
-- АвтоЗапчасть — Migration #2
-- Adds: i18n, multi-currency, car compatibility, reviews,
-- wishlist, compare, addresses, deliveries, payments, blog,
-- product images, VIN lookup
-- ============================================================

USE `avtozapchast`;
SET NAMES utf8mb4;

-- ── Languages ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `languages` (
  `code`       VARCHAR(5)  NOT NULL,
  `name`       VARCHAR(60) NOT NULL,
  `is_default` TINYINT(1)  NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1)  NOT NULL DEFAULT 1,
  `sort_order` INT         NOT NULL DEFAULT 0,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `languages` (`code`, `name`, `is_default`, `sort_order`) VALUES
('ru', 'Русский',  1, 1),
('tg', 'Тоҷикӣ',   0, 2),
('en', 'English',  0, 3);

-- ── Currencies ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `currencies` (
  `code`       VARCHAR(5)    NOT NULL,
  `symbol`     VARCHAR(8)    NOT NULL,
  `name`       VARCHAR(60)   NOT NULL,
  `rate`       DECIMAL(12,6) NOT NULL DEFAULT 1.000000 COMMENT 'multiplier from base RUB',
  `is_default` TINYINT(1)    NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1)    NOT NULL DEFAULT 1,
  `sort_order` INT           NOT NULL DEFAULT 0,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `currencies` (`code`, `symbol`, `name`, `rate`, `is_default`, `sort_order`) VALUES
('RUB', '₽',    'Российский рубль', 1.000000, 1, 1),
('TJS', 'смн',  'Таджикский сомони',0.120000, 0, 2),
('USD', '$',    'Доллар США',       0.011000, 0, 3);

-- ── Translations key-value ────────────────────────────────
CREATE TABLE IF NOT EXISTS `translations` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lang_code`  VARCHAR(5)   NOT NULL,
  `entity`     VARCHAR(40)  NOT NULL COMMENT 'category|brand|part|page',
  `entity_id`  INT UNSIGNED NOT NULL,
  `field`      VARCHAR(40)  NOT NULL COMMENT 'name|description',
  `value`      TEXT         NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_trans` (`lang_code`,`entity`,`entity_id`,`field`),
  KEY `idx_lookup` (`entity`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Car Makes / Models / Years ────────────────────────────
CREATE TABLE IF NOT EXISTS `car_makes` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`      VARCHAR(80)  NOT NULL,
  `slug`      VARCHAR(80)  NOT NULL UNIQUE,
  `country`   VARCHAR(60)  DEFAULT NULL,
  `logo_path` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `car_models` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `make_id`    INT UNSIGNED NOT NULL,
  `name`       VARCHAR(120) NOT NULL,
  `slug`       VARCHAR(120) NOT NULL,
  `year_from`  SMALLINT     NOT NULL DEFAULT 1990,
  `year_to`    SMALLINT     NOT NULL DEFAULT 2030,
  `body_type`  VARCHAR(40)  DEFAULT NULL,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_make` (`make_id`),
  CONSTRAINT `fk_cm_make` FOREIGN KEY (`make_id`) REFERENCES `car_makes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Part-Car compatibility ────────────────────────────────
CREATE TABLE IF NOT EXISTS `part_compatibility` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `part_id`    INT UNSIGNED NOT NULL,
  `model_id`   INT UNSIGNED NOT NULL,
  `year_from`  SMALLINT     DEFAULT NULL,
  `year_to`    SMALLINT     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pc` (`part_id`,`model_id`),
  KEY `idx_model` (`model_id`),
  CONSTRAINT `fk_pc_part`  FOREIGN KEY (`part_id`)  REFERENCES `parts`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pc_model` FOREIGN KEY (`model_id`) REFERENCES `car_models`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── VIN lookup table (stub) ───────────────────────────────
CREATE TABLE IF NOT EXISTS `vin_records` (
  `vin`        VARCHAR(17) NOT NULL,
  `make_id`    INT UNSIGNED NOT NULL,
  `model_id`   INT UNSIGNED NOT NULL,
  `year`       SMALLINT     NOT NULL,
  `engine`     VARCHAR(60)  DEFAULT NULL,
  `notes`      TEXT         DEFAULT NULL,
  PRIMARY KEY (`vin`),
  KEY `idx_make`  (`make_id`),
  KEY `idx_model` (`model_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Product images (multiple per part) ────────────────────
CREATE TABLE IF NOT EXISTS `part_images` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `part_id`    INT UNSIGNED NOT NULL,
  `path`       VARCHAR(255) NOT NULL,
  `sort_order` INT          NOT NULL DEFAULT 0,
  `is_primary` TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_part` (`part_id`),
  CONSTRAINT `fk_pi_part` FOREIGN KEY (`part_id`) REFERENCES `parts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Reviews ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `reviews` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `part_id`    INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `rating`     TINYINT      NOT NULL DEFAULT 5,
  `title`      VARCHAR(180) DEFAULT NULL,
  `body`       TEXT         NOT NULL,
  `status`     ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_part`   (`part_id`),
  KEY `idx_user`   (`user_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_rev_part` FOREIGN KEY (`part_id`) REFERENCES `parts`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rev_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Wishlist ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `wishlist` (
  `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`  INT UNSIGNED NOT NULL,
  `part_id`  INT UNSIGNED NOT NULL,
  `added_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_part` (`user_id`,`part_id`),
  CONSTRAINT `fk_wl_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wl_part` FOREIGN KEY (`part_id`) REFERENCES `parts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Compare list (per session/user) ───────────────────────
CREATE TABLE IF NOT EXISTS `compare_list` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED DEFAULT NULL,
  `session_id` VARCHAR(64)  DEFAULT NULL,
  `part_id`    INT UNSIGNED NOT NULL,
  `added_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user`    (`user_id`),
  KEY `idx_session` (`session_id`),
  CONSTRAINT `fk_cmp_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cmp_part` FOREIGN KEY (`part_id`) REFERENCES `parts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── User Addresses ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `addresses` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL,
  `recipient`    VARCHAR(120) NOT NULL,
  `phone`        VARCHAR(30)  NOT NULL,
  `country`      VARCHAR(60)  NOT NULL DEFAULT 'Россия',
  `region`       VARCHAR(120) DEFAULT NULL,
  `city`         VARCHAR(120) NOT NULL,
  `street`       VARCHAR(200) NOT NULL,
  `building`     VARCHAR(40)  DEFAULT NULL,
  `apartment`    VARCHAR(40)  DEFAULT NULL,
  `postal_code`  VARCHAR(20)  DEFAULT NULL,
  `notes`        TEXT         DEFAULT NULL,
  `is_default`   TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_addr_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Delivery methods ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `delivery_methods` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(40)  NOT NULL UNIQUE,
  `name`        VARCHAR(120) NOT NULL,
  `description` TEXT         DEFAULT NULL,
  `cost`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `eta_days`    VARCHAR(40)  DEFAULT NULL,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order`  INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `delivery_methods` (`code`,`name`,`description`,`cost`,`eta_days`,`sort_order`) VALUES
('pickup',    'Самовывоз',           'Со склада в Москве, бесплатно',           0.00,    'сегодня', 1),
('courier_msk','Курьер по Москве',   'Доставка курьером в пределах МКАД',       450.00,  '1 день',  2),
('cdek',      'СДЭК',                'Доставка СДЭК по России',                 590.00,  '2-5 дней',3),
('post',      'Почта России',        'Стандартная доставка Почтой России',      390.00,  '3-10 дней',4),
('intl',      'Международная доставка','EMS до Душанбе и других городов',       1900.00, '7-14 дней',5);

-- ── Payment methods ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `payment_methods` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(40)  NOT NULL UNIQUE,
  `name`        VARCHAR(120) NOT NULL,
  `description` TEXT         DEFAULT NULL,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order`  INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `payment_methods` (`code`,`name`,`description`,`sort_order`) VALUES
('cash',    'Наличными при получении', 'Оплата курьеру или на пункте выдачи', 1),
('card',    'Картой онлайн',           'Visa / MasterCard / МИР',             2),
('sbp',     'СБП (Система Быстрых Платежей)','Оплата по QR-коду',             3),
('invoice', 'Безналичный расчёт',      'Для юридических лиц по счёту',        4);

-- ── Extend orders table ───────────────────────────────────
ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `address_id`         INT UNSIGNED  DEFAULT NULL AFTER `user_id`,
  ADD COLUMN IF NOT EXISTS `delivery_method_id` INT UNSIGNED  DEFAULT NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `payment_method_id`  INT UNSIGNED  DEFAULT NULL AFTER `delivery_method_id`,
  ADD COLUMN IF NOT EXISTS `payment_status`     ENUM('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid' AFTER `payment_method_id`,
  ADD COLUMN IF NOT EXISTS `delivery_cost`      DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `payment_status`,
  ADD COLUMN IF NOT EXISTS `subtotal`           DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `delivery_cost`,
  ADD COLUMN IF NOT EXISTS `tracking_number`    VARCHAR(80)   DEFAULT NULL AFTER `notes`,
  ADD COLUMN IF NOT EXISTS `currency_code`      VARCHAR(5)    NOT NULL DEFAULT 'RUB' AFTER `tracking_number`,
  ADD COLUMN IF NOT EXISTS `email`              VARCHAR(180)  DEFAULT NULL AFTER `currency_code`;

-- ── Blog ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `blog_posts` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`        VARCHAR(180) NOT NULL UNIQUE,
  `title`       VARCHAR(220) NOT NULL,
  `excerpt`     VARCHAR(500) DEFAULT NULL,
  `body`        MEDIUMTEXT   NOT NULL,
  `cover_path`  VARCHAR(255) DEFAULT NULL,
  `author_id`   INT UNSIGNED DEFAULT NULL,
  `is_published`TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_slug` (`slug`),
  KEY `idx_pub`  (`is_published`),
  CONSTRAINT `fk_blog_author` FOREIGN KEY (`author_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Newsletter subscribers ────────────────────────────────
CREATE TABLE IF NOT EXISTS `newsletter` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`      VARCHAR(180) NOT NULL UNIQUE,
  `subscribed_at` DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Contact form messages ─────────────────────────────────
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(120) NOT NULL,
  `email`      VARCHAR(180) NOT NULL,
  `phone`      VARCHAR(30)  DEFAULT NULL,
  `subject`    VARCHAR(200) DEFAULT NULL,
  `message`    TEXT         NOT NULL,
  `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Site settings: extend ─────────────────────────────────
INSERT IGNORE INTO `site_settings` (`key`,`value`) VALUES
('site_phone_tj',     '+992 92 646-46-46'),
('site_email_tj',     'info@autodoc.tj'),
('site_address_tj',   'г. Душанбе, пр. Рудаки, 25'),
('site_telegram',     'https://t.me/autodoc_tj'),
('site_whatsapp',     'https://wa.me/79161234567'),
('site_instagram',    'https://instagram.com/autodoc'),
('default_currency',  'RUB'),
('default_language',  'ru'),
('order_email_admin', 'admin@avtozapchast.ru'),
('smtp_from_name',    'АвтоЗапчасть'),
('smtp_from_email',   'no-reply@avtozapchast.ru'),
('seo_title_suffix',  'АвтоЗапчасть | AutoDoc'),
('meta_description',  'Профессиональный подбор и продажа автозапчастей. Оригинальные и аналоговые детали с гарантией качества.');

-- ============================================================
-- SEED DATA
-- ============================================================

-- Car makes
INSERT IGNORE INTO `car_makes` (`name`,`slug`,`country`) VALUES
('BMW',        'bmw',        'Германия'),
('Mercedes-Benz','mercedes', 'Германия'),
('Audi',       'audi',       'Германия'),
('Volkswagen', 'volkswagen', 'Германия'),
('Toyota',     'toyota',     'Япония'),
('Nissan',     'nissan',     'Япония'),
('Hyundai',    'hyundai',    'Корея'),
('Kia',        'kia',        'Корея'),
('Lada',       'lada',       'Россия'),
('Renault',    'renault',    'Франция'),
('Ford',       'ford',       'США'),
('Chevrolet',  'chevrolet',  'США');

-- Car models (a representative sample)
INSERT IGNORE INTO `car_models` (`make_id`,`name`,`slug`,`year_from`,`year_to`,`body_type`) VALUES
((SELECT id FROM car_makes WHERE slug='bmw'),       '3 Series (E90)', 'bmw-3-e90',     2005, 2012, 'Седан'),
((SELECT id FROM car_makes WHERE slug='bmw'),       '5 Series (F10)', 'bmw-5-f10',     2010, 2017, 'Седан'),
((SELECT id FROM car_makes WHERE slug='bmw'),       'X5 (E70)',       'bmw-x5-e70',    2007, 2013, 'Кроссовер'),
((SELECT id FROM car_makes WHERE slug='mercedes'),  'C-Class (W204)', 'mb-c-w204',     2007, 2014, 'Седан'),
((SELECT id FROM car_makes WHERE slug='mercedes'),  'E-Class (W212)', 'mb-e-w212',     2009, 2016, 'Седан'),
((SELECT id FROM car_makes WHERE slug='audi'),      'A4 (B8)',        'audi-a4-b8',    2008, 2016, 'Седан'),
((SELECT id FROM car_makes WHERE slug='audi'),      'Q5',             'audi-q5',       2008, 2017, 'Кроссовер'),
((SELECT id FROM car_makes WHERE slug='volkswagen'),'Passat B7',      'vw-passat-b7',  2010, 2015, 'Седан'),
((SELECT id FROM car_makes WHERE slug='volkswagen'),'Tiguan',         'vw-tiguan',     2007, 2017, 'Кроссовер'),
((SELECT id FROM car_makes WHERE slug='volkswagen'),'Golf VI',        'vw-golf-6',     2008, 2013, 'Хэтчбек'),
((SELECT id FROM car_makes WHERE slug='toyota'),    'Camry (XV40)',   'toyota-camry-40',2006,2011, 'Седан'),
((SELECT id FROM car_makes WHERE slug='toyota'),    'Corolla E150',   'toyota-corolla-150',2006,2013,'Седан'),
((SELECT id FROM car_makes WHERE slug='toyota'),    'RAV4 III',       'toyota-rav4-3', 2005, 2012, 'Кроссовер'),
((SELECT id FROM car_makes WHERE slug='nissan'),    'Qashqai (J10)',  'nissan-qashqai-j10',2007,2013,'Кроссовер'),
((SELECT id FROM car_makes WHERE slug='nissan'),    'X-Trail (T31)',  'nissan-xtrail-t31',2007,2014,'Кроссовер'),
((SELECT id FROM car_makes WHERE slug='hyundai'),   'Solaris',        'hyundai-solaris',2010,2017, 'Седан'),
((SELECT id FROM car_makes WHERE slug='hyundai'),   'Tucson',         'hyundai-tucson',2015, 2020, 'Кроссовер'),
((SELECT id FROM car_makes WHERE slug='kia'),       'Rio III',        'kia-rio-3',     2011, 2017, 'Седан'),
((SELECT id FROM car_makes WHERE slug='kia'),       'Sportage III',   'kia-sportage-3',2010, 2016, 'Кроссовер'),
((SELECT id FROM car_makes WHERE slug='lada'),      'Vesta',          'lada-vesta',    2015, 2030, 'Седан'),
((SELECT id FROM car_makes WHERE slug='lada'),      'Granta',         'lada-granta',   2011, 2030, 'Седан'),
((SELECT id FROM car_makes WHERE slug='renault'),   'Logan II',       'renault-logan-2',2014,2022, 'Седан'),
((SELECT id FROM car_makes WHERE slug='renault'),   'Duster',         'renault-duster',2010, 2021, 'Кроссовер'),
((SELECT id FROM car_makes WHERE slug='ford'),      'Focus III',      'ford-focus-3',  2011, 2018, 'Седан'),
((SELECT id FROM car_makes WHERE slug='chevrolet'), 'Cruze',          'chevy-cruze',   2008, 2016, 'Седан');

-- VIN sample records
INSERT IGNORE INTO `vin_records` (`vin`,`make_id`,`model_id`,`year`,`engine`,`notes`) VALUES
('WBAFR9C50BC123456', (SELECT id FROM car_makes WHERE slug='bmw'),
   (SELECT id FROM car_models WHERE slug='bmw-5-f10'), 2011, '3.0L N55 петрол','Демо запись'),
('WAUZZZ8K0AA112233', (SELECT id FROM car_makes WHERE slug='audi'),
   (SELECT id FROM car_models WHERE slug='audi-a4-b8'), 2010, '2.0 TFSI', 'Демо запись'),
('JTDBR32E330064577', (SELECT id FROM car_makes WHERE slug='toyota'),
   (SELECT id FROM car_models WHERE slug='toyota-corolla-150'), 2008, '1.6 1ZR-FE', 'Демо запись'),
('XW8ZZZ5NZGG098765', (SELECT id FROM car_makes WHERE slug='volkswagen'),
   (SELECT id FROM car_models WHERE slug='vw-tiguan'), 2014, '2.0 TSI', 'Демо запись'),
('Z0LXXX1234567890', (SELECT id FROM car_makes WHERE slug='lada'),
   (SELECT id FROM car_models WHERE slug='lada-vesta'), 2018, '1.6 16v', 'Демо запись');

-- Sample part-car compatibility (linking some existing parts to models)
INSERT IGNORE INTO `part_compatibility` (`part_id`,`model_id`,`year_from`,`year_to`)
SELECT p.id, m.id, m.year_from, m.year_to
FROM parts p, car_models m
WHERE (p.part_number IN ('BKR6EK','IK20','F026407077') AND m.slug IN ('toyota-camry-40','toyota-corolla-150','toyota-rav4-3'))
   OR (p.part_number IN ('K015561XS','TCK329') AND m.slug IN ('vw-passat-b7','vw-tiguan','vw-golf-6','audi-a4-b8'))
   OR (p.part_number IN ('BP-0001','P50090') AND m.slug IN ('bmw-3-e90','bmw-5-f10','mb-c-w204','mb-e-w212'))
   OR (p.part_number IN ('O0390241','OE648') AND m.slug IN ('hyundai-solaris','kia-rio-3','renault-logan-2'))
   OR (p.part_number IN ('1987432803','F026402330') AND m.slug IN ('ford-focus-3','chevy-cruze','vw-passat-b7'));

-- Sample reviews
INSERT IGNORE INTO `reviews` (`part_id`,`user_id`,`rating`,`title`,`body`,`status`) VALUES
((SELECT id FROM parts WHERE part_number='BKR6EK' LIMIT 1), 4, 5, 'Отличные свечи',
 'Поставил на Camry — двигатель работает заметно ровнее. Расход немного снизился.',          'approved'),
((SELECT id FROM parts WHERE part_number='K015561XS' LIMIT 1), 4, 5, 'Качество на уровне',
 'Настоящий Gates. Поставил, прошёл уже 30 тыс — никаких нареканий.',                       'approved'),
((SELECT id FROM parts WHERE part_number='BP-0001' LIMIT 1), 4, 4, 'Хорошие колодки',
 'Тормозят отлично, пыли немного. Сам пыли быть, но в пределах нормы.',                     'approved'),
((SELECT id FROM parts WHERE part_number='O0390241' LIMIT 1), 4, 5, 'Monroe — это надёжно',
 'Проверенный бренд. Машина едет мягче, на ямах гасит хорошо.',                             'approved'),
((SELECT id FROM parts WHERE part_number='F026407077' LIMIT 1), 4, 4, 'Bosch держит марку',
 'Стандартный качественный фильтр. Менять каждые 10 тыс — самое то.',                       'approved');

-- Sample blog posts
INSERT IGNORE INTO `blog_posts` (`slug`,`title`,`excerpt`,`body`,`is_published`,`author_id`) VALUES
('how-to-choose-brake-pads', 'Как выбрать тормозные колодки',
 'Главные критерии выбора колодок для вашего автомобиля.',
 'Тормозные колодки — один из ключевых элементов безопасности. При выборе обращайте внимание на материал фрикционной накладки, температурный диапазон, уровень шума и совместимость с вашим автомобилем...',
 1, 2),
('oil-change-myths', '5 мифов о замене моторного масла',
 'Развенчиваем популярные заблуждения о масле и сроках замены.',
 'Миф 1: «Чем дороже масло — тем лучше». На самом деле важнее соответствие допускам производителя автомобиля...',
 1, 2),
('vin-decoder-guide', 'Что такое VIN-код и как его расшифровать',
 'Полный гайд по VIN-номеру: как читать, где искать.',
 'VIN (Vehicle Identification Number) — уникальный 17-значный идентификатор. Первые 3 символа — WMI (производитель), следующие 6 — VDS (характеристики), последние 8 — VIS (серийный номер)...',
 1, 2),
('winter-car-prep',         'Подготовка автомобиля к зиме: чек-лист',
 'Что нужно проверить перед зимним сезоном — пошагово.',
 'Антифриз, тормозная жидкость, аккумулятор, шины, дворники, омывайка, освещение — пройдитесь по нашему чек-листу...',
 1, 3);

-- Newsletter sample
INSERT IGNORE INTO `newsletter` (`email`) VALUES ('demo@example.com');
