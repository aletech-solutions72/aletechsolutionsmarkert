<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';

check_admin_access();

$database = new Database();
$db = $database->getConnection();

// Handle order status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['new_status'];
    
    $query = "UPDATE orders SET status = ? WHERE id = ?";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$new_status, $order_id])) {
        log_admin_action($db, $_SESSION['user_id'], 'update_order_status', 
                        "Updated order ID: $order_id to status: $new_status");
        $_SESSION['success'] = "Order status updated successfully!";
    }
    
    header("Location: orders.php");
    exit();
}

// Get all orders
$query = "SELECT o.*, u.full_name as customer_name, u.email as customer_email 
          FROM orders o 
          JOIN users u ON o.user_id = u.id 
          ORDER BY o.created_at DESC";
$stmt = $db->query($query);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Msika Admin</title>
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
        .admin-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
        .badge-paid { background: #d1fae5; color: #065f46; }
        .badge-unpaid { background: #fee2e2; color: #991b1b; }
        
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
        .btn-update:hover {
            background: #2563eb;
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
            <li><a href="users.php"><i class="fas fa-users"></i> Manage Users</a></li>
            <li><a href="sellers.php"><i class="fas fa-store"></i> Manage Sellers</a></li>
            <li><a href="products.php"><i class="fas fa-box"></i> Manage Products</a></li>
            <li><a href="orders.php" class="active"><i class="fas fa-shopping-cart"></i> Manage Orders</a></li>
            <li><a href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
            <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="admin-main">
        <div class="admin-header">
            <h1><i class="fas fa-shopping-cart"></i> Manage Orders</h1>
            <p>Total Orders: <?php echo count($orders); ?></p>
        </div>
        
        <?php if(isset($_SESSION['success'])): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Order Number</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Order Status</th>
                        <th>Payment Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($orders as $order): ?>
                    <tr>
                        <td>#<?php echo $order['id']; ?></td>
                        <td><?php echo $order['order_number']; ?></td>
                        <td>
                            <?php echo htmlspecialchars($order['customer_name']); ?><br>
                            <small><?php echo htmlspecialchars($order['customer_email']); ?></small>
                        </td>
                        <td>ZMW <?php echo number_format($order['total_amount'], 2); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $order['status']; ?>">
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $order['payment_status'] == 'paid' ? 'paid' : 'unpaid'; ?>">
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
                                    <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
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