<?php
/**
 * Отправка e-mail через SMTP (без внешних библиотек).
 *
 * Почему свой мини-клиент, а не PHPMailer: проект без Composer и фреймворка,
 * тянуть большую зависимость ради письма — лишнее. Здесь ровно то, что нужно:
 * SSL/TLS, AUTH LOGIN, UTF-8 тема и тело, plain+html. Настройки (в т.ч. пароль)
 * живут в site_settings — их вводит владелец в суперадминке, в git они не
 * попадают.
 *
 * Порт 465 = SSL сразу (ssl://), порт 587 = обычное подключение + STARTTLS.
 */

/** Настройки почты из site_settings. */
function mailerSettings(): array
{
    $user = getSetting('smtp_user', '');
    return [
        'enabled'    => getSetting('mail_enabled', '0') === '1',
        'host'       => getSetting('smtp_host', ''),
        'port'       => (int)(getSetting('smtp_port', '465') ?: 465),
        'secure'     => getSetting('smtp_secure', 'ssl'),   // ssl | tls | none
        'user'       => $user,
        'pass'       => getSetting('smtp_pass', ''),
        'from_email' => getSetting('smtp_from_email', '') ?: $user,
        'from_name'  => getSetting('smtp_from_name', '') ?: getSetting('site_name', 'AutoDoc'),
    ];
}

/** Готова ли отправка: включена и заполнены обязательные поля. */
function mailerReady(): bool
{
    $s = mailerSettings();
    return $s['enabled'] && $s['host'] !== '' && $s['user'] !== '' && $s['pass'] !== '' && $s['from_email'] !== '';
}

