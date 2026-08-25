<?php
/**
 * Маркетплейс, Фаза 4 (вторая половина) — возвраты и споры.
 *
 * Чего не хватало: после доставки заказ считался закрытым навсегда. Если товар не
 * подошёл или пришёл сломанным, покупателю оставалось писать менеджеру в чат, а
 * деньги продавцу уже начислены — снять их можно было только ручной
 * корректировкой, без следа о причине.
 *
 * Модель:
 *   order_returns   заявка на возврат: чей заказ, какая позиция, причина, решение
 *   seller_ledger   ← возврат сторнирует долю продавца, ссылаясь на заявку
 *
 * Возврат привязан к ПОДЗАКАЗУ, а при необходимости и к конкретной позиции: в одном
 * заказе от одного продавца может не подойти только одна деталь из трёх.
 *
 * Почему PHP, а не .sql: в `seller_ledger` добавляется колонка, а `ADD COLUMN IF NOT
 * EXISTS` есть только в MariaDB — на MySQL 8 это синтаксическая ошибка (см.
 * dbAddColumnIfMissing).
 *
 * Запуск (повторный безопасен):
 *   php sql/migrate_returns.php
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Только из командной строки.\n"); }

require_once dirname(__DIR__) . '/config/config.php';

$db = getDB();

echo "Маркетплейс · возвраты и споры\n";
echo "База: " . DB_NAME . "\n";
echo str_repeat('-', 60) . "\n";

// ── Заявки на возврат ────────────────────────────────────────────────────────
// status:
//   requested — покупатель попросил, решения нет;
//   approved  — продавец (или админ в споре) согласился, деньги сторнированы;
//   rejected  — отказано, с причиной;
//   cancelled — покупатель передумал до решения.
//
// `amount` — сумма, которую вернули покупателю. `payout_reversed` — сколько сняли
// с баланса продавца: это НЕ то же самое. Продавцу начислялась сумма за вычетом
// комиссии, поэтому и снимать надо его долю, а не полную стоимость товара.
$db->exec(
    "CREATE TABLE IF NOT EXISTS `order_returns` (
      `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `order_seller_id`  INT UNSIGNED NOT NULL,
      `order_item_id`    INT UNSIGNED DEFAULT NULL COMMENT 'NULL = весь подзаказ',
      `user_id`          INT UNSIGNED NOT NULL,
      `seller_id`        INT UNSIGNED DEFAULT NULL COMMENT 'NULL = наш каталог',
      `reason`           VARCHAR(40)  NOT NULL DEFAULT 'other',
      `comment`          VARCHAR(1000) DEFAULT NULL,
      `status`           ENUM('requested','approved','rejected','cancelled')
                         NOT NULL DEFAULT 'requested',
      `amount`           DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'вернули покупателю',
      `payout_reversed`  DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'сняли с продавца',
      `resolution`       VARCHAR(500) DEFAULT NULL COMMENT 'что ответил продавец или админ',
      `resolved_by`      INT UNSIGNED DEFAULT NULL,
      `resolved_at`      DATETIME     DEFAULT NULL,
      `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_os`     (`order_seller_id`),
      KEY `idx_seller` (`seller_id`, `status`),
      KEY `idx_user`   (`user_id`),
      KEY `idx_status` (`status`),
      CONSTRAINT `fk_ret_os` FOREIGN KEY (`order_seller_id`)
        REFERENCES `order_sellers` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
echo "  [OK] таблица order_returns\n";

// ── Связь журнала с заявкой ──────────────────────────────────────────────────
// Без неё в балансе продавца висело бы движение «минус 300» без объяснения,
// откуда оно взялось. Через полгода такую строку невозможно оспорить.
dbAddColumnIfMissing($db, 'seller_ledger', 'return_id',
    "`return_id` INT UNSIGNED DEFAULT NULL AFTER `payout_id`");
dbAddIndexIfMissing($db, 'seller_ledger', 'idx_return', "KEY `idx_return` (`return_id`)");
echo "  [OK] seller_ledger.return_id\n";

// Тип движения `refund` — отдельно от `reversal` (отмена до доставки) и
// `adjustment` (ручная правка владельца). Разные причины должны читаться в
// журнале по-разному, иначе спор не разобрать.
$col = $db->query("SHOW COLUMNS FROM `seller_ledger` LIKE 'type'")->fetch();
if ($col && stripos((string)$col['Type'], "'refund'") === false) {
    $db->exec(
        "ALTER TABLE `seller_ledger`
         MODIFY COLUMN `type` ENUM('earning','reversal','payout','adjustment','refund') NOT NULL"
    );
    echo "  [OK] seller_ledger.type += 'refund'\n";
} else {
    echo "  [есть] тип 'refund' уже в seller_ledger\n";
}

echo str_repeat('-', 60) . "\n";
$n = (int)$db->query("SELECT COUNT(*) FROM order_returns")->fetchColumn();
echo "Готово. Заявок на возврат в базе: $n\n";
