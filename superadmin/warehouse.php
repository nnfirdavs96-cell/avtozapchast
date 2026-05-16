<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/autoeuro.php';
requireRole(['superadmin', 'admin', 'manager']);
requirePermission('warehouse');

$db   = getDB();
$csrf = generateCsrfToken();

$testResult    = null;
$deliveries    = null;
$payers        = null;
$balance       = null;

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF ошибка.'); redirect(APP_URL . '/superadmin/warehouse.php');
    }

    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'save') {
        $fields = [
            'autoeuro_api_key',
            'autoeuro_delivery_key',
            'autoeuro_payer_key',
        ];
        foreach ($fields as $key) {
            $val = trim($_POST[$key] ?? '');
            $db->prepare("INSERT INTO site_settings (`key`, `value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=?, updated_at=NOW()")
               ->execute([$key, $val, $val]);
        }
        $enabled = isset($_POST['autoeuro_enabled']) ? '1' : '0';
        $db->prepare("INSERT INTO site_settings (`key`, `value`) VALUES ('autoeuro_enabled', ?) ON DUPLICATE KEY UPDATE `value`=?, updated_at=NOW()")
           ->execute([$enabled, $enabled]);

        flashMessage('success', 'Настройки AutoEuro API сохранены.');
        redirect(APP_URL . '/superadmin/warehouse.php');
    }

    if ($postAction === 'test_balance') {
        $ae = AutoEuro::fromSettings();
        if (!$ae) {
            $testResult = ['success' => false, 'error' => 'API отключён или ключ не задан.', 'action' => 'balance'];
        } else {
            $t0      = microtime(true);
            $data    = $ae->getBalance();
            $elapsed = round((microtime(true) - $t0) * 1000);
            $success = !isset($data['error']);
            $testResult = ['success' => $success, 'data' => $data, 'elapsed' => $elapsed, 'action' => 'balance'];
            try {
                $db->prepare("INSERT INTO warehouse_api_log (action, request_url, response_code, response_body, success, created_at) VALUES (?,?,?,?,?,NOW())")
                   ->execute(['get_balance', 'autoeuro/get_balance', $success ? 200 : 502, mb_substr(json_encode($data), 0, 2000), $success ? 1 : 0]);
            } catch (Exception $e) {}
        }
    }

    if ($postAction === 'test_deliveries') {
        $ae = AutoEuro::fromSettings();
        if (!$ae) {
            $testResult = ['success' => false, 'error' => 'API отключён или ключ не задан.', 'action' => 'deliveries'];
        } else {
            $t0      = microtime(true);
            $data    = $ae->getDeliveries();
            $elapsed = round((microtime(true) - $t0) * 1000);
            $success = !isset($data['error']);
            $testResult = ['success' => $success, 'data' => $data, 'elapsed' => $elapsed, 'action' => 'deliveries'];
            if ($success) $deliveries = $data;
            try {
                $db->prepare("INSERT INTO warehouse_api_log (action, request_url, response_code, response_body, success, created_at) VALUES (?,?,?,?,?,NOW())")
                   ->execute(['get_deliveries', 'autoeuro/get_deliveries', $success ? 200 : 502, mb_substr(json_encode($data), 0, 2000), $success ? 1 : 0]);
            } catch (Exception $e) {}
        }
    }

    if ($postAction === 'test_payers') {
        $ae = AutoEuro::fromSettings();
        if (!$ae) {
            $testResult = ['success' => false, 'error' => 'API отключён или ключ не задан.', 'action' => 'payers'];
        } else {
            $t0      = microtime(true);
            $data    = $ae->getPayers();
            $elapsed = round((microtime(true) - $t0) * 1000);
            $success = !isset($data['error']);
            $testResult = ['success' => $success, 'data' => $data, 'elapsed' => $elapsed, 'action' => 'payers'];
            if ($success) $payers = $data;
            try {
                $db->prepare("INSERT INTO warehouse_api_log (action, request_url, response_code, response_body, success, created_at) VALUES (?,?,?,?,?,NOW())")
                   ->execute(['get_payers', 'autoeuro/get_payers', $success ? 200 : 502, mb_substr(json_encode($data), 0, 2000), $success ? 1 : 0]);
            } catch (Exception $e) {}
        }
    }
}

