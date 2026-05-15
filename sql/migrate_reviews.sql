-- Product reviews (authorized users only, pre-moderated)
-- Run: mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/migrate_reviews.sql

CREATE TABLE IF NOT EXISTS `product_reviews` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `part_id`    INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED NOT NULL,
    `rating`     TINYINT UNSIGNED NOT NULL DEFAULT 5,
    `comment`    TEXT NOT NULL,
    `status`     ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_part_user` (`part_id`, `user_id`),
    KEY `idx_part`   (`part_id`),
    KEY `idx_user`   (`user_id`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_review_part` FOREIGN KEY (`part_id`) REFERENCES `parts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_review_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'Reviews migration complete' AS status;
