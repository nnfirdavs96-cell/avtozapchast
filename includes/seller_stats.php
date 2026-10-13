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

/**
 * То же, но СУММАРНО по всем продавцам (для админки).
 * Возвращает ['visits','orders','pay_count','pay_sum','messages','sellers'].
 * Учитываем только подзаказы реальных продавцов (seller_id IS NOT NULL) —
 * позиции нашего каталога (seller_id=NULL) к продавцам не относятся.
 */
function sellerStatsAll(PDO $db, string $period): array
{
    $period = ssNormPeriod($period);
    $from   = ssPeriodStart($period);
    $out = ['visits' => 0, 'orders' => 0, 'pay_count' => 0, 'pay_sum' => 0.0, 'messages' => 0, 'sellers' => 0];

    try {
        $sql = "SELECT COUNT(*) FROM seller_views" . ($from ? " WHERE created_at >= ?" : "");
        $st = $db->prepare($sql); $st->execute($from ? [$from] : []);
        $out['visits'] = (int)$st->fetchColumn();
    } catch (Throwable $e) {}

    try {
        $sql = "SELECT COUNT(*) FROM order_sellers WHERE seller_id IS NOT NULL AND status <> 'cancelled'"
             . ($from ? " AND created_at >= ?" : "");
        $st = $db->prepare($sql); $st->execute($from ? [$from] : []);
        $out['orders'] = (int)$st->fetchColumn();
    } catch (Throwable $e) {}

    try {
        $sql = "SELECT COUNT(*) c, COALESCE(SUM(os.subtotal),0) s
                  FROM order_sellers os JOIN orders o ON o.id = os.order_id
                 WHERE os.seller_id IS NOT NULL AND os.status <> 'cancelled' AND o.payment_status = 'paid'"
             . ($from ? " AND COALESCE(o.paid_at, os.created_at) >= ?" : "");
        $st = $db->prepare($sql); $st->execute($from ? [$from] : []);
        if ($r = $st->fetch()) { $out['pay_count'] = (int)$r['c']; $out['pay_sum'] = (float)$r['s']; }
    } catch (Throwable $e) {}

    try {
        $sql = "SELECT COUNT(*) FROM seller_messages WHERE sender = 'buyer'"
             . ($from ? " AND created_at >= ?" : "");
        $st = $db->prepare($sql); $st->execute($from ? [$from] : []);
        $out['messages'] = (int)$st->fetchColumn();
    } catch (Throwable $e) {}

    try {
        $out['sellers'] = (int)$db->query("SELECT COUNT(*) FROM sellers WHERE status = 'approved'")->fetchColumn();
    } catch (Throwable $e) {}

    return $out;
}

/**
 * Посещения по дням за последние $days дней (заполняя нули).
 * $sellerId = null → по всем продавцам (для админки).
 * Возвращает список ['d' => 'Y-m-d', 'c' => int] по возрастанию даты.
 */
function sellerVisitsSeries(PDO $db, ?int $sellerId, int $days = 30): array
{
    $days = max(1, min($days, 90));
    $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));

    $map = [];
    try {
        $sql = "SELECT DATE(created_at) d, COUNT(*) c FROM seller_views WHERE created_at >= ?";
        $args = [$from . ' 00:00:00'];
        if ($sellerId !== null) { $sql .= " AND seller_id = ?"; $args[] = $sellerId; }
        $sql .= " GROUP BY DATE(created_at)";
        $st = $db->prepare($sql);
        $st->execute($args);
        foreach ($st as $r) { $map[(string)$r['d']] = (int)$r['c']; }
    } catch (Throwable $e) { /* нет таблицы — вернём нули */ }

    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $out[] = ['d' => $d, 'c' => $map[$d] ?? 0];
    }
    return $out;
}

/**
 * Inline-SVG столбчатый график посещений по дням (без внешних библиотек).
 * $series — результат sellerVisitsSeries(). Масштабируется по ширине контейнера.
 */
function visitsChartHtml(array $series, string $caption = 'Посещения по дням (30 дней)'): string
{
    $n = count($series);
    $total = 0; $max = 0;
    foreach ($series as $p) { $total += $p['c']; if ($p['c'] > $max) $max = $p['c']; }

    $head = '<div class="sl-chart"><div class="sl-chart-cap">' . htmlspecialchars($caption, ENT_QUOTES)
          . ' <span class="sl-chart-sum">· всего ' . (int)$total . '</span></div>';

    if ($n === 0 || $total === 0) {
        return $head . '<div class="sl-chart-empty">Пока нет посещений за этот период.</div></div>';
    }

    // Геометрия в координатах viewBox (масштабируется под ширину).
    $W = 720; $H = 190; $padL = 34; $padR = 10; $padT = 14; $padB = 26;
    $plotW = $W - $padL - $padR;
    $plotH = $H - $padT - $padB;
    $slot  = $plotW / $n;
    $bw    = max(2, $slot * 0.64);
    $scaleMax = max(1, $max);
    $today = date('Y-m-d');

    $svg = '<svg class="sl-chart-svg" viewBox="0 0 ' . $W . ' ' . $H . '" preserveAspectRatio="none" role="img" aria-label="' . htmlspecialchars($caption, ENT_QUOTES) . '">';
    // Базовая линия и верхняя подпись-максимум.
    $yBase = $padT + $plotH;
    $svg .= '<line x1="' . $padL . '" y1="' . $yBase . '" x2="' . ($W - $padR) . '" y2="' . $yBase . '" class="sl-chart-axis"/>';
    $svg .= '<line x1="' . $padL . '" y1="' . $padT . '" x2="' . ($W - $padR) . '" y2="' . $padT . '" class="sl-chart-grid"/>';
    $svg .= '<text x="' . ($padL - 6) . '" y="' . ($padT + 4) . '" class="sl-chart-yl" text-anchor="end">' . $scaleMax . '</text>';
    $svg .= '<text x="' . ($padL - 6) . '" y="' . ($yBase + 4) . '" class="sl-chart-yl" text-anchor="end">0</text>';

    $labelEvery = (int)ceil($n / 8);  // ~8 подписей по оси X
    foreach ($series as $i => $p) {
        $x = $padL + $i * $slot + ($slot - $bw) / 2;
        $h = $p['c'] > 0 ? ($p['c'] / $scaleMax) * $plotH : 0;
        $y = $yBase - $h;
        $cls = $p['d'] === $today ? 'sl-bar sl-bar--today' : 'sl-bar';
        $dLabel = date('d.m', strtotime($p['d']));
        $svg .= '<rect class="' . $cls . '" x="' . round($x, 1) . '" y="' . round($y, 1)
              . '" width="' . round($bw, 1) . '" height="' . round($h, 1) . '" rx="2">'
              . '<title>' . $dLabel . ' — ' . (int)$p['c'] . '</title></rect>';
        if ($i % $labelEvery === 0) {
            $tx = $padL + $i * $slot + $slot / 2;
            $svg .= '<text x="' . round($tx, 1) . '" y="' . ($H - 8) . '" class="sl-chart-xl" text-anchor="middle">' . $dLabel . '</text>';
        }
    }
    $svg .= '</svg>';

    return $head . $svg . '</div>';
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
