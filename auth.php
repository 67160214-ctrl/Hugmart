<?php
session_start();
require_once 'config/db.php';

// หากเข้าสู่ระบบแล้ว ให้เปลี่ยนหน้า (เว้นแต่จะเข้ามาเปลี่ยนรหัสผ่านหรือ Logout)
$action_get = $_GET['action'] ?? '';
if (isset($_SESSION['user']) && $action_get !== 'logout' && $action_get !== 'change_password') {
    header("Location: index.php");
    exit();
}

// ระบบ Logout
if ($action_get === 'logout') {
    session_unset();
    session_destroy();
    header("Location: auth.php");
    exit();
}

$error = '';
$success = '';
$active_tab = $_GET['tab'] ?? 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- LOGIN ---
    if ($action === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'fullname' => $user['fullname'],
                'email' => $user['email'],
                'role' => $user['role']
            ];
            header("Location: index.php");
            exit();
        } else {
            $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง หรือบัญชีถูกระงับ';
        }

    // --- REGISTER ---
    } elseif ($action === 'register') {
        $active_tab = 'register';
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        
        $address_line1 = trim($_POST['address_line1'] ?? '');
        $subdistrict = trim($_POST['subdistrict'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');

        if (empty($fullname) || empty($email) || empty($password) || empty($address_line1)) {
            $error = 'กรุณากรอกข้อมูลสำคัญให้ครบถ้วน';
        } else {
            // ตรวจสอบอีเมลซ้ำ
            $check_email = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $check_email->execute([$email]);
            
            if ($check_email->fetch()) {
                $error = 'อีเมลนี้ถูกใช้งานในระบบแล้ว';
            } else {
                try {
                    $pdo->beginTransaction();

                    // 1. บันทึกข้อมูลผู้ใช้
                    $pass_hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password_hash, phone) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$fullname, $email, $pass_hash, $phone]);
                    $user_id = $pdo->lastInsertId();

                    // 2. บันทึกที่อยู่จัดส่ง
                    $stmt_addr = $pdo->prepare("INSERT INTO user_addresses (user_id, recipient_name, phone, address_line1, subdistrict, district, province, postal_code, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE)");
                    $stmt_addr->execute([$user_id, $fullname, $phone, $address_line1, $subdistrict, $district, $province, $postal_code]);

                    $pdo->commit();
                    $success = 'สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ';
                    $active_tab = 'login';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง';
                }
            }
        }

    // --- CHANGE PASSWORD ---
    } elseif ($action === 'change_password' && isset($_SESSION['user'])) {
        $active_tab = 'change_password';
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';

        if (empty($current_password) || empty($new_password)) {
            $error = 'กรุณากรอกรหัสผ่านให้ครบถ้วน';
        } elseif (strlen($new_password) < 6) {
            $error = 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
        } else {
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user']['id']]);
            $user = $stmt->fetch();

            if ($user && password_verify($current_password, $user['password_hash'])) {
                $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $update->execute([$new_hash, $_SESSION['user']['id']]);
                $success = 'เปลี่ยนรหัสผ่านสำเร็จแล้ว';
            } else {
                $error = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ / สมัครสมาชิก - Hugmart</title>
    <!-- Google Font & FontAwesome -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0d9488;
            --primary-hover: #0f766e;
            --secondary: #0f172a;
            --bg-body: #f8fafc;
            --surface: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
        }

        * { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0; 
            font-family: 'Prompt', system-ui, -apple-system, sans-serif; 
        }

        body { 
            background: var(--bg-body); 
            color: var(--text-main); 
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* --- Header Bar --- */
        .auth-header {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 16px 8%;
            display: flex;
            align-items: center;
        }

        .auth-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .brand-icon {
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .brand-text {
            display: flex;
            flex-direction: column;
        }

        .brand-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--secondary);
            line-height: 1;
        }

        .brand-sub {
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--primary);
            letter-spacing: 1.5px;
            margin-top: 2px;
        }

        /* --- Container & Card --- */
        .auth-container { 
            flex: 1; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            padding: 40px 20px; 
        }

        .auth-box { 
            background: var(--surface); 
            width: 100%; 
            max-width: 480px; 
            padding: 35px; 
            border-radius: 12px; 
            border: 1px solid var(--border);
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05); 
        }

        .tab-menu { 
            display: flex; 
            border-bottom: 2px solid var(--border); 
            margin-bottom: 25px; 
        }

        .tab-btn { 
            flex: 1; 
            text-align: center; 
            padding: 12px; 
            cursor: pointer; 
            border: none; 
            background: none; 
            font-weight: 500; 
            color: var(--text-muted); 
            text-decoration: none; 
            font-size: 1rem;
            transition: all 0.2s;
            position: relative;
            bottom: -2px;
        }

        .tab-btn.active { 
            color: var(--primary); 
            border-bottom: 3px solid var(--primary); 
            font-weight: 600;
        }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 0.88rem; margin-bottom: 6px; color: var(--text-main); font-weight: 500; }
        .form-group input { 
            width: 100%; 
            padding: 11px 14px; 
            border: 1px solid var(--border); 
            border-radius: 8px; 
            outline: none; 
            font-size: 0.92rem;
            transition: border-color 0.2s;
            background: #fafafa;
        }
        .form-group input:focus { 
            border-color: var(--primary); 
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.1);
        }

        .btn-submit { 
            width: 100%; 
            background: var(--primary); 
            color: white; 
            border: none; 
            padding: 12px; 
            font-weight: 600; 
            font-size: 1rem;
            cursor: pointer; 
            margin-top: 10px; 
            border-radius: 8px; 
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-submit:hover { background: var(--primary-hover); }

        .alert { 
            padding: 12px; 
            font-size: 0.9rem; 
            text-align: center; 
            margin-bottom: 20px; 
            border-radius: 8px; 
        }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }

        .user-info { text-align: center; margin-bottom: 25px; }
        .user-info h3 { font-size: 1.25rem; color: var(--secondary); margin-bottom: 4px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    </style>
</head>
<body>

    <!-- Header Bar -->
    <header class="auth-header">
        <a href="index.php" class="auth-brand">
            <div class="brand-icon">
                <i class="fa-solid fa-store"></i>
            </div>
            <div class="brand-text">
                <span class="brand-title">Hugmart</span>
                <span class="brand-sub">MARKETPLACE</span>
            </div>
        </a>
    </header>

    <div class="auth-container">
        <div class="auth-box">
            <?php if (!isset($_SESSION['user'])): ?>
                <!-- Tab Login / Register -->
                <div class="tab-menu">
                    <a href="auth.php?tab=login" class="tab-btn <?= $active_tab === 'login' ? 'active' : '' ?>">
                        <i class="fa-solid fa-right-to-bracket"></i> เข้าสู่ระบบ
                    </a>
                    <a href="auth.php?tab=register" class="tab-btn <?= $active_tab === 'register' ? 'active' : '' ?>">
                        <i class="fa-solid fa-user-plus"></i> สมัครสมาชิก
                    </a>
                </div>
            <?php else: ?>
                <!-- แสดงเมื่อเข้าสู่ระบบแล้ว -->
                <div class="user-info">
                    <h3><i class="fa-solid fa-circle-user" style="color: var(--primary);"></i> บัญชีผู้ใช้ของคุณ</h3>
                    <p style="color: var(--text-muted); font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['user']['email']) ?></p>
                </div>
            <?php endif; ?>

            <?php if ($error): ?><div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= $error ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= $success ?></div><?php endif; ?>

            <?php if (!isset($_SESSION['user'])): ?>
                <?php if ($active_tab === 'login'): ?>
                    <!-- FORM LOGIN -->
                    <form method="POST">
                        <input type="hidden" name="action" value="login">
                        <div class="form-group">
                            <label>อีเมล</label>
                            <input type="email" name="email" required placeholder="name@example.com">
                        </div>
                        <div class="form-group">
                            <label>รหัสผ่าน</label>
                            <input type="password" name="password" required placeholder="••••••••">
                        </div>
                        <button type="submit" class="btn-submit">เข้าสู่ระบบ</button>
                    </form>
                <?php else: ?>
                    <!-- FORM REGISTER -->
                    <form method="POST">
                        <input type="hidden" name="action" value="register">
                        <div class="form-group">
                            <label>ชื่อ-นามสกุล *</label>
                            <input type="text" name="fullname" required placeholder="สมชาย ใจดี">
                        </div>
                        <div class="form-group">
                            <label>อีเมล *</label>
                            <input type="email" name="email" required placeholder="name@example.com">
                        </div>
                        <div class="form-group">
                            <label>เบอร์โทรศัพท์</label>
                            <input type="text" name="phone" placeholder="0812345678">
                        </div>
                        <div class="form-group">
                            <label>เลขที่บ้าน / หมู่บ้าน / ซอย *</label>
                            <input type="text" name="address_line1" required placeholder="เช่น 123/45 หมู่ 2">
                        </div>
                        <div class="grid-2">
                            <div class="form-group"><label>ตำบล/แขวง</label><input type="text" name="subdistrict"></div>
                            <div class="form-group"><label>อำเภอ/เขต</label><input type="text" name="district"></div>
                        </div>
                        <div class="grid-2">
                            <div class="form-group"><label>จังหวัด</label><input type="text" name="province"></div>
                            <div class="form-group"><label>รหัสไปรษณีย์</label><input type="text" name="postal_code"></div>
                        </div>
                        <div class="form-group">
                            <label>รหัสผ่าน *</label>
                            <input type="password" name="password" required minlength="6" placeholder="อย่างน้อย 6 ตัวอักษร">
                        </div>
                        <button type="submit" class="btn-submit">ลงทะเบียน</button>
                    </form>
                <?php endif; ?>

            <?php else: ?>
                <!-- FORM CHANGE PASSWORD -->
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <h4 style="margin-bottom: 15px; color: var(--secondary); font-size: 1.05rem;">เปลี่ยนรหัสผ่าน</h4>
                    <div class="form-group">
                        <label>รหัสผ่านปัจจุบัน</label>
                        <input type="password" name="current_password" required placeholder="••••••••">
                    </div>
                    <div class="form-group">
                        <label>รหัสผ่านใหม่ (อย่างน้อย 6 ตัวอักษร)</label>
                        <input type="password" name="new_password" required minlength="6" placeholder="••••••••">
                    </div>
                    <button type="submit" class="btn-submit">อัปเดตรหัสผ่าน</button>
                    <a href="auth.php?action=logout" style="display:block; text-align:center; margin-top:18px; color:#ef4444; text-decoration:none; font-size:0.9rem; font-weight: 500;">
                        <i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ
                    </a>
                </form>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>