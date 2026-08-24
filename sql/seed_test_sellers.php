<?php
/**
 * Тестовые продавцы для проверки buy-box.
 *
 * ЗАЧЕМ. Механика конкуренции продавцов (этапы 1–4) на боевом невидима: продавец
 * там один — наш собственный каталог, а блок «этот товар продают N продавцов»
 * появляется только при двух и более предложениях. Проверить его глазами нечем.
 *
 * Скрипт заводит несколько магазинов и выкладывает им ТЕ ЖЕ артикулы, что уже есть
 * в нашем каталоге, — с разными ценами и наличием. Тогда витрина сворачивает их в
 * одну карточку, buy-box выбирает победителя, и всё это можно увидеть.
 *
 * БЕЗОПАСНОСТЬ. Скрипт трогает только то, что создал сам:
 *   • пользователи опознаются по e-mail вида seller1@test.local;
 *   • товары удаляются ТОЛЬКО по seller_id этих магазинов.
 * Наш каталог (seller_id IS NULL) и настоящие продавцы не затрагиваются никогда.
 * Удаление требует явного подтверждения --yes: снести чужие товары одной опечаткой
 * слишком легко.
 *
 * Запуск:
 *   php sql/seed_test_sellers.php                # создать 3 магазина с товарами
 *   php sql/seed_test_sellers.php --sellers=5    # создать 5
 *   php sql/seed_test_sellers.php --list         # что уже создано
 *   php sql/seed_test_sellers.php --remove --yes # удалить всё созданное
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Только из командной строки.\n"); }

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/parts/grouping.php';

$db = getDB();

// Опознавательный признак тестовых аккаунтов. Домен .local не существует в
// интернете, поэтому такие адреса заведомо не пересекутся с настоящими.
const TEST_EMAIL_LIKE = '%@test.local';
const TEST_PASSWORD   = 'Test12345!';

$arg = static function (string $name, $default = null) use ($argv) {
    foreach ($argv as $a) {
        if (str_starts_with($a, "--$name=")) return substr($a, strlen($name) + 3);
        if ($a === "--$name") return true;
    }
    return $default;
};

$doRemove = (bool)$arg('remove');
$doList   = (bool)$arg('list');
$count    = max(1, min(10, (int)($arg('sellers', 3))));

/** Магазины, созданные этим скриптом. */
$testSellers = static function (PDO $db): array {
    return $db->query(
        "SELECT s.id, s.shop_name, s.slug, s.status, s.commission_percent,
                u.id AS user_id, u.email,
                (SELECT COUNT(*) FROM parts p WHERE p.seller_id = s.id) AS products
           FROM sellers s JOIN users u ON u.id = s.user_id
          WHERE u.email LIKE '" . TEST_EMAIL_LIKE . "'
       ORDER BY s.id"
    )->fetchAll();
};

echo "Тестовые продавцы · buy-box\n";
echo "База: " . DB_NAME . "\n";
echo str_repeat('-', 62) . "\n";

// ── Список ───────────────────────────────────────────────────────────────────
if ($doList) {
    $rows = $testSellers($db);
    if (!$rows) { exit("Тестовых продавцов нет.\n"); }
    foreach ($rows as $r) {
        printf("  #%-3d %-22s %-10s комиссия %5s%%  товаров: %-3d  %s\n",
            $r['id'], $r['shop_name'], $r['status'],
            rtrim(rtrim(number_format((float)$r['commission_percent'], 2, '.', ''), '0'), '.'),
            $r['products'], $r['email']);
    }
    echo "\nВход в кабинет: e-mail из списка, пароль " . TEST_PASSWORD . "\n";
    exit;
}

