-- Добавляем поле sort_order в таблицу brands (идемпотентно)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'brands'
             AND COLUMN_NAME = 'sort_order');
SET @s := IF(@c = 0,
    'ALTER TABLE `brands` ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0 AFTER `is_active`',
    'SELECT "sort_order already exists" AS info');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
