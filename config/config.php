<?php
define('APP_NAME', 'AutoDoc');

// APP_URL is resolved automatically so it survives `git pull` on the host:
//   • local dev (localhost / 127.0.0.1) → relative URLs (works on any port);
//   • production → the canonical domain, so links and redirects never expose
//     the internal server IP that Timeweb's reverse proxy forwards as Host
//     (e.g. 10.230.13.107). The proxy would otherwise rewrite relative
//     redirects to that private IP and pin the browser to it.
if (!defined('APP_URL')) {
    $__host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
    if ($__host === '' || $__host === 'localhost' || $__host === '127.0.0.1' || $__host === '::1') {
        define('APP_URL', '');                    // local dev → relative URLs
    } else {
        define('APP_URL', 'https://autodoc.tj');  // production → canonical domain
    }
    unset($__host);
}
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
require_once APP_ROOT . '/includes/cart_lib.php';
require_once APP_ROOT . '/includes/i18n.php';
require_once APP_ROOT . '/includes/currency.php';
