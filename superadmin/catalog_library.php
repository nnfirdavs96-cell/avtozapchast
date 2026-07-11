<?php
/**
 * Библиотека каталога Parts-Catalogs — постоянный архив реальных ответов API
 * (carInfoFull/oemNodesForVin/fetchScheme в PartsCatalogsAdapter пишут сюда на
 * каждый свежий, не из TTL-кэша, запрос — и по VIN, и «по параметрам»).
 * В отличие от partsapi_kv_cache (24ч, авто-протухание) эти таблицы не чистятся
 * автоматически, поэтому здесь можно накопленное скачать.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['superadmin']);
require_once dirname(__DIR__) . '/includes/catalog.php';

$db     = getDB();
$csrf   = generateCsrfToken();
$action = $_GET['action'] ?? 'list';

function clFetchOne(PDO $db, string $sql, array $params = []) {
    try { $st = $db->prepare($sql); $st->execute($params); return $st->fetch(); }
    catch (Exception $e) { return null; }
}
function clFetchAll(PDO $db, string $sql, array $params = []): array {
    try { $st = $db->prepare($sql); $st->execute($params); return $st->fetchAll(); }
    catch (Exception $e) { return []; }
}
function clCount(PDO $db, string $sql, array $params = []): int {
    try { $st = $db->prepare($sql); $st->execute($params); return (int)$st->fetchColumn(); }
    catch (Exception $e) { return 0; }
}

/**
 * Узлы авто, у которых схема ЕЩЁ НЕ собрана в библиотеку (для догрузки).
 * Список узлов дедуплицируется по groupId: PC иногда отдаёт один и тот же
 * узел (ту же схему) сразу в двух ветках дерева — без дедупа "Узлов N" не
 * совпадало бы с реальным числом схем, которые вообще можно собрать.
 * @return array{nodes:array,missing:string[],total:int,have:int}
 */
function clMissingGroups(PDO $db, string $catalogId, string $carId): array {
    $n = clFetchOne($db, "SELECT nodes_json FROM catalog_library_nodes WHERE catalog_id=? AND car_id=?", [$catalogId, $carId]);
    $rawNodes = $n ? (json_decode($n['nodes_json'] ?? '[]', true) ?: []) : [];
    $nodes = []; $seenCat = [];
    foreach ($rawNodes as $nd) {
        $cat = (string)($nd['cat'] ?? '');
        if ($cat === '' || isset($seenCat[$cat])) continue;
        $seenCat[$cat] = true; $nodes[] = $nd;
    }
    $rows = clFetchAll($db, "SELECT group_id FROM catalog_library_schemes WHERE catalog_id=? AND car_id=?", [$catalogId, $carId]);
    $have = [];
    foreach ($rows as $r) $have[(string)$r['group_id']] = true;
    $missing = [];
    foreach ($nodes as $nd) {
        $cat = (string)($nd['cat'] ?? '');
        if (empty($have[$cat])) $missing[] = $cat;
    }
    return ['nodes' => $nodes, 'missing' => $missing, 'total' => count($nodes), 'have' => count($have)];
}

// ── AJAX: собрать порцию недостающих схем авто (кнопка «Собрать все схемы») ──
if ($action === 'collect_batch') {
    header('Content-Type: application/json; charset=utf-8');
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { echo json_encode(['error' => 'csrf']); exit; }
    $catalogId = trim($_POST['catalog_id'] ?? '');
    $carId     = trim($_POST['car_id'] ?? '');
    $car = clFetchOne($db, "SELECT * FROM catalog_library_cars WHERE catalog_id=? AND car_id=?", [$catalogId, $carId]);
    if (!$car) { echo json_encode(['error' => 'not_found']); exit; }

    $prov = Catalog::provider();
    if (!($prov instanceof PartsCatalogsAdapter) || !$prov->enabled()) {
        echo json_encode(['error' => 'provider', 'message' => 'Провайдер Parts-Catalogs выключен или не выбран.']); exit;
    }

    $mg = clMissingGroups($db, $catalogId, $carId);
    if (empty($mg['missing'])) {
        echo json_encode(['done' => true, 'fetched' => 0, 'have' => $mg['have'], 'total' => $mg['total'], 'remaining' => 0]); exit;
    }
    $batch = array_slice($mg['missing'], 0, 8);   // до 8 узлов за вызов — запрос короткий
    @set_time_limit(60);
    $res = $prov->harvestSchemes($catalogId, $carId, (string)($car['criteria'] ?? ''), (string)($car['brand'] ?? ''), $batch, 8, 300);

    $mg2 = clMissingGroups($db, $catalogId, $carId);   // пересчёт после догрузки
    echo json_encode([
        'done'         => empty($mg2['missing']) || $res['rate_limited'],
        'fetched'      => $res['fetched'],
        'rate_limited' => $res['rate_limited'],
        'have'         => $mg2['have'],
        'total'        => $mg2['total'],
        'remaining'    => count($mg2['missing']),
    ]);
    exit;
}

