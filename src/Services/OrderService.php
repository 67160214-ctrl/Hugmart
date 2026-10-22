<?php

function generateOrderNumber(): string
{
    return 'HM' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
}

/**
 * หาชื่อคอลัมน์สต็อกในตาราง products (stock หรือ product_stock)
 */
function productStockColumn(PDO $pdo): ?string
{
    static $col = false;
    if ($col !== false) {
        return $col;
    }
    $col = null;
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_COLUMN);
        foreach (['stock', 'product_stock'] as $candidate) {
            if (in_array($candidate, $cols, true)) {
                $col = $candidate;
                break;
            }
        }
    } catch (PDOException $e) {
        error_log('[Hugmart order] stock column lookup: ' . $e->getMessage());
    }
    return $col;
}

/**
 * ผู้ใช้นี้เป็นแอดมินหรือไม่ (อ่านจากคอลัมน์ users.role)
 * ถ้าไม่มีคอลัมน์นี้หรือค่าไม่ตรง จะถือว่าไม่ใช่แอดมิน (ปลอดภัยไว้ก่อน)
 */
function isAdminUser(PDO $pdo, $userId): bool
{
    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $role = strtolower(trim((string)$stmt->fetchColumn()));
        return in_array($role, ['admin', 'super_admin', 'superadmin', 'administrator'], true);
    } catch (PDOException $e) {
        error_log('[Hugmart auth] isAdminUser: ' . $e->getMessage());
        return false;
    }
}

/**
 * ดึงรายการในรถเข็นที่เลือก (เฉพาะของผู้ใช้คนนี้)
 */
