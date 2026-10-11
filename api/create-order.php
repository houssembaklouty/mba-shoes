<?php
/**
 * MBA Orders - WooCommerce Order Proxy
 *
 * Receives order JSON from the landing page,
 * creates a WooCommerce order via REST API.
 *
 * No API keys are exposed to the browser.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// ─── CONFIGURATION ───────────────────────────────────
// Replace with your real WooCommerce REST API keys
// Generate at: wp-admin > WooCommerce > Settings > Advanced > REST API
$WC_URL    = 'http://localhost:8080';  // WordPress URL
$WC_KEY    = getenv('WC_CONSUMER_KEY')    ?: 'ck_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
$WC_SECRET = getenv('WC_CONSUMER_SECRET') ?: 'cs_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';

// ─── PRODUCT / VARIATION MAPPING ─────────────────────
// Product IDs from WooCommerce (created via WP-CLI)
// أسود: product_id = 10, variations: 40-44
// مرون: product_id = 11, variations: 40-44
$PRODUCTS = [
    'أسود' => [
        'product_id' => 10,
        'variations' => [
            '40' => 12, '41' => 13, '42' => 14, '43' => 15, '44' => 16
        ]
    ],
    'مرون' => [
        'product_id' => 11,
        'variations' => [
            '40' => 17, '41' => 18, '42' => 19, '43' => 20, '44' => 21
        ]
    ]
];

// Free shipping for qty >= 2
$FREE_SHIPPING_MIN_QTY = 2;
$SHIPPING_COST = 7.00;

// ─── HELPER FUNCTIONS ────────────────────────────────

function wc_request($endpoint, $method = 'GET', $data = null) {
    global $WC_URL, $WC_KEY, $WC_SECRET;

    $url = rtrim($WC_URL, '/') . '/index.php?rest_route=/wc/v3/' . $endpoint;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_USERPWD, $WC_KEY . ':' . $WC_SECRET);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'message' => 'cURL error: ' . $error];
    }

    return [
        'success'  => $httpCode >= 200 && $httpCode < 300,
        'code'     => $httpCode,
        'body'     => json_decode($response, true)
    ];
}

// ─── MAIN ────────────────────────────────────────────

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// Validate required fields
$required = ['name', 'phone', 'address', 'qty', 'pairs', 'price'];
foreach ($required as $field) {
    if (!isset($input[$field]) || (empty($input[$field]) && $input[$field] !== '0' && $input[$field] !== 0)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "حقل '$field' مطلوب"]);
        exit;
    }
}

$name     = trim($input['name']);
$phone    = trim($input['phone']);
$address  = trim($input['address']);
$qty      = (int) $input['qty'];
$pairs    = $input['pairs'];
$price    = (float) $input['price'];
$delivery = filter_var($input['delivery'], FILTER_VALIDATE_BOOLEAN);

// Build WooCommerce order line items
$line_items = [];
$has_errors = false;
$error_msg  = '';

foreach ($pairs as $pair) {
    $color = $pair['color'] ?? '';
    $size  = $pair['size'] ?? '';

    if (!isset($PRODUCTS[$color])) {
        $has_errors = true;
        $error_msg  = "اللون '$color' غير موجود";
        break;
    }

    $vid = $PRODUCTS[$color]['variations'][$size] ?? 0;

    if ($vid === 0) {
        // Variation not configured — fallback to simple product
        $line_items[] = [
            'product_id' => $PRODUCTS[$color]['product_id'],
            'quantity'   => 1,
            'meta_data'  => [
                ['key' => 'اللون', 'value' => $color],
                ['key' => 'القياس', 'value' => $size]
            ]
        ];
    } else {
        $line_items[] = [
            'variation_id' => $vid,
            'product_id'   => $PRODUCTS[$color]['product_id'],
            'quantity'     => 1
        ];
    }
}

if ($has_errors) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $error_msg]);
    exit;
}

// Build order
$order_data = [
    'payment_method'       => 'cod',
    'payment_method_title' => 'الدفع عند الاستلام',
    'set_paid'             => false,
    'billing' => [
        'first_name' => $name,
        'phone'      => $phone,
        'address_1'  => $address,
        'country'    => 'TN'
    ],
    'shipping' => [
        'first_name' => $name,
        'address_1'  => $address,
        'country'    => 'TN'
    ],
    'line_items' => $line_items,
    'customer_note' => "الكمية: $qty | الهاتف: $phone | الألوان: " . implode(', ', array_unique(array_map(fn($p) => $p['color'] ?? '', $pairs))),
    'meta_data' => [
        ['key' => '_mba_qty', 'value' => $qty]
    ]
];

// Add shipping line item
if ($delivery) {
    $order_data['shipping_lines'] = [[
        'method_title' => 'توصيل مجاني',
        'method_id'    => 'free_shipping',
        'total'        => '0.00'
    ]];
} else {
    $order_data['shipping_lines'] = [[
        'method_title' => 'توصيل',
        'method_id'    => 'flat_rate',
        'total'        => number_format($SHIPPING_COST, 2, '.', '')
    ]];
}

// Create order via REST API
$result = wc_request('orders', 'POST', $order_data);

if ($result['success']) {
    $order_id = $result['body']['id'] ?? 0;
    echo json_encode([
        'success'  => true,
        'order_id' => $order_id,
        'message'  => 'تم استلام طلبك بنجاح! رقم الطلب: #' . $order_id
    ]);
} else {
    $err = $result['body']['message'] ?? ($result['body']['code'] ?? 'Unknown error');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'فشل إنشاء الطلب: ' . $err,
        'debug'   => $result['body']
    ]);
}
