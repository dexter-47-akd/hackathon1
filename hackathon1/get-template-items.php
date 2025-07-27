<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

requireLogin();

if (getUserType() !== 'vendor') {
    http_response_code(403);
    exit;
}

$templateId = isset($_GET['template_id']) ? intval($_GET['template_id']) : 0;

if ($templateId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid template ID']);
    exit;
}

try {
    // Get vendor ID to ensure template belongs to this vendor
    $stmt = $pdo->prepare("SELECT vendor_id FROM order_templates WHERE id = ?");
    $stmt->execute([$templateId]);
    $template = $stmt->fetch();
    
    if (!$template || $template['vendor_id'] != $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    
    // Get template items with product details
    $stmt = $pdo->prepare("
        SELECT ti.quantity, ti.notes,
               sp.id as product_id, sp.product_name, sp.sku, sp.unit, sp.price_per_unit
        FROM template_items ti
        JOIN supplier_products sp ON ti.product_id = sp.id
        WHERE ti.template_id = ?
        ORDER BY sp.category, sp.product_name
    ");
    $stmt->execute([$templateId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format items for cart
    $cartItems = [];
    foreach ($items as $item) {
        $cartItems[] = [
            'product_id' => $item['product_id'],
            'product_name' => $item['product_name'],
            'sku' => $item['sku'],
            'unit' => $item['unit'],
            'unit_price' => floatval($item['price_per_unit']),
            'quantity' => floatval($item['quantity']),
            'notes' => $item['notes']
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($cartItems);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch template items']);
}
?> 