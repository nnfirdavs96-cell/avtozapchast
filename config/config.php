<?php
define('APP_NAME', 'AutoDoc');
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

// Prevent browsers from caching dynamic HTML so updated CSS/JS (versioned by
// filemtime) are always picked up. Static assets are served directly by the
// web server and keep their own caching, busted via ?v= query strings.
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/i18n.php';
require_once APP_ROOT . '/includes/currency.php';
