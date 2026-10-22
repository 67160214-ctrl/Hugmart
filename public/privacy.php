<?php
session_start();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เงื่อนไขและนโยบายความเป็นส่วนตัว - Hugmart</title>
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
        <h1 class="text-2xl font-bold text-[#1B4332] border-b pb-4"><i class="fa-solid fa-shield-halved text-[#52B788]"></i> เงื่อนไขและนโยบายความเป็นส่วนตัว</h1>
        
        <div class="space-y-4 text-xs leading-relaxed text-gray-600">
            <h3 class="font-bold text-gray-800 text-sm">1. การเก็บรวบรวมข้อมูลส่วนบุคคล</h3>
            <p>บริษัทฯ จะเก็บรวบรวมข้อมูลส่วนบุคคลของท่าน เช่น ชื่อ, อีเมล, เบอร์โทรศัพท์ และที่อยู่จัดส่ง เมื่อท่านสมัครสมาชิกหรือทำรายการสั่งซื้อสินค้าบนแพลตฟอร์ม Hugmart</p>
            
            <h3 class="font-bold text-gray-800 text-sm">2. การใช้งานข้อมูล</h3>
            <p>ข้อมูลของท่านจะถูกนำไปใช้เพื่อการจัดส่งสินค้า, การปรับปรุงคุณภาพบริการ, และการติดต่อประสานงานเกี่ยวกับคำสั่งซื้อเท่านั้น โดยไม่มีการเปิดเผยข้อมูลแก่บุคคลภายนอก</p>

            <h3 class="font-bold text-gray-800 text-sm">3. ความปลอดภัยของข้อมูล</h3>
            <p>เรามีมาตรการรักษาความปลอดภัยที่เข้มงวดในการปกป้องข้อมูลส่วนตัวของท่าน เพื่อป้องกันการเข้าถึงหรือเปิดเผยข้อมูลโดยไม่ได้รับอนุญาต</p>
        </div>
    </main>

    <footer class="bg-[#1B4332] text-white py-4 text-center text-xs">
        <p>© 2026 Hugmart Marketplace. All Rights Reserved.</p>
    </footer>
</body>
</html>