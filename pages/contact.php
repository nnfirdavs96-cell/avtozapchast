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

<div class="contact_area section_padding" style="padding:60px 0">
  <div class="container">
    <div class="row">
      <div class="col-lg-8 col-md-7 mb-4">
        <div class="section_title"><h2><?= t('contact_us') ?></h2></div>
        <?php if ($success): ?>
        <div class="az-alert az-alert-success"><?= t('success') ?>! Ваше сообщение отправлено. Мы свяжемся с вами в ближайшее время.</div>
        <?php endif; ?>
        <?php foreach ($errors as $err): ?>
        <div class="az-alert az-alert-danger"><?= sanitize($err) ?></div>
        <?php endforeach; ?>
        <form method="POST" class="contact_form" style="background:#fff;padding:30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.06)">
          <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
          <div class="row">
            <div class="col-md-6 mb-3">
              <div class="form-group">
                <label><?= t('your_name') ?> *</label>
                <input type="text" class="form-control" name="name" value="<?= sanitize($_POST['name'] ?? '') ?>" required>
              </div>
            </div>
            <div class="col-md-6 mb-3">
              <div class="form-group">
                <label><?= t('email') ?> *</label>
                <input type="email" class="form-control" name="email" value="<?= sanitize($_POST['email'] ?? '') ?>" required>
              </div>
            </div>
          </div>
          <div class="mb-3">
            <div class="form-group">
              <label><?= t('subject') ?></label>
              <input type="text" class="form-control" name="subject" value="<?= sanitize($_POST['subject'] ?? '') ?>">
            </div>
          </div>
          <div class="mb-3">
            <div class="form-group">
              <label><?= t('message') ?> *</label>
              <textarea class="form-control" name="message" rows="6" required><?= sanitize($_POST['message'] ?? '') ?></textarea>
            </div>
          </div>
          <button type="submit" class="button"><?= t('send_message') ?></button>
        </form>
      </div>
      <div class="col-lg-4 col-md-5 mb-4">
        <div style="background:#fff;padding:30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.06)">
          <h4 style="font-size:1.1rem;font-weight:700;margin-bottom:20px"><?= t('contact_info') ?></h4>
          <div style="display:flex;flex-direction:column;gap:16px">
            <div><strong><?= t('working_hours') ?></strong><p style="color:#666;margin:4px 0 0;font-size:0.875rem">Пн–Пт: 9:00–20:00<br>Сб–Вс: 10:00–18:00</p></div>
            <div><strong><?= t('phone') ?></strong><p style="color:#d32f2f;margin:4px 0 0"><a href="tel:<?= sanitize(getSetting('site_phone','+74951234567')) ?>" style="color:#d32f2f;text-decoration:none"><?= sanitize(getSetting('site_phone','+7 (495) 123-45-67')) ?></a></p></div>
            <div><strong><?= t('email') ?></strong><p style="color:#d32f2f;margin:4px 0 0"><a href="mailto:<?= sanitize(getSetting('site_email','info@avtozapchast.ru')) ?>" style="color:#d32f2f;text-decoration:none"><?= sanitize(getSetting('site_email','info@avtozapchast.ru')) ?></a></p></div>
            <div><strong><?= t('address') ?></strong><p style="color:#666;margin:4px 0 0;font-size:0.875rem"><?= sanitize(getSetting('site_address','г. Москва, ул. Автомобильная, д. 1')) ?></p></div>
          </div>
          <?php if (getSetting('site_telegram')): ?>
          <div class="mt-3"><a href="https://t.me/<?= sanitize(getSetting('site_telegram')) ?>" class="button" style="display:inline-block;width:100%;text-align:center">Telegram</a></div>
          <?php endif; ?>
          <?php if (getSetting('site_whatsapp')): ?>
          <div class="mt-2"><a href="https://wa.me/<?= sanitize(getSetting('site_whatsapp')) ?>" class="button button_2" style="display:inline-block;width:100%;text-align:center">WhatsApp</a></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
