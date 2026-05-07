<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_layout.php';
requireRole(['superadmin']);

$db = getDB();
if ($_SERVER['REQUEST_METHOD']==='POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $a = $_POST['action'] ?? '';
    if ($a==='create') $db->prepare("INSERT INTO payment_methods (code,name,description,sort_order,is_active) VALUES (?,?,?,?,1)")
        ->execute([trim($_POST['code']), trim($_POST['name']), trim($_POST['description'] ?? ''), (int)($_POST['sort_order'] ?? 0)]);
    if ($a==='update') $db->prepare("UPDATE payment_methods SET name=?, description=?, sort_order=?, is_active=? WHERE id=?")
        ->execute([trim($_POST['name']), trim($_POST['description'] ?? ''), (int)($_POST['sort_order'] ?? 0), isset($_POST['is_active'])?1:0, (int)$_POST['id']]);
    if ($a==='delete') $db->prepare("DELETE FROM payment_methods WHERE id=?")->execute([(int)$_POST['id']]);
    redirect(APP_URL . '/superadmin/payment.php');
}
$items = $db->query("SELECT * FROM payment_methods ORDER BY sort_order, name")->fetchAll();
$pageTitle = 'Способы оплаты';
$adminArea = true;
require_once __DIR__ . '/../includes/header.php';
?>
<div class="dash-layout">
  <?php renderAdminSidebar(); ?>
  <div class="dash-main">
    <h1 class="dash-heading">Способы оплаты</h1>

    <div class="admin-card mb-32">
      <h3 style="margin-bottom:14px">Добавить способ</h3>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
        <input type="hidden" name="action" value="create">
        <div class="form-row">
          <input type="text" name="code" class="form-input" placeholder="код" required>
          <input type="text" name="name" class="form-input" placeholder="Название" required>
          <input type="number" name="sort_order" class="form-input" placeholder="Сортировка" value="0">
        </div>
        <textarea name="description" class="form-textarea mt-8" placeholder="Описание"></textarea>
        <button class="btn btn-primary mt-16">Добавить</button>
      </form>
    </div>

    <?php foreach ($items as $p): ?>
      <div class="admin-card mb-16">
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <div class="flex-between mb-16">
            <h4><?= sanitize($p['code']) ?></h4>
            <label><input type="checkbox" name="is_active" value="1" <?= $p['is_active']?'checked':'' ?>> Активен</label>
          </div>
          <div class="form-row">
            <input type="text" name="name" class="form-input" value="<?= sanitize($p['name']) ?>" required>
            <input type="number" name="sort_order" class="form-input" value="<?= (int)$p['sort_order'] ?>">
          </div>
          <textarea name="description" class="form-textarea mt-8"><?= sanitize($p['description'] ?? '') ?></textarea>
          <div class="mt-16" style="display:flex;gap:8px">
            <button class="btn btn-primary btn-sm">Сохранить</button>
            <button formaction="?" formmethod="post" name="action" value="delete" onclick="return confirm('Удалить?')" class="btn btn-outline btn-sm">🗑 Удалить</button>
          </div>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
