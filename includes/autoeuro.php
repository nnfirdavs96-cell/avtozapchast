<?php
/**
 * AutoEuro API v2 client
 * Base URL: https://api.autoeuro.ru/api/v2/json/
 */
class AutoEuro
{
    private const BASE_URL = 'https://api.autoeuro.ru/api/v2/json';
    private const TIMEOUT  = 15;

    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    // ── Public API methods ───────────────────────────────────────────────────

    public function getBalance(): array
    {
        return $this->get('get_balance');
    }

    public function getDeliveries(): array
    {
        return $this->get('get_deliveries');
    }

    public function getWarehouses(?string $deliveryKey = null): array
    {
        $params = $deliveryKey ? ['delivery_key' => $deliveryKey] : [];
        return $this->get('get_warehouses', $params);
    }

    public function getPayers(): array
    {
        return $this->get('get_payers');
    }

    public function getBrands(): array
    {
        return $this->get('get_brands');
    }

    public function searchBrands(string $code): array
    {
        return $this->get('search_brands', ['code' => $code]);
    }

    /**
     * @param string $brand        Brand name (as returned by get_brands)
     * @param string $code         Part number / article
     * @param string $deliveryKey  Delivery method key
     * @param bool   $withCrosses  Include cross-references
     * @param bool   $withOffers   Include partner offers
     */
    public function searchItems(
        string $brand,
        string $code,
        string $deliveryKey,
        bool $withCrosses = true,
        bool $withOffers  = false
    ): array {
        return $this->get('search_items', [
            'brand'        => $brand,
            'code'         => $code,
            'delivery_key' => $deliveryKey,
            'with_crosses' => $withCrosses ? 1 : 0,
            'with_offers'  => $withOffers  ? 1 : 0,
        ]);
    }

    /**
     * @param string $deliveryKey
     * @param string $payerKey
     * @param array  $items       [['offer_key'=>'...','quantity'=>1,'price'=>0,'comment'=>''], ...]
     * @param bool   $waitAll     Wait for all goods before shipping
     * @param string $comment
     * @param string $deliveryDate YYYY-MM-DD
     */
    public function createOrder(
        string $deliveryKey,
        string $payerKey,
        array  $items,
        bool   $waitAll      = true,
        string $comment      = '',
        string $deliveryDate = ''
    ): array {
        $body = [
            'delivery_key'    => $deliveryKey,
            'payer_key'       => $payerKey,
            'stock_items'     => $items,
            'wait_all_goods'  => $waitAll ? 1 : 0,
        ];
        if ($comment !== '')      $body['comment']       = $comment;
        if ($deliveryDate !== '') $body['delivery_date'] = $deliveryDate;

        return $this->post('create_order', $body);
    }

    /**
     * @param array|null $orderIds   Simple array of order IDs for status check
     * @param array|null $filters    ['from'=>'YYYY-MM-DD','to'=>'YYYY-MM-DD','delivery_key'=>...,'payer_key'=>...]
     */
    public function getOrders(?array $orderIds = null, ?array $filters = null): array
    {
        $body = [];
        if ($orderIds !== null) $body['orders']  = $orderIds;
        if ($filters  !== null) $body['filters'] = $filters;
        return $this->post('get_orders', $body ?: null);
    }

    public function getStatuses(): array
    {
        return $this->get('get_statuses');
    }

    // ── HTTP helpers ─────────────────────────────────────────────────────────

    private function get(string $action, array $params = []): array
    {
        $url = self::BASE_URL . '/' . $action . '/' . rawurlencode($this->apiKey) . '/';
        if ($params) {
            $url .= '?' . http_build_query($params);
        }
        return $this->request('GET', $url);
    }

    private function post(string $action, ?array $body = null): array
    {
        $url = self::BASE_URL . '/' . $action . '/';
        return $this->request('POST', $url, $body);
    }

    private function request(string $method, string $url, ?array $body = null): array
    {
        if (!function_exists('curl_init')) {
            return ['error' => 'cURL не установлен на сервере.'];
        }

        $ch = curl_init();
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'key: ' . $this->apiKey,
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => $headers,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
            }
        }

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return ['error' => 'Ошибка соединения: ' . $curlErr];
        }
        if ($response === false || $response === '') {
            return ['error' => 'Пустой ответ от API (HTTP ' . $httpCode . ')'];
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Некорректный JSON от API', 'raw' => mb_substr($response, 0, 500)];
        }

        if (isset($data['ERROR'])) {
            return [
                'error'      => $data['ERROR']['message'] ?? 'Ошибка API',
                'error_code' => $data['ERROR']['code'] ?? $httpCode,
            ];
        }

        return $data['DATA'] ?? $data;
    }

    // ── Factory ──────────────────────────────────────────────────────────────

    /**
     * Create instance from site_settings, or return null if disabled/unconfigured.
     */
    public static function fromSettings(): ?self
    {
        if (getSetting('autoeuro_enabled') !== '1') return null;
        $key = getSetting('autoeuro_api_key');
        if (!$key) return null;
        return new self($key);
    }
}
