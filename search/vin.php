<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/product_card.php';

$pageTitle = t('find_by_vin');
$db = getDB();
$vin = trim(strtoupper((string)($_GET['vin'] ?? '')));
$record  = null;
$parts   = [];
$error   = '';

if ($vin !== '') {
    if (!preg_match('/^[A-HJ-NPR-Z0-9]{11,17}$/', $vin)) {
        $error = 'VIN должен состоять из 11–17 символов латиницы и цифр (без I,O,Q).';
    } else {
        $stmt = $db->prepare(
            "SELECT v.*, mk.name AS make_name, cm.name AS model_name, cm.year_from, cm.year_to
             FROM vin_records v
             JOIN car_makes  mk ON mk.id=v.make_id
             JOIN car_models cm ON cm.id=v.model_id
             WHERE v.vin=? LIMIT 1"
        );
        $stmt->execute([$vin]);
        $record = $stmt->fetch();

        if ($record) {
            $ps = $db->prepare(
                "SELECT DISTINCT p.*, b.name AS brand_name
                 FROM parts p
                 LEFT JOIN brands b ON b.id=p.brand_id
                 JOIN part_compatibility pc ON pc.part_id=p.id
                 WHERE pc.model_id=? AND p.is_active=1
                 ORDER BY p.created_at DESC LIMIT 24"
            );
            $ps->execute([$record['model_id']]);
            $parts = $ps->fetchAll();
        } else {
            $error = 'VIN не найден в нашей базе. Попробуйте подбор по марке/модели или свяжитесь с менеджером.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<meta name="csrf" content="<?= sanitize($csrfToken) ?>">

<div class="page-head">
  <div class="container">
    <h1><?= t('find_by_vin') ?></h1>
    <nav class="breadcrumb">
      <a href="<?= APP_URL ?>/index.php"><?= t('home') ?></a><span class="sep">/</span>
      <span class="current"><?= t('find_by_vin') ?></span>
    </nav>
  </div>
</div>

<section class="section">
  <div class="container">

    <div class="qf-card" style="max-width:780px;margin:0 auto 40px">
      <h3>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        Введите VIN-код автомобиля
      </h3>
      <form action="" method="get">
        <div class="form-group">
          <input type="text" name="vin" maxlength="17" minlength="11" required class="form-input"
                 placeholder="Например: WBAFR9C50BC123456"
                 value="<?= sanitize($vin) ?>"
                 style="text-transform:uppercase;letter-spacing:1px;font-family:'Rubik',monospace;font-size:1.1rem">
        </div>
        <p class="form-help">VIN-код состоит из 17 символов и обычно нанесён под лобовым стеклом, на стойке двери или в свидетельстве о регистрации.</p>
        <button type="submit" class="btn btn-primary mt-16"><?= t('find_parts') ?></button>
      </form>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-warning"><?= sanitize($error) ?></div>
    <?php endif; ?>

    <?php if ($record): ?>
      <div class="checkout-card mb-32" style="max-width:780px;margin:0 auto 32px">
        <h3>🚗 Найденный автомобиль</h3>
        <table class="spec-table">
          <tr><th>VIN</th><td><code><?= sanitize($record['vin']) ?></code></td></tr>
          <tr><th>Марка</th><td><strong><?= sanitize($record['make_name']) ?></strong></td></tr>
          <tr><th>Модель</th><td><?= sanitize($record['model_name']) ?> (<?= (int)$record['year_from'] ?>–<?= (int)$record['year_to'] ?>)</td></tr>
          <tr><th>Год выпуска</th><td><?= (int)$record['year'] ?></td></tr>
          <?php if ($record['engine']): ?><tr><th>Двигатель</th><td><?= sanitize($record['engine']) ?></td></tr><?php endif; ?>
        </table>
      </div>

      <div class="section-head left">
        <h2>Запчасти, подходящие для вашего авто</h2>
        <p>Найдено <?= count($parts) ?> совместимых деталей.</p>
      </div>
      <?php if (empty($parts)): ?>
        <p class="text-muted">К сожалению, в каталоге пока нет деталей именно для этой модели. Свяжитесь с менеджером для подбора.</p>
      <?php else: ?>
        <div class="products-grid">
          <?php foreach ($parts as $p) renderProductCard($p); ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($vin === ''): ?>
      <div class="feature-grid">
        <div class="feature">
          <div class="feature-icon">1</div>
          <div><h4>Введите VIN</h4><p>17-значный код вашего автомобиля</p></div>
        </div>
        <div class="feature">
          <div class="feature-icon">2</div>
          <div><h4>Получите подбор</h4><p>Мы найдём подходящие детали из нашего каталога</p></div>
        </div>
        <div class="feature">
          <div class="feature-icon">3</div>
          <div><h4>Заказывайте</h4><p>Оплата онлайн или при получении</p></div>
        </div>
        <div class="feature">
          <div class="feature-icon">4</div>
          <div><h4>Доставка</h4><p>По Москве за 24 часа, по России — 2-5 дней</p></div>
        </div>
      </div>

      <div class="text-center mt-32">
        <p class="text-muted mb-16">Не знаете VIN? Можно подобрать запчасти по марке и модели:</p>
        <a href="<?= APP_URL ?>/catalog/index.php" class="btn btn-outline btn-lg"><?= t('find_by_car') ?></a>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
