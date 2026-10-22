<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../src/Config/Database.php';

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

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?error=' . urlencode('กรุณาเข้าสู่ระบบ'));
    exit;
}

$userId = $_SESSION['user_id'];
$orderNumber = trim($_GET['order'] ?? '');

$order = null;
if ($orderNumber !== '') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$orderNumber, $userId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('[Hugmart payment_success] ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ผลการชำระเงิน | Hugmart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 flex flex-col min-h-screen font-sans">

    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center">
            <a href="index.php" class="text-3xl font-extrabold text-[#1B4332] tracking-tight">Hugmart</a>
        </div>
    </header>

    <main class="flex-grow max-w-xl w-full mx-auto px-4 py-10">
        <div class="bg-white rounded-lg shadow p-8 text-center">
            <?php if ($order): ?>
                <div class="w-20 h-20 bg-green-50 text-[#2D6A4F] rounded-full flex items-center justify-center mx-auto mb-4 text-4xl">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <h1 class="text-xl font-bold text-gray-800 mb-1">บันทึกการชำระเงินสำเร็จ</h1>
                <p class="text-sm text-gray-500 mb-5">ขอบคุณที่ใช้บริการ Hugmart ระบบได้รับคำสั่งซื้อของคุณแล้ว</p>

                <div class="bg-gray-50 rounded-lg p-4 text-sm text-left space-y-2 mb-6">
                    <div class="flex justify-between">
                        <span class="text-gray-500">เลขที่คำสั่งซื้อ</span>
                        <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($order['order_number']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">ยอดที่ชำระ</span>
                        <span class="font-bold text-[#2D6A4F]">฿<?php echo number_format((float)$order['total_amount'], 2); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">วิธีชำระเงิน</span>
                        <span class="text-gray-800">โอนเงินผ่านบัญชีกลาง (SCB)</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">สถานะ</span>
                        <span class="text-green-600 font-semibold">ชำระเงินแล้ว / รอตรวจสอบ</span>
                    </div>
                </div>

                <div class="flex gap-3 justify-center">
                    <a href="index.php" class="bg-[#2D6A4F] hover:bg-[#1B4332] text-white font-medium px-6 py-2.5 rounded text-sm transition">เลือกซื้อสินค้าต่อ</a>
                </div>
            <?php else: ?>
                <div class="w-20 h-20 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4 text-4xl">
                    <i class="fa-solid fa-circle-exclamation"></i>
                </div>
                <h1 class="text-xl font-bold text-gray-800 mb-2">ไม่พบข้อมูลคำสั่งซื้อ</h1>
                <p class="text-sm text-gray-500 mb-6">ไม่พบหมายเลขคำสั่งซื้อที่คุณระบุในระบบ</p>
                <a href="index.php" class="inline-block bg-[#2D6A4F] hover:bg-[#1B4332] text-white font-medium px-6 py-2.5 rounded text-sm transition">กลับหน้าหลัก</a>
            <?php endif; ?>
        </div>
    </main>

    <footer class="bg-gray-100 py-6 border-t text-center text-xs text-gray-500 mt-auto">
        <p>© 2026 Hugmart Marketplace. All Rights Reserved.</p>
    </footer>
</body>
</html>