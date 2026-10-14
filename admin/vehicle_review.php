<?php
/**
 * Админка — проверка авто-привязок (найденных авто-скриптом) и заявки продавцов
 * на добавление машин. Доступ: админ и суперадмин.
 *   Вкладка «review» — товары с привязками needs_review=1: подтвердить/удалить.
 *   Вкладка «requests» — заявки продавцов (vehicle_requests): выполнено/отклонить.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['admin', 'superadmin']);
require_once dirname(__DIR__) . '/includes/vehicles.php';

$db = getDB();
ensureVehicleSchema();

$tab  = ($_GET['tab'] ?? 'review') === 'requests' ? 'requests' : 'review';
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 25;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['_csrf'] ?? '')) { denyAccess(); }
    $act = $_POST['act'] ?? '';
    try {
        switch ($act) {
            case 'confirm_part':   // подтвердить привязки товара
                $db->prepare("UPDATE part_vehicles SET needs_review=0 WHERE part_id=? AND needs_review=1")->execute([(int)$_POST['part_id']]);
                break;
            case 'delete_part':    // удалить авто-привязки товара
                $db->prepare("DELETE FROM part_vehicles WHERE part_id=? AND needs_review=1")->execute([(int)$_POST['part_id']]);
                break;
            case 'confirm_page':   // подтвердить все на странице
                $ids = array_filter(array_map('intval', (array)($_POST['part_ids'] ?? [])));
                if ($ids) { $in = implode(',', array_fill(0, count($ids), '?')); $db->prepare("UPDATE part_vehicles SET needs_review=0 WHERE needs_review=1 AND part_id IN ($in)")->execute($ids); }
                break;
            case 'req_done':
                $db->prepare("UPDATE vehicle_requests SET status='done' WHERE id=?")->execute([(int)$_POST['id']]);
                break;
            case 'req_reject':
                $db->prepare("UPDATE vehicle_requests SET status='rejected' WHERE id=?")->execute([(int)$_POST['id']]);
                break;
        }
    } catch (Throwable $e) { flashMessage('danger', 'Ошибка: ' . $e->getMessage()); }
    redirect(APP_URL . '/admin/vehicle_review.php?tab=' . $tab . '&page=' . $page);
}

$csrf = generateCsrfToken();
$h = static fn($s) => sanitize((string)$s);

// Данные вкладки.
$rows = []; $total = 0;
if ($tab === 'review') {
    $total = (int)$db->query("SELECT COUNT(DISTINCT part_id) FROM part_vehicles WHERE needs_review=1")->fetchColumn();
    $off = ($page - 1) * $per;
    $pstmt = $db->prepare("SELECT DISTINCT pv.part_id, p.name, p.part_number
                             FROM part_vehicles pv JOIN parts p ON p.id=pv.part_id
                            WHERE pv.needs_review=1 ORDER BY pv.part_id LIMIT $per OFFSET $off");
    $pstmt->execute();
    $rows = $pstmt->fetchAll();
    // Подписи привязок по каждому товару.
    foreach ($rows as &$r) { $r['veh'] = partVehicles($db, (int)$r['part_id']); } unset($r);
} else {
    $total = (int)$db->query("SELECT COUNT(*) FROM vehicle_requests WHERE status='new'")->fetchColumn();
    $off = ($page - 1) * $per;
    $rstmt = $db->prepare("SELECT vr.*, s.shop_name FROM vehicle_requests vr
                           LEFT JOIN sellers s ON s.id=vr.seller_id
                           WHERE vr.status='new' ORDER BY vr.created_at DESC LIMIT $per OFFSET $off");
    $rstmt->execute();
    $rows = $rstmt->fetchAll();
}
$pages = max(1, (int)ceil($total / $per));

$pageTitle = 'Проверка авто-привязок — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/admin-header.php';
?>
<div class="az-panel">
  <?php renderRoleSidebar('vehicles'); ?>
  <div class="az-main">
    <div class="az-topbar"><h1>Проверка авто-привязок</h1></div>
    <div class="az-content">
      <?php if ($flash = getFlashMessage()): ?><div class="az-alert az-alert-<?= $h($flash['type']) ?>"><?= $h($flash['message']) ?></div><?php endif; ?>

      <div style="margin-bottom:14px;display:flex;gap:8px;">
        <a class="az-btn <?= $tab==='review'?'az-btn-primary':'az-btn-outline' ?> az-btn-sm" href="?tab=review">Авто-привязки на проверке</a>
        <a class="az-btn <?= $tab==='requests'?'az-btn-primary':'az-btn-outline' ?> az-btn-sm" href="?tab=requests">Заявки от продавцов</a>
        <a class="az-btn az-btn-outline az-btn-sm" href="<?= APP_URL ?>/admin/vehicles.php" style="margin-left:auto;">← К справочнику</a>
      </div>

      <p style="color:#777;font-size:.88rem;margin-bottom:12px;">
        <?php if ($tab==='review'): ?>Это привязки, найденные автоматически по названию товара. Подтвердите верные или удалите ошибочные.
        <?php else: ?>Заявки продавцов на добавление машин, которых нет в справочнике. Добавьте авто в справочник и отметьте «выполнено».<?php endif; ?>
      </p>

      <?php if ($tab === 'review'): ?>
      <?php if (!$rows): ?>
      <div class="az-card"><div class="az-card-body" style="text-align:center;color:#999;">Нет привязок на проверке — всё подтверждено. 👍</div></div>
      <?php else: ?>
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="act" value="confirm_page">
        <div class="az-card">
          <div class="az-card-header"><h4 class="az-card-title">Товары с авто-привязками (<?= $total ?>)</h4>
            <button class="az-btn az-btn-primary az-btn-sm" type="submit"><i class="fa fa-check"></i> Подтвердить все на странице</button>
          </div>
          <div class="az-card-body p-0"><div class="table-responsive"><table class="az-table">
            <thead><tr><th>#</th><th>Товар</th><th>Привязки</th><th style="width:220px;"></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $pid=(int)$r['part_id'];
              $names=[]; foreach ($r['veh'] as $v) { $lab=$v['make'].' '.$v['model']; $ml=vehModLabel($v); if($ml) $lab.=' ('.$ml.')'; $names[]=$lab; }
            ?>
            <tr>
              <td><input type="checkbox" name="part_ids[]" value="<?= $pid ?>" checked> <?= $pid ?></td>
              <td><a href="<?= APP_URL ?>/admin/products.php?id=<?= $pid ?>" target="_blank"><?= $h(truncate($r['name'],60)) ?></a><br><small style="color:#999;"><?= $h($r['part_number']) ?></small></td>
              <td style="font-size:.85rem;"><?= $h(implode(', ', $names)) ?></td>
              <td>
                <div style="display:flex;gap:6px;">
                  <button class="az-btn az-btn-outline az-btn-sm" type="submit" form="cf<?= $pid ?>" style="color:#1f8a3b;border-color:#bfe8cd;"><i class="fa fa-check"></i> Подтвердить</button>
                  <button class="az-btn az-btn-outline az-btn-sm" type="submit" form="df<?= $pid ?>" style="color:#c0392b;border-color:#eec4c4;"><i class="fa fa-trash-o"></i> Удалить</button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table></div></div>
        </div>
      </form>
      <?php foreach ($rows as $r): $pid=(int)$r['part_id']; ?>
      <form method="post" id="cf<?= $pid ?>" style="display:none;"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="act" value="confirm_part"><input type="hidden" name="part_id" value="<?= $pid ?>"></form>
      <form method="post" id="df<?= $pid ?>" style="display:none;" onsubmit="return confirm('Удалить авто-привязки этого товара?');"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="act" value="delete_part"><input type="hidden" name="part_id" value="<?= $pid ?>"></form>
      <?php endforeach; ?>
      <?php endif; ?>

      <?php else: /* requests */ ?>
      <?php if (!$rows): ?>
      <div class="az-card"><div class="az-card-body" style="text-align:center;color:#999;">Новых заявок нет.</div></div>
      <?php else: ?>
      <div class="az-card"><div class="az-card-body p-0"><div class="table-responsive"><table class="az-table">
        <thead><tr><th>Дата</th><th>Продавец</th><th>Марка</th><th>Модель</th><th>Годы</th><th>Двигатель</th><th style="width:220px;"></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td style="font-size:.8rem;color:#888;"><?= date('d.m.Y', strtotime($r['created_at'])) ?></td>
          <td><?= $h($r['shop_name'] ?: '—') ?></td>
          <td><b><?= $h($r['make']) ?></b></td>
          <td><?= $h($r['model']) ?></td>
          <td><?= $h($r['years']) ?></td>
          <td><?= $h($r['engine']) ?></td>
          <td><div style="display:flex;gap:6px;">
            <form method="post" style="margin:0;"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="act" value="req_done"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="az-btn az-btn-outline az-btn-sm" type="submit" style="color:#1f8a3b;border-color:#bfe8cd;">Выполнено</button></form>
            <form method="post" style="margin:0;"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="act" value="req_reject"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="az-btn az-btn-outline az-btn-sm" type="submit" style="color:#c0392b;border-color:#eec4c4;">Отклонить</button></form>
          </div></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div></div></div>
      <?php endif; ?>
      <?php endif; ?>

      <?php if ($pages > 1): ?>
      <div style="margin-top:14px;display:flex;gap:6px;flex-wrap:wrap;">
        <?php for ($p=1;$p<=$pages;$p++): ?>
        <a class="az-btn <?= $p===$page?'az-btn-primary':'az-btn-outline' ?> az-btn-sm" href="?tab=<?= $tab ?>&page=<?= $p ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>
<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
