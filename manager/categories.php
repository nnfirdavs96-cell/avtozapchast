<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_layout.php';
requireRole(['manager','admin','superadmin']);

$db = getDB();
if ($_SERVER['REQUEST_METHOD']==='POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $a = $_POST['action'] ?? '';
    if ($a==='create') {
        $db->prepare("INSERT INTO categories (name,slug,parent_id,description,sort_order,is_active) VALUES (?,?,?,?,?,1)")
            ->execute([trim($_POST['name']), trim($_POST['slug']), ($_POST['parent_id'] ?: null), trim($_POST['description'] ?? '') ?: null, (int)($_POST['sort_order'] ?? 0)]);
    }
    if ($a==='update') {
        $db->prepare("UPDATE categories SET name=?, slug=?, parent_id=?, description=?, sort_order=?, is_active=? WHERE id=?")
            ->execute([trim($_POST['name']), trim($_POST['slug']), ($_POST['parent_id'] ?: null), trim($_POST['description'] ?? '') ?: null, (int)($_POST['sort_order'] ?? 0), isset($_POST['is_active'])?1:0, (int)$_POST['id']]);
    }
    if ($a==='delete') $db->prepare("UPDATE categories SET is_active=0 WHERE id=?")->execute([(int)$_POST['id']]);
    redirect(APP_URL . '/manager/categories.php');
}
$editId = (int)($_GET['edit'] ?? 0);
$editing = $editId ? $db->query("SELECT * FROM categories WHERE id={$editId}")->fetch() : null;
$cats = $db->query("SELECT c.*, p.name AS parent_name FROM categories c LEFT JOIN categories p ON p.id=c.parent_id ORDER BY c.parent_id IS NULL DESC, c.sort_order, c.name")->fetchAll();

$pageTitle = 'Категории';
$adminArea = true;
require_once __DIR__ . '/../includes/header.php';
?>
<div class="dash-layout">
  <?php renderAdminSidebar(); ?>
  <div class="dash-main">
    <h1 class="dash-heading">Категории <span class="dash-heading-badge">catalog</span></h1>

    <div class="admin-card mb-32">
      <h3 style="margin-bottom:14px"><?= $editing?'Редактировать':'Добавить категорию' ?></h3>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
        <input type="hidden" name="action" value="<?= $editing?'update':'create' ?>">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
        <div class="form-row">
          <input type="text" name="name" class="form-input" placeholder="Название" required value="<?= sanitize($editing['name'] ?? '') ?>">
          <input type="text" name="slug" class="form-input" placeholder="slug" required value="<?= sanitize($editing['slug'] ?? '') ?>">
        </div>
        <div class="form-row mt-8">
          <select name="parent_id" class="form-select">
            <option value="">— Корневая —</option>
            <?php foreach ($cats as $c): if ($c['parent_id']) continue; if ($editing && $c['id']==$editing['id']) continue; ?>
              <option value="<?= (int)$c['id'] ?>" <?= ($editing['parent_id'] ?? 0)==(int)$c['id']?'selected':'' ?>><?= sanitize($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="number" name="sort_order" class="form-input" placeholder="Сортировка" value="<?= sanitize($editing['sort_order'] ?? 0) ?>">
        </div>
        <textarea name="description" class="form-textarea mt-8" placeholder="Описание"><?= sanitize($editing['description'] ?? '') ?></textarea>
        <?php if ($editing): ?>
          <label class="mt-8" style="display:block"><input type="checkbox" name="is_active" value="1" <?= (int)$editing['is_active']?'checked':'' ?>> Активна</label>
        <?php endif; ?>
        <button class="btn btn-primary mt-16"><?= $editing?'Сохранить':'Добавить' ?></button>
        <?php if ($editing): ?><a href="?" class="btn btn-outline">Отмена</a><?php endif; ?>
      </form>
    </div>

    <div class="admin-card">
      <h3 style="margin-bottom:14px">Все категории</h3>
      <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>Название</th><th>Slug</th><th>Родитель</th><th>Активна</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($cats as $c): ?>
          <tr>
            <td><?= $c['parent_id']?'↳ ':'' ?><strong><?= sanitize($c['name']) ?></strong></td>
            <td><span class="mono"><?= sanitize($c['slug']) ?></span></td>
            <td><?= sanitize($c['parent_name'] ?? '—') ?></td>
            <td><span class="badge badge-<?= $c['is_active']?'success':'danger' ?>"><?= $c['is_active']?'Да':'Нет' ?></span></td>
            <td class="actions">
              <a href="?edit=<?= (int)$c['id'] ?>" class="btn btn-outline btn-sm">✏</a>
              <form method="post" onsubmit="return confirm('Деактивировать?')" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
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
