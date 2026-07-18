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
 *
 * Случаи, когда AutoEuro не вернул цену (ни ошибка API, ни совпадение по
 * артикулу), логируются в `warehouse_api_log` (action=autoeuro_price_miss) —
 * видно в Суперадмин → Склад → Лог запросов. Ничего не меняет для покупателя
 * (как показывалось «под заказ», так и показывается) — это только диагностика,
 * чтобы понять, из-за чего конкретно не находится цена (детали действительно
 * нет у поставщика, или, например, расхождение в написании бренда).
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
        if (!is_array($res) || isset($res['error'])) {
            self::logMiss($brand, $oem, $res);
            return null;
        }
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
        if ($best === null) {
            self::logMiss($brand, $oem, $res);
            return null;
        }

        $markup = (float)getSetting('global_markup', '0');
        $best['price']   = round($best['price'] * (1 + $markup / 100), 2);
        $best['source']  = 'autoeuro';
        $best['part_id'] = null;
        $best['url']     = null;
        return $best;
    }

    /** Пишет случай «цена не найдена» в лог (см. докблок класса). Сбой логирования не влияет на показ цены. */
    private static function logMiss(string $brand, string $oem, $rawResponse): void
    {
        try {
            $db = getDB();
            $db->exec("CREATE TABLE IF NOT EXISTS warehouse_api_log (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                action VARCHAR(80) NOT NULL DEFAULT '',
                request_url VARCHAR(500) NOT NULL DEFAULT '',
                response_code SMALLINT NOT NULL DEFAULT 0,
                response_body TEXT DEFAULT NULL,
                success TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id), KEY idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $url = 'brand=' . $brand . '&code=' . $oem;
            $db->prepare("INSERT INTO warehouse_api_log (action, request_url, response_code, response_body, success, created_at) VALUES (?,?,0,?,0,NOW())")
               ->execute(['autoeuro_price_miss', mb_substr($url, 0, 500), mb_substr(json_encode($rawResponse, JSON_UNESCAPED_UNICODE), 0, 2000)]);
        } catch (Exception $e) { /* лог необязателен */ }
    }

    private static function norm(string $s): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $s));
    }
}
