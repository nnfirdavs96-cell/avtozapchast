<?php
/**
 * Статистика кабинета продавца («Финансовая часть») + переписка покупатель↔продавец.
 *
 * Показатели считаем ТОЛЬКО по одному продавцу (seller_id приходит с сервера из
 * сессии продавца — покупатель чужие цифры увидеть не может). Схему создаём и
 * лениво (ensureSellerStatsSchema), и отдельной миграцией sql/migrate_seller_stats.php.
 */

/** Ленивая гарантия схемы — чтобы запись просмотра/сообщения не падала до миграции. */
function ensureSellerStatsSchema(): void
{
    static $done = false;
    if ($done) return;
    try {
        $db = getDB();
        $db->exec("CREATE TABLE IF NOT EXISTS `seller_views` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `seller_id` INT UNSIGNED NOT NULL,
            `part_id` INT UNSIGNED DEFAULT NULL,
            `visitor_key` VARCHAR(64) NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_seller_date` (`seller_id`, `created_at`),
            KEY `idx_dedup` (`seller_id`, `part_id`, `visitor_key`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE IF NOT EXISTS `seller_messages` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `seller_id` INT UNSIGNED NOT NULL,
            `buyer_id` INT UNSIGNED NOT NULL,
            `part_id` INT UNSIGNED DEFAULT NULL,
            `sender` ENUM('buyer','seller') NOT NULL,
            `body` TEXT NOT NULL,
            `read_by_seller` TINYINT(1) NOT NULL DEFAULT 0,
            `read_by_buyer` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_seller_date` (`seller_id`, `created_at`),
            KEY `idx_thread` (`seller_id`, `buyer_id`, `created_at`),
            KEY `idx_unread_seller` (`seller_id`, `read_by_seller`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $done = true;
    } catch (Throwable $e) { /* не роняем страницу, если схему не удалось создать */ }
}

/** Это бот? Грубая проверка по User-Agent — чтобы не накручивать посещения. */
function ssIsBot(string $ua): bool
{
    if ($ua === '') return true;  // без UA — почти всегда бот/скрипт
    return (bool)preg_match('~bot|crawl|spider|slurp|bing|yandex|google|baidu|duckduck|facebookexternal|semrush|ahrefs|mj12|dotbot|petal|headless|python-requests|curl|wget|httpclient~i', $ua);
}

/** Ключ посетителя: привязываем к сессии, иначе к IP — для дедупа повторов. */
function ssVisitorKey(): string
{
    $sid = session_id();
    $base = $sid !== '' ? 'S:' . $sid : 'I:' . ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    return substr(hash('sha256', $base), 0, 64);
}

/**
 * Записать просмотр страницы продавца. $partId = null — страница магазина.
 * Повтор той же страницы тем же посетителем за 30 минут не пишем. Ботов не пишем.
 */
function recordSellerView(PDO $db, int $sellerId, ?int $partId = null): void
{
    if ($sellerId <= 0) return;
    if (ssIsBot((string)($_SERVER['HTTP_USER_AGENT'] ?? ''))) return;
    ensureSellerStatsSchema();
    try {
        $key = ssVisitorKey();
        // Дедуп: был ли такой же просмотр за последние 30 минут?
        $cond = $partId === null ? 'part_id IS NULL' : 'part_id = :pid';
        $sql  = "SELECT 1 FROM seller_views
                  WHERE seller_id = :sid AND visitor_key = :vk AND $cond
                    AND created_at >= (NOW() - INTERVAL 30 MINUTE) LIMIT 1";
        $st = $db->prepare($sql);
        $st->bindValue(':sid', $sellerId, PDO::PARAM_INT);
        $st->bindValue(':vk', $key);
        if ($partId !== null) $st->bindValue(':pid', $partId, PDO::PARAM_INT);
        $st->execute();
        if ($st->fetchColumn()) return;  // уже считали недавно

        $ins = $db->prepare("INSERT INTO seller_views (seller_id, part_id, visitor_key) VALUES (?, ?, ?)");
        $ins->execute([$sellerId, $partId, $key]);
    } catch (Throwable $e) { /* статистика не критична — молчим */ }
}

/** Начало периода: 'today' | '7d' | '30d' | 'all'. null = без ограничения (всё время). */
function ssPeriodStart(string $period): ?string
{
    switch ($period) {
        case 'today': return date('Y-m-d 00:00:00');
        case '7d':    return date('Y-m-d H:i:s', strtotime('-7 days'));
        case 'all':   return null;
        case '30d':
        default:      return date('Y-m-d H:i:s', strtotime('-30 days'));
    }
}

/** Допустимое значение периода (с дефолтом 30d). */
function ssNormPeriod(?string $p): string
{
    return in_array($p, ['today', '7d', '30d', 'all'], true) ? $p : '30d';
}

/**
 * Сводка показателей для продавца за период.
 * Возвращает ['visits','orders','pay_count','pay_sum','messages'].
 * Любая недостающая таблица → 0 (страница не падает).
 */
function sellerStats(PDO $db, int $sellerId, string $period): array
{
    $period = ssNormPeriod($period);
    $from   = ssPeriodStart($period);
    $out = ['visits' => 0, 'orders' => 0, 'pay_count' => 0, 'pay_sum' => 0.0, 'messages' => 0];
    if ($sellerId <= 0) return $out;

    // Посещения магазина (страница магазина + страницы товаров).
    try {
        $sql = "SELECT COUNT(*) FROM seller_views WHERE seller_id = ?" . ($from ? " AND created_at >= ?" : "");
        $st = $db->prepare($sql);
        $st->execute($from ? [$sellerId, $from] : [$sellerId]);
        $out['visits'] = (int)$st->fetchColumn();
    } catch (Throwable $e) {}

    // Заказы (подзаказы продавца), кроме отменённых.
    try {
        $sql = "SELECT COUNT(*) FROM order_sellers WHERE seller_id = ? AND status <> 'cancelled'"
             . ($from ? " AND created_at >= ?" : "");
        $st = $db->prepare($sql);
        $st->execute($from ? [$sellerId, $from] : [$sellerId]);
        $out['orders'] = (int)$st->fetchColumn();
    } catch (Throwable $e) {}

    // Оплаты: подзаказы продавца в оплаченных заказах. Период — по дате оплаты.
    try {
        $sql = "SELECT COUNT(*) c, COALESCE(SUM(os.subtotal),0) s
                  FROM order_sellers os
                  JOIN orders o ON o.id = os.order_id
                 WHERE os.seller_id = ? AND os.status <> 'cancelled' AND o.payment_status = 'paid'"
             . ($from ? " AND COALESCE(o.paid_at, os.created_at) >= ?" : "");
        $st = $db->prepare($sql);
        $st->execute($from ? [$sellerId, $from] : [$sellerId]);
        if ($r = $st->fetch()) { $out['pay_count'] = (int)$r['c']; $out['pay_sum'] = (float)$r['s']; }
    } catch (Throwable $e) {}

    // Сообщения от покупателей.
    try {
        $sql = "SELECT COUNT(*) FROM seller_messages WHERE seller_id = ? AND sender = 'buyer'"
             . ($from ? " AND created_at >= ?" : "");
        $st = $db->prepare($sql);
        $st->execute($from ? [$sellerId, $from] : [$sellerId]);
        $out['messages'] = (int)$st->fetchColumn();
    } catch (Throwable $e) {}

    return $out;
}

/* ── Переписка покупатель ↔ продавец ──────────────────────────────────────── */

/** Записать сообщение в ветку (продавец, покупатель). Возвращает id или 0. */
function sellerPostMessage(PDO $db, int $sellerId, int $buyerId, ?int $partId, string $sender, string $body): int
{
    ensureSellerStatsSchema();
    $body = trim($body);
    if ($sellerId <= 0 || $buyerId <= 0 || $body === '' || !in_array($sender, ['buyer', 'seller'], true)) return 0;
    $body = mb_substr($body, 0, 4000);
    $readSeller = $sender === 'seller' ? 1 : 0;   // своя сторона прочитана сразу
    $readBuyer  = $sender === 'buyer' ? 1 : 0;
    try {
        $db->prepare("INSERT INTO seller_messages
            (seller_id, buyer_id, part_id, sender, body, read_by_seller, read_by_buyer)
            VALUES (?,?,?,?,?,?,?)")
           ->execute([$sellerId, $buyerId, $partId, $sender, $body, $readSeller, $readBuyer]);
        return (int)$db->lastInsertId();
    } catch (Throwable $e) { return 0; }
}

/** Сколько непрочитанных продавцом сообщений. */
function sellerUnreadCount(PDO $db, int $sellerId): int
{
    if ($sellerId <= 0) return 0;
    try {
        $st = $db->prepare("SELECT COUNT(*) FROM seller_messages WHERE seller_id = ? AND sender = 'buyer' AND read_by_seller = 0");
        $st->execute([$sellerId]);
        return (int)$st->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

/** Список веток продавца (по покупателям): последнее сообщение + непрочитанные. */
function sellerThreads(PDO $db, int $sellerId): array
{
    if ($sellerId <= 0) return [];
    try {
        $st = $db->prepare(
            "SELECT m.buyer_id,
                    u.username AS buyer_name,
                    MAX(m.created_at) AS last_at,
                    SUM(m.sender = 'buyer' AND m.read_by_seller = 0) AS unread,
                    COUNT(*) AS total
               FROM seller_messages m
               LEFT JOIN users u ON u.id = m.buyer_id
              WHERE m.seller_id = ?
           GROUP BY m.buyer_id, u.username
           ORDER BY last_at DESC"
        );
        $st->execute([$sellerId]);
        return $st->fetchAll();
    } catch (Throwable $e) { return []; }
}

/** Сообщения одной ветки (продавец ↔ конкретный покупатель), по возрастанию. */
function sellerThreadMessages(PDO $db, int $sellerId, int $buyerId): array
{
    if ($sellerId <= 0 || $buyerId <= 0) return [];
    try {
        $st = $db->prepare(
            "SELECT * FROM seller_messages
              WHERE seller_id = ? AND buyer_id = ?
           ORDER BY created_at ASC, id ASC LIMIT 500"
        );
        $st->execute([$sellerId, $buyerId]);
        return $st->fetchAll();
    } catch (Throwable $e) { return []; }
}

/** Пометить ветку прочитанной продавцом. */
function sellerMarkThreadRead(PDO $db, int $sellerId, int $buyerId): void
{
    if ($sellerId <= 0 || $buyerId <= 0) return;
    try {
        $db->prepare("UPDATE seller_messages SET read_by_seller = 1
                       WHERE seller_id = ? AND buyer_id = ? AND sender = 'buyer' AND read_by_seller = 0")
           ->execute([$sellerId, $buyerId]);
    } catch (Throwable $e) {}
}
