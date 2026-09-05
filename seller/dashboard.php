<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';

// Check if user is seller
check_seller_access();

$database = new Database();
$db = $database->getConnection();
$seller_id = $_SESSION['user_id'];

// Get seller statistics
$total_products = $db->prepare("SELECT COUNT(*) FROM products WHERE seller_id = ?");
$total_products->execute([$seller_id]);
$product_count = $total_products->fetchColumn();

$total_orders = $db->prepare("SELECT COUNT(DISTINCT o.id) FROM orders o 
                              JOIN order_items oi ON o.id = oi.order_id 
                              JOIN products p ON oi.product_id = p.id 
                              WHERE p.seller_id = ?");
$total_orders->execute([$seller_id]);
$order_count = $total_orders->fetchColumn();

$total_revenue = $db->prepare("SELECT SUM(oi.subtotal) FROM order_items oi 
                               JOIN products p ON oi.product_id = p.id 
                               WHERE p.seller_id = ?");
$total_revenue->execute([$seller_id]);
$revenue = $total_revenue->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard - Msika Premium</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: #f0f4f8;
            display: flex;
        }
        .seller-sidebar {
            width: 250px;
            background: #0f172a;
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            padding: 20px 0;
            color: white;
        }
        .seller-logo {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #1e293b;
        }
        .seller-logo h2 {
            color: #D32F2F;
            font-size: 1.5rem;
        }
        .seller-menu {
            list-style: none;
            padding: 20px 0;
        }
        .seller-menu li a {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: #94a3b8;
            text-decoration: none;
            transition: all 0.3s;
            gap: 10px;
        }
        .seller-menu li a:hover {
            background: #1e293b;
            color: white;
        }
        .seller-menu li a.active {
            background: #D32F2F;
            color: white;
        }
        .seller-main {
            margin-left: 250px;
            flex: 1;
            padding: 20px;
        }
        .seller-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
        .quick-actions {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .action-btn {
            padding: 15px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .btn-add-product {
            background: #D32F2F;
            color: white;
        }
        .btn-view-orders {
            background: #3b82f6;
            color: white;
        }
        .btn-inventory {
            background: #10b981;
            color: white;
        }
        .btn-analytics {
            background: #8b5cf6;
            color: white;
        }
    </style>
</head>
<body>
    <div class="seller-sidebar">
        <div class="seller-logo">
            <h2><i class="fas fa-store"></i> Seller Panel</h2>
            <p style="font-size: 0.8rem; color: #94a3b8;"><?php echo $_SESSION['full_name']; ?></p>
        </div>
        <ul class="seller-menu">
            <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="products.php"><i class="fas fa-box"></i> My Products</a></li>
            <li><a href="add_product.php"><i class="fas fa-plus-circle"></i> Add Product</a></li>
            <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a></li>
            <li><a href="inventory.php"><i class="fas fa-warehouse"></i> Inventory</a></li>
            <li><a href="analytics.php"><i class="fas fa-chart-line"></i> Analytics</a></li>
            <li><a href="reviews.php"><i class="fas fa-star"></i> Reviews</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="seller-main">
        <div class="seller-header">
            <h1><i class="fas fa-tachometer-alt"></i> Seller Dashboard</h1>
            <a href="add_product.php" class="action-btn btn-add-product">
                <i class="fas fa-plus"></i> Add New Product
            </a>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-box"></i>
                <h3><?php echo $product_count; ?></h3>
                <p>Total Products</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-shopping-cart"></i>
                <h3><?php echo $order_count; ?></h3>
                <p>Total Orders</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-dollar-sign"></i>
                <h3>ZMW <?php echo number_format($revenue ?? 0, 2); ?></h3>
                <p>Total Revenue</p>
            </div>
        </div>
        
        <div class="quick-actions">
            <h2>Quick Actions</h2>
            <div class="action-buttons">
                <a href="add_product.php" class="action-btn btn-add-product">
                    <i class="fas fa-plus-circle"></i> Add Product
                </a>
                <a href="orders.php" class="action-btn btn-view-orders">
                    <i class="fas fa-shopping-cart"></i> View Orders
                </a>
                <a href="inventory.php" class="action-btn btn-inventory">
                    <i class="fas fa-warehouse"></i> Manage Inventory
                </a>
                <a href="analytics.php" class="action-btn btn-analytics">
                    <i class="fas fa-chart-bar"></i> View Analytics
                </a>
            </div>
        </div>
    </div>
</body>
</html>