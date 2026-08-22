<?php
/**
 * Накатить SQL-миграцию, не вводя доступы к БД руками.
 *
 * Зачем: `mysql -u USER -p БАЗА < файл.sql` требует логина и пароля в командной
 * строке. На боевом сервере `~/.bash_history` сделан append-only — очистить его
 * нельзя, и пароль осел бы там навсегда. Этот скрипт берёт доступы оттуда же,
 * откуда их берёт сайт (`config/db_credentials.php`), поэтому в историю попадает
 * только имя файла миграции.
 *
 * Запуск:
 *   php sql/apply.php sql/marketplace_phase3_payouts.sql
 *
 * Повторный запуск безопасен: миграции проекта идемпотентны, а «уже существует»
 * (таблица/колонка/индекс) считается успехом, а не ошибкой — иначе накат,
 * прерванный на середине, нельзя было бы доиграть.
 *
 * Ограничение: файл режется на запросы по `;` в конце строки. Для миграций
 * проекта этого достаточно (в них нет ни процедур, ни точек с запятой внутри
 * строковых литералов). Для чего-то сложнее — обычный mysql-клиент.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Только из командной строки.\n"); }

require_once dirname(__DIR__) . '/config/database.php';

$file = $argv[1] ?? '';
if ($file === '') {
    exit("Укажите файл миграции:\n  php sql/apply.php sql/marketplace_phase3_payouts.sql\n");
}
// Относительный путь считаем от корня проекта, а не от текущей папки.
if (!is_file($file)) {
    $try = dirname(__DIR__) . '/' . ltrim($file, '/');
    if (is_file($try)) $file = $try;
}
if (!is_file($file)) exit("Файл не найден: $file\n");

$sql = file_get_contents($file);
if ($sql === false || trim($sql) === '') exit("Файл пуст: $file\n");

// Убираем строчные комментарии `--`, чтобы они не мешали резать на запросы.
$clean = preg_replace('/^\s*--.*$/m', '', $sql);

$statements = [];
foreach (preg_split('/;\s*[\r\n]+/', $clean) as $s) {
    $s = trim($s, " \t\r\n;");
    if ($s !== '') $statements[] = $s;
}
if (!$statements) exit("В файле нет ни одного запроса.\n");

// Коды «объект уже существует» — для идемпотентного наката это норма.
$alreadyThere = [
    '42S01' => 'таблица уже есть',
    '42S21' => 'колонка уже есть',
    '42000' => 'индекс/ключ уже есть',
];

echo "Миграция: " . basename($file) . "\n";
echo "База: " . DB_NAME . " (пользователь " . DB_USER . ")\n";
echo str_repeat('-', 60) . "\n";

$db = getDB();
$done = $skipped = 0;

foreach ($statements as $i => $stmt) {
    // Первые слова запроса — понятная подпись в выводе.
    $label = trim(preg_replace('/\s+/', ' ', mb_substr($stmt, 0, 70)));
    try {
        $db->exec($stmt);
        $done++;
        echo "  [OK]      $label…\n";
    } catch (PDOException $e) {
        $state = $e->getCode();
        $msg   = $e->getMessage();
        // «Уже существует» ловим и по SQLSTATE, и по тексту: MySQL отдаёт
        // duplicate column/key именно так, а не отдельным состоянием.
        $dup = isset($alreadyThere[$state])
            || stripos($msg, 'already exists') !== false
            || stripos($msg, 'Duplicate column') !== false
            || stripos($msg, 'Duplicate key') !== false;
        if ($dup) {
            $skipped++;
            echo "  [есть]    $label…\n";
        } else {
            echo "\n  [ОШИБКА]  $label…\n  $msg\n";
            echo "\nНакат остановлен на запросе #" . ($i + 1) . ". Уже применённое не откатывалось —\n";
            echo "исправьте причину и запустите скрипт снова: применится только недостающее.\n";
            exit(1);
        }
    }
}

echo str_repeat('-', 60) . "\n";
echo "Готово: применено $done, уже было $skipped.\n";
