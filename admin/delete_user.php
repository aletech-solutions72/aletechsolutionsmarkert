<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

// Check if user is admin
check_admin_access();

$database = new Database();
$db = $database->getConnection();

// Check if user ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Invalid user ID!";
    header("Location: users.php");
    exit();
}

$user_id = $_GET['id'];
$admin_id = $_SESSION['user_id'];

// Prevent deleting own account
if ($user_id == $admin_id) {
    $_SESSION['error'] = "You cannot delete your own account!";
    header("Location: users.php");
    exit();
}

// Get user details before deletion for logging
$user_query = "SELECT * FROM users WHERE id = ?";
$user_stmt = $db->prepare($user_query);
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['error'] = "User not found!";
    header("Location: users.php");
    exit();
}

// Check if user has related records
$has_orders = false;
$has_products = false;
$has_reviews = false;
$has_cart = false;
$has_seller_application = false;

// Check orders
$order_check = "SELECT COUNT(*) FROM orders WHERE user_id = ?";
$order_stmt = $db->prepare($order_check);
$order_stmt->execute([$user_id]);
$has_orders = $order_stmt->fetchColumn() > 0;

// Check products (if seller)
if ($user['role'] == 'seller') {
    $product_check = "SELECT COUNT(*) FROM products WHERE seller_id = ?";
    $product_stmt = $db->prepare($product_check);
    $product_stmt->execute([$user_id]);
    $has_products = $product_stmt->fetchColumn() > 0;
}

// Check reviews
$review_check = "SELECT COUNT(*) FROM reviews WHERE user_id = ?";
$review_stmt = $db->prepare($review_check);
$review_stmt->execute([$user_id]);
$has_reviews = $review_stmt->fetchColumn() > 0;

// Check cart
$cart_check = "SELECT COUNT(*) FROM carts WHERE user_id = ?";
$cart_stmt = $db->prepare($cart_check);
$cart_stmt->execute([$user_id]);
$has_cart = $cart_stmt->fetchColumn() > 0;

