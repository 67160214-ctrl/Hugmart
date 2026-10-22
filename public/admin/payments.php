<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Config/Payment.php';
require_once __DIR__ . '/../../src/Services/OrderService.php';

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
    header('Location: ../login.php?error=' . urlencode('กรุณาเข้าสู่ระบบ'));
    exit;
}

if (!isAdminUser($pdo, $_SESSION['user_id'])) {
    http_response_code(403);
    echo "<div style='text-align:center; margin-top:50px; font-family:sans-serif;'>
            <h2>ไม่มีสิทธิ์เข้าถึงหน้านี้</h2>
            <p style='color:#666'>ต้องเป็นผู้ใช้ที่มี role = admin เท่านั้น</p>
            <a href='../index.php'>กลับสู่หน้าหลัก</a>
          </div>";
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$tabs = [
    'pending_review' => 'รอตรวจสอบ',
    'paid'           => 'ชำระแล้ว',
    'rejected'       => 'ปฏิเสธ',
    'unpaid'         => 'ยังไม่แนบสลิป',
];
$tab = $_GET['status'] ?? 'pending_review';
if (!isset($tabs[$tab])) {
    $tab = 'pending_review';
}

// ===== อนุมัติ / ปฏิเสธ =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $flash = ['type' => 'error', 'msg' => 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง'];
    $action = $_POST['action'] ?? '';
    $orderId = (int)($_POST['order_id'] ?? 0);

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $flash['msg'] = 'คำขอไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองใหม่';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND payment_method = 'transfer' LIMIT 1");
            $stmt->execute([$orderId]);
            $o = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$o) {
                $flash['msg'] = 'ไม่พบคำสั่งซื้อ';
            } elseif ($o['payment_status'] !== 'pending_review') {
                $flash['msg'] = 'คำสั่งซื้อนี้ไม่อยู่ในสถานะรอตรวจสอบ';
            } elseif ($action === 'approve') {
                $snap = json_decode($o['items_snapshot'], true);
                $problems = is_array($snap) ? orderStockProblems($pdo, $snap) : ['ข้อมูลรายการสินค้าเสียหาย'];

                if (!empty($problems)) {
                    $flash['msg'] = 'อนุมัติไม่ได้ สต็อกไม่พอ: ' . implode(', ', $problems) . ' (ควรปฏิเสธและคืนเงินลูกค้า)';
                } elseif (fulfillOrder($pdo, $orderId, 'transfer-slip')) {
                    $upd = $pdo->prepare("UPDATE orders SET reviewed_by = ?, reviewed_at = NOW(), reject_reason = NULL WHERE id = ?");
                    $upd->execute([$_SESSION['user_id'], $orderId]);
                    $flash = ['type' => 'success', 'msg' => 'อนุมัติคำสั่งซื้อ ' . $o['order_number'] . ' เรียบร้อยแล้ว'];
                } else {
                    $flash['msg'] = 'อนุมัติไม่สำเร็จ กรุณาตรวจสอบ log ของระบบ';
                }
            } elseif ($action === 'reject') {
                $reason = trim($_POST['reason'] ?? '');
                if ($reason === '') {
                    $flash['msg'] = 'กรุณาระบุเหตุผลที่ปฏิเสธ';
                } else {
                    $reason = mb_substr($reason, 0, 200, 'UTF-8');
                    $upd = $pdo->prepare("
                        UPDATE orders
                        SET payment_status = 'rejected', reject_reason = ?, reviewed_by = ?, reviewed_at = NOW()
                        WHERE id = ? AND payment_status = 'pending_review'
                    ");
                    $upd->execute([$reason, $_SESSION['user_id'], $orderId]);
                    $flash = ['type' => 'success', 'msg' => 'ปฏิเสธคำสั่งซื้อ ' . $o['order_number'] . ' แล้ว'];
                }
            }
        } catch (PDOException $e) {
            error_log('[Hugmart admin payments] ' . $e->getMessage());
            $flash['msg'] = 'เกิดข้อผิดพลาดกับฐานข้อมูล';
        }
    }

    $_SESSION['admin_flash'] = $flash;
    header('Location: payments.php?status=' . urlencode($tab));
    exit;
}

$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

// จำนวนในแต่ละแท็บ
$counts = array_fill_keys(array_keys($tabs), 0);
try {
    $rows = $pdo->query("SELECT payment_status, COUNT(*) AS c FROM orders WHERE payment_method = 'transfer' GROUP BY payment_status")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        if (isset($counts[$r['payment_status']])) {
            $counts[$r['payment_status']] = (int)$r['c'];
        }
    }
} catch (PDOException $e) {
    error_log('[Hugmart admin payments] counts: ' . $e->getMessage());
}

