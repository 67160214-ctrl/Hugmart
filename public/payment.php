<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../src/Config/Database.php';
require_once __DIR__ . '/../src/Config/Payment.php';
require_once __DIR__ . '/../src/Services/OrderService.php';

if (!isset($pdo) && class_exists('Database')) {
    if (method_exists('Database', 'getConnection')) {
        $pdo = Database::getConnection();
    } elseif (method_exists('Database', 'getInstance')) {
        $pdo = Database::getInstance()->getConnection();
    } else {
        $db = new Database();
        $pdo = method_exists($db, 'getConnection') ? $db->getConnection() : ($db->pdo ?? null);
    }
}

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?error=' . urlencode('กรุณาเข้าสู่ระบบก่อนชำระเงิน'));
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$userId = $_SESSION['user_id'];
$cfg = paymentConfig();

// ป้องกัน Undefined array key โดยใช้ Null Coalescing Operator (??) ทันที
$promptpayId = $cfg['promptpay_id'] ?? '';
$bankAccount = $cfg['bank_account_number'] ?? '433-190275-9';
$accountReady = ($promptpayId !== '' || $bankAccount !== '');

// รับรายการที่เลือกมาจากรถเข็น เช่น payment.php?items=12,15
$rawItems = explode(',', $_GET['items'] ?? '');
$itemIds = array_values(array_unique(array_filter(array_map('intval', $rawItems), function ($v) {
    return $v > 0;
})));

if (empty($itemIds)) {
    header('Location: cart.php');
    exit;
}

$errorMsg = null;

// โหลดรายการสินค้า
$items = [];
try {
    $items = loadCheckoutItems($pdo, $userId, $itemIds);
} catch (PDOException $e) {
    error_log('[Hugmart payment] ' . $e->getMessage());
}

if (empty($items)) {
    header('Location: cart.php');
    exit;
}

$groupedItems = [];
$grandTotal = 0;
$totalQty = 0;
foreach ($items as $item) {
    $storeName = $item['store_name'] ?: 'ร้านค้าทั่วไป';
    $groupedItems[$storeName][] = $item;
    $grandTotal += round((float)$item['price'], 2) * (int)$item['quantity'];
    $totalQty += (int)$item['quantity'];
}
$grandTotal = round($grandTotal, 2);

