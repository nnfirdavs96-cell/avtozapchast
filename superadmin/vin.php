<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['admin', 'superadmin']);
require_once dirname(__DIR__) . '/includes/vin_service.php';

$db     = getDB();
$csrf   = generateCsrfToken();
$role   = $_SESSION['role'] ?? '';
$action = $_GET['action'] ?? 'settings';
$editId = (int)($_GET['id'] ?? 0);
$errors = [];

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'Ошибка CSRF.'); redirect(APP_URL . '/superadmin/vin.php');
    }

    $postAction = $_POST['action'] ?? '';

    // Save VIN API settings (superadmin only)
    if ($postAction === 'save_settings' && $role === 'superadmin') {
        $fields = ['vin_search_enabled','vin_api_provider','vin_api_url','vin_api_key','vin_api_timeout'];
        foreach ($fields as $key) {
            $val = trim($_POST[$key] ?? '');
            $db->prepare("INSERT INTO site_settings (`key`,`value`) VALUES(?,?) ON DUPLICATE KEY UPDATE `value`=?,updated_at=NOW()")
               ->execute([$key, $val, $val]);
        }
        flashMessage('success', 'Настройки VIN сохранены.');
        redirect(APP_URL . '/superadmin/vin.php?action=settings');
    }

    // Clear cache (superadmin only)
    if ($postAction === 'clear_cache' && $role === 'superadmin') {
        VinService::clearCache();
        flashMessage('success', 'Кэш VIN очищен.');
        redirect(APP_URL . '/superadmin/vin.php?action=settings');
    }

    // Save car model
    if ($postAction === 'save_model') {
        $make     = trim($_POST['make'] ?? '');
        $model    = trim($_POST['model'] ?? '');
        $yearFrom = ($_POST['year_from'] ?? '') !== '' ? (int)$_POST['year_from'] : null;
        $yearTo   = ($_POST['year_to'] ?? '') !== '' ? (int)$_POST['year_to'] : null;
        $engine   = trim($_POST['engine'] ?? '') ?: null;
        $body     = trim($_POST['body_type'] ?? '') ?: null;
        $region   = $_POST['region'] ?? 'other';
        $mid      = (int)($_POST['id'] ?? 0);

        if (!$make)  $errors[] = 'Укажите марку.';
        if (!$model) $errors[] = 'Укажите модель.';

        if (empty($errors)) {
            if ($mid) {
                $db->prepare("UPDATE car_models SET make=?,model=?,year_from=?,year_to=?,engine=?,body_type=?,region=? WHERE id=?")
                   ->execute([$make, $model, $yearFrom, $yearTo, $engine, $body, $region, $mid]);
                flashMessage('success', 'Автомобиль обновлён.');
            } else {
                $db->prepare("INSERT INTO car_models (make,model,year_from,year_to,engine,body_type,region) VALUES (?,?,?,?,?,?,?)")
                   ->execute([$make, $model, $yearFrom, $yearTo, $engine, $body, $region]);
                flashMessage('success', 'Автомобиль добавлен.');
            }
            redirect(APP_URL . '/superadmin/vin.php?action=models');
        }
        $action = $mid ? 'edit_model' : 'new_model';
        $editId = $mid;
    }

    // Delete car model
    if ($postAction === 'delete_model') {
        $did = (int)($_POST['id'] ?? 0);
        if ($did) {
            $db->prepare("UPDATE car_models SET is_active=0 WHERE id=?")->execute([$did]);
            flashMessage('success', 'Автомобиль удалён.');
        }
        redirect(APP_URL . '/superadmin/vin.php?action=models');
    }

    // Save parts compatibility
    if ($postAction === 'save_compat') {
        $partId   = (int)($_POST['part_id'] ?? 0);
        $modelIds = array_map('intval', (array)($_POST['model_ids'] ?? []));
        if ($partId && $modelIds) {
            foreach ($modelIds as $mId) {
                $db->prepare("INSERT IGNORE INTO parts_compatibility (part_id,car_model_id) VALUES (?,?)")
                   ->execute([$partId, $mId]);
            }
            flashMessage('success', 'Совместимость сохранена.');
        }
        redirect(APP_URL . '/superadmin/vin.php?action=compat');
    }

    // Delete compat row
    if ($postAction === 'delete_compat') {
        $cid = (int)($_POST['id'] ?? 0);
        if ($cid) $db->prepare("DELETE FROM parts_compatibility WHERE id=?")->execute([$cid]);
        redirect(APP_URL . '/superadmin/vin.php?action=compat');
    }
}

