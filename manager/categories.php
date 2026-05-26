<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['manager', 'admin', 'superadmin']);
requirePermission('categories');

$db     = getDB();
$csrf   = generateCsrfToken();
$action = $_GET['action'] ?? 'list';
$editId = (int)($_GET['id'] ?? 0);
$errors = [];

// Separate mobile image column (added on demand, portable across MariaDB / MySQL 8.0).
dbAddColumnIfMissing($db, 'categories', 'image_path_mobile', "`image_path_mobile` VARCHAR(500) NOT NULL DEFAULT '' AFTER `image_path`");

// Заполняем подкатегории под основными категориями (однократно, идемпотентно).
seedCategorySubcategories();

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
    $imagePath    = trim($_POST['image_path'] ?? '') ?: null;
    $imagePathMob = trim($_POST['image_path_mobile'] ?? '');
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
                "UPDATE categories SET name=?, slug=?, parent_id=?, description=?, image_path=?, image_path_mobile=?, sort_order=?, is_active=?, markup_percent=? WHERE id=?"
            )->execute([$name, $slug, $parentId, $desc, $imagePath, $imagePathMob, $sort, $isActive, $markupPct, $cid]);
            flashMessage('success', 'Категория обновлена.');
        } else {
            $db->prepare(
                "INSERT INTO categories (name, slug, parent_id, description, image_path, image_path_mobile, sort_order, is_active, markup_percent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([$name, $slug, $parentId, $desc, $imagePath, $imagePathMob, $sort, 1, $markupPct]);
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
    <?php renderRoleSidebar('categories'); ?>

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

                        <?php $curImg = $editCat['image_path'] ?? ''; $curImgMob = $editCat['image_path_mobile'] ?? ''; ?>
                        <div class="az-form-group">
                            <label><i class="fa fa-desktop"></i> Изображение для десктопа (главная страница)</label>
                            <input type="hidden" name="image_path" id="catImagePath" value="<?= sanitize($curImg) ?>">
                            <div id="catImgPreview" style="margin:8px 0;<?= $curImg ? '' : 'display:none;' ?>">
                                <img src="<?= sanitize($curImg) ?>" alt="" id="catImgEl"
                                     style="max-width:160px;max-height:120px;border:1px solid #dee2e6;border-radius:6px;background:#f5f5f5;">
                                <button type="button" class="az-btn az-btn-danger az-btn-sm" onclick="catRemoveImg('catImagePath','catImgPreview','catImgStatus')"
                                        style="vertical-align:top;margin-left:8px;"><i class="fa fa-trash-o"></i> Удалить</button>
                            </div>
                            <input type="file" accept="image/*" onchange="catUploadImg(this,'catImagePath','catImgPreview','catImgEl','catImgStatus')">
                            <div style="margin-top:8px;padding:10px 12px;background:#eef6ff;border:1px solid #cfe4fb;border-radius:6px;font-size:0.78rem;color:#1c5a99;line-height:1.55;">
                                <i class="fa fa-info-circle"></i> <strong>Рекомендуемый размер:</strong> 320&times;240&nbsp;px (соотношение&nbsp;4:3)<br>
                                Формат: <strong>JPG</strong>, PNG или WEBP &middot; до&nbsp;5&nbsp;МБ<br>
                                <span style="color:#5a87b3;">Если не задано — на главной показывается стандартная картинка.</span>
                            </div>
                            <span id="catImgStatus" style="font-size:0.8rem;color:#0a7;"></span>
                        </div>

                        <div class="az-form-group">
                            <label><i class="fa fa-mobile"></i> Изображение для мобильного (необязательно)</label>
                            <input type="hidden" name="image_path_mobile" id="catImagePathMob" value="<?= sanitize($curImgMob) ?>">
                            <div id="catImgPreviewMob" style="margin:8px 0;<?= $curImgMob ? '' : 'display:none;' ?>">
                                <img src="<?= sanitize($curImgMob) ?>" alt="" id="catImgElMob"
                                     style="max-width:140px;max-height:160px;border:1px solid #dee2e6;border-radius:6px;background:#f5f5f5;">
                                <button type="button" class="az-btn az-btn-danger az-btn-sm" onclick="catRemoveImg('catImagePathMob','catImgPreviewMob','catImgStatusMob')"
                                        style="vertical-align:top;margin-left:8px;"><i class="fa fa-trash-o"></i> Удалить</button>
                            </div>
                            <input type="file" accept="image/*" onchange="catUploadImg(this,'catImagePathMob','catImgPreviewMob','catImgElMob','catImgStatusMob')">
                            <div style="margin-top:8px;padding:10px 12px;background:#fff7ec;border:1px solid #ffe2b8;border-radius:6px;font-size:0.78rem;color:#9a6e14;line-height:1.55;">
                                <i class="fa fa-info-circle"></i> Если не задано — на телефоне используется десктопная версия.
                            </div>
                            <span id="catImgStatusMob" style="font-size:0.8rem;color:#0a7;"></span>
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

<script>
async function catUploadImg(input, fieldId, previewId, imgElId, statusId) {
    const file = input.files[0];
    if (!file) return;
    const status = document.getElementById(statusId);
    status.style.color = '#0a7';
    status.textContent = 'Загрузка...';
    const fd = new FormData();
    fd.append('file', file);
    try {
        const res  = await fetch('<?= APP_URL ?>/api/upload.php?type=categories', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.url) {
            document.getElementById(fieldId).value = data.url;
            document.getElementById(imgElId).src = data.url;
            document.getElementById(previewId).style.display = '';
            status.textContent = 'Загружено';
        } else {
            status.style.color = '#c30f0f';
            status.textContent = data.error || 'Ошибка загрузки';
        }
    } catch (e) {
        status.style.color = '#c30f0f';
        status.textContent = 'Ошибка сети: ' + e.message;
    }
    input.value = '';
}
function catRemoveImg(fieldId, previewId, statusId) {
    document.getElementById(fieldId).value = '';
    document.getElementById(previewId).style.display = 'none';
    document.getElementById(statusId).textContent = '';
}
</script>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
