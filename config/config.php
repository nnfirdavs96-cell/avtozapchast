<?php
define('APP_NAME', 'АвтоЗапчасть');
define('APP_URL', 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_PATH', APP_ROOT . '/assets/uploads/');
define('UPLOAD_URL', APP_URL . '/assets/uploads/');

// Session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/functions.php';