// Check seller application
$app_check = "SELECT COUNT(*) FROM seller_applications WHERE user_id = ?";
$app_stmt = $db->prepare($app_check);
$app_stmt->execute([$user_id]);
$has_seller_application = $app_stmt->fetchColumn() > 0;

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_delete'])) {
    $delete_option = $_POST['delete_option'] ?? 'cancel';
    
    if ($delete_option == 'cancel') {
        header("Location: view_user.php?id=" . $user_id);
        exit();
    }
    
    try {
        // Begin transaction
        $db->beginTransaction();
        
        if ($delete_option == 'delete_all') {
            // Delete all related records
            
            // Delete order items for user's orders
            $delete_order_items = "DELETE oi FROM order_items oi 
                                   JOIN orders o ON oi.order_id = o.id 
                                   WHERE o.user_id = ?";
            $stmt = $db->prepare($delete_order_items);
            $stmt->execute([$user_id]);
            
            // Delete payments for user's orders
            $delete_payments = "DELETE p FROM payments p 
                                JOIN orders o ON p.order_id = o.id 
                                WHERE o.user_id = ?";
            $stmt = $db->prepare($delete_payments);
            $stmt->execute([$user_id]);
            
            // Delete user's orders
            $delete_orders = "DELETE FROM orders WHERE user_id = ?";
            $stmt = $db->prepare($delete_orders);
            $stmt->execute([$user_id]);
            
            // Delete user's reviews
            $delete_reviews = "DELETE FROM reviews WHERE user_id = ?";
            $stmt = $db->prepare($delete_reviews);
            $stmt->execute([$user_id]);
            
            // Delete user's cart
            $delete_cart = "DELETE FROM carts WHERE user_id = ?";
            $stmt = $db->prepare($delete_cart);
            $stmt->execute([$user_id]);
            
            // Delete seller application if exists
            $delete_app = "DELETE FROM seller_applications WHERE user_id = ?";
            $stmt = $db->prepare($delete_app);
            $stmt->execute([$user_id]);
            
            // If seller, handle products
            if ($user['role'] == 'seller') {
                // Delete order items for seller's products
                $delete_product_order_items = "DELETE oi FROM order_items oi 
                                               JOIN products p ON oi.product_id = p.id 
                                               WHERE p.seller_id = ?";
                $stmt = $db->prepare($delete_product_order_items);
                $stmt->execute([$user_id]);
                
                // Delete reviews for seller's products
                $delete_product_reviews = "DELETE r FROM reviews r 
                                           JOIN products p ON r.product_id = p.id 
                                           WHERE p.seller_id = ?";
                $stmt = $db->prepare($delete_product_reviews);
                $stmt->execute([$user_id]);
                
                // Delete seller's products
                $delete_products = "DELETE FROM products WHERE seller_id = ?";
                $stmt = $db->prepare($delete_products);
                $stmt->execute([$user_id]);
            }
            
            // Delete admin logs if user is admin
            if ($user['role'] == 'admin') {
                $delete_logs = "DELETE FROM admin_logs WHERE admin_id = ?";
                $stmt = $db->prepare($delete_logs);
                $stmt->execute([$user_id]);
            }
            
            // Finally, delete the user
            $delete_user = "DELETE FROM users WHERE id = ?";
            $stmt = $db->prepare($delete_user);
            $stmt->execute([$user_id]);
            
        } elseif ($delete_option == 'anonymize') {
            // Anonymize user data instead of deleting
            $anonymize_query = "UPDATE users SET 
                                username = CONCAT('deleted_user_', id),
                                email = CONCAT('deleted_', id, '@anonymous.com'),
                                password = '',
                                full_name = 'Deleted User',
                                phone = NULL,
                                address = NULL,
                                profile_image = NULL,
                                status = 'suspended'
                                WHERE id = ?";
            $stmt = $db->prepare($anonymize_query);
            $stmt->execute([$user_id]);
            
            // Delete cart items
            $delete_cart = "DELETE FROM carts WHERE user_id = ?";
            $stmt = $db->prepare($delete_cart);
            $stmt->execute([$user_id]);
        }
        
        // Commit transaction
        $db->commit();
        
        // Log the action
        log_admin_action($db, $admin_id, 'delete_user', 
                        "Deleted user ID: $user_id ({$user['username']}) - Option: $delete_option");
        
        $_SESSION['success'] = "User '{$user['full_name']}' has been " . 
                               ($delete_option == 'anonymize' ? 'anonymized' : 'deleted') . 
                               " successfully!";
        
        header("Location: users.php");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollBack();
        $_SESSION['error'] = "Failed to delete user: " . $e->getMessage();
        header("Location: view_user.php?id=" . $user_id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete User - Msika Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: #f5f7fa; 
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .delete-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 600px;
            overflow: hidden;
        }
        
        .delete-header {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .delete-header i {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        .delete-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .delete-body {
            padding: 30px;
        }
        
        .warning-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .warning-box h3 {
            color: #991b1b;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .warning-box p {
            color: #7f1d1d;
            font-size: 0.9rem;
            line-height: 1.6;
        }
        
        .user-info {
            background: #f8fafc;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .user-info h3 {
            margin-bottom: 15px;
            color: #1e293b;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 500;
            color: #475569;
        }
        .info-value {
            font-weight: 600;
            color: #1e293b;
        }
        
        .related-data {
            margin-bottom: 20px;
        }
        .related-data h3 {
            margin-bottom: 15px;
            color: #1e293b;
        }
        .data-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 8px;
        }
        .data-item i {
            color: #64748b;
            width: 20px;
        }
        .data-item .count {
            margin-left: auto;
            font-weight: 600;
            color: #D32F2F;
        }
        
        .delete-options {
            margin-bottom: 20px;
        }
        .delete-options h3 {
            margin-bottom: 15px;
            color: #1e293b;
        }
        .option {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .option:hover {
            border-color: #D32F2F;
            background: #fef2f2;
        }
        .option input[type="radio"] {
            margin-top: 3px;
        }
        .option-content {
            flex: 1;
        }
        .option-content strong {
            display: block;
            margin-bottom: 5px;
        }
        .option-content p {
            font-size: 0.85rem;
            color: #64748b;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
        }
        .btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-cancel {
            background: #64748b;
            color: white;
        }
        .btn-delete {
            background: #ef4444;
            color: white;
        }
        .btn-anonymize {
            background: #f59e0b;
            color: white;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="delete-container">
        <div class="delete-header">
            <i class="fas fa-exclamation-triangle"></i>
            <h1>Delete User Account</h1>
            <p style="margin-top: 10px; opacity: 0.9;">This action requires confirmation</p>
        </div>
        
        <div class="delete-body">
            <div class="warning-box">
                <h3>
                    <i class="fas fa-exclamation-circle"></i>
                    Warning
                </h3>
                <p>
                    You are about to delete this user account. This action cannot be undone. 
                    Please review the information below carefully before proceeding.
                </p>
            </div>
            
            <div class="user-info">
                <h3>User Information</h3>
                <div class="info-row">
                    <span class="info-label">User ID</span>
                    <span class="info-value">#<?php echo $user['id']; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Full Name</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['full_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Username</span>
                    <span class="info-value">@<?php echo htmlspecialchars($user['username']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Role</span>
                    <span class="info-value"><?php echo ucfirst($user['role']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Member Since</span>
                    <span class="info-value"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></span>
                </div>
            </div>
            
            <div class="related-data">
                <h3>Related Data</h3>
                <?php if($has_orders): ?>
                <div class="data-item">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Orders</span>
                    <span class="count">Will be deleted</span>
                </div>
                <?php endif; ?>
                
                <?php if($has_products): ?>
                <div class="data-item">
                    <i class="fas fa-box"></i>
                    <span>Products</span>
                    <span class="count">Will be deleted</span>
                </div>
                <?php endif; ?>
                
                <?php if($has_reviews): ?>
                <div class="data-item">
                    <i class="fas fa-star"></i>
                    <span>Reviews</span>
                    <span class="count">Will be deleted</span>
                </div>
                <?php endif; ?>
                
                <?php if($has_cart): ?>
                <div class="data-item">
                    <i class="fas fa-shopping-bag"></i>
                    <span>Cart Items</span>
                    <span class="count">Will be deleted</span>
                </div>
                <?php endif; ?>
                
                <?php if($has_seller_application): ?>
                <div class="data-item">
                    <i class="fas fa-file-alt"></i>
                    <span>Seller Application</span>
                    <span class="count">Will be deleted</span>
                </div>
                <?php endif; ?>
                
                <?php if(!$has_orders && !$has_products && !$has_reviews && !$has_cart && !$has_seller_application): ?>
                <div class="data-item">
                    <i class="fas fa-check-circle"></i>
                    <span>No related data found</span>
                    <span class="count" style="color: #10b981;">Clean</span>
                </div>
                <?php endif; ?>
            </div>
            
            <form method="POST" action="">
                <div class="delete-options">
                    <h3>Choose Deletion Method</h3>
                    
                    <label class="option">
                        <input type="radio" name="delete_option" value="delete_all" checked>
                        <div class="option-content">
                            <strong>Permanent Delete</strong>
                            <p>Permanently delete user and all related data. This cannot be undone.</p>
                        </div>
                    </label>
                    
                    <label class="option">
                        <input type="radio" name="delete_option" value="anonymize">
                        <div class="option-content">
                            <strong>Anonymize User</strong>
                            <p>Keep user records but remove personal information. Useful for maintaining order history.</p>
                        </div>
                    </label>
                </div>
                
                <div class="action-buttons">
                    <a href="view_user.php?id=<?php echo $user['id']; ?>" class="btn btn-cancel">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" name="confirm_delete" class="btn btn-delete" 
                            onclick="return confirm('Are you absolutely sure you want to delete this user? This action cannot be undone!')">
                        <i class="fas fa-trash"></i> Delete User
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>