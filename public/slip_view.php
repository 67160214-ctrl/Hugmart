<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../src/Config/Database.php';
require_once __DIR__ . '/../src/Config/Payment.php';
require_once __DIR__ . '/../src/Services/OrderService.php';

if (!isset($pdo) && class_exists('Database')) {
    if (method_exists('Database', 'getConnection')) {
        $pdo = Database::getConnection();
    } elseif (method_exists('Database', 'getInstance')) {
        $pdo = Database::getInstance()->getConnection();
    } else {
        $db = new Database();
        $pdo = method_exists($db, 'getConnection') ? $db->getConnection() : ($db->pdo ?? null);
    }
}

function denySlip(int $code = 404): void
{
    http_response_code($code);
    echo $code === 403 ? 'Forbidden' : 'Not found';
    exit;
}

if (!isset($_SESSION['user_id'])) {
    denySlip(403);
}

$orderNumber = trim($_GET['order'] ?? '');
if ($orderNumber === '') {
    denySlip();
}

try {
    $stmt = $pdo->prepare("SELECT user_id, slip_image FROM orders WHERE order_number = ? LIMIT 1");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[Hugmart slip] ' . $e->getMessage());
    denySlip();
}

if (!$order || empty($order['slip_image'])) {
    denySlip();
}

// เฉพาะเจ้าของออเดอร์หรือแอดมินเท่านั้น
$isOwner = (string)$order['user_id'] === (string)$_SESSION['user_id'];
if (!$isOwner && !isAdminUser($pdo, $_SESSION['user_id'])) {
    denySlip(403);
}

$filename = $order['slip_image'];
if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $filename)) {
    denySlip();
}

$cfg = paymentConfig();
$path = $cfg['slip_dir'] . '/' . $filename;
if (!is_file($path)) {
    denySlip();
}

$mimeMap = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
$ext = pathinfo($filename, PATHINFO_EXTENSION);

header('Content-Type: ' . $mimeMap[$ext]);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
readfile($path);