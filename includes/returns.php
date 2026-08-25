<?php
/**
 * Возвраты и споры (маркетплейс, Фаза 4).
 *
 * До этого после доставки заказ считался закрытым навсегда. Если товар не подошёл
 * или пришёл сломанным, покупателю оставалось писать в чат, а деньги продавцу уже
 * начислены — снять их можно было только ручной корректировкой, без следа о причине.
 *
 * ДЕНЬГИ. Ключевой момент, который легко перепутать: покупателю возвращают ПОЛНУЮ
 * стоимость товара, а с баланса продавца снимают только ЕГО ДОЛЮ — то, что ему
 * начислили за вычетом комиссии. Снять полную стоимость значило бы заставить
 * продавца оплатить ещё и комиссию площадки за несостоявшуюся продажу.
 *
 * СПОР. Решение принимает продавец. Если он отказал, а покупатель не согласен,
 * последнее слово за администратором: он может пересмотреть решение (`admin/returns.php`).
 * Отдельной сущности «спор» нет намеренно — это то же самое обращение, просто
 * рассмотренное второй инстанцией.
 */

require_once __DIR__ . '/seller_finance.php';

/** Причины возврата. Свободный текст покупатель добавляет отдельно, в комментарии. */
function returnReasons(): array
{
    return [
        'not_fit'   => 'Не подошла деталь',
        'defect'    => 'Брак или повреждение',
        'wrong'     => 'Прислали не то',
        'not_as_described' => 'Не соответствует описанию',
        'other'     => 'Другая причина',
    ];
}

function returnStatusLabel(string $s): string
{
    return ['requested' => 'На рассмотрении',
            'approved'  => 'Возврат одобрен',
            'rejected'  => 'Отказано',
            'cancelled' => 'Отозвано покупателем'][$s] ?? $s;
}

/** Готова ли схема возвратов? Проверяем саму таблицу — работает на любой СУБД. */
function returnsReady(?PDO $db = null): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        ($db ?: getDB())->query("SELECT 1 FROM order_returns LIMIT 1")->fetchAll();
        $ready = true;
    } catch (Throwable $e) { $ready = false; }
    return $ready;
}

/**
 * Сколько снять с баланса продавца при возврате позиции.
 *
 * Пропорционально: если из подзаказа на 1000 сомони с выплатой 900 возвращают товар
 * на 250, продавец теряет 225 — свою долю, а не всю стоимость. Комиссию площадка
 * возвращает вместе с товаром, а не оставляет себе.
 *
 * Возврат всего подзаказа — частный случай: доля равна единице.
 */
function returnPayoutShare(float $refundAmount, float $subtotal, float $payoutAmount): float
{
    if ($subtotal <= 0) return 0.0;
    return round($payoutAmount * ($refundAmount / $subtotal), 2);
}

/**
 * Создать заявку на возврат.
 *
 * Право проверяется здесь, а не в вызывающем коде: подзаказ должен принадлежать
 * этому покупателю и быть доставленным. Возврат до доставки — это отмена, для неё
 * есть свой путь.
 *
 * $orderItemId = 0 → возвращают весь подзаказ.
 * Возвращает [успех, сообщение].
 */
