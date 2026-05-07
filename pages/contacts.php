<?php
require_once __DIR__ . '/../config/config.php';
$err = $msg = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $err = 'Неверный CSRF';
    } else {
        $name = trim((string)$_POST['name']); $email = trim((string)$_POST['email']);
        $phone = trim((string)$_POST['phone']); $subject = trim((string)$_POST['subject']);
        $message = trim((string)$_POST['message']);
        if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$message) {
            $err = 'Заполните имя, корректный e-mail и сообщение';
        } else {
            getDB()->prepare("INSERT INTO contact_messages (name,email,phone,subject,message) VALUES (?,?,?,?,?)")
                ->execute([$name, $email, $phone ?: null, $subject ?: null, $message]);
            sendEmail(getSetting('order_email_admin','admin@avtozapchast.ru'),
                "Сообщение с сайта от {$name}",
                emailLayout('Новое сообщение с сайта',
                    "<p><strong>{$name}</strong> ({$email}, {$phone})</p><p><em>{$subject}</em></p><blockquote style='border-left:3px solid #C70909;padding-left:12px'>" . nl2br(htmlspecialchars($message)) . "</blockquote>"));
            $msg = 'Спасибо! Мы свяжемся с вами в ближайшее время.';
        }
    }
}
$pageTitle = t('contacts');
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div class="container">
  <h1><?= t('contacts') ?></h1>
  <nav class="breadcrumb"><a href="<?= APP_URL ?>/index.php"><?= t('home') ?></a><span class="sep">/</span><span class="current"><?= t('contacts') ?></span></nav>
</div></div>
<section class="section">
  <div class="container">
    <div class="checkout-grid">
      <div class="checkout-card">
        <h3>Связаться с нами</h3>
        <?php if ($err): ?><div class="alert alert-danger"><?= sanitize($err) ?></div><?php endif; ?>
        <?php if ($msg): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Имя *</label>
              <input type="text" name="name" class="form-input" required>
            </div>
            <div class="form-group">
              <label class="form-label">E-mail *</label>
              <input type="email" name="email" class="form-input" required>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Телефон</label>
              <input type="tel" name="phone" class="form-input">
            </div>
            <div class="form-group">
              <label class="form-label">Тема</label>
              <input type="text" name="subject" class="form-input">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Сообщение *</label>
            <textarea name="message" class="form-textarea" required rows="6"></textarea>
          </div>
          <button type="submit" class="btn btn-primary"><?= t('subscribe') ?? 'Отправить' ?>📨 Отправить</button>
        </form>
      </div>
      <div class="checkout-card">
        <h3>Контактные данные</h3>
        <table class="spec-table">
          <tr><th>Россия</th><td>+7 (800) 555-35-35<br>info@avtozapchast.ru<br>г. Москва, ул. Автомобильная, 1</td></tr>
          <tr><th>Таджикистан</th><td>+992 92 646-46-46<br>info@autodoc.tj<br>г. Душанбе, пр. Рудаки, 25</td></tr>
          <tr><th>Время работы</th><td>Пн–Пт: 9:00–20:00<br>Сб–Вс: 10:00–18:00</td></tr>
          <tr><th>Соцсети</th><td><a href="https://t.me/autodoc_tj">Telegram</a> · <a href="https://wa.me/79161234567">WhatsApp</a></td></tr>
        </table>
      </div>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
