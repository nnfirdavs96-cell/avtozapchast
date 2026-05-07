<?php
require_once __DIR__ . '/../config/config.php';
$pageTitle = t('delivery');
$delivery = getDB()->query("SELECT * FROM delivery_methods WHERE is_active=1 ORDER BY sort_order")->fetchAll();
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div class="container">
  <h1><?= t('delivery') ?></h1>
  <nav class="breadcrumb"><a href="<?= APP_URL ?>/index.php"><?= t('home') ?></a><span class="sep">/</span><span class="current"><?= t('delivery') ?></span></nav>
</div></div>
<section class="section">
  <div class="container container-sm">
    <div class="checkout-card">
      <h2>Способы доставки</h2>
      <p class="mt-16" style="color:var(--muted)">Мы доставляем по всей России и в страны СНГ. Стоимость и сроки зависят от выбранного способа:</p>
      <div class="option-list mt-24">
        <?php foreach ($delivery as $d): ?>
          <div class="option-item">
            <div class="option-info">
              <div class="name"><?= sanitize($d['name']) ?></div>
              <div class="desc"><?= sanitize($d['description']) ?> · ⏱ <?= sanitize($d['eta_days']) ?></div>
            </div>
            <div class="option-price"><?= $d['cost']>0 ? money($d['cost']) : 'Бесплатно' ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <h3 class="mt-32 mb-16">Условия</h3>
      <ul style="line-height:1.9;list-style:disc inside">
        <li>Заказы принимаются круглосуточно. Обработка — в рабочее время.</li>
        <li>По Москве курьер доставит в течение суток (при заказе до 16:00).</li>
        <li>Самовывоз доступен сразу после подтверждения заказа.</li>
        <li>При заказе от 5 000 ₽ доставка по Москве — бесплатно.</li>
        <li>Доставка в Таджикистан выполняется EMS — 7–14 дней.</li>
      </ul>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
