<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['manager', 'admin', 'superadmin']);
requirePermission('brands');

$db     = getDB();
$csrf   = generateCsrfToken();
$action = $_GET['action'] ?? 'list';
$editId = (int)($_GET['id'] ?? 0);
$errors = [];

// ── POST handler ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'Ошибка CSRF. Попробуйте снова.');
        redirect(APP_URL . '/manager/brands.php');
    }

    $postAction = $_POST['action'] ?? '';

    // Delete
    if ($postAction === 'delete') {
        $delId = (int)($_POST['id'] ?? 0);
        if ($delId) {
            $cnt = $db->prepare("SELECT COUNT(*) FROM parts WHERE brand_id = ? AND is_active = 1");
            $cnt->execute([$delId]);
            if ((int)$cnt->fetchColumn() > 0) {
                flashMessage('danger', 'Нельзя удалить бренд — есть привязанные запчасти.');
            } else {
                $db->prepare("UPDATE brands SET is_active = 0 WHERE id = ?")->execute([$delId]);
                flashMessage('success', 'Бренд удалён.');
            }
        }
        redirect(APP_URL . '/manager/brands.php');
    }

    // Save
    $name     = trim($_POST['name'] ?? '');
    $slug     = trim($_POST['slug'] ?? '');
    $country  = trim($_POST['country'] ?? '') ?: null;
    $desc     = trim($_POST['description'] ?? '') ?: null;
    $isActive = (int)(!empty($_POST['is_active']));
    $logoPath = trim($_POST['logo_path'] ?? '') ?: null;
    $bid      = (int)($_POST['id'] ?? 0);

    if (empty($name)) {
        $errors[] = 'Введите название бренда.';
    }

    // Auto-generate slug from name if empty
    if (empty($slug) && !empty($name)) {
        $slug = mb_strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
    }

    if (empty($errors)) {
        $chk = $db->prepare("SELECT id FROM brands WHERE slug = ? AND id != ? LIMIT 1");
        $chk->execute([$slug, $bid]);
        if ($chk->fetch()) {
            $errors[] = 'Бренд с таким слагом уже существует.';
        }
    }

    if (empty($errors)) {
        if ($bid) {
            $db->prepare(
                "UPDATE brands SET name=?, slug=?, country=?, description=?, logo_path=?, is_active=? WHERE id=?"
            )->execute([$name, $slug, $country, $desc, $logoPath, $isActive, $bid]);
            flashMessage('success', 'Бренд обновлён.');
        } else {
            $db->prepare(
                "INSERT INTO brands (name, slug, country, description, logo_path, is_active)
                 VALUES (?, ?, ?, ?, ?, ?)"
            )->execute([$name, $slug, $country, $desc, $logoPath, 1]);
            flashMessage('success', 'Бренд добавлен.');
        }
        redirect(APP_URL . '/manager/brands.php');
    }

    $action = $bid ? 'edit' : 'new';
    $editId = $bid;
}

