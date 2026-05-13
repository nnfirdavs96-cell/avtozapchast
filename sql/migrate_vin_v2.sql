-- VIN search history per user + part analogs
-- Run: mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/migrate_vin_v2.sql

CREATE TABLE IF NOT EXISTS `vin_search_history` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED NOT NULL,
    `vin`        CHAR(17) NOT NULL,
    `make`       VARCHAR(100) DEFAULT NULL,
    `model`      VARCHAR(100) DEFAULT NULL,
    `year`       SMALLINT     DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_created` (`user_id`, `created_at`),
    INDEX `idx_vin` (`vin`),
    CONSTRAINT `fk_vinhist_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `part_analogs` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `part_id`        INT UNSIGNED NOT NULL,
    `analog_part_id` INT UNSIGNED NOT NULL,
    `confidence`     ENUM('exact','high','medium','low') DEFAULT 'high',
    `notes`          VARCHAR(255) DEFAULT NULL,
    `created_at`     DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_analog_pair` (`part_id`, `analog_part_id`),
    INDEX `idx_analog_part` (`analog_part_id`),
    CONSTRAINT `fk_analog_part`
        FOREIGN KEY (`part_id`) REFERENCES `parts`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_analog_analog`
        FOREIGN KEY (`analog_part_id`) REFERENCES `parts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
