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

$db     = getDB();
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
        $n = clFetchOne($db, "SELECT nodes_json FROM catalog_library_nodes WHERE catalog_id=? AND car_id=?", [$catalogId, $carId]);
        $viewNodes = $n ? (json_decode($n['nodes_json'] ?? '[]', true) ?: []) : [];
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
                <thead><tr><th></th><th>Узел</th><th style="text-align:center;">Деталей</th><th>Обновлено</th></tr></thead>
                <tbody>
                <?php foreach ($viewSchemes as $s): ?>
                <tr>
                    <td style="width:50px;">
                        <?php if ($s['img']): ?><img src="<?= sanitize($s['img']) ?>" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:4px;"><?php endif; ?>
                    </td>
                    <td><?= sanitize($s['group_name'] ?: $s['group_id']) ?> <span style="color:#aaa;font-size:0.75rem;">(<?= sanitize($s['group_id']) ?>)</span></td>
                    <td style="text-align:center;"><?= (int)$s['parts_count'] ?></td>
                    <td style="color:#888;font-size:0.8rem;"><?= sanitize($s['updated_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

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
                        <td><code style="font-size:0.75rem;"><?= sanitize($c['vin'] ?: '—') ?></code></td>
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
