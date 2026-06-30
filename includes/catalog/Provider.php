<?php
/**
 * Единый контракт провайдера каталога (Этап 1 универсальной архитектуры).
 *
 * Любой источник данных каталога — PartsAPI, UMAPI, Laximo, демо — реализует этот
 * интерфейс. Фронт (`pages/vin.php`) и AJAX-эндпоинты (`api/vin_catalog.php`,
 * `api/vin_crosses.php`) работают ТОЛЬКО через него и не знают, какой сервис под
 * капотом. Смена провайдера = выбор в админке (`catalog_provider`), без правок кода.
 *
 * Методы намеренно повторяют поверхность, которую сайт использует уже сегодня
 * (searchByVin / searchByVinCat / oemNodes / crossesWithWarehouse / testConnection),
 * поэтому переход на адаптеры не меняет фронт. Расширенный интерфейс
 * (getBrands/getModels/getParts) добавится на Этапе 2 вместе с GenericRestAdapter —
 * обратносовместимо.
 *
 * Контракт форматов (одинаков для всех адаптеров):
 *   item = ['name','group','brand','part_number','in_catalog'(bool),
 *           'part_id'(?int),'price'(?float),'stock'(?int),'url'(?string)]
 *   searchByVin()  → ['items'=>item[], 'count'=>int, 'groups_scanned'=>int,
 *                     'errors'=>int, 'rate_limited'=>bool, 'type'=>string, 'from_cache'=>bool]
 *   searchByVinCat() → ['items'=>item[], 'count'=>int, 'cat'=>int,
 *                       'rate_limited'=>bool, 'from_cache'=>bool]
 *   crossesWithWarehouse() → ['items'=>item[]+['is_original'], 'count'=>int,
 *                             'rate_limited'=>bool, 'from_cache'=>bool]
 */
interface CatalogProvider
{
    /** Идентификатор: 'partsapi' | 'umapi' | 'laximo' | 'mock'. */
    public function id(): string;

    /** Человекочитаемое имя для выпадающего списка в админке. */
    public function title(): string;

    /** Готов ли провайдер показывать каталог на сайте (включён + есть доступы). */
    public function enabled(): bool;

    /** Заданы ли доступы (ключ/логин). */
    public function hasKey(): bool;

    /** Полный каталог по VIN (перебор узлов). */
    public function searchByVin(string $vin, bool $useCache = true): array;

    /** Каталог по одному узлу (cat) — один клик дерева = один запрос. */
    public function searchByVinCat(string $vin, int $cat, bool $useCache = true): array;

    /** Узлы дерева каталога: [['cat'=>int,'name'=>string], …]. */
    public function oemNodes(): array;

    /** Аналоги-кроссы по номеру детали + обогащение своим складом. */
    public function crossesWithWarehouse(string $article, string $brand = ''): array;

    /** Тест соединения для кнопки «Проверить» в админке: ['ok','message','count','sample']. */
    public function testConnection(string $vin = ''): array;

    /** Сбросить серверный кэш провайдера (при смене настроек). */
    public function clearCache(): void;
}
