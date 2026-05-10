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
                        <form action="<?= APP_URL ?>/search/index.php" method="GET">
                            <input name="q" placeholder="<?= t('search_placeholder') ?>" type="text">
                            <button type="submit"><i class="ion-ios-search-strong"></i></button>
                        </form>
                        <a href="<?= APP_URL ?>/index.php"><?= t('home') ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!--error section area end-->

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
