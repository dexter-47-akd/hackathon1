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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_item'])) {
        $itemName = trim($_POST['item_name']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $availability = isset($_POST['availability']) ? 1 : 0;
        
        if (!empty($itemName) && $price > 0) {
            $stmt = $pdo->prepare("INSERT INTO menu_items (vendor_id, item_name, description, price, availability) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$vendor['id'], $itemName, $description, $price, $availability]);
            $successMessage = "Menu item added successfully!";
        } else {
            $errorMessage = "Please fill all required fields correctly.";
        }
    }
    
    if (isset($_POST['update_item'])) {
        $itemId = intval($_POST['item_id']);
        $itemName = trim($_POST['item_name']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $availability = isset($_POST['availability']) ? 1 : 0;
        
        if (!empty($itemName) && $price > 0) {
            $stmt = $pdo->prepare("UPDATE menu_items SET item_name = ?, description = ?, price = ?, availability = ? WHERE id = ? AND vendor_id = ?");
            $stmt->execute([$itemName, $description, $price, $availability, $itemId, $vendor['id']]);
            $successMessage = "Menu item updated successfully!";
        } else {
            $errorMessage = "Please fill all required fields correctly.";
        }
    }
    
    if (isset($_POST['delete_item'])) {
        $itemId = intval($_POST['item_id']);
        $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$itemId, $vendor['id']]);
        $successMessage = "Menu item deleted successfully!";
    }
}

// Get all menu items for this vendor
$stmt = $pdo->prepare("SELECT * FROM menu_items WHERE vendor_id = ? ORDER BY item_name");
$stmt->execute([$vendor['id']]);
$menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Management - FreshStalls</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .menu-form {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
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
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #007bff;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        .menu-items {
            display: grid;
            gap: 1rem;
        }
        .menu-item {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
        .menu-item.unavailable {
            opacity: 0.6;
            border-left-color: #6c757d;
        }
        .menu-item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        .menu-item-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
        }
        .menu-item-price {
            font-size: 1.1rem;
            font-weight: 600;
            color: #28a745;
        }
        .menu-item-description {
            color: #666;
            margin-bottom: 1rem;
        }
        .menu-item-actions {
            display: flex;
            gap: 0.5rem;
        }
        .btn-edit, .btn-delete {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .btn-edit {
            background: #007bff;
            color: white;
        }
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-available {
            background: #d4edda;
            color: #155724;
        }
        .status-unavailable {
            background: #f8d7da;
            color: #721c24;
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
                        <span style="font-weight: 600; color: #333;">
                            <?php echo ucfirst($vendor['shop_status']); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <a href="vendor-dashboard.php" class="nav-item">
                    <i>📊</i> Dashboard
                </a>
                <a href="suppliers.php" class="nav-item">
                    <i>🏭</i> Suppliers
                </a>
                <a href="ingredient-status.php" class="nav-item">
                    <i>📋</i> Ingredient Status
                </a>
                <a href="menu-management.php" class="nav-item active">
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
                <h1>Menu Management</h1>
                <p>Add, edit, and manage your menu items. Customers will see these items when they visit your stall.</p>
            </div>

            <?php if (isset($successMessage)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>

            <?php if (isset($errorMessage)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>

            <!-- Add New Menu Item Form -->
            <div class="menu-form">
                <h3>Add New Menu Item</h3>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="item_name">Item Name *</label>
                            <input type="text" id="item_name" name="item_name" required placeholder="e.g., Butter Chicken">
                        </div>
                        <div class="form-group">
                            <label for="price">Price (₹) *</label>
                            <input type="number" id="price" name="price" step="0.01" min="0" required placeholder="150.00">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3" placeholder="Describe your dish, ingredients, or special features..."></textarea>
                    </div>
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="availability" name="availability" checked>
                            <label for="availability">Available for customers</label>
                        </div>
                    </div>
                    <button type="submit" name="add_item" class="btn btn-primary">Add Menu Item</button>
                </form>
            </div>

            <!-- Menu Items List -->
            <div class="dashboard-card">
                <h3 class="card-title">Your Menu Items (<?php echo count($menuItems); ?>)</h3>
                
                <?php if (empty($menuItems)): ?>
                    <div class="empty-state">
                        <p>No menu items yet. Add your first item above!</p>
                    </div>
                <?php else: ?>
                    <div class="menu-items">
                        <?php foreach ($menuItems as $item): ?>
                            <div class="menu-item <?php echo !$item['availability'] ? 'unavailable' : ''; ?>">
                                <div class="menu-item-header">
                                    <div>
                                        <div class="menu-item-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                        <span class="status-badge <?php echo $item['availability'] ? 'status-available' : 'status-unavailable'; ?>">
                                            <?php echo $item['availability'] ? 'Available' : 'Unavailable'; ?>
                                        </span>
                                    </div>
                                    <div class="menu-item-price">₹<?php echo number_format($item['price'], 2); ?></div>
                                </div>
                                
                                <?php if (!empty($item['description'])): ?>
                                    <div class="menu-item-description"><?php echo htmlspecialchars($item['description']); ?></div>
                                <?php endif; ?>
                                
                                <div class="menu-item-actions">
                                    <button class="btn-edit" onclick="editItem(<?php echo htmlspecialchars(json_encode($item)); ?>)">Edit</button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this menu item?')">
                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" name="delete_item" class="btn-delete">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 12px; width: 90%; max-width: 500px;">
            <h3>Edit Menu Item</h3>
            <form method="POST" id="editForm">
                <input type="hidden" name="item_id" id="edit_item_id">
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_item_name">Item Name *</label>
                        <input type="text" id="edit_item_name" name="item_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_price">Price (₹) *</label>
                        <input type="number" id="edit_price" name="price" step="0.01" min="0" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="edit_availability" name="availability">
                        <label for="edit_availability">Available for customers</label>
                    </div>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" name="update_item" class="btn btn-primary">Update Item</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editItem(item) {
            document.getElementById('edit_item_id').value = item.id;
            document.getElementById('edit_item_name').value = item.item_name;
            document.getElementById('edit_price').value = item.price;
            document.getElementById('edit_description').value = item.description;
            document.getElementById('edit_availability').checked = item.availability == 1;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
    </script>
</body>
</html> 