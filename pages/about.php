<?php
require_once __DIR__ . '/../config/config.php';
$pageTitle = t('about');
$pageDescription = 'АвтоЗапчасть / AutoDoc — оптовая и розничная продажа автозапчастей в России и Таджикистане с 2013 года.';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div class="container">
  <h1><?= t('about') ?></h1>
  <nav class="breadcrumb"><a href="<?= APP_URL ?>/index.php"><?= t('home') ?></a><span class="sep">/</span><span class="current"><?= t('about') ?></span></nav>
</div></div>
<section class="section">
  <div class="container container-sm">
    <div class="checkout-card">
      <h2>Об «АвтоDOC»</h2>
      <p class="mt-16" style="font-size:1.05rem;line-height:1.85">Мы работаем с 2013 года и поставляем оригинальные и качественные аналоговые запчасти для легковых и грузовых автомобилей. Наши склады расположены в Москве и Душанбе — это позволяет быстро доставлять заказы по всей России и Таджикистану.</p>
      <h3 class="mt-32 mb-16">Наши преимущества</h3>
      <div class="feature-grid">
        <div class="feature"><div class="feature-icon">12</div><div><h4>лет на рынке</h4><p>Стабильность и надёжность</p></div></div>
        <div class="feature"><div class="feature-icon">50K+</div><div><h4>SKU в наличии</h4><p>Огромный ассортимент</p></div></div>
        <div class="feature"><div class="feature-icon">100+</div><div><h4>брендов</h4><p>Bosch, NGK, Brembo, Gates, SKF и др.</p></div></div>
        <div class="feature"><div class="feature-icon">24ч</div><div><h4>доставка</h4><p>По Москве и Душанбе</p></div></div>
      </div>
      <h3 class="mt-32 mb-16">Наша миссия</h3>
      <p style="line-height:1.8">Сделать качественные автозапчасти доступными каждому автолюбителю и СТО. Мы внимательно отбираем поставщиков, проверяем каждую партию и стоим на стороне покупателя при любых вопросах.</p>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
