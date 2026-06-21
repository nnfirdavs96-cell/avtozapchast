<?php
/**
 * Финальная настройка + проверка каталога PartsAPI.
 * Запуск: php setup_catalog.php   →  проверь вывод  →  rm setup_catalog.php
 *
 * Прописывает корректные настройки каталога (метод getPartsbyVIN) и делает
 * реальный тест: декодирует VIN + перебирает несколько групп.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/vin_service.php';
require_once __DIR__ . '/includes/catalog_api.php';

$br = (PHP_SAPI === 'cli') ? "\n" : "<br>\n";

// ── Настройки каталога (новая модель: ключ + тип + число групп) ──────────
$cfg = [
    'catalog_api_enabled'    => '1',
    'catalog_api_base'       => 'https://api.partsapi.ru/',
    'catalog_api_type'       => 'oem',   // оригинал
    'catalog_api_max_groups' => '25',    // демо: 15–25; платный: больше/0
    'catalog_api_timeout'    => '12',
];
foreach ($cfg as $k => $v) { setSetting($k, $v); echo "✓ $k = $v$br"; }
// ключ getPartsbyVIN уже записан ранее; не трогаем, но проверим наличие
echo (CatalogApi::hasKey() ? "✓ ключ каталога задан" : "✗ ключ каталога НЕ задан — впишите в админке") . $br;

echo $br . "──────────────────────────────" . $br;

// ── Тест VIN-декодера ────────────────────────────────────────────────────
$vin = 'Z8TXLCW6WCM902224'; // Mitsubishi Outlander (из доки PartsAPI)
echo "Тест VIN ($vin):" . $br;
$d = VinService::decode($vin);
echo (!empty($d['make'])
    ? "✓ " . $d['make'] . ' ' . ($d['model'] ?? '') . ' ' . ($d['year'] ?? '') . " (источник: " . ($d['source'] ?? '?') . ")"
    : "⚠ марка не определена") . $br;

// ── Тест каталога (перебор групп) ────────────────────────────────────────
echo $br . "Тест каталога (перебор групп, может занять ~10 сек)…" . $br;
CatalogApi::clearCache();
$res = CatalogApi::searchByVin($vin);
echo "Опрошено групп: " . $res['groups_scanned'] . ", найдено позиций: " . $res['count'] . $br;
foreach (array_slice($res['items'], 0, 8) as $it) {
    echo "  • [" . $it['group'] . "] " . $it['name'] . " — " . $it['brand'] . ' ' . $it['part_number']
       . ($it['in_catalog'] ? " (есть на складе: " . $it['price'] . ")" : " (под заказ)") . $br;
}
if ($res['count'] === 0) {
    echo $br . "⚠ Запчасти не вернулись. Возможные причины:" . $br;
    echo "  - демо-ключ исчерпал суточный лимит (≈50 запросов);" . $br;
    echo "  - для этого VIN нет позиций в выбранных группах;" . $br;
    echo "  - попробуйте другой VIN или увеличьте число групп." . $br;
}

echo $br . "⚠  УДАЛИ ФАЙЛ: rm ~/public_html/setup_catalog.php" . $br;
