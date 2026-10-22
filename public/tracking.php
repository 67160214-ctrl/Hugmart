<?php
session_start();
require_once __DIR__ . '/../src/Config/Database.php';

$searchQuery = trim($_GET['tracking_no'] ?? '');
$orderResult = null;
$errorMsg = '';

if (!empty($searchQuery)) {
    try {
        $stmt = $pdo->prepare("SELECT o.*, u.full_name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.tracking_number = ? OR o.id = ?");
        $stmt->execute([$searchQuery, $searchQuery]);
        $orderResult = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$orderResult) {
            $errorMsg = 'ไม่พบข้อมูลพัสดุหรือหมายเลขคำสั่งซื้อนี้ กรุณาตรวจสอบอีกครั้ง';
        }
    } catch (PDOException $e) {
        $errorMsg = 'เกิดข้อผิดพลาดในการค้นหา';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดตามสถานะพัสดุ - Hugmart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans text-gray-800 min-h-screen flex flex-col justify-between">
    <header class="bg-[#1B4332] text-white py-4 shadow-md">
        <div class="max-w-7xl mx-auto px-4 flex justify-between items-center">
            <a href="index.php" class="text-2xl font-extrabold flex items-center gap-2"><i class="fa-solid fa-bag-shopping text-[#52B788]"></i> Hugmart</a>
            <a href="index.php" class="text-xs hover:underline text-green-200"><i class="fa-solid fa-arrow-left"></i> กลับหน้าแรก</a>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 py-12 flex-1 w-full">
        <div class="bg-white rounded-lg shadow p-8 text-center space-y-6">
            <h1 class="text-2xl font-bold text-[#1B4332]"><i class="fa-solid fa-truck-fast text-[#52B788]"></i> ตรวจสอบสถานะพัสดุ</h1>
            <p class="text-xs text-gray-500">กรอกหมายเลขคำสั่งซื้อ หรือ หมายเลขติดตามพัสดุ (Tracking Number) ของคุณ</p>
            
            <form action="tracking.php" method="GET" class="flex gap-2 max-w-lg mx-auto">
                <input type="text" name="tracking_no" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="ระบุเลขพัสดุ หรือ รหัสคำสั่งซื้อ..." class="flex-1 px-4 py-2.5 border border-gray-300 rounded text-xs focus:outline-none focus:border-[#2D6A4F]" required>
                <button type="submit" class="bg-[#1B4332] hover:bg-[#2D6A4F] text-white px-6 py-2.5 rounded text-xs font-semibold shadow transition">ค้นหา</button>
            </form>

            <?php if ($errorMsg): ?>
                <div class="p-3 bg-red-50 text-red-700 text-xs rounded border border-red-200"><?php echo htmlspecialchars($errorMsg); ?></div>
            <?php endif; ?>

            <?php if ($orderResult): ?>
                <div class="mt-8 text-left border rounded-lg p-6 bg-gray-50 text-xs space-y-3">
                    <div class="flex justify-between border-b pb-2">
                        <span class="font-bold text-gray-700">หมายเลขคำสั่งซื้อ: #<?php echo $orderResult['id']; ?></span>
                        <span class="text-[#2D6A4F] font-bold"><?php echo htmlspecialchars($orderResult['status']); ?></span>
                    </div>
                    <p><strong>ผู้รับ:</strong> <?php echo htmlspecialchars($orderResult['full_name']); ?></p>
                    <p><strong>บริษัทขนส่ง:</strong> <?php echo htmlspecialchars($orderResult['carrier_name'] ?? 'Kerry / Flash Express'); ?></p>
                    <p><strong>เลขพัสดุ:</strong> <span class="text-blue-600 font-semibold"><?php echo htmlspecialchars($orderResult['tracking_number'] ?? 'กำลังเตรียมจัดส่ง'); ?></span></p>
                    <p><strong>วันที่สั่งซื้อ:</strong> <?php echo $orderResult['created_at']; ?></p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="bg-[#1B4332] text-white py-4 text-center text-xs">
        <p>© 2026 Hugmart Marketplace. All Rights Reserved.</p>
    </footer>
</body>
</html>