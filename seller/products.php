<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';

check_seller_access();

$database = new Database();
$db = $database->getConnection();
$seller_id = $_SESSION['user_id'];

// Handle product deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_product'])) {
    $product_id = $_POST['product_id'];
    
    $query = "DELETE FROM products WHERE id = ? AND seller_id = ?";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$product_id, $seller_id])) {
        $_SESSION['success'] = "Product deleted successfully!";
    } else {
        $_SESSION['error'] = "Failed to delete product!";
    }
    
    header("Location: products.php");
    exit();
}

// Get seller's products
$query = "SELECT p.*, c.name as category_name, 
          (SELECT COUNT(*) FROM order_items oi WHERE oi.product_id = p.id) as times_ordered 
          FROM products p 
          JOIN categories c ON p.category_id = c.id 
          WHERE p.seller_id = ? 
          ORDER BY p.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$seller_id]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Products - Seller Dashboard</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .add-product-btn {
            padding: 12px 24px;
            background: #D32F2F;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
        }
        .add-product-btn:hover {
            background: #B71C1C;
            transform: translateY(-2px);
        }
        
        .products-table {
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
        .product-thumbnail {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
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
        .btn:hover { opacity: 0.8; }
        
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-inactive { background: #fee2e2; color: #991b1b; }
        .badge-out { background: #fef3c7; color: #92400e; }
        
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
            <li><a href="products.php" class="active"><i class="fas fa-box"></i> My Products</a></li>
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
            <h1><i class="fas fa-box"></i> My Products</h1>
            <a href="add_product.php" class="add-product-btn">
                <i class="fas fa-plus"></i> Add New Product
            </a>
        </div>
        
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <div class="products-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Orders</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($products as $product): ?>
                    <tr>
                        <td>
                            <img src="<?php echo $product['image_url'] ?: 'https://placehold.co/50x50'; ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                 class="product-thumbnail"
                                 onerror="this.src='https://placehold.co/50x50'">
                        </td>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                        <td>ZMW <?php echo number_format($product['price'], 2); ?></td>
                        <td><?php echo $product['stock_quantity']; ?></td>
                        <td>
                            <?php
                            $status_class = 'active';
                            if ($product['status'] == 'inactive') $status_class = 'inactive';
                            if ($product['status'] == 'out_of_stock') $status_class = 'out';
                            ?>
                            <span class="badge badge-<?php echo $status_class; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $product['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo $product['times_ordered']; ?></td>
                        <td>
                            <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="../product.php?id=<?php echo $product['id']; ?>" class="btn btn-view">
                                <i class="fas fa-eye"></i>
                            </a>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <button type="submit" name="delete_product" class="btn btn-delete" 
                                        onclick="return confirm('Delete this product?')">
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