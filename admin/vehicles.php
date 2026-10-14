<?php
/**
 * Админка — справочник автомобилей (марки → модели → модификации) + импорт CSV.
 * Доступ: админ и суперадмин. С подсказками и примерами, чтобы было понятно,
 * что и куда вводить. Три колонки: марки / модели выбранной марки /
 * модификации выбранной модели.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['admin', 'superadmin']);
require_once dirname(__DIR__) . '/includes/vehicles.php';

$db = getDB();
ensureVehicleSchema();

$makeId  = (int)($_GET['make'] ?? 0);
$modelId = (int)($_GET['model'] ?? 0);

// Скачать пример CSV.
if (isset($_GET['sample_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="vehicles_sample.csv"');
    echo "\xEF\xBB\xBF";
    echo "make;model;year_from;year_to;engine;volume;fuel;body\r\n";
    echo "Lexus;RX350;2006;2009;2GR-FE;3.5;бензин;XU30\r\n";
    echo "Toyota;Camry;2011;2017;2AR-FE;2.5;бензин;XV50\r\n";
    echo "Hyundai;Sonata;2014;2019;G4KH;2.0;бензин;LF\r\n";
    exit;
}

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
            case 'del_model':
                $db->prepare("DELETE FROM vehicle_models WHERE id=?")->execute([(int)$_POST['id']]);
                $makeId = (int)$_POST['make_id']; $modelId = 0;
                break;
            case 'add_mod':
            case 'edit_mod':
                $ml = (int)$_POST['model_id'];
                $vals = [
                    ((int)($_POST['year_from'] ?? 0)) ?: null,
                    ((int)($_POST['year_to'] ?? 0)) ?: null,
                    trim($_POST['engine'] ?? '') ?: null,
                    (trim($_POST['volume'] ?? '') !== '') ? (float)str_replace(',', '.', $_POST['volume']) : null,
                    trim($_POST['fuel'] ?? '') ?: null,
                    trim($_POST['body'] ?? '') ?: null,
                ];
                if ($act === 'edit_mod' && (int)($_POST['id'] ?? 0) > 0) {
                    $db->prepare("UPDATE vehicle_mods SET year_from=?, year_to=?, engine=?, volume=?, fuel=?, body=? WHERE id=?")
                       ->execute(array_merge($vals, [(int)$_POST['id']]));
                } elseif ($ml > 0) {
                    $db->prepare("INSERT INTO vehicle_mods (model_id, name, year_from, year_to, engine, volume, fuel, body) VALUES (?,?,?,?,?,?,?,?)")
                       ->execute(array_merge([$ml, ''], $vals));
                }
                $makeId = (int)$_POST['make_id']; $modelId = $ml;
                break;
            case 'del_mod':
                $db->prepare("DELETE FROM vehicle_mods WHERE id=?")->execute([(int)$_POST['id']]);
                $makeId = (int)$_POST['make_id']; $modelId = (int)$_POST['model_id'];
                break;
            case 'import_csv':
                $_SESSION['veh_import'] = vehImportCsv($db, $_FILES['csv'] ?? []);
                break;
        }
    } catch (Throwable $e) {
        flashMessage('danger', 'Ошибка: ' . $e->getMessage());
    }
    $qs = array_filter(['make' => $makeId ?: null, 'model' => $modelId ?: null]);
    redirect($back . ($qs ? '?' . http_build_query($qs) : ''));
}

/** Импорт CSV: make;model;year_from;year_to;engine;volume;fuel;body. */
function vehImportCsv(PDO $db, array $file): array
{
    $res = ['makes' => 0, 'models' => 0, 'mods' => 0, 'skipped' => 0, 'errors' => []];
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) { $res['errors'][] = 'Файл не загружен.'; return $res; }
    $fh = fopen($file['tmp_name'], 'r');
    if (!$fh) { $res['errors'][] = 'Не удалось открыть файл.'; return $res; }

    $mkIns = $db->prepare("INSERT IGNORE INTO vehicle_makes (name, slug) VALUES (?,?)");
    $mkGet = $db->prepare("SELECT id FROM vehicle_makes WHERE slug=? LIMIT 1");
    $mdIns = $db->prepare("INSERT IGNORE INTO vehicle_models (make_id, name, slug) VALUES (?,?,?)");
    $mdGet = $db->prepare("SELECT id FROM vehicle_models WHERE make_id=? AND slug=? LIMIT 1");
    $moIns = $db->prepare("INSERT INTO vehicle_mods (model_id, name, year_from, year_to, engine, volume, fuel, body) VALUES (?,?,?,?,?,?,?,?)");

    $lineNo = 0; $first = true;
    while (($row = fgetcsv($fh, 0, ';')) !== false) {
        $lineNo++;
        if ($row === [null]) continue;
        if (count($row) === 1 && strpos((string)$row[0], ',') !== false) $row = str_getcsv((string)$row[0], ',');
        $make = trim((string)($row[0] ?? ''));
        $model = trim((string)($row[1] ?? ''));
        if ($first) { $first = false; if (mb_strtolower($make) === 'make' || mb_strtolower($make) === 'марка') continue; }
        if ($make === '' || $model === '') { $res['skipped']++; if (count($res['errors']) < 20) $res['errors'][] = "строка $lineNo: пустая марка или модель"; continue; }

        $mkSlug = vehSlug($make);
        $mkIns->execute([$make, $mkSlug]); if ($mkIns->rowCount() > 0) $res['makes']++;
        $mkGet->execute([$mkSlug]); $mid = (int)$mkGet->fetchColumn();
        if ($mid <= 0) { $res['skipped']++; continue; }

        $mdSlug = vehSlug($model);
        $mdIns->execute([$mid, $model, $mdSlug]); if ($mdIns->rowCount() > 0) $res['models']++;
        $mdGet->execute([$mid, $mdSlug]); $mdid = (int)$mdGet->fetchColumn();
        if ($mdid <= 0) { $res['skipped']++; continue; }

        $yf = (int)($row[2] ?? 0); $yt = (int)($row[3] ?? 0);
        $eng = trim((string)($row[4] ?? ''));
        $vol = trim((string)($row[5] ?? '')); $vol = $vol !== '' ? (float)str_replace(',', '.', $vol) : null;
        $fuel = trim((string)($row[6] ?? '')); $body = trim((string)($row[7] ?? ''));
        if ($yf || $yt || $eng !== '' || $vol !== null || $fuel !== '' || $body !== '') {
            $moIns->execute([$mdid, '', $yf ?: null, $yt ?: null, $eng ?: null, $vol, $fuel ?: null, $body ?: null]);
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
$mods = $modelId ? vehMods($db, $modelId) : [];

$curMake  = null; foreach ($makes as $m) { if ((int)$m['id'] === $makeId) { $curMake = $m; break; } }
$curModel = null; foreach ($models as $m) { if ((int)$m['id'] === $modelId) { $curModel = $m; break; } }

$noVeh = (int)$db->query(
    "SELECT COUNT(*) FROM parts p
      WHERE p.is_active=1 AND (p.seller_id IS NULL OR p.moderation_status='active')
        AND NOT EXISTS (SELECT 1 FROM part_vehicles pv WHERE pv.part_id=p.id)"
)->fetchColumn();
$needReview = (int)$db->query("SELECT COUNT(DISTINCT part_id) FROM part_vehicles WHERE needs_review=1")->fetchColumn();
$newReq     = (int)$db->query("SELECT COUNT(*) FROM vehicle_requests WHERE status='new'")->fetchColumn();

$import = $_SESSION['veh_import'] ?? null; unset($_SESSION['veh_import']);

$csrf = generateCsrfToken();
$yearNow = (int)date('Y');
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

      <!-- Инструкция (сворачивается, состояние запоминается) -->
      <div class="az-card veh-howto" id="vehHowto" style="margin-bottom:16px;">
        <div class="az-card-header" style="cursor:pointer;" id="vehHowtoToggle">
          <h4 class="az-card-title"><i class="fa fa-info-circle"></i> Как заполнять справочник</h4>
          <button type="button" class="az-btn az-btn-outline az-btn-sm" id="vehHowtoBtn">свернуть</button>
        </div>
        <div class="az-card-body" id="vehHowtoBody">
          <ol style="margin:0;padding-left:20px;line-height:1.9;color:#444;">
            <li><b>Марка</b> — производитель авто: Toyota, Lexus, Hyundai.</li>
            <li><b>Модель</b> — выберите марку слева и добавьте модель: Camry, RX350, Sonata.</li>
            <li><b>Модификация</b> — выберите модель и укажите годы выпуска и двигатель: 2006–2011, 2GR-FE, 3.5 л, бензин.</li>
          </ol>
          <p style="margin:8px 0 0;color:#777;">Товары привязываются к модификациям — так покупатель найдёт запчасть под свою машину.</p>
        </div>
      </div>

      <!-- Импорт CSV + проверка -->
      <div class="az-card" style="margin-bottom:16px;">
        <div class="az-card-header"><h4 class="az-card-title">Импорт CSV и проверка</h4></div>
        <div class="az-card-body">
          <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;">
            <form method="post" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
              <input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="act" value="import_csv">
              <input type="file" name="csv" accept=".csv,text/csv" required>
              <button class="az-btn az-btn-primary az-btn-sm" type="submit"><i class="fa fa-upload"></i> Импорт</button>
              <a class="az-btn az-btn-outline az-btn-sm" href="<?= $selfBase ?>?sample_csv=1"><i class="fa fa-download"></i> Скачать пример CSV</a>
            </form>
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
              <a class="az-btn az-btn-outline az-btn-sm" href="<?= APP_URL ?>/admin/vehicle_review.php"
                 title="На проверке — привязки, найденные автоматически по названию товара. Откройте список и подтвердите или удалите их.">
                 Авто-привязки на проверке: <b><?= $needReview ?></b></a>
              <a class="az-btn az-btn-outline az-btn-sm" href="<?= APP_URL ?>/admin/products.php?no_vehicle=1"
                 title="Товары, для которых ещё не указана ни одна машина.">Товаров без привязки: <b><?= $noVeh ?></b></a>
              <?php if ($newReq > 0): ?>
              <a class="az-btn az-btn-outline az-btn-sm" href="<?= APP_URL ?>/admin/vehicle_review.php?tab=requests" title="Заявки продавцов на добавление авто.">Заявки от продавцов: <b><?= $newReq ?></b></a>
              <?php endif; ?>
            </div>
          </div>
          <p style="margin:10px 0 0;color:#777;font-size:.85rem;">
            Колонки: <code>make;model;year_from;year_to;engine;volume;fuel;body</code>. Первая строка — названия колонок.
            Разделитель — точка с запятой «;». Если марки или модели нет — она создастся автоматически. Дубликаты пропускаются.
          </p>
          <?php if ($import): ?>
          <div class="az-alert az-alert-success" style="margin:12px 0 0;">
            Импорт: добавлено марок <b><?= (int)$import['makes'] ?></b>, моделей <b><?= (int)$import['models'] ?></b>, модификаций <b><?= (int)$import['mods'] ?></b>.
            <?php if (!empty($import['skipped'])): ?>Пропущено строк: <b><?= (int)$import['skipped'] ?></b>
              <?php if (!empty($import['errors'])): ?><a href="javascript:void(0)" onclick="document.getElementById('impErr').style.display='block';this.style.display='none';">— показать ошибки</a>
              <div id="impErr" style="display:none;margin-top:6px;font-size:.82rem;color:#b23;"><?php foreach ($import['errors'] as $er) echo $h($er) . '<br>'; ?></div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="row">
        <!-- ── Марки ── -->
        <div class="col-lg-4 mb-16">
          <div class="az-card"><div class="az-card-header"><h4 class="az-card-title">Марки (<?= count($makes) ?>)</h4></div>
            <div class="az-card-body">
              <input type="text" id="makeSearch" placeholder="Поиск марки…" style="width:100%;padding:7px 10px;border:1px solid #e3e3ea;border-radius:7px;margin-bottom:10px;">
              <form method="post" style="margin-bottom:4px;">
                <input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="act" value="add_make">
                <div style="display:flex;gap:6px;">
                  <input type="text" name="name" placeholder="Например: Toyota" required style="flex:1;min-width:0;padding:8px 10px;border:1px solid #ddd;border-radius:7px;">
                  <button class="az-btn az-btn-primary az-btn-sm" type="submit">Добавить</button>
                </div>
                <small style="color:#999;">Название производителя латиницей, как на эмблеме.</small>
              </form>
              <ul class="veh-list" id="makeList" style="list-style:none;margin:10px 0 0;padding:0;max-height:460px;overflow:auto;">
                <?php foreach ($makes as $m): $act = $makeId === (int)$m['id']; ?>
                <li class="veh-item <?= $act ? 'is-active' : '' ?>" data-name="<?= $h(mb_strtolower($m['name'])) ?>">
                  <a href="<?= $selfBase ?>?make=<?= (int)$m['id'] ?>" class="veh-item-name"><?= $h($m['name']) ?> <span class="veh-count" title="Количество моделей">(<?= (int)$m['models_cnt'] ?>)</span></a>
                  <form method="post" onsubmit="return confirm('Удалить марку <?= $h($m['name']) ?> и все её модели (<?= (int)$m['models_cnt'] ?>)? Товары будут отвязаны.');" style="margin:0;">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="act" value="del_make"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                    <button class="veh-del" type="submit" title="Удалить марку"><i class="fa fa-trash-o"></i></button>
                  </form>
                </li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        </div>

        <!-- ── Модели ── -->
        <div class="col-lg-4 mb-16">
          <div class="az-card"><div class="az-card-header"><h4 class="az-card-title"><?= $curMake ? 'Модели ' . $h($curMake['name']) : 'Модели' ?></h4></div>
            <div class="az-card-body">
              <?php if (!$curMake): ?>
              <p style="color:#999;">← Выберите марку слева, чтобы увидеть и добавить её модели.</p>
              <?php else: ?>
              <form method="post" style="margin-bottom:4px;">
                <input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="act" value="add_model"><input type="hidden" name="make_id" value="<?= $makeId ?>">
                <div style="display:flex;gap:6px;">
                  <input type="text" name="name" placeholder="Например: RX350" required style="flex:1;min-width:0;padding:8px 10px;border:1px solid #ddd;border-radius:7px;">
                  <button class="az-btn az-btn-primary az-btn-sm" type="submit">Добавить</button>
                </div>
                <small style="color:#999;">Название модели без года и двигателя. Год и двигатель — в модификациях.</small>
              </form>
              <ul class="veh-list" style="list-style:none;margin:10px 0 0;padding:0;max-height:460px;overflow:auto;">
                <?php foreach ($models as $m): $act = $modelId === (int)$m['id']; ?>
                <li class="veh-item <?= $act ? 'is-active' : '' ?>">
                  <a href="<?= $selfBase ?>?make=<?= $makeId ?>&model=<?= (int)$m['id'] ?>" class="veh-item-name"><?= $h($m['name']) ?> <span class="veh-count" title="Количество модификаций">(<?= (int)$m['mods_cnt'] ?>)</span></a>
                  <form method="post" onsubmit="return confirm('Удалить модель <?= $h($m['name']) ?>?');" style="margin:0;">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="act" value="del_model"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="make_id" value="<?= $makeId ?>">
                    <button class="veh-del" type="submit" title="Удалить модель"><i class="fa fa-trash-o"></i></button>
                  </form>
                </li>
                <?php endforeach; ?>
                <?php if (!$models): ?><li style="color:#999;">Моделей пока нет — добавьте первую.</li><?php endif; ?>
              </ul>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- ── Модификации ── -->
        <div class="col-lg-4 mb-16">
          <div class="az-card"><div class="az-card-header"><h4 class="az-card-title"><?= $curModel ? 'Модификации ' . $h(($curMake['name'] ?? '') . ' ' . $curModel['name']) : 'Модификации' ?></h4></div>
            <div class="az-card-body">
              <?php if (!$curModel): ?>
              <p style="color:#999;">Выберите модель, чтобы добавить годы и двигатель.</p>
              <?php else: ?>
              <form method="post" id="modForm" class="veh-modform">
                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                <input type="hidden" name="act" value="add_mod" id="modAct">
                <input type="hidden" name="id" value="" id="modEditId">
                <input type="hidden" name="model_id" value="<?= $modelId ?>"><input type="hidden" name="make_id" value="<?= $makeId ?>">
                <div class="veh-modgrid">
                  <label>Год с <b>*</b>
                    <select name="year_from" id="mf_year_from" required>
                      <option value="">—</option>
                      <?php for ($y = $yearNow; $y >= 1980; $y--): ?><option value="<?= $y ?>"><?= $y ?></option><?php endfor; ?>
                    </select>
                  </label>
                  <label>Год по <b>*</b>
                    <select name="year_to" id="mf_year_to" required>
                      <option value="">—</option>
                      <option value="<?= $yearNow ?>">по н.в.</option>
                      <?php for ($y = $yearNow; $y >= 1980; $y--): ?><option value="<?= $y ?>"><?= $y ?></option><?php endfor; ?>
                    </select>
                  </label>
                  <label>Код двигателя
                    <input type="text" name="engine" id="mf_engine" placeholder="Например: 2GR-FE">
                  </label>
                  <label>Объём, л
                    <input type="text" name="volume" id="mf_volume" placeholder="3.5">
                  </label>
                  <label>Топливо
                    <select name="fuel" id="mf_fuel"><option value="">—</option><?php foreach (vehFuels() as $f): ?><option value="<?= $h($f) ?>"><?= $h($f) ?></option><?php endforeach; ?></select>
                  </label>
                  <label>Кузов / поколение
                    <input type="text" name="body" id="mf_body" placeholder="XU30, GSU35">
                  </label>
                </div>
                <small style="color:#999;display:block;margin:2px 0 8px;">Код двигателя указан в техпаспорте или на двигателе. Можно оставить пустым.</small>
                <button class="az-btn az-btn-primary az-btn-sm" type="submit" id="modSubmit">+ Добавить модификацию</button>
                <button class="az-btn az-btn-outline az-btn-sm" type="button" id="modCancel" style="display:none;">Отмена</button>
                <p style="margin:8px 0 0;color:#888;font-size:.82rem;">Пример: Lexus RX350, 2006–2009, 2GR-FE, 3.5 л, бензин, кузов XU30.</p>
              </form>

              <ul class="veh-list" style="list-style:none;margin:12px 0 0;padding:0;max-height:360px;overflow:auto;">
                <?php foreach ($mods as $m): ?>
                <li class="veh-item">
                  <span class="veh-item-name"><?= $h(vehModLabel($m) ?: 'Модификация') ?></span>
                  <button type="button" class="az-btn az-btn-outline az-btn-sm veh-mod-edit"
                          data-id="<?= (int)$m['id'] ?>" data-yf="<?= (int)$m['year_from'] ?>" data-yt="<?= (int)$m['year_to'] ?>"
                          data-engine="<?= $h($m['engine']) ?>" data-volume="<?= $h($m['volume']) ?>" data-fuel="<?= $h($m['fuel']) ?>" data-body="<?= $h($m['body']) ?>">Изменить</button>
                  <form method="post" onsubmit="return confirm('Удалить модификацию?');" style="margin:0;">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="act" value="del_mod"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="make_id" value="<?= $makeId ?>"><input type="hidden" name="model_id" value="<?= $modelId ?>">
                    <button class="veh-del" type="submit" title="Удалить"><i class="fa fa-trash-o"></i></button>
                  </form>
                </li>
                <?php endforeach; ?>
                <?php if (!$mods): ?><li style="color:#999;">Модификаций нет. Товар можно привязать к модели целиком.</li><?php endif; ?>
              </ul>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
<script>
// Инструкция — сворачивание с запоминанием.
(function(){
  var card=document.getElementById('vehHowto'), body=document.getElementById('vehHowtoBody'),
      btn=document.getElementById('vehHowtoBtn'), head=document.getElementById('vehHowtoToggle');
  if(!card)return;
  function set(open){ body.style.display=open?'':'none'; btn.textContent=open?'свернуть':'развернуть'; try{localStorage.setItem('vehHowto',open?'1':'0');}catch(e){} }
  var saved; try{saved=localStorage.getItem('vehHowto');}catch(e){}
  set(saved!=='0');
  head.addEventListener('click',function(){ set(body.style.display==='none'); });
})();
// Поиск по маркам.
(function(){
  var s=document.getElementById('makeSearch'); if(!s)return;
  s.addEventListener('input',function(){
    var q=s.value.trim().toLowerCase();
    document.querySelectorAll('#makeList .veh-item').forEach(function(li){
      li.style.display=(!q||(li.getAttribute('data-name')||'').indexOf(q)>=0)?'':'none';
    });
  });
})();
// Модификации — «Изменить»: заполнить форму и переключить на обновление.
(function(){
  var f=document.getElementById('modForm'); if(!f)return;
  var cancel=document.getElementById('modCancel'), submit=document.getElementById('modSubmit');
  function reset(){ document.getElementById('modAct').value='add_mod'; document.getElementById('modEditId').value='';
    submit.textContent='+ Добавить модификацию'; cancel.style.display='none'; f.reset();
    document.getElementById('modForm').querySelector('[name=model_id]').value='<?= $modelId ?>'; }
  document.querySelectorAll('.veh-mod-edit').forEach(function(b){
    b.addEventListener('click',function(){
      document.getElementById('modAct').value='edit_mod';
      document.getElementById('modEditId').value=b.dataset.id;
      document.getElementById('mf_year_from').value=b.dataset.yf||'';
      document.getElementById('mf_year_to').value=b.dataset.yt||'';
      document.getElementById('mf_engine').value=b.dataset.engine||'';
      document.getElementById('mf_volume').value=b.dataset.volume||'';
      document.getElementById('mf_fuel').value=b.dataset.fuel||'';
      document.getElementById('mf_body').value=b.dataset.body||'';
      submit.textContent='Сохранить изменения'; cancel.style.display='inline-flex';
      f.scrollIntoView({behavior:'smooth',block:'center'});
    });
  });
  cancel.addEventListener('click',reset);
})();
</script>
<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
