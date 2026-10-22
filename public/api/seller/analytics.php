<?php
require_once __DIR__ . '/../../../vendor/autoload.php';

use Hugmart\Config\Database;
use Hugmart\Middleware\AuthMiddleware;

session_start();
header('Content-Type: application/json');

AuthMiddleware::authorize(['seller']);
$userId = $_SESSION['user']['id'];

$db = Database::getConnection();

// Get Seller Profile
$stmtSeller = $db->prepare("SELECT id, store_name, commission_rate FROM seller_profiles WHERE user_id = ?");
$stmtSeller->execute([$userId]);
$seller = $stmtSeller->fetch();

if (!$seller) {
    http_response_code(404);
    echo json_encode(['error' => 'Seller profile not found']);
    exit();
}

$sellerId = $seller['id'];

// 1. Total Revenue
$stmtRev = $db->prepare("SELECT SUM(grand_total) as total_revenue, COUNT(id) as total_orders 
                         FROM orders WHERE seller_id = ? AND status IN ('paid', 'processing', 'shipped', 'delivered')");
$stmtRev->execute([$sellerId]);
$revenueData = $stmtRev->fetch();

// 2. Sales Chart Data (Last 7 Days)
$stmtChart = $db->prepare("SELECT DATE(created_at) as sale_date, SUM(grand_total) as daily_sales
                           FROM orders 
                           WHERE seller_id = ? AND status IN ('paid', 'processing', 'shipped', 'delivered')
                             AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                           GROUP BY DATE(created_at)
                           ORDER BY sale_date ASC");
$stmtChart->execute([$sellerId]);
$chartData = $stmtChart->fetchAll();

echo json_encode([
    'success' => true,
    'store_name' => $seller['store_name'],
    'metrics' => [
        'total_revenue' => number_format((float)($revenueData['total_revenue'] ?? 0), 2),
        'total_orders'  => (int)($revenueData['total_orders'] ?? 0),
        'net_revenue'   => number_format(($revenueData['total_revenue'] ?? 0) * (1 - ($seller['commission_rate'] / 100)), 2)
    ],
    'sales_chart' => $chartData
]);