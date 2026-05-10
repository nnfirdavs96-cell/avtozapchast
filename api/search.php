<?php
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) { echo '[]'; exit; }

try {
    $db   = getDB();
    $like = '%' . $q . '%';
    $stmt = $db->prepare("SELECT p.id, p.name, p.part_number, p.price, b.name AS brand_name
        FROM parts p LEFT JOIN brands b ON b.id=p.brand_id
        WHERE p.is_active=1 AND (p.name LIKE ? OR p.part_number LIKE ? OR b.name LIKE ?)
        LIMIT 8");
    $stmt->execute([$like, $like, $like]);
    $results = $stmt->fetchAll();
    $out = array_map(fn($r) => [
        'id'             => (int)$r['id'],
        'name'           => $r['name'],
        'part_number'    => $r['part_number'],
        'brand_name'     => $r['brand_name'],
        'price_formatted'=> formatPrice($r['price']),
        'url'            => APP_URL . '/catalog/part.php?id=' . $r['id'],
    ], $results);
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo '[]';
}