// ── Тумблер шага 1: библиотека — источник чтения до API. Аварийный откат к старому
//    поведению (TTL-кэш → API) без деплоя кода, если библиотека вдруг начнёт мешать. ──
if ($action === 'toggle_library_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'Ошибка CSRF.'); redirect(APP_URL . '/superadmin/catalog_library.php');
    }
    setSetting('catalog_library_read_first', isset($_POST['catalog_library_read_first']) ? '1' : '0');
    flashMessage('success', 'Настройка сохранена.');
    redirect(APP_URL . '/superadmin/catalog_library.php');
}

// ── Тумблер кнопки «Скачать КП» на странице VIN ──────────────────────────────
if ($action === 'toggle_kp' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'Ошибка CSRF.'); redirect(APP_URL . '/superadmin/catalog_library.php');
    }
    setSetting('catalog_kp_enabled', isset($_POST['catalog_kp_enabled']) ? '1' : '0');
    flashMessage('success', 'Настройка сохранена.');
    redirect(APP_URL . '/superadmin/catalog_library.php');
}

// ── Тумблер автосбора (cron): суперадмин может отключить фоновый дособиратель ──
if ($action === 'toggle_autocollect' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'Ошибка CSRF.'); redirect(APP_URL . '/superadmin/catalog_library.php');
    }
    setSetting('catalog_library_autocollect', isset($_POST['catalog_library_autocollect']) ? '1' : '0');
    flashMessage('success', 'Настройка автосбора сохранена.');
    redirect(APP_URL . '/superadmin/catalog_library.php');
}

// ── Тумблер аналитики спроса (шаг 4 плана) — выключен, пока библиотека маленькая ──
if ($action === 'toggle_demand' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'Ошибка CSRF.'); redirect(APP_URL . '/superadmin/catalog_library.php');
    }
    setSetting('catalog_demand_enabled', isset($_POST['catalog_demand_enabled']) ? '1' : '0');
    flashMessage('success', 'Настройка аналитики спроса сохранена.');
    redirect(APP_URL . '/superadmin/catalog_library.php');
}

