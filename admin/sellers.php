<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

check_admin_access();

$database = new Database();
$db = $database->getConnection();

// Handle seller actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $seller_id = $_POST['seller_id'] ?? 0;
    
    switch ($action) {
        case 'approve_seller':
            $query = "UPDATE users SET role = 'seller', status = 'active' WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$seller_id]);
            
            // Update seller application
            $app_query = "UPDATE seller_applications SET status = 'approved' WHERE user_id = ?";
            $app_stmt = $db->prepare($app_query);
            $app_stmt->execute([$seller_id]);
            
            log_admin_action($db, $_SESSION['user_id'], 'approve_seller', "Approved seller ID: $seller_id");
            $_SESSION['success'] = "Seller approved successfully!";
            break;
            
        case 'reject_seller':
            $query = "UPDATE users SET role = 'user' WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$seller_id]);
            
            // Update seller application
            $app_query = "UPDATE seller_applications SET status = 'rejected' WHERE user_id = ?";
            $app_stmt = $db->prepare($app_query);
            $app_stmt->execute([$seller_id]);
            
            log_admin_action($db, $_SESSION['user_id'], 'reject_seller', "Rejected seller ID: $seller_id");
            $_SESSION['success'] = "Seller rejected successfully!";
            break;
            
        case 'suspend_seller':
            $query = "UPDATE users SET status = 'suspended' WHERE id = ? AND role = 'seller'";
            $stmt = $db->prepare($query);
            $stmt->execute([$seller_id]);
            
            log_admin_action($db, $_SESSION['user_id'], 'suspend_seller', "Suspended seller ID: $seller_id");
            $_SESSION['success'] = "Seller suspended successfully!";
            break;
            
        case 'activate_seller':
            $query = "UPDATE users SET status = 'active' WHERE id = ? AND role = 'seller'";
            $stmt = $db->prepare($query);
            $stmt->execute([$seller_id]);
            
            log_admin_action($db, $_SESSION['user_id'], 'activate_seller', "Activated seller ID: $seller_id");
            $_SESSION['success'] = "Seller activated successfully!";
            break;
    }
    
    header("Location: sellers.php");
    exit();
}

// Get all sellers with their stats
$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM products WHERE seller_id = u.id) as product_count,
          (SELECT COUNT(DISTINCT o.id) FROM orders o 
           JOIN order_items oi ON o.id = oi.order_id 
           JOIN products p ON oi.product_id = p.id 
           WHERE p.seller_id = u.id) as order_count,
          (SELECT COALESCE(SUM(oi.subtotal), 0) FROM order_items oi 
           JOIN products p ON oi.product_id = p.id 
           WHERE p.seller_id = u.id) as total_revenue,
          sa.business_name, sa.business_type, sa.status as application_status
          FROM users u 
          LEFT JOIN seller_applications sa ON u.id = sa.user_id 
          WHERE u.role = 'seller' OR sa.id IS NOT NULL
          ORDER BY u.created_at DESC";
$stmt = $db->query($query);
$sellers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending applications
$pending_query = "SELECT sa.*, u.full_name, u.email, u.phone, u.created_at as user_created
                  FROM seller_applications sa 
                  JOIN users u ON sa.user_id = u.id 
                  WHERE sa.status = 'pending' 
                  ORDER BY sa.created_at DESC";
$pending_stmt = $db->query($pending_query);
$pending_applications = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sellers - Msika Admin</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            margin-bottom: 30px;
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
        .btn-approve { background: #10b981; color: white; }
        .btn-reject { background: #ef4444; color: white; }
        .btn-suspend { background: #f59e0b; color: white; }
        .btn-activate { background: #10b981; color: white; }
        .btn-view { background: #3b82f6; color: white; }
        .btn:hover { opacity: 0.8; }
        
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-suspended { background: #fee2e2; color: #991b1b; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-approved { background: #d1fae5; color: #065f46; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }
        
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
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: #1e293b;
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
            <li><a href="sellers.php" class="active"><i class="fas fa-store"></i> Manage Sellers</a></li>
            <li><a href="products.php"><i class="fas fa-box"></i> Manage Products</a></li>
            <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> Manage Orders</a></li>
            <li><a href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
            <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="admin-main">
        <div class="admin-header">
            <h1><i class="fas fa-store"></i> Manage Sellers</h1>
            <p>Total Sellers: <?php echo count($sellers); ?></p>
        </div>
        
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-cards">
            <div class="stat-card">
                <i class="fas fa-store"></i>
                <h3><?php echo count($sellers); ?></h3>
                <p>Total Sellers</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-clock"></i>
                <h3><?php echo count($pending_applications); ?></h3>
                <p>Pending Applications</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-check-circle"></i>
                <h3><?php echo count(array_filter($sellers, fn($s) => $s['status'] == 'active')); ?></h3>
                <p>Active Sellers</p>
            </div>
        </div>
        
        <?php if(count($pending_applications) > 0): ?>
        <div class="table-container">
            <h2 class="section-title">⏳ Pending Seller Applications</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Business Name</th>
                        <th>Business Type</th>
                        <th>Contact</th>
                        <th>Applied Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pending_applications as $app): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($app['full_name']); ?></strong><br>
                            <small><?php echo htmlspecialchars($app['email']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($app['business_name'] ?: 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($app['business_type'] ?: 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($app['phone'] ?: 'N/A'); ?></td>
                        <td><?php echo date('M d, Y', strtotime($app['created_at'])); ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="approve_seller">
                                <input type="hidden" name="seller_id" value="<?php echo $app['user_id']; ?>">
                                <button type="submit" class="btn btn-approve">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="reject_seller">
                                <input type="hidden" name="seller_id" value="<?php echo $app['user_id']; ?>">
                                <button type="submit" class="btn btn-reject" onclick="return confirm('Reject this application?')">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <div class="table-container">
            <h2 class="section-title">📋 All Sellers</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Seller Info</th>
                        <th>Business</th>
                        <th>Products</th>
                        <th>Orders</th>
                        <th>Revenue</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($sellers as $seller): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($seller['full_name']); ?></strong><br>
                            <small><?php echo htmlspecialchars($seller['email']); ?></small><br>
                            <small>@<?php echo htmlspecialchars($seller['username']); ?></small>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($seller['business_name'] ?: 'N/A'); ?><br>
                            <small><?php echo htmlspecialchars($seller['business_type'] ?: ''); ?></small>
                        </td>
                        <td><?php echo $seller['product_count']; ?></td>
                        <td><?php echo $seller['order_count']; ?></td>
                        <td>ZMW <?php echo number_format($seller['total_revenue'], 2); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $seller['status']; ?>">
                                <?php echo ucfirst($seller['status']); ?>
                            </span>
                        </td>
                        <td>
                            <a href="view_seller.php?id=<?php echo $seller['id']; ?>" class="btn btn-view">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if($seller['status'] == 'active'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="suspend_seller">
                                    <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
                                    <button type="submit" class="btn btn-suspend" onclick="return confirm('Suspend this seller?')">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="activate_seller">
                                    <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
                                    <button type="submit" class="btn btn-activate" onclick="return confirm('Activate this seller?')">
                                        <i class="fas fa-check"></i>
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