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
                  ?string $textBody = null, string $toName = ''): array
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
    $boundary   = 'bnd_' . bin2hex(random_bytes(12));

    $headers  = 'Date: ' . date('r') . "\r\n";
    $headers .= 'From: ' . $fromHeader . "\r\n";
    $headers .= 'To: ' . $toHeader . "\r\n";
    $headers .= 'Reply-To: ' . $fromHeader . "\r\n";
    $headers .= 'Subject: ' . $enc($subject) . "\r\n";
    $headers .= 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $host . ">\r\n";
    $headers .= 'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . "\r\n";

    $b64 = fn(string $t): string => chunk_split(base64_encode($t));
    $body  = '--' . $boundary . "\r\n";
    $body .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
    $body .= 'Content-Transfer-Encoding: base64' . "\r\n\r\n";
    $body .= $b64($textBody) . "\r\n";
    $body .= '--' . $boundary . "\r\n";
    $body .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
    $body .= 'Content-Transfer-Encoding: base64' . "\r\n\r\n";
    $body .= $b64($htmlBody) . "\r\n";
    $body .= '--' . $boundary . '--' . "\r\n";

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
                         ?int $toUserId = null, ?int $sentBy = null): array
{
    [$ok, $err] = smtpSend($toEmail, $subject, $htmlBody, $textBody, $toName);
    emailLog($db, $toEmail, $toUserId, $subject, $htmlBody, $ok, $err, $sentBy);
    return [$ok, $err];
}