/** Простейшая валидация адреса. */
function mailValidAddress(string $email): bool
{
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Отправить письмо. Возвращает [ok(bool), error(string)].
 *
 * $htmlBody — HTML-версия; $textBody — текстовая (если пусто, соберём из HTML).
 * Ошибку возвращаем текстом, чтобы показать в форме и записать в журнал.
 */
function smtpSend(string $toEmail, string $subject, string $htmlBody,
                  ?string $textBody = null, string $toName = '', array $attachments = []): array
{
    $s = mailerSettings();
    if (!$s['enabled'])                return [false, 'Отправка почты выключена в настройках.'];
    if ($s['host'] === '' || $s['user'] === '' || $s['pass'] === '')
        return [false, 'Не заполнены настройки SMTP (сервер/логин/пароль).'];
    if (!mailValidAddress($toEmail))   return [false, 'Некорректный адрес получателя.'];
    if (!mailValidAddress($s['from_email']))
        return [false, 'Некорректный адрес отправителя в настройках.'];

    if ($textBody === null || $textBody === '') {
        // Грубая текстовая версия из HTML — на случай почтовиков без HTML.
        $textBody = trim(html_entity_decode(strip_tags(str_replace(
            ['</p>', '<br>', '<br/>', '<br />'], "\n", $htmlBody
        )), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    $secure  = strtolower($s['secure']);
    $host    = $s['host'];
    $port    = $s['port'];
    // Для SSL (465) оборачиваем поток сразу; для TLS/none — обычный TCP, TLS поднимем STARTTLS.
    $remote  = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;

    $ctx = stream_context_create(['ssl' => [
        'verify_peer'       => true,
        'verify_peer_name'  => true,
        'SNI_enabled'       => true,
        'peer_name'         => $host,
    ]]);

    $errno = 0; $errstr = '';
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) return [false, 'Не удалось подключиться к SMTP: ' . ($errstr ?: ('код ' . $errno))];
    stream_set_timeout($fp, 15);

    // Помощники чтения/записи. Читаем многострочные ответы: «250-...» продолжение, «250 ...» конец.
    $read = function () use ($fp): array {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            // 4-й символ '-' => есть продолжение; ' ' => последняя строка.
            if (strlen($line) < 4 || $line[3] !== '-') break;
        }
        $code = (int)substr(ltrim($data), 0, 3);
        return [$code, $data];
    };
    $write = function (string $cmd) use ($fp): void { fwrite($fp, $cmd . "\r\n"); };

    $fail = function (string $msg) use ($fp): array {
        @fwrite($fp, "QUIT\r\n"); @fclose($fp);
        return [false, $msg];
    };

    [$code] = $read();                                   // приветствие 220
    if ($code !== 220) return $fail('SMTP не поздоровался (код ' . $code . ').');

    $ehloHost = preg_replace('/[^a-zA-Z0-9.\-]/', '', (string)($_SERVER['SERVER_NAME'] ?? 'localhost')) ?: 'localhost';
    $write('EHLO ' . $ehloHost); [$code] = $read();
    if ($code !== 250) return $fail('EHLO отклонён (код ' . $code . ').');

    if ($secure === 'tls') {
        $write('STARTTLS'); [$code] = $read();
        if ($code !== 220) return $fail('STARTTLS отклонён (код ' . $code . ').');
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT
                | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
            return $fail('Не удалось установить TLS-шифрование.');
        }
        $write('EHLO ' . $ehloHost); [$code] = $read();  // повторный EHLO уже в TLS
        if ($code !== 250) return $fail('EHLO после STARTTLS отклонён (код ' . $code . ').');
    }

    // Авторизация AUTH LOGIN (логин и пароль — base64, каждый отдельной строкой).
    $write('AUTH LOGIN'); [$code] = $read();
    if ($code !== 334) return $fail('Сервер не принял AUTH LOGIN (код ' . $code . ').');
    $write(base64_encode($s['user'])); [$code] = $read();
    if ($code !== 334) return $fail('Логин отклонён (код ' . $code . ').');
    $write(base64_encode($s['pass'])); [$code, $resp] = $read();
    if ($code !== 235) return $fail('Не удалось авторизоваться на почте (проверьте логин/пароль). Ответ: ' . trim($resp));

    $write('MAIL FROM:<' . $s['from_email'] . '>'); [$code] = $read();
    if ($code !== 250) return $fail('MAIL FROM отклонён (код ' . $code . ').');
    $write('RCPT TO:<' . $toEmail . '>'); [$code, $resp] = $read();
    if ($code !== 250 && $code !== 251) return $fail('Получатель отклонён (код ' . $code . '): ' . trim($resp));

    $write('DATA'); [$code] = $read();
    if ($code !== 354) return $fail('DATA отклонён (код ' . $code . ').');

    // Заголовки и тело. Тему и имена кодируем по RFC 2047 (UTF-8, base64).
    $enc = fn(string $t): string => '=?UTF-8?B?' . base64_encode($t) . '?=';
    $fromHeader = $s['from_name'] !== '' ? $enc($s['from_name']) . ' <' . $s['from_email'] . '>' : '<' . $s['from_email'] . '>';
    $toHeader   = $toName !== '' ? $enc($toName) . ' <' . $toEmail . '>' : '<' . $toEmail . '>';
    $b64 = fn(string $t): string => chunk_split(base64_encode($t));

    // Текст+HTML всегда лежат в multipart/alternative. Если есть вложения — эту
    // «альтернативу» кладём внутрь multipart/mixed, а рядом — файлы. Так письмо
    // и читается как обычно, и несёт вложения.
    $altBnd = 'alt_' . bin2hex(random_bytes(10));
    $alt  = '--' . $altBnd . "\r\n";
    $alt .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
    $alt .= 'Content-Transfer-Encoding: base64' . "\r\n\r\n";
    $alt .= $b64($textBody) . "\r\n";
    $alt .= '--' . $altBnd . "\r\n";
    $alt .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
    $alt .= 'Content-Transfer-Encoding: base64' . "\r\n\r\n";
    $alt .= $b64($htmlBody) . "\r\n";
    $alt .= '--' . $altBnd . '--' . "\r\n";

    $headers  = 'Date: ' . date('r') . "\r\n";
    $headers .= 'From: ' . $fromHeader . "\r\n";
    $headers .= 'To: ' . $toHeader . "\r\n";
    $headers .= 'Reply-To: ' . $fromHeader . "\r\n";
    $headers .= 'Subject: ' . $enc($subject) . "\r\n";
    $headers .= 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $host . ">\r\n";

    if (empty($attachments)) {
        $headers .= 'Content-Type: multipart/alternative; boundary="' . $altBnd . '"' . "\r\n";
        $body = $alt;
    } else {
        $mixBnd = 'mix_' . bin2hex(random_bytes(10));
        $headers .= 'Content-Type: multipart/mixed; boundary="' . $mixBnd . '"' . "\r\n";
        $body  = '--' . $mixBnd . "\r\n";
        $body .= 'Content-Type: multipart/alternative; boundary="' . $altBnd . '"' . "\r\n\r\n";
        $body .= $alt . "\r\n";
        foreach ($attachments as $att) {
            $name = (string)($att['name'] ?? 'file');
            $type = (string)($att['type'] ?? 'application/octet-stream') ?: 'application/octet-stream';
            $content = (string)($att['content'] ?? '');
            if ($content === '') continue;
            $body .= '--' . $mixBnd . "\r\n";
            $body .= 'Content-Type: ' . $type . '; name="' . $enc($name) . '"' . "\r\n";
            $body .= 'Content-Transfer-Encoding: base64' . "\r\n";
            $body .= 'Content-Disposition: attachment; filename="' . $enc($name) . '"' . "\r\n\r\n";
            $body .= $b64($content) . "\r\n";
        }
        $body .= '--' . $mixBnd . '--' . "\r\n";
    }

    // Dot-stuffing: строки, начинающиеся с точки, экранируем — иначе «.» в начале
    // строки оборвёт передачу данных.
    $message = $headers . "\r\n" . $body;
    $message = preg_replace('/^\./m', '..', $message);

    fwrite($fp, $message . "\r\n.\r\n");
    [$code, $resp] = $read();
    if ($code !== 250) return $fail('Сервер не принял письмо (код ' . $code . '): ' . trim($resp));

    $write('QUIT'); @fclose($fp);
    return [true, ''];
}

/** Есть ли таблица журнала писем. */
function emailLogReady(?PDO $db = null): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        ($db ?: getDB())->query("SELECT 1 FROM email_log LIMIT 1")->fetchAll();
        $ready = true;
    } catch (Throwable $e) { $ready = false; }
    return $ready;
}

