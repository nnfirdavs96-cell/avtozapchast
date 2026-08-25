<?php
/** Навигация кабинета продавца. Активный пункт — через $sellerNavActive. */
$a = $sellerNavActive ?? '';
?>
<nav class="sl-nav">
  <a href="<?= APP_URL ?>/seller/index.php" class="<?= $a==='dashboard'?'active':'' ?>"><i class="fa fa-th-large"></i> Обзор</a>
  <a href="<?= APP_URL ?>/seller/products.php" class="<?= $a==='products'?'active':'' ?>"><i class="fa fa-list"></i> Мои товары</a>
  <a href="<?= APP_URL ?>/seller/orders.php" class="<?= $a==='orders'?'active':'' ?>"><i class="fa fa-shopping-bag"></i> Мои заказы</a>
  <a href="<?= APP_URL ?>/seller/finance.php" class="<?= $a==='finance'?'active':'' ?>"><i class="fa fa-money"></i> Финансы</a>
  <a href="<?= APP_URL ?>/seller/returns.php" class="<?= $a==='returns'?'active':'' ?>"><i class="fa fa-undo"></i> Возвраты</a>
  <a href="<?= APP_URL ?>/seller/product_edit.php" class="<?= $a==='add'?'active':'' ?>"><i class="fa fa-plus"></i> Добавить</a>
  <a href="<?= APP_URL ?>/index.php"><i class="fa fa-home"></i> На сайт</a>
  <a href="<?= APP_URL ?>/auth/logout.php" class="sl-nav-out"><i class="fa fa-sign-out"></i> Выйти</a>
</nav>
