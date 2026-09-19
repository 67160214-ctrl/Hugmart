<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config/db.php';

// ตรวจสอบสิทธิ์ (ตัวอย่าง: เช็ก session แอดมิน)
if (!isset($_SESSION['user'])) {
    header("Location: auth.php");
    exit();
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['qr_image'])) {
    $file = $_FILES['qr_image'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array(strtolower($ext), $allowed)) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $filename = 'qr_store_' . time() . '.' . $ext;
            $target = $upload_dir . $filename;

            if (move_uploaded_file($file['tmp_name'], $target)) {
                // บันทึก/อัปเดตตำแหน่งไฟล์ลงฐานข้อมูล
                $stmt = $pdo->prepare("
                    INSERT INTO system_settings (setting_key, setting_value) 
                    VALUES ('qr_code_image', ?) 
                    ON DUPLICATE KEY UPDATE setting_value = ?
                ");
                $stmt->execute([$target, $target]);
                $message = "อัปโหลด QR Code ใหม่สำเร็จแล้ว!";
            } else {
                $message = "เกิดข้อผิดพลาดในการย้ายไฟล์";
            }
        } else {
            $message = "กรุณาอัปโหลดไฟล์รูปภาพเท่านั้น (JPG, PNG, WEBP)";
        }
    }
}

// ดึง QR Code ปัจจุบัน
$stmt_get = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'qr_code_image'");
$stmt_get->execute();
$current_qr = $stmt_get->fetchColumn() ?: 'assets/images/qr-code.png';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการ QR Code ร้านค้า - Hugmart</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; background: #f8fafc; padding: 40px 20px; }
        .card { max-width: 500px; margin: 0 auto; background: white; padding: 25px; border-radius: 12px; border: 1px solid #e2e8f0; text-align: center; }
        .btn { background: #0d9488; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 1rem; width: 100%; margin-top: 15px; }
        .qr-preview { width: 200px; height: 200px; object-fit: contain; margin: 15px 0; border: 1px solid #e2e8f0; padding: 5px; }
    </style>
</head>
<body>
    <div class="card">
        <h2>จัดการ QR Code ร้านค้า</h2>
        
        <?php if (!empty($message)): ?>
            <p style="color: #0d9488; margin-top: 10px;"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <p style="color: #64748b; font-size: 0.9rem; margin-top: 10px;">QR Code ที่ใช้อยู่ปัจจุบัน:</p>
        <img src="<?= htmlspecialchars($current_qr) ?>" class="qr-preview" alt="QR Code">

        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="qr_image" accept="image/*" required style="margin-top: 10px;">
            <button type="submit" class="btn">อัปโหลด QR Code ใหม่</button>
        </form>
    </div>
</body>
</html>