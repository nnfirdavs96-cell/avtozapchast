<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['manager', 'admin', 'superadmin']);
requirePermission('pages');

$db     = getDB();
$csrf   = generateCsrfToken();
$action = $_GET['action'] ?? 'list';
$editId = (int)($_GET['id'] ?? 0);
$errors = [];

// ── POST ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF ошибка.');
        redirect(APP_URL . '/manager/pages.php');
    }

    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'toggle') {
        $tid = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE site_sections SET is_active = NOT is_active WHERE id = ?")->execute([$tid]);
        redirect(APP_URL . '/manager/pages.php');
    }

    // Save section
    $uid        = (int)($_POST['id'] ?? 0);
    $title_ru   = trim($_POST['title_ru']   ?? '');
    $title_tg   = trim($_POST['title_tg']   ?? '');
    $title_en   = trim($_POST['title_en']   ?? '');
    $subtitle_ru = trim($_POST['subtitle_ru'] ?? '');
    $subtitle_tg = trim($_POST['subtitle_tg'] ?? '');
    $subtitle_en = trim($_POST['subtitle_en'] ?? '');
    $content_ru = trim($_POST['content_ru'] ?? '');
    $content_tg = trim($_POST['content_tg'] ?? '');
    $content_en = trim($_POST['content_en'] ?? '');
    $image      = trim($_POST['image']       ?? '');
    $sortOrder  = (int)($_POST['sort_order'] ?? 0);
    $isActive   = isset($_POST['is_active']) ? 1 : 0;

    if (empty($title_ru)) $errors[] = 'Укажите заголовок (RU).';

    if (empty($errors) && $uid) {
        $db->prepare(
            "UPDATE site_sections
             SET title_ru=?, title_tg=?, title_en=?,
                 subtitle_ru=?, subtitle_tg=?, subtitle_en=?,
                 content_ru=?, content_tg=?, content_en=?,
                 image=?, sort_order=?, is_active=?
             WHERE id=?"
        )->execute([$title_ru, $title_tg, $title_en,
                    $subtitle_ru, $subtitle_tg, $subtitle_en,
                    $content_ru ?: null, $content_tg ?: null, $content_en ?: null,
                    $image ?: null, $sortOrder, $isActive, $uid]);
        flashMessage('success', 'Раздел обновлён.');
        redirect(APP_URL . '/manager/pages.php');
    }

    $action = 'edit';
    $editId = $uid;
}

