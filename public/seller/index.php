<?php
session_start();
require_once __DIR__ . '/../../src/Config/Database.php';

// ดึง PDO Connection
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

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php?error=' . urlencode('กรุณาเข้าสู่ระบบก่อนเข้าสู่ Seller Centre'));
    exit;
}

$userId = $_SESSION['user_id'];
$successMsg = '';
$errorMsg = '';

// จัดการบันทึกตั้งชื่อร้านค้าเมื่อผู้ใช้ยังไม่มีร้าน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_shop'])) {
    $shopName = trim($_POST['shop_name'] ?? '');
    if (!empty($shopName)) {
        try {
            $insertStmt = $pdo->prepare("INSERT INTO shops (user_id, shop_name) VALUES (?, ?)");
            $insertStmt->execute([$userId, $shopName]);
            header('Location: /seller/index.php');
            exit;
        } catch (PDOException $e) {
            $errorMsg = 'เกิดข้อผิดพลาดในการสร้างร้านค้า: ' . $e->getMessage();
        }
    } else {
        $errorMsg = 'กรุณากรอกชื่อร้านค้าของคุณ';
    }
}

// ดึงข้อมูลร้านค้าจากตาราง shops
$stmt = $pdo->prepare("SELECT s.*, u.avatar_url FROM shops s JOIN users u ON s.user_id = u.id WHERE s.user_id = ?");
$stmt->execute([$userId]);
$seller = $stmt->fetch(PDO::FETCH_ASSOC);

