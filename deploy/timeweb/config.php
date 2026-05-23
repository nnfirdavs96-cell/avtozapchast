<?php
// Продакшн конфиг для Timeweb (autodoc.tj)
// Скопировать этот файл в config/config.php перед загрузкой на Timeweb

define('APP_NAME', 'AutoDoc');
define('APP_URL', 'https://autodoc.tj');
define('ADMIN_PORT', '');
define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_PATH', APP_ROOT . '/assets/uploads/');
define('UPLOAD_URL', APP_URL . '/assets/uploads/');
define('ASSETS_URL', APP_URL . '/assets');
define('MAZLAY_CSS', APP_URL . '/assets/mazlay-css');
define('MAZLAY_JS',  APP_URL . '/assets/mazlay-js');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/i18n.php';
require_once APP_ROOT . '/includes/currency.php';
