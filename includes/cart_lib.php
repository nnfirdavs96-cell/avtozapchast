<?php
/**
 * Единое хранилище корзины для гостя и авторизованного покупателя (Этап 5).
 *
 *   • Гость           — корзина в сессии ($_SESSION['guest_cart'] = [part_id => qty]).
 *   • Авторизованный  — таблица cart (как было, поведение 1:1).
 *
 * Снимает login-wall: гость подбирает по VIN, кладёт в корзину и оформляет заказ
 * без регистрации. При входе гостевая корзина сливается в БД
 * (cartMergeGuestIntoUser, вызывается из loginUser()). Заказ гостя привязывается
 * к аккаунту по телефону (guestOrderUserId) — без изменения схемы orders.
 *
 * Контракт позиции (cartDetailedItems): ['id','part_id','name','price','images',
 *   'stock','part_number','brand_name','quantity'].
 */

function cartIsGuest(): bool { return !isLoggedIn(); }

/** Нормализованная карта корзины: [part_id => quantity]. */
function cartRawMap(PDO $db): array
{
    if (cartIsGuest()) {
        $m = $_SESSION['guest_cart'] ?? [];
        if (!is_array($m)) return [];
        $out = [];
        foreach ($m as $pid => $qty) {
            $pid = (int)$pid; $qty = (int)$qty;
            if ($pid > 0 && $qty > 0) $out[$pid] = $qty;
        }
        return $out;
    }
    try {
        $st = $db->prepare("SELECT part_id, quantity FROM cart WHERE user_id = ?");
        $st->execute([(int)$_SESSION['user_id']]);
        $out = [];
        foreach ($st->fetchAll() as $r) $out[(int)$r['part_id']] = (int)$r['quantity'];
        return $out;
    } catch (\Throwable $e) { return []; }
}

function cartAdd(PDO $db, int $partId, int $qty): void
{
    if ($partId <= 0) return;
    $qty = max(1, min(99, $qty));
    if (cartIsGuest()) {
        if (!isset($_SESSION['guest_cart']) || !is_array($_SESSION['guest_cart'])) $_SESSION['guest_cart'] = [];
        $cur = (int)($_SESSION['guest_cart'][$partId] ?? 0);
        $_SESSION['guest_cart'][$partId] = min(99, $cur + $qty);
        return;
    }
    $db->prepare(
        "INSERT INTO cart (user_id, part_id, quantity) VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + VALUES(quantity), 99), added_at = NOW()"
    )->execute([(int)$_SESSION['user_id'], $partId, $qty]);
}

function cartSetQty(PDO $db, int $partId, int $qty): void
{
    if ($partId <= 0) return;
    $qty = max(1, min(99, $qty));
    if (cartIsGuest()) {
        if (isset($_SESSION['guest_cart'][$partId])) $_SESSION['guest_cart'][$partId] = $qty;
        return;
    }
    $db->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND part_id = ?")
       ->execute([$qty, (int)$_SESSION['user_id'], $partId]);
}

function cartRemove(PDO $db, int $partId): void
{
    if (cartIsGuest()) { unset($_SESSION['guest_cart'][$partId]); return; }
    $db->prepare("DELETE FROM cart WHERE user_id = ? AND part_id = ?")
       ->execute([(int)$_SESSION['user_id'], $partId]);
}

function cartClearAny(PDO $db): void
{
    if (cartIsGuest()) { unset($_SESSION['guest_cart']); return; }
    $db->prepare("DELETE FROM cart WHERE user_id = ?")->execute([(int)$_SESSION['user_id']]);
}

/** Детализированные позиции (join parts), только активные товары. */
function cartDetailedItems(PDO $db): array
{
    $map = cartRawMap($db);
    if (!$map) return [];
    try {
        $ids = array_keys($map);
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $st  = $db->prepare(
            "SELECT p.id, p.name, p.price, p.images, p.stock, p.part_number,
                    b.name AS brand_name
               FROM parts p LEFT JOIN brands b ON b.id = p.brand_id
              WHERE p.is_active = 1 AND p.id IN ($ph)"
        );
        $st->execute($ids);
        $rows = [];
        foreach ($st->fetchAll() as $r) {
            $pid = (int)$r['id'];
            $r['part_id']  = $pid;
            $r['quantity'] = (int)($map[$pid] ?? 1);
            $rows[] = $r;
        }
        return $rows;
    } catch (\Throwable $e) { return []; }
}

function cartCountAny(PDO $db): int
{
    $n = 0;
    foreach (cartRawMap($db) as $q) $n += (int)$q;
    return $n;
}

function cartTotalAny(PDO $db): float
{
    $t = 0.0;
    foreach (cartDetailedItems($db) as $it) $t += (float)$it['price'] * (int)$it['quantity'];
    return $t;
}

/** Слить гостевую корзину (сессия) в БД-корзину пользователя; очистить сессию. */
function cartMergeGuestIntoUser(PDO $db, int $userId): void
{
    $g = $_SESSION['guest_cart'] ?? [];
    unset($_SESSION['guest_cart']);
    if ($userId <= 0 || !is_array($g) || !$g) return;
    try {
        $ins = $db->prepare(
            "INSERT INTO cart (user_id, part_id, quantity) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + VALUES(quantity), 99), added_at = NOW()"
        );
        foreach ($g as $pid => $qty) {
            $pid = (int)$pid; $qty = max(1, min(99, (int)$qty));
            if ($pid > 0) { try { $ins->execute([$userId, $pid, $qty]); } catch (Exception $e) {} }
        }
    } catch (\Throwable $e) { /* merge — не критично */ }
}

/**
 * Заказ гостя привязываем к аккаунту по телефону: если пользователь с таким
 * номером есть — берём его, иначе создаём облегчённый аккаунт (как при регистрации
 * по телефону: без пароля, role=buyer). Так гость потом сможет войти по SMS и
 * увидеть заказ, а схему orders менять не нужно. Возврат: user_id или 0.
 */
function guestOrderUserId(PDO $db, string $phone, string $firstName = '', string $lastName = ''): int
{
    $norm = normalizePhone($phone);
    if ($norm === '') return 0;
    try {
        // Уже есть аккаунт с этим номером?
        $st = $db->prepare("SELECT id FROM users WHERE phone_e164 = ? OR phone = ? LIMIT 1");
        $st->execute([$norm, '+' . $norm]);
        $existing = (int)($st->fetchColumn() ?: 0);
        if ($existing > 0) return $existing;

        // Уникальный username вида user{last4}.
        $base = 'user' . substr($norm, -4);
        $uname = $base; $i = 0;
        while (true) {
            $chk = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $chk->execute([$uname]);
            if (!$chk->fetch()) break;
            $uname = $base . (++$i);
        }
        $db->prepare(
            "INSERT INTO users (username, email, password_hash, role, phone, phone_e164, is_active, created_at)
             VALUES (?, NULL, NULL, 'buyer', ?, ?, 1, NOW())"
        )->execute([$uname, '+' . $norm, $norm]);
        return (int)$db->lastInsertId();
    } catch (\Throwable $e) {
        return 0;
    }
}