// ===== กดสั่งซื้อ: สร้างออเดอร์แล้วไปหน้าโอนเงิน SCB กลาง =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errorMsg = 'คำขอไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองใหม่';
    } elseif ($grandTotal <= 0) {
        $errorMsg = 'ยอดชำระไม่ถูกต้อง';
    } else {
        foreach ($items as $it) {
            $stock = intval($it['stock'] ?? $it['product_stock'] ?? 0);
            if ((int)$it['quantity'] > $stock) {
                $nm = $it['name'] ?? $it['product_name'] ?? 'สินค้า';
                $errorMsg = 'สินค้า "' . $nm . '" มีในสต็อกไม่เพียงพอ (เหลือ ' . $stock . ' ชิ้น) กรุณากลับไปแก้จำนวนในรถเข็น';
                break;
            }
        }
    }

    if ($errorMsg === null) {
        try {
            $existing = findReusableOrder($pdo, $userId, $items);
            $orderNumber = $existing ? $existing['order_number'] : createPendingOrder($pdo, $userId, $items, 'transfer')['order_number'];

            header('Location: payment_transfer.php?order=' . urlencode($orderNumber));
            exit;
        } catch (Throwable $e) {
            error_log('[Hugmart payment] create order failed: ' . $e->getMessage());
            $errorMsg = 'ไม่สามารถสร้างคำสั่งซื้อได้ กรุณาลองใหม่อีกครั้ง';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ชำระเงิน | Hugmart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 flex flex-col min-h-screen font-sans">

    <header class="bg-white shadow-sm border-b sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="index.php" class="text-3xl font-extrabold text-[#1B4332] tracking-tight">Hugmart</a>
                <span class="text-xl font-medium text-gray-700 border-l-2 border-gray-300 pl-4">ชำระเงินผ่านบัญชีกลาง</span>
            </div>
            <a href="cart.php" class="text-sm text-[#2D6A4F] hover:underline flex items-center gap-1">
                <i class="fa-solid fa-arrow-left"></i> กลับไปรถเข็น
            </a>
        </div>
    </header>

    <main class="flex-grow max-w-5xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">

        <?php if ($errorMsg): ?>
            <div class="text-sm bg-red-50 border border-red-200 text-red-600 rounded px-4 py-3">
                <?php echo htmlspecialchars($errorMsg); ?>
            </div>
        <?php endif; ?>

        <!-- รายการสินค้าที่สั่งซื้อ -->
        <?php foreach ($groupedItems as $storeName => $products): ?>
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="border-b p-4 bg-gray-50 flex items-center gap-2 text-sm font-bold text-gray-800">
                    <i class="fa-solid fa-shop text-[#2D6A4F]"></i>
                    <?php echo htmlspecialchars($storeName); ?>
                </div>
                <div class="divide-y divide-gray-100">
                    <?php foreach ($products as $p):
                        $pName = $p['name'] ?? $p['product_name'] ?? 'สินค้าไม่มีชื่อ';
                        $lineTotal = floatval($p['price']) * intval($p['quantity']);
                    ?>
                        <div class="p-4 flex items-center gap-4">
                            <img src="<?php echo htmlspecialchars($p['image_url'] ?: 'assets/images/no-image.png'); ?>" class="w-16 h-16 object-cover rounded border border-gray-200">
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-gray-800 line-clamp-2"><?php echo htmlspecialchars($pName); ?></div>
                                <div class="text-xs text-gray-400 mt-1">฿<?php echo number_format($p['price'], 2); ?> x <?php echo intval($p['quantity']); ?></div>
                            </div>
                            <div class="text-sm font-bold text-[#2D6A4F]">฿<?php echo number_format($lineTotal, 2); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- วิธีชำระเงินผ่านบัญชีกลาง SCB -->
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="text-base font-bold text-gray-800 mb-4">ช่องทางชำระเงิน (ผ่านบัญชีกลาง Hugmart)</h3>
            <div class="border border-[#2D6A4F] bg-green-50 rounded-lg p-4 flex items-center gap-3">
                <div class="w-10 h-10 bg-purple-900 text-white font-bold rounded-full flex items-center justify-center text-xs shrink-0">SCB</div>
                <div class="flex-1">
                    <div class="text-sm font-semibold text-gray-800">โอนเงินเข้าบัญชีกลาง SCB: <span class="text-purple-900 font-bold">433-190275-9</span></div>
                    <div class="text-xs text-gray-500">โอนยอดชำระตามจริงผ่านแอปธนาคาร แล้วกดปุ่มยืนยันการชำระเงินด้านล่าง</div>
                </div>
            </div>
        </div>

        <!-- สรุปยอดชำระ -->
        <div class="bg-white rounded-lg shadow p-5">
            <div class="flex justify-between text-sm text-gray-600 mb-2">
                <span>จำนวนสินค้า</span>
                <span><?php echo number_format($totalQty); ?> ชิ้น</span>
            </div>
            <div class="flex justify-between items-baseline border-t pt-3">
                <span class="text-sm font-semibold text-gray-800">ยอดชำระทั้งหมด</span>
                <span class="text-2xl font-bold text-[#2D6A4F]">฿<?php echo number_format($grandTotal, 2); ?></span>
            </div>

            <form method="POST" action="payment.php?items=<?php echo htmlspecialchars(implode(',', $itemIds)); ?>" onsubmit="lockPayButton()">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <button type="submit" id="payBtn"
                    class="mt-4 w-full bg-[#2D6A4F] hover:bg-[#1B4332] text-white font-bold py-3 rounded shadow transition text-sm flex items-center justify-center gap-2">
                    <i class="fa-solid fa-check-circle"></i> ไปหน้ายืนยันการโอนเงิน ฿<?php echo number_format($grandTotal, 2); ?>
                </button>
            </form>
        </div>

    </main>

    <footer class="bg-gray-100 py-6 border-t text-center text-xs text-gray-500 mt-auto">
        <p>© 2026 Hugmart Marketplace. All Rights Reserved.</p>
    </footer>

    <script>
        function lockPayButton() {
            const btn = document.getElementById('payBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> กำลังดำเนินการ...';
        }
    </script>
</body>
</html>