-- Добавляет колонку logo_path в brands, если её ещё нет. Идемпотентно.
SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'brands'
    AND COLUMN_NAME  = 'logo_path'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE `brands` ADD COLUMN `logo_path` VARCHAR(255) DEFAULT NULL AFTER `slug`',
  'SELECT "logo_path already exists" AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
