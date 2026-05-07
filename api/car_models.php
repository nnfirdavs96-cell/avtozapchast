<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json; charset=utf-8');

$makeId = (int)($_GET['make_id'] ?? 0);
echo json_encode(['models' => $makeId ? getCarModels($makeId) : []]);
