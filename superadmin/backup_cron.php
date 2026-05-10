<?php
/**
 * Cron-режим. Создаёт SQL-бэкап и удаляет копии старше N дней.
 * Запуск: php superadmin/backup_cron.php [retention_days=14]
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }

require_once dirname(__DIR__) . '/config/database.php';
define('APP_ROOT', dirname(__DIR__));

$retention = (int)($argv[1] ?? 14);
$dir       = APP_ROOT . '/storage/backups';
if (!is_dir($dir)) mkdir($dir, 0750, true);

$db = getDB();

// Reuse the same routine as the web UI by including its function definitions.
require_once __DIR__ . '/_backup_lib.php';

$res = backup_create($db, $dir);
if (!$res['ok']) {
    fwrite(STDERR, "Backup failed: {$res['error']}\n");
    exit(1);
}
echo "[" . date('c') . "] Created {$res['file']} ({$res['count']} tables, " . number_format($res['size']/1024, 1) . " KB)\n";

// Retention
$cutoff = time() - $retention * 86400;
$removed = 0;
foreach (glob($dir . '/backup_*.sql*') ?: [] as $f) {
    if (filemtime($f) < $cutoff) {
        if (@unlink($f)) $removed++;
    }
}
echo "Removed old backups: {$removed} (retention={$retention}d)\n";
