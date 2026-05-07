<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_layout.php';
requireRole(['superadmin']);

$db = getDB();
if ($_SERVER['REQUEST_METHOD']==='POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $upd = $db->prepare("INSERT INTO site_settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
    foreach ($_POST['settings'] ?? [] as $k => $v) {
        $upd->execute([$k, (string)$v]);
    }
    flashMessage('success', 'Настройки сохранены');
    redirect(APP_URL . '/superadmin/settings.php');
}
$rows = $db->query("SELECT `key`, `value` FROM site_settings ORDER BY `key`")->fetchAll();
$settings = [];
foreach ($rows as $r) $settings[$r['key']] = $r['value'];

$groups = [
    'Сайт'         => ['site_name','site_email','site_email_tj','site_phone','site_phone_tj','site_address','site_address_tj','site_currency'],
    'SEO'          => ['seo_title_suffix','meta_description'],
    'Соцсети'      => ['site_telegram','site_whatsapp','site_instagram'],
    'i18n'         => ['default_language','default_currency'],
    'E-mail'       => ['order_email_admin','smtp_from_name','smtp_from_email'],
    'Прочее'       => ['items_per_page'],
];

$pageTitle = 'Настройки сайта';
$adminArea = true;
require_once __DIR__ . '/../includes/header.php';
?>
<div class="dash-layout">
  <?php renderAdminSidebar(); ?>
  <div class="dash-main">
    <h1 class="dash-heading">Настройки сайта</h1>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
      <?php foreach ($groups as $title => $keys): ?>
        <div class="admin-card mb-16">
          <h3 style="margin-bottom:14px"><?= sanitize($title) ?></h3>
          <?php foreach ($keys as $k): ?>
            <div class="form-group">
              <label class="form-label"><?= sanitize($k) ?></label>
              <?php if ($k==='meta_description'): ?>
                <textarea name="settings[<?= sanitize($k) ?>]" class="form-textarea" rows="3"><?= sanitize($settings[$k] ?? '') ?></textarea>
              <?php else: ?>
                <input type="text" name="settings[<?= sanitize($k) ?>]" class="form-input" value="<?= sanitize($settings[$k] ?? '') ?>">
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
      <button class="btn btn-primary btn-lg">Сохранить настройки</button>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
