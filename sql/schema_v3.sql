-- ============================================================
-- schema_v3.sql — Backup tracking + warehouse_api_log fix
-- Run after schema_v2.sql
-- ============================================================

SET NAMES utf8mb4;

-- --------------------------------------------------------
-- Fix warehouse_api_log: add columns used by warehouse.php UI
-- (schema_v2 had different column names than the PHP code)
-- --------------------------------------------------------
ALTER TABLE `warehouse_api_log`
  ADD COLUMN IF NOT EXISTS `request_url`    TEXT         DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `response_code`  SMALLINT     DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `response_body`  TEXT         DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `success`        TINYINT(1)   NOT NULL DEFAULT 0;

-- --------------------------------------------------------
-- Table: backups (tracks SQL backup files created via UI/cron)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `backups` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `filename`   VARCHAR(220) NOT NULL,
  `size_bytes` INT UNSIGNED NOT NULL DEFAULT 0,
  `tables`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_by` INT UNSIGNED DEFAULT NULL COMMENT 'user_id or NULL for cron',
  `note`       VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Site settings: currency auto-fetch toggle
-- --------------------------------------------------------
INSERT IGNORE INTO `site_settings` (`key`, `value`) VALUES
  ('currency_auto_fetch',      '0'),
  ('currency_last_fetch',      '');
