<?php
/**
 * Админка — справочник автомобилей (марки → модели → модификации) + импорт CSV.
 * Доступ: админ и суперадмин. Три колонки: марки / модели выбранной марки /
 * модификации выбранной модели. Внизу — список товаров без привязки к авто.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['admin', 'superadmin']);
require_once dirname(__DIR__) . '/includes/vehicles.php';

$db = getDB();
ensureVehicleSchema();

$makeId  = (int)($_GET['make'] ?? 0);
$modelId = (int)($_GET['model'] ?? 0);

// ── POST-действия ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['_csrf'] ?? '')) { denyAccess(); }
    $act = $_POST['act'] ?? '';
    $back = APP_URL . '/admin/vehicles.php';
    try {
        switch ($act) {
            case 'add_make':
                $n = trim($_POST['name'] ?? '');
                if ($n !== '') { $db->prepare("INSERT IGNORE INTO vehicle_makes (name, slug, sort_order) VALUES (?,?,?)")->execute([$n, vehSlug($n), (int)($_POST['sort_order'] ?? 0)]); }
                break;
            case 'edit_make':
                $db->prepare("UPDATE vehicle_makes SET name=?, slug=?, sort_order=?, is_active=? WHERE id=?")
                   ->execute([trim($_POST['name'] ?? ''), vehSlug($_POST['name'] ?? ''), (int)($_POST['sort_order'] ?? 0), !empty($_POST['is_active']) ? 1 : 0, (int)$_POST['id']]);
                break;
            case 'del_make':
                $db->prepare("DELETE FROM vehicle_makes WHERE id=?")->execute([(int)$_POST['id']]);
                $db->prepare("DELETE md FROM vehicle_models md WHERE md.make_id=?")->execute([(int)$_POST['id']]);
                $makeId = 0; $modelId = 0;
                break;
            case 'add_model':
                $n = trim($_POST['name'] ?? ''); $mk = (int)$_POST['make_id'];
                if ($n !== '' && $mk > 0) { $db->prepare("INSERT IGNORE INTO vehicle_models (make_id, name, slug, sort_order) VALUES (?,?,?,?)")->execute([$mk, $n, vehSlug($n), (int)($_POST['sort_order'] ?? 0)]); }
                $makeId = $mk;
                break;
            case 'edit_model':
                $db->prepare("UPDATE vehicle_models SET name=?, slug=?, sort_order=?, is_active=? WHERE id=?")
                   ->execute([trim($_POST['name'] ?? ''), vehSlug($_POST['name'] ?? ''), (int)($_POST['sort_order'] ?? 0), !empty($_POST['is_active']) ? 1 : 0, (int)$_POST['id']]);
                $makeId = (int)$_POST['make_id'];
                break;
            case 'del_model':
                $db->prepare("DELETE FROM vehicle_models WHERE id=?")->execute([(int)$_POST['id']]);
                $makeId = (int)$_POST['make_id']; $modelId = 0;
                break;
            case 'add_mod':
                $ml = (int)$_POST['model_id'];
                if ($ml > 0) {
                    $db->prepare("INSERT INTO vehicle_mods (model_id, name, year_from, year_to, engine, body) VALUES (?,?,?,?,?,?)")
                       ->execute([$ml, trim($_POST['name'] ?? ''), ((int)($_POST['year_from'] ?? 0)) ?: null, ((int)($_POST['year_to'] ?? 0)) ?: null, trim($_POST['engine'] ?? '') ?: null, trim($_POST['body'] ?? '') ?: null]);
                }
                $makeId = (int)$_POST['make_id']; $modelId = $ml;
                break;
            case 'del_mod':
                $db->prepare("DELETE FROM vehicle_mods WHERE id=?")->execute([(int)$_POST['id']]);
                $makeId = (int)$_POST['make_id']; $modelId = (int)$_POST['model_id'];
                break;
            case 'import_csv':
                $added = vehImportCsv($db, $_FILES['csv'] ?? []);
                flashMessage('success', "Импорт CSV: добавлено марок {$added['makes']}, моделей {$added['models']}, модификаций {$added['mods']}.");
                break;
        }
    } catch (Throwable $e) {
        flashMessage('danger', 'Ошибка: ' . $e->getMessage());
    }
    $qs = array_filter(['make' => $makeId ?: null, 'model' => $modelId ?: null]);
    redirect($back . ($qs ? '?' . http_build_query($qs) : ''));
}

/** Импорт CSV: колонки make;model[;year_from;year_to;engine;body]. */
function vehImportCsv(PDO $db, array $file): array
{
    $res = ['makes' => 0, 'models' => 0, 'mods' => 0];
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return $res;
    $fh = fopen($file['tmp_name'], 'r');
    if (!$fh) return $res;

    $mkIns = $db->prepare("INSERT IGNORE INTO vehicle_makes (name, slug) VALUES (?,?)");
    $mkGet = $db->prepare("SELECT id FROM vehicle_makes WHERE slug=? LIMIT 1");
    $mdIns = $db->prepare("INSERT IGNORE INTO vehicle_models (make_id, name, slug) VALUES (?,?,?)");
    $mdGet = $db->prepare("SELECT id FROM vehicle_models WHERE make_id=? AND slug=? LIMIT 1");
    $moIns = $db->prepare("INSERT INTO vehicle_mods (model_id, name, year_from, year_to, engine, body) VALUES (?,?,?,?,?,?)");

    $first = true;
    while (($row = fgetcsv($fh, 0, ';')) !== false) {
        if ($row === [null] || $row === false) continue;
        // авто-детект разделителя: если одна колонка — пробуем запятую
        if (count($row) === 1 && strpos((string)$row[0], ',') !== false) $row = str_getcsv((string)$row[0], ',');
        $make = trim((string)($row[0] ?? ''));
        $model = trim((string)($row[1] ?? ''));
        // пропускаем строку-заголовок
        if ($first) { $first = false; if (mb_strtolower($make) === 'make' || mb_strtolower($make) === 'марка') continue; }
        if ($make === '' || $model === '') continue;

        $mkSlug = vehSlug($make);
        $mkIns->execute([$make, $mkSlug]); if ($mkIns->rowCount() > 0) $res['makes']++;
        $mkGet->execute([$mkSlug]); $mid = (int)$mkGet->fetchColumn();
        if ($mid <= 0) continue;

        $mdSlug = vehSlug($model);
        $mdIns->execute([$mid, $model, $mdSlug]); if ($mdIns->rowCount() > 0) $res['models']++;
        $mdGet->execute([$mid, $mdSlug]); $mdid = (int)$mdGet->fetchColumn();
        if ($mdid <= 0) continue;

        $yf = (int)($row[2] ?? 0); $yt = (int)($row[3] ?? 0);
        $eng = trim((string)($row[4] ?? '')); $body = trim((string)($row[5] ?? ''));
        if ($yf || $yt || $eng !== '' || $body !== '') {
            $moIns->execute([$mdid, '', $yf ?: null, $yt ?: null, $eng ?: null, $body ?: null]);
            $res['mods']++;
        }
    }
    fclose($fh);
    return $res;
}

