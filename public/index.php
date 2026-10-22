<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../src/Config/Database.php';

// ดึงข้อมูลรูปโปรไฟล์ล่าสุดของผู้ใช้จากฐานข้อมูล
$userAvatar = '';
if (isset($_SESSION['user_id'])) {
    try {
        $stmtUsr = $pdo->prepare("SELECT avatar_url, full_name FROM users WHERE id = ?");
        $stmtUsr->execute([$_SESSION['user_id']]);
        $usrData = $stmtUsr->fetch(PDO::FETCH_ASSOC);
        if ($usrData) {
            $userAvatar = $usrData['avatar_url'] ?? '';
            $_SESSION['full_name'] = $usrData['full_name'];
            $_SESSION['avatar_url'] = $userAvatar;
        }
    } catch (PDOException $e) {
        $userAvatar = $_SESSION['avatar_url'] ?? '';
    }
}

// ดึงหมวดหมู่สินค้าทั้งหมดจากฐานข้อมูล
try {
    $stmtCat = $pdo->query("SELECT * FROM categories LIMIT 30");
    $categories = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
}

// ดึงสินค้าแนะนำ พร้อมเชื่อมตาราง shops เพื่อให้แสดงชื่อร้านค้าจริง (เช่น TCG SHOP)
try {
    $stmtProd = $pdo->query("
        SELECT p.*, s.shop_name AS store_name,
            (SELECT AVG(r.rating) FROM reviews r WHERE r.product_id = p.id) AS avg_rating,
            (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id) AS review_count,
            (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.product_id = p.id) AS sold_count
        FROM products p 
        LEFT JOIN shops s ON p.shop_id = s.id 
        ORDER BY p.id DESC LIMIT 12
    ");
    $products = $stmtProd->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $stmtProd = $pdo->query("
            SELECT p.*, s.shop_name AS store_name,
                NULL AS avg_rating, 0 AS review_count, 0 AS sold_count
            FROM products p 
            LEFT JOIN shops s ON p.shop_id = s.id 
            ORDER BY p.id DESC LIMIT 12
        ");
        $products = $stmtProd->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        $products = [];
    }
}

// นับจำนวนสินค้าในรถเข็น
$cartCount = 0;
if (isset($_SESSION['user_id'])) {
    try {
        $stmtCart = $pdo->prepare("
            SELECT SUM(ci.quantity) as total 
            FROM carts c 
            JOIN cart_items ci ON c.id = ci.cart_id 
            WHERE c.user_id = ?
        ");
        $stmtCart->execute([$_SESSION['user_id']]);
        $cartCount = $stmtCart->fetchColumn() ?: 0;
    } catch (PDOException $e) {
        $cartCount = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hugmart | ตลาดออนไลน์คุณภาพ ช้อปคุ้ม ส่งไว ได้ของชัวร์</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans text-gray-800">

    <!-- Header -->
    <header class="bg-[#1B4332] text-white sticky top-0 z-50 shadow-md">
        <div class="max-w-7xl mx-auto px-4 text-xs py-1.5 flex justify-between items-center border-b border-[#2D6A4F] text-green-100">
            <div class="flex space-x-4">
                <a href="/seller/index.php" class="hover:text-white">Seller Centre</a>
                <span>|</span>
                <a href="/seller/index.php" class="hover:text-white">ลงขายสินค้า</a>
            </div>
            <div class="flex space-x-4 items-center">
                
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="relative group py-1">
                        <div class="flex items-center space-x-2 cursor-pointer py-1">
                            <div class="w-5 h-5 bg-[#52B788] text-[#1B4332] rounded-full flex items-center justify-center font-bold text-[10px] overflow-hidden">
                                <?php if (!empty($userAvatar)): ?>
                                    <img src="<?php echo htmlspecialchars($userAvatar); ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <?php echo mb_substr($_SESSION['full_name'] ?? 'U', 0, 1, 'UTF-8'); ?>
                                <?php endif; ?>
                            </div>
                            <span class="font-semibold text-white"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'สมาชิก'); ?></span>
                        </div>

                        <div class="absolute right-0 top-full pt-1 w-48 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-150 ease-out z-50">
                            <div class="bg-white text-gray-800 rounded shadow-lg border border-gray-100 py-1 text-xs">
                                <a href="profile.php" class="flex items-center gap-2.5 px-4 py-2 hover:bg-gray-50 text-gray-700 hover:text-[#2D6A4F]">
                                    <i class="fa-solid fa-user text-gray-400 w-4"></i>
                                    <span>บัญชีของฉัน</span>
                                </a>
                                <a href="orders.php" class="flex items-center gap-2.5 px-4 py-2 hover:bg-gray-50 text-gray-700 hover:text-[#2D6A4F]">
                                    <i class="fa-solid fa-box text-gray-400 w-4"></i>
                                    <span>คำสั่งซื้อของฉัน</span>
                                </a>
                                <a href="/seller/index.php" class="flex items-center gap-2.5 px-4 py-2 hover:bg-gray-50 text-gray-700 hover:text-[#2D6A4F] border-t border-gray-100">
                                    <i class="fa-solid fa-store text-gray-400 w-4"></i>
                                    <span>หน้าร้านค้าของคุณ</span>
                                </a>
                                <a href="logout.php" class="flex items-center gap-2.5 px-4 py-2 hover:bg-gray-50 text-red-600 border-t border-gray-100">
                                    <i class="fa-solid fa-arrow-right-from-bracket text-red-400 w-4"></i>
                                    <span>ออกจากระบบ</span>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="register.php" class="font-semibold hover:text-white">สมัครใหม่</a>
                    <span>|</span>
                    <a href="login.php" class="font-semibold hover:text-white">เข้าสู่ระบบ</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Search Header -->
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between gap-6">
            <a href="index.php" class="text-3xl font-extrabold tracking-tight text-white flex items-center gap-2">
                <i class="fa-solid fa-bag-shopping text-[#52B788]"></i> Hugmart
            </a>

            <!-- Search Form -->
            <div class="flex-1 max-w-2xl">
                <form action="search.php" method="GET" class="flex bg-white rounded-md overflow-hidden p-1 shadow">
                    <input type="text" name="q" placeholder="ค้นหาชื่อสินค้าที่ต้องการ..." class="w-full px-4 py-2 text-sm text-gray-800 focus:outline-none">
                    <button type="submit" class="bg-[#2D6A4F] hover:bg-[#1B4332] text-white px-6 py-2 rounded transition">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                </form>
            </div>

            <a href="cart.php" class="relative p-2 text-2xl hover:text-green-200 transition">
                <i class="fa-solid fa-cart-shopping"></i>
                <?php if (!empty($cartCount) && $cartCount > 0): ?>
                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs w-5 h-5 rounded-full flex items-center justify-center font-bold shadow">
                        <?php echo $cartCount; ?>
                    </span>
                <?php endif; ?>
            </a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-6 space-y-6">

        <!-- Banners Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="md:col-span-2 bg-gradient-to-r from-[#1B4332] to-[#2D6A4F] rounded-lg p-8 text-white flex flex-col justify-between relative overflow-hidden shadow">
                <div class="z-10">
                    <span class="bg-[#52B788] text-[#1B4332] text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider">Hugmart 11.11</span>
                    <h1 class="text-3xl font-extrabold mt-3">ครบ คุ้ม ไว การันตีสินค้าคุณภาพ</h1>
                    <p class="mt-2 text-green-100 text-sm">เริ่มช้อปสินค้าราคาพิเศษลดสูงสุด 70% ส่งฟรีทั่วไทย</p>
                </div>
                <div class="z-10 mt-6">
                    <a href="#" class="bg-[#52B788] hover:bg-white text-[#1B4332] font-bold px-6 py-2.5 rounded-md transition inline-block text-sm">ช้อปเลยตอนนี้</a>
                </div>
                <i class="fa-solid fa-store absolute -right-6 -bottom-6 text-9xl text-white opacity-10"></i>
            </div>

            <div class="flex flex-col gap-3">
                <div class="bg-emerald-800 rounded-lg p-4 text-white flex-1 flex flex-col justify-center shadow">
                    <span class="text-xs text-green-300">ดีลประจำวัน</span>
                    <h3 class="font-bold text-lg">สินค้าสุขภาพ ลด 50%</h3>
                </div>
                <div class="bg-teal-900 rounded-lg p-4 text-white flex-1 flex flex-col justify-center shadow">
                    <span class="text-xs text-teal-300">เปิดร้านค้าฟรี</span>
                    <h3 class="font-bold text-lg">สมัครเป็นผู้ขาย Hugmart</h3>
                </div>
            </div>
        </div>

        <!-- หมวดหมู่สินค้า (Categories Slider) -->
        <div class="bg-white rounded-lg shadow p-4 relative">
            <div class="flex justify-between items-center mb-4 pb-2 border-b">
                <h3 class="text-base font-bold text-gray-700 uppercase tracking-wide">หมวดหมู่สินค้า</h3>
                <div class="flex gap-2">
                    <button onclick="scrollCategories(-300)" class="w-8 h-8 rounded-full bg-gray-100 hover:bg-[#2D6A4F] hover:text-white flex items-center justify-center text-gray-600 transition shadow-sm cursor-pointer">
                        <i class="fa-solid fa-chevron-left text-xs"></i>
                    </button>
                    <button onclick="scrollCategories(300)" class="w-8 h-8 rounded-full bg-gray-100 hover:bg-[#2D6A4F] hover:text-white flex items-center justify-center text-gray-600 transition shadow-sm cursor-pointer">
                        <i class="fa-solid fa-chevron-right text-xs"></i>
                    </button>
                </div>
            </div>

            <?php if (empty($categories)): ?>
                <div class="text-center text-gray-400 py-4 text-sm">ยังไม่มีหมวดหมู่สินค้าในระบบ</div>
            <?php else: ?>
                <div id="categoryContainer" class="flex gap-3 overflow-x-auto scroll-smooth no-scrollbar pb-2 pt-1">
                    <?php foreach ($categories as $cat): ?>
                        <a href="category.php?id=<?php echo $cat['id']; ?>" class="min-w-[110px] max-w-[110px] border rounded-lg p-3 flex flex-col items-center hover:border-[#2D6A4F] hover:shadow-md transition text-center group bg-white shrink-0">
                            <div class="w-16 h-16 rounded-full overflow-hidden mb-2 border border-gray-100 group-hover:scale-105 transition duration-200">
                                <img src="<?php echo htmlspecialchars($cat['image_url'] ?? 'assets/images/no-image.png'); ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>" class="w-full h-full object-cover">
                            </div>
                            <span class="text-[11px] font-medium text-gray-700 group-hover:text-[#2D6A4F] line-clamp-2 h-8 leading-4">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- สินค้าแนะนำสำหรับคุณ -->
        <div class="space-y-4">
            <div class="border-b-2 border-[#2D6A4F] pb-2 flex justify-between items-center">
                <h3 class="text-lg font-bold text-[#1B4332] flex items-center gap-2">
                    <i class="fa-solid fa-leaf text-[#52B788]"></i> สินค้าแนะนำสำหรับคุณ
                </h3>
            </div>

            <?php if (empty($products)): ?>
                <div class="bg-white p-8 text-center text-gray-500 rounded-lg shadow">
                    ยังไม่มีสินค้าในระบบขณะนี้
                </div>
            <?php else: ?>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                    <?php foreach ($products as $p): ?>
                        <?php
                            $reviewCount = (int)($p['review_count'] ?? 0);
                            $avgRating   = $reviewCount > 0 ? round((float)$p['avg_rating'], 1) : null;
                            $soldCount   = (int)($p['sold_count'] ?? 0);
                        ?>
                        <a href="product.php?id=<?php echo $p['id']; ?>" class="bg-white rounded-lg shadow hover:shadow-lg transition flex flex-col justify-between overflow-hidden group border hover:border-[#2D6A4F]">
                            <div>
                                <div class="relative pt-[100%] bg-gray-100">
                                    <img src="<?php echo htmlspecialchars($p['image_url'] ?: 'assets/images/no-image.png'); ?>" class="absolute top-0 left-0 w-full h-full object-cover group-hover:scale-105 transition duration-300">
                                </div>
                                <div class="p-3">
                                    <span class="text-[11px] text-[#2D6A4F] font-semibold block mb-0.5">
                                        <?php echo htmlspecialchars($p['store_name'] ?? 'Hugmart Official'); ?>
                                    </span>
                                    <h4 class="text-xs font-medium text-gray-800 line-clamp-2 h-8 leading-4 mb-2">
                                        <?php echo htmlspecialchars($p['name']); ?>
                                    </h4>
                                    <div class="flex items-baseline space-x-1">
                                        <span class="text-sm font-bold text-[#2D6A4F]">฿<?php echo number_format($p['price'], 0); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="px-3 pb-3 pt-0 flex justify-between items-center text-[11px] text-gray-400 min-h-[28px]">
                                <?php if ($avgRating !== null): ?>
                                    <span class="text-yellow-500"><i class="fa-solid fa-star"></i> <?php echo number_format($avgRating, 1); ?></span>
                                <?php else: ?>
                                    <span></span>
                                <?php endif; ?>
                                <?php if ($soldCount > 0): ?>
                                    <span>ขายแล้ว <?php echo number_format($soldCount); ?> ชิ้น</span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- Footer -->
    <footer class="bg-[#1B4332] text-white mt-12 pt-10 pb-6 text-sm border-t-4 border-[#52B788]">
        <div class="max-w-7xl mx-auto px-4 grid grid-cols-1 md:grid-cols-4 gap-8 mb-8">
            <div>
                <h4 class="font-bold text-base mb-3 text-[#52B788]">Hugmart</h4>
                <p class="text-xs text-green-100 leading-relaxed">มาร์เก็ตเพลสออนไลน์ยุคใหม่ รวมสินค้าออร์แกนิกและไลฟ์สไตล์ที่เป็นมิตรต่อสิ่งแวดล้อม เพื่อสุขภาพที่ดีอย่างยั่งยืน</p>
            </div>
            <div>
                <h4 class="font-bold text-base mb-3 text-[#52B788]">การดูแลลูกค้า</h4>
                <ul class="text-xs space-y-2 text-green-100">
                    <li><a href="help.php" class="hover:underline">ศูนย์ช่วยเหลือ</a></li>
                    <li><a href="how-to-buy.php" class="hover:underline">วิธีการสั่งซื้อสินค้า</a></li>
                    <li><a href="tracking.php" class="hover:underline">การติดตามสถานะพัสดุ</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold text-base mb-3 text-[#52B788]">เกี่ยวกับ Hugmart</h4>
                <ul class="text-xs space-y-2 text-green-100">
                    <li><a href="careers.php" class="hover:underline">ร่วมงานกับเรา</a></li>
                    <li><a href="/seller/index.php" class="hover:underline">สมัครเปิดร้านค้า (Seller Center)</a></li>
                    <li><a href="privacy.php" class="hover:underline">เงื่อนไขและนโยบายความเป็นส่วนตัว</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold text-base mb-3 text-[#52B788]">การชำระเงินที่ปลอดภัย</h4>
                <div class="flex gap-2 text-2xl text-white">
                    <i class="fa-brands fa-cc-visa"></i>
                    <i class="fa-brands fa-cc-mastercard"></i>
                    <i class="fa-solid fa-qrcode"></i>
                </div>
            </div>
        </div>
        <div class="border-t border-[#2D6A4F] pt-4 text-center text-xs text-green-200">
            <p>© 2026 Hugmart Marketplace. All Rights Reserved.</p>
        </div>
    </footer>

    <script>
        function scrollCategories(amount) {
            const container = document.getElementById('categoryContainer');
            if (container) {
                container.scrollBy({ left: amount, behavior: 'smooth' });
            }
        }
    </script>
</body>
</html>