<?php
require_once __DIR__ . '/../config/config.php';
$code = $_GET['code'] ?? $_POST['code'] ?? '';
if ($code) setLanguage($code);
$ref = $_SERVER['HTTP_REFERER'] ?? APP_URL . '/index.php';
redirect($ref);
