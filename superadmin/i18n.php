<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_layout.php';
requireRole(['superadmin']);

$db = getDB();
if ($_SERVER['REQUEST_METHOD']==='POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $entity = $_POST['entity'] ?? '';
    $a = $_POST['action'] ?? '';
    if ($entity==='currency' && $a==='update') {
        $db->prepare("UPDATE currencies SET symbol=?, name=?, rate=?, is_active=? WHERE code=?")
            ->execute([trim($_POST['symbol']), trim($_POST['name']), (float)$_POST['rate'], isset($_POST['is_active'])?1:0, $_POST['code']]);
    }
    if ($entity==='language' && $a==='update') {
        $db->prepare("UPDATE languages SET name=?, is_active=? WHERE code=?")
            ->execute([trim($_POST['name']), isset($_POST['is_active'])?1:0, $_POST['code']]);
    }
    redirect(APP_URL . '/superadmin/i18n.php');
}
$languages  = $db->query("SELECT * FROM languages ORDER BY sort_order")->fetchAll();
$currencies = $db->query("SELECT * FROM currencies ORDER BY sort_order")->fetchAll();

$pageTitle = 'Языки и валюты';
$adminArea = true;
require_once __DIR__ . '/../includes/header.php';
?>
<div class="dash-layout">
  <?php renderAdminSidebar(); ?>
  <div class="dash-main">
    <h1 class="dash-heading">Языки и валюты</h1>

    <div class="admin-card mb-32">
      <h3 style="margin-bottom:14px">Языки</h3>
      <?php foreach ($languages as $l): ?>
        <form method="post" class="flex gap-16 mb-8" style="align-items:center">
          <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
          <input type="hidden" name="entity" value="language">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="code" value="<?= sanitize($l['code']) ?>">
          <code style="background:#222;padding:6px 12px;border-radius:4px;color:#fcb700"><?= sanitize($l['code']) ?></code>
          <input type="text" name="name" class="form-input" value="<?= sanitize($l['name']) ?>" required>
          <label><input type="checkbox" name="is_active" value="1" <?= $l['is_active']?'checked':'' ?>> Активен</label>
          <?php if ($l['is_default']): ?><span class="badge badge-primary">по умолч.</span><?php endif; ?>
          <button class="btn btn-primary btn-sm">Сохранить</button>
        </form>
      <?php endforeach; ?>
    </div>

    <div class="admin-card">
      <h3 style="margin-bottom:14px">Валюты (курсы относительно базы — RUB)</h3>
      <?php foreach ($currencies as $c): ?>
        <form method="post" class="flex gap-16 mb-8" style="align-items:center;flex-wrap:wrap">
          <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
          <input type="hidden" name="entity" value="currency">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="code" value="<?= sanitize($c['code']) ?>">
          <code style="background:#222;padding:6px 12px;border-radius:4px;color:#fcb700"><?= sanitize($c['code']) ?></code>
          <input type="text" name="symbol" class="form-input" style="max-width:80px" value="<?= sanitize($c['symbol']) ?>" required>
          <input type="text" name="name" class="form-input" value="<?= sanitize($c['name']) ?>" required>
          <label>Курс: <input type="number" name="rate" step="0.000001" class="form-input" style="max-width:140px" value="<?= sanitize($c['rate']) ?>" required></label>
          <label><input type="checkbox" name="is_active" value="1" <?= $c['is_active']?'checked':'' ?>> Активна</label>
          <?php if ($c['is_default']): ?><span class="badge badge-primary">база</span><?php endif; ?>
          <button class="btn btn-primary btn-sm">Сохранить</button>
        </form>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
