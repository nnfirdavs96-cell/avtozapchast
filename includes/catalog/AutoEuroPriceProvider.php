<?php
/**
 * Цена от поставщика AutoEuro по OEM-номеру — фолбэк, когда детали нет на своём
 * складе. Берём самое дешёвое предложение с точным совпадением артикула,
 * применяем общую наценку (global_markup). Цена AutoEuro — в RUB (как и база
 * проекта), поэтому конвертация валют не нужна: formatPrice() во фронте сам
 * переведёт в сомони.
 *
 * Требует настроенного AutoEuro (autoeuro_enabled + ключ + delivery_key) и
 * непустого бренда (AutoEuro ищет по паре бренд+код).
 */
require_once __DIR__ . '/PriceProvider.php';
require_once __DIR__ . '/../autoeuro.php';

class AutoEuroPriceProvider implements PriceProvider
{
    public function id(): string { return 'autoeuro'; }

    public function priceByOem(string $oem, string $brand = ''): ?array
    {
        $oem   = trim($oem);
        $brand = trim($brand);
        if ($oem === '' || $brand === '') return null;

        $ae = AutoEuro::fromSettings();
        if (!$ae) return null;
        $deliveryKey = trim(getSetting('autoeuro_delivery_key', ''));
        if ($deliveryKey === '') return null;

        $res = $ae->searchItems($brand, $oem, $deliveryKey, false, false);
        if (!is_array($res) || isset($res['error'])) return null;
        $offers = isset($res[0]) ? $res : (array)$res;

        $want = self::norm($oem);
        $best = null;
        foreach ($offers as $o) {
            if (!is_array($o)) continue;
            $price = (float)($o['price'] ?? 0);
            if ($price <= 0) continue;
            // Только точное совпадение кода: searchItems может вернуть и кроссы.
            if (self::norm((string)($o['code'] ?? '')) !== $want) continue;
            if ($best === null || $price < $best['price']) {
                $best = [
                    'price'    => $price,
                    'stock'    => (int)($o['stock'] ?? $o['amount'] ?? 0),
                    'delivery' => $o['delivery_time'] ?? null,
                    'name'     => $o['name'] ?? null,
                ];
            }
        }
        if ($best === null) return null;

        $markup = (float)getSetting('global_markup', '0');
        $best['price']   = round($best['price'] * (1 + $markup / 100), 2);
        $best['source']  = 'autoeuro';
        $best['part_id'] = null;
        $best['url']     = null;
        return $best;
    }

    private static function norm(string $s): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $s));
    }
}
