<?php
// โหลด Config เพื่อเริ่ม Session Cookie แบบครอบคลุม และสร้าง CSRF Token
require_once __DIR__ . '/../src/Config/Database.php';

// หากล็อกอินอยู่แล้ว ให้ไปที่หน้าแรก
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>สมัครสมาชิก | Hugmart ตลาดออนไลน์</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<body class="bg-gray-50 flex flex-col min-h-screen font-sans">

    <!-- Navbar / Header -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">

            <div class="flex items-center space-x-4">
                <a href="index.php"
                   class="text-3xl font-extrabold text-[#1B4332] tracking-tight">
                    Hugmart
                </a>

                <span class="text-xl font-medium text-gray-700 border-l-2 border-gray-300 pl-4">
                    สมัครสมาชิก
                </span>
            </div>

            <a href="help.php"
               class="text-sm text-[#2D6A4F] hover:underline">
                ต้องการความช่วยเหลือ?
            </a>

        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow bg-gradient-to-r from-[#1B4332] via-[#2D6A4F] to-[#1B4332] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">

        <div class="max-w-6xl w-full grid grid-cols-1 md:grid-cols-12 gap-8 items-center">

            <!-- ฝั่งซ้าย: Banner โปรโมชัน -->
            <div class="hidden md:flex md:col-span-7 flex-col items-center justify-center text-white text-center p-8">

                <div class="w-32 h-32 bg-[#52B788] bg-opacity-20 rounded-full flex items-center justify-center mb-6 border-4 border-[#52B788]">
                    <i class="fa-solid fa-store text-6xl text-[#52B788]"></i>
                </div>

                <h2 class="text-4xl font-extrabold mb-3">
                    เข้าร่วมกับ Hugmart
                </h2>

                <p class="text-xl font-light text-green-100 mb-6">
                    เปิดบัญชีผู้ใช้ฟรี ช้อปปิ้งและลงขายสินค้าได้ทันที
                </p>

                <div class="inline-flex items-center space-x-2 bg-white bg-opacity-10 backdrop-blur-md px-6 py-3 rounded-full text-green-200 border border-green-300 border-opacity-30">
                    <i class="fa-solid fa-shield-halved text-[#52B788]"></i>
                    <span>ปลอดภัย มั่นใจ ช้อปง่ายทุกรายการ</span>
                </div>

            </div>

            <!-- ฝั่งขวา: Register Form -->
            <div class="md:col-span-5 w-full max-w-md mx-auto">

                <div class="bg-white p-8 rounded-xl shadow-2xl">

                    <h3 class="text-xl font-bold text-gray-800 mb-6">
                        สมัครสมาชิก
                    </h3>

                    <!-- กล่องแสดง Error/Success แจ้งเตือน -->
                    <div id="alertBox" class="hidden p-3 rounded text-sm mb-4 border-l-4"></div>

                    <!-- ฟอร์มสมัครสมาชิก -->
                    <form id="registerForm" class="space-y-3.5">

                        <!-- CSRF Token Hidden Field -->
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <!-- หมายเลขโทรศัพท์ / ชื่อผู้ใช้ -->
                        <div>
                            <input
                                type="text"
                                name="username"
                                required
                                autocomplete="username"
                                placeholder="หมายเลขโทรศัพท์ / ชื่อผู้ใช้"
                                class="w-full px-4 py-3 border border-gray-300 rounded focus:ring-2 focus:ring-[#52B788] focus:border-[#2D6A4F] focus:outline-none text-sm transition"
                            >
                        </div>

                        <!-- ชื่อ-นามสกุล -->
                        <div>
                            <input
                                type="text"
                                name="fullname"
                                required
                                autocomplete="name"
                                placeholder="ชื่อ - นามสกุล"
                                class="w-full px-4 py-3 border border-gray-300 rounded focus:ring-2 focus:ring-[#52B788] focus:border-[#2D6A4F] focus:outline-none text-sm transition"
                            >
                        </div>

                        <!-- อีเมล -->
                        <div>
                            <input
                                type="email"
                                name="email"
                                required
                                autocomplete="email"
                                placeholder="อีเมล (Email Address)"
                                class="w-full px-4 py-3 border border-gray-300 rounded focus:ring-2 focus:ring-[#52B788] focus:border-[#2D6A4F] focus:outline-none text-sm transition"
                            >
                        </div>

                        <!-- รหัสผ่าน -->
                        <div>
                            <input
                                type="password"
                                name="password"
                                required
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="ตั้งรหัสผ่าน (อย่างน้อย 8 ตัวอักษร)"
                                class="w-full px-4 py-3 border border-gray-300 rounded focus:ring-2 focus:ring-[#52B788] focus:border-[#2D6A4F] focus:outline-none text-sm transition"
                            >
                        </div>

                        <!-- ยืนยันรหัสผ่าน -->
                        <div>
                            <input
                                type="password"
                                name="confirm_password"
                                required
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="ยืนยันรหัสผ่าน"
                                class="w-full px-4 py-3 border border-gray-300 rounded focus:ring-2 focus:ring-[#52B788] focus:border-[#2D6A4F] focus:outline-none text-sm transition"
                            >
                        </div>

                        <!-- ปุ่มสมัครสมาชิก -->
                        <button
                            type="submit"
                            class="w-full bg-[#2D6A4F] hover:bg-[#1B4332] text-white font-bold py-3 rounded shadow transition duration-200 uppercase tracking-wider text-sm mt-2"
                        >
                            สมัครสมาชิก
                        </button>

                    </form>

                    <!-- ลิงก์เข้าสู่ระบบ -->
                    <div class="mt-6 pt-4 border-t border-gray-200 text-center text-sm text-gray-500">

                        มีบัญชีผู้ใช้อยู่แล้ว?

                        <a href="login.php"
                           class="text-[#2D6A4F] font-bold hover:underline ml-1">
                            เข้าสู่ระบบ
                        </a>

                    </div>

                </div>

            </div>

        </div>

    </main>

    <!-- Footer -->
    <footer class="bg-gray-100 py-6 border-t text-center text-xs text-gray-500">
        <p>© 2026 Hugmart Marketplace. All Rights Reserved.</p>
    </footer>

    <script>
    document.getElementById('registerForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const alertBox = document.getElementById('alertBox');
        alertBox.classList.add('hidden');
        alertBox.className = 'p-3 rounded text-sm mb-4 border-l-4 hidden';

        const formData = new FormData(this);

        try {
            const response = await fetch('api/auth/register.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin' // ส่ง Session Cookie เพื่อยืนยัน CSRF
            });

            const result = await response.json();

            if (response.ok && result.success) {
                alertBox.classList.remove('hidden');
                alertBox.classList.add('bg-green-50', 'border-green-500', 'text-green-700');
                alertBox.innerText = result.message;

                setTimeout(() => {
                    window.location.href = 'login.php';
                }, 1500);
            } else {
                alertBox.classList.remove('hidden');
                alertBox.classList.add('bg-red-50', 'border-red-500', 'text-red-700');
                alertBox.innerText = result.error || 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง';
            }
        } catch (err) {
            alertBox.classList.remove('hidden');
            alertBox.classList.add('bg-red-50', 'border-red-500', 'text-red-700');
            alertBox.innerText = 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้';
        }
    });
    </script>

</body>
</html>