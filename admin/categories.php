<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

check_admin_access();

$database = new Database();
$db = $database->getConnection();

// Handle category actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_category':
            $name = sanitize_input($_POST['name']);
            $slug = generate_slug($name);
            $icon = sanitize_input($_POST['icon'] ?? 'fa-tag');
            $description = sanitize_input($_POST['description'] ?? '');
            
            // Check if slug exists
            $check_query = "SELECT id FROM categories WHERE slug = ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$slug]);
            
            if ($check_stmt->rowCount() > 0) {
                $slug .= '-' . uniqid();
            }
            
            $query = "INSERT INTO categories (name, slug, icon, description) VALUES (?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$name, $slug, $icon, $description])) {
                log_admin_action($db, $_SESSION['user_id'], 'add_category', "Added category: $name");
                $_SESSION['success'] = "Category added successfully!";
            }
            break;
            
        case 'update_category':
            $category_id = $_POST['category_id'];
            $name = sanitize_input($_POST['name']);
            $icon = sanitize_input($_POST['icon'] ?? 'fa-tag');
            $description = sanitize_input($_POST['description'] ?? '');
            $status = $_POST['status'] ?? 'active';
            
            $query = "UPDATE categories SET name = ?, icon = ?, description = ?, status = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$name, $icon, $description, $status, $category_id])) {
                log_admin_action($db, $_SESSION['user_id'], 'update_category', "Updated category ID: $category_id");
                $_SESSION['success'] = "Category updated successfully!";
            }
            break;
            
        case 'delete_category':
            $category_id = $_POST['category_id'];
            
            // Check if category has products
            $check_products = "SELECT COUNT(*) FROM products WHERE category_id = ?";
            $check_stmt = $db->prepare($check_products);
            $check_stmt->execute([$category_id]);
            $product_count = $check_stmt->fetchColumn();
            
            if ($product_count > 0) {
                $_SESSION['error'] = "Cannot delete category with $product_count products. Move or delete products first.";
            } else {
                $query = "DELETE FROM categories WHERE id = ?";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$category_id])) {
                    log_admin_action($db, $_SESSION['user_id'], 'delete_category', "Deleted category ID: $category_id");
                    $_SESSION['success'] = "Category deleted successfully!";
                }
            }
            break;
    }
    
    header("Location: categories.php");
    exit();
}

// Get all categories with product counts
$query = "SELECT c.*, 
          (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count,
          (SELECT COALESCE(SUM(stock_quantity), 0) FROM products WHERE category_id = c.id) as total_stock
          FROM categories c 
          ORDER BY c.name";
$stmt = $db->query($query);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Msika Admin</title>
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
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-container, .categories-list {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none;
            border-color: #D32F2F;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
            transition: all 0.3s;
        }
        .btn-add { background: #D32F2F; color: white; }
        .btn-edit { background: #3b82f6; color: white; }
        .btn-delete { background: #ef4444; color: white; }
        .btn:hover { opacity: 0.8; }
        
        .category-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            gap: 15px;
        }
        .category-icon {
            width: 40px;
            height: 40px;
            background: #fef2f2;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #D32F2F;
        }
        .category-info {
            flex: 1;
        }
        .category-name {
            font-weight: 600;
            margin-bottom: 5px;
        }
        .category-stats {
            font-size: 0.85rem;
            color: #64748b;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-inactive { background: #fee2e2; color: #991b1b; }
        
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
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
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
            <li><a href="users.php"><i class="fas fa-users"></i> Manage Users</a></li>
            <li><a href="sellers.php"><i class="fas fa-store"></i> Manage Sellers</a></li>
            <li><a href="products.php"><i class="fas fa-box"></i> Manage Products</a></li>
            <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> Manage Orders</a></li>
            <li><a href="categories.php" class="active"><i class="fas fa-tags"></i> Categories</a></li>
            <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="admin-main">
        <div class="admin-header">
            <h1><i class="fas fa-tags"></i> Manage Categories</h1>
            <p>Total Categories: <?php echo count($categories); ?></p>
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
        
        <div class="content-grid">
            <div class="form-container">
                <h2 style="margin-bottom: 20px;">Add New Category</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_category">
                    
                    <div class="form-group">
                        <label>Category Name *</label>
                        <input type="text" name="name" required placeholder="Enter category name">
                    </div>
                    
                    <div class="form-group">
                        <label>Icon (Font Awesome class)</label>
                        <input type="text" name="icon" placeholder="fa-microchip">
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="3" placeholder="Enter category description"></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-add">
                        <i class="fas fa-plus"></i> Add Category
                    </button>
                </form>
            </div>
            
            <div class="categories-list">
                <h2 style="margin-bottom: 20px;">All Categories</h2>
                <?php foreach($categories as $category): ?>
                <div class="category-item">
                    <div class="category-icon">
                        <i class="fas <?php echo $category['icon'] ?: 'fa-tag'; ?>"></i>
                    </div>
                    <div class="category-info">
                        <div class="category-name"><?php echo htmlspecialchars($category['name']); ?></div>
                        <div class="category-stats">
                            <?php echo $category['product_count']; ?> products | 
                            <?php echo $category['total_stock']; ?> items in stock
                        </div>
                    </div>
                    <span class="badge badge-<?php echo $category['status']; ?>">
                        <?php echo ucfirst($category['status']); ?>
                    </span>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete_category">
                        <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                        <button type="submit" class="btn btn-delete" onclick="return confirm('Delete this category?')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>