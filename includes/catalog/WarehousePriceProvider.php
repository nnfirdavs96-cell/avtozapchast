<?php
/**
 * Цена со своего склада (таблица parts) — приоритетный источник. Если деталь с
 * таким артикулом есть в наличии у нас, показываем свою цену и «в корзину».
 */
require_once __DIR__ . '/PriceProvider.php';

class WarehousePriceProvider implements PriceProvider
{
    public function id(): string { return 'warehouse'; }

    public function priceByOem(string $oem, string $brand = ''): ?array
    {
        $oem = trim($oem);
        if ($oem === '') return null;
        try {
            $db = getDB();
            // Совпадение по нормализованному артикулу (без регистра и разделителей).
            $norm = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $oem));
            $st = $db->prepare(
                "SELECT id, price, stock
                   FROM parts
                  WHERE is_active = 1
                    AND UPPER(REPLACE(REPLACE(REPLACE(part_number,'-',''),' ',''),'.','')) = ?
                  ORDER BY stock DESC, price ASC
                  LIMIT 1"
            );
            $st->execute([$norm]);
            $r = $st->fetch();
            if (!$r) return null;
            return [
                'price'    => (float)$r['price'],
                'stock'    => (int)$r['stock'],
                'source'   => 'warehouse',
                'delivery' => null,
                'name'     => null,
                'part_id'  => (int)$r['id'],
                'url'      => APP_URL . '/catalog/part.php?id=' . (int)$r['id'],
            ];
        } catch (Exception $e) {
            return null;
        }
    }
}
