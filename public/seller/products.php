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

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// ดึงข้อมูลร้านค้า
$stmt = $pdo->prepare("SELECT * FROM shops WHERE user_id = ?");
$stmt->execute([$userId]);
$shop = $stmt->fetch(PDO::FETCH_ASSOC);
$shopId = $shop['id'] ?? $userId;

// การลบสินค้า
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $deleteId = intval($_GET['id']);
    $stmtDel = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmtDel->execute([$deleteId]);
    header("Location: /seller/products.php?msg=deleted");
    exit;
}

$searchName = trim($_GET['search_name'] ?? '');
$searchCategory = trim($_GET['search_category'] ?? '');
$tab = $_GET['tab'] ?? 'all';

// ดึงข้อมูลสินค้าทั้งหมด โดยเช็กครอบคลุมทุกคอลัมน์ ID
$sql = "SELECT * FROM products WHERE (shop_id = ? OR seller_id = ? OR shop_id IS NULL OR seller_id IS NULL)";
$params = [$shopId, $userId];

if (!empty($searchName)) {
    $sql .= " AND (name LIKE ? OR product_name LIKE ?)";
    $params[] = '%' . $searchName . '%';
    $params[] = '%' . $searchName . '%';
}

if (!empty($searchCategory)) {
    $sql .= " AND (category LIKE ? OR category_name LIKE ?)";
    $params[] = '%' . $searchCategory . '%';
    $params[] = '%' . $searchCategory . '%';
}

if ($tab === 'soldout') {
    $sql .= " AND (stock = 0 OR product_stock = 0)";
} elseif ($tab === 'active') {
    $sql .= " AND (stock > 0 OR product_stock > 0)";
}

$sql .= " ORDER BY id DESC";

$stmtProducts = $pdo->prepare($sql);
$stmtProducts->execute($params);
$products = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);

