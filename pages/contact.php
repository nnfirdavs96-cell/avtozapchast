<?php
require_once dirname(__DIR__) . '/config/config.php';
$pageTitle = t('contact') . ' — ' . getSetting('site_name');
$success = false;
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Неверный токен безопасности.';
    } else {
        $name    = trim($_POST['name'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        if (!$name)    $errors[] = t('your_name') . ' обязательно.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = t('email') . ' некорректен.';
        if (!$message) $errors[] = t('message') . ' обязательно.';
        if (!$errors) {
            // Store contact message (optional: could email admin)
            // For now just show success
            $success = true;
        }
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>
<?= breadcrumb([['label'=>t('home'),'url'=>APP_URL.'/index.php'],['label'=>t('contact')]]) ?>

<div class="contact_page_bg">
    <div class="container">
        <!--contact area start-->
        <div class="contact_area">
            <div class="row">
                <div class="col-lg-8 col-md-12">
                    <div class="contact_message form">
                        <h3><?= t('contact_us') ?></h3>

                        <?php if ($success): ?>
                        <div class="alert alert-success" role="alert"><?= t('contact_success') ?></div>
                        <?php endif; ?>
                        <?php foreach ($errors as $err): ?>
                        <div class="alert alert-danger" role="alert"><?= sanitize($err) ?></div>
                        <?php endforeach; ?>

                        <form id="contact-form" method="POST" action="<?= APP_URL ?>/pages/contact.php">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            <p>
                                <label><?= t('your_name') ?> <span>*</span></label>
                                <input name="name" placeholder="<?= t('your_name') ?> *" type="text" value="<?= sanitize($_POST['name'] ?? '') ?>" required>
                            </p>
                            <p>
                                <label><?= t('email') ?> <span>*</span></label>
                                <input name="email" placeholder="<?= t('email') ?> *" type="email" value="<?= sanitize($_POST['email'] ?? '') ?>" required>
                            </p>
                            <p>
                                <label><?= t('subject') ?></label>
                                <input name="subject" placeholder="<?= t('subject') ?>" type="text" value="<?= sanitize($_POST['subject'] ?? '') ?>">
                            </p>
                            <div class="contact_textarea">
                                <label><?= t('message') ?> <span>*</span></label>
                                <textarea placeholder="<?= t('message') ?> *" name="message" class="form-control2" required><?= sanitize($_POST['message'] ?? '') ?></textarea>
                            </div>
                            <button type="submit"><?= t('send_message') ?></button>
                            <p class="form-messege"></p>
                        </form>
                    </div>
                </div>

                <div class="col-lg-4 col-md-12">
                    <div class="contact_message content">
                        <h3><?= t('contact_info') ?></h3>
                        <ul>
                            <li>
                                <i class="fa fa-phone"></i>
                                <a href="tel:<?= sanitize(getSetting('site_phone', '+74951234567')) ?>">
                                    <?= sanitize(getSetting('site_phone', '+7 (495) 123-45-67')) ?>
                                </a>
                            </li>
                            <li>
                                <i class="fa fa-envelope-o"></i>
                                <a href="mailto:<?= sanitize(getSetting('site_email', 'info@avtozapchast.ru')) ?>">
                                    <?= sanitize(getSetting('site_email', 'info@avtozapchast.ru')) ?>
                                </a>
                            </li>
                            <li>
                                <i class="fa fa-fax"></i>
                                <?= sanitize(getSetting('site_address', 'г. Москва, ул. Автомобильная, д. 1')) ?>
                            </li>
                            <li>
                                <i class="fa fa-clock-o"></i>
                                <?= t('mon_fri') ?>: 9:00–20:00 / <?= t('sat_sun') ?>: 10:00–18:00
                            </li>
                        </ul>
                        <?php if (getSetting('site_telegram')): ?>
                        <p><a href="https://t.me/<?= sanitize(getSetting('site_telegram')) ?>" class="button" style="display:inline-block;text-align:center;margin-bottom:8px;width:100%">Telegram</a></p>
                        <?php endif; ?>
                        <?php if (getSetting('site_whatsapp')): ?>
                        <p><a href="https://wa.me/<?= sanitize(getSetting('site_whatsapp')) ?>" class="button button_2" style="display:inline-block;text-align:center;width:100%">WhatsApp</a></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <!--contact area end-->
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
