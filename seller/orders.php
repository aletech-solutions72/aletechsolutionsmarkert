<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

check_seller_access();

$database = new Database();
$db = $database->getConnection();
$seller_id = $_SESSION['user_id'];

// Handle order status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['new_status'];
    
    // Verify order belongs to seller
    $verify_query = "SELECT o.id FROM orders o 
                     JOIN order_items oi ON o.id = oi.order_id 
                     JOIN products p ON oi.product_id = p.id 
                     WHERE o.id = ? AND p.seller_id = ?";
    $verify_stmt = $db->prepare($verify_query);
    $verify_stmt->execute([$order_id, $seller_id]);
    
    if ($verify_stmt->rowCount() > 0) {
        $query = "UPDATE orders SET status = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$new_status, $order_id])) {
            $_SESSION['success'] = "Order status updated successfully!";
        }
    } else {
        $_SESSION['error'] = "You don't have permission to update this order!";
    }
    
    header("Location: orders.php");
    exit();
}

// Get seller's orders
$query = "SELECT DISTINCT o.*, u.full_name as customer_name, u.email as customer_email, u.phone as customer_phone,
          (SELECT COUNT(*) FROM order_items oi JOIN products p ON oi.product_id = p.id 
           WHERE oi.order_id = o.id AND p.seller_id = ?) as seller_items_count,
          (SELECT COALESCE(SUM(oi.subtotal), 0) FROM order_items oi JOIN products p ON oi.product_id = p.id 
           WHERE oi.order_id = o.id AND p.seller_id = ?) as seller_total
          FROM orders o 
          JOIN order_items oi ON o.id = oi.order_id 
          JOIN products p ON oi.product_id = p.id 
          JOIN users u ON o.user_id = u.id 
          WHERE p.seller_id = ?
          ORDER BY o.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$seller_id, $seller_id, $seller_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get order statistics
$total_orders = count($orders);
$pending_orders = count(array_filter($orders, fn($o) => $o['status'] == 'pending'));
$processing_orders = count(array_filter($orders, fn($o) => $o['status'] == 'processing'));
$shipped_orders = count(array_filter($orders, fn($o) => $o['status'] == 'shipped'));
$delivered_orders = count(array_filter($orders, fn($o) => $o['status'] == 'delivered'));
$total_revenue = array_sum(array_column($orders, 'seller_total'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Seller Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f4f8; display: flex; }
        
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
        .seller-logo h2 { color: #D32F2F; font-size: 1.5rem; }
        .seller-menu { list-style: none; padding: 20px 0; }
        .seller-menu li a {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: #94a3b8;
            text-decoration: none;
            transition: all 0.3s;
            gap: 10px;
        }
        .seller-menu li a:hover { background: #1e293b; color: white; }
        .seller-menu li a.active { background: #D32F2F; color: white; }
        
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card i {
            font-size: 1.5rem;
            color: #D32F2F;
            margin-bottom: 8px;
        }
        .stat-card h3 {
            font-size: 1.2rem;
            margin-bottom: 5px;
        }
        .stat-card p {
            color: #64748b;
            font-size: 0.85rem;
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        .table { width: 100%; border-collapse: collapse; }
        .table th {
            background: #f8fafc;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #475569;
        }
        .table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-processing { background: #dbeafe; color: #1e40af; }
        .badge-shipped { background: #e0e7ff; color: #3730a3; }
        .badge-delivered { background: #d1fae5; color: #065f46; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; }
        
        select.status-select {
            padding: 8px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        .btn-update {
            padding: 8px 16px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85rem;
        }
        .btn-view {
            padding: 8px 16px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 0.85rem;
        }
        .btn:hover { opacity: 0.8; }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="products.php"><i class="fas fa-box"></i> My Products</a></li>
            <li><a href="add_product.php"><i class="fas fa-plus-circle"></i> Add Product</a></li>
            <li><a href="orders.php" class="active"><i class="fas fa-shopping-cart"></i> Orders</a></li>
            <li><a href="inventory.php"><i class="fas fa-warehouse"></i> Inventory</a></li>
            <li><a href="analytics.php"><i class="fas fa-chart-line"></i> Analytics</a></li>
            <li><a href="reviews.php"><i class="fas fa-star"></i> Reviews</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="seller-main">
        <div class="seller-header">
            <h1><i class="fas fa-shopping-cart"></i> My Orders</h1>
            <p>Total Orders: <?php echo $total_orders; ?></p>
        </div>
        
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-cards">
            <div class="stat-card">
                <i class="fas fa-shopping-cart"></i>
                <h3><?php echo $total_orders; ?></h3>
                <p>Total Orders</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-clock"></i>
                <h3><?php echo $pending_orders; ?></h3>
                <p>Pending</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-cog"></i>
                <h3><?php echo $processing_orders; ?></h3>
                <p>Processing</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-truck"></i>
                <h3><?php echo $shipped_orders; ?></h3>
                <p>Shipped</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-check-circle"></i>
                <h3><?php echo $delivered_orders; ?></h3>
                <p>Delivered</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-dollar-sign"></i>
                <h3>ZMW <?php echo number_format($total_revenue, 2); ?></h3>
                <p>Revenue</p>
            </div>
        </div>
        
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($orders as $order): ?>
                    <tr>
                        <td><?php echo $order['order_number']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong><br>
                            <small><?php echo htmlspecialchars($order['customer_email']); ?></small><br>
                            <small><?php echo htmlspecialchars($order['customer_phone']); ?></small>
                        </td>
                        <td><?php echo $order['seller_items_count']; ?></td>
                        <td>ZMW <?php echo number_format($order['seller_total'], 2); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $order['status']; ?>">
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $order['payment_status'] == 'paid' ? 'delivered' : 'pending'; ?>">
                                <?php echo ucfirst($order['payment_status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                        <td>
                            <form method="POST" action="" style="display: flex; gap: 5px;">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <select name="new_status" class="status-select">
                                    <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                    <option value="shipped" <?php echo $order['status'] == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                    <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                </select>
                                <button type="submit" name="update_status" class="btn-update">
                                    <i class="fas fa-check"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>