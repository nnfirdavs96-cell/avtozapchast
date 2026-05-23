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

function getDB() {
    static $pdo = null;
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
