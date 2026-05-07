<?php
require_once __DIR__ . '/../config/config.php';
$pageTitle = t('payment');
$payments = getDB()->query("SELECT * FROM payment_methods WHERE is_active=1 ORDER BY sort_order")->fetchAll();
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div class="container">
  <h1><?= t('payment') ?></h1>
  <nav class="breadcrumb"><a href="<?= APP_URL ?>/index.php"><?= t('home') ?></a><span class="sep">/</span><span class="current"><?= t('payment') ?></span></nav>
</div></div>
<section class="section">
  <div class="container container-sm">
    <div class="checkout-card">
      <h2>Способы оплаты</h2>
      <div class="option-list mt-24">
        <?php foreach ($payments as $p): ?>
          <div class="option-item">
            <div class="option-info">
              <div class="name"><?= sanitize($p['name']) ?></div>
              <div class="desc"><?= sanitize($p['description']) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <h3 class="mt-32 mb-16">Безопасность</h3>
      <p style="line-height:1.8">Мы принимаем карты Visa, MasterCard, МИР, а также СБП. Все платежи проходят через защищённый шлюз. Данные карт не хранятся на нашем сервере. Для юридических лиц возможна оплата по счёту с НДС.</p>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
