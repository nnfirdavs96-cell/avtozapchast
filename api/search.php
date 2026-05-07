<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json; charset=utf-8');

$q = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) { echo json_encode(['results' => []]); exit; }

try {
    $db = getDB();
    $param = '%' . $q . '%';
    $stmt  = $db->prepare(
        "SELECT p.id, p.part_number, p.name, p.price, b.name AS brand_name
         FROM parts p
         LEFT JOIN brands b ON b.id=p.brand_id
         WHERE p.is_active=1 AND (p.part_number LIKE ? OR p.name LIKE ?)
         ORDER BY CASE WHEN p.part_number=? THEN 0
                       WHEN p.part_number LIKE ? THEN 1 ELSE 2 END,
                  p.part_number
         LIMIT 10"
    );
    $stmt->execute([$param, $param, $q, $q . '%']);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['url'] = APP_URL . '/catalog/part.php?id=' . (int)$r['id'];
        $r['price_formatted'] = money($r['price']);
    }
    echo json_encode(['results' => $rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['results' => [], 'error' => 'Search failed']);
}
