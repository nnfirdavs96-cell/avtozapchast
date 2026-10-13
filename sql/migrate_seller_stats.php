<?php
/**
 * Статистика кабинета продавца («Финансовая часть»).
 *
 * Создаёт две таблицы (идемпотентно):
 *   • seller_views    — просмотры страницы магазина и страниц товаров продавца.
 *                       Нужна для показателя «Посещения магазина». Дедуп по
 *                       (продавец, страница, посетитель) делаем в коде за 30 мин.
 *   • seller_messages — переписка покупатель ↔ продавец (на сайте такой ещё не
 *                       было — была только покупатель ↔ менеджер в `messages`).
 *
 * Индексы по (seller_id, created_at) — чтобы выборки за период не делали
 * полный скан. Запуск (повторный безопасен):  php sql/migrate_seller_stats.php
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Только из командной строки.\n"); }

require_once dirname(__DIR__) . '/config/config.php';

$db = getDB();
echo "Статистика кабинета продавца — миграция\n";
echo "База: " . DB_NAME . "\n";
echo str_repeat('-', 60) . "\n";

$db->exec("CREATE TABLE IF NOT EXISTS `seller_views` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `seller_id`   INT UNSIGNED NOT NULL,
    `part_id`     INT UNSIGNED DEFAULT NULL,          -- NULL = просмотр страницы магазина
    `visitor_key` VARCHAR(64) NOT NULL,               -- хэш сессии/IP, чтобы отсеять повторы
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_seller_date` (`seller_id`, `created_at`),
    KEY `idx_dedup` (`seller_id`, `part_id`, `visitor_key`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "  [OK] таблица seller_views\n";

$db->exec("CREATE TABLE IF NOT EXISTS `seller_messages` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `seller_id`      INT UNSIGNED NOT NULL,
    `buyer_id`       INT UNSIGNED NOT NULL,            -- users.id покупателя
    `part_id`        INT UNSIGNED DEFAULT NULL,        -- товар, с которого написали (для контекста)
    `sender`         ENUM('buyer','seller') NOT NULL,
    `body`           TEXT NOT NULL,
    `read_by_seller` TINYINT(1) NOT NULL DEFAULT 0,
    `read_by_buyer`  TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_seller_date` (`seller_id`, `created_at`),
    KEY `idx_thread` (`seller_id`, `buyer_id`, `created_at`),
    KEY `idx_unread_seller` (`seller_id`, `read_by_seller`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "  [OK] таблица seller_messages\n";

echo str_repeat('-', 60) . "\n";
echo "Готово.\n";
