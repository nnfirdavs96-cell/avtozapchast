<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole('superadmin');
$pageTitle = t('warehouse_api') . ' — ' . getSetting('site_name');

$db = getDB();
$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $keys = ['warehouse_api_url','warehouse_api_key','warehouse_api_enabled'];
        foreach ($keys as $key) {
            $val = $key === 'warehouse_api_enabled' ? (isset($_POST[$key]) ? '1' : '0') : ($_POST[$key] ?? '');
            $db->prepare("INSERT INTO site_settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=?")->execute([$key,$val,$val]);
        }
        $message = 'Настройки сохранены.';
    }

    if ($action === 'test') {
        $apiUrl = getSetting('warehouse_api_url','');
        $apiKey = getSetting('warehouse_api_key','');
        $start  = microtime(true);
        $ctx    = stream_context_create(['http'=>['timeout'=>10,'method'=>'GET','header'=>"X-Api-Key: $apiKey\r\n"]]);
        $resp   = @file_get_contents($apiUrl, false, $ctx);
        $ms     = round((microtime(true)-$start)*1000);
        $code   = 0;
        if (isset($http_response_header)) {
            preg_match('/HTTP\/\d\.?\d? (\d{3})/', $http_response_header[0] ?? '', $m);
            $code = (int)($m[1] ?? 0);
        }
        $preview = $resp ? substr($resp, 0, 500) : 'Нет ответа';
        $db->prepare("INSERT INTO warehouse_api_log (action,request,response,status_code) VALUES (?,?,?,?)")
           ->execute(['test', $apiUrl, $preview, $code]);
        $message = "Ответ получен за {$ms}мс. Код: {$code}";
    }
}

$apiUrl     = getSetting('warehouse_api_url','');
$apiKey     = getSetting('warehouse_api_key','');
$apiEnabled = getSetting('warehouse_api_enabled','0');

$logs = $db->query("SELECT * FROM warehouse_api_log ORDER BY created_at DESC LIMIT 20")->fetchAll();

$sidebarLinks = [
    ['url'=>'/superadmin/index.php','icon'=>'icon-home','label'=>t('dashboard')],
    ['url'=>'/admin/users.php','icon'=>'icon-users','label'=>t('users')],
    ['url'=>'/admin/orders.php','icon'=>'icon-shopping-bag2','label'=>t('orders')],
    ['url'=>'/superadmin/settings.php','icon'=>'icon-settings','label'=>t('settings')],
    ['url'=>'/superadmin/currencies.php','icon'=>'icon-dollar-sign','label'=>t('currencies_mgmt')],
    ['url'=>'/superadmin/languages.php','icon'=>'icon-globe','label'=>t('languages_mgmt')],
    ['url'=>'/superadmin/warehouse.php','icon'=>'icon-database','label'=>t('warehouse_api')],
    ['url'=>'/superadmin/blog.php','icon'=>'icon-edit','label'=>t('blog_mgmt')],
];

require_once dirname(__DIR__) . '/includes/header.php';
?>
<div class="az-panel">
  <aside class="az-sidebar">
    <div class="az-sidebar-logo">AUTO<span>PARTS</span> <small style="font-size:0.65rem;opacity:0.6;display:block;margin-top:2px">Superadmin</small></div>
    <nav><ul>
      <?php foreach ($sidebarLinks as $l): ?>
      <li><a href="<?php echo APP_URL.$l['url']; ?>" class="<?php echo strpos($_SERVER['REQUEST_URI'],$l['url'])!==false?'active':''; ?>"><i class="<?php echo $l['icon']; ?>"></i> <?php echo $l['label']; ?></a></li>
      <?php endforeach; ?>
    </ul></nav>
  </aside>
  <main class="az-main">
    <div class="az-topbar"><h1><?php echo t('warehouse_api'); ?></h1><a href="<?php echo APP_URL; ?>/index.php" class="az-btn az-btn-secondary az-btn-sm">Сайт</a></div>
    <div class="az-content">
      <?php if ($message): ?><div class="az-alert az-alert-success"><?php echo sanitize($message); ?></div><?php endif; ?>
      <?php if ($error): ?><div class="az-alert az-alert-danger"><?php echo sanitize($error); ?></div><?php endif; ?>

      <div class="az-card">
        <h3>Настройки API склада</h3>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
          <input type="hidden" name="action" value="save_settings">
          <div class="az-form-group">
            <label>URL API склада</label>
            <input type="url" name="warehouse_api_url" value="<?php echo sanitize($apiUrl); ?>" placeholder="https://api.example.com/v1/stock">
          </div>
          <div class="az-form-group">
            <label>API Ключ</label>
            <input type="text" name="warehouse_api_key" value="<?php echo sanitize($apiKey); ?>" placeholder="your-api-key-here">
          </div>
          <div style="margin-bottom:16px">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
              <input type="checkbox" name="warehouse_api_enabled" value="1" <?php echo $apiEnabled==='1'?'checked':''; ?>>
              <span>Включить интеграцию с API склада</span>
            </label>
          </div>
          <button type="submit" class="az-btn az-btn-primary"><?php echo t('save'); ?></button>
        </form>
        <form method="POST" style="margin-top:16px">
          <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
          <input type="hidden" name="action" value="test">
          <button type="submit" class="az-btn az-btn-secondary">Тест соединения</button>
        </form>
      </div>

      <div class="az-card">
        <h3>Журнал запросов API (последние 20)</h3>
        <?php if (empty($logs)): ?>
        <p style="color:#aaa"><?php echo t('no_records'); ?></p>
        <?php else: ?>
        <div class="table-responsive">
          <table class="az-table">
            <thead><tr><th>ID</th><th>Действие</th><th>Запрос</th><th>Код</th><th>Ответ (превью)</th><th>Время</th></tr></thead>
            <tbody>
              <?php foreach ($logs as $log): ?>
              <tr>
                <td><?php echo (int)$log['id']; ?></td>
                <td><?php echo sanitize($log['action']); ?></td>
                <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo sanitize($log['request']); ?></td>
                <td><?php echo (int)$log['status_code']; ?></td>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.75rem;color:#666"><?php echo sanitize(substr($log['response'],0,100)); ?></td>
                <td style="font-size:0.75rem;color:#aaa"><?php echo $log['created_at']; ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
