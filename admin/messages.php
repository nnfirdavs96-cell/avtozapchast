<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/messaging.php';
requireRole(['superadmin', 'admin', 'manager']);

$staff   = getCurrentUser();
$staffId = (int)$staff['id'];
$db      = getDB();

// Открытая ветка: ?user=ID (&order=ID). Заказ-ветка, если order задан и валиден.
$openUserId  = isset($_GET['user'])  && ctype_digit((string)$_GET['user'])  ? (int)$_GET['user']  : 0;
$openOrderId = isset($_GET['order']) && ctype_digit((string)$_GET['order']) ? (int)$_GET['order'] : null;

// Проверим, что покупатель существует (иначе ветку не открываем).
$openCustomer = null;
if ($openUserId > 0) {
    $st = $db->prepare("SELECT id, first_name, last_name, phone_e164, email, username FROM users WHERE id = ? LIMIT 1");
    $st->execute([$openUserId]);
    $openCustomer = $st->fetch() ?: null;
    if (!$openCustomer) { $openUserId = 0; $openOrderId = null; }
}

// ── Ответ сотрудника ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reply') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF ошибка.');
        redirect(APP_URL . '/admin/messages.php');
    }
    $toUser  = (int)($_POST['user_id'] ?? 0);
    $toOrder = ($_POST['order_id'] ?? '') !== '' ? (int)$_POST['order_id'] : null;
    $body    = trim($_POST['body'] ?? '');
    if ($toUser > 0 && $body !== '') {
        postMessage($toUser, $toOrder, 'staff', $body, $staffId);
    }
    redirect(APP_URL . '/admin/messages.php?user=' . $toUser . ($toOrder ? '&order=' . $toOrder : ''));
}

// Открытую ветку помечаем прочитанной сотрудником.
if ($openUserId > 0) markThreadRead($openUserId, $openOrderId, 'staff');

$threads  = getStaffThreads();
$messages = $openUserId > 0 ? getThreadMessages($openUserId, $openOrderId) : [];

$pageTitle = 'Сообщения — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/admin-header.php';
?>
<div class="az-panel">
  <?php echo renderRoleSidebar('messages'); ?>
  <div class="az-main">
    <div class="az-topbar">
      <div class="az-topbar-title"><i class="fa fa-comments"></i> Сообщения покупателей</div>
      <div class="az-topbar-user"><?= sanitize($staff['username'] ?? 'Staff') ?> &middot; <a href="<?= APP_URL ?>/auth/logout.php">Выйти</a></div>
    </div>

    <div class="az-content">
      <?php if ($flash = getFlashMessage()): ?>
        <div class="alert alert-<?= sanitize($flash['type']) ?> mb-16"><?= sanitize($flash['message']) ?></div>
      <?php endif; ?>

      <div class="smsg-layout">
        <!-- ── Список веток ── -->
        <aside class="smsg-threads">
          <?php if (!$threads): ?>
            <div class="smsg-empty">Пока нет переписок.</div>
          <?php else: foreach ($threads as $t):
            $u = (int)$t['user_id'];
            $o = $t['order_id'] !== null ? (int)$t['order_id'] : null;
            $isActive = $u === $openUserId && (($o === null && $openOrderId === null) || $o === $openOrderId);
            $href = APP_URL . '/admin/messages.php?user=' . $u . ($o ? '&order=' . $o : '');
          ?>
            <a href="<?= $href ?>" class="smsg-thread<?= $isActive ? ' active' : '' ?>">
              <div class="smsg-thread-top">
                <span class="smsg-name"><?= sanitize(threadCustomerName($t)) ?></span>
                <?php if ((int)$t['unread'] > 0): ?><span class="smsg-badge"><?= (int)$t['unread'] ?></span><?php endif; ?>
              </div>
              <div class="smsg-thread-sub">
                <?= $o ? '<i class="fa fa-shopping-bag"></i> Заказ #' . $o : '<i class="fa fa-life-ring"></i> Поддержка' ?>
                — <?= sanitize(mb_substr($t['last_body'], 0, 40)) ?>
              </div>
              <div class="smsg-thread-time"><?= sanitize(date('d.m.Y H:i', strtotime($t['last_at']))) ?></div>
            </a>
          <?php endforeach; endif; ?>
        </aside>

        <!-- ── Открытая ветка ── -->
        <section class="smsg-panel">
          <?php if ($openUserId === 0): ?>
            <div class="smsg-placeholder">Выберите переписку слева.</div>
          <?php else: ?>
            <header class="smsg-panel-head">
              <div>
                <h3><?= sanitize(threadCustomerName(array_merge($openCustomer, ['user_id' => $openUserId]))) ?></h3>
                <div class="smsg-panel-meta">
                  <?= $openOrderId ? 'Заказ #' . (int)$openOrderId : 'Общая поддержка' ?>
                  <?php if (!empty($openCustomer['phone_e164'])): ?> · +<?= sanitize($openCustomer['phone_e164']) ?><?php endif; ?>
                </div>
              </div>
              <?php if ($openOrderId): ?>
                <a href="<?= APP_URL ?>/admin/orders.php?id=<?= (int)$openOrderId ?>" class="smsg-order-link">Открыть заказ →</a>
              <?php endif; ?>
            </header>

            <div class="smsg-feed" id="smsgFeed">
              <?php if (!$messages): ?>
                <div class="smsg-empty">Сообщений пока нет.</div>
              <?php else: foreach ($messages as $m): ?>
                <div class="smsg-row smsg-<?= sanitize($m['sender']) ?>">
                  <div class="smsg-bubble">
                    <?php if ($m['sender'] === 'system'): ?>
                      <div class="smsg-lbl"><i class="fa fa-info-circle"></i> Системное</div>
                    <?php elseif ($m['sender'] === 'customer'): ?>
                      <div class="smsg-lbl">Покупатель</div>
                    <?php endif; ?>
                    <div class="smsg-text"><?= nl2br(sanitize($m['body'])) ?></div>
                    <div class="smsg-time"><?= sanitize(date('d.m.Y H:i', strtotime($m['created_at']))) ?></div>
                  </div>
                </div>
              <?php endforeach; endif; ?>
            </div>

            <form method="post" class="smsg-compose" action="<?= APP_URL ?>/admin/messages.php?user=<?= (int)$openUserId ?><?= $openOrderId ? '&order=' . (int)$openOrderId : '' ?>">
              <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
              <input type="hidden" name="action" value="reply">
              <input type="hidden" name="user_id" value="<?= (int)$openUserId ?>">
              <input type="hidden" name="order_id" value="<?= $openOrderId ? (int)$openOrderId : '' ?>">
              <textarea name="body" rows="2" placeholder="Ответ покупателю…" required></textarea>
              <button type="submit" class="az-btn az-btn-primary"><i class="fa fa-paper-plane"></i> Ответить</button>
            </form>
          <?php endif; ?>
        </section>
      </div>

    </div><!-- /.az-content -->
  </div><!-- /.az-main -->
