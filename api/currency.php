<?php
require_once __DIR__ . '/../config/config.php';
$code = $_GET['code'] ?? $_POST['code'] ?? '';
if ($code) setCurrency($code);
$ref = $_SERVER['HTTP_REFERER'] ?? APP_URL . '/index.php';
redirect($ref);