// ── Load for edit ─────────────────────────────────────────────────────
$editBrand = null;
if ($editId && $action === 'edit') {
    $stmt = $db->prepare("SELECT * FROM brands WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $editBrand = $stmt->fetch();
}

// ── All brands with part count ────────────────────────────────────────
$brands = $db->query(
    "SELECT b.*,
            (SELECT COUNT(*) FROM parts p WHERE p.brand_id = b.id AND p.is_active = 1) AS part_count
     FROM brands b
     WHERE b.is_active = 1
     ORDER BY b.name ASC"
)->fetchAll();

$pageTitle = 'Управление брендами';
require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="az-panel">

    <!-- ── Sidebar ─────────────────────────────────────────────────── -->
    <aside class="az-sidebar">
        <div class="az-sidebar-logo">AUTO<span>PARTS</span></div>
        <nav>
            <ul>
                <li><a href="<?= APP_URL ?>/manager/index.php"><i class="fa fa-dashboard"></i> <?= t('dashboard') ?></a></li>
                <li><a href="<?= APP_URL ?>/manager/parts.php"><i class="fa fa-cogs"></i> <?= t('parts_mgmt') ?></a></li>
                <li><a href="<?= APP_URL ?>/manager/categories.php"><i class="fa fa-sitemap"></i> <?= t('categories_mgmt') ?></a></li>
                <li><a href="<?= APP_URL ?>/manager/brands.php" class="active"><i class="fa fa-tag"></i> <?= t('brands_mgmt') ?></a></li>
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
                <?= $action === 'new' ? 'Новый бренд' : ($action === 'edit' ? 'Редактировать бренд' : 'Бренды') ?>
            </h1>
            <div>
                <?php if ($action === 'list'): ?>
                    <a href="?action=new" class="az-btn az-btn-primary az-btn-sm">
                        <i class="fa fa-plus"></i> Добавить
                    </a>
                <?php else: ?>
                    <a href="<?= APP_URL ?>/manager/brands.php" class="az-btn az-btn-secondary az-btn-sm">
                        <i class="fa fa-arrow-left"></i> Список
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="az-content">

            <?php if ($action === 'new' || $action === 'edit'): ?>
            <!-- ── Form ─────────────────────────────────────────── -->
            <div style="max-width:560px;">
                <div class="az-card">
                    <h3><?= $action === 'edit' ? 'Редактировать бренд' : 'Новый бренд' ?></h3>

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
                        <?php if ($editBrand): ?>
                            <input type="hidden" name="id" value="<?= (int)$editBrand['id'] ?>">
                        <?php endif; ?>

                        <div class="az-form-group">
                            <label>Название бренда *</label>
                            <input type="text" name="name"
                                   value="<?= sanitize($editBrand['name'] ?? ($_POST['name'] ?? '')) ?>"
                                   required placeholder="NGK">
                        </div>

                        <div class="az-form-group">
                            <label>Слаг (URL)</label>
                            <input type="text" name="slug"
                                   value="<?= sanitize($editBrand['slug'] ?? ($_POST['slug'] ?? '')) ?>"
                                   placeholder="auto-generated">
                            <small style="color:#888;font-size:0.78rem;">Оставьте пустым — будет создан автоматически.</small>
                        </div>

                        <div class="az-form-group">
                            <label>Страна производителя</label>
                            <input type="text" name="country"
                                   value="<?= sanitize($editBrand['country'] ?? ($_POST['country'] ?? '')) ?>"
                                   placeholder="Япония">
                        </div>

                        <div class="az-form-group">
                            <label>Описание</label>
                            <textarea name="description" rows="3"
                                      placeholder="Краткое описание бренда..."><?= sanitize($editBrand['description'] ?? ($_POST['description'] ?? '')) ?></textarea>
                        </div>

                        <?php $curLogo = $editBrand['logo_path'] ?? ''; ?>
                        <div class="az-form-group">
                            <label>Логотип бренда (для блока партнёров на главной)</label>
                            <input type="hidden" name="logo_path" id="brLogoPath" value="<?= sanitize($curLogo) ?>">
                            <div id="brLogoPreview" style="margin:8px 0;<?= $curLogo ? '' : 'display:none;' ?>">
                                <img src="<?= sanitize($curLogo) ?>" alt="" id="brLogoEl"
                                     style="max-width:160px;max-height:90px;border:1px solid #dee2e6;border-radius:6px;background:#fff;padding:6px;">
                                <button type="button" class="az-btn az-btn-danger az-btn-sm" onclick="brRemoveLogo()"
                                        style="vertical-align:top;margin-left:8px;"><i class="fa fa-trash-o"></i> Удалить</button>
                            </div>
                            <input type="file" id="brLogoFile" accept="image/*" onchange="brUploadLogo(this)">
                            <small style="color:#888;font-size:0.78rem;display:block;margin-top:4px;">
                                JPG/PNG/WEBP, до 5 МБ. Если не задано — показывается стандартная картинка.
                            </small>
                            <span id="brLogoStatus" style="font-size:0.8rem;color:#0a7;"></span>
                        </div>

                        <div class="az-form-group">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400;margin:0;">
                                <input type="checkbox" name="is_active" value="1"
                                       <?= (isset($editBrand) ? $editBrand['is_active'] : 1) ? 'checked' : '' ?>>
                                Активен
                            </label>
                        </div>

                        <div style="display:flex;gap:12px;">
                            <button type="submit" class="az-btn az-btn-primary">
                                <i class="fa fa-save"></i> <?= $action === 'edit' ? 'Сохранить' : 'Добавить' ?>
                            </button>
                            <a href="<?= APP_URL ?>/manager/brands.php" class="az-btn az-btn-secondary">Отмена</a>
                        </div>
                    </form>
                </div>
            </div>

            <?php else: ?>
            <!-- ── List ─────────────────────────────────────────── -->
            <div class="az-card" style="padding:0;overflow:hidden;">
                <div style="padding:16px 20px;border-bottom:1px solid #dee2e6;display:flex;align-items:center;justify-content:space-between;">
                    <strong>Всего активных брендов: <?= count($brands) ?></strong>
                    <a href="?action=new" class="az-btn az-btn-primary az-btn-sm">
                        <i class="fa fa-plus"></i> Добавить бренд
                    </a>
                </div>
                <div style="overflow-x:auto;">
                    <table class="az-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Название</th>
                                <th>Слаг</th>
                                <th>Страна</th>
                                <th style="text-align:center;">Запчастей</th>
                                <th>Описание</th>
                                <th style="text-align:center;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($brands)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center;color:#aaa;padding:30px;">Брендов нет.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($brands as $b): ?>
                                    <tr>
                                        <td style="color:#888;font-size:0.8rem;"><?= (int)$b['id'] ?></td>
                                        <td style="font-weight:700;"><?= sanitize($b['name']) ?></td>
                                        <td><code style="font-size:0.75rem;color:#666;"><?= sanitize($b['slug']) ?></code></td>
                                        <td style="color:#888;font-size:0.85rem;"><?= sanitize($b['country'] ?? '—') ?></td>
                                        <td style="text-align:center;">
                                            <span class="badge badge-<?= $b['part_count'] > 0 ? 'info' : 'warning' ?>">
                                                <?= (int)$b['part_count'] ?>
                                            </span>
                                        </td>
                                        <td style="font-size:0.8rem;color:#888;max-width:180px;">
                                            <?= sanitize(truncate($b['description'] ?? '', 60)) ?>
                                        </td>
                                        <td style="text-align:center;white-space:nowrap;">
                                            <a href="?action=edit&id=<?= (int)$b['id'] ?>"
                                               class="az-btn az-btn-secondary az-btn-sm">
                                                <i class="fa fa-pencil"></i> Ред.
                                            </a>
                                            <form method="POST" action="" style="display:inline;"
                                                  onsubmit="return confirm('Удалить бренд «<?= sanitize(addslashes($b['name'])) ?>»?')">
                                                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
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
            <?php endif; ?>

        </div><!-- /.az-content -->
    </main>
</div><!-- /.az-panel -->

<script>
async function brUploadLogo(input) {
    const file = input.files[0];
    if (!file) return;
    const status = document.getElementById('brLogoStatus');
    status.style.color = '#0a7';
    status.textContent = 'Загрузка...';
    const fd = new FormData();
    fd.append('file', file);
    try {
        const res  = await fetch('<?= APP_URL ?>/api/upload.php?type=brands', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.url) {
            document.getElementById('brLogoPath').value = data.url;
            document.getElementById('brLogoEl').src = data.url;
            document.getElementById('brLogoPreview').style.display = '';
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
function brRemoveLogo() {
    document.getElementById('brLogoPath').value = '';
    document.getElementById('brLogoPreview').style.display = 'none';
    document.getElementById('brLogoStatus').textContent = '';
}
</script>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
