<?php
/**
 * Хелперы кабинета продавца (маркетплейс).
 */

/** Магазин текущего пользователя-продавца, или null. */
function currentSeller(): ?array
{
    static $cache = null;
    if ($cache !== null) return $cache ?: null;
    if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'seller') { $cache = false; return null; }
    try {
        $st = getDB()->prepare("SELECT * FROM sellers WHERE user_id = ? LIMIT 1");
        $st->execute([(int)$_SESSION['user_id']]);
        $cache = $st->fetch() ?: false;
    } catch (Exception $e) { $cache = false; }
    return $cache ?: null;
}

/** Гейт кабинета: только продавец. Возвращает строку магазина. */
function requireSeller(): array
{
    requireRole('seller');
    $s = currentSeller();
    if (!$s) denyAccess();
    return $s;
}

/** Человеко-понятный статус магазина: [class, label, hint]. */
function sellerStatusInfo(string $status): array
{
    switch ($status) {
        case 'approved':
            return ['ok', 'Магазин одобрен', 'Вы можете добавлять товары — после проверки они появятся в каталоге.'];
        case 'blocked':
            return ['bad', 'Магазин заблокирован', 'Обратитесь к администратору. Добавление товаров недоступно.'];
        default:
            return ['wait', 'Магазин на модерации', 'Дождитесь одобрения администратором — обычно это недолго. После одобрения появится возможность добавлять товары.'];
    }
}

/** Понятный статус товара продавца: [class, label]. */
function sellerProductStatusInfo(string $status): array
{
    switch ($status) {
        case 'active':   return ['ok',   'Опубликован'];
        case 'rejected': return ['bad',  'Отклонён'];
        case 'draft':    return ['draft','Черновик'];
        default:         return ['wait', 'На проверке'];
    }
}
