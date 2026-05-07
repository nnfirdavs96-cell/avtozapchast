<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_layout.php';
requireRole(['manager','admin','superadmin']);

$db = getDB();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'approve') $db->prepare("UPDATE reviews SET status='approved' WHERE id=?")->execute([$id]);
    if ($action === 'reject')  $db->prepare("UPDATE reviews SET status='rejected' WHERE id=?")->execute([$id]);
    if ($action === 'delete')  $db->prepare("DELETE FROM reviews WHERE id=?")->execute([$id]);
    redirect(APP_URL . '/manager/reviews.php');
}

$status = $_GET['status'] ?? 'pending';
$stmt = $db->prepare(
    "SELECT r.*, u.username, p.name AS part_name, p.part_number
     FROM reviews r
     JOIN users u ON u.id=r.user_id
     JOIN parts p ON p.id=r.part_id
     WHERE r.status=?
     ORDER BY r.created_at DESC"
);
$stmt->execute([$status]);
$reviews = $stmt->fetchAll();

$pageTitle = 'Модерация отзывов';
$adminArea = true;
require_once __DIR__ . '/../includes/header.php';
?>
<div class="dash-layout">
  <?php renderAdminSidebar(); ?>
  <div class="dash-main">
    <h1 class="dash-heading">Отзывы <span class="dash-heading-badge">moderation</span></h1>

    <div class="mb-24">
      <a href="?status=pending"  class="btn btn-<?= $status==='pending'?'primary':'outline' ?> btn-sm">⏳ На модерации</a>
      <a href="?status=approved" class="btn btn-<?= $status==='approved'?'primary':'outline' ?> btn-sm">✓ Одобренные</a>
      <a href="?status=rejected" class="btn btn-<?= $status==='rejected'?'primary':'outline' ?> btn-sm">✕ Отклонённые</a>
    </div>

    <?php if (empty($reviews)): ?>
      <p style="color:#888">Нет отзывов в выбранной категории.</p>
    <?php else: ?>
      <?php foreach ($reviews as $r): ?>
        <div class="admin-card mb-16">
          <div class="flex-between mb-8">
            <div>
              <strong><?= sanitize($r['username']) ?></strong> ·
              <?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5-(int)$r['rating']) ?> ·
              <span style="color:#888;font-size:0.85rem"><?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></span>
            </div>
            <div style="font-size:0.85rem;color:#888">→ <?= sanitize($r['part_name']) ?> (<?= sanitize($r['part_number']) ?>)</div>
          </div>
          <?php if ($r['title']): ?><h4 style="margin-bottom:6px"><?= sanitize($r['title']) ?></h4><?php endif; ?>
          <p style="color:#ccc"><?= nl2br(sanitize($r['body'])) ?></p>
          <div class="mt-16" style="display:flex;gap:8px">
            <?php foreach (['approve'=>'✓ Одобрить','reject'=>'✕ Отклонить','delete'=>'🗑 Удалить'] as $a => $label): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                <input type="hidden" name="action" value="<?= $a ?>">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-<?= $a==='approve'?'primary':'outline' ?> btn-sm"><?= $label ?></button>
              </form>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
