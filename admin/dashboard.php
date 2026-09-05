<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';

// Check if user is admin
check_admin_access();

$database = new Database();
$db = $database->getConnection();

// Get statistics
$total_users = $db->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$total_sellers = $db->query("SELECT COUNT(*) FROM users WHERE role = 'seller'")->fetchColumn();
$total_products = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$total_orders = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$total_revenue = $db->query("SELECT SUM(total_amount) FROM orders WHERE payment_status = 'paid'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Msika Premium</title>
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
            background: #f5f7fa;
            display: flex;
        }
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
        .admin-logo h2 {
            color: #D32F2F;
            font-size: 1.5rem;
        }
        .admin-menu {
            list-style: none;
            padding: 20px 0;
        }
        .admin-menu li a {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: #cbd5e1;
            text-decoration: none;
            transition: all 0.3s;
            gap: 10px;
        }
        .admin-menu li a:hover {
            background: #334155;
            color: white;
        }
        .admin-menu li a.active {
            background: #D32F2F;
            color: white;
        }
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
        .table-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .table {
            width: 100%;
            border-collapse: collapse;
        }
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
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-edit {
            background: #3b82f6;
            color: white;
        }
        .btn-delete {
            background: #ef4444;
            color: white;
        }
        .btn-view {
            background: #10b981;
            color: white;
        }
        .btn-edit:hover, .btn-delete:hover, .btn-view:hover {
            opacity: 0.8;
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
            <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="users.php"><i class="fas fa-users"></i> Manage Users</a></li>
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
        <div class="admin-header">
            <h1><i class="fas fa-tachometer-alt"></i> Dashboard Overview</h1>
            <p>Welcome, <?php echo $_SESSION['full_name']; ?>!</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <h3><?php echo $total_users; ?></h3>
                <p>Total Users</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-store"></i>
                <h3><?php echo $total_sellers; ?></h3>
                <p>Total Sellers</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-box"></i>
                <h3><?php echo $total_products; ?></h3>
                <p>Total Products</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-shopping-cart"></i>
                <h3><?php echo $total_orders; ?></h3>
                <p>Total Orders</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-dollar-sign"></i>
                <h3>ZMW <?php echo number_format($total_revenue ?? 0, 2); ?></h3>
                <p>Total Revenue</p>
            </div>
        </div>
        
        <div class="table-container">
            <h2 style="margin-bottom: 20px;">Recent Users</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $users_query = "SELECT * FROM users ORDER BY created_at DESC LIMIT 10";
                    $users_stmt = $db->query($users_query);
                    while($user = $users_stmt->fetch(PDO::FETCH_ASSOC)):
                    ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo $user['username']; ?></td>
                        <td><?php echo $user['email']; ?></td>
                        <td><?php echo $user['role']; ?></td>
                        <td><?php echo $user['status']; ?></td>
                        <td>
                            <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-edit"><i class="fas fa-edit"></i></a>
                            <a href="view_user.php?id=<?php echo $user['id']; ?>" class="btn btn-view"><i class="fas fa-eye"></i></a>
                            <a href="delete_user.php?id=<?php echo $user['id']; ?>" class="btn btn-delete" onclick="return confirm('Are you sure?')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>