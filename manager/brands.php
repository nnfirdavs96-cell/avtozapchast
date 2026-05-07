<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_layout.php';
requireRole(['manager','admin','superadmin']);

$db = getDB();
if ($_SERVER['REQUEST_METHOD']==='POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $a = $_POST['action'] ?? '';
    if ($a==='create') $db->prepare("INSERT INTO brands (name,slug,country,description,is_active) VALUES (?,?,?,?,1)")
        ->execute([trim($_POST['name']), trim($_POST['slug']), trim($_POST['country'] ?? '') ?: null, trim($_POST['description'] ?? '') ?: null]);
    if ($a==='update') $db->prepare("UPDATE brands SET name=?, slug=?, country=?, description=?, is_active=? WHERE id=?")
        ->execute([trim($_POST['name']), trim($_POST['slug']), trim($_POST['country'] ?? '') ?: null, trim($_POST['description'] ?? '') ?: null, isset($_POST['is_active'])?1:0, (int)$_POST['id']]);
    if ($a==='delete') $db->prepare("UPDATE brands SET is_active=0 WHERE id=?")->execute([(int)$_POST['id']]);
    redirect(APP_URL . '/manager/brands.php');
}
$editId = (int)($_GET['edit'] ?? 0);
$editing = $editId ? $db->query("SELECT * FROM brands WHERE id={$editId}")->fetch() : null;
$brands = $db->query("SELECT * FROM brands ORDER BY name")->fetchAll();

$pageTitle = 'Бренды';
$adminArea = true;
require_once __DIR__ . '/../includes/header.php';
?>
<div class="dash-layout">
  <?php renderAdminSidebar(); ?>
  <div class="dash-main">
    <h1 class="dash-heading">Бренды</h1>
    <div class="admin-card mb-32">
      <h3 style="margin-bottom:14px"><?= $editing?'Редактировать':'Добавить бренд' ?></h3>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
        <input type="hidden" name="action" value="<?= $editing?'update':'create' ?>">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
        <div class="form-row">
          <input type="text" name="name" class="form-input" placeholder="Название" required value="<?= sanitize($editing['name'] ?? '') ?>">
          <input type="text" name="slug" class="form-input" placeholder="slug" required value="<?= sanitize($editing['slug'] ?? '') ?>">
          <input type="text" name="country" class="form-input" placeholder="Страна" value="<?= sanitize($editing['country'] ?? '') ?>">
        </div>
        <textarea name="description" class="form-textarea mt-8" placeholder="Описание"><?= sanitize($editing['description'] ?? '') ?></textarea>
        <?php if ($editing): ?>
          <label class="mt-8" style="display:block"><input type="checkbox" name="is_active" value="1" <?= (int)$editing['is_active']?'checked':'' ?>> Активен</label>
        <?php endif; ?>
        <button class="btn btn-primary mt-16"><?= $editing?'Сохранить':'Добавить' ?></button>
        <?php if ($editing): ?><a href="?" class="btn btn-outline">Отмена</a><?php endif; ?>
      </form>
    </div>

    <div class="admin-card">
      <h3 style="margin-bottom:14px">Все бренды (<?= count($brands) ?>)</h3>
      <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>Название</th><th>Страна</th><th>Slug</th><th>Активен</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($brands as $b): ?>
          <tr>
            <td><strong><?= sanitize($b['name']) ?></strong></td>
            <td><?= sanitize($b['country'] ?? '—') ?></td>
            <td><span class="mono"><?= sanitize($b['slug']) ?></span></td>
            <td><span class="badge badge-<?= $b['is_active']?'success':'danger' ?>"><?= $b['is_active']?'Да':'Нет' ?></span></td>
            <td class="actions">
              <a href="?edit=<?= (int)$b['id'] ?>" class="btn btn-outline btn-sm">✏</a>
              <form method="post" onsubmit="return confirm('Деактивировать?')" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                <button class="btn btn-outline btn-sm">🗑</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
