<?php
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
$error = '';

try {
    $stmt_cat = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
    $categories = $stmt_cat->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $price = (float)($_POST['price'] ?? 0);
    $stock_qty = (int)($_POST['stock_quantity'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $image_url = '';

    if (empty($name) || $price <= 0) {
        $error = 'กรุณากรอกชื่อสินค้าและราคาให้ถูกต้อง';
    } else {
        // เจน slug จากชื่อสินค้า + ประทับเวลาเพื่อป้องกันชื่อซ้ำ
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        if (empty($slug)) {
            $slug = 'product-' . time();
        } else {
            $slug .= '-' . time();
        }

        // จัดการอัปโหลดรูปภาพ
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/products/';
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0777, true);
            }

            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $filename = $upload_dir . 'prod_' . time() . '_' . rand(100, 999) . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $filename)) {
                    @chmod($filename, 0644);
                    $image_url = $filename;
                } else {
                    $error = 'ไม่สามารถบันทึกไฟล์รูปภาพลงโฟลเดอร์ uploads/products/ ได้';
                }
            } else {
                $error = 'รองรับเฉพาะไฟล์รูปภาพ .jpg, .png, .webp เท่านั้น';
            }
        }

        if (!$error) {
            try {
                // บันทึกเฉพาะคอลัมน์ stock_quantity และ slug ที่ตรงกับโครงสร้างฐานข้อมูล
                $stmt = $pdo->prepare("INSERT INTO products (seller_id, category_id, name, slug, description, price, stock_quantity, image_url, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$seller_id, $category_id, $name, $slug, $description, $price, $stock_qty, $image_url]);
                
                header("Location: seller_dashboard.php");
                exit();
            } catch (PDOException $e) {
                $error = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เพิ่มสินค้าใหม่ | DecorAura</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; }
        body { background: #f5f5f5; color: #333; }
        .form-card { max-width: 550px; margin: 30px auto; background: white; padding: 25px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 0.9rem; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 0.95rem; }
        .btn-submit { background: #ee4d2d; color: white; border: none; padding: 12px; width: 100%; border-radius: 4px; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>

    <?php include 'includes/header.php'; ?>

    <div class="form-card">
        <h2>➕ เพิ่มสินค้าใหม่</h2><br>
        <?php if($error): ?>
            <p style="color:red; margin-bottom:15px; word-break: break-word;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>ชื่อสินค้า *</label>
                <input type="text" name="name" required placeholder="เช่น ลูกฟุตบอล">
            </div>

            <div class="form-group">
                <label>หมวดหมู่</label>
                <select name="category_id">
                    <option value="">-- เลือกหมวดหมู่ --</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="display:flex; gap:10px;">
                <div style="flex:1;">
                    <label>ราคา (บาท) *</label>
                    <input type="number" name="price" step="0.01" min="0" required placeholder="0.00">
                </div>
                <div style="flex:1;">
                    <label>จำนวนในคลัง (ชิ้น) *</label>
                    <input type="number" name="stock_quantity" min="0" value="10" required>
                </div>
            </div>

            <div class="form-group">
                <label>รายละเอียดสินค้า</label>
                <textarea name="description" rows="3"></textarea>
            </div>

            <div class="form-group">
                <label>รูปภาพสินค้า (JPG, PNG)</label>
                <input type="file" name="image" accept="image/jpeg, image/png, image/webp">
            </div>

            <button type="submit" class="btn-submit">ลงขายสินค้า</button>
            <a href="seller_dashboard.php" style="display:block; text-align:center; margin-top:10px; color:#666; text-decoration:none;">ยกเลิก</a>
        </form>
    </div>
</body>
</html>