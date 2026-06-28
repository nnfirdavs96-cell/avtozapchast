<?php
require_once dirname(__DIR__) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');

// Корзина работает и для гостя (сессия), и для авторизованного (БД) — через cart_lib.
$db = getDB();

// ── GET ───────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'count';

    // GET remove (ссылка из мини-корзины с CSRF в query)
    if ($action === 'remove') {
        header('Content-Type: text/html'); // редирект-ответ
        if (!verifyCsrfToken($_GET['_csrf'] ?? '')) {
            flashMessage('danger', 'CSRF ошибка.');
            redirect($_SERVER['HTTP_REFERER'] ?? APP_URL . '/buyer/cart.php');
        }
        cartRemove($db, (int)($_GET['part_id'] ?? 0));
        redirect($_SERVER['HTTP_REFERER'] ?? APP_URL . '/buyer/cart.php');
    }

    // mini — cart_count + cart_total_html + items HTML для живого обновления
    if ($action === 'mini') {
        $rows = cartDetailedItems($db);
        $csrf = generateCsrfToken();
        ob_start();
        if (empty($rows)) {
            echo '<p style="padding:16px;color:#888;text-align:center">' . t('cart_empty') . '</p>';
        } else {
            foreach ($rows as $row) {
                $img = productImageUrl($row['images']);
                $url = partUrl((int)$row['id'], $row['name'] ?? '');
                echo '<div class="cart_item">'
                   . '<div class="cart_img"><a href="' . $url . '"><img src="' . sanitize($img) . '" alt="' . sanitize($row['name']) . '" style="width:60px;height:60px;object-fit:cover"></a></div>'
                   . '<div class="cart_info"><a href="' . $url . '">' . sanitize(truncate($row['name'], 40)) . '</a>'
                   . '<p>' . t('quantity') . ': ' . (int)$row['quantity'] . ' &times; <span>' . formatPrice($row['price']) . '</span></p></div>'
                   . '<div class="cart_remove"><a href="' . APP_URL . '/api/cart.php?action=remove&part_id=' . (int)$row['id'] . '&_csrf=' . $csrf . '"><i class="ion-android-close"></i></a></div>'
                   . '</div>';
            }
        }
        $itemsHtml = ob_get_clean();
        echo json_encode([
            'cart_count'     => cartCountAny($db),
            'cart_total_html'=> formatPrice(cartTotalAny($db)),
            'items_html'     => $itemsHtml,
        ]);
        exit;
    }

    echo json_encode(['cart_count' => cartCountAny($db)]);
    exit;
}

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$rawBody = file_get_contents('php://input');
$data    = json_decode($rawBody, true);
if (!is_array($data)) {
    $data = $_POST;
}

$action = $data['action'] ?? '';

switch ($action) {

    case 'add': {
        $csrf   = $data['_csrf'] ?? '';
        $partId = (int)($data['part_id'] ?? 0);
        $qty    = max(1, min(99, (int)($data['quantity'] ?? 1)));

        if (!verifyCsrfToken($csrf)) {
            echo json_encode(['success' => false, 'message' => 'Ошибка проверки безопасности (CSRF).']);
            exit;
        }
        if (!$partId) {
            echo json_encode(['success' => false, 'message' => 'Не указан товар.']);
            exit;
        }

        $pStmt = $db->prepare("SELECT id, name, stock FROM parts WHERE id = ? AND is_active = 1");
        $pStmt->execute([$partId]);
        $part = $pStmt->fetch();
        if (!$part) {
            echo json_encode(['success' => false, 'message' => 'Товар не найден.']);
            exit;
        }

        cartAdd($db, $partId, $qty);

        $newTotal = cartTotalAny($db);
        echo json_encode([
            'success'         => true,
            'cart_count'      => cartCountAny($db),
            'cart_total'      => $newTotal,
            'cart_total_html' => formatPrice($newTotal),
            'message'         => 'Товар добавлен в корзину.',
        ]);
        break;
    }

    case 'remove': {
        $csrf   = $data['_csrf'] ?? '';
        $partId = (int)($data['part_id'] ?? 0);

        if (!verifyCsrfToken($csrf)) {
            echo json_encode(['success' => false, 'message' => 'CSRF ошибка.']);
            exit;
        }

        cartRemove($db, $partId);

        echo json_encode([
            'success'    => true,
            'cart_count' => cartCountAny($db),
            'cart_total' => cartTotalAny($db),
            'message'    => 'Товар удалён из корзины.',
        ]);
        break;
    }

    case 'update': {
        // {action:'update', items:[{part_id:X, quantity:Y}]}
        $items = $data['items'] ?? [];
        if (empty($items) && isset($data['part_id'])) {
            $items = [['part_id' => $data['part_id'], 'quantity' => $data['quantity'] ?? 1]];
        }
        if (!is_array($items)) {
            echo json_encode(['success' => false, 'message' => 'Некорректные данные.']);
            exit;
        }

        foreach ($items as $item) {
            $partId = (int)($item['part_id'] ?? 0);
            $qty    = max(1, min(99, (int)($item['quantity'] ?? 1)));
            if ($partId) cartSetQty($db, $partId, $qty);
        }

        $rowSub = 0.0;
        if (count($items) === 1) {
            $pid = (int)($items[0]['part_id'] ?? 0);
            foreach (cartDetailedItems($db) as $it) {
                if ((int)$it['part_id'] === $pid) { $rowSub = (float)$it['price'] * (int)$it['quantity']; break; }
            }
        }

        echo json_encode([
            'success'      => true,
            'cart_count'   => cartCountAny($db),
            'cart_total'   => cartTotalAny($db),
            'row_subtotal' => $rowSub,
            'message'      => 'Корзина обновлена.',
        ]);
        break;
    }

    case 'count': {
        echo json_encode(['cart_count' => cartCountAny($db)]);
        break;
    }

    default: {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Неизвестное действие: ' . sanitize($action)]);
    }
}
