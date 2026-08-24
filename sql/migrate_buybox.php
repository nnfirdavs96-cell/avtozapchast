<?php
/**
 * Маркетплейс, buy-box — этап 1: модель товара.
 *
 * Что делает:
 *   1. снимает глобальную уникальность `parts.part_number` — из-за неё второй
 *      продавец физически не мог выложить артикул, уже занятый первым;
 *   2. добавляет ключи группировки: `part_key`, `product_id`, `product_group_id`;
 *   3. добавляет флаг `is_unique_item` — б/у и разборка не сводятся в общую карточку;
 *   4. создаёт `part_attributes` — гибкие атрибуты (цвет, объём, возраст, вес);
 *   5. пересчитывает группировку по существующим товарам.
 *
 * Почему PHP, а не .sql: уникальность на `part_number` в schema.sql объявлена
 * дважды (инлайном и явным `uk_part_number`), и какие индексы реально существуют,
 * зависит от истории накатов. Снять их можно, только посмотрев information_schema.
 *
 * Запуск (повторный безопасен):
 *   php sql/migrate_buybox.php
 *   php sql/migrate_buybox.php --check      # работает ли группировка на этом сервере
 *   php sql/migrate_buybox.php --report     # какие товары попали в одну карточку
 *   php sql/migrate_buybox.php --regroup    # только пересчёт группировки
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Только из командной строки.\n"); }

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/parts/grouping.php';

$db          = getDB();
$regroupOnly = in_array('--regroup', $argv, true);
$reportOnly  = in_array('--report', $argv, true);
$checkOnly   = in_array('--check', $argv, true);

echo "Маркетплейс · buy-box, этап 1 — модель товара\n";
echo "База: " . DB_NAME . "\n";
echo str_repeat('-', 60) . "\n";

// ── Диагностика: работает ли группировка на этом сервере ─────────────────────
// Вся витрина опирается на оконные функции. Если сервер их не умеет, код молча
// уходит на запасной путь (плоский список) — сайт работает, но buy-box нет, и
// понять это по внешнему виду трудно. Этот режим отвечает прямо.
if ($checkOnly) {
    $ver = $db->query("SELECT VERSION()")->fetchColumn();
    echo "Версия сервера БД: $ver\n";

    $ok = dbSupportsWindowFunctions($db);
    echo "Оконные функции: " . ($ok ? "ЕСТЬ — группировка работает"
                                    : "НЕТ — витрина работает без buy-box") . "\n";
    if (!$ok) {
        // Показываем настоящую ошибку, а не просто «нет»: без неё непонятно,
        // сервер старый или запрос не тот.
        try { $db->query("SELECT ROW_NUMBER() OVER (ORDER BY id) AS rn FROM parts LIMIT 1")->fetchAll(); }
        catch (Throwable $e) { echo "  причина: " . $e->getMessage() . "\n"; }
    }

    $offers = (int)$db->query("SELECT COUNT(*) FROM parts")->fetchColumn();
    $cards  = (int)$db->query("SELECT COUNT(DISTINCT COALESCE(product_id, id)) FROM parts")->fetchColumn();
    echo "Предложений: $offers, карточек: $cards\n";

    $dups = partsDuplicateOfferIds($db);
    echo "Дублей (один продавец выложил товар дважды): "
       . ($dups ? implode(', ', $dups) . " — эти id должны быть помечены в Админ → Товары"
                : "нет") . "\n";
    exit;
}

// ── Просмотр: какие товары оказались в одной карточке ────────────────────────
// Нужен ДО того, как витрина начнёт показывать группы (этап 2): нормализация
// артикула склеивает «90915-YZZE1» и «90915 YZZE1», и надо глазами убедиться,
// что это действительно один товар, а не две разные детали с похожими номерами.
if ($reportOnly) {
    $rows = $db->query(
        "SELECT COALESCE(p.product_id, p.id) AS card, p.id, p.part_number, p.name,
                p.price, p.seller_id, b.name AS brand, s.shop_name
           FROM parts p
           LEFT JOIN brands b  ON b.id = p.brand_id
           LEFT JOIN sellers s ON s.id = p.seller_id
          WHERE COALESCE(p.product_id, p.id) IN (
                SELECT card FROM (
                    SELECT COALESCE(product_id, id) AS card
                      FROM parts GROUP BY card HAVING COUNT(*) > 1
                ) g
          )
       ORDER BY card, p.id"
    )->fetchAll();

    if (!$rows) {
        echo "Общих карточек с несколькими предложениями нет.\n";
        exit;
    }

    $byCard = [];
    foreach ($rows as $r) $byCard[(int)$r['card']][] = $r;

    echo "Общих карточек: " . count($byCard) . "\n\n";
    foreach ($byCard as $card => $items) {
        echo "Карточка #$card — предложений: " . count($items) . "\n";
        foreach ($items as $r) {
            printf("   id %-5d %-22s %-14s %10s   %s\n",
                (int)$r['id'],
                mb_substr((string)$r['part_number'], 0, 22),
                mb_substr((string)($r['brand'] ?? '—'), 0, 14),
                number_format((float)$r['price'], 2, '.', ' '),
                $r['seller_id'] ? ('продавец: ' . $r['shop_name']) : 'наш каталог'
            );
            echo "         " . mb_substr((string)$r['name'], 0, 70) . "\n";
        }
        echo "\n";
    }
    echo "Если в одной карточке оказались РАЗНЫЕ детали — пометьте лишнюю как\n";
    echo "«уникальный товар» либо исправьте у неё артикул, затем: php sql/migrate_buybox.php --regroup\n";
    exit;
}

if (!$regroupOnly) {

    // ── 1. Колонки ───────────────────────────────────────────────────────────
    // part_key — артикул в каноническом виде (только буквы и цифры, верхний
    // регистр). Храним отдельной колонкой, потому что вычислить такую
    // нормализацию в SQL можно лишь цепочкой REPLACE по каждому знаку, а искать
    // по ней надо на каждом сохранении товара.
    dbAddColumnIfMissing($db, 'parts', 'part_key',
        "`part_key` VARCHAR(80) NOT NULL DEFAULT '' AFTER `part_number`");

    // product_id — номер КАРТОЧКИ: тот же товар у других продавцов.
    // NULL = товар пока один в своём роде (ключ группы = собственный id).
    dbAddColumnIfMissing($db, 'parts', 'product_id',
        "`product_id` INT UNSIGNED DEFAULT NULL AFTER `part_key`");

    // product_group_id — номер СЕМЕЙСТВА: тот же товар в другом исполнении
    // (цвет, объём, возрастная группа). Заполняется на этапе вариантов; колонку
    // заводим сразу, чтобы второй миграции по живым данным не потребовалось.
    dbAddColumnIfMissing($db, 'parts', 'product_group_id',
        "`product_group_id` INT UNSIGNED DEFAULT NULL AFTER `product_id`");

    // is_unique_item — «уникальный товар»: б/у, разборка, позиция без артикула.
    // Такие никогда не сводятся с чужими: двигатель с разборки от двух продавцов —
    // это два разных товара, а не два предложения на один.
    dbAddColumnIfMissing($db, 'parts', 'is_unique_item',
        "`is_unique_item` TINYINT(1) NOT NULL DEFAULT 0 AFTER `product_group_id`");
    echo "  [OK] колонки parts: part_key, product_id, product_group_id, is_unique_item\n";

    // ── 2. Снять уникальность с артикула ─────────────────────────────────────
    $dropped = [];
    foreach (dbUniqueIndexesOnColumn($db, 'parts', 'part_number') as $idx) {
        $db->exec("ALTER TABLE `parts` DROP INDEX `$idx`");
        $dropped[] = $idx;
    }
    echo $dropped
        ? "  [OK] снята уникальность part_number (индексы: " . implode(', ', $dropped) . ")\n"
        : "  [есть] уникальных индексов на part_number нет\n";

    // Искать по артикулу всё равно надо — возвращаем обычный индекс.
    dbAddIndexIfMissing($db, 'parts', 'idx_part_number', "KEY `idx_part_number` (`part_number`)");
    dbAddIndexIfMissing($db, 'parts', 'idx_part_key',    "KEY `idx_part_key` (`part_key`, `brand_id`)");
    dbAddIndexIfMissing($db, 'parts', 'idx_product',     "KEY `idx_product` (`product_id`)");
    dbAddIndexIfMissing($db, 'parts', 'idx_product_group', "KEY `idx_product_group` (`product_group_id`)");
    echo "  [OK] индексы группировки\n";

    // ── 3. Атрибуты ──────────────────────────────────────────────────────────
    // kind: axis — ось варианта (своя цена и наличие, показывается переключателем),
    //       spec — характеристика (просто описание).
    // UNIQUE(part_id, kind, name) — у товара не может быть двух значений «Цвет».
    // KEY(name, value) — под будущие фильтры каталога («показать только чёрные»).
    $db->exec(
        "CREATE TABLE IF NOT EXISTS `part_attributes` (
          `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `part_id`    INT UNSIGNED NOT NULL,
          `kind`       ENUM('axis','spec') NOT NULL DEFAULT 'spec',
          `name`       VARCHAR(60)  NOT NULL,
          `value`      VARCHAR(120) NOT NULL,
          `sort_order` INT NOT NULL DEFAULT 0,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_part_attr` (`part_id`, `kind`, `name`),
          KEY `idx_filter` (`name`, `value`),
          CONSTRAINT `fk_pattr_part` FOREIGN KEY (`part_id`)
            REFERENCES `parts` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    echo "  [OK] таблица part_attributes\n";
}

// ── 4. Пересчёт группировки ──────────────────────────────────────────────────
[$keyed, $grouped] = partsRebuildGrouping($db);
echo "  [OK] ключей артикулов проставлено: $keyed\n";
echo "  [OK] товаров объединено в общие карточки: $grouped\n";

// ── Итог ─────────────────────────────────────────────────────────────────────
$total = (int)$db->query("SELECT COUNT(*) FROM parts")->fetchColumn();
$cards = (int)$db->query("SELECT COUNT(DISTINCT COALESCE(product_id, id)) FROM parts")->fetchColumn();
echo str_repeat('-', 60) . "\n";
echo "Товаров (предложений): $total, карточек после группировки: $cards\n";
echo $total === $cards
    ? "Пока ни один артикул не выложен двумя продавцами — это нормально до появления продавцов.\n"
    : "Общих карточек с несколькими продавцами: " . ($total - $cards) . " лишних предложений свёрнуто.\n";
