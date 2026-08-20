-- ═══════════════════════════════════════════════════════════════════════════
-- Маркетплейс, Фаза 1 — продавцы и владение товарами.
-- Только ДОБАВЛЕНИЯ (роль, таблица, колонки) — существующие данные не трогаются.
-- Существующий каталог остаётся видимым (moderation_status='active' по умолчанию).
-- Запуск один раз:  mysql -u USER -p БАЗА < sql/marketplace_phase1.sql
-- ═══════════════════════════════════════════════════════════════════════════

-- 1) Роль продавца в users.role
ALTER TABLE `users`
  MODIFY `role` ENUM('buyer','seller','admin','manager','superadmin')
  NOT NULL DEFAULT 'buyer';

-- 2) Магазины продавцов
CREATE TABLE IF NOT EXISTS `sellers` (
  `id`                 INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `user_id`            INT UNSIGNED   NOT NULL,
  `shop_name`          VARCHAR(150)   NOT NULL,
  `slug`               VARCHAR(160)   NOT NULL,
  `phone`              VARCHAR(32)    DEFAULT NULL,
  `description`        TEXT           DEFAULT NULL,
  `logo`               VARCHAR(255)   DEFAULT NULL,
  `status`             ENUM('pending','approved','blocked') NOT NULL DEFAULT 'pending',
  `commission_percent` DECIMAL(5,2)   NOT NULL DEFAULT 0.00,
  `reject_reason`      VARCHAR(255)   DEFAULT NULL,
  `created_at`         DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user` (`user_id`),
  UNIQUE KEY `uk_slug` (`slug`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) Владение товаром + модерация листингов в parts
--    seller_id NULL       = товар нашего каталога (админ), виден как раньше.
--    seller_id = продавец = товар продавца, показывается только при 'active'.
ALTER TABLE `parts`
  ADD COLUMN `seller_id`         INT UNSIGNED DEFAULT NULL AFTER `id`,
  ADD COLUMN `moderation_status` ENUM('draft','pending','active','rejected')
             NOT NULL DEFAULT 'active' AFTER `is_active`,
  ADD COLUMN `reject_reason`     VARCHAR(255) DEFAULT NULL,
  ADD KEY `idx_seller` (`seller_id`),
  ADD KEY `idx_moderation` (`moderation_status`);
