<?php
/**
 * I18n + Currency core for АвтоЗапчасть.
 * Resolves the active language and currency from session/cookie/query,
 * loads dictionary, exposes t() and money() helpers.
 */

const I18N_COOKIE_LANG     = 'az_lang';
const I18N_COOKIE_CURRENCY = 'az_currency';
const I18N_DEFAULT_LANG    = 'ru';
const I18N_DEFAULT_CURR    = 'RUB';

function availableLanguages(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    try {
        $stmt = getDB()->query("SELECT * FROM languages WHERE is_active = 1 ORDER BY sort_order");
        $cache = $stmt->fetchAll();
    } catch (Throwable $e) {
        $cache = [['code'=>'ru','name'=>'Русский','is_default'=>1,'is_active'=>1,'sort_order'=>1]];
    }
    return $cache;
}

function availableCurrencies(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    try {
        $stmt = getDB()->query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY sort_order");
        $cache = $stmt->fetchAll();
    } catch (Throwable $e) {
        $cache = [['code'=>'RUB','symbol'=>'₽','name'=>'Российский рубль','rate'=>1,'is_default'=>1,'is_active'=>1,'sort_order'=>1]];
    }
    return $cache;
}

function setLanguage(string $code): void {
    foreach (availableLanguages() as $l) {
        if ($l['code'] === $code) {
            $_SESSION['lang'] = $code;
            setcookie(I18N_COOKIE_LANG, $code, time() + 60*60*24*365, '/');
            return;
        }
    }
}

function setCurrency(string $code): void {
    foreach (availableCurrencies() as $c) {
        if ($c['code'] === $code) {
            $_SESSION['currency'] = $code;
            setcookie(I18N_COOKIE_CURRENCY, $code, time() + 60*60*24*365, '/');
            return;
        }
    }
}

function currentLanguage(): string {
    if (!empty($_GET['lang'])) {
        setLanguage((string)$_GET['lang']);
    }
    if (!empty($_SESSION['lang'])) return $_SESSION['lang'];
    if (!empty($_COOKIE[I18N_COOKIE_LANG])) {
        $_SESSION['lang'] = $_COOKIE[I18N_COOKIE_LANG];
        return $_SESSION['lang'];
    }
    foreach (availableLanguages() as $l) {
        if ($l['is_default']) { $_SESSION['lang'] = $l['code']; return $l['code']; }
    }
    return I18N_DEFAULT_LANG;
}

function currentCurrency(): array {
    if (!empty($_GET['cur'])) {
        setCurrency((string)$_GET['cur']);
    }
    $code = $_SESSION['currency'] ?? $_COOKIE[I18N_COOKIE_CURRENCY] ?? null;
    foreach (availableCurrencies() as $c) {
        if ($code && $c['code'] === $code) return $c;
        if (!$code && $c['is_default']) return $c;
    }
    return ['code'=>I18N_DEFAULT_CURR,'symbol'=>'₽','rate'=>1,'name'=>'RUB'];
}

function loadTranslations(string $code): array {
    static $cache = [];
    if (isset($cache[$code])) return $cache[$code];
    $path = __DIR__ . '/lang/' . preg_replace('/[^a-z]/', '', $code) . '.php';
    if (!is_file($path)) $path = __DIR__ . '/lang/' . I18N_DEFAULT_LANG . '.php';
    $cache[$code] = is_file($path) ? require $path : [];
    return $cache[$code];
}

/**
 * Translate key. Optional :placeholder substitutions.
 */
function t(string $key, array $params = []): string {
    $dict = loadTranslations(currentLanguage());
    $val = $dict[$key] ?? $key;
    foreach ($params as $k => $v) {
        $val = str_replace(':' . $k, (string)$v, $val);
    }
    return $val;
}

/**
 * Format a price stored in base currency (RUB) into the active currency.
 */
function money($amount, ?array $cur = null): string {
    $cur ??= currentCurrency();
    $value = (float)$amount * (float)$cur['rate'];
    $decimals = $cur['code'] === 'USD' ? 2 : 0;
    $formatted = number_format($value, $decimals, ',', ' ');
    return $formatted . ' ' . $cur['symbol'];
}

/**
 * Convert amount in base RUB to active currency raw value.
 */
function convertPrice($amount, ?array $cur = null): float {
    $cur ??= currentCurrency();
    return round((float)$amount * (float)$cur['rate'], 2);
}

/**
 * Translate database entity field via translations table.
 * Falls back to default value if no translation present.
 */
function tField(string $entity, int $entityId, string $field, string $fallback): string {
    static $cache = [];
    $lang = currentLanguage();
    $key  = "{$lang}|{$entity}|{$entityId}|{$field}";
    if (isset($cache[$key])) return $cache[$key];
    if ($lang === I18N_DEFAULT_LANG) return $cache[$key] = $fallback;
    try {
        $stmt = getDB()->prepare(
            "SELECT value FROM translations WHERE lang_code = ? AND entity = ? AND entity_id = ? AND field = ? LIMIT 1"
        );
        $stmt->execute([$lang, $entity, $entityId, $field]);
        $val = $stmt->fetchColumn();
        return $cache[$key] = ($val !== false ? $val : $fallback);
    } catch (Throwable $e) {
        return $cache[$key] = $fallback;
    }
}
