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

// Get ingredient sourcing status (read-only for vendors)
$stmt = $pdo->prepare("
    SELECT ing.*, s.supplier_name, s.is_verified 
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
    <title>Ingredient Status - FreshStalls</title>
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
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <a href="vendor-dashboard.php" class="nav-item">
                    <i>📊</i> Dashboard
                </a>
                <a href="suppliers.php" class="nav-item">
                    <i>🏭</i> Suppliers
                </a>
                <a href="ingredient-status.php" class="nav-item active">
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
                <h1>Ingredient Status</h1>
                <p>View your ingredient inventory status (updated by suppliers)</p>
            </div>

            <!-- Ingredient Status -->
            <div class="dashboard-card">
                <h3 class="card-title">Current Ingredient Status</h3>
                
                <?php if (empty($ingredients)): ?>
                    <div class="empty-state">
                        <h3>No ingredient data available</h3>
                        <p>Suppliers will update ingredient status when they deliver items to you.</p>
                        <p>Start by placing orders with suppliers to track your ingredients.</p>
                        <a href="suppliers.php" class="btn btn-primary">Find Suppliers</a>
                    </div>
                <?php else: ?>
                    <div class="ingredient-table">
                        <div class="table-header" style="display: grid; grid-template-columns: 2fr 2fr 1fr 1fr 1fr 1fr; gap: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 8px; font-weight: 600; margin-bottom: 1rem;">
                            <div>Ingredient Name</div>
                            <div>Supplier</div>
                            <div>Status</div>
                            <div>Last Delivered</div>
                            <div>Next Delivery</div>
                            <div>Verified</div>
                        </div>
                        
                        <?php foreach ($ingredients as $ingredient): ?>
                            <div class="ingredient-row" style="display: grid; grid-template-columns: 2fr 2fr 1fr 1fr 1fr 1fr; gap: 1rem; padding: 1rem; border: 1px solid #e9ecef; border-radius: 8px; margin-bottom: 0.5rem; align-items: center;">
                                <div>
                                    <strong><?php echo htmlspecialchars($ingredient['ingredient_name']); ?></strong>
                                    <?php if ($ingredient['description']): ?>
                                        <br><small style="color: #666;"><?php echo htmlspecialchars($ingredient['description']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if ($ingredient['supplier_name']): ?>
                                        <?php echo htmlspecialchars($ingredient['supplier_name']); ?>
                                    <?php else: ?>
                                        <span style="color: #999;">No supplier assigned</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php
                                    $statusClass = '';
                                    $statusText = ucfirst(str_replace('_', ' ', $ingredient['status']));
                                    
                                    switch($ingredient['status']) {
                                        case 'out_of_stock':
                                            $statusClass = 'stock-status' . ' ' . 'stock-low';
                                            break;
                                        case 'low_stock':
                                            $statusClass = 'stock-status' . ' ' . 'stock-low';
                                            break;
                                        case 'in_stock':
                                            $statusClass = 'stock-status' . ' ' . 'stock-good';
                                            break;
                                        default:
                                            $statusClass = 'stock-status';
                                    }
                                    ?>
                                    <span class="<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                </div>
                                <div>
                                    <?php if ($ingredient['last_delivered']): ?>
                                        <?php echo date('M j, Y', strtotime($ingredient['last_delivered'])); ?>
                                    <?php else: ?>
                                        <span style="color: #999;">Not delivered</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if ($ingredient['next_delivery_date']): ?>
                                        <?php echo date('M j, Y', strtotime($ingredient['next_delivery_date'])); ?>
                                    <?php else: ?>
                                        <span style="color: #999;">Not scheduled</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if ($ingredient['is_verified']): ?>
                                        <span class="verified-badge">✓ Verified</span>
                                    <?php else: ?>
                                        <span style="color: #999;">Not verified</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div style="margin-top: 2rem; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                        <h4>Note:</h4>
                        <p style="margin: 0; color: #666;">This information is updated by your suppliers when they deliver ingredients. You cannot edit this data directly. Contact your suppliers for any updates or corrections.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
