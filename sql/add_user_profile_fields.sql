-- Добавляет в таблицу users поля аватара и адреса доставки. Идемпотентно.
-- Можно запускать многократно — существующие колонки не трогаются.

-- avatar_path
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'avatar_path');
SET @s := IF(@c = 0,
  'ALTER TABLE `users` ADD COLUMN `avatar_path` VARCHAR(255) DEFAULT NULL AFTER `phone`',
  'SELECT "avatar_path exists" AS info');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- first_name
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'first_name');
SET @s := IF(@c = 0,
  'ALTER TABLE `users` ADD COLUMN `first_name` VARCHAR(80) DEFAULT NULL AFTER `avatar_path`',
  'SELECT "first_name exists" AS info');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- last_name
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'last_name');
SET @s := IF(@c = 0,
  'ALTER TABLE `users` ADD COLUMN `last_name` VARCHAR(80) DEFAULT NULL AFTER `first_name`',
  'SELECT "last_name exists" AS info');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- address
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'address');
SET @s := IF(@c = 0,
  'ALTER TABLE `users` ADD COLUMN `address` VARCHAR(255) DEFAULT NULL AFTER `last_name`',
  'SELECT "address exists" AS info');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- city
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'city');
SET @s := IF(@c = 0,
  'ALTER TABLE `users` ADD COLUMN `city` VARCHAR(120) DEFAULT NULL AFTER `address`',
  'SELECT "city exists" AS info');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- zip_code
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'zip_code');
SET @s := IF(@c = 0,
  'ALTER TABLE `users` ADD COLUMN `zip_code` VARCHAR(20) DEFAULT NULL AFTER `city`',
  'SELECT "zip_code exists" AS info');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- country
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'country');
SET @s := IF(@c = 0,
  'ALTER TABLE `users` ADD COLUMN `country` VARCHAR(80) DEFAULT NULL AFTER `zip_code`',
  'SELECT "country exists" AS info');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
