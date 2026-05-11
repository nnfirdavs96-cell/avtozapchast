<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole('superadmin');

$db   = getDB();
$csrf = generateCsrfToken();

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF ошибка.'); redirect(APP_URL . '/superadmin/currencies.php');
    }

    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'update_rate') {
        $code = trim($_POST['code'] ?? '');
        $rate = (float)($_POST['rate'] ?? 1);
        if ($code && $rate > 0) {
            $db->prepare("UPDATE currencies SET rate = ?, updated_at = NOW() WHERE code = ?")->execute([$rate, $code]);
            flashMessage('success', "Курс {$code} обновлён.");
        }
        redirect(APP_URL . '/superadmin/currencies.php');
    }

    if ($postAction === 'toggle_active') {
        $code   = trim($_POST['code'] ?? '');
        $active = (int)($_POST['is_active'] ?? 0);
        if ($code) {
            // Prevent deactivating default
            $row = $db->prepare("SELECT is_default FROM currencies WHERE code = ?");
            $row->execute([$code]);
            $cur = $row->fetch();
            if ($cur && $cur['is_default'] && !$active) {
                flashMessage('danger', 'Нельзя деактивировать валюту по умолчанию.');
            } else {
                $db->prepare("UPDATE currencies SET is_active = ?, updated_at = NOW() WHERE code = ?")->execute([$active, $code]);
                flashMessage('success', 'Статус валюты обновлён.');
            }
        }
        redirect(APP_URL . '/superadmin/currencies.php');
    }

    if ($postAction === 'set_default') {
        $code = trim($_POST['code'] ?? '');
        if ($code) {
            $db->exec("UPDATE currencies SET is_default = 0");
            $db->prepare("UPDATE currencies SET is_default = 1, is_active = 1, updated_at = NOW() WHERE code = ?")->execute([$code]);
            // Update site_settings too
            $db->prepare("INSERT INTO site_settings (`key`, `value`) VALUES ('default_currency', ?) ON DUPLICATE KEY UPDATE `value`=?, updated_at=NOW()")
               ->execute([$code, $code]);
            flashMessage('success', "Валюта {$code} установлена по умолчанию.");
        }
        redirect(APP_URL . '/superadmin/currencies.php');
    }

    if ($postAction === 'fetch_cbr') {
        $url  = 'https://www.cbr-xml-daily.ru/daily_json.js';
        $json = false;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10, CURLOPT_SSL_VERIFYPEER=>false]);
            $json = curl_exec($ch);
            curl_close($ch);
        } else {
            $json = @file_get_contents($url);
        }
        $data = $json ? json_decode($json, true) : null;
        if (!$data || empty($data['Valute'])) {
            flashMessage('danger', 'Не удалось получить курсы ЦБ РФ. Проверьте соединение.');
            redirect(APP_URL . '/superadmin/currencies.php');
        }
        $existing = $db->query("SELECT code FROM currencies WHERE code != 'RUB'")->fetchAll(PDO::FETCH_COLUMN);
        $updated = 0; $missed = [];
        foreach ($existing as $code) {
            $upper = strtoupper($code);
            if (isset($data['Valute'][$upper])) {
                $v = $data['Valute'][$upper];
                $rubPerUnit = (float)$v['Value'] / max(1, (int)$v['Nominal']);
                if ($rubPerUnit > 0) {
                    $rate = round(1 / $rubPerUnit, 8);
                    $db->prepare("UPDATE currencies SET rate=?, updated_at=NOW() WHERE code=?")->execute([$rate, $code]);
                    $updated++;
                }
            } else { $missed[] = $code; }
        }
        $msg = "Обновлено по ЦБ РФ: {$updated} валют.";
        if ($missed) $msg .= ' Не найдено в ЦБ: ' . implode(', ', $missed) . '.';
        flashMessage('success', $msg);
        redirect(APP_URL . '/superadmin/currencies.php');
    }

    if ($postAction === 'bulk_update') {
        $codes = $_POST['rate_code'] ?? [];
        $rates = $_POST['rate_val']  ?? [];
        $count = 0;
        foreach ($codes as $i => $code) {
            $code = trim($code);
            $rate = (float)($rates[$i] ?? 0);
            if ($code && $rate > 0) {
                $db->prepare("UPDATE currencies SET rate = ?, updated_at = NOW() WHERE code = ?")->execute([$rate, $code]);
                $count++;
            }
        }
        flashMessage('success', "Обновлено курсов: $count.");
        redirect(APP_URL . '/superadmin/currencies.php');
    }

    redirect(APP_URL . '/superadmin/currencies.php');
}

