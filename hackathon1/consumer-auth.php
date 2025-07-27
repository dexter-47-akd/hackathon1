<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'login') {
            $email = $_POST['email'];
            $password = $_POST['password'];
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND user_type = 'consumer'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_type'] = $user['user_type'];
                header('Location: index.php');
                exit;
            } else {
                $error = 'Invalid credentials';
            }
        } elseif ($_POST['action'] === 'register') {
            $email = $_POST['email'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $name = $_POST['name'];
            
            try {
                $stmt = $pdo->prepare("INSERT INTO users (email, password, user_type) VALUES (?, ?, 'consumer')");
                $stmt->execute([$email, $password]);
                $success = 'Registration successful! You can now login.';
            } catch (Exception $e) {
                $error = 'Registration failed. Email might already exist.';
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
    <title>Consumer Sign In - Street Food Connect</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-form">
            <h2>🍽️ Consumer Portal</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="auth-tabs">
                <button class="tab-btn active" onclick="showTab('login')">Sign In</button>
                <button class="tab-btn" onclick="showTab('register')">Sign Up</button>
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
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Sign In</button>
            </form>
            
            <!-- Registration Form -->
            <form id="registerForm" method="POST" class="tab-content">
                <input type="hidden" name="action" value="register">
                
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="reg_email">Email</label>
                    <input type="email" id="reg_email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="reg_password">Password</label>
                    <input type="password" id="reg_password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Sign Up</button>
            </form>
            
            <div class="auth-links">
                <a href="index.php">← Back to Home</a>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + 'Form').classList.add('active');
            event.target.classList.add('active');
        }
    </script>
    
    <style>
        .auth-tabs {
            display: flex;
            margin-bottom: 2rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .tab-btn {
            flex: 1;
            padding: 1rem;
            border: none;
            background: none;
            cursor: pointer;
            font-weight: 600;
            color: #666;
            transition: all 0.3s ease;
        }
        
        .tab-btn.active {
            color: #667eea;
            border-bottom: 2px solid #667eea;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
    </style>
</body>
</html>
