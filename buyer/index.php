<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole('buyer');

$user = getCurrentUser();
$db   = getDB();

// Stats
$ordersTotal = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
$ordersTotal->execute([$user['id']]);
$totalOrders = (int)$ordersTotal->fetchColumn();

$ordersPending = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'pending'");
$ordersPending->execute([$user['id']]);
$pendingOrders = (int)$ordersPending->fetchColumn();

$totalSpent = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE user_id = ? AND status != 'cancelled'");
$totalSpent->execute([$user['id']]);
$spent = (float)$totalSpent->fetchColumn();

// Recent orders
$recentStmt = $db->prepare(
    "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5"
);
$recentStmt->execute([$user['id']]);
$recentOrders = $recentStmt->fetchAll();

$pageTitle = 'Мой кабинет';
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/nav.php';
?>

<div class="dash-layout">
  <div class="dash-sidebar">
    <?php renderNav(); ?>
  </div>
  <div class="dash-main">
    <div class="dash-heading">
      МОЙ КАБИНЕТ
      <span class="dash-heading-badge">buyer</span>
    </div>

    <p style="color:var(--text-secondary);margin-bottom:28px;">
      Добро пожаловать, <strong style="color:var(--text-primary);"><?= sanitize($user['username']) ?></strong>!
    </p>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Всего заказов</div>
        <div class="stat-value"><?= $totalOrders ?></div>
        <div class="stat-sub">За всё время</div>
        <div class="stat-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Новых заказов</div>
        <div class="stat-value"><?= $pendingOrders ?></div>
        <div class="stat-sub">В ожидании обработки</div>
        <div class="stat-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Сумма заказов</div>
        <div class="stat-value" style="font-size:1.6rem;"><?= formatPrice($spent) ?></div>
        <div class="stat-sub">Без учёта отменённых</div>
        <div class="stat-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div>
      </div>
    </div>

    <!-- Recent orders -->
    <div class="card mb-24">
      <div class="card-header">
        <h3>ПОСЛЕДНИЕ ЗАКАЗЫ</h3>
        <a href="<?= APP_URL ?>/buyer/orders.php" class="btn btn-outline btn-sm">Все заказы</a>
      </div>
      <?php if (empty($recentOrders)): ?>
        <div class="card-body no-data" style="padding:40px;">
          <div class="no-data-icon">📦</div>
          <p>У вас ещё нет заказов.</p>
          <a href="<?= APP_URL ?>/catalog/index.php" class="btn btn-primary btn-sm" style="margin-top:12px;">Перейти в каталог</a>
        </div>
      <?php else: ?>
        <div class="table-wrap" style="border:none;border-radius:0;">
          <table class="data-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Дата</th>
                <th>Сумма</th>
                <th>Статус</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentOrders as $order): ?>
              <tr>
                <td><span class="mono">#<?= $order['id'] ?></span></td>
                <td style="color:var(--text-muted);font-size:0.8rem;"><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                <td style="font-family:var(--font-mono);color:var(--accent);"><?= formatPrice($order['total_amount']) ?></td>
                <td><span class="badge badge-<?= getOrderStatusClass($order['status']) ?>"><?= getOrderStatusLabel($order['status']) ?></span></td>
                <td><a href="<?= APP_URL ?>/buyer/orders.php?id=<?= $order['id'] ?>" class="btn btn-outline btn-sm">Детали</a></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Quick links -->
    <div class="grid-3">
      <a href="<?= APP_URL ?>/catalog/index.php" class="card" style="padding:20px;text-decoration:none;">
        <div style="font-family:var(--font-mono);font-size:0.65rem;color:var(--accent);text-transform:uppercase;letter-spacing:0.1em;margin-bottom:8px;">// Каталог</div>
        <div style="font-family:var(--font-display);font-size:1.1rem;letter-spacing:1px;">ПЕРЕЙТИ В КАТАЛОГ</div>
      </a>
      <a href="<?= APP_URL ?>/buyer/cart.php" class="card" style="padding:20px;text-decoration:none;">
        <div style="font-family:var(--font-mono);font-size:0.65rem;color:var(--accent);text-transform:uppercase;letter-spacing:0.1em;margin-bottom:8px;">// Корзина</div>
        <div style="font-family:var(--font-display);font-size:1.1rem;letter-spacing:1px;">МОЯ КОРЗИНА</div>
      </a>
      <a href="<?= APP_URL ?>/buyer/profile.php" class="card" style="padding:20px;text-decoration:none;">
        <div style="font-family:var(--font-mono);font-size:0.65rem;color:var(--accent);text-transform:uppercase;letter-spacing:0.1em;margin-bottom:8px;">// Профиль</div>
        <div style="font-family:var(--font-display);font-size:1.1rem;letter-spacing:1px;">РЕДАКТИРОВАТЬ</div>
      </a>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
