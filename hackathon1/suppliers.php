<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

requireLogin();

if (getUserType() !== 'vendor') {
    header('Location: index.php');
    exit;
}

// Get vendor details
$stmt = $pdo->prepare("SELECT * FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    header('Location: vendor-auth.php');
    exit;
}

$success = '';
$error = '';

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['place_quick_order'])) {
        $supplierId = $_POST['supplier_id'];
        $orderItems = json_decode($_POST['order_items'], true);
        $message = $_POST['message'];
        $templateId = !empty($_POST['template_id']) ? $_POST['template_id'] : null;
        
        if (!empty($orderItems)) {
            try {
                $pdo->beginTransaction();
                
                // Create the main order
                $stmt = $pdo->prepare("
                    INSERT INTO vendor_orders (vendor_id, supplier_id, items, message, status, order_type, template_id) 
                    VALUES (?, ?, ?, ?, 'pending', 'structured', ?)
                ");
                $stmt->execute([$vendor['id'], $supplierId, json_encode($orderItems), $message, $templateId]);
                $orderId = $pdo->lastInsertId();
                
                // Add order items
                foreach ($orderItems as $item) {
                    $stmt = $pdo->prepare("
                        INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price, notes) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $totalPrice = $item['quantity'] * $item['unit_price'];
                    $stmt->execute([$orderId, $item['product_id'], $item['quantity'], $item['unit_price'], $totalPrice, $item['notes']]);
                }
                
                $pdo->commit();
                $success = 'Quick Order placed successfully! The supplier will review your request.';
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Failed to place order. Please try again.';
            }
        } else {
            $error = 'Please add at least one item to your order.';
        }
    }
    
    if (isset($_POST['place_text_order'])) {
        $supplierId = $_POST['supplier_id'];
        $items = $_POST['items'];
        $message = $_POST['message'];
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO vendor_orders (vendor_id, supplier_id, items, message, status, order_type) 
                VALUES (?, ?, ?, ?, 'pending', 'text')
            ");
            $stmt->execute([$vendor['id'], $supplierId, $items, $message]);
            $success = 'Text Order placed successfully! The supplier will review your request.';
        } catch (Exception $e) {
            $error = 'Failed to place order. Please try again.';
        }
    }
}

