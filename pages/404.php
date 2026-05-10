<?php
require_once dirname(__DIR__) . '/config/config.php';
$pageTitle = '404 — ' . getSetting('site_name');
header('HTTP/1.0 404 Not Found');
require_once dirname(__DIR__) . '/includes/header.php';
?>

<!--error section area start-->
<div class="error_page_bg">
    <div class="container">
        <div class="error_section">
            <div class="row">
                <div class="col-12">
                    <div class="error_form">
                        <h1>404</h1>
                        <h2><?= t('page_not_found') ?></h2>
                        <p><?= t('page_not_found_desc') ?></p>
                        <div style="display:flex;gap:12px;flex-wrap:wrap;justify-content:center;margin-top:20px;">
                            <a href="<?= APP_URL ?>/index.php" class="button"><?= t('home') ?></a>
                            <a href="<?= APP_URL ?>/catalog/index.php" class="button button_2"><?= t('shop') ?></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!--error section area end-->

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
