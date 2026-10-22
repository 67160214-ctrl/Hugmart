<?php
require_once __DIR__ . '/../../../vendor/autoload.php';

use Hugmart\Config\Database;
use Hugmart\Middleware\AuthMiddleware;
use Hugmart\Helpers\CSRF;

session_start();
header('Content-Type: application/json');

$user = AuthMiddleware::authenticate();
$data = json_decode(file_get_contents("php://input"), true);

if (!CSRF::verifyToken($data['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF Security Token']);
    exit();
}

$addressId     = filter_var($data['address_id'] ?? null, FILTER_VALIDATE_INT);
$paymentMethod = $data['payment_method'] ?? '';
$cartItems     = $data['items'] ?? []; // Array of cart item IDs

if (!$addressId || empty($cartItems) || !in_array($paymentMethod, ['qr_payment', 'credit_card', 'bank_transfer_slip'])) {
    http_response_code(422);
    echo json_encode(['error' => 'Incomplete order checkout parameter.']);
    exit();
}

$db = Database::getConnection();

try {
    $db->beginTransaction();

    // Fetch Address
    $stmtAddr = $db->prepare("SELECT * FROM user_addresses WHERE id = ? AND user_id = ?");
    $stmtAddr->execute([$addressId, $user['id']]);
    $address = $stmtAddr->fetch();
    if (!$address) throw new Exception("Address not found.");

    $formattedAddress = "{$address['recipient_name']} ({$address['phone']}) - {$address['address_line1']} {$address['subdistrict']} {$address['district']} {$address['province']} {$address['postal_code']}";

    // Group items by Seller
    $itemsBySeller = [];
    foreach ($cartItems as $cId) {
        // LOCK PRODUCT ROW FOR STOCK SAFETY
        $stmtItem = $db->prepare("SELECT ci.id, ci.quantity, ci.product_id, ci.variant_id,
                                         p.name, p.current_price, p.total_stock, p.seller_id,
                                         pv.variant_name, pv.price as variant_price, pv.stock as variant_stock
                                  FROM cart_items ci
                                  JOIN products p ON ci.product_id = p.id
                                  LEFT JOIN product_variants pv ON ci.variant_id = pv.id
                                  WHERE ci.id = ? FOR UPDATE");
        $stmtItem->execute([$cId]);
        $item = $stmtItem->fetch();

        if (!$item) throw new Exception("Cart item ID {$cId} not found.");

        // Check Stock
        $availableStock = $item['variant_id'] ? $item['variant_stock'] : $item['total_stock'];
        if ($availableStock < $item['quantity']) {
            throw new Exception("Stock exceeded for product: {$item['name']}. Available: {$availableStock}");
        }

        $itemsBySeller[$item['seller_id']][] = $item;
    }

    $createdOrders = [];

    // Create Orders per seller
    foreach ($itemsBySeller as $sellerId => $sellerProducts) {
        $subtotal = 0;
        foreach ($sellerProducts as $prod) {
            $price = $prod['variant_id'] ? $prod['variant_price'] : $prod['current_price'];
            $subtotal += ($price * $prod['quantity']);
        }

        $shippingFee = 40.00; // Standard Flat Rate
        $grandTotal  = $subtotal + $shippingFee;
        $orderNo     = "HUG-" . date('YmdHis') . "-" . rand(1000, 9999);

        $stmtOrder = $db->prepare("INSERT INTO orders (order_no, user_id, seller_id, subtotal, shipping_fee, grand_total, payment_method, shipping_address, status)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending_payment')");
        $stmtOrder->execute([$orderNo, $user['id'], $sellerId, $subtotal, $shippingFee, $grandTotal, $paymentMethod, $formattedAddress]);
        $orderId = $db->lastInsertId();

        // Insert Order Items & Deduct Stock
        foreach ($sellerProducts as $prod) {
            $unitPrice  = $prod['variant_id'] ? $prod['variant_price'] : $prod['current_price'];
            $totalPrice = $unitPrice * $prod['quantity'];

            $stmtOI = $db->prepare("INSERT INTO order_items (order_id, product_id, variant_id, product_name, variant_name, unit_price, quantity, total_price)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtOI->execute([$orderId, $prod['product_id'], $prod['variant_id'], $prod['name'], $prod['variant_name'], $unitPrice, $prod['quantity'], $totalPrice]);

            // DEDUCT STOCK
            if ($prod['variant_id']) {
                $stmtDeduct = $db->prepare("UPDATE product_variants SET stock = stock - ? WHERE id = ?");
                $stmtDeduct->execute([$prod['quantity'], $prod['variant_id']]);
            }
            $stmtDeductP = $db->prepare("UPDATE products SET total_stock = total_stock - ?, sold_count = sold_count + ? WHERE id = ?");
            $stmtDeductP->execute([$prod['quantity'], $prod['quantity'], $prod['product_id']]);

            // Clear Cart Item
            $stmtDelCart = $db->prepare("DELETE FROM cart_items WHERE id = ?");
            $stmtDelCart->execute([$prod['id']]);
        }

        $createdOrders[] = ['order_id' => $orderId, 'order_no' => $orderNo, 'amount' => $grandTotal];
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Orders generated successfully.',
        'orders'  => $createdOrders
    ]);

} catch (\Exception $e) {
    $db->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}