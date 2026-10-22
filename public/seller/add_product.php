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

// ดึงข้อมูลร้านค้าของผู้ขาย
$stmt = $pdo->prepare("SELECT * FROM shops WHERE user_id = ?");
$stmt->execute([$userId]);
$shop = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$shop) {
    header('Location: /seller/index.php?step=register');
    exit;
}

$errorMsg = '';

// จัดการบันทึกข้อมูลสินค้าเมื่อมีการส่งฟอร์ม (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_product') {
    $productName = trim($_POST['product_name'] ?? '');
    $categoryName = trim($_POST['category_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $sku = trim($_POST['sku'] ?? '');
    $weight = floatval($_POST['weight'] ?? 0);

    // จัดการอัปโหลดรูปภาพหลายรูป
    $uploadedImages = [];
    if (isset($_FILES['product_images'])) {
        $targetDir = __DIR__ . '/../uploads/products/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        foreach ($_FILES['product_images']['tmp_name'] as $key => $tmpName) {
            if ($_FILES['product_images']['error'][$key] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['product_images']['name'][$key], PATHINFO_EXTENSION);
                $newFileName = time() . '_' . $key . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($tmpName, $targetDir . $newFileName)) {
                    $uploadedImages[] = '/uploads/products/' . $newFileName;
                }
            }
        }
    }

    $mainImage = $uploadedImages[0] ?? '/assets/images/placeholder.jpg';

    if (empty($productName) || $price <= 0) {
        $errorMsg = 'กรุณากรอกชื่อสินค้าและราคาให้ถูกต้อง';
    } else {
        try {
            // บันทึกเข้าฐานข้อมูลรองรับทุกฟิลด์
            $stmtInsert = $pdo->prepare("INSERT INTO products (shop_id, seller_id, name, category, description, price, stock, sku, weight, image_url, images_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtInsert->execute([
                $shop['id'],
                $shop['id'],
                $productName,
                $categoryName,
                $description,
                $price,
                $stock,$sku,
                $weight,$mainImage,
                json_encode($uploadedImages)
            ]);

            // บันทึกสำเร็จแล้วเปลี่ยนหน้าไปยัง "สินค้าของฉัน"
            header("Location: /seller/products.php?msg=success");
            exit;
        } catch (PDOException $e) {
            try {
                // Fallback กรณีตารางไม่ได้ใส่คอลัมน์ใหม่ครบ
                $stmtFallback =$pdo->prepare("INSERT INTO products (shop_id, name, price, stock, image_url) VALUES (?, ?, ?, ?, ?)");
                $stmtFallback->execute([$shop['id'], $productName,$price, $stock,$mainImage]);
                
                header("Location: /seller/products.php?msg=success");
                exit;
            } catch (PDOException $ex) {
                $errorMsg = 'เกิดข้อผิดพลาดในการบันทึกสินค้า: ' . $ex->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มสินค้าใหม่ - Hugmart Seller Centre</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-[#F5F5F5] font-sans text-gray-800 antialiased min-h-screen pb-20">

    <!-- Header Bar -->
    <header class="bg-white border-b border-gray-200 h-14 sticky top-0 z-50 flex items-center justify-between px-6 shadow-sm">
        <div class="flex items-center space-x-3">
            <a href="/index.php" class="text-xl font-bold text-[#1B4332] flex items-center gap-2">
                <span class="bg-[#1B4332] text-white px-2 py-0.5 rounded text-sm font-black">H</span>
                Hugmart <span class="text-xs font-normal text-gray-500 border-l pl-2 border-gray-300">Seller Centre</span>
            </a>
        </div>
        <div class="text-xs text-gray-500">
            <a href="/seller/index.php" class="hover:text-[#2D6A4F]">หน้าหลัก</a> &gt; 
            <a href="/seller/products.php" class="hover:text-[#2D6A4F]">สินค้าของฉัน</a> &gt; 
            <span class="text-gray-800 font-semibold">เพิ่มสินค้าใหม่</span>
        </div>
    </header>

    <main class="max-w-6xl mx-auto mt-6 px-4">

        <!-- Step 1: เลือกหมวดหมู่สินค้า (Cascade Category Picker) -->
        <div id="categoryStep" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h1 class="text-xl font-bold text-gray-800">เพิ่มสินค้าใหม่</h1>
            <p class="text-xs text-gray-400 mt-1 mb-6">โปรดเลือกหมวดหมู่ที่เหมาะสมสำหรับสินค้าของคุณ</p>

            <div class="flex items-center mb-6">
                <label class="w-32 text-xs font-semibold text-gray-600 text-right pr-4"><span class="text-red-500">*</span> ชื่อสินค้า:</label>
                <input type="text" id="initialProductName" placeholder="ใส่ชื่อสินค้า..." class="flex-1 max-w-2xl px-3 py-2 text-xs border border-gray-300 rounded focus:outline-none focus:border-[#2D6A4F]">
            </div>

            <div class="border border-gray-200 rounded-lg p-4 bg-gray-50/50">
                <div class="relative max-w-sm mb-4">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-xs text-gray-400"></i>
                    <input type="text" id="searchCategoryInput" onkeyup="filterCategories()" placeholder="ค้นหาหมวดหมู่..." class="w-full pl-8 pr-3 py-1.5 text-xs border border-gray-300 rounded-full bg-white focus:outline-none">
                </div>

                <!-- 3 Columns Selector -->
                <div class="grid grid-cols-3 gap-0 border border-gray-200 bg-white rounded h-80 overflow-hidden text-xs">
                    <div class="border-r border-gray-100 overflow-y-auto py-2" id="mainCatList">
                        <div onclick="selectMainCat(this, 'ความงามและของใช้ส่วนตัว')" class="cat-item px-4 py-2 hover:bg-green-50 flex items-center justify-between cursor-pointer font-medium text-gray-700">
                            <span>ความงามและของใช้ส่วนตัว</span><i class="fa-solid fa-chevron-right text-[10px] text-gray-400"></i>
                        </div>
                        <div onclick="selectMainCat(this, 'เสื้อผ้าแฟชั่นผู้ชาย')" class="cat-item px-4 py-2 hover:bg-green-50 flex items-center justify-between cursor-pointer font-medium text-gray-700">
                            <span>เสื้อผ้าแฟชั่นผู้ชาย</span><i class="fa-solid fa-chevron-right text-[10px] text-gray-400"></i>
                        </div>
                        <div onclick="selectMainCat(this, 'กระเป๋า')" class="cat-item px-4 py-2 hover:bg-green-50 flex items-center justify-between cursor-pointer font-medium text-gray-700">
                            <span>กระเป๋า</span><i class="fa-solid fa-chevron-right text-[10px] text-gray-400"></i>
                        </div>
                        <div onclick="selectMainCat(this, 'รองเท้าผู้หญิง')" class="cat-item px-4 py-2 hover:bg-green-50 flex items-center justify-between cursor-pointer font-medium text-gray-700">
                            <span>รองเท้าผู้หญิง</span><i class="fa-solid fa-chevron-right text-[10px] text-gray-400"></i>
                        </div>
                        <div onclick="selectMainCat(this, 'นาฬิกาและแว่นตา')" class="cat-item px-4 py-2 hover:bg-green-50 flex items-center justify-between cursor-pointer font-medium text-gray-700">
                            <span>นาฬิกาและแว่นตา</span><i class="fa-solid fa-chevron-right text-[10px] text-gray-400"></i>
                        </div>
                        <div onclick="selectMainCat(this, 'อุปกรณ์อิเล็กทรอนิกส์')" class="cat-item px-4 py-2 hover:bg-green-50 flex items-center justify-between cursor-pointer font-medium text-gray-700">
                            <span>อุปกรณ์อิเล็กทรอนิกส์</span><i class="fa-solid fa-chevron-right text-[10px] text-gray-400"></i>
                        </div>
                        <div onclick="selectMainCat(this, 'เครื่องใช้ไฟฟ้าภายในบ้าน')" class="cat-item px-4 py-2 hover:bg-green-50 flex items-center justify-between cursor-pointer font-medium text-gray-700">
                            <span>เครื่องใช้ไฟฟ้าภายในบ้าน</span><i class="fa-solid fa-chevron-right text-[10px] text-gray-400"></i>
                        </div>
                        <div onclick="selectMainCat(this, 'กล้องและอุปกรณ์ถ่ายภาพ')" class="cat-item px-4 py-2 hover:bg-green-50 flex items-center justify-between cursor-pointer font-medium text-gray-700">
                            <span>กล้องและอุปกรณ์ถ่ายภาพ</span><i class="fa-solid fa-chevron-right text-[10px] text-gray-400"></i>
                        </div>
                        <div onclick="selectMainCat(this, 'ของเล่น สินค้าแม่และเด็ก')" class="cat-item px-4 py-2 hover:bg-green-50 flex items-center justify-between cursor-pointer font-medium text-gray-700">
                            <span>ของเล่น สินค้าแม่และเด็ก</span><i class="fa-solid fa-chevron-right text-[10px] text-gray-400"></i>
                        </div>
                        <div onclick="selectMainCat(this, 'สัตว์เลี้ยง')" class="cat-item px-4 py-2 hover:bg-green-50 flex items-center justify-between cursor-pointer font-medium text-gray-700">
                            <span>สัตว์เลี้ยง</span><i class="fa-solid fa-chevron-right text-[10px] text-gray-400"></i>
                        </div>
                        <div onclick="selectMainCat(this, 'กลุ่มผลิตภัณฑ์เพื่อสุขภาพ')" class="cat-item px-4 py-2 hover:bg-green-50 flex items-center justify-between cursor-pointer font-medium text-gray-700">
                            <span>กลุ่มผลิตภัณฑ์เพื่อสุขภาพ</span><i class="fa-solid fa-chevron-right text-[10px] text-gray-400"></i>
                        </div>
                        <div onclick="selectMainCat(this, 'เสื้อผ้าแฟชั่นผู้หญิง')" class="cat-item px-4 py-2 hover:bg-green-50 flex items-center justify-between cursor-pointer font-medium text-gray-700">
                            <span>เสื้อผ้าแฟชั่นผู้หญิง</span><i class="fa-solid fa-chevron-right text-[10px] text-gray-400"></i>
                        </div>
                        <div onclick="selectMainCat(this, 'รองเท้าผู้ชาย')" class="cat-item px-4 py-2 hover:bg-green-50 flex items-center justify-between cursor-pointer font-medium text-gray-700">
                            <span>รองเท้าผู้ชาย</span><i class="fa-solid fa-chevron-right text-[10px] text-gray-400"></i>
                        </div>
                        <div onclick="selectMainCat(this, 'เครื่องประดับ')" class="cat-item px-4 py-2 hover:bg-green-50 flex items-center justify-between cursor-pointer font-medium text-gray-700">
                            <span>เครื่องประดับ</span><i class="fa-solid fa-chevron-right text-[10px] text-gray-400"></i>
                        </div>
                        <div onclick="selectMainCat(this, 'เครื่องใช้ในบ้าน')" class="cat-item px-4 py-2 hover:bg-green-50 flex items-center justify-between cursor-pointer font-medium text-gray-700">
                            <span>เครื่องใช้ในบ้าน</span><i class="fa-solid fa-chevron-right text-[10px] text-gray-400"></i>
                        </div>
                        <div onclick="selectMainCat(this, 'มือถือ และแท็บเล็ต')" class="cat-item px-4 py-2 hover:bg-green-50 flex items-center justify-between cursor-pointer font-medium text-gray-700">
                            <span>มือถือ และแท็บเล็ต</span><i class="fa-solid fa-chevron-right text-[10px] text-gray-400"></i>
                        </div>
                        <div onclick="selectMainCat(this, 'คอมพิวเตอร์และแล็ปท็อป')" class="cat-item px-4 py-2 hover:bg-green-50 flex items-center justify-between cursor-pointer font-medium text-gray-700">
                            <span>คอมพิวเตอร์และแล็ปท็อป</span><i class="fa-solid fa-chevron-right text-[10px] text-gray-400"></i>
                        </div>
                        <div onclick="selectMainCat(this, 'อาหารและเครื่องดื่ม')" class="cat-item px-4 py-2 hover:bg-green-50 flex items-center justify-between cursor-pointer font-medium text-gray-700">
                            <span>อาหารและเครื่องดื่ม</span><i class="fa-solid fa-chevron-right text-[10px] text-gray-400"></i>
                        </div>
                        <div onclick="selectMainCat(this, 'กีฬาและกิจกรรมกลางแจ้ง')" class="cat-item px-4 py-2 hover:bg-green-50 flex items-center justify-between cursor-pointer font-medium text-gray-700">
                            <span>กีฬาและกิจกรรมกลางแจ้ง</span><i class="fa-solid fa-chevron-right text-[10px] text-gray-400"></i>
                        </div>
                        <div onclick="selectMainCat(this, 'เกมและอุปกรณ์เสริม')" class="cat-item px-4 py-2 hover:bg-green-50 flex items-center justify-between cursor-pointer font-medium text-gray-700">
                            <span>เกมและอุปกรณ์เสริม</span><i class="fa-solid fa-chevron-right text-[10px] text-gray-400"></i>
                        </div>
                    </div>

                    <div class="border-r border-gray-100 overflow-y-auto py-2 bg-gray-50/30" id="subCatList">
                        <div class="p-4 text-gray-400 text-center">กรุณาเลือกหมวดหมู่หลัก</div>
                    </div>

                    <div class="overflow-y-auto py-2 bg-gray-50/50" id="finalCatList">
                        <div class="p-4 text-gray-400 text-center">กรุณาเลือกหมวดหมู่ย่อย</div>
                    </div>
                </div>

                <div class="mt-4 text-xs text-gray-500">
                    หมวดหมู่ที่เลือก: <span id="selectedCatText" class="font-bold text-[#2D6A4F]">-</span>
                </div>
            </div>

            <div class="mt-6 text-right">
                <button type="button" onclick="goToFormStep()" class="bg-[#1B4332] hover:bg-[#2D6A4F] text-white px-8 py-2 rounded text-xs font-semibold shadow transition">
                    ถัดไป
                </button>
            </div>
        </div>

        <!-- Step 2: Form กรอกรายละเอียดสินค้า -->
        <form id="productForm" action="add_product.php" method="POST" enctype="multipart/form-data" class="hidden">
            <input type="hidden" name="action" value="save_product">
            <input type="hidden" name="category_name" id="hiddenCategory">

            <?php if ($errorMsg): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 text-xs p-4 rounded mb-6 flex items-center gap-2">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?php echo htmlspecialchars($errorMsg); ?></span>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

                <!-- คอลัมน์ซ้าย: แถบตรวจเช็กคุณภาพข้อมูลสินค้า -->
                <div class="lg:col-span-1 space-y-4">
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 sticky top-20 text-xs">
                        <h3 class="font-bold text-gray-800 border-b pb-2 mb-3">การปรับปรุงที่แนะนำ</h3>
                        
                        <ul class="space-y-3">
                            <li class="flex items-start gap-2 text-gray-500" id="checkImg">
                                <i class="fa-regular fa-circle-check text-gray-300 mt-0.5"></i>
                                <span>เพิ่มรูปภาพอย่างน้อย 3 รูป</span>
                            </li>
                            <li class="flex items-start gap-2 text-gray-500" id="checkTitle">
                                <i class="fa-regular fa-circle-check text-gray-300 mt-0.5"></i>
                                <span>ตั้งชื่อสินค้าที่มีความยาว 25-100 ตัวอักษร</span>
                            </li>
                            <li class="flex items-start gap-2 text-gray-500" id="checkDesc">
                                <i class="fa-regular fa-circle-check text-gray-300 mt-0.5"></i>
                                <span>เขียนรายละเอียดอย่างน้อย 100 ตัวอักษร</span>
                            </li>
                        </ul>

                        <div class="mt-6 pt-4 border-t border-gray-100">
                            <h4 class="font-bold text-gray-700 mb-1">เคล็ดลับการลงขาย</h4>
                            <p class="text-[11px] text-gray-400">รูปภาพหน้าปกควรมองเห็นชัดเจน และใช้พื้นหลังสีขาวหรือบรรยากาศธรรมชาติออร์แกนิก</p>
                        </div>
                    </div>
                </div>

                <!-- คอลัมน์ขวา: รายละเอียดสินค้า -->
                <div class="lg:col-span-3 space-y-6">

                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-2 flex gap-2 text-xs font-semibold text-gray-600">
                        <button type="button" class="px-4 py-2 rounded bg-green-50 text-[#2D6A4F] border border-green-200">1. ข้อมูลทั่วไป</button>
                        <button type="button" class="px-4 py-2 rounded hover:bg-gray-50">2. ข้อมูลการขาย</button>
                        <button type="button" class="px-4 py-2 rounded hover:bg-gray-50">3. การจัดส่ง</button>
                    </div>

                    <!-- Card 1: ข้อมูลทั่วไป -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-6 text-xs">
                        <h2 class="text-sm font-bold text-gray-800 border-b pb-3">ข้อมูลทั่วไป</h2>

                        <div>
                            <label class="block font-semibold text-gray-700 mb-2">
                                <span class="text-red-500">*</span> ภาพสินค้า (สัดส่วน 1:1, สูงสุด 9 รูป)
                            </label>
                            
                            <div class="grid grid-cols-5 gap-3" id="imageGrid">
                                <label class="img-upload-box w-full h-24 border-2 border-dashed border-gray-300 rounded-lg flex flex-col items-center justify-center cursor-pointer hover:border-[#2D6A4F] bg-gray-50 relative overflow-hidden">
                                    <i class="fa-solid fa-camera text-gray-400 text-lg mb-1"></i>
                                    <span class="text-[10px] text-gray-400">ภาพหลัก (1/9)</span>
                                    <input type="file" name="product_images[]" accept="image/*" class="hidden img-input" onchange="handleImageUpload(this)">
                                </label>
                                <?php for($i=2; $i<=5; $i++): ?>
                                <label class="img-upload-box w-full h-24 border-2 border-dashed border-gray-200 rounded-lg flex flex-col items-center justify-center cursor-pointer hover:border-[#2D6A4F] bg-gray-50 relative overflow-hidden">
                                    <i class="fa-solid fa-plus text-gray-300 text-base"></i>
                                    <input type="file" name="product_images[]" accept="image/*" class="hidden img-input" onchange="handleImageUpload(this)">
                                </label>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <label class="font-semibold text-gray-700"><span class="text-red-500">*</span> ชื่อสินค้า</label>
                                <span class="text-gray-400 text-[11px]"><span id="titleCount">0</span>/120</span>
                            </div>
                            <input type="text" id="finalProductName" name="product_name" maxlength="120" required oninput="updateTitleCount(this)" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-[#2D6A4F]">
                        </div>

                        <div>
                            <label class="block font-semibold text-gray-700 mb-1"><span class="text-red-500">*</span> รายละเอียดสินค้า</label>
                            <textarea id="productDescription" name="description" rows="5" required oninput="updateDescCheck(this)" placeholder="อธิบายคุณลักษณะ จุดเด่น วิธีการปลูกออร์แกนิก หรือการเก็บรักษา..." class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-[#2D6A4F]"></textarea>
                        </div>
                    </div>

                    <!-- Card 2: ข้อมูลการขาย -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-6 text-xs">
                        <h2 class="text-sm font-bold text-gray-800 border-b pb-3">ข้อมูลการขาย</h2>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block font-semibold text-gray-700 mb-1"><span class="text-red-500">*</span> ราคา (บาท)</label>
                                <input type="number" step="0.01" name="price" required placeholder="0.00" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-[#2D6A4F]">
                            </div>
                            <div>
                                <label class="block font-semibold text-gray-700 mb-1"><span class="text-red-500">*</span> คลัง (จำนวนสินค้า)</label>
                                <input type="number" name="stock" value="10" required class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-[#2D6A4F]">
                            </div>
                            <div>
                                <label class="block font-semibold text-gray-700 mb-1">เลข SKU สินค้า</label>
                                <input type="text" name="sku" placeholder="เช่น HUG-VEG-001" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-[#2D6A4F]">
                            </div>
                        </div>
                    </div>

                    <!-- Card 3: การจัดส่ง -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-6 text-xs">
                        <h2 class="text-sm font-bold text-gray-800 border-b pb-3">การจัดส่ง</h2>

                        <div>
                            <label class="block font-semibold text-gray-700 mb-1">น้ำหนักพัสดุ (กิโลกรัม)</label>
                            <input type="number" step="0.01" name="weight" placeholder="0.5" class="w-full max-w-xs px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-[#2D6A4F]">
                        </div>
                    </div>

                    <!-- Footer Action Buttons -->
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                        <a href="/seller/products.php" class="px-6 py-2 border border-gray-300 rounded text-xs font-semibold text-gray-600 hover:bg-gray-100 transition">
                            ยกเลิก
                        </a>
                        <button type="submit" class="bg-[#1B4332] hover:bg-[#2D6A4F] text-white px-8 py-2 rounded text-xs font-semibold shadow transition">
                            บันทึกและเผยแพร่
                        </button>
                    </div>

                </div>
            </div>
        </form>

    </main>

    <script>
        const categoryData = {
            'ความงามและของใช้ส่วนตัว': {
                'ผลิตภัณฑ์ดูแลผิวหน้า': ['เซรั่ม', 'ครีมบำรุงผิว', 'คลีนซิ่ง/โฟมล้างหน้า'],
                'เครื่องสำอาง': ['ลิปสติก', 'แป้งพัฟ/รองพื้น', 'อายไลน์เนอร์'],
                'ของใช้ส่วนตัว': ['สบู่และครีมอาบน้ำ', 'แชมพูและครีมนวดผม', 'ผลิตภัณฑ์ดูแลช่องปาก']
            },
            'เสื้อผ้าแฟชั่นผู้ชาย': {
                'เสื้อผู้ชาย': ['เสื้อยืด', 'เสื้อเชิ้ต', 'เสื้อโปโล'],
                'กางเกงผู้ชาย': ['กางเกงขายาว', 'กางเกงขาสั้น', 'กางเกงยีนส์']
            },
            'กระเป๋า': {
                'กระเป๋าผู้หญิง': ['กระเป๋าถือ', 'กระเป๋าสะพายข้าง', 'กระเป๋าสตางค์'],
                'กระเป๋าผู้ชาย': ['กระเป๋าเป้', 'กระเป๋าเดินทาง', 'กระเป๋าคาดอก']
            },
            'รองเท้าผู้หญิง': {
                'รองเท้าแฟชั่น': ['รองเท้าส้นสูง', 'รองเท้าคัทชู', 'รองเท้าแตะ'],
                'รองเท้าออกกำลังกาย': ['รองเท้าวิ่ง', 'รองเท้าสนีกเกอร์']
            },
            'นาฬิกาและแว่นตา': {
                'นาฬิกา': ['นาฬิกาข้อมือผู้ชาย', 'นาฬิกาข้อมือผู้หญิง', 'สมาร์ทวอทช์'],
                'แว่นตา': ['แว่นตากันแดด', 'กรอบแว่นสายตา']
            },
            'อุปกรณ์อิเล็กทรอนิกส์': {
                'หูฟังและลำโพง': ['หูฟังบลูทูธ', 'หูฟังไร้สาย', 'ลำโพงบลูทูธ'],
                'อุปกรณ์เกมมิ่ง': ['หูฟังเกมมิ่ง', 'คีย์บอร์ดเกมมิ่ง']
            },
            'เครื่องใช้ไฟฟ้าภายในบ้าน': {
                'เครื่องใช้ไฟฟ้าในครัว': ['หม้อทอดไร้น้ำมัน', 'ไมโครเวฟ', 'หม้อหุงข้าว'],
                'เครื่องใช้ไฟฟ้าทำความสะอาด': ['เครื่องดูดฝุ่น', 'พัดลม', 'เครื่องฟอกอากาศ']
            },
            'กล้องและอุปกรณ์ถ่ายภาพ': {
                'กล้องถ่ายภาพ': ['กล้องมิลเลอร์เลส', 'กล้อง DSLR', 'กล้องแอคชั่น'],
                'อุปกรณ์เสริมกล้อง': ['ขาตั้งกล้อง', 'เลนส์กล้อง', 'เมมโมรี่การ์ด']
            },
            'ของเล่น สินค้าแม่และเด็ก': {
                'ของเล่นเด็ก': ['ตัวต่อ/บล็อกไม้', 'ของเล่นเสริมพัฒนาการ'],
                'ของใช้แม่และเด็ก': ['ขวดนม', 'ผ้าอ้อม', 'รถเข็นเด็ก']
            },
            'สัตว์เลี้ยง': {
                'อาหารสัตว์เลี้ยง': ['อาหารสุนัข', 'อาหารแมว', 'ขนมสัตว์เลี้ยง'],
                'ของใช้สัตว์เลี้ยง': ['ทรายแมว', 'คอนโดแมว', 'กรงและกระเป๋าเดินทาง']
            },
            'กลุ่มผลิตภัณฑ์เพื่อสุขภาพ': {
                'อาหารเสริม': ['วิตามินและแร่ธาตุ', 'คอลลาเจน', 'สมุนไพรบำรุงสุขภาพ'],
                'อุปกรณ์เพื่อสุขภาพ': ['หน้ากากอนามัย', 'เจลล้างมือ', 'เครื่องวัดความดัน']
            },
            'เสื้อผ้าแฟชั่นผู้หญิง': {
                'เสื้อและชุดชุดเดรส': ['ชุดเดรส', 'เสื้อยืด', 'เสื้อเบลเซอร์'],
                'กางเกงและกระโปรง': ['กางเกงขายาว', 'กระโปรง', 'กางเกงยีนส์']
            },
            'รองเท้าผู้ชาย': {
                'รองเท้าแฟชั่น': ['รองเท้าสนีกเกอร์', 'รองเท้าหนัง', 'รองเท้าแตะ'],
                'รองเท้ากีฬา': ['รองเท้าวิ่ง', 'รองเท้าฟุตบอล']
            },
            'เครื่องประดับ': {
                'เครื่องประดับแฟชั่น': ['สร้อยคอ', 'แหวน', 'ต่างหู', 'กำไลข้อมือ']
            },
            'เครื่องใช้ในบ้าน': {
                'ห้องนอน': ['ชุดเครื่องนอน', 'หมอน', 'เตียงและฟูก'],
                'ห้องครัว': ['อุปกรณ์ทำอาหาร', 'จานชามและแก้วน้ำ', 'กล่องถนอมอาหาร']
            },
            'มือถือ และแท็บเล็ต': {
                'โทรศัพท์มือถือ': ['iOS', 'Android'],
                'แท็บเล็ต': ['iPad', 'Android Tablet'],
                'อุปกรณ์เสริมมือถือ': ['เคสและฟิล์มกันรอย', 'สายชาร์จและอะแดปเตอร์', 'เพาเวอร์แบงค์']
            },
            'คอมพิวเตอร์และแล็ปท็อป': {
                'แล็ปท็อป': ['แล็ปท็อปทำงาน', 'เกมมิ่งแล็ปท็อป'],
                'อุปกรณ์คอมพิวเตอร์': ['เมาส์และคีย์บอร์ด', 'จอมอนิเตอร์', 'อุปกรณ์จัดเก็บข้อมูล']
            },
            'อาหารและเครื่องดื่ม': {
                'อาหารแห้งและแปรรูป': ['ผักและผลไม้ออร์แกนิก', 'ข้าวสาร/ธัญพืช', 'เครื่องปรุงรส'],
                'เครื่องดื่ม': ['ชาออร์แกนิก', 'กาแฟ', 'น้ำผลไม้สด']
            },
            'กีฬาและกิจกรรมกลางแจ้ง': {
                'อุปกรณ์กีฬา': ['จักรยาน', 'อุปกรณ์ฟิตเนส', 'ลูกฟุตบอล/บาสเกตบอล'],
                'แคมป์ปิ้ง': ['เต็นท์', 'เก้าอี้สนาม', 'กระเป๋าเดินป่า']
            },
            'เกมและอุปกรณ์เสริม': {
                'เครื่องคอนโซล': ['PlayStation', 'Nintendo Switch', 'Xbox'],
                'แผ่นและอุปกรณ์เกม': ['แผ่นเกม', 'จอยสติ๊ก']
            }
        };

        let mainCatSelected = '';
        let subCatSelected = '';
        let finalCatSelected = '';

        function selectMainCat(el, catName) {
            document.querySelectorAll('#mainCatList .cat-item').forEach(i => i.classList.remove('bg-green-100', 'font-bold'));
            el.classList.add('bg-green-100', 'font-bold');

            mainCatSelected = catName;
            subCatSelected = '';
            finalCatSelected = '';

            const subBox = document.getElementById('subCatList');
            const finalBox = document.getElementById('finalCatList');
            finalBox.innerHTML = '<div class="p-4 text-gray-400 text-center">กรุณาเลือกหมวดหมู่ย่อย</div>';

            if (categoryData[catName]) {
                let html = '';
                for (let subName in categoryData[catName]) {
                    html += `
                        <div onclick="selectSubCat(this, '${subName}')" class="subcat-item px-4 py-2 hover:bg-green-50 flex items-center justify-between cursor-pointer text-gray-600">
                            <span>${subName}</span>
                            <i class="fa-solid fa-chevron-right text-[10px] text-gray-400"></i>
                        </div>
                    `;
                }
                subBox.innerHTML = html;
            } else {
                subBox.innerHTML = `
                    <div onclick="selectSubCat(this, 'ทั่วไป')" class="subcat-item px-4 py-2 hover:bg-green-50 flex items-center justify-between cursor-pointer text-gray-600">
                        <span>ทั่วไป</span>
                        <i class="fa-solid fa-chevron-right text-[10px] text-gray-400"></i>
                    </div>
                `;
            }

            updateSelectedText();
        }

        function selectSubCat(el, subName) {
            document.querySelectorAll('#subCatList .subcat-item').forEach(i => i.classList.remove('bg-green-100', 'font-bold'));
            el.classList.add('bg-green-100', 'font-bold');

            subCatSelected = subName;
            finalCatSelected = '';

            const finalBox = document.getElementById('finalCatList');

            if (categoryData[mainCatSelected] && categoryData[mainCatSelected][subName]) {
                let html = '';
                categoryData[mainCatSelected][subName].forEach(finalName => {
                    html += `
                        <div onclick="selectFinalCat(this, '${finalName}')" class="finalcat-item px-4 py-2 hover:bg-green-50 cursor-pointer text-gray-600">
                            <span>${finalName}</span>
                        </div>
                    `;
                });
                finalBox.innerHTML = html;
            } else {
                finalBox.innerHTML = `
                    <div onclick="selectFinalCat(this, 'สินค้าทั่วไป')" class="finalcat-item px-4 py-2 hover:bg-green-50 cursor-pointer text-gray-600">
                        <span>สินค้าทั่วไป</span>
                    </div>
                `;
            }

            updateSelectedText();
        }

        function selectFinalCat(el, finalName) {
            document.querySelectorAll('#finalCatList .finalcat-item').forEach(i => i.classList.remove('bg-green-100', 'font-bold'));
            el.classList.add('bg-green-100', 'font-bold');

            finalCatSelected = finalName;
            updateSelectedText();
        }

        function updateSelectedText() {
            let text = '-';
            if (mainCatSelected) text = mainCatSelected;
            if (subCatSelected) text += ' > ' + subCatSelected;
            if (finalCatSelected) text += ' > ' + finalCatSelected;
            document.getElementById('selectedCatText').innerText = text;
        }

        function filterCategories() {
            let input = document.getElementById('searchCategoryInput').value.toLowerCase();
            let items = document.querySelectorAll('#mainCatList .cat-item');
            items.forEach(item => {
                let text = item.innerText.toLowerCase();
                item.style.display = text.includes(input) ? 'flex' : 'none';
            });
        }

        function goToFormStep() {
            if (!mainCatSelected || !subCatSelected || !finalCatSelected) {
                alert('กรุณาเลือกหมวดหมู่ให้ครบถ้วน (หลัก > ย่อย > ปลายทาง)');
                return;
            }

            let fullCatPath = mainCatSelected + ' > ' + subCatSelected + ' > ' + finalCatSelected;
            document.getElementById('hiddenCategory').value = fullCatPath;

            let initialTitle = document.getElementById('initialProductName').value;
            if (initialTitle) {
                document.getElementById('finalProductName').value = initialTitle;
                updateTitleCount(document.getElementById('finalProductName'));
            }

            document.getElementById('categoryStep').classList.add('hidden');
            document.getElementById('productForm').classList.remove('hidden');
            window.scrollTo(0, 0);
        }

        function handleImageUpload(input) {
            if (input.files && input.files[0]) {
                let reader = new FileReader();
                let label = input.parentElement;

                reader.onload = function(e) {
                    label.style.backgroundImage = `url('${e.target.result}')`;
                    label.style.backgroundSize = 'cover';
                    label.style.backgroundPosition = 'center';
                    let icons = label.querySelectorAll('i, span');
                    icons.forEach(el => el.style.display = 'none');
                }
                reader.readAsDataURL(input.files[0]);
                checkQualityBenchmarks();
            }
        }

        function updateTitleCount(input) {
            let len = input.value.length;
            document.getElementById('titleCount').innerText = len;

            let checkTitle = document.getElementById('checkTitle');
            if (len >= 25 && len <= 100) {
                checkTitle.classList.remove('text-gray-500');
                checkTitle.classList.add('text-green-600', 'font-semibold');
                checkTitle.querySelector('i').className = 'fa-solid fa-circle-check text-green-500 mt-0.5';
            } else {
                checkTitle.classList.add('text-gray-500');
                checkTitle.classList.remove('text-green-600', 'font-semibold');
                checkTitle.querySelector('i').className = 'fa-regular fa-circle-check text-gray-300 mt-0.5';
            }
            checkQualityBenchmarks();
        }

        function updateDescCheck(textarea) {
            let len = textarea.value.length;
            let checkDesc = document.getElementById('checkDesc');
            if (len >= 100) {
                checkDesc.classList.remove('text-gray-500');
                checkDesc.classList.add('text-green-600', 'font-semibold');
                checkDesc.querySelector('i').className = 'fa-solid fa-circle-check text-green-500 mt-0.5';
            } else {
                checkDesc.classList.add('text-gray-500');
                checkDesc.classList.remove('text-green-600', 'font-semibold');
                checkDesc.querySelector('i').className = 'fa-regular fa-circle-check text-gray-300 mt-0.5';
            }
            checkQualityBenchmarks();
        }

        function checkQualityBenchmarks() {
            let inputs = document.querySelectorAll('.img-input');
            let count = 0;
            inputs.forEach(i => {
                if (i.files && i.files.length > 0) count++;
            });

            let checkImg = document.getElementById('checkImg');
            if (count >= 3) {
                checkImg.classList.remove('text-gray-500');
                checkImg.classList.add('text-green-600', 'font-semibold');
                checkImg.querySelector('i').className = 'fa-solid fa-circle-check text-green-500 mt-0.5';
            } else {
                checkImg.classList.add('text-gray-500');
                checkImg.classList.remove('text-green-600', 'font-semibold');
                checkImg.querySelector('i').className = 'fa-regular fa-circle-check text-gray-300 mt-0.5';
            }
        }
    </script>
</body>
</html>