// ── Удаление ─────────────────────────────────────────────────────────────────
if ($doRemove) {
    $rows = $testSellers($db);
    if (!$rows) { exit("Тестовых продавцов нет — удалять нечего.\n"); }

    if (!$arg('yes')) {
        echo "Будет удалено:\n";
        foreach ($rows as $r) printf("  %s — товаров %d\n", $r['shop_name'], $r['products']);
        exit("\nЭто необратимо. Повторите с --yes, если уверены:\n"
           . "  php sql/seed_test_sellers.php --remove --yes\n");
    }

    $ids = array_column($rows, 'id');
    $ph  = implode(',', array_fill(0, count($ids), '?'));

    // Порядок важен: сначала товары, потом магазины, потом пользователи —
    // иначе внешние ключи не дадут удалить родителя.
    // Условие seller_id IN (...) отсекает наш каталог: у него seller_id NULL,
    // а NULL никогда не входит в IN.
    $st = $db->prepare("DELETE FROM parts WHERE seller_id IN ($ph)");
    $st->execute($ids);
    $delParts = $st->rowCount();

    $st = $db->prepare("DELETE FROM sellers WHERE id IN ($ph)");
    $st->execute($ids);
    $delShops = $st->rowCount();

    $uids = array_column($rows, 'user_id');
    $st = $db->prepare("DELETE FROM users WHERE id IN (" . implode(',', array_fill(0, count($uids), '?')) . ")");
    $st->execute($uids);
    $delUsers = $st->rowCount();

    echo "Удалено: товаров $delParts, магазинов $delShops, пользователей $delUsers.\n";
    // Группировка пересчитывается сама: ключ карточки считается на лету, а
    // осиротевший product_id у оставшихся товаров указывает на прежний номер
    // группы и продолжает их объединять.
    exit;
}

// ── Создание ─────────────────────────────────────────────────────────────────
// Берём товары НАШЕГО каталога: тестовые магазины должны конкурировать с ними, а
// не жить в вакууме. Иначе карточка соберётся только из выдуманных позиций и
// проверка ничего не покажет про реальную витрину.
$donors = $db->query(
    "SELECT id, part_number, name, brand_id, category_id, price, images
       FROM parts
      WHERE seller_id IS NULL AND is_active = 1 AND part_key <> ''
        AND is_unique_item = 0
   ORDER BY id LIMIT 6"
)->fetchAll();

if (!$donors) {
    exit("В нашем каталоге нет подходящих товаров — нечего продублировать.\n"
       . "Сначала добавьте хотя бы один товар с артикулом.\n");
}

$existing = count($testSellers($db));
if ($existing) {
    echo "Внимание: тестовых магазинов уже " . $existing . ". Новые добавятся к ним.\n";
    echo "Чтобы начать с чистого листа: php sql/seed_test_sellers.php --remove --yes\n\n";
}

$names = ['Автомир', 'ДеталиПлюс', 'ТурбоСервис', 'МастерАвто', 'ЗапчастиХоум',
          'АвтоЛига', 'ПартСити', 'ГаражПро', 'МоторЛенд', 'КолесоТДж'];
$hash  = password_hash(TEST_PASSWORD, PASSWORD_DEFAULT);
$made  = 0;
$madeParts = 0;

