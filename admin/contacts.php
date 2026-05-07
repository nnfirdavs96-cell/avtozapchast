<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_layout.php';
requireRole(['admin','superadmin']);

$db = getDB();
if ($_SERVER['REQUEST_METHOD']==='POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $id = (int)$_POST['id'];
    if (($_POST['action'] ?? '')==='read')   $db->prepare("UPDATE contact_messages SET is_read=1 WHERE id=?")->execute([$id]);
    if (($_POST['action'] ?? '')==='delete') $db->prepare("DELETE FROM contact_messages WHERE id=?")->execute([$id]);
    redirect(APP_URL . '/admin/contacts.php');
}
$msgs = $db->query("SELECT * FROM contact_messages ORDER BY is_read, created_at DESC")->fetchAll();

$pageTitle = 'Сообщения';
$adminArea = true;
require_once __DIR__ . '/../includes/header.php';
?>
<div class="dash-layout">
  <?php renderAdminSidebar(); ?>
  <div class="dash-main">
    <h1 class="dash-heading">Обращения</h1>
    <?php if (empty($msgs)): ?>
      <p style="color:#888">Нет новых сообщений.</p>
    <?php else: foreach ($msgs as $m): ?>
      <div class="admin-card mb-16" style="border-left:3px solid <?= $m['is_read']?'#444':'#C70909' ?>">
        <div class="flex-between mb-8">
          <div>
            <strong><?= sanitize($m['name']) ?></strong> <small style="color:#888">· <?= sanitize($m['email']) ?> · <?= sanitize($m['phone'] ?? '—') ?></small>
            <?php if (!$m['is_read']): ?><span class="badge badge-primary">Новое</span><?php endif; ?>
          </div>
          <div style="color:#888;font-size:0.85rem"><?= date('d.m.Y H:i', strtotime($m['created_at'])) ?></div>
        </div>
        <?php if ($m['subject']): ?><h4 style="margin-bottom:6px"><?= sanitize($m['subject']) ?></h4><?php endif; ?>
        <p style="color:#ccc"><?= nl2br(sanitize($m['message'])) ?></p>
        <div class="mt-16" style="display:flex;gap:8px">
          <?php if (!$m['is_read']): ?>
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
            <input type="hidden" name="action" value="read">
            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
            <button class="btn btn-outline btn-sm">✓ Прочитано</button>
          </form>
          <?php endif; ?>
          <form method="post" onsubmit="return confirm('Удалить?')" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
            <button class="btn btn-outline btn-sm">🗑 Удалить</button>
          </form>
          <a href="mailto:<?= sanitize($m['email']) ?>" class="btn btn-primary btn-sm">↳ Ответить</a>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
