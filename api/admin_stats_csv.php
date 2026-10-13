<?php
/**
 * Выгрузка статистики магазинов (все продавцы) в CSV — только для админки.
 * Отдаёт сводку по выбранному периоду + ряд посещений (по дням или по часам
 * для «сегодня»). Разделитель «;» и UTF-8 BOM — чтобы корректно открывалось
 * в Excel с кириллицей.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/seller_stats.php';

if (!isLoggedIn() || !in_array($_SESSION['role'] ?? '', ['admin', 'superadmin'], true)) {
    http_response_code(403); exit('Доступ запрещён');
}

$db     = getDB();
$period = ssNormPeriod(is_scalar($_GET['period'] ?? null) ? (string)$_GET['period'] : '30d');
$labels = ['today' => 'Сегодня', '7d' => '7 дней', '30d' => '30 дней', 'all' => 'Всё время'];

$s     = sellerStatsAll($db, $period);
$chart = visitsChartData($db, null, $period);

$fname = 'statistika_prodavcy_' . $period . '_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fname . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");            // BOM для Excel
$w = static function (array $row) use ($out) { fputcsv($out, $row, ';'); };

// Сводка.
$w(['Период', $labels[$period] ?? $period]);
$w(['Сформировано', date('d.m.Y H:i')]);
$w(['Магазинов (одобрено)', (int)$s['sellers']]);
$w([]);
$w(['Показатель', 'Значение']);
$w(['Посещения магазинов', (int)$s['visits']]);
$w(['Заказы', (int)$s['orders']]);
$w(['Оплаты (кол-во)', (int)$s['pay_count']]);
$w(['Оплаты (сумма, смн)', number_format((float)$s['pay_sum'], 2, '.', '')]);
$w(['Сообщения покупателей', (int)$s['messages']]);
$w([]);

// Ряд посещений (детализация под период).
$w([$period === 'today' ? 'Час' : 'Дата', 'Посещения']);
foreach ($chart['points'] as $p) {
    $w([$p['key'] ?? $p['label'], (int)$p['c']]);
}

fclose($out);
