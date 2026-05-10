<?php
/**
 * Shared backup routines used by both the superadmin UI (backup.php)
 * and the CLI cron job (backup_cron.php).
 */

if (!function_exists('backup_safe_name')) {
    function backup_safe_name(string $name): string {
        $name = basename($name);
        return preg_match('/^backup_[\w\-\.]+\.sql(\.gz)?$/', $name) ? $name : '';
    }
}

if (!function_exists('backup_create')) {
    function backup_create(PDO $db, string $dir): array {
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
            return ['ok' => false, 'error' => 'Не удалось создать директорию.'];
        }

        $stamp = date('Ymd_His');
        $file  = $dir . "/backup_{$stamp}.sql";
        $fp    = @fopen($file, 'w');
        if (!$fp) return ['ok' => false, 'error' => 'Не удалось создать файл бэкапа.'];

        fwrite($fp, "-- Avtozapchast backup\n");
        fwrite($fp, "-- Date: " . date('Y-m-d H:i:s') . "\n");
        fwrite($fp, "-- DB: " . (defined('DB_NAME') ? DB_NAME : '') . "\n\n");
        fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($fp, "SET NAMES utf8mb4;\n\n");

        $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $tbl = "`" . str_replace("`", "``", $table) . "`";

            fwrite($fp, "-- Table: {$table}\n");
            fwrite($fp, "DROP TABLE IF EXISTS {$tbl};\n");

            $create = $db->query("SHOW CREATE TABLE {$tbl}")->fetch(PDO::FETCH_NUM);
            fwrite($fp, $create[1] . ";\n\n");

            $stmt = $db->query("SELECT * FROM {$tbl}");
            $cols = null; $batch = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($cols === null) {
                    $cols = '`' . implode('`,`', array_keys($row)) . '`';
                }
                $vals = [];
                foreach ($row as $v) {
                    if ($v === null)            $vals[] = 'NULL';
                    elseif (is_int($v) || is_float($v)) $vals[] = $v;
                    elseif (is_numeric($v) && !preg_match('/^0\d/', (string)$v)) $vals[] = $v;
                    else                        $vals[] = $db->quote((string)$v);
                }
                $batch[] = '(' . implode(',', $vals) . ')';
                if (count($batch) >= 200) {
                    fwrite($fp, "INSERT INTO {$tbl} ({$cols}) VALUES\n" . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                fwrite($fp, "INSERT INTO {$tbl} ({$cols}) VALUES\n" . implode(",\n", $batch) . ";\n");
            }
            fwrite($fp, "\n");
        }

        fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fp);

        if (function_exists('gzopen') && filesize($file) > 0) {
            $gz = gzopen($file . '.gz', 'wb6');
            if ($gz) {
                $in = fopen($file, 'rb');
                while (!feof($in)) gzwrite($gz, fread($in, 65536));
                fclose($in);
                gzclose($gz);
                @unlink($file);
                $file .= '.gz';
            }
        }

        return ['ok' => true, 'file' => basename($file), 'size' => filesize($file), 'count' => count($tables)];
    }
}

if (!function_exists('backup_restore')) {
    function backup_restore(PDO $db, string $path): array {
        if (!is_file($path)) return ['ok' => false, 'error' => 'Файл не найден.'];

        if (substr($path, -3) === '.gz') {
            $sql = '';
            $gz = gzopen($path, 'rb');
            if (!$gz) return ['ok' => false, 'error' => 'Не удалось открыть архив.'];
            while (!gzeof($gz)) $sql .= gzread($gz, 65536);
            gzclose($gz);
        } else {
            $sql = file_get_contents($path);
        }
        if ($sql === false || $sql === '') return ['ok' => false, 'error' => 'Пустой файл бэкапа.'];

        $sql = preg_replace('/^\s*--[^\n]*$/m', '', $sql);
        $statements = preg_split('/;\s*\n/', $sql);

        $db->exec("SET FOREIGN_KEY_CHECKS=0");
        $executed = 0; $errors = 0; $lastError = '';
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || $stmt === ';') continue;
            try { $db->exec($stmt); $executed++; }
            catch (Exception $e) { $errors++; $lastError = $e->getMessage(); }
        }
        $db->exec("SET FOREIGN_KEY_CHECKS=1");

        return ['ok' => $errors === 0, 'executed' => $executed, 'errors' => $errors, 'lastError' => $lastError];
    }
}
