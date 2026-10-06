<?php
/**
 * Характеристики товара по типу категории («Шины», «Масла» и т.п.).
 *
 * Идея: у категории есть «схема характеристик» (categories.spec_schema). Если
 * она задана — продавец при добавлении товара заполняет не свободные пары
 * «ключ-значение», а понятные поля из списка (ширина, профиль, сезon…), а в
 * каталоге по ним строится фильтр. Сами значения лежат в уже существующей
 * таблице part_attributes (kind='spec'), поэтому отдельных колонок не нужно.
 */

require_once __DIR__ . '/grouping.php';   // part_attributes: partsAttributes/partsSaveAttributes

/** Типы техники для классификации товара (parts.vehicle_type). */
function vehicleTypes(): array
{
    return [
        'car'        => 'Легковой',
        'truck'      => 'Грузовой',
        'commercial' => 'Коммерческий',
        'moto'       => 'Мото',
        'special'    => 'Спецтехника',
    ];
}

/** Человеко-понятное имя типа техники по коду, или ''. */
function vehicleTypeLabel(?string $code): string
{
    if ($code === null || $code === '') return '';
    return vehicleTypes()[$code] ?? '';
}

/** Все схемы характеристик. Ключ схемы хранится в categories.spec_schema. */
function partSpecSchemas(): array
{
    static $s = null;
    if ($s !== null) return $s;
    return $s = [
        'tires' => [
            'label'  => 'Шины',
            'fields' => [
                ['name' => 'Ширина',  'unit' => 'мм', 'options' => [145,155,165,175,185,195,205,215,225,235,245,255,265,275,285,295,305,315,325,335]],
                ['name' => 'Профиль', 'unit' => '%',  'options' => [25,30,35,40,45,50,55,60,65,70,75,80,85]],
                ['name' => 'Диаметр', 'unit' => '',   'options' => ['R13','R14','R15','R16','R17','R18','R19','R20','R21','R22','R22.5']],
                ['name' => 'Сезон',   'unit' => '',   'options' => ['Летние','Зимние','Всесезонные']],
                ['name' => 'Шипы',    'unit' => '',   'options' => ['Шипованные','Нешипованные']],
            ],
        ],
        'oils' => [
            'label'  => 'Масла и жидкости',
            'fields' => [
                ['name' => 'Вид',      'unit' => '', 'options' => ['Моторное масло','Трансмиссионное масло','Антифриз','Тормозная жидкость','Жидкость ГУР','Промывочная жидкость','Прочее']],
                ['name' => 'Вязкость', 'unit' => '', 'options' => ['0W-20','0W-30','0W-40','5W-20','5W-30','5W-40','5W-50','10W-30','10W-40','10W-60','15W-40','20W-50','75W-90','80W-90','75W-140']],
                ['name' => 'Тип',      'unit' => '', 'options' => ['Синтетическое','Полусинтетическое','Минеральное']],
                ['name' => 'Объём',    'unit' => 'л', 'options' => [1,4,5,10,20,60,200]],
            ],
        ],
    ];
}

/** Схема по ключу, или null. */
function partSpecSchema(?string $key): ?array
{
    if ($key === null || $key === '') return null;
    $all = partSpecSchemas();
    return $all[$key] ?? null;
}

/**
 * Ключ схемы для категории: берём categories.spec_schema, при пустом —
 * наследуем от родителя (чтобы подкатегории работали как основная).
 */
function categorySpecKey(PDO $db, int $catId): ?string
{
    static $cache = [];
    if ($catId <= 0) return null;
    if (array_key_exists($catId, $cache)) return $cache[$catId];
    try {
        $st = $db->prepare("SELECT spec_schema, parent_id FROM categories WHERE id = ? LIMIT 1");
        $st->execute([$catId]);
        $row = $st->fetch();
    } catch (Throwable $e) { return $cache[$catId] = null; }   // колонки ещё нет
    if (!$row) return $cache[$catId] = null;
    $key = trim((string)($row['spec_schema'] ?? ''));
    if ($key !== '' && partSpecSchema($key)) return $cache[$catId] = $key;
    if (!empty($row['parent_id'])) return $cache[$catId] = categorySpecKey($db, (int)$row['parent_id']);
    return $cache[$catId] = null;
}

/** Текущие характеристики товара как [name => value] (только kind='spec'). */
function partReadSpecs(PDO $db, int $partId): array
{
    if ($partId <= 0) return [];
    $rows = partsAttributes($db, [$partId], 'spec')[$partId] ?? [];
    $out = [];
    foreach ($rows as $r) $out[(string)$r['name']] = (string)$r['value'];
    return $out;
}

/**
 * Собрать строки характеристик из $_POST['spec'] по схеме категории —
 * в формате partsSaveAttributes ([['kind'=>'spec','name'=>...,'value'=>...], …]).
 * Берём только поля, которые есть в схеме (чужое из формы игнорируем).
 */
function partSpecAttrsFromPost(?string $specKey, array $postSpec): array
{
    $schema = partSpecSchema($specKey);
    if (!$schema) return [];
    $out = [];
    foreach ($schema['fields'] as $f) {
        $v = trim((string)($postSpec[$f['name']] ?? ''));
        if ($v !== '') $out[] = ['kind' => 'spec', 'name' => $f['name'], 'value' => $v];
    }
    return $out;
}

/**
 * Данные для фильтра-сайдбара каталога: по каждому полю схемы — какие значения
 * реально встречаются у активных товаров этих категорий и сколько их.
 * Возвращает [fieldName => [ [value, count], … ], …].
 */
function categorySpecFacets(PDO $db, array $catIds, string $specKey): array
{
    $schema = partSpecSchema($specKey);
    if (!$schema || !$catIds) return [];
    $inPlaces = implode(',', array_fill(0, count($catIds), '?'));
    $facets = [];
    foreach ($schema['fields'] as $f) {
        try {
            $st = $db->prepare(
                "SELECT pa.value AS v, COUNT(DISTINCT p.id) AS n
                   FROM part_attributes pa
                   JOIN parts p ON p.id = pa.part_id
                  WHERE p.category_id IN ($inPlaces)
                    AND p.is_active = 1
                    AND (p.seller_id IS NULL OR p.moderation_status = 'active')
                    AND pa.kind = 'spec' AND pa.name = ?
               GROUP BY pa.value
               ORDER BY (pa.value + 0), pa.value"
            );
            $st->execute(array_merge($catIds, [$f['name']]));
            $vals = $st->fetchAll();
        } catch (Throwable $e) { $vals = []; }
        if ($vals) $facets[$f['name']] = array_map(fn($r) => [(string)$r['v'], (int)$r['n']], $vals);
    }
    return $facets;
}
