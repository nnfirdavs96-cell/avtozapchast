<?php
/**
 * Восстановление пароля по email — таблица одноразовых токенов.
 *
 * Как это работает: пользователь просит сброс → создаём случайный токен, в БД
 * храним ТОЛЬКО его sha256-хеш (как пароль — сырой токен в базе не лежит),
 * шлём на почту ссылку с сырым токеном. По ссылке проверяем хеш, срок и что он
 * не использован — и даём задать новый пароль. Токен одноразовый и живёт 1 час.
 *
 * Запуск (повторный безопасен):
 *   php sql/migrate_password_reset.php
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Только из командной строки.\n"); }

require_once dirname(__DIR__) . '/config/config.php';

$db = getDB();

echo "Восстановление пароля · токены сброса\n";
echo "База: " . DB_NAME . "\n";
echo str_repeat('-', 60) . "\n";

$db->exec(
    "CREATE TABLE IF NOT EXISTS `password_resets` (
      `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `user_id`    INT UNSIGNED NOT NULL,
      `token_hash` CHAR(64)     NOT NULL COMMENT 'sha256 от сырого токена',
      `expires_at` DATETIME     NOT NULL,
      `used_at`    DATETIME     DEFAULT NULL,
      `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uk_token` (`token_hash`),
      KEY `idx_user` (`user_id`),
      KEY `idx_expires` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
echo "  [OK] таблица password_resets\n";

echo str_repeat('-', 60) . "\n";
echo "Готово. Ссылка «Забыли пароль?» на странице входа теперь рабочая.\n";
echo "Для отправки писем должна быть настроена почта (Настройки → Почта SMTP).\n";
