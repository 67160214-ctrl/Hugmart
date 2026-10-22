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

$orders = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 50");
    $stmt->execute([$_SESSION['user_id']]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[Hugmart orders] ' . $e->getMessage());
}

// เพิ่มและปรับแต่งสถานะ รวมถึง "รอตรวจสอบ"
$statusLabels = [
    'unpaid'         => ['รอชำระเงิน', 'bg-gray-100 text-gray-600'],
    'pending_review' => ['รอตรวจสอบ', 'bg-amber-50 text-amber-600'],
    'rejected'       => ['สลิปไม่ผ่าน', 'bg-red-50 text-red-600'],
    'paid'           => ['ชำระเงินแล้ว', 'bg-green-50 text-[#2D6A4F]'],
    'shipping'       => ['กำลังจัดส่ง', 'bg-blue-50 text-blue-600'],
    'completed'      => ['สำเร็จ', 'bg-green-100 text-green-800'],
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>คำสั่งซื้อของฉัน | Hugmart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 flex flex-col min-h-screen font-sans">

    <header class="bg-white shadow-sm border-b sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="index.php" class="text-3xl font-extrabold text-[#1B4332] tracking-tight">Hugmart</a>
                <span class="text-xl font-medium text-gray-700 border-l-2 border-gray-300 pl-4">คำสั่งซื้อของฉัน</span>
            </div>
            <a href="index.php" class="text-sm text-[#2D6A4F] hover:underline flex items-center gap-1">
                <i class="fa-solid fa-store"></i> เลือกซื้อสินค้าต่อ
            </a>
        </div>
    </header>

    <main class="flex-grow max-w-4xl w-full mx-auto px-4 sm:px-6 py-6 space-y-4">

        <?php if (empty($orders)): ?>
            <div class="bg-white rounded-lg shadow p-12 text-center">
                <div class="w-20 h-20 bg-green-50 text-[#2D6A4F] rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">
                    <i class="fa-solid fa-box-open"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-800 mb-2">ยังไม่มีคำสั่งซื้อ</h3>
                <a href="index.php" class="inline-block bg-[#2D6A4F] hover:bg-[#1B4332] text-white font-medium px-8 py-3 rounded-md transition shadow mt-2">เลือกช้อปสินค้า</a>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $o):
                $snap = json_decode($o['items_snapshot'], true);
                if (!is_array($snap)) {
                    $snap = [];
                }
                $key = $o['payment_status'];
                $label = $statusLabels[$key] ?? [$key, 'bg-gray-100 text-gray-600'];
                $needsAction = in_array($key, ['unpaid', 'rejected', 'pending_review'], true) && $o['status'] === 'pending';
            ?>
                <div class="bg-white rounded-lg shadow p-5 space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="font-bold text-gray-800 text-sm flex items-center gap-2">
                                <span><?php echo htmlspecialchars($o['order_number']); ?></span>
                                <!-- ปุ่มคลิกเพื่อนำเลขคำสั่งซื้อไปค้นหาในหน้าติดตามพัสดุ -->
                                <a href="tracking.php?q=<?php echo urlencode($o['order_number']); ?>" class="text-[11px] bg-gray-100 hover:bg-[#2D6A4F] hover:text-white text-gray-600 px-2 py-0.5 rounded transition" title="ติดตามพัสดุด้วยเลขนี้">
                                    <i class="fa-solid fa-truck-fast"></i> ติดตามพัสดุ
                                </a>
                            </div>
                            <div class="text-xs text-gray-400 mt-0.5"><?php echo date('d/m/Y H:i', strtotime($o['created_at'])); ?></div>
                        </div>
                        <span class="text-xs font-semibold px-3 py-1 rounded-full <?php echo $label[1]; ?>"><?php echo htmlspecialchars($label[0]); ?></span>
                    </div>

                    <div class="border-t pt-3 text-xs text-gray-600 space-y-1">
                        <?php foreach ($snap as $it): ?>
                            <div class="flex justify-between gap-4">
                                <span class="truncate"><?php echo htmlspecialchars($it['name']); ?> x <?php echo (int)$it['quantity']; ?></span>
                                <span>฿<?php echo number_format((float)$it['price'] * (int)$it['quantity'], 2); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($key === 'rejected' && !empty($o['reject_reason'])): ?>
                        <div class="text-xs bg-red-50 border border-red-200 text-red-600 rounded px-3 py-2">
                            เหตุผล: <?php echo htmlspecialchars($o['reject_reason']); ?>
                        </div>
                    <?php endif; ?>

                    <div class="border-t pt-3 flex items-center justify-between">
                        <div class="text-sm">ยอดรวม <span class="font-bold text-[#2D6A4F]">฿<?php echo number_format((float)$o['total_amount'], 2); ?></span></div>
                        
                        <?php if ($key === 'pending_review'): ?>
                            <span class="text-xs text-amber-600 font-semibold bg-amber-50 px-3 py-1.5 rounded border border-amber-200">
                                <i class="fa-solid fa-clock"></i> กำลังรอผู้ขายตรวจสอบสลิป
                            </span>
                        <?php elseif ($needsAction): ?>
                            <a href="payment_transfer.php?order=<?php echo urlencode($o['order_number']); ?>" class="bg-[#2D6A4F] hover:bg-[#1B4332] text-white text-xs font-bold px-4 py-2 rounded transition">
                                โอนเงิน / แนบสลิป
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </main>

    <footer class="bg-gray-100 py-6 border-t text-center text-xs text-gray-500 mt-auto">
        <p>© 2026 Hugmart Marketplace. All Rights Reserved.</p>
    </footer>
</body>
</html>