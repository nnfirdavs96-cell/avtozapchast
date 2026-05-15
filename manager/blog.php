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
        flashMessage('danger', 'CSRF ошибка.');
        redirect(APP_URL . '/manager/blog.php');
    }

    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'delete') {
        $delId = (int)($_POST['id'] ?? 0);
        if ($delId) $db->prepare("DELETE FROM blog_posts WHERE id = ?")->execute([$delId]);
        flashMessage('success', 'Статья удалена.');
        redirect(APP_URL . '/manager/blog.php');
    }

    if ($postAction === 'toggle') {
        $tid = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE blog_posts SET is_published = NOT is_published WHERE id = ?")->execute([$tid]);
        redirect(APP_URL . '/manager/blog.php');
    }

    // Save post
    $uid        = (int)($_POST['id'] ?? 0);
    $slug       = trim($_POST['slug'] ?? '');
    $title_ru   = trim($_POST['title_ru'] ?? '');
    $title_tg   = trim($_POST['title_tg'] ?? '');
    $title_en   = trim($_POST['title_en'] ?? '');
    $excerpt_ru = trim($_POST['excerpt_ru'] ?? '');
    $excerpt_tg = trim($_POST['excerpt_tg'] ?? '');
    $excerpt_en = trim($_POST['excerpt_en'] ?? '');
    $body_ru    = trim($_POST['body_ru'] ?? '');
    $body_tg    = trim($_POST['body_tg'] ?? '');
    $body_en    = trim($_POST['body_en'] ?? '');
    $imagePath  = trim($_POST['image_path'] ?? '');
    $category   = trim($_POST['category'] ?? 'news');
    $published  = isset($_POST['is_published']) ? 1 : 0;
    $allowedCats = ['news','tips','review','other'];
    if (!in_array($category, $allowedCats)) $category = 'news';

    if (empty($slug))     $errors[] = 'Укажите slug (URL-идентификатор).';
    if (!preg_match('/^[a-z0-9\-]+$/i', $slug)) $errors[] = 'Slug: только латиница, цифры, дефис.';
    if (empty($title_ru)) $errors[] = 'Укажите заголовок (RU).';

    if (empty($errors)) {
        $chk = $db->prepare("SELECT id FROM blog_posts WHERE slug = ? AND id != ?");
        $chk->execute([$slug, $uid]);
        if ($chk->fetch()) $errors[] = 'Такой slug уже используется.';
    }

    if (empty($errors)) {
        if ($uid) {
            $db->prepare(
                "UPDATE blog_posts SET slug=?, category=?, title_ru=?, title_tg=?, title_en=?,
                 excerpt_ru=?, excerpt_tg=?, excerpt_en=?,
                 body_ru=?, body_tg=?, body_en=?, image_path=?, is_published=?, updated_at=NOW()
                 WHERE id=?"
            )->execute([$slug, $category, $title_ru, $title_tg, $title_en,
                        $excerpt_ru, $excerpt_tg, $excerpt_en,
                        $body_ru, $body_tg, $body_en,
                        $imagePath ?: null, $published, $uid]);
            flashMessage('success', 'Статья обновлена.');
        } else {
            $db->prepare(
                "INSERT INTO blog_posts (slug, category, title_ru, title_tg, title_en,
                 excerpt_ru, excerpt_tg, excerpt_en, body_ru, body_tg, body_en,
                 image_path, is_published, author_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
            )->execute([$slug, $category, $title_ru, $title_tg, $title_en,
                        $excerpt_ru, $excerpt_tg, $excerpt_en,
                        $body_ru, $body_tg, $body_en,
                        $imagePath ?: null, $published, $_SESSION['user_id']]);
            flashMessage('success', 'Статья добавлена.');
        }
        redirect(APP_URL . '/manager/blog.php');
    }

    $action = $uid ? 'edit' : 'new';
    $editId = $uid;
}