$totalCount = count($products);
$soldoutCount = 0;
foreach ($products as $item) {
    $stk = intval($item['stock'] ?? $item['product_stock'] ?? 0);
    if ($stk === 0) {
        $soldoutCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สินค้าของฉัน - Hugmart Seller Centre</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-[#F6F6F6] font-sans text-gray-800 antialiased min-h-screen flex flex-col">

    <header class="bg-white border-b border-gray-200 h-14 sticky top-0 z-50 flex items-center justify-between px-6 shadow-sm">
        <div class="flex items-center space-x-3">
            <a href="/index.php" class="text-xl font-bold text-[#1B4332] flex items-center gap-2">
                <span class="bg-[#1B4332] text-white px-2 py-0.5 rounded text-sm font-black">H</span>
                Hugmart <span class="text-xs font-normal text-gray-500 border-l pl-2 border-gray-300">Seller Centre</span>
            </a>
        </div>
        <div class="text-xs text-gray-500">
            <a href="/seller/index.php" class="hover:text-[#2D6A4F]">หน้าหลัก</a> &gt; 
            <span class="text-gray-800 font-semibold">สินค้าของฉัน</span>
        </div>
    </header>

    <div class="flex flex-1 max-w-[1440px] w-full mx-auto">

        <aside class="w-56 bg-white border-r border-gray-200 p-4 hidden md:block shrink-0 text-xs space-y-4">
            <div>
                <div class="font-bold text-gray-800 mb-3 flex items-center justify-between cursor-pointer text-sm">
                    <span>สินค้า</span>
                    <i class="fa-solid fa-chevron-down text-xs text-gray-400"></i>
                </div>
                <ul class="space-y-2.5 pl-2 text-gray-600">
                    <li>
                        <a href="/seller/products.php" class="flex items-center justify-between text-[#2D6A4F] font-bold py-1">
                            <span>สินค้าของฉัน</span>
                            <span class="bg-green-100 text-[#2D6A4F] px-1.5 py-0.5 rounded text-[10px] font-semibold">(<?php echo $totalCount; ?>)</span>
                        </a>
                    </li>
                    <li>
                        <a href="/seller/add_product.php" class="block py-1 hover:text-[#2D6A4F]">+ เพิ่มสินค้าใหม่</a>
                    </li>
                </ul>
            </div>
        </aside>

        <main class="flex-1 p-6 space-y-5 overflow-x-hidden">

            

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                
                <div class="border-b border-gray-200 flex text-xs font-semibold text-gray-600 bg-gray-50/50">
                    <a href="products.php?tab=all" class="px-6 py-3 border-b-2 <?php echo $tab === 'all' ? 'border-[#1B4332] text-[#1B4332] bg-white font-bold' : 'border-transparent hover:text-gray-800'; ?>">
                        ทั้งหมด (<?php echo $totalCount; ?>)
                    </a>
                   
                </div>

                <div class="p-4 flex items-center justify-between border-b border-gray-100 text-xs">
                    <div class="font-bold text-gray-700">สินค้า <?php echo count($products); ?> รายการ</div>
                    <a href="/seller/add_product.php" class="bg-[#1B4332] hover:bg-[#2D6A4F] text-white px-4 py-2 rounded font-bold shadow transition flex items-center gap-1.5">
                        <i class="fa-solid fa-plus text-xs"></i> เพิ่มสินค้าใหม่
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 border-b border-gray-200">
                                <th class="p-3 w-10 text-center"><input type="checkbox"></th>
                                <th class="p-3">ชื่อสินค้า</th>
                                <th class="p-3 w-32">เลข SKU</th>
                                <th class="p-3 w-28">ราคา</th>
                                <th class="p-3 w-24">คลัง</th>
                                <th class="p-3 w-24">ยอดขาย</th>
                                <th class="p-3 w-28 text-center">การดำเนินการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="7" class="p-10 text-center text-gray-400">
                                        <i class="fa-solid fa-box-open text-4xl mb-2 text-gray-300 block"></i>
                                        ไม่พบรายการสินค้า
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $p): ?>
                                    <?php 
                                        $pName  = $p['name'] ?? $p['product_name'] ?? 'ไม่มีชื่อสินค้า';
                                        $pPrice = floatval($p['price'] ?? $p['product_price'] ?? 0);
                                        $pStock = intval($p['stock'] ?? $p['product_stock'] ?? 0);
                                        $pSku   = $p['sku'] ?? $p['product_sku'] ?? '-';
                                        $pImg   = $p['image_url'] ?? $p['image'] ?? '/assets/images/placeholder.jpg';
                                        $pCat   = $p['category'] ?? $p['category_name'] ?? '-';
                                    ?>
                                    <tr class="hover:bg-gray-50/80 transition">
                                        <td class="p-3 text-center"><input type="checkbox"></td>
                                        <td class="p-3">
                                            <div class="flex items-center gap-3">
                                                <img src="<?php echo htmlspecialchars($pImg); ?>" class="w-12 h-12 object-cover rounded border border-gray-200 shrink-0">
                                                <div>
                                                    <span class="font-bold text-gray-800 line-clamp-1">
                                                        <?php echo htmlspecialchars($pName); ?>
                                                    </span>
                                                    <span class="text-[10px] text-gray-400 block mt-0.5"><?php echo htmlspecialchars($pCat); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-3 text-gray-500"><?php echo htmlspecialchars($pSku ?: '-'); ?></td>
                                        <td class="p-3 font-bold text-gray-800">฿<?php echo number_format($pPrice, 2); ?></td>
                                        <td class="p-3">
                                            <?php if ($pStock > 0): ?>
                                                <span class="text-gray-700 font-medium"><?php echo number_format($pStock); ?></span>
                                            <?php else: ?>
                                                <span class="text-red-500 font-bold bg-red-50 px-2 py-0.5 rounded text-[10px]">หมด</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3 text-gray-500">0</td>
                                        <td class="p-3 text-center">
                                            <div class="flex items-center justify-center gap-3">
                                                <a href="/seller/add_product.php?id=<?php echo $p['id']; ?>" class="text-blue-600 hover:underline font-semibold">แก้ไข</a>
                                                <a href="products.php?action=delete&id=<?php echo $p['id']; ?>" onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบสินค้านี้?')" class="text-red-500 hover:underline font-semibold">ลบ</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

        </main>
    </div>

</body>
</html>