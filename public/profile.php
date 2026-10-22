<?php
session_start();
require_once __DIR__ . '/../src/Config/Database.php';

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
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$successMsg = '';
$errorMsg = '';
$tab = $_GET['tab'] ?? 'profile';

// 1. จัดการบันทึกข้อมูลส่วนตัว (ประวัติ)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $gender = $_POST['gender'] ?? 'other';
    $birthDay = $_POST['birth_day'] ?? '';
    $birthMonth = $_POST['birth_month'] ?? '';
    $birthYear = $_POST['birth_year'] ?? '';

    $birthDate = null;
    if ($birthDay && $birthMonth && $birthYear) {
        $birthDate = sprintf('%04d-%02d-%02d', $birthYear, $birthMonth, $birthDay);
    }

    $stmtUser = $pdo->prepare("SELECT avatar_url FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
    $avatarPath = $currentUser['avatar_url'] ?? '';

    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['avatar']['tmp_name'];
        $fileName = time() . '_' . basename($_FILES['avatar']['name']);
        $targetDir = __DIR__ . '/uploads/avatars/';
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        
        $targetFile = $targetDir . $fileName;
        if (in_array($_FILES['avatar']['type'], ['image/jpeg', 'image/png', 'image/webp'])) {
            if (move_uploaded_file($fileTmp, $targetFile)) {
                $avatarPath = '/uploads/avatars/' . $fileName;
                $_SESSION['avatar_url'] = $avatarPath;
            }
        } else {
            $errorMsg = 'รองรับเฉพาะไฟล์รูปภาพ .JPEG, .PNG และ .WEBP เท่านั้น';
        }
    }

    if (empty($errorMsg)) {
        try {
            $updateStmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, gender = ?, birth_date = ?, avatar_url = ? WHERE id = ?");
            $updateStmt->execute([$fullName, $phone, $gender, $birthDate, $avatarPath, $userId]);
            $_SESSION['full_name'] = $fullName;
            $successMsg = 'บันทึกข้อมูลส่วนตัวเรียบร้อยแล้ว';
        } catch (PDOException $e) {
            $errorMsg = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    }
}