/** Записать факт отправки (успех или ошибку) в журнал. */
function emailLog(PDO $db, string $toEmail, ?int $toUserId, string $subject,
                  string $body, bool $ok, string $error, ?int $sentBy): void
{
    if (!emailLogReady($db)) return;
    try {
        $st = $db->prepare(
            "INSERT INTO email_log (to_email, to_user_id, subject, body, status, error, sent_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $st->execute([
            mb_substr($toEmail, 0, 190),
            $toUserId ?: null,
            mb_substr($subject, 0, 255),
            mb_substr($body, 0, 60000),
            $ok ? 'sent' : 'failed',
            mb_substr($error, 0, 500),
            $sentBy ?: null,
        ]);
    } catch (Throwable $e) { /* журнал не должен ронять отправку */ }
}

/**
 * Отправить и сразу записать в журнал. Возвращает [ok, error].
 * Единая точка для страниц: и письмо ушло, и след остался.
 */
function sendEmailLogged(PDO $db, string $toEmail, string $subject, string $htmlBody,
                         ?string $textBody = null, string $toName = '',
                         ?int $toUserId = null, ?int $sentBy = null,
                         array $attachments = []): array
{
    [$ok, $err] = smtpSend($toEmail, $subject, $htmlBody, $textBody, $toName, $attachments);
    emailLog($db, $toEmail, $toUserId, $subject, $htmlBody, $ok, $err, $sentBy);
    return [$ok, $err];
}