// Load for edit
$editPost = null;
if ($editId && $action === 'edit') {
    $stmt = $db->prepare("SELECT * FROM blog_posts WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $editPost = $stmt->fetch();
}

// List
$search  = trim($_GET['search'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$where   = [];
$params  = [];
if ($search) {
    $where[] = '(slug LIKE ? OR title_ru LIKE ? OR title_en LIKE ?)';
    $params  = ["%$search%", "%$search%", "%$search%"];
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$cntStmt = $db->prepare("SELECT COUNT(*) FROM blog_posts $whereSQL");
$cntStmt->execute($params);
$total = (int)$cntStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$postsStmt = $db->prepare(
    "SELECT p.*, u.username AS author_name
     FROM blog_posts p LEFT JOIN users u ON u.id = p.author_id
     $whereSQL ORDER BY p.created_at DESC LIMIT $perPage OFFSET $offset"
);
$postsStmt->execute($params);
$posts = $postsStmt->fetchAll();

$pageTitle = 'Блог — Менеджер';
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
            <li><a href="<?= APP_URL ?>/manager/blog.php" class="active"><i class="fa fa-newspaper-o"></i> Блог</a></li>
            <li><a href="<?= APP_URL ?>/manager/pages.php"><i class="fa fa-file-text-o"></i> Страницы</a></li>
            <li><a href="<?= APP_URL ?>/manager/reviews.php"><i class="fa fa-star"></i> Отзывы</a></li>
            <li style="border-top:1px solid rgba(255,255,255,0.1);margin-top:12px;">
                <a href="<?= APP_URL ?>/index.php"><i class="fa fa-home"></i> На сайт</a>
            </li>
            <li><a href="<?= APP_URL ?>/auth/logout.php" style="color:rgba(255,100,100,0.85)!important;"><i class="fa fa-sign-out"></i> Выйти</a></li>
        </ul></nav>
    </aside>

    <main class="az-main">
        <div class="az-topbar">
            <h1>Управление блогом</h1>
            <span style="font-size:0.85rem;color:#666;">
                <?= sanitize($_SESSION['username'] ?? '') ?>
                <span style="background:#d32f2f;color:#fff;border-radius:4px;padding:2px 7px;font-size:0.72rem;margin-left:4px;"><?= sanitize($_SESSION['role'] ?? '') ?></span>
            </span>
        </div>

        <div class="az-content">

            <?php if ($flash = getFlashMessage()): ?>
                <div class="az-alert az-alert-<?= sanitize($flash['type']) ?>"><?= sanitize($flash['message']) ?></div>
            <?php endif; ?>

            <?php if (in_array($action, ['new', 'edit'])): ?>
            <!-- ── Form ────────────────────────────────────────────── -->
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <h2 style="margin:0;font-size:1.1rem;"><?= $action === 'edit' ? 'Редактировать статью' : 'Новая статья' ?></h2>
                <a href="<?= APP_URL ?>/manager/blog.php" class="az-btn az-btn-secondary az-btn-sm">← Список</a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="az-alert az-alert-danger">
                    <?php foreach ($errors as $e): ?><div>• <?= sanitize($e) ?></div><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                <input type="hidden" name="action" value="<?= $action === 'edit' ? 'edit' : 'add' ?>">
                <?php if ($editPost): ?><input type="hidden" name="id" value="<?= (int)$editPost['id'] ?>"><?php endif; ?>
                <input type="hidden" name="image_path" id="imagePath" value="<?= sanitize($editPost['image_path'] ?? '') ?>">

                <div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;">

                    <!-- Left -->
                    <div>
                        <div class="az-card">
                            <h3>Идентификатор и настройки</h3>
                            <div style="display:grid;grid-template-columns:1fr auto;gap:12px;align-items:end;">
                                <div class="az-form-group" style="margin-bottom:0;">
                                    <label>Slug (URL) *</label>
                                    <input type="text" name="slug"
                                           value="<?= sanitize($editPost['slug'] ?? ($_POST['slug'] ?? '')) ?>"
                                           placeholder="kak-vybrat-maslo-dlya-dvigatelya"
                                           pattern="[a-zA-Z0-9\-]+" required
                                           id="slugField">
                                </div>
                                <button type="button" class="az-btn az-btn-secondary az-btn-sm"
                                        onclick="generateSlug()" style="margin-bottom:0;">
                                    <i class="fa fa-magic"></i> Из заголовка
                                </button>
                            </div>
                            <p style="font-size:0.78rem;color:#aaa;margin-top:6px;margin-bottom:0;">
                                Будет: <code>/blog/<?= sanitize($editPost['slug'] ?? 'slug') ?></code>
                            </p>
                        </div>

                        <!-- Russian -->
                        <div class="az-card">
                            <h3><span style="background:#cc0000;color:#fff;border-radius:3px;padding:1px 6px;font-size:0.75rem;">RU</span> Русский</h3>
                            <div class="az-form-group">
                                <label>Заголовок *</label>
                                <input type="text" name="title_ru" id="titleRu"
                                       value="<?= sanitize($editPost['title_ru'] ?? ($_POST['title_ru'] ?? '')) ?>"
                                       placeholder="Как выбрать масло для двигателя" required>
                            </div>
                            <div class="az-form-group">
                                <label>Краткое описание (анонс)</label>
                                <textarea name="excerpt_ru" rows="2" placeholder="Краткое описание статьи..."><?= sanitize($editPost['excerpt_ru'] ?? ($_POST['excerpt_ru'] ?? '')) ?></textarea>
                            </div>
                            <div class="az-form-group" style="margin-bottom:0;">
                                <label>Текст статьи</label>
                                <textarea name="body_ru" rows="10" placeholder="Основной текст статьи на русском..."><?= sanitize($editPost['body_ru'] ?? ($_POST['body_ru'] ?? '')) ?></textarea>
                            </div>
                        </div>

                        <!-- Tajik -->
                        <div class="az-card">
                            <h3><span style="background:#006600;color:#fff;border-radius:3px;padding:1px 6px;font-size:0.75rem;">TG</span> Тоҷикӣ</h3>
                            <div class="az-form-group">
                                <label>Сарлавҳа</label>
                                <input type="text" name="title_tg"
                                       value="<?= sanitize($editPost['title_tg'] ?? ($_POST['title_tg'] ?? '')) ?>"
                                       placeholder="Чӣ тавр равғани муҳаррик интихоб кардан">
                            </div>
                            <div class="az-form-group">
                                <label>Хулоса</label>
                                <textarea name="excerpt_tg" rows="2"><?= sanitize($editPost['excerpt_tg'] ?? ($_POST['excerpt_tg'] ?? '')) ?></textarea>
                            </div>
                            <div class="az-form-group" style="margin-bottom:0;">
                                <label>Матни мақола</label>
                                <textarea name="body_tg" rows="6"><?= sanitize($editPost['body_tg'] ?? ($_POST['body_tg'] ?? '')) ?></textarea>
                            </div>
                        </div>

                        <!-- English -->
                        <div class="az-card">
                            <h3><span style="background:#003399;color:#fff;border-radius:3px;padding:1px 6px;font-size:0.75rem;">EN</span> English</h3>
                            <div class="az-form-group">
                                <label>Title</label>
                                <input type="text" name="title_en"
                                       value="<?= sanitize($editPost['title_en'] ?? ($_POST['title_en'] ?? '')) ?>"
                                       placeholder="How to choose engine oil">
                            </div>
                            <div class="az-form-group">
                                <label>Excerpt</label>
                                <textarea name="excerpt_en" rows="2"><?= sanitize($editPost['excerpt_en'] ?? ($_POST['excerpt_en'] ?? '')) ?></textarea>
                            </div>
                            <div class="az-form-group" style="margin-bottom:0;">
                                <label>Body</label>
                                <textarea name="body_en" rows="6"><?= sanitize($editPost['body_en'] ?? ($_POST['body_en'] ?? '')) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Right -->
                    <div>
                        <div class="az-card">
                            <h3>Публикация</h3>
                            <div class="az-form-group">
                                <label>Категория</label>
                                <select name="category" style="width:100%;padding:8px 10px;border:1px solid #ced4da;border-radius:6px;font-size:0.875rem;">
                                    <?php
                                    $cats = ['news'=>'Новости','tips'=>'Советы по ТО','review'=>'Обзоры запчастей','other'=>'Другое'];
                                    $curCat = $editPost['category'] ?? $_POST['category'] ?? 'news';
                                    foreach ($cats as $v => $l):
                                    ?>
                                    <option value="<?= $v ?>" <?= $curCat === $v ? 'selected' : '' ?>><?= $l ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:0.875rem;">
                                <input type="checkbox" name="is_published" value="1"
                                       <?= ($editPost['is_published'] ?? 1) ? 'checked' : '' ?>
                                       style="width:16px;height:16px;">
                                Опубликовать статью
                            </label>
                        </div>

                        <div class="az-card">
                            <h3>Обложка статьи</h3>
                            <div id="imgPreviewWrap" style="margin-bottom:12px;">
                                <?php if (!empty($editPost['image_path'])): ?>
                                    <img src="<?= sanitize($editPost['image_path']) ?>" id="imgPreview"
                                         style="width:100%;max-height:180px;object-fit:cover;border-radius:6px;border:1px solid #dee2e6;">
                                <?php else: ?>
                                    <div id="imgPlaceholder"
                                         style="width:100%;height:140px;background:#f5f5f5;border:2px dashed #ced4da;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:2.5rem;">
                                        <i class="fa fa-image"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <label style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:#f8f9fa;border:2px dashed #ced4da;border-radius:6px;cursor:pointer;font-size:0.825rem;color:#555;">
                                <i class="fa fa-upload"></i> Загрузить обложку
                                <input type="file" id="coverImg" accept="image/*" style="display:none;" onchange="uploadCover(this)">
                            </label>
                            <span id="uploadStatus" style="font-size:0.78rem;color:#888;display:block;margin-top:6px;"></span>
                            <?php if (!empty($editPost['image_path'])): ?>
                                <button type="button" onclick="removeCover()"
                                        class="az-btn az-btn-secondary az-btn-sm" style="margin-top:8px;">
                                    <i class="fa fa-trash-o"></i> Удалить обложку
                                </button>
                            <?php endif; ?>
                        </div>

                        <div style="display:flex;flex-direction:column;gap:10px;">
                            <button type="submit" class="az-btn az-btn-primary">
                                <i class="fa fa-save"></i> <?= $action === 'edit' ? 'Сохранить' : 'Опубликовать' ?>
                            </button>
                            <a href="<?= APP_URL ?>/manager/blog.php" class="az-btn az-btn-secondary" style="text-align:center;">Отмена</a>
                        </div>
                    </div>

                </div><!-- /grid -->
            </form>

            <script>
            function generateSlug() {
                const title = document.getElementById('titleRu').value;
                const slug = title.toLowerCase()
                    .replace(/[а-яёa-z0-9]+/gi, m => m.split('').map(c => ({
                        'а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ё':'yo','ж':'zh',
                        'з':'z','и':'i','й':'j','к':'k','л':'l','м':'m','н':'n','о':'o',
                        'п':'p','р':'r','с':'s','т':'t','у':'u','ф':'f','х':'kh','ц':'ts',
                        'ч':'ch','ш':'sh','щ':'shch','ъ':'','ы':'y','ь':'','э':'e','ю':'yu','я':'ya'
                    }[c] || c)).join(''))
                    .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
                document.getElementById('slugField').value = slug;
            }

            async function uploadCover(input) {
                const status = document.getElementById('uploadStatus');
                const fd = new FormData(); fd.append('file', input.files[0]);
                status.textContent = 'Загрузка...';
                try {
                    const res  = await fetch('<?= APP_URL ?>/api/upload.php?type=blog', {method:'POST',body:fd});
                    const data = await res.json();
                    if (data.url) {
                        document.getElementById('imagePath').value = data.url;
                        const ph = document.getElementById('imgPlaceholder');
                        const prev = document.getElementById('imgPreview');
                        if (ph) ph.remove();
                        if (prev) { prev.src = data.url; }
                        else {
                            const img = document.createElement('img');
                            img.id = 'imgPreview'; img.src = data.url;
                            img.style.cssText = 'width:100%;max-height:180px;object-fit:cover;border-radius:6px;border:1px solid #dee2e6;';
                            document.getElementById('imgPreviewWrap').appendChild(img);
                        }
                        status.textContent = 'Загружено';
                    } else { status.textContent = data.error || 'Ошибка'; }
                } catch(e) { status.textContent = 'Ошибка сети'; }
                input.value = '';
            }

            function removeCover() {
                document.getElementById('imagePath').value = '';
                const prev = document.getElementById('imgPreview');
                if (prev) {
                    prev.insertAdjacentHTML('afterend', `<div id="imgPlaceholder" style="width:100%;height:140px;background:#f5f5f5;border:2px dashed #ced4da;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:2.5rem;"><i class="fa fa-image"></i></div>`);
                    prev.remove();
                }
            }
            </script>

            <?php else: ?>
            <!-- ── List ────────────────────────────────────────────── -->
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
                <h2 style="margin:0;font-size:1.1rem;">Статьи блога
                    <span style="font-size:0.85rem;font-weight:400;color:#888;margin-left:8px;"><?= $total ?> шт.</span>
                </h2>
                <a href="?action=new" class="az-btn az-btn-primary">
                    <i class="fa fa-plus"></i> Написать статью
                </a>
            </div>

            <div class="az-card" style="padding:14px 20px;margin-bottom:16px;">
                <form method="GET" style="display:flex;gap:10px;align-items:center;">
                    <input type="text" name="search" value="<?= sanitize($search) ?>"
                           placeholder="Поиск по slug или заголовку..."
                           style="flex:1;padding:8px 12px;border:1px solid #ced4da;border-radius:6px;font-size:0.875rem;outline:none;">
                    <button type="submit" class="az-btn az-btn-primary az-btn-sm"><i class="fa fa-search"></i></button>
                    <?php if ($search): ?><a href="<?= APP_URL ?>/manager/blog.php" class="az-btn az-btn-secondary az-btn-sm">Сброс</a><?php endif; ?>
                </form>
            </div>

            <div class="az-card" style="padding:0;overflow:hidden;">
                <table class="az-table">
                    <thead>
                        <tr>
                            <th style="width:70px;">Фото</th>
                            <th>Заголовок (RU)</th>
                            <th>Категория</th>
                            <th>Автор</th>
                            <th style="text-align:center;">Статус</th>
                            <th>Дата</th>
                            <th style="text-align:center;">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($posts)): ?>
                            <tr><td colspan="7" style="text-align:center;color:#aaa;padding:32px;">Статей ещё нет.</td></tr>
                        <?php else: ?>
                            <?php foreach ($posts as $post): ?>
                            <tr>
                                <td>
                                    <?php if ($post['image_path']): ?>
                                        <img src="<?= sanitize($post['image_path']) ?>" alt=""
                                             style="width:58px;height:42px;object-fit:cover;border-radius:4px;border:1px solid #eee;">
                                    <?php else: ?>
                                        <div style="width:58px;height:42px;background:#f5f5f5;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#ddd;">
                                            <i class="fa fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.875rem;font-weight:600;">
                                    <?= sanitize(truncate($post['title_ru'], 60)) ?>
                                </td>
                                <td>
                                    <?php $catLabels = ['news'=>'Новости','tips'=>'Советы по ТО','review'=>'Обзоры','other'=>'Другое']; ?>
                                    <span class="badge badge-info" style="font-size:0.72rem;">
                                        <?= $catLabels[$post['category'] ?? 'news'] ?? 'Новости' ?>
                                    </span>
                                </td>
                                <td style="font-size:0.8rem;color:#888;"><?= sanitize($post['author_name'] ?? '—') ?></td>
                                <td style="text-align:center;">
                                    <span class="badge badge-<?= $post['is_published'] ? 'success' : 'warning' ?>">
                                        <?= $post['is_published'] ? 'Опубликована' : 'Черновик' ?>
                                    </span>
                                </td>
                                <td style="font-size:0.8rem;color:#888;">
                                    <?= date('d.m.Y', strtotime($post['created_at'])) ?>
                                </td>
                                <td style="text-align:center;white-space:nowrap;">
                                    <a href="?action=edit&id=<?= (int)$post['id'] ?>" class="az-btn az-btn-secondary az-btn-sm">
                                        <i class="fa fa-pencil"></i>
                                    </a>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
                                        <button type="submit" class="az-btn az-btn-sm <?= $post['is_published'] ? 'az-btn-secondary' : 'az-btn-success' ?>">
                                            <i class="fa fa-<?= $post['is_published'] ? 'eye-slash' : 'eye' ?>"></i>
                                        </button>
                                    </form>
                                    <form method="POST" style="display:inline;"
                                          onsubmit="return confirm('Удалить статью «<?= sanitize(addslashes($post['title_ru'])) ?>»?')">
                                        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
                                        <button type="submit" class="az-btn az-btn-danger az-btn-sm"><i class="fa fa-trash-o"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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
