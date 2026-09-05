<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

check_admin_access();

$database = new Database();
$db = $database->getConnection();

// Handle payment actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $payment_id = $_POST['payment_id'] ?? 0;
    
    switch ($action) {
        case 'refund':
            $query = "UPDATE payments SET status = 'refunded' WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$payment_id]);
            
            // Update order payment status
            $update_order = "UPDATE orders SET payment_status = 'refunded' WHERE id = (SELECT order_id FROM payments WHERE id = ?)";
            $update_stmt = $db->prepare($update_order);
            $update_stmt->execute([$payment_id]);
            
            log_admin_action($db, $_SESSION['user_id'], 'refund_payment', "Refunded payment ID: $payment_id");
            $_SESSION['success'] = "Payment refunded successfully!";
            break;
            
        case 'mark_completed':
            $query = "UPDATE payments SET status = 'completed' WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$payment_id]);
            
            log_admin_action($db, $_SESSION['user_id'], 'complete_payment', "Completed payment ID: $payment_id");
            $_SESSION['success'] = "Payment marked as completed!";
            break;
    }
    
    header("Location: payments.php");
    exit();
}

// Get all payments
$query = "SELECT p.*, o.order_number, u.full_name as customer_name, u.email as customer_email
          FROM payments p 
          JOIN orders o ON p.order_id = o.id 
          JOIN users u ON o.user_id = u.id 
          ORDER BY p.created_at DESC";
$stmt = $db->query($query);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment statistics
$total_payments = count($payments);
$total_amount = array_sum(array_column($payments, 'amount'));
$completed_payments = count(array_filter($payments, fn($p) => $p['status'] == 'completed'));
$pending_payments = count(array_filter($payments, fn($p) => $p['status'] == 'pending'));
$refunded_payments = count(array_filter($payments, fn($p) => $p['status'] == 'refunded'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payments - Msika Admin</title>
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
        
        .stats-cards {
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
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
            transition: all 0.3s;
        }
        .btn-refund { background: #ef4444; color: white; }
        .btn-complete { background: #10b981; color: white; }
        .btn:hover { opacity: 0.8; }
        
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-completed { background: #d1fae5; color: #065f46; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-failed { background: #fee2e2; color: #991b1b; }
        .badge-refunded { background: #e0e7ff; color: #3730a3; }
        
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
            <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> Manage Orders</a></li>
            <li><a href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
            <li><a href="payments.php" class="active"><i class="fas fa-credit-card"></i> Payments</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="admin-main">
        <div class="admin-header">
            <h1><i class="fas fa-credit-card"></i> Manage Payments</h1>
            <p>Total Payments: <?php echo $total_payments; ?></p>
        </div>
        
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-cards">
            <div class="stat-card">
                <i class="fas fa-credit-card"></i>
                <h3><?php echo $total_payments; ?></h3>
                <p>Total Payments</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-dollar-sign"></i>
                <h3>ZMW <?php echo number_format($total_amount, 2); ?></h3>
                <p>Total Amount</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-check-circle"></i>
                <h3><?php echo $completed_payments; ?></h3>
                <p>Completed</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-clock"></i>
                <h3><?php echo $pending_payments; ?></h3>
                <p>Pending</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-undo"></i>
                <h3><?php echo $refunded_payments; ?></h3>
                <p>Refunded</p>
            </div>
        </div>
        
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Transaction ID</th>
                        <th>Order Number</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($payments as $payment): ?>
                    <tr>
                        <td><?php echo $payment['transaction_id']; ?></td>
                        <td><?php echo $payment['order_number']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($payment['customer_name']); ?></strong><br>
                            <small><?php echo htmlspecialchars($payment['customer_email']); ?></small>
                        </td>
                        <td>ZMW <?php echo number_format($payment['amount'], 2); ?></td>
                        <td><?php echo ucfirst($payment['payment_method']); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $payment['status']; ?>">
                                <?php echo ucfirst($payment['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y H:i', strtotime($payment['created_at'])); ?></td>
                        <td>
                            <?php if($payment['status'] == 'completed'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="refund">
                                    <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                    <button type="submit" class="btn btn-refund" onclick="return confirm('Refund this payment?')">
                                        <i class="fas fa-undo"></i> Refund
                                    </button>
                                </form>
                            <?php elseif($payment['status'] == 'pending'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="mark_completed">
                                    <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                    <button type="submit" class="btn btn-complete">
                                        <i class="fas fa-check"></i> Mark Completed
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>