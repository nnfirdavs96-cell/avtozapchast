<?php
require_once dirname(__DIR__) . '/config/config.php';
$pageTitle = '404 — ' . getSetting('site_name');
header('HTTP/1.0 404 Not Found');
require_once dirname(__DIR__) . '/includes/header.php';
?>
<div style="text-align:center;padding:100px 20px;min-height:60vh;display:flex;flex-direction:column;justify-content:center;align-items:center">
  <div style="font-size:8rem;font-weight:900;color:#f0f0f0;line-height:1">404</div>
  <h2 style="font-size:2rem;font-weight:800;color:#222;margin:20px 0 12px"><?= t('page_not_found') ?></h2>
  <p style="color:#888;font-size:1rem;max-width:400px;margin-bottom:30px"><?= t('page_not_found_desc') ?></p>
  <div style="display:flex;gap:12px;flex-wrap:wrap;justify-content:center">
    <a href="<?= APP_URL ?>/index.php" class="button"><?= t('home') ?></a>
    <a href="<?= APP_URL ?>/catalog/index.php" class="button button_2"><?= t('shop') ?></a>
  </div>
</div>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
