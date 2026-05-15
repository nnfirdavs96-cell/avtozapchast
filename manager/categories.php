<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['manager', 'admin', 'superadmin']);

$db     = getDB();
$csrf   = generateCsrfToken();
$action = $_GET['action'] ?? 'list';
$editId = (int)($_GET['id'] ?? 0);
$errors = [];

// ── POST handler ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'Ошибка CSRF. Попробуйте снова.');
        redirect(APP_URL . '/manager/categories.php');
    }

    $postAction = $_POST['action'] ?? '';

    // Delete
    if ($postAction === 'delete') {
        $delId = (int)($_POST['id'] ?? 0);
        if ($delId) {
            $cnt = $db->prepare("SELECT COUNT(*) FROM parts WHERE category_id = ? AND is_active = 1");
            $cnt->execute([$delId]);
            if ((int)$cnt->fetchColumn() > 0) {
                flashMessage('danger', 'Нельзя удалить категорию — в ней есть привязанные товары.');
            } else {
                $db->prepare("UPDATE categories SET is_active = 0 WHERE id = ?")->execute([$delId]);
                flashMessage('success', 'Категория удалена.');
            }
        }
        redirect(APP_URL . '/manager/categories.php');
    }

    // Save
    $name         = trim($_POST['name'] ?? '');
    $slug         = trim($_POST['slug'] ?? '');
    $parentId     = (int)($_POST['parent_id'] ?? 0) ?: null;
    $desc         = trim($_POST['description'] ?? '') ?: null;
    $isActive     = (int)(!empty($_POST['is_active']));
    $sort         = (int)($_POST['sort_order'] ?? 0);
    $markupPct    = ($_POST['markup_percent'] ?? '') !== '' ? (float)str_replace(',', '.', $_POST['markup_percent']) : null;
    $cid          = (int)($_POST['id'] ?? 0);

    if (empty($name)) {
        $errors[] = 'Введите название категории.';
    }

    // Auto-generate slug from name if empty
    if (empty($slug) && !empty($name)) {
        $slug = mb_strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
    }

    if (empty($errors)) {
        $chk = $db->prepare("SELECT id FROM categories WHERE slug = ? AND id != ? LIMIT 1");
        $chk->execute([$slug, $cid]);
        if ($chk->fetch()) {
            $errors[] = 'Категория с таким слагом уже существует.';
        }
    }

    if (empty($errors)) {
        if ($cid) {
            $db->prepare(
                "UPDATE categories SET name=?, slug=?, parent_id=?, description=?, sort_order=?, is_active=?, markup_percent=? WHERE id=?"
            )->execute([$name, $slug, $parentId, $desc, $sort, $isActive, $markupPct, $cid]);
            flashMessage('success', 'Категория обновлена.');
        } else {
            $db->prepare(
                "INSERT INTO categories (name, slug, parent_id, description, sort_order, is_active, markup_percent)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            )->execute([$name, $slug, $parentId, $desc, $sort, 1, $markupPct]);
            flashMessage('success', 'Категория добавлена.');
        }
        redirect(APP_URL . '/manager/categories.php');
    }

    $action = $cid ? 'edit' : 'new';
    $editId = $cid;
}

// ── Load for edit ─────────────────────────────────────────────────────
$editCat = null;
if ($editId && $action === 'edit') {
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $editCat = $stmt->fetch();
}

// ── All categories ────────────────────────────────────────────────────
$allCats = $db->query(
    "SELECT c.*,
            (SELECT COUNT(*) FROM parts p WHERE p.category_id = c.id AND p.is_active = 1) AS part_count
     FROM categories c
     WHERE c.is_active = 1
     ORDER BY c.sort_order, c.name"
)->fetchAll();

$tree = getCategoryTree($allCats);

$pageTitle = 'Управление категориями';
require_once dirname(__DIR__) . '/includes/admin-header.php';