// Load current settings
$apiKey      = getSetting('autoeuro_api_key');
$deliveryKey = getSetting('autoeuro_delivery_key');
$payerKey    = getSetting('autoeuro_payer_key');
$apiEnabled  = getSetting('autoeuro_enabled') === '1';

// Load log
$logs = [];
try {
    $logs = $db->query("SELECT * FROM warehouse_api_log ORDER BY created_at DESC LIMIT 30")->fetchAll();
} catch (Exception $e) {}

$pageTitle = 'AutoEuro API — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="az-panel">
  <?php echo renderRoleSidebar('warehouse'); ?>

  <div class="az-main">
    <div class="az-topbar" style="border-bottom-color:#9b59b622;background:#f9f5ff;">
      <div class="az-topbar-title" style="color:#6a1b9a;">
        <img src="https://shop.autoeuro.ru/favicon.ico" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;" alt="" onerror="this.style.display='none'">
        AutoEuro API v2
      </div>
      <div class="az-topbar-user"><?= sanitize($_SESSION['username'] ?? 'Superadmin') ?> &middot; <a href="<?= APP_URL ?>/auth/logout.php">Выйти</a></div>
    </div>

    <div class="az-content">
      <?php if ($flash = getFlashMessage()): ?>
      <div class="alert alert-<?= sanitize($flash['type']) ?> mb-16"><?= sanitize($flash['message']) ?></div>
      <?php endif; ?>

      <div class="row">

        <!-- ── Settings ───────────────────────────────────────────── -->
        <div class="col-lg-6 mb-24">
          <div class="az-card">
            <div class="az-card-header">
              <h4 class="az-card-title">Настройки AutoEuro API</h4>
              <span class="badge badge-<?= $apiEnabled ? 'success' : 'secondary' ?>">
                <?= $apiEnabled ? 'Включён' : 'Отключён' ?>
              </span>
            </div>
            <div class="az-card-body">
              <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                <input type="hidden" name="action"     value="save">

                <div class="az-form-group">
                  <div class="form-check mb-16">
                    <input type="checkbox" name="autoeuro_enabled" id="ae_enabled"
                           class="form-check-input" value="1" <?= $apiEnabled ? 'checked' : '' ?>>
                    <label for="ae_enabled" class="form-check-label">Интеграция активна</label>
                  </div>
                </div>

                <div class="az-form-group">
                  <label>API ключ <span style="color:#c0392b">*</span></label>
                  <input type="text" name="autoeuro_api_key" class="form-control"
                         value="<?= sanitize($apiKey) ?>"
                         placeholder="Ваш ключ с shop.autoeuro.ru">
                  <small class="text-muted">
                    Получите в личном кабинете на
                    <a href="https://shop.autoeuro.ru" target="_blank" rel="noopener">shop.autoeuro.ru</a>
                    → Настройки → API.
                  </small>
                </div>

                <div class="az-form-group">
                  <label>Ключ способа доставки (delivery_key)</label>
                  <input type="text" name="autoeuro_delivery_key" class="form-control"
                         value="<?= sanitize($deliveryKey) ?>"
                         placeholder="Нажмите «Получить варианты» справа">
                  <small class="text-muted">
                    Используется по умолчанию при поиске и заказе.
                    Получите через кнопку «Варианты доставки».
                  </small>
                </div>

                <div class="az-form-group">
                  <label>Ключ плательщика (payer_key)</label>
                  <input type="text" name="autoeuro_payer_key" class="form-control"
                         value="<?= sanitize($payerKey) ?>"
                         placeholder="Нажмите «Получить плательщиков» справа">
                  <small class="text-muted">
                    Используется по умолчанию при оформлении заказов.
                  </small>
                </div>

                <button type="submit" class="az-btn az-btn-primary">Сохранить настройки</button>
              </form>
            </div>
          </div>
        </div>

        <!-- ── Test panel ─────────────────────────────────────────── -->
        <div class="col-lg-6 mb-24">
          <div class="az-card">
            <div class="az-card-header">
              <h4 class="az-card-title">Тест подключения</h4>
            </div>
            <div class="az-card-body">
              <p style="font-size:0.85rem;color:#666;margin-bottom:16px;">
                Запросы выполняются с текущим API ключом из настроек. Результаты сохраняются в лог.
              </p>

              <?php if (!$apiKey): ?>
              <div class="alert alert-warning">Сначала введите и сохраните API ключ.</div>
              <?php else: ?>
              <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                  <input type="hidden" name="action" value="test_balance">
                  <button class="az-btn az-btn-primary az-btn-sm"><i class="fa fa-wallet"></i> Баланс</button>
                </form>
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                  <input type="hidden" name="action" value="test_deliveries">
                  <button class="az-btn az-btn-primary az-btn-sm"><i class="fa fa-truck"></i> Варианты доставки</button>
                </form>
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                  <input type="hidden" name="action" value="test_payers">
                  <button class="az-btn az-btn-primary az-btn-sm"><i class="fa fa-users"></i> Плательщики</button>
                </form>
              </div>
              <?php endif; ?>

              <?php if ($testResult !== null): ?>
              <div class="alert alert-<?= $testResult['success'] ? 'success' : 'danger' ?>" style="margin-bottom:10px;">
                <?php if ($testResult['success']): ?>
                  <strong>Успешно!</strong>
                  <?php if (isset($testResult['elapsed'])): ?>
                  &middot; <?= (int)$testResult['elapsed'] ?> мс
                  <?php endif; ?>
                <?php else: ?>
                  <strong>Ошибка:</strong> <?= sanitize($testResult['error'] ?? ($testResult['data']['error'] ?? 'Неизвестно')) ?>
                <?php endif; ?>
              </div>

              <?php if ($testResult['success'] && isset($testResult['data'])): ?>
                <?php $data = $testResult['data']; ?>

                <?php if ($testResult['action'] === 'balance' && is_array($data)): ?>
                <table class="az-table" style="font-size:0.82rem;margin-top:8px;">
                  <tbody>
                    <?php $labels = ['balance'=>'Баланс','credit'=>'Кредит','ordered'=>'В работе','reserved'=>'Готово к отгрузке','limit'=>'Доступно для заказа','pay_tomorrow'=>'К оплате завтра','active'=>'Статус клиента']; ?>
                    <?php foreach ($labels as $k => $lbl): if (!array_key_exists($k, $data)) continue; ?>
                    <tr>
                      <td style="color:#888;width:55%"><?= $lbl ?></td>
                      <td>
                        <?php if ($k === 'active'): ?>
                          <span class="badge badge-<?= $data[$k] ? 'success' : 'danger' ?>">
                            <?= $data[$k] ? 'Активен' : 'Заблокирован' ?>
                          </span>
                        <?php else: ?>
                          <strong><?= number_format((float)$data[$k], 2, '.', ' ') ?> ₽</strong>
                        <?php endif; ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>

                <?php elseif ($testResult['action'] === 'deliveries' && is_array($data)): ?>
                <p style="font-size:0.8rem;color:#666;margin:8px 0 4px">Скопируйте нужный <code>delivery_key</code> в поле слева:</p>
                <table class="az-table" style="font-size:0.8rem;">
                  <thead><tr><th>Название</th><th>delivery_key</th></tr></thead>
                  <tbody>
                    <?php foreach ($data as $d): if (!is_array($d)) continue; ?>
                    <tr>
                      <td><?= sanitize($d['name'] ?? '') ?></td>
                      <td>
                        <code style="word-break:break-all;font-size:0.72rem;"
                              title="Нажмите чтобы скопировать"
                              onclick="navigator.clipboard.writeText(this.textContent);this.style.background='#d4edda'"
                              style="cursor:pointer">
                          <?= sanitize($d['delivery_key'] ?? '') ?>
                        </code>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>

                <?php elseif ($testResult['action'] === 'payers' && is_array($data)): ?>
                <p style="font-size:0.8rem;color:#666;margin:8px 0 4px">Скопируйте нужный <code>payer_key</code> в поле слева:</p>
                <table class="az-table" style="font-size:0.8rem;">
                  <thead><tr><th>Плательщик</th><th>payer_key</th></tr></thead>
                  <tbody>
                    <?php foreach ($data as $p): if (!is_array($p)) continue; ?>
                    <tr>
                      <td><?= sanitize($p['payer_name'] ?? '') ?></td>
                      <td>
                        <code style="word-break:break-all;font-size:0.72rem;cursor:pointer"
                              title="Нажмите чтобы скопировать"
                              onclick="navigator.clipboard.writeText(this.textContent);this.style.background='#d4edda'">
                          <?= sanitize($p['payer_key'] ?? '') ?>
                        </code>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
                <?php endif; ?>

              <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>

          <!-- Quick search test -->
          <div class="az-card mt-16">
            <div class="az-card-header">
              <h4 class="az-card-title">Быстрый поиск товара</h4>
            </div>
            <div class="az-card-body">
              <?php if (!$apiEnabled || !$apiKey || !$deliveryKey): ?>
              <div class="alert alert-warning" style="font-size:0.85rem;">
                Заполните и сохраните API ключ + delivery_key для тестового поиска.
              </div>
              <?php else: ?>
              <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
                <input type="text" id="test_brand" class="form-control" placeholder="Бренд (напр. BOSCH)"
                       style="flex:1;min-width:100px">
                <input type="text" id="test_code" class="form-control" placeholder="Артикул (напр. 0986424797)"
                       style="flex:2;min-width:120px">
                <button onclick="runTestSearch()" class="az-btn az-btn-primary az-btn-sm">
                  <i class="fa fa-search"></i> Найти
                </button>
              </div>
              <div id="test_search_result" style="font-size:0.8rem;max-height:280px;overflow:auto;"></div>
              <script>
              function runTestSearch() {
                var brand = document.getElementById('test_brand').value.trim();
                var code  = document.getElementById('test_code').value.trim();
                var out   = document.getElementById('test_search_result');
                if (!brand || !code) { out.innerHTML = '<span style="color:#c0392b">Заполните бренд и артикул.</span>'; return; }
                out.innerHTML = '<span style="color:#888"><i class="fa fa-spinner fa-spin"></i> Поиск...</span>';
                fetch('<?= APP_URL ?>/api/autoeuro_search.php?brand=' + encodeURIComponent(brand) + '&code=' + encodeURIComponent(code))
                  .then(function(r){ return r.json(); })
                  .then(function(d){
                    if (d.error) { out.innerHTML = '<span style="color:#c0392b">Ошибка: ' + d.error + '</span>'; return; }
                    var offers = d.offers || [];
                    if (!offers.length) { out.innerHTML = '<span style="color:#888">Товаров не найдено.</span>'; return; }
                    var html = '<table style="width:100%;border-collapse:collapse;font-size:0.77rem">';
                    html += '<tr style="background:#f5f5f5"><th style="padding:4px 6px;text-align:left">Бренд</th><th>Артикул</th><th>Название</th><th>Цена</th><th>Кол-во</th><th>Доставка</th></tr>';
                    offers.forEach(function(o){
                      html += '<tr style="border-bottom:1px solid #eee">';
                      html += '<td style="padding:4px 6px">' + o.brand + '</td>';
                      html += '<td style="padding:4px 6px;font-family:monospace">' + o.code + '</td>';
                      html += '<td style="padding:4px 6px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + o.name + '">' + o.name + '</td>';
                      html += '<td style="padding:4px 6px;white-space:nowrap">' + o.price.toFixed(2) + ' ' + o.currency + '</td>';
                      html += '<td style="padding:4px 6px;text-align:center">' + o.amount + ' ' + o.unit + '</td>';
                      html += '<td style="padding:4px 6px;white-space:nowrap">' + (o.delivery_time ? o.delivery_time.substring(0,10) : '—') + '</td>';
                      html += '</tr>';
                    });
                    html += '</table><p style="color:#888;margin-top:6px">Найдено: ' + offers.length + '</p>';
                    out.innerHTML = html;
                  })
                  .catch(function(e){ out.innerHTML = '<span style="color:#c0392b">Ошибка запроса: ' + e.message + '</span>'; });
              }
              </script>
              <?php endif; ?>
            </div>
          </div>
        </div>

      </div><!-- /.row -->

      <!-- ── API reference ──────────────────────────────────────── -->
      <div class="az-card mb-24">
        <div class="az-card-header">
          <h4 class="az-card-title">Эндпоинты для интеграции</h4>
        </div>
        <div class="az-card-body" style="font-size:0.85rem;">
          <div class="table-responsive">
            <table class="az-table">
              <thead>
                <tr><th>Эндпоинт</th><th>Метод</th><th>Описание</th><th>Ключевые параметры</th></tr>
              </thead>
              <tbody>
                <tr>
                  <td><code>/api/autoeuro_search.php</code></td>
                  <td>GET</td>
                  <td>Поиск товаров</td>
                  <td><code>brand</code>, <code>code</code>, <code>with_crosses</code></td>
                </tr>
                <tr>
                  <td><code>/api/autoeuro_order.php</code></td>
                  <td>POST JSON</td>
                  <td>Оформление заказа</td>
                  <td><code>items[]</code> (offer_key, quantity), <code>comment</code></td>
                </tr>
              </tbody>
            </table>
          </div>
          <details style="margin-top:12px;">
            <summary style="cursor:pointer;color:#6a1b9a;font-weight:600">Пример: поиск</summary>
            <pre style="background:#f5f5f5;padding:10px;border-radius:4px;font-size:0.78rem;margin-top:8px;">GET /api/autoeuro_search.php?brand=BOSCH&code=0986424797&with_crosses=1

