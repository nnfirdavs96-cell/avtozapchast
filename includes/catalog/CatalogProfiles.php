<?php
/**
 * Реестр профилей провайдеров каталога (Этап 2 — ядро универсальности).
 *
 * Профиль — это ОПИСАНИЕ REST-сервиса ДАННЫМИ (не кодом): базовый URL, способ
 * авторизации, шаблоны эндпоинтов и маппинг полей ответа. Один движок
 * `GenericRestAdapter` исполняет любой профиль → подключение нового REST-сервиса
 * не требует программиста: владелец выбирает готовый профиль из списка или
 * добавляет свой JSON в админке (Суперадмин → VIN-поиск → «Профили провайдеров»).
 *
 * Источники профилей:
 *   • встроенные пресеты (этот файл) — стартовые шаблоны для известных сервисов;
 *   • пользовательские — JSON в настройке `catalog_profiles` (правится в админке),
 *     перекрывают одноимённые встроенные.
 *
 * ── Схема профиля ───────────────────────────────────────────────────────────
 * [
 *   'id'        => 'umapi',                 // латиницей, уникальный
 *   'title'     => 'UMAPI',                 // имя для выпадающего списка
 *   'base_url'  => 'https://api.umapi.ru/',
 *   'timeout'   => 12,
 *   'auth'      => 'query',                 // query | bearer | header
 *   'key_param' => 'key',                   // имя GET-параметра ключа (auth=query)
 *   'key_header'=> 'X-Api-Key',             // имя заголовка ключа (auth=header)
 *   'endpoints' => [
 *       'parts'   => '?method=getParts&vin={VIN}&cat={CAT}&key={KEY}&format=json',
 *       'crosses' => '?method=getCrosses&art={ART}&brand={BRAND}&key={KEY}&format=json',
 *   ],
 *   'parse' => [
 *       'list_path' => 'data.array',        // путь до массива в ответе ('' = корень)
 *       'mode'      => 'pairs',             // pairs | objects
 *       // mode=pairs: каждый элемент содержит строку "БРЕНД|АРТ,БРЕНД|АРТ"
 *       'parts_field' => 'parts', 'parts_sep' => ',', 'pair_sep' => '|',
 *       'name_field'  => 'shortname', 'group_field' => 'group',
 *       // mode=objects: каждый элемент = одна деталь
 *       'brand_field' => 'manufacturer', 'article_field' => 'article',
 *       // (name_field/group_field используются в обоих режимах)
 *   ],
 *   'nodes' => ['1=Двигатель','2=Тормоза'], // дерево узлов «cat=Название»
 * ]
 * Плейсхолдеры в endpoints (регистронезависимо): {VIN} {KEY} {CAT} {ART} {BRAND} {TYPE}.
 */
class CatalogProfiles
{
    /** Встроенные пресеты-шаблоны. Точные эндпоинты/маппинг донастраиваются на боевом ключе. */
    private static function builtin(): array
    {
        return [
            // Пример REST-профиля. Метод/поля — типовые; проверить по докам UMAPI
            // на боевом ключе и при необходимости поправить здесь или в админке.
            'umapi' => [
                'id' => 'umapi', 'title' => 'UMAPI', 'base_url' => 'https://api.umapi.ru/',
                'timeout' => 12, 'auth' => 'query', 'key_param' => 'key',
                'endpoints' => [
                    'parts'   => '?method=getParts&vin={VIN}&cat={CAT}&key={KEY}&format=json',
                    'crosses' => '?method=getCrosses&art={ART}&brand={BRAND}&key={KEY}&format=json',
                ],
                'parse' => [
                    'list_path' => '', 'mode' => 'objects',
                    'brand_field' => 'brand', 'article_field' => 'article',
                    'name_field' => 'name', 'group_field' => 'group',
                ],
                'nodes' => ['1=Двигатель','2=Тормозная система','3=Подвеска','4=Электрика'],
                '_template' => true,
            ],
        ];
    }

    /** Пользовательские профили из настройки (JSON id=>profile). */
    private static function custom(): array
    {
        $raw = trim(getSetting('catalog_profiles', ''));
        if ($raw === '') return [];
        $data = json_decode($raw, true);
        if (!is_array($data)) return [];
        $out = [];
        foreach ($data as $id => $p) {
            if (is_array($p) && isset($p['base_url'])) {
                $p['id']    = (string)($p['id'] ?? $id);
                $p['title'] = (string)($p['title'] ?? $p['id']);
                $out[$p['id']] = $p;
            }
        }
        return $out;
    }

    /** Все профили (пользовательские перекрывают встроенные). */
    public static function all(): array
    {
        return array_merge(self::builtin(), self::custom());
    }

    /** Один профиль по id или null. */
    public static function get(string $id): ?array
    {
        $all = self::all();
        return $all[$id] ?? null;
    }

    /** id => title для выпадающего списка. */
    public static function options(): array
    {
        $out = [];
        foreach (self::all() as $id => $p) {
            $out[$id] = (string)($p['title'] ?? $id) . (!empty($p['_template']) ? ' (шаблон)' : '');
        }
        return $out;
    }

    /** Проверка JSON пользовательских профилей перед сохранением: [ok(bool), message]. */
    public static function validateJson(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') return [true, ''];
        $data = json_decode($raw, true);
        if (!is_array($data)) return [false, 'Невалидный JSON.'];
        foreach ($data as $id => $p) {
            if (!is_array($p) || empty($p['base_url']) || empty($p['endpoints']['parts'])) {
                return [false, "Профиль «$id»: нужны base_url и endpoints.parts."];
            }
        }
        return [true, ''];
    }
}
