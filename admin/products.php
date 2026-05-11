<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['admin', 'superadmin']);

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
        flashMessage('danger', 'Ошибка CSRF.');
        redirect(APP_URL . '/admin/products.php');
    }

    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'delete') {
        $delId = (int)($_POST['id'] ?? 0);
        if ($delId) {
            $db->prepare("UPDATE parts SET is_active = 0 WHERE id = ?")->execute([$delId]);
            flashMessage('success', 'Товар деактивирован.');
        }
        redirect(APP_URL . '/admin/products.php');
    }

    if ($postAction === 'restore') {
        $restId = (int)($_POST['id'] ?? 0);
        if ($restId) {
            $db->prepare("UPDATE parts SET is_active = 1 WHERE id = ?")->execute([$restId]);
            flashMessage('success', 'Товар восстановлен.');
        }
        redirect(APP_URL . '/admin/products.php');
    }

    // Save product
    $pnum   = trim($_POST['part_number'] ?? '');
    $name   = trim($_POST['name'] ?? '');
    $desc   = trim($_POST['description'] ?? '');
    $brand  = (int)($_POST['brand_id'] ?? 0);
    $cat    = (int)($_POST['category_id'] ?? 0);
    $price  = (float)str_replace(',', '.', $_POST['price'] ?? 0);
    $stock  = (int)($_POST['stock'] ?? 0);
    $weight = ($_POST['weight'] ?? '') !== '' ? (float)str_replace(',', '.', $_POST['weight']) : null;
    $dims   = trim($_POST['dimensions'] ?? '') ?: null;
    $pid    = (int)($_POST['id'] ?? 0);

    // Images: existing JSON + new uploads
    $existingImgs = json_decode($_POST['existing_images'] ?? '[]', true) ?: [];
    $newImgs      = array_filter(array_map('trim', explode(',', $_POST['new_images'] ?? '')));
    $allImages    = array_values(array_unique(array_merge($existingImgs, $newImgs)));

    if (empty($pnum))  $errors[] = 'Укажите артикул.';
    if (empty($name))  $errors[] = 'Укажите название.';
    if (!$brand)       $errors[] = 'Выберите бренд.';
    if (!$cat)         $errors[] = 'Выберите категорию.';
    if ($price <= 0)   $errors[] = 'Цена должна быть больше 0.';

    if (empty($errors)) {
        $chk = $db->prepare("SELECT id FROM parts WHERE part_number = ? AND id != ? LIMIT 1");
        $chk->execute([$pnum, $pid]);
        if ($chk->fetch()) $errors[] = 'Артикул уже используется другим товаром.';
    }

    if (empty($errors)) {
        $imagesJson = json_encode($allImages);
        if ($pid) {
            $db->prepare(
                "UPDATE parts SET part_number=?, name=?, description=?, brand_id=?, category_id=?,
                 price=?, stock=?, weight=?, dimensions=?, images=?, updated_at=NOW() WHERE id=?"
            )->execute([$pnum, $name, $desc ?: null, $brand, $cat, $price, $stock, $weight, $dims, $imagesJson, $pid]);
            flashMessage('success', 'Товар обновлён.');
        } else {
            $db->prepare(
                "INSERT INTO parts (part_number, name, description, brand_id, category_id,
                 price, stock, weight, dimensions, images, is_active, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,1,NOW())"
            )->execute([$pnum, $name, $desc ?: null, $brand, $cat, $price, $stock, $weight, $dims, $imagesJson]);
            flashMessage('success', 'Товар добавлен.');
        }
        redirect(APP_URL . '/admin/products.php');
    }

    $action = $pid ? 'edit' : 'new';
    $editId = $pid;
}