for ($i = 1; $i <= $count; $i++) {
    $email = "seller$i@test.local";

    $chk = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $chk->execute([$email]);
    if ($chk->fetch()) { echo "  [есть] $email — пропускаю\n"; continue; }

    $shop = ($names[$i - 1] ?? "Магазин $i") . ' (тест)';
    $slug = 'test-shop-' . $i;

    $db->prepare(
        "INSERT INTO users (username, email, password_hash, role, is_active, created_at)
         VALUES (?, ?, ?, 'seller', 1, NOW())"
    )->execute(["test_seller_$i", $email, $hash]);
    $uid = (int)$db->lastInsertId();

    // Сразу approved: иначе кабинет закрыт и товары выложить нельзя, а нам нужно
    // проверять витрину, а не процесс модерации (он проверен в Фазе 1).
    // Комиссия разная у каждого — чтобы в реестре выплат было видно, что она
    // фиксируется отдельно по каждому магазину.
    $commission = 5 + $i * 2;
    $db->prepare(
        "INSERT INTO sellers (user_id, shop_name, slug, phone, status, commission_percent, created_at)
         VALUES (?, ?, ?, ?, 'approved', ?, NOW())"
    )->execute([$uid, $shop, $slug, '+992 90 000-00-0' . $i, $commission]);
    $sid = (int)$db->lastInsertId();
    $made++;

    // Каждому магазину — свой набор артикулов из нашего каталога. Первый товар
    // берут все (гарантированная карточка с максимумом предложений), остальные —
    // вразнобой, чтобы карточки получились разной «глубины».
    $take = [$donors[0]];
    foreach ($donors as $k => $d) {
        if ($k > 0 && ($k + $i) % 2 === 0) $take[] = $d;
    }

    foreach ($take as $d) {
        // Часть предложений намеренно без наличия: правило buy-box — «сначала в
        // наличии, среди них дешевле», и без нулевых остатков его не проверить.
        $stock = ($i % 3 === 0) ? 0 : (3 + $i * 2);

        // Цена гуляет вокруг нашей, чтобы победитель не был предсказуем по порядку.
        // А магазину БЕЗ наличия даём САМУЮ НИЗКУЮ цену — тогда демонстрация
        // доказывает правило: побеждает не самый дешёвый вообще, а самый дешёвый
        // из тех, у кого товар есть. Иначе тест прошёл бы и при неверной сортировке.
        $price = $stock === 0
            ? round((float)$d['price'] * 0.55, 2)
            : round((float)$d['price'] * (0.75 + 0.12 * $i), 2);

        $db->prepare(
            "INSERT INTO parts (seller_id, part_number, name, description, brand_id, category_id,
                                price, stock, images, is_active, moderation_status, created_by, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,1,'active',?,NOW())"
        )->execute([
            $sid, $d['part_number'], $d['name'] . ' — ' . $shop,
            'Тестовый товар для проверки buy-box. Создан скриптом seed_test_sellers.php.',
            $d['brand_id'], $d['category_id'], $price, $stock, $d['images'],
            $uid,
        ]);
        partsApplyGrouping($db, (int)$db->lastInsertId(), $d['part_number'], (int)$d['brand_id'], false);
        $madeParts++;
    }

    printf("  [OK] %-24s комиссия %2d%%  товаров: %d\n", $shop, $commission, count($take));
}

echo str_repeat('-', 62) . "\n";
echo "Создано магазинов: $made, товаров: $madeParts.\n";
echo "Вход в кабинет продавца: seller1@test.local … пароль " . TEST_PASSWORD . "\n\n";

// Показываем, что получилось на витрине — ради этого всё и затевалось.
$cards = $db->query(
    "SELECT COALESCE(product_id, id) AS card, COUNT(DISTINCT COALESCE(seller_id, 0)) AS sellers
       FROM parts
      WHERE is_active = 1 AND (seller_id IS NULL OR moderation_status = 'active')
   GROUP BY card HAVING sellers > 1
   ORDER BY sellers DESC LIMIT 5"
)->fetchAll();

if ($cards) {
    echo "Карточки, где теперь несколько продавцов:\n";
    foreach ($cards as $c) {
        $p = $db->prepare("SELECT name, part_number FROM parts WHERE id = ? LIMIT 1");
        $p->execute([(int)$c['card']]);
        $row = $p->fetch();
        printf("  карточка #%-4d продавцов: %d   %s (%s)\n",
            (int)$c['card'], (int)$c['sellers'],
            mb_substr((string)($row['name'] ?? '—'), 0, 40), $row['part_number'] ?? '');
    }
    echo "\nОткройте любую из них в каталоге — там должен появиться блок\n";
    echo "«Этот товар продают N продавцов».\n";
} else {
    echo "Карточек с несколькими продавцами не появилось — это странно, покажите вывод.\n";
}
