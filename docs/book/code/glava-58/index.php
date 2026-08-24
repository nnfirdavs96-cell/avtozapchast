<?php
// Принудительный переход на https: первое, что делает сайт на каждом запросе.
$zashchishcheno = ($_SERVER['HTTPS'] ?? '') === 'on'
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

if (!$zashchishcheno) {
    $adres = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('Location: ' . $adres, true, 301);
    exit;
}

header('Strict-Transport-Security: max-age=31536000');
echo "Соединение защищено\n";
