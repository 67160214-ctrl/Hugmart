<?php
require_once __DIR__ . '/../../../vendor/autoload.php';

use Hugmart\Config\Database;
use Hugmart\Middleware\AuthMiddleware;
use Hugmart\Helpers\CSRF;

session_start();
header('Content-Type: application/json');

AuthMiddleware::authorize(['admin']);

$data = json_decode(file_get_contents("php://input"), true);

if (!CSRF::verifyToken($data['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF verification failed']);
    exit();
}

$sellerProfileId = filter_var($data['seller_profile_id'] ?? null, FILTER_VALIDATE_INT);
$action          = $data['action'] ?? ''; // 'approve' or 'reject'

if (!$sellerProfileId || !in_array($action, ['approve', 'reject'])) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid parameters']);
    exit();
}

$db = Database::getConnection();

$status = ($action === 'approve') ? 'approved' : 'rejected';

$stmt = $db->prepare("UPDATE seller_profiles SET status = ? WHERE id = ?");
$stmt->execute([$status, $sellerProfileId]);

// Audit Log Entry
$stmtLog = $db->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
$stmtLog->execute([
    $_SESSION['user']['id'],
    'ADMIN_STORE_' . strtoupper($action),
    "Updated Seller Profile ID {$sellerProfileId} to {$status}",
    $_SERVER['REMOTE_ADDR']
]);

echo json_encode([
    'success' => true,
    'message' => "Store profile successfully " . $status
]);