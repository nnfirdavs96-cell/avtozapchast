<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['manager', 'admin', 'superadmin']);

$db     = getDB();
$csrf   = generateCsrfToken();
$action = $_GET['action'] ?? 'list';
$editId = (int)($_GET['id'] ?? 0);
$errors = [];

$brands     = getBrands();
$categories = getCategories();

// ── POST handler ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'Ошибка CSRF. Попробуйте снова.');
        redirect(APP_URL . '/manager/parts.php');
    }

    $postAction = $_POST['action'] ?? '';

    // Delete (soft-delete)
    if ($postAction === 'delete') {
        $delId = (int)($_POST['id'] ?? 0);
        if ($delId) {
            $db->prepare("UPDATE parts SET is_active = 0 WHERE id = ?")->execute([$delId]);
            flashMessage('success', 'Запчасть удалена.');
        }
        redirect(APP_URL . '/manager/parts.php');
    }

    // Save (add or edit)
    $pnum      = trim($_POST['part_number'] ?? '');
    $name      = trim($_POST['name'] ?? '');
    $desc      = trim($_POST['description'] ?? '');
    $brand     = (int)($_POST['brand_id'] ?? 0);
    $cat       = (int)($_POST['category_id'] ?? 0);
    $price     = (float)str_replace(',', '.', $_POST['price'] ?? 0);
    $costPrice = ($_POST['cost_price'] ?? '') !== '' ? (float)str_replace(',', '.', $_POST['cost_price']) : null;
    $markupPct = ($_POST['markup_percent'] ?? '') !== '' ? (float)str_replace(',', '.', $_POST['markup_percent']) : null;
    $stock     = (int)($_POST['stock'] ?? 0);
    $weight    = isset($_POST['weight']) && $_POST['weight'] !== ''
                 ? (float)str_replace(',', '.', $_POST['weight'])
                 : null;
    $dims      = trim($_POST['dimensions'] ?? '') ?: null;
    $pid       = (int)($_POST['id'] ?? 0);

    if (empty($pnum))  $errors[] = 'Укажите артикул (номер детали).';
    if (empty($name))  $errors[] = 'Укажите название.';
    if (!$brand)       $errors[] = 'Выберите бренд.';
    if (!$cat)         $errors[] = 'Выберите категорию.';
    if ($price <= 0)   $errors[] = 'Укажите корректную цену (больше 0).';

    // Uniqueness of part_number
    if (empty($errors)) {
        $chk = $db->prepare("SELECT id FROM parts WHERE part_number = ? AND id != ? LIMIT 1");
        $chk->execute([$pnum, $pid]);
        if ($chk->fetch()) {
            $errors[] = 'Запчасть с таким артикулом уже существует.';
        }
    }

    if (empty($errors)) {
        // Images: merge existing + new
        $existingImgs = json_decode($_POST['existing_images'] ?? '[]', true) ?: [];
        $newImgs      = array_filter(array_map('trim', explode(',', $_POST['new_images'] ?? '')));
        $allImages    = array_values(array_unique(array_merge($existingImgs, $newImgs)));
        $imagesJson   = json_encode($allImages);

        if ($pid) {
            $db->prepare(
                "UPDATE parts
                 SET part_number=?, name=?, description=?, brand_id=?, category_id=?,
                     price=?, cost_price=?, markup_percent=?, stock=?, weight=?, dimensions=?, images=?, updated_at=NOW()
                 WHERE id=?"
            )->execute([$pnum, $name, $desc ?: null, $brand, $cat, $price, $costPrice, $markupPct, $stock, $weight, $dims, $imagesJson, $pid]);
            flashMessage('success', 'Запчасть обновлена.');
        } else {
            $db->prepare(
                "INSERT INTO parts
                     (part_number, name, description, brand_id, category_id,
                      price, cost_price, markup_percent, stock, weight, dimensions, images, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())"
            )->execute([$pnum, $name, $desc ?: null, $brand, $cat, $price, $costPrice, $markupPct, $stock, $weight, $dims, $imagesJson]);
            flashMessage('success', 'Запчасть добавлена.');
        }
        redirect(APP_URL . '/manager/parts.php');
    }

    // Stay on form
    $action = $pid ? 'edit' : 'new';
    $editId = $pid;
}

