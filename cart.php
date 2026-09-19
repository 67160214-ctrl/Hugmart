<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config/db.php';

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user'])) {
    header("Location: auth.php");
    exit();
}

$user_id = $_SESSION['user']['id'];

// --- [ACTION 1] ปรับอัปเดตจำนวนสินค้า ---
if (isset($_POST['update_quantity'])) {
    $cart_id = (int)$_POST['cart_id'];
    $new_qty = (int)$_POST['quantity'];

    if ($new_qty <= 0) {
        $stmt_del = $pdo->prepare("DELETE FROM cart_items WHERE id = ? AND user_id = ?");
        $stmt_del->execute([$cart_id, $user_id]);
    } else {
        // เช็กสต็อกสินค้าก่อนอัปเดต
        $stmt_chk = $pdo->prepare("
            SELECT p.stock 
            FROM cart_items ci 
            JOIN products p ON ci.product_id = p.id 
            WHERE ci.id = ? AND ci.user_id = ?
        ");
        $stmt_chk->execute([$cart_id, $user_id]);
        $prod = $stmt_chk->fetch();

        if ($prod) {
            $stock = (int)($prod['stock'] ?? 0);
            if ($new_qty > $stock) {
                $new_qty = $stock;
            }
            $stmt_up = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ? AND user_id = ?");
            $stmt_up->execute([$new_qty, $cart_id, $user_id]);
        }
    }
    header("Location: cart.php");
    exit();
}

// --- [ACTION 2] ลบสินค้าชิ้นเดียวออกจากตะกร้า ---
if (isset($_GET['remove'])) {
    $remove_id = (int)$_GET['remove'];
    $stmt_rem = $pdo->prepare("DELETE FROM cart_items WHERE id = ? AND user_id = ?");
    $stmt_rem->execute([$remove_id, $user_id]);
    header("Location: cart.php");
    exit();
}

// --- [ACTION 3] ล้างตะกร้าสินค้าทั้งหมด ---
if (isset($_GET['clear_all'])) {
    $stmt_clear = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?");
    $stmt_clear->execute([$user_id]);
    header("Location: cart.php");
    exit();
}

// --- ดึงรายการสินค้าในตะกร้าของผู้ใช้ ---
$sql = "
    SELECT 
        ci.id AS cart_id,
        ci.quantity,
        p.id AS product_id,
        p.name,
        p.price,
        p.image,
        p.stock
    FROM cart_items ci
    JOIN products p ON ci.product_id = p.id
    WHERE ci.user_id = ?
    ORDER BY ci.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll();

// คำนวณราคารวมทั้งหมด
$total_amount = 0;
foreach ($cart_items as $item) {
    $total_amount += $item['price'] * $item['quantity'];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตะกร้าสินค้า - Hugmart</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0d9488;
            --primary-hover: #0f766e;
            --secondary: #0f172a;
            --bg-body: #f8fafc;
            --surface: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --danger: #ef4444;
            --danger-hover: #dc2626;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Prompt', sans-serif; }
        body { background: var(--bg-body); color: var(--text-main); line-height: 1.6; }
        .container { max-width: 1100px; margin: 30px auto; padding: 0 20px; }

        .page-title { 
            font-size: 1.5rem; 
            font-weight: 600; 
            margin-bottom: 20px; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }

        .cart-layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 25px;
            align-items: start;
        }

        .cart-table-card {
            background: var(--surface);
            border-radius: 12px;
            border: 1px solid var(--border);
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .cart-table {
            width: 100%;
            border-collapse: collapse;
        }

        .cart-table th {
            background: #f1f5f9;
            padding: 14px;
            text-align: left;
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .cart-table td {
            padding: 16px 14px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .product-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .product-img {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            background: #f8fafc;
            border: 1px solid var(--border);
        }

        .product-name {
            font-weight: 500;
            font-size: 0.95rem;
            color: var(--text-main);
        }

        .qty-control {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .qty-input {
            width: 55px;
            text-align: center;
            padding: 5px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .btn-sm {
            padding: 5px 10px;
            border: 1px solid var(--border);
            background: #f8fafc;
            border-radius: 6px;
            cursor: pointer;
            color: var(--text-main);
        }
        .btn-sm:hover { background: #e2e8f0; }

        .btn-del {
            color: var(--danger);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.1rem;
            padding: 5px;
            transition: color 0.2s;
        }
        .btn-del:hover { color: var(--danger-hover); }

        .summary-card {
            background: var(--surface);
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 22px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .summary-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 0.95rem;
            color: var(--text-muted);
        }

        .summary-row.total {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-main);
            border-top: 1px dashed var(--border);
            padding-top: 12px;
            margin-top: 12px;
        }

        .total-price { color: var(--primary); }

        .btn-checkout {
            width: 100%;
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            margin-top: 15px;
            display: block;
            text-align: center;
            text-decoration: none;
            transition: background 0.2s;
        }
        .btn-checkout:hover { background: var(--primary-hover); }

        .btn-continue {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            margin-top: 15px;
        }
        .btn-continue:hover { color: var(--primary); }

        .empty-cart {
            background: var(--surface);
            padding: 60px 20px;
            text-align: center;
            border-radius: 12px;
            border: 1px solid var(--border);
        }

        @media (max-width: 768px) {
            .cart-layout { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <?php include 'includes/header.php'; ?>

    <div class="container">
        <h1 class="page-title"><i class="fa-solid fa-cart-shopping" style="color: var(--primary);"></i> ตะกร้าสินค้าของคุณ</h1>

        <?php if (empty($cart_items)): ?>
            <div class="empty-cart">
                <i class="fa-solid fa-basket-shopping" style="font-size: 3.5rem; color: #cbd5e1; margin-bottom: 15px;"></i>
                <h2>ไม่มีสินค้าในตะกร้า</h2>
                <p style="color: var(--text-muted); margin-bottom: 20px;">เลือกซื้อสินค้ามือสองคุณภาพดีในร้านเลยตอนนี้</p>
                <a href="index.php" class="btn-checkout" style="display: inline-block; width: auto; padding: 10px 24px;">
                    <i class="fa-solid fa-arrow-left"></i> กลับไปเลือกสินค้า
                </a>
            </div>
        <?php else: ?>
            <div class="cart-layout">
                
                <div class="cart-table-card">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>สินค้า</th>
                                <th>ราคา</th>
                                <th style="text-align: center;">จำนวน</th>
                                <th style="text-align: right;">รวม</th>
                                <th style="text-align: center;"><i class="fa-solid fa-trash"></i></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart_items as $item): ?>
                                <?php 
                                    $subtotal = $item['price'] * $item['quantity']; 
                                    $img = !empty($item['image']) ? $item['image'] : 'assets/images/no-image.png';
                                ?>
                                <tr>
                                    <td>
                                        <div class="product-info">
                                            <img src="<?= htmlspecialchars($img) ?>" class="product-img" alt="">
                                            <div class="product-name"><?= htmlspecialchars($item['name']) ?></div>
                                        </div>
                                    </td>
                                    <td>฿<?= number_format($item['price'], 2) ?></td>
                                    <td>
                                        <form method="POST" class="qty-control" style="justify-content: center;">
                                            <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
                                            <input type="hidden" name="update_quantity" value="1">
                                            <button type="submit" name="quantity" value="<?= $item['quantity'] - 1 ?>" class="btn-sm">-</button>
                                            <input type="text" name="quantity_value" value="<?= $item['quantity'] ?>" class="qty-input" readonly>
                                            <button type="submit" name="quantity" value="<?= $item['quantity'] + 1 ?>" class="btn-sm" <?= $item['quantity'] >= $item['stock'] ? 'disabled' : '' ?>>+</button>
                                        </form>
                                    </td>
                                    <td style="text-align: right; font-weight: 500;">฿<?= number_format($subtotal, 2) ?></td>
                                    <td style="text-align: center;">
                                        <a href="cart.php?remove=<?= $item['cart_id'] ?>" class="btn-del" onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบรายการนี้?');" title="ลบรายการ">
                                            <i class="fa-solid fa-xmark"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div style="padding: 15px; display: flex; justify-content: space-between; background: #fafafa;">
                        <a href="index.php" class="btn-continue"><i class="fa-solid fa-arrow-left"></i> เลือกซื้อสินค้าเพิ่ม</a>
                        <a href="cart.php?clear_all=1" style="color: var(--danger); text-decoration: none; font-size: 0.88rem;" onclick="return confirm('คุณต้องการล้างสินค้าทั้งหมดในตะกร้าใช่หรือไม่?');">
                            <i class="fa-solid fa-trash-can"></i> ล้างตะกร้าทั้งหมด
                        </a>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="summary-title">สรุปคำสั่งซื้อ</div>
                    <div class="summary-row">
                        <span>จำนวนสินค้า</span>
                        <span><?= count($cart_items) ?> รายการ</span>
                    </div>
                    <div class="summary-row total">
                        <span>ราคารวมทั้งสิ้น</span>
                        <span class="total-price">฿<?= number_format($total_amount, 2) ?></span>
                    </div>
                    <a href="checkout.php" class="btn-checkout">
                        ดำเนินการสั่งซื้อ <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>

            </div>
        <?php endif; ?>
    </div>

</body>
</html>