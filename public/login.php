<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<!DOCTYPE html>
<html lang="th">
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>เข้าสู่ระบบ | Hugmart ตลาดออนไลน์</title>

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
                    เข้าสู่ระบบ
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
                    <i class="fa-solid fa-shopping-bag text-6xl text-[#52B788]"></i>
                </div>

                <h2 class="text-4xl font-extrabold mb-3">
                    Hugmart 11.11
                </h2>

                <p class="text-xl font-light text-green-100 mb-6">
                    ตลาดออนไลน์คุณภาพ ช้อปคุ้ม ส่งไว ได้ของชัวร์
                </p>

                <div class="inline-flex items-center space-x-2 bg-white bg-opacity-10 backdrop-blur-md px-6 py-3 rounded-full text-green-200 border border-green-300 border-opacity-30">
                    <i class="fa-solid fa-truck-fast text-[#52B788]"></i>
                    <span>ส่งฟรีขั้นต่ำ 0 บาท | แจกส่วนลดเพียบ</span>
                </div>

            </div>

            <!-- ฝั่งขวา: Login Form -->
            <div class="md:col-span-5 w-full max-w-md mx-auto">

                <div class="bg-white p-8 rounded-xl shadow-2xl">

                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-bold text-gray-800">
                            เข้าสู่ระบบ
                        </h3>
                    </div>

                    <!-- แสดงข้อความ Error -->
                    <?php if (isset($_GET['error'])): ?>
                        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-3 rounded text-sm mb-4">
                            <?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <!-- ฟอร์มเข้าสู่ระบบ -->
                    <form action="api/auth/login.php" method="POST" class="space-y-4">

                        <!-- Email / Phone / Username -->
                        <div>
                            <input
                                type="text"
                                name="username"
                                required
                                autocomplete="username"
                                placeholder="อีเมล / หมายเลขโทรศัพท์ / ชื่อผู้ใช้"
                                class="w-full px-4 py-3 border border-gray-300 rounded focus:ring-2 focus:ring-[#52B788] focus:border-[#2D6A4F] focus:outline-none text-sm transition"
                            >
                        </div>

                        <!-- Password -->
                        <div>
                            <input
                                type="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                placeholder="รหัสผ่าน"
                                class="w-full px-4 py-3 border border-gray-300 rounded focus:ring-2 focus:ring-[#52B788] focus:border-[#2D6A4F] focus:outline-none text-sm transition"
                            >
                        </div>

                        <!-- Remember Me / Forgot Password -->
                        <div class="flex items-center justify-between text-xs text-gray-600">

                            <label class="flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    name="remember"
                                    value="1"
                                    class="rounded border-gray-300 text-[#2D6A4F] focus:ring-[#52B788]"
                                >
                                <span class="ml-2">จดจำการเข้าสู่ระบบ</span>
                            </label>

                           
                            </a>

                        </div>

                        <!-- Login Button -->
                        <button
                            type="submit"
                            class="w-full bg-[#2D6A4F] hover:bg-[#1B4332] text-white font-bold py-3 rounded shadow transition duration-200 uppercase tracking-wider text-sm"
                        >
                            เข้าสู่ระบบ
                        </button>

                    </form>

                    <!-- Register Link -->
                    <div class="mt-6 pt-4 border-t border-gray-200 text-center text-sm text-gray-500">

                        เพิ่งเคยเข้ามาใน Hugmart ใช่หรือไม่?

                        <a href="register.php"
                           class="text-[#2D6A4F] font-bold hover:underline ml-1">
                            สมัครใหม่
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

</body>
</html>