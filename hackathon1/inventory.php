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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $itemName = $_POST['item_name'];
            $quantity = $_POST['quantity'];
            $unit = $_POST['unit'];
            $supplierId = $_POST['supplier_id'] ?: null;
            $estimatedDays = $_POST['estimated_days_remaining'] ?: null;
            
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO inventory (vendor_id, item_name, quantity, unit, supplier_id, estimated_days_remaining, last_ordered) 
                    VALUES (?, ?, ?, ?, ?, ?, CURDATE())
                ");
                $stmt->execute([$vendor['id'], $itemName, $quantity, $unit, $supplierId, $estimatedDays]);
                $success = 'Inventory item added successfully!';
            } catch (Exception $e) {
                $error = 'Failed to add inventory item.';
            }
        } elseif ($_POST['action'] === 'update') {
            $itemId = $_POST['item_id'];
            $quantity = $_POST['quantity'];
            $estimatedDays = $_POST['estimated_days_remaining'] ?: null;
            
            try {
                $stmt = $pdo->prepare("
                    UPDATE inventory 
                    SET quantity = ?, estimated_days_remaining = ?, last_ordered = CURDATE() 
                    WHERE id = ? AND vendor_id = ?
                ");
                $stmt->execute([$quantity, $estimatedDays, $itemId, $vendor['id']]);
                $success = 'Inventory updated successfully!';
            } catch (Exception $e) {
                $error = 'Failed to update inventory.';
            }
        } elseif ($_POST['action'] === 'delete') {
            $itemId = $_POST['item_id'];
            
            try {
                $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ? AND vendor_id = ?");
                $stmt->execute([$itemId, $vendor['id']]);
                $success = 'Inventory item deleted successfully!';
            } catch (Exception $e) {
                $error = 'Failed to delete inventory item.';
            }
        }
    }
}

