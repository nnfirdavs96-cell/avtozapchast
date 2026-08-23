<?php
/**
 * Группировка товаров: карточка, семейство, варианты (маркетплейс, этап buy-box).
 *
 * ЗАЧЕМ. До сих пор `parts.part_number` был уникален по всей базе, поэтому если
 * один продавец выложил артикул, второй выложить его уже не мог. Конкуренции
 * продавцов не существовало — то есть маркетплейса, по сути, тоже.
 *
 * МОДЕЛЬ. Строка `parts` = ПРЕДЛОЖЕНИЕ конкретного продавца (своя цена, наличие,
 * фото). Над ней два уровня группировки:
 *
 *   семейство            «Автокресло Комфорт»          product_group_id
 *     └── карточка       «Комфорт, 1–3 года, чёрное»   product_id      ← buy-box тут
 *           └── предложения продавцов                  parts.id
 *
 * Карточка — это «один и тот же товар у разных продавцов»: у запчастей ключом
 * служит артикул производителя + бренд, и это объективный признак (BOSCH
 * 0986452041 — физически одна деталь у кого угодно). Семейство — «тот же товар в
 * другом исполнении»: цвет, объём, возрастная группа.
 *
 * ПОЧЕМУ КЛЮЧ ЧЕРЕЗ COALESCE. `product_id` заполняется, только когда товар реально
 * с кем-то объединён; у одиночного товара он NULL. Ключ группы — это
 * COALESCE(product_id, id), поэтому одиночный товар автоматически «группа из
 * самого себя», и нигде не нужен особый случай «а если NULL». То же для семейства.
 *
 * ПОЧЕМУ НЕТ ВНЕШНЕГО КЛЮЧА на product_id. Это НЕ ссылка на строку, а НОМЕР
 * ГРУППЫ, которым оказался id первого выложившего. Если тот продавец удалит свой
 * товар, остальные предложения обязаны остаться сгруппированными — с внешним
 * ключом их бы раскидало (SET NULL) или удалило (CASCADE). Номер группы живёт
 * дольше строки, которая его породила.
 *
 * УНИКАЛЬНЫЕ ТОВАРЫ. Б/у деталь, разборка, товар без артикула сводить нельзя:
 * двигатель с разборки от двух продавцов — это два разных товара, а не два
 * предложения на один. Такие помечаются `is_unique_item = 1` и никогда не
 * получают `product_id`.
 */

/**
 * Канонический вид артикула для сравнения: только буквы и цифры, верхний регистр.
 *
 * Правило намеренно совпадает с `AutoEuroPriceProvider::norm()` и
 * `GenericRestAdapter` — иначе наши товары и предложения поставщика склеивались бы
 * по разным правилам, и «90915-YZZE1» с «90915 YZZE1» оказались бы разными
 * товарами в одной выдаче.
 */
function partsCanonicalArticle(string $article): string
{
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $article));
}

/**
 * SQL-выражение ключа КАРТОЧКИ (кто ещё продаёт этот же товар).
 * $alias — псевдоним таблицы parts в запросе.
 */
function partsCardKeyExpr(string $alias = 'p'): string
{
    return "COALESCE($alias.product_id, $alias.id)";
}

/**
 * SQL-выражение ключа СЕМЕЙСТВА (тот же товар в другом исполнении).
 * Падает обратно на карточку, а та — на сам товар: товар без семейства это
 * семейство из одного элемента.
 */
function partsFamilyKeyExpr(string $alias = 'p'): string
{
    return "COALESCE($alias.product_group_id, $alias.product_id, $alias.id)";
}

/**
 * Найти номер карточки для «артикул + бренд», если такой товар уже кто-то выложил.
 *
 * Возвращает номер группы или null, если товар первый в своём роде — тогда он сам
 * становится карточкой и `product_id` остаётся NULL (см. COALESCE выше).
 *
 * $excludeId — редактируемый товар не должен находить сам себя.
 * Товары, помеченные уникальными, в группу не берутся и группу не образуют.
 */
function partsResolveProductId(PDO $db, string $article, ?int $brandId, ?int $excludeId = null): ?int
{
    $key = partsCanonicalArticle($article);
    if ($key === '') return null;   // без артикула объединять не по чему

    // MIN(COALESCE(...)) — потому что у первого выложившего product_id как раз NULL,
    // и его собственный id и есть номер группы.
    $sql = "SELECT MIN(COALESCE(product_id, id))
              FROM parts
             WHERE part_key = ? AND brand_id <=> ? AND is_unique_item = 0";
    $args = [$key, $brandId];
    if ($excludeId) { $sql .= " AND id <> ?"; $args[] = $excludeId; }

    $st = $db->prepare($sql);
    $st->execute($args);
    $found = $st->fetchColumn();
    return $found !== false && $found !== null ? (int)$found : null;
}

