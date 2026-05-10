<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['admin','superadmin']);
$pageTitle = t('blog_mgmt') . ' — ' . getSetting('site_name');

$db = getDB();
$message = '';
$error   = '';
$editPost = null;
$action   = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    if ($action === 'save') {
        $id    = (int)($_POST['id'] ?? 0);
        $slug  = trim($_POST['slug'] ?? '');
        $published = isset($_POST['is_published']) ? 1 : 0;
        $fields = ['title_ru','title_tg','title_en','excerpt_ru','excerpt_tg','excerpt_en','body_ru','body_tg','body_en'];
        $vals = [];
        foreach ($fields as $f) $vals[$f] = trim($_POST[$f] ?? '');
        if (!$slug || !$vals['title_ru']) { $error = 'Slug и заголовок (рус) обязательны.'; }
        else {
            if ($id) {
                $set = implode(',', array_map(fn($f)=>"`$f`=?", array_keys($vals)));
                $stmt = $db->prepare("UPDATE blog_posts SET slug=?,$set,is_published=? WHERE id=?");
                $stmt->execute(array_merge([$slug], array_values($vals), [$published, $id]));
                $message = 'Пост обновлён.';
            } else {
                $cols = 'slug,' . implode(',', array_keys($vals)) . ',is_published,author_id';
                $qs   = rtrim(str_repeat('?,',count($vals)+3),',');
                $stmt = $db->prepare("INSERT INTO blog_posts ($cols) VALUES ($qs)");
                $stmt->execute(array_merge([$slug], array_values($vals), [$published, $_SESSION['user_id']]));
                $message = 'Пост добавлен.';
            }
        }
    }
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) { $db->prepare("DELETE FROM blog_posts WHERE id=?")->execute([$id]); $message = 'Пост удалён.'; }
    }
}

if (isset($_GET['edit'])) {
    $editPost = $db->prepare("SELECT * FROM blog_posts WHERE id=?")->execute([(int)$_GET['edit']]) ? null : null;
    $s = $db->prepare("SELECT * FROM blog_posts WHERE id=?"); $s->execute([(int)$_GET['edit']]); $editPost = $s->fetch();
}

$posts = $db->query("SELECT id,slug,title_ru,is_published,created_at FROM blog_posts ORDER BY created_at DESC")->fetchAll();

$sidebarLinks = [
    ['url'=>'/superadmin/index.php','label'=>t('dashboard')],
    ['url'=>'/admin/users.php','label'=>t('users')],
    ['url'=>'/admin/orders.php','label'=>t('orders')],
    ['url'=>'/superadmin/settings.php','label'=>t('settings')],
    ['url'=>'/superadmin/currencies.php','label'=>t('currencies_mgmt')],
    ['url'=>'/superadmin/languages.php','label'=>t('languages_mgmt')],
    ['url'=>'/superadmin/warehouse.php','label'=>t('warehouse_api')],
    ['url'=>'/superadmin/blog.php','label'=>t('blog_mgmt')],
];

