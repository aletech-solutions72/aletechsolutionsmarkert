<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

check_admin_access();

$database = new Database();
$db = $database->getConnection();

$user_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Get user details
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: users.php");
    exit();
}

// Get user's orders
$orders_query = "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
$orders_stmt = $db->prepare($orders_query);
$orders_stmt->execute([$user_id]);
$user_orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's cart items
$cart_query = "SELECT c.*, p.name, p.price, p.image_url 
               FROM carts c 
               JOIN products p ON c.product_id = p.id 
               WHERE c.user_id = ?";
$cart_stmt = $db->prepare($cart_query);
$cart_stmt->execute([$user_id]);
$cart_items = $cart_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's reviews
$reviews_query = "SELECT r.*, p.name as product_name 
                  FROM reviews r 
                  JOIN products p ON r.product_id = p.id 
                  WHERE r.user_id = ? 
                  ORDER BY r.created_at DESC";
$reviews_stmt = $db->prepare($reviews_query);
$reviews_stmt->execute([$user_id]);
$user_reviews = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's seller application if any
$seller_app_query = "SELECT * FROM seller_applications WHERE user_id = ?";
$seller_app_stmt = $db->prepare($seller_app_query);
$seller_app_stmt->execute([$user_id]);
$seller_application = $seller_app_stmt->fetch(PDO::FETCH_ASSOC);

// Get user's products if seller
$products_query = "SELECT p.*, c.name as category_name 
                   FROM products p 
                   JOIN categories c ON p.category_id = c.id 
                   WHERE p.seller_id = ? 
                   ORDER BY p.created_at DESC LIMIT 10";
$products_stmt = $db->prepare($products_query);
$products_stmt->execute([$user_id]);
$user_products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total spent
$total_spent = array_sum(array_column($user_orders, 'total_amount'));

