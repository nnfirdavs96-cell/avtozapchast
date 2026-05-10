-- Schema v4: sliders table + blog image_path column
-- Run after schema_v3.sql

-- Homepage sliders
CREATE TABLE IF NOT EXISTS `sliders` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `title`      VARCHAR(255)  NOT NULL DEFAULT '',
  `subtitle`   VARCHAR(255)  NOT NULL DEFAULT '',
  `image_url`  VARCHAR(500)  NOT NULL,
  `link_url`   VARCHAR(500)  NOT NULL DEFAULT '',
  `sort_order` SMALLINT      NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sort` (`sort_order`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add image_path to blog_posts if missing
ALTER TABLE `blog_posts`
  ADD COLUMN IF NOT EXISTS `image_path` VARCHAR(255) DEFAULT NULL AFTER `body_en`;

-- Add updated_at to blog_posts if missing
ALTER TABLE `blog_posts`
  ADD COLUMN IF NOT EXISTS `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;
