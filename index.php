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

// ดึงค่าการค้นหาและหมวดหมู่ไว้ใช้งานร่วมกัน
$selected_cat = $_GET['cat'] ?? 'all';
$search = trim($_GET['q'] ?? '');

// เพิ่มสินค้าลงตาราง cart_items
if (isset($_POST['add_to_cart'])) {
    $product_id = (int)$_POST['product_id'];

    // ดึงข้อมูลสินค้าเพื่อตรวจสอบสต็อก (ใช้คอลัมน์ stock ตามตารางจริง)
    $stmt_stock = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt_stock->execute([$product_id]);
    $product_info = $stmt_stock->fetch();

    if ($product_info) {
        $current_stock = (int)($product_info['stock'] ?? 0);

        if ($current_stock > 0) {
            $stmt_check = $pdo->prepare("SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ?");
            $stmt_check->execute([$user_id, $product_id]);
            $item = $stmt_check->fetch();

            if ($item) {
                if ($item['quantity'] < $current_stock) {
                    $stmt_update = $pdo->prepare("UPDATE cart_items SET quantity = quantity + 1 WHERE id = ?");
                    $stmt_update->execute([$item['id']]);
                }
            } else {
                $stmt_insert = $pdo->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, 1)");
                $stmt_insert->execute([$user_id, $product_id]);
            }
        }
    }

    $redirect_url = "index.php?cat=" . urlencode($selected_cat);
    if (!empty($search)) {
        $redirect_url .= "&q=" . urlencode($search);
    }
    header("Location: " . $redirect_url);
    exit();
}

// ดึงรายการหมวดหมู่
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// ดึงรายการสินค้าตามหมวดหมู่
$sql = "SELECT * FROM products WHERE 1=1";
$params = [];

if ($selected_cat !== 'all') {
    $sql .= " AND category_id = ?";
    $params[] = (int)$selected_cat;
}

if (!empty($search)) {
    $sql .= " AND name LIKE ?";
    $params[] = "%{$search}%";
}