// ── Экспорт одного авто (карточка + узлы + все сохранённые схемы) → JSON-файл ──
if ($action === 'export_car') {
    $catalogId = trim($_GET['catalog_id'] ?? '');
    $carId     = trim($_GET['car_id'] ?? '');
    $car    = clFetchOne($db, "SELECT * FROM catalog_library_cars WHERE catalog_id=? AND car_id=?", [$catalogId, $carId]);
    if (!$car) { http_response_code(404); exit('Не найдено'); }
    $nodes  = clFetchOne($db, "SELECT * FROM catalog_library_nodes WHERE catalog_id=? AND car_id=?", [$catalogId, $carId]);
    $schemes = clFetchAll($db, "SELECT * FROM catalog_library_schemes WHERE catalog_id=? AND car_id=? ORDER BY group_id", [$catalogId, $carId]);

    $out = [
        'car' => [
            'catalog_id' => $car['catalog_id'], 'car_id' => $car['car_id'], 'vin' => $car['vin'],
            'brand' => $car['brand'], 'criteria' => $car['criteria'],
            'attrs' => json_decode($car['attrs_json'] ?? '[]', true),
            'created_at' => $car['created_at'], 'updated_at' => $car['updated_at'],
        ],
        'nodes' => $nodes ? json_decode($nodes['nodes_json'] ?? '[]', true) : [],
        'schemes' => array_map(function ($s) {
            return [
                'group_id' => $s['group_id'], 'group_name' => $s['group_name'],
                'img' => $s['img'], 'caption' => $s['caption'],
                'hotspots' => json_decode($s['hotspots_json'] ?? '[]', true),
                'parts' => json_decode($s['parts_json'] ?? '[]', true),
            ];
        }, $schemes),
    ];

    $fname = 'catalog_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $catalogId . '_' . $carId) . '.json';
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ── Экспорт всей библиотеки → один JSON-файл ────────────────────────────────
if ($action === 'export_all') {
    $cars = clFetchAll($db, "SELECT * FROM catalog_library_cars ORDER BY brand, catalog_id, car_id");
    $out = [];
    foreach ($cars as $car) {
        $nodes   = clFetchOne($db, "SELECT nodes_json FROM catalog_library_nodes WHERE catalog_id=? AND car_id=?", [$car['catalog_id'], $car['car_id']]);
        $schemes = clFetchAll($db, "SELECT group_id, group_name, img, caption, hotspots_json, parts_json FROM catalog_library_schemes WHERE catalog_id=? AND car_id=?", [$car['catalog_id'], $car['car_id']]);
        $out[] = [
            'catalog_id' => $car['catalog_id'], 'car_id' => $car['car_id'], 'vin' => $car['vin'],
            'brand' => $car['brand'], 'attrs' => json_decode($car['attrs_json'] ?? '[]', true),
            'nodes' => $nodes ? json_decode($nodes['nodes_json'] ?? '[]', true) : [],
            'schemes' => array_map(function ($s) {
                return [
                    'group_id' => $s['group_id'], 'group_name' => $s['group_name'],
                    'img' => $s['img'], 'caption' => $s['caption'],
                    'hotspots' => json_decode($s['hotspots_json'] ?? '[]', true),
                    'parts' => json_decode($s['parts_json'] ?? '[]', true),
                ];
            }, $schemes),
        ];
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="catalog_library_full_' . date('Y-m-d') . '.json"');
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ── Экспорт плоского списка деталей → CSV ───────────────────────────────────
if ($action === 'export_parts_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="catalog_library_parts_' . date('Y-m-d') . '.csv"');
    $f = fopen('php://output', 'w');
    fwrite($f, "\xEF\xBB\xBF"); // BOM — чтобы Excel не путал кодировку
    fputcsv($f, ['brand', 'catalog_id', 'car_id', 'vin', 'group_id', 'group_name', 'part_brand', 'part_number', 'part_name', 'pos']);
    $schemes = clFetchAll($db, "SELECT s.*, c.brand AS car_brand, c.vin FROM catalog_library_schemes s
                                 LEFT JOIN catalog_library_cars c ON c.catalog_id=s.catalog_id AND c.car_id=s.car_id");
    foreach ($schemes as $s) {
        $parts = json_decode($s['parts_json'] ?? '[]', true) ?: [];
        foreach ($parts as $p) {
            fputcsv($f, [
                $s['car_brand'], $s['catalog_id'], $s['car_id'], $s['vin'],
                $s['group_id'], $s['group_name'],
                $p['brand'] ?? '', $p['part_number'] ?? '', $p['name'] ?? '', $p['pos'] ?? '',
            ]);
        }
    }
    fclose($f);
    exit;
}

// ── Просмотр карточки ────────────────────────────────────────────────────────
$viewCar = null; $viewNodes = []; $viewSchemes = [];
if ($action === 'view') {
    $catalogId = trim($_GET['catalog_id'] ?? '');
    $carId     = trim($_GET['car_id'] ?? '');
    $viewCar = clFetchOne($db, "SELECT * FROM catalog_library_cars WHERE catalog_id=? AND car_id=?", [$catalogId, $carId]);
    if ($viewCar) {
        $viewNodes = clMissingGroups($db, $catalogId, $carId)['nodes'];   // дедуп по groupId
        $viewSchemes = clFetchAll($db, "SELECT * FROM catalog_library_schemes WHERE catalog_id=? AND car_id=? ORDER BY group_id", [$catalogId, $carId]);
    }
}

// ── Список авто (пагинация + поиск) ─────────────────────────────────────────
$page   = max(1, (int)($_GET['p'] ?? 1));
$per    = 25;
$search = trim($_GET['s'] ?? '');
$where  = [];
$params = [];
if ($search !== '') {
    $where[] = '(brand LIKE ? OR vin LIKE ? OR car_id LIKE ?)';
    $params  = ["%$search%", "%$search%", "%$search%"];
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$migrationMissing = false;
$total = 0; $cars = [];
$statCars = $statNodes = $statSchemes = 0;
try {
    $total = clCount($db, "SELECT COUNT(*) FROM catalog_library_cars $whereSql", $params);
    $offset = ($page - 1) * $per;
    $cars = clFetchAll($db,
        "SELECT c.*,
                (SELECT nodes_count FROM catalog_library_nodes n WHERE n.catalog_id=c.catalog_id AND n.car_id=c.car_id) AS nodes_count,
                (SELECT COUNT(*) FROM catalog_library_schemes s WHERE s.catalog_id=c.catalog_id AND s.car_id=c.car_id) AS schemes_count
         FROM catalog_library_cars c $whereSql ORDER BY c.updated_at DESC LIMIT $per OFFSET $offset", $params);
    $statCars    = clCount($db, "SELECT COUNT(*) FROM catalog_library_cars");
    $statNodes   = clCount($db, "SELECT COUNT(*) FROM catalog_library_nodes");
    $statSchemes = clCount($db, "SELECT COUNT(*) FROM catalog_library_schemes");
} catch (Exception $e) {
    $migrationMissing = true;
}
$pages = max(1, (int)ceil($total / $per));

$pageTitle = 'Библиотека каталога — Администрирование';
require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="az-panel">
<?php renderRoleSidebar('catalog_library'); ?>

<main class="az-main">
    <div class="az-topbar">
        <h1><i class="fa fa-archive"></i> Библиотека каталога</h1>
        <div>
            <a href="?action=export_all" class="az-btn az-btn-secondary az-btn-sm">
                <i class="fa fa-download"></i> Скачать всё (JSON)
            </a>
            <a href="?action=export_parts_csv" class="az-btn az-btn-secondary az-btn-sm">
                <i class="fa fa-table"></i> Детали (CSV)
            </a>
        </div>
    </div>

    <div class="az-content">

    <?php if ($flash = getFlashMessage()): ?>
        <div class="az-alert az-alert-<?= sanitize($flash['type']) ?>"><?= sanitize($flash['message']) ?></div>
    <?php endif; ?>

    <?php if ($migrationMissing): ?>
        <div class="az-alert az-alert-warning" style="background:#fff3cd;border:1px solid #ffc107;color:#856404;">
            <strong><i class="fa fa-exclamation-triangle"></i> Миграция БД ещё не запущена.</strong><br>
            На сервере выполните: <code>mysql -u avtouser -p avtozapchast &lt; sql/migrate_catalog_library.sql</code>
        </div>
    <?php endif; ?>

    <div class="az-card" style="background:#f8f9fa;border:1px solid #eef0f3;margin-bottom:16px;">
        <p style="margin:0;font-size:0.85rem;color:#666;line-height:1.6;">
            Каждый реальный (не из кэша) ответ Parts-Catalogs — карточка авто по VIN или по параметрам,
            дерево узлов, схема + список деталей узла — архивируется сюда без TTL. Кэш
            (<code>partsapi_kv_cache</code>, 24ч) отвечает за скорость и лимит API; эта библиотека — за
            накопление собственной базы для выгрузки.
        </p>
    </div>

    <?php $libReadFirst = getSetting('catalog_library_read_first', '1') === '1'; ?>
    <div class="az-card" style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
        <div style="font-size:0.85rem;color:#555;line-height:1.5;max-width:640px;">
            <strong><i class="fa fa-bolt" style="color:#d32f2f;"></i> Библиотека — источник чтения до API</strong><br>
            Собранное авто отдаётся из библиотеки без обращения к Parts-Catalogs — это и есть
            вся экономия лимита. Выключайте только как аварийный откат к старому поведению
            (TTL-кэш → API), если библиотека вдруг начнёт отдавать не то.
        </div>
        <form method="POST" action="?action=toggle_library_read" style="margin:0;">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;font-weight:700;font-size:0.85rem;color:<?= $libReadFirst ? '#2e7d32' : '#c62828' ?>;">
                <input type="checkbox" name="catalog_library_read_first" value="1" <?= $libReadFirst ? 'checked' : '' ?>
                       onchange="this.form.submit()">
                <?= $libReadFirst ? 'Включена' : 'Выключена (расходует лимит зря!)' ?>
            </label>
        </form>
    </div>

    <?php $kpEnabled = getSetting('catalog_kp_enabled', '1') === '1'; ?>
    <div class="az-card" style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
        <div style="font-size:0.85rem;color:#555;line-height:1.5;max-width:640px;">
            <strong><i class="fa fa-file-text-o" style="color:#d32f2f;"></i> Кнопка «Скачать КП»</strong><br>
            Печатная страница со списком деталей узла и живыми ценами — кнопка на странице VIN.
        </div>
        <form method="POST" action="?action=toggle_kp" style="margin:0;">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;font-weight:700;font-size:0.85rem;color:<?= $kpEnabled ? '#2e7d32' : '#999' ?>;">
                <input type="checkbox" name="catalog_kp_enabled" value="1" <?= $kpEnabled ? 'checked' : '' ?>
                       onchange="this.form.submit()">
                <?= $kpEnabled ? 'Включена' : 'Выключена' ?>
            </label>
        </form>
    </div>

    <?php $autocollect = getSetting('catalog_library_autocollect', '0') === '1'; ?>
    <div class="az-card" style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
        <div style="font-size:0.85rem;color:#555;line-height:1.5;max-width:640px;">
            <strong><i class="fa fa-refresh" style="color:#d32f2f;"></i> Автосбор схем (cron)</strong><br>
            Фоновый дособиратель по расписанию тихо дотягивает недостающие схемы у накопленных авто —
            малыми порциями, чтобы не выжечь суточный лимит ключа. Выключите, если хотите собирать только
            вручную кнопкой «Собрать все схемы» у авто.
            <span style="color:#999;">Требует строки в cron: <code>*/5 * * * * php <?= sanitize(dirname(__DIR__)) ?>/superadmin/catalog_library_cron.php</code></span>
        </div>
        <form method="POST" action="?action=toggle_autocollect" style="margin:0;">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;font-weight:700;font-size:0.85rem;color:<?= $autocollect ? '#2e7d32' : '#999' ?>;">
                <input type="checkbox" name="catalog_library_autocollect" value="1" <?= $autocollect ? 'checked' : '' ?>
                       onchange="this.form.submit()">
                <?= $autocollect ? 'Включён' : 'Выключен' ?>
            </label>
        </form>
    </div>

    <?php $demandOn = getSetting('catalog_demand_enabled', '0') === '1'; ?>
    <div class="az-card" style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
        <div style="font-size:0.85rem;color:#555;line-height:1.5;max-width:640px;">
            <strong><i class="fa fa-bar-chart" style="color:#d32f2f;"></i> Аналитика спроса</strong><br>
            Считает обращения клиентов к авто и узлам (не только живые запросы к API — вообще
            все просмотры), чтобы видеть, что реально ищут. Пока в библиотеке мало авто — выводы
            будут случайными; включайте, когда накопится достаточно данных.
        </div>
        <form method="POST" action="?action=toggle_demand" style="margin:0;">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;font-weight:700;font-size:0.85rem;color:<?= $demandOn ? '#2e7d32' : '#999' ?>;">
                <input type="checkbox" name="catalog_demand_enabled" value="1" <?= $demandOn ? 'checked' : '' ?>
                       onchange="this.form.submit()">
                <?= $demandOn ? 'Включена' : 'Выключена' ?>
            </label>
        </form>
    </div>

    <?php if ($demandOn):
        $topCars = clFetchAll($db,
            "SELECT c.brand, c.vin, c.catalog_id, c.car_id, SUM(d.hits) AS total_hits, MAX(d.last_hit_at) AS last_hit
             FROM catalog_demand d JOIN catalog_library_cars c ON c.catalog_id=d.catalog_id AND c.car_id=d.car_id
             GROUP BY c.catalog_id, c.car_id ORDER BY total_hits DESC LIMIT 10");
        $topNodes = clFetchAll($db,
            "SELECT c.brand, d.group_id, s.group_name, d.hits, d.last_hit_at
             FROM catalog_demand d
             JOIN catalog_library_cars c ON c.catalog_id=d.catalog_id AND c.car_id=d.car_id
             LEFT JOIN catalog_library_schemes s ON s.catalog_id=d.catalog_id AND s.car_id=d.car_id AND s.group_id=d.group_id
             WHERE d.group_id <> '' ORDER BY d.hits DESC LIMIT 10");
    ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">
        <div class="az-card" style="padding:0;overflow:hidden;">
            <div style="padding:14px 18px;border-bottom:1px solid #eef0f3;font-weight:700;font-size:0.9rem;">Топ авто по обращениям</div>
            <?php if (empty($topCars)): ?>
                <p style="color:#aaa;padding:16px 18px;font-size:0.85rem;">Пока нет данных.</p>
            <?php else: ?>
            <table class="az-table" style="font-size:0.8rem;">
                <thead><tr><th>Авто</th><th style="text-align:center;">Обращений</th></tr></thead>
                <tbody>
                <?php foreach ($topCars as $tc): ?>
                <tr>
                    <td><a href="?action=view&catalog_id=<?= urlencode($tc['catalog_id']) ?>&car_id=<?= urlencode($tc['car_id']) ?>">
                        <?= sanitize($tc['brand'] ?: '—') ?></a>
                        <?php if ($tc['vin']): ?><br><code style="font-size:0.7rem;color:#aaa;"><?= sanitize($tc['vin']) ?></code><?php endif; ?>
                    </td>
                    <td style="text-align:center;font-weight:700;"><?= (int)$tc['total_hits'] ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <div class="az-card" style="padding:0;overflow:hidden;">
            <div style="padding:14px 18px;border-bottom:1px solid #eef0f3;font-weight:700;font-size:0.9rem;">Топ узлов по обращениям</div>
            <?php if (empty($topNodes)): ?>
                <p style="color:#aaa;padding:16px 18px;font-size:0.85rem;">Пока нет данных.</p>
            <?php else: ?>
            <table class="az-table" style="font-size:0.8rem;">
                <thead><tr><th>Узел</th><th>Авто</th><th style="text-align:center;">Обращений</th></tr></thead>
                <tbody>
                <?php foreach ($topNodes as $tn): ?>
                <tr>
                    <td><?= sanitize($tn['group_name'] ?: $tn['group_id']) ?></td>
                    <td style="color:#888;"><?= sanitize($tn['brand'] ?: '—') ?></td>
                    <td style="text-align:center;font-weight:700;"><?= (int)$tn['hits'] ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;">
        <?php foreach ([
            ['fa-car',      'Автомобилей', $statCars],
            ['fa-sitemap',  'Деревьев узлов', $statNodes],
            ['fa-picture-o','Схем узлов', $statSchemes],
        ] as [$icon, $title, $val]): ?>
        <div class="az-card" style="text-align:center;padding:20px;">
            <i class="fa fa-<?= $icon ?>" style="font-size:1.8rem;color:#d32f2f;margin-bottom:8px;display:block;"></i>
            <div style="font-size:1.4rem;font-weight:800;color:#1a1a2e;"><?= (int)$val ?></div>
            <div style="font-size:0.8rem;color:#aaa;"><?= $title ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($action === 'view' && $viewCar): ?>
    <!-- ── Просмотр одного авто ──────────────────────────────────────────── -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
        <h2 style="margin:0;font-size:1.05rem;">
            <?= sanitize($viewCar['brand'] ?: '—') ?>
            <span style="color:#aaa;font-weight:400;font-size:0.85rem;">
                <?= sanitize($viewCar['catalog_id']) ?> / <?= sanitize($viewCar['car_id']) ?>
                <?php if ($viewCar['vin']): ?> · VIN <?= sanitize($viewCar['vin']) ?><?php endif; ?>
            </span>
        </h2>
        <div>
            <a href="?action=export_car&catalog_id=<?= urlencode($viewCar['catalog_id']) ?>&car_id=<?= urlencode($viewCar['car_id']) ?>"
               class="az-btn az-btn-primary az-btn-sm"><i class="fa fa-download"></i> Скачать JSON</a>
            <a href="?action=list" class="az-btn az-btn-secondary az-btn-sm">← Список</a>
        </div>
    </div>

    <?php
        $mg = clMissingGroups($db, $viewCar['catalog_id'], $viewCar['car_id']);
        $collectDone = empty($mg['missing']) && $mg['total'] > 0;
    ?>
    <div class="az-card" style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
        <div style="font-size:0.85rem;color:#555;line-height:1.5;">
            <strong><i class="fa fa-picture-o" style="color:#d32f2f;"></i> Сбор всех схем этого авто</strong><br>
            Собрано схем: <b id="clHave"><?= (int)$mg['have'] ?></b> из <b><?= (int)$mg['total'] ?></b> узлов.
            <?php if ($collectDone): ?><span style="color:#2e7d32;">— всё собрано.</span><?php endif; ?>
            <div style="color:#999;margin-top:2px;">Тянет недостающие схемы порциями с паузами — не выжигает лимит; собранное хранится навсегда.</div>
        </div>
        <div style="display:flex;align-items:center;gap:12px;">
            <div id="clProgWrap" style="display:none;width:160px;height:8px;background:#eef0f3;border-radius:6px;overflow:hidden;">
                <div id="clProgBar" style="height:100%;width:0;background:#d32f2f;transition:width .3s;"></div>
            </div>
            <button type="button" id="clCollectBtn" class="az-btn az-btn-primary az-btn-sm"
                    data-catalog="<?= sanitize($viewCar['catalog_id']) ?>" data-car="<?= sanitize($viewCar['car_id']) ?>"
                    onclick="clCollectAll(this)" <?= $collectDone ? 'disabled' : '' ?>>
                <i class="fa fa-cloud-download"></i> <?= $collectDone ? 'Всё собрано' : 'Собрать все схемы' ?>
            </button>
        </div>
    </div>
    <script>
    function clCollectAll(btn){
        var cat = btn.getAttribute('data-catalog'), car = btn.getAttribute('data-car');
        var wrap = document.getElementById('clProgWrap'), bar = document.getElementById('clProgBar'), have = document.getElementById('clHave');
        btn.disabled = true; wrap.style.display = 'block';
        var origHtml = btn.innerHTML;
        function step(){
            var body = new FormData();
            body.append('csrf_token', '<?= sanitize($csrf) ?>');
            body.append('catalog_id', cat); body.append('car_id', car);
            fetch('?action=collect_batch', { method:'POST', body:body })
              .then(function(r){ return r.json(); })
              .then(function(d){
                if (d.error){ btn.innerHTML = '<i class="fa fa-exclamation-triangle"></i> ' + (d.message || d.error); btn.disabled = false; return; }
                if (typeof d.have === 'number' && d.total){ have.textContent = d.have; bar.style.width = Math.round(d.have / d.total * 100) + '%'; }
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Собираю… осталось ' + (d.remaining || 0);
                if (d.rate_limited){ btn.innerHTML = '<i class="fa fa-clock-o"></i> Пауза (лимит) — осталось ' + d.remaining + ', нажмите позже'; btn.disabled = false; return; }
                if (d.done || (d.remaining || 0) <= 0){ btn.innerHTML = '<i class="fa fa-check"></i> Всё собрано'; setTimeout(function(){ location.reload(); }, 800); return; }
                step();  // следующая порция
              })
              .catch(function(){ btn.innerHTML = origHtml; btn.disabled = false; });
        }
        step();
    }
    </script>

    <?php $attrs = json_decode($viewCar['attrs_json'] ?? '[]', true) ?: []; ?>
    <?php if ($attrs): ?>
    <div class="az-card" style="margin-bottom:16px;">
        <h3 style="margin-top:0;">Атрибуты</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px;font-size:0.85rem;">
            <?php foreach ($attrs as $k => $v): ?>
            <div><span style="color:#aaa;"><?= sanitize((string)$k) ?>:</span> <strong><?= sanitize((string)$v) ?></strong></div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="az-card" style="margin-bottom:16px;">
        <h3 style="margin-top:0;">Узлы (<?= count($viewNodes) ?>)</h3>
        <?php if (!$viewNodes): ?>
            <p style="color:#aaa;">Дерево узлов ещё не сохранено.</p>
        <?php else: ?>
        <div style="display:flex;flex-wrap:wrap;gap:6px;">
            <?php foreach ($viewNodes as $n): ?>
            <span style="background:#f0f1f5;border-radius:14px;padding:4px 12px;font-size:0.78rem;">
                <?= sanitize($n['name'] ?? $n['cat'] ?? '') ?>
            </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="az-card" style="padding:0;overflow:hidden;">
        <div style="padding:16px 20px;border-bottom:1px solid #dee2e6;font-weight:700;">
            Сохранённые схемы (<?= count($viewSchemes) ?>)
        </div>
        <?php if (!$viewSchemes): ?>
            <p style="color:#aaa;padding:20px;">Ни одна схема узла ещё не открывалась.</p>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="az-table">
                <thead><tr><th></th><th>Узел</th><th style="text-align:center;">Деталей</th><th>Обновлено</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($viewSchemes as $s): ?>
                <tr>
                    <td style="width:60px;">
                        <?php if ($s['img']): ?>
                        <img src="<?= sanitize($s['img']) ?>" alt="" loading="lazy"
                             style="width:48px;height:48px;object-fit:cover;border-radius:4px;cursor:zoom-in;transition:transform .15s;"
                             onmouseover="this.style.transform='scale(1.15)'" onmouseout="this.style.transform='scale(1)'"
                             onclick="clOpenLightbox('<?= sanitize(addslashes($s['img'])) ?>', '<?= sanitize(addslashes($s['group_name'] ?: $s['group_id'])) ?>')">
                        <?php else: ?>
                        <span style="color:#ccc;font-size:0.75rem;">нет фото</span>
                        <?php endif; ?>
                    </td>
                    <td><?= sanitize($s['group_name'] ?: $s['group_id']) ?> <span style="color:#aaa;font-size:0.75rem;">(<?= sanitize($s['group_id']) ?>)</span></td>
                    <td style="text-align:center;"><?= (int)$s['parts_count'] ?></td>
                    <td style="color:#888;font-size:0.8rem;"><?= sanitize($s['updated_at']) ?></td>
                    <td style="text-align:right;white-space:nowrap;">
                        <?php if ($s['img']): ?>
                        <button type="button" class="az-btn az-btn-secondary az-btn-sm"
                                onclick="clOpenLightbox('<?= sanitize(addslashes($s['img'])) ?>', '<?= sanitize(addslashes($s['group_name'] ?: $s['group_id'])) ?>')">
                            <i class="fa fa-search-plus"></i> Просмотр
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Лайтбокс: увеличенный просмотр схемы узла -->
    <div id="clLightbox" onclick="if(event.target===this) clCloseLightbox()"
         style="display:none;position:fixed;inset:0;background:rgba(10,10,15,0.9);z-index:9999;align-items:center;justify-content:center;flex-direction:column;cursor:zoom-out;padding:24px;">
        <button type="button" onclick="clCloseLightbox()"
                style="position:absolute;top:20px;right:24px;background:none;border:none;color:#fff;font-size:1.8rem;cursor:pointer;line-height:1;">&times;</button>
        <div id="clLightboxCaption" style="color:#fff;font-weight:700;margin-bottom:12px;font-size:0.95rem;"></div>
        <img id="clLightboxImg" src="" alt="" style="max-width:92vw;max-height:82vh;border-radius:8px;box-shadow:0 20px 60px rgba(0,0,0,.6);cursor:default;">
    </div>
    <script>
    function clOpenLightbox(url, caption) {
        if (!url) return;
        document.getElementById('clLightboxImg').src = url;
        document.getElementById('clLightboxCaption').textContent = caption || '';
        document.getElementById('clLightbox').style.display = 'flex';
    }
    function clCloseLightbox() {
        document.getElementById('clLightbox').style.display = 'none';
        document.getElementById('clLightboxImg').src = '';
    }
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') clCloseLightbox(); });
    </script>

    <?php else: ?>
    <!-- ── Список авто ───────────────────────────────────────────────────── -->
    <div class="az-card" style="padding:12px 20px;margin-bottom:16px;">
        <form method="GET" style="display:flex;gap:8px;">
            <input type="hidden" name="action" value="list">
            <input type="text" name="s" value="<?= sanitize($search) ?>"
                   placeholder="Марка, VIN или carId..."
                   style="flex:1;padding:8px 12px;border:1px solid #ced4da;border-radius:6px;font-size:0.875rem;outline:none;">
            <button type="submit" class="az-btn az-btn-primary az-btn-sm"><i class="fa fa-search"></i></button>
            <?php if ($search): ?><a href="?action=list" class="az-btn az-btn-secondary az-btn-sm">Сброс</a><?php endif; ?>
        </form>
    </div>

    <div class="az-card" style="padding:0;overflow:hidden;">
        <div style="overflow-x:auto;">
            <table class="az-table">
                <thead>
                    <tr>
                        <th>Марка</th>
                        <th>VIN</th>
                        <th>catalogId / carId</th>
                        <th style="text-align:center;">Узлов</th>
                        <th style="text-align:center;">Схем</th>
                        <th>Обновлено</th>
                        <th style="text-align:center;">Действия</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($cars)): ?>
                    <tr><td colspan="7" style="text-align:center;color:#aaa;padding:28px;">Пока пусто — библиотека наполняется автоматически при каждом реальном поиске по VIN или по параметрам.</td></tr>
                <?php else: ?>
                    <?php foreach ($cars as $c): ?>
                    <tr>
                        <td><strong><?= sanitize($c['brand'] ?: '—') ?></strong></td>
                        <td>
                            <?php if ($c['vin']): ?>
                                <code style="font-size:0.75rem;"><?= sanitize($c['vin']) ?></code>
                            <?php else: ?>
                                <span style="color:#aaa;font-size:0.72rem;font-style:italic;" title="Найдено по марке/модели без VIN — так работает поиск «по параметрам»">без VIN (по параметрам)</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:#888;font-size:0.78rem;"><?= sanitize($c['catalog_id']) ?> / <?= sanitize($c['car_id']) ?></td>
                        <td style="text-align:center;"><?= (int)($c['nodes_count'] ?? 0) ?></td>
                        <td style="text-align:center;"><?= (int)($c['schemes_count'] ?? 0) ?></td>
                        <td style="color:#888;font-size:0.8rem;"><?= sanitize($c['updated_at']) ?></td>
                        <td style="text-align:center;white-space:nowrap;">
                            <a href="?action=view&catalog_id=<?= urlencode($c['catalog_id']) ?>&car_id=<?= urlencode($c['car_id']) ?>"
                               class="az-btn az-btn-secondary az-btn-sm"><i class="fa fa-eye"></i></a>
                            <a href="?action=export_car&catalog_id=<?= urlencode($c['catalog_id']) ?>&car_id=<?= urlencode($c['car_id']) ?>"
                               class="az-btn az-btn-primary az-btn-sm"><i class="fa fa-download"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($pages > 1): ?>
    <div style="display:flex;justify-content:center;margin-top:16px;">
        <ul class="pagination">
            <?php for ($pg = 1; $pg <= $pages; $pg++): ?>
            <li><a href="?action=list&p=<?= $pg ?>&s=<?= urlencode($search) ?>" class="page-link <?= $pg === $page ? 'active' : '' ?>"><?= $pg ?></a></li>
            <?php endfor; ?>
        </ul>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    </div><!-- /.az-content -->
</main>
</div><!-- /.az-panel -->

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
