<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['admin', 'superadmin']);

$db     = getDB();
$csrf   = generateCsrfToken();
$action = $_GET['action'] ?? 'list';
$editId = (int)($_GET['id'] ?? 0);
$errors = [];

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF ошибка.'); redirect(APP_URL . '/superadmin/blog.php');
    }

    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'delete') {
        $delId = (int)($_POST['id'] ?? 0);
        if ($delId) {
            $db->prepare("DELETE FROM blog_posts WHERE id = ?")->execute([$delId]);
            flashMessage('success', 'Статья удалена.');
        }
        redirect(APP_URL . '/superadmin/blog.php');
    }

    // Save/update post
    $slug        = trim($_POST['slug'] ?? '');
    $title_ru    = trim($_POST['title_ru'] ?? '');
    $title_tg    = trim($_POST['title_tg'] ?? '');
    $title_en    = trim($_POST['title_en'] ?? '');
    $excerpt_ru  = trim($_POST['excerpt_ru'] ?? '');
    $excerpt_tg  = trim($_POST['excerpt_tg'] ?? '');
    $excerpt_en  = trim($_POST['excerpt_en'] ?? '');
    $body_ru     = trim($_POST['body_ru'] ?? '');
    $body_tg     = trim($_POST['body_tg'] ?? '');
    $body_en     = trim($_POST['body_en'] ?? '');
    $published   = isset($_POST['is_published']) ? 1 : 0;
    $uid         = (int)($_POST['id'] ?? 0);

    // Validate
    if (mb_strlen($slug) < 2)    $errors[] = 'Slug слишком короткий.';
    if (!preg_match('/^[a-z0-9\-]+$/i', $slug)) $errors[] = 'Slug может содержать только буквы a-z, цифры и дефис.';
    if (mb_strlen($title_ru) < 3) $errors[] = 'Заголовок (RU) обязателен.';

    if (empty($errors)) {
        // Check slug uniqueness
        $chk = $db->prepare("SELECT id FROM blog_posts WHERE slug = ? AND id != ?");
        $chk->execute([$slug, $uid]);
        if ($chk->fetch()) $errors[] = 'Такой slug уже используется другой статьёй.';
    }

    if (empty($errors)) {
        if ($uid) {
            $db->prepare(
                "UPDATE blog_posts SET slug=?, title_ru=?, title_tg=?, title_en=?,
                 excerpt_ru=?, excerpt_tg=?, excerpt_en=?,
                 body_ru=?, body_tg=?, body_en=?, is_published=?, updated_at=NOW()
                 WHERE id=?"
            )->execute([$slug, $title_ru, $title_tg, $title_en,
                        $excerpt_ru, $excerpt_tg, $excerpt_en,
                        $body_ru, $body_tg, $body_en, $published, $uid]);
            flashMessage('success', 'Статья обновлена.');
        } else {
            $db->prepare(
                "INSERT INTO blog_posts (slug, title_ru, title_tg, title_en,
                 excerpt_ru, excerpt_tg, excerpt_en, body_ru, body_tg, body_en,
                 is_published, created_at, updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())"
            )->execute([$slug, $title_ru, $title_tg, $title_en,
                        $excerpt_ru, $excerpt_tg, $excerpt_en,
                        $body_ru, $body_tg, $body_en, $published]);
            flashMessage('success', 'Статья создана.');
        }
        redirect(APP_URL . '/superadmin/blog.php');
    }
    $action = $uid ? 'edit' : 'new';
    $editId = $uid;
}

// Load for edit
$editPost = null;
if ($editId && in_array($action, ['edit'])) {
    $stmt = $db->prepare("SELECT * FROM blog_posts WHERE id = ?");
    $stmt->execute([$editId]);
    $editPost = $stmt->fetch();
    if (!$editPost) { flashMessage('danger', 'Статья не найдена.'); redirect(APP_URL . '/superadmin/blog.php'); }
}

// List
$posts = [];
try {
    $posts = $db->query(
        "SELECT id, slug, title_ru, is_published, created_at, updated_at FROM blog_posts ORDER BY created_at DESC"
    )->fetchAll();
} catch (Exception $e) {
    flashMessage('danger', 'Ошибка: таблица blog_posts не найдена.');
}

// Helper: get POST or editPost field
function bf(string $field, ?array $edit = null): string {
    if (isset($_POST[$field])) return sanitize($_POST[$field]);
    return sanitize($edit[$field] ?? '');
}

