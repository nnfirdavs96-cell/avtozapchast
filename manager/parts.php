<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['manager', 'superadmin']);

$db     = getDB();
$csrf   = generateCsrfToken();
$action = $_GET['action'] ?? 'list';
$editId = (int)($_GET['id'] ?? 0);
$errors = [];

$brands     = getBrands();
$categories = getCategories();

// ── POST handler ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF error.'); redirect(APP_URL . '/manager/parts.php');
    }
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'delete') {
        $delId = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE parts SET is_active = 0 WHERE id = ?")->execute([$delId]);
        flashMessage('success', 'Товар удалён.');
        redirect(APP_URL . '/manager/parts.php');
    }

    // Add or Edit
    $pnum   = trim($_POST['part_number'] ?? '');
    $name   = trim($_POST['name'] ?? '');
    $desc   = trim($_POST['description'] ?? '');
    $brand  = (int)($_POST['brand_id'] ?? 0);
    $cat    = (int)($_POST['category_id'] ?? 0);
    $price  = (float)str_replace(',', '.', $_POST['price'] ?? 0);
    $stock  = (int)($_POST['stock'] ?? 0);
    $weight = $_POST['weight'] ? (float)str_replace(',', '.', $_POST['weight']) : null;
    $dims   = trim($_POST['dimensions'] ?? '');
    $pid    = (int)($_POST['id'] ?? 0);

    if (empty($pnum))   $errors[] = 'Укажите номер детали.';
    if (empty($name))   $errors[] = 'Укажите название.';
    if (!$brand)        $errors[] = 'Выберите бренд.';
    if (!$cat)          $errors[] = 'Выберите категорию.';
    if ($price <= 0)    $errors[] = 'Укажите корректную цену.';

    if (empty($errors)) {
        // Check part_number uniqueness
        $chkStmt = $db->prepare("SELECT id FROM parts WHERE part_number = ? AND id != ?");
        $chkStmt->execute([$pnum, $pid]);
        if ($chkStmt->fetch()) $errors[] = 'Такой номер детали уже существует.';
    }

    if (empty($errors)) {
        if ($pid) {
            $db->prepare(
                "UPDATE parts SET part_number=?, name=?, description=?, brand_id=?, category_id=?,
                 price=?, stock=?, weight=?, dimensions=?, updated_at=NOW() WHERE id=?"
            )->execute([$pnum, $name, $desc ?: null, $brand, $cat, $price, $stock, $weight, $dims ?: null, $pid]);
            flashMessage('success', 'Товар обновлён.');
        } else {
            $db->prepare(
                "INSERT INTO parts (part_number, name, description, brand_id, category_id, price, stock, weight, dimensions, images, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '[]', ?)"
            )->execute([$pnum, $name, $desc ?: null, $brand, $cat, $price, $stock, $weight, $dims ?: null, $_SESSION['user_id']]);
            flashMessage('success', 'Товар добавлен.');
        }
        redirect(APP_URL . '/manager/parts.php');
    }
    // If errors keep form open
    $action = $pid ? 'edit' : 'new';
    $editId = $pid;
}

// ── Edit: load part ───────────────────────────────────────────
$editPart = null;
if ($editId && in_array($action, ['edit'])) {
    $stmt = $db->prepare("SELECT * FROM parts WHERE id = ?");
    $stmt->execute([$editId]);
    $editPart = $stmt->fetch();
}

