<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$vendorId = (int)$_GET['id'];

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $customerName = $_POST['customer_name'];
    $rating = (int)$_POST['rating'];
    $reviewText = $_POST['review_text'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO reviews (vendor_id, customer_name, rating, review_text) VALUES (?, ?, ?, ?)");
        $stmt->execute([$vendorId, $customerName, $rating, $reviewText]);
        
        // Update vendor rating
        $stmt = $pdo->prepare("
            UPDATE vendors SET 
            rating = (SELECT AVG(rating) FROM reviews WHERE vendor_id = ?),
            review_count = (SELECT COUNT(*) FROM reviews WHERE vendor_id = ?)
            WHERE id = ?
        ");
        $stmt->execute([$vendorId, $vendorId, $vendorId]);
        
        $success = "Review submitted successfully!";
    } catch (Exception $e) {
        $error = "Failed to submit review.";
    }
}

// Get vendor details with user info
$stmt = $pdo->prepare("
    SELECT v.*, u.email 
    FROM vendors v 
    JOIN users u ON v.user_id = u.id 
    WHERE v.id = ?
");
$stmt->execute([$vendorId]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    header('Location: index.php');
    exit;
}

// Get shop hours
$stmt = $pdo->prepare("SELECT * FROM shop_hours WHERE vendor_id = ? ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')");
$stmt->execute([$vendorId]);
$shopHours = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get menu items
$stmt = $pdo->prepare("SELECT * FROM menu_items WHERE vendor_id = ? AND availability = 1 ORDER BY item_name");
$stmt->execute([$vendorId]);
$menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get ingredient sourcing (including orders and deliveries)
$stmt = $pdo->prepare("
    SELECT ing.*, s.supplier_name, s.is_verified as supplier_verified,
           vo.items as order_items, vo.order_date, vo.status as order_status,
           vo.delivery_date as order_delivery_date
    FROM ingredient_sourcing ing
    LEFT JOIN suppliers s ON ing.supplier_id = s.id
    LEFT JOIN vendor_orders vo ON ing.vendor_id = vo.vendor_id 
        AND ing.supplier_id = vo.supplier_id 
        AND vo.status IN ('accepted', 'delivered')
    WHERE ing.vendor_id = ?
    ORDER BY ing.ingredient_name, vo.order_date DESC
");
$stmt->execute([$vendorId]);
$ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get reviews
$stmt = $pdo->prepare("SELECT * FROM reviews WHERE vendor_id = ? ORDER BY review_date DESC LIMIT 5");
$stmt->execute([$vendorId]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if currently open
$currentDay = date('l');
$currentTime = date('H:i:s');
$isOpen = false;

foreach ($shopHours as $hours) {
    if ($hours['day_of_week'] === $currentDay && !$hours['is_closed']) {
        if ($currentTime >= $hours['open_time'] && $currentTime <= $hours['close_time']) {
            $isOpen = true;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($vendor['shop_name']); ?> - FreshStalls</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="nav-brand">
                <div class="logo">FS</div>
                <span class="brand-name">FreshStalls</span>
            </div>
            <nav class="nav-menu">
                <a href="index.php" class="nav-link">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.5rem;">
                        <path d="m12 19-7-7 7-7"/>
                        <path d="M19 12H5"/>
                    </svg>
                    Back to Home
                </a>
            </nav>
            <div class="nav-actions">
                <a href="#" class="btn-save">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.29 1.51 4.04 3 5.5l7 7Z"/>
                    </svg>
                    Save
                </a>
                <a href="#" class="btn-share">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>
                        <polyline points="16,6 12,2 8,6"/>
                        <line x1="12" y1="2" x2="12" y2="15"/>
                    </svg>
                    Share
                </a>
            </div>
        </div>
    </header>

    <!-- Vendor Hero Section -->
    <section class="hero" style="padding: 3rem 0;">
        <div class="container">
            <div style="max-width: 800px;">
                <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                    <span class="badge" style="background: var(--success-color); color: white; display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20,6 9,17 4,12"/>
                        </svg>
                        Verified Sourcing
                    </span>
                    <span class="badge badge-<?php echo $isOpen ? 'open' : 'closed'; ?>">
                        <?php echo $isOpen ? 'Open Now' : 'Closed'; ?>
                    </span>
                </div>
                <h1 style="font-size: 3rem; font-weight: 700; color: white; margin-bottom: 1rem;"><?php echo htmlspecialchars($vendor['shop_name']); ?></h1>
                <p style="font-size: 1.3rem; color: rgba(255,255,255,0.9); margin: 0;"><?php echo htmlspecialchars($vendor['category']); ?></p>
            </div>
        </div>
    </section>

    <!-- Vendor Content -->
    <section style="padding: 3rem 0; background: var(--gray-50);">
        <div class="container">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 3rem;">
                <div>
                    <!-- Rating and Basic Info -->
                    <div style="background: white; padding: 2rem; border-radius: var(--radius-xl); box-shadow: var(--shadow-md); margin-bottom: 2rem; border: 1px solid var(--gray-200);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                            <div class="rating">
                                <span class="star">★</span>
                                <span class="rating-text" style="font-size: 1.2rem;"><?php echo $vendor['rating']; ?></span>
                                <span class="review-count">(<?php echo $vendor['review_count']; ?> reviews)</span>
                            </div>
                            <span class="price-range" style="font-size: 1.2rem;"><?php echo $vendor['price_range']; ?></span>
                        </div>

                        <p style="font-size: 1.1rem; line-height: 1.7; color: var(--gray-700); margin-bottom: 1.5rem;"><?php echo htmlspecialchars($vendor['description']); ?></p>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" stroke-width="2">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                    <circle cx="12" cy="10" r="3"/>
                                </svg>
                                <span style="color: var(--gray-700);"><?php echo htmlspecialchars($vendor['location']); ?></span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" stroke-width="2">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                </svg>
                                <span style="color: var(--gray-700);"><?php echo htmlspecialchars($vendor['contact_number']); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Ingredient Transparency Section -->
                    <?php if (!empty($ingredients)): ?>
                        <div style="background: white; padding: 2rem; border-radius: var(--radius-xl); box-shadow: var(--shadow-md); margin-bottom: 2rem; border: 1px solid var(--gray-200);">
                            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                                <div style="width: 48px; height: 48px; background: var(--success-color); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                        <polyline points="20,6 9,17 4,12"/>
                                    </svg>
                                </div>
                                <div>
                                    <h2 style="font-size: 1.75rem; font-weight: 600; color: var(--gray-900); margin-bottom: 0.5rem;">Ingredient Transparency</h2>
                                    <p style="color: var(--gray-600); margin: 0;">Complete traceability from source to plate</p>
                                </div>
                            </div>
                            
                                                         <div style="display: flex; flex-direction: column; gap: 1rem;">
                                 <?php 
                                 $processed_ingredients = [];
                                 foreach ($ingredients as $ingredient): 
                                     $ingredient_key = $ingredient['ingredient_name'] . '_' . $ingredient['supplier_id'];
                                     if (!isset($processed_ingredients[$ingredient_key])):
                                         $processed_ingredients[$ingredient_key] = true;
                                 ?>
                                     <div style="background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: var(--radius-lg); padding: 1.5rem; position: relative;">
                                         <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                                             <div style="display: flex; align-items: center; gap: 1rem;">
                                                 <div style="width: 32px; height: 32px; background: var(--success-color); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                                     <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                                         <polyline points="20,6 9,17 4,12"/>
                                                     </svg>
                                                 </div>
                                                 <h4 style="font-weight: 600; color: var(--gray-900); margin: 0;"><?php echo htmlspecialchars($ingredient['ingredient_name']); ?></h4>
                                             </div>
                                             <?php if ($ingredient['is_verified']): ?>
                                                 <span class="verified-badge">Verified Source</span>
                                             <?php endif; ?>
                                         </div>
                                         <p style="color: var(--gray-700); margin-bottom: 0.5rem;">
                                             <strong>Supplier:</strong> <?php echo htmlspecialchars($ingredient['supplier_name'] ?? 'Local Supplier'); ?>
                                             <?php if ($ingredient['supplier_verified']): ?>
                                                 <span style="color: var(--success-color); font-weight: 600;"> (Verified)</span>
                                             <?php endif; ?>
                                         </p>
                                         <?php if ($ingredient['description']): ?>
                                             <p style="color: var(--gray-600); margin-bottom: 0.5rem; font-style: italic;"><?php echo htmlspecialchars($ingredient['description']); ?></p>
                                         <?php endif; ?>
                                         
                                         <!-- Order and Delivery Information -->
                                         <div style="background: white; border-radius: var(--radius-md); padding: 1rem; margin-top: 1rem; border: 1px solid var(--gray-100);">
                                             <h5 style="font-weight: 600; color: var(--gray-800); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                                 <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                     <path d="M9 12l2 2 4-4"/>
                                                     <path d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z"/>
                                                 </svg>
                                                 Order & Delivery History
                                             </h5>
                                             
                                             <?php 
                                             // Get all orders for this ingredient
                                             $ingredient_orders = array_filter($ingredients, function($item) use ($ingredient) {
                                                 return $item['ingredient_name'] === $ingredient['ingredient_name'] && 
                                                        $item['supplier_id'] === $ingredient['supplier_id'];
                                             });
                                             
                                             foreach ($ingredient_orders as $order): 
                                                 if ($order['order_items']):
                                             ?>
                                                 <div style="border-left: 3px solid var(--primary-color); padding-left: 1rem; margin-bottom: 0.75rem;">
                                                     <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                                         <span style="font-weight: 500; color: var(--gray-800);">
                                                             Order placed: <?php echo date('F j, Y', strtotime($order['order_date'])); ?>
                                                         </span>
                                                         <span style="padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; 
                                                                      background: <?php echo $order['order_status'] === 'delivered' ? 'var(--success-color)' : 'var(--warning-color)'; ?>; 
                                                                      color: white;">
                                                             <?php echo ucfirst($order['order_status']); ?>
                                                         </span>
                                                     </div>
                                                     <p style="color: var(--gray-600); font-size: 0.9rem; margin-bottom: 0.5rem;">
                                                         <strong>Items:</strong> <?php echo htmlspecialchars($order['order_items']); ?>
                                                     </p>
                                                     <?php if ($order['order_delivery_date']): ?>
                                                         <p style="color: var(--success-color); font-size: 0.9rem; font-weight: 500; margin: 0;">
                                                             <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.25rem;">
                                                                 <path d="M9 12l2 2 4-4"/>
                                                                 <path d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z"/>
                                                             </svg>
                                                             Delivered: <?php echo date('F j, Y', strtotime($order['order_delivery_date'])); ?>
                                                         </p>
                                                     <?php endif; ?>
                                                 </div>
                                             <?php 
                                                 endif;
                                             endforeach; 
                                             ?>
                                             
                                             <?php if ($ingredient['last_delivered']): ?>
                                                 <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--gray-200);">
                                                     <p style="color: var(--gray-500); font-size: 0.9rem; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                                                         <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                             <path d="M20 6L9 17l-5-5"/>
                                                         </svg>
                                                         Last ingredient delivery: <?php echo date('F j, Y', strtotime($ingredient['last_delivered'])); ?>
                                                     </p>
                                                 </div>
                                             <?php endif; ?>
                                         </div>
                                     </div>
                                 <?php endif; endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Menu Section -->
                    <?php if (!empty($menuItems)): ?>
                        <div style="background: white; padding: 2rem; border-radius: var(--radius-xl); box-shadow: var(--shadow-md); margin-bottom: 2rem; border: 1px solid var(--gray-200);">
                            <h2 style="font-size: 1.75rem; font-weight: 600; color: var(--gray-900); margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid var(--gray-100);">Our Menu</h2>
                            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                                <?php foreach ($menuItems as $item): ?>
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 1.5rem; border-bottom: 1px solid var(--gray-200);">
                                        <div style="flex: 1;">
                                            <h4 style="font-size: 1.2rem; font-weight: 600; color: var(--gray-900); margin-bottom: 0.5rem;"><?php echo htmlspecialchars($item['item_name']); ?></h4>
                                            <p style="color: var(--gray-600); margin: 0; line-height: 1.6;"><?php echo htmlspecialchars($item['description']); ?></p>
                                        </div>
                                        <div style="color: var(--primary-color); font-weight: 700; font-size: 1.2rem; margin-left: 1rem;">₹<?php echo number_format($item['price'], 2); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Review Form -->
                    <div style="background: white; padding: 2rem; border-radius: var(--radius-xl); box-shadow: var(--shadow-md); margin-bottom: 2rem; border: 1px solid var(--gray-200);">
                        <h2 style="font-size: 1.75rem; font-weight: 600; color: var(--gray-900); margin-bottom: 1.5rem;">Share Your Experience</h2>
                        <button class="btn btn-primary" onclick="showReviewForm()" style="margin-bottom: 1rem;">Write a Review</button>
                        
                        <div id="reviewForm" style="display: none; padding: 1.5rem; background: var(--gray-50); border-radius: var(--radius-lg); border: 1px solid var(--gray-200);">
                            <form method="POST">
                                <div class="form-group">
                                    <label for="customer_name">Your Name</label>
                                    <input type="text" id="customer_name" name="customer_name" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="rating">Rating</label>
                                    <select id="rating" name="rating" required>
                                        <option value="">Select Rating</option>
                                        <option value="5">★★★★★ Excellent</option>
                                        <option value="4">★★★★☆ Very Good</option>
                                        <option value="3">★★★☆☆ Good</option>
                                        <option value="2">★★☆☆☆ Fair</option>
                                        <option value="1">★☆☆☆☆ Poor</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="review_text">Your Review</label>
                                    <textarea id="review_text" name="review_text" rows="4" placeholder="Share your experience with this vendor..." required></textarea>
                                </div>
                                
                                <div style="display: flex; gap: 1rem;">
                                    <button type="submit" name="submit_review" class="btn btn-primary">Submit Review</button>
                                    <button type="button" class="btn btn-secondary" onclick="hideReviewForm()">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Customer Reviews -->
                    <?php if (!empty($reviews)): ?>
                        <div style="background: white; padding: 2rem; border-radius: var(--radius-xl); box-shadow: var(--shadow-md); border: 1px solid var(--gray-200);">
                            <h2 style="font-size: 1.75rem; font-weight: 600; color: var(--gray-900); margin-bottom: 1.5rem;">Customer Reviews</h2>
                            <?php foreach ($reviews as $review): ?>
                                <div style="background: var(--gray-50); border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 1rem; border: 1px solid var(--gray-200);">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                        <div style="display: flex; align-items: center; gap: 1rem;">
                                            <div style="width: 48px; height: 48px; background: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 1.2rem;">
                                                <?php echo strtoupper(substr($review['customer_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div style="font-weight: 600; color: var(--gray-900);"><?php echo htmlspecialchars($review['customer_name']); ?></div>
                                                <div style="color: var(--gray-500); font-size: 0.9rem;"><?php echo date('F j, Y', strtotime($review['review_date'])); ?></div>
                                            </div>
                                        </div>
                                        <div style="display: flex; gap: 2px;">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <span style="color: <?php echo $i <= $review['rating'] ? 'var(--accent-color)' : 'var(--gray-300)'; ?>; font-size: 1.2rem;">★</span>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <p style="color: var(--gray-700); line-height: 1.6; margin: 0;"><?php echo htmlspecialchars($review['review_text']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div>
                    <!-- Hours -->
                    <?php if (!empty($shopHours)): ?>
                        <div style="background: white; padding: 1.5rem; border-radius: var(--radius-xl); box-shadow: var(--shadow-md); margin-bottom: 1.5rem; border: 1px solid var(--gray-200);">
                            <h3 style="display: flex; align-items: center; gap: 0.75rem; font-size: 1.2rem; font-weight: 600; color: var(--gray-900); margin-bottom: 1rem;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12,6 12,12 16,14"/>
                                </svg>
                                Opening Hours
                            </h3>
                            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                <?php foreach ($shopHours as $hours): ?>
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0;">
                                        <span style="font-weight: 500; color: var(--gray-800);"><?php echo $hours['day_of_week']; ?></span>
                                        <span style="color: var(--gray-600); font-size: 0.9rem;">
                                            <?php if ($hours['is_closed']): ?>
                                                Closed
                                            <?php else: ?>
                                                <?php echo date('g:i A', strtotime($hours['open_time'])); ?> - <?php echo date('g:i A', strtotime($hours['close_time'])); ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if ($isOpen): ?>
                                <div style="margin-top: 1rem; padding: 0.75rem; background: var(--success-color); color: white; text-align: center; border-radius: var(--radius-md); font-weight: 600;">Open Now</div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Location -->
                    <div style="background: white; padding: 1.5rem; border-radius: var(--radius-xl); box-shadow: var(--shadow-md); border: 1px solid var(--gray-200);">
                        <h3 style="display: flex; align-items: center; gap: 0.75rem; font-size: 1.2rem; font-weight: 600; color: var(--gray-900); margin-bottom: 1rem;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                            Location
                        </h3>
                        <p style="color: var(--gray-700); margin-bottom: 1rem; line-height: 1.5;"><?php echo htmlspecialchars($vendor['location']); ?></p>
                        <div id="map" style="height: 250px; border-radius: var(--radius-md); border: 1px solid var(--gray-200);"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script>
        // Initialize map
        const map = L.map('map').setView([<?php echo $vendor['latitude']; ?>, <?php echo $vendor['longitude']; ?>], 15);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        
        L.marker([<?php echo $vendor['latitude']; ?>, <?php echo $vendor['longitude']; ?>])
            .addTo(map)
            .bindPopup('<?php echo htmlspecialchars($vendor['shop_name']); ?>')
            .openPopup();

        function showReviewForm() {
            document.getElementById('reviewForm').style.display = 'block';
        }

        function hideReviewForm() {
            document.getElementById('reviewForm').style.display = 'none';
        }
    </script>
</body>
</html>
