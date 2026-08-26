<?php
/**
 * Фаза 3b — каркас приёма оплаты («деньги внутрь»).
 *
 * Эквайринга в Таджикистане у нас пока нет: нужен договор с банком и его песочница.
 * Но всё, что НЕ зависит от конкретного банка, можно построить заранее — тогда, когда
 * договор появится, останется написать один адаптер по документации, а не поднимать
 * платёжный контур с нуля.
 *
 * Что делает миграция:
 *   1. `orders.payment_status` — оплачен заказ или нет. Полезно уже сейчас, без банка:
 *      наличные тоже бывают получены и не получены, а до сих пор это нигде не хранилось;
 *   2. `orders.paid_at` — когда деньги фактически получены;
 *   3. `payment_transactions` — журнал попыток оплаты: провайдер, внешний id, сумма,
 *      статус, сырой ответ. Без журнала разбирать спор с банком не по чему.
 *
 * Чего миграция НЕ делает и не может: маппинг запросов конкретного банка, формат
 * подписи, коды статусов. Это описано в документации банка, и угадывать её — значит
 * написать абстракцию, которую придётся переделать.
 *
 * Запуск (повторный безопасен):
 *   php sql/migrate_payments.php
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Только из командной строки.\n"); }

require_once dirname(__DIR__) . '/config/config.php';

$db = getDB();

echo "Фаза 3b · каркас приёма оплаты\n";
echo "База: " . DB_NAME . "\n";
echo str_repeat('-', 60) . "\n";

// ── 1. Статус оплаты заказа ──────────────────────────────────────────────────
// Отдельно от `orders.status`: заказ может быть доставлен, но не оплачен (спор), и
// наоборот — оплачен онлайн, но ещё не собран. Смешивать эти две шкалы нельзя.
//
// unpaid   — денег нет;
// pending  — платёж начат, ждём подтверждения от банка;
// paid     — деньги получены;
// refunded — возвращены покупателю;
// failed   — попытка не удалась.
dbAddColumnIfMissing($db, 'orders', 'payment_status',
    "`payment_status` ENUM('unpaid','pending','paid','refunded','failed')
     NOT NULL DEFAULT 'unpaid' AFTER `payment_method`");
dbAddColumnIfMissing($db, 'orders', 'paid_at',
    "`paid_at` DATETIME DEFAULT NULL AFTER `payment_status`");
dbAddIndexIfMissing($db, 'orders', 'idx_payment_status',
    "KEY `idx_payment_status` (`payment_status`)");
echo "  [OK] orders.payment_status, orders.paid_at\n";

// ── 2. Журнал платежей ───────────────────────────────────────────────────────
// `external_id` — идентификатор платежа на стороне банка. UNIQUE вместе с
// провайдером: банк присылает подтверждение по нескольку раз, и повторная обработка
// не должна ни задваивать платёж, ни ронять обработчик.
//
// `payload` — сырой ответ как есть. Разбирать спор по разобранным полям невозможно:
// в претензии банк оперирует своим форматом, а не нашим.
$db->exec(
    "CREATE TABLE IF NOT EXISTS `payment_transactions` (
      `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
      `order_id`    INT UNSIGNED  NOT NULL,
      `provider`    VARCHAR(40)   NOT NULL COMMENT 'manual, alif, eskhata…',
      `external_id` VARCHAR(120)  DEFAULT NULL COMMENT 'id платежа у банка',
      `amount`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
      `currency`    VARCHAR(8)    NOT NULL DEFAULT 'TJS',
      `status`      ENUM('created','pending','succeeded','failed','refunded')
                    NOT NULL DEFAULT 'created',
      `payload`     TEXT          DEFAULT NULL COMMENT 'сырой ответ провайдера',
      `note`        VARCHAR(255)  DEFAULT NULL,
      `created_by`  INT UNSIGNED  DEFAULT NULL COMMENT 'кто отметил вручную',
      `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uk_provider_external` (`provider`, `external_id`),
      KEY `idx_order`  (`order_id`),
      KEY `idx_status` (`status`),
      CONSTRAINT `fk_paytx_order` FOREIGN KEY (`order_id`)
        REFERENCES `orders` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
echo "  [OK] таблица payment_transactions\n";

// ── 3. Проставить статус уже существующим заказам ────────────────────────────
// Доставленные заказы наличными деньги, очевидно, получили — иначе их бы не отдали.
// Остальные оставляем `unpaid`: гадать за прошлое нельзя, а неверный «оплачен»
// хуже честного «не знаем».
$n = $db->exec(
    "UPDATE orders
        SET payment_status = 'paid', paid_at = COALESCE(paid_at, updated_at)
      WHERE payment_status = 'unpaid' AND status = 'delivered'"
);
echo "  [OK] доставленным заказам проставлено «оплачен»: " . (int)$n . "\n";

echo str_repeat('-', 60) . "\n";
$paid = (int)$db->query("SELECT COUNT(*) FROM orders WHERE payment_status='paid'")->fetchColumn();
$all  = (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
echo "Заказов: $all, из них помечены оплаченными: $paid\n";
echo "Провайдер по умолчанию — `manual` (владелец отмечает оплату сам).\n";
echo "Банковский адаптер добавится, когда будет договор и песочница.\n";
