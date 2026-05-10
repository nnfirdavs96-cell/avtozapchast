<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole('superadmin');

$db   = getDB();
$csrf = generateCsrfToken();

$testResult = null;

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF ошибка.'); redirect(APP_URL . '/superadmin/warehouse.php');
    }

    $postAction = $_POST['action'] ?? '';

    // Save settings
    if ($postAction === 'save') {
        $fields = ['warehouse_api_url', 'warehouse_api_key'];
        foreach ($fields as $key) {
            $val = trim($_POST[$key] ?? '');
            $db->prepare("INSERT INTO site_settings (`key`, `value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=?, updated_at=NOW()")
               ->execute([$key, $val, $val]);
        }
        $enabled = isset($_POST['warehouse_api_enabled']) ? '1' : '0';
        $db->prepare("INSERT INTO site_settings (`key`, `value`) VALUES ('warehouse_api_enabled', ?) ON DUPLICATE KEY UPDATE `value`=?, updated_at=NOW()")
           ->execute([$enabled, $enabled]);
        flashMessage('success', 'Настройки склада сохранены.');
        redirect(APP_URL . '/superadmin/warehouse.php');
    }

    // Test connection
    if ($postAction === 'test') {
        $apiUrl    = getSetting('warehouse_api_url');
        $apiKey    = getSetting('warehouse_api_key');
        $startTime = microtime(true);
        $success   = false;
        $body      = '';
        $httpCode  = 0;
        $error     = '';

        if (!$apiUrl) {
            $error = 'URL склада API не настроен.';
        } else {
            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL            => $apiUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 10,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_HTTPHEADER     => array_values(array_filter([
                        'Accept: application/json',
                        $apiKey ? 'Authorization: Bearer ' . $apiKey : null,
                    ])),
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_FOLLOWLOCATION => true,
                ]);
                $response = curl_exec($ch);
                $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErr  = curl_error($ch);
                curl_close($ch);
                if ($curlErr) {
                    $error = 'cURL ошибка: ' . $curlErr;
                } elseif ($response !== false) {
                    $body    = (string)$response;
                    $success = true;
                }
            } else {
                $ctxOpts = ['http' => ['timeout' => 10, 'ignore_errors' => true,
                    'header' => "Accept: application/json\r\n" . ($apiKey ? "Authorization: Bearer $apiKey\r\n" : '')]];
                $ctx  = stream_context_create($ctxOpts);
                $resp = @file_get_contents($apiUrl, false, $ctx);
                if ($resp === false) {
                    $error = 'Не удалось подключиться к API.';
                } else {
                    $body     = $resp;
                    $success  = true;
                    $httpCode = 200;
                }
            }
        }

        $elapsed = round((microtime(true) - $startTime) * 1000);

        try {
            $db->prepare(
                "INSERT INTO warehouse_api_log (action, request_url, response_code, response_body, success, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())"
            )->execute(['test', $apiUrl, $httpCode, mb_substr($body, 0, 2000), $success ? 1 : 0]);
        } catch (Exception $e) {}

        $testResult = compact('success', 'body', 'httpCode', 'error', 'elapsed', 'apiUrl');
    }
}

// Load settings
$apiUrl     = getSetting('warehouse_api_url');
$apiKey     = getSetting('warehouse_api_key');
$apiEnabled = getSetting('warehouse_api_enabled') === '1';

// Load log
$logs = [];
try {
    $logs = $db->query("SELECT * FROM warehouse_api_log ORDER BY created_at DESC LIMIT 20")->fetchAll();
} catch (Exception $e) {}

