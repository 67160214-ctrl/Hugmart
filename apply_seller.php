<?php
require_once 'config/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: auth.php");
    exit();
}

// ถ้าเป็น seller อยู่แล้วให้ไปหน้า dashboard
if (($_SESSION['user']['role'] ?? '') === 'seller') {
    header("Location: seller_dashboard.php");
    exit();
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user']['id'];
    
    // อัปเดตสิทธิ์ผู้ใช้เป็น seller
    $stmt = $pdo->prepare("UPDATE users SET role = 'seller' WHERE id = ?");
    if ($stmt->execute([$user_id])) {
        $_SESSION['user']['role'] = 'seller';
        header("Location: seller_dashboard.php");
        exit();
    } else {
        $message = 'เกิดข้อผิดพลาดในการลงทะเบียน';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>สมัครเป็นพ่อค้า/แม่ค้า | DecorAura</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f5f5f5; margin: 0; }
        .box { max-width: 500px; margin: 50px auto; background: white; padding: 30px; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; }
        .btn-apply { background: #ee4d2d; color: white; border: none; padding: 12px 25px; font-size: 1rem; font-weight: bold; cursor: pointer; border-radius: 4px; margin-top: 20px; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="box">
        <h2>🏪 สมัครเปิดร้านค้ากับ DecorAura</h2>
        <p style="margin-top: 15px; color: #666;">เริ่มลงขายสินค้า ตกแต่งบ้าน และสร้างรายได้ง่ายๆ ได้ทันที</p>
        <?php if($message): ?><p style="color:red;"><?= $message ?></p><?php endif; ?>
        <form method="POST">
            <button type="submit" class="btn-apply">ยืนยันเปิดร้านค้าทันที</button>
        </form>
    </div>
</body>
</html>