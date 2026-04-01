<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole('superadmin');

$db   = getDB();
$csrf = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF error.'); redirect(APP_URL . '/superadmin/settings.php');
    }
    $keys = ['site_name','site_email','site_phone','site_address','site_currency','items_per_page'];
    foreach ($keys as $key) {
        $val = trim($_POST[$key] ?? '');
        $db->prepare("INSERT INTO site_settings (`key`, `value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=?, updated_at=NOW()")
           ->execute([$key, $val, $val]);
    }
    // Handle custom keys
    $customKeys  = $_POST['custom_key'] ?? [];
    $customVals  = $_POST['custom_val'] ?? [];
    foreach ($customKeys as $i => $ck) {
        $ck = trim($ck);
        $cv = trim($customVals[$i] ?? '');
        if ($ck) {
            $db->prepare("INSERT INTO site_settings (`key`, `value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=?, updated_at=NOW()")
               ->execute([$ck, $cv, $cv]);
        }
    }
    flashMessage('success', 'Настройки сохранены.');
    redirect(APP_URL . '/superadmin/settings.php');
}

// Load all settings
$settingsStmt = $db->query("SELECT * FROM site_settings ORDER BY id");
$settings = [];
foreach ($settingsStmt->fetchAll() as $row) {
    $settings[$row['key']] = $row['value'];
}

$pageTitle = 'Настройки сайта';
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/nav.php';
?>

<div class="dash-layout">
  <div class="dash-sidebar"><?php renderNav(); ?></div>
  <div class="dash-main">
    <div class="dash-heading">НАСТРОЙКИ САЙТА</div>

    <div style="max-width:700px;">
      <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

        <div class="card mb-24">
          <div class="card-header"><h3>ОСНОВНЫЕ НАСТРОЙКИ</h3></div>
          <div class="card-body">
            <div class="form-group">
              <label class="form-label">Название сайта</label>
              <input type="text" name="site_name" class="form-input" value="<?= sanitize($settings['site_name'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Контактный email</label>
              <input type="email" name="site_email" class="form-input" value="<?= sanitize($settings['site_email'] ?? '') ?>">
            </div>
            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Телефон</label>
                <input type="text" name="site_phone" class="form-input" value="<?= sanitize($settings['site_phone'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Валюта</label>
                <input type="text" name="site_currency" class="form-input" value="<?= sanitize($settings['site_currency'] ?? '₽') ?>">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Адрес</label>
              <input type="text" name="site_address" class="form-input" value="<?= sanitize($settings['site_address'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Товаров на странице каталога</label>
              <input type="number" name="items_per_page" class="form-input" min="4" max="100" value="<?= sanitize($settings['items_per_page'] ?? '12') ?>">
            </div>
          </div>
        </div>

        <div class="card mb-24">
          <div class="card-header">
            <h3>ВСЕ КЛЮЧИ / ЗНАЧЕНИЯ</h3>
          </div>
          <div class="table-wrap" style="border:none;border-radius:0;">
            <table class="data-table">
              <thead><tr><th>Ключ</th><th>Значение</th><th>Обновлён</th></tr></thead>
              <tbody>
                <?php
                $allSettings = $db->query("SELECT * FROM site_settings ORDER BY id")->fetchAll();
                foreach ($allSettings as $row): ?>
                <tr>
                  <td><span class="mono"><?= sanitize($row['key']) ?></span></td>
                  <td style="font-size:0.875rem;color:var(--text-secondary);"><?= sanitize($row['value'] ?? '') ?></td>
                  <td style="color:var(--text-muted);font-size:0.78rem;"><?= date('d.m.Y H:i', strtotime($row['updated_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="card mb-24">
          <div class="card-header"><h3>ДОБАВИТЬ ПОЛЬЗОВАТЕЛЬСКИЕ КЛЮЧИ</h3></div>
          <div class="card-body">
            <p class="form-help mb-16">Добавьте дополнительные настройки в формате ключ-значение.</p>
            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Ключ</label>
                <input type="text" name="custom_key[]" class="form-input" placeholder="my_setting">
              </div>
              <div class="form-group">
                <label class="form-label">Значение</label>
                <input type="text" name="custom_val[]" class="form-input" placeholder="значение">
              </div>
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary">СОХРАНИТЬ НАСТРОЙКИ</button>
      </form>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
