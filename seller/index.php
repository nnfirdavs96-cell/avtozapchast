<?php
/**
 * Кабинет продавца — дашборд. Понятный обзор: статус магазина, счётчики
 * товаров, крупная кнопка добавления и короткая инструкция.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/seller.php';
$seller = requireSeller();

$db = getDB();
$sid = (int)$seller['id'];

// Счётчики товаров по статусам.
$counts = ['total' => 0, 'active' => 0, 'pending' => 0, 'rejected' => 0, 'draft' => 0];
$st = $db->prepare("SELECT moderation_status, COUNT(*) c FROM parts WHERE seller_id = ? GROUP BY moderation_status");
$st->execute([$sid]);
foreach ($st as $r) { $counts[$r['moderation_status']] = (int)$r['c']; $counts['total'] += (int)$r['c']; }

// Заказы продавца (маркетплейс, Фаза 2). Новые — то, что ждёт реакции.
// Таблицы может не быть, если миграция Фазы 2 не применена — тогда просто нули.
$ordNew = $ordTotal = 0;
try {
    $os = $db->prepare("SELECT COUNT(*) t, SUM(status='pending') n FROM order_sellers WHERE seller_id = ?");
    $os->execute([(int)$seller['id']]);
    if ($r = $os->fetch()) { $ordTotal = (int)$r['t']; $ordNew = (int)$r['n']; }
} catch (Throwable $e) { $ordNew = $ordTotal = 0; }

[$scls, $slabel, $shint] = sellerStatusInfo($seller['status']);
$approved = $seller['status'] === 'approved';

$pageTitle = 'Кабинет продавца — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="sl-wrap">
  <div class="container">

    <div class="sl-head">
      <div>
        <h1 class="sl-title"><i class="fa fa-briefcase"></i> <?= sanitize($seller['shop_name']) ?></h1>
        <p class="sl-sub">Кабинет продавца</p>
      </div>
      <?php require dirname(__DIR__) . '/includes/seller_nav.php'; ?>
    </div>

    <!-- Статус магазина -->
    <div class="sl-status sl-status--<?= $scls ?>">
      <div class="sl-status-ic">
        <i class="fa <?= $scls==='ok'?'fa-check-circle':($scls==='bad'?'fa-ban':'fa-clock-o') ?>"></i>
      </div>
      <div>
        <div class="sl-status-label"><?= sanitize($slabel) ?></div>
        <div class="sl-status-hint"><?= sanitize($shint) ?></div>
      </div>
    </div>

    <!-- Счётчики -->
    <div class="sl-stats">
      <div class="sl-stat"><span class="sl-stat-n"><?= $counts['total'] ?></span><span class="sl-stat-l">Всего товаров</span></div>
      <div class="sl-stat"><span class="sl-stat-n sl-green"><?= $counts['active'] ?></span><span class="sl-stat-l">Опубликовано</span></div>
      <div class="sl-stat"><span class="sl-stat-n sl-amber"><?= $counts['pending'] ?></span><span class="sl-stat-l">На проверке</span></div>
      <div class="sl-stat"><span class="sl-stat-n sl-red"><?= $counts['rejected'] ?></span><span class="sl-stat-l">Отклонено</span></div>
      <a href="<?= APP_URL ?>/seller/orders.php" class="sl-stat" style="text-decoration:none;">
        <span class="sl-stat-n <?= $ordNew > 0 ? 'sl-amber' : '' ?>"><?= $ordNew ?></span>
        <span class="sl-stat-l">Новых заказов<?= $ordTotal > 0 ? ' (всего ' . $ordTotal . ')' : '' ?></span>
      </a>
    </div>

    <!-- Действие -->
    <div class="sl-actions">
      <?php if ($approved): ?>
      <a href="<?= APP_URL ?>/seller/product_edit.php" class="sl-btn sl-btn-primary"><i class="fa fa-plus"></i> Добавить товар</a>
      <a href="<?= APP_URL ?>/seller/products.php" class="sl-btn sl-btn-outline"><i class="fa fa-list"></i> Мои товары</a>
      <?php else: ?>
      <button class="sl-btn sl-btn-primary" disabled title="Доступно после одобрения магазина"><i class="fa fa-plus"></i> Добавить товар</button>
      <span class="sl-lock"><i class="fa fa-lock"></i> Добавление товаров откроется после одобрения магазина</span>
      <?php endif; ?>
    </div>

    <!-- Как это работает -->
    <div class="sl-how">
      <h3>Как это работает</h3>
      <ol>
        <li><b>Добавьте товар</b> — название, артикул, цену, наличие, фото, категорию и бренд.</li>
        <li><b>Товар уходит на проверку</b> — модератор проверяет карточку.</li>
        <li><b>После одобрения</b> товар появляется в каталоге, и покупатели могут его найти и заказать.</li>
        <li><b>Заказы</b> по вашим товарам появятся в кабинете (раздел «Заказы» — скоро).</li>
      </ol>
    </div>

  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
