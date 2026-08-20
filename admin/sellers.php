<?php
/**
 * Модерация продавцов (маркетплейс, Фаза 1).
 * Одобрение / блокировка заявок продавцов + установка комиссии.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['admin', 'superadmin']);

$db   = getDB();
$csrf = generateCsrfToken();

// ── POST ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF ошибка.');
        redirect(APP_URL . '/admin/sellers.php');
    }
    $act = $_POST['action'] ?? '';
    $sid = (int)($_POST['id'] ?? 0);

    if ($act === 'approve') {
        $db->prepare("UPDATE sellers SET status='approved', reject_reason=NULL, updated_at=NOW() WHERE id=?")->execute([$sid]);
        flashMessage('success', 'Продавец одобрен.');
    } elseif ($act === 'block') {
        $reason = trim($_POST['reason'] ?? '');
        $db->prepare("UPDATE sellers SET status='blocked', reject_reason=?, updated_at=NOW() WHERE id=?")
           ->execute([$reason !== '' ? mb_substr($reason, 0, 255) : null, $sid]);
        flashMessage('success', 'Продавец заблокирован.');
    } elseif ($act === 'commission') {
        $val = (float)str_replace(',', '.', $_POST['commission'] ?? '0');
        if ($val < 0) $val = 0; if ($val > 100) $val = 100;
        $db->prepare("UPDATE sellers SET commission_percent=?, updated_at=NOW() WHERE id=?")->execute([$val, $sid]);
        flashMessage('success', 'Комиссия обновлена.');
    }
    redirect(APP_URL . '/admin/sellers.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
}

// ── Список ────────────────────────────────────────────────────────────────────
$statusFilter = $_GET['status'] ?? '';
$where = [];
$params = [];
if (in_array($statusFilter, ['pending', 'approved', 'blocked'], true)) {
    $where[] = 's.status = ?';
    $params[] = $statusFilter;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sellers = $db->prepare(
    "SELECT s.*, u.email, u.username, u.is_active,
            (SELECT COUNT(*) FROM parts p WHERE p.seller_id = s.id) AS products_total,
            (SELECT COUNT(*) FROM parts p WHERE p.seller_id = s.id AND p.moderation_status='pending') AS products_pending
       FROM sellers s
       JOIN users u ON u.id = s.user_id
       $whereSQL
   ORDER BY (s.status='pending') DESC, s.created_at DESC"
);
$sellers->execute($params);
$sellers = $sellers->fetchAll();

$counts = [];
foreach ($db->query("SELECT status, COUNT(*) c FROM sellers GROUP BY status") as $r) $counts[$r['status']] = (int)$r['c'];

$pageTitle = 'Продавцы — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="az-panel">
  <?php renderRoleSidebar('sellers'); ?>

  <div class="az-main">
    <div class="az-topbar">
      <div class="az-topbar-title">Модерация продавцов</div>
      <div class="az-topbar-user">
        <?= sanitize($_SESSION['username'] ?? 'Admin') ?> &middot;
        <a href="<?= APP_URL ?>/auth/logout.php">Выйти</a>
      </div>
    </div>

    <div class="az-content">
      <?php if ($flash = getFlashMessage()): ?>
      <div class="alert alert-<?= sanitize($flash['type']) ?> mb-16"><?= sanitize($flash['message']) ?></div>
      <?php endif; ?>

      <!-- Фильтр по статусу -->
      <div class="az-card mb-16">
        <div class="az-card-body">
          <div class="d-flex align-items-center gap-8 flex-wrap">
            <a href="?" class="az-btn <?= $statusFilter===''?'az-btn-primary':'az-btn-outline' ?>">Все</a>
            <a href="?status=pending" class="az-btn <?= $statusFilter==='pending'?'az-btn-primary':'az-btn-outline' ?>">На модерации (<?= $counts['pending'] ?? 0 ?>)</a>
            <a href="?status=approved" class="az-btn <?= $statusFilter==='approved'?'az-btn-primary':'az-btn-outline' ?>">Одобрены (<?= $counts['approved'] ?? 0 ?>)</a>
            <a href="?status=blocked" class="az-btn <?= $statusFilter==='blocked'?'az-btn-primary':'az-btn-outline' ?>">Заблокированы (<?= $counts['blocked'] ?? 0 ?>)</a>
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
                  <th>Магазин</th>
                  <th>Контакты</th>
                  <th>Товары</th>
                  <th>Комиссия</th>
                  <th>Статус</th>
                  <th>Заявка</th>
                  <th>Действия</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($sellers as $s): ?>
                <tr>
                  <td><?= (int)$s['id'] ?></td>
                  <td>
                    <strong><?= sanitize($s['shop_name']) ?></strong>
                    <div style="font-size:.75rem;color:#999;">/<?= sanitize($s['slug']) ?></div>
                    <?php if (!empty($s['reject_reason'])): ?>
                    <div style="font-size:.75rem;color:#c0392b;">Причина: <?= sanitize($s['reject_reason']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td style="font-size:.8rem;color:#666;">
                    <?= sanitize($s['email']) ?><br>
                    <?= sanitize($s['phone'] ?? '—') ?>
                  </td>
                  <td style="text-align:center;">
                    <?= (int)$s['products_total'] ?>
                    <?php if ((int)$s['products_pending'] > 0): ?>
                    <span class="badge badge-warning" title="на модерации"><?= (int)$s['products_pending'] ?></span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <form method="post" action="" style="display:inline-flex;align-items:center;gap:4px;">
                      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                      <input type="hidden" name="action" value="commission">
                      <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                      <input type="number" name="commission" step="0.5" min="0" max="100"
                             value="<?= rtrim(rtrim(number_format((float)$s['commission_percent'], 2, '.', ''), '0'), '.') ?>"
                             style="width:64px;padding:3px 6px;font-size:.8rem;" onchange="this.form.submit()"> %
                    </form>
                  </td>
                  <td>
                    <?php
                      $badge = ['pending'=>'warning','approved'=>'success','blocked'=>'danger'][$s['status']] ?? 'secondary';
                      $label = ['pending'=>'На модерации','approved'=>'Одобрен','blocked'=>'Заблокирован'][$s['status']] ?? $s['status'];
                    ?>
                    <span class="badge badge-<?= $badge ?>"><?= $label ?></span>
                  </td>
                  <td style="font-size:.8rem;color:#888;"><?= date('d.m.Y', strtotime($s['created_at'])) ?></td>
                  <td>
                    <?php if ($s['status'] !== 'approved'): ?>
                    <form method="post" action="" style="display:inline;">
                      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                      <input type="hidden" name="action" value="approve">
                      <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                      <button type="submit" class="az-btn az-btn-sm az-btn-success">Одобрить</button>
                    </form>
                    <?php endif; ?>
                    <?php if ($s['status'] !== 'blocked'): ?>
                    <button type="button" class="az-btn az-btn-sm az-btn-danger"
                            onclick="var r=prompt('Причина блокировки (необязательно):','');if(r!==null){var f=this.nextElementSibling;f.reason.value=r;f.submit();}">Блок</button>
                    <form method="post" action="" style="display:none;">
                      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                      <input type="hidden" name="action" value="block">
                      <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                      <input type="hidden" name="reason" value="">
                    </form>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($sellers)): ?>
                <tr><td colspan="8" style="text-align:center;color:#999;padding:24px;">Продавцов нет</td></tr>
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
