-- Reviews v2: shop reviews + featured flag for «О нас» showcase
-- Run: mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/migrate_reviews_v2.sql

-- 1. Featured flag on product reviews (control which appear on About page)
ALTER TABLE `product_reviews`
    ADD COLUMN IF NOT EXISTS `is_featured` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`;

-- 2. Shop / company reviews (about the store overall)
CREATE TABLE IF NOT EXISTS `shop_reviews` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED NOT NULL,
    `rating`      TINYINT UNSIGNED NOT NULL DEFAULT 5,
    `comment`     TEXT NOT NULL,
    `status`      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user` (`user_id`),
    KEY `idx_status`   (`status`),
    KEY `idx_featured` (`is_featured`),
    CONSTRAINT `fk_shopreview_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'Reviews v2 migration complete' AS status;
