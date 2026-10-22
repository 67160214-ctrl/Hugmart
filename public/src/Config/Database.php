<?php

// เริ่ม Session และตั้งค่า Cookie ให้ครอบคลุมทุก Path บน Domain
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// ========================================
// Database Configuration (Docker MySQL)
// ========================================

define('DB_HOST', 'db');
define('DB_USER', 'root');
define('DB_PASS', 'root_secret');
define('DB_NAME', 'hugmart_db');
define('DB_PORT', '3306');

// ========================================
// Database Connection
// ========================================

try {
    $dsn = 'mysql:host=' . DB_HOST
         . ';port=' . DB_PORT
         . ';dbname=' . DB_NAME
         . ';charset=utf8mb4';

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

} catch (PDOException $e) {
    error_log('Database Connection Failed: ' . $e->getMessage());

    http_response_code(500);
    exit('ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาตรวจสอบการตั้งค่า MySQL');
}

// ========================================
// CSRF Token Generation
// ========================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}