// Load part for edit
$editPart = null;
if ($editId && $action === 'edit') {
    $stmt = $db->prepare("SELECT * FROM parts WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $editPart = $stmt->fetch();
}

// List
$search  = trim($_GET['search'] ?? '');
$filter  = $_GET['filter'] ?? 'active'; // active | all
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$where   = [];
$params  = [];
if ($filter !== 'all') { $where[] = 'p.is_active = 1'; }
if ($search) {
    $where[] = '(p.part_number LIKE ? OR p.name LIKE ? OR b.name LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $db->prepare(
    "SELECT COUNT(*) FROM parts p LEFT JOIN brands b ON b.id=p.brand_id $whereSQL"
);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$partsStmt = $db->prepare(
    "SELECT p.*, b.name AS brand_name, c.name AS category_name
     FROM parts p
     LEFT JOIN brands b ON b.id = p.brand_id
     LEFT JOIN categories c ON c.id = p.category_id
     $whereSQL ORDER BY p.created_at DESC LIMIT $perPage OFFSET $offset"
);
$partsStmt->execute($params);
$parts = $partsStmt->fetchAll();

$pageTitle = 'Товары — Администратор';
require_once dirname(__DIR__) . '/includes/header.php';

function adminSidebar(string $active = ''): void {
    $url = APP_URL;
    $links = [
        ['href' => '/admin/index.php',    'icon' => 'tachometer', 'label' => 'Панель'],
        ['href' => '/admin/products.php', 'icon' => 'cogs',       'label' => 'Товары'],
        ['href' => '/admin/sliders.php',  'icon' => 'picture-o',  'label' => 'Слайдер'],
        ['href' => '/admin/orders.php',   'icon' => 'shopping-bag','label' => 'Заказы'],
        ['href' => '/admin/users.php',    'icon' => 'users',       'label' => 'Пользователи'],
    ];
    echo '<aside class="az-sidebar"><div class="az-sidebar-logo">ADMIN<span>PANEL</span></div><nav><ul>';
    foreach ($links as $l) {
        $cls = strpos($_SERVER['REQUEST_URI'], $l['href']) !== false ? ' class="active"' : '';
        echo "<li><a href=\"{$url}{$l['href']}\"{$cls}><i class=\"fa fa-{$l['icon']}\"></i> {$l['label']}</a></li>";
    }
    echo '<li style="border-top:1px solid rgba(255,255,255,0.1);margin-top:12px;">';
    echo "<li><a href=\"{$url}/index.php\"><i class=\"fa fa-home\"></i> На сайт</a></li>";
    echo "<li><a href=\"{$url}/auth/logout.php\" style=\"color:rgba(255,100,100,0.85)!important;\"><i class=\"fa fa-sign-out\"></i> Выйти</a></li>";
    echo '</ul></nav></aside>';
}
?>

<div class="az-panel">
    <?php renderRoleSidebar('products'); ?>

    <main class="az-main">
        <div class="az-topbar">
            <h1>Управление товарами</h1>
            <div style="display:flex;align-items:center;gap:12px;">
                <span style="font-size:0.85rem;color:#666;">
                    <?= sanitize($_SESSION['username'] ?? '') ?>
                    <span style="background:#d32f2f;color:#fff;border-radius:4px;padding:2px 7px;font-size:0.72rem;margin-left:4px;"><?= sanitize($_SESSION['role'] ?? '') ?></span>
                </span>
            </div>
        </div>

        <div class="az-content">

            <?php if ($flash = getFlashMessage()): ?>
                <div class="az-alert az-alert-<?= sanitize($flash['type']) ?>"><?= sanitize($flash['message']) ?></div>
            <?php endif; ?>

            <?php if (in_array($action, ['new', 'edit'])): ?>
            <!-- ── Form ────────────────────────────────────────────── -->
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <h2 style="margin:0;font-size:1.1rem;"><?= $action === 'edit' ? 'Редактировать товар' : 'Новый товар' ?></h2>
                <a href="<?= APP_URL ?>/admin/products.php" class="az-btn az-btn-secondary az-btn-sm">← Список</a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="az-alert az-alert-danger">
                    <?php foreach ($errors as $e): ?><div>• <?= sanitize($e) ?></div><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php $imgs = json_decode($editPart['images'] ?? '[]', true) ?: []; ?>

            <form method="POST" action="" id="productForm">
                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                <input type="hidden" name="action" value="<?= $action === 'edit' ? 'edit' : 'add' ?>">
                <?php if ($editPart): ?><input type="hidden" name="id" value="<?= (int)$editPart['id'] ?>"><?php endif; ?>
                <input type="hidden" name="existing_images" id="existingImages" value="<?= sanitize(json_encode($imgs)) ?>">
                <input type="hidden" name="new_images" id="newImages" value="">

                <div style="display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start;">

                    <!-- Left col -->
                    <div>
                        <div class="az-card">
                            <h3>Основная информация</h3>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                                <div class="az-form-group">
                                    <label>Артикул *</label>
                                    <input type="text" name="part_number"
                                           value="<?= sanitize($editPart['part_number'] ?? ($_POST['part_number'] ?? '')) ?>"
                                           placeholder="BKR6EK" required>
                                </div>
                                <div class="az-form-group">
                                    <label>Цена (₽) *</label>
                                    <input type="number" name="price" step="0.01" min="0.01"
                                           value="<?= sanitize((string)($editPart['price'] ?? ($_POST['price'] ?? ''))) ?>"
                                           placeholder="1500.00" required>
                                </div>
                            </div>

                            <div class="az-form-group">
                                <label>Название *</label>
                                <input type="text" name="name"
                                       value="<?= sanitize($editPart['name'] ?? ($_POST['name'] ?? '')) ?>"
                                       placeholder="Свеча зажигания NGK BKR6EK" required>
                            </div>

                            <div class="az-form-group">
                                <label>Описание</label>
                                <textarea name="description" rows="5"
                                          placeholder="Подробное описание товара..."><?= sanitize($editPart['description'] ?? ($_POST['description'] ?? '')) ?></textarea>
                            </div>
                        </div>

                        <div class="az-card">
                            <h3>Изображения товара</h3>
                            <p style="font-size:0.825rem;color:#888;margin-bottom:14px;">
                                Загрузите до 6 изображений. Первое будет главным.
                            </p>

                            <div id="imgGrid" class="img-grid">
                                <?php foreach ($imgs as $imgUrl): ?>
                                    <div class="img-grid-item" data-url="<?= sanitize($imgUrl) ?>">
                                        <img src="<?= sanitize($imgUrl) ?>" alt="">
                                        <button type="button" class="img-remove" onclick="removeExistingImage(this, '<?= sanitize(addslashes($imgUrl)) ?>')" title="Удалить">×</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div style="margin-top:14px;">
                                <label style="display:inline-flex;align-items:center;gap:8px;padding:9px 16px;background:#f8f9fa;border:2px dashed #ced4da;border-radius:6px;cursor:pointer;font-size:0.875rem;color:#555;transition:border-color 0.2s;">
                                    <i class="fa fa-upload"></i> Выбрать изображения
                                    <input type="file" id="imgUpload" multiple accept="image/*" style="display:none;" onchange="uploadImages(this)">
                                </label>
                                <span id="uploadStatus" style="font-size:0.8rem;color:#888;margin-left:10px;"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Right col -->
                    <div>
                        <div class="az-card">
                            <h3>Категория и бренд</h3>
                            <div class="az-form-group">
                                <label>Бренд *</label>
                                <select name="brand_id" required>
                                    <option value="">— Выберите —</option>
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
                                    <option value="">— Выберите —</option>
                                    <?php foreach ($categories as $c): ?>
                                        <option value="<?= (int)$c['id'] ?>"
                                            <?= ((int)($editPart['category_id'] ?? $_POST['category_id'] ?? 0)) === (int)$c['id'] ? 'selected' : '' ?>>
                                            <?= $c['parent_id'] ? '↳ ' : '' ?><?= sanitize($c['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="az-card">
                            <h3>Склад и габариты</h3>
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
                            <div class="az-form-group">
                                <label>Габариты (LxWxH мм)</label>
                                <input type="text" name="dimensions"
                                       value="<?= sanitize($editPart['dimensions'] ?? ($_POST['dimensions'] ?? '')) ?>"
                                       placeholder="90x45x38">
                            </div>
                        </div>

                        <div style="display:flex;gap:10px;">
                            <button type="submit" class="az-btn az-btn-primary" style="flex:1;">
                                <i class="fa fa-save"></i> <?= $action === 'edit' ? 'Сохранить' : 'Добавить товар' ?>
                            </button>
                            <a href="<?= APP_URL ?>/admin/products.php" class="az-btn az-btn-secondary">Отмена</a>
                        </div>
                    </div>

                </div><!-- /grid -->
            </form>

            <script>
            const uploadUrl = '<?= APP_URL ?>/api/upload.php?type=products';
            const newImageUrls = [];

            function updateNewImagesField() {
                document.getElementById('newImages').value = newImageUrls.join(',');
            }

            function removeExistingImage(btn, url) {
                const item = btn.closest('.img-grid-item');
                item.remove();
                let existing = JSON.parse(document.getElementById('existingImages').value || '[]');
                existing = existing.filter(u => u !== url);
                document.getElementById('existingImages').value = JSON.stringify(existing);
            }

            async function uploadImages(input) {
                const files = Array.from(input.files);
                const status = document.getElementById('uploadStatus');
                const grid   = document.getElementById('imgGrid');
                const total  = grid.querySelectorAll('.img-grid-item').length;

                if (total + files.length > 6) {
                    alert('Максимум 6 изображений');
                    input.value = '';
                    return;
                }

                status.textContent = 'Загрузка...';
                for (const file of files) {
                    const fd = new FormData();
                    fd.append('file', file);
                    try {
                        const res  = await fetch(uploadUrl, { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.url) {
                            newImageUrls.push(data.url);
                            updateNewImagesField();
                            const div = document.createElement('div');
                            div.className = 'img-grid-item';
                            div.dataset.url = data.url;
                            div.innerHTML = `<img src="${data.url}" alt=""><button type="button" class="img-remove" onclick="removeNewImage(this,'${data.url}')" title="Удалить">×</button>`;
                            grid.appendChild(div);
                        } else {
                            alert(data.error || 'Ошибка загрузки');
                        }
                    } catch (e) {
                        alert('Ошибка сети: ' + e.message);
                    }
                }
                status.textContent = '';
                input.value = '';
            }

            function removeNewImage(btn, url) {
                const idx = newImageUrls.indexOf(url);
                if (idx > -1) newImageUrls.splice(idx, 1);
                updateNewImagesField();
                btn.closest('.img-grid-item').remove();
            }
            </script>

            <?php else: ?>
            <!-- ── List ────────────────────────────────────────────── -->
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
                <h2 style="margin:0;font-size:1.1rem;">Товары
                    <span style="font-size:0.85rem;font-weight:400;color:#888;margin-left:8px;"><?= $total ?> шт.</span>
                </h2>
                <a href="?action=new" class="az-btn az-btn-primary">
                    <i class="fa fa-plus"></i> Добавить товар
                </a>
            </div>

            <div class="az-card" style="padding:14px 20px;margin-bottom:16px;">
                <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <input type="text" name="search" value="<?= sanitize($search) ?>"
                           placeholder="Артикул, название, бренд..."
                           style="flex:1;min-width:200px;padding:8px 12px;border:1px solid #ced4da;border-radius:6px;font-size:0.875rem;outline:none;">
                    <select name="filter" style="padding:8px 12px;border:1px solid #ced4da;border-radius:6px;font-size:0.875rem;outline:none;">
                        <option value="active" <?= $filter === 'active' ? 'selected' : '' ?>>Активные</option>
                        <option value="all"    <?= $filter === 'all'    ? 'selected' : '' ?>>Все (включая удалённые)</option>
                    </select>
                    <button type="submit" class="az-btn az-btn-primary az-btn-sm"><i class="fa fa-search"></i> Найти</button>
                    <?php if ($search): ?>
                        <a href="<?= APP_URL ?>/admin/products.php" class="az-btn az-btn-secondary az-btn-sm">Сброс</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="az-card" style="padding:0;overflow:hidden;">
                <div style="overflow-x:auto;">
                    <table class="az-table">
                        <thead>
                            <tr>
                                <th style="width:70px;">Фото</th>
                                <th>Артикул</th>
                                <th>Название</th>
                                <th>Бренд / Категория</th>
                                <th style="text-align:right;">Цена</th>
                                <th style="text-align:center;">Остаток</th>
                                <th style="text-align:center;">Статус</th>
                                <th style="text-align:center;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($parts)): ?>
                                <tr><td colspan="8" style="text-align:center;color:#aaa;padding:32px;">Товары не найдены</td></tr>
                            <?php else: ?>
                                <?php foreach ($parts as $p):
                                    $imgs2 = json_decode($p['images'] ?? '[]', true) ?: [];
                                    $thumb = $imgs2[0] ?? '';
                                    $st    = getStockStatus((int)$p['stock']);
                                ?>
                                <tr style="<?= !$p['is_active'] ? 'opacity:0.5;' : '' ?>">
                                    <td>
                                        <?php if ($thumb): ?>
                                            <img src="<?= sanitize($thumb) ?>" alt=""
                                                 style="width:56px;height:42px;object-fit:cover;border-radius:4px;border:1px solid #eee;">
                                        <?php else: ?>
                                            <div style="width:56px;height:42px;background:#f5f5f5;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#ddd;">
                                                <i class="fa fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><code style="font-size:0.8rem;"><?= sanitize($p['part_number']) ?></code></td>
                                    <td style="font-size:0.875rem;max-width:180px;"><?= sanitize(truncate($p['name'], 45)) ?></td>
                                    <td style="font-size:0.8rem;color:#777;">
                                        <?= sanitize($p['brand_name'] ?? '—') ?><br>
                                        <span style="color:#aaa;"><?= sanitize($p['category_name'] ?? '—') ?></span>
                                    </td>
                                    <td style="text-align:right;font-weight:700;color:#d32f2f;white-space:nowrap;"><?= formatPrice($p['price']) ?></td>
                                    <td style="text-align:center;">
                                        <span class="badge badge-<?= $st['class'] ?>"><?= (int)$p['stock'] ?></span>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="badge badge-<?= $p['is_active'] ? 'success' : 'danger' ?>">
                                            <?= $p['is_active'] ? 'Активен' : 'Удалён' ?>
                                        </span>
                                    </td>
                                    <td style="text-align:center;white-space:nowrap;">
                                        <a href="?action=edit&id=<?= (int)$p['id'] ?>" class="az-btn az-btn-secondary az-btn-sm">
                                            <i class="fa fa-pencil"></i>
                                        </a>
                                        <?php if ($p['is_active']): ?>
                                            <form method="POST" style="display:inline;"
                                                  onsubmit="return confirm('Деактивировать «<?= sanitize(addslashes($p['name'])) ?>»?')">
                                                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                                <button type="submit" class="az-btn az-btn-danger az-btn-sm"><i class="fa fa-trash-o"></i></button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                                <input type="hidden" name="action" value="restore">
                                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                                <button type="submit" class="az-btn az-btn-success az-btn-sm"><i class="fa fa-undo"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($pages > 1): ?>
            <div style="display:flex;justify-content:center;margin-top:20px;">
                <ul class="pagination">
                    <?php for ($pg = 1; $pg <= $pages; $pg++):
                        $q = array_merge($_GET, ['page' => $pg]); unset($q['action']); ?>
                        <li><a href="?<?= http_build_query($q) ?>" class="page-link <?= $pg === $page ? 'active' : '' ?>"><?= $pg ?></a></li>
                    <?php endfor; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php endif; // list / form ?>

        </div><!-- /.az-content -->
    </main>
</div><!-- /.az-panel -->

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
