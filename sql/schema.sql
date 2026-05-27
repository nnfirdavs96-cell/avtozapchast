-- АвтоЗапчасть Database Schema
-- MySQL 5.7+ / MariaDB 10.3+

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE DATABASE IF NOT EXISTS `avtozapchast`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `avtozapchast`;

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(80)      NOT NULL,
  `email`         VARCHAR(180)     NOT NULL UNIQUE,
  `password_hash` VARCHAR(255)     NOT NULL,
  `role`          ENUM('buyer','admin','manager','superadmin') NOT NULL DEFAULT 'buyer',
  `phone`         VARCHAR(30)      DEFAULT NULL,
  `created_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active`     TINYINT(1)       NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_email`  (`email`),
  KEY `idx_role`   (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: categories
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(120)  NOT NULL,
  `slug`        VARCHAR(120)  NOT NULL UNIQUE,
  `parent_id`   INT UNSIGNED  DEFAULT NULL,
  `description` TEXT          DEFAULT NULL,
  `image_path`  VARCHAR(255)  DEFAULT NULL,
  `sort_order`  INT           NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_slug`   (`slug`),
  CONSTRAINT `fk_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: brands
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `brands` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100)  NOT NULL,
  `slug`        VARCHAR(100)  NOT NULL UNIQUE,
  `logo_path`   VARCHAR(255)  DEFAULT NULL,
  `country`     VARCHAR(80)   DEFAULT NULL,
  `description` TEXT          DEFAULT NULL,
  `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: parts
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `parts` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `part_number` VARCHAR(80)     NOT NULL UNIQUE,
  `name`        VARCHAR(220)    NOT NULL,
  `description` TEXT            DEFAULT NULL,
  `brand_id`    INT UNSIGNED    NOT NULL,
  `category_id` INT UNSIGNED    NOT NULL,
  `price`       DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `stock`       INT             NOT NULL DEFAULT 0,
  `weight`      DECIMAL(8,3)    DEFAULT NULL COMMENT 'kg',
  `dimensions`  VARCHAR(80)     DEFAULT NULL COMMENT 'LxWxH mm',
  `images`      JSON            DEFAULT NULL,
  `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
  `created_by`  INT UNSIGNED    DEFAULT NULL,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_part_number` (`part_number`),
  KEY `idx_brand`    (`brand_id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_price`    (`price`),
  KEY `idx_active`   (`is_active`),
  CONSTRAINT `fk_part_brand`    FOREIGN KEY (`brand_id`)    REFERENCES `brands`     (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_part_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_part_creator`  FOREIGN KEY (`created_by`)  REFERENCES `users`      (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: orders
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`          INT UNSIGNED  NOT NULL,
  `status`           ENUM('pending','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `total_amount`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `shipping_cost`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `shipping_address` TEXT          NOT NULL,
  `notes`            TEXT          DEFAULT NULL,
  `payment_method`   VARCHAR(50)   NOT NULL DEFAULT 'cash_on_delivery',
  `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user`   (`user_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_order_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: order_items
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_items` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `order_id`   INT UNSIGNED  NOT NULL,
  `part_id`    INT UNSIGNED  NOT NULL,
  `quantity`   INT           NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_part`  (`part_id`),
  CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `orders`     (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_oi_part`  FOREIGN KEY (`part_id`)  REFERENCES `parts`      (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: cart
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cart` (
  `id`       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`  INT UNSIGNED  NOT NULL,
  `part_id`  INT UNSIGNED  NOT NULL,
  `quantity` INT           NOT NULL DEFAULT 1,
  `added_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_part` (`user_id`, `part_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cart_part` FOREIGN KEY (`part_id`) REFERENCES `parts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: site_settings
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `site_settings` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `key`        VARCHAR(80)   NOT NULL UNIQUE,
  `value`      TEXT          DEFAULT NULL,
  `updated_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: delivery_zones (доставка по городам, способ — такси)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `delivery_zones` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `city`          VARCHAR(120)  NOT NULL,
  `cost`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `delivery_days` VARCHAR(40)   DEFAULT NULL,
  `is_active`     TINYINT(1)    NOT NULL DEFAULT 1,
  `sort_order`    INT           NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_city` (`city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Users
-- All passwords = "Password123!"
-- Hash: password_hash('Password123!', PASSWORD_DEFAULT) using bcrypt cost 12
-- Using a known valid bcrypt hash for "Password123!"
-- All passwords = "Password123!"
-- Hash: password_hash('Password123!', PASSWORD_BCRYPT, ['cost'=>10])
INSERT INTO `users` (`username`, `email`, `password_hash`, `role`, `phone`, `is_active`) VALUES
('superadmin',  'superadmin@avtozapchast.ru',  '$2y$10$lkgF10iXJBq2uMatK.L19O3tR6Y0/cbHs9ZdmmpTcX7HJt7RfZ4WG', 'superadmin', '+7 (999) 000-00-00', 1),
('admin1',      'admin@avtozapchast.ru',        '$2y$10$lkgF10iXJBq2uMatK.L19O3tR6Y0/cbHs9ZdmmpTcX7HJt7RfZ4WG', 'admin',      '+7 (999) 111-11-11', 1),
('manager1',    'manager@avtozapchast.ru',      '$2y$10$lkgF10iXJBq2uMatK.L19O3tR6Y0/cbHs9ZdmmpTcX7HJt7RfZ4WG', 'manager',    '+7 (999) 222-22-22', 1),
('buyer1',      'buyer@avtozapchast.ru',        '$2y$10$lkgF10iXJBq2uMatK.L19O3tR6Y0/cbHs9ZdmmpTcX7HJt7RfZ4WG', 'buyer',      '+7 (999) 333-33-33', 1);

-- Categories
INSERT INTO `categories` (`name`, `slug`, `parent_id`, `description`, `sort_order`, `is_active`) VALUES
('Двигатель',          'dvigatel',           NULL, 'Детали и запчасти для двигателя автомобиля', 1, 1),
('Тормозная система',  'tormoznaya-sistema',  NULL, 'Тормозные колодки, диски, суппорты и шланги', 2, 1),
('Подвеска',           'podveska',           NULL, 'Амортизаторы, пружины, сайлентблоки, шаровые', 3, 1),
('Электрика',          'elektrika',          NULL, 'Генераторы, стартеры, свечи, датчики', 4, 1),
('Кузов',              'kuzov',              NULL, 'Бамперы, крылья, капоты, стёкла', 5, 1),
('Трансмиссия',        'transmissiya',       NULL, 'АКПП, МКПП, сцепление, карданный вал', 6, 1),
('Фильтры',            'filtry',             1,    'Масляные, воздушные, топливные фильтры', 7, 1),
('Ремни и цепи',       'remni-i-tsepi',      1,    'Ремни ГРМ, поликлиновые ремни, цепи', 8, 1),
('Свечи зажигания',    'svechi-zazgiganiya', 4,    'Свечи зажигания и накаливания', 9, 1),
('Амортизаторы',       'amortizatory',       3,    'Газомасляные и масляные амортизаторы', 10, 1);

-- Brands
INSERT INTO `brands` (`name`, `slug`, `country`, `description`, `is_active`) VALUES
('Bosch',   'bosch',  'Германия',  'Мировой лидер в производстве автомобильных компонентов', 1),
('NGK',     'ngk',    'Япония',    'Ведущий производитель свечей зажигания и кислородных датчиков', 1),
('Gates',   'gates',  'США',       'Специализируется на ремнях ГРМ и системах привода', 1),
('SKF',     'skf',    'Швеция',    'Мировой лидер в производстве подшипников и уплотнений', 1),
('Febi',    'febi',   'Германия',  'Оригинальное качество запчастей для европейских автомобилей', 1),
('Brembo',  'brembo', 'Италия',    'Премиальные тормозные системы и компоненты', 1),
('Denso',   'denso',  'Япония',    'Крупнейший производитель автокомпонентов в Японии', 1),
('Monroe',  'monroe', 'Бельгия',   'Специалист в области амортизаторов и подвески', 1);

-- Parts (20 sample parts)
INSERT INTO `parts` (`part_number`, `name`, `description`, `brand_id`, `category_id`, `price`, `stock`, `weight`, `dimensions`, `images`, `is_active`, `created_by`) VALUES
('0280218116',   'Датчик массового расхода воздуха BOSCH', 'Оригинальный датчик MAF для автомобилей VAG. Обеспечивает точное измерение воздушного потока для оптимального состава смеси.', 1, 4, 4850.00, 15, 0.180, '90x45x38',   '[]', 1, 1),
('BKR6EK',       'Свеча зажигания NGK BKR6EK',            'Иридиевая свеча зажигания NGK с увеличенным ресурсом. Подходит для большинства бензиновых двигателей.',                             2, 9, 620.00,  200, 0.045, '19x19x55',   '[]', 1, 1),
('K015561XS',    'Ремень ГРМ Gates PowerGrip',            'Высококачественный ремень ГРМ Gates для двигателей 1.6-2.0 TDI. Усиленная конструкция из арамидного волокна.',                      3, 8, 2350.00, 45,  0.320, '870x25',     '[]', 1, 1),
('6205-2RS1C3',  'Подшипник SKF 6205-2RS1/C3',            'Радиальный шарикоподшипник SKF с двусторонним уплотнением. Повышенный зазор C3 для условий высоких температур.',                    4, 3, 1180.00, 80,  0.215, '52x25x15',   '[]', 1, 1),
('BP-0001',      'Тормозные колодки Brembo P28025',       'Высококачественные тормозные колодки Brembo для передней оси. Низкий уровень пыли и шума, стабильные характеристики.',              6, 2, 3200.00, 35,  0.580, '155x65x18',  '[]', 1, 1),
('O0390241',     'Амортизатор Monroe Original',           'Газомасляный амортизатор Monroe для задней оси. Обеспечивает комфортную и безопасную езду.',                                         8, 10, 5600.00, 22,  1.850, '350x52',     '[]', 1, 1),
('F026407077',   'Масляный фильтр Bosch P7077',           'Масляный фильтр Bosch высокого качества. Эффективная фильтрация моторного масла, надёжный клапан обратного тока.',                  1, 7, 380.00,  150, 0.120, '76x66',      '[]', 1, 1),
('IK20',         'Свеча зажигания NGK Iridium IX',        'Иридиево-платиновая свеча NGK с центральным электродом 0.6 мм. Улучшенное воспламенение, экономия топлива.',                        2, 9, 890.00,  120, 0.042, '19x19x55',   '[]', 1, 1),
('TCK329',       'Комплект ГРМ Gates TCK329',             'Полный комплект ГРМ Gates: ремень, ролики натяжителя. Для двигателей Volkswagen, Audi, Skoda 1.9 TDI.',                             3, 8, 4750.00, 18,  0.680, '870x25',     '[]', 1, 1),
('VKBA3648',     'Подшипник ступицы колеса SKF',          'Двухрядный угловой подшипник SKF в сборе. Для передней оси. Полный узел со ступицей.',                                              4, 3, 7800.00, 12,  1.450, '85x42',      '[]', 1, 1),
('32311FEBI',    'Сайлентблок рычага Febi',               'Сайлентблок переднего нижнего рычага Febi. Высокотвёрдая резина, усиленный металлический корпус.',                                  5, 3, 650.00,  95,  0.095, '55x42x38',   '[]', 1, 1),
('18723FEBI',    'Термостат Febi',                        'Термостат Febi для системы охлаждения. Точная температура открытия 87°C. Включает новую прокладку.',                                5, 1, 1250.00, 40,  0.185, '75x65x50',   '[]', 1, 1),
('P50090',       'Тормозные колодки Brembo P50090',       'Тормозные колодки Brembo для задней оси. Синтетический состав, длительный ресурс.',                                                  6, 2, 2450.00, 50,  0.420, '105x55x17',  '[]', 1, 1),
('F026402330',   'Топливный фильтр Bosch F026402330',     'Топливный фильтр высокого давления для дизельных двигателей Bosch. Фильтрация до 5 мкм.',                                           1, 7, 1890.00, 30,  0.250, '145x90',     '[]', 1, 1),
('E500L18B17A',  'Свеча накаливания Bosch',               'Свеча накаливания Bosch для дизельных двигателей. Быстрый прогрев за 2 секунды, ресурс 80 000 км.',                                 1, 9, 740.00,  85,  0.065, '10x115',     '[]', 1, 1),
('DN0SD264',     'Генератор Denso 100A',                  'Генератор Denso 100А для японских автомобилей. Встроенный регулятор напряжения, повышенная надёжность.',                            7, 4, 12500.00, 8, 4.200, '170x135x85', '[]', 1, 1),
('128501FEBI',   'Термостат Mahle/Febi',                  'Термостат для системы охлаждения BMW, Mercedes. Карта температур 80-92°C.',                                                         5, 1, 2100.00, 25,  0.210, '90x70x60',   '[]', 1, 1),
('OE648',        'Амортизатор Monroe OESpectrum',         'Газомасляный амортизатор Monroe OESpectrum для передней оси. Технология Reflex обеспечивает оптимальный контроль.',                 8, 10, 6200.00, 16,  1.920, '360x55',     '[]', 1, 1),
('VKM31010',     'Ролик натяжителя ремня SKF',            'Ролик натяжителя поликлинового ремня SKF. Встроенный подшипник, антикоррозионное покрытие.',                                        4, 8, 1350.00, 60,  0.280, '65x30',      '[]', 1, 1),
('1987432803',   'Воздушный фильтр Bosch S0803',          'Высококачественный воздушный фильтр Bosch. Эффективность фильтрации 99.9%, увеличенный ресурс замены.',                             1, 7, 450.00,  110, 0.090, '300x195x30', '[]', 1, 1);

-- Site settings
INSERT INTO `site_settings` (`key`, `value`) VALUES
('site_name',     'AvtoDoc'),
('site_email',    'info@avtozapchast.ru'),
('site_phone',    '+992 92 646-46-46'),
('site_address',  'г. Худжанд, 19 мкр, дом 30'),
('site_currency', 'SM'),
('items_per_page','12');
