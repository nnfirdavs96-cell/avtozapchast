<?php
require_once dirname(__DIR__) . '/config/config.php';
$pageTitle = '403 — ' . getSetting('site_name');
header('HTTP/1.1 403 Forbidden');
require_once dirname(__DIR__) . '/includes/header.php';
?>

<!--error section area start-->
<div class="error_page_bg">
    <div class="container">
        <div class="error_section">
            <div class="row">
                <div class="col-12">
                    <div class="error_form">
                        <h1>403</h1>
                        <h2><?= t('access_denied') ?></h2>
                        <p><?= t('access_denied_desc') ?></p>
                        <a href="<?= APP_URL ?>/index.php"><?= t('home') ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!--error section area end-->

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
