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

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$orderNumber = $_GET['order'] ?? '';
if (empty($orderNumber)) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ? AND user_id = ?");
$stmt->execute([$orderNumber, $_SESSION['user_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo "ไม่พบคำสั่งซื้อนี้";
    exit;
}

$successMsg = '';
$errorMsg = '';

// เมื่อลูกค้ากดปุ่มแจ้งโอนเงิน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    try {
        // อัปเดตสถานะการชำระเงินเป็น 'pending_review' (รอตรวจสอบ)
        $upd = $pdo->prepare("UPDATE orders SET payment_status = 'pending_review' WHERE id = ? AND user_id = ?");
        $upd->execute([$order['id'], $_SESSION['user_id']]);

        $successMsg = 'แจ้งชำระเงินสำเร็จ! ระบบกำลังนำท่านไปหน้าคำสั่งซื้อเพื่อรอผู้ขายตรวจสอบ...';
        header("Refresh: 2; url=orders.php");
        exit;
    } catch (PDOException $e) {
        $errorMsg = 'ไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่อีกครั้ง';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แจ้งชำระเงิน | Hugmart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 flex flex-col min-h-screen font-sans">

    <header class="bg-white shadow-sm border-b sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 h-20 flex items-center justify-between">
            <a href="index.php" class="text-3xl font-extrabold text-[#1B4332]">Hugmart</a>
            <span class="text-xs text-gray-500">คำสั่งซื้อ: <b><?php echo htmlspecialchars($orderNumber); ?></b></span>
        </div>
    </header>

    <main class="flex-grow max-w-2xl w-full mx-auto px-4 py-8 space-y-6">

        <?php if (!empty($successMsg)): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-4 text-center text-sm font-bold">
                <i class="fa-solid fa-circle-check text-lg mr-1"></i> <?php echo $successMsg; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMsg)): ?>
            <div class="bg-red-50 border border-red-200 text-red-600 rounded-lg p-4 text-center text-sm font-bold">
                <i class="fa-solid fa-triangle-exclamation text-lg mr-1"></i> <?php echo $errorMsg; ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow p-6 text-center space-y-4">
            <h2 class="text-lg font-bold text-gray-800">โอนเงินผ่านบัญชีกลาง Hugmart (SCB)</h2>
            <p class="text-xs text-gray-500">กรุณาโอนเงินตามยอดสุทธิผ่านแอปพลิเคชันธนาคารของคุณ</p>

            <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 max-w-md mx-auto text-left space-y-2">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-purple-900 text-white font-bold rounded-full flex items-center justify-center text-sm shrink-0">SCB</div>
                    <div>
                        <div class="text-xs text-gray-500">ธนาคารไทยพาณิชย์ (SCB)</div>
                        <div class="text-lg font-bold text-purple-900 tracking-wider">433-190275-9</div>
                    </div>
                </div>
                <div class="text-xs text-gray-600 pt-2 border-t border-blue-100 flex justify-between">
                    <span>ชื่อบัญชี: <b>Hugmart Marketplace</b></span>
                    <span class="text-purple-700 font-semibold">บัญชีกลางตัวกลาง</span>
                </div>
            </div>

            <div class="py-2">
                <span class="text-xs text-gray-500">ยอดที่ต้องชำระสุทธิ</span>
                <div class="text-3xl font-extrabold text-[#2D6A4F]">฿<?php echo number_format($order['total_amount'] ?? 0, 2); ?></div>
            </div>

            <form method="POST" class="pt-4">
                <button type="submit" name="confirm_payment" class="w-full bg-[#2D6A4F] hover:bg-[#1B4332] text-white font-bold py-3.5 rounded-lg shadow transition text-sm flex items-center justify-center gap-2">
                    <i class="fa-solid fa-file-invoice-dollar text-lg"></i> แจ้งโอนเงิน / ส่งหลักฐาน (รอตรวจสอบ)
                </button>
            </form>
            <p class="text-[11px] text-gray-400 mt-2">เมื่อโอนเงินเรียบร้อยแล้ว ให้กดปุ่มแจ้งโอน สถานะจะเปลี่ยนเป็น "รอตรวจสอบ" เพื่อให้ผู้ผู้ขายตรวจสอบสลิปต่อไป</p>
        </div>

    </main>

    <footer class="bg-gray-100 py-6 border-t text-center text-xs text-gray-500 mt-auto">
        <p>© 2026 Hugmart Marketplace. All Rights Reserved.</p>
    </footer>

</body>
</html>