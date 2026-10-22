<?php
require_once __DIR__ . '/../src/Config/Database.php';
require_once __DIR__ . '/../src/Config/Payment.php';
require_once __DIR__ . '/../src/Services/StripeService.php';
require_once __DIR__ . '/../src/Services/OrderService.php';

if (!isset($pdo) && class_exists('Database')) {
    if (method_exists('Database', 'getConnection')) {
        $pdo = Database::getConnection();
    } elseif (method_exists('Database', 'getInstance')) {
        $pdo = Database::getInstance()->getConnection();
    } else {
        $db = new Database();
        $pdo = method_exists($db, 'getConnection') ? $db->getConnection() : ($db->pdo ?? null);
    }
}

$cfg = paymentConfig();
$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if ($cfg['stripe_webhook_secret'] === '') {
    error_log('[Hugmart webhook] STRIPE_WEBHOOK_SECRET is not configured');
    http_response_code(500);
    echo 'webhook not configured';
    exit;
}

if (!stripeVerifyWebhook($payload, $sigHeader, $cfg['stripe_webhook_secret'])) {
    http_response_code(400);
    echo 'invalid signature';
    exit;
}

$event = json_decode($payload, true);
if (!is_array($event)) {
    http_response_code(400);
    echo 'invalid payload';
    exit;
}

$type = $event['type'] ?? '';
$handled = ['checkout.session.completed', 'checkout.session.async_payment_succeeded'];

if (in_array($type, $handled, true)) {
    $obj = $event['data']['object'] ?? [];
    $orderId = (int)($obj['metadata']['order_id'] ?? 0);

    if (($obj['payment_status'] ?? '') === 'paid' && $orderId > 0) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            $valid = $order
                && (string)$order['stripe_session_id'] === (string)($obj['id'] ?? '')
                && (int)($obj['amount_total'] ?? -1) === (int)round((float)$order['total_amount'] * 100)
                && strtolower($obj['currency'] ?? '') === 'thb';

            if ($valid) {
                $intent = is_string($obj['payment_intent'] ?? null) ? $obj['payment_intent'] : null;
                if (!fulfillOrder($pdo, $orderId, $intent)) {
                    http_response_code(500); // ให้ Stripe ส่งซ้ำภายหลัง
                    echo 'fulfill failed';
                    exit;
                }
            } else {
                error_log('[Hugmart webhook] order mismatch for order ' . $orderId);
            }
        } catch (Throwable $e) {
            error_log('[Hugmart webhook] ' . $e->getMessage());
            http_response_code(500);
            echo 'error';
            exit;
        }
    }
}

http_response_code(200);
echo 'ok';