function loadCheckoutItems(PDO $pdo, $userId, array $itemIds): array
{
    if (empty($itemIds)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
    $stmt = $pdo->prepare("
        SELECT
            ci.id AS cart_item_id,
            ci.quantity,
            p.*,
            sp.store_name
        FROM carts c
        JOIN cart_items ci ON c.id = ci.cart_id
        JOIN products p ON ci.product_id = p.id
        LEFT JOIN seller_profiles sp ON p.seller_id = sp.id
        WHERE c.user_id = ? AND ci.id IN ($placeholders)
        ORDER BY sp.id, ci.created_at DESC
    ");
    $stmt->execute(array_merge([$userId], $itemIds));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * ลายเซ็นของรายการสินค้า (ใช้เช็กว่าเป็นชุดสินค้า/จำนวน/ราคาเดียวกันหรือไม่)
 * แต่ละรายการ: [cart_item_id, quantity, price]
 */
function orderItemsSignature(array $rows): string
{
    $parts = [];
    foreach ($rows as $r) {
        $parts[] = (int)$r[0] . ':' . (int)$r[1] . ':' . number_format((float)$r[2], 2, '.', '');
    }
    sort($parts);
    return implode('|', $parts);
}

/**
 * หาออเดอร์ที่ยังรอชำระของผู้ใช้ ซึ่งเป็นสินค้าชุดเดียวกับที่เลือกอยู่ (กันสร้างออเดอร์ซ้ำ)
 */
function findReusableOrder(PDO $pdo, $userId, array $items): ?array
{
    $current = [];
    foreach ($items as $it) {
        $current[] = [$it['cart_item_id'], $it['quantity'], $it['price']];
    }
    $currentSig = orderItemsSignature($current);

    $stmt = $pdo->prepare("
        SELECT * FROM orders
        WHERE user_id = ? AND status = 'pending' AND payment_method = 'transfer'
        ORDER BY id DESC LIMIT 20
    ");
    $stmt->execute([$userId]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $order) {
        $snap = json_decode($order['items_snapshot'], true);
        if (!is_array($snap)) {
            continue;
        }
        $rows = [];
        foreach ($snap as $s) {
            $rows[] = [$s['cart_item_id'] ?? 0, $s['quantity'] ?? 0, $s['price'] ?? 0];
        }
        if (orderItemsSignature($rows) === $currentSig) {
            return $order;
        }
    }
    return null;
}

/**
 * สร้างออเดอร์สถานะรอชำระ เก็บรายการและราคา ณ เวลานั้นไว้ใน items_snapshot
 * (order_items จะถูกบันทึกตอนแอดมินอนุมัติการชำระเงินเท่านั้น)
 */
function createPendingOrder(PDO $pdo, $userId, array $items, string $paymentMethod = 'transfer'): array
{
    $snapshot = [];
    $total = 0.0;
    foreach ($items as $it) {
        $price = round((float)$it['price'], 2);
        $qty = (int)$it['quantity'];
        $snapshot[] = [
            'cart_item_id' => (int)$it['cart_item_id'],
            'product_id'   => (int)$it['id'],
            'seller_id'    => isset($it['seller_id']) ? (int)$it['seller_id'] : null,
            'name'         => (string)($it['name'] ?? $it['product_name'] ?? 'สินค้า'),
            'price'        => $price,
            'quantity'     => $qty,
        ];
        $total += $price * $qty;
    }
    $total = round($total, 2);
    $orderNumber = generateOrderNumber();

    $stmt = $pdo->prepare("
        INSERT INTO orders (order_number, user_id, total_amount, currency, status, payment_method, payment_status, items_snapshot)
        VALUES (?, ?, ?, 'THB', 'pending', ?, 'unpaid', ?)
    ");
    $stmt->execute([$orderNumber, $userId, $total, $paymentMethod, json_encode($snapshot, JSON_UNESCAPED_UNICODE)]);

    return [
        'id'           => (int)$pdo->lastInsertId(),
        'order_number' => $orderNumber,
        'total'        => $total,
    ];
}

/**
 * ตรวจสต็อกปัจจุบันเทียบกับรายการในออเดอร์ คืนรายการที่สต็อกไม่พอ
 */
function orderStockProblems(PDO $pdo, array $snapshot): array
{
    $col = productStockColumn($pdo);
    if ($col === null) {
        return [];
    }
    $problems = [];
    $stmt = $pdo->prepare("SELECT {$col} FROM products WHERE id = ?");
    foreach ($snapshot as $it) {
        $stmt->execute([(int)$it['product_id']]);
        $stock = $stmt->fetchColumn();
        if ($stock === false) {
            $problems[] = $it['name'] . ' (ไม่พบสินค้าในระบบ)';
        } elseif ((int)$stock < (int)$it['quantity']) {
            $problems[] = $it['name'] . ' (เหลือ ' . (int)$stock . ' ชิ้น ต้องการ ' . (int)$it['quantity'] . ' ชิ้น)';
        }
    }
    return $problems;
}

/**
 * ยืนยันการชำระเงิน: บันทึก order_items, ตัดสต็อก, ลบรายการออกจากรถเข็น, เปลี่ยนสถานะเป็นชำระแล้ว
 * เรียกซ้ำได้ปลอดภัย (ถ้าชำระแล้วจะไม่ทำซ้ำ)
 */
function fulfillOrder(PDO $pdo, int $orderId, ?string $paymentReference = null): bool
{
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? FOR UPDATE");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $pdo->rollBack();
            return false;
        }
        if ($order['payment_status'] === 'paid') {
            $pdo->commit();
            return true;
        }

        $snapshot = json_decode($order['items_snapshot'], true);
        if (!is_array($snapshot) || empty($snapshot)) {
            $pdo->rollBack();
            error_log('[Hugmart order] empty snapshot for order ' . $orderId);
            return false;
        }

        $stockCol = productStockColumn($pdo);
        $insItem = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, seller_id, product_name, price, quantity)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $cartItemIds = [];
        foreach ($snapshot as $it) {
            $insItem->execute([
                $orderId,
                (int)$it['product_id'],
                $it['seller_id'] ?? null,
                (string)$it['name'],
                (float)$it['price'],
                (int)$it['quantity'],
            ]);

            if ($stockCol !== null) {
                // $stockCol มาจากรายการคงที่ (stock / product_stock) ไม่ได้มาจากผู้ใช้
                $upd = $pdo->prepare("UPDATE products SET {$stockCol} = GREATEST({$stockCol} - ?, 0) WHERE id = ?");
                $upd->execute([(int)$it['quantity'], (int)$it['product_id']]);
            }

            if (!empty($it['cart_item_id'])) {
                $cartItemIds[] = (int)$it['cart_item_id'];
            }
        }

        if (!empty($cartItemIds)) {
            $placeholders = implode(',', array_fill(0, count($cartItemIds), '?'));
            $del = $pdo->prepare("
                DELETE ci FROM cart_items ci
                JOIN carts c ON c.id = ci.cart_id
                WHERE c.user_id = ? AND ci.id IN ($placeholders)
            ");
            $del->execute(array_merge([$order['user_id']], $cartItemIds));
        }

        $done = $pdo->prepare("
            UPDATE orders
            SET status = 'paid', payment_status = 'paid', paid_at = NOW(), stripe_payment_intent = ?
            WHERE id = ?
        ");
        $done->execute([$paymentReference, $orderId]);

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[Hugmart order] fulfill failed for order ' . $orderId . ': ' . $e->getMessage());
        return false;
    }
}