// ── Load part for edit ────────────────────────────────────────────────
$editPart = null;
if ($editId && $action === 'edit') {
    $stmt = $db->prepare("SELECT * FROM parts WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $editPart = $stmt->fetch();
}

// ── List with search & pagination ─────────────────────────────────────
$search  = trim($_GET['search'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$where   = ['p.is_active = 1'];
$params  = [];

if ($search !== '') {
    $where[]  = '(p.part_number LIKE ? OR p.name LIKE ? OR b.name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$whereSQL = 'WHERE ' . implode(' AND ', $where);

$cntStmt = $db->prepare(
    "SELECT COUNT(*) FROM parts p
     LEFT JOIN brands b ON b.id = p.brand_id
     $whereSQL"
);
$cntStmt->execute($params);
$total  = (int)$cntStmt->fetchColumn();
$pages  = max(1, (int)ceil($total / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

$partsStmt = $db->prepare(
    "SELECT p.*, b.name AS brand_name, c.name AS category_name
     FROM parts p
     LEFT JOIN brands b ON b.id = p.brand_id
     LEFT JOIN categories c ON c.id = p.category_id
     $whereSQL
     ORDER BY p.created_at DESC
     LIMIT $perPage OFFSET $offset"
);
$partsStmt->execute($params);
$parts = $partsStmt->fetchAll();

$pageTitle = 'Управление запчастями';
require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="az-panel">

    <!-- ── Sidebar ─────────────────────────────────────────────────── -->
    <aside class="az-sidebar">
        <div class="az-sidebar-logo">AUTO<span>PARTS</span></div>
        <nav>
            <ul>
                <li><a href="<?= APP_URL ?>/manager/index.php"><i class="fa fa-dashboard"></i> <?= t('dashboard') ?></a></li>
                <li><a href="<?= APP_URL ?>/manager/parts.php" class="active"><i class="fa fa-cogs"></i> <?= t('parts_mgmt') ?></a></li>
                <li><a href="<?= APP_URL ?>/manager/categories.php"><i class="fa fa-sitemap"></i> <?= t('categories_mgmt') ?></a></li>
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
                <?php if ($action === 'new'): ?>
                    Добавить запчасть
                <?php elseif ($action === 'edit'): ?>
                    Редактировать запчасть
                <?php else: ?>
                    Запчасти
                <?php endif; ?>
            </h1>
            <div>
                <?php if ($action === 'list'): ?>
                    <a href="?action=new" class="az-btn az-btn-primary az-btn-sm">
                        <i class="fa fa-plus"></i> Добавить
                    </a>
                <?php else: ?>
                    <a href="<?= APP_URL ?>/manager/parts.php" class="az-btn az-btn-secondary az-btn-sm">
                        <i class="fa fa-arrow-left"></i> Список
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="az-content">

            <?php if ($action === 'new' || $action === 'edit'): ?>
            <!-- ── Add / Edit Form ──────────────────────────────── -->
            <div style="max-width:760px;">
                <div class="az-card">
                    <h3><?= $action === 'edit' ? 'Редактировать запчасть' : 'Новая запчасть' ?></h3>

                    <?php if (!empty($errors)): ?>
                        <div class="az-alert az-alert-danger">
                            <?php foreach ($errors as $err): ?>
                                <div><?= sanitize($err) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php $existImgs = json_decode($editPart['images'] ?? '[]', true) ?: []; ?>
                    <form method="POST" action="<?= APP_URL ?>/manager/parts.php">
                        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="existing_images" id="existingImages" value="<?= sanitize(json_encode($existImgs)) ?>">
                        <input type="hidden" name="new_images" id="newImages" value="">
                        <?php if ($editPart): ?>
                            <input type="hidden" name="id" value="<?= (int)$editPart['id'] ?>">
                        <?php endif; ?>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                            <div class="az-form-group">
                                <label>Артикул (номер детали) *</label>
                                <input type="text" name="part_number"
                                       value="<?= sanitize($editPart['part_number'] ?? ($_POST['part_number'] ?? '')) ?>"
                                       placeholder="BKR6EK" required>
                            </div>
                            <div class="az-form-group">
                                <label>Цена продажи (СМН) *</label>
                                <input type="number" id="fieldPrice" name="price" step="0.01" min="0"
                                       value="<?= sanitize($editPart['price'] ?? ($_POST['price'] ?? '')) ?>"
                                       placeholder="1500.00" required>
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;background:#fafafa;border:1px solid #e9ecef;border-radius:8px;padding:14px;margin-bottom:16px;">
                            <div class="az-form-group" style="margin:0;">
                                <label>Себестоимость / закупка (СМН)</label>
                                <input type="number" id="fieldCostPrice" name="cost_price" step="0.01" min="0"
                                       value="<?= sanitize((string)($editPart['cost_price'] ?? ($_POST['cost_price'] ?? ''))) ?>"
                                       placeholder="1000.00">
                            </div>
                            <div class="az-form-group" style="margin:0;">
                                <label>Наценка (%)</label>
                                <input type="number" id="fieldMarkup" name="markup_percent" step="0.01" min="0" max="1000"
                                       value="<?= sanitize((string)($editPart['markup_percent'] ?? ($_POST['markup_percent'] ?? ''))) ?>"
                                       placeholder="Пусто — категорийная/глобальная">
                                <small style="color:#888;font-size:0.78rem;">Заполните для авторасчёта цены</small>
                            </div>
                        </div>

                        <div class="az-form-group">
                            <label>Название *</label>
                            <input type="text" name="name"
                                   value="<?= sanitize($editPart['name'] ?? ($_POST['name'] ?? '')) ?>"
                                   placeholder="Свеча зажигания NGK BKR6EK" required>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                            <div class="az-form-group">
                                <label>Бренд *</label>
                                <select name="brand_id" required>
                                    <option value="">— Выберите бренд —</option>
                                    <?php foreach ($brands as $b): ?>
                                        <option value="<?= (int)$b['id'] ?>"
                                            <?= ((int)($editPart['brand_id'] ?? $_POST['brand_id'] ?? 0)) === (int)$b['id'] ? 'selected' : '' ?>>
                                            <?= sanitize($b['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="az-form-group">
                                <label>Категория *</label>
                                <select name="category_id" required>
                                    <option value="">— Выберите категорию —</option>
                                    <?php foreach ($categories as $c): ?>
                                        <option value="<?= (int)$c['id'] ?>"
                                            <?= ((int)($editPart['category_id'] ?? $_POST['category_id'] ?? 0)) === (int)$c['id'] ? 'selected' : '' ?>>
                                            <?= $c['parent_id'] ? '&nbsp;&nbsp;↳ ' : '' ?><?= sanitize($c['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                            <div class="az-form-group">
                                <label>Остаток (шт)</label>
                                <input type="number" name="stock" min="0"
                                       value="<?= sanitize((string)($editPart['stock'] ?? ($_POST['stock'] ?? 0))) ?>">
                            </div>
                            <div class="az-form-group">
                                <label>Вес (кг)</label>
                                <input type="text" name="weight"
                                       value="<?= sanitize((string)($editPart['weight'] ?? ($_POST['weight'] ?? ''))) ?>"
                                       placeholder="0.250">
                            </div>
                        </div>

                        <div class="az-form-group">
                            <label>Габариты (LxWxH мм)</label>
                            <input type="text" name="dimensions"
                                   value="<?= sanitize($editPart['dimensions'] ?? ($_POST['dimensions'] ?? '')) ?>"
                                   placeholder="90x45x38">
                        </div>

                        <div class="az-form-group">
                            <label>Описание</label>
                            <textarea name="description" rows="4"
                                      placeholder="Подробное описание запчасти..."><?= sanitize($editPart['description'] ?? ($_POST['description'] ?? '')) ?></textarea>
                        </div>

                        <!-- Image upload -->
                        <div class="az-form-group">
                            <label>Изображения товара</label>
                            <div id="imgGrid" class="img-grid" style="margin-bottom:10px;">
                                <?php foreach ($existImgs as $imgUrl): ?>
                                    <div class="img-grid-item" data-url="<?= sanitize($imgUrl) ?>">
                                        <img src="<?= sanitize($imgUrl) ?>" alt="">
                                        <button type="button" class="img-remove"
                                                onclick="removeExistingImage(this,'<?= sanitize(addslashes($imgUrl)) ?>')"
                                                title="Удалить">×</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <label style="display:inline-flex;align-items:center;gap:8px;padding:8px 14px;background:#f8f9fa;border:2px dashed #ced4da;border-radius:6px;cursor:pointer;font-size:0.825rem;color:#555;">
                                <i class="fa fa-upload"></i> Загрузить фото (до 6, JPG/PNG/WEBP)
                                <input type="file" id="imgUpload" multiple accept="image/*" style="display:none;" onchange="uploadImages(this)">
                            </label>
                            <span id="uploadStatus" style="font-size:0.8rem;color:#888;margin-left:8px;"></span>
                        </div>

                        <div style="display:flex;gap:12px;">
                            <button type="submit" class="az-btn az-btn-primary">
                                <i class="fa fa-save"></i>
                                <?= $action === 'edit' ? 'Сохранить' : 'Добавить запчасть' ?>
                            </button>
                            <a href="<?= APP_URL ?>/manager/parts.php" class="az-btn az-btn-secondary">Отмена</a>
                        </div>
                    </form>
                    <script>
                    // Markup auto-calculation: price = cost_price * (1 + markup/100)
                    (function() {
                        const cost   = document.getElementById('fieldCostPrice');
                        const markup = document.getElementById('fieldMarkup');
                        const price  = document.getElementById('fieldPrice');
                        function recalc() {
                            const c = parseFloat(cost.value);
                            const m = parseFloat(markup.value);
                            if (!isNaN(c) && c > 0 && !isNaN(m) && m >= 0) {
                                price.value = (c * (1 + m / 100)).toFixed(2);
                            }
                        }
                        cost.addEventListener('input', recalc);
                        markup.addEventListener('input', recalc);
                    })();

                    const newImageUrls = [];
                    function updateNewImagesField() {
                        document.getElementById('newImages').value = newImageUrls.join(',');
                    }
                    function removeExistingImage(btn, url) {
                        btn.closest('.img-grid-item').remove();
                        let ex = JSON.parse(document.getElementById('existingImages').value || '[]');
                        ex = ex.filter(u => u !== url);
                        document.getElementById('existingImages').value = JSON.stringify(ex);
                    }
                    async function uploadImages(input) {
                        const files = Array.from(input.files);
                        const status = document.getElementById('uploadStatus');
                        const grid = document.getElementById('imgGrid');
                        if (grid.querySelectorAll('.img-grid-item').length + files.length > 6) {
                            alert('Максимум 6 изображений'); input.value = ''; return;
                        }
                        status.textContent = 'Загрузка...';
                        for (const file of files) {
                            const fd = new FormData(); fd.append('file', file);
                            try {
                                const res = await fetch('<?= APP_URL ?>/api/upload.php?type=products', {method:'POST',body:fd});
                                const data = await res.json();
                                if (data.url) {
                                    newImageUrls.push(data.url); updateNewImagesField();
                                    const div = document.createElement('div');
                                    div.className = 'img-grid-item'; div.dataset.url = data.url;
                                    div.innerHTML = `<img src="${data.url}" alt=""><button type="button" class="img-remove" onclick="removeNewImage(this,'${data.url}')" title="Удалить">×</button>`;
                                    grid.appendChild(div);
                                } else { alert(data.error || 'Ошибка загрузки'); }
                            } catch(e) { alert('Ошибка сети'); }
                        }
                        status.textContent = ''; input.value = '';
                    }
                    function removeNewImage(btn, url) {
                        const idx = newImageUrls.indexOf(url);
                        if (idx > -1) newImageUrls.splice(idx, 1);
                        updateNewImagesField();
                        btn.closest('.img-grid-item').remove();
                    }
                    </script>
                </div>
            </div>

            <?php else: ?>
            <!-- ── List ─────────────────────────────────────────── -->

            <!-- Search -->
            <div class="az-card" style="padding:16px;">
                <form method="GET" action=""
                      style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <input type="text" name="search"
                           value="<?= sanitize($search) ?>"
                           placeholder="Артикул, название, бренд..."
                           style="flex:1;min-width:200px;padding:8px 12px;border:1px solid #ced4da;border-radius:6px;font-size:0.875rem;outline:none;">
                    <button type="submit" class="az-btn az-btn-primary az-btn-sm">
                        <i class="fa fa-search"></i> Найти
                    </button>
                    <?php if ($search): ?>
                        <a href="<?= APP_URL ?>/manager/parts.php" class="az-btn az-btn-secondary az-btn-sm">Сброс</a>
                    <?php endif; ?>
                    <span style="margin-left:auto;font-size:0.8rem;color:#888;">
                        Найдено: <strong><?= $total ?></strong>
                    </span>
                </form>
            </div>

            <div class="az-card" style="padding:0;overflow:hidden;">
                <div style="overflow-x:auto;">
                    <table class="az-table">
                        <thead>
                            <tr>
                                <th>Артикул</th>
                                <th>Название</th>
                                <th>Бренд</th>
                                <th>Категория</th>
                                <th style="text-align:right;">Цена</th>
                                <th style="text-align:center;">Остаток</th>
                                <th style="text-align:center;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($parts)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center;color:#aaa;padding:30px;">
                                        <?= $search ? 'Ничего не найдено по запросу «' . sanitize($search) . '»' : 'Запчастей ещё нет.' ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($parts as $p):
                                    $st = getStockStatus((int)$p['stock']);
                                ?>
                                <tr>
                                    <td><code style="font-size:0.8rem;"><?= sanitize($p['part_number']) ?></code></td>
                                    <td style="font-size:0.875rem;max-width:200px;">
                                        <?= sanitize(truncate($p['name'], 45)) ?>
                                    </td>
                                    <td style="color:#888;font-size:0.8rem;"><?= sanitize($p['brand_name'] ?? '—') ?></td>
                                    <td style="color:#888;font-size:0.8rem;"><?= sanitize($p['category_name'] ?? '—') ?></td>
                                    <td style="text-align:right;font-weight:700;color:#d32f2f;white-space:nowrap;">
                                        <?= formatPrice($p['price']) ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="badge badge-<?= $st['class'] ?>"><?= (int)$p['stock'] ?></span>
                                    </td>
                                    <td style="text-align:center;white-space:nowrap;">
                                        <a href="?action=edit&id=<?= (int)$p['id'] ?>"
                                           class="az-btn az-btn-secondary az-btn-sm">
                                            <i class="fa fa-pencil"></i> Ред.
                                        </a>
                                        <form method="POST" action="" style="display:inline;"
                                              onsubmit="return confirm('Удалить запчасть «<?= sanitize(addslashes($p['name'])) ?>»?')">
                                            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                            <button type="submit" class="az-btn az-btn-danger az-btn-sm">
                                                <i class="fa fa-trash-o"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($pages > 1): ?>
            <div class="paginatoin-area">
                <div class="row"><div class="col-12">
                    <div class="pagination-box">
                        <ul class="pagination">
                            <?php for ($pg = 1; $pg <= $pages; $pg++):
                                $q = array_merge($_GET, ['page' => $pg]);
                                unset($q['action']);
                            ?>
                                <li class="<?= $pg === $page ? 'active' : '' ?>">
                                    <a href="?<?= http_build_query($q) ?>"><?= $pg ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </div>
                </div></div>
            </div>
            <?php endif; ?>

            <?php endif; // end list/form ?>

        </div><!-- /.az-content -->
    </main>
</div><!-- /.az-panel -->

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