function returnRequest(PDO $db, int $userId, int $orderSellerId, int $orderItemId,
                       string $reason, string $comment): array
{
    if (!returnsReady($db)) return [false, 'Возвраты ещё не активированы.'];
    if (!isset(returnReasons()[$reason])) $reason = 'other';

    $st = $db->prepare(
        "SELECT os.id, os.seller_id, os.subtotal, os.payout_amount
           FROM order_sellers os
           JOIN orders o ON o.id = os.order_id
          WHERE os.id = ? AND o.user_id = ? AND os.status = 'delivered'
          LIMIT 1"
    );
    $st->execute([$orderSellerId, $userId]);
    $os = $st->fetch();
    if (!$os) return [false, 'Вернуть можно только доставленный заказ.'];

    // Сумма возврата: либо строка товара, либо весь подзаказ.
    $amount = (float)$os['subtotal'];
    if ($orderItemId > 0) {
        $it = $db->prepare(
            "SELECT unit_price, quantity FROM order_items
              WHERE id = ? AND order_seller_id = ? LIMIT 1"
        );
        $it->execute([$orderItemId, $orderSellerId]);
        $row = $it->fetch();
        if (!$row) return [false, 'Позиция не найдена в этом заказе.'];
        $amount = round((float)$row['unit_price'] * (int)$row['quantity'], 2);
    }

    // Повторная заявка на то же самое, пока прежняя не рассмотрена, только
    // запутает и продавца, и покупателя.
    $dup = $db->prepare(
        "SELECT id FROM order_returns
          WHERE order_seller_id = ? AND status IN ('requested','approved')
            AND (order_item_id <=> ?) LIMIT 1"
    );
    $dup->execute([$orderSellerId, $orderItemId ?: null]);
    if ($dup->fetch()) return [false, 'Заявка на возврат по этой позиции уже есть.'];

    $db->prepare(
        "INSERT INTO order_returns
            (order_seller_id, order_item_id, user_id, seller_id, reason, comment, amount, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
    )->execute([
        $orderSellerId, $orderItemId ?: null, $userId, $os['seller_id'] ?: null,
        $reason, ($comment = trim($comment)) !== '' ? mb_substr($comment, 0, 1000) : null,
        $amount,
    ]);

    return [true, 'Заявка на возврат отправлена. Продавец рассмотрит её и ответит.'];
}

/**
 * Решение по заявке: одобрить или отказать.
 *
 * Одобрение СРАЗУ двигает деньги: снимает с баланса продавца его долю и оставляет
 * в журнале запись со ссылкой на заявку. Без ссылки в балансе висело бы движение
 * «минус 300» без объяснения, и через полгода его невозможно было бы оспорить.
 *
 * Всё одной транзакцией: «возврат одобрен, но деньги не сняты» — худшее из
 * состояний, потому что заметят его нескоро.
 *
 * $actorSellerId — если решение принимает продавец, передайте его id: тогда чужую
 * заявку он не тронет. Администратор передаёт null (последнее слово в споре).
 */
function returnResolve(PDO $db, int $returnId, string $decision, string $resolution,
                       int $actorUserId, ?int $actorSellerId = null): array
{
    if (!returnsReady($db)) return [false, 'Возвраты ещё не активированы.'];
    if (!in_array($decision, ['approved', 'rejected'], true)) return [false, 'Неизвестное решение.'];

    $sql  = "SELECT r.*, os.subtotal, os.payout_amount
               FROM order_returns r
               JOIN order_sellers os ON os.id = r.order_seller_id
              WHERE r.id = ?";
    $args = [$returnId];
    if ($actorSellerId !== null) { $sql .= " AND r.seller_id = ?"; $args[] = $actorSellerId; }
    $sql .= " LIMIT 1";

    $st = $db->prepare($sql);
    $st->execute($args);
    $ret = $st->fetch();
    if (!$ret) return [false, 'Заявка не найдена.'];
    if ($ret['status'] !== 'requested') {
        return [false, 'Заявка уже рассмотрена: ' . returnStatusLabel($ret['status'])];
    }

    $own = !$db->inTransaction();
    if ($own) $db->beginTransaction();
    try {
        $reversed = 0.0;

        if ($decision === 'approved' && $ret['seller_id'] !== null) {
            // Снимаем ДОЛЮ ПРОДАВЦА, а не полную стоимость: комиссию площадка
            // возвращает вместе с товаром, а не перекладывает на продавца.
            $reversed = returnPayoutShare(
                (float)$ret['amount'], (float)$ret['subtotal'], (float)$ret['payout_amount']
            );
            if ($reversed > 0) {
                $db->prepare(
                    "INSERT INTO seller_ledger
                        (seller_id, order_seller_id, return_id, type, amount, note, created_at)
                     VALUES (?, NULL, ?, 'refund', ?, ?, NOW())"
                )->execute([
                    (int)$ret['seller_id'], $returnId, -$reversed,
                    'Возврат по заявке #' . $returnId,
                ]);
            }
        }

        $db->prepare(
            "UPDATE order_returns
                SET status = ?, resolution = ?, payout_reversed = ?,
                    resolved_by = ?, resolved_at = NOW()
              WHERE id = ?"
        )->execute([
            $decision,
            ($resolution = trim($resolution)) !== '' ? mb_substr($resolution, 0, 500) : null,
            $reversed, $actorUserId ?: null, $returnId,
        ]);

        if ($own) $db->commit();
    } catch (Throwable $e) {
        if ($own && $db->inTransaction()) $db->rollBack();
        return [false, 'Не удалось сохранить решение.'];
    }

    return [true, $decision === 'approved'
        ? 'Возврат одобрен' . ($reversed > 0 ? ', с баланса продавца снято ' . number_format($reversed, 2, '.', ' ') : '') . '.'
        : 'В возврате отказано.'];
}

/** Покупатель отзывает свою заявку, пока её не рассмотрели. */
function returnCancel(PDO $db, int $returnId, int $userId): array
{
    if (!returnsReady($db)) return [false, 'Возвраты ещё не активированы.'];
    $st = $db->prepare(
        "UPDATE order_returns SET status = 'cancelled'
          WHERE id = ? AND user_id = ? AND status = 'requested'"
    );
    $st->execute([$returnId, $userId]);
    return $st->rowCount()
        ? [true, 'Заявка отозвана.']
        : [false, 'Заявку уже рассмотрели — отозвать нельзя.'];
}

/**
 * Заявки на возврат с контекстом. Один запрос под все три роли — покупателя,
 * продавца и админа, — чтобы правила отбора не разъехались по трём страницам.
 */
function returnList(PDO $db, array $filter = [], int $limit = 200): array
{
    if (!returnsReady($db)) return [];

    $where = []; $args = [];
    if (!empty($filter['user_id']))   { $where[] = 'r.user_id = ?';   $args[] = (int)$filter['user_id']; }
    if (!empty($filter['seller_id'])) { $where[] = 'r.seller_id = ?'; $args[] = (int)$filter['seller_id']; }
    if (!empty($filter['status']))    { $where[] = 'r.status = ?';    $args[] = (string)$filter['status']; }
    $sql = 'WHERE ' . ($where ? implode(' AND ', $where) : '1');

    $st = $db->prepare(
        "SELECT r.*, os.order_id, os.subtotal, os.payout_amount,
                s.shop_name, u.username,
                p.name AS part_name, p.part_number
           FROM order_returns r
           JOIN order_sellers os ON os.id = r.order_seller_id
           LEFT JOIN sellers s     ON s.id = r.seller_id
           LEFT JOIN users u       ON u.id = r.user_id
           LEFT JOIN order_items oi ON oi.id = r.order_item_id
           LEFT JOIN parts p        ON p.id = oi.part_id
         $sql
      ORDER BY (r.status='requested') DESC, r.created_at DESC
         LIMIT " . max(1, min(500, $limit))
    );
    $st->execute($args);
    return $st->fetchAll();
}

/** Сколько заявок ждёт решения — для бейджей в меню. */
function returnsPendingCount(PDO $db, ?int $sellerId = null): int
{
    if (!returnsReady($db)) return 0;
    if ($sellerId !== null) {
        $st = $db->prepare("SELECT COUNT(*) FROM order_returns WHERE status='requested' AND seller_id = ?");
        $st->execute([$sellerId]);
        return (int)$st->fetchColumn();
    }
    return (int)$db->query("SELECT COUNT(*) FROM order_returns WHERE status='requested'")->fetchColumn();
}