// รายการออเดอร์
$orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT o.*, u.full_name
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.payment_method = 'transfer' AND o.payment_status = ?
        ORDER BY o.slip_uploaded_at DESC, o.id DESC
        LIMIT 100
    ");
    $stmt->execute([$tab]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $stmt = $pdo->prepare("
            SELECT o.* FROM orders o
            WHERE o.payment_method = 'transfer' AND o.payment_status = ?
            ORDER BY o.id DESC LIMIT 100
        ");
        $stmt->execute([$tab]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        error_log('[Hugmart admin payments] list: ' . $e2->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตรวจสอบการชำระเงิน | Hugmart Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen font-sans">

    <header class="bg-[#1B4332] text-white shadow">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
            <div class="font-extrabold text-xl">Hugmart <span class="text-[#52B788] font-semibold text-sm ml-2">Admin</span></div>
            <a href="../index.php" class="text-sm text-green-100 hover:text-white"><i class="fa-solid fa-house"></i> หน้าร้าน</a>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-6 space-y-4">
        <h1 class="text-lg font-bold text-gray-800">ตรวจสอบการชำระเงิน (โอนเงิน/สลิป)</h1>

        <?php if ($flash): ?>
            <div class="text-sm rounded px-4 py-3 border <?php echo $flash['type'] === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-600'; ?>">
                <?php echo htmlspecialchars($flash['msg']); ?>
            </div>
        <?php endif; ?>

        <!-- แท็บสถานะ -->
        <div class="flex flex-wrap gap-2">
            <?php foreach ($tabs as $key => $label): ?>
                <a href="payments.php?status=<?php echo $key; ?>"
                   class="px-4 py-2 rounded text-sm font-medium border transition <?php echo $tab === $key ? 'bg-[#2D6A4F] text-white border-[#2D6A4F]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#2D6A4F]'; ?>">
                    <?php echo htmlspecialchars($label); ?>
                    <span class="ml-1 text-xs opacity-80">(<?php echo $counts[$key]; ?>)</span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($orders)): ?>
            <div class="bg-white rounded-lg shadow p-10 text-center text-gray-400 text-sm">ไม่มีรายการในสถานะนี้</div>
        <?php else: ?>
            <?php foreach ($orders as $o):
                $snap = json_decode($o['items_snapshot'], true);
                if (!is_array($snap)) {
                    $snap = [];
                }
            ?>
                <div class="bg-white rounded-lg shadow p-5 grid grid-cols-1 md:grid-cols-12 gap-5">
                    <!-- ข้อมูลออเดอร์ -->
                    <div class="md:col-span-7 space-y-3">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="font-bold text-gray-800"><?php echo htmlspecialchars($o['order_number']); ?></div>
                                <div class="text-xs text-gray-400 mt-0.5">
                                    ลูกค้า: <?php echo htmlspecialchars($o['full_name'] ?? ('ผู้ใช้ #' . $o['user_id'])); ?>
                                    <?php if (!empty($o['slip_uploaded_at'])): ?>
                                        · ส่งสลิป <?php echo date('d/m/Y H:i', strtotime($o['slip_uploaded_at'])); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs text-gray-400">ยอดที่ต้องได้รับ</div>
                                <div class="text-xl font-bold text-[#2D6A4F]">฿<?php echo number_format((float)$o['total_amount'], 2); ?></div>
                            </div>
                        </div>

                        <div class="border-t pt-3 text-xs text-gray-600 space-y-1">
                            <?php foreach ($snap as $it): ?>
                                <div class="flex justify-between gap-4">
                                    <span class="truncate"><?php echo htmlspecialchars($it['name']); ?> x <?php echo (int)$it['quantity']; ?></span>
                                    <span>฿<?php echo number_format((float)$it['price'] * (int)$it['quantity'], 2); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($tab === 'rejected' && !empty($o['reject_reason'])): ?>
                            <div class="text-xs bg-red-50 border border-red-200 text-red-600 rounded px-3 py-2">
                                เหตุผลที่ปฏิเสธ: <?php echo htmlspecialchars($o['reject_reason']); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($tab === 'pending_review'): ?>
                            <div class="border-t pt-3 flex flex-col sm:flex-row gap-3">
                                <form method="POST" action="payments.php?status=<?php echo $tab; ?>" onsubmit="return confirm('ยืนยันว่าเงินเข้าบัญชีครบ ฿<?php echo number_format((float)$o['total_amount'], 2); ?> แล้ว?')">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="order_id" value="<?php echo (int)$o['id']; ?>">
                                    <button type="submit" class="bg-[#2D6A4F] hover:bg-[#1B4332] text-white font-bold px-5 py-2 rounded text-sm transition">
                                        <i class="fa-solid fa-check mr-1"></i> อนุมัติ
                                    </button>
                                </form>

                                <form method="POST" action="payments.php?status=<?php echo $tab; ?>" class="flex flex-1 gap-2">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="order_id" value="<?php echo (int)$o['id']; ?>">
                                    <input type="text" name="reason" required maxlength="200" placeholder="เหตุผลที่ปฏิเสธ เช่น ยอดไม่ตรง"
                                        class="flex-1 border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:border-red-400">
                                    <button type="submit" class="border border-red-300 text-red-600 hover:bg-red-50 font-medium px-4 py-2 rounded text-sm transition">
                                        ปฏิเสธ
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- สลิป -->
                    <div class="md:col-span-5">
                        <?php if (!empty($o['slip_image'])): ?>
                            <a href="../slip_view.php?order=<?php echo urlencode($o['order_number']); ?>" target="_blank" class="block">
                                <img src="../slip_view.php?order=<?php echo urlencode($o['order_number']); ?>" class="w-full max-h-72 object-contain bg-gray-50 border border-gray-200 rounded">
                            </a>
                            <div class="text-[11px] text-gray-400 mt-1 text-center">คลิกเพื่อดูภาพเต็ม</div>
                        <?php else: ?>
                            <div class="h-full min-h-[8rem] flex items-center justify-center bg-gray-50 border border-dashed border-gray-200 rounded text-xs text-gray-400">
                                ยังไม่มีสลิป
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
</body>
</html>