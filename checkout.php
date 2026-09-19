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

// ดึงรายการสินค้า พร้อม JOIN ไปเอา QR Code ของผู้ขาย (u.qr_code)
$sql = "
    SELECT 
        ci.quantity,
        p.name,
        p.price,
        u.qr_code AS seller_qr
    FROM cart_items ci
    JOIN products p ON ci.product_id = p.id
    LEFT JOIN users u ON p.seller_id = u.id
    WHERE ci.user_id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll();

if (empty($cart_items)) {
    header("Location: cart.php");
    exit();
}

$total_amount = 0;
$seller_qr = "";

foreach ($cart_items as $item) {
    $total_amount += $item['price'] * $item['quantity'];
    // ดึง QR Code ของผู้ขาย (ถ้ามี)
    if (!empty($item['seller_qr'])) {
        $seller_qr = $item['seller_qr'];
    }
}

// จัดการเมื่อมีการส่งฟอร์มชำระเงิน
$success_msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    $slip_path = null;
    
    // จัดการอัปโหลดไฟล์สลิป
    if (isset($_FILES['slip']) && $_FILES['slip']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['slip']['tmp_name'];
        $file_name = $_FILES['slip']['name'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowed_ext)) {
            $upload_dir = 'assets/images/slips/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $slip_path = $upload_dir . 'slip_' . $user_id . '_' . time() . '.' . $ext;
            move_uploaded_file($file_tmp, $slip_path);
        }
    }

    // ล้างตะกร้าสินค้าหลังจากชำระเงินเสร็จ
    $stmt_clear = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?");
    $stmt_clear->execute([$user_id]);
    
    $success_msg = "ส่งข้อมูลการชำระเงินเรียบร้อยแล้ว! ร้านค้าจะทำการตรวจสอบและจัดส่งสินค้าโดยเร็วที่สุด";
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ชำระเงิน - Hugmart</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Prompt', sans-serif; }
        body { background: var(--bg-body); color: var(--text-main); line-height: 1.6; }
        .container { max-width: 800px; margin: 30px auto; padding: 0 20px; }

        .checkout-card {
            background: var(--surface);
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 30px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }

        .title {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 20px;
            text-align: center;
            color: var(--text-main);
        }

        .qr-section {
            text-align: center;
            background: #f1f5f9;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 1px dashed var(--primary);
        }

        .qr-img {
            width: 220px;
            height: 220px;
            object-fit: contain;
            border-radius: 8px;
            background: white;
            padding: 10px;
            border: 1px solid var(--border);
            margin: 15px 0;
        }

        .amount-badge {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-top: 5px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 0.95rem;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.95rem;
        }

        .btn-submit {
            width: 100%;
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-submit:hover { background: var(--primary-hover); }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
        }

        .btn-home {
            display: inline-block;
            margin-top: 15px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
    </style>
</head>
<body>

    <?php include 'includes/header.php'; ?>

    <div class="container">
        <div class="checkout-card">
            <h1 class="title"><i class="fa-solid fa-qrcode" style="color: var(--primary);"></i> ชำระเงินค่าสินค้า</h1>

            <?php if (!empty($success_msg)): ?>
                <div class="alert-success">
                    <i class="fa-solid fa-circle-check" style="font-size: 2rem; margin-bottom: 10px;"></i>
                    <h3>ชำระเงินสำเร็จ</h3>
                    <p><?= $success_msg ?></p>
                    <a href="index.php" class="btn-home"><i class="fa-solid fa-house"></i> กลับสู่หน้าหลัก</a>
                </div>
            <?php else: ?>
                <div class="qr-section">
                    <p style="font-size: 0.95rem; color: var(--text-muted);">สแกน QR Code ด้านล่างเพื่อโอนเงินผ่านแอปธนาคาร</p>
                    
                    <!-- แสดง QR Code ที่ผู้ขายอัปโหลดไว้ในระบบ (ดึงจาก database) -->
                    <?php if (!empty($seller_qr) && file_exists($seller_qr)): ?>
                        <img src="<?= htmlspecialchars($seller_qr) ?>" alt="QR Code ร้านค้า" class="qr-img">
                    <?php else: ?>
                        <img src="assets/images/qr-code.png" alt="QR Code ชำระเงิน" class="qr-img" onerror="this.src='https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=HugmartPayment';">
                    <?php endif; ?>
                    
                    <div>ยอดเงินที่ต้องชำระ</div>
                    <div class="amount-badge">฿<?= number_format($total_amount, 2) ?></div>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label class="form-label">แนบหลักฐานการโอนเงิน (สลิป)</label>
                        <input type="file" name="slip" class="form-control" accept="image/*" required>
                    </div>

                    <button type="submit" name="confirm_payment" class="btn-submit">
                        <i class="fa-solid fa-paper-plane"></i> ยืนยันการชำระเงิน
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>