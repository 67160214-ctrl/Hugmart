<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: auth.php");
    exit();
}

$user_id = $_SESSION['user']['id'];

// ดึง Role และ ข้อมูลผู้ใช้ (รวมถึง QR Code)
$stmt_check = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt_check->execute([$user_id]);
$user_data = $stmt_check->fetch();
$current_role = $user_data['role'] ?? 'buyer';

// --- [ACTION 1] ลงทะเบียนผู้ขาย ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_seller'])) {
    $shop_name = trim($_POST['shop_name']);
    $phone = trim($_POST['phone']);

    if (!empty($shop_name) && !empty($phone)) {
        $stmt = $pdo->prepare("UPDATE users SET role = 'seller', phone = ? WHERE id = ?");
        if ($stmt->execute([$phone, $user_id])) {
            $_SESSION['user']['role'] = 'seller';
            header("Location: seller_center.php"); 
            exit();
        }
    }
}

// --- [ACTION 2] อัปโหลด QR Code PromptPay ---
$qr_msg = "";
$qr_status = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_qr'])) {
    if (isset($_FILES['qr_code']) && $_FILES['qr_code']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['qr_code']['tmp_name'];
        $file_name = $_FILES['qr_code']['name'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowed_ext)) {
            $new_filename = 'qr_' . $user_id . '_' . time() . '.' . $ext;
            $upload_dir = 'assets/images/qr/';
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $destination = $upload_dir . $new_filename;
            if (move_uploaded_file($file_tmp, $destination)) {
                // ลบรูปเก่าออกหากมี
                if (!empty($user_data['qr_code']) && file_exists($user_data['qr_code'])) {
                    @unlink($user_data['qr_code']);
                }
                
                $stmt_qr = $pdo->prepare("UPDATE users SET qr_code = ? WHERE id = ?");
                $stmt_qr->execute([$destination, $user_id]);
                
                $_SESSION['qr_msg'] = "อัปโหลด QR Code สำเร็จแล้ว!";
                $_SESSION['qr_status'] = "success";
                header("Location: seller_center.php");
                exit();
            }
        } else {
            $qr_msg = "รองรับเฉพาะไฟล์รูปภาพ (JPG, PNG, WEBP) เท่านั้น";
            $qr_status = "error";
        }
    }
}

// --- [ACTION 3] ลบ QR Code ---
if (isset($_GET['action']) && $_GET['action'] === 'delete_qr') {
    if (!empty($user_data['qr_code']) && file_exists($user_data['qr_code'])) {
        @unlink($user_data['qr_code']);
    }
    $stmt_del_qr = $pdo->prepare("UPDATE users SET qr_code = NULL WHERE id = ?");
    $stmt_del_qr->execute([$user_id]);
    
    $_SESSION['qr_msg'] = "ลบ QR Code เรียบร้อยแล้ว";
    $_SESSION['qr_status'] = "success";
    header("Location: seller_center.php");
    exit();
}

// --- [ACTION 4] ลบสินค้า ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $product_id = $_GET['id'];
    $stmt_del = $pdo->prepare("DELETE FROM products WHERE id = ? AND seller_id = ?");
    $stmt_del->execute([$product_id, $user_id]);
    header("Location: seller_center.php");
    exit();
}

