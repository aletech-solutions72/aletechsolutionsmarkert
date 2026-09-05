<?php
// Sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Check admin access
function check_admin_access() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
        header("Location: ../auth/login.php");
        exit();
    }
}

// Check seller access
function check_seller_access() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'seller') {
        header("Location: ../auth/login.php");
        exit();
    }
}

// Check user access
function check_user_access() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: auth/login.php");
        exit();
    }
}

// Log admin actions
function log_admin_action($db, $admin_id, $action, $details = '') {
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $query = "INSERT INTO admin_logs (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([$admin_id, $action, $details, $ip_address]);
}

// Generate slug
function generate_slug($string) {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string)));
    return $slug;
}

// Upload image
function upload_image($file, $target_dir = 'uploads/') {
    $target_file = $target_dir . basename($file["name"]);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if image file is actual image
    $check = getimagesize($file["tmp_name"]);
    if($check !== false) {
        // Allow certain file formats
        if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
            return false;
        }
        
        // Generate unique filename
        $new_filename = uniqid() . '.' . $imageFileType;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($file["tmp_name"], $target_file)) {
            return $target_file;
        }
    }
    return false;
}

// Get user by ID
function get_user($db, $user_id) {
    $query = "SELECT * FROM users WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get product by ID
function get_product($db, $product_id) {
    $query = "SELECT p.*, c.name as category_name, u.full_name as seller_name 
              FROM products p 
              JOIN categories c ON p.category_id = c.id 
              JOIN users u ON p.seller_id = u.id 
              WHERE p.id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$product_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Format currency
function format_currency($amount, $currency = 'ZMW') {
    return $currency . ' ' . number_format($amount, 2);
}

// Check if product in stock
function check_stock($product_id, $quantity = 1) {
    global $db;
    $query = "SELECT stock_quantity FROM products WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$product_id]);
    $stock = $stmt->fetchColumn();
    return $stock >= $quantity;
}

// Get cart count
function get_cart_count($db, $user_id) {
    $query = "SELECT SUM(quantity) FROM carts WHERE user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() ?: 0;
}
?>