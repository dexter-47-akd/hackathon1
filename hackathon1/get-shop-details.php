<?php
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Vendor ID required']);
    exit;
}

$vendorId = (int)$_GET['id'];

try {
    // Get vendor details
    $stmt = $pdo->prepare("
        SELECT v.*, u.email,
               (SELECT MAX(order_date) FROM orders WHERE vendor_id = v.id) as last_ordered
        FROM vendors v 
        JOIN users u ON v.user_id = u.id 
        WHERE v.id = ?
    ");
    $stmt->execute([$vendorId]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vendor) {
        echo json_encode(['success' => false, 'message' => 'Vendor not found']);
        exit;
    }
    
    // Get menu items
    $stmt = $pdo->prepare("
        SELECT * FROM menu_items 
        WHERE vendor_id = ? AND availability = 1
        ORDER BY item_name
    ");
    $stmt->execute([$vendorId]);
    $menu = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'vendor' => $vendor,
        'menu' => $menu
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
