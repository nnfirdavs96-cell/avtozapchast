<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_layout.php';
requireRole(['superadmin']);

$db = getDB();
if ($_SERVER['REQUEST_METHOD']==='POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $a = $_POST['action'] ?? '';
    if ($a==='create') $db->prepare("INSERT INTO delivery_methods (code,name,description,cost,eta_days,sort_order,is_active) VALUES (?,?,?,?,?,?,1)")
        ->execute([trim($_POST['code']), trim($_POST['name']), trim($_POST['description'] ?? ''), (float)$_POST['cost'], trim($_POST['eta_days'] ?? ''), (int)($_POST['sort_order'] ?? 0)]);
    if ($a==='update') $db->prepare("UPDATE delivery_methods SET name=?, description=?, cost=?, eta_days=?, sort_order=?, is_active=? WHERE id=?")
        ->execute([trim($_POST['name']), trim($_POST['description'] ?? ''), (float)$_POST['cost'], trim($_POST['eta_days'] ?? ''), (int)($_POST['sort_order'] ?? 0), isset($_POST['is_active'])?1:0, (int)$_POST['id']]);
    if ($a==='delete') $db->prepare("DELETE FROM delivery_methods WHERE id=?")->execute([(int)$_POST['id']]);
    redirect(APP_URL . '/superadmin/delivery.php');
}
$items = $db->query("SELECT * FROM delivery_methods ORDER BY sort_order, name")->fetchAll();
$pageTitle = 'Способы доставки';
$adminArea = true;
require_once __DIR__ . '/../includes/header.php';
?>
<div class="dash-layout">
  <?php renderAdminSidebar(); ?>
  <div class="dash-main">
    <h1 class="dash-heading">Способы доставки</h1>

    <div class="admin-card mb-32">
      <h3 style="margin-bottom:14px">Добавить способ</h3>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
        <input type="hidden" name="action" value="create">
        <div class="form-row">
          <input type="text" name="code" class="form-input" placeholder="код (slug)" required>
          <input type="text" name="name" class="form-input" placeholder="Название" required>
        </div>
        <div class="form-row mt-8">
          <input type="number" name="cost" step="0.01" class="form-input" placeholder="Стоимость" required>
          <input type="text" name="eta_days" class="form-input" placeholder="Срок (например, 1-3 дня)">
          <input type="number" name="sort_order" class="form-input" placeholder="Сортировка" value="0">
        </div>
        <textarea name="description" class="form-textarea mt-8" placeholder="Описание"></textarea>
        <button class="btn btn-primary mt-16">Добавить</button>
      </form>
    </div>

    <?php foreach ($items as $d): ?>
      <div class="admin-card mb-16">
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
          <div class="flex-between mb-16">
            <h4><?= sanitize($d['code']) ?></h4>
            <label><input type="checkbox" name="is_active" value="1" <?= $d['is_active']?'checked':'' ?>> Активен</label>
          </div>
          <div class="form-row">
            <input type="text" name="name" class="form-input" value="<?= sanitize($d['name']) ?>" required>
            <input type="number" name="cost" step="0.01" class="form-input" value="<?= sanitize($d['cost']) ?>" required>
            <input type="text" name="eta_days" class="form-input" value="<?= sanitize($d['eta_days'] ?? '') ?>">
            <input type="number" name="sort_order" class="form-input" value="<?= (int)$d['sort_order'] ?>">
          </div>
          <textarea name="description" class="form-textarea mt-8"><?= sanitize($d['description'] ?? '') ?></textarea>
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