/**
 * Пересчитать `part_key` и `product_id` по всей базе.
 *
 * Нужна при первом накате (товары заведены до появления группировки) и как
 * ремонтная кнопка, если данные правили напрямую в БД. Идемпотентна.
 *
 * ⚠️ Побочный эффект: если ведущий товар группы удалён, пересчёт назначит ведущим
 * следующий по id — то есть НОМЕР ГРУППЫ ПОМЕНЯЕТСЯ. Раз номер карточки войдёт в
 * адрес товара, ссылка на такую карточку сменится. Без пересчёта этого не
 * происходит: оставшиеся предложения продолжают держаться за прежний номер, даже
 * если породившей его строки уже нет. Поэтому пересчёт — ручная операция, а не
 * что-то, что дёргается по расписанию.
 *
 * Возвращает [сколько ключей проставлено, сколько товаров объединено в карточки].
 */
function partsRebuildGrouping(PDO $db): array
{
    // 1) Нормализованный ключ. Считаем в PHP: убрать все не-буквенно-цифровые
    //    символы средствами MySQL можно только цепочкой REPLACE по каждому знаку.
    $keyed = 0;
    $rows = $db->query("SELECT id, part_number, part_key FROM parts")->fetchAll();
    $upd  = $db->prepare("UPDATE parts SET part_key = ? WHERE id = ?");
    foreach ($rows as $r) {
        $key = partsCanonicalArticle((string)$r['part_number']);
        if ($key !== (string)($r['part_key'] ?? '')) {
            $upd->execute([$key, (int)$r['id']]);
            $keyed++;
        }
    }

    // 2) Сбрасываем прежнюю группировку, чтобы пересчёт был именно пересчётом,
    //    а не наслоением на старое (иначе распавшиеся группы никогда не разойдутся).
    $db->exec("UPDATE parts SET product_id = NULL");

    // 3) Объединяем в карточки только те ключи, где товар действительно не один.
    //    Одиночкам product_id не нужен — COALESCE(product_id, id) и так даст id.
    $groups = $db->query(
        "SELECT part_key, brand_id, MIN(id) AS leader, COUNT(*) AS cnt
           FROM parts
          WHERE is_unique_item = 0 AND part_key <> ''
       GROUP BY part_key, brand_id
         HAVING cnt > 1"
    )->fetchAll();

    $grouped = 0;
    // Ведущему товару product_id не ставим намеренно: его id и есть номер группы,
    // а лишняя запись создала бы второй источник правды.
    $set = $db->prepare(
        "UPDATE parts SET product_id = ?
          WHERE part_key = ? AND brand_id <=> ? AND is_unique_item = 0 AND id <> ?"
    );
    foreach ($groups as $g) {
        $set->execute([(int)$g['leader'], $g['part_key'], $g['brand_id'], (int)$g['leader']]);
        $grouped += $set->rowCount();
    }

    return [$keyed, $grouped];
}

/**
 * Атрибуты товара: ось варианта или характеристика.
 *
 * ОСЬ (`axis`) — по ней товар отличается от собратьев по семейству и у неё своя
 * цена и наличие: цвет, объём, возрастная группа. Показывается переключателем.
 * ХАРАКТЕРИСТИКА (`spec`) — просто описание: вес брутто, страна, гарантия.
 *
 * Различать обязательно: «вес ребёнка» у автокресла — это ось (разные кресла,
 * разные цены), а «вес посылки» — характеристика. Слово одно, смысл разный.
 */
function partsAttributes(PDO $db, array $partIds, ?string $kind = null): array
{
    $partIds = array_values(array_unique(array_map('intval', $partIds)));
    if (!$partIds) return [];

    $ph  = implode(',', array_fill(0, count($partIds), '?'));
    $sql = "SELECT part_id, kind, name, value FROM part_attributes WHERE part_id IN ($ph)";
    $args = $partIds;
    if ($kind !== null) { $sql .= " AND kind = ?"; $args[] = $kind; }
    $sql .= " ORDER BY sort_order, id";

    $st = $db->prepare($sql);
    $st->execute($args);

    $out = [];
    foreach ($st->fetchAll() as $r) $out[(int)$r['part_id']][] = $r;
    return $out;
}

/**
 * Переписать атрибуты товара одним набором.
 *
 * Заменяем целиком, а не досыпаем: форма продавца присылает полное состояние, и
 * «дозапись» оставила бы удалённые в интерфейсе атрибуты жить в базе.
 *
 * $attrs — [['kind' => 'axis'|'spec', 'name' => 'Цвет', 'value' => 'Чёрный'], …]
 */
function partsSaveAttributes(PDO $db, int $partId, array $attrs): void
{
    if ($partId <= 0) return;

    $db->prepare("DELETE FROM part_attributes WHERE part_id = ?")->execute([$partId]);
    if (!$attrs) return;

    $ins = $db->prepare(
        "INSERT INTO part_attributes (part_id, kind, name, value, sort_order)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE value = VALUES(value), sort_order = VALUES(sort_order)"
    );
    $i = 0;
    foreach ($attrs as $a) {
        $kind  = ($a['kind'] ?? 'spec') === 'axis' ? 'axis' : 'spec';
        $name  = trim((string)($a['name'] ?? ''));
        $value = trim((string)($a['value'] ?? ''));
        if ($name === '' || $value === '') continue;   // пустые пары не храним
        $ins->execute([$partId, $kind, mb_substr($name, 0, 60), mb_substr($value, 0, 120), $i++]);
    }
}
