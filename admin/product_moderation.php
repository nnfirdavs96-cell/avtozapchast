<?php
/**
 * Модерация товаров продавцов (маркетплейс, Фаза 1, шаг 4).
 * Одобрение / отклонение (с причиной) листингов продавцов.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['admin', 'superadmin', 'manager']);
// Делегируется правом «Модерация товаров продавцов».
requirePermission('moderation');

$db   = getDB();
$csrf = generateCsrfToken();

// ── POST ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF ошибка.');
        redirect(APP_URL . '/admin/product_moderation.php');
    }
    $act = $_POST['action'] ?? '';
    $pid = (int)($_POST['id'] ?? 0);

    if ($act === 'approve') {
        $db->prepare("UPDATE parts SET moderation_status='active', reject_reason=NULL, is_active=1, updated_at=NOW()
                      WHERE id=? AND seller_id IS NOT NULL")->execute([$pid]);
        flashMessage('success', 'Товар одобрен и опубликован.');
    } elseif ($act === 'reject') {
        $reason = trim($_POST['reason'] ?? '');
        $db->prepare("UPDATE parts SET moderation_status='rejected', reject_reason=?, updated_at=NOW()
                      WHERE id=? AND seller_id IS NOT NULL")
           ->execute([$reason !== '' ? mb_substr($reason, 0, 255) : 'Без указания причины', $pid]);
        flashMessage('success', 'Товар отклонён.');
    }
    redirect(APP_URL . '/admin/product_moderation.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
}

// ── Список ────────────────────────────────────────────────────────────────────
$statusFilter = $_GET['status'] ?? 'pending';
$where = ['p.seller_id IS NOT NULL'];
$params = [];
if (in_array($statusFilter, ['pending', 'active', 'rejected'], true)) {
    $where[] = 'p.moderation_status = ?';
    $params[] = $statusFilter;
}
$whereSQL = 'WHERE ' . implode(' AND ', $where);

$rows = $db->prepare(
    "SELECT p.*, b.name AS brand_name, c.name AS category_name, s.shop_name, s.id AS sid
       FROM parts p
       LEFT JOIN brands b ON b.id = p.brand_id
       LEFT JOIN categories c ON c.id = p.category_id
       LEFT JOIN sellers s ON s.id = p.seller_id
       $whereSQL
   ORDER BY p.updated_at DESC LIMIT 200"
);
$rows->execute($params);
$rows = $rows->fetchAll();

$counts = ['pending' => 0, 'active' => 0, 'rejected' => 0];
foreach ($db->query("SELECT moderation_status, COUNT(*) c FROM parts WHERE seller_id IS NOT NULL GROUP BY moderation_status") as $r) {
    $counts[$r['moderation_status']] = (int)$r['c'];
}

$pageTitle = 'Модерация товаров — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="az-panel">
  <?php renderRoleSidebar('moderation'); ?>

  <div class="az-main">
    <div class="az-topbar">
      <div class="az-topbar-title">Модерация товаров</div>
      <div class="az-topbar-user">
        <?= sanitize($_SESSION['username'] ?? 'Admin') ?> &middot;
        <a href="<?= APP_URL ?>/auth/logout.php">Выйти</a>
      </div>
    </div>

    <div class="az-content">
      <?php if ($flash = getFlashMessage()): ?>
      <div class="alert alert-<?= sanitize($flash['type']) ?> mb-16"><?= sanitize($flash['message']) ?></div>
      <?php endif; ?>

      <div class="az-card mb-16">
        <div class="az-card-body">
          <div class="d-flex align-items-center gap-8 flex-wrap">
            <a href="?status=pending"  class="az-btn <?= $statusFilter==='pending'?'az-btn-primary':'az-btn-outline' ?>">На проверке (<?= $counts['pending'] ?>)</a>
            <a href="?status=active"   class="az-btn <?= $statusFilter==='active'?'az-btn-primary':'az-btn-outline' ?>">Опубликованы (<?= $counts['active'] ?>)</a>
            <a href="?status=rejected" class="az-btn <?= $statusFilter==='rejected'?'az-btn-primary':'az-btn-outline' ?>">Отклонены (<?= $counts['rejected'] ?>)</a>
          </div>
        </div>
      </div>

      <div class="az-card">
        <div class="az-card-body p-0">
          <div class="table-responsive">
            <table class="az-table">
              <thead>
                <tr><th>Фото</th><th>Товар</th><th>Продавец</th><th>Артикул</th><th>Цена</th><th>Статус</th><th>Действия</th></tr>
              </thead>
              <tbody>
                <?php foreach ($rows as $p):
                  $imgs = json_decode($p['images'] ?? '[]', true) ?: [];
                  $img  = $imgs[0] ?? (APP_URL . '/assets/img/product/placeholder.jpg');
                  $badge = ['pending'=>'warning','active'=>'success','rejected'=>'danger'][$p['moderation_status']] ?? 'secondary';
                  $label = ['pending'=>'На проверке','active'=>'Опубликован','rejected'=>'Отклонён'][$p['moderation_status']] ?? $p['moderation_status'];
                ?>
                <tr>
                  <td><img src="<?= sanitize($img) ?>" alt="" style="width:52px;height:52px;object-fit:contain;background:#f6f6f9;border-radius:8px;"></td>
                  <td>
                    <strong><?= sanitize($p['name']) ?></strong>
                    <div style="font-size:.76rem;color:#999;"><?= sanitize($p['brand_name'] ?? '') ?> · <?= sanitize($p['category_name'] ?? '') ?></div>
                    <?php if (!empty($p['description'])): ?>
                    <div style="font-size:.76rem;color:#aaa;max-width:340px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= sanitize($p['description']) ?></div>
                    <?php endif; ?>
                    <?php if ($p['moderation_status']==='rejected' && !empty($p['reject_reason'])): ?>
                    <div style="font-size:.76rem;color:#c0392b;">Причина: <?= sanitize($p['reject_reason']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td style="font-size:.82rem;"><?= sanitize($p['shop_name'] ?? '—') ?></td>
                  <td><code style="font-size:.8rem;"><?= sanitize($p['part_number']) ?></code></td>
                  <td><?= formatPrice((float)$p['price']) ?><div style="font-size:.72rem;color:#999;"><?= (int)$p['stock'] ?> шт.</div></td>
                  <td><span class="badge badge-<?= $badge ?>"><?= $label ?></span></td>
                  <td style="white-space:nowrap;">
                    <?php if ($p['moderation_status'] !== 'active'): ?>
                    <form method="post" action="" style="display:inline;">
                      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                      <input type="hidden" name="action" value="approve">
                      <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                      <button type="submit" class="az-btn az-btn-sm az-btn-success">Одобрить</button>
                    </form>
                    <?php endif; ?>
                    <?php if ($p['moderation_status'] !== 'rejected'): ?>
                    <button type="button" class="az-btn az-btn-sm az-btn-danger"
                            onclick="var r=prompt('Причина отклонения:','');if(r!==null){var f=this.nextElementSibling;f.reason.value=r;f.submit();}">Отклонить</button>
                    <form method="post" action="" style="display:none;">
                      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                      <input type="hidden" name="action" value="reject">
                      <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                      <input type="hidden" name="reason" value="">
                    </form>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($rows)): ?>
                <tr><td colspan="7" style="text-align:center;color:#999;padding:24px;">Нет товаров в этом статусе</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div><!-- /.az-content -->
  </div><!-- /.az-main -->
</div><!-- /.az-panel -->

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
