-- Create database
CREATE DATABASE IF NOT EXISTS msika_premium;
USE msika_premium;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'seller', 'user') DEFAULT 'user',
    phone VARCHAR(20),
    address TEXT,
    profile_image VARCHAR(255),
    status ENUM('active', 'suspended', 'pending') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) UNIQUE NOT NULL,
    icon VARCHAR(50),
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE IF NOT EXISTS products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    seller_id INT NOT NULL,
    category_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) UNIQUE NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    stock_quantity INT DEFAULT 0,
    image_url VARCHAR(500),
    additional_images TEXT,
    brand VARCHAR(100),
    sku VARCHAR(50),
    status ENUM('active', 'inactive', 'out_of_stock') DEFAULT 'active',
    is_featured BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id),
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- Cart table
CREATE TABLE IF NOT EXISTS carts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (product_id) REFERENCES products(id),
    UNIQUE KEY unique_cart_item (user_id, product_id)
);

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    payment_method VARCHAR(50),
    shipping_address TEXT,
    billing_address TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Order items table
CREATE TABLE IF NOT EXISTS order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Payments table
CREATE TABLE IF NOT EXISTS payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    transaction_id VARCHAR(100) UNIQUE,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50),
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    payment_details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id)
);

-- Reviews table
CREATE TABLE IF NOT EXISTS reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    seller_response TEXT,
    responded_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Seller applications table
CREATE TABLE IF NOT EXISTS seller_applications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    business_name VARCHAR(100),
    business_type VARCHAR(50),
    tax_id VARCHAR(50),
    business_address TEXT,
    phone VARCHAR(20),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Admin logs table
CREATE TABLE IF NOT EXISTS admin_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    action VARCHAR(100),
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id)
);

-- Insert default admin (password: password)
INSERT INTO users (username, email, password, full_name, role) 
VALUES ('admin', 'admin@msika.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin')
ON DUPLICATE KEY UPDATE username = username;

-- Insert sample seller
INSERT INTO users (username, email, password, full_name, role, phone, status) 
VALUES ('seller1', 'seller1@msika.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Daka', 'seller', '+260977123456', 'active')
ON DUPLICATE KEY UPDATE username = username;

-- Insert sample user
INSERT INTO users (username, email, password, full_name, role, phone, status) 
VALUES ('user1', 'user1@msika.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mary Banda', 'user', '+260966789012', 'active')
ON DUPLICATE KEY UPDATE username = username;

-- Insert categories
INSERT INTO categories (name, slug, icon, description) VALUES
('Electronics', 'electronics', 'fa-microchip', 'Electronic devices and accessories'),
('Fashion', 'fashion', 'fa-tshirt', 'Clothing and fashion accessories'),
('Bags', 'bags', 'fa-bag-shopping', 'Bags and luggage'),
('Vehicles', 'vehicles', 'fa-car', 'Vehicles and automotive'),
('Home', 'home', 'fa-couch', 'Home and furniture'),
('Office', 'office', 'fa-chair', 'Office supplies and furniture'),
('Sports', 'sports', 'fa-bicycle', 'Sports equipment and gear')
ON DUPLICATE KEY UPDATE slug = slug;

-- Get seller ID
SET @seller_id = (SELECT id FROM users WHERE username = 'seller1' LIMIT 1);

-- Get category IDs
SET @electronics_id = (SELECT id FROM categories WHERE slug = 'electronics' LIMIT 1);
SET @fashion_id = (SELECT id FROM categories WHERE slug = 'fashion' LIMIT 1);
SET @bags_id = (SELECT id FROM categories WHERE slug = 'bags' LIMIT 1);
SET @vehicles_id = (SELECT id FROM categories WHERE slug = 'vehicles' LIMIT 1);
SET @home_id = (SELECT id FROM categories WHERE slug = 'home' LIMIT 1);
SET @office_id = (SELECT id FROM categories WHERE slug = 'office' LIMIT 1);
SET @sports_id = (SELECT id FROM categories WHERE slug = 'sports' LIMIT 1);

