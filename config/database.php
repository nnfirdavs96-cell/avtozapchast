<?php
// Per-server DB credentials live in config/db_credentials.php (git-ignored).
// Each environment (Debian dev / Timeweb prod) keeps its own copy, so a
// `git pull` never overwrites the other server's connection settings.
$localCreds = __DIR__ . '/db_credentials.php';
if (is_file($localCreds)) {
    require $localCreds;
}

// Fallback defaults (Debian dev) — used only if db_credentials.php is absent.
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'avtouser');
if (!defined('DB_PASS')) define('DB_PASS', 'Avto@2024!');
if (!defined('DB_NAME')) define('DB_NAME', 'avtozapchast');

/**
 * Соединение с БД (singleton).
 *
 * $reconnect=true — принудительно создать новое соединение. Нужно длительным
 * CLI-скриптам (сбор каталога, импорт прайса): между запросами к БД они минутами
 * ходят по HTTP, MySQL успевает закрыть простаивающее соединение по wait_timeout,
 * и следующий запрос падает с «MySQL server has gone away». Статик держал мёртвый
 * PDO и сам не восстанавливался — процесс умирал. См. dbKeepAlive().
 */
function getDB(bool $reconnect = false) {
    static $pdo = null;
    if ($reconnect) $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER, DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            die(json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

/**
 * Проверить соединение и восстановить его, если MySQL уже закрыл сессию.
 * Вызывать в долгих CLI-скриптах перед блоками работы с БД — на обычных
 * страницах не нужно (там запрос живёт секунды). Дешёвый `SELECT 1`.
 */
function dbKeepAlive(): PDO {
    try {
        $pdo = getDB();
        $pdo->query('SELECT 1');
        return $pdo;
    } catch (PDOException $e) {
        return getDB(true);   // соединение умерло — поднимаем заново
    }
}
