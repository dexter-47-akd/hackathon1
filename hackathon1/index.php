<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

// Fetch all vendors for display
$stmt = $pdo->query("
    SELECT v.*, u.email 
    FROM vendors v 
    JOIN users u ON v.user_id = u.id 
    WHERE v.shop_status = 'open'
    ORDER BY v.created_at DESC
");
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FreshStalls - Premium Street Food Platform</title>
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
                <a href="#about" class="nav-link">About</a>
                <div class="dropdown">
                    <a href="#" class="nav-link">For Business</a>
                    <div class="dropdown-content">
                        <a href="vendor-auth.php">Become a Vendor</a>
                        <a href="supplier-auth.php">Become a Supplier</a>
                    </div>
                </div>
                <?php if (isLoggedIn()): ?>
                    <a href="dashboard.php" class="nav-link">Dashboard</a>
                    <a href="logout.php" class="nav-link">Logout</a>
                <?php else: ?>
                    <a href="consumer-auth.php" class="nav-link">Sign In</a>
                <?php endif; ?>
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

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h1>Discover Authentic Street Food<br>with Complete Transparency</h1>
                <p>Connect with premium food vendors who prioritize quality ingredients and transparent sourcing. Experience the finest street food with complete ingredient traceability.</p>
                
                <div class="search-container">
                    <input type="text" class="search-input" placeholder="Search by location, cuisine, or vendor name..." id="searchInput">
                    <button class="search-btn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="m21 21-4.35-4.35"/>
                        </svg>
                    </button>
                </div>
                
                <div class="food-categories">
                    <a href="#" class="category-btn active" data-category="indian">Indian Cuisine</a>
                    <a href="#" class="category-btn" data-category="mexican">Mexican Street Food</a>
                    <a href="#" class="category-btn" data-category="asian">Asian Delicacies</a>
                    <a href="#" class="category-btn" data-category="bbq">BBQ & Grills</a>
                    <a href="#" class="category-btn" data-category="desserts">Sweet Treats</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Vendors Section -->
    <section class="stalls-section">
        <div class="container">
            <div style="text-align: center; margin-bottom: 3rem;">
                <h2 style="font-size: 2.5rem; font-weight: 700; color: var(--gray-900); margin-bottom: 1rem;">Featured Vendors</h2>
                <p style="font-size: 1.2rem; color: var(--gray-600); max-width: 600px; margin: 0 auto;">Discover exceptional street food from our carefully curated network of premium vendors</p>
            </div>

            <?php if (empty($vendors)): ?>
                <div class="empty-state">
                    <h3>No vendors available at the moment</h3>
                    <p>We're working hard to bring you the best street food vendors. Check back soon!</p>
                    <a href="vendor-auth.php" class="btn btn-primary">Become a Vendor</a>
                </div>
            <?php else: ?>
                <div class="stalls-grid">
                    <?php foreach ($vendors as $vendor): ?>
                        <div class="stall-card" onclick="viewStallDetails(<?php echo $vendor['id']; ?>)">
                            <div class="stall-card-header">
                                <div>
                                    <h3><?php echo htmlspecialchars($vendor['shop_name']); ?></h3>
                                    <div class="rating">
                                        <span class="star">★</span>
                                        <span class="rating-text"><?php echo $vendor['rating']; ?></span>
                                        <span class="review-count">(<?php echo $vendor['review_count']; ?> reviews)</span>
                                        <span class="price-range"><?php echo $vendor['price_range']; ?></span>
                                    </div>
                                </div>
                                <span class="badge badge-<?php echo $vendor['shop_status']; ?>">
                                    <?php echo $vendor['shop_status'] === 'open' ? 'Open Now' : 'Closed'; ?>
                                </span>
                            </div>
                            <div class="stall-card-info">
                                <p><strong>Cuisine:</strong> <?php echo htmlspecialchars($vendor['category']); ?></p>
                                <p><strong>Location:</strong> <?php echo htmlspecialchars($vendor['location']); ?></p>
                                <p style="margin-top: 1rem; line-height: 1.6;"><?php echo htmlspecialchars(substr($vendor['description'], 0, 120)) . '...'; ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Features Section -->
    <section style="padding: 4rem 0; background: white;">
        <div class="container">
            <div style="text-align: center; margin-bottom: 4rem;">
                <h2 style="font-size: 2.5rem; font-weight: 700; color: var(--gray-900); margin-bottom: 1rem;">Why Choose FreshStalls?</h2>
                <p style="font-size: 1.2rem; color: var(--gray-600); max-width: 700px; margin: 0 auto;">We're revolutionizing street food by connecting you with vendors who prioritize quality, transparency, and exceptional taste</p>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 3rem;">
                <div style="text-align: center; padding: 2rem;">
                    <div style="width: 100px; height: 100px; background: linear-gradient(135deg, var(--primary-color), var(--primary-light)); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 2rem; box-shadow: var(--shadow-lg);">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                            <polyline points="20,6 9,17 4,12"/>
                        </svg>
                    </div>
                    <h3 style="margin-bottom: 1rem; color: var(--gray-900); font-size: 1.4rem;">Verified Suppliers</h3>
                    <p style="color: var(--gray-600); line-height: 1.6;">All ingredients sourced from thoroughly vetted, premium suppliers with complete documentation and quality assurance</p>
                </div>
                <div style="text-align: center; padding: 2rem;">
                    <div style="width: 100px; height: 100px; background: linear-gradient(135deg, var(--success-color), #34d399); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 2rem; box-shadow: var(--shadow-lg);">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </div>
                    <h3 style="margin-bottom: 1rem; color: var(--gray-900); font-size: 1.4rem;">Complete Transparency</h3>
                    <p style="color: var(--gray-600); line-height: 1.6;">Track every ingredient from source to plate with real-time delivery updates and comprehensive supplier information</p>
                </div>
                <div style="text-align: center; padding: 2rem;">
                    <div style="width: 100px; height: 100px; background: linear-gradient(135deg, var(--accent-color), #fbbf24); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 2rem; box-shadow: var(--shadow-lg);">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                    </div>
                    <h3 style="margin-bottom: 1rem; color: var(--gray-900); font-size: 1.4rem;">Premium Quality</h3>
                    <p style="color: var(--gray-600); line-height: 1.6;">Experience exceptional street food crafted with the finest ingredients and traditional recipes passed down through generations</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section style="padding: 4rem 0; background: linear-gradient(135deg, var(--primary-color), var(--primary-light)); color: white;">
        <div class="container">
            <div style="text-align: center; max-width: 800px; margin: 0 auto;">
                <h2 style="font-size: 2.5rem; font-weight: 700; margin-bottom: 1rem; color: white;">Ready to Join Our Community?</h2>
                <p style="font-size: 1.2rem; margin-bottom: 2rem; opacity: 0.9;">Whether you're a food lover, vendor, or supplier, there's a place for you in the FreshStalls ecosystem</p>
                <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                    <a href="vendor-auth.php" class="btn btn-outline" style="background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.3); color: white;">Become a Vendor</a>
                    <a href="supplier-auth.php" class="btn btn-outline" style="background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.3); color: white;">Become a Supplier</a>
                    <a href="consumer-auth.php" class="btn" style="background: white; color: var(--primary-color);">Start Exploring</a>
                </div>
            </div>
        </div>
    </section>

    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
