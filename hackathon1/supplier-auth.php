<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

$error = '';
$success = '';

// GST validation function
function validateGST($gst) {
    $pattern = '/^[0-9]{2}[A-Z0-9]{10}[0-9]{1}[A-Z]{1}[0-9]{1}$/';
    return preg_match($pattern, $gst) && strlen($gst) === 15;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'login') {
            $email = $_POST['email'];
            $password = $_POST['password'];
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND user_type = 'supplier'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_type'] = $user['user_type'];
                header('Location: supplier-dashboard.php');
                exit;
            } else {
                $error = 'Invalid credentials';
            }
        } elseif ($_POST['action'] === 'register') {
            $email = $_POST['email'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $supplierName = $_POST['supplier_name'];
            $ownerName = $_POST['owner_name'];
            $gstNumber = $_POST['gst_number'];
            $location = $_POST['location'];
            $latitude = $_POST['latitude'];
            $longitude = $_POST['longitude'];
            $category = $_POST['category'];
            $specialty = $_POST['specialty'];
            $contactNumber = $_POST['contact_number'];
            $minimumOrderQuantity = $_POST['minimum_order_quantity'];
            
            // Validate GST number
            if (!validateGST($gstNumber)) {
                $error = 'Invalid GST number format. GST should be 15 characters (e.g., 22AAAAA0000A1Z5)';
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // Create user account
                    $stmt = $pdo->prepare("INSERT INTO users (email, password, user_type, name) VALUES (?, ?, 'supplier', ?)");
                    $stmt->execute([$email, $password, $ownerName]);
                    $userId = $pdo->lastInsertId();
                    
                    // Create supplier profile
                    $stmt = $pdo->prepare("
                        INSERT INTO suppliers (user_id, supplier_name, owner_name, gst_number, location, latitude, longitude, category, specialty, contact_number, minimum_order_quantity) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$userId, $supplierName, $ownerName, $gstNumber, $location, $latitude, $longitude, $category, $specialty, $contactNumber, $minimumOrderQuantity]);
                    
                    $pdo->commit();
                    $success = 'Registration successful! You can now login.';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Registration failed. Email might already exist.';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Portal - FreshStalls</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-form">
            <h2>🏭 Supplier Portal</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="auth-tabs">
                <button class="tab-btn active" onclick="showTab('login')">Login</button>
                <button class="tab-btn" onclick="showTab('register')">Register</button>
            </div>
            
            <!-- Login Form -->
            <form id="loginForm" method="POST" class="tab-content active">
                <input type="hidden" name="action" value="login">
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
            </form>
            
            <!-- Registration Form -->
            <form id="registerForm" method="POST" class="tab-content">
                <input type="hidden" name="action" value="register">
                
                <div class="form-group">
                    <label for="owner_name">Owner Name</label>
                    <input type="text" id="owner_name" name="owner_name" required>
                </div>
                
                <div class="form-group">
                    <label for="reg_email">Email</label>
                    <input type="email" id="reg_email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="reg_password">Password</label>
                    <input type="password" id="reg_password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="supplier_name">Supplier/Business Name</label>
                    <input type="text" id="supplier_name" name="supplier_name" required>
                </div>
                
                <div class="form-group">
                    <label for="gst_number">GST Number (15 characters)</label>
                    <input type="text" id="gst_number" name="gst_number" maxlength="15" pattern="[0-9]{2}[A-Z0-9]{10}[0-9]{1}[A-Z]{1}[0-9]{1}" placeholder="22AAAAA0000A1Z5" required>
                    <small style="color: #666;">Format: 2 digits + 10 alphanumeric + 1 digit + 1 letter + 1 digit</small>
                </div>
                
                <div class="form-group">
                    <label for="location">Location Address</label>
                    <textarea id="location" name="location" required></textarea>
                    <button type="button" class="btn btn-secondary" onclick="getCurrentLocation()" style="margin-top: 0.5rem;">
                        📍 Get Current Location
                    </button>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="latitude">Latitude</label>
                        <input type="number" step="any" id="latitude" name="latitude" readonly required>
                    </div>
                    
                    <div class="form-group">
                        <label for="longitude">Longitude</label>
                        <input type="number" step="any" id="longitude" name="longitude" readonly required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category" required>
                        <option value="">Select Category</option>
                        <option value="vegetables">Vegetables</option>
                        <option value="spices">Spices & Masalas</option>
                        <option value="grains">Grains & Pulses</option>
                        <option value="dairy">Dairy Products</option>
                        <option value="oil">Oils & Fats</option>
                        <option value="packaging">Packaging Materials</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="specialty">Specialty/Description</label>
                    <textarea id="specialty" name="specialty" placeholder="Describe your products and specialties..." required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="contact_number">Contact Number</label>
                    <input type="tel" id="contact_number" name="contact_number" required>
                </div>
                
                <div class="form-group">
                    <label for="minimum_order_quantity">Minimum Order Quantity</label>
                    <input type="number" id="minimum_order_quantity" name="minimum_order_quantity" min="1" value="1" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Register</button>
            </form>
            
            <div class="auth-links">
                <a href="index.php">← Back to Home</a>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            document.getElementById(tabName + 'Form').classList.add('active');
            event.target.classList.add('active');
        }

        // Get current location
        function getCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    document.getElementById('latitude').value = position.coords.latitude;
                    document.getElementById('longitude').value = position.coords.longitude;
                    
                    // You can add reverse geocoding here if needed
                    alert('Location captured successfully!');
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