// ── Load data ─────────────────────────────────────────────────────────────────
$settingsRaw = $db->query("SELECT `key`,`value` FROM site_settings WHERE `key` LIKE 'vin_%'")->fetchAll();
$settings = [];
foreach ($settingsRaw as $r) $settings[$r['key']] = $r['value'];

$stats = VinService::getStats();

function sv2(array $s, string $k, string $d = ''): string {
    return htmlspecialchars($s[$k] ?? $d, ENT_QUOTES, 'UTF-8');
}

$editCar = null;
if ($editId && $action === 'edit_model') {
    try {
        $stmt = $db->prepare("SELECT * FROM car_models WHERE id=? LIMIT 1");
        $stmt->execute([$editId]);
        $editCar = $stmt->fetch();
    } catch (Exception $e) {}
}

// Models list (paginated)
$modPage   = max(1,(int)($_GET['p'] ?? 1));
$modPer    = 25;
$modSearch = trim($_GET['s'] ?? '');
$modWhere  = ['cm.is_active=1'];
$modParams = [];
if ($modSearch) {
    $modWhere[]  = '(cm.make LIKE ? OR cm.model LIKE ?)';
    $modParams[] = "%$modSearch%"; $modParams[] = "%$modSearch%";
}
$modWhereSQL = 'WHERE ' . implode(' AND ', $modWhere);
$modTotal  = 0;
$models    = [];
$migrationMissing = false;
try {
    $cnt = $db->prepare("SELECT COUNT(*) FROM car_models cm $modWhereSQL");
    $cnt->execute($modParams);
    $modTotal = (int)$cnt->fetchColumn();
    $modOffset = (max(1,$modPage) - 1) * $modPer;
    $modStmt = $db->prepare("SELECT cm.*, (SELECT COUNT(*) FROM parts_compatibility pc WHERE pc.car_model_id=cm.id) AS compat_count FROM car_models cm $modWhereSQL ORDER BY cm.make,cm.model LIMIT $modPer OFFSET $modOffset");
    $modStmt->execute($modParams);
    $models = $modStmt->fetchAll();
} catch (Exception $e) {
    $migrationMissing = true;
}
$modPages = max(1,(int)ceil($modTotal / $modPer));

// Compat list
$compatList = [];
if ($action === 'compat') {
    try {
        $compatList = $db->query(
            "SELECT pc.id, p.name AS part_name, p.part_number,
                    cm.make, cm.model, cm.year_from, cm.year_to
             FROM parts_compatibility pc
             JOIN parts p ON p.id = pc.part_id
             JOIN car_models cm ON cm.id = pc.car_model_id
             ORDER BY cm.make, cm.model LIMIT 100"
        )->fetchAll();
    } catch (Exception $e) {
        $migrationMissing = true;
    }
}

// Test VIN (AJAX-like via GET)
$testResult = null;
if ($action === 'settings' && isset($_GET['test_vin'])) {
    $testVin = strtoupper(trim($_GET['test_vin']));
    if (VinService::validate($testVin)) {
        $testResult = VinService::decode($testVin);
    } else {
        $testResult = ['error' => 'Неверный VIN'];
    }
}

$pageTitle = 'VIN-поиск — Администрирование';
require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="az-panel">
<?php renderRoleSidebar('vin'); ?>