// Recursive tree renderer
function renderCatRows(array $cats, string $csrf, int $depth = 0): void {
    foreach ($cats as $cat) {
        $pad = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth);
        $prefix = $depth > 0 ? '↳ ' : '';
        echo '<tr>';
        echo '<td>' . $pad . $prefix . '<strong style="font-size:0.875rem;">' . htmlspecialchars((string)$cat['name']) . '</strong></td>';
        echo '<td><code style="font-size:0.75rem;color:#666;">' . htmlspecialchars((string)$cat['slug']) . '</code></td>';
        echo '<td style="text-align:center;">' . (int)$cat['part_count'] . '</td>';
        $mp = $cat['markup_percent'] !== null ? htmlspecialchars((string)$cat['markup_percent']) . '%' : '<span style="color:#bbb">—</span>';
        echo '<td style="text-align:center;">' . $mp . '</td>';
        echo '<td style="text-align:center;">' . (int)$cat['sort_order'] . '</td>';
        echo '<td style="text-align:center;white-space:nowrap;">';
        echo '<a href="?action=edit&id=' . (int)$cat['id'] . '" class="az-btn az-btn-secondary az-btn-sm">'
           . '<i class="fa fa-pencil"></i> Ред.</a> ';
        echo '<form method="POST" style="display:inline;" onsubmit="return confirm(\'Удалить категорию?\')">
                <input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf) . '">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="' . (int)$cat['id'] . '">
                <button type="submit" class="az-btn az-btn-danger az-btn-sm"><i class="fa fa-trash-o"></i></button>
              </form>';
        echo '</td>';
        echo '</tr>';
        if (!empty($cat['children'])) {
            renderCatRows($cat['children'], $csrf, $depth + 1);
        }
    }
}
?>