// ── Load currencies ────────────────────────────────────────────────────────────
$currencies = [];
try {
    $currencies = $db->query(
        "SELECT * FROM currencies ORDER BY is_default DESC, is_active DESC, code ASC"
    )->fetchAll();
} catch (Exception $e) {
    flashMessage('danger', 'Ошибка загрузки валют: ' . $e->getMessage());
}

$pageTitle = 'Валюты — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="az-panel">
  <aside class="az-sidebar" style="background:#1a0533;">
    <div class="az-sidebar-brand" style="background:rgba(155,89,182,0.3);border-bottom-color:rgba(155,89,182,0.3);">
      <span style="color:#ce93d8;">&#x2605;</span> Суперадмин
    </div>
    <nav class="az-sidebar-nav">
      <a href="<?= APP_URL ?>/superadmin/index.php" class="az-sidebar-link"><i class="fa fa-star"></i> Панель</a>
      <a href="<?= APP_URL ?>/superadmin/users.php" class="az-sidebar-link"><i class="fa fa-users"></i> Пользователи</a>
      <a href="<?= APP_URL ?>/admin/orders.php" class="az-sidebar-link"><i class="fa fa-shopping-bag"></i> Заказы</a>
      <a href="<?= APP_URL ?>/admin/products.php" class="az-sidebar-link"><i class="fa fa-cogs"></i> Товары</a>
      <a href="<?= APP_URL ?>/admin/sliders.php" class="az-sidebar-link"><i class="fa fa-picture-o"></i> Слайдер</a>
      <a href="<?= APP_URL ?>/superadmin/settings.php" class="az-sidebar-link"><i class="fa fa-cog"></i> Настройки</a>
      <a href="<?= APP_URL ?>/superadmin/currencies.php" class="az-sidebar-link active" style="color:#ce93d8;"><i class="fa fa-money"></i> Валюты</a>
      <a href="<?= APP_URL ?>/superadmin/languages.php" class="az-sidebar-link"><i class="fa fa-language"></i> Языки</a>
      <a href="<?= APP_URL ?>/superadmin/warehouse.php" class="az-sidebar-link"><i class="fa fa-database"></i> Склад API</a>
      <a href="<?= APP_URL ?>/superadmin/blog.php" class="az-sidebar-link"><i class="fa fa-newspaper-o"></i> Блог</a>
      <a href="<?= APP_URL ?>/superadmin/backup.php" class="az-sidebar-link"><i class="fa fa-archive"></i> Бэкапы</a>
      <hr style="border-color:rgba(255,255,255,0.1);margin:12px 0;">
      <a href="<?= APP_URL ?>/index.php" class="az-sidebar-link"><i class="fa fa-home"></i> На сайт</a>
      <a href="<?= APP_URL ?>/auth/logout.php" class="az-sidebar-link" style="color:rgba(255,100,100,0.85)!important;"><i class="fa fa-sign-out"></i> Выйти</a>
    </nav>
  </aside>

  <div class="az-main">
    <div class="az-topbar" style="border-bottom-color:#9b59b622;background:#f9f5ff;">
      <div class="az-topbar-title" style="color:#6a1b9a;">Управление валютами</div>
      <div class="az-topbar-user"><?= sanitize($_SESSION['username'] ?? 'Superadmin') ?> &middot; <a href="<?= APP_URL ?>/auth/logout.php">Выйти</a></div>
    </div>

    <div class="az-content">
      <?php if ($flash = getFlashMessage()): ?>
      <div class="alert alert-<?= sanitize($flash['type']) ?> mb-16"><?= sanitize($flash['message']) ?></div>
      <?php endif; ?>

      <!-- Bulk rate update -->
      <div class="az-card mb-24">
        <div class="az-card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
          <h4 class="az-card-title">Курсы валют <small style="font-weight:400;color:#888;font-size:0.8rem;">(база: RUB = 1.0)</small></h4>
          <form method="post" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="action" value="fetch_cbr">
            <button type="submit" class="az-btn az-btn-outline az-btn-sm" title="Загрузить актуальные курсы с сайта ЦБ РФ">
              <i class="fa fa-refresh"></i> Обновить по ЦБ РФ
            </button>
          </form>
        </div>
        <div class="az-card-body p-0">
          <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="action" value="bulk_update">
            <div class="table-responsive">
              <table class="az-table">
                <thead>
                  <tr>
                    <th>Код</th>
                    <th>Название (RU)</th>
                    <th>Название (TG)</th>
                    <th>Название (EN)</th>
                    <th>Символ</th>
                    <th style="width:150px;">Курс к RUB</th>
                    <th style="text-align:center;">По умолч.</th>
                    <th style="text-align:center;">Активна</th>
                    <th>Действия</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($currencies as $cur): ?>
                  <tr <?= $cur['is_default'] ? 'style="background:#f9f5ff;"' : '' ?>>
                    <td>
                      <strong><?= sanitize($cur['code']) ?></strong>
                      <input type="hidden" name="rate_code[]" value="<?= sanitize($cur['code']) ?>">
                    </td>
                    <td style="font-size:0.85rem;"><?= sanitize($cur['name_ru']) ?></td>
                    <td style="font-size:0.85rem;"><?= sanitize($cur['name_tg'] ?? '') ?></td>
                    <td style="font-size:0.85rem;"><?= sanitize($cur['name_en'] ?? '') ?></td>
                    <td style="font-size:1.1rem;font-weight:700;"><?= sanitize($cur['symbol']) ?></td>
                    <td>
                      <input type="number" name="rate_val[]" step="0.000001" min="0.000001"
                             value="<?= number_format((float)$cur['rate'], 6, '.', '') ?>"
                             class="form-control form-control-sm" style="font-family:monospace;">
                    </td>
                    <td style="text-align:center;">
                      <?php if ($cur['is_default']): ?>
                      <span class="badge badge-success">По умолч.</span>
                      <?php else: ?>
                      <form method="post" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                        <input type="hidden" name="action" value="set_default">
                        <input type="hidden" name="code" value="<?= sanitize($cur['code']) ?>">
                        <button type="submit" class="az-btn az-btn-outline az-btn-sm">Сделать</button>
                      </form>
                      <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                      <form method="post" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                        <input type="hidden" name="action" value="toggle_active">
                        <input type="hidden" name="code" value="<?= sanitize($cur['code']) ?>">
                        <input type="hidden" name="is_active" value="<?= $cur['is_active'] ? 0 : 1 ?>">
                        <button type="submit" class="az-btn az-btn-sm <?= $cur['is_active'] ? 'az-btn-danger' : 'az-btn-success' ?>">
                          <?= $cur['is_active'] ? 'Откл.' : 'Вкл.' ?>
                        </button>
                      </form>
                    </td>
                    <td>
                      <span class="badge badge-<?= $cur['is_active'] ? 'success' : 'danger' ?>">
                        <?= $cur['is_active'] ? 'Активна' : 'Откл.' ?>
                      </span>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($currencies)): ?>
                  <tr><td colspan="9" style="text-align:center;color:#999;padding:24px;">Валюты не найдены. Добавьте их в базу данных.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <?php if (!empty($currencies)): ?>
            <div style="padding:16px;">
              <button type="submit" class="az-btn az-btn-primary">Сохранить все курсы</button>
            </div>
            <?php endif; ?>
          </form>
        </div>
      </div>

      <!-- Info -->
      <div class="az-card" style="max-width:600px;border-left:3px solid #9b59b6;">
        <div class="az-card-body">
          <h5 style="color:#6a1b9a;margin-bottom:8px;">Справка по курсам</h5>
          <p style="font-size:0.85rem;color:#555;line-height:1.7;margin:0;">
            Все цены в базе данных хранятся в <strong>рублях (RUB)</strong>.<br>
            Курс — коэффициент перевода из RUB. Например: курс USD = 0.011 означает, что 1000 ₽ = 11 USD.<br>
            Только одна валюта может быть «по умолчанию». Она используется для новых посетителей.
          </p>
        </div>
      </div>

    </div><!-- /.az-content -->
  </div><!-- /.az-main -->
</div><!-- /.az-panel -->

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
