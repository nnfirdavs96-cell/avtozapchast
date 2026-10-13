<?php
/** Навигация кабинета продавца. Активный пункт — через $sellerNavActive. */
$a = $sellerNavActive ?? '';
// Счётчик непрочитанных сообщений от покупателей (для бейджа у «Сообщения»).
require_once dirname(__DIR__) . '/includes/seller_stats.php';
$navSeller   = function_exists('currentSeller') ? currentSeller() : null;
$navUnread   = $navSeller ? sellerUnreadCount(getDB(), (int)$navSeller['id']) : 0;
?>
<nav class="sl-nav">
  <a href="<?= APP_URL ?>/seller/index.php" class="<?= $a==='dashboard'?'active':'' ?>"><i class="fa fa-th-large"></i> Обзор</a>
  <a href="<?= APP_URL ?>/seller/products.php" class="<?= $a==='products'?'active':'' ?>"><i class="fa fa-list"></i> Мои товары</a>
  <a href="<?= APP_URL ?>/seller/orders.php" class="<?= $a==='orders'?'active':'' ?>"><i class="fa fa-shopping-bag"></i> Мои заказы</a>
  <a href="<?= APP_URL ?>/seller/finance.php" class="<?= $a==='finance'?'active':'' ?>"><i class="fa fa-money"></i> Финансы</a>
  <a href="<?= APP_URL ?>/seller/messages.php" class="<?= $a==='messages'?'active':'' ?>"><i class="fa fa-comments"></i> Сообщения<?php if ($navUnread > 0): ?> <span class="sl-nav-badge"><?= (int)$navUnread ?></span><?php endif; ?></a>
  <a href="<?= APP_URL ?>/seller/returns.php" class="<?= $a==='returns'?'active':'' ?>"><i class="fa fa-undo"></i> Возвраты</a>
  <a href="<?= APP_URL ?>/seller/shop.php" class="<?= $a==='shop'?'active':'' ?>"><i class="fa fa-cog"></i> Магазин</a>
  <a href="<?= APP_URL ?>/seller/product_edit.php" class="<?= $a==='add'?'active':'' ?>"><i class="fa fa-plus"></i> Добавить</a>
  <a href="<?= APP_URL ?>/index.php"><i class="fa fa-home"></i> На сайт</a>
  <a href="<?= APP_URL ?>/auth/logout.php" class="sl-nav-out"><i class="fa fa-sign-out"></i> Выйти</a>
</nav>
