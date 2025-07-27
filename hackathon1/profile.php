<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

requireLogin();

if (getUserType() !== 'vendor') {
    header('Location: index.php');
    exit;
}

// Get vendor details with user info
$stmt = $pdo->prepare("
    SELECT v.*, u.name, u.email 
    FROM vendors v 
    JOIN users u ON v.user_id = u.id 
    WHERE v.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    header('Location: vendor-auth.php');
    exit;
}

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $shopName = trim($_POST['shop_name']);
        $gstNumber = trim($_POST['gst_number']);
        $location = trim($_POST['location']);
        $latitude = floatval($_POST['latitude']);
        $longitude = floatval($_POST['longitude']);
        $category = $_POST['category'];
        $description = trim($_POST['description']);
        $contactNumber = trim($_POST['contact_number']);
        
        // Validate GST number
        function validateGST($gst) {
            $pattern = '/^[0-9]{2}[A-Z0-9]{10}[0-9]{1}[A-Z]{1}[0-9]{1}$/';
            return preg_match($pattern, $gst) && strlen($gst) === 15;
        }
        
        if (!validateGST($gstNumber)) {
            $error = 'Invalid GST number format. GST should be 15 characters (e.g., 22AAAAA0000A1Z5)';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Update user info
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                $stmt->execute([$name, $email, $_SESSION['user_id']]);
                
                // Update vendor info
                $stmt = $pdo->prepare("
                    UPDATE vendors 
                    SET shop_name = ?, gst_number = ?, location = ?, latitude = ?, longitude = ?, 
                        category = ?, description = ?, contact_number = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([
                    $shopName, $gstNumber, $location, $latitude, $longitude,
                    $category, $description, $contactNumber, $_SESSION['user_id']
                ]);
                
                // Handle image upload if provided
                if (isset($_FILES['shop_image']) && $_FILES['shop_image']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = 'uploads/shops/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    $fileExtension = pathinfo($_FILES['shop_image']['name'], PATHINFO_EXTENSION);
                    $fileName = uniqid() . '.' . $fileExtension;
                    $uploadPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['shop_image']['tmp_name'], $uploadPath)) {
                        // Delete old image if exists
                        if ($vendor['shop_image'] && file_exists($vendor['shop_image'])) {
                            unlink($vendor['shop_image']);
                        }
                        
                        $stmt = $pdo->prepare("UPDATE vendors SET shop_image = ? WHERE user_id = ?");
                        $stmt->execute([$uploadPath, $_SESSION['user_id']]);
                        $vendor['shop_image'] = $uploadPath;
                    }
                }
                
                $pdo->commit();
                $success = 'Profile updated successfully!';
                
                // Refresh vendor data
                $stmt = $pdo->prepare("
                    SELECT v.*, u.name, u.email 
                    FROM vendors v 
                    JOIN users u ON v.user_id = u.id 
                    WHERE v.user_id = ?
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Update failed. Please try again.';
            }
        }
    }
    
    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if (!password_verify($currentPassword, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
            $success = 'Password changed successfully!';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Management - FreshStalls</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .profile-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .profile-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f8f9fa;
        }
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            color: white;
        }
        .profile-info h2 {
            margin: 0;
            color: #333;
        }
        .profile-info p {
            margin: 0.5rem 0 0 0;
            color: #666;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
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
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none;
            border-color: #007bff;
        }
        .form-group input[readonly] {
            background-color: #f8f9fa;
            color: #666;
        }
        .image-preview {
            width: 150px;
            height: 150px;
            border-radius: 12px;
            object-fit: cover;
            border: 3px solid #e1e5e9;
        }
        .file-upload {
            border: 2px dashed #e1e5e9;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.3s;
        }
        .file-upload:hover {
            border-color: #007bff;
        }
        .file-upload input[type="file"] {
            display: none;
        }
        .upload-text {
            color: #666;
            font-size: 0.9rem;
            margin-top: 0.5rem;
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
        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        .password-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1rem;
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
                <a href="menu-management.php" class="nav-item">
                    <i>🍽️</i> Menu
                </a>
                <a href="profile.php" class="nav-item active">
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
                <h1>Profile Management</h1>
                <p>Update your personal and shop information</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- Profile Information -->
            <div class="profile-section">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($vendor['shop_name'], 0, 1)); ?>
                    </div>
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($vendor['shop_name']); ?></h2>
                        <p><?php echo htmlspecialchars($vendor['category']); ?> • <?php echo htmlspecialchars($vendor['location']); ?></p>
                        <p>Member since <?php echo date('F Y', strtotime($vendor['created_at'])); ?></p>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Full Name *</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($vendor['name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($vendor['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="shop_name">Shop Name *</label>
                            <input type="text" id="shop_name" name="shop_name" value="<?php echo htmlspecialchars($vendor['shop_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="gst_number">GST Number *</label>
                            <input type="text" id="gst_number" name="gst_number" value="<?php echo htmlspecialchars($vendor['gst_number']); ?>" maxlength="15" required>
                            <small style="color: #666;">Format: 22AAAAA0000A1Z5</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="category">Category *</label>
                            <select id="category" name="category" required>
                                <option value="Indian Street Food" <?php echo $vendor['category'] === 'Indian Street Food' ? 'selected' : ''; ?>>Indian Street Food</option>
                                <option value="Mexican Tacos" <?php echo $vendor['category'] === 'Mexican Tacos' ? 'selected' : ''; ?>>Mexican Tacos</option>
                                <option value="Asian Noodles" <?php echo $vendor['category'] === 'Asian Noodles' ? 'selected' : ''; ?>>Asian Noodles</option>
                                <option value="BBQ" <?php echo $vendor['category'] === 'BBQ' ? 'selected' : ''; ?>>BBQ</option>
                                <option value="Desserts" <?php echo $vendor['category'] === 'Desserts' ? 'selected' : ''; ?>>Desserts</option>
                                <option value="Other" <?php echo $vendor['category'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="contact_number">Contact Number *</label>
                            <input type="tel" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($vendor['contact_number']); ?>" required>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="location">Location Address *</label>
                            <textarea id="location" name="location" rows="3" required><?php echo htmlspecialchars($vendor['location']); ?></textarea>
                            <button type="button" class="btn btn-secondary" onclick="getCurrentLocation()" style="margin-top: 0.5rem;">
                                📍 Get Current Location
                            </button>
                        </div>
                        
                        <div class="form-group">
                            <label for="latitude">Latitude</label>
                            <input type="number" step="any" id="latitude" name="latitude" value="<?php echo $vendor['latitude']; ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="longitude">Longitude</label>
                            <input type="number" step="any" id="longitude" name="longitude" value="<?php echo $vendor['longitude']; ?>" readonly>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="description">Shop Description</label>
                            <textarea id="description" name="description" rows="4" placeholder="Describe your food stall and specialties..."><?php echo htmlspecialchars($vendor['description']); ?></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="shop_image">Shop Image</label>
                            <?php if ($vendor['shop_image']): ?>
                                <div style="margin-bottom: 1rem;">
                                    <img src="<?php echo htmlspecialchars($vendor['shop_image']); ?>" alt="Current shop image" class="image-preview">
                                </div>
                            <?php endif; ?>
                            <div class="file-upload" onclick="document.getElementById('shop_image').click()">
                                <input type="file" id="shop_image" name="shop_image" accept="image/*">
                                <div>📷 Click to upload new shop image</div>
                                <div class="upload-text">JPG, PNG or GIF (Max 5MB)</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                        <a href="vendor-dashboard.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>

            <!-- Change Password Section -->
            <div class="profile-section">
                <h3>Change Password</h3>
                <form method="POST">
                    <input type="hidden" name="change_password" value="1">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="current_password">Current Password *</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password *</label>
                            <input type="password" id="new_password" name="new_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // File upload preview
        document.getElementById('shop_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const fileUpload = document.querySelector('.file-upload');
                fileUpload.innerHTML = `
                    <div>✓ ${file.name}</div>
                    <div class="upload-text">Click to change image</div>
                `;
            }
        });

        // Get current location
        function getCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    document.getElementById('latitude').value = position.coords.latitude;
                    document.getElementById('longitude').value = position.coords.longitude;
                    
                    // Reverse geocoding to get address
                    fetch(`https://api.opencagedata.com/geocode/v1/json?q=${position.coords.latitude}+${position.coords.longitude}&key=YOUR_API_KEY`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.results && data.results[0]) {
                                document.getElementById('location').value = data.results[0].formatted;
                            }
                        })
                        .catch(error => {
                            console.log('Geocoding failed:', error);
                        });
                }, function(error) {
                    alert('Error getting location: ' + error.message);
                });
            } else {
                alert('Geolocation is not supported by this browser.');
            }
        }

        // GST validation
        document.getElementById('gst_number').addEventListener('input', function(e) {
            const gst = e.target.value.toUpperCase();
            e.target.value = gst;
            
            const pattern = /^[0-9]{2}[A-Z0-9]{10}[0-9]{1}[A-Z]{1}[0-9]{1}$/;
            if (gst.length === 15) {
                if (pattern.test(gst)) {
                    e.target.style.borderColor = '#28a745';
                } else {
                    e.target.style.borderColor = '#dc3545';
                }
            } else {
                e.target.style.borderColor = '#e9ecef';
            }
        });
    </script>
</body>
</html> 