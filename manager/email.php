<?php
/**
 * «Письма клиентам» — отправка e-mail покупателю/продавцу из кабинета.
 *
 * Менеджер/админ пишет адрес (или выбирает из подсказки по нашим пользователям),
 * тему и текст — письмо уходит через SMTP (настройки в суперадминке) и попадает
 * в журнал email_log. Доступ — по праву 'email' (делегируется суперадмином).
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/mailer.php';
requireRole(['manager', 'admin', 'superadmin']);
requirePermission('email');

$db    = getDB();
$csrf  = generateCsrfToken();
$ready = mailerReady();
$uid   = (int)($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF ошибка.');
    } elseif (!$ready) {
        flashMessage('danger', 'Отправка почты не настроена. Обратитесь к суперадмину (Настройки → Почта).');
    } else {
        $to      = trim($_POST['to_email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $bodyTxt = trim($_POST['body'] ?? '');

        // Собираем вложения из формы. Лимиты держим скромными: почтовики режут
        // большие письма, а Timeweb SMTP имеет свой предел на размер сообщения.
        $attachments = [];
        $attachError = '';
        $totalSize   = 0;
        $MAX_TOTAL    = 12 * 1024 * 1024;   // суммарно до ~12 МБ (в base64 вырастет ~на треть)
        $BLOCKED_EXT  = ['php','phtml','exe','bat','cmd','sh','js','jar','msi','scr','com','vbs'];
        if (!empty($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
            foreach ($_FILES['attachments']['name'] as $i => $name) {
                if (($_FILES['attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                if (($_FILES['attachments']['error'][$i] ?? 1) !== UPLOAD_ERR_OK) { $attachError = 'Ошибка загрузки файла «' . sanitize($name) . '».'; break; }
                $tmp  = $_FILES['attachments']['tmp_name'][$i];
                $size = (int)($_FILES['attachments']['size'][$i] ?? 0);
                $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (in_array($ext, $BLOCKED_EXT, true)) { $attachError = 'Файл «' . sanitize($name) . '» такого типа отправлять нельзя.'; break; }
                $totalSize += $size;
                if ($totalSize > $MAX_TOTAL) { $attachError = 'Суммарный размер вложений больше 12 МБ.'; break; }
                $content = @file_get_contents($tmp);
                if ($content === false) { $attachError = 'Не удалось прочитать файл «' . sanitize($name) . '».'; break; }
                $type = function_exists('mime_content_type') ? (@mime_content_type($tmp) ?: 'application/octet-stream') : 'application/octet-stream';
                $attachments[] = ['name' => basename($name), 'type' => $type, 'content' => $content];
            }
        }

        if (!mailValidAddress($to)) {
            flashMessage('danger', 'Укажите корректный адрес получателя.');
        } elseif ($subject === '' || $bodyTxt === '') {
            flashMessage('danger', 'Заполните тему и текст письма.');
        } elseif ($attachError !== '') {
            flashMessage('danger', $attachError);
        } else {
            // Если адрес принадлежит нашему пользователю — привяжем письмо к нему.
            $toUserId = null;
            $toName   = '';
            try {
                $st = $db->prepare("SELECT id, username FROM users WHERE email = ? LIMIT 1");
                $st->execute([$to]);
                if ($u = $st->fetch()) { $toUserId = (int)$u['id']; $toName = (string)$u['username']; }
            } catch (Throwable $e) { /* не критично */ }

            // Текст письма → фирменный HTML-шаблон (шапка/подвал с брендом).
            $inner = nl2br(sanitize($bodyTxt));
            $html  = mailerWrap($inner);

            [$ok, $err] = sendEmailLogged($db, $to, $subject, $html, $bodyTxt, $toName, $toUserId, $uid, $attachments);
            $extra = $attachments ? ' (вложений: ' . count($attachments) . ')' : '';
            flashMessage($ok ? 'success' : 'danger',
                $ok ? 'Письмо отправлено на ' . sanitize($to) . '.' . $extra
                    : 'Не удалось отправить: ' . sanitize($err));
        }
    }
    redirect(APP_URL . '/manager/email.php');
}

// Подсказка получателей: наши пользователи с почтой (для datalist).
$recipients = [];
try {
    $recipients = $db->query(
        "SELECT username, email, role FROM users
          WHERE email IS NOT NULL AND email <> '' AND is_active = 1
       ORDER BY role, username LIMIT 500"
    )->fetchAll();
} catch (Throwable $e) { $recipients = []; }

// Журнал последних писем.
$log = [];
if (emailLogReady($db)) {
    $log = $db->query(
        "SELECT l.*, u.username AS sender FROM email_log l
           LEFT JOIN users u ON u.id = l.sent_by
       ORDER BY l.id DESC LIMIT 30"
    )->fetchAll();
}

$roleRu = ['buyer' => 'покупатель', 'seller' => 'продавец', 'manager' => 'менеджер',
           'admin' => 'админ', 'superadmin' => 'суперадмин'];

