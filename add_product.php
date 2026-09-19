<?php
session_start();
require_once 'config/db.php';

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user'])) {
    header("Location: auth.php");
    exit();
}

$user_id = $_SESSION['user']['id'];
$error = '';

// ดึงหมวดหมู่สินค้าทั้งหมดสำหรับแสดงใน Dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $stock = trim($_POST['stock'] ?? '');
    $category_id = trim($_POST['category_id'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    $image_path = '';
    
    // จัดการอัปโหลดรูปภาพ
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['image']['tmp_name'];
        $file_name = time() . '_' . basename($_FILES['image']['name']);
        $upload_dir = 'uploads/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $destination = $upload_dir . $file_name;
        if (move_uploaded_file($file_tmp, $destination)) {
            $image_path = $destination;
        }
    }

    if (empty($name) || empty($price) || empty($stock) || empty($category_id)) {
        $error = 'กรุณากรอกข้อมูลในช่องที่มีเครื่องหมาย * ให้ครบถ้วน';
    } else {
        $stmt = $pdo->prepare("INSERT INTO products (name, price, stock, category_id, description, image, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$name, $price, $stock, $category_id, $description, $image_path, $user_id])) {
            header("Location: seller_center.php");
            exit();
        } else {
            $error = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>วางขายสินค้าใหม่ - Hugmart</title>
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
        .container { max-width: 800px; margin: 40px auto; padding: 0 20px; }
        .card { background: var(--surface); border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); padding: 30px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 1px solid var(--border); padding-bottom: 15px; }
        .card-header h2 { font-size: 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 10px; color: var(--primary); }
        .back-link { color: var(--text-muted); text-decoration: none; font-size: 0.9rem; transition: color 0.2s; }
        .back-link:hover { color: var(--text-main); }
        
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-weight: 500; margin-bottom: 8px; font-size: 0.95rem; }
        .form-label span { color: #ef4444; }
        .form-control, .form-select { width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.95rem; outline: none; transition: border-color 0.2s; background: #fff; }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.1); }
        textarea.form-control { resize: vertical; min-height: 100px; }
        
        .btn-submit { background: var(--primary); color: white; border: none; width: 100%; padding: 12px; font-size: 1rem; font-weight: 500; border-radius: 8px; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 10px; }
        .btn-submit:hover { background: var(--primary-hover); }
        
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; }
    </style>
</head>
<body>

    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2><i class="fa-solid fa-plus-circle"></i> วางขายสินค้าใหม่</h2>
                <a href="seller_center.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> ย้อนกลับ</a>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">ชื่อสินค้า <span>*</span></label>
                    <input type="text" name="name" class="form-control" required placeholder="ระบุชื่อสินค้าของคุณ">
                </div>

                <div class="form-group">
                    <label class="form-label">หมวดหมู่สินค้า <span>*</span></label>
                    <select name="category_id" class="form-select" required>
                        <option value="">-- เลือกหมวดหมู่สินค้า --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">ราคา (บาท) <span>*</span></label>
                    <input type="number" step="0.01" name="price" class="form-control" required placeholder="0.00">
                </div>

                <div class="form-group">
                    <label class="form-label">จำนวนสินค้าในคลัง <span>*</span></label>
                    <input type="number" name="stock" class="form-control" value="1" min="1" required>
                </div>

                <div class="form-group">
                    <label class="form-label">รายละเอียดสินค้า</label>
                    <textarea name="description" class="form-control" placeholder="อธิบายรายละเอียด สภาพสินค้า หรือตำหนิ (ถ้ามี)"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">รูปภาพสินค้า</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-check"></i> บันทึกและลงขายสินค้า
                </button>
            </form>
        </div>
    </div>

</body>
</html>