// ── Данные для вывода ────────────────────────────────────────────────────────
$makes  = $db->query("SELECT mk.*, (SELECT COUNT(*) FROM vehicle_models md WHERE md.make_id=mk.id) AS models_cnt FROM vehicle_makes mk ORDER BY mk.sort_order, mk.name")->fetchAll();
$models = [];
if ($makeId) { $st = $db->prepare("SELECT md.*, (SELECT COUNT(*) FROM vehicle_mods vm WHERE vm.model_id=md.id) AS mods_cnt FROM vehicle_models md WHERE md.make_id=? ORDER BY md.sort_order, md.name"); $st->execute([$makeId]); $models = $st->fetchAll(); }
$mods = [];
if ($modelId) { $st = $db->prepare("SELECT * FROM vehicle_mods WHERE model_id=? ORDER BY year_from DESC, id"); $st->execute([$modelId]); $mods = $st->fetchAll(); }

$curMake  = null; foreach ($makes as $m) { if ((int)$m['id'] === $makeId) { $curMake = $m; break; } }
$curModel = null; foreach ($models as $m) { if ((int)$m['id'] === $modelId) { $curModel = $m; break; } }

// Товары без привязки к авто (активные).
$noVeh = (int)$db->query(
    "SELECT COUNT(*) FROM parts p
      WHERE p.is_active=1 AND (p.seller_id IS NULL OR p.moderation_status='active')
        AND NOT EXISTS (SELECT 1 FROM part_vehicles pv WHERE pv.part_id=p.id)"
)->fetchColumn();
$needReview = (int)$db->query("SELECT COUNT(DISTINCT part_id) FROM part_vehicles WHERE needs_review=1")->fetchColumn();

