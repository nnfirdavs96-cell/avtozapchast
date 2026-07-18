<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/messaging.php';
requireRole('buyer');

$user   = getCurrentUser();
$userId = (int)$user['id'];
$db     = getDB();

// Какая ветка открыта: ?order=ID → по заказу (проверяем принадлежность); иначе — поддержка.
$openOrderId = null;
if (isset($_GET['order']) && ctype_digit((string)$_GET['order'])) {
    $oid = (int)$_GET['order'];
    $chk = $db->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
    $chk->execute([$oid, $userId]);
    if ($chk->fetchColumn()) $openOrderId = $oid;
}

// ── Отправка сообщения ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send') {
    if (!verifyCsrfToken($_POST['_csrf'] ?? '')) {
        flashMessage('danger', 'Ошибка безопасности. Попробуйте снова.');
        redirect(APP_URL . '/buyer/messages.php' . ($openOrderId ? '?order=' . $openOrderId : ''));
    }
    $body = trim($_POST['body'] ?? '');
    if ($body !== '') {
        postMessage($userId, $openOrderId, 'customer', $body);
    }
    redirect(APP_URL . '/buyer/messages.php' . ($openOrderId ? '?order=' . $openOrderId : ''));
}

// Открытую ветку помечаем прочитанной покупателем.
markThreadRead($userId, $openOrderId, 'customer');

$threads  = getCustomerThreads($userId);
$messages = getThreadMessages($userId, $openOrderId);

$pageTitle = 'Сообщения';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<?= breadcrumb([
    ['label' => t('home'), 'url' => APP_URL . '/index.php'],
    ['label' => 'Сообщения'],
]) ?>

<div class="az-account">
    <div class="container">
        <?= renderBuyerAccountNav('messages') ?>
        <div class="az-account-body">

            <div class="msg-layout">
                <!-- ── Список веток ── -->
                <aside class="msg-threads">
                    <a href="<?= APP_URL ?>/buyer/messages.php"
                       class="msg-thread<?= $openOrderId === null ? ' active' : '' ?>">
                        <span class="msg-thread-title"><i class="fa fa-life-ring"></i> Поддержка</span>
                        <span class="msg-thread-sub">Общие вопросы</span>
                    </a>
                    <?php foreach ($threads as $t): if ($t['order_id'] === null) continue; ?>
                        <a href="<?= APP_URL ?>/buyer/messages.php?order=<?= (int)$t['order_id'] ?>"
                           class="msg-thread<?= $openOrderId === (int)$t['order_id'] ? ' active' : '' ?>">
                            <span class="msg-thread-title">
                                <i class="fa fa-shopping-bag"></i> Заказ #<?= (int)$t['order_id'] ?>
                                <?php if ((int)$t['unread'] > 0): ?><span class="msg-badge"><?= (int)$t['unread'] ?></span><?php endif; ?>
                            </span>
                            <span class="msg-thread-sub"><?= sanitize(mb_substr($t['last_body'], 0, 46)) ?></span>
                        </a>
                    <?php endforeach; ?>
                </aside>

                <!-- ── Открытая ветка ── -->
                <section class="msg-panel">
                    <header class="msg-panel-head">
                        <?php if ($openOrderId): ?>
                            <h3>Заказ #<?= (int)$openOrderId ?></h3>
                            <a href="<?= APP_URL ?>/buyer/orders.php?id=<?= (int)$openOrderId ?>" class="msg-panel-link">Открыть заказ →</a>
                        <?php else: ?>
                            <h3>Поддержка</h3>
                            <span class="msg-panel-link">Напишите нам — менеджер ответит здесь</span>
                        <?php endif; ?>
                    </header>

                    <div class="msg-feed" id="msgFeed">
                        <?php if (!$messages): ?>
                            <div class="msg-empty">Пока сообщений нет. Напишите первым — мы на связи.</div>
                        <?php else: foreach ($messages as $m): ?>
                            <div class="msg-row msg-<?= sanitize($m['sender']) ?>">
                                <div class="msg-bubble">
                                    <?php if ($m['sender'] === 'system'): ?>
                                        <div class="msg-sys-label"><i class="fa fa-info-circle"></i> Уведомление</div>
                                    <?php elseif ($m['sender'] === 'staff'): ?>
                                        <div class="msg-who">Менеджер</div>
                                    <?php endif; ?>
                                    <div class="msg-text"><?= nl2br(sanitize($m['body'])) ?></div>
                                    <div class="msg-time"><?= sanitize(date('d.m.Y H:i', strtotime($m['created_at']))) ?></div>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>

                    <form method="post" class="msg-compose" action="<?= APP_URL ?>/buyer/messages.php<?= $openOrderId ? '?order=' . (int)$openOrderId : '' ?>">
                        <input type="hidden" name="_csrf" value="<?= generateCsrfToken() ?>">
                        <input type="hidden" name="action" value="send">
                        <textarea name="body" rows="2" placeholder="Ваше сообщение…" required></textarea>
                        <button type="submit" class="az-btn az-btn-primary"><i class="fa fa-paper-plane"></i> Отправить</button>
                    </form>
                </section>
            </div>

        </div><!-- /.az-account-body -->
    </div><!-- /.container -->
