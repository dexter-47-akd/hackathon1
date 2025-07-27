-- Product Catalog Setup for Structured Ordering System

-- Products table for supplier catalog
CREATE TABLE supplier_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    sku VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    unit VARCHAR(50) NOT NULL, -- kg, pieces, liters, etc.
    price_per_unit DECIMAL(10, 2) NOT NULL,
    min_order_quantity DECIMAL(10,2) DEFAULT 1,
    max_order_quantity DECIMAL(10,2) DEFAULT 1000,
    is_available BOOLEAN DEFAULT TRUE,
    image VARCHAR(255),
    category VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
);

-- Order templates for vendors
CREATE TABLE order_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    template_name VARCHAR(255) NOT NULL,
    description TEXT,
    is_favorite BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
);

-- Template items
CREATE TABLE template_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL,
    notes TEXT,
    FOREIGN KEY (template_id) REFERENCES order_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES supplier_products(id) ON DELETE CASCADE
);

-- Vendor favorites (quick reorder items)
CREATE TABLE vendor_favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    product_id INT NOT NULL,
    preferred_quantity DECIMAL(10, 2) DEFAULT 1,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES supplier_products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_vendor_product (vendor_id, product_id)
);

-- Update vendor_orders table to support structured orders
ALTER TABLE vendor_orders ADD COLUMN order_type ENUM('structured', 'text') DEFAULT 'text';
ALTER TABLE vendor_orders ADD COLUMN template_id INT NULL;
ALTER TABLE vendor_orders ADD FOREIGN KEY (template_id) REFERENCES order_templates(id) ON DELETE SET NULL;

-- Create order_items table for structured orders
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    notes TEXT,
    FOREIGN KEY (order_id) REFERENCES vendor_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES supplier_products(id) ON DELETE CASCADE
);

-- Insert sample products for demo suppliers
INSERT INTO supplier_products (supplier_id, product_name, sku, description, unit, price_per_unit, min_order_quantity, category) VALUES
-- Supplier 1 (Demo Supplier)
(1, 'Toor Dal', 'TD001', 'Premium quality toor dal for authentic Indian dishes', 'kg', 120.00, 1, 'Pulses'),
(1, 'Moong Dal', 'MD001', 'Organic moong dal for healthy cooking', 'kg', 140.00, 1, 'Pulses'),
(1, 'Urad Dal', 'UD001', 'Black urad dal for idli and dosa', 'kg', 130.00, 1, 'Pulses'),
(1, 'Chana Dal', 'CD001', 'Split chickpeas for traditional recipes', 'kg', 110.00, 1, 'Pulses'),
(1, 'Basmati Rice', 'BR001', 'Premium long grain basmati rice', 'kg', 80.00, 5, 'Grains'),
(1, 'Sona Masoori Rice', 'SMR001', 'Medium grain rice for daily cooking', 'kg', 65.00, 5, 'Grains'),
(1, 'Wheat Flour', 'WF001', 'Fine wheat flour for rotis and breads', 'kg', 45.00, 5, 'Grains'),
(1, 'Sugar', 'SG001', 'Refined white sugar', 'kg', 42.00, 1, 'Essentials'),
(1, 'Salt', 'SL001', 'Iodized table salt', 'kg', 18.00, 1, 'Essentials'),
(1, 'Cooking Oil', 'CO001', 'Pure vegetable cooking oil', 'liter', 120.00, 1, 'Essentials'),
(1, 'Ghee', 'GH001', 'Pure cow ghee for authentic taste', 'kg', 450.00, 0.5, 'Essentials'),
(1, 'Onions', 'ON001', 'Fresh red onions', 'kg', 25.00, 2, 'Vegetables'),
(1, 'Tomatoes', 'TM001', 'Fresh red tomatoes', 'kg', 30.00, 2, 'Vegetables'),
(1, 'Potatoes', 'PT001', 'Fresh potatoes', 'kg', 20.00, 2, 'Vegetables'),
(1, 'Ginger', 'GN001', 'Fresh ginger root', 'kg', 80.00, 0.5, 'Vegetables'),
(1, 'Garlic', 'GL001', 'Fresh garlic bulbs', 'kg', 60.00, 0.5, 'Vegetables'),
(1, 'Turmeric Powder', 'TP001', 'Pure turmeric powder', 'kg', 200.00, 0.25, 'Spices'),
(1, 'Red Chili Powder', 'RCP001', 'Hot red chili powder', 'kg', 180.00, 0.25, 'Spices'),
(1, 'Coriander Powder', 'CP001', 'Ground coriander powder', 'kg', 160.00, 0.25, 'Spices'),
(1, 'Cumin Seeds', 'CS001', 'Whole cumin seeds', 'kg', 220.00, 0.25, 'Spices'),
(1, 'Mustard Seeds', 'MS001', 'Black mustard seeds', 'kg', 180.00, 0.25, 'Spices'),
(1, 'Cardamom', 'CD001', 'Green cardamom pods', 'kg', 1200.00, 0.1, 'Spices'),
(1, 'Cinnamon', 'CN001', 'Cinnamon sticks', 'kg', 800.00, 0.1, 'Spices'),
(1, 'Bay Leaves', 'BL001', 'Dried bay leaves', 'kg', 400.00, 0.1, 'Spices'),
(1, 'Black Pepper', 'BP001', 'Whole black peppercorns', 'kg', 600.00, 0.25, 'Spices'),
(1, 'Green Chilies', 'GC001', 'Fresh green chilies', 'kg', 40.00, 0.5, 'Vegetables'),
(1, 'Curry Leaves', 'CL001', 'Fresh curry leaves', 'bunch', 15.00, 1, 'Vegetables'),
(1, 'Mint Leaves', 'ML001', 'Fresh mint leaves', 'bunch', 20.00, 1, 'Vegetables'),
(1, 'Coriander Leaves', 'CL002', 'Fresh coriander leaves', 'bunch', 15.00, 1, 'Vegetables');

