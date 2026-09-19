<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/db.php';

$user = $_SESSION['user'] ?? null;
$user_role = strtolower($user['role'] ?? '');

if (!$user || ($user_role !== 'seller' && $user_role !== 'admin')) {
    header("Location: apply_seller.php");
    exit();
}

$seller_id = $user['id'];
$msg = '';
$error = '';

// จัดการอัปโหลด QR Code (รองรับ JPG, PNG)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_qr'])) {
    if (isset($_FILES['qr_code']) && $_FILES['qr_code']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/qrcodes/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $ext = strtolower(pathinfo($_FILES['qr_code']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $filename = $upload_dir . 'qr_' . $seller_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['qr_code']['tmp_name'], $filename)) {
                $stmt = $pdo->prepare("UPDATE users SET qr_code_url = ? WHERE id = ?");
                $stmt->execute([$filename, $seller_id]);
                $msg = 'อัปโหลด QR Code เรียบร้อยแล้ว!';
            } else {
                $error = 'ไม่สามารถบันทึกไฟล์ QR Code ลงเซิร์ฟเวอร์ได้';
            }
        } else {
            $error = 'รองรับเฉพาะไฟล์รูปภาพ (JPG, PNG, WEBP)';
        }
    }
}

// ลบสินค้า
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $stmt_del = $pdo->prepare("DELETE FROM products WHERE id = ? AND seller_id = ?");
    $stmt_del->execute([$del_id, $seller_id]);
    header("Location: seller_dashboard.php");
    exit();
}

// ดึง QR Code ผู้ขาย
$seller_info = ['qr_code_url' => ''];
try {
    $stmt_seller = $pdo->prepare("SELECT qr_code_url FROM users WHERE id = ?");
    $stmt_seller->execute([$seller_id]);
    $seller_info = $stmt_seller->fetch() ?: ['qr_code_url' => ''];
} catch (Exception $e) {}

// ดึงรายการสินค้าของผู้ขาย
$my_products = [];
try {
    $stmt_products = $pdo->prepare("SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.seller_id = ? 
        ORDER BY p.id DESC");
    $stmt_products->execute([$seller_id]);
    $my_products = $stmt_products->fetchAll();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ศูนย์ผู้ขาย | DecorAura</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; }
        body { background: #f8f9fa; color: #333; }
        .container { max-width: 1050px; margin: 25px auto; padding: 0 15px; }
        .qr-card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 25px; display: flex; align-items: center; gap: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); }
        .qr-img { width: 100px; height: 100px; object-fit: contain; border: 1px solid #ddd; border-radius: 6px; padding: 2px; }
        .qr-placeholder { width: 100px; height: 100px; border: 1px dashed #ccc; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #999; font-size: 0.75rem; text-align: center; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .btn-add { background: #26aa99; color: white; padding: 10px 18px; text-decoration: none; border-radius: 6px; font-weight: bold; }
        .table-container { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.06); }
        .table-products { width: 100%; border-collapse: collapse; }
        .table-products th, .table-products td { padding: 14px; text-align: left; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        .table-products th { background: #fafafa; color: #555; }
        .img-thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 6px; border: 1px solid #eee; }
        .action-btns a { text-decoration: none; font-weight: bold; margin-right: 10px; }
        .btn-edit { color: #2196F3; }
        .btn-delete { color: #d0011b; }
    </style>
</head>
<body>
    <div class="container">
        <!-- จัดการ QR Code -->
        <div class="qr-card">
            <div>
                <?php if (!empty($seller_info['qr_code_url'])): ?>
                    <img src="<?= htmlspecialchars($seller_info['qr_code_url']) ?>" class="qr-img" onerror="this.onerror=null; this.src='https://via.placeholder.com/100?text=Error';">
                <?php else: ?>
                    <div class="qr-placeholder">ยังไม่มี<br>QR Code</div>
                <?php endif; ?>
            </div>
            <div>
                <h3>📱 QR Code รับเงินประจำร้านค้า</h3>
                <p style="font-size:0.85rem; color:#666; margin: 4px 0 10px 0;">อัปโหลด QR Code พร้อมเพย์ (ไฟล์ .jpg หรือ .png)</p>
                
                <?php if($msg): ?><p style="color:green; font-weight:bold; font-size:0.85rem;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
                <?php if($error): ?><p style="color:red; font-weight:bold; font-size:0.85rem;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

                <form method="POST" enctype="multipart/form-data" style="display:flex; gap:10px; align-items:center;">
                    <input type="file" name="qr_code" accept="image/jpeg, image/png, image/webp" required style="font-size:0.85rem;">
                    <button type="submit" name="upload_qr" style="background:#ee4d2d; color:white; border:none; padding:7px 16px; border-radius:4px; cursor:pointer; font-weight:bold;">บันทึก QR Code</button>
                </form>
            </div>
        </div>

        <div class="top-bar">
            <h2>📦 รายการสินค้าของฉัน (<?= count($my_products) ?> รายการ)</h2>
            <a href="seller_add_product.php" class="btn-add">+ เพิ่มสินค้าใหม่</a>
        </div>

        <!-- รายการสินค้า -->
        <div class="table-container">
            <table class="table-products">
                <thead>
                    <tr>
                        <th>รูปสินค้า</th>
                        <th>ชื่อสินค้า</th>
                        <th>หมวดหมู่</th>
                        <th>ราคา</th>
                        <th>คลัง</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($my_products)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding:30px; color:#888;">ยังไม่มีสินค้าในคลัง</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($my_products as $item): ?>
                            <tr>
                                <td>
                                    <?php $img_p = !empty($item['image_url']) ? $item['image_url'] : 'https://via.placeholder.com/50?text=No+Img'; ?>
                                    <img src="<?= htmlspecialchars($img_p) ?>" class="img-thumb" onerror="this.onerror=null; this.src='https://via.placeholder.com/50?text=No+Img';">
                                </td>
                                <td><b><?= htmlspecialchars($item['name']) ?></b></td>
                                <td><?= htmlspecialchars($item['category_name'] ?? 'ทั่วไป') ?></td>
                                <td style="color:#ee4d2d; font-weight:bold;">฿<?= number_format($item['price'], 2) ?></td>
                                <td><b><?= number_format((int)($item['stock_quantity'] ?? 0)) ?></b> ชิ้น</td>
                                <td class="action-btns">
                                    <a href="seller_edit_product.php?id=<?= $item['id'] ?>" class="btn-edit">✏️ แก้ไข</a>
                                    <a href="seller_dashboard.php?delete=<?= $item['id'] ?>" class="btn-delete" onclick="return confirm('ยืนยันลบสินค้าชิ้นนี้?')">🗑️ ลบ</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>