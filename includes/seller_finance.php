<?php
/**
 * Финансы продавцов (маркетплейс, Фаза 3 — половина «деньги наружу»).
 *
 * Считает, сколько площадка должна продавцу, и фиксирует выплаты. Эквайринг для
 * этого не нужен: деньги уже приходят площадке (наличными при получении), задача —
 * честно рассчитаться с продавцом и оставить след для сверки.
 *
 * Единственный источник правды — журнал `seller_ledger`. Баланс НИКОГДА не хранится
 * отдельным полем: он всегда SUM(amount) по журналу, поэтому его нельзя «испортить»
 * и всегда можно объяснить, из чего он сложился.
 *
 * Правила начисления (согласованы как разумный дефолт, меняются в одном месте):
 *   • начисляем, когда подзаказ переходит в `delivered` — заработано то, что дошло
 *     до покупателя, а не то, что просто оформлено;
 *   • сумма начисления = `order_sellers.payout_amount` (subtotal − комиссия),
 *     зафиксированная в момент заказа;
 *   • отмена уже доставленного подзаказа сторнируется в минус;
 *   • наш собственный каталог (`seller_id IS NULL`) в журнал не попадает — площадка
 *     не должна денег сама себе.
 */

/** Готова ли схема Фазы 3 (миграция накачена)? Результат кэшируется на запрос. */
function sellerFinanceReady(?PDO $db = null): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        $db = $db ?: getDB();
        $ready = (int)$db->query(
            "SELECT COUNT(*) FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME IN ('seller_ledger', 'seller_payouts')"
        )->fetchColumn() === 2;
    } catch (Throwable $e) { $ready = false; }
    return $ready;
}

/**
 * Провести движение в журнал.
 *
 * Для earning/reversal используем INSERT IGNORE: UNIQUE(order_seller_id, type)
 * гарантирует, что повторный клик по «Доставлен» не удвоит долг. Возвращает true,
 * если запись действительно добавлена.
 */
function sellerLedgerAdd(PDO $db, int $sellerId, string $type, float $amount,
                         ?int $orderSellerId = null, ?string $note = null,
                         ?int $payoutId = null): bool
{
    if (!sellerFinanceReady($db) || $sellerId <= 0) return false;
    if (!in_array($type, ['earning', 'reversal', 'payout', 'adjustment'], true)) return false;

    $st = $db->prepare(
        "INSERT IGNORE INTO seller_ledger
            (seller_id, order_seller_id, payout_id, type, amount, note, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())"
    );
    $st->execute([
        $sellerId, $orderSellerId ?: null, $payoutId ?: null, $type,
        round($amount, 2), $note !== null ? mb_substr($note, 0, 255) : null,
    ]);
    return $st->rowCount() > 0;
}

/**
 * Синхронизировать журнал со статусом ОДНОГО подзаказа.
 *
 * Вызывается после каждой смены статуса. Идемпотентна — можно звать сколько угодно
 * раз, лишних движений не появится:
 *   delivered  → начисление (если ещё не начисляли);
 *   cancelled  → сторно (только если начисление было — отмена «непринятого» заказа
 *                денег не касается).
 * Промежуточные статусы не двигают деньги вообще.
 */
