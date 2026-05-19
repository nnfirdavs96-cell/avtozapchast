-- Добавляет колонку image_path в categories, если её ещё нет.
-- Безопасно запускать повторно (идемпотентно).
SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'categories'
    AND COLUMN_NAME  = 'image_path'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE `categories` ADD COLUMN `image_path` VARCHAR(255) DEFAULT NULL AFTER `description`',
  'SELECT "image_path already exists" AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
