<?php
require_once dirname(__DIR__) . '/config/config.php';

// Require login with appropriate role
if (!isLoggedIn() || !in_array($_SESSION['role'] ?? '', ['manager', 'admin', 'superadmin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Доступ запрещён']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Метод не поддерживается']);
    exit;
}

$type    = $_GET['type'] ?? 'products'; // products | sliders | blog | categories
$allowed = ['products', 'sliders', 'blog', 'categories'];
if (!in_array($type, $allowed)) {
    echo json_encode(['error' => 'Недопустимый тип']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errMsg = match($_FILES['file']['error'] ?? -1) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Файл слишком большой',
        UPLOAD_ERR_NO_FILE  => 'Файл не выбран',
        default             => 'Ошибка загрузки файла',
    };
    echo json_encode(['error' => $errMsg]);
    exit;
}

$file     = $_FILES['file'];
$maxBytes = 5 * 1024 * 1024; // 5 MB
if ($file['size'] > $maxBytes) {
    echo json_encode(['error' => 'Файл больше 5 МБ']);
    exit;
}

// Check real MIME
$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!in_array($mimeType, $allowedMimes)) {
    echo json_encode(['error' => 'Допустимые форматы: JPG, PNG, WEBP, GIF']);
    exit;
}

$ext       = match($mimeType) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
};
$uploadDir = APP_ROOT . '/assets/uploads/' . $type . '/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$filename = uniqid($type . '_', true) . '.' . $ext;
$destPath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['error' => 'Не удалось сохранить файл']);
    exit;
}

$relUrl = UPLOAD_URL . $type . '/' . $filename;
echo json_encode(['url' => $relUrl, 'filename' => $filename]);