// ── List with search ──────────────────────────────────────────
$search  = trim($_GET['search'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$where   = ['p.is_active = 1'];
$params  = [];
if ($search) {
    $where[]  = '(p.part_number LIKE ? OR p.name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$whereSQL = 'WHERE ' . implode(' AND ', $where);

$cntStmt = $db->prepare("SELECT COUNT(*) FROM parts p $whereSQL");
$cntStmt->execute($params);
$total  = (int)$cntStmt->fetchColumn();
$pages  = max(1, ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$partsStmt = $db->prepare(
    "SELECT p.*, b.name AS brand_name, c.name AS category_name
     FROM parts p LEFT JOIN brands b ON b.id = p.brand_id LEFT JOIN categories c ON c.id = p.category_id
     $whereSQL ORDER BY p.created_at DESC LIMIT $perPage OFFSET $offset"
);
$partsStmt->execute($params);
$parts = $partsStmt->fetchAll();

$pageTitle = 'Управление товарами';
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/nav.php';
?>

<div class="dash-layout">
  <div class="dash-sidebar"><?php renderNav(); ?></div>
  <div class="dash-main">
    <div class="dash-heading flex-between" style="font-size:1.5rem;">
      ТОВАРЫ
      <?php if ($action === 'list'): ?>
        <a href="?action=new" class="btn btn-primary btn-sm">+ Добавить</a>
      <?php else: ?>
        <a href="<?= APP_URL ?>/manager/parts.php" class="btn btn-outline btn-sm">← Список</a>
      <?php endif; ?>
    </div>

    <?php if ($action === 'new' || $action === 'edit'): ?>
    <!-- Form -->
    <div style="max-width:760px;">
      <div class="card">
        <div class="card-header">
          <h3><?= $action === 'edit' ? 'РЕДАКТИРОВАТЬ ТОВАР' : 'НОВЫЙ ТОВАР' ?></h3>
        </div>
        <div class="card-body">
          <?php if (!empty($errors)): ?>
          <div class="alert alert-danger mb-16">
            <?php foreach ($errors as $e): ?><div>• <?= sanitize($e) ?></div><?php endforeach; ?>
          </div>
          <?php endif; ?>

          <form method="post" action="<?= APP_URL ?>/manager/parts.php">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="action" value="<?= $action === 'edit' ? 'edit' : 'add' ?>">
            <?php if ($editPart): ?><input type="hidden" name="id" value="<?= $editPart['id'] ?>"><?php endif; ?>

            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Номер детали *</label>
                <input type="text" name="part_number" class="form-input"
                       value="<?= sanitize($editPart['part_number'] ?? ($_POST['part_number'] ?? '')) ?>"
                       placeholder="BKR6EK" required>
              </div>
              <div class="form-group">
                <label class="form-label">Цена (₽) *</label>
                <input type="number" name="price" class="form-input" step="0.01" min="0"
                       value="<?= sanitize($editPart['price'] ?? ($_POST['price'] ?? '')) ?>"
                       placeholder="1500.00" required>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Название *</label>
              <input type="text" name="name" class="form-input"
                     value="<?= sanitize($editPart['name'] ?? ($_POST['name'] ?? '')) ?>"
                     placeholder="Свеча зажигания NGK BKR6EK" required>
            </div>

            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Бренд *</label>
                <select name="brand_id" class="form-select" required>
                  <option value="">— Выберите бренд —</option>
                  <?php foreach ($brands as $b): ?>
                  <option value="<?= $b['id'] ?>" <?= ((int)($editPart['brand_id'] ?? $_POST['brand_id'] ?? 0)) === (int)$b['id'] ? 'selected' : '' ?>>
                    <?= sanitize($b['name']) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Категория *</label>
                <select name="category_id" class="form-select" required>
                  <option value="">— Выберите категорию —</option>
                  <?php foreach ($categories as $c): ?>
                  <option value="<?= $c['id'] ?>" <?= ((int)($editPart['category_id'] ?? $_POST['category_id'] ?? 0)) === (int)$c['id'] ? 'selected' : '' ?>>
                    <?= $c['parent_id'] ? '&nbsp;&nbsp;↳ ' : '' ?><?= sanitize($c['name']) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Остаток (шт)</label>
                <input type="number" name="stock" class="form-input" min="0"
                       value="<?= sanitize($editPart['stock'] ?? ($_POST['stock'] ?? 0)) ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Вес (кг)</label>
                <input type="text" name="weight" class="form-input"
                       value="<?= sanitize($editPart['weight'] ?? ($_POST['weight'] ?? '')) ?>"
                       placeholder="0.250">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Размеры (LxWxH мм)</label>
              <input type="text" name="dimensions" class="form-input"
                     value="<?= sanitize($editPart['dimensions'] ?? ($_POST['dimensions'] ?? '')) ?>"
                     placeholder="90x45x38">
            </div>

            <div class="form-group">
              <label class="form-label">Описание</label>
              <textarea name="description" class="form-textarea" rows="4"
                        placeholder="Подробное описание товара..."><?= sanitize($editPart['description'] ?? ($_POST['description'] ?? '')) ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary">
              <?= $action === 'edit' ? 'СОХРАНИТЬ' : 'ДОБАВИТЬ ТОВАР' ?>
            </button>
          </form>
        </div>
      </div>
    </div>

    <?php else: // LIST ?>

    <!-- Search -->
    <form method="get" class="flex gap-8 mb-16">
      <input type="text" name="search" class="form-input" style="max-width:300px;" placeholder="Номер детали или название..." value="<?= sanitize($search) ?>">
      <button type="submit" class="btn btn-outline btn-sm">Найти</button>
      <?php if ($search): ?><a href="<?= APP_URL ?>/manager/parts.php" class="btn btn-outline btn-sm">Сбросить</a><?php endif; ?>
      <span style="margin-left:auto;font-family:var(--font-mono);font-size:0.75rem;color:var(--text-muted);align-self:center;">Всего: <?= $total ?></span>
    </form>

    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>Артикул</th><th>Название</th><th>Бренд</th><th>Категория</th><th style="text-align:right;">Цена</th><th style="text-align:center;">Остаток</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($parts as $p):
            $st = getStockStatus((int)$p['stock']);
          ?>
          <tr>
            <td><span class="mono"><?= sanitize($p['part_number']) ?></span></td>
            <td style="font-size:0.875rem;"><?= sanitize(truncate($p['name'], 45)) ?></td>
            <td style="color:var(--text-muted);font-size:0.8rem;"><?= sanitize($p['brand_name']) ?></td>
            <td style="color:var(--text-muted);font-size:0.8rem;"><?= sanitize($p['category_name']) ?></td>
            <td style="text-align:right;font-family:var(--font-mono);color:var(--accent);"><?= formatPrice($p['price']) ?></td>
            <td style="text-align:center;"><span class="badge badge-<?= $st['class'] ?>"><?= $p['stock'] ?></span></td>
            <td>
              <a href="?action=edit&id=<?= $p['id'] ?>" class="btn btn-outline btn-sm">Ред.</a>
              <form method="post" action="" style="display:inline;" onsubmit="return confirm('Удалить товар?')">
                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Удалить</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): ?>
    <div class="pagination">
      <?php for ($pg = 1; $pg <= $pages; $pg++): $q = array_merge($_GET, ['page' => $pg, 'action' => 'list']); ?>
      <a href="?<?= http_build_query($q) ?>" class="page-link <?= $pg == $page ? 'active' : '' ?>"><?= $pg ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