$sql .= " ORDER BY id DESC";
$stmt_p = $pdo->prepare($sql);
$stmt_p->execute($params);
$products = $stmt_p->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hugmart - ตลาดซื้อขายสินค้ามือสองคุณภาพ</title>
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
            --badge-used: #f59e0b;
        }

        * { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0; 
            font-family: 'Prompt', system-ui, -apple-system, sans-serif; 
        }

        body { 
            background: var(--bg-body); 
            color: var(--text-main); 
            line-height: 1.6;
        }

        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }

        .hero-banner {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: white;
            padding: 30px 28px;
            border-radius: 12px;
            margin: 20px auto;
            box-shadow: 0 8px 20px -4px rgba(15, 23, 42, 0.12);
        }
        .hero-text h1 { 
            font-size: 1.6rem; 
            font-weight: 600; 
            margin-bottom: 6px; 
            color: #f8fafc; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            line-height: 1.3;
        }
        .hero-text h1 i { color: var(--primary); }
        .hero-text p { color: #94a3b8; font-size: 0.95rem; font-weight: 300; }

        .category-bar { 
            background: var(--surface); 
            padding: 12px 15px; 
            border-radius: 10px; 
            display: flex; 
            gap: 10px; 
            margin-bottom: 25px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.05); 
            border: 1px solid var(--border);
            overflow-x: auto; 
        }
        .cat-btn { 
            padding: 8px 18px; 
            background: #f1f5f9; 
            text-decoration: none; 
            color: var(--text-main); 
            border-radius: 20px; 
            font-size: 0.88rem; 
            font-weight: 400;
            white-space: nowrap; 
            transition: all 0.2s ease; 
        }
        .cat-btn.active, .cat-btn:hover { 
            background: var(--primary); 
            color: white; 
            font-weight: 500;
        }

        .product-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); 
            gap: 20px; 
            margin-bottom: 40px;
        }
        .product-card { 
            background: var(--surface); 
            border-radius: 10px; 
            overflow: hidden; 
            border: 1px solid var(--border);
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
            transition: all 0.25s ease; 
            position: relative;
        }
        .product-card:hover { 
            transform: translateY(-4px); 
            box-shadow: 0 12px 20px -5px rgba(0, 0, 0, 0.08); 
            border-color: #cbd5e1;
        }
        
        .badge-used {
            position: absolute;
            top: 10px;
            left: 10px;
            background: var(--badge-used);
            color: white;
            font-size: 0.72rem;
            font-weight: 500;
            padding: 3px 9px;
            border-radius: 4px;
            z-index: 2;
            box-shadow: 0 2px 4px rgba(0,0,0,0.15);
        }

        .img-box { height: 200px; width: 100%; background: #f8fafc; position: relative; overflow: hidden; }
        .img-box img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease; }
        .product-card:hover .img-box img { transform: scale(1.04); }
        
        .info-box { padding: 14px; }
        .product-title { 
            font-size: 0.95rem; 
            font-weight: 500;
            color: var(--text-main);
            height: 2.8rem; 
            overflow: hidden; 
            margin-bottom: 10px; 
            line-height: 1.4; 
            display: -webkit-box; 
            -webkit-line-clamp: 2; 
            -webkit-box-orient: vertical; 
        }
        .price-row { display: flex; align-items: baseline; justify-content: space-between; }
        .price-box { color: var(--primary); font-size: 1.25rem; font-weight: 600; }
        .stock-label { font-size: 0.8rem; color: var(--text-muted); font-weight: 300; }

        .card-action { padding: 0 14px 14px 14px; }
        .btn-add { 
            background: var(--primary); 
            color: white; 
            border: none; 
            width: 100%; 
            padding: 9px; 
            font-size: 0.9rem;
            font-weight: 500; 
            cursor: pointer; 
            border-radius: 6px; 
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-add:hover { background: var(--primary-hover); }
        .btn-add:disabled { background: #cbd5e1; cursor: not-allowed; color: #94a3b8; }
        
        .empty-box { background: var(--surface); padding: 60px 20px; text-align: center; border-radius: 10px; color: var(--text-muted); border: 1px solid var(--border); }
    </style>
</head>
<body>

    <?php include 'includes/header.php'; ?>

    <div class="container">
        <div class="hero-banner">
            <div class="hero-text">
                <h1><i class="fa-solid fa-handshake"></i> Hugmart Marketplace</h1>
                <p>แหล่งรวมสินค้ามือสองสภาพดี คัดสรรคุณภาพ เพื่อความคุ้มค่าส่งตรงถึงมือคุณ</p>
            </div>
        </div>

        <div class="category-bar">
            <?php $search_param = !empty($search) ? '&q=' . urlencode($search) : ''; ?>
            <a href="index.php?cat=all<?= $search_param ?>" class="cat-btn <?= $selected_cat === 'all' ? 'active' : '' ?>">
                <i class="fa-solid fa-border-all"></i> สินค้าทั้งหมด
            </a>
            <?php foreach ($categories as $cat): ?>
                <a href="index.php?cat=<?= $cat['id'] ?><?= $search_param ?>" class="cat-btn <?= $selected_cat == $cat['id'] ? 'active' : '' ?>">
                    <?= htmlspecialchars($cat['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($products)): ?>
            <div class="empty-box">
                <i class="fa-solid fa-box-open" style="font-size: 2.5rem; margin-bottom: 10px; color: #cbd5e1;"></i>
                <p>ไม่พบรายการสินค้าในขณะนี้</p>
            </div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($products as $item): ?>
                    <?php 
                    // ดึงสต็อกและรูปภาพให้ตรงกับคอลัมน์จริงในตาราง products (`stock` และ `image`)
                    $stock = (int)($item['stock'] ?? 0); 
                    ?>
                    <div class="product-card">
                        <span class="badge-used"><i class="fa-solid fa-tag"></i> มือสอง</span>
                        
                        <div>
                            <div class="img-box">
                                <?php $img = !empty($item['image']) ? $item['image'] : 'assets/images/no-image.png'; ?>
                                <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                            </div>
                            <div class="info-box">
                                <div class="product-title" title="<?= htmlspecialchars($item['name']) ?>">
                                    <?= htmlspecialchars($item['name']) ?>
                                </div>
                                <div class="price-row">
                                    <div class="price-box">฿<?= number_format($item['price'], 2) ?></div>
                                    <div class="stock-label">เหลือ <?= $stock ?> ชิ้น</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-action">
                            <form method="POST">
                                <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                <button type="submit" name="add_to_cart" class="btn-add" <?= $stock <= 0 ? 'disabled' : '' ?>>
                                    <?php if ($stock > 0): ?>
                                        <i class="fa-solid fa-cart-plus"></i> เพิ่มลงตะกร้า
                                    <?php else: ?>
                                        <i class="fa-solid fa-ban"></i> สินค้าหมด
                                    <?php endif; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>