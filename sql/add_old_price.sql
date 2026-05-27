-- Добавляет колонку old_price (старая цена до скидки) в таблицу parts.
-- Если old_price > price → товар «со скидкой».
-- Идемпотентно — можно запускать многократно.

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parts' AND COLUMN_NAME = 'old_price');
SET @s = IF(@c = 0,
    'ALTER TABLE parts ADD COLUMN old_price DECIMAL(10,2) DEFAULT NULL AFTER price',
    'SELECT 1 AS noop');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
