<?php
/**
 * Разделы с характеристиками: «Шины» и «Масла и жидкости».
 *
 * Добавляет категориям колонку spec_schema (ключ набора характеристик) и
 * создаёт две категории с этими схемами. Дальше продавцы сами выкладывают
 * товары в эти разделы, указывая характеристики (размер/сезон, вязкость/объём),
 * а в каталоге по ним работает фильтр. Схемы описаны в includes/parts/specs.php.
 *
 * Запуск (повторный безопасен):
 *   php sql/migrate_catalog_specs.php
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Только из командной строки.\n"); }

require_once dirname(__DIR__) . '/config/config.php';

$db = getDB();

echo "Разделы с характеристиками (шины, масла)\n";
echo "База: " . DB_NAME . "\n";
echo str_repeat('-', 60) . "\n";

// 1. Колонка-ключ схемы характеристик.
dbAddColumnIfMissing($db, 'categories', 'spec_schema',
    "`spec_schema` VARCHAR(20) DEFAULT NULL COMMENT 'ключ набора характеристик (tires, oils)' AFTER `slug`");
echo "  [OK] categories.spec_schema\n";

// 2. Создаём/обновляем категории. Если категория с таким slug уже есть —
//    только проставляем ей spec_schema, не плодим дубли.
function ensureSpecCategory(PDO $db, string $name, string $slug, string $schema, int $sort): void
{
    $st = $db->prepare("SELECT id FROM categories WHERE slug = ? LIMIT 1");
    $st->execute([$slug]);
    $id = $st->fetchColumn();
    if ($id) {
        $db->prepare("UPDATE categories SET spec_schema = ? WHERE id = ?")->execute([$schema, (int)$id]);
        echo "  [OK] категория «$name» уже была — схема обновлена\n";
    } else {
        $db->prepare(
            "INSERT INTO categories (name, slug, spec_schema, parent_id, sort_order, is_active)
             VALUES (?, ?, ?, NULL, ?, 1)"
        )->execute([$name, $slug, $schema, $sort]);
        echo "  [OK] создана категория «$name» (/catalog/category.php?slug=$slug)\n";
    }
}

ensureSpecCategory($db, 'Шины',              'shiny',           'tires', 50);
ensureSpecCategory($db, 'Масла и жидкости',  'masla-zhidkosti', 'oils',  51);

echo str_repeat('-', 60) . "\n";
echo "Готово. Продавцы теперь могут выкладывать шины и масла с характеристиками,\n";
echo "а покупатели — фильтровать их в каталоге.\n";
