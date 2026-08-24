<?php
/**
 * Каталог. Пока — массив в файле.
 * В главе 34 этот же файл будет доставать товары из MySQL,
 * а страницы менять не придётся: наружу отдаются те же функции.
 */

function vse_tovary(): array {
    return [
        ['id'=>1,'nazvanie'=>'Тормозные колодки Bosch','artikul'=>'0986424815','brend'=>'Bosch','zakup'=>18500,'ostatok'=>7, 'kategoriya'=>'Тормоза'],
        ['id'=>2,'nazvanie'=>'Масляный фильтр Mann',   'artikul'=>'W71280',    'brend'=>'Mann', 'zakup'=>3300, 'ostatok'=>23,'kategoriya'=>'Фильтры'],
        ['id'=>3,'nazvanie'=>'Свечи зажигания Denso',  'artikul'=>'IK20',      'brend'=>'Denso','zakup'=>8900, 'ostatok'=>0, 'kategoriya'=>'Зажигание'],
        ['id'=>4,'nazvanie'=>'Тормозные диски Brembo', 'artikul'=>'09.9468.11','brend'=>'Brembo','zakup'=>57800,'ostatok'=>2,'kategoriya'=>'Тормоза'],
        ['id'=>5,'nazvanie'=>'Воздушный фильтр Mann',  'artikul'=>'C25114',    'brend'=>'Mann', 'zakup'=>4800, 'ostatok'=>14,'kategoriya'=>'Фильтры'],
        ['id'=>6,'nazvanie'=>'Аккумулятор Bosch S4',   'artikul'=>'0092S40050','brend'=>'Bosch','zakup'=>70400,'ostatok'=>4, 'kategoriya'=>'Электрика'],
        ['id'=>7,'nazvanie'=>'Салонный фильтр Mann',   'artikul'=>'CU2545',    'brend'=>'Mann', 'zakup'=>4100, 'ostatok'=>9, 'kategoriya'=>'Фильтры'],
        ['id'=>8,'nazvanie'=>'Ремень ГРМ Bosch',       'artikul'=>'1987949095','brend'=>'Bosch','zakup'=>23000,'ostatok'=>3, 'kategoriya'=>'Двигатель'],
        ['id'=>9,'nazvanie'=>'Тормозная жидкость DOT4','artikul'=>'1987479107','brend'=>'Bosch','zakup'=>2900, 'ostatok'=>31,'kategoriya'=>'Тормоза'],
        ['id'=>10,'nazvanie'=>'Свечи NGK Iridium',     'artikul'=>'ILKAR7B11', 'brend'=>'NGK',  'zakup'=>11200,'ostatok'=>6, 'kategoriya'=>'Зажигание'],
    ];
}

/** Один товар по номеру. null, если такого нет. */
function tovar_po_id(int $id): ?array {
    foreach (vse_tovary() as $t) {
        if ($t['id'] === $id) return $t;
    }
    return null;
}

/** Список брендов для фильтра. */
function vse_brendy(): array {
    $b = array_values(array_unique(array_column(vse_tovary(), 'brend')));
    sort($b);
    return $b;
}

/** Список категорий. */
function vse_kategorii(): array {
    $k = array_values(array_unique(array_column(vse_tovary(), 'kategoriya')));
    sort($k);
    return $k;
}
