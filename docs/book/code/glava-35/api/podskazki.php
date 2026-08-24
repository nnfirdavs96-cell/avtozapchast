<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json; charset=utf-8');

$zapros = trim($_GET['q'] ?? '');
if (mb_strlen($zapros) < 2) { echo json_encode([]); exit; }

$nachalo = $zapros . '%';
$vezde   = '%' . $zapros . '%';

// Сначала совпадения с начала (их индекс находит быстро), затем вхождения.
// Ранг решает порядок, LIMIT 8 не даёт запросу разрастись.
$stroki = zapros('
    SELECT id, nazvanie, artikul, cena,
           CASE WHEN artikul LIKE ? THEN 1
                WHEN nazvanie LIKE ? THEN 2
                ELSE 3 END AS rang
    FROM tovary
    WHERE aktivnyi = 1
      AND (nazvanie LIKE ? OR artikul LIKE ?)
    ORDER BY rang, ostatok DESC
    LIMIT 8
', [$nachalo, $nachalo, $vezde, $vezde]);

echo json_encode($stroki, JSON_UNESCAPED_UNICODE);