// 2. จัดการเพิ่ม / แก้ไขที่อยู่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_address'])) {
    $addressId = intval($_POST['address_id'] ?? 0);
    $recipientName = trim($_POST['recipient_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $addressDetail = trim($_POST['address_detail'] ?? '');
    $isDefault = isset($_POST['is_default']) ? 1 : 0;

    if (!empty($recipientName) && !empty($phone) && !empty($addressDetail)) {
        try {
            if ($isDefault == 1) {
                $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
            }

            if ($addressId > 0) {
                $stmt = $pdo->prepare("UPDATE user_addresses SET recipient_name = ?, phone = ?, address_detail = ?, is_default = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$recipientName, $phone, $addressDetail, $isDefault, $addressId, $userId]);
                $successMsg = 'แก้ไขที่อยู่เรียบร้อยแล้ว';
            } else {
                $checkCount = $pdo->prepare("SELECT COUNT(*) FROM user_addresses WHERE user_id = ?");
                $checkCount->execute([$userId]);
                if ($checkCount->fetchColumn() == 0) {
                    $isDefault = 1;
                }

                $stmt = $pdo->prepare("INSERT INTO user_addresses (user_id, recipient_name, phone, address_detail, is_default) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $recipientName, $phone, $addressDetail, $isDefault]);
                $successMsg = 'เพิ่มที่อยู่ใหม่เรียบร้อยแล้ว';
            }
        } catch (PDOException $e) {
            $errorMsg = 'เกิดข้อผิดพลาดในการบันทึกที่อยู่: ' . $e->getMessage();
        }
    } else {
        $errorMsg = 'กรุณากรอกข้อมูลที่อยู่ให้ครบถ้วน';
    }
    $tab = 'address';
}

// 3. จัดการตั้งค่าที่อยู่เริ่มต้น หรือ ลบที่อยู่
if (isset($_GET['action']) && isset($_GET['id'])) {
    $actionId = intval($_GET['id']);
    if ($_GET['action'] === 'setdefault') {
        $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
        $pdo->prepare("UPDATE user_addresses SET is_default = 1 WHERE id = ? AND user_id = ?")->execute([$actionId, $userId]);
        header("Location: profile.php?tab=address");
        exit();
    } elseif ($_GET['action'] === 'delete') {
        $pdo->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?")->execute([$actionId, $userId]);
        header("Location: profile.php?tab=address");
        exit();
    }
}

// ดึงข้อมูลผู้ใช้
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// ดึงรายการที่อยู่ทั้งหมดของผู้ใช้
$stmtAddr = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
$stmtAddr->execute([$userId]);
$addresses = $stmtAddr->fetchAll(PDO::FETCH_ASSOC);

$bDay = ''; $bMonth = ''; $bYear = '';
if (!empty($user['birth_date'])) {
    $time = strtotime($user['birth_date']);
    $bDay = date('j', $time);
    $bMonth = date('n', $time);
    $bYear = date('Y', $time);
}
$cartCount = $_SESSION['cart_count'] ?? 0;
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อมูลของฉัน - Hugmart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 text-gray-800 font-sans min-h-screen">

    <!-- Top Header -->
    <header class="bg-[#1B4332] text-white sticky top-0 z-50 shadow-md">
        <div class="max-w-7xl mx-auto px-4 text-xs py-1.5 flex justify-between items-center border-b border-[#2D6A4F] text-green-100">
            <div class="flex space-x-4">
                <a href="/seller/index.php" class="hover:text-white">Seller Centre</a>
                <span>|</span>
                <a href="/seller/index.php" class="hover:text-white">ลงขายสินค้า</a>
            </div>
            <div class="flex space-x-4 items-center">
                <span class="font-semibold text-white"><?php echo htmlspecialchars($user['full_name'] ?? 'สมาชิก'); ?></span>
            </div>
        </div>
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between gap-6">
            <a href="index.php" class="text-3xl font-extrabold tracking-tight text-white flex items-center gap-2">
                <i class="fa-solid fa-bag-shopping text-[#52B788]"></i> Hugmart
            </a>
            <div class="flex-1 max-w-2xl">
                <form action="search.php" method="GET" class="flex bg-white rounded-md overflow-hidden p-1 shadow">
                    <input type="text" name="q" placeholder="ค้นหาสินค้าออร์แกนิก, ร้านค้าชื่นชอบ..." class="w-full px-4 py-2 text-sm text-gray-800 focus:outline-none">
                    <button type="submit" class="bg-[#2D6A4F] hover:bg-[#1B4332] text-white px-6 py-2 rounded transition">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                </form>
            </div>
            <a href="cart.php" class="relative p-2 text-2xl hover:text-green-200 transition">
                <i class="fa-solid fa-cart-shopping"></i>
                <?php if ($cartCount > 0): ?>
                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs w-5 h-5 rounded-full flex items-center justify-center font-bold shadow"><?php echo $cartCount; ?></span>
                <?php endif; ?>
            </a>
        </div>
    </header>

    <!-- Main Container -->
    <main class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex flex-col md:flex-row gap-6">
            
            <!-- Sidebar -->
            <aside class="w-full md:w-56 shrink-0">
                <div class="flex items-center gap-3 pb-6 border-b border-gray-200">
                    <div class="w-12 h-12 bg-[#2D6A4F] text-white rounded-full flex items-center justify-center font-bold text-lg overflow-hidden border">
                        <?php if (!empty($user['avatar_url'])): ?>
                            <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <?php echo mb_substr($user['full_name'] ?? 'U', 0, 1, 'UTF-8'); ?>
                        <?php endif; ?>
                    </div>
                    <div class="overflow-hidden">
                        <div class="font-bold text-sm text-gray-800 truncate"><?php echo htmlspecialchars($user['full_name'] ?? ''); ?></div>
                        <a href="profile.php?tab=profile" class="text-xs text-gray-500 hover:text-[#2D6A4F] flex items-center gap-1 mt-0.5">
                            <i class="fa-solid fa-pen text-[10px]"></i> แก้ไขข้อมูลส่วนตัว
                        </a>
                    </div>
                </div>

                <nav class="mt-6 space-y-4 text-xs">
                    <div>
                        <div class="flex items-center gap-2.5 font-bold text-gray-800 mb-2">
                            <i class="fa-regular fa-user text-[#2D6A4F] text-sm w-4"></i>
                            <span>บัญชีของฉัน</span>
                        </div>
                        <div class="pl-6 space-y-2">
                            <a href="profile.php?tab=profile" class="block font-semibold <?php echo $tab === 'profile' ? 'text-[#2D6A4F]' : 'text-gray-600 hover:text-[#2D6A4F]'; ?>">ประวัติ</a>
                            <a href="profile.php?tab=address" class="block font-semibold <?php echo $tab === 'address' ? 'text-[#2D6A4F]' : 'text-gray-600 hover:text-[#2D6A4F]'; ?>">ที่อยู่</a>
                        </div>
                    </div>
                </nav>
            </aside>

            <!-- Content Section -->
            <section class="flex-1 bg-white rounded shadow-sm p-6">
                
                <?php if ($successMsg): ?>
                    <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 text-xs rounded flex items-center gap-2">
                        <i class="fa-solid fa-circle-check"></i>
                        <span><?php echo htmlspecialchars($successMsg); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($errorMsg): ?>
                    <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded flex items-center gap-2">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span><?php echo htmlspecialchars($errorMsg); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($tab === 'address'): ?>
                    <!-- TAB: จัดการที่อยู่ ธีมสีเขียว -->
                    <div class="flex justify-between items-center border-b border-gray-100 pb-4 mb-6">
                        <div>
                            <h1 class="text-lg font-bold text-gray-800">ที่อยู่ของฉัน</h1>
                            <p class="text-xs text-gray-500 mt-0.5">จัดการที่อยู่สำหรับจัดส่งสินค้าของคุณ</p>
                        </div>
                        <button onclick="openAddressModal()" class="bg-[#2D6A4F] hover:bg-[#1B4332] text-white px-4 py-2 rounded text-xs font-semibold flex items-center gap-2 transition shadow-sm">
                            <i class="fa-solid fa-plus"></i> เพิ่มที่อยู่ใหม่
                        </button>
                    </div>

                    <?php if (empty($addresses)): ?>
                        <div class="text-center py-12 text-gray-400 text-xs">
                            <i class="fa-solid fa-location-dot text-4xl mb-3 text-gray-300"></i>
                            <p>ยังไม่มีที่อยู่จัดส่งในระบบ กรุณาเพิ่มที่อยู่ใหม่</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4 text-xs divide-y divide-gray-100">
                            <?php foreach ($addresses as $addr): ?>
                                <div class="pt-4 first:pt-0 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-3">
                                            <span class="font-bold text-gray-800 text-sm"><?php echo htmlspecialchars($addr['recipient_name']); ?></span>
                                            <span class="text-gray-500 border-l pl-3 border-gray-300">(+66) <?php echo htmlspecialchars($addr['phone']); ?></span>
                                        </div>
                                        <p class="text-gray-600 max-w-xl leading-relaxed"><?php echo nl2br(htmlspecialchars($addr['address_detail'])); ?></p>
                                        <?php if ($addr['is_default'] == 1): ?>
                                            <div>
                                                <span class="inline-block border border-[#2D6A4F] text-[#2D6A4F] bg-green-50 px-2 py-0.5 text-[10px] font-semibold mt-1 rounded">ค่าเริ่มต้น</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex flex-col items-end gap-2 shrink-0">
                                        <div class="flex gap-3 text-[#2D6A4F]">
                                            <button onclick="editAddress(<?php echo htmlspecialchars(json_encode($addr)); ?>)" class="hover:underline">แก้ไข</button>
                                            <?php if ($addr['is_default'] == 0): ?>
                                                <a href="profile.php?tab=address&action=delete&id=<?php echo $addr['id']; ?>" onclick="return confirm('คุณต้องการลบที่อยู่นี้ใช่หรือไม่?')" class="text-gray-400 hover:text-red-600">ลบ</a>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($addr['is_default'] == 0): ?>
                                            <a href="profile.php?tab=address&action=setdefault&id=<?php echo $addr['id']; ?>" class="border border-gray-300 px-3 py-1 rounded text-gray-600 hover:border-[#2D6A4F] hover:text-[#2D6A4F] transition">ตั้งเป็นค่าเริ่มต้น</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Modal เพิ่ม/แก้ไขที่อยู่ -->
                    <div id="addressModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
                        <div class="bg-white rounded-lg max-w-lg w-full p-6 shadow-xl text-xs space-y-4">
                            <h3 id="modalTitle" class="text-base font-bold text-gray-800 border-b pb-3">เพิ่มที่อยู่ใหม่</h3>
                            
                            <form action="profile.php?tab=address" method="POST" class="space-y-4">
                                <input type="hidden" name="save_address" value="1">
                                <input type="hidden" name="address_id" id="editAddressId" value="">

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block font-semibold text-gray-700 mb-1">ชื่อ-นามสกุล ผู้รับ</label>
                                        <input type="text" name="recipient_name" id="editRecipient" required class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-[#2D6A4F]">
                                    </div>
                                    <div>
                                        <label class="block font-semibold text-gray-700 mb-1">เบอร์โทรศัพท์</label>
                                        <input type="text" name="phone" id="editPhone" required class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-[#2D6A4F]">
                                    </div>
                                </div>

                                <div>
                                    <label class="block font-semibold text-gray-700 mb-1">รายละเอียดที่อยู่ (บ้านเลขที่, ถนน, ตำบล, อำเภอ, จังหวัด, รหัสไปรษณีย์)</label>
                                    <textarea name="address_detail" id="editDetail" rows="4" required class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-[#2D6A4F]" placeholder="เช่น 3,5,7 ซอย 1 ถนนลงหาดบางแสน, แสนสุข, เมืองชลบุรี, ชลบุรี 20130"></textarea>
                                </div>

                                <div class="flex items-center gap-2">
                                    <input type="checkbox" name="is_default" id="editDefault" value="1" class="accent-[#2D6A4F]">
                                    <label for="editDefault" class="text-gray-700 select-none cursor-pointer">ตั้งเป็นที่อยู่เริ่มต้น</label>
                                </div>

                                <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                                    <button type="button" onclick="closeAddressModal()" class="px-5 py-2 border border-gray-300 rounded text-gray-600 hover:bg-gray-50">ยกเลิก</button>
                                    <button type="submit" class="px-6 py-2 bg-[#1B4332] hover:bg-[#2D6A4F] text-white rounded font-semibold shadow">บันทึก</button>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- TAB: ประวัติ (ข้อมูลส่วนตัว) -->
                    <div class="border-b border-gray-100 pb-4 mb-6">
                        <h1 class="text-lg font-bold text-gray-800">ข้อมูลของฉัน</h1>
                        <p class="text-xs text-gray-500 mt-0.5">จัดการข้อมูลส่วนตัวเพื่อความปลอดภัยของบัญชีผู้ใช้นี้</p>
                    </div>

                    <form action="profile.php?tab=profile" method="POST" enctype="multipart/form-data" class="flex flex-col-reverse lg:flex-row gap-8">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="flex-1 space-y-5 text-xs">
                            <div class="flex items-center">
                                <label class="w-28 text-gray-500 text-right pr-4">ชื่อผู้ใช้</label>
                                <div class="flex-1">
                                    <input type="text" value="<?php echo htmlspecialchars($user['username'] ?? $user['full_name'] ?? ''); ?>" readonly class="w-full max-w-md px-3 py-2 bg-gray-50 border border-gray-200 rounded text-gray-700 cursor-not-allowed">
                                </div>
                            </div>

                            <div class="flex items-center">
                                <label class="w-28 text-gray-500 text-right pr-4">ชื่อ</label>
                                <div class="flex-1">
                                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" class="w-full max-w-md px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-[#2D6A4F]">
                                </div>
                            </div>

                            <div class="flex items-center">
                                <label class="w-28 text-gray-500 text-right pr-4">อีเมล</label>
                                <div class="flex-1 text-gray-700">
                                    <?php echo htmlspecialchars($user['email'] ?? ''); ?>
                                </div>
                            </div>

                            <div class="flex items-center">
                                <label class="w-28 text-gray-500 text-right pr-4">หมายเลขโทรศัพท์</label>
                                <div class="flex-1">
                                    <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" class="w-full max-w-md px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-[#2D6A4F]">
                                </div>
                            </div>

                            <div class="flex items-center">
                                <label class="w-28 text-gray-500 text-right pr-4">เพศ</label>
                                <div class="flex-1 flex items-center space-x-6 text-gray-700">
                                    <label class="flex items-center gap-2 cursor-pointer"><input type="radio" name="gender" value="male" <?php echo ($user['gender'] ?? '') === 'male' ? 'checked' : ''; ?> class="accent-[#2D6A4F]"> ชาย</label>
                                    <label class="flex items-center gap-2 cursor-pointer"><input type="radio" name="gender" value="female" <?php echo ($user['gender'] ?? '') === 'female' ? 'checked' : ''; ?> class="accent-[#2D6A4F]"> หญิง</label>
                                    <label class="flex items-center gap-2 cursor-pointer"><input type="radio" name="gender" value="other" <?php echo ($user['gender'] ?? 'other') === 'other' ? 'checked' : ''; ?> class="accent-[#2D6A4F]"> อื่น ๆ</label>
                                </div>
                            </div>

                            <div class="flex items-center">
                                <label class="w-28 text-gray-500 text-right pr-4">วัน/เดือน/ปี เกิด</label>
                                <div class="flex-1 flex items-center gap-2 max-w-md">
                                    <select name="birth_day" class="w-1/3 px-2 py-1.5 border border-gray-300 rounded bg-white focus:outline-none focus:border-[#2D6A4F]">
                                        <option value="">Date</option>
                                        <?php for ($d = 1; $d <= 31; $d++): ?>
                                            <option value="<?php echo $d; ?>" <?php echo $bDay == $d ? 'selected' : ''; ?>><?php echo $d; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <select name="birth_month" class="w-1/3 px-2 py-1.5 border border-gray-300 rounded bg-white focus:outline-none focus:border-[#2D6A4F]">
                                        <option value="">เดือน</option>
                                        <?php 
                                        $months = [1=>'มกราคม', 2=>'กุมภาพันธ์', 3=>'มีนาคม', 4=>'เมษายน', 5=>'พฤษภาคม', 6=>'มิถุนายน', 7=>'กรกฎาคม', 8=>'สิงหาคม', 9=>'กันยายน', 10=>'ตุลาคม', 11=>'พฤศจิกายน', 12=>'ธันวาคม'];
                                        foreach ($months as $mNum => $mName): ?>
                                            <option value="<?php echo $mNum; ?>" <?php echo $bMonth == $mNum ? 'selected' : ''; ?>><?php echo $mName; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select name="birth_year" class="w-1/3 px-2 py-1.5 border border-gray-300 rounded bg-white focus:outline-none focus:border-[#2D6A4F]">
                                        <option value="">ปี</option>
                                        <?php 
                                        $currentYear = (int)date('Y');
                                        for ($y = $currentYear; $y >= $currentYear - 90; $y--): ?>
                                            <option value="<?php echo $y; ?>" <?php echo $bYear == $y ? 'selected' : ''; ?>><?php echo $y + 543; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="flex items-center pt-4">
                                <div class="w-28"></div>
                                <button type="submit" class="bg-[#1B4332] hover:bg-[#2D6A4F] text-white px-8 py-2.5 rounded text-xs font-semibold shadow transition">
                                    บันทึก
                                </button>
                            </div>
                        </div>

                        <!-- Avatar Upload -->
                        <div class="w-full lg:w-60 border-l-0 lg:border-l border-gray-100 flex flex-col items-center justify-start pl-0 lg:pl-8 py-4">
                            <div class="w-28 h-28 bg-[#2D6A4F] text-white rounded-full overflow-hidden flex items-center justify-center font-bold text-3xl mb-4 border-2 border-gray-100 shadow-sm">
                                <?php if (!empty($user['avatar_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" id="avatarPreview" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <span id="avatarPlaceholder"><?php echo mb_substr($user['username'] ?? $user['full_name'] ?? 'U', 0, 1, 'UTF-8'); ?></span>
                                    <img id="avatarPreview" class="w-full h-full object-cover hidden">
                                <?php endif; ?>
                            </div>
                            <label for="avatarInput" class="px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 rounded text-xs text-gray-700 cursor-pointer shadow-sm transition">เลือกรูป</label>
                            <input type="file" id="avatarInput" name="avatar" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="previewImage(this)">
                        </div>
                    </form>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <script>
        function openAddressModal() {
            document.getElementById('modalTitle').innerText = 'เพิ่มที่อยู่ใหม่';
            document.getElementById('editAddressId').value = '';
            document.getElementById('editRecipient').value = '';
            document.getElementById('editPhone').value = '';
            document.getElementById('editDetail').value = '';
            document.getElementById('editDefault').checked = false;
            document.getElementById('addressModal').classList.remove('hidden');
        }

        function editAddress(addr) {
            document.getElementById('modalTitle').innerText = 'แก้ไขที่อยู่';
            document.getElementById('editAddressId').value = addr.id;
            document.getElementById('editRecipient').value = addr.recipient_name;
            document.getElementById('editPhone').value = addr.phone;
            document.getElementById('editDetail').value = addr.address_detail;
            document.getElementById('editDefault').checked = (addr.is_default == 1);
            document.getElementById('addressModal').classList.remove('hidden');
        }

        function closeAddressModal() {
            document.getElementById('addressModal').classList.add('hidden');
        }

        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('avatarPreview');
                    const placeholder = document.getElementById('avatarPlaceholder');
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                    if (placeholder) placeholder.classList.add('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>