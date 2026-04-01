<?php
require_once dirname(__DIR__) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $db    = getDB();
    $param = '%' . $q . '%';
    $stmt  = $db->prepare(
        "SELECT p.id, p.part_number, p.name, p.price, b.name AS brand_name
         FROM parts p
         LEFT JOIN brands b ON b.id = p.brand_id
         WHERE p.is_active = 1
           AND (p.part_number LIKE ? OR p.name LIKE ?)
         ORDER BY
           CASE WHEN p.part_number = ? THEN 0
                WHEN p.part_number LIKE ? THEN 1
                ELSE 2 END,
           p.part_number
         LIMIT 10"
    );
    $stmt->execute([$param, $param, $q, $q . '%']);
    $results = $stmt->fetchAll();
    echo json_encode($results);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Search failed']);
}
