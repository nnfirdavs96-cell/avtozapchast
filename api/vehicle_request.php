<?php
/**
 * Заявка продавца на добавление авто, которого нет в справочнике.
 * Уходит админу в раздел «Автомобили» (vehicle_requests). Только для продавца.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/seller.php';
require_once dirname(__DIR__) . '/includes/vehicles.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok' => false, 'error' => 'method']); exit; }
if (!verifyCsrfToken($_POST['_csrf'] ?? '')) { echo json_encode(['ok' => false, 'error' => 'csrf']); exit; }

$seller = currentSeller();
if (!$seller) { echo json_encode(['ok' => false, 'msg' => 'Доступно только продавцу.']); exit; }

$make  = trim((string)($_POST['make'] ?? ''));
$model = trim((string)($_POST['model'] ?? ''));
if ($make === '' || $model === '') { echo json_encode(['ok' => false, 'msg' => 'Укажите марку и модель.']); exit; }

$db = getDB();
ensureVehicleSchema();
try {
    $db->prepare("INSERT INTO vehicle_requests (seller_id, make, model, years, engine) VALUES (?,?,?,?,?)")
       ->execute([
           (int)$seller['id'],
           mb_substr($make, 0, 80),
           mb_substr($model, 0, 100),
           mb_substr(trim((string)($_POST['years'] ?? '')), 0, 40) ?: null,
           mb_substr(trim((string)($_POST['engine'] ?? '')), 0, 60) ?: null,
       ]);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'msg' => 'Не удалось отправить.']);
}
