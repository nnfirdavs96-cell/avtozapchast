<?php
require_once dirname(__DIR__) . '/config/config.php';

// Destroy session completely
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();

// Restart session for flash
session_start();
flashMessage('success', 'Вы успешно вышли из системы.');
redirect(APP_URL . '/auth/login.php');