$pageTitle = 'Склад API — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="az-panel">
  <aside class="az-sidebar" style="background:#1a0533;">
    <div class="az-sidebar-brand" style="background:rgba(155,89,182,0.3);border-bottom-color:rgba(155,89,182,0.3);">
      <span style="color:#ce93d8;">&#x2605;</span> Суперадмин
    </div>
    <nav class="az-sidebar-nav">
      <a href="<?= APP_URL ?>/superadmin/index.php" class="az-sidebar-link"><i class="fa fa-star"></i> Панель</a>
      <a href="<?= APP_URL ?>/admin/users.php" class="az-sidebar-link"><i class="fa fa-users"></i> Пользователи</a>
      <a href="<?= APP_URL ?>/admin/orders.php" class="az-sidebar-link"><i class="fa fa-shopping-bag"></i> Заказы</a>
      <a href="<?= APP_URL ?>/superadmin/settings.php" class="az-sidebar-link"><i class="fa fa-cog"></i> Настройки</a>
      <a href="<?= APP_URL ?>/superadmin/currencies.php" class="az-sidebar-link"><i class="fa fa-money"></i> Валюты</a>
      <a href="<?= APP_URL ?>/superadmin/languages.php" class="az-sidebar-link"><i class="fa fa-language"></i> Языки</a>
      <a href="<?= APP_URL ?>/superadmin/warehouse.php" class="az-sidebar-link active" style="color:#ce93d8;"><i class="fa fa-database"></i> Склад API</a>
      <a href="<?= APP_URL ?>/superadmin/blog.php" class="az-sidebar-link"><i class="fa fa-newspaper-o"></i> Блог</a>
      <a href="<?= APP_URL ?>/superadmin/backup.php" class="az-sidebar-link"><i class="fa fa-archive"></i> Бэкапы</a>
      <hr style="border-color:rgba(255,255,255,0.1);margin:12px 0;">
      <a href="<?= APP_URL ?>/index.php" class="az-sidebar-link"><i class="fa fa-home"></i> На сайт</a>
    </nav>
  </aside>

  <div class="az-main">
    <div class="az-topbar" style="border-bottom-color:#9b59b622;background:#f9f5ff;">
      <div class="az-topbar-title" style="color:#6a1b9a;">Московский Склад API</div>
      <div class="az-topbar-user"><?= sanitize($_SESSION['username'] ?? 'Superadmin') ?> &middot; <a href="<?= APP_URL ?>/auth/logout.php">Выйти</a></div>
    </div>

    <div class="az-content">
      <?php if ($flash = getFlashMessage()): ?>
      <div class="alert alert-<?= sanitize($flash['type']) ?> mb-16"><?= sanitize($flash['message']) ?></div>
      <?php endif; ?>

      <div class="row">
        <!-- Settings form -->
        <div class="col-lg-6 mb-24">
          <div class="az-card">
            <div class="az-card-header">
              <h4 class="az-card-title">Настройки API</h4>
              <span class="badge badge-<?= $apiEnabled ? 'success' : 'secondary' ?>">
                <?= $apiEnabled ? 'Включён' : 'Отключён' ?>
              </span>
            </div>
            <div class="az-card-body">
              <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                <input type="hidden" name="action" value="save">
                <div class="az-form-group">
                  <div class="form-check mb-16">
                    <input type="checkbox" name="warehouse_api_enabled" id="wh_enabled" class="form-check-input"
                           value="1" <?= $apiEnabled ? 'checked' : '' ?>>
                    <label for="wh_enabled" class="form-check-label">Интеграция активна</label>
                  </div>
                </div>
                <div class="az-form-group">
                  <label>URL склада API</label>
                  <input type="url" name="warehouse_api_url" class="form-control"
                         value="<?= sanitize($apiUrl) ?>"
                         placeholder="https://api.warehouse.ru/v1/products">
                </div>
                <div class="az-form-group">
                  <label>API ключ / Bearer токен</label>
                  <input type="text" name="warehouse_api_key" class="form-control"
                         value="<?= sanitize($apiKey) ?>"
                         placeholder="sk_live_...">
                  <small class="text-muted">Передаётся как <code>Authorization: Bearer {key}</code></small>
                </div>
                <button type="submit" class="az-btn az-btn-primary">Сохранить настройки</button>
              </form>
            </div>
          </div>
        </div>

        <!-- Test connection -->
        <div class="col-lg-6 mb-24">
          <div class="az-card">
            <div class="az-card-header">
              <h4 class="az-card-title">Тест подключения</h4>
            </div>
            <div class="az-card-body">
              <p style="font-size:0.85rem;color:#666;margin-bottom:16px;">
                GET запрос к указанному URL с вашим API ключом. Результат сохраняется в лог.
              </p>
              <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                <input type="hidden" name="action" value="test">
                <button type="submit" class="az-btn az-btn-primary" <?= !$apiUrl ? 'disabled' : '' ?>>
                  <i class="fa fa-plug"></i> Тест подключения
                </button>
                <?php if (!$apiUrl): ?>
                <p class="text-danger" style="margin-top:8px;font-size:0.8rem;">Настройте URL API слева.</p>
                <?php endif; ?>
              </form>

              <?php if ($testResult !== null): ?>
              <div style="margin-top:16px;">
                <div class="alert alert-<?= $testResult['success'] ? 'success' : 'danger' ?>" style="margin-bottom:12px;">
                  <?php if ($testResult['success']): ?>
                  <strong>Успешно!</strong> HTTP <?= (int)$testResult['httpCode'] ?> &middot; <?= (int)$testResult['elapsed'] ?> мс
                  <?php else: ?>
                  <strong>Ошибка:</strong> <?= sanitize($testResult['error'] ?: 'Неизвестная ошибка') ?>
                  <?php endif; ?>
                </div>
                <?php if ($testResult['body']): ?>
                <strong style="font-size:0.8rem;color:#555;">Ответ API:</strong>
                <pre style="background:#f5f5f5;padding:10px;border-radius:4px;font-size:0.72rem;max-height:180px;overflow:auto;margin-top:6px;border:1px solid #e0e0e0;"><?= sanitize(mb_substr($testResult['body'], 0, 1500)) ?></pre>
                <?php endif; ?>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Log -->
      <div class="az-card">
        <div class="az-card-header">
          <h4 class="az-card-title">Лог запросов <small style="font-weight:400;color:#888;">(последние 20)</small></h4>
        </div>
        <div class="az-card-body p-0">
          <div class="table-responsive">
            <table class="az-table" style="font-size:0.8rem;">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Действие</th>
                  <th>URL</th>
                  <th style="text-align:center;">HTTP</th>
                  <th style="text-align:center;">Статус</th>
                  <th>Ответ (обрезан)</th>
                  <th style="white-space:nowrap;">Дата</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($logs)): ?>
                <tr>
                  <td colspan="7" style="text-align:center;color:#999;padding:24px;">
                    Лог пуст или таблица <code>warehouse_api_log</code> не существует.
                  </td>
                </tr>
                <?php endif; ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                  <td><?= (int)$log['id'] ?></td>
                  <td><code><?= sanitize($log['action'] ?? '') ?></code></td>
                  <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <span title="<?= sanitize($log['request_url'] ?? '') ?>">
                      <?= sanitize(mb_substr($log['request_url'] ?? '', 0, 40)) ?>
                    </span>
                  </td>
                  <td style="text-align:center;font-family:monospace;"><?= (int)($log['response_code'] ?? 0) ?></td>
                  <td style="text-align:center;">
                    <span class="badge badge-<?= ($log['success'] ?? 0) ? 'success' : 'danger' ?>">
                      <?= ($log['success'] ?? 0) ? 'OK' : 'ERR' ?>
                    </span>
                  </td>
                  <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.72rem;color:#888;">
                    <?= sanitize(mb_substr($log['response_body'] ?? '', 0, 60)) ?>
                  </td>
                  <td style="white-space:nowrap;color:#aaa;"><?= date('d.m.Y H:i', strtotime($log['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div><!-- /.az-content -->
  </div><!-- /.az-main -->
</div><!-- /.az-panel -->

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