<main class="az-main">
    <div class="az-topbar">
        <h1><i class="fa fa-search"></i> VIN-поиск — управление</h1>
        <div>
            <a href="<?= APP_URL ?>/pages/vin.php" target="_blank" class="az-btn az-btn-secondary az-btn-sm">
                <i class="fa fa-external-link"></i> Страница VIN
            </a>
        </div>
    </div>

    <!-- Tabs -->
    <div style="border-bottom:1px solid #dee2e6;background:#fff;padding:0 24px;">
        <?php
        $tabs = [
            'settings' => ['fa-cog',    'Настройки API'],
            'models'   => ['fa-car',    "Автомобили ({$stats['models_total']})"],
            'compat'   => ['fa-link',   "Совместимость ({$stats['compat_total']})"],
        ];
        foreach ($tabs as $tab => [$icon, $label]):
        ?>
        <a href="?action=<?= $tab ?>"
           style="display:inline-block;padding:14px 20px;font-size:0.875rem;font-weight:600;border-bottom:3px solid <?= $action===$tab?'#d32f2f':'transparent' ?>;color:<?= $action===$tab?'#d32f2f':'#666' ?>;text-decoration:none;transition:color 0.2s;">
            <i class="fa fa-<?= $icon ?>"></i> <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="az-content">

    <?php if ($flash = getFlashMessage()): ?>
        <div class="az-alert az-alert-<?= sanitize($flash['type']) ?>"><?= sanitize($flash['message']) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="az-alert az-alert-danger"><?php foreach ($errors as $e) echo '<div>' . sanitize($e) . '</div>'; ?></div>
    <?php endif; ?>
    <?php if (!empty($migrationMissing)): ?>
        <div class="az-alert az-alert-warning" style="background:#fff3cd;border:1px solid #ffc107;color:#856404;">
            <strong><i class="fa fa-exclamation-triangle"></i> Миграция БД ещё не запущена.</strong><br>
            На сервере выполните: <code>mysql -u avtouser -p avtozapchast &lt; sql/migrate_vin.sql</code>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <?php if ($action === 'settings'): ?>
    <!-- ── Settings tab ───────────────────────────────────────────────── -->

    <!-- Stats row -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;">
        <?php
        $statCards = [
            ['fa-database',    'Кэш VIN',          $stats['cache_total'] . ' запросов'],
            ['fa-car',         'Автомобилей',       $stats['models_total'] . ' моделей'],
            ['fa-link',        'Совместимостей',    $stats['compat_total'] . ' связей'],
        ];
        foreach ($statCards as [$icon, $title, $val]):
        ?>
        <div class="az-card" style="text-align:center;padding:20px;">
            <i class="fa fa-<?= $icon ?>" style="font-size:1.8rem;color:#d32f2f;margin-bottom:8px;display:block;"></i>
            <div style="font-size:1.4rem;font-weight:800;color:#1a1a2e;"><?= $val ?></div>
            <div style="font-size:0.8rem;color:#aaa;"><?= $title ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($role === 'superadmin'): ?>
    <!-- API Settings form -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">
        <div class="az-card">
            <h3>Настройки VIN API</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                <input type="hidden" name="action" value="save_settings">

                <div class="az-form-group">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="vin_search_enabled" value="1"
                               <?= ($settings['vin_search_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                        Включить VIN-поиск на сайте
                    </label>
                </div>

                <div class="az-form-group">
                    <label>Провайдер API</label>
                    <select name="vin_api_provider" id="providerSelect" onchange="toggleCustom()">
                        <option value="nhtsa"  <?= ($settings['vin_api_provider']??'nhtsa')==='nhtsa' ?'selected':'' ?>>
                            NHTSA (бесплатный, без ключа) — США/Япония/Европа
                        </option>
                        <option value="custom" <?= ($settings['vin_api_provider']??'')==='custom'?'selected':'' ?>>
                            Платный / собственный API
                        </option>
                    </select>
                </div>

                <div id="customFields" style="display:<?= ($settings['vin_api_provider']??'nhtsa')==='custom'?'block':'none' ?>;">
                    <div class="az-form-group">
                        <label>URL платного API <small style="color:#888;">(используйте {VIN} как плейсхолдер)</small></label>
                        <input type="text" name="vin_api_url" value="<?= sv2($settings,'vin_api_url') ?>"
                               placeholder="https://api.vinprovider.com/decode/{VIN}">
                    </div>
                    <div class="az-form-group">
                        <label>API ключ</label>
                        <input type="text" name="vin_api_key" value="<?= sv2($settings,'vin_api_key') ?>"
                               placeholder="sk_live_...">
                    </div>
                </div>

                <div class="az-form-group">
                    <label>Таймаут запроса (сек)</label>
                    <input type="number" name="vin_api_timeout" min="2" max="30"
                           value="<?= sv2($settings,'vin_api_timeout','8') ?>">
                </div>

                <div style="display:flex;gap:10px;">
                    <button type="submit" class="az-btn az-btn-primary">
                        <i class="fa fa-save"></i> Сохранить
                    </button>
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                        <input type="hidden" name="action" value="clear_cache">
                        <button type="submit" class="az-btn az-btn-secondary"
                                onclick="return confirm('Очистить кэш VIN-запросов?')">
                            <i class="fa fa-trash-o"></i> Очистить кэш
                        </button>
                    </form>
                </div>
            </form>
        </div>

        <!-- Test VIN -->
        <div class="az-card">
            <h3>Тестировать VIN</h3>
            <p style="color:#888;font-size:0.85rem;margin-bottom:16px;">
                Введите VIN для проверки работы текущего API-провайдера.
            </p>
            <form method="GET">
                <input type="hidden" name="action" value="settings">
                <div style="display:flex;gap:8px;">
                    <input type="text" name="test_vin" maxlength="17"
                           value="<?= sanitize($_GET['test_vin'] ?? '') ?>"
                           placeholder="WBAWX31060PK42218"
                           style="flex:1;font-family:monospace;text-transform:uppercase;padding:8px 12px;border:1px solid #ced4da;border-radius:6px;font-size:0.875rem;outline:none;">
                    <button type="submit" class="az-btn az-btn-primary az-btn-sm">
                        <i class="fa fa-search"></i> Проверить
                    </button>
                </div>
            </form>

            <?php if ($testResult): ?>
            <div style="margin-top:16px;background:#f8f9fa;border-radius:8px;padding:14px;font-size:0.85rem;">
                <?php if (isset($testResult['error'])): ?>
                    <span style="color:#d32f2f;"><?= sanitize($testResult['error']) ?></span>
                <?php else: ?>
                    <?php foreach ([
                        'vin'=>'VIN','make'=>'Марка','model'=>'Модель',
                        'year'=>'Год','country'=>'Страна','body_type'=>'Кузов',
                        'engine'=>'Двигатель','fuel_type'=>'Топливо','source'=>'Источник',
                    ] as $k=>$label):
                        $v = $testResult[$k] ?? '';
                        if (!$v) continue;
                    ?>
                    <div style="display:flex;gap:8px;padding:3px 0;border-bottom:1px solid #eee;">
                        <span style="color:#aaa;min-width:100px;"><?= $label ?>:</span>
                        <strong><?= sanitize((string)$v) ?></strong>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="az-card" style="margin-top:0;background:#fff8e1;">
        <h4 style="margin:0 0 8px;color:#795548;"><i class="fa fa-info-circle"></i> Смена API-провайдера</h4>
        <p style="margin:0;font-size:0.875rem;color:#6d4c41;line-height:1.6;">
            Сейчас используется <strong>NHTSA</strong> — бесплатный US-сервис без ключа. Хорошо работает для Toyota, Honda, Nissan, BMW, Mercedes, VW, Audi, Renault и других крупных марок.
            Для российских автомобилей (Lada, GAZ, UAZ) данные берутся из локальной базы WMI.<br>
            Когда приобретёте платный API — укажите URL и ключ выше, переключите провайдер на «Платный».
        </p>
    </div>
    <?php else: ?>
    <div class="az-card">
        <p style="color:#888;">Настройки API доступны только суперадминистратору.</p>
    </div>
    <?php endif; // superadmin ?>

    <!-- ================================================================ -->
    <?php elseif ($action === 'models' || $action === 'edit_model' || $action === 'new_model'): ?>
    <!-- ── Car models tab ─────────────────────────────────────────────── -->

    <?php if ($action === 'edit_model' || $action === 'new_model'): ?>
    <!-- Form -->
    <div style="max-width:600px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
            <h2 style="margin:0;font-size:1.05rem;"><?= $action==='edit_model'?'Редактировать автомобиль':'Новый автомобиль' ?></h2>
            <a href="?action=models" class="az-btn az-btn-secondary az-btn-sm">← Список</a>
        </div>
        <div class="az-card">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                <input type="hidden" name="action" value="save_model">
                <?php if ($editCar): ?><input type="hidden" name="id" value="<?= (int)$editCar['id'] ?>"><?php endif; ?>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div class="az-form-group">
                        <label>Марка *</label>
                        <input type="text" name="make" required
                               value="<?= sanitize($editCar['make'] ?? ($_POST['make'] ?? '')) ?>"
                               placeholder="Toyota, BMW, Lada">
                    </div>
                    <div class="az-form-group">
                        <label>Модель *</label>
                        <input type="text" name="model" required
                               value="<?= sanitize($editCar['model'] ?? ($_POST['model'] ?? '')) ?>"
                               placeholder="Camry, 3 Series, Priora">
                    </div>
                    <div class="az-form-group">
                        <label>Год от</label>
                        <input type="number" name="year_from" min="1900" max="2100"
                               value="<?= (int)($editCar['year_from'] ?? ($_POST['year_from'] ?? '')) ?: '' ?>">
                    </div>
                    <div class="az-form-group">
                        <label>Год до <small style="color:#aaa;">(пусто = по сей день)</small></label>
                        <input type="number" name="year_to" min="1900" max="2100"
                               value="<?= (int)($editCar['year_to'] ?? ($_POST['year_to'] ?? '')) ?: '' ?>">
                    </div>
                    <div class="az-form-group">
                        <label>Двигатель</label>
                        <input type="text" name="engine"
                               value="<?= sanitize($editCar['engine'] ?? ($_POST['engine'] ?? '')) ?>"
                               placeholder="2.0L 4-цил.">
                    </div>
                    <div class="az-form-group">
                        <label>Тип кузова</label>
                        <input type="text" name="body_type"
                               value="<?= sanitize($editCar['body_type'] ?? ($_POST['body_type'] ?? '')) ?>"
                               placeholder="Седан, SUV, Хэтчбек">
                    </div>
                    <div class="az-form-group" style="grid-column:1/-1;">
                        <label>Регион</label>
                        <select name="region">
                            <?php foreach (['ru'=>'Россия/СНГ','jp'=>'Япония','eu'=>'Европа','us'=>'США','other'=>'Другой'] as $v=>$l): ?>
                            <option value="<?= $v ?>" <?= ($editCar['region']??$_POST['region']??'other')===$v?'selected':'' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display:flex;gap:10px;">
                    <button type="submit" class="az-btn az-btn-primary">
                        <i class="fa fa-save"></i> <?= $action==='edit_model'?'Сохранить':'Добавить' ?>
                    </button>
                    <a href="?action=models" class="az-btn az-btn-secondary">Отмена</a>
                </div>
            </form>
        </div>
    </div>

    <?php else: ?>
    <!-- List -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
        <h2 style="margin:0;font-size:1.05rem;">Автомобили <span style="color:#aaa;font-weight:400;"><?= $modTotal ?></span></h2>
        <a href="?action=new_model" class="az-btn az-btn-primary az-btn-sm">
            <i class="fa fa-plus"></i> Добавить авто
        </a>
    </div>

    <div class="az-card" style="padding:12px 20px;margin-bottom:16px;">
        <form method="GET" style="display:flex;gap:8px;">
            <input type="hidden" name="action" value="models">
            <input type="text" name="s" value="<?= sanitize($modSearch) ?>"
                   placeholder="Марка или модель..."
                   style="flex:1;padding:8px 12px;border:1px solid #ced4da;border-radius:6px;font-size:0.875rem;outline:none;">
            <button type="submit" class="az-btn az-btn-primary az-btn-sm"><i class="fa fa-search"></i></button>
            <?php if ($modSearch): ?>
            <a href="?action=models" class="az-btn az-btn-secondary az-btn-sm">Сброс</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="az-card" style="padding:0;overflow:hidden;">
        <div style="overflow-x:auto;">
            <table class="az-table">
                <thead>
                    <tr>
                        <th>Марка</th>
                        <th>Модель</th>
                        <th>Годы</th>
                        <th>Двигатель</th>
                        <th>Регион</th>
                        <th style="text-align:center;">Запчастей</th>
                        <th style="text-align:center;">Действия</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="7" style="text-align:center;color:#aaa;padding:28px;">Нет автомобилей.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $m):
                        $regionLabels = ['ru'=>'🇷🇺 Россия','jp'=>'🇯🇵 Япония','eu'=>'🇪🇺 Европа','us'=>'🇺🇸 США','other'=>'🌐'];
                    ?>
                    <tr>
                        <td><strong><?= sanitize($m['make']) ?></strong></td>
                        <td><?= sanitize($m['model']) ?></td>
                        <td style="color:#888;font-size:0.8rem;">
                            <?= $m['year_from'] ? (int)$m['year_from'] : '?' ?> –
                            <?= $m['year_to']   ? (int)$m['year_to']   : 'н.в.' ?>
                        </td>
                        <td style="color:#888;font-size:0.8rem;"><?= sanitize($m['engine'] ?? '—') ?></td>
                        <td style="font-size:0.8rem;"><?= $regionLabels[$m['region']] ?? $m['region'] ?></td>
                        <td style="text-align:center;font-weight:700;color:<?= $m['compat_count']>0?'#28a745':'#aaa' ?>;">
                            <?= (int)$m['compat_count'] ?>
                        </td>
                        <td style="text-align:center;white-space:nowrap;">
                            <a href="?action=edit_model&id=<?= (int)$m['id'] ?>" class="az-btn az-btn-secondary az-btn-sm">
                                <i class="fa fa-pencil"></i>
                            </a>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('Удалить <?= sanitize(addslashes($m['make'].' '.$m['model'])) ?>?')">
                                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                <input type="hidden" name="action" value="delete_model">
                                <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                <button type="submit" class="az-btn az-btn-danger az-btn-sm"><i class="fa fa-trash-o"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($modPages > 1): ?>
    <div style="display:flex;justify-content:center;margin-top:16px;">
        <ul class="pagination">
            <?php for ($pg=1;$pg<=$modPages;$pg++): ?>
            <li><a href="?action=models&p=<?=$pg?>&s=<?=urlencode($modSearch)?>" class="page-link <?=$pg===$modPage?'active':''?>"><?=$pg?></a></li>
            <?php endfor; ?>
        </ul>
    </div>
    <?php endif; ?>
    <?php endif; // new/edit vs list ?>

    <!-- ================================================================ -->
    <?php elseif ($action === 'compat'): ?>
    <!-- ── Compatibility tab ─────────────────────────────────────────── -->

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">

        <!-- Add compatibility -->
        <div class="az-card">
            <h3>Привязать запчасть к автомобилю</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                <input type="hidden" name="action" value="save_compat">

                <div class="az-form-group">
                    <label>Запчасть</label>
                    <select name="part_id" style="width:100%;">
                        <option value="">— Выберите запчасть —</option>
                        <?php
                        $allParts = [];
                        try { $allParts = $db->query("SELECT id,part_number,name FROM parts WHERE is_active=1 ORDER BY name LIMIT 500")->fetchAll(); } catch (Exception $e) {}
                        foreach ($allParts as $pt):
                        ?>
                        <option value="<?= (int)$pt['id'] ?>">
                            [<?= sanitize($pt['part_number']) ?>] <?= sanitize(truncate($pt['name'], 50)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="az-form-group">
                    <label>Автомобили <small style="color:#aaa;">(удерживайте Ctrl для выбора нескольких)</small></label>
                    <select name="model_ids[]" multiple size="8" style="width:100%;">
                        <?php
                        $allModels = [];
                        try { $allModels = $db->query("SELECT id,make,model,year_from,year_to FROM car_models WHERE is_active=1 ORDER BY make,model")->fetchAll(); } catch (Exception $e) {}
                        foreach ($allModels as $cm):
                            $yr = ($cm['year_from'] ? $cm['year_from'] : '?') . '–' . ($cm['year_to'] ? $cm['year_to'] : 'н.в.');
                        ?>
                        <option value="<?= (int)$cm['id'] ?>">
                            <?= sanitize($cm['make']) ?> <?= sanitize($cm['model']) ?> (<?= $yr ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="az-btn az-btn-primary">
                    <i class="fa fa-link"></i> Добавить совместимость
                </button>
            </form>
        </div>

        <!-- Current compat list -->
        <div class="az-card" style="padding:0;overflow:hidden;">
            <div style="padding:16px 20px;border-bottom:1px solid #dee2e6;font-weight:700;">
                Привязки (<?= count($compatList) ?>)
            </div>
            <div style="overflow-y:auto;max-height:420px;">
                <table class="az-table" style="font-size:0.8rem;">
                    <thead><tr>
                        <th>Запчасть</th>
                        <th>Автомобиль</th>
                        <th style="width:40px;"></th>
                    </tr></thead>
                    <tbody>
                    <?php if (empty($compatList)): ?>
                        <tr><td colspan="3" style="text-align:center;color:#aaa;padding:20px;">Нет привязок.</td></tr>
                    <?php else: ?>
                        <?php foreach ($compatList as $cl): ?>
                        <tr>
                            <td>
                                <code style="font-size:0.75rem;"><?= sanitize($cl['part_number']) ?></code><br>
                                <span style="color:#666;"><?= sanitize(truncate($cl['part_name'],35)) ?></span>
                            </td>
                            <td>
                                <?= sanitize($cl['make']) ?> <?= sanitize($cl['model']) ?><br>
                                <span style="color:#aaa;"><?= $cl['year_from'] ? (int)$cl['year_from'] : '?' ?>–<?= $cl['year_to'] ? (int)$cl['year_to'] : 'н.в.' ?></span>
                            </td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="action" value="delete_compat">
                                    <input type="hidden" name="id" value="<?= (int)$cl['id'] ?>">
                                    <button type="submit" class="az-btn az-btn-danger az-btn-sm" title="Удалить">×</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <?php endif; // action tabs ?>

    </div><!-- /.az-content -->
</main>
</div><!-- /.az-panel -->

<script>
function toggleCustom() {
    const v = document.getElementById('providerSelect');
    if (v) document.getElementById('customFields').style.display = v.value === 'custom' ? 'block' : 'none';
}
</script>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