// Get admin logs for this user if they're admin
$admin_logs = [];
if ($user['role'] == 'admin') {
    $logs_query = "SELECT * FROM admin_logs WHERE admin_id = ? ORDER BY created_at DESC LIMIT 10";
    $logs_stmt = $db->prepare($logs_query);
    $logs_stmt->execute([$user_id]);
    $admin_logs = $logs_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User - Msika Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fa; display: flex; }
        
        .admin-sidebar {
            width: 250px;
            background: #1e293b;
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            padding: 20px 0;
            color: white;
        }
        .admin-logo {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #334155;
        }
        .admin-logo h2 { color: #D32F2F; font-size: 1.5rem; }
        .admin-menu { list-style: none; padding: 20px 0; }
        .admin-menu li a {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: #cbd5e1;
            text-decoration: none;
            transition: all 0.3s;
            gap: 10px;
        }
        .admin-menu li a:hover { background: #334155; color: white; }
        .admin-menu li a.active { background: #D32F2F; color: white; }
        
        .admin-main {
            margin-left: 250px;
            flex: 1;
            padding: 20px;
        }
        
        .profile-header {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 30px;
            flex-wrap: wrap;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #D32F2F, #B71C1C);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 700;
        }
        .profile-info {
            flex: 1;
        }
        .profile-info h1 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }
        .profile-info p {
            color: #64748b;
            margin-bottom: 3px;
        }
        .profile-actions {
            display: flex;
            gap: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card i {
            font-size: 2rem;
            color: #D32F2F;
            margin-bottom: 10px;
        }
        .stat-card h3 {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }
        .stat-card p {
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .details-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .details-card h2 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .details-card h2 i {
            color: #D32F2F;
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
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .table th {
            background: #f8fafc;
            padding: 10px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            font-size: 0.85rem;
        }
        .table td {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.9rem;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-admin { background: #fee2e2; color: #991b1b; }
        .badge-seller { background: #dbeafe; color: #1e40af; }
        .badge-user { background: #d1fae5; color: #065f46; }
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-suspended { background: #fee2e2; color: #991b1b; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-approved { background: #d1fae5; color: #065f46; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .btn-back {
            background: #64748b;
            color: white;
        }
        .btn-edit {
            background: #3b82f6;
            color: white;
        }
        .btn-suspend {
            background: #f59e0b;
            color: white;
        }
        .btn-activate {
            background: #10b981;
            color: white;
        }
        .btn:hover {
            opacity: 0.8;
            transform: translateY(-2px);
        }
        
        .empty-state {
            text-align: center;
            padding: 30px;
            color: #64748b;
        }
        .empty-state i {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            .profile-actions {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="admin-sidebar">
        <div class="admin-logo">
            <h2><i class="fas fa-chart-line"></i> Msika Admin</h2>
            <p style="font-size: 0.8rem; color: #94a3b8;">Admin Panel</p>
        </div>
        <ul class="admin-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="users.php" class="active"><i class="fas fa-users"></i> Manage Users</a></li>
            <li><a href="sellers.php"><i class="fas fa-store"></i> Manage Sellers</a></li>
            <li><a href="products.php"><i class="fas fa-box"></i> Manage Products</a></li>
            <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> Manage Orders</a></li>
            <li><a href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
            <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="admin-main">
        <div style="margin-bottom: 20px;">
            <a href="users.php" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Back to Users
            </a>
        </div>
        
        <div class="profile-header">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
            </div>
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($user['full_name']); ?></h1>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                <p><i class="fas fa-user"></i> @<?php echo htmlspecialchars($user['username']); ?></p>
                <p>
                    <span class="badge badge-<?php echo $user['role']; ?>">
                        <?php echo ucfirst($user['role']); ?>
                    </span>
                    <span class="badge badge-<?php echo $user['status']; ?>">
                        <?php echo ucfirst($user['status']); ?>
                    </span>
                </p>
            </div>
            <div class="profile-actions">
                <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-edit">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <?php if($user['status'] == 'active'): ?>
                    <form method="POST" action="users.php" style="display: inline;">
                        <input type="hidden" name="action" value="suspend">
                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                        <button type="submit" class="btn btn-suspend" onclick="return confirm('Suspend this user?')">
                            <i class="fas fa-ban"></i> Suspend
                        </button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="users.php" style="display: inline;">
                        <input type="hidden" name="action" value="activate">
                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                        <button type="submit" class="btn btn-activate" onclick="return confirm('Activate this user?')">
                            <i class="fas fa-check"></i> Activate
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-shopping-cart"></i>
                <h3><?php echo count($user_orders); ?></h3>
                <p>Total Orders</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-dollar-sign"></i>
                <h3>ZMW <?php echo number_format($total_spent, 2); ?></h3>
                <p>Total Spent</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-shopping-bag"></i>
                <h3><?php echo count($cart_items); ?></h3>
                <p>Cart Items</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-star"></i>
                <h3><?php echo count($user_reviews); ?></h3>
                <p>Reviews</p>
            </div>
            <?php if($user['role'] == 'seller'): ?>
            <div class="stat-card">
                <i class="fas fa-box"></i>
                <h3><?php echo count($user_products); ?></h3>
                <p>Products</p>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="details-grid">
            <div class="details-card">
                <h2><i class="fas fa-info-circle"></i> Personal Information</h2>
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
                    <span class="info-value"><?php echo htmlspecialchars($user['username']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['phone'] ?: 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Address</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['address'] ?: 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Role</span>
                    <span class="info-value"><?php echo ucfirst($user['role']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status</span>
                    <span class="info-value"><?php echo ucfirst($user['status']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Registered</span>
                    <span class="info-value"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Last Updated</span>
                    <span class="info-value"><?php echo date('F j, Y', strtotime($user['updated_at'])); ?></span>
                </div>
            </div>
            
            <?php if($user['role'] == 'seller' && $seller_application): ?>
            <div class="details-card">
                <h2><i class="fas fa-store"></i> Seller Application</h2>
                <div class="info-row">
                    <span class="info-label">Business Name</span>
                    <span class="info-value"><?php echo htmlspecialchars($seller_application['business_name'] ?: 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Business Type</span>
                    <span class="info-value"><?php echo htmlspecialchars($seller_application['business_type'] ?: 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Tax ID</span>
                    <span class="info-value"><?php echo htmlspecialchars($seller_application['tax_id'] ?: 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Business Address</span>
                    <span class="info-value"><?php echo htmlspecialchars($seller_application['business_address'] ?: 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Application Status</span>
                    <span class="info-value">
                        <span class="badge badge-<?php echo $seller_application['status']; ?>">
                            <?php echo ucfirst($seller_application['status']); ?>
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Applied On</span>
                    <span class="info-value"><?php echo date('F j, Y', strtotime($seller_application['created_at'])); ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="details-card">
                <h2><i class="fas fa-shopping-cart"></i> Recent Orders</h2>
                <?php if(count($user_orders) > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($user_orders as $order): ?>
                        <tr>
                            <td><?php echo $order['order_number']; ?></td>
                            <td>ZMW <?php echo number_format($order['total_amount'], 2); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $order['status']; ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-cart"></i>
                    <p>No orders yet</p>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="details-card">
                <h2><i class="fas fa-star"></i> Recent Reviews</h2>
                <?php if(count($user_reviews) > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Rating</th>
                            <th>Comment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($user_reviews as $review): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($review['product_name']); ?></td>
                            <td>
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star" style="color: <?php echo $i <= $review['rating'] ? '#f59e0b' : '#e2e8f0'; ?>;"></i>
                                <?php endfor; ?>
                            </td>
                            <td><?php echo htmlspecialchars(substr($review['comment'], 0, 50)); ?>...</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-star"></i>
                    <p>No reviews yet</p>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if($user['role'] == 'seller' && count($user_products) > 0): ?>
            <div class="details-card">
                <h2><i class="fas fa-box"></i> Products</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($user_products as $product): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                            <td>ZMW <?php echo number_format($product['price'], 2); ?></td>
                            <td><?php echo $product['stock_quantity']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <?php if($user['role'] == 'admin' && count($admin_logs) > 0): ?>
            <div class="details-card">
                <h2><i class="fas fa-history"></i> Recent Admin Actions</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Details</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($admin_logs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['action']); ?></td>
                            <td><?php echo htmlspecialchars($log['details']); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>