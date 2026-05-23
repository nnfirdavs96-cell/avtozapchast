<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['admin', 'manager', 'superadmin']);
requirePermission('sliders');

$db   = getDB();
$csrf = generateCsrfToken();

// ── Ensure banners table exists ───────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS `banners` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `title`            VARCHAR(255)  NOT NULL DEFAULT '',
  `image_url`        VARCHAR(500)  NOT NULL DEFAULT '',
  `image_url_mobile` VARCHAR(500)  NOT NULL DEFAULT '',
  `link_url`         VARCHAR(500)  NOT NULL DEFAULT '',
  `sort_order`       SMALLINT      NOT NULL DEFAULT 0,
  `is_active`        TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add mobile column to pre-existing tables
try { $db->exec("ALTER TABLE `banners` ADD COLUMN IF NOT EXISTS `image_url_mobile` VARCHAR(500) NOT NULL DEFAULT '' AFTER `image_url`"); } catch (Throwable $e) {}

// ── POST handler ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF ошибка.');
        redirect(APP_URL . '/admin/banners.php');
    }

    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'delete') {
        $delId = (int)($_POST['id'] ?? 0);
        if ($delId) $db->prepare("DELETE FROM banners WHERE id = ?")->execute([$delId]);
        flashMessage('success', 'Баннер удалён.');
        redirect(APP_URL . '/admin/banners.php');
    }

    if ($postAction === 'toggle') {
        $tid = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE banners SET is_active = NOT is_active WHERE id = ?")->execute([$tid]);
        redirect(APP_URL . '/admin/banners.php');
    }

    // Save (add / edit)
    $bid       = (int)($_POST['id'] ?? 0);
    $title     = trim($_POST['title'] ?? '');
    $imgUrl    = trim($_POST['image_url'] ?? '');
    $imgMobile = trim($_POST['image_url_mobile'] ?? '');
    $linkUrl   = trim($_POST['link_url'] ?? '');
    $sort      = (int)($_POST['sort_order'] ?? 0);

    if ($imgUrl === '' && $imgMobile === '') {
        flashMessage('danger', 'Загрузите хотя бы одно изображение (десктоп или мобильное).');
        redirect(APP_URL . '/admin/banners.php' . ($bid ? "?edit=$bid" : '?action=new'));
    }

    if ($bid) {
        $db->prepare(
            "UPDATE banners SET title=?, image_url=?, image_url_mobile=?, link_url=?, sort_order=? WHERE id=?"
        )->execute([$title, $imgUrl, $imgMobile, $linkUrl, $sort, $bid]);
        flashMessage('success', 'Баннер обновлён.');
    } else {
        $db->prepare(
            "INSERT INTO banners (title, image_url, image_url_mobile, link_url, sort_order, is_active) VALUES (?,?,?,?,?,1)"
        )->execute([$title, $imgUrl, $imgMobile, $linkUrl, $sort]);
        flashMessage('success', 'Баннер добавлен.');
    }
    redirect(APP_URL . '/admin/banners.php');
}

