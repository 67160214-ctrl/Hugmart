<?php
session_start();
require_once __DIR__ . '/../src/Config/Database.php';

$successMsg = '';
$errorMsg = '';

// จัดการเมื่อกดส่งใบสมัคร (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_job'])) {
    $position = trim($_POST['position'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $resumePath = '';

    // จัดการอัปโหลดไฟล์เรซูเม่ (.pdf, .doc, .docx)
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['resume']['tmp_name'];
        $fileName = time() . '_' . basename($_FILES['resume']['name']);
        $targetDir = __DIR__ . '/uploads/resumes/';
        
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $targetFile = $targetDir . $fileName;
        $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        
        if (in_array($_FILES['resume']['type'], $allowedTypes)) {
            if (move_uploaded_file($fileTmp, $targetFile)) {
                $resumePath = '/uploads/resumes/' . $fileName;
            } else {
                $errorMsg = 'ไม่สามารถอัปโหลดไฟล์เรซูเม่ได้';
            }
        } else {
            $errorMsg = 'รองรับเฉพาะไฟล์เอกสาร .PDF, .DOC และ .DOCX เท่านั้น';
        }
    } else {
        $errorMsg = 'กรุณาแนบไฟล์เรซูเม่ (Resume) ของคุณ';
    }

    if (empty($errorMsg) && !empty($resumePath)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO job_applications (position, full_name, email, phone, resume_path) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$position, $fullName, $email, $phone, $resumePath]);
            $successMsg = 'ส่งใบสมัครงานพร้อมเรซูเม่เรียบร้อยแล้ว ขอบคุณที่สนใจร่วมงานกับเรา';
        } catch (PDOException $e) {
            $errorMsg = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ร่วมงานกับเรา - Hugmart</title>
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

    <main class="max-w-4xl mx-auto px-4 py-10 flex-1 w-full bg-white rounded-lg shadow my-6 space-y-6">
        <h1 class="text-2xl font-bold text-[#1B4332] border-b pb-4"><i class="fa-solid fa-users text-[#52B788]"></i> ร่วมงานกับเรา (Careers)</h1>
        
        <?php if ($successMsg): ?>
            <div class="p-3 bg-green-50 text-green-700 text-xs rounded border border-green-200 flex items-center gap-2">
                <i class="fa-solid fa-circle-check"></i> <span><?php echo htmlspecialchars($successMsg); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
            <div class="p-3 bg-red-50 text-red-700 text-xs rounded border border-red-200 flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation"></i> <span><?php echo htmlspecialchars($errorMsg); ?></span>
            </div>
        <?php endif; ?>

        <p class="text-xs text-gray-600">มาร่วมเป็นส่วนหนึ่งของครอบครัว Hugmart มาร์เก็ตเพลสออนไลน์ยุคใหม่เพื่อสุขภาพและความยั่งยืน เลือกตำแหน่งงานและส่งเรซูเม่ของคุณมาได้เลย!</p>

        <div class="space-y-4 text-xs">
            <div class="border p-4 rounded-lg flex justify-between items-center hover:shadow transition">
                <div>
                    <h3 class="font-bold text-sm text-gray-800">Backend PHP Developer</h3>
                    <p class="text-gray-500 mt-1">พัฒนาและดูแลระบบ API, ฐานข้อมูล และโครงสร้างเว็บไซต์หลัก</p>
                </div>
                <button onclick="openApplyModal('Backend PHP Developer')" class="bg-[#2D6A4F] hover:bg-[#1B4332] text-white px-4 py-2 rounded text-xs font-semibold cursor-pointer transition">สมัครงาน</button>
            </div>
            <div class="border p-4 rounded-lg flex justify-between items-center hover:shadow transition">
                <div>
                    <h3 class="font-bold text-sm text-gray-800">Frontend UI/UX Designer</h3>
                    <p class="text-gray-500 mt-1">ออกแบบหน้าตาเว็บไซต์และประสบการณ์ใช้งานให้ใช้งานง่ายและสวยงาม</p>
                </div>
                <button onclick="openApplyModal('Frontend UI/UX Designer')" class="bg-[#2D6A4F] hover:bg-[#1B4332] text-white px-4 py-2 rounded text-xs font-semibold cursor-pointer transition">สมัครงาน</button>
            </div>
        </div>

        <!-- Modal ฟอร์มสมัครงานพร้อมช่องอัปโหลดเรซูเม่ -->
        <div id="applyModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-lg max-w-lg w-full p-6 shadow-xl text-xs space-y-4">
                <h3 class="text-base font-bold text-gray-800 border-b pb-3">สมัครงานตำแหน่ง: <span id="modalPositionText" class="text-[#2D6A4F]"></span></h3>
                
                <form action="careers.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="apply_job" value="1">
                    <input type="hidden" name="position" id="inputPosition" value="">

                    <div>
                        <label class="block font-semibold text-gray-700 mb-1">ชื่อ-นามสกุล</label>
                        <input type="text" name="full_name" required class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-[#2D6A4F]">
                    </div>

                    <div>
                        <label class="block font-semibold text-gray-700 mb-1">อีเมลติดต่อ</label>
                        <input type="email5" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-[#2D6A4F]">
                    </div>

                    <div>
                        <label class="block font-semibold text-gray-700 mb-1">เบอร์โทรศัพท์</label>
                        <input type="text" name="phone" required class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-[#2D6A4F]">
                    </div>

                    <div>
                        <label class="block font-semibold text-gray-700 mb-1">แนบไฟล์เรซูเม่ (Resume / CV)</label>
                        <input type="file" name="resume" accept=".pdf,.doc,.docx" required class="w-full px-3 py-1.5 border border-gray-300 rounded bg-white text-gray-700 file:mr-4 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-[#2D6A4F] file:text-white hover:file:bg-[#1B4332]">
                        <p class="text-[10px] text-gray-400 mt-1">รองรับไฟล์ .PDF, .DOC, .DOCX ขนาดไม่เกิน 5MB</p>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button type="button" onclick="closeApplyModal()" class="px-4 py-2 border border-gray-300 rounded text-gray-600 hover:bg-gray-50">ยกเลิก</button>
                        <button type="submit" class="px-6 py-2 bg-[#1B4332] hover:bg-[#2D6A4F] text-white rounded font-semibold shadow">ส่งใบสมัคร</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <footer class="bg-[#1B4332] text-white py-4 text-center text-xs">
        <p>© 2026 Hugmart Marketplace. All Rights Reserved.</p>
    </footer>

    <script>
        function openApplyModal(positionName) {
            document.getElementById('modalPositionText').innerText = positionName;
            document.getElementById('inputPosition').value = positionName;
            document.getElementById('applyModal').classList.remove('hidden');
        }

        function closeApplyModal() {
            document.getElementById('applyModal').classList.add('hidden');
        }
    </script>
</body>
</html>