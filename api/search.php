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
           AND (p.name LIKE ? OR p.part_number LIKE ?)
         ORDER BY
           CASE WHEN p.part_number = ? THEN 0
                WHEN p.part_number LIKE ? THEN 1
                ELSE 2 END,
           p.part_number
         LIMIT 8"
    );
    $stmt->execute([$param, $param, $q, $q . '%']);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $output = [];
    foreach ($results as $row) {
        $output[] = [
            'id'              => (int)$row['id'],
            'name'            => $row['name'],
            'part_number'     => $row['part_number'],
            'brand_name'      => $row['brand_name'] ?? '',
            'price'           => (float)$row['price'],
            'price_formatted' => formatPrice($row['price']),
        ];
    }

    echo json_encode($output);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Search failed']);
}
