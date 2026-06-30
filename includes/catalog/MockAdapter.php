<?php
/**
 * Демо-провайдер каталога — работает БЕЗ ключа. Нужен, чтобы:
 *   • владелец сразу увидел, как выглядит каталог по VIN в нашем дизайне (узлы,
 *     детали, кроссы, «в корзину») ещё до покупки подписки;
 *   • фронт и эндпоинты всегда были рабочими (тесты, демонстрация заказчику);
 *   • при совпадении демо-артикула со своим складом подсвечивались реальная цена
 *     и кнопка «в корзину» (через ту же warehouse-обогащалку, что и у боевых).
 *
 * Данные — небольшой статичный набор по узлам. Формат ответов 1:1 совпадает с
 * боевыми адаптерами (см. контракт в Provider.php), поэтому фронт не отличает.
 */
require_once __DIR__ . '/Provider.php';
require_once __DIR__ . '/../catalog_api.php';

class MockAdapter implements CatalogProvider
{
    /** Узлы дерева и их детали: cat => [название узла, [[имя, бренд, артикул], …]]. */
    private const DEMO = [
        1 => ['Двигатель', [
            ['Масляный фильтр',        'MANN',   'W712/52'],
            ['Воздушный фильтр',       'BOSCH',  'F026400119'],
            ['Свеча зажигания',        'NGK',    'BKR6E-11'],
            ['Ремень ГРМ',             'GATES',  '5631XS'],
        ]],
        2 => ['Тормозная система', [
            ['Колодки тормозные пер.', 'BREMBO', 'P50090'],
            ['Диск тормозной пер.',    'TRW',    'DF4456'],
            ['Колодки тормозные задн.','FERODO', 'FDB1672'],
        ]],
        3 => ['Подвеска', [
            ['Амортизатор передний',   'KYB',    '339704'],
            ['Опора стойки',           'SACHS',  '802422'],
            ['Стойка стабилизатора',   'LEMFORDER', '3308301'],
        ]],
        4 => ['Электрика', [
            ['Аккумулятор 60Ah',       'VARTA',  '560408054'],
            ['Лампа H7 12V',           'OSRAM',  '64210'],
        ]],
    ];

    public function id(): string    { return 'mock'; }
    public function title(): string { return 'Демо (без ключа)'; }

    /** Демо подчиняется общему тумблеру каталога, но ключ не требует. */
    public function enabled(): bool { return getSetting('catalog_api_enabled', '0') === '1'; }
    public function hasKey(): bool  { return true; }

    public function oemNodes(): array
    {
        $nodes = [];
        foreach (self::DEMO as $cat => [$name, ]) {
            $nodes[] = ['cat' => $cat, 'name' => $name];
        }
        return $nodes;
    }

    public function searchByVinCat(string $vin, int $cat, bool $useCache = true): array
    {
        $items = isset(self::DEMO[$cat]) ? $this->itemsForNode($cat) : [];
        $items = CatalogApi::enrichItemsFromWarehouse($items);
        return ['items' => $items, 'count' => count($items), 'cat' => $cat,
                'rate_limited' => false, 'type' => 'demo', 'from_cache' => false];
    }

    public function searchByVin(string $vin, bool $useCache = true): array
    {
        $items = [];
        foreach (array_keys(self::DEMO) as $cat) {
            $items = array_merge($items, $this->itemsForNode($cat));
        }
        $items = CatalogApi::enrichItemsFromWarehouse($items);
        return ['items' => $items, 'count' => count($items), 'groups_scanned' => count(self::DEMO),
                'errors' => 0, 'rate_limited' => false, 'type' => 'demo', 'from_cache' => false];
    }

    public function crossesWithWarehouse(string $article, string $brand = ''): array
    {
        // Демо-кроссы: исходный номер + пара «аналогов» того же узла.
        $cands = [['brand' => $brand, 'part_number' => $article, 'is_original' => true]];
        $pool  = [['ALPHA', $article . 'A'], ['BETA', $article . 'B']];
        foreach ($pool as [$b, $a]) {
            $cands[] = ['brand' => $b, 'part_number' => $a, 'is_original' => false];
        }
        $items = [];
        foreach ($cands as $c) {
            $items[] = [
                'name' => $c['part_number'], 'group' => '',
                'brand' => $c['brand'], 'part_number' => $c['part_number'],
                'is_original' => $c['is_original'],
                'in_catalog' => false, 'part_id' => null,
                'price' => null, 'stock' => null, 'url' => null,
            ];
        }
        $items = CatalogApi::enrichItemsFromWarehouse($items);
        return ['items' => $items, 'count' => count($items), 'rate_limited' => false, 'from_cache' => false];
    }

    public function testConnection(string $vin = ''): array
    {
        return ['ok' => true, 'count' => 4,
                'message' => 'Демо-режим: показаны примерные данные без обращения к API. '
                           . 'Для реального каталога выберите провайдера и введите ключ.',
                'sample' => ''];
    }

    public function clearCache(): void { /* демо без кэша */ }

    /** Построить позиции одного узла в общем формате item. */
    private function itemsForNode(int $cat): array
    {
        [$group, $parts] = self::DEMO[$cat];
        $out = [];
        foreach ($parts as [$name, $brand, $art]) {
            $out[] = [
                'name' => $name, 'group' => $group,
                'brand' => $brand, 'part_number' => $art,
                'in_catalog' => false, 'part_id' => null,
                'price' => null, 'stock' => null, 'url' => null,
            ];
        }
        return $out;
    }
}
