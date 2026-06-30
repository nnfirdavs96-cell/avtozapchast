<?php
/**
 * Контракт слоя цен (Этап 3). Каталог даёт OEM-номер — слой цен возвращает цену.
 * Каталог и цены независимы: один поставщик каталога может работать с любым
 * источником цен. Все цены — в базовой валюте проекта (RUB); во фронте
 * formatPrice() переводит их в активную валюту (сомони).
 *
 * Возврат priceByOem():
 *   ['price'=>float(RUB), 'stock'=>int, 'source'=>string,
 *    'delivery'=>?string, 'name'=>?string, 'part_id'=>?int, 'url'=>?string]
 *   либо null — если источник не нашёл деталь или не настроен.
 */
interface PriceProvider
{
    public function id(): string;
    public function priceByOem(string $oem, string $brand = ''): ?array;
}
