<?php
/**
 * Приём подтверждений оплаты от банка (Фаза 3b).
 *
 * Эндпоинт открытый — банк не умеет входить под нашей сессией. Поэтому вся защита
 * держится на трёх вещах, и все три реализованы здесь, а не в адаптере:
 *
 *   1. ПОДПИСЬ. Проверяет адаптер банка (`handleCallback`), потому что схема подписи
 *      у каждого своя. Без неё любой смог бы объявить чужой заказ оплаченным.
 *   2. ИДЕМПОТЕНТНОСТЬ. Банк присылает подтверждение по нескольку раз — это норма.
 *      Обеспечена UNIQUE(provider, external_id) в журнале и проверкой уже
 *      оплаченного заказа в `paymentMarkPaid()`.
 *   3. СВЕРКА СУММЫ. Делается в `paymentMarkPaid()`, единожды на все адаптеры:
 *      если бы каждый проверял сам, однажды какой-нибудь забыл бы.
 *
 * Отвечаем всегда коротким текстом и осмысленным кодом: банки считают ответ 200
 * подтверждением доставки и перестают повторять попытки.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/payments/gateway.php';

header('Content-Type: text/plain; charset=utf-8');

// Тело читаем сырым: подпись почти всегда считается по нему, а не по разобранным
// полям — любая нормализация её сломает.
$rawBody = file_get_contents('php://input') ?: '';
$request = array_merge($_GET, $_POST);

// Провайдера берём из запроса, но только из известного списка: имя попадает в
// журнал и выбирает код обработки.
$providerId = (string)($request['provider'] ?? getSetting('payment_provider', 'manual'));
if (!isset(paymentProviders()[$providerId])) {
    http_response_code(400);
    exit("unknown provider\n");
}
$provider = paymentProvider($providerId);

if (!paymentsReady()) {
    http_response_code(503);
    exit("payments not configured\n");
}

$res = $provider->handleCallback($request, $rawBody);

if (empty($res['ok'])) {
    // Причину пишем в журнал, а наружу не отдаём: подробности отказа помогают
    // подбирать подпись тому, кто её подделывает.
    $oid = (int)($res['order_id'] ?? 0);
    if ($oid > 0) {
        paymentLog(getDB(), $oid, $providerId, 'failed', (float)($res['amount'] ?? 0),
                   $res['external_id'] ?? null, $rawBody, (string)($res['error'] ?? 'отклонено'));
    }
    http_response_code(400);
    exit("rejected\n");
}

$orderId = (int)($res['order_id'] ?? 0);
$amount  = (float)($res['amount'] ?? 0);
$extId   = $res['external_id'] ?? null;
$status  = (string)($res['status'] ?? 'pending');

if ($status === 'succeeded') {
    [$ok, $msg] = paymentMarkPaid(getDB(), $orderId, $amount, $providerId, $extId, $rawBody);
    // 200 даже при несовпадении суммы: платёж ДОСТАВЛЕН, повторять его не надо,
    // а расхождение уже записано в журнал и разбирается вручную.
    exit(($ok ? "ok\n" : "logged\n"));
}

paymentLog(getDB(), $orderId, $providerId, $status === 'failed' ? 'failed' : 'pending',
           $amount, $extId, $rawBody);
exit("ok\n");
