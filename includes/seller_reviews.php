<?php
/**
 * Отзывы о продавцах (маркетплейс, Фаза 4).
 *
 * Отвечают на вопрос, на который не отвечали ни отзывы о товаре, ни отзывы о нашем
 * магазине: КАКОВ ЭТОТ ПРОДАВЕЦ. Вовремя ли отправляет, соответствует ли товар
 * описанию. Без такой оценки покупателю не на что опереться при выборе между
 * предложениями на одной карточке, а честному продавцу нечем отличаться от небрежного.
 *
 * Ключевое правило: оценить можно только ВЫПОЛНЕННЫЙ подзаказ. Отзыв привязан к
 * `order_seller_id`, и это одновременно доказательство покупки и защита от накрутки —
 * без заказа отзыва не существует, а на один заказ он ровно один.
 */

/** Готова ли схема Фазы 4? Результат кэшируется на запрос. */
function sellerReviewsReady(?PDO $db = null): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        // Пробуем саму таблицу, а не information_schema: пробник должен выполнять
        // то же, что потом выполнит рабочий код, и работать на любой СУБД. Через
        // information_schema эта проверка была непроверяемой в тестах — а
        // непроверенный пробник уже однажды тихо отключил половину витрины.
        ($db ?: getDB())->query("SELECT 1 FROM seller_reviews LIMIT 1")->fetchAll();
        $ready = true;
    } catch (Throwable $e) { $ready = false; }
    return $ready;
}

/**
 * Рейтинги продавцов: [seller_id => ['avg' => 4.7, 'count' => 12]].
 *
 * Считаем только одобренные: непроверенный отзыв не должен влиять на то, чьё
 * предложение покупатель увидит первым.
 */
function sellerRatings(PDO $db, array $sellerIds): array
{
    $ids = array_values(array_filter(array_map('intval', $sellerIds)));
    if (!$ids || !sellerReviewsReady($db)) return [];

    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $db->prepare(
        "SELECT seller_id, AVG(rating) AS avg_r, COUNT(*) AS cnt
           FROM seller_reviews
          WHERE status = 'approved' AND seller_id IN ($in)
       GROUP BY seller_id"
    );
    $st->execute($ids);

    $out = [];
    foreach ($st->fetchAll() as $r) {
        $out[(int)$r['seller_id']] = [
            'avg'   => round((float)$r['avg_r'], 1),
            'count' => (int)$r['cnt'],
        ];
    }
    return $out;
}

/**
 * Подзаказы, которые покупатель может оценить: доставленные и ещё без отзыва.
 *
 * Наш собственный каталог (`seller_id IS NULL`) сюда не попадает — площадка не
 * собирает отзывы сама о себе, для этого есть `shop_reviews`.
 */
function sellerReviewableOrders(PDO $db, int $userId): array
{
    if ($userId <= 0 || !sellerReviewsReady($db)) return [];

    $st = $db->prepare(
        "SELECT os.id AS order_seller_id, os.order_id, os.seller_id, os.updated_at,
                s.shop_name, s.slug
           FROM order_sellers os
           JOIN orders  o ON o.id = os.order_id
           JOIN sellers s ON s.id = os.seller_id
           LEFT JOIN seller_reviews r ON r.order_seller_id = os.id
          WHERE o.user_id = ? AND os.status = 'delivered'
            AND os.seller_id IS NOT NULL AND r.id IS NULL
       ORDER BY os.updated_at DESC"
    );
    $st->execute([$userId]);
    return $st->fetchAll();
}

/**
 * Сохранить отзыв покупателя о продавце.
 *
 * Право оставить отзыв проверяется здесь, а не в вызывающем коде: это правило
 * безопасности, и оно должно быть в одном месте. Проверяем, что подзаказ
 * действительно принадлежит этому покупателю и действительно доставлен.
 *
 * Возвращает [успех, сообщение].
 */
function sellerReviewSubmit(PDO $db, int $userId, int $orderSellerId,
                            int $rating, string $comment): array
{
    if (!sellerReviewsReady($db)) return [false, 'Отзывы о продавцах ещё не активированы.'];
    if ($rating < 1 || $rating > 5) return [false, 'Оценка должна быть от 1 до 5.'];

    $st = $db->prepare(
        "SELECT os.id, os.seller_id
           FROM order_sellers os
           JOIN orders o ON o.id = os.order_id
          WHERE os.id = ? AND o.user_id = ? AND os.status = 'delivered'
            AND os.seller_id IS NOT NULL
          LIMIT 1"
    );
    $st->execute([$orderSellerId, $userId]);
    $row = $st->fetch();
    if (!$row) {
        return [false, 'Оценить можно только доставленный заказ этого продавца.'];
    }

    // UNIQUE(order_seller_id) не даст оставить второй отзыв на тот же заказ, но
    // сообщение об ошибке БД покупателю показывать нельзя — проверяем заранее.
    $chk = $db->prepare("SELECT id FROM seller_reviews WHERE order_seller_id = ? LIMIT 1");
    $chk->execute([$orderSellerId]);
    if ($chk->fetch()) return [false, 'Вы уже оценили этот заказ.'];

    $db->prepare(
        "INSERT INTO seller_reviews (seller_id, user_id, order_seller_id, rating, comment, status, created_at)
         VALUES (?, ?, ?, ?, ?, 'pending', NOW())"
    )->execute([
        (int)$row['seller_id'], $userId, $orderSellerId, $rating,
        ($comment = trim($comment)) !== '' ? mb_substr($comment, 0, 2000) : null,
    ]);

    return [true, 'Спасибо! Отзыв отправлен на проверку и появится после модерации.'];
}

/** Одобренные отзывы о продавце — для страницы магазина. */
function sellerReviewList(PDO $db, int $sellerId, int $limit = 50): array
{
    if ($sellerId <= 0 || !sellerReviewsReady($db)) return [];
    $st = $db->prepare(
        "SELECT r.rating, r.comment, r.reply, r.created_at, u.username
           FROM seller_reviews r
           JOIN users u ON u.id = r.user_id
          WHERE r.seller_id = ? AND r.status = 'approved'
       ORDER BY r.created_at DESC
          LIMIT " . max(1, min(200, $limit))
    );
    $st->execute([$sellerId]);
    return $st->fetchAll();
}

/**
 * Звёзды рейтинга одной строкой. Половинки не рисуем: на мелком шрифте они
 * неразличимы, а число рядом и так всё говорит.
 */
function sellerRatingHtml(?array $rating): string
{
    if (!$rating || empty($rating['count'])) {
        return '<span class="srev_none">нет отзывов</span>';
    }
    $full = (int)round($rating['avg']);
    $stars = str_repeat('★', max(0, min(5, $full))) . str_repeat('☆', max(0, 5 - $full));
    return '<span class="srev_stars" title="' . sanitize((string)$rating['avg']) . ' из 5">'
         . $stars . '</span> <span class="srev_count">'
         . number_format($rating['avg'], 1, '.', '') . ' (' . (int)$rating['count'] . ')</span>';
}
