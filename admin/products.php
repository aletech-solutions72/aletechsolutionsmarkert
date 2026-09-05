<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';

check_admin_access();

$database = new Database();
$db = $database->getConnection();

// Handle product actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $product_id = $_POST['product_id'];
        
        switch ($action) {
            case 'delete':
                $query = "DELETE FROM products WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$product_id]);
                log_admin_action($db, $_SESSION['user_id'], 'delete_product', "Deleted product ID: $product_id");
                break;
                
            case 'toggle_status':
                $query = "UPDATE products SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$product_id]);
                log_admin_action($db, $_SESSION['user_id'], 'toggle_product_status', "Toggled status for product ID: $product_id");
                break;
                
            case 'feature':
                $query = "UPDATE products SET is_featured = NOT is_featured WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$product_id]);
                log_admin_action($db, $_SESSION['user_id'], 'toggle_featured', "Toggled featured for product ID: $product_id");
                break;
        }
    }
}

// Get all products
$query = "SELECT p.*, c.name as category_name, u.full_name as seller_name 
          FROM products p 
          JOIN categories c ON p.category_id = c.id 
          JOIN users u ON p.seller_id = u.id 
          ORDER BY p.created_at DESC";
$stmt = $db->query($query);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Msika Admin</title>
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
        
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        .product-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #f8fafc;
        }
        .product-info {
            padding: 15px;
        }
        .product-name {
            font-weight: 600;
            margin-bottom: 10px;
        }
        .product-price {
            color: #D32F2F;
            font-weight: 700;
            font-size: 1.2rem;
            margin-bottom: 10px;
        }
        .product-meta {
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 10px;
        }
        .product-actions {
            display: flex;
            gap: 5px;
            margin-top: 10px;
        }
        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-edit { background: #3b82f6; color: white; }
        .btn-delete { background: #ef4444; color: white; }
        .btn-view { background: #10b981; color: white; }
        .btn-feature { background: #8b5cf6; color: white; }
        .btn-toggle { background: #f59e0b; color: white; }
        .btn:hover { opacity: 0.8; }
        
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-inactive { background: #fee2e2; color: #991b1b; }
        .badge-featured { background: #fef3c7; color: #92400e; }
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
            <li><a href="products.php" class="active"><i class="fas fa-box"></i> Manage Products</a></li>
            <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> Manage Orders</a></li>
            <li><a href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
            <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="admin-main">
        <div class="admin-header">
            <h1><i class="fas fa-box"></i> Manage Products</h1>
            <p>Total Products: <?php echo count($products); ?></p>
        </div>
        
        <div class="product-grid">
            <?php foreach($products as $product): ?>
            <div class="product-card">
                <img src="<?php echo $product['image_url'] ?: 'https://placehold.co/400x300'; ?>" 
                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                     class="product-image"
                     onerror="this.src='https://placehold.co/400x300'">
                <div class="product-info">
                    <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                    <div class="product-price">ZMW <?php echo number_format($product['price'], 2); ?></div>
                    <div class="product-meta">
                        <div>Category: <?php echo htmlspecialchars($product['category_name']); ?></div>
                        <div>Seller: <?php echo htmlspecialchars($product['seller_name']); ?></div>
                        <div>Stock: <?php echo $product['stock_quantity']; ?></div>
                    </div>
                    <div>
                        <span class="badge badge-<?php echo $product['status']; ?>">
                            <?php echo ucfirst($product['status']); ?>
                        </span>
                        <?php if($product['is_featured']): ?>
                            <span class="badge badge-featured">Featured</span>
                        <?php endif; ?>
                    </div>
                    <div class="product-actions">
                        <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="../product.php?id=<?php echo $product['id']; ?>" class="btn btn-view">
                            <i class="fas fa-eye"></i>
                        </a>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="feature">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            <button type="submit" class="btn btn-feature" title="Toggle Featured">
                                <i class="fas fa-star"></i>
                            </button>
                        </form>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            <button type="submit" class="btn btn-toggle" title="Toggle Status">
                                <i class="fas fa-toggle-on"></i>
                            </button>
                        </form>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            <button type="submit" class="btn btn-delete" 
                                    onclick="return confirm('Delete this product? This cannot be undone!')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>