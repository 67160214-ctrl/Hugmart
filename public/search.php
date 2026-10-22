<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../src/Config/Database.php';

$keyword = trim($_GET['q'] ?? '');
$products = [];

if ($keyword !== '') {
    try {
        // ค้นหาชื่อสินค้าที่ตรงกับคำค้นหา (ใช้ LIKE สำหรับค้นหาบางส่วนของชื่อได้)
        $stmt = $pdo->prepare("
            SELECT p.*, sp.store_name,
                (SELECT AVG(r.rating) FROM reviews r WHERE r.product_id = p.id) AS avg_rating,
                (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id) AS review_count,
                (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.product_id = p.id) AS sold_count
            FROM products p 
            LEFT JOIN seller_profiles sp ON p.seller_id = sp.id 
            WHERE p.name LIKE ? OR p.description LIKE ?
            ORDER BY p.id DESC
        ");
        $searchTerm = '%' . $keyword . '%';
        $stmt->execute([$searchTerm, $searchTerm]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $products = [];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ผลการค้นหา "<?php echo htmlspecialchars($keyword); ?>" | Hugmart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans text-gray-800 min-h-screen flex flex-col justify-between">

    <!-- Header -->
    <header class="bg-[#1B4332] text-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between gap-6">
            <a href="index.php" class="text-3xl font-extrabold tracking-tight text-white flex items-center gap-2">
                <i class="fa-solid fa-bag-shopping text-[#52B788]"></i> Hugmart
            </a>

            <div class="flex-1 max-w-2xl">
                <form action="search.php" method="GET" class="flex bg-white rounded-md overflow-hidden p-1 shadow">
                    <input type="text" name="q" value="<?php echo htmlspecialchars($keyword); ?>" placeholder="ค้นหาชื่อสินค้าที่ต้องการ..." class="w-full px-4 py-2 text-sm text-gray-800 focus:outline-none">
                    <button type="submit" class="bg-[#2D6A4F] hover:bg-[#1B4332] text-white px-6 py-2 rounded transition">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                </form>
            </div>

            <a href="cart.php" class="text-white text-2xl hover:text-green-200 transition">
                <i class="fa-solid fa-cart-shopping"></i>
            </a>
        </div>
    </header>

    <!-- Content -->
    <main class="max-w-7xl mx-auto px-4 py-8 flex-grow w-full space-y-6">
        <div class="text-sm text-gray-500">
            หน้าหลัก &gt; ผลการค้นหา: <span class="font-bold text-gray-800">"<?php echo htmlspecialchars($keyword); ?>"</span> (พบ <?php echo count($products); ?> รายการ)
        </div>

        <?php if (empty($products)): ?>
            <div class="bg-white rounded-lg shadow p-12 text-center space-y-4">
                <i class="fa-solid fa-box-open text-5xl text-gray-300"></i>
                <h3 class="text-lg font-bold text-gray-700">ไม่พบสินค้าที่คุณค้นหา</h3>
                <p class="text-xs text-gray-400">ลองเปลี่ยนคำค้นหาใหม่อีกครั้ง หรือเลือกดูสินค้าแนะนำในหน้าแรก</p>
                <div>
                    <a href="index.php" class="inline-block bg-[#2D6A4F] text-white px-6 py-2 rounded text-xs font-semibold hover:bg-[#1B4332] transition">กลับสู่หน้าแรก</a>
                </div>
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
                                <span class="text-[11px] text-[#2D6A4F] font-semibold block mb-0.5"><?php echo htmlspecialchars($p['store_name'] ?: 'HugOrganic Store'); ?></span>
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
    </main>

    <!-- Footer -->
    <footer class="bg-[#1B4332] text-white mt-12 py-6 text-center text-xs">
        <p>© 2026 Hugmart Marketplace. All Rights Reserved.</p>
    </footer>

</body>
</html>