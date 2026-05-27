-- Добавляет колонку country в delivery_zones и меняет уникальный ключ.
-- Идемпотентно — можно запускать многократно.

-- 1. Добавить колонку country (если не существует)
SET @c1 = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_zones' AND COLUMN_NAME = 'country');
SET @s1 = IF(@c1 = 0,
    'ALTER TABLE delivery_zones ADD COLUMN country VARCHAR(100) NOT NULL DEFAULT ''Таджикистан'' AFTER city',
    'SELECT 1 AS noop');
PREPARE st1 FROM @s1; EXECUTE st1; DEALLOCATE PREPARE st1;

-- 2. Удалить старый уникальный ключ по (city) — если существует
SET @c2 = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_zones'
    AND INDEX_NAME = 'uk_city' AND NON_UNIQUE = 0);
SET @s2 = IF(@c2 > 0,
    'ALTER TABLE delivery_zones DROP INDEX uk_city',
    'SELECT 1 AS noop');
PREPARE st2 FROM @s2; EXECUTE st2; DEALLOCATE PREPARE st2;

-- 3. Добавить новый уникальный ключ по (city, country) — если не существует
SET @c3 = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_zones'
    AND INDEX_NAME = 'uk_city_country');
SET @s3 = IF(@c3 = 0,
    'ALTER TABLE delivery_zones ADD UNIQUE KEY uk_city_country (city, country)',
    'SELECT 1 AS noop');
PREPARE st3 FROM @s3; EXECUTE st3; DEALLOCATE PREPARE st3;
