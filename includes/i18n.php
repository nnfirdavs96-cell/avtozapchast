<?php
/**
 * i18n — Internationalization helper
 * Supported: ru (default), tg, en
 */

$_SUPPORTED_LANGS = ['ru', 'tg', 'en'];
$_TRANSLATIONS    = [];

// Determine active language
function initLang(): string {
    global $_SUPPORTED_LANGS;
    // 1. URL param ?lang=xx (sets session)
    if (!empty($_GET['lang']) && in_array($_GET['lang'], $_SUPPORTED_LANGS, true)) {
        $_SESSION['lang'] = $_GET['lang'];
    }
    // 2. Session
    if (!empty($_SESSION['lang']) && in_array($_SESSION['lang'], $_SUPPORTED_LANGS, true)) {
        return $_SESSION['lang'];
    }
    // 3. DB default
    try {
        $db = getDB();
        $row = $db->query("SELECT value FROM site_settings WHERE `key`='default_lang' LIMIT 1")->fetch();
        if ($row && in_array($row['value'], $_SUPPORTED_LANGS, true)) {
            $_SESSION['lang'] = $row['value'];
            return $row['value'];
        }
    } catch (Exception $e) {}
    $_SESSION['lang'] = 'ru';
    return 'ru';
}

function getLang(): string {
    return $_SESSION['lang'] ?? 'ru';
}

function loadTranslations(string $lang): array {
    global $_TRANSLATIONS;
    if (!empty($_TRANSLATIONS[$lang])) return $_TRANSLATIONS[$lang];
    $file = APP_ROOT . '/lang/' . $lang . '.php';
    if (!file_exists($file)) $file = APP_ROOT . '/lang/ru.php';
    $_TRANSLATIONS[$lang] = require $file;
    return $_TRANSLATIONS[$lang];
}

/**
 * Translate a key
 */
function t(string $key, array $params = []): string {
    global $_TRANSLATIONS;
    $lang = getLang();
    if (empty($_TRANSLATIONS[$lang])) loadTranslations($lang);
    $str = $_TRANSLATIONS[$lang][$key] ?? $_TRANSLATIONS['ru'][$key] ?? $key;
    foreach ($params as $k => $v) {
        $str = str_replace(':' . $k, $v, $str);
    }
    return $str;
}

/**
 * Get translated field from a DB row
 * Looks for field_{lang} column, falls back to field (which is Russian)
 */
function tField(array $row, string $field, ?string $lang = null): string {
    $lang = $lang ?? getLang();
    $col  = $field . '_' . $lang;
    if (!empty($row[$col])) return $row[$col];
    if (!empty($row[$field])) return $row[$field];
    // fallback: Russian base column
    $ruCol = $field . '_ru';
    return $row[$ruCol] ?? $row[$field] ?? '';
}

// Initialize on include
initLang();
loadTranslations(getLang());
// Also preload Russian as fallback
if (getLang() !== 'ru') loadTranslations('ru');
