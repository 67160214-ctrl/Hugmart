<?php
session_start();
require_once __DIR__ . '/../src/Config/Database.php';

if (class_exists('Database')) {
    if (method_exists('Database', 'getConnection')) {
        $pdo = Database::getConnection();
    } elseif (method_exists('Database', 'getInstance')) {
        $pdo = Database::getInstance()->getConnection();
    } else {
        $db = new Database();
        $pdo = method_exists($db, 'getConnection') ? $db->getConnection() : ($db->pdo ?? $pdo);
    }
}

$productId = intval($_GET['id'] ?? 0);

// ดึงข้อมูลสินค้า พร้อมเชื่อมกับตาราง shops เพื่อดึงชื่อร้านค้า
$stmt = $pdo->prepare("
    SELECT p.*, s.shop_name 
    FROM products p 
    LEFT JOIN shops s ON p.shop_id = s.id 
    WHERE p.id = ?
");
$stmt->execute([$productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo "<div style='text-align:center; margin-top:50px; font-family:sans-serif;'><h2>ไม่พบสินค้าที่คุณต้องการ</h2><a href='/'>กลับสู่หน้าหลัก</a></div>";
    exit;
}

// กำหนดชื่อร้านค้า (ถ้าไม่มีให้แสดงเป็น Hugmart Official)
$storeName = $product['shop_name'] ?? 'Hugmart Official';

// หา order ล่าสุดของผู้ใช้ที่มีสินค้านี้อยู่ (ใช้ยืนยันว่าเคยซื้อ และเป็น order_id ของรีวิว)
function findPurchasedOrderId(PDO $pdo, $userId, $productId) {
    try {
        $q = $pdo->prepare("
            SELECT o.id
            FROM orders o
            JOIN order_items oi ON oi.order_id = o.id
            WHERE o.user_id = ? AND oi.product_id = ?
            ORDER BY o.id DESC
            LIMIT 1
        ");
        $q->execute([$userId, $productId]);
        $id = $q->fetchColumn();
        return $id ? intval($id) : null;
    } catch (PDOException $e) {
        error_log('[Hugmart review] findPurchasedOrderId: ' . $e->getMessage());
        return null;
    }
}

// CSRF token สำหรับฟอร์มรีวิวและการเพิ่มสินค้าลงรถเข็น
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

// ===== รับฟอร์มเขียนรีวิว =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_review') {
    $flash = ['type' => 'error', 'msg' => 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง'];

    if (!isset($_SESSION['user_id'])) {
        $flash['msg'] = 'กรุณาเข้าสู่ระบบก่อนเขียนรีวิว';
    } elseif (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $flash['msg'] = 'คำขอไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองใหม่';
    } else {
        $rating  = intval($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        if ($rating < 1 || $rating > 5) {
            $flash['msg'] = 'กรุณาเลือกจำนวนดาว 1-5 ดาว';
        } elseif (mb_strlen($comment, 'UTF-8') > 1000) {
            $flash['msg'] = 'ข้อความรีวิวต้องไม่เกิน 1,000 ตัวอักษร';
        } else {
            $orderId = findPurchasedOrderId($pdo, $_SESSION['user_id'], $productId);

            if (!$orderId) {
                $flash['msg'] = 'รีวิวได้เฉพาะผู้ที่เคยสั่งซื้อสินค้านี้เท่านั้น';
            } else {
                try {
                    $chk = $pdo->prepare("SELECT id FROM reviews WHERE user_id = ? AND product_id = ? LIMIT 1");
                    $chk->execute([$_SESSION['user_id'], $productId]);
                    $existingId = $chk->fetchColumn();

                    $commentValue = $comment !== '' ? $comment : null;

                    if ($existingId) {
                        $upd = $pdo->prepare("
                            UPDATE reviews
                            SET rating = ?, comment = ?, created_at = NOW()
                            WHERE id = ? AND user_id = ?
                        ");
                        $upd->execute([$rating, $commentValue, $existingId, $_SESSION['user_id']]);
                    } else {
                        $ins = $pdo->prepare("
                            INSERT INTO reviews (order_id, product_id, user_id, rating, comment)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $ins->execute([$orderId, $productId, $_SESSION['user_id'], $rating, $commentValue]);
                    }
                    $flash = ['type' => 'success', 'msg' => 'ขอบคุณสำหรับรีวิวของคุณ'];
                } catch (PDOException $e) {
                    error_log('[Hugmart review] save failed: ' . $e->getMessage());
                    $flash['msg'] = 'ไม่สามารถบันทึกรีวิวได้ กรุณาลองใหม่อีกครั้ง';
                }
            }
        }
    }

    $_SESSION['review_flash'] = $flash;
    header('Location: product.php?id=' . $productId . '#reviews');
    exit;
}

$flash = $_SESSION['review_flash'] ?? null;
unset($_SESSION['review_flash']);

$pName  = $product['name'] ?? $product['product_name'] ?? 'สินค้าไม่มีชื่อ';
$pPrice = floatval($product['price'] ?? $product['product_price'] ?? 0);
$pStock = intval($product['stock'] ?? $product['product_stock'] ?? 0);
$pDesc  = $product['description'] ?? 'ยังไม่มีรายละเอียดสินค้า';
$pCat   = $product['category'] ?? $product['category_name'] ?? 'ทั่วไป';
$pImg   = $product['image_url'] ?? $product['image'] ?? '/assets/images/placeholder.jpg';

// ===== เรตติ้งและยอดขายจริงจากฐานข้อมูล =====
$pRating = 0;
$pReviewsCount = 0;
try {
    $stmtR = $pdo->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS total FROM reviews WHERE product_id = ?");
    $stmtR->execute([$productId]);
    $rRow = $stmtR->fetch(PDO::FETCH_ASSOC);
    $pReviewsCount = intval($rRow['total'] ?? 0);
    $pRating = $pReviewsCount > 0 ? round(floatval($rRow['avg_rating']), 1) : 0;
} catch (PDOException $e) {
    $pRating = 0;
    $pReviewsCount = 0;
}

$pSold = 0;
try {
    $stmtS = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE product_id = ?");
    $stmtS->execute([$productId]);
    $pSold = intval($stmtS->fetchColumn());
} catch (PDOException $e) {
    $pSold = intval($product['sold'] ?? 0);
}

// ===== สัดส่วนดาวแต่ละระดับ =====
$starCounts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
try {
    $stmtD = $pdo->prepare("SELECT rating, COUNT(*) AS c FROM reviews WHERE product_id = ? GROUP BY rating");
    $stmtD->execute([$productId]);
    foreach ($stmtD->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $starCounts[intval($row['rating'])] = intval($row['c']);
    }
} catch (PDOException $e) {
}

// ===== รายการรีวิว =====
$reviews = [];
try {
    $stmtL = $pdo->prepare("
        SELECT r.*, u.full_name
        FROM reviews r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.product_id = ?
        ORDER BY r.created_at DESC
        LIMIT 50
    ");
    $stmtL->execute([$productId]);
    $reviews = $stmtL->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $stmtL = $pdo->prepare("SELECT r.* FROM reviews r WHERE r.product_id = ? ORDER BY r.created_at DESC LIMIT 50");
        $stmtL->execute([$productId]);
        $reviews = $stmtL->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        $reviews = [];
    }
}

// ===== สถานะของผู้ใช้ที่ล็อกอินอยู่: เคยซื้อไหม / เคยรีวิวแล้วไหม =====
$myReview = null;
$canReview = false;
if (isset($_SESSION['user_id'])) {
    try {
        $stmtM = $pdo->prepare("SELECT rating, comment FROM reviews WHERE product_id = ? AND user_id = ? LIMIT 1");
        $stmtM->execute([$productId, $_SESSION['user_id']]);
        $myReview = $stmtM->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        $myReview = null;
    }
    $canReview = $myReview !== null || findPurchasedOrderId($pdo, $_SESSION['user_id'], $productId) !== null;
}

// แสดงดาวเต็ม/ดาวว่าง
function renderStars($value, $sizeClass = 'text-xs') {
    $value = (int) round($value);
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $color = $i <= $value ? 'text-amber-400' : 'text-gray-300';
        $html .= '<i class="fa-solid fa-star ' . $color . ' ' . $sizeClass . '"></i>';
    }
    return $html;
}

// ตรวจสอบว่าเป็นหมวดหมู่เสื้อผ้า/แฟชั่นหรือไม่ เพื่อแสดงตัวเลือกไซส์
$isClothing = preg_match('/(เสื้อ|กางเกง|แฟชั่น|รองเท้า|ชุด)/ui', $pCat . ' ' . $pName);

$imagesList = [];
if (!empty($product['images_json'])) {
    $decoded = json_decode($product['images_json'], true);
    if (is_array($decoded)) {
        $imagesList = $decoded;
    }
}
if (empty($imagesList)) {
    $imagesList = [$pImg];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pName); ?> - Hugmart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-[#F5F5F5] font-sans text-gray-800 antialiased min-h-screen">

    <header class="bg-white border-b border-gray-200 h-16 sticky top-0 z-50 flex items-center justify-between px-8 shadow-sm">
        <a href="/" class="text-2xl font-black text-[#1B4332] flex items-center gap-2">
            <span class="bg-[#1B4332] text-white px-2.5 py-1 rounded text-lg">H</span> Hugmart
        </a>
        <div class="flex items-center gap-4 text-sm font-semibold">
            <a href="/seller/index.php" class="text-gray-600 hover:text-[#2D6A4F]">Seller Centre</a>
            <a href="cart.php" class="text-gray-600 hover:text-[#2D6A4F]"><i class="fa-solid fa-cart-shopping"></i> รถเข็น</a>
            <a href="/" class="text-[#2D6A4F]"><i class="fa-solid fa-house"></i> หน้าแรก</a>
        </div>
    </header>

    <!-- Toast แจ้งผลการเพิ่มสินค้า -->
    <div id="toast" class="fixed top-20 right-6 z-[60] hidden max-w-xs bg-white border border-gray-200 shadow-lg rounded-lg px-4 py-3 text-xs">
        <div id="toastMsg" class="font-semibold text-gray-800"></div>
        <a id="toastLink" href="cart.php" class="hidden mt-1 text-[#2D6A4F] font-bold hover:underline">ดูรถเข็น &gt;</a>
    </div>

    <main class="max-w-7xl mx-auto mt-6 px-4 pb-20">
        
        <div class="text-xs text-gray-500 mb-4 flex items-center gap-2">
            <a href="/" class="hover:underline">หน้าหลัก</a> &gt; 
            <span><?php echo htmlspecialchars($pCat); ?></span> &gt; 
            <span class="text-gray-800 truncate max-w-md"><?php echo htmlspecialchars($pName); ?></span>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 grid grid-cols-1 md:grid-cols-12 p-6 gap-8">
            
            <!-- รูปภาพสินค้า -->
            <div class="md:col-span-5 space-y-4">
                <div class="w-full h-[420px] border border-gray-200 rounded-lg overflow-hidden bg-white flex items-center justify-center p-2">
                    <img id="mainProductImg" src="<?php echo htmlspecialchars($imagesList[0]); ?>" class="max-w-full max-h-full object-contain">
                </div>
                <div class="grid grid-cols-5 gap-2">
                    <?php foreach($imagesList as $img): ?>
                    <div onclick="changeMainImage('<?php echo htmlspecialchars($img); ?>')" class="h-20 border border-gray-200 rounded cursor-pointer overflow-hidden hover:border-[#2D6A4F] bg-white flex items-center justify-center p-1">
                        <img src="<?php echo htmlspecialchars($img); ?>" class="max-w-full max-h-full object-contain">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ข้อมูลสินค้า -->
            <div class="md:col-span-7 space-y-4">
                <!-- แสดงชื่อร้านค้า -->
                <div class="text-xs text-[#2D6A4F] font-semibold flex items-center gap-1.5 bg-green-50 px-3 py-1.5 rounded-md w-fit border border-green-200">
                    <i class="fa-solid fa-store"></i> ร้านค้า: <?php echo htmlspecialchars($storeName); ?>
                </div>

                <h1 class="text-xl font-bold text-gray-800 leading-snug">
                    <?php echo htmlspecialchars($pName); ?>
                </h1>

                <!-- เรตติ้งและยอดขายจากข้อมูลจริง -->
                <div class="flex items-center gap-6 text-xs text-gray-500 border-b border-gray-100 pb-3">
                    <div class="flex items-center gap-1.5">
                        <?php if ($pReviewsCount > 0): ?>
                            <span class="text-amber-500 font-bold text-sm"><?php echo number_format($pRating, 1); ?></span>
                            <span><?php echo renderStars($pRating); ?></span>
                            <a href="#reviews" class="hover:underline">(รีวิว <?php echo number_format($pReviewsCount); ?>)</a>
                        <?php else: ?>
                            <a href="#reviews" class="text-gray-400 hover:underline">ยังไม่มีรีวิว</a>
                        <?php endif; ?>
                    </div>
                    <?php if ($pSold > 0): ?>
                    <div class="border-l pl-4 border-gray-200">
                        ขายแล้ว <span class="text-gray-800 font-bold"><?php echo number_format($pSold); ?></span> ชิ้น
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ราคา -->
                <div class="bg-gray-50 p-4 rounded-lg flex items-baseline gap-3">
                    <span class="text-2xl font-bold text-[#EE4D2D]">฿<?php echo number_format($pPrice, 2); ?></span>
                </div>

                <!-- แสดงไซส์เฉพาะหมวดหมู่เสื้อผ้า/แฟชั่น -->
                <?php if ($isClothing): ?>
                <div class="flex items-start gap-4 text-xs pt-2">
                    <span class="text-gray-400 w-24 pt-1.5">ไซส์</span>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach(['S', 'M', 'L', 'XL', '2XL', '3XL'] as $size): ?>
                        <button type="button" onclick="selectSize(this)" class="size-btn px-4 py-2 border border-gray-300 rounded hover:border-[#2D6A4F] hover:text-[#2D6A4F] font-medium transition">
                            <?php echo $size; ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- จัดส่ง -->
                <div class="space-y-2 text-xs text-gray-600 border-b border-gray-100 pb-4">
                    <div class="flex gap-4">
                        <span class="text-gray-400 w-24">การจัดส่ง</span>
                        <div>
                            <div class="flex items-center gap-1.5 font-semibold text-gray-800">
                                <i class="fa-solid fa-truck-fast text-[#2D6A4F]"></i> จัดส่งสินค้ามาตรฐาน
                            </div>
                            <span class="text-gray-400 text-[11px]">ได้รับสินค้าภายใน 2-3 วันทำการ</span>
                        </div>
                    </div>
                </div>

                <!-- จำนวนสินค้า -->
                <div class="flex items-center gap-4 text-xs">
                    <span class="text-gray-400 w-24">จำนวนสินค้า</span>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="decrementQty()" class="w-8 h-8 border border-gray-300 rounded flex items-center justify-center hover:bg-gray-100">-</button>
                        <input type="number" id="buyQty" value="1" min="1" max="<?php echo $pStock; ?>" class="w-16 h-8 text-center border border-gray-300 rounded text-xs focus:outline-none">
                        <button type="button" onclick="incrementQty(<?php echo $pStock; ?>)" class="w-8 h-8 border border-gray-300 rounded flex items-center justify-center hover:bg-gray-100">+</button>
                        <span class="text-gray-400 text-[11px] ml-2">มีสินค้าทั้งหมด <?php echo number_format($pStock); ?> ชิ้น</span>
                    </div>
                </div>

                <!-- ปุ่มซื้อสินค้า -->
                <div class="flex items-center gap-4 pt-4">
                    <button id="addCartBtn" onclick="addToCart()" class="flex-1 bg-[#2D6A4F]/10 hover:bg-[#2D6A4F]/20 text-[#2D6A4F] border border-[#2D6A4F] py-3 rounded-lg text-xs font-bold transition flex items-center justify-center gap-2 disabled:opacity-50">
                        <i class="fa-solid fa-cart-shopping"></i> เพิ่มไปยังรถเข็น
                    </button>
                    <button id="buyNowBtn" onclick="buyNow()" class="flex-1 bg-[#EE4D2D] hover:bg-[#d73a1a] text-white py-3 rounded-lg text-xs font-bold transition shadow disabled:opacity-50">
                        ซื้อสินค้า
                    </button>
                </div>
            </div>

        </div>

        <!-- รายละเอียดสินค้า -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mt-6 p-6 text-xs space-y-3">
            <h3 class="font-bold text-gray-800 text-sm border-b pb-3 bg-gray-50 px-4 -mx-6 -mt-6 rounded-t-lg">รายละเอียดสินค้า</h3>
            <div class="text-gray-600 leading-relaxed whitespace-pre-line pt-2">
                <?php echo nl2br(htmlspecialchars($pDesc)); ?>
            </div>
        </div>

        <!-- รีวิวสินค้า -->
        <div id="reviews" class="bg-white rounded-lg shadow-sm border border-gray-200 mt-6 p-6 text-xs scroll-mt-20">
            <h3 class="font-bold text-gray-800 text-sm border-b pb-3 bg-gray-50 px-4 -mx-6 -mt-6 rounded-t-lg">คะแนนและรีวิวสินค้า</h3>

            <?php if ($flash): ?>
                <div class="mt-4 px-4 py-3 rounded border <?php echo $flash['type'] === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-600'; ?>">
                    <?php echo htmlspecialchars($flash['msg']); ?>
                </div>
            <?php endif; ?>

            <!-- สรุปคะแนน -->
            <div class="mt-4 bg-[#F0F7F3] border border-[#D8EBDF] rounded-lg p-5 flex flex-col sm:flex-row sm:items-center gap-6">
                <div class="text-center sm:pr-6 sm:border-r border-[#D8EBDF]">
                    <?php if ($pReviewsCount > 0): ?>
                        <div class="text-3xl font-extrabold text-[#2D6A4F]"><?php echo number_format($pRating, 1); ?><span class="text-sm font-medium text-gray-500"> / 5</span></div>
                        <div class="mt-1"><?php echo renderStars($pRating, 'text-base'); ?></div>
                        <div class="text-gray-500 mt-1">จาก <?php echo number_format($pReviewsCount); ?> รีวิว</div>
                    <?php else: ?>
                        <div class="text-gray-400 py-2">ยังไม่มีรีวิว</div>
                    <?php endif; ?>
                </div>
                <div class="flex-1 space-y-1.5">
                    <?php foreach ([5, 4, 3, 2, 1] as $s):
                        $pct = $pReviewsCount > 0 ? ($starCounts[$s] / $pReviewsCount) * 100 : 0;
                    ?>
                    <div class="flex items-center gap-2">
                        <span class="w-10 text-gray-500"><?php echo $s; ?> ดาว</span>
                        <div class="flex-1 h-2 bg-white rounded-full overflow-hidden border border-[#D8EBDF]">
                            <div class="h-full bg-amber-400" style="width: <?php echo $pct; ?>%"></div>
                        </div>
                        <span class="w-8 text-right text-gray-500"><?php echo $starCounts[$s]; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ฟอร์มเขียนรีวิว -->
            <div class="mt-6 border border-gray-200 rounded-lg p-5">
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <div class="text-center text-gray-500 py-2">
                        <a href="login.php" class="text-[#2D6A4F] font-semibold hover:underline">เข้าสู่ระบบ</a> เพื่อเขียนรีวิวและให้ดาวสินค้านี้
                    </div>
                <?php elseif (!$canReview): ?>
                    <div class="text-center text-gray-500 py-2">
                        <i class="fa-solid fa-circle-info text-[#2D6A4F]"></i>
                        รีวิวได้เฉพาะผู้ที่เคยสั่งซื้อสินค้านี้เท่านั้น
                    </div>
                <?php else: ?>
                    <h4 class="font-bold text-gray-800 text-sm mb-3">
                        <?php echo $myReview ? 'แก้ไขรีวิวของคุณ' : 'เขียนรีวิวสินค้านี้'; ?>
                    </h4>
                    <form method="POST" action="product.php?id=<?php echo $productId; ?>" onsubmit="return validateReview()">
                        <input type="hidden" name="action" value="submit_review">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="rating" id="ratingInput" value="<?php echo intval($myReview['rating'] ?? 0); ?>">

                        <div class="flex items-center gap-3 mb-3">
                            <span class="text-gray-500">ให้คะแนน</span>
                            <div id="starPicker" class="flex gap-1 text-2xl">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <button type="button" data-value="<?php echo $i; ?>" class="star-btn text-gray-300 hover:scale-110 transition" aria-label="<?php echo $i; ?> ดาว">
                                        <i class="fa-solid fa-star"></i>
                                    </button>
                                <?php endfor; ?>
                            </div>
                            <span id="ratingLabel" class="text-amber-500 font-semibold"></span>
                        </div>

                        <textarea name="comment" rows="4" maxlength="1000" placeholder="แบ่งปันประสบการณ์การใช้สินค้านี้ให้ผู้ซื้อคนอื่น (ไม่บังคับ)"
                            class="w-full border border-gray-300 rounded p-3 text-xs focus:outline-none focus:border-[#2D6A4F]"><?php echo htmlspecialchars($myReview['comment'] ?? ''); ?></textarea>

                        <div class="mt-3 flex justify-end">
                            <button type="submit" class="bg-[#2D6A4F] hover:bg-[#1B4332] text-white px-6 py-2.5 rounded font-bold transition">
                                <?php echo $myReview ? 'บันทึกการแก้ไข' : 'ส่งรีวิว'; ?>
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <!-- รายการรีวิว -->
            <div class="mt-6 divide-y divide-gray-100">
                <?php if (empty($reviews)): ?>
                    <div class="text-center text-gray-400 py-8">ยังไม่มีรีวิวสำหรับสินค้านี้</div>
                <?php else: ?>
                    <?php foreach ($reviews as $rv):
                        $reviewerName = trim($rv['full_name'] ?? '') !== '' ? $rv['full_name'] : 'ผู้ใช้ Hugmart';
                        $initial = mb_substr($reviewerName, 0, 1, 'UTF-8');
                    ?>
                    <div class="py-4 flex gap-3">
                        <div class="w-9 h-9 bg-[#52B788] text-[#1B4332] rounded-full flex items-center justify-center font-bold text-sm shrink-0">
                            <?php echo htmlspecialchars($initial); ?>
                        </div>
                        <div class="flex-1">
                            <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($reviewerName); ?></div>
                            <div class="mt-0.5"><?php echo renderStars($rv['rating']); ?></div>
                            <div class="text-[11px] text-gray-400 mt-0.5"><?php echo date('d/m/Y H:i', strtotime($rv['created_at'])); ?></div>
                            <?php if (!empty($rv['comment'])): ?>
                                <p class="mt-2 text-gray-700 leading-relaxed whitespace-pre-line"><?php echo htmlspecialchars($rv['comment']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <script>
        const PRODUCT_ID = <?php echo $productId; ?>;
        const CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token']); ?>;
        const PRODUCT_STOCK = <?php echo $pStock; ?>;

        function changeMainImage(imgSrc) {
            document.getElementById('mainProductImg').src = imgSrc;
        }

        function selectSize(btn) {
            document.querySelectorAll('.size-btn').forEach(b => {
                b.classList.remove('border-[#2D6A4F]', 'text-[#2D6A4F]', 'bg-green-50', 'font-bold');
            });
            btn.classList.add('border-[#2D6A4F]', 'text-[#2D6A4F]', 'bg-green-50', 'font-bold');
        }

        function decrementQty() {
            let qtyInput = document.getElementById('buyQty');
            let val = parseInt(qtyInput.value);
            if (val > 1) {
                qtyInput.value = val - 1;
            }
        }

        function incrementQty(maxStock) {
            let qtyInput = document.getElementById('buyQty');
            let val = parseInt(qtyInput.value);
            if (val < maxStock) {
                qtyInput.value = val + 1;
            }
        }

        // ===== รถเข็น / ซื้อทันที =====
        let toastTimer = null;
        function showToast(message, showCartLink) {
            const toast = document.getElementById('toast');
            document.getElementById('toastMsg').textContent = message;
            document.getElementById('toastLink').classList.toggle('hidden', !showCartLink);
            toast.classList.remove('hidden');
            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => toast.classList.add('hidden'), 3500);
        }

        function getSelectedQty() {
            let qty = parseInt(document.getElementById('buyQty').value) || 1;
            if (qty < 1) qty = 1;
            if (PRODUCT_STOCK > 0 && qty > PRODUCT_STOCK) qty = PRODUCT_STOCK;
            document.getElementById('buyQty').value = qty;
            return qty;
        }

        async function sendCartAction(action) {
            const res = await fetch('cart_action.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN
                },
                body: JSON.stringify({
                    action: action,
                    product_id: PRODUCT_ID,
                    quantity: getSelectedQty()
                })
            });
            return res.json();
        }

        async function addToCart() {
            const btn = document.getElementById('addCartBtn');
            btn.disabled = true;
            try {
                const data = await sendCartAction('add');
                if (data.login_required) {
                    window.location.href = 'login.php?error=' + encodeURIComponent('กรุณาเข้าสู่ระบบก่อนเพิ่มสินค้าลงรถเข็น');
                    return;
                }
                showToast(data.message, data.ok);
            } catch (e) {
                showToast('ไม่สามารถเพิ่มสินค้าได้ กรุณาลองใหม่อีกครั้ง', false);
            } finally {
                btn.disabled = false;
            }
        }

        async function buyNow() {
            const btn = document.getElementById('buyNowBtn');
            btn.disabled = true;
            try {
                const data = await sendCartAction('buy_now');
                if (data.login_required) {
                    window.location.href = 'login.php?error=' + encodeURIComponent('กรุณาเข้าสู่ระบบก่อนสั่งซื้อสินค้า');
                    return;
                }
                if (data.ok) {
                    window.location.href = 'payment.php?items=' + encodeURIComponent(data.cart_item_id);
                    return;
                }
                showToast(data.message, false);
            } catch (e) {
                showToast('ไม่สามารถดำเนินการได้ กรุณาลองใหม่อีกครั้ง', false);
            } finally {
                btn.disabled = false;
            }
        }

        // ===== ระบบเลือกดาวสำหรับเขียนรีวิว =====
        (function () {
            const picker = document.getElementById('starPicker');
            if (!picker) return;

            const input = document.getElementById('ratingInput');
            const label = document.getElementById('ratingLabel');
            const buttons = picker.querySelectorAll('.star-btn');
            const labels = ['', 'แย่มาก', 'ไม่ค่อยดี', 'พอใช้', 'ดี', 'ดีเยี่ยม'];

            function paint(value) {
                buttons.forEach(btn => {
                    const active = parseInt(btn.dataset.value) <= value;
                    btn.classList.toggle('text-amber-400', active);
                    btn.classList.toggle('text-gray-300', !active);
                });
                label.textContent = value > 0 ? labels[value] : '';
            }

            buttons.forEach(btn => {
                btn.addEventListener('click', () => {
                    input.value = btn.dataset.value;
                    paint(parseInt(btn.dataset.value));
                });
                btn.addEventListener('mouseenter', () => paint(parseInt(btn.dataset.value)));
            });
            picker.addEventListener('mouseleave', () => paint(parseInt(input.value) || 0));

            paint(parseInt(input.value) || 0);
        })();

        function validateReview() {
            const val = parseInt(document.getElementById('ratingInput').value) || 0;
            if (val < 1) {
                alert('กรุณาเลือกจำนวนดาวก่อนส่งรีวิว');
                return false;
            }
            return true;
        }
    </script>
</body>
</html>