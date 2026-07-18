<?php
/**
 * Переписка покупатель ↔ менеджер.
 *
 * Одна таблица `messages`. «Ветка» (тред) — это пара (user_id, order_id):
 *   • order_id = NULL  → общая переписка поддержки покупателя;
 *   • order_id = число → переписка по конкретному заказу.
 *
 * Отправитель (`sender`):
 *   • customer — написал покупатель;
 *   • staff    — ответил сотрудник (staff_id — кто именно);
 *   • system   — автосообщение сайта (чек по оплаченному заказу, статус и т.п.).
 *
 * Флаги прочтения раздельные: read_by_customer / read_by_staff. Своя сторона
 * помечается прочитанной сразу при отправке; чужая — 0 (появится «непрочитанное»).
 *
 * Схема создаётся лениво (ensureMessagingSchema) — отдельная миграция не нужна.
 */

function ensureMessagingSchema(): void
{
    static $done = false;
    if ($done) return;
    try {
        getDB()->exec("CREATE TABLE IF NOT EXISTS `messages` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `order_id` INT UNSIGNED DEFAULT NULL,
            `sender` ENUM('customer','staff','system') NOT NULL,
            `staff_id` INT UNSIGNED DEFAULT NULL,
            `body` TEXT NOT NULL,
            `read_by_customer` TINYINT(1) NOT NULL DEFAULT 0,
            `read_by_staff` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_thread` (`user_id`, `order_id`, `created_at`),
            KEY `idx_unread_staff` (`read_by_staff`),
            KEY `idx_unread_cust` (`user_id`, `read_by_customer`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $done = true;
    } catch (Exception $e) { /* таблица необязательна — не роняем страницу */ }
}

/**
 * Записать сообщение в ветку. Сторона отправителя помечается прочитанной сразу.
 * Возвращает id вставленной строки или 0 при ошибке.
 */
function postMessage(int $userId, ?int $orderId, string $sender, string $body, ?int $staffId = null): int
{
    ensureMessagingSchema();
    $body = trim($body);
    if ($userId <= 0 || $body === '' || !in_array($sender, ['customer', 'staff', 'system'], true)) return 0;
    // Лимит длины — защита от гигантских вставок.
    $body = mb_substr($body, 0, 4000);
    // Своя сторона читала (система «читается» и покупателем позже, но для staff — да).
    $readCust = $sender === 'customer' ? 1 : 0;
    $readStaff = ($sender === 'staff' || $sender === 'system') ? 1 : 0;
    try {
        $db = getDB();
        $db->prepare(
            "INSERT INTO messages (user_id, order_id, sender, staff_id, body, read_by_customer, read_by_staff, created_at)
             VALUES (?,?,?,?,?,?,?,NOW())"
        )->execute([$userId, $orderId ?: null, $sender, $staffId ?: null, $body, $readCust, $readStaff]);
        return (int)$db->lastInsertId();
    } catch (Exception $e) {
        return 0;
    }
}

/** Автосообщение от системы в ветку (обёртка над postMessage). */
function postSystemMessage(int $userId, ?int $orderId, string $body): int
{
    return postMessage($userId, $orderId, 'system', $body, null);
}

/** Сообщения одной ветки, по возрастанию времени. */
function getThreadMessages(int $userId, ?int $orderId): array
{
    ensureMessagingSchema();
    try {
        $db = getDB();
        if ($orderId === null) {
            $st = $db->prepare("SELECT * FROM messages WHERE user_id = ? AND order_id IS NULL ORDER BY created_at ASC, id ASC");
            $st->execute([$userId]);
        } else {
            $st = $db->prepare("SELECT * FROM messages WHERE user_id = ? AND order_id = ? ORDER BY created_at ASC, id ASC");
            $st->execute([$userId, $orderId]);
        }
        return $st->fetchAll();
    } catch (Exception $e) { return []; }
}

/** Пометить ветку прочитанной со стороны 'customer' или 'staff'. */
function markThreadRead(int $userId, ?int $orderId, string $side): void
{
    ensureMessagingSchema();
    $col = $side === 'staff' ? 'read_by_staff' : 'read_by_customer';
    try {
        $db = getDB();
        if ($orderId === null) {
            $db->prepare("UPDATE messages SET $col = 1 WHERE user_id = ? AND order_id IS NULL AND $col = 0")->execute([$userId]);
        } else {
            $db->prepare("UPDATE messages SET $col = 1 WHERE user_id = ? AND order_id = ? AND $col = 0")->execute([$userId, $orderId]);
        }
    } catch (Exception $e) { /* игнор */ }
}

/**
 * Список веток покупателя: общая поддержка + по каждому заказу, где есть сообщения.
 * Каждый элемент: order_id (null=поддержка), last_body, last_at, unread (для покупателя).
 */
function getCustomerThreads(int $userId): array
{
    ensureMessagingSchema();
    try {
        $db = getDB();
        $st = $db->prepare(
            "SELECT order_id,
                    MAX(created_at) AS last_at,
                    SUM(CASE WHEN read_by_customer = 0 AND sender <> 'customer' THEN 1 ELSE 0 END) AS unread,
                    COUNT(*) AS cnt
             FROM messages WHERE user_id = ?
             GROUP BY order_id
             ORDER BY (order_id IS NULL) DESC, last_at DESC"
        );
        $st->execute([$userId]);
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            $r['last_body'] = messageLastBody($userId, $r['order_id'] !== null ? (int)$r['order_id'] : null);
        }
        return $rows;
    } catch (Exception $e) { return []; }
}

/** Последнее сообщение ветки (короткий текст для превью списка). */
function messageLastBody(int $userId, ?int $orderId): string
{
    try {
        $db = getDB();
        if ($orderId === null) {
            $st = $db->prepare("SELECT body FROM messages WHERE user_id = ? AND order_id IS NULL ORDER BY created_at DESC, id DESC LIMIT 1");
            $st->execute([$userId]);
        } else {
            $st = $db->prepare("SELECT body FROM messages WHERE user_id = ? AND order_id = ? ORDER BY created_at DESC, id DESC LIMIT 1");
            $st->execute([$userId, $orderId]);
        }
        return (string)($st->fetchColumn() ?: '');
    } catch (Exception $e) { return ''; }
}

/** Число непрочитанных покупателем сообщений (для значка в кабинете). */
function customerUnreadCount(int $userId): int
{
    ensureMessagingSchema();
    try {
        $db = getDB();
        $st = $db->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ? AND read_by_customer = 0 AND sender <> 'customer'");
        $st->execute([$userId]);
        return (int)$st->fetchColumn();
    } catch (Exception $e) { return 0; }
}

/**
 * Ветки для сотрудника: по всем покупателям, непрочитанные — выше.
 * Элемент: user_id, order_id, customer name/phone, last_at, unread (для staff).
 */
function getStaffThreads(int $limit = 100): array
{
    ensureMessagingSchema();
    $limit = max(1, min(500, $limit));
    try {
        $db = getDB();
        $st = $db->query(
            "SELECT m.user_id, m.order_id,
                    MAX(m.created_at) AS last_at,
                    SUM(CASE WHEN m.read_by_staff = 0 AND m.sender = 'customer' THEN 1 ELSE 0 END) AS unread,
                    COUNT(*) AS cnt,
                    u.first_name, u.last_name, u.phone_e164, u.email, u.username
             FROM messages m
             JOIN users u ON u.id = m.user_id
             GROUP BY m.user_id, m.order_id
             ORDER BY unread DESC, last_at DESC
             LIMIT $limit"
        );
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            $r['last_body'] = messageLastBody((int)$r['user_id'], $r['order_id'] !== null ? (int)$r['order_id'] : null);
        }
        return $rows;
    } catch (Exception $e) { return []; }
}

/** Число веток с непрочитанными сотрудником сообщениями (значок в админ-меню). */
function staffUnreadCount(): int
{
    ensureMessagingSchema();
    try {
        return (int)getDB()->query(
            "SELECT COUNT(DISTINCT CONCAT(user_id,'-',COALESCE(order_id,0)))
             FROM messages WHERE read_by_staff = 0 AND sender = 'customer'"
        )->fetchColumn();
    } catch (Exception $e) { return 0; }
}

/** Человеко-читаемое имя покупателя из строки ветки (staff-список). */
function threadCustomerName(array $row): string
{
    $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    if ($name !== '') return $name;
    if (!empty($row['username'])) return (string)$row['username'];
    if (!empty($row['phone_e164'])) return '+' . $row['phone_e164'];
    if (!empty($row['email'])) return (string)$row['email'];
    return 'Покупатель #' . (int)($row['user_id'] ?? 0);
}
