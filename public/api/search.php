<?php
require_once __DIR__ . '/../../../vendor/autoload.php';

use Hugmart\Config\Database;

header('Content-Type: application/json');

$query      = htmlspecialchars(trim($_GET['q'] ?? ''));
$categoryId = filter_var($_GET['category'] ?? null, FILTER_VALIDATE_INT);
$minPrice   = filter_var($_GET['min_price'] ?? 0, FILTER_VALIDATE_FLOAT);
$maxPrice   = filter_var($_GET['max_price'] ?? 999999, FILTER_VALIDATE_FLOAT);
$sortBy     = $_GET['sort'] ?? 'relevance';
$page       = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT));
$limit      = 12;
$offset     = ($page - 1) * $limit;

$db = Database::getConnection();

$whereConditions = ["p.status = 'active'"];
$params = [];

if (!empty($query)) {
    $whereConditions[] = "(p.name LIKE ? OR p.description LIKE ? OR sp.store_name LIKE ?)";
    $searchTerm = "%{$query}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($categoryId) {
    $whereConditions[] = "p.category_id = ?";
    $params[] = $categoryId;
}

if ($minPrice !== false && $maxPrice !== false) {
    $whereConditions[] = "p.current_price BETWEEN ? AND ?";
    $params[] = $minPrice;
    $params[] = $maxPrice;
}

$whereClause = implode(" AND ", $whereConditions);

$orderClause = match ($sortBy) {
    'price_low' => 'p.current_price ASC',
    'price_high' => 'p.current_price DESC',
    'best_selling' => 'p.sold_count DESC',
    'newest' => 'p.id DESC',
    default => 'p.rating_avg DESC, p.sold_count DESC'
};

// Count total
$countSql = "SELECT COUNT(*) as total FROM products p JOIN seller_profiles sp ON p.seller_id = sp.id WHERE {$whereClause}";
$stmtCount = $db->prepare($countSql);
$stmtCount->execute($params);
$totalRows = $stmtCount->fetch()['total'];

// Fetch data
$sql = "SELECT p.id, p.name, p.slug, p.original_price, p.current_price, p.rating_avg, p.sold_count, p.is_flash_sale, p.flash_sale_price,
               sp.store_name, pi.image_url
        FROM products p
        JOIN seller_profiles sp ON p.seller_id = sp.id
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
        WHERE {$whereClause}
        ORDER BY {$orderClause}
        LIMIT {$limit} OFFSET {$offset}";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'data' => $products,
    'pagination' => [
        'current_page' => $page,
        'total_pages'  => ceil($totalRows / $limit),
        'total_items'  => $totalRows
    ]
]);