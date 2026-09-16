<?php
/**
 * Журнал отправленных писем (e-mail).
 *
 * Отправка идёт через SMTP (см. includes/mailer.php). Настройки почты живут в
 * site_settings и вводятся в суперадминке — миграция их НЕ трогает (секреты в
 * git не кладём). Здесь только таблица журнала: кто, кому и что отправил, ушло
 * или нет. Без журнала «я писал клиенту» невозможно ни подтвердить, ни
 * опровергнуть, а спам-жалобы разбирать не по чему.
 *
 * Запуск (повторный безопасен):
 *   php sql/migrate_email.php
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Только из командной строки.\n"); }

require_once dirname(__DIR__) . '/config/config.php';

$db = getDB();

echo "Почта · журнал отправленных писем\n";
echo "База: " . DB_NAME . "\n";
echo str_repeat('-', 60) . "\n";

$db->exec(
    "CREATE TABLE IF NOT EXISTS `email_log` (
      `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `to_email`   VARCHAR(190) NOT NULL,
      `to_user_id` INT UNSIGNED DEFAULT NULL COMMENT 'если получатель — наш пользователь',
      `subject`    VARCHAR(255) NOT NULL DEFAULT '',
      `body`       MEDIUMTEXT   DEFAULT NULL,
      `status`     ENUM('sent','failed') NOT NULL DEFAULT 'sent',
      `error`      VARCHAR(500) DEFAULT NULL COMMENT 'текст ошибки, если не ушло',
      `sent_by`    INT UNSIGNED DEFAULT NULL COMMENT 'сотрудник, отправивший письмо',
      `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_to_user` (`to_user_id`),
      KEY `idx_sent_by` (`sent_by`),
      KEY `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
echo "  [OK] таблица email_log\n";

echo str_repeat('-', 60) . "\n";
echo "Готово. Дальше: суперадмин → Настройки → раздел «Почта (SMTP)» — ввести\n";
echo "сервер, порт, логин и пароль ящика, включить отправку и нажать «Тест».\n";
echo "Письма сотрудники отправляют в разделе «Письма клиентам».\n";
