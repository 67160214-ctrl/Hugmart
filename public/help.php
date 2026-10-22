<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// เช็กว่ามีการล็อกอินอยู่หรือไม่
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ศูนย์ช่วยเหลือ | Hugmart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 flex flex-col min-h-screen font-sans text-gray-800">

    <!-- Navbar / Header -->
    <header class="bg-white shadow-sm border-b sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="index.php" class="text-3xl font-extrabold text-[#1B4332] tracking-tight">
                    Hugmart
                </a>
                <span class="text-xl font-medium text-gray-700 border-l-2 border-gray-300 pl-4">Hugmart Help Center</span>
            </div>
            <div class="flex items-center space-x-6 text-sm">
                <?php if ($isLoggedIn): ?>
                    <!-- ถ้าล็อกอินแล้ว แสดงปุ่มกลับหน้าแรกแบบมีลูกศรชี้ซ้าย -->
                    <a href="index.php" class="text-[#2D6A4F] font-bold hover:underline flex items-center gap-1.5">
                        <i class="fa-solid fa-arrow-left"></i> กลับหน้าแรก
                    </a>
                <?php else: ?>
                    <!-- ถ้ายังไม่ล็อกอิน แสดงปุ่มเข้าสู่ระบบปกติ -->
                    <a href="login.php" class="text-gray-600 hover:text-[#2D6A4F] transition">เข้าสู่ระบบ</a>
                    <a href="register.php" class="text-[#2D6A4F] font-semibold hover:underline">สมัครสมาชิก</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Hero Banner & Search Bar -->
    <section class="bg-gradient-to-r from-[#1B4332] via-[#2D6A4F] to-[#1B4332] py-14 px-4 text-center text-white">
        <div class="max-w-3xl mx-auto">
            <h1 class="text-3xl sm:text-4xl font-bold mb-6">สวัสดี มีอะไรให้ช่วยหรือไม่</h1>
            
            <!-- Search Box -->
            <form action="#" method="GET" class="flex items-center bg-white rounded-lg p-1.5 shadow-lg max-w-2xl mx-auto">
                <input type="text" 
                       name="q" 
                       placeholder="ค้นหาคำถาม, วิธีการชำระเงิน, การคืนสินค้า..." 
                       class="w-full px-4 py-2.5 text-gray-800 focus:outline-none text-sm">
                <button type="submit" 
                        class="bg-[#2D6A4F] hover:bg-[#1B4332] text-white px-6 py-2.5 rounded-md transition flex items-center justify-center">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
            </form>
        </div>
    </section>

    <!-- Main Content: FAQ แบบแสดงคำตอบชัดเจน -->
    <main class="max-w-4xl mx-auto px-4 py-12 flex-grow w-full">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-2">
            <i class="fa-solid fa-circle-question text-[#2D6A4F]"></i> คำถามที่พบบ่อยและวิธีแก้ไข
        </h2>

        <div class="space-y-4">
            
            <!-- ข้อ 1 -->
            <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm space-y-2">
                <h3 class="font-bold text-base text-[#1B4332] flex items-center gap-2">
                    <span class="bg-green-100 text-[#2D6A4F] px-2.5 py-0.5 rounded text-xs">การชำระเงิน</span> 
                    ชำระเงินผ่าน PromptPay หรือสแกน QR Code อย่างไร?
                </h3>
                <p class="text-sm text-gray-600 leading-relaxed pl-4 border-l-2 border-[#52B788]">
                    <strong>วิธีแก้ไข/ขั้นตอน:</strong> เมื่อคุณเลือกสินค้าและกดสั่งซื้อ ให้เลือกช่องทางการชำระเงินเป็น "PromptPay / QR Code" ระบบจะแสดงภาพ QR Code ขึ้นมา ให้ท่านเปิดแอปพลิเคชันธนาคารบนมือถือเพื่อสแกน QR Code ดังกล่าว และอัปโหลดสลีปหลักฐานการโอนเงินในหน้าชำระเงินเพื่อให้ระบบตรวจสอบและยืนยันคำสั่งซื้อ
                </p>
            </div>

            <!-- ข้อ 2 -->
            <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm space-y-2">
                <h3 class="font-bold text-base text-[#1B4332] flex items-center gap-2">
                    <span class="bg-green-100 text-[#2D6A4F] px-2.5 py-0.5 rounded text-xs">การจัดส่ง</span> 
                    สามารถนัดรับสินค้าภายในบริเวณมหาวิทยาลัยได้หรือไม่?
                </h3>
                <p class="text-sm text-gray-600 leading-relaxed pl-4 border-l-2 border-[#52B788]">
                    <strong>วิธีแก้ไข/ขั้นตอน:</strong> ขึ้นอยู่กับข้อตกลงกับผู้ขายแต่ละร้าน ท่านสามารถติดต่อสอบถามผู้ขายโดยตรงผ่านระบบแชทในร้านค้านั้นๆ ก่อนทำการสั่งซื้อ เพื่อตกลงจุดนัดรับสินค้าภายในมหาวิทยาลัยร่วมกัน
                </p>
            </div>

            <!-- ข้อ 3 -->
            <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm space-y-2">
                <h3 class="font-bold text-base text-[#1B4332] flex items-center gap-2">
                    <span class="bg-green-100 text-[#2D6A4F] px-2.5 py-0.5 rounded text-xs">บัญชีผู้ใช้</span> 
                    วิธีเข้าสู่ระบบด้วยรหัสนักศึกษา (Student ID)
                </h3>
                <p class="text-sm text-gray-600 leading-relaxed pl-4 border-l-2 border-[#52B788]">
                    <strong>วิธีแก้ไข/ขั้นตอน:</strong> ไปที่หน้า <a href="login.php" class="text-[#2D6A4F] font-semibold underline">เข้าสู่ระบบ</a> จากนั้นกรอกรหัสนักศึกษาหรืออีเมลมหาวิทยาลัยของคุณในช่องชื่อผู้ใช้ และใส่รหัสผ่านที่ท่านได้ลงทะเบียนไว้ หากยังไม่เคยสมัครใช้งาน ให้ไปที่หน้า <a href="register.php" class="text-[#2D6A4F] font-semibold underline">สมัครสมาชิก</a> เพื่อสร้างบัญชีใหม่
                </p>
            </div>

            <!-- ข้อ 4 -->
            <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm space-y-2">
                <h3 class="font-bold text-base text-[#1B4332] flex items-center gap-2">
                    <span class="bg-green-100 text-[#2D6A4F] px-2.5 py-0.5 rounded text-xs">การใช้งาน</span> 
                    ขั้นตอนการเปิดร้านค้าและลงขายสินค้าบน Hugmart
                </h3>
                <p class="text-sm text-gray-600 leading-relaxed pl-4 border-l-2 border-[#52B788]">
                    <strong>วิธีแก้ไข/ขั้นตอน:</strong> 
                    1. เข้าสู่ระบบด้วยบัญชีของคุณ<br>
                    2. คลิกที่เมนู <strong>"Seller Centre"</strong> ที่แถบด้านบนเว็บไซต์<br>
                    3. ระบบจะบังคับให้ท่านกรอกชื่อร้านค้าในครั้งแรก (เช่น TCG SHOP) เมื่อบันทึกแล้วจะสามารถกดปุ่ม "เพิ่มสินค้าใหม่" เพื่อลงขายสินค้าออร์แกนิกหรือสินค้าของคุณได้ทันที
                </p>
            </div>

            <!-- ข้อ 5 -->
            <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm space-y-2">
                <h3 class="font-bold text-base text-[#1B4332] flex items-center gap-2">
                    <span class="bg-green-100 text-[#2D6A4F] px-2.5 py-0.5 rounded text-xs">ความปลอดภัย</span> 
                    ทำอย่างไรหากลืมรหัสผ่านบัญชีใช้งาน?
                </h3>
                <p class="text-sm text-gray-600 leading-relaxed pl-4 border-l-2 border-[#52B788]">
                    <strong>วิธีแก้ไข/ขั้นตอน:</strong> ในหน้าเข้าสู่ระบบ สามารถกดคลิกที่ลิงก์ "Logn / Forgot Password" (หากมี) หรือติดต่อผู้ดูแลระบบผ่านช่องทางอีเมลสนับสนุน เพื่อทำการรีเซ็ตรหัสผ่านใหม่ให้กับบัญชีของคุณได้อย่างปลอดภัย
                </p>
            </div>

        </div>
    </main>

    <!-- Footer ส่วนท้าย -->
    <footer class="bg-gray-100 py-6 border-t text-center text-xs text-gray-500">
        <p>© 2026 Hugmart Marketplace. All Rights Reserved.</p>
    </footer>

</body>
</html>