<div class="az-panel">

    <!-- ── Sidebar ─────────────────────────────────────────────────── -->
    <aside class="az-sidebar">
        <div class="az-sidebar-logo">AUTO<span>PARTS</span></div>
        <nav>
            <ul>
                <li><a href="<?= APP_URL ?>/manager/index.php"><i class="fa fa-dashboard"></i> <?= t('dashboard') ?></a></li>
                <li><a href="<?= APP_URL ?>/manager/parts.php"><i class="fa fa-cogs"></i> <?= t('parts_mgmt') ?></a></li>
                <li><a href="<?= APP_URL ?>/manager/categories.php" class="active"><i class="fa fa-sitemap"></i> <?= t('categories_mgmt') ?></a></li>
                <li><a href="<?= APP_URL ?>/manager/brands.php"><i class="fa fa-tag"></i> <?= t('brands_mgmt') ?></a></li>
                <li><a href="<?= APP_URL ?>/manager/blog.php"><i class="fa fa-newspaper-o"></i> Блог</a></li>
            <li><a href="<?= APP_URL ?>/manager/pages.php"><i class="fa fa-file-text-o"></i> Страницы</a></li>
            <li><a href="<?= APP_URL ?>/manager/reviews.php"><i class="fa fa-star"></i> Отзывы</a></li>
                <li style="border-top:1px solid rgba(255,255,255,0.1);margin-top:20px;">
                    <a href="<?= APP_URL ?>/index.php"><i class="fa fa-home"></i> На сайт</a>
                </li>
                <li>
                    <a href="<?= APP_URL ?>/auth/logout.php" style="color:rgba(255,100,100,0.85)!important;">
                        <i class="fa fa-sign-out"></i> <?= t('logout') ?>
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <!-- ── Main ───────────────────────────────────────────────────── -->
    <main class="az-main">
        <div class="az-topbar">
            <h1>
                <?= $action === 'new' ? 'Новая категория' : ($action === 'edit' ? 'Редактировать категорию' : 'Категории') ?>
            </h1>
            <div>
                <?php if ($action === 'list'): ?>
                    <a href="?action=new" class="az-btn az-btn-primary az-btn-sm">
                        <i class="fa fa-plus"></i> Добавить
                    </a>
                <?php else: ?>
                    <a href="<?= APP_URL ?>/manager/categories.php" class="az-btn az-btn-secondary az-btn-sm">
                        <i class="fa fa-arrow-left"></i> Список
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="az-content">

            <?php if ($action === 'new' || $action === 'edit'): ?>
            <!-- ── Form ─────────────────────────────────────────── -->
            <div style="max-width:600px;">
                <div class="az-card">
                    <h3><?= $action === 'edit' ? 'Редактировать категорию' : 'Новая категория' ?></h3>

                    <?php if (!empty($errors)): ?>
                        <div class="az-alert az-alert-danger">
                            <?php foreach ($errors as $err): ?>
                                <div><?= sanitize($err) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                        <input type="hidden" name="action" value="save">
                        <?php if ($editCat): ?>
                            <input type="hidden" name="id" value="<?= (int)$editCat['id'] ?>">
                        <?php endif; ?>

                        <div class="az-form-group">
                            <label>Название *</label>
                            <input type="text" name="name"
                                   value="<?= sanitize($editCat['name'] ?? ($_POST['name'] ?? '')) ?>"
                                   required placeholder="Двигатель">
                        </div>

                        <div class="az-form-group">
                            <label>Слаг (URL)</label>
                            <input type="text" name="slug"
                                   value="<?= sanitize($editCat['slug'] ?? ($_POST['slug'] ?? '')) ?>"
                                   placeholder="auto-generated">
                            <small style="color:#888;font-size:0.78rem;">Оставьте пустым — будет создан автоматически.</small>
                        </div>

                        <div class="az-form-group">
                            <label>Родительская категория</label>
                            <select name="parent_id">
                                <option value="">— Верхний уровень —</option>
                                <?php foreach ($allCats as $c):
                                    if ($editCat && $c['id'] == $editCat['id']) continue; // prevent self-parent
                                    if ($c['parent_id'] !== null) continue; // only top-level as parents
                                ?>
                                    <option value="<?= (int)$c['id'] ?>"
                                        <?= (($editCat['parent_id'] ?? null) == $c['id'] || ($_POST['parent_id'] ?? 0) == $c['id']) ? 'selected' : '' ?>>
                                        <?= sanitize($c['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                            <div class="az-form-group">
                                <label>Порядок сортировки</label>
                                <input type="number" name="sort_order" min="0"
                                       value="<?= (int)($editCat['sort_order'] ?? ($_POST['sort_order'] ?? 0)) ?>">
                            </div>
                            <div class="az-form-group" style="display:flex;align-items:flex-end;padding-bottom:2px;">
                                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400;margin:0;">
                                    <input type="checkbox" name="is_active" value="1"
                                           <?= (isset($editCat) ? $editCat['is_active'] : 1) ? 'checked' : '' ?>>
                                    Активна
                                </label>
                            </div>
                        </div>

                        <div class="az-form-group">
                            <label>Описание</label>
                            <textarea name="description" rows="3"
                                      placeholder="Краткое описание категории"><?= sanitize($editCat['description'] ?? ($_POST['description'] ?? '')) ?></textarea>
                        </div>

                        <div class="az-form-group">
                            <label>Наценка для категории (%)</label>
                            <input type="number" name="markup_percent" min="0" max="1000" step="0.01"
                                   value="<?= sanitize((string)($editCat['markup_percent'] ?? ($_POST['markup_percent'] ?? ''))) ?>"
                                   placeholder="Оставьте пустым — будет применена глобальная наценка">
                            <small style="color:#888;font-size:0.78rem;">
                                Применяется ко всем товарам этой категории, у которых не задана собственная наценка.
                            </small>
                        </div>

                        <div style="display:flex;gap:12px;">
                            <button type="submit" class="az-btn az-btn-primary">
                                <i class="fa fa-save"></i> <?= $action === 'edit' ? 'Сохранить' : 'Добавить' ?>
                            </button>
                            <a href="<?= APP_URL ?>/manager/categories.php" class="az-btn az-btn-secondary">Отмена</a>
                        </div>
                    </form>
                </div>
            </div>

            <?php else: ?>
            <!-- ── List ─────────────────────────────────────────── -->
            <div class="az-card" style="padding:0;overflow:hidden;">
                <div style="padding:16px 20px;border-bottom:1px solid #dee2e6;display:flex;align-items:center;justify-content:space-between;">
                    <strong>Всего активных: <?= count($allCats) ?></strong>
                    <a href="?action=new" class="az-btn az-btn-primary az-btn-sm">
                        <i class="fa fa-plus"></i> Добавить категорию
                    </a>
                </div>
                <div style="overflow-x:auto;">
                    <table class="az-table">
                        <thead>
                            <tr>
                                <th>Название</th>
                                <th>Слаг</th>
                                <th style="text-align:center;">Товаров</th>
                                <th style="text-align:center;">Наценка</th>
                                <th style="text-align:center;">Порядок</th>
                                <th style="text-align:center;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php renderCatRows($tree, $csrf); ?>
                            <?php if (empty($allCats)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center;color:#aaa;padding:30px;">Категорий нет.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /.az-content -->
    </main>
</div><!-- /.az-panel -->

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
