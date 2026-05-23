-- ============================================================
-- Миграция: VIN-поиск
-- Запуск: mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/migrate_vin.sql
-- ============================================================

SET NAMES utf8mb4;
USE `avtozapchast`;

-- 1. Таблица автомобилей (для совместимости запчастей)
CREATE TABLE IF NOT EXISTS `car_models` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `make`       VARCHAR(100) NOT NULL COMMENT 'Марка: Toyota, BMW, Lada',
  `model`      VARCHAR(150) NOT NULL COMMENT 'Модель: Camry, 3 Series',
  `year_from`  SMALLINT     DEFAULT NULL,
  `year_to`    SMALLINT     DEFAULT NULL,
  `engine`     VARCHAR(100) DEFAULT NULL,
  `body_type`  VARCHAR(80)  DEFAULT NULL,
  `region`     ENUM('ru','eu','jp','us','other') NOT NULL DEFAULT 'other',
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_make_model` (`make`, `model`),
  KEY `idx_region`     (`region`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Совместимость запчастей с автомобилями
CREATE TABLE IF NOT EXISTS `parts_compatibility` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `part_id`      INT UNSIGNED NOT NULL,
  `car_model_id` INT UNSIGNED NOT NULL,
  `notes`        VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_part_car` (`part_id`, `car_model_id`),
  CONSTRAINT `fk_compat_part` FOREIGN KEY (`part_id`) REFERENCES `parts`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_compat_car`  FOREIGN KEY (`car_model_id`) REFERENCES `car_models`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Кэш VIN-запросов (кэш на 30 дней)
CREATE TABLE IF NOT EXISTS `vin_cache` (
  `vin`       CHAR(17)     NOT NULL,
  `result`    JSON         NOT NULL,
  `source`    VARCHAR(20)  NOT NULL DEFAULT 'nhtsa',
  `cached_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`vin`),
  KEY `idx_cached_at` (`cached_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Настройки VIN в site_settings
INSERT IGNORE INTO `site_settings` (`key`, `value`) VALUES
('vin_search_enabled', '1'),
('vin_api_provider',   'nhtsa'),
('vin_api_url',        ''),
('vin_api_key',        ''),
('vin_api_timeout',    '8');

-- 5. Примеры автомобилей (российский, японский, европейский рынки)
INSERT IGNORE INTO `car_models` (`make`, `model`, `year_from`, `year_to`, `engine`, `body_type`, `region`) VALUES
-- Россия
('Lada (ВАЗ)', 'Priora',   2007, 2018, '1.6L 16V', 'Седан', 'ru'),
('Lada (ВАЗ)', 'Granta',   2011, NULL,  '1.6L',     'Седан', 'ru'),
('Lada (ВАЗ)', 'Vesta',    2015, NULL,  '1.6L',     'Седан', 'ru'),
('Lada (ВАЗ)', 'Niva',     1977, NULL,  '1.7L',     'SUV',   'ru'),
('UAZ',         'Patriot',  2005, NULL,  '2.7L',     'SUV',   'ru'),
('GAZ',         'Gazelle',  1994, NULL,  '2.5L',     'Фургон','ru'),
-- Япония
('Toyota', 'Camry',    1982, NULL, '2.5L',   'Седан',  'jp'),
('Toyota', 'Corolla',  1966, NULL, '1.6L',   'Седан',  'jp'),
('Toyota', 'Land Cruiser', 1951, NULL, '4.0L', 'SUV',  'jp'),
('Toyota', 'RAV4',     1994, NULL, '2.0L',   'SUV',    'jp'),
('Honda',  'Civic',    1972, NULL, '1.5L',   'Седан',  'jp'),
('Honda',  'CR-V',     1995, NULL, '1.5L',   'SUV',    'jp'),
('Nissan', 'Qashqai',  2006, NULL, '1.6L',   'SUV',    'jp'),
('Nissan', 'X-Trail',  2000, NULL, '2.0L',   'SUV',    'jp'),
('Mazda',  'CX-5',     2011, NULL, '2.0L',   'SUV',    'jp'),
('Mazda',  'Mazda6',   2002, NULL, '2.0L',   'Седан',  'jp'),
-- Европа
('BMW',        '3 Series', 1975, NULL, '2.0L',  'Седан', 'eu'),
('BMW',        '5 Series', 1972, NULL, '2.0L',  'Седан', 'eu'),
('BMW',        'X5',       1999, NULL, '3.0L',  'SUV',   'eu'),
('Mercedes-Benz','C-Class', 1993, NULL, '1.6L', 'Седан', 'eu'),
('Mercedes-Benz','E-Class', 1953, NULL, '2.0L', 'Седан', 'eu'),
('Volkswagen', 'Golf',     1974, NULL, '1.4L',  'Хэтчбек','eu'),
('Volkswagen', 'Passat',   1973, NULL, '1.8L',  'Седан', 'eu'),
('Volkswagen', 'Tiguan',   2007, NULL, '1.4L',  'SUV',   'eu'),
('Audi',       'A4',       1994, NULL, '2.0L',  'Седан', 'eu'),
('Audi',       'Q5',       2008, NULL, '2.0L',  'SUV',   'eu'),
('Renault',    'Logan',    2004, NULL, '1.6L',  'Седан', 'eu'),
('Renault',    'Duster',   2010, NULL, '2.0L',  'SUV',   'eu'),
('Hyundai',    'Solaris',  2010, NULL, '1.6L',  'Седан', 'eu'),
('Hyundai',    'Tucson',   2004, NULL, '2.0L',  'SUV',   'eu'),
('Kia',        'Rio',      1999, NULL, '1.6L',  'Седан', 'eu'),
('Kia',        'Sportage', 1993, NULL, '2.0L',  'SUV',   'eu');

-- Проверка
SELECT 'car_models' AS tbl, COUNT(*) AS rows FROM car_models;
SELECT 'vin_cache'  AS tbl, COUNT(*) AS rows FROM vin_cache;
SELECT `key`, `value` FROM site_settings WHERE `key` LIKE 'vin_%';