// Get inventory with supplier info
$stmt = $pdo->prepare("
    SELECT i.*, s.supplier_name, s.is_verified 
    FROM inventory i 
    LEFT JOIN suppliers s ON i.supplier_id = s.id 
    WHERE i.vendor_id = ? 
    ORDER BY i.estimated_days_remaining ASC, i.item_name
");
$stmt->execute([$vendor['id']]);
$inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get suppliers for dropdown
$stmt = $pdo->query("SELECT id, supplier_name FROM suppliers WHERE shop_status = 'open' ORDER BY supplier_name");
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - FreshStalls</title>
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
                        <span><?php echo ucfirst($vendor['shop_status']); ?></span>
                    </div>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <a href="vendor-dashboard.php" class="nav-item">
                    <i>📊</i> Dashboard
                </a>
                <a href="#orders" class="nav-item">
                    <i>📦</i> Orders
                </a>
                <a href="inventory.php" class="nav-item active">
                    <i>📋</i> Inventory
                </a>
                <a href="#suppliers" class="nav-item">
                    <i>🏭</i> Suppliers
                </a>
                <a href="#menu" class="nav-item">
                    <i>🍽️</i> Menu
                </a>
                <a href="#profile" class="nav-item">
                    <i>👤</i> Profile
                </a>
                <a href="logout.php" class="nav-item">
                    <i>🚪</i> Logout
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="dashboard-main">
            <div class="dashboard-header">
                <h1>Inventory Management</h1>
                <p>Track your ingredients and raw materials</p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Add Inventory Form -->
            <div class="dashboard-card">
                <h3 class="card-title">Add New Inventory Item</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <div class="form-group">
                            <label for="item_name">Item Name</label>
                            <input type="text" id="item_name" name="item_name" required>
                        </div>
                        <div class="form-group">
                            <label for="quantity">Quantity</label>
                            <input type="number" id="quantity" name="quantity" min="0" step="0.1" required>
                        </div>
                        <div class="form-group">
                            <label for="unit">Unit</label>
                            <select id="unit" name="unit" required>
                                <option value="kg">Kg</option>
                                <option value="grams">Grams</option>
                                <option value="liters">Liters</option>
                                <option value="pieces">Pieces</option>
                                <option value="packets">Packets</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="supplier_id">Supplier</label>
                            <select id="supplier_id" name="supplier_id">
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['id']; ?>">
                                        <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="estimated_days_remaining">Estimated Days Remaining</label>
                            <input type="number" id="estimated_days_remaining" name="estimated_days_remaining" min="1">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Item</button>
                </form>
            </div>

            <!-- Inventory List -->
            <div class="dashboard-card">
                <h3 class="card-title">Current Inventory</h3>
                
                <?php if (empty($inventory)): ?>
                    <div class="empty-state">
                        <h3>No inventory items yet</h3>
                        <p>Add your first inventory item using the form above.</p>
                    </div>
                <?php else: ?>
                    <div class="inventory-table">
                        <div class="table-header" style="display: grid; grid-template-columns: 2fr 1fr 1fr 2fr 1fr 1fr 2fr; gap: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 8px; font-weight: 600; margin-bottom: 1rem;">
                            <div>Item Name</div>
                            <div>Quantity</div>
                            <div>Unit</div>
                            <div>Supplier</div>
                            <div>Days Left</div>
                            <div>Status</div>
                            <div>Actions</div>
                        </div>
                        
                        <?php foreach ($inventory as $item): ?>
                            <div class="inventory-row" style="display: grid; grid-template-columns: 2fr 1fr 1fr 2fr 1fr 1fr 2fr; gap: 1rem; padding: 1rem; border: 1px solid #e9ecef; border-radius: 8px; margin-bottom: 0.5rem; align-items: center;">
                                <div>
                                    <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                    <?php if ($item['last_ordered']): ?>
                                        <br><small style="color: #666;">Last ordered: <?php echo date('M j, Y', strtotime($item['last_ordered'])); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div><?php echo $item['quantity']; ?></div>
                                <div><?php echo $item['unit']; ?></div>
                                <div>
                                    <?php if ($item['supplier_name']): ?>
                                        <?php echo htmlspecialchars($item['supplier_name']); ?>
                                        <?php if ($item['is_verified']): ?>
                                            <span class="verified-badge" style="display: block; margin-top: 2px;">✓ Verified</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #999;">No supplier</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if ($item['estimated_days_remaining']): ?>
                                        <?php echo $item['estimated_days_remaining']; ?> days
                                    <?php else: ?>
                                        <span style="color: #999;">Not set</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if ($item['estimated_days_remaining'] <= 3): ?>
                                        <span class="stock-status stock-low">Low Stock</span>
                                    <?php elseif ($item['estimated_days_remaining'] <= 7): ?>
                                        <span class="stock-status" style="background: #fff3cd; color: #856404;">Medium</span>
                                    <?php else: ?>
                                        <span class="stock-status stock-good">Good</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <button class="btn btn-secondary btn-sm" onclick="editItem(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['item_name']); ?>', <?php echo $item['quantity']; ?>, <?php echo $item['estimated_days_remaining'] ?: 0; ?>)">
                                        Edit
                                    </button>
                                    <button class="btn btn-secondary btn-sm" onclick="deleteItem(<?php echo $item['id']; ?>)" style="background: #dc3545; margin-left: 0.5rem;">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>Edit Inventory Item</h3>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="item_id" id="edit_item_id">
                
                <div class="form-group">
                    <label for="edit_item_name">Item Name</label>
                    <input type="text" id="edit_item_name" readonly style="background: #f8f9fa;">
                </div>
                
                <div class="form-group">
                    <label for="edit_quantity">Quantity</label>
                    <input type="number" id="edit_quantity" name="quantity" min="0" step="0.1" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_estimated_days">Estimated Days Remaining</label>
                    <input type="number" id="edit_estimated_days" name="estimated_days_remaining" min="1">
                </div>
                
                <button type="submit" class="btn btn-primary">Update Item</button>
            </form>
        </div>
    </div>

    <!-- Delete Form -->
    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="item_id" id="delete_item_id">
    </form>

    <script>
        // Modal functionality
        const modal = document.getElementById('editModal');
        const closeBtn = document.querySelector('.close');

        closeBtn.addEventListener('click', () => {
            modal.style.display = 'none';
        });

        window.addEventListener('click', (event) => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });

        function editItem(id, name, quantity, estimatedDays) {
            document.getElementById('edit_item_id').value = id;
            document.getElementById('edit_item_name').value = name;
            document.getElementById('edit_quantity').value = quantity;
            document.getElementById('edit_estimated_days').value = estimatedDays || '';
            modal.style.display = 'block';
        }

        function deleteItem(id) {
            if (confirm('Are you sure you want to delete this inventory item?')) {
                document.getElementById('delete_item_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>
