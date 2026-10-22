<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../src/Config/Database.php';

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

function respond($ok, $message, $extra = []) {
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function getCartId(PDO $pdo, $userId, $create = false) {
    $stmt = $pdo->prepare("SELECT id FROM carts WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $id = $stmt->fetchColumn();
    if ($id) {
        return intval($id);
    }
    if (!$create) {
        return null;
    }
    $ins = $pdo->prepare("INSERT INTO carts (user_id) VALUES (?)");
    $ins->execute([$userId]);
    return intval($pdo->lastInsertId());
}

function getCartCount(PDO $pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(ci.quantity), 0)
        FROM carts c
        JOIN cart_items ci ON c.id = ci.cart_id
        WHERE c.user_id = ?
    ");
    $stmt->execute([$userId]);
    return intval($stmt->fetchColumn());
}

function productStock(array $p) {
    return intval($p['stock'] ?? $p['product_stock'] ?? 0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Method not allowed');
}

if (empty($_SESSION['user_id'])) {
    respond(false, 'กรุณาเข้าสู่ระบบก่อน', ['login_required' => true]);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    respond(false, 'คำขอไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองใหม่');
}

$userId = $_SESSION['user_id'];
$action = $input['action'] ?? '';

try {
    switch ($action) {

        // เพิ่มลงรถเข็น / ซื้อทันที
        case 'add':
        case 'buy_now':
            $productId = intval($input['product_id'] ?? 0);
            $qty = max(1, intval($input['quantity'] ?? 1));

            // ดึงข้อมูลสินค้าพร้อมตรวจสอบร้านค้า (เชื่อมกับตาราง shops)
            $stmt = $pdo->prepare("
                SELECT p.*, s.user_id AS shop_user_id 
                FROM products p 
                LEFT JOIN shops s ON p.shop_id = s.id 
                WHERE p.id = ?
            ");
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) {
                respond(false, 'ไม่พบสินค้า');
            }

            // [เพิ่มระบบป้องกัน] ตรวจสอบว่าสินค้าชิ้นนี้เป็นของผู้ใช้ปัจจุบัน (เจ้าของร้าน) หรือไม่
            if (!empty($product['shop_user_id']) && intval($product['shop_user_id']) === intval($userId)) {
                respond(false, 'คุณไม่สามารถซื้อสินค้าจากร้านค้าของตนเองได้');
            }

            $stock = productStock($product);
            if ($stock < 1) {
                respond(false, 'สินค้าหมด');
            }

            $cartId = getCartId($pdo, $userId, true);

            $q = $pdo->prepare("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ? LIMIT 1");
            $q->execute([$cartId, $productId]);
            $row = $q->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $newQty = $action === 'add' ? min(intval($row['quantity']) + $qty, $stock) : min($qty, $stock);
                $upd = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
                $upd->execute([$newQty, $row['id']]);
                $itemId = intval($row['id']);
            } else {
                $newQty = min($qty, $stock);
                $ins = $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)");
                $ins->execute([$cartId, $productId, $newQty]);
                $itemId = intval($pdo->lastInsertId());
            }

            respond(true, 'เพิ่มสินค้าลงรถเข็นแล้ว', [
                'cart_item_id' => $itemId,
                'quantity'     => $newQty,
                'cart_count'   => getCartCount($pdo, $userId),
            ]);
            break;

        // แก้จำนวนสินค้าในรถเข็น
        case 'update':
            $itemId = intval($input['cart_item_id'] ?? 0);
            $qty = max(1, intval($input['quantity'] ?? 1));

            $stmt = $pdo->prepare("
                SELECT p.*
                FROM cart_items ci
                JOIN carts c ON c.id = ci.cart_id
                JOIN products p ON p.id = ci.product_id
                WHERE ci.id = ? AND c.user_id = ?
            ");
            $stmt->execute([$itemId, $userId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) {
                respond(false, 'ไม่พบสินค้าในรถเข็น');
            }

            $stock = productStock($product);
            if ($stock > 0) {
                $qty = min($qty, $stock);
            }

            $upd = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
            $upd->execute([$qty, $itemId]);

            respond(true, 'อัปเดตจำนวนแล้ว', [
                'quantity'   => $qty,
                'cart_count' => getCartCount($pdo, $userId),
            ]);
            break;

        // ลบสินค้าออกจากรถเข็น
        case 'remove':
            $ids = $input['cart_item_ids'] ?? [];
            if (!is_array($ids)) {
                $ids = [$ids];
            }
            $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($v) {
                return $v > 0;
            })));
            if (empty($ids)) {
                respond(false, 'ไม่ได้เลือกสินค้า');
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $del = $pdo->prepare("
                DELETE ci FROM cart_items ci
                JOIN carts c ON c.id = ci.cart_id
                WHERE c.user_id = ? AND ci.id IN ($placeholders)
            ");
            $del->execute(array_merge([$userId], $ids));

            respond(true, 'ลบสินค้าแล้ว', ['cart_count' => getCartCount($pdo, $userId)]);
            break;

        default:
            respond(false, 'คำสั่งไม่ถูกต้อง');
    }
} catch (PDOException $e) {
    error_log('[Hugmart cart] ' . $e->getMessage());
    respond(false, 'เกิดข้อผิดพลาดกับฐานข้อมูล กรุณาลองใหม่อีกครั้ง');
}