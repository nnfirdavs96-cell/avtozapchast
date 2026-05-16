-- AutoEuro API v2 integration settings
-- Run after schema.sql (requires site_settings and warehouse_api_log tables)

-- AutoEuro API settings in site_settings
INSERT IGNORE INTO `site_settings` (`key`, `value`, `updated_at`) VALUES
  ('autoeuro_enabled',      '0', NOW()),
  ('autoeuro_api_key',      '',  NOW()),
  ('autoeuro_delivery_key', '',  NOW()),
  ('autoeuro_payer_key',    '',  NOW());

-- Create warehouse_api_log if it doesn't exist yet
CREATE TABLE IF NOT EXISTS `warehouse_api_log` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `action`        VARCHAR(80)  NOT NULL DEFAULT '',
  `request_url`   VARCHAR(500) NOT NULL DEFAULT '',
  `response_code` SMALLINT     NOT NULL DEFAULT 0,
  `response_body` TEXT         DEFAULT NULL,
  `success`       TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
