<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'db';
$dbname = 's67160214';   // ชื่อฐานข้อมูล (หากไม่ได้ผลให้ลองเปลี่ยนเป็น '67160214')
$username = 's67160214'; // Username ตามภาพ
$password = 'W5xPdkn9';  // Password ตามภาพ

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $e->getMessage());
}
?>