<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ดึงชื่อผู้ใช้จาก Session (รองรับทั้ง fullname, first_name หรือ fallback ไปที่ อีเมล/สมาชิก)
$display_name = 'สมาชิก';
if (isset($_SESSION['user'])) {
    if (!empty($_SESSION['user']['fullname'])) {
        $display_name = $_SESSION['user']['fullname'];
    } elseif (!empty($_SESSION['user']['first_name'])) {
        $display_name = $_SESSION['user']['first_name'];
    } elseif (!empty($_SESSION['user']['email'])) {
        $display_name = explode('@', $_SESSION['user']['email'])[0];
    }
}
?>
<!-- โหลด FontAwesome ให้แสดงไอคอน SVG แน่นอน ไม่เป็นกล่องสี่เหลี่ยม -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
    .top-bar {
        background-color: #0f172a;
        color: #cbd5e1;
        padding: 6px 8%;
        font-size: 0.82rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .top-bar a {
        color: #cbd5e1;
        text-decoration: none;
        margin-left: 12px;
        transition: color 0.2s;
    }
    .top-bar a:hover {
        color: #ffffff;
    }
    .main-header {
        background: #ffffff;
        border-bottom: 1px solid #e2e8f0;
        padding: 14px 8%;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .brand-logo {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
    }
    .brand-icon-box {
        width: 40px;
        height: 40px;
        background: #0d9488;
        color: white;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }
    .brand-title-text {
        font-size: 1.4rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1;
    }
    .brand-sub-text {
        font-size: 0.65rem;
        font-weight: 600;
        color: #0d9488;
        letter-spacing: 1.5px;
        margin-top: 2px;
    }
    .btn-cart {
        background: #0d9488;
        color: white;
        padding: 8px 16px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 500;
        font-size: 0.9rem;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-cart:hover {
        background: #0f766e;
    }
</style>

<!-- แถบด้านบนสุด Top Bar -->
<div class="top-bar">
    <div>
        <a href="seller_center.php"><i class="fa-solid fa-store"></i> ศูนย์จัดการร้านค้า</a>
    </div>
    <div>
        <?php if (isset($_SESSION['user'])): ?>
            <span>ยินดีต้อนรับ, <strong><?= htmlspecialchars($display_name) ?></strong></span>
            <span>|</span>
            <a href="auth.php?action=logout"><i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ</a>
        <?php else: ?>
            <a href="auth.php?tab=login"><i class="fa-solid fa-right-to-bracket"></i> เข้าสู่ระบบ</a>
            <span>/</span>
            <a href="auth.php?tab=register">สมัครสมาชิก</a>
        <?php endif; ?>
    </div>
</div>

<!-- แถบโลโก้และตะกร้า Main Header -->
<header class="main-header">
    <a href="index.php" class="brand-logo">
        <div class="brand-icon-box">
            <i class="fa-solid fa-bag-shopping"></i>
        </div>
        <div style="display:flex; flex-direction:column;">
            <span class="brand-title-text">Hugmart</span>
            <span class="brand-sub-text">MARKETPLACE</span>
        </div>
    </a>

    <div>
        <a href="cart.php" class="btn-cart">
            <i class="fa-solid fa-cart-shopping"></i> ตะกร้าสินค้า
        </a>
    </div>
</header>