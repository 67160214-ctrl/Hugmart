<?php

// 1. โหลดไฟล์ Config DB
require_once __DIR__ . '/../../../src/Config/Database.php';

header('Content-Type: application/json; charset=utf-8');

// 2. ตรวจสอบ Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

// 3. รับข้อมูล (รองรับทั้ง JSON Payload และ Form Data/$_POST)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (str_contains($contentType, 'application/json')) {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];
} else {
    $data = $_POST;
}

if (empty($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or empty payload']);
    exit();
}

// 4. ตรวจสอบ CSRF Token
$token = $data['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF Token']);
    exit();
}

// 5. ดึงข้อมูลจาก ฟอร์ม
$username        = trim($data['username'] ?? '');
$fullName        = htmlspecialchars(trim($data['fullname'] ?? ''));
$email           = filter_var(trim($data['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$password        = $data['password'] ?? '';
$confirmPassword = $data['confirm_password'] ?? '';

// Check รหัสผ่านตรงกัน
if ($password !== $confirmPassword) {
    http_response_code(422);
    echo json_encode(['error' => 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน']);
    exit();
}

if (!$email || strlen($password) < 8 || empty($fullName) || empty($username)) {
    http_response_code(422);
    echo json_encode(['error' => 'กรุณากรอกข้อมูลให้ครบถ้วน และรหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร']);
    exit();
}

// 6. บันทึกลง Database
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $stmt->execute([$email, $username]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'อีเมลหรือชื่อผู้ใช้นี้ถูกใช้งานแล้ว']);
        exit();
    }

    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, 'buyer')");
    $stmt->execute([$username, $email, $passwordHash, $fullName]);
    $userId = $pdo->lastInsertId();

    $stmtCart = $pdo->prepare("INSERT INTO carts (user_id) VALUES (?)");
    $stmtCart->execute([$userId]);

    $pdo->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'สมัครสมาชิกสำเร็จ! กำลังนำท่านไปหน้าเข้าสู่ระบบ...'
    ]);

} catch (\Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}