<?php
/**
 * Восстановление пароля по email: генерация, проверка и «погашение» токенов.
 *
 * Правила безопасности:
 *  - в БД лежит только sha256(token), сам токен уходит лишь в письме;
 *  - токен одноразовый (used_at) и живёт 1 час (expires_at);
 *  - при запросе сброса гасим прежние токены пользователя, чтобы старая ссылка
 *    не работала;
 *  - смена пароля и пометка токена — в одной транзакции.
 */

/** Есть ли таблица токенов (миграция применена). */
function passwordResetReady(?PDO $db = null): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        ($db ?: getDB())->query("SELECT 1 FROM password_resets LIMIT 1")->fetchAll();
        $ready = true;
    } catch (Throwable $e) { $ready = false; }
    return $ready;
}

/**
 * Создать токен сброса для пользователя. Возвращает СЫРОЙ токен (для ссылки в
 * письме) или '' при неудаче. Прежние неиспользованные токены гасит.
 */
function passwordResetCreate(PDO $db, int $userId): string
{
    if (!passwordResetReady($db) || $userId <= 0) return '';
    try {
        // Погасить старые активные токены — чтобы жила только последняя ссылка.
        $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")
           ->execute([$userId]);

        $raw  = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);
        $db->prepare(
            "INSERT INTO password_resets (user_id, token_hash, expires_at, created_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())"
        )->execute([$userId, $hash]);
        return $raw;
    } catch (Throwable $e) {
        return '';
    }
}

/**
 * Найти пользователя по сырому токену, если токен валиден (не использован и не
 * протух). Возвращает [id, username, email] или null.
 */
function passwordResetLookup(PDO $db, string $rawToken): ?array
{
    if (!passwordResetReady($db) || $rawToken === '') return null;
    try {
        $hash = hash('sha256', $rawToken);
        $st = $db->prepare(
            "SELECT r.id AS token_id, u.id, u.username, u.email
               FROM password_resets r
               JOIN users u ON u.id = r.user_id
              WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > NOW()
              LIMIT 1"
        );
        $st->execute([$hash]);
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Установить новый пароль по токену и погасить его. Возвращает [ok, error].
 * Всё в транзакции: либо пароль сменился и токен погашен, либо ничего.
 */
function passwordResetConsume(PDO $db, string $rawToken, string $newPassword): array
{
    $found = passwordResetLookup($db, $rawToken);
    if (!$found) return [false, 'Ссылка недействительна или устарела. Запросите сброс заново.'];

    $own = !$db->inTransaction();
    if ($own) $db->beginTransaction();
    try {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
           ->execute([$hash, (int)$found['id']]);
        // Погасить этот токен и любые другие активные у пользователя.
        $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")
           ->execute([(int)$found['id']]);
        if ($own) $db->commit();
    } catch (Throwable $e) {
        if ($own && $db->inTransaction()) $db->rollBack();
        return [false, 'Не удалось сохранить новый пароль. Попробуйте ещё раз.'];
    }
    return [true, ''];
}
