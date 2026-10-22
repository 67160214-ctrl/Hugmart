<?php
session_start();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>วิธีการสั่งซื้อสินค้า - Hugmart</title>
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
        <h1 class="text-2xl font-bold text-[#1B4332] border-b pb-4"><i class="fa-solid fa-book-open text-[#52B788]"></i> วิธีการสั่งซื้อสินค้า</h1>
        
        <div class="space-y-4 text-xs leading-relaxed text-gray-600">
            <div class="p-4 bg-green-50 border-l-4 border-[#2D6A4F] rounded">
                <h3 class="font-bold text-gray-800 text-sm mb-1">ขั้นตอนที่ 1: ค้นหาและเลือกสินค้า</h3>
                <p>เลือกสินค้าที่คุณต้องการผ่านหน้าแรก หมวดหมู่สินค้า หรือใช้ช่องค้นหาด้านบน จากนั้นคลิกดูรายละเอียดสินค้า</p>
            </div>
            <div class="p-4 bg-green-50 border-l-4 border-[#2D6A4F] rounded">
                <h3 class="font-bold text-gray-800 text-sm mb-1">ขั้นตอนที่ 2: เพิ่มลงรถเข็นหรือกดสั่งซื้อ</h3>
                <p>ตรวจสอบรายละเอียด ขนาด หรือจำนวนสินค้าที่ต้องการ แล้วกดปุ่ม "เพิ่มลงรถเข็น" หรือ "ซื้อสินค้า"</p>
            </div>
            <div class="p-4 bg-green-50 border-l-4 border-[#2D6A4F] rounded">
                <h3 class="font-bold text-gray-800 text-sm mb-1">ขั้นตอนที่ 3: ตรวจสอบที่อยู่จัดส่ง</h3>
                <p>ตรวจสอบที่อยู่ในการจัดส่งสินค้าที่บันทึกไว้ในระบบบัญชีของคุณ หรือเพิ่มที่อยู่ใหม่ที่ต้องการให้จัดส่ง</p>
            </div>
            <div class="p-4 bg-green-50 border-l-4 border-[#2D6A4F] rounded">
                <h3 class="font-bold text-gray-800 text-sm mb-1">ขั้นตอนที่ 4: ชำระเงินและยืนยันคำสั่งซื้อ</h3>
                <p>เลือกช่องทางการชำระเงินที่สะดวก และยืนยันการสั่งซื้อ จากนั้นรอรับสินค้าคุณภาพส่งตรงถึงบ้านคุณได้เลย</p>
            </div>
        </div>
    </main>

    <footer class="bg-[#1B4332] text-white py-4 text-center text-xs">
        <p>© 2026 Hugmart Marketplace. All Rights Reserved.</p>
    </footer>
</body>
</html>