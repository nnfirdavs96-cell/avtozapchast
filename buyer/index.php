<?php
require_once __DIR__ . '/../config/config.php';
requireRole(['buyer','manager','admin','superadmin']);

$db = getDB();
$user = getCurrentUser();
$ordersCnt   = (int)$db->prepare("SELECT COUNT(*) FROM orders WHERE user_id=?")->execute([$_SESSION['user_id']]) ? (int)$db->query("SELECT COUNT(*) FROM orders WHERE user_id=" . (int)$_SESSION['user_id'])->fetchColumn() : 0;
$wishlistCnt = (int)$db->query("SELECT COUNT(*) FROM wishlist WHERE user_id=" . (int)$_SESSION['user_id'])->fetchColumn();
$cartCnt     = getCartCount();
$totalSpent  = (float)$db->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE user_id=" . (int)$_SESSION['user_id'] . " AND status<>'cancelled'")->fetchColumn();

$recent = $db->prepare("SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
$recent->execute([$_SESSION['user_id']]);
$recentOrders = $recent->fetchAll();

$pageTitle = t('my_account');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head"><div class="container">
  <h1><?= t('my_account') ?></h1>
  <nav class="breadcrumb"><a href="<?= APP_URL ?>/index.php"><?= t('home') ?></a><span class="sep">/</span><span class="current"><?= t('my_account') ?></span></nav>
</div></div>

<section class="section">
  <div class="container">
    <div class="catalog-layout">
      <aside class="sidebar">
        <div class="filter-block">
          <div style="display:flex;align-items:center;gap:12px">
            <div style="width:48px;height:48px;background:var(--primary);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.2rem">
              <?= sanitize(mb_strtoupper(mb_substr($user['username'],0,1))) ?>
            </div>
            <div>
              <strong><?= sanitize($user['username']) ?></strong>
              <div class="text-muted" style="font-size:0.78rem"><?= sanitize($user['email']) ?></div>
            </div>
          </div>
        </div>
        <ul class="filter-list">
          <li><a href="<?= APP_URL ?>/buyer/index.php" class="active">📊 Обзор</a></li>
          <li><a href="<?= APP_URL ?>/buyer/orders.php">📦 <?= t('my_orders') ?></a></li>
          <li><a href="<?= APP_URL ?>/buyer/wishlist.php">❤ <?= t('wishlist') ?> (<?= $wishlistCnt ?>)</a></li>
          <li><a href="<?= APP_URL ?>/buyer/cart.php">🛒 <?= t('cart') ?> (<?= $cartCnt ?>)</a></li>
          <li><a href="<?= APP_URL ?>/buyer/profile.php">👤 <?= t('profile') ?></a></li>
          <li><a href="<?= APP_URL ?>/auth/logout.php" style="color:var(--danger)">🚪 <?= t('logout') ?></a></li>
        </ul>
      </aside>

      <div>
        <div class="grid-3 mb-32">
          <div class="checkout-card text-center" style="margin:0">
            <div style="font-size:2.4rem;color:var(--primary);font-weight:800"><?= count($recentOrders) ? (int)$db->query("SELECT COUNT(*) FROM orders WHERE user_id=" . (int)$_SESSION['user_id'])->fetchColumn() : 0 ?></div>
            <div class="text-muted">Всего заказов</div>
          </div>
          <div class="checkout-card text-center" style="margin:0">
            <div style="font-size:2.4rem;color:var(--primary);font-weight:800"><?= $wishlistCnt ?></div>
            <div class="text-muted"><?= t('wishlist') ?></div>
          </div>
          <div class="checkout-card text-center" style="margin:0">
            <div style="font-size:2.4rem;color:var(--primary);font-weight:800"><?= money($totalSpent) ?></div>
            <div class="text-muted">Общая сумма заказов</div>
          </div>
        </div>

        <div class="checkout-card">
          <div class="flex-between mb-16">
            <h3>Последние заказы</h3>
            <a href="<?= APP_URL ?>/buyer/orders.php" class="btn btn-link"><?= t('view_all') ?> →</a>
          </div>
          <?php if (empty($recentOrders)): ?>
            <p class="text-muted">У вас пока нет заказов.</p>
          <?php else: ?>
            <table class="cart-table">
              <thead><tr><th>№</th><th>Дата</th><th>Сумма</th><th>Статус</th></tr></thead>
              <tbody>
                <?php foreach ($recentOrders as $o): ?>
                  <tr>
                    <td>#<?= (int)$o['id'] ?></td>
                    <td><?= date('d.m.Y', strtotime($o['created_at'])) ?></td>
                    <td><strong><?= money($o['total_amount']) ?></strong></td>
                    <td><span class="badge badge-<?= getOrderStatusClass($o['status']) ?>"><?= sanitize(getOrderStatusLabel($o['status'])) ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
