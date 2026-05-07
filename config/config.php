<?php
define('APP_NAME', 'АвтоЗапчасть');
define('APP_URL', rtrim(getenv('APP_URL') ?: 'http://localhost', '/'));
define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_PATH', APP_ROOT . '/assets/uploads/');
define('UPLOAD_URL',  APP_URL  . '/assets/uploads/');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/i18n.php';
require_once APP_ROOT . '/includes/email.php';

// Resolve active locale & currency on every request (sets cookies if ?lang/?cur passed)
currentLanguage();
currentCurrency();