/**
 * Фирменная HTML-обёртка письма: шапка с названием магазина, тело, подвал с
 * контактами. Вёрстка табличная и стили строго инлайновые — почтовики (особенно
 * Gmail) вырезают <style> и внешний CSS, поэтому «красиво» делается только так.
 *
 * $innerHtml — уже готовый HTML содержимого (абзацы, кнопки и т.п.).
 * $heading   — крупный заголовок над телом (необязательно).
 */
function mailerWrap(string $innerHtml, string $heading = ''): string
{
    $brand   = getSetting('site_name', 'AutoDoc');
    $url     = defined('APP_URL') ? APP_URL : '';
    $email   = getSetting('site_email', '');
    $phone   = getSetting('site_phone', '');
    $accent  = '#C70909';
    $year    = date('Y');

    $safeBrand = htmlspecialchars($brand, ENT_QUOTES, 'UTF-8');
    $headingHtml = $heading !== ''
        ? '<h1 style="margin:0 0 18px;font-size:20px;line-height:1.3;color:#1a1a1a;font-weight:700;">'
          . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . '</h1>'
        : '';

    $contacts = [];
    if ($phone !== '') $contacts[] = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
    if ($email !== '') $contacts[] = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $contactsLine = $contacts ? implode(' &middot; ', $contacts) : '';

    return
'<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f2f3f5;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f3f5;padding:24px 12px;">
<tr><td align="center">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;box-shadow:0 2px 10px rgba(20,22,30,.06);">
    <tr><td style="background:' . $accent . ';padding:20px 28px;">
      <span style="font-size:22px;font-weight:800;color:#ffffff;letter-spacing:.3px;">' . $safeBrand . '</span>
    </td></tr>
    <tr><td style="padding:28px;color:#333;font-size:15px;line-height:1.65;">
      ' . $headingHtml . '
      <div style="color:#333;font-size:15px;line-height:1.65;">' . $innerHtml . '</div>
    </td></tr>
    <tr><td style="padding:18px 28px;background:#fafafa;border-top:1px solid #eee;color:#8a8f98;font-size:12px;line-height:1.6;">
      ' . ($contactsLine !== '' ? $contactsLine . '<br>' : '') . '
      ' . ($url !== '' ? '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" style="color:' . $accent . ';text-decoration:none;">' . htmlspecialchars(preg_replace('#^https?://#', '', $url), ENT_QUOTES, 'UTF-8') . '</a><br>' : '') . '
      <span style="color:#b0b4bb;">&copy; ' . $year . ' ' . $safeBrand . '</span>
    </td></tr>
  </table>
</td></tr>
</table>
</body></html>';
}

/**
 * Отправить письмо нашему пользователю по его id (найдём email и имя сами).
 * Тело оборачиваем в фирменный шаблон. Тихо выходит, если почта не настроена
 * или у пользователя нет email — событийные письма не должны ронять основную
 * операцию (регистрацию, одобрение и т.п.).
 */
function notifyUser(PDO $db, int $userId, string $subject, string $innerHtml,
                    string $heading = '', ?int $sentBy = null): bool
{
    if (!mailerReady()) return false;
    try {
        $st = $db->prepare("SELECT email, username FROM users WHERE id = ? LIMIT 1");
        $st->execute([$userId]);
        $u = $st->fetch();
    } catch (Throwable $e) { return false; }
    if (!$u || empty($u['email']) || !mailValidAddress($u['email'])) return false;

    $html = mailerWrap($innerHtml, $heading);
    [$ok] = sendEmailLogged($db, $u['email'], $subject, $html, null,
                            (string)($u['username'] ?? ''), $userId, $sentBy);
    return $ok;
}