$pageTitle = 'Письма клиентам — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="az-panel">
  <?php renderRoleSidebar('email'); ?>

  <div class="az-main">
    <div class="az-topbar">
      <div class="az-topbar-title">Письма клиентам</div>
      <div class="az-topbar-user">
        <?= sanitize($_SESSION['username'] ?? '') ?> &middot;
        <a href="<?= APP_URL ?>/auth/logout.php">Выйти</a>
      </div>
    </div>

    <div class="az-content">
      <?php if ($flash = getFlashMessage()): ?>
      <div class="alert alert-<?= sanitize($flash['type']) ?> mb-16"><?= sanitize($flash['message']) ?></div>
      <?php endif; ?>

      <?php if (!$ready): ?>
      <div class="az-card mb-24"><div class="az-card-body">
        <p><b>Отправка почты пока не настроена.</b></p>
        <p style="color:#666;">Суперадмину нужно зайти в <b>Настройки → Почта (SMTP)</b>, ввести сервер,
        логин и пароль ящика, включить отправку и проверить кнопкой «Тест».
        <?php if (emailLogReady($db)): ?><?php else: ?><br>Также примените миграцию журнала:
        <code>php sql/migrate_email.php</code>.<?php endif; ?></p>
      </div></div>
      <?php endif; ?>

      <div class="az-card mb-24">
        <div class="az-card-header"><h4 class="az-card-title">Новое письмо</h4></div>
        <div class="az-card-body">
          <form method="post" action="" enctype="multipart/form-data" style="max-width:720px;">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

            <div class="az-form-group">
              <label>Кому (адрес почты)</label>
              <input type="email" name="to_email" class="form-control" list="recipientsList"
                     placeholder="email покупателя или продавца" required <?= $ready ? '' : 'disabled' ?>>
              <datalist id="recipientsList">
                <?php foreach ($recipients as $r): ?>
                <option value="<?= sanitize($r['email']) ?>"><?= sanitize($r['username']) ?> — <?= sanitize($roleRu[$r['role']] ?? $r['role']) ?></option>
                <?php endforeach; ?>
              </datalist>
              <small style="color:#888;display:block;">Начните вводить — появятся подсказки из наших пользователей. Можно вписать любой адрес.</small>
            </div>

            <div class="az-form-group">
              <label>Тема</label>
              <input type="text" name="subject" class="form-control" maxlength="200"
                     placeholder="Тема письма" required <?= $ready ? '' : 'disabled' ?>>
            </div>

            <div class="az-form-group">
              <label>Текст письма</label>
              <textarea name="body" class="form-control" rows="9"
                        placeholder="Здравствуйте! ..." required <?= $ready ? '' : 'disabled' ?>></textarea>
              <small style="color:#888;display:block;">Обычный текст. Переносы строк сохранятся. Письмо уйдёт в фирменном оформлении.</small>
            </div>

            <div class="az-form-group">
              <label>Вложения (файлы, фото) — необязательно</label>
              <input type="file" name="attachments[]" class="form-control" multiple <?= $ready ? '' : 'disabled' ?>>
              <small style="color:#888;display:block;">Можно прикрепить несколько файлов. Суммарно до 12 МБ. Нельзя: exe, php и подобные исполняемые файлы.</small>
            </div>

            <button type="submit" class="az-btn az-btn-primary" style="min-width:200px;" <?= $ready ? '' : 'disabled' ?>>
              Отправить письмо
            </button>
          </form>
        </div>
      </div>

      <div class="az-card"><div class="az-card-body p-0">
        <div class="az-card-header" style="padding:14px 16px;"><h4 class="az-card-title" style="margin:0;">Последние письма</h4></div>
        <div class="table-responsive">
          <table class="az-table">
            <thead>
              <tr><th>Когда</th><th>Кому</th><th>Тема</th><th>Статус</th><th>Отправил</th></tr>
            </thead>
            <tbody>
            <?php if (!$log): ?>
              <tr><td colspan="5" class="text-center text-muted" style="padding:22px;">Писем пока нет.</td></tr>
            <?php else: foreach ($log as $l): ?>
              <tr>
                <td style="white-space:nowrap;font-size:.82rem;"><?= date('d.m.Y H:i', strtotime($l['created_at'])) ?></td>
                <td style="font-size:.85rem;"><?= sanitize($l['to_email']) ?></td>
                <td style="font-size:.85rem;"><?= sanitize($l['subject']) ?></td>
                <td>
                  <?php if ($l['status'] === 'sent'): ?>
                    <span style="color:#1b5e20;">✅ отправлено</span>
                  <?php else: ?>
                    <span style="color:#b71c1c;" title="<?= sanitize($l['error'] ?? '') ?>">❌ ошибка</span>
                  <?php endif; ?>
                </td>
                <td style="font-size:.82rem;"><?= sanitize($l['sender'] ?? '—') ?></td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div></div>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
