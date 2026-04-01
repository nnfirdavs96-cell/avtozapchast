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
        flashMessage('danger', 'CSRF error.'); redirect(APP_URL . '/manager/brands.php');
    }
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'delete') {
        $delId = (int)($_POST['id'] ?? 0);
        $cnt = $db->prepare("SELECT COUNT(*) FROM parts WHERE brand_id = ? AND is_active = 1");
        $cnt->execute([$delId]);
        if ((int)$cnt->fetchColumn() > 0) {
            flashMessage('danger', 'Нельзя удалить бренд — есть привязанные товары.');
        } else {
            $db->prepare("UPDATE brands SET is_active = 0 WHERE id = ?")->execute([$delId]);
            flashMessage('success', 'Бренд удалён.');
        }
        redirect(APP_URL . '/manager/brands.php');
    }

    $name    = trim($_POST['name'] ?? '');
    $slug    = trim($_POST['slug'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $desc    = trim($_POST['description'] ?? '');
    $bid     = (int)($_POST['id'] ?? 0);

    if (empty($name)) $errors[] = 'Укажите название бренда.';
    if (empty($slug)) $slug = mb_strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));

    if (empty($errors)) {
        $chk = $db->prepare("SELECT id FROM brands WHERE slug = ? AND id != ?");
        $chk->execute([$slug, $bid]);
        if ($chk->fetch()) $errors[] = 'Такой слаг уже существует.';
    }

    if (empty($errors)) {
        if ($bid) {
            $db->prepare("UPDATE brands SET name=?, slug=?, country=?, description=? WHERE id=?")
               ->execute([$name, $slug, $country ?: null, $desc ?: null, $bid]);
            flashMessage('success', 'Бренд обновлён.');
        } else {
            $db->prepare("INSERT INTO brands (name, slug, country, description) VALUES (?,?,?,?)")
               ->execute([$name, $slug, $country ?: null, $desc ?: null]);
            flashMessage('success', 'Бренд добавлен.');
        }
        redirect(APP_URL . '/manager/brands.php');
    }
    $action = $bid ? 'edit' : 'new';
    $editId = $bid;
}

$editBrand = null;
if ($editId && $action === 'edit') {
    $stmt = $db->prepare("SELECT * FROM brands WHERE id = ?");
    $stmt->execute([$editId]);
    $editBrand = $stmt->fetch();
}

$brands = $db->query(
    "SELECT b.*, (SELECT COUNT(*) FROM parts p WHERE p.brand_id = b.id AND p.is_active = 1) AS part_count
     FROM brands b WHERE b.is_active = 1 ORDER BY b.name"
)->fetchAll();

$pageTitle = 'Бренды';
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/nav.php';
?>

<div class="dash-layout">
  <div class="dash-sidebar"><?php renderNav(); ?></div>
  <div class="dash-main">
    <div class="dash-heading flex-between" style="font-size:1.5rem;">
      БРЕНДЫ
      <?php if ($action === 'list'): ?>
        <a href="?action=new" class="btn btn-primary btn-sm">+ Добавить</a>
      <?php else: ?>
        <a href="<?= APP_URL ?>/manager/brands.php" class="btn btn-outline btn-sm">← Список</a>
      <?php endif; ?>
    </div>

    <?php if ($action === 'new' || $action === 'edit'): ?>
    <div style="max-width:540px;">
      <div class="card">
        <div class="card-header"><h3><?= $action === 'edit' ? 'РЕДАКТИРОВАТЬ БРЕНД' : 'НОВЫЙ БРЕНД' ?></h3></div>
        <div class="card-body">
          <?php if (!empty($errors)): ?>
          <div class="alert alert-danger mb-16">
            <?php foreach ($errors as $e): ?><div>• <?= sanitize($e) ?></div><?php endforeach; ?>
          </div>
          <?php endif; ?>
          <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="action" value="<?= $action === 'edit' ? 'edit' : 'add' ?>">
            <?php if ($editBrand): ?><input type="hidden" name="id" value="<?= $editBrand['id'] ?>"><?php endif; ?>

            <div class="form-group">
              <label class="form-label">Название бренда *</label>
              <input type="text" name="name" class="form-input" value="<?= sanitize($editBrand['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Слаг (URL)</label>
              <input type="text" name="slug" class="form-input" value="<?= sanitize($editBrand['slug'] ?? '') ?>" placeholder="auto-generated">
            </div>
            <div class="form-group">
              <label class="form-label">Страна производителя</label>
              <input type="text" name="country" class="form-input" value="<?= sanitize($editBrand['country'] ?? '') ?>" placeholder="Германия">
            </div>
            <div class="form-group">
              <label class="form-label">Описание</label>
              <textarea name="description" class="form-textarea" rows="3"><?= sanitize($editBrand['description'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary"><?= $action === 'edit' ? 'СОХРАНИТЬ' : 'ДОБАВИТЬ' ?></button>
          </form>
        </div>
      </div>
    </div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>#</th><th>Название</th><th>Слаг</th><th>Страна</th><th style="text-align:center;">Товаров</th><th>Описание</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($brands as $b): ?>
          <tr>
            <td><span class="mono"><?= $b['id'] ?></span></td>
            <td style="font-weight:600;font-size:0.875rem;"><?= sanitize($b['name']) ?></td>
            <td style="font-family:var(--font-mono);font-size:0.72rem;color:var(--text-muted);"><?= sanitize($b['slug']) ?></td>
            <td style="color:var(--text-muted);font-size:0.825rem;"><?= sanitize($b['country'] ?? '—') ?></td>
            <td style="text-align:center;font-family:var(--font-mono);"><?= $b['part_count'] ?></td>
            <td style="font-size:0.8rem;color:var(--text-muted);"><?= sanitize(truncate($b['description'] ?? '', 60)) ?></td>
            <td>
              <a href="?action=edit&id=<?= $b['id'] ?>" class="btn btn-outline btn-sm">Ред.</a>
              <form method="post" action="" style="display:inline;" onsubmit="return confirm('Удалить бренд?')">
                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $b['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Удалить</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
