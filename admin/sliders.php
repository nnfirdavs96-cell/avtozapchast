<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['admin', 'manager', 'superadmin']);
requirePermission('sliders');

$db   = getDB();
$csrf = generateCsrfToken();

// ── Ensure sliders table exists ───────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS `sliders` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `title`      VARCHAR(255)  NOT NULL DEFAULT '',
  `subtitle`   VARCHAR(255)  NOT NULL DEFAULT '',
  `image_url`  VARCHAR(500)  NOT NULL,
  `link_url`   VARCHAR(500)  NOT NULL DEFAULT '',
  `sort_order` SMALLINT      NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── POST handler ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF ошибка.');
        redirect(APP_URL . '/admin/sliders.php');
    }

    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'delete') {
        $delId = (int)($_POST['id'] ?? 0);
        if ($delId) $db->prepare("DELETE FROM sliders WHERE id = ?")->execute([$delId]);
        flashMessage('success', 'Слайд удалён.');
        redirect(APP_URL . '/admin/sliders.php');
    }

    if ($postAction === 'toggle') {
        $tid = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE sliders SET is_active = NOT is_active WHERE id = ?")->execute([$tid]);
        redirect(APP_URL . '/admin/sliders.php');
    }

    if ($postAction === 'reorder') {
        $order = json_decode($_POST['order'] ?? '[]', true) ?: [];
        foreach ($order as $sort => $sid) {
            $db->prepare("UPDATE sliders SET sort_order = ? WHERE id = ?")->execute([(int)$sort, (int)$sid]);
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    // Save (add / edit)
    $sid      = (int)($_POST['id'] ?? 0);
    $title    = trim($_POST['title'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $imgUrl   = trim($_POST['image_url'] ?? '');
    $linkUrl  = trim($_POST['link_url'] ?? '');
    $sort     = (int)($_POST['sort_order'] ?? 0);

    if (empty($imgUrl)) {
        flashMessage('danger', 'Загрузите изображение.');
        redirect(APP_URL . '/admin/sliders.php' . ($sid ? "?edit=$sid" : '?action=new'));
    }

    if ($sid) {
        $db->prepare(
            "UPDATE sliders SET title=?, subtitle=?, image_url=?, link_url=?, sort_order=? WHERE id=?"
        )->execute([$title, $subtitle, $imgUrl, $linkUrl, $sort, $sid]);
        flashMessage('success', 'Слайд обновлён.');
    } else {
        $db->prepare(
            "INSERT INTO sliders (title, subtitle, image_url, link_url, sort_order, is_active) VALUES (?,?,?,?,?,1)"
        )->execute([$title, $subtitle, $imgUrl, $linkUrl, $sort]);
        flashMessage('success', 'Слайд добавлен.');
    }
    redirect(APP_URL . '/admin/sliders.php');
}

// Load edit
$editSlide = null;
$action    = $_GET['action'] ?? 'list';
$editId    = (int)($_GET['edit'] ?? 0);
if ($editId) {
    $stmt = $db->prepare("SELECT * FROM sliders WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $editSlide = $stmt->fetch();
    $action = 'edit';
}
if ($_GET['action'] ?? '' === 'new') $action = 'new';

$sliders = $db->query("SELECT * FROM sliders ORDER BY sort_order ASC, id ASC")->fetchAll();

$pageTitle = 'Слайдер — Администратор';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="az-panel">
    <?php renderRoleSidebar('sliders'); ?>

    <main class="az-main">
        <div class="az-topbar">
            <h1>Управление слайдером главной страницы</h1>
            <span style="font-size:0.85rem;color:#666;"><?= sanitize($_SESSION['username'] ?? '') ?></span>
        </div>

        <div class="az-content">

            <?php if ($flash = getFlashMessage()): ?>
                <div class="az-alert az-alert-<?= sanitize($flash['type']) ?>"><?= sanitize($flash['message']) ?></div>
            <?php endif; ?>

            <?php if (in_array($action, ['new', 'edit'])): ?>
            <!-- ── Form ──────────────────────────────────────────── -->
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <h2 style="margin:0;font-size:1.1rem;"><?= $action === 'edit' ? 'Редактировать слайд' : 'Новый слайд' ?></h2>
                <a href="<?= APP_URL ?>/admin/sliders.php" class="az-btn az-btn-secondary az-btn-sm">← Список</a>
            </div>

            <div style="max-width:640px;">
                <form method="POST" action="" id="sliderForm">
                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                    <input type="hidden" name="action" value="<?= $action === 'edit' ? 'edit' : 'add' ?>">
                    <?php if ($editSlide): ?><input type="hidden" name="id" value="<?= (int)$editSlide['id'] ?>"><?php endif; ?>

                    <div class="az-card">
                        <h3>Изображение слайда *</h3>
                        <div id="imgPreviewWrap" style="margin-bottom:12px;">
                            <?php if ($editSlide && $editSlide['image_url']): ?>
                                <img src="<?= sanitize($editSlide['image_url']) ?>" id="imgPreview"
                                     style="max-width:100%;max-height:220px;object-fit:cover;border-radius:6px;border:1px solid #dee2e6;">
                            <?php else: ?>
                                <div id="imgPlaceholder"
                                     style="width:100%;height:180px;background:#f5f5f5;border:2px dashed #ced4da;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#bbb;font-size:2.5rem;">
                                    <i class="fa fa-image"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <label style="display:inline-flex;align-items:center;gap:8px;padding:9px 16px;background:#f8f9fa;border:2px dashed #ced4da;border-radius:6px;cursor:pointer;font-size:0.875rem;color:#555;">
                            <i class="fa fa-upload"></i> <?= $editSlide ? 'Заменить изображение' : 'Загрузить изображение' ?>
                            <input type="file" id="sliderImg" accept="image/*" style="display:none;" onchange="uploadSliderImg(this)">
                        </label>
                        <span id="uploadStatus" style="font-size:0.8rem;color:#888;margin-left:8px;"></span>
                        <input type="hidden" name="image_url" id="imageUrl"
                               value="<?= sanitize($editSlide['image_url'] ?? '') ?>">
                    </div>

                    <div class="az-card">
                        <h3>Текст и ссылка</h3>
                        <div class="az-form-group">
                            <label>Заголовок слайда</label>
                            <input type="text" name="title"
                                   value="<?= sanitize($editSlide['title'] ?? '') ?>"
                                   placeholder="Авто запчасти по лучшим ценам">
                        </div>
                        <div class="az-form-group">
                            <label>Подзаголовок</label>
                            <input type="text" name="subtitle"
                                   value="<?= sanitize($editSlide['subtitle'] ?? '') ?>"
                                   placeholder="Более 10 000 позиций в наличии">
                        </div>
                        <div class="az-form-group">
                            <label>Ссылка кнопки (URL)</label>
                            <input type="text" name="link_url"
                                   value="<?= sanitize($editSlide['link_url'] ?? '') ?>"
                                   placeholder="/shop.php">
                        </div>
                        <div class="az-form-group">
                            <label>Порядок сортировки</label>
                            <input type="number" name="sort_order" min="0"
                                   value="<?= (int)($editSlide['sort_order'] ?? 0) ?>"
                                   style="max-width:100px;">
                        </div>
                    </div>

                    <div style="display:flex;gap:10px;">
                        <button type="submit" class="az-btn az-btn-primary">
                            <i class="fa fa-save"></i> <?= $action === 'edit' ? 'Сохранить' : 'Добавить слайд' ?>
                        </button>
                        <a href="<?= APP_URL ?>/admin/sliders.php" class="az-btn az-btn-secondary">Отмена</a>
                    </div>
                </form>
            </div>

            <script>
            async function uploadSliderImg(input) {
                const status = document.getElementById('uploadStatus');
                const fd = new FormData();
                fd.append('file', input.files[0]);
                status.textContent = 'Загрузка...';
                try {
                    const res  = await fetch('<?= APP_URL ?>/api/upload.php?type=sliders', { method:'POST', body:fd });
                    const data = await res.json();
                    if (data.url) {
                        document.getElementById('imageUrl').value = data.url;
                        const prev = document.getElementById('imgPreview');
                        const ph   = document.getElementById('imgPlaceholder');
                        if (prev) { prev.src = data.url; }
                        else {
                            if (ph) ph.remove();
                            const img = document.createElement('img');
                            img.id = 'imgPreview';
                            img.src = data.url;
                            img.style.cssText = 'max-width:100%;max-height:220px;object-fit:cover;border-radius:6px;border:1px solid #dee2e6;';
                            document.getElementById('imgPreviewWrap').appendChild(img);
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
                <h2 style="margin:0;font-size:1.1rem;">Слайды (<?= count($sliders) ?> шт.)</h2>
                <a href="?action=new" class="az-btn az-btn-primary">
                    <i class="fa fa-plus"></i> Добавить слайд
                </a>
            </div>

            <?php if (empty($sliders)): ?>
                <div class="az-card" style="text-align:center;padding:48px;color:#aaa;">
                    <i class="fa fa-picture-o" style="font-size:3rem;display:block;margin-bottom:12px;color:#ddd;"></i>
                    Слайдов ещё нет. <a href="?action=new" style="color:#d32f2f;">Добавить первый</a>
                </div>
            <?php else: ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;">
                    <?php foreach ($sliders as $slide): ?>
                    <div class="az-card" style="padding:0;overflow:hidden;<?= !$slide['is_active'] ? 'opacity:0.55;' : '' ?>">
                        <div style="position:relative;height:160px;background:#f5f5f5;">
                            <?php if ($slide['image_url']): ?>
                                <img src="<?= sanitize($slide['image_url']) ?>" alt=""
                                     style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>
                                <div style="height:100%;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:2.5rem;">
                                    <i class="fa fa-image"></i>
                                </div>
                            <?php endif; ?>
                            <div style="position:absolute;top:8px;right:8px;">
                                <span class="badge badge-<?= $slide['is_active'] ? 'success' : 'danger' ?>">
                                    <?= $slide['is_active'] ? 'Активен' : 'Скрыт' ?>
                                </span>
                            </div>
                            <div style="position:absolute;bottom:8px;left:8px;background:rgba(0,0,0,0.55);color:#fff;border-radius:4px;padding:2px 8px;font-size:0.75rem;">
                                #<?= (int)$slide['sort_order'] ?>
                            </div>
                        </div>
                        <div style="padding:14px;">
                            <?php if ($slide['title']): ?>
                                <div style="font-weight:700;font-size:0.9rem;margin-bottom:4px;"><?= sanitize($slide['title']) ?></div>
                            <?php endif; ?>
                            <?php if ($slide['subtitle']): ?>
                                <div style="font-size:0.8rem;color:#888;margin-bottom:8px;"><?= sanitize($slide['subtitle']) ?></div>
                            <?php endif; ?>
                            <?php if ($slide['link_url']): ?>
                                <div style="font-size:0.75rem;color:#aaa;word-break:break-all;"><?= sanitize($slide['link_url']) ?></div>
                            <?php endif; ?>
                            <div style="display:flex;gap:8px;margin-top:12px;">
                                <a href="?edit=<?= (int)$slide['id'] ?>" class="az-btn az-btn-secondary az-btn-sm">
                                    <i class="fa fa-pencil"></i> Ред.
                                </a>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int)$slide['id'] ?>">
                                    <button type="submit" class="az-btn az-btn-sm <?= $slide['is_active'] ? 'az-btn-secondary' : 'az-btn-success' ?>">
                                        <i class="fa fa-<?= $slide['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;"
                                      onsubmit="return confirm('Удалить этот слайд?')">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$slide['id'] ?>">
                                    <button type="submit" class="az-btn az-btn-danger az-btn-sm"><i class="fa fa-trash-o"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p style="font-size:0.8rem;color:#aaa;margin-top:16px;">
                    <i class="fa fa-info-circle"></i> Для изменения порядка отредактируйте поле «Порядок сортировки» каждого слайда.
                </p>
            <?php endif; ?>

            <?php endif; // list / form ?>

        </div><!-- /.az-content -->
    </main>
</div><!-- /.az-panel -->

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