// Get all suppliers
$stmt = $pdo->query("
    SELECT s.*, u.name as owner_name 
    FROM suppliers s 
    JOIN users u ON s.user_id = u.id 
    WHERE s.shop_status = 'open' 
    ORDER BY s.is_verified DESC, s.supplier_name
");
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get vendor's templates
$stmt = $pdo->prepare("
    SELECT * FROM order_templates 
    WHERE vendor_id = ? 
    ORDER BY is_favorite DESC, template_name
");
$stmt->execute([$vendor['id']]);
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get common supplies for auto-add
$commonSupplies = [
    ['name' => 'Potatoes', 'quantity' => 1, 'unit' => 'kg', 'category' => 'Vegetables'],
    ['name' => 'Onions', 'quantity' => 2, 'unit' => 'kg', 'category' => 'Vegetables'],
    ['name' => 'Tomatoes', 'quantity' => 2, 'unit' => 'kg', 'category' => 'Vegetables'],
    ['name' => 'Ginger', 'quantity' => 0.5, 'unit' => 'kg', 'category' => 'Vegetables'],
    ['name' => 'Garlic', 'quantity' => 0.5, 'unit' => 'kg', 'category' => 'Vegetables'],
    ['name' => 'Green Chilies', 'quantity' => 0.25, 'unit' => 'kg', 'category' => 'Vegetables'],
    ['name' => 'Cooking Oil', 'quantity' => 2, 'unit' => 'liter', 'category' => 'Oils'],
    ['name' => 'Salt', 'quantity' => 1, 'unit' => 'kg', 'category' => 'Essentials'],
    ['name' => 'Sugar', 'quantity' => 1, 'unit' => 'kg', 'category' => 'Sweeteners'],
    ['name' => 'Turmeric Powder', 'quantity' => 0.25, 'unit' => 'kg', 'category' => 'Spices'],
    ['name' => 'Red Chili Powder', 'quantity' => 0.25, 'unit' => 'kg', 'category' => 'Spices'],
    ['name' => 'Coriander Powder', 'quantity' => 0.25, 'unit' => 'kg', 'category' => 'Spices'],
    ['name' => 'Wheat Flour', 'quantity' => 5, 'unit' => 'kg', 'category' => 'Grains'],
    ['name' => 'Basmati Rice', 'quantity' => 5, 'unit' => 'kg', 'category' => 'Grains'],
    ['name' => 'Toor Dal', 'quantity' => 1, 'unit' => 'kg', 'category' => 'Pulses'],
    ['name' => 'Mustard Oil', 'quantity' => 1, 'unit' => 'liter', 'category' => 'Oils'],
    ['name' => 'Ghee', 'quantity' => 0.5, 'unit' => 'kg', 'category' => 'Oils'],
    ['name' => 'Butter', 'quantity' => 0.5, 'unit' => 'kg', 'category' => 'Oils'],
    ['name' => 'Milk', 'quantity' => 2, 'unit' => 'liter', 'category' => 'Dairy'],
    ['name' => 'Curd', 'quantity' => 1, 'unit' => 'kg', 'category' => 'Dairy'],
    ['name' => 'Paneer', 'quantity' => 0.5, 'unit' => 'kg', 'category' => 'Dairy']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppliers - FreshStalls</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .order-tabs {
            display: flex;
            margin-bottom: 2rem;
            border-bottom: 2px solid #e1e5e9;
        }
        .order-tab {
            padding: 1rem 2rem;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 600;
            color: #666;
            border-bottom: 3px solid transparent;
        }
        .order-tab.active {
            color: #007bff;
            border-bottom-color: #007bff;
        }
        .order-content {
            display: none;
        }
        .order-content.active {
            display: block;
        }
        .category-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid #e1e5e9;
            padding-bottom: 1rem;
        }
        .category-tab {
            padding: 0.5rem 1rem;
            background: #f8f9fa;
            border: 1px solid #e1e5e9;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        .category-tab.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        .category-content {
            display: none;
        }
        .category-content.active {
            display: block;
        }
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .product-card {
            background: white;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            padding: 1rem;
            cursor: pointer;
            transition: border-color 0.3s;
        }
        .product-card:hover {
            border-color: #007bff;
        }
        .product-card.selected {
            border-color: #28a745;
            background: #f8fff9;
        }
        .product-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        .product-details {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        .product-price {
            font-weight: 600;
            color: #28a745;
            margin-bottom: 0.5rem;
        }
        .product-quantity {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .quantity-input {
            width: 80px;
            padding: 0.5rem;
            border: 1px solid #e1e5e9;
            border-radius: 4px;
            text-align: center;
        }
        .auto-add-section {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .auto-add-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }
        .auto-add-items {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .auto-add-item {
            background: white;
            padding: 0.5rem 1rem;
            border: 1px solid #e1e5e9;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        .auto-add-item:hover {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        .cart-items {
            margin: 1rem 0;
        }
        .cart-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }
        .cart-item-info {
            flex: 1;
        }
        .cart-item-name {
            font-weight: 600;
            color: #333;
        }
        .cart-item-details {
            color: #666;
            font-size: 0.9rem;
        }
        .cart-item-quantity {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .quantity-input {
            width: 80px;
            padding: 0.5rem;
            border: 1px solid #e1e5e9;
            border-radius: 4px;
            text-align: center;
        }
        .cart-item-price {
            font-weight: 600;
            color: #28a745;
            min-width: 100px;
            text-align: right;
        }
        .remove-item {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.5rem;
            border-radius: 4px;
            cursor: pointer;
        }
        .cart-summary {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1rem;
            border: 2px solid #e1e5e9;
        }
        .template-card {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 2px solid #e1e5e9;
            cursor: pointer;
            transition: border-color 0.3s;
        }
        .template-card:hover {
            border-color: #007bff;
        }
        .template-card.favorite {
            border-color: #ffc107;
            background: #fffbf0;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 12px;
            width: 90%;
            max-width: 1000px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: #000;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
        }
        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 1rem 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 10000;
            display: none;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }
        .search-highlight {
            background: #ffeb3b;
            padding: 0 2px;
        }
        .min-order-info {
            color: #dc3545;
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <div class="vendor-sidebar">
            <div class="sidebar-header">
                <div class="vendor-info">
                    <div class="vendor-avatar">
                        <?php echo strtoupper(substr($vendor['shop_name'], 0, 1)); ?>
                    </div>
                    <div class="vendor-name"><?php echo htmlspecialchars($vendor['shop_name']); ?></div>
                    <div class="vendor-category"><?php echo htmlspecialchars($vendor['category']); ?></div>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <a href="vendor-dashboard.php" class="nav-item">
                    <i>📊</i> Dashboard
                </a>
                <a href="suppliers.php" class="nav-item active">
                    <i>🏭</i> Suppliers
                </a>
                <a href="ingredient-status.php" class="nav-item">
                    <i>📋</i> Ingredient Status
                </a>
                <a href="menu-management.php" class="nav-item">
                    <i>🍽️</i> Menu
                </a>
                <a href="profile.php" class="nav-item">
                    <i>👤</i> Profile
                </a>
                <a href="index.php" class="nav-item">
                    <i>🏠</i> Home
                </a>
                <a href="logout.php" class="nav-item">
                    <i>🚪</i> Logout
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="dashboard-main">
            <div class="dashboard-header">
                <h1>Available Suppliers</h1>
                <p>Connect with verified suppliers for your ingredient needs</p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Suppliers List -->
            <div class="suppliers-container">
                <?php if (empty($suppliers)): ?>
                    <div class="empty-state">
                        <h3>No suppliers available</h3>
                        <p>Check back later for available suppliers in your area.</p>
                    </div>
                <?php else: ?>
                    <div class="suppliers-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem;">
                        <?php foreach ($suppliers as $supplier): ?>
                            <div class="supplier-card" style="background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                <div class="supplier-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                                    <div>
                                        <h3 style="color: #333; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($supplier['supplier_name']); ?></h3>
                                        <p style="color: #666; margin: 0;">Owner: <?php echo htmlspecialchars($supplier['owner_name']); ?></p>
                                    </div>
                                    <?php if ($supplier['is_verified']): ?>
                                        <span class="verified-badge">✓ Verified</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="supplier-info" style="margin-bottom: 1.5rem;">
                                    <p style="margin-bottom: 0.5rem;"><strong>Category:</strong> <?php echo htmlspecialchars($supplier['category']); ?></p>
                                    <p style="margin-bottom: 0.5rem;"><strong>GST:</strong> <?php echo htmlspecialchars($supplier['gst_number']); ?></p>
                                    <p style="margin-bottom: 0.5rem;"><strong>Contact:</strong> <?php echo htmlspecialchars($supplier['contact_number']); ?></p>
                                    <p style="margin-bottom: 0.5rem;"><strong>Min Order:</strong> <?php echo $supplier['minimum_order_quantity']; ?> units</p>
                                    <p style="margin-bottom: 1rem;"><strong>Location:</strong> <?php echo htmlspecialchars($supplier['location']); ?></p>
                                    
                                    <?php if ($supplier['specialty']): ?>
                                        <p style="color: #666; font-style: italic;"><?php echo htmlspecialchars($supplier['specialty']); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Mini Map -->
                                <div id="map-<?php echo $supplier['id']; ?>" style="height: 150px; border-radius: 8px; margin-bottom: 1rem;"></div>
                                
                                <div class="supplier-actions" style="display: flex; gap: 1rem;">
                                    <button class="btn btn-primary" onclick="showQuickOrderForm(<?php echo $supplier['id']; ?>, '<?php echo htmlspecialchars($supplier['supplier_name']); ?>')">
                                        ⚡ Quick Order
                                    </button>
                                    <button class="btn btn-secondary" onclick="showTextOrderForm(<?php echo $supplier['id']; ?>, '<?php echo htmlspecialchars($supplier['supplier_name']); ?>')">
                                        📝 Text Order
                                    </button>
                                    <button class="btn btn-secondary" onclick="showSupplierDetails(<?php echo $supplier['id']; ?>)">
                                        View Details
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Order Modal -->
    <div id="quickOrderModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('quickOrderModal')">&times;</span>
            <h3>⚡ Quick Order</h3>
            <div class="alert alert-info" style="background:#e9f7fd;color:#31708f;border:1px solid #bce8f1;margin-bottom:1.5rem;">
                <strong>Note:</strong> Ordering is only available from <b>5:00 AM to 12:00 PM</b>.
            </div>
            
            <div class="order-tabs">
                <button class="order-tab active" onclick="showOrderTab('catalog')">Product Catalog</button>
                <button class="order-tab" onclick="showOrderTab('templates')">Order Templates</button>
            </div>
            
            <!-- Product Catalog Tab -->
            <div id="catalog-tab" class="order-content active">
                <!-- Auto-add Common Supplies -->
                <div class="auto-add-section">
                    <div class="auto-add-title">🚀 Quick Add Common Supplies:</div>
                    <div class="auto-add-items" id="quickAddItems">
                        <!-- Will be populated by JS based on available products -->
                    </div>
                </div>
                
                <!-- Category Tabs -->
                <div class="category-tabs">
                    <div class="category-tab active" onclick="showCategory('all')">All Items</div>
                    <div class="category-tab" onclick="showCategory('Grains')">Grains & Flours</div>
                    <div class="category-tab" onclick="showCategory('Pulses')">Pulses & Legumes</div>
                    <div class="category-tab" onclick="showCategory('Vegetables')">Vegetables</div>
                    <div class="category-tab" onclick="showCategory('Spices')">Spices & Masalas</div>
                    <div class="category-tab" onclick="showCategory('Oils')">Oils & Fats</div>
                    <div class="category-tab" onclick="showCategory('Sweeteners')">Sweeteners</div>
                    <div class="category-tab" onclick="showCategory('Essentials')">Essentials</div>
                    <div class="category-tab" onclick="showCategory('Dairy')">Dairy</div>
                </div>
                
                <!-- Product Grid -->
                <div id="productGrid" class="product-grid">
                    <!-- Products will be loaded here -->
                </div>
                
                <div class="cart-items" id="cartItems">
                    <!-- Cart items will be added here -->
                </div>
                
                <div class="cart-summary" id="cartSummary" style="display: none;">
                    <h4>Order Summary</h4>
                    <div id="summaryDetails"></div>
                    <div style="margin-top: 1rem;">
                        <strong>Total: ₹<span id="totalAmount">0.00</span></strong>
                    </div>
                </div>
            </div>
            
            <!-- Templates Tab -->
            <div id="templates-tab" class="order-content">
                <?php if (empty($templates)): ?>
                    <p>No order templates available. Create templates for quick reordering.</p>
                <?php else: ?>
                    <?php foreach ($templates as $template): ?>
                        <div class="template-card <?php echo $template['is_favorite'] ? 'favorite' : ''; ?>" onclick="loadTemplate(<?php echo $template['id']; ?>)">
                            <h4><?php echo htmlspecialchars($template['template_name']); ?></h4>
                            <p><?php echo htmlspecialchars($template['description']); ?></p>
                            <?php if ($template['is_favorite']): ?>
                                <span style="color: #ffc107;">⭐ Favorite</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <form method="POST" id="quickOrderForm">
                <input type="hidden" name="supplier_id" id="quick_supplier_id">
                <input type="hidden" name="order_items" id="orderItems">
                <input type="hidden" name="template_id" id="templateId">
                
                <div class="form-group">
                    <label for="quick_message">Additional Message</label>
                    <textarea id="quick_message" name="message" rows="3" placeholder="Any specific requirements or delivery instructions"></textarea>
                </div>
                
                <div class="btn-group">
                    <button type="submit" name="place_quick_order" class="btn btn-primary" onclick="return validateQuickOrder()">Place Order</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('quickOrderModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Text Order Modal -->
    <div id="textOrderModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('textOrderModal')">&times;</span>
            <h3>📝 Text Order</h3>
            <div class="alert alert-info" style="background:#e9f7fd;color:#31708f;border:1px solid #bce8f1;margin-bottom:1.5rem;">
                <strong>Note:</strong> Ordering is only available from <b>5:00 AM to 12:00 PM</b>.
            </div>
            <form method="POST" id="textOrderForm">
                <input type="hidden" name="supplier_id" id="text_supplier_id">
                
                <div class="form-group">
                    <label for="text_supplier_name">Supplier</label>
                    <input type="text" id="text_supplier_name" readonly style="background: #f8f9fa;">
                </div>
                
                <div class="form-group">
                    <label for="text_items">Items Needed</label>
                    <textarea id="text_items" name="items" rows="4" placeholder="List the items you need (e.g., 10kg onions, 5kg tomatoes, 2kg spices)" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="text_message">Additional Message</label>
                    <textarea id="text_message" name="message" rows="3" placeholder="Any specific requirements or delivery instructions"></textarea>
                </div>
                
                <div class="btn-group">
                    <button type="submit" name="place_text_order" class="btn btn-primary">Place Order</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('textOrderModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Notification -->
    <div id="notification" class="notification">
        <div id="notificationMessage"></div>
    </div>

    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script>
        // Global variables
        let currentSupplierId = null;
        let cart = [];
        let products = [];
        let currentCategory = 'all';
        
        // Initialize maps for each supplier
        <?php foreach ($suppliers as $supplier): ?>
            (function() {
                const map = L.map('map-<?php echo $supplier['id']; ?>').setView([<?php echo $supplier['latitude']; ?>, <?php echo $supplier['longitude']; ?>], 13);
                
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);
                
                L.marker([<?php echo $supplier['latitude']; ?>, <?php echo $supplier['longitude']; ?>])
                    .addTo(map)
                    .bindPopup('<?php echo htmlspecialchars($supplier['supplier_name']); ?>');
            })();
        <?php endforeach; ?>

        // Modal functionality
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        window.addEventListener('click', (event) => {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        });

        function showQuickOrderForm(supplierId, supplierName) {
            currentSupplierId = supplierId;
            document.getElementById('quick_supplier_id').value = supplierId;
            document.getElementById('quickOrderModal').style.display = 'block';
            loadProducts(supplierId);
        }

        function showTextOrderForm(supplierId, supplierName) {
            document.getElementById('text_supplier_id').value = supplierId;
            document.getElementById('text_supplier_name').value = supplierName;
            document.getElementById('textOrderModal').style.display = 'block';
        }

        function showOrderTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.order-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.order-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
        }

        // Show category
        function showCategory(category) {
            currentCategory = category;
            
            // Update active tab
            document.querySelectorAll('.category-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Filter and display products
            displayProducts();
        }

        // Load products for supplier
        function loadProducts(supplierId) {
            fetch(`get-supplier-products.php?supplier_id=${supplierId}`)
                .then(response => response.json())
                .then(data => {
                    products = data;
                    displayProducts();
                })
                .catch(error => {
                    console.error('Error loading products:', error);
                });
        }

        // Display products by category
        function displayProducts() {
            const grid = document.getElementById('productGrid');
            grid.innerHTML = '';
            
            let filteredProducts = products;
            if (currentCategory !== 'all') {
                filteredProducts = products.filter(product => 
                    product.category === currentCategory
                );
            }
            
            // Show message if no products in category
            if (filteredProducts.length === 0) {
                grid.innerHTML = '<div style="padding:2rem;text-align:center;color:#888;">No products available in this category.</div>';
            }
            
            filteredProducts.forEach(product => {
                const card = document.createElement('div');
                card.className = 'product-card';
                card.onclick = () => addToCart(product);
                
                const isInCart = cart.find(item => item.product_id === product.id);
                if (isInCart) {
                    card.classList.add('selected');
                }
                
                card.innerHTML = `
                    <div class="product-name">${product.product_name}</div>
                    <div class="product-details">
                        SKU: ${product.sku} • ${product.category} • ${product.unit}
                    </div>
                    <div class="product-price">
                        ₹${product.price_per_unit}/${product.unit}
                    </div>
                    <div class="min-order-info">
                        Min Order: ${product.min_order_quantity} ${product.unit}
                    </div>
                `;
                
                grid.appendChild(card);
            });

            // Update Quick Add Common Supplies based on available products
            updateQuickAddItems();
        }

        // Update Quick Add Common Supplies to only show available products
        function updateQuickAddItems() {
            const quickAddDiv = document.getElementById('quickAddItems');
            if (!quickAddDiv) return;
            quickAddDiv.innerHTML = '';
            // List of common supplies (should match PHP $commonSupplies)
            const commonSupplies = [
                { name: 'Potatoes', quantity: 1, unit: 'kg', category: 'Vegetables' },
                { name: 'Onions', quantity: 2, unit: 'kg', category: 'Vegetables' },
                { name: 'Tomatoes', quantity: 2, unit: 'kg', category: 'Vegetables' },
                { name: 'Ginger', quantity: 0.5, unit: 'kg', category: 'Vegetables' },
                { name: 'Garlic', quantity: 0.5, unit: 'kg', category: 'Vegetables' },
                { name: 'Green Chilies', quantity: 0.25, unit: 'kg', category: 'Vegetables' },
                { name: 'Cooking Oil', quantity: 2, unit: 'liter', category: 'Oils' },
                { name: 'Salt', quantity: 1, unit: 'kg', category: 'Essentials' },
                { name: 'Sugar', quantity: 1, unit: 'kg', category: 'Sweeteners' },
                { name: 'Turmeric Powder', quantity: 0.25, unit: 'kg', category: 'Spices' },
                { name: 'Red Chili Powder', quantity: 0.25, unit: 'kg', category: 'Spices' },
                { name: 'Coriander Powder', quantity: 0.25, unit: 'kg', category: 'Spices' },
                { name: 'Wheat Flour', quantity: 5, unit: 'kg', category: 'Grains' },
                { name: 'Basmati Rice', quantity: 5, unit: 'kg', category: 'Grains' },
                { name: 'Toor Dal', quantity: 1, unit: 'kg', category: 'Pulses' },
                { name: 'Mustard Oil', quantity: 1, unit: 'liter', category: 'Oils' },
                { name: 'Ghee', quantity: 0.5, unit: 'kg', category: 'Oils' },
                { name: 'Butter', quantity: 0.5, unit: 'kg', category: 'Oils' },
                { name: 'Milk', quantity: 2, unit: 'liter', category: 'Dairy' },
                { name: 'Curd', quantity: 1, unit: 'kg', category: 'Dairy' },
                { name: 'Paneer', quantity: 0.5, unit: 'kg', category: 'Dairy' }
            ];
            // Only show if available in products
            commonSupplies.forEach(supply => {
                const match = products.find(p => p.product_name.toLowerCase().includes(supply.name.toLowerCase()) && p.category === supply.category);
                if (match) {
                    const div = document.createElement('div');
                    div.className = 'auto-add-item';
                    div.onclick = () => addCommonSupply(supply.name, supply.quantity, supply.unit, supply.category);
                    div.textContent = `${supply.name} (${supply.quantity} ${supply.unit})`;
                    quickAddDiv.appendChild(div);
                }
            });
        }

        // Add common supply
        function addCommonSupply(name, quantity, unit, category) {
            // More flexible search - look for partial matches
            const product = products.find(p => {
                const productNameLower = p.product_name.toLowerCase();
                const searchNameLower = name.toLowerCase();
                
                // Check if product name contains the search term
                const nameMatch = productNameLower.includes(searchNameLower) || 
                                searchNameLower.includes(productNameLower);
                
                // Also check category match
                const categoryMatch = p.category === category;
                
                return nameMatch && categoryMatch;
            });
            
            if (product) {
                const existingItem = cart.find(item => item.product_id === product.id);
                
                if (existingItem) {
                    existingItem.quantity += quantity;
                } else {
                    cart.push({
                        product_id: product.id,
                        product_name: product.product_name,
                        sku: product.sku,
                        unit: product.unit,
                        unit_price: parseFloat(product.price_per_unit),
                        quantity: quantity,
                        notes: ''
                    });
                }
                
                updateCartDisplay();
                displayProducts(); // Refresh to show selected state
                showNotification(`Added ${quantity} ${unit} of ${product.product_name}`, 'success');
            } else {
                // If exact match not found, try to find any product in that category
                const fallbackProduct = products.find(p => p.category === category);
                if (fallbackProduct) {
                    const existingItem = cart.find(item => item.product_id === fallbackProduct.id);
                    
                    if (existingItem) {
                        existingItem.quantity += quantity;
                    } else {
                        cart.push({
                            product_id: fallbackProduct.id,
                            product_name: fallbackProduct.product_name,
                            sku: fallbackProduct.sku,
                            unit: fallbackProduct.unit,
                            unit_price: parseFloat(fallbackProduct.price_per_unit),
                            quantity: quantity,
                            notes: ''
                        });
                    }
                    
                    updateCartDisplay();
                    displayProducts();
                    showNotification(`Added ${quantity} ${unit} of ${fallbackProduct.product_name} (closest match)`, 'success');
                } else {
                    showNotification(`No products found in ${category} category`, 'error');
                }
            }
        }

        // Add to cart
        function addToCart(product) {
            const existingItem = cart.find(item => item.product_id === product.id);
            
            if (existingItem) {
                existingItem.quantity += 1;
            } else {
                cart.push({
                    product_id: product.id,
                    product_name: product.product_name,
                    sku: product.sku,
                    unit: product.unit,
                    unit_price: parseFloat(product.price_per_unit),
                    quantity: 1,
                    notes: ''
                });
            }
            
            updateCartDisplay();
            displayProducts(); // Refresh to show selected state
        }

        // Update cart display with dynamic pricing
        function updateCartDisplay() {
            const cartContainer = document.getElementById('cartItems');
            const summaryContainer = document.getElementById('cartSummary');
            const summaryDetails = document.getElementById('summaryDetails');
            const totalAmount = document.getElementById('totalAmount');
            
            cartContainer.innerHTML = '';
            
            if (cart.length === 0) {
                summaryContainer.style.display = 'none';
                return;
            }
            
            let total = 0;
            
            cart.forEach((item, index) => {
                const itemTotal = item.quantity * item.unit_price;
                total += itemTotal;
                
                const itemElement = document.createElement('div');
                itemElement.className = 'cart-item';
                itemElement.innerHTML = `
                    <div class="cart-item-info">
                        <div class="cart-item-name">${item.product_name}</div>
                        <div class="cart-item-details">
                            SKU: ${item.sku} • ₹${item.unit_price}/${item.unit}
                        </div>
                    </div>
                    <div class="cart-item-quantity">
                        <input type="number" class="quantity-input" value="${item.quantity}" 
                               min="0.25" step="0.25" onchange="updateQuantity(${index}, this.value)">
                        <span>${item.unit}</span>
                    </div>
                    <div class="cart-item-price">₹${itemTotal.toFixed(2)}</div>
                    <button type="button" class="remove-item" onclick="removeFromCart(${index})">×</button>
                `;
                cartContainer.appendChild(itemElement);
            });
            
            summaryDetails.innerHTML = `
                <p><strong>Items:</strong> ${cart.length}</p>
                <p><strong>Total:</strong> ₹${total.toFixed(2)}</p>
            `;
            totalAmount.textContent = total.toFixed(2);
            summaryContainer.style.display = 'block';
            
            // Update hidden input
            document.getElementById('orderItems').value = JSON.stringify(cart);
        }

        // Update quantity with dynamic pricing
        function updateQuantity(index, quantity) {
            cart[index].quantity = parseFloat(quantity);
            updateCartDisplay();
        }

        // Remove from cart
        function removeFromCart(index) {
            cart.splice(index, 1);
            updateCartDisplay();
            displayProducts(); // Refresh to show selected state
        }

        // Load template
        function loadTemplate(templateId) {
            fetch(`get-template-items.php?template_id=${templateId}`)
                .then(response => response.json())
                .then(data => {
                    cart = data;
                    updateCartDisplay();
                    document.getElementById('templateId').value = templateId;
                    showOrderTab('catalog');
                })
                .catch(error => {
                    console.error('Error loading template:', error);
                });
        }

        // Validate quick order
        function validateQuickOrder() {
            if (cart.length === 0) {
                showNotification('Please add at least one item to your order.', 'error');
                return false;
            }
            document.getElementById('orderItems').value = JSON.stringify(cart);
            return true;
        }

        // Validate text order
        function validateTextOrder() {
            return true;
        }

        // Show popup message
        function showPopupMessage(message) {
            // Create modal if not exists
            let popup = document.getElementById('orderTimePopup');
            if (!popup) {
                popup = document.createElement('div');
                popup.id = 'orderTimePopup';
                popup.style.position = 'fixed';
                popup.style.top = '0';
                popup.style.left = '0';
                popup.style.width = '100vw';
                popup.style.height = '100vh';
                popup.style.background = 'rgba(0,0,0,0.5)';
                popup.style.display = 'flex';
                popup.style.alignItems = 'center';
                popup.style.justifyContent = 'center';
                popup.style.zIndex = '10001';
                popup.innerHTML = `
                    <div style="background:white;padding:2rem 3rem;border-radius:12px;max-width:90vw;text-align:center;box-shadow:0 4px 16px rgba(0,0,0,0.2);">
                        <h3 style="margin-bottom:1rem;">Ordering Unavailable</h3>
                        <div id="orderTimePopupMessage" style="margin-bottom:1.5rem;font-size:1.1rem;color:#333;"></div>
                        <button onclick="document.getElementById('orderTimePopup').remove()" style="padding:0.75rem 2rem;font-size:1rem;background:#007bff;color:white;border:none;border-radius:8px;cursor:pointer;">OK</button>
                    </div>
                `;
                document.body.appendChild(popup);
            }
            document.getElementById('orderTimePopupMessage').textContent = message;
        }

        // Show notification
        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            const notificationMessage = document.getElementById('notificationMessage');
            
            notification.style.background = type === 'success' ? '#28a745' : '#dc3545';
            notificationMessage.textContent = message;
            notification.style.display = 'block';
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                notification.style.display = 'none';
            }, 5000);
        }

        // Handle form submission for notifications
        document.getElementById('quickOrderForm').addEventListener('submit', function(e) {
            if (validateQuickOrder()) {
                // Show notification immediately
                showNotification('Quick Order placed successfully! The supplier will review your request.', 'success');
            }
        });

        document.getElementById('textOrderForm').addEventListener('submit', function(e) {
            if (!validateTextOrder()) {
                e.preventDefault();
                return false;
            }
            showNotification('Text Order placed successfully! The supplier will review your request.', 'success');
        });

        function showSupplierDetails(supplierId) {
            alert('Supplier details view - to be implemented');
        }
    </script>
</body>
</html>
