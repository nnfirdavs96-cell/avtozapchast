<?php
/**
 * Сводная статистика магазинов (все продавцы) для админки — JSON для
 * переключателя периода. Доступ только админу/суперадмину.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/seller_stats.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !in_array($_SESSION['role'] ?? '', ['admin', 'superadmin'], true)) {
    echo json_encode(['ok' => false, 'error' => 'auth']); exit;
}

$db     = getDB();
$period = ssNormPeriod(is_scalar($_GET['period'] ?? null) ? (string)$_GET['period'] : '30d');
$s      = sellerStatsAll($db, $period);

echo json_encode([
    'ok'           => true,
    'period'       => $period,
    'visits'       => (int)$s['visits'],
    'orders'       => (int)$s['orders'],
    'pay_count'    => (int)$s['pay_count'],
    'pay_sum'      => (float)$s['pay_sum'],
    'pay_sum_html' => formatPrice($s['pay_sum']),
    'messages'     => (int)$s['messages'],
], JSON_UNESCAPED_UNICODE);