</div><!-- /.az-panel -->

<style>
.smsg-layout { display:grid; grid-template-columns:300px 1fr; gap:16px; align-items:start; }
@media (max-width:820px){ .smsg-layout{ grid-template-columns:1fr; } }
.smsg-threads { display:flex; flex-direction:column; gap:6px; max-height:72vh; overflow-y:auto; }
.smsg-empty, .smsg-placeholder { color:#999; padding:24px; text-align:center; font-size:0.9rem; }
.smsg-thread { display:block; padding:10px 12px; border:1px solid #e6e6ea; border-radius:9px; background:#fff; text-decoration:none; color:#1c1c22; }
.smsg-thread:hover { border-color:#c9c9d2; }
.smsg-thread.active { border-color:#6a1b9a; box-shadow:0 0 0 1px #6a1b9a inset; }
.smsg-thread-top { display:flex; align-items:center; gap:8px; }
.smsg-name { font-weight:700; font-size:0.9rem; }
.smsg-badge { background:#C70909; color:#fff; border-radius:10px; font-size:0.7rem; padding:1px 7px; margin-left:auto; }
.smsg-thread-sub { color:#8a8a95; font-size:0.78rem; margin-top:3px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.smsg-thread-time { color:#b3b3bd; font-size:0.7rem; margin-top:3px; }
.smsg-panel { border:1px solid #e6e6ea; border-radius:11px; background:#fff; display:flex; flex-direction:column; min-height:460px; }
.smsg-panel-head { padding:14px 18px; border-bottom:1px solid #eee; display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
.smsg-panel-head h3 { margin:0 0 3px; font-size:1.05rem; }
.smsg-panel-meta { font-size:0.8rem; color:#8a8a95; }
.smsg-order-link { font-size:0.82rem; color:#6a1b9a; text-decoration:none; white-space:nowrap; }
.smsg-feed { flex:1; padding:18px; overflow-y:auto; max-height:54vh; display:flex; flex-direction:column; gap:12px; }
.smsg-row { display:flex; }
.smsg-row.smsg-staff { justify-content:flex-end; }
.smsg-row.smsg-customer, .smsg-row.smsg-system { justify-content:flex-start; }
.smsg-bubble { max-width:74%; padding:9px 13px; border-radius:13px; font-size:0.9rem; line-height:1.45; }
.smsg-staff .smsg-bubble { background:#6a1b9a; color:#fff; border-bottom-right-radius:4px; }
.smsg-customer .smsg-bubble { background:#f1f1f4; color:#1c1c22; border-bottom-left-radius:4px; }
.smsg-system .smsg-bubble { background:#fff7e8; color:#7a5a12; border:1px solid #f0dfb8; max-width:88%; }
.smsg-lbl { font-size:0.72rem; font-weight:700; margin-bottom:3px; opacity:0.85; }
.smsg-text { white-space:pre-wrap; word-break:break-word; }
.smsg-time { font-size:0.68rem; opacity:0.7; margin-top:4px; }
.smsg-compose { display:flex; gap:9px; padding:13px 16px; border-top:1px solid #eee; }
.smsg-compose textarea { flex:1; resize:vertical; min-height:42px; border:1px solid #d8d8de; border-radius:8px; padding:9px 11px; font-family:inherit; font-size:0.9rem; }
.smsg-compose button { white-space:nowrap; }
</style>
<script>
(function(){ var f=document.getElementById('smsgFeed'); if(f) f.scrollTop=f.scrollHeight; })();
</script>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
