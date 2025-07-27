<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

requireLogin();

if (getUserType() !== 'vendor') {
    http_response_code(403);
    exit;
}

$supplierId = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : 0;

if ($supplierId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid supplier ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, product_name, sku, description, unit, price_per_unit, 
               min_order_quantity, max_order_quantity, category, is_available
        FROM supplier_products 
        WHERE supplier_id = ? AND is_available = 1
        ORDER BY category, product_name
    ");
    $stmt->execute([$supplierId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($products);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch products']);
}
?> 