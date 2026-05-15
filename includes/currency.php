<?php
/**
 * Currency helper
 * Base currency: RUB (all prices stored in RUB)
 */

$_CURRENCIES_CACHE = null;

function initCurrency(): string {
    $supported = getCurrencyCodes();
    // 1. URL param ?currency=XXX
    if (!empty($_GET['currency']) && in_array($_GET['currency'], $supported, true)) {
        $_SESSION['currency'] = $_GET['currency'];
    }
    // 2. Session
    if (!empty($_SESSION['currency']) && in_array($_SESSION['currency'], $supported, true)) {
        return $_SESSION['currency'];
    }
    // 3. DB default
    try {
        $db  = getDB();
        $row = $db->query("SELECT code FROM currencies WHERE is_default=1 AND is_active=1 LIMIT 1")->fetch();
        if ($row) { $_SESSION['currency'] = $row['code']; return $row['code']; }
    } catch (Exception $e) {}
    $_SESSION['currency'] = 'TJS';
    return 'TJS';
}

function getActiveCurrency(): string {
    return $_SESSION['currency'] ?? 'TJS';
}

function getCurrencies(): array {
    global $_CURRENCIES_CACHE;
    if ($_CURRENCIES_CACHE !== null) return $_CURRENCIES_CACHE;
    try {
        $lang = getLang();
        $nameCol = 'name_' . $lang;
        $db  = getDB();
        $res = $db->query("SELECT code, name_ru, name_tg, name_en, symbol, rate FROM currencies WHERE is_active=1 ORDER BY is_default DESC, code");
        $currencies = $res->fetchAll();
        $_CURRENCIES_CACHE = $currencies;
        return $currencies;
    } catch (Exception $e) {
        return [['code'=>'TJS','name_ru'=>'Таджикский сомони','name_tg'=>'Сомони','name_en'=>'Tajik Somoni','symbol'=>'СМН','rate'=>1]];
    }
}

function getCurrencyCodes(): array {
    return array_column(getCurrencies(), 'code');
}

function getCurrencyRate(string $code): float {
    foreach (getCurrencies() as $c) {
        if ($c['code'] === $code) return (float)$c['rate'];
    }
    return 1.0;
}

function getCurrencySymbol(?string $code = null): string {
    $code = $code ?? getActiveCurrency();
    foreach (getCurrencies() as $c) {
        if ($c['code'] === $code) return $c['symbol'];
    }
    return 'СМН';
}

/**
 * Convert price from RUB to active currency and format
 */
function formatPrice($priceRub, ?string $currency = null): string {
    $currency = $currency ?? getActiveCurrency();
    $rate     = getCurrencyRate($currency);
    $converted = (float)$priceRub * $rate;
    $symbol    = getCurrencySymbol($currency);
    if ($currency === 'RUB') {
        return number_format($converted, 0, ',', ' ') . ' ' . $symbol;
    }
    return $symbol . number_format($converted, 2, '.', ',');
}

/**
 * Convert price from RUB to active currency (raw float)
 */
function convertPrice($priceRub, ?string $currency = null): float {
    $currency = $currency ?? getActiveCurrency();
    return (float)$priceRub * getCurrencyRate($currency);
}

// Initialize on include
initCurrency();