$csrf = generateCsrfToken();
$pageTitle = 'Автомобили — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/admin-header.php';

$selfBase = APP_URL . '/admin/vehicles.php';
$h = static fn($s) => sanitize((string)$s);
?>
<div class="az-panel">
  <?php renderRoleSidebar('vehicles'); ?>
  <div class="az-main">
    <div class="az-topbar"><h1>Автомобили (справочник)</h1></div>
    <div class="az-content">
      <?php if ($flash = getFlashMessage()): ?><div class="az-alert az-alert-<?= $h($flash['type']) ?>"><?= $h($flash['message']) ?></div><?php endif; ?>

      <!-- Импорт CSV + статусы -->
      <div class="az-card" style="margin-bottom:16px;">
        <div class="az-card-header"><h4 class="az-card-title">Импорт CSV и проверка</h4></div>
        <div class="az-card-body" style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;">
          <form method="post" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="act" value="import_csv">
            <input type="file" name="csv" accept=".csv,text/csv" required>
            <button class="az-btn az-btn-primary az-btn-sm" type="submit"><i class="fa fa-upload"></i> Импорт</button>
            <span style="color:#888;font-size:.8rem;">Колонки: <code>make;model;year_from;year_to;engine;body</code> (разделитель «;» или «,»)</span>
          </form>
          <div style="font-size:.9rem;">
            <div>Товаров без привязки к авто: <b><?= $noVeh ?></b> <a href="<?= APP_URL ?>/admin/products.php?no_vehicle=1">показать</a></div>
            <div>Авто-привязки на проверке: <b><?= $needReview ?></b> <a href="<?= APP_URL ?>/admin/products.php?veh_review=1">показать</a></div>
          </div>
        </div>
      </div>

      <div class="row">
        <!-- Марки -->
        <div class="col-lg-4 mb-16">
          <div class="az-card"><div class="az-card-header"><h4 class="az-card-title">Марки (<?= count($makes) ?>)</h4></div>
            <div class="az-card-body">
              <form method="post" style="display:flex;gap:6px;margin-bottom:10px;">
                <input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="act" value="add_make">
                <input type="text" name="name" placeholder="Новая марка" required style="flex:1;padding:7px 10px;border:1px solid #ddd;border-radius:7px;">
                <button class="az-btn az-btn-primary az-btn-sm" type="submit">+</button>
              </form>
              <ul style="list-style:none;margin:0;padding:0;max-height:460px;overflow:auto;">
                <?php foreach ($makes as $m): ?>
                <li style="display:flex;align-items:center;gap:6px;padding:5px 0;border-bottom:1px solid #f0f0f4;">
                  <a href="<?= $selfBase ?>?make=<?= (int)$m['id'] ?>" style="flex:1;font-weight:<?= $makeId===(int)$m['id']?'700':'400' ?>;color:<?= $makeId===(int)$m['id']?'#C70909':'#333' ?>;"><?= $h($m['name']) ?> <span style="color:#aaa;font-size:.8rem;">(<?= (int)$m['models_cnt'] ?>)</span></a>
                  <form method="post" onsubmit="return confirm('Удалить марку и все её модели?');" style="margin:0;">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="act" value="del_make"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                    <button class="az-btn az-btn-outline az-btn-sm" type="submit" title="Удалить" style="color:#c0392b;border-color:#eec4c4;">&times;</button>
                  </form>
                </li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        </div>

        <!-- Модели -->
        <div class="col-lg-4 mb-16">
          <div class="az-card"><div class="az-card-header"><h4 class="az-card-title"><?= $curMake ? 'Модели: ' . $h($curMake['name']) : 'Модели' ?></h4></div>
            <div class="az-card-body">
              <?php if (!$curMake): ?><p style="color:#999;">Выберите марку слева.</p><?php else: ?>
              <form method="post" style="display:flex;gap:6px;margin-bottom:10px;">
                <input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="act" value="add_model"><input type="hidden" name="make_id" value="<?= $makeId ?>">
                <input type="text" name="name" placeholder="Новая модель" required style="flex:1;padding:7px 10px;border:1px solid #ddd;border-radius:7px;">
                <button class="az-btn az-btn-primary az-btn-sm" type="submit">+</button>
              </form>
              <ul style="list-style:none;margin:0;padding:0;max-height:460px;overflow:auto;">
                <?php foreach ($models as $m): ?>
                <li style="display:flex;align-items:center;gap:6px;padding:5px 0;border-bottom:1px solid #f0f0f4;">
                  <a href="<?= $selfBase ?>?make=<?= $makeId ?>&model=<?= (int)$m['id'] ?>" style="flex:1;font-weight:<?= $modelId===(int)$m['id']?'700':'400' ?>;color:<?= $modelId===(int)$m['id']?'#C70909':'#333' ?>;"><?= $h($m['name']) ?> <span style="color:#aaa;font-size:.8rem;">(<?= (int)$m['mods_cnt'] ?>)</span></a>
                  <form method="post" onsubmit="return confirm('Удалить модель?');" style="margin:0;">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="act" value="del_model"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="make_id" value="<?= $makeId ?>">
                    <button class="az-btn az-btn-outline az-btn-sm" type="submit" title="Удалить" style="color:#c0392b;border-color:#eec4c4;">&times;</button>
                  </form>
                </li>
                <?php endforeach; ?>
              </ul>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Модификации -->
        <div class="col-lg-4 mb-16">
          <div class="az-card"><div class="az-card-header"><h4 class="az-card-title"><?= $curModel ? 'Модификации: ' . $h($curModel['name']) : 'Модификации' ?></h4></div>
            <div class="az-card-body">
              <?php if (!$curModel): ?><p style="color:#999;">Выберите модель.</p><?php else: ?>
              <form method="post" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px;">
                <input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="act" value="add_mod"><input type="hidden" name="model_id" value="<?= $modelId ?>"><input type="hidden" name="make_id" value="<?= $makeId ?>">
                <input type="number" name="year_from" placeholder="с" style="width:70px;padding:7px;border:1px solid #ddd;border-radius:7px;">
                <input type="number" name="year_to" placeholder="по" style="width:70px;padding:7px;border:1px solid #ddd;border-radius:7px;">
                <input type="text" name="engine" placeholder="двигатель (2GR-FE 3.5)" style="flex:1;min-width:120px;padding:7px;border:1px solid #ddd;border-radius:7px;">
                <input type="text" name="body" placeholder="кузов" style="width:90px;padding:7px;border:1px solid #ddd;border-radius:7px;">
                <button class="az-btn az-btn-primary az-btn-sm" type="submit">+ Добавить</button>
              </form>
              <ul style="list-style:none;margin:0;padding:0;max-height:430px;overflow:auto;">
                <?php foreach ($mods as $m): ?>
                <li style="display:flex;align-items:center;gap:6px;padding:5px 0;border-bottom:1px solid #f0f0f4;">
                  <span style="flex:1;"><?= $h(vehModLabel($m) ?: 'Модификация') ?></span>
                  <form method="post" onsubmit="return confirm('Удалить модификацию?');" style="margin:0;">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="act" value="del_mod"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="make_id" value="<?= $makeId ?>"><input type="hidden" name="model_id" value="<?= $modelId ?>">
                    <button class="az-btn az-btn-outline az-btn-sm" type="submit" title="Удалить" style="color:#c0392b;border-color:#eec4c4;">&times;</button>
                  </form>
                </li>
                <?php endforeach; ?>
                <?php if (!$mods): ?><li style="color:#999;">Модификаций нет (товар можно привязать к модели целиком).</li><?php endif; ?>
              </ul>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
