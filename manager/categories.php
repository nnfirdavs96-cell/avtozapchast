<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['manager', 'superadmin']);

$db     = getDB();
$csrf   = generateCsrfToken();
$action = $_GET['action'] ?? 'list';
$editId = (int)($_GET['id'] ?? 0);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF error.'); redirect(APP_URL . '/manager/categories.php');
    }
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'delete') {
        $delId = (int)($_POST['id'] ?? 0);
        // Check no parts assigned
        $cnt = $db->prepare("SELECT COUNT(*) FROM parts WHERE category_id = ? AND is_active = 1");
        $cnt->execute([$delId]);
        if ((int)$cnt->fetchColumn() > 0) {
            flashMessage('danger', 'Нельзя удалить категорию — есть привязанные товары.');
        } else {
            $db->prepare("UPDATE categories SET is_active = 0 WHERE id = ?")->execute([$delId]);
            flashMessage('success', 'Категория удалена.');
        }
        redirect(APP_URL . '/manager/categories.php');
    }

    $name     = trim($_POST['name'] ?? '');
    $slug     = trim($_POST['slug'] ?? '');
    $parentId = (int)($_POST['parent_id'] ?? 0) ?: null;
    $desc     = trim($_POST['description'] ?? '');
    $sort     = (int)($_POST['sort_order'] ?? 0);
    $cid      = (int)($_POST['id'] ?? 0);

    if (empty($name)) $errors[] = 'Укажите название.';
    if (empty($slug)) {
        $slug = mb_strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
    }

    if (empty($errors)) {
        $chk = $db->prepare("SELECT id FROM categories WHERE slug = ? AND id != ?");
        $chk->execute([$slug, $cid]);
        if ($chk->fetch()) $errors[] = 'Такой слаг уже существует.';
    }

    if (empty($errors)) {
        if ($cid) {
            $db->prepare("UPDATE categories SET name=?, slug=?, parent_id=?, description=?, sort_order=? WHERE id=?")
               ->execute([$name, $slug, $parentId, $desc ?: null, $sort, $cid]);
            flashMessage('success', 'Категория обновлена.');
        } else {
            $db->prepare("INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES (?,?,?,?,?)")
               ->execute([$name, $slug, $parentId, $desc ?: null, $sort]);
            flashMessage('success', 'Категория добавлена.');
        }
        redirect(APP_URL . '/manager/categories.php');
    }
    $action = $cid ? 'edit' : 'new';
    $editId = $cid;
}

// Load for edit
$editCat = null;
if ($editId && $action === 'edit') {
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$editId]);
    $editCat = $stmt->fetch();
}

// All categories for tree and parent select
$allCats = $db->query("SELECT c.*, (SELECT COUNT(*) FROM parts p WHERE p.category_id = c.id AND p.is_active=1) AS part_count FROM categories c WHERE c.is_active = 1 ORDER BY c.sort_order, c.name")->fetchAll();
$tree    = getCategoryTree($allCats);

$pageTitle = 'Категории';
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/nav.php';

function renderCatTree(array $cats, PDO $db, string $csrf, string $appUrl, int $depth = 0): void {
    foreach ($cats as $cat) {
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth);
        echo '<tr>';
        echo '<td>' . $indent . ($depth > 0 ? '↳ ' : '') . '<strong style="font-size:0.875rem;">' . htmlspecialchars($cat['name']) . '</strong></td>';
        echo '<td><span style="font-family:var(--font-mono);font-size:0.72rem;color:var(--text-muted);">' . htmlspecialchars($cat['slug']) . '</span></td>';
        echo '<td style="text-align:center;font-family:var(--font-mono);">' . $cat['part_count'] . '</td>';
        echo '<td style="text-align:center;">' . $cat['sort_order'] . '</td>';
        echo '<td>';
        echo '<a href="?action=edit&id=' . $cat['id'] . '" class="btn btn-outline btn-sm">Ред.</a> ';
        echo '<form method="post" action="" style="display:inline;" onsubmit="return confirm(\'Удалить?\')">
          <input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf) . '">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="' . $cat['id'] . '">
          <button type="submit" class="btn btn-danger btn-sm">Удалить</button>
        </form>';
        echo '</td>';
        echo '</tr>';
        if (!empty($cat['children'])) {
            renderCatTree($cat['children'], $db, $csrf, $appUrl, $depth + 1);
        }
    }
}
?>

<div class="dash-layout">
  <div class="dash-sidebar"><?php renderNav(); ?></div>
  <div class="dash-main">
    <div class="dash-heading flex-between" style="font-size:1.5rem;">
      КАТЕГОРИИ
      <?php if ($action === 'list'): ?>
        <a href="?action=new" class="btn btn-primary btn-sm">+ Добавить</a>
      <?php else: ?>
        <a href="<?= APP_URL ?>/manager/categories.php" class="btn btn-outline btn-sm">← Список</a>
      <?php endif; ?>
    </div>

    <?php if ($action === 'new' || $action === 'edit'): ?>
    <div style="max-width:600px;">
      <div class="card">
        <div class="card-header"><h3><?= $action === 'edit' ? 'РЕДАКТИРОВАТЬ' : 'НОВАЯ КАТЕГОРИЯ' ?></h3></div>
        <div class="card-body">
          <?php if (!empty($errors)): ?>
          <div class="alert alert-danger mb-16">
            <?php foreach ($errors as $e): ?><div>• <?= sanitize($e) ?></div><?php endforeach; ?>
          </div>
          <?php endif; ?>
          <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="action" value="<?= $action === 'edit' ? 'edit' : 'add' ?>">
            <?php if ($editCat): ?><input type="hidden" name="id" value="<?= $editCat['id'] ?>"><?php endif; ?>

            <div class="form-group">
              <label class="form-label">Название *</label>
              <input type="text" name="name" class="form-input" value="<?= sanitize($editCat['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Слаг (URL)</label>
              <input type="text" name="slug" class="form-input" value="<?= sanitize($editCat['slug'] ?? '') ?>" placeholder="auto-generated">
            </div>
            <div class="form-group">
              <label class="form-label">Родительская категория</label>
              <select name="parent_id" class="form-select">
                <option value="">— Верхний уровень —</option>
                <?php foreach ($allCats as $c): if ($c['parent_id'] !== null) continue; ?>
                <option value="<?= $c['id'] ?>" <?= ($editCat['parent_id'] ?? null) == $c['id'] ? 'selected' : '' ?>>
                  <?= sanitize($c['name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Порядок сортировки</label>
                <input type="number" name="sort_order" class="form-input" value="<?= $editCat['sort_order'] ?? 0 ?>">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Описание</label>
              <textarea name="description" class="form-textarea" rows="3"><?= sanitize($editCat['description'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary"><?= $action === 'edit' ? 'СОХРАНИТЬ' : 'ДОБАВИТЬ' ?></button>
          </form>
        </div>
      </div>
    </div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Название</th><th>Слаг</th><th style="text-align:center;">Товаров</th><th style="text-align:center;">Порядок</th><th></th></tr></thead>
        <tbody>
          <?php renderCatTree($tree, $db, $csrf, APP_URL); ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
