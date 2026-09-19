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
$product_id = (int)($_GET['id'] ?? 0);
$error = '';

$stmt_p = $pdo->prepare("SELECT * FROM products WHERE id = ? AND seller_id = ?");
$stmt_p->execute([$product_id, $seller_id]);
$product = $stmt_p->fetch();

if (!$product) {
    die("ไม่พบสินค้าชิ้นนี้");
}

$stmt_cat = $pdo->query("SELECT * FROM categories ORDER BY id ASC");
$categories = $stmt_cat->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $category_id = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
    $price = (float)($_POST['price'] ?? 0);
    $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $image_url = $product['image_url'];

    if (empty($name) || $price <= 0) {
        $error = 'กรุณากรอกชื่อสินค้าและราคาให้ถูกต้อง';
    } else {
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/products/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $filename = $upload_dir . 'prod_' . time() . '_' . rand(100, 999) . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $filename)) {
                    $image_url = $filename;
                }
            }
        }

        if (!$error) {
            $stmt = $pdo->prepare("UPDATE products SET category_id = ?, name = ?, description = ?, price = ?, stock_quantity = ?, image_url = ? WHERE id = ? AND seller_id = ?");
            $stmt->execute([$category_id, $name, $description, $price, $stock_quantity, $image_url, $product_id, $seller_id]);
            header("Location: seller_dashboard.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขสินค้า | DecorAura</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; }
        body { background: #f8f9fa; color: #333; }
        .form-card { max-width: 550px; margin: 30px auto; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 0.9rem; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 0.95rem; }
        .btn-submit { background: #2196F3; color: white; border: none; padding: 12px; width: 100%; border-radius: 5px; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>
    <div class="form-card">
        <h2>✏️ แก้ไขสินค้า</h2><br>
        <?php if($error): ?><p style="color:red; margin-bottom:15px;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>ชื่อสินค้า *</label>
                <input type="text" name="name" value="<?= htmlspecialchars($product['name']) ?>" required>
            </div>

            <div class="form-group">
                <label>หมวดหมู่</label>
                <select name="category_id">
                    <option value="">-- เลือกหมวดหมู่ --</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $product['category_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="display:flex; gap:10px;">
                <div style="flex:1;">
                    <label>ราคา (บาท) *</label>
                    <input type="number" name="price" step="0.01" min="0" value="<?= $product['price'] ?>" required>
                </div>
                <div style="flex:1;">
                    <label>จำนวนในคลัง (ชิ้น) *</label>
                    <input type="number" name="stock_quantity" min="0" value="<?= $product['stock_quantity'] ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>รายละเอียดสินค้า</label>
                <textarea name="description" rows="3"><?= htmlspecialchars($product['description']) ?></textarea>
            </div>

            <div class="form-group">
                <label>รูปภาพปัจจุบัน</label><br>
                <?php if(!empty($product['image_url'])): ?>
                    <img src="<?= htmlspecialchars($product['image_url']) ?>" style="width:70px; height:70px; object-fit:cover; border-radius:4px; margin-bottom:8px;">
                <?php endif; ?>
                <input type="file" name="image" accept="image/jpeg, image/png, image/webp">
            </div>

            <button type="submit" class="btn-submit">บันทึกการแก้ไข</button>
            <a href="seller_dashboard.php" style="display:block; text-align:center; margin-top:10px; color:#666; text-decoration:none;">ยกเลิก</a>
        </form>
    </div>
</body>
</html>