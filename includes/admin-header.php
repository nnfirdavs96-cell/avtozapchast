<?php
// Clean admin/panel header — no front-end navigation
if (!function_exists('t')) require_once dirname(__DIR__) . '/config/config.php';

$lang        = getLang();
$currentUser = getCurrentUser();
$siteName    = getSetting('site_name', t('site_name'));
$pageTitle   = isset($pageTitle) ? $pageTitle : ($siteName . ' — Panel');
?>
<!doctype html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= sanitize($pageTitle) ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="<?= APP_URL ?>/assets/img/favicon.ico">
    <link rel="stylesheet" href="<?= MAZLAY_CSS ?>/plugins.css">
    <link rel="stylesheet" href="<?= MAZLAY_CSS ?>/style.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/custom.css">
    <meta name="csrf" content="<?= generateCsrfToken() ?>">
</head>
<body class="az-body">

<?php if ($flash = getFlashMessage()): ?>
<div id="flash-global" style="position:fixed;top:0;left:0;right:0;z-index:9999;padding:12px 20px;text-align:center;font-weight:600;background:<?= $flash['type']==='success'?'#28a745':($flash['type']==='danger'?'#dc3545':'#ffc107') ?>;color:<?= $flash['type']==='warning'?'#333':'#fff' ?>">
    <?= sanitize($flash['message']) ?>
    <button onclick="this.parentNode.remove()" style="float:right;background:none;border:none;color:inherit;font-size:1.2rem;cursor:pointer">&times;</button>
</div>
<script>setTimeout(()=>{const f=document.getElementById('flash-global');if(f)f.remove();},4000)</script>
<?php endif; ?>
