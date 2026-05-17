<?php
define('APP_NAME', 'AvtoDoc');
define('APP_URL', '');  // empty = relative URLs; set to https://yourdomain.com for production
// Защита админ-раздела: nginx Basic Auth на /admin, /superadmin, /manager.
// ADMIN_PORT='' отключает PHP-гейт (провайдер форвардит только порт 80).
define('ADMIN_PORT', '');
define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_PATH', APP_ROOT . '/assets/uploads/');
define('UPLOAD_URL', APP_URL . '/assets/uploads/');
define('ASSETS_URL', APP_URL . '/assets');
define('MAZLAY_CSS', APP_URL . '/assets/mazlay-css');
define('MAZLAY_JS',  APP_URL . '/assets/mazlay-js');

// Session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/i18n.php';
require_once APP_ROOT . '/includes/currency.php';
