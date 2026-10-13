<?php
/**
 * Кабинет продавца — «Сообщения» (переписка с покупателями).
 * Слева список веток (по покупателям), справа переписка и форма ответа.
 * Продавец видит только свои ветки (seller_id из сессии).
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/seller.php';
require_once dirname(__DIR__) . '/includes/seller_stats.php';
$seller = requireSeller();

$db  = getDB();
$sid = (int)$seller['id'];

// Ответ продавца.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['_csrf'] ?? '')) { denyAccess(); }
    $buyerId = (int)($_POST['buyer_id'] ?? 0);
    $body    = trim((string)($_POST['body'] ?? ''));
    if ($buyerId > 0 && $body !== '') {
        sellerPostMessage($db, $sid, $buyerId, null, 'seller', $body);
    }
    redirect(APP_URL . '/seller/messages.php?buyer=' . $buyerId);
}

$threads = sellerThreads($db, $sid);

$activeBuyer = (int)($_GET['buyer'] ?? 0);
if (!$activeBuyer && $threads) { $activeBuyer = (int)$threads[0]['buyer_id']; }

$thread = [];
$activeName = '';
if ($activeBuyer) {
    sellerMarkThreadRead($db, $sid, $activeBuyer);
    $thread = sellerThreadMessages($db, $sid, $activeBuyer);
    foreach ($threads as $t) { if ((int)$t['buyer_id'] === $activeBuyer) { $activeName = (string)($t['buyer_name'] ?? ''); break; } }
}

$sellerNavActive = 'messages';
$pageTitle = 'Сообщения — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="sl-wrap">
  <div class="container">
    <div class="sl-head">
      <div>
        <h1 class="sl-title"><i class="fa fa-comments"></i> Сообщения</h1>
        <p class="sl-sub">Переписка с покупателями · <?= sanitize($seller['shop_name']) ?></p>
      </div>
      <?php require dirname(__DIR__) . '/includes/seller_nav.php'; ?>
    </div>

    <?php if (!$threads): ?>
    <div class="sl-how" style="text-align:center;">
      <h3>Пока нет сообщений</h3>
      <p style="color:#77777f;margin:6px 0 0;">Когда покупатель напишет вам со страницы товара, переписка появится здесь.</p>
    </div>
    <?php else: ?>

    <div class="sl-msg">
      <!-- Список веток -->
      <aside class="sl-msg-list">
        <?php foreach ($threads as $t): $bid=(int)$t['buyer_id']; $un=(int)$t['unread']; ?>
        <a href="<?= APP_URL ?>/seller/messages.php?buyer=<?= $bid ?>"
           class="sl-msg-thread <?= $bid===$activeBuyer?'is-active':'' ?>">
          <span class="sl-msg-name"><i class="fa fa-user-circle-o"></i> <?= sanitize($t['buyer_name'] ?: ('Покупатель #'.$bid)) ?></span>
          <?php if ($un>0): ?><span class="sl-nav-badge"><?= $un ?></span><?php endif; ?>
          <span class="sl-msg-when"><?= date('d.m.Y', strtotime($t['last_at'])) ?></span>
        </a>
        <?php endforeach; ?>
      </aside>

      <!-- Переписка -->
      <section class="sl-msg-conv">
        <?php if ($activeBuyer): ?>
        <div class="sl-msg-conv-head"><i class="fa fa-user-circle-o"></i> <?= sanitize($activeName ?: ('Покупатель #'.$activeBuyer)) ?></div>
        <div class="sl-msg-body">
          <?php foreach ($thread as $m): $mine = $m['sender']==='seller'; ?>
          <div class="sl-bubble <?= $mine?'sl-bubble--me':'sl-bubble--them' ?>">
            <div class="sl-bubble-txt"><?= nl2br(sanitize($m['body'])) ?></div>
            <div class="sl-bubble-when"><?= date('d.m.Y H:i', strtotime($m['created_at'])) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <form method="POST" class="sl-msg-form">
          <input type="hidden" name="_csrf" value="<?= generateCsrfToken() ?>">
          <input type="hidden" name="buyer_id" value="<?= $activeBuyer ?>">
          <textarea name="body" rows="2" maxlength="4000" placeholder="Ваш ответ покупателю…" required></textarea>
          <button type="submit" class="sl-btn sl-btn-primary"><i class="fa fa-paper-plane"></i> Отправить</button>
        </form>
        <?php else: ?>
        <p style="color:#888;padding:20px;">Выберите переписку слева.</p>
        <?php endif; ?>
      </section>
    </div>

    <?php endif; ?>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