</div><!-- /.az-account -->

<style>
.msg-layout { display:grid; grid-template-columns:260px 1fr; gap:18px; align-items:start; }
@media (max-width:720px){ .msg-layout{ grid-template-columns:1fr; } }
.msg-threads { display:flex; flex-direction:column; gap:6px; }
.msg-thread { display:block; padding:11px 13px; border:1px solid #e6e2d8; border-radius:9px; background:#fff; text-decoration:none; color:#1c1a15; }
.msg-thread:hover { border-color:#c9c2b2; }
.msg-thread.active { border-color:#C70909; box-shadow:0 0 0 1px #C70909 inset; }
.msg-thread-title { display:flex; align-items:center; gap:7px; font-weight:600; font-size:0.9rem; }
.msg-thread-sub { display:block; color:#9a9384; font-size:0.76rem; margin-top:3px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.msg-badge { background:#C70909; color:#fff; border-radius:10px; font-size:0.7rem; padding:1px 7px; margin-left:auto; }
.msg-panel { border:1px solid #e6e2d8; border-radius:11px; background:#fff; display:flex; flex-direction:column; min-height:440px; }
.msg-panel-head { padding:14px 18px; border-bottom:1px solid #eee6d8; display:flex; align-items:baseline; justify-content:space-between; gap:10px; }
.msg-panel-head h3 { margin:0; font-size:1.05rem; }
.msg-panel-link { font-size:0.82rem; color:#8a8372; text-decoration:none; }
.msg-panel-link:hover { color:#C70909; }
.msg-feed { flex:1; padding:18px; overflow-y:auto; max-height:52vh; display:flex; flex-direction:column; gap:12px; }
.msg-empty { color:#9a9384; text-align:center; margin:auto; font-size:0.9rem; }
.msg-row { display:flex; }
.msg-row.msg-customer { justify-content:flex-end; }
.msg-row.msg-staff, .msg-row.msg-system { justify-content:flex-start; }
.msg-bubble { max-width:74%; padding:9px 13px; border-radius:13px; font-size:0.9rem; line-height:1.45; }
.msg-customer .msg-bubble { background:#C70909; color:#fff; border-bottom-right-radius:4px; }
.msg-staff .msg-bubble { background:#f1efe8; color:#1c1a15; border-bottom-left-radius:4px; }
.msg-system .msg-bubble { background:#fff7e8; color:#7a5a12; border:1px solid #f0dfb8; max-width:88%; }
.msg-who { font-size:0.72rem; font-weight:700; color:#8a8372; margin-bottom:3px; }
.msg-sys-label { font-size:0.72rem; font-weight:700; margin-bottom:3px; }
.msg-text { white-space:pre-wrap; word-break:break-word; }
.msg-time { font-size:0.68rem; opacity:0.7; margin-top:4px; }
.msg-compose { display:flex; gap:9px; padding:13px 16px; border-top:1px solid #eee6d8; }
.msg-compose textarea { flex:1; resize:vertical; min-height:42px; border:1px solid #d8d2c4; border-radius:8px; padding:9px 11px; font-family:inherit; font-size:0.9rem; }
.msg-compose button { white-space:nowrap; }
</style>
<script>
(function(){ var f=document.getElementById('msgFeed'); if(f) f.scrollTop=f.scrollHeight; })();
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
