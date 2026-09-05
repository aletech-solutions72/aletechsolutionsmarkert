<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';

// Check if user is admin
check_admin_access();

$database = new Database();
$db = $database->getConnection();

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $user_id = $_POST['user_id'];
        
        switch ($action) {
            case 'suspend':
                $query = "UPDATE users SET status = 'suspended' WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$user_id]);
                log_admin_action($db, $_SESSION['user_id'], 'suspend_user', "Suspended user ID: $user_id");
                break;
                
            case 'activate':
                $query = "UPDATE users SET status = 'active' WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$user_id]);
                log_admin_action($db, $_SESSION['user_id'], 'activate_user', "Activated user ID: $user_id");
                break;
                
            case 'delete':
                $query = "DELETE FROM users WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$user_id]);
                log_admin_action($db, $_SESSION['user_id'], 'delete_user', "Deleted user ID: $user_id");
                break;
                
            case 'change_role':
                $new_role = $_POST['new_role'];
                $query = "UPDATE users SET role = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$new_role, $user_id]);
                log_admin_action($db, $_SESSION['user_id'], 'change_role', "Changed user ID: $user_id to role: $new_role");
                break;
        }
    }
}

// Get all users with filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$query = "SELECT * FROM users WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($role_filter) {
    $query .= " AND role = ?";
    $params[] = $role_filter;
}

if ($status_filter) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Msika Admin</title>
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
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .filters input, .filters select {
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        .filters button {
            padding: 10px 20px;
            background: #D32F2F;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
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
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
            transition: all 0.3s;
        }
        .btn-edit { background: #3b82f6; color: white; }
        .btn-delete { background: #ef4444; color: white; }
        .btn-view { background: #10b981; color: white; }
        .btn-suspend { background: #f59e0b; color: white; }
        .btn-activate { background: #10b981; color: white; }
        .btn:hover { opacity: 0.8; }
        
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
        <div class="admin-header">
            <h1><i class="fas fa-users"></i> Manage Users</h1>
            <p>Total Users: <?php echo count($users); ?></p>
        </div>
        
        <div class="filters">
            <form method="GET" action="" style="display: flex; gap: 15px; flex-wrap: wrap;">
                <input type="text" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="role">
                    <option value="">All Roles</option>
                    <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="seller" <?php echo $role_filter == 'seller' ? 'selected' : ''; ?>>Seller</option>
                    <option value="user" <?php echo $role_filter == 'user' ? 'selected' : ''; ?>>User</option>
                </select>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="suspended" <?php echo $status_filter == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                </select>
                <button type="submit"><i class="fas fa-filter"></i> Filter</button>
            </form>
        </div>
        
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User Info</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Phone</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($user['full_name']); ?></strong><br>
                            <small><?php echo htmlspecialchars($user['email']); ?></small><br>
                            <small>@<?php echo htmlspecialchars($user['username']); ?></small>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $user['role']; ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $user['status']; ?>">
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </td>
                        <td><?php echo $user['phone'] ?: 'N/A'; ?></td>
                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                        <td>
                            <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="view_user.php?id=<?php echo $user['id']; ?>" class="btn btn-view">
                                <i class="fas fa-eye"></i>
                            </a>
                            
                            <?php if($user['status'] == 'active'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="suspend">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-suspend" onclick="return confirm('Suspend this user?')">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="activate">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-activate" onclick="return confirm('Activate this user?')">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" class="btn btn-delete" onclick="return confirm('Delete this user? This cannot be undone!')">
                                    <i class="fas fa-trash"></i>
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