// หากยังไม่มีร้านค้า ให้แสดงฟอร์มตั้งชื่อร้านค้า
if (!$seller):
    // ดึงข้อมูลรูปโปรไฟล์ผู้ใช้มาสำรองแสดงผลตอนตั้งชื่อร้าน
    $stmtUser = $pdo->prepare("SELECT avatar_url, full_name FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งชื่อร้านค้า - Hugmart Seller Centre</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-[#F6F6F6] font-sans text-gray-800 antialiased min-h-screen flex flex-col justify-center items-center p-4">
    <div class="max-w-md w-full bg-white rounded-lg shadow-md p-8 space-y-6">
        <div class="text-center space-y-2">
            <a href="/index.php" class="text-2xl font-bold text-[#1B4332] inline-flex items-center gap-2">
                <span class="bg-[#1B4332] text-white px-2 py-0.5 rounded text-sm font-black">H</span> Hugmart
            </a>
            <h1 class="text-lg font-bold text-gray-800 mt-2">ยินดีต้อนรับสู่ Seller Centre</h1>
            <p class="text-xs text-gray-500">กรุณาตั้งชื่อร้านค้าของคุณเพื่อเริ่มต้นลงขายสินค้าออร์แกนิก</p>
        </div>

        <?php if ($errorMsg): ?>
            <div class="p-3 bg-red-50 text-red-700 text-xs rounded border border-red-200 flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation"></i> <span><?php echo htmlspecialchars($errorMsg); ?></span>
            </div>
        <?php endif; ?>

        <form action="index.php" method="POST" class="space-y-4 text-xs">
            <input type="hidden" name="register_shop" value="1">
            <div>
                <label class="block font-semibold text-gray-700 mb-1">ชื่อร้านค้าของคุณ</label>
                <input type="text" name="shop_name" required placeholder="เช่น HugOrganic Shop" class="w-full px-3 py-2.5 border border-gray-300 rounded focus:outline-none focus:border-[#2D6A4F] text-sm">
            </div>
            <button type="submit" class="w-full bg-[#1B4332] hover:bg-[#2D6A4F] text-white py-2.5 rounded font-bold transition shadow text-xs">
                ยืนยันและสร้างร้านค้า
            </button>
        </form>
    </div>
</body>
</html>
<?php 
    exit;
endif;

$sellerId = $seller['id'];

// ดึงจำนวนสินค้าทั้งหมด
try {
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM products WHERE shop_id = ?");
    $stmtCount->execute([$sellerId]);
    $totalProducts = $stmtCount->fetchColumn() ?: 0;
} catch (PDOException $e) {
    $totalProducts = 0;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hugmart Seller Centre</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-[#F6F6F6] font-sans text-gray-800 antialiased min-h-screen flex flex-col">

    <!-- Top Header Bar -->
    <header class="bg-white border-b border-gray-200 h-14 sticky top-0 z-50 flex items-center justify-between px-6 shadow-sm">
        <div class="flex items-center space-x-3">
            <a href="/index.php" class="text-xl font-bold text-[#1B4332] flex items-center gap-2">
                <span class="bg-[#1B4332] text-white px-2 py-0.5 rounded text-sm font-black">H</span>
                Hugmart <span class="text-xs font-normal text-gray-500 border-l pl-2 border-gray-300">Seller Centre</span>
            </a>
        </div>

        <!-- Profile Dropdown -->
        <div class="relative group py-2">
            <button class="flex items-center space-x-2 focus:outline-none cursor-pointer">
                <div class="w-8 h-8 bg-[#2D6A4F] text-white rounded-full flex items-center justify-center font-bold text-xs overflow-hidden border">
                    <?php if (!empty($seller['avatar_url'])): ?>
                        <img src="<?php echo htmlspecialchars($seller['avatar_url']); ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <?php echo mb_substr($seller['shop_name'], 0, 1, 'UTF-8'); ?>
                    <?php endif; ?>
                </div>
                <span class="text-xs font-semibold text-gray-700"><?php echo htmlspecialchars($seller['shop_name']); ?></span>
                <i class="fa-solid fa-chevron-down text-[10px] text-gray-400 group-hover:rotate-180 transition-transform"></i>
            </button>

            <div class="absolute right-0 mt-1 w-56 bg-white rounded-md shadow-lg border border-gray-100 hidden group-hover:block z-50">
                <div class="p-4 text-center border-b border-gray-100">
                    <div class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($seller['shop_name']); ?></div>
                </div>
                <div class="py-1 text-xs text-gray-700">
                    <a href="/logout.php" class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 text-red-600">
                        <i class="fa-solid fa-arrow-right-from-bracket w-4"></i>
                        <span>ออกจากระบบ</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <div class="flex flex-1 max-w-[1440px] w-full mx-auto">

        <!-- Left Sidebar (เฉพาะหัวข้อใหญ่สินค้าตามรูป) -->
        <aside class="w-56 bg-white border-r border-gray-200 p-4 hidden md:block shrink-0 text-xs space-y-4">
            <div>
                <div class="font-bold text-gray-800 mb-3 flex items-center justify-between cursor-pointer text-sm">
                    <span>สินค้า</span>
                    <i class="fa-solid fa-chevron-down text-xs text-gray-400"></i>
                </div>
                <ul class="space-y-2.5 pl-2 text-gray-600">
                    <li>
                        <a href="/seller/products.php" class="flex items-center justify-between hover:text-[#2D6A4F] py-1">
                            <span>สินค้าของฉัน</span>
                            <span class="bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded text-[10px] font-semibold">(<?php echo $totalProducts; ?>)</span>
                        </a>
                    </li>
                    <li>
                        <a href="/seller/add_product.php" class="block py-1 text-[#2D6A4F] font-bold hover:underline">
                            + เพิ่มสินค้าใหม่
                        </a>
                    </li>
                    <li>
                        <a href="#" class="block py-1 hover:text-[#2D6A4F]">
                            Hugmart Standard Product
                        </a>
                    </li>
                </ul>
            </div>
        </aside>

        <!-- Right Content Area -->
        <main class="flex-1 p-6 space-y-5">
            <!-- Banner แจ้งเตือน -->
            <div class="bg-blue-50 border border-blue-200 rounded p-3 text-xs text-blue-800 flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <i class="fa-solid fa-volume-high text-blue-500"></i>
                    <span>ระบบลงขายสินค้าพร้อมใช้งานแล้ว คุณสามารถลงขายสินค้าออร์แกนิกได้ทันที</span>
                </div>
                <a href="/seller/add_product.php" class="text-[#2D6A4F] font-bold hover:underline">ลงขายสินค้าเลย ></a>
            </div>

            <!-- Quick Action Box -->
            <div class="bg-white p-6 rounded shadow-sm border border-gray-100 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-gray-800">จัดการรายการสินค้าของคุณ</h2>
                    <p class="text-xs text-gray-500 mt-1">เพิ่มสินค้าใหม่ เข้าระบบ พร้อมตั้งราคาและสต็อกสินค้าออร์แกนิก</p>
                </div>
                <a href="/seller/add_product.php" class="bg-[#1B4332] hover:bg-[#2D6A4F] text-white px-6 py-2.5 rounded text-xs font-bold transition flex items-center gap-2 shadow">
                    <i class="fa-solid fa-plus"></i> เพิ่มสินค้าใหม่
                </a>
            </div>
        </main>
    </div>

</body>
</html>