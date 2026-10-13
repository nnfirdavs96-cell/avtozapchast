<?php
/**
 * Статистика «Финансовой части» кабинета продавца — JSON для AJAX-переключателя
 * периода. Отдаёт цифры ТОЛЬКО по текущему продавцу (seller_id берём из сессии,
 * из запроса его взять нельзя) — чужие данные увидеть невозможно.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/seller.php';
require_once dirname(__DIR__) . '/includes/seller_stats.php';

header('Content-Type: application/json; charset=utf-8');

$seller = currentSeller();
if (!$seller) { echo json_encode(['ok' => false, 'error' => 'auth']); exit; }

$db     = getDB();
$sid    = (int)$seller['id'];
$period = ssNormPeriod(is_scalar($_GET['period'] ?? null) ? (string)$_GET['period'] : '30d');

$s = sellerStats($db, $sid, $period);

echo json_encode([
    'ok'          => true,
    'period'      => $period,
    'visits'      => (int)$s['visits'],
    'orders'      => (int)$s['orders'],
    'pay_count'   => (int)$s['pay_count'],
    'pay_sum'     => (float)$s['pay_sum'],
    'pay_sum_html'=> formatPrice($s['pay_sum']),
    'messages'    => (int)$s['messages'],
], JSON_UNESCAPED_UNICODE);
