-- Phone (SMS) registration & login support. Idempotent — safe to run repeatedly.
-- NOTE: the app also applies this automatically at runtime (ensurePhoneAuthSchema),
-- so running this file by hand is optional; it is provided for explicit deploys.

-- 1) email becomes optional (phone-only accounts have no email).
--    The UNIQUE index is kept — MySQL permits multiple NULLs in a unique index.
SET @n := (SELECT IS_NULLABLE FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email');
SET @s := IF(@n = 'NO',
  'ALTER TABLE `users` MODIFY `email` VARCHAR(180) NULL',
  'SELECT "email already nullable" AS info');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 2) password_hash becomes optional (SMS-only accounts have no password).
SET @n := (SELECT IS_NULLABLE FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password_hash');
SET @s := IF(@n = 'NO',
  'ALTER TABLE `users` MODIFY `password_hash` VARCHAR(255) NULL',
  'SELECT "password_hash already nullable" AS info');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 3) phone_e164 — normalized digits (country code + number), the canonical login key.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'phone_e164');
SET @s := IF(@c = 0,
  'ALTER TABLE `users` ADD COLUMN `phone_e164` VARCHAR(20) DEFAULT NULL AFTER `phone`',
  'SELECT "phone_e164 exists" AS info');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_phone_e164');
SET @s := IF(@c = 0,
  'CREATE INDEX `idx_phone_e164` ON `users` (`phone_e164`)',
  'SELECT "idx_phone_e164 exists" AS info');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 4) One-time SMS codes.
CREATE TABLE IF NOT EXISTS `phone_otp` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `phone`       VARCHAR(20)  NOT NULL,
  `code_hash`   VARCHAR(255) NOT NULL,
  `purpose`     VARCHAR(20)  NOT NULL DEFAULT 'login',
  `attempts`    TINYINT      NOT NULL DEFAULT 0,
  `expires_at`  DATETIME     NOT NULL,
  `consumed_at` DATETIME     DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
