<?php
/**
 * Диагностика канонических артикулов (part_key).
 *
 * Зачем: поиск находит артикул без учёта дефисов/пробелов через колонку
 * `part_key` (артикул без разделителей, верхний регистр). Если у товара эта
 * колонка пустая или устарела (товар завели/правили в обход сохранения через
 * форму), он перестанет находиться по «слитному» запросу. Этот скрипт
 * показывает, сколько таких товаров, и — по флагу — чинит ТОЛЬКО part_key,
 * не трогая группировку (product_id) и, значит, ссылки на карточки.
 *
 * Запуск на сервере (из корня сайта):
 *   php sql/check_part_keys.php          # только показать (ничего не меняет)
 *   php sql/check_part_keys.php --fix    # добить несовпадающие part_key
 *
 * В отличие от `migrate_buybox.php --regroup`, здесь НЕТ побочного эффекта
 * смены номера карточки: product_id не пересчитывается.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Только из командной строки.\n"); }

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/parts/grouping.php';

$db  = getDB();
$fix = in_array('--fix', $argv, true);

echo "Проверка part_key (канонический артикул)\n";
echo "База: " . DB_NAME . "\n";
echo str_repeat('-', 60) . "\n";

$rows = $db->query("SELECT id, part_number, part_key FROM parts")->fetchAll();
$total = count($rows);

$withSeparator = 0;   // артикулов с разделителем (-, пробел, точка и т.п.)
$mismatch      = [];  // товары, где part_key не совпадает с нормализованным артикулом

foreach ($rows as $r) {
    $pn   = (string)$r['part_number'];
    $key  = (string)($r['part_key'] ?? '');
    $canon = partsCanonicalArticle($pn);

    // Есть ли в артикуле что-то кроме букв/цифр — такие и ломались в поиске.
    if ($canon !== $pn && $canon !== '') $withSeparator++;

    if ($canon !== $key) {
        $mismatch[] = ['id' => (int)$r['id'], 'pn' => $pn, 'key' => $key, 'canon' => $canon];
    }
}

echo "Всего товаров: $total\n";
echo "Артикулов с разделителями (дефис/пробел/точка): $withSeparator\n";
echo "part_key не совпадает с артикулом: " . count($mismatch) . "\n";
echo str_repeat('-', 60) . "\n";

if (!$mismatch) {
    echo "✅ Все part_key в порядке — поиск по «слитному» артикулу работает.\n";
    exit(0);
}

// Показываем до 30 примеров, чтобы было видно характер проблемы.
echo "Примеры расхождений (до 30):\n";
foreach (array_slice($mismatch, 0, 30) as $m) {
    printf("  #%-6d  артикул «%s»  part_key «%s»  → должно быть «%s»\n",
        $m['id'], $m['pn'], $m['key'], $m['canon']);
}
if (count($mismatch) > 30) echo "  … и ещё " . (count($mismatch) - 30) . "\n";
echo str_repeat('-', 60) . "\n";

if (!$fix) {
    echo "Это только проверка. Чтобы починить part_key, запустите:\n";
    echo "  php sql/check_part_keys.php --fix\n";
    exit(0);
}

// ── Починка: проставляем ТОЛЬКО part_key, группировку не трогаем ──────────────
$upd = $db->prepare("UPDATE parts SET part_key = ? WHERE id = ?");
$fixed = 0;
foreach ($mismatch as $m) {
    $upd->execute([$m['canon'], $m['id']]);
    $fixed++;
}
echo "✅ Исправлено part_key: $fixed\n";
echo "Группировка (product_id) и ссылки на карточки не менялись.\n";
