<?php
/**
 * Lightweight email sender using PHP mail().
 * In production swap for PHPMailer/SMTP — interface stays the same.
 */

function getSetting(string $key, ?string $default = null): ?string {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $rows = getDB()->query("SELECT `key`, `value` FROM site_settings")->fetchAll();
            foreach ($rows as $r) $cache[$r['key']] = $r['value'];
        } catch (Throwable $e) { /* ignore */ }
    }
    return $cache[$key] ?? $default;
}

function sendEmail(string $to, string $subject, string $htmlBody, array $opts = []): bool {
    $fromName  = $opts['from_name']  ?? getSetting('smtp_from_name', 'АвтоЗапчасть');
    $fromEmail = $opts['from_email'] ?? getSetting('smtp_from_email', 'no-reply@avtozapchast.ru');

    $headers   = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: =?UTF-8?B?' . base64_encode($fromName) . "?= <{$fromEmail}>";
    $headers[] = 'Reply-To: ' . $fromEmail;
    $headers[] = 'X-Mailer: PHP/' . phpversion();

    $subjEncoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    if (!empty($opts['log_only']) || !function_exists('mail')) {
        // dev fallback: write to log
        $logFile = APP_ROOT . '/assets/uploads/email.log';
        @file_put_contents($logFile,
            "==== " . date('Y-m-d H:i:s') . " ====\nTo: {$to}\nSubject: {$subject}\n\n{$htmlBody}\n\n",
            FILE_APPEND);
        return true;
    }

    return @mail($to, $subjEncoded, $htmlBody, implode("\r\n", $headers));
}

function emailLayout(string $title, string $bodyHtml): string {
    return "<!doctype html>
<html><head><meta charset='utf-8'><title>" . htmlspecialchars($title) . "</title></head>
<body style='font-family:Arial,Helvetica,sans-serif;background:#f6fafb;margin:0;padding:32px'>
  <div style='max-width:600px;margin:0 auto;background:#fff;border:1px solid #eee;border-radius:8px;overflow:hidden'>
    <div style='background:#222;padding:20px 28px'>
      <span style='color:#fff;font-size:22px;font-weight:700;letter-spacing:1px'>АВТО<span style='color:#C70909'>ЗАПЧАСТЬ</span></span>
    </div>
    <div style='padding:28px'>
      <h2 style='margin-top:0;color:#222;font-size:20px'>" . htmlspecialchars($title) . "</h2>
      {$bodyHtml}
    </div>
    <div style='padding:18px 28px;background:#fafafa;color:#888;font-size:12px;border-top:1px solid #eee'>
      © " . date('Y') . " АвтоЗапчасть. Все права защищены.
    </div>
  </div>
</body></html>";
}
