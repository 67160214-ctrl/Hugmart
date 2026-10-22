<?php
// โหลดไฟล์ Config ฐานข้อมูล
require_once __DIR__ . '/../../../src/Config/Database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login.php?error=' . urlencode('Method Not Allowed'));
    exit();
}

$identity = trim($_POST['username'] ?? $_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($identity) || empty($password)) {
    header('Location: /login.php?error=' . urlencode('กรุณากรอกข้อมูลให้ครบถ้วน'));
    exit();
}

try {
    // ดึงข้อมูลผู้ใช้โดยรองรับการล็อกอินด้วย email หรือ phone/username
    // หมายเหตุ: ปรับชื่อคอลัมน์ phone ให้ตรงกับตารางจริง (หากตารางไม่มีคอลัมน์ phone ให้ใช้ SELECT * FROM users WHERE email = ?)
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR phone = ?");
    $stmt->execute([$identity, $identity]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // ตรวจสอบสถานะผู้ใช้ (ถ้ามีคอลัมน์ status)
        if (isset($user['status']) && $user['status'] !== 'active') {
            header('Location: /login.php?error=' . urlencode('บัญชีของคุณถูกระงับการใช้งาน'));
            exit();
        }

        // บันทึก Session
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['email']     = $user['email'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];

        header('Location: /index.php');
        exit();
    } else {
        header('Location: /login.php?error=' . urlencode('อีเมล/เบอร์โทรศัพท์ หรือรหัสผ่านไม่ถูกต้อง'));
        exit();
    }

} catch (PDOException $e) {
    // บันทึก Error Log สำหรับ Developer ดูในหลังบ้าน
    error_log("Login DB Error: " . $e->getMessage());

    // ส่งข้อความแจ้งเตือนกลับไปยังหน้า Login
    header('Location: /login.php?error=' . urlencode('เกิดข้อผิดพลาดในระบบฐานข้อมูล: ' . $e->getMessage()));
    exit();
}