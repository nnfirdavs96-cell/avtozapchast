<?php
/**
 * Возвраты и споры — админка (маркетплейс, Фаза 4).
 *
 * Вторая инстанция. Продавец рассматривает заявки по своим заказам сам; если он
 * отказал, а покупатель не согласен, решение принимает администратор. Отдельной
 * сущности «спор» намеренно нет — это то же обращение, просто рассмотренное
 * второй инстанцией, и заводить ради этого параллельную таблицу значило бы
 * раздвоить историю одного и того же события.
 *
 * Админ также рассматривает возвраты по НАШЕМУ каталогу (`seller_id IS NULL`) —
 * там продавца, который мог бы решить, попросту нет.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/returns.php';
requireRole(['admin', 'superadmin']);

$db    = getDB();
$csrf  = generateCsrfToken();
$ready = returnsReady($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF ошибка.');
    } elseif (!$ready) {
        flashMessage('danger', 'Миграция возвратов не применена: php sql/migrate_returns.php');
    } else {
        $rid = (int)($_POST['return_id'] ?? 0);

        if (($_POST['do'] ?? '') === 'reopen') {
            // Пересмотр: возвращаем заявку в работу, чтобы решить заново. Деньги
            // при этом не двигаем — если возврат был одобрен, сторно уже в журнале
            // и отменять его надо осознанно, отдельной корректировкой.
            $st = $db->prepare(
                "UPDATE order_returns SET status='requested', resolution=NULL,
                        resolved_by=NULL, resolved_at=NULL
                  WHERE id = ? AND status IN ('rejected','cancelled')"
            );
            $st->execute([$rid]);
            flashMessage($st->rowCount() ? 'success' : 'danger',
                $st->rowCount()
                    ? 'Заявка возвращена на рассмотрение.'
                    : 'Пересмотреть можно только отклонённую или отозванную заявку.');
        } else {
            // Админ решает без ограничения по продавцу — последнее слово в споре.
            [$ok, $msg] = returnResolve(
                $db, $rid,
                (string)($_POST['decision'] ?? ''),
                (string)($_POST['resolution'] ?? ''),
                (int)($_SESSION['user_id'] ?? 0),
                null
            );
            flashMessage($ok ? 'success' : 'danger', $msg);
        }
    }
    redirect(APP_URL . '/admin/returns.php' . (isset($_GET['status']) ? '?status=' . urlencode((string)$_GET['status']) : ''));
}

$filter = (string)($_GET['status'] ?? '');
$rows   = $ready ? returnList($db, array_filter([
    'status' => in_array($filter, ['requested','approved','rejected','cancelled'], true) ? $filter : null,
])) : [];

$counts = [];
if ($ready) {
    foreach ($db->query("SELECT status, COUNT(*) n FROM order_returns GROUP BY status") as $r) {
        $counts[$r['status']] = (int)$r['n'];
    }
}

$pageTitle = 'Возвраты — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="az-panel">
  <?php renderRoleSidebar('returns'); ?>

  <div class="az-main">
    <div class="az-topbar">
      <div class="az-topbar-title">Возвраты и споры</div>
      <div class="az-topbar-user">
        <?= sanitize($_SESSION['username'] ?? 'Admin') ?> &middot;
        <a href="<?= APP_URL ?>/auth/logout.php">Выйти</a>
      </div>
    </div>

    <div class="az-content">
      <?php if ($flash = getFlashMessage()): ?>
      <div class="alert alert-<?= sanitize($flash['type']) ?> mb-16"><?= sanitize($flash['message']) ?></div>
      <?php endif; ?>

      <?php if (!$ready): ?>
      <div class="az-card"><div class="az-card-body">
        <p><b>Раздел не активирован.</b> Примените миграцию:</p>
        <pre style="background:#f6f6f8;padding:12px;border-radius:8px;overflow:auto;">php sql/migrate_returns.php</pre>
      </div></div>

      <?php else: ?>
      <div class="az-card mb-16"><div class="az-card-body">
        <div class="d-flex align-items-center gap-8 flex-wrap">
          <a href="?" class="az-btn <?= $filter===''?'az-btn-primary':'az-btn-outline' ?>">Все</a>
          <?php foreach (['requested','approved','rejected','cancelled'] as $st2): ?>
          <a href="?status=<?= $st2 ?>" class="az-btn <?= $filter===$st2?'az-btn-primary':'az-btn-outline' ?>">
            <?= sanitize(returnStatusLabel($st2)) ?> (<?= $counts[$st2] ?? 0 ?>)
          </a>
          <?php endforeach; ?>
        </div>
      </div></div>

      <div class="az-card"><div class="az-card-body p-0">
        <div class="table-responsive">
          <table class="az-table">
            <thead>
              <tr>
                <th>#</th><th>Заказ</th><th>Продавец</th><th>Покупатель</th>
                <th>Причина</th><th style="text-align:right;">Сумма</th>
                <th>Статус</th><th>Решение</th>
              </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="8" class="text-center text-muted" style="padding:24px;">Заявок нет.</td></tr>
            <?php else: foreach ($rows as $r):
              $share = returnPayoutShare((float)$r['amount'], (float)$r['subtotal'], (float)$r['payout_amount']);
            ?>
              <tr>
                <td><?= (int)$r['id'] ?></td>
                <td>
                  #<?= (int)$r['order_id'] ?>
                  <?php if (!empty($r['part_name'])): ?>
                  <div class="text-muted" style="font-size:.78rem;"><?= sanitize(mb_substr($r['part_name'], 0, 34)) ?></div>
                  <?php else: ?>
                  <div class="text-muted" style="font-size:.78rem;">весь подзаказ</div>
                  <?php endif; ?>
                </td>
                <td><?= $r['seller_id'] ? sanitize($r['shop_name']) : '<span class="text-muted">наш каталог</span>' ?></td>
                <td><?= sanitize($r['username'] ?? '—') ?></td>
                <td style="font-size:.82rem;">
                  <?= sanitize(returnReasons()[$r['reason']] ?? $r['reason']) ?>
                  <?php if (!empty($r['comment'])): ?>
                  <div class="text-muted" style="font-size:.76rem;">«<?= sanitize(mb_substr($r['comment'], 0, 60)) ?>»</div>
                  <?php endif; ?>
                </td>
                <td style="text-align:right;white-space:nowrap;">
                  <b><?= formatPrice((float)$r['amount']) ?></b>
                  <?php if ($r['seller_id']): ?>
                  <div class="text-muted" style="font-size:.74rem;">
                    <?= $r['status'] === 'approved'
                        ? 'снято ' . formatPrice((float)$r['payout_reversed'])
                        : 'снимется ' . formatPrice($share) ?>
                  </div>
                  <?php endif; ?>
                </td>
                <td><span class="sl-badge ret_badge--<?= sanitize($r['status']) ?>"><?= sanitize(returnStatusLabel($r['status'])) ?></span></td>
                <td>
                  <?php if ($r['status'] === 'requested'): ?>
                  <form method="post" style="display:flex;gap:5px;flex-wrap:wrap;align-items:center;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                    <input type="hidden" name="return_id" value="<?= (int)$r['id'] ?>">
                    <input type="text" name="resolution" maxlength="500" placeholder="комментарий"
                           style="width:130px;padding:5px 8px;border:1px solid #d9d9e3;border-radius:6px;font-size:.78rem;">
                    <button type="submit" name="decision" value="approved" class="az-btn az-btn-sm az-btn-success">Одобрить</button>
                    <button type="submit" name="decision" value="rejected" class="az-btn az-btn-sm az-btn-danger">Отказать</button>
                  </form>
                  <?php else: ?>
                    <?php if (!empty($r['resolution'])): ?>
                    <div style="font-size:.78rem;color:#555;"><?= sanitize($r['resolution']) ?></div>
                    <?php endif; ?>
                    <?php if (in_array($r['status'], ['rejected','cancelled'], true)): ?>
                    <form method="post" style="margin-top:4px;">
                      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                      <input type="hidden" name="do" value="reopen">
                      <input type="hidden" name="return_id" value="<?= (int)$r['id'] ?>">
                      <button type="submit" class="az-btn az-btn-sm az-btn-outline"
                              title="Покупатель не согласен с отказом — вернуть заявку на рассмотрение">
                        Пересмотреть
                      </button>
                    </form>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