// Load for edit
$editSection = null;
if ($editId && $action === 'edit') {
    $stmt = $db->prepare("SELECT * FROM site_sections WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $editSection = $stmt->fetch();
}

// List all sections
$sections = $db->query(
    "SELECT * FROM site_sections ORDER BY section_group, sort_order, id"
)->fetchAll();

$pageTitle = 'Страницы — Менеджер';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="az-panel">

    <aside class="az-sidebar">
        <div class="az-sidebar-logo">AUTO<span>PARTS</span></div>
        <nav><ul>
            <li><a href="<?= APP_URL ?>/manager/index.php"><i class="fa fa-dashboard"></i> Панель</a></li>
            <li><a href="<?= APP_URL ?>/manager/parts.php"><i class="fa fa-cogs"></i> Запчасти</a></li>
            <li><a href="<?= APP_URL ?>/manager/categories.php"><i class="fa fa-sitemap"></i> Категории</a></li>
            <li><a href="<?= APP_URL ?>/manager/brands.php"><i class="fa fa-tag"></i> Бренды</a></li>
            <li><a href="<?= APP_URL ?>/manager/blog.php"><i class="fa fa-newspaper-o"></i> Блог</a></li>
            <li><a href="<?= APP_URL ?>/manager/pages.php" class="active"><i class="fa fa-file-text-o"></i> Страницы</a></li>
            <li><a href="<?= APP_URL ?>/manager/reviews.php"><i class="fa fa-star"></i> Отзывы</a></li>
            <li style="border-top:1px solid rgba(255,255,255,0.1);margin-top:12px;">
                <a href="<?= APP_URL ?>/index.php"><i class="fa fa-home"></i> На сайт</a>
            </li>
            <li><a href="<?= APP_URL ?>/auth/logout.php" style="color:rgba(255,100,100,0.85)!important;"><i class="fa fa-sign-out"></i> Выйти</a></li>
        </ul></nav>
    </aside>

    <main class="az-main">
        <div class="az-topbar">
            <h1>Управление страницами</h1>
            <span style="font-size:0.85rem;color:#666;">
                <?= sanitize($_SESSION['username'] ?? '') ?>
                <span style="background:#d32f2f;color:#fff;border-radius:4px;padding:2px 7px;font-size:0.72rem;margin-left:4px;"><?= sanitize($_SESSION['role'] ?? '') ?></span>
            </span>
        </div>

        <div class="az-content">

            <?php if ($flash = getFlashMessage()): ?>
                <div class="az-alert az-alert-<?= sanitize($flash['type']) ?>"><?= sanitize($flash['message']) ?></div>
            <?php endif; ?>

            <?php if ($action === 'edit' && $editSection): ?>
            <!-- ── Edit form ──────────────────────────────────────────── -->
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <h2 style="margin:0;font-size:1.1rem;">Редактировать раздел: <em><?= sanitize($editSection['title_ru']) ?></em></h2>
                <a href="<?= APP_URL ?>/manager/pages.php" class="az-btn az-btn-secondary az-btn-sm">← Назад</a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="az-alert az-alert-danger">
                    <?php foreach ($errors as $e): ?><div>• <?= sanitize($e) ?></div><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?= (int)$editSection['id'] ?>">
                <input type="hidden" name="image" id="sectionImage" value="<?= sanitize($editSection['image'] ?? '') ?>">

                <div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start;">

                    <div>
                        <?php
                        $hasSubtitle = in_array($editSection['section_group'] ?? '', ['testimonials']);
                        $subtitleLabel = ['ru'=>'Должность / Роль','tg'=>'Вазифа / Нақш','en'=>'Role / Position'];
                        ?>
                        <!-- RU -->
                        <div class="az-card">
                            <h3><span style="background:#cc0000;color:#fff;border-radius:3px;padding:1px 6px;font-size:0.75rem;">RU</span> Русский</h3>
                            <div class="az-form-group">
                                <label>Заголовок *</label>
                                <input type="text" name="title_ru"
                                       value="<?= sanitize($editSection['title_ru'] ?? '') ?>" required>
                            </div>
                            <?php if ($hasSubtitle): ?>
                            <div class="az-form-group">
                                <label><?= $subtitleLabel['ru'] ?></label>
                                <input type="text" name="subtitle_ru"
                                       value="<?= sanitize($editSection['subtitle_ru'] ?? '') ?>"
                                       placeholder="Например: Постоянный клиент">
                            </div>
                            <?php else: ?>
                                <input type="hidden" name="subtitle_ru" value="<?= sanitize($editSection['subtitle_ru'] ?? '') ?>">
                            <?php endif; ?>
                            <div class="az-form-group" style="margin-bottom:0;">
                                <label>Текст раздела</label>
                                <textarea name="content_ru" rows="6"
                                          placeholder="Текст на русском языке..."><?= sanitize($editSection['content_ru'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <!-- TG -->
                        <div class="az-card">
                            <h3><span style="background:#006600;color:#fff;border-radius:3px;padding:1px 6px;font-size:0.75rem;">TG</span> Тоҷикӣ</h3>
                            <div class="az-form-group">
                                <label>Сарлавҳа</label>
                                <input type="text" name="title_tg"
                                       value="<?= sanitize($editSection['title_tg'] ?? '') ?>">
                            </div>
                            <?php if ($hasSubtitle): ?>
                            <div class="az-form-group">
                                <label><?= $subtitleLabel['tg'] ?></label>
                                <input type="text" name="subtitle_tg"
                                       value="<?= sanitize($editSection['subtitle_tg'] ?? '') ?>">
                            </div>
                            <?php else: ?>
                                <input type="hidden" name="subtitle_tg" value="<?= sanitize($editSection['subtitle_tg'] ?? '') ?>">
                            <?php endif; ?>
                            <div class="az-form-group" style="margin-bottom:0;">
                                <label>Матн</label>
                                <textarea name="content_tg" rows="5"><?= sanitize($editSection['content_tg'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <!-- EN -->
                        <div class="az-card">
                            <h3><span style="background:#003399;color:#fff;border-radius:3px;padding:1px 6px;font-size:0.75rem;">EN</span> English</h3>
                            <div class="az-form-group">
                                <label>Title</label>
                                <input type="text" name="title_en"
                                       value="<?= sanitize($editSection['title_en'] ?? '') ?>">
                            </div>
                            <?php if ($hasSubtitle): ?>
                            <div class="az-form-group">
                                <label><?= $subtitleLabel['en'] ?></label>
                                <input type="text" name="subtitle_en"
                                       value="<?= sanitize($editSection['subtitle_en'] ?? '') ?>">
                            </div>
                            <?php else: ?>
                                <input type="hidden" name="subtitle_en" value="<?= sanitize($editSection['subtitle_en'] ?? '') ?>">
                            <?php endif; ?>
                            <div class="az-form-group" style="margin-bottom:0;">
                                <label>Content</label>
                                <textarea name="content_en" rows="5"><?= sanitize($editSection['content_en'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Right sidebar -->
                    <div>
                        <div class="az-card">
                            <h3>Настройки</h3>
                            <div class="az-form-group">
                                <label>Порядок сортировки</label>
                                <input type="number" name="sort_order" value="<?= (int)$editSection['sort_order'] ?>" min="0" max="99">
                            </div>
                            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:0.875rem;">
                                <input type="checkbox" name="is_active" value="1"
                                       <?= $editSection['is_active'] ? 'checked' : '' ?>
                                       style="width:16px;height:16px;">
                                Показывать на сайте
                            </label>
                        </div>

                        <div class="az-card">
                            <h3>Изображение раздела</h3>
                            <div id="imgPreviewWrap" style="margin-bottom:12px;">
                                <?php if (!empty($editSection['image'])): ?>
                                    <img src="<?= sanitize($editSection['image']) ?>" id="imgPreview"
                                         style="width:100%;max-height:200px;object-fit:cover;border-radius:6px;border:1px solid #dee2e6;">
                                <?php else: ?>
                                    <div id="imgPlaceholder"
                                         style="width:100%;height:120px;background:#f5f5f5;border:2px dashed #ced4da;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:2rem;">
                                        <i class="fa fa-image"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <label style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:#f8f9fa;border:2px dashed #ced4da;border-radius:6px;cursor:pointer;font-size:0.825rem;color:#555;margin-bottom:8px;">
                                <i class="fa fa-upload"></i> Загрузить изображение
                                <input type="file" id="sectionImg" accept="image/*" style="display:none;" onchange="uploadSectionImg(this)">
                            </label>
                            <span id="uploadStatus" style="font-size:0.78rem;color:#888;display:block;"></span>
                            <div style="margin-top:8px;padding:10px 12px;background:#eef6ff;border:1px solid #cfe4fb;border-radius:6px;font-size:0.78rem;color:#1c5a99;line-height:1.55;">
                                <i class="fa fa-info-circle"></i> <strong>Рекомендуемый размер:</strong> 1000&times;600&nbsp;px (соотношение&nbsp;5:3)<br>
                                Формат: <strong>JPG</strong> или WEBP &middot; до&nbsp;5&nbsp;МБ
                            </div>
                            <?php if (!empty($editSection['image'])): ?>
                                <button type="button" onclick="removeImg()"
                                        class="az-btn az-btn-secondary az-btn-sm" style="margin-top:4px;">
                                    <i class="fa fa-trash-o"></i> Удалить фото
                                </button>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="az-btn az-btn-primary" style="width:100%;">
                            <i class="fa fa-save"></i> Сохранить
                        </button>
                    </div>
                </div>
            </form>

            <script>
            async function uploadSectionImg(input) {
                const status = document.getElementById('uploadStatus');
                const fd = new FormData(); fd.append('file', input.files[0]);
                status.textContent = 'Загрузка...';
                try {
                    const res  = await fetch('<?= APP_URL ?>/api/upload.php?type=blog', {method:'POST',body:fd});
                    const data = await res.json();
                    if (data.url) {
                        document.getElementById('sectionImage').value = data.url;
                        const ph = document.getElementById('imgPlaceholder');
                        const prev = document.getElementById('imgPreview');
                        if (ph) ph.remove();
                        if (prev) prev.src = data.url;
                        else {
                            const img = document.createElement('img');
                            img.id = 'imgPreview'; img.src = data.url;
                            img.style.cssText = 'width:100%;max-height:200px;object-fit:cover;border-radius:6px;border:1px solid #dee2e6;';
                            document.getElementById('imgPreviewWrap').appendChild(img);
                        }
                        status.textContent = 'Загружено ✓';
                    } else { status.textContent = data.error || 'Ошибка'; }
                } catch(e) { status.textContent = 'Ошибка сети'; }
                input.value = '';
            }
            function removeImg() {
                document.getElementById('sectionImage').value = '';
                const prev = document.getElementById('imgPreview');
                if (prev) {
                    prev.insertAdjacentHTML('afterend', `<div id="imgPlaceholder" style="width:100%;height:120px;background:#f5f5f5;border:2px dashed #ced4da;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:2rem;"><i class="fa fa-image"></i></div>`);
                    prev.remove();
                }
            }
            </script>

            <?php else: ?>
            <!-- ── List ───────────────────────────────────────────────── -->
            <h2 style="margin:0 0 16px;font-size:1.1rem;">Разделы страниц сайта</h2>

            <?php
            $groups = [
                'about'        => ['label' => 'О нас — Основные разделы',         'icon' => 'fa-info-circle',    'link' => '/pages/about.php#about'],
                'benefits'     => ['label' => 'О нас — Преимущества (3 иконки)',   'icon' => 'fa-star',           'link' => '/pages/about.php'],
                'faq'          => ['label' => 'О нас — FAQ / Аккордеон',           'icon' => 'fa-question-circle','link' => '/pages/about.php#reviews'],
                'testimonials' => ['label' => 'О нас — Отзывы клиентов',           'icon' => 'fa-comments',       'link' => '/pages/about.php#reviews'],
            ];
            foreach ($groups as $groupKey => $groupMeta):
                $groupSections = array_filter($sections, fn($s) => $s['section_group'] === $groupKey);
                $groupLabel = $groupMeta['label'];
            ?>
            <div class="az-card" style="padding:0;overflow:hidden;margin-bottom:20px;">
                <div style="padding:14px 20px;background:#f8f9fa;border-bottom:1px solid #eee;display:flex;align-items:center;justify-content:space-between;">
                    <strong style="font-size:0.9rem;"><i class="fa <?= $groupMeta['icon'] ?>" style="color:#d32f2f;margin-right:6px;"></i><?= $groupLabel ?></strong>
                    <a href="<?= APP_URL . $groupMeta['link'] ?>" target="_blank" class="az-btn az-btn-secondary az-btn-sm">
                        <i class="fa fa-external-link"></i> Посмотреть
                    </a>
                </div>
                <table class="az-table">
                    <thead>
                        <tr>
                            <th style="width:50px;">Фото</th>
                            <th>Раздел (slug)</th>
                            <th>Заголовок RU</th>
                            <th><?= $groupKey === 'testimonials' ? 'Роль' : 'Таджикский' ?></th>
                            <th style="text-align:center;">Статус</th>
                            <th style="text-align:center;">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($groupSections as $sec): ?>
                        <tr>
                            <td>
                                <?php if ($sec['image']): ?>
                                    <img src="<?= sanitize($sec['image']) ?>" alt=""
                                         style="width:44px;height:36px;object-fit:cover;border-radius:4px;border:1px solid #eee;">
                                <?php else: ?>
                                    <div style="width:44px;height:36px;background:#f5f5f5;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#ddd;">
                                        <i class="fa fa-image"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><code style="font-size:0.78rem;color:#888;"><?= sanitize($sec['slug']) ?></code></td>
                            <td style="font-size:0.875rem;font-weight:600;"><?= sanitize($sec['title_ru']) ?></td>
                            <td style="font-size:0.82rem;color:#666;">
                                <?php if ($groupKey === 'testimonials'): ?>
                                    <?= sanitize($sec['subtitle_ru'] ?: '—') ?>
                                <?php else: ?>
                                    <?= sanitize($sec['title_tg'] ?: '—') ?>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <span class="badge badge-<?= $sec['is_active'] ? 'success' : 'warning' ?>">
                                    <?= $sec['is_active'] ? 'Активен' : 'Скрыт' ?>
                                </span>
                            </td>
                            <td style="text-align:center;white-space:nowrap;">
                                <a href="?action=edit&id=<?= (int)$sec['id'] ?>"
                                   class="az-btn az-btn-secondary az-btn-sm">
                                    <i class="fa fa-pencil"></i> Изменить
                                </a>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int)$sec['id'] ?>">
                                    <button type="submit" class="az-btn az-btn-sm <?= $sec['is_active'] ? 'az-btn-secondary' : 'az-btn-success' ?>"
                                            title="<?= $sec['is_active'] ? 'Скрыть' : 'Показать' ?>">
                                        <i class="fa fa-<?= $sec['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>

            <div class="az-card" style="background:#fff8e1;border:1px solid #ffe082;">
                <p style="margin:0;font-size:0.875rem;color:#795548;">
                    <i class="fa fa-info-circle"></i>
                    <strong>Блог-категории</strong> (Новости, Советы по ТО, Обзоры) управляются в разделе
                    <a href="<?= APP_URL ?>/manager/blog.php">Блог</a> — при создании/редактировании статьи выбирайте категорию.
                </p>
            </div>

            <?php endif; ?>

        </div>
    </main>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
