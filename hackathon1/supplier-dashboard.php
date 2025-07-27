<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

requireLogin();

if (getUserType() !== 'supplier') {
    header('Location: index.php');
    exit;
}

// Get supplier details
$stmt = $pdo->prepare("SELECT s.*, u.name FROM suppliers s JOIN users u ON s.user_id = u.id WHERE s.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$supplier = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$supplier) {
    header('Location: supplier-auth.php');
    exit;
}

$success = '';
$error = '';

// Handle order response
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['respond_order'])) {
        $orderId = $_POST['order_id'];
        $response = $_POST['response'];
        $deliveryDate = isset($_POST['delivery_date']) && !empty($_POST['delivery_date']) ? $_POST['delivery_date'] : null;
        
        try {
            $stmt = $pdo->prepare("
                UPDATE vendor_orders 
                SET status = ?, response_date = NOW(), delivery_date = ? 
                WHERE id = ? AND supplier_id = ?
            ");
            $stmt->execute([$response, $deliveryDate, $orderId, $supplier['id']]);
            $success = 'Order response updated successfully!';
        } catch (Exception $e) {
            $error = 'Failed to update order response.';
        }
    } elseif (isset($_POST['update_ingredient'])) {
        $vendorId = $_POST['vendor_id'];
        $ingredientName = $_POST['ingredient_name'];
        $status = $_POST['status'];
        $deliveryDate = isset($_POST['delivery_date']) && !empty($_POST['delivery_date']) ? $_POST['delivery_date'] : null;
        $nextDelivery = isset($_POST['next_delivery']) && !empty($_POST['next_delivery']) ? $_POST['next_delivery'] : null;
        
        try {
            // Check if ingredient sourcing record exists
            $stmt = $pdo->prepare("
                SELECT id FROM ingredient_sourcing 
                WHERE vendor_id = ? AND supplier_id = ? AND ingredient_name = ?
            ");
            $stmt->execute([$vendorId, $supplier['id'], $ingredientName]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update existing record
                $stmt = $pdo->prepare("
                    UPDATE ingredient_sourcing 
                    SET status = ?, last_delivered = ?, next_delivery_date = ?, is_verified = 1
                    WHERE id = ?
                ");
                $stmt->execute([$status, $deliveryDate, $nextDelivery, $existing['id']]);
            } else {
                // Create new record
                $stmt = $pdo->prepare("
                    INSERT INTO ingredient_sourcing (vendor_id, supplier_id, ingredient_name, status, last_delivered, next_delivery_date, is_verified) 
                    VALUES (?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$vendorId, $supplier['id'], $ingredientName, $status, $deliveryDate, $nextDelivery]);
            }
            
            $success = 'Ingredient status updated successfully!';
        } catch (Exception $e) {
            $error = 'Failed to update ingredient status.';
        }
    }
}

// Get pending orders
$stmt = $pdo->prepare("
    SELECT vo.*, v.shop_name, v.contact_number, u.name as vendor_owner
    FROM vendor_orders vo 
    JOIN vendors v ON vo.vendor_id = v.id 
    JOIN users u ON v.user_id = u.id
    WHERE vo.supplier_id = ? 
    ORDER BY vo.order_date DESC
");
$stmt->execute([$supplier['id']]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$totalOrders = count($orders);
$pendingOrders = count(array_filter($orders, function($order) { return $order['status'] === 'pending'; }));
$acceptedOrders = count(array_filter($orders, function($order) { return $order['status'] === 'accepted'; }));
$deliveredOrders = count(array_filter($orders, function($order) { return $order['status'] === 'delivered'; }));

// Get vendors we supply to
$stmt = $pdo->prepare("
    SELECT DISTINCT v.id, v.shop_name, v.contact_number, u.name as vendor_owner
    FROM vendor_orders vo 
    JOIN vendors v ON vo.vendor_id = v.id 
    JOIN users u ON v.user_id = u.id
    WHERE vo.supplier_id = ? AND vo.status IN ('accepted', 'delivered')
");
$stmt->execute([$supplier['id']]);
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to parse order items
function parseOrderItems($items, $orderType) {
    if ($orderType === 'structured') {
        $itemsArray = json_decode($items, true);
        if (is_array($itemsArray)) {
            $formattedItems = [];
            foreach ($itemsArray as $item) {
                $formattedItems[] = [
                    'name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'price' => $item['unit_price'],
                    'total' => $item['quantity'] * $item['unit_price']
                ];
            }
            return $formattedItems;
        }
    }
    return null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Dashboard - FreshStalls</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .dashboard {
            background: #f8f9fa;
            min-height: 100vh;
            padding: 2rem 0;
        }
        .dashboard-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        .dashboard-header h2 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        .dashboard-header p {
            color: #666;
            font-size: 1.1rem;
        }
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
        }
        .stat-card h3 {
            font-size: 2.5rem;
            color: #007bff;
            margin-bottom: 0.5rem;
        }
        .stat-card p {
            color: #666;
            font-weight: 600;
        }
        .dashboard-content {
            display: grid;
            gap: 2rem;
        }
        .dashboard-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
        }
        .dashboard-card h3 {
            color: #333;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }
        .orders-list {
            display: grid;
            gap: 1.5rem;
        }
        .order-item {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 2rem;
            transition: all 0.3s ease;
        }
        .order-item:hover {
            border-color: #007bff;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }
        .order-info h5 {
            color: #333;
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
        }
        .order-info p {
            color: #666;
            margin: 0.25rem 0;
            font-size: 0.95rem;
        }
        .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .badge-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .badge-accepted {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .badge-delivered {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .badge-rejected {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .order-details {
            margin-bottom: 1.5rem;
        }
        .order-items {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin: 1rem 0;
        }
        .order-items h6 {
            color: #333;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        .item-card {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .item-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        .item-details {
            color: #666;
            font-size: 0.9rem;
        }
        .item-quantity {
            color: #007bff;
            font-weight: 600;
        }
        .item-price {
            color: #28a745;
            font-weight: 600;
        }
        .order-message {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
        .order-message h6 {
            color: #1976d2;
            margin-bottom: 0.5rem;
        }
        .order-message p {
            color: #424242;
            margin: 0;
        }
        .order-meta {
            color: #666;
            font-size: 0.9rem;
            margin: 1rem 0;
        }
        .order-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #545b62;
        }
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
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
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #007bff;
        }
        .business-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .info-item strong {
            color: #333;
        }
        .info-item span {
            color: #666;
        }
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
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
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="nav-brand">
                <div class="logo">F</div>
                <span class="brand-name">FreshStalls - Supplier</span>
            </div>
            <nav class="nav-menu">
                <a href="index.php" class="nav-link">Home</a>
                <a href="logout.php" class="nav-link">Logout</a>
            </nav>
        </div>
    </header>

    <div class="dashboard">
        <div class="container">
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <h2>Welcome, <?php echo htmlspecialchars($supplier['supplier_name']); ?>!</h2>
                <p>Manage your orders and update ingredient deliveries for vendors.</p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Dashboard Stats -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <h3><?php echo $totalOrders; ?></h3>
                    <p>Total Orders</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $pendingOrders; ?></h3>
                    <p>Pending Orders</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $acceptedOrders; ?></h3>
                    <p>Accepted Orders</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $deliveredOrders; ?></h3>
                    <p>Delivered Orders</p>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <!-- Order Management -->
                <div class="dashboard-card">
                    <h3>📋 Order Management</h3>
                    
                    <?php if (empty($orders)): ?>
                        <div class="empty-state">
                            <p>No orders received yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="orders-list">
                            <?php foreach ($orders as $order): ?>
                                <div class="order-item">
                                    <div class="order-header">
                                        <div class="order-info">
                                            <h5>Order #<?php echo $order['id']; ?></h5>
                                            <p>From: <?php echo htmlspecialchars($order['shop_name']); ?></p>
                                            <p>Owner: <?php echo htmlspecialchars($order['vendor_owner']); ?></p>
                                            <p>Contact: <?php echo htmlspecialchars($order['contact_number']); ?></p>
                                        </div>
                                        <span class="badge badge-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span>
                                    </div>
                                    
                                    <div class="order-details">
                                        <?php 
                                        $parsedItems = parseOrderItems($order['items'], $order['order_type']);
                                        if ($parsedItems): ?>
                                            <div class="order-items">
                                                <h6>📦 Order Items:</h6>
                                                <div class="items-grid">
                                                    <?php foreach ($parsedItems as $item): ?>
                                                        <div class="item-card">
                                                            <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                                            <div class="item-details">
                                                                <span class="item-quantity"><?php echo $item['quantity']; ?> <?php echo $item['unit']; ?></span>
                                                                <span class="item-price"> • ₹<?php echo number_format($item['total'], 2); ?></span>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="order-items">
                                                <h6>�� Order Items:</h6>
                                                <p><?php echo htmlspecialchars($order['items']); ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($order['message']): ?>
                                            <div class="order-message">
                                                <h6>💬 Vendor Message:</h6>
                                                <p><?php echo htmlspecialchars($order['message']); ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="order-meta">
                                            <p>📅 Order Date: <?php echo date('M j, Y g:i A', strtotime($order['order_date'])); ?></p>
                                            <?php if ($order['delivery_date']): ?>
                                                <p>🚚 Delivery Date: <?php echo date('M j, Y', strtotime($order['delivery_date'])); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($order['status'] === 'pending'): ?>
                                        <div class="order-actions">
                                            <form method="POST" style="display: flex; gap: 1rem; align-items: center;">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="response" value="accepted">
                                                <div class="form-group" style="margin: 0;">
                                                    <label for="delivery_date_<?php echo $order['id']; ?>">Delivery Date:</label>
                                                    <input type="date" name="delivery_date" id="delivery_date_<?php echo $order['id']; ?>" required>
                                                </div>
                                                <button type="submit" name="respond_order" class="btn btn-primary btn-sm">✅ Accept Order</button>
                                            </form>
                                            
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="response" value="rejected">
                                                <button type="submit" name="respond_order" class="btn btn-secondary btn-sm" onclick="return confirm('Are you sure you want to reject this order?')">❌ Reject Order</button>
                                            </form>
                                        </div>
                                    <?php elseif ($order['status'] === 'accepted'): ?>
                                        <div class="order-actions">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="response" value="delivered">
                                                <button type="submit" name="respond_order" class="btn btn-primary btn-sm">🚚 Mark as Delivered</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Ingredient Status Updates -->
                <div class="dashboard-card">
                    <h3>📦 Update Ingredient Status</h3>
                    <p>Update ingredient delivery status for your vendor clients</p>
                    
                    <?php if (empty($vendors)): ?>
                        <div class="empty-state">
                            <p>No active vendor relationships yet. Accept some orders first!</p>
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                                <div class="form-group">
                                    <label for="vendor_id">Vendor</label>
                                    <select id="vendor_id" name="vendor_id" required>
                                        <option value="">Select Vendor</option>
                                        <?php foreach ($vendors as $vendor): ?>
                                            <option value="<?php echo $vendor['id']; ?>">
                                                <?php echo htmlspecialchars($vendor['shop_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="ingredient_name">Ingredient Name</label>
                                    <input type="text" id="ingredient_name" name="ingredient_name" placeholder="e.g., Fresh Vegetables" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="status">Status</label>
                                    <select id="status" name="status" required>
                                        <option value="in_stock">In Stock</option>
                                        <option value="low_stock">Low Stock</option>
                                        <option value="out_of_stock">Out of Stock</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="delivery_date">Last Delivered</label>
                                    <input type="date" id="delivery_date" name="delivery_date">
                                </div>
                                
                                <div class="form-group">
                                    <label for="next_delivery">Next Delivery</label>
                                    <input type="date" id="next_delivery" name="next_delivery">
                                </div>
                            </div>
                            
                            <button type="submit" name="update_ingredient" class="btn btn-primary">Update Ingredient Status</button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Business Information -->
                <div class="dashboard-card">
                    <h3>🏭 Business Information</h3>
                    
                    <div class="business-info">
                        <div class="info-item">
                            <strong>Business Name:</strong>
                            <span><?php echo htmlspecialchars($supplier['supplier_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Owner:</strong>
                            <span><?php echo htmlspecialchars($supplier['owner_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <strong>GST Number:</strong>
                            <span><?php echo htmlspecialchars($supplier['gst_number']); ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Category:</strong>
                            <span><?php echo htmlspecialchars($supplier['category']); ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Location:</strong>
                            <span><?php echo htmlspecialchars($supplier['location']); ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Contact:</strong>
                            <span><?php echo htmlspecialchars($supplier['contact_number']); ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Minimum Order:</strong>
                            <span><?php echo $supplier['minimum_order_quantity']; ?> units</span>
                        </div>
                        <div class="info-item">
                            <strong>Verification Status:</strong>
                            <span><?php echo $supplier['is_verified'] ? '✓ Verified' : 'Not Verified'; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
