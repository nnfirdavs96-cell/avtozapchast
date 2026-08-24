<?php
/**
 * Подключение к базе.
 *
 * В книге показана строка подключения к MySQL. Здесь, чтобы пример
 * запускался без установки сервера, используется SQLite — весь остальной
 * код PDO не меняется ни на строчку. В этом и смысл PDO.
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    try {
        $pdo = new PDO('sqlite:' . __DIR__ . '/../magazin.sqlite', null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        error_log('Не удалось подключиться к базе: ' . $e->getMessage());
        http_response_code(503);
        exit('Сайт временно недоступен. Попробуйте через несколько минут.');
    }
    return $pdo;
}

function zapros(string $sql, array $parametry = []): array
{
    $st = db()->prepare($sql);
    $st->execute($parametry);
    return $st->fetchAll();
}

function zapros_odin(string $sql, array $parametry = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($parametry);
    $stroka = $st->fetch();
    return $stroka === false ? null : $stroka;
}

function zapros_znachenie(string $sql, array $parametry = [])
{
    $st = db()->prepare($sql);
    $st->execute($parametry);
    return $st->fetchColumn();
}

function vypolnit(string $sql, array $parametry = []): int
{
    $st = db()->prepare($sql);
    $st->execute($parametry);
    return $st->rowCount();
}

function posledniy_id(): int
{
    return (int) db()->lastInsertId();
}