// ดึงรายการสินค้าของผู้ขาย
$products = [];
if ($current_role === 'seller' || (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'seller')) {
    $stmt_products = $pdo->prepare("SELECT * FROM products WHERE seller_id = ? ORDER BY id DESC");
    $stmt_products->execute([$user_id]);
    $products = $stmt_products->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ศูนย์จัดการร้านค้า - Hugmart</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0d9488;
            --primary-hover: #0f766e;
            --bg-body: #f8fafc;
            --surface: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --danger: #ef4444;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Prompt', sans-serif; }
        body { background: var(--bg-body); color: var(--text-main); }
        .container { max-width: 1000px; margin: 30px auto; padding: 0 20px; }

        .card {
            background: var(--surface);
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 30px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }

        .form-register { max-width: 520px; margin: 0 auto; }
        .form-group { margin-bottom: 18px; }
        .form-label { display: block; font-size: 0.95rem; font-weight: 500; margin-bottom: 6px; }
        .form-control { width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.95rem; outline: none; }
        .form-control:focus { border-color: var(--primary); }
        .btn-submit { width: 100%; background: var(--primary); color: white; border: none; padding: 12px; border-radius: 8px; font-size: 1rem; font-weight: 500; cursor: pointer; }
        .btn-submit:hover { background: var(--primary-hover); }

        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .btn-add { background: var(--primary); color: white; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; transition: background 0.2s; }
        .btn-add:hover { background: var(--primary-hover); }

        .table-responsive { overflow-x: auto; }
        .product-table { width: 100%; border-collapse: collapse; text-align: left; }
        .product-table th, .product-table td { padding: 14px 16px; border-bottom: 1px solid var(--border); }
        .product-table th { background: #f1f5f9; color: var(--text-muted); font-weight: 600; font-size: 0.9rem; }
        .product-img { width: 50px; height: 50px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border); }
        .badge-stock { background: #d1fae5; color: #065f46; padding: 4px 10px; border-radius: 20px; font-size: 0.85rem; font-weight: 500; }
        
        .btn-edit { color: var(--primary); text-decoration: none; padding: 6px 10px; border-radius: 6px; margin-right: 5px; display: inline-flex; align-items: center; gap: 4px; }
        .btn-edit:hover { background: #f0fdfa; }
        .btn-action { color: var(--danger); text-decoration: none; padding: 6px 10px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px; }
        .btn-action:hover { background: #fee2e2; }

        /* Style สำหรับส่วน QR Code */
        .qr-section { display: flex; align-items: center; gap: 25px; flex-wrap: wrap; }
        .qr-preview { width: 120px; height: 120px; border: 2px dashed var(--border); border-radius: 10px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #fafafa; }
        .qr-preview img { width: 100%; height: 100%; object-fit: contain; }
        .alert-msg { padding: 10px 14px; border-radius: 8px; font-size: 0.9rem; margin-bottom: 15px; }
        .alert-msg.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-msg.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body>

    <?php include 'includes/header.php'; ?>

    <div class="container">
        
        <?php if ($current_role !== 'seller' && (!isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'seller')): ?>
            
            <div class="card form-register">
                <div style="text-align: center; margin-bottom: 25px;">
                    <h1 style="font-size: 1.4rem;"><i class="fa-solid fa-store" style="color: var(--primary);"></i> ลงทะเบียนเปิดร้านค้า</h1>
                    <p style="color: var(--text-muted); font-size: 0.9rem;">กรอกข้อมูลเพื่อเริ่มต้นวางขายสินค้าใน Hugmart</p>
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">ชื่อร้านค้า / ชื่อผู้ขาย <span style="color: red;">*</span></label>
                        <input type="text" name="shop_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">เบอร์โทรศัพท์ติดต่อ <span style="color: red;">*</span></label>
                        <input type="tel" name="phone" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">รายละเอียดร้านค้า</label>
                        <textarea name="shop_description" class="form-control" rows="3"></textarea>
                    </div>

                    <button type="submit" name="register_seller" class="btn-submit">
                        <i class="fa-solid fa-paper-plane"></i> ยืนยันการลงทะเบียนร้านค้า
                    </button>
                </form>
            </div>

        <?php else: ?>

            <!-- ================= [ส่วนเพิ่มใหม่]: การตั้งค่า QR Code ================= -->
            <div class="card">
                <div style="margin-bottom: 15px;">
                    <h2 style="font-size: 1.15rem;"><i class="fa-solid fa-qrcode" style="color: var(--primary);"></i> ตั้งค่า QR Code PromptPay สำหรับรับเงิน</h2>
                    <p style="color: var(--text-muted); font-size: 0.88rem;">อัปโหลดรูป QR Code พร้อมเพย์ของคุณ เพื่อให้ลูกค้าสแกนจ่ายเงินในหน้าชำระเงิน</p>
                </div>

                <?php 
                if (isset($_SESSION['qr_msg'])) {
                    $qr_msg = $_SESSION['qr_msg'];
                    $qr_status = $_SESSION['qr_status'];
                    unset($_SESSION['qr_msg'], $_SESSION['qr_status']);
                }
                ?>

                <?php if (!empty($qr_msg)): ?>
                    <div class="alert-msg <?= $qr_status ?>">
                        <?= htmlspecialchars($qr_msg) ?>
                    </div>
                <?php endif; ?>

                <div class="qr-section">
                    <div class="qr-preview">
                        <?php if (!empty($user_data['qr_code']) && file_exists($user_data['qr_code'])): ?>
                            <img src="<?= htmlspecialchars($user_data['qr_code']) ?>" alt="QR Code ร้านค้า">
                        <?php else: ?>
                            <div style="text-align: center; color: var(--text-muted); font-size: 0.8rem; padding: 5px;">
                                <i class="fa-solid fa-image" style="font-size: 1.8rem; margin-bottom: 5px; display: block; opacity: 0.4;"></i>
                                ยังไม่มี QR
                            </div>
                        <?php endif; ?>
                    </div>

                    <div style="flex: 1; min-width: 250px;">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-group" style="margin-bottom: 12px;">
                                <label class="form-label" style="font-size: 0.88rem;">เลือกไฟล์รูปภาพ QR Code</label>
                                <input type="file" name="qr_code" class="form-control" accept="image/*" required>
                            </div>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <button type="submit" name="upload_qr" class="btn-add" style="padding: 8px 16px; font-size: 0.9rem;">
                                    <i class="fa-solid fa-cloud-arrow-up"></i> อัปโหลด / เปลี่ยน QR Code
                                </button>
                                <?php if (!empty($user_data['qr_code'])): ?>
                                    <a href="seller_center.php?action=delete_qr" class="btn-action" style="font-size: 0.88rem;" onclick="return confirm('คุณต้องการลบ QR Code นี้ใช่หรือไม่?');">
                                        <i class="fa-solid fa-trash-can"></i> ลบรูปภาพ
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <!-- =================================================================== -->

            <div class="card">
                <div class="dashboard-header">
                    <div>
                        <h2><i class="fa-solid fa-store" style="color: var(--primary);"></i> การตั้งร้านวางขายสินค้า (Seller Dashboard)</h2>
                        <p style="color: var(--text-muted); font-size: 0.9rem;">จัดการรายการสินค้าของคุณที่กำลังวางขายในระบบ</p>
                    </div>
                    <a href="add_product.php" class="btn-add">
                        <i class="fa-solid fa-plus"></i> วางขายสินค้าใหม่
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="product-table">
                        <thead>
                            <tr>
                                <th>รูปสินค้า</th>
                                <th>ชื่อรายการสินค้า</th>
                                <th>ราคา</th>
                                <th>จำนวน</th>
                                <th>สถานะ</th>
                                <th style="text-align: center;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($products)): ?>
                                <?php foreach ($products as $p): ?>
                                    <tr>
                                        <td>
                                            <img src="<?= !empty($p['image']) ? htmlspecialchars($p['image']) : 'assets/images/no-image.png' ?>" class="product-img" alt="Product">
                                        </td>
                                        <td style="font-weight: 500;"><?= htmlspecialchars($p['name']) ?></td>
                                        <td style="color: var(--primary); font-weight: 600;">฿<?= number_format($p['price'], 2) ?></td>
                                        <td><strong><?= isset($p['stock']) ? number_format($p['stock']) : 0 ?></strong> ชิ้น</td>
                                        <td><span class="badge-stock">กำลังวางขาย</span></td>
                                        <td style="text-align: center; white-space: nowrap;">
                                            <a href="edit_product.php?id=<?= $p['id'] ?>" class="btn-edit">
                                                <i class="fa-solid fa-pen-to-square"></i> แก้ไข
                                            </a>
                                            <a href="seller_center.php?action=delete&id=<?= $p['id'] ?>" class="btn-action" onclick="return confirm('ยืนยันการนำสินค้านี้ออกจากร้าน?');">
                                                <i class="fa-solid fa-trash-can"></i> ลบ
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 40px 20px;">
                                        <i class="fa-solid fa-box-open" style="font-size: 2.5rem; margin-bottom: 10px; opacity: 0.5;"></i><br>
                                        ยังไม่มีสินค้าที่ตั้งวางขาย กดปุ่ม <b>"วางขายสินค้าใหม่"</b> เพื่อลงรายการขายชิ้นแรกได้เลย!
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php endif; ?>

    </div>

</body>
</html>