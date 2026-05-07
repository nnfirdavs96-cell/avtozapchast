<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_layout.php';
requireRole(['admin','superadmin']);

$db = getDB();
if ($_SERVER['REQUEST_METHOD']==='POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $a = $_POST['action'] ?? '';
    if ($a==='create') $db->prepare("INSERT INTO blog_posts (slug,title,excerpt,body,is_published,author_id) VALUES (?,?,?,?,?,?)")
        ->execute([trim($_POST['slug']), trim($_POST['title']), trim($_POST['excerpt'] ?? '') ?: null, trim($_POST['body']), isset($_POST['is_published'])?1:0, (int)$_SESSION['user_id']]);
    if ($a==='update') $db->prepare("UPDATE blog_posts SET slug=?, title=?, excerpt=?, body=?, is_published=? WHERE id=?")
        ->execute([trim($_POST['slug']), trim($_POST['title']), trim($_POST['excerpt'] ?? '') ?: null, trim($_POST['body']), isset($_POST['is_published'])?1:0, (int)$_POST['id']]);
    if ($a==='delete') $db->prepare("DELETE FROM blog_posts WHERE id=?")->execute([(int)$_POST['id']]);
    redirect(APP_URL . '/admin/blog.php');
}
$editId = (int)($_GET['edit'] ?? 0);
$editing = $editId ? $db->query("SELECT * FROM blog_posts WHERE id={$editId}")->fetch() : null;
$posts = $db->query("SELECT * FROM blog_posts ORDER BY created_at DESC")->fetchAll();

$pageTitle = 'Блог';
$adminArea = true;
require_once __DIR__ . '/../includes/header.php';
?>
<div class="dash-layout">
  <?php renderAdminSidebar(); ?>
  <div class="dash-main">
    <h1 class="dash-heading">Блог</h1>

    <div class="admin-card mb-32">
      <h3 style="margin-bottom:14px"><?= $editing?'Редактировать':'Новая статья' ?></h3>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
        <input type="hidden" name="action" value="<?= $editing?'update':'create' ?>">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
        <div class="form-row">
          <input type="text" name="title" class="form-input" placeholder="Заголовок" required value="<?= sanitize($editing['title'] ?? '') ?>">
          <input type="text" name="slug"  class="form-input" placeholder="slug-url" required value="<?= sanitize($editing['slug'] ?? '') ?>">
        </div>
        <input type="text" name="excerpt" class="form-input mt-8" placeholder="Краткое описание" value="<?= sanitize($editing['excerpt'] ?? '') ?>">
        <textarea name="body" class="form-textarea mt-8" rows="10" required placeholder="Содержание..."><?= sanitize($editing['body'] ?? '') ?></textarea>
        <label class="mt-8" style="display:block"><input type="checkbox" name="is_published" value="1" <?= !$editing || (int)($editing['is_published'] ?? 1)?'checked':'' ?>> Опубликовать</label>
        <button class="btn btn-primary mt-16"><?= $editing?'Сохранить':'Опубликовать' ?></button>
        <?php if ($editing): ?><a href="?" class="btn btn-outline">Отмена</a><?php endif; ?>
      </form>
    </div>

    <div class="admin-card">
      <h3 style="margin-bottom:14px">Все статьи</h3>
      <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>Заголовок</th><th>Slug</th><th>Опубл.</th><th>Дата</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($posts as $p): ?>
          <tr>
            <td><strong><?= sanitize($p['title']) ?></strong></td>
            <td><span class="mono"><?= sanitize($p['slug']) ?></span></td>
            <td><span class="badge badge-<?= $p['is_published']?'success':'warning' ?>"><?= $p['is_published']?'Да':'Черновик' ?></span></td>
            <td><?= date('d.m.Y', strtotime($p['created_at'])) ?></td>
            <td class="actions">
              <a href="?edit=<?= (int)$p['id'] ?>" class="btn btn-outline btn-sm">✏</a>
              <a href="<?= APP_URL ?>/blog/post.php?slug=<?= sanitize($p['slug']) ?>" target="_blank" class="btn btn-outline btn-sm">→</a>
              <form method="post" onsubmit="return confirm('Удалить?')" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <button class="btn btn-outline btn-sm">🗑</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