// Load edit
$editBanner = null;
$action     = $_GET['action'] ?? 'list';
$editId     = (int)($_GET['edit'] ?? 0);
if ($editId) {
    $stmt = $db->prepare("SELECT * FROM banners WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $editBanner = $stmt->fetch();
    $action = 'edit';
}
if (($_GET['action'] ?? '') === 'new') $action = 'new';

$banners = $db->query("SELECT * FROM banners ORDER BY sort_order ASC, id ASC")->fetchAll();

$pageTitle = 'Баннеры — Администратор';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="az-panel">
    <?php renderRoleSidebar('banners'); ?>

    <main class="az-main">
        <div class="az-topbar">
            <h1>Баннеры главной страницы</h1>
            <span style="font-size:0.85rem;color:#666;"><?= sanitize($_SESSION['username'] ?? '') ?></span>
        </div>

        <div class="az-content">

            <?php if ($flash = getFlashMessage()): ?>
                <div class="az-alert az-alert-<?= sanitize($flash['type']) ?>"><?= sanitize($flash['message']) ?></div>
            <?php endif; ?>

            <?php if (in_array($action, ['new', 'edit'])): ?>
            <!-- ── Form ──────────────────────────────────────────── -->
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <h2 style="margin:0;font-size:1.1rem;"><?= $action === 'edit' ? 'Редактировать баннер' : 'Новый баннер' ?></h2>
                <a href="<?= APP_URL ?>/admin/banners.php" class="az-btn az-btn-secondary az-btn-sm">← Список</a>
            </div>

            <div style="max-width:640px;">
                <form method="POST" action="" id="bannerForm">
                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                    <input type="hidden" name="action" value="<?= $action === 'edit' ? 'edit' : 'add' ?>">
                    <?php if ($editBanner): ?><input type="hidden" name="id" value="<?= (int)$editBanner['id'] ?>"><?php endif; ?>

                    <div class="az-alert az-alert-info" style="font-size:0.83rem;">
                        <i class="fa fa-info-circle"></i> Загрузите хотя бы одну версию. Если мобильная не задана —
                        на телефонах покажется десктопная (и наоборот).
                    </div>

                    <div class="az-card">
                        <h3><i class="fa fa-desktop"></i> Версия для десктопа</h3>
                        <div id="dtPreviewWrap" style="margin-bottom:12px;">
                            <?php if ($editBanner && !empty($editBanner['image_url'])): ?>
                                <img src="<?= sanitize($editBanner['image_url']) ?>" id="dtPreview"
                                     style="max-width:100%;max-height:220px;object-fit:cover;border-radius:6px;border:1px solid #dee2e6;">
                            <?php else: ?>
                                <div id="dtPlaceholder"
                                     style="width:100%;height:160px;background:#f5f5f5;border:2px dashed #ced4da;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#bbb;font-size:2.5rem;">
                                    <i class="fa fa-image"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <label style="display:inline-flex;align-items:center;gap:8px;padding:9px 16px;background:#f8f9fa;border:2px dashed #ced4da;border-radius:6px;cursor:pointer;font-size:0.875rem;color:#555;">
                            <i class="fa fa-upload"></i> Загрузить изображение
                            <input type="file" accept="image/*" style="display:none;"
                                   onchange="uploadBannerImg(this, 'imageUrl', 'dtPreview', 'dtPlaceholder', 'dtPreviewWrap', 'dtStatus')">
                        </label>
                        <span id="dtStatus" style="font-size:0.8rem;color:#888;margin-left:8px;"></span>
                        <input type="hidden" name="image_url" id="imageUrl"
                               value="<?= sanitize($editBanner['image_url'] ?? '') ?>">
                        <p style="font-size:0.78rem;color:#aaa;margin-top:10px;">
                            <i class="fa fa-info-circle"></i> Рекомендуемый размер: ~570×320px (горизонтальный).
                        </p>
                    </div>

                    <div class="az-card">
                        <h3><i class="fa fa-mobile"></i> Версия для мобильного</h3>
                        <div id="mbPreviewWrap" style="margin-bottom:12px;">
                            <?php if ($editBanner && !empty($editBanner['image_url_mobile'])): ?>
                                <img src="<?= sanitize($editBanner['image_url_mobile']) ?>" id="mbPreview"
                                     style="max-width:100%;max-height:280px;object-fit:cover;border-radius:6px;border:1px solid #dee2e6;">
                            <?php else: ?>
                                <div id="mbPlaceholder"
                                     style="width:100%;height:160px;background:#f5f5f5;border:2px dashed #ced4da;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#bbb;font-size:2.5rem;">
                                    <i class="fa fa-mobile"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <label style="display:inline-flex;align-items:center;gap:8px;padding:9px 16px;background:#f8f9fa;border:2px dashed #ced4da;border-radius:6px;cursor:pointer;font-size:0.875rem;color:#555;">
                            <i class="fa fa-upload"></i> Загрузить изображение
                            <input type="file" accept="image/*" style="display:none;"
                                   onchange="uploadBannerImg(this, 'imageUrlMobile', 'mbPreview', 'mbPlaceholder', 'mbPreviewWrap', 'mbStatus')">
                        </label>
                        <span id="mbStatus" style="font-size:0.8rem;color:#888;margin-left:8px;"></span>
                        <input type="hidden" name="image_url_mobile" id="imageUrlMobile"
                               value="<?= sanitize($editBanner['image_url_mobile'] ?? '') ?>">
                        <p style="font-size:0.78rem;color:#aaa;margin-top:10px;">
                            <i class="fa fa-info-circle"></i> Рекомендуемый размер: ~640×800px (вертикальный).
                        </p>
                    </div>

                    <div class="az-card">
                        <h3>Описание и ссылка</h3>
                        <div class="az-form-group">
                            <label>Название (для админки)</label>
                            <input type="text" name="title"
                                   value="<?= sanitize($editBanner['title'] ?? '') ?>"
                                   placeholder="Например: Двигатели — скидка 10%">
                        </div>
                        <div class="az-form-group">
                            <label>Ссылка при клике (URL)</label>
                            <input type="text" name="link_url"
                                   value="<?= sanitize($editBanner['link_url'] ?? '') ?>"
                                   placeholder="/catalog/index.php">
                        </div>
                        <div class="az-form-group">
                            <label>Порядок сортировки</label>
                            <input type="number" name="sort_order" min="0"
                                   value="<?= (int)($editBanner['sort_order'] ?? 0) ?>"
                                   style="max-width:100px;">
                        </div>
                    </div>

                    <div style="display:flex;gap:10px;">
                        <button type="submit" class="az-btn az-btn-primary">
                            <i class="fa fa-save"></i> <?= $action === 'edit' ? 'Сохранить' : 'Добавить баннер' ?>
                        </button>
                        <a href="<?= APP_URL ?>/admin/banners.php" class="az-btn az-btn-secondary">Отмена</a>
                    </div>
                </form>
            </div>

            <script>
            async function uploadBannerImg(input, fieldId, previewId, placeholderId, wrapId, statusId) {
                const status = document.getElementById(statusId);
                if (!input.files || !input.files[0]) return;
                const fd = new FormData();
                fd.append('file', input.files[0]);
                status.textContent = 'Загрузка...';
                try {
                    const res  = await fetch('<?= APP_URL ?>/api/upload.php?type=banners', { method:'POST', body:fd });
                    const data = await res.json();
                    if (data.url) {
                        document.getElementById(fieldId).value = data.url;
                        const prev = document.getElementById(previewId);
                        const ph   = document.getElementById(placeholderId);
                        if (prev) { prev.src = data.url; }
                        else {
                            if (ph) ph.remove();
                            const img = document.createElement('img');
                            img.id = previewId;
                            img.src = data.url;
                            img.style.cssText = 'max-width:100%;max-height:280px;object-fit:cover;border-radius:6px;border:1px solid #dee2e6;';
                            document.getElementById(wrapId).appendChild(img);
                        }
                        status.textContent = 'Загружено';
                    } else {
                        status.textContent = data.error || 'Ошибка';
                    }
                } catch (e) {
                    status.textContent = 'Ошибка сети';
                }
                input.value = '';
            }
            </script>

            <?php else: ?>
            <!-- ── List ──────────────────────────────────────────── -->
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <h2 style="margin:0;font-size:1.1rem;">Баннеры (<?= count($banners) ?> шт.)</h2>
                <a href="?action=new" class="az-btn az-btn-primary">
                    <i class="fa fa-plus"></i> Добавить баннер
                </a>
            </div>

            <div class="az-alert az-alert-info" style="font-size:0.85rem;">
                <i class="fa fa-info-circle"></i> Это три рекламных баннера под слайдером на главной.
                Если баннеров нет — на сайте показываются стандартные картинки из шаблона.
            </div>

            <?php if (empty($banners)): ?>
                <div class="az-card" style="text-align:center;padding:48px;color:#aaa;">
                    <i class="fa fa-clone" style="font-size:3rem;display:block;margin-bottom:12px;color:#ddd;"></i>
                    Баннеров ещё нет. <a href="?action=new" style="color:#d32f2f;">Добавить первый</a>
                </div>
            <?php else: ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;">
                    <?php foreach ($banners as $banner): ?>
                    <?php $previewImg = $banner['image_url'] ?: ($banner['image_url_mobile'] ?? ''); ?>
                    <div class="az-card" style="padding:0;overflow:hidden;<?= !$banner['is_active'] ? 'opacity:0.55;' : '' ?>">
                        <div style="position:relative;height:160px;background:#f5f5f5;">
                            <?php if ($previewImg): ?>
                                <img src="<?= sanitize($previewImg) ?>" alt=""
                                     style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>
                                <div style="height:100%;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:2.5rem;">
                                    <i class="fa fa-image"></i>
                                </div>
                            <?php endif; ?>
                            <div style="position:absolute;top:8px;right:8px;display:flex;gap:4px;">
                                <?php if (!empty($banner['image_url'])): ?>
                                    <span class="badge badge-secondary" title="Есть десктоп-версия"><i class="fa fa-desktop"></i></span>
                                <?php endif; ?>
                                <?php if (!empty($banner['image_url_mobile'])): ?>
                                    <span class="badge badge-secondary" title="Есть мобильная версия"><i class="fa fa-mobile"></i></span>
                                <?php endif; ?>
                                <span class="badge badge-<?= $banner['is_active'] ? 'success' : 'danger' ?>">
                                    <?= $banner['is_active'] ? 'Активен' : 'Скрыт' ?>
                                </span>
                            </div>
                            <div style="position:absolute;bottom:8px;left:8px;background:rgba(0,0,0,0.55);color:#fff;border-radius:4px;padding:2px 8px;font-size:0.75rem;">
                                #<?= (int)$banner['sort_order'] ?>
                            </div>
                        </div>
                        <div style="padding:14px;">
                            <?php if ($banner['title']): ?>
                                <div style="font-weight:700;font-size:0.9rem;margin-bottom:4px;"><?= sanitize($banner['title']) ?></div>
                            <?php endif; ?>
                            <?php if ($banner['link_url']): ?>
                                <div style="font-size:0.75rem;color:#aaa;word-break:break-all;"><?= sanitize($banner['link_url']) ?></div>
                            <?php endif; ?>
                            <div style="display:flex;gap:8px;margin-top:12px;">
                                <a href="?edit=<?= (int)$banner['id'] ?>" class="az-btn az-btn-secondary az-btn-sm">
                                    <i class="fa fa-pencil"></i> Ред.
                                </a>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int)$banner['id'] ?>">
                                    <button type="submit" class="az-btn az-btn-sm <?= $banner['is_active'] ? 'az-btn-secondary' : 'az-btn-success' ?>">
                                        <i class="fa fa-<?= $banner['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;"
                                      onsubmit="return confirm('Удалить этот баннер?')">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$banner['id'] ?>">
                                    <button type="submit" class="az-btn az-btn-danger az-btn-sm"><i class="fa fa-trash-o"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php endif; // list / form ?>

        </div><!-- /.az-content -->
    </main>
</div><!-- /.az-panel -->

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