-- Insert sample templates for demo vendor
INSERT INTO order_templates (vendor_id, template_name, description, is_favorite) VALUES
(1, 'Weekly Staples', 'Essential items for weekly cooking', TRUE),
(1, 'Festival Stock', 'Special items for festival cooking', FALSE),
(1, 'Bulk Order', 'Large quantity order for events', FALSE);

-- Insert sample template items
INSERT INTO template_items (template_id, product_id, quantity) VALUES
-- Weekly Staples template
(1, 1, 2), -- 2kg Toor Dal
(1, 5, 10), -- 10kg Basmati Rice
(1, 7, 5), -- 5kg Wheat Flour
(1, 8, 2), -- 2kg Sugar
(1, 9, 1), -- 1kg Salt
(1, 10, 2), -- 2 liters Cooking Oil
(1, 12, 5), -- 5kg Onions
(1, 13, 3), -- 3kg Tomatoes
(1, 14, 5), -- 5kg Potatoes
(1, 15, 0.5), -- 0.5kg Ginger
(1, 16, 0.5), -- 0.5kg Garlic
(1, 17, 0.25), -- 0.25kg Turmeric Powder
(1, 18, 0.25), -- 0.25kg Red Chili Powder
(1, 19, 0.25), -- 0.25kg Coriander Powder

-- Festival Stock template
(2, 1, 5), -- 5kg Toor Dal
(2, 2, 3), -- 3kg Moong Dal
(2, 5, 20), -- 20kg Basmati Rice
(2, 11, 2), -- 2kg Ghee
(2, 20, 0.5), -- 0.5kg Cumin Seeds
(2, 21, 0.5), -- 0.5kg Mustard Seeds
(2, 22, 0.1), -- 0.1kg Cardamom
(2, 23, 0.1), -- 0.1kg Cinnamon
(2, 24, 0.2), -- 0.2kg Bay Leaves
(2, 25, 0.5), -- 0.5kg Black Pepper

-- Bulk Order template
(3, 1, 10), -- 10kg Toor Dal
(3, 5, 50), -- 50kg Basmati Rice
(3, 7, 25), -- 25kg Wheat Flour
(3, 10, 10), -- 10 liters Cooking Oil
(3, 12, 20), -- 20kg Onions
(3, 13, 15), -- 15kg Tomatoes
(3, 14, 25), -- 25kg Potatoes
(3, 17, 1), -- 1kg Turmeric Powder
(3, 18, 1), -- 1kg Red Chili Powder
(3, 19, 1); -- 1kg Coriander Powder

-- Insert sample favorites for demo vendor
INSERT INTO vendor_favorites (vendor_id, product_id, preferred_quantity) VALUES
(1, 1, 2), -- Toor Dal
(1, 5, 10), -- Basmati Rice
(1, 7, 5), -- Wheat Flour
(1, 12, 5), -- Onions
(1, 13, 3), -- Tomatoes
(1, 14, 5), -- Potatoes
(1, 15, 0.5), -- Ginger
(1, 16, 0.5), -- Garlic
(1, 17, 0.25), -- Turmeric Powder
(1, 18, 0.25); -- Red Chili Powder 