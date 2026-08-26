<?php
/**
 * Платёжный шлюз: реестр провайдеров и общая логика (Фаза 3b).
 *
 * Всё, что НЕ зависит от конкретного банка, живёт здесь: журнал транзакций,
 * идемпотентность подтверждений, сверка суммы, перевод заказа в «оплачен».
 * Адаптер банка отвечает только за формат запросов и подпись.
 *
 * Такое разделение сделано намеренно: именно эта часть — где легче всего ошибиться
 * дорого, и переписывать её при смене банка нельзя.
 */

require_once __DIR__ . '/PaymentProvider.php';
require_once __DIR__ . '/ManualProvider.php';

/** Есть ли схема Фазы 3b. Проверяем саму таблицу — работает на любой СУБД. */
function paymentsReady(?PDO $db = null): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        ($db ?: getDB())->query("SELECT 1 FROM payment_transactions LIMIT 1")->fetchAll();
        $ready = true;
    } catch (Throwable $e) { $ready = false; }
    return $ready;
}

/**
 * Все известные провайдеры.
 *
 * Банковские адаптеры добавляются сюда одной строкой — как и провайдеры каталога.
 * Пока список из одного: договора с банком нет, и выдумывать его формат заранее
 * бессмысленно.
 */
function paymentProviders(): array
{
    static $list = null;
    if ($list !== null) return $list;

    $list = [];
    foreach ([new ManualProvider()] as $p) $list[$p->id()] = $p;
    return $list;
}

/** Активный провайдер по настройке `payment_provider`; по умолчанию ручной. */
function paymentProvider(?string $id = null): PaymentProvider
{
    $all = paymentProviders();
    $id  = $id ?: (string)getSetting('payment_provider', 'manual');
    return $all[$id] ?? $all['manual'];
}

/**
 * Записать попытку оплаты в журнал.
 *
 * Даже неудачная попытка должна остаться: «покупатель говорит, что платил» без
 * журнала невозможно ни подтвердить, ни опровергнуть.
 */
function paymentLog(PDO $db, int $orderId, string $provider, string $status,
                    float $amount, ?string $externalId = null,
                    ?string $payload = null, ?string $note = null,
                    ?int $createdBy = null): int
{
    if (!paymentsReady($db) || $orderId <= 0) return 0;

    $args = [
        $orderId, $provider, $externalId ?: null, round($amount, 2),
        getSetting('payment_currency', 'TJS'), $status,
        $payload !== null ? mb_substr($payload, 0, 60000) : null,
        $note !== null ? mb_substr($note, 0, 255) : null,
        $createdBy ?: null,
    ];

    // Повторное подтверждение того же платежа не должно создавать вторую строку:
    // банки шлют callback по нескольку раз, это норма, а не сбой.
    //
    // Пишем через «попробовать вставить, при дубле обновить», а не через
    // ON DUPLICATE KEY UPDATE. Тот синтаксис есть только в MySQL, и код, отвечающий
    // за деньги, оказался бы непроверяемым нигде, кроме боевого сервера — а это
    // ровно то место, где ошибку находить нельзя. Заодно такой порядок устойчив к
    // гонке: если два подтверждения придут одновременно, вставку отобьёт UNIQUE, и
    // проигравший просто обновит строку.
    try {
        $st = $db->prepare(
            "INSERT INTO payment_transactions
                (order_id, provider, external_id, amount, currency, status, payload, note, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $st->execute($args);
        return (int)$db->lastInsertId();
    } catch (PDOException $e) {
        if ($externalId === null) throw $e;   // дубля быть не могло — это другая ошибка

        $upd = $db->prepare(
            "UPDATE payment_transactions
                SET status = ?, payload = ?, note = ?, updated_at = NOW()
              WHERE provider = ? AND external_id = ?"
        );
        $upd->execute([$status, $args[6], $args[7], $provider, $externalId]);

        $find = $db->prepare("SELECT id FROM payment_transactions WHERE provider = ? AND external_id = ? LIMIT 1");
        $find->execute([$provider, $externalId]);
        return (int)$find->fetchColumn();
    }
}

/**
 * Признать заказ оплаченным.
 *
 * ⚠️ Сверка суммы обязательна и делается ЗДЕСЬ, а не в адаптере банка: если бы
 * каждый адаптер проверял её сам, однажды какой-нибудь забыл бы — и заказ на 5000
 * закрылся бы платежом на 5.
 *
 * Идемпотентна: уже оплаченный заказ повторное подтверждение не трогает.
 * Возвращает [успех, сообщение].
 */
function paymentMarkPaid(PDO $db, int $orderId, float $amount, string $provider,
                         ?string $externalId = null, ?string $payload = null,
                         ?int $createdBy = null): array
{
    if (!paymentsReady($db)) return [false, 'Платёжный контур не активирован.'];

    $st = $db->prepare("SELECT id, total_amount, payment_status FROM orders WHERE id = ? LIMIT 1");
    $st->execute([$orderId]);
    $order = $st->fetch();
    if (!$order) return [false, 'Заказ не найден.'];

    if ($order['payment_status'] === 'paid') {
        // Не ошибка: банк прислал подтверждение повторно. Молча соглашаемся.
        paymentLog($db, $orderId, $provider, 'succeeded', $amount, $externalId, $payload,
                   'повторное подтверждение', $createdBy);
        return [true, 'Заказ уже был отмечен оплаченным.'];
    }

    // Допуск в одну копейку — на округления при конвертации валют.
    $expected = (float)$order['total_amount'];
    if ($amount + 0.01 < $expected) {
        paymentLog($db, $orderId, $provider, 'failed', $amount, $externalId, $payload,
                   'сумма меньше заказа: ожидали ' . number_format($expected, 2, '.', ''), $createdBy);
        return [false, 'Сумма платежа меньше суммы заказа — заказ не отмечен оплаченным.'];
    }

    $own = !$db->inTransaction();
    if ($own) $db->beginTransaction();
    try {
        paymentLog($db, $orderId, $provider, 'succeeded', $amount, $externalId, $payload, null, $createdBy);
        $db->prepare(
            "UPDATE orders SET payment_status = 'paid', paid_at = NOW(), updated_at = NOW() WHERE id = ?"
        )->execute([$orderId]);
        if ($own) $db->commit();
    } catch (Throwable $e) {
        if ($own && $db->inTransaction()) $db->rollBack();
        return [false, 'Не удалось сохранить оплату.'];
    }

    return [true, 'Заказ отмечен оплаченным.'];
}

/** Пометить заказ как возвращённый покупателю (полный возврат средств). */
function paymentMarkRefunded(PDO $db, int $orderId, float $amount, string $provider,
                             ?int $createdBy = null): array
{
    if (!paymentsReady($db)) return [false, 'Платёжный контур не активирован.'];
    paymentLog($db, $orderId, $provider, 'refunded', $amount, null, null, 'возврат средств', $createdBy);
    $db->prepare("UPDATE orders SET payment_status = 'refunded', updated_at = NOW() WHERE id = ?")
       ->execute([$orderId]);
    return [true, 'Заказ отмечен как возвращённый.'];
}

function paymentStatusLabel(string $s): string
{
    return ['unpaid'   => 'Не оплачен',
            'pending'  => 'Ожидает оплаты',
            'paid'     => 'Оплачен',
            'refunded' => 'Возвращён',
            'failed'   => 'Оплата не прошла'][$s] ?? $s;
}
