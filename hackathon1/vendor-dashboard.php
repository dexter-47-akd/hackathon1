<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

requireLogin();

if (getUserType() !== 'vendor') {
    header('Location: index.php');
    exit;
}

// Get vendor details
$stmt = $pdo->prepare("SELECT v.*, u.name FROM vendors v JOIN users u ON v.user_id = u.id WHERE v.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    header('Location: vendor-auth.php');
    exit;
}

// Handle shop status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = $_POST['shop_status'];
    $stmt = $pdo->prepare("UPDATE vendors SET shop_status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $vendor['id']]);
    $vendor['shop_status'] = $newStatus;
}

// Get statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total_orders FROM vendor_orders WHERE vendor_id = ?");
$stmt->execute([$vendor['id']]);
$totalOrders = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as ingredient_items FROM ingredient_sourcing WHERE vendor_id = ?");
$stmt->execute([$vendor['id']]);
$ingredientItems = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as low_stock FROM ingredient_sourcing WHERE vendor_id = ? AND status = 'low_stock'");
$stmt->execute([$vendor['id']]);
$lowStock = $stmt->fetchColumn();

// Get recent orders
$stmt = $pdo->prepare("
    SELECT vo.*, s.supplier_name 
    FROM vendor_orders vo 
    LEFT JOIN suppliers s ON vo.supplier_id = s.id 
    WHERE vo.vendor_id = ? 
    ORDER BY vo.order_date DESC 
    LIMIT 5
");
$stmt->execute([$vendor['id']]);
$recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get ingredient sourcing status
$stmt = $pdo->prepare("
    SELECT ing.*, s.supplier_name 
    FROM ingredient_sourcing ing 
    LEFT JOIN suppliers s ON ing.supplier_id = s.id 
    WHERE ing.vendor_id = ? 
    ORDER BY 
        CASE ing.status 
            WHEN 'out_of_stock' THEN 1 
            WHEN 'low_stock' THEN 2 
            WHEN 'in_stock' THEN 3 
        END,
        ing.ingredient_name
");
$stmt->execute([$vendor['id']]);
$ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Dashboard - FreshStalls</title>
    <link rel="stylesheet" href="assets/css/styles.css">
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
                    
                    <div class="status-toggle">
                        <div class="status-indicator <?php echo $vendor['shop_status']; ?>"></div>
                        <form method="POST" style="display: inline;">
                            <select name="shop_status" onchange="this.form.submit()" style="border: none; background: none; font-weight: 600;">
                                <option value="open" <?php echo $vendor['shop_status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                                <option value="closed" <?php echo $vendor['shop_status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                            <input type="hidden" name="update_status" value="1">
                        </form>
                    </div>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <a href="vendor-dashboard.php" class="nav-item active">
                    <i>📊</i> Dashboard
                </a>
                <a href="suppliers.php" class="nav-item">
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
                <h1>Welcome back, <?php echo htmlspecialchars($vendor['name']); ?>!</h1>
                <p>Manage your stall, track ingredients, and connect with suppliers.</p>
            </div>

            <!-- Stats -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalOrders; ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $ingredientItems; ?></div>
                    <div class="stat-label">Ingredient Items</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $lowStock; ?></div>
                    <div class="stat-label">Low Stock Items</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $vendor['rating']; ?></div>
                    <div class="stat-label">Rating</div>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <!-- Quick Actions -->
                <div class="dashboard-card">
                    <h3 class="card-title">Quick Actions</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <a href="suppliers.php" class="btn btn-primary">
                            🏭 Find Suppliers
                        </a>
                        <a href="ingredient-status.php" class="btn btn-primary">
                            📦 View Ingredients
                        </a>
                        <a href="stall-details.php?id=<?php echo $vendor['id']; ?>" class="btn btn-primary">
                            👁️ View My Stall
                        </a>
                        <a href="profile.php" class="btn btn-secondary">
                            ⚙️ Settings
                        </a>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="dashboard-card">
                    <h3 class="card-title">Recent Orders to Suppliers</h3>
                    <?php if (empty($recentOrders)): ?>
                        <div class="empty-state">
                            <p>No orders yet. Start by finding suppliers!</p>
                            <a href="suppliers.php" class="btn btn-primary">Find Suppliers</a>
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach ($recentOrders as $order): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                                    <div>
                                        <h5>Order #<?php echo $order['id']; ?></h5>
                                        <p style="color: #666; margin: 0;">To: <?php echo htmlspecialchars($order['supplier_name']); ?></p>
                                        <div style="margin: 0 0 0.5rem 0;">
                                            <span style="font-weight: 600;">Items:</span>
                                            <ul style="margin: 0.25rem 0 0 1rem; padding: 0; list-style: disc;">
                                                <?php 
                                                $items = json_decode($order['items'], true);
                                                if (is_array($items)) {
                                                    foreach ($items as $item) {
                                                ?>
                                                    <li style="margin-bottom: 0.25rem;">
                                                        <span style="font-weight: 500; color: #222;">
                                                            <?php echo htmlspecialchars($item['product_name']); ?>
                                                        </span>
                                                        <span style="color: #555;">(<?php echo htmlspecialchars($item['quantity']); ?> <?php echo htmlspecialchars($item['unit']); ?> @ ₹<?php echo htmlspecialchars($item['unit_price']); ?>/<?php echo htmlspecialchars($item['unit']); ?>)</span>
                                                        <?php if (!empty($item['notes'])): ?>
                                                            <span style="color: #888; font-style: italic;">- <?php echo htmlspecialchars($item['notes']); ?></span>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php 
                                                    }
                                                } else {
                                                    echo '<li style="color: #888;">No items</li>';
                                                }
                                                ?>
                                            </ul>
                                        </div>
                                        <p style="color: #666; margin: 0;">Date: <?php echo date('M j, Y', strtotime($order['order_date'])); ?></p>
                                    </div>
                                    <span class="badge badge-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Ingredient Status -->
                <div class="dashboard-card">
                    <h3 class="card-title">Ingredient Status</h3>
                    <?php if (empty($ingredients)): ?>
                        <div class="empty-state">
                            <p>No ingredient data available yet.</p>
                            <p>Suppliers will update ingredient status when they deliver items to you.</p>
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach (array_slice($ingredients, 0, 5) as $ingredient): ?>
                                <div class="inventory-item">
                                    <div class="inventory-info">
                                        <h5><?php echo htmlspecialchars($ingredient['ingredient_name']); ?></h5>
                                        <p>Supplier: <?php echo htmlspecialchars($ingredient['supplier_name'] ?? 'Unknown'); ?></p>
                                        <?php if ($ingredient['last_delivered']): ?>
                                            <p>Last delivered: <?php echo date('M j, Y', strtotime($ingredient['last_delivered'])); ?></p>
                                        <?php endif; ?>
                                        <?php if ($ingredient['next_delivery_date']): ?>
                                            <p>Next delivery: <?php echo date('M j, Y', strtotime($ingredient['next_delivery_date'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="stock-status <?php echo $ingredient['status'] === 'low_stock' || $ingredient['status'] === 'out_of_stock' ? 'stock-low' : 'stock-good'; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $ingredient['status'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <a href="ingredient-status.php" class="btn btn-secondary">View All Ingredients</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
