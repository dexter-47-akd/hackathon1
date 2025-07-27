-- Updated Database setup for Street Food Vendor Platform (FreshStalls Clone)
CREATE DATABASE IF NOT EXISTS street_food_platform;
USE street_food_platform;

-- Users table for authentication
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    user_type ENUM('consumer', 'vendor', 'supplier') NOT NULL,
    name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Vendors table
CREATE TABLE vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    shop_name VARCHAR(255) NOT NULL,
    gst_number VARCHAR(15) NOT NULL,
    location VARCHAR(500) NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT,
    contact_number VARCHAR(15),
    shop_status ENUM('open', 'closed') DEFAULT 'closed',
    shop_image VARCHAR(255),
    rating DECIMAL(2,1) DEFAULT 0.0,
    review_count INT DEFAULT 0,
    price_range ENUM('$', '$$', '$$$') DEFAULT '$$',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Shop hours table
CREATE TABLE shop_hours (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
    open_time TIME,
    close_time TIME,
    is_closed BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
);

-- Suppliers table
CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    supplier_name VARCHAR(255) NOT NULL,
    owner_name VARCHAR(255) NOT NULL,
    gst_number VARCHAR(15) NOT NULL,
    location VARCHAR(500) NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    category VARCHAR(100) NOT NULL,
    specialty TEXT,
    contact_number VARCHAR(15),
    shop_status ENUM('open', 'closed') DEFAULT 'open',
    minimum_order_quantity INT DEFAULT 1,
    is_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Menu items for vendors
CREATE TABLE menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT,
    item_name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    availability BOOLEAN DEFAULT TRUE,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
);

-- Ingredient sourcing table (read-only for vendors, updated by suppliers)
CREATE TABLE ingredient_sourcing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT,
    supplier_id INT,
    ingredient_name VARCHAR(255) NOT NULL,
    category VARCHAR(100),
    description TEXT,
    last_delivered DATE,
    next_delivery_date DATE,
    is_verified BOOLEAN DEFAULT FALSE,
    status ENUM('in_stock', 'low_stock', 'out_of_stock') DEFAULT 'in_stock',
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
);

-- Vendor orders to suppliers
CREATE TABLE vendor_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT,
    supplier_id INT,
    items TEXT NOT NULL,
    message TEXT,
    total_amount DECIMAL(10, 2),
    status ENUM('pending', 'accepted', 'rejected', 'delivered') DEFAULT 'pending',
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    response_date TIMESTAMP NULL,
    delivery_date DATE NULL,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
);

-- Customer reviews
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT,
    customer_name VARCHAR(255),
    rating INT CHECK (rating >= 1 AND rating <= 5),
    review_text TEXT,
    review_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
);

-- Insert sample users
INSERT INTO users (email, password, user_type, name) VALUES
('demo@vendor.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'vendor', 'Demo Vendor'),
('demo@supplier.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'supplier', 'Demo Supplier'),
('demo@consumer.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'consumer', 'Demo Consumer');