$pageTitle = 'Блог — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="az-panel">
  <aside class="az-sidebar" style="background:#1a0533;">
    <div class="az-sidebar-brand" style="background:rgba(155,89,182,0.3);border-bottom-color:rgba(155,89,182,0.3);">
      <span style="color:#ce93d8;">&#x2605;</span> Суперадмин
    </div>
    <nav class="az-sidebar-nav">
      <a href="<?= APP_URL ?>/superadmin/index.php" class="az-sidebar-link"><i class="fa fa-star"></i> Панель</a>
      <a href="<?= APP_URL ?>/admin/users.php" class="az-sidebar-link"><i class="fa fa-users"></i> Пользователи</a>
      <a href="<?= APP_URL ?>/admin/orders.php" class="az-sidebar-link"><i class="fa fa-shopping-bag"></i> Заказы</a>
      <a href="<?= APP_URL ?>/superadmin/settings.php" class="az-sidebar-link"><i class="fa fa-cog"></i> Настройки</a>
      <a href="<?= APP_URL ?>/superadmin/currencies.php" class="az-sidebar-link"><i class="fa fa-money"></i> Валюты</a>
      <a href="<?= APP_URL ?>/superadmin/languages.php" class="az-sidebar-link"><i class="fa fa-language"></i> Языки</a>
      <a href="<?= APP_URL ?>/superadmin/warehouse.php" class="az-sidebar-link"><i class="fa fa-database"></i> Склад API</a>
      <a href="<?= APP_URL ?>/superadmin/blog.php" class="az-sidebar-link active" style="color:#ce93d8;"><i class="fa fa-newspaper-o"></i> Блог</a>
      <hr style="border-color:rgba(255,255,255,0.1);margin:12px 0;">
      <a href="<?= APP_URL ?>/index.php" class="az-sidebar-link"><i class="fa fa-home"></i> На сайт</a>
    </nav>
  </aside>

  <div class="az-main">
    <div class="az-topbar" style="border-bottom-color:#9b59b622;background:#f9f5ff;">
      <div class="az-topbar-title" style="color:#6a1b9a;">Управление блогом</div>
      <div class="az-topbar-user"><?= sanitize($_SESSION['username'] ?? '') ?> &middot; <a href="<?= APP_URL ?>/auth/logout.php">Выйти</a></div>
    </div>

    <div class="az-content">
      <?php if ($flash = getFlashMessage()): ?>
      <div class="alert alert-<?= sanitize($flash['type']) ?> mb-16"><?= sanitize($flash['message']) ?></div>
      <?php endif; ?>

      <?php if (in_array($action, ['new', 'edit'])): ?>
      <!-- Post form -->
      <div class="az-card mb-24">
        <div class="az-card-header">
          <h4 class="az-card-title"><?= $action === 'edit' ? 'Редактировать статью' : 'Новая статья' ?></h4>
          <a href="<?= APP_URL ?>/superadmin/blog.php" class="az-btn az-btn-outline az-btn-sm">← Список</a>
        </div>
        <div class="az-card-body">
          <?php if (!empty($errors)): ?>
          <div class="alert alert-danger mb-16">
            <?php foreach ($errors as $e): ?><div>• <?= sanitize($e) ?></div><?php endforeach; ?>
          </div>
          <?php endif; ?>

          <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="action" value="<?= $action === 'edit' ? 'edit' : 'add' ?>">
            <?php if ($editPost): ?><input type="hidden" name="id" value="<?= (int)$editPost['id'] ?>"><?php endif; ?>

            <div class="row">
              <div class="col-md-6">
                <div class="az-form-group">
                  <label>Slug (URL) * <small style="font-weight:400;">(только a-z, 0-9, дефис)</small></label>
                  <input type="text" name="slug" class="form-control" value="<?= bf('slug', $editPost) ?>"
                         pattern="[a-zA-Z0-9\-]+" required placeholder="my-article-slug">
                </div>
              </div>
              <div class="col-md-6">
                <div class="az-form-group">
                  <label>Публикация</label>
                  <div class="form-check" style="margin-top:8px;">
                    <input type="checkbox" name="is_published" id="is_published" class="form-check-input"
                           value="1" <?= ($editPost ? $editPost['is_published'] : 0) || isset($_POST['is_published']) ? 'checked' : '' ?>>
                    <label for="is_published" class="form-check-label">Опубликовать статью</label>
                  </div>
                </div>
              </div>
            </div>

            <!-- Titles -->
            <h5 style="margin:20px 0 12px;font-size:0.9rem;text-transform:uppercase;color:#666;letter-spacing:0.05em;">Заголовки</h5>
            <div class="row">
              <div class="col-md-4">
                <div class="az-form-group">
                  <label>Заголовок (RU) *</label>
                  <input type="text" name="title_ru" class="form-control" value="<?= bf('title_ru', $editPost) ?>" required>
                </div>
              </div>
              <div class="col-md-4">
                <div class="az-form-group">
                  <label>Заголовок (TG)</label>
                  <input type="text" name="title_tg" class="form-control" value="<?= bf('title_tg', $editPost) ?>">
                </div>
              </div>
              <div class="col-md-4">
                <div class="az-form-group">
                  <label>Заголовок (EN)</label>
                  <input type="text" name="title_en" class="form-control" value="<?= bf('title_en', $editPost) ?>">
                </div>
              </div>
            </div>

            <!-- Excerpts -->
            <h5 style="margin:20px 0 12px;font-size:0.9rem;text-transform:uppercase;color:#666;letter-spacing:0.05em;">Краткое описание</h5>
            <div class="row">
              <div class="col-md-4">
                <div class="az-form-group">
                  <label>Анонс (RU)</label>
                  <textarea name="excerpt_ru" class="form-control" rows="3"><?= bf('excerpt_ru', $editPost) ?></textarea>
                </div>
              </div>
              <div class="col-md-4">
                <div class="az-form-group">
                  <label>Анонс (TG)</label>
                  <textarea name="excerpt_tg" class="form-control" rows="3"><?= bf('excerpt_tg', $editPost) ?></textarea>
                </div>
              </div>
              <div class="col-md-4">
                <div class="az-form-group">
                  <label>Анонс (EN)</label>
                  <textarea name="excerpt_en" class="form-control" rows="3"><?= bf('excerpt_en', $editPost) ?></textarea>
                </div>
              </div>
            </div>

            <!-- Body -->
            <h5 style="margin:20px 0 12px;font-size:0.9rem;text-transform:uppercase;color:#666;letter-spacing:0.05em;">Полный текст</h5>
            <div class="row">
              <div class="col-md-4">
                <div class="az-form-group">
                  <label>Текст (RU)</label>
                  <textarea name="body_ru" class="form-control" rows="10"><?= bf('body_ru', $editPost) ?></textarea>
                </div>
              </div>
              <div class="col-md-4">
                <div class="az-form-group">
                  <label>Текст (TG)</label>
                  <textarea name="body_tg" class="form-control" rows="10"><?= bf('body_tg', $editPost) ?></textarea>
                </div>
              </div>
              <div class="col-md-4">
                <div class="az-form-group">
                  <label>Текст (EN)</label>
                  <textarea name="body_en" class="form-control" rows="10"><?= bf('body_en', $editPost) ?></textarea>
                </div>
              </div>
            </div>

            <button type="submit" class="az-btn az-btn-primary"><?= $editPost ? 'Сохранить изменения' : 'Создать статью' ?></button>
          </form>
        </div>
      </div>
      <?php else: ?>

      <!-- List -->
      <div class="az-card mb-16">
        <div class="az-card-body">
          <div class="d-flex align-items-center">
            <span style="color:#888;font-size:0.85rem;">Всего статей: <?= count($posts) ?></span>
            <a href="?action=new" class="az-btn az-btn-primary" style="margin-left:auto;">+ Новая статья</a>
          </div>
        </div>
      </div>

      <div class="az-card">
        <div class="az-card-body p-0">
          <div class="table-responsive">
            <table class="az-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Slug</th>
                  <th>Заголовок (RU)</th>
                  <th style="text-align:center;">Опубликована</th>
                  <th>Создана</th>
                  <th>Действия</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($posts as $post): ?>
                <tr>
                  <td><?= (int)$post['id'] ?></td>
                  <td><code><?= sanitize($post['slug']) ?></code></td>
                  <td>
                    <strong><?= sanitize(truncate($post['title_ru'], 60)) ?></strong>
                  </td>
                  <td style="text-align:center;">
                    <span class="badge badge-<?= $post['is_published'] ? 'success' : 'secondary' ?>">
                      <?= $post['is_published'] ? 'Да' : 'Черновик' ?>
                    </span>
                  </td>
                  <td style="font-size:0.8rem;color:#888;white-space:nowrap;">
                    <?= date('d.m.Y', strtotime($post['created_at'])) ?>
                  </td>
                  <td style="white-space:nowrap;">
                    <a href="<?= APP_URL ?>/pages/blog-detail.php?slug=<?= urlencode($post['slug']) ?>"
                       target="_blank" class="az-btn az-btn-outline az-btn-sm" title="Просмотр">
                      <i class="fa fa-eye"></i>
                    </a>
                    <a href="?action=edit&id=<?= (int)$post['id'] ?>" class="az-btn az-btn-outline az-btn-sm">Ред.</a>
                    <form method="post" style="display:inline;"
                          onsubmit="return confirm('Удалить статью «<?= sanitize(mb_substr($post['title_ru'], 0, 30)) ?>»?')">
                      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
                      <button type="submit" class="az-btn az-btn-danger az-btn-sm">Удалить</button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($posts)): ?>
                <tr><td colspan="6" style="text-align:center;color:#999;padding:24px;">Статей пока нет. <a href="?action=new">Создать первую</a></td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /.az-content -->
  </div><!-- /.az-main -->
</div><!-- /.az-panel -->

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