require_once dirname(__DIR__) . '/includes/header.php';
?>
<div class="az-panel">
  <aside class="az-sidebar">
    <div class="az-sidebar-logo">AUTO<span>PARTS</span></div>
    <nav><ul>
      <?php foreach ($sidebarLinks as $l): ?>
      <li><a href="<?php echo APP_URL.$l['url']; ?>" class="<?php echo strpos($_SERVER['REQUEST_URI'],$l['url'])!==false?'active':''; ?>"><?php echo $l['label']; ?></a></li>
      <?php endforeach; ?>
    </ul></nav>
  </aside>
  <main class="az-main">
    <div class="az-topbar"><h1><?php echo t('blog_mgmt'); ?></h1></div>
    <div class="az-content">
      <?php if ($message): ?><div class="az-alert az-alert-success"><?php echo sanitize($message); ?></div><?php endif; ?>
      <?php if ($error): ?><div class="az-alert az-alert-danger"><?php echo sanitize($error); ?></div><?php endif; ?>

      <!-- Edit/Add form -->
      <div class="az-card">
        <h3><?php echo $editPost ? 'Редактировать пост' : 'Добавить пост'; ?></h3>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
          <input type="hidden" name="action" value="save">
          <?php if ($editPost): ?><input type="hidden" name="id" value="<?php echo (int)$editPost['id']; ?>"><?php endif; ?>
          <div class="row">
            <div class="col-md-6">
              <div class="az-form-group"><label>Slug (URL)</label><input type="text" name="slug" value="<?php echo sanitize($editPost['slug']??''); ?>" required></div>
              <div class="az-form-group"><label>Заголовок (RU) *</label><input type="text" name="title_ru" value="<?php echo sanitize($editPost['title_ru']??''); ?>" required></div>
              <div class="az-form-group"><label>Заголовок (TG)</label><input type="text" name="title_tg" value="<?php echo sanitize($editPost['title_tg']??''); ?>"></div>
              <div class="az-form-group"><label>Заголовок (EN)</label><input type="text" name="title_en" value="<?php echo sanitize($editPost['title_en']??''); ?>"></div>
              <div style="margin-bottom:16px"><label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="is_published" value="1" <?php echo (!$editPost||$editPost['is_published'])?'checked':''; ?>> Опубликовано</label></div>
            </div>
            <div class="col-md-6">
              <div class="az-form-group"><label>Краткое описание (RU)</label><textarea name="excerpt_ru" rows="3"><?php echo sanitize($editPost['excerpt_ru']??''); ?></textarea></div>
              <div class="az-form-group"><label>Краткое описание (TG)</label><textarea name="excerpt_tg" rows="3"><?php echo sanitize($editPost['excerpt_tg']??''); ?></textarea></div>
              <div class="az-form-group"><label>Краткое описание (EN)</label><textarea name="excerpt_en" rows="3"><?php echo sanitize($editPost['excerpt_en']??''); ?></textarea></div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-4"><div class="az-form-group"><label>Текст (RU)</label><textarea name="body_ru" rows="8"><?php echo sanitize($editPost['body_ru']??''); ?></textarea></div></div>
            <div class="col-md-4"><div class="az-form-group"><label>Текст (TG)</label><textarea name="body_tg" rows="8"><?php echo sanitize($editPost['body_tg']??''); ?></textarea></div></div>
            <div class="col-md-4"><div class="az-form-group"><label>Текст (EN)</label><textarea name="body_en" rows="8"><?php echo sanitize($editPost['body_en']??''); ?></textarea></div></div>
          </div>
          <button type="submit" class="az-btn az-btn-primary"><?php echo t('save'); ?></button>
          <?php if ($editPost): ?><a href="<?php echo APP_URL; ?>/superadmin/blog.php" class="az-btn az-btn-secondary" style="margin-left:8px"><?php echo t('cancel'); ?></a><?php endif; ?>
        </form>
      </div>

      <!-- Posts list -->
      <div class="az-card">
        <h3>Все посты (<?php echo count($posts); ?>)</h3>
        <table class="az-table">
          <thead><tr><th>ID</th><th>Slug</th><th>Заголовок</th><th>Статус</th><th>Дата</th><th><?php echo t('actions'); ?></th></tr></thead>
          <tbody>
            <?php foreach ($posts as $p): ?>
            <tr>
              <td><?php echo (int)$p['id']; ?></td>
              <td style="font-size:0.8rem;color:#888"><?php echo sanitize($p['slug']); ?></td>
              <td><?php echo sanitize(truncate($p['title_ru'],60)); ?></td>
              <td><span class="badge <?php echo $p['is_published']?'badge-success':'badge-warning'; ?>"><?php echo $p['is_published']?'Опубл.':'Черновик'; ?></span></td>
              <td style="font-size:0.8rem;color:#aaa"><?php echo date('d.m.Y',strtotime($p['created_at'])); ?></td>
              <td>
                <a href="<?php echo APP_URL; ?>/superadmin/blog.php?edit=<?php echo (int)$p['id']; ?>" class="az-btn az-btn-secondary az-btn-sm"><?php echo t('edit'); ?></a>
                <a href="<?php echo APP_URL; ?>/pages/blog-detail.php?slug=<?php echo urlencode($p['slug']); ?>" class="az-btn az-btn-primary az-btn-sm" target="_blank">Просмотр</a>
                <form method="POST" style="display:inline" onsubmit="return confirm(<?php echo json_encode(t('confirm_delete')); ?>)">
                  <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                  <button type="submit" class="az-btn az-btn-danger az-btn-sm"><?php echo t('delete'); ?></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