// Ответ:
{
  "offers": [
    {
      "offer_key": "...",
      "brand": "BOSCH",
      "code": "0986424797",
      "name": "Диск тормозной",
      "price": 1234.56,
      "currency": "RUB",
      "amount": 10,
      "delivery_time": "2025-05-14 10:00",
      "delivery_time_max": "2025-05-15 18:00"
    }
  ],
  "count": 1
}</pre>
          </details>
          <details style="margin-top:8px;">
            <summary style="cursor:pointer;color:#6a1b9a;font-weight:600">Пример: оформление заказа</summary>
            <pre style="background:#f5f5f5;padding:10px;border-radius:4px;font-size:0.78rem;margin-top:8px;">POST /api/autoeuro_order.php
Content-Type: application/json
X-CSRF-Token: {csrf_token}

{
  "items": [
    {"offer_key": "abc123", "quantity": 2, "price": 0}
  ],
  "comment": "Срочный заказ"
}

// Ответ:
{
  "success": true,
  "order_id": 987654,
  "result_description": "Заказ принят в обработку"
}</pre>
          </details>
        </div>
      </div>

      <!-- ── Log ───────────────────────────────────────────────── -->
      <div class="az-card">
        <div class="az-card-header">
          <h4 class="az-card-title">Лог запросов <small style="font-weight:400;color:#888;">(последние 30)</small></h4>
        </div>
        <div class="az-card-body p-0">
          <div class="table-responsive">
            <table class="az-table" style="font-size:0.8rem;">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Действие</th>
                  <th style="text-align:center;">HTTP</th>
                  <th style="text-align:center;">Статус</th>
                  <th>Ответ (обрезан)</th>
                  <th style="white-space:nowrap;">Дата</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($logs)): ?>
                <tr>
                  <td colspan="6" style="text-align:center;color:#999;padding:24px;">
                    Лог пуст или таблица <code>warehouse_api_log</code> не существует.
                  </td>
                </tr>
                <?php endif; ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                  <td><?= (int)$log['id'] ?></td>
                  <td><code><?= sanitize($log['action'] ?? '') ?></code></td>
                  <td style="text-align:center;font-family:monospace;"><?= (int)($log['response_code'] ?? 0) ?></td>
                  <td style="text-align:center;">
                    <span class="badge badge-<?= ($log['success'] ?? 0) ? 'success' : 'danger' ?>">
                      <?= ($log['success'] ?? 0) ? 'OK' : 'ERR' ?>
                    </span>
                  </td>
                  <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.72rem;color:#888;">
                    <?= sanitize(mb_substr($log['response_body'] ?? '', 0, 80)) ?>
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
