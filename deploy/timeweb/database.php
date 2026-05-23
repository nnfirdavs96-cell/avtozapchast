<?php
// Конфиг базы данных для Timeweb
// Скопировать в config/database.php
// Заполнить реальными данными из панели Timeweb → Базы данных

define('DB_HOST', 'localhost');         // Timeweb: всегда localhost
define('DB_USER', 'cs360870_ЗАМЕНИТЕ'); // Пользователь БД из панели Timeweb
define('DB_PASS', 'ЗАМЕНИТЕ_ПАРОЛЬ');   // Пароль БД из панели Timeweb
define('DB_NAME', 'cs360870_ЗАМЕНИТЕ'); // Название БД из панели Timeweb

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