-- Insert sample products
INSERT INTO products (seller_id, category_id, name, slug, description, price, stock_quantity, image_url, brand, sku, status, is_featured) VALUES
(@seller_id, @electronics_id, 'HP LaserJet Pro M428fdw', 'hp-laserjet-pro-m428fdw', 'Professional monochrome laser printer with wireless connectivity', 489.99, 50, 'https://images.pexels.com/photos/38544/imac-apple-macbook-computer-38544.jpeg?auto=compress&cs=tinysrgb&w=200&h=200&fit=crop', 'HP', 'HP-M428', 'active', 1),
(@seller_id, @electronics_id, 'Dell XPS 13 Plus Laptop', 'dell-xps-13-plus-laptop', 'Premium ultrabook with Intel Core i7, 16GB RAM, 512GB SSD', 1399.99, 25, 'https://images.pexels.com/photos/303383/pexels-photo-303383.jpeg?auto=compress&cs=tinysrgb&w=200&h=200&fit=crop', 'Dell', 'XPS13-2024', 'active', 1),
(@seller_id, @electronics_id, 'Lenovo ThinkCentre Desktop', 'lenovo-thinkcentre-desktop', 'Business desktop computer with Intel Core i5, 8GB RAM, 256GB SSD', 899.99, 30, 'https://images.pexels.com/photos/276452/pexels-photo-276452.jpeg?auto=compress&cs=tinysrgb&w=200&h=200&fit=crop', 'Lenovo', 'TC-2024', 'active', 0),
(@seller_id, @sports_id, 'Mountain Bike XC 29er', 'mountain-bike-xc-29er', 'Professional mountain bike with 29-inch wheels, 21-speed gear system', 650.00, 15, 'https://images.pexels.com/photos/100582/pexels-photo-100582.jpeg?auto=compress&cs=tinysrgb&w=200&h=200&fit=crop', 'Trek', 'MB-XC29', 'active', 1),
(@seller_id, @fashion_id, 'Nike Air Zoom Pegasus 40', 'nike-air-zoom-pegasus-40', 'Premium running shoes with responsive cushioning', 129.99, 100, 'https://images.pexels.com/photos/1598505/pexels-photo-1598505.jpeg?auto=compress&cs=tinysrgb&w=200&h=200&fit=crop', 'Nike', 'NK-P40', 'active', 0),
(@seller_id, @office_id, 'Ergonomic Office Chair', 'ergonomic-office-chair', 'Comfortable office chair with lumbar support and adjustable height', 299.99, 40, 'https://images.pexels.com/photos/1957477/pexels-photo-1957477.jpeg?auto=compress&cs=tinysrgb&w=200&h=200&fit=crop', 'OfficePro', 'OC-2024', 'active', 1),
(@seller_id, @office_id, 'Solid Wood Conference Table', 'solid-wood-conference-table', 'Elegant conference table for meeting rooms, seats 8 people', 1199.00, 10, 'https://images.pexels.com/photos/1293261/pexels-photo-1293261.jpeg?auto=compress&cs=tinysrgb&w=200&h=200&fit=crop', 'WoodCraft', 'CT-8', 'active', 0),
(@seller_id, @vehicles_id, 'Michelin Car Tires (Set of 4)', 'michelin-car-tires-set-of-4', 'Premium all-season tires for SUVs and sedans', 899.95, 20, 'https://images.pexels.com/photos/2288234/pexels-photo-2288234.jpeg?auto=compress&cs=tinysrgb&w=200&h=200&fit=crop', 'Michelin', 'MC-T4', 'active', 0),
(@seller_id, @fashion_id, 'Tailored Men''s Business Suit', 'tailored-mens-business-suit', 'Elegant business suit made from premium wool blend', 349.99, 35, 'https://images.pexels.com/photos/1043474/pexels-photo-1043474.jpeg?auto=compress&cs=tinysrgb&w=200&h=200&fit=crop', 'Armani', 'AM-BS', 'active', 1),
(@seller_id, @electronics_id, 'Canon PIXMA Printer', 'canon-pixma-printer', 'All-in-one printer with scan, copy, and wireless printing', 199.00, 60, 'https://images.pexels.com/photos/38544/imac-apple-macbook-computer-38544.jpeg?auto=compress&cs=tinysrgb&w=200&h=200&fit=crop', 'Canon', 'CN-PX', 'active', 0),
(@seller_id, @electronics_id, 'Gaming Desktop RTX 4060', 'gaming-desktop-rtx-4060', 'High-performance gaming PC with NVIDIA RTX 4060 graphics', 1499.99, 15, 'https://images.pexels.com/photos/2582937/pexels-photo-2582937.jpeg?auto=compress&cs=tinysrgb&w=200&h=200&fit=crop', 'Custom', 'GD-4060', 'active', 1),
(@seller_id, @sports_id, 'City Commuter Bicycle', 'city-commuter-bicycle', 'Lightweight bicycle for daily commuting', 480.00, 25, 'https://images.pexels.com/photos/248547/pexels-photo-248547.jpeg?auto=compress&cs=tinysrgb&w=200&h=200&fit=crop', 'Giant', 'CB-2024', 'active', 0),
(@seller_id, @bags_id, 'Leather Travel Bag', 'leather-travel-bag', 'Premium leather travel bag with multiple compartments', 189.99, 45, 'https://images.pexels.com/photos/1152077/pexels-photo-1152077.jpeg?auto=compress&cs=tinysrgb&w=200&h=200&fit=crop', 'Samsonite', 'LTB-2024', 'active', 1),
(@seller_id, @home_id, 'Modern Sofa Set', 'modern-sofa-set', 'Contemporary 3-piece sofa set with premium fabric', 2499.00, 8, 'https://images.pexels.com/photos/1571460/pexels-photo-1571460.jpeg?auto=compress&cs=tinysrgb&w=200&h=200&fit=crop', 'HomeStyle', 'HS-SF3', 'active', 0),
(@seller_id, @electronics_id, 'Samsung 55" Smart TV', 'samsung-55-smart-tv', '4K UHD Smart TV with HDR and built-in streaming apps', 799.99, 30, 'https://images.pexels.com/photos/1201996/pexels-photo-1201996.jpeg?auto=compress&cs=tinysrgb&w=200&h=200&fit=crop', 'Samsung', 'SS-55TV', 'active', 1),
(@seller_id, @fashion_id, 'Women''s Summer Dress', 'womens-summer-dress', 'Elegant floral summer dress made from lightweight fabric', 79.99, 80, 'https://images.pexels.com/photos/985635/pexels-photo-985635.jpeg?auto=compress&cs=tinysrgb&w=200&h=200&fit=crop', 'Zara', 'ZR-SD', 'active', 0)
ON DUPLICATE KEY UPDATE slug = slug;

-- Insert sample reviews
INSERT INTO reviews (product_id, user_id, rating, comment) VALUES
(1, 3, 5, 'Excellent printer! Fast and reliable.'),
(1, 3, 4, 'Good quality, easy setup.'),
(2, 3, 5, 'Amazing laptop, very fast!'),
(3, 3, 4, 'Great desktop for office work.'),
(4, 3, 5, 'Perfect mountain bike for trails!'),
(5, 3, 4, 'Comfortable running shoes.'),
(6, 3, 5, 'Very comfortable office chair.'),
(9, 3, 5, 'Perfect fit and great quality!')
ON DUPLICATE KEY UPDATE id = id;