function sellerLedgerSyncOrderSeller(PDO $db, int $orderSellerId): void
{
    if (!sellerFinanceReady($db) || $orderSellerId <= 0) return;

    $st = $db->prepare("SELECT id, seller_id, status, payout_amount FROM order_sellers WHERE id = ?");
    $st->execute([$orderSellerId]);
    $os = $st->fetch();
    // Наш собственный каталог (seller_id IS NULL) в расчётах не участвует.
    if (!$os || $os['seller_id'] === null) return;

    $sellerId = (int)$os['seller_id'];
    $payout   = (float)$os['payout_amount'];

    if ($os['status'] === 'delivered') {
        sellerLedgerAdd($db, $sellerId, 'earning', $payout, $orderSellerId,
                        'Доставленный заказ');
        return;
    }

    if ($os['status'] === 'cancelled') {
        // Сторнируем ровно ту сумму, которую начислили (а не текущую) — если бы
        // payout_amount когда-нибудь пересчитали, баланс всё равно сошёлся бы в ноль.
        $e = $db->prepare("SELECT amount FROM seller_ledger
                            WHERE order_seller_id = ? AND type = 'earning'");
        $e->execute([$orderSellerId]);
        $earned = $e->fetchColumn();
        if ($earned !== false) {
            sellerLedgerAdd($db, $sellerId, 'reversal', -(float)$earned, $orderSellerId,
                            'Отмена доставленного заказа');
        }
    }
}

/**
 * Пересчитать журнал по ВСЕМ подзаказам (back-fill).
 *
 * Нужна потому, что Фаза 2 работала до появления журнала: доставленные заказы уже
 * есть, а начислений по ним нет. Также страхует случаи, когда статус меняли прямо
 * в БД мимо интерфейса. Полностью идемпотентна. Возвращает [начислено, сторнировано].
 */
function sellerLedgerSyncAll(PDO $db): array
{
    if (!sellerFinanceReady($db)) return [0, 0];

    $accrued = $reversed = 0;

    // Начисления по доставленным, которых ещё нет в журнале.
    $rows = $db->query(
        "SELECT os.id, os.seller_id, os.payout_amount
           FROM order_sellers os
           LEFT JOIN seller_ledger sl
                  ON sl.order_seller_id = os.id AND sl.type = 'earning'
          WHERE os.seller_id IS NOT NULL
            AND os.status = 'delivered'
            AND sl.id IS NULL"
    )->fetchAll();
    foreach ($rows as $r) {
        if (sellerLedgerAdd($db, (int)$r['seller_id'], 'earning', (float)$r['payout_amount'],
                            (int)$r['id'], 'Доставленный заказ')) $accrued++;
    }

    // Сторно по отменённым, у которых начисление есть, а сторно нет.
    $rows = $db->query(
        "SELECT os.id, os.seller_id, e.amount
           FROM order_sellers os
           JOIN seller_ledger e ON e.order_seller_id = os.id AND e.type = 'earning'
           LEFT JOIN seller_ledger r ON r.order_seller_id = os.id AND r.type = 'reversal'
          WHERE os.status = 'cancelled' AND r.id IS NULL"
    )->fetchAll();
    foreach ($rows as $r) {
        if (sellerLedgerAdd($db, (int)$r['seller_id'], 'reversal', -(float)$r['amount'],
                            (int)$r['id'], 'Отмена доставленного заказа')) $reversed++;
    }

    return [$accrued, $reversed];
}

/**
 * Финансовая сводка одного продавца.
 *
 * earned     — начислено по доставленным (за вычетом сторно);
 * commission — сколько удержала площадка (для прозрачности, в баланс не входит);
 * paid       — уже выплачено;
 * balance    — к выплате прямо сейчас (это и есть SUM журнала);
 * pending    — «в пути»: подзаказы приняты, но ещё не доставлены — деньги, которые
 *              станут балансом, если заказы дойдут. Помогает продавцу планировать.
 */
function sellerFinanceSummary(PDO $db, int $sellerId): array
{
    $out = ['earned' => 0.0, 'commission' => 0.0, 'paid' => 0.0,
            'balance' => 0.0, 'pending' => 0.0, 'orders_paid' => 0];
    if (!sellerFinanceReady($db) || $sellerId <= 0) return $out;

    $st = $db->prepare(
        "SELECT
            COALESCE(SUM(amount), 0)                                        AS balance,
            COALESCE(SUM(CASE WHEN type IN ('earning','reversal') THEN amount END), 0) AS earned,
            COALESCE(SUM(CASE WHEN type = 'payout' THEN -amount END), 0)    AS paid,
            COALESCE(SUM(CASE WHEN type = 'earning' THEN 1 END), 0)         AS orders_paid
           FROM seller_ledger WHERE seller_id = ?"
    );
    $st->execute([$sellerId]);
    if ($r = $st->fetch()) {
        $out['balance']     = (float)$r['balance'];
        $out['earned']      = (float)$r['earned'];
        $out['paid']        = (float)$r['paid'];
        $out['orders_paid'] = (int)$r['orders_paid'];
    }

    // Удержанная комиссия — по тем же подзаказам, что дали начисление.
    $st = $db->prepare(
        "SELECT COALESCE(SUM(os.commission_amount), 0)
           FROM seller_ledger sl
           JOIN order_sellers os ON os.id = sl.order_seller_id
          WHERE sl.seller_id = ? AND sl.type = 'earning' AND os.status = 'delivered'"
    );
    $st->execute([$sellerId]);
    $out['commission'] = (float)$st->fetchColumn();

    // «В пути» — принято, но ещё не доставлено.
    $st = $db->prepare(
        "SELECT COALESCE(SUM(payout_amount), 0) FROM order_sellers
          WHERE seller_id = ? AND status IN ('processing','shipped')"
    );
    $st->execute([$sellerId]);
    $out['pending'] = (float)$st->fetchColumn();

    return $out;
}

/**
 * Реестр для владельца: кому сколько должны.
 * Продавцы без единого движения по журналу тоже показываются (с нулями) —
 * иначе непонятно, «магазин ничего не продал» или «магазина нет в реестре».
 */
function sellerPayoutRegistry(PDO $db): array
{
    if (!sellerFinanceReady($db)) return [];
    return $db->query(
        "SELECT s.id, s.shop_name, s.commission_percent, s.status, s.phone,
                COALESCE(l.balance, 0) AS balance,
                COALESCE(l.earned,  0) AS earned,
                COALESCE(l.paid,    0) AS paid,
                (SELECT MAX(created_at) FROM seller_payouts p WHERE p.seller_id = s.id) AS last_payout
           FROM sellers s
           LEFT JOIN (
                SELECT seller_id,
                       SUM(amount) AS balance,
                       SUM(CASE WHEN type IN ('earning','reversal') THEN amount END) AS earned,
                       SUM(CASE WHEN type = 'payout' THEN -amount END) AS paid
                  FROM seller_ledger GROUP BY seller_id
           ) l ON l.seller_id = s.id
       ORDER BY COALESCE(l.balance, 0) DESC, s.shop_name"
    )->fetchAll();
}

/** Человеко-понятные названия движений и способов выплаты. */
function sellerLedgerTypeLabel(string $type): string
{
    return ['earning'    => 'Начислено',
            'reversal'   => 'Сторно (отмена)',
            'payout'     => 'Выплата',
            'adjustment' => 'Корректировка'][$type] ?? $type;
}

function sellerPayoutMethodLabel(string $method): string
{
    return ['card'   => 'На карту',
            'cash'   => 'Наличные',
            'bank'   => 'Банковский перевод',
            'mobile' => 'Мобильный кошелёк',
            'other'  => 'Другое'][$method] ?? $method;
}

/**
 * Провести выплату продавцу.
 *
 * Пишем факт в реестр и минусовое движение в журнал — одной транзакцией, чтобы не
 * получилось «выплата записана, а долг не уменьшился». Возвращает id выплаты или 0.
 */
function sellerPayoutCreate(PDO $db, int $sellerId, float $amount, string $method,
                            ?string $reference = null, ?string $note = null,
                            ?string $periodFrom = null, ?string $periodTo = null,
                            ?int $createdBy = null): int
{
    if (!sellerFinanceReady($db) || $sellerId <= 0 || $amount <= 0) return 0;
    if (!in_array($method, ['card', 'cash', 'bank', 'mobile', 'other'], true)) $method = 'other';

    $amount = round($amount, 2);
    // Вложенная транзакция уронила бы внешнюю — начинаем только свою.
    $own = !$db->inTransaction();
    if ($own) $db->beginTransaction();
    try {
        $st = $db->prepare(
            "INSERT INTO seller_payouts
                (seller_id, amount, method, reference, period_from, period_to, note, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $st->execute([
            $sellerId, $amount, $method,
            $reference !== null && $reference !== '' ? mb_substr($reference, 0, 120) : null,
            $periodFrom ?: null, $periodTo ?: null,
            $note !== null && $note !== '' ? mb_substr($note, 0, 255) : null,
            $createdBy ?: null,
        ]);
        $payoutId = (int)$db->lastInsertId();

        // Выплата уменьшает долг — поэтому в журнал минусом.
        $db->prepare(
            "INSERT INTO seller_ledger (seller_id, payout_id, type, amount, note, created_at)
             VALUES (?, ?, 'payout', ?, ?, NOW())"
        )->execute([$sellerId, $payoutId, -$amount, sellerPayoutMethodLabel($method)]);

        if ($own) $db->commit();
        return $payoutId;
    } catch (Throwable $e) {
        if ($own && $db->inTransaction()) $db->rollBack();
        return 0;
    }
}
