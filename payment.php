<?php
require_once 'config/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: auth.php");
    exit();
}

$order_id = (int)($_GET['order_id'] ?? 0);
$user_id = $_SESSION['user']['id'];
$error = '';
$success = '';

// ดึงข้อมูล Order พร้อมรูป QR Code ของสินค้านั้นๆ
$stmt = $pdo->prepare("SELECT o.*, p.qr_code_url, p.name as product_name 
    FROM orders o 
    JOIN order_items oi ON o.id = oi.order_id 
    JOIN products p ON oi.product_id = p.id 
    WHERE o.id = ? AND o.user_id = ?");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch();

if (!$order) {
    die("ไม่พบข้อมูลคำสั่งซื้อ");
}

// ลูกค้าอัปโหลดสลิปชำระเงิน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['slip'])) {
    if ($_FILES['slip']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/slips/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $ext = strtolower(pathinfo($_FILES['slip']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $slip_path = $upload_dir . 'slip_' . $order_id . '_' . time() . '.' . $ext;
            
            if (move_uploaded_file($_FILES['slip']['tmp_name'], $slip_path)) {
                // ปรับสถานะออเดอร์เป็น paid
                $stmt_up = $pdo->prepare("UPDATE orders SET status = 'paid' WHERE id = ?");
                $stmt_up->execute([$order_id]);
                $success = 'อัปโหลดสลิปชำระเงินเรียบร้อยแล้ว!';
            }
        } else {
            $error = 'กรุณาอัปโหลดไฟล์รูปภาพเท่านั้น';
        }
    } else {
        $error = 'กรุณเลือกไฟล์สลิปการโอนเงิน';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ชำระเงิน | DecorAura</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, sans-serif; }
        body { background: #f5f5f5; }
        .pay-box { max-width: 480px; margin: 30px auto; background: white; padding: 25px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center; }
        .qr-img { width: 230px; height: 230px; object-fit: contain; border: 1px solid #ddd; margin: 15px 0; padding: 5px; border-radius: 4px; }
        .form-group { margin-bottom: 15px; text-align: left; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 0.9rem; }
        .form-group input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        .btn-submit { background: #ee4d2d; color: white; border: none; width: 100%; padding: 12px; font-weight: bold; cursor: pointer; border-radius: 4px; font-size: 1rem; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="pay-box">
        <h2>💳 ชำระเงินค่าสินค้า</h2>
        <p style="color: #666; font-size: 0.9rem; margin-top: 5px;">สินค้า: <b><?= htmlspecialchars($order['product_name']) ?></b></p>
        <h3 style="color: #ee4d2d; margin-top: 8px;">ยอดที่ต้องชำระ: ฿<?= number_format($order['total_amount'], 2) ?></h3>

        <hr style="margin: 15px 0; border: none; border-top: 1px solid #eee;">

        <?php if ($success): ?>
            <div style="background: #e6ffe6; color: green; padding: 15px; border-radius: 4px; font-weight: bold;"><?= $success ?></div>
            <br><a href="index.php" style="color: #ee4d2d; font-weight: bold;">กลับสู่หน้าหลัก</a>
        <?php else: ?>
            <h4>สแกน QR Code ด้านล่างเพื่อโอนเงิน</h4>
            
            <?php if (!empty($order['qr_code_url'])): ?>
                <img src="<?= htmlspecialchars($order['qr_code_url']) ?>" class="qr-img">
            <?php else: ?>
                <div style="background: #fff3cd; color: #856404; padding: 15px; margin: 15px 0; border-radius: 4px; font-size: 0.85rem;">
                    ผู้ขายไม่ได้แนบ QR Code ไว้ กรุณาติดต่อผู้ขาย
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <p style="color: red; font-size: 0.85rem; margin-bottom: 10px;"><?= $error ?></p>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>แนบหลักฐานการโอนเงิน (รูปสลิป) *</label>
                    <input type="file" name="slip" accept="image/*" required>
                </div>
                <button type="submit" class="btn-submit">ยืนยันการแจ้งชำระเงิน</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>