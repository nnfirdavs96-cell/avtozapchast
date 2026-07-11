-- ============================================================
-- Миграция: постоянная библиотека каталога Parts-Catalogs
-- В отличие от partsapi_kv_cache (TTL 24ч, авто-протухание), эти таблицы
-- копят историю без удаления — каждый реальный (не из TTL-кэша) ответ
-- архивируется, чтобы суперадмин мог выгрузить накопленное.
-- Запуск: mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/migrate_catalog_library.sql
-- ============================================================

SET NAMES utf8mb4;
USE `avtozapchast`;

-- 1. Карточки авто (VIN-путь и путь «по параметрам» пишут сюда одинаково)
CREATE TABLE IF NOT EXISTS `catalog_library_cars` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `catalog_id` VARCHAR(64)  NOT NULL,
  `car_id`     VARCHAR(64)  NOT NULL,
  `vin`        CHAR(17)     DEFAULT NULL,
  `brand`      VARCHAR(120) DEFAULT NULL,
  `criteria`   VARCHAR(255) DEFAULT NULL,
  `attrs_json` MEDIUMTEXT,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_car` (`catalog_id`, `car_id`),
  KEY `idx_vin` (`vin`),
  KEY `idx_brand` (`brand`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Дерево узлов конкретного авто
CREATE TABLE IF NOT EXISTS `catalog_library_nodes` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `catalog_id`  VARCHAR(64) NOT NULL,
  `car_id`      VARCHAR(64) NOT NULL,
  `nodes_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `nodes_json`  MEDIUMTEXT,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_car` (`catalog_id`, `car_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Схема узла + детали (взрыв-схема, хотспоты, список деталей)
CREATE TABLE IF NOT EXISTS `catalog_library_schemes` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `catalog_id`     VARCHAR(64)  NOT NULL,
  `car_id`         VARCHAR(64)  NOT NULL,
  `group_id`       VARCHAR(64)  NOT NULL,
  `group_name`     VARCHAR(255) DEFAULT NULL,
  `img`            VARCHAR(500) DEFAULT NULL,
  `caption`        VARCHAR(255) DEFAULT NULL,
  `parts_count`    INT UNSIGNED NOT NULL DEFAULT 0,
  `hotspots_json`  MEDIUMTEXT,
  `parts_json`     MEDIUMTEXT,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_scheme` (`catalog_id`, `car_id`, `group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Проверка
SELECT 'catalog_library_cars'    AS tbl, COUNT(*) AS rows FROM catalog_library_cars;
SELECT 'catalog_library_nodes'   AS tbl, COUNT(*) AS rows FROM catalog_library_nodes;
SELECT 'catalog_library_schemes' AS tbl, COUNT(*) AS rows FROM catalog_library_schemes;
