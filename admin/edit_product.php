<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

check_admin_access();

$database = new Database();
$db = $database->getConnection();

$product_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Get product details
$product_query = "SELECT p.*, c.name as category_name, u.full_name as seller_name, u.email as seller_email
                  FROM products p 
                  JOIN categories c ON p.category_id = c.id 
                  JOIN users u ON p.seller_id = u.id 
                  WHERE p.id = ?";
$product_stmt = $db->prepare($product_query);
$product_stmt->execute([$product_id]);
$product = $product_stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    $_SESSION['error'] = "Product not found!";
    header("Location: products.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_product'])) {
    $name = sanitize_input($_POST['name']);
    $category_id = $_POST['category_id'];
    $description = sanitize_input($_POST['description']);
    $price = $_POST['price'];
    $stock_quantity = $_POST['stock_quantity'];
    $brand = sanitize_input($_POST['brand'] ?? '');
    $sku = sanitize_input($_POST['sku'] ?? '');
    $status = $_POST['status'];
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    
    // Handle image upload
    $image_url = $product['image_url']; // Keep existing image by default
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_result = upload_image($_FILES['image']);
        if ($upload_result) {
            // Delete old image if exists
            if ($product['image_url'] && file_exists('../' . $product['image_url'])) {
                unlink('../' . $product['image_url']);
            }
            $image_url = $upload_result;
        }
    }
    
    try {
        // Begin transaction
        $db->beginTransaction();
        
        // Update product
        $update_query = "UPDATE products SET 
                         name = ?, 
                         category_id = ?, 
                         description = ?, 
                         price = ?, 
                         stock_quantity = ?, 
                         image_url = ?, 
                         brand = ?, 
                         sku = ?, 
                         status = ?, 
                         is_featured = ? 
                         WHERE id = ?";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute([
            $name, 
            $category_id, 
            $description, 
            $price, 
            $stock_quantity, 
            $image_url, 
            $brand, 
            $sku, 
            $status, 
            $is_featured, 
            $product_id
        ]);
        
        // If status is out_of_stock and stock is 0, update accordingly
        if ($stock_quantity == 0 && $status != 'inactive') {
            $status_update = "UPDATE products SET status = 'out_of_stock' WHERE id = ?";
            $status_stmt = $db->prepare($status_update);
            $status_stmt->execute([$product_id]);
        }
        
        // Commit transaction
        $db->commit();
        
        // Log admin action
        log_admin_action($db, $_SESSION['user_id'], 'update_product', 
                        "Updated product ID: $product_id - Name: $name");
        
        $_SESSION['success'] = "Product updated successfully!";
        header("Location: products.php");
        exit();
        
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Failed to update product: " . $e->getMessage();
        header("Location: edit_product.php?id=" . $product_id);
        exit();
    }
}

// Get all categories for dropdown
$categories_query = "SELECT * FROM categories WHERE status = 'active' ORDER BY name";
$categories_stmt = $db->query($categories_query);
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get product statistics
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM order_items WHERE product_id = ?) as times_ordered,
                (SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE product_id = ?) as units_sold,
                (SELECT COALESCE(SUM(subtotal), 0) FROM order_items WHERE product_id = ?) as total_revenue,
                (SELECT COUNT(*) FROM reviews WHERE product_id = ?) as review_count,
                (SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE product_id = ?) as avg_rating";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute([$product_id, $product_id, $product_id, $product_id, $product_id]);
$product_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - Msika Admin</title>
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
        
        .page-header {
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
            grid-template-columns: 1fr 350px;
            gap: 20px;
        }
        
        .edit-form {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        .form-section h2 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-section h2 i {
            color: #D32F2F;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #D32F2F;
            box-shadow: 0 0 0 3px rgba(211,47,47,0.1);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .current-image {
            text-align: center;
            margin-bottom: 20px;
        }
        .current-image img {
            max-width: 200px;
            max-height: 200px;
            object-fit: contain;
            border-radius: 10px;
            background: #f8fafc;
            padding: 10px;
        }
        
        .product-stats {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        .product-stats h2 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            color: #1e293b;
        }
        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .stat-item:last-child {
            border-bottom: none;
        }
        .stat-label {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #64748b;
        }
        .stat-label i {
            color: #D32F2F;
            width: 20px;
        }
        .stat-value {
            font-weight: 600;
            color: #1e293b;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .btn-back {
            background: #64748b;
            color: white;
        }
        .btn-submit {
            background: #D32F2F;
            color: white;
            width: 100%;
            justify-content: center;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .seller-info {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .seller-info p {
            margin-bottom: 5px;
            color: #64748b;
        }
        .seller-info strong {
            color: #1e293b;
        }
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            .product-stats {
                position: static;
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
            <li><a href="products.php" class="active"><i class="fas fa-box"></i> Manage Products</a></li>
            <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> Manage Orders</a></li>
            <li><a href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
            <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="admin-main">
        <div class="page-header">
            <h1><i class="fas fa-edit"></i> Edit Product</h1>
            <a href="products.php" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Back to Products
            </a>
        </div>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> 
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <div class="content-grid">
            <div class="edit-form">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="update_product" value="1">
                    
                    <div class="form-section">
                        <h2><i class="fas fa-info-circle"></i> Product Information</h2>
                        
                        <div class="form-group">
                            <label>Product Name *</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Category *</label>
                                <select name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" 
                                            <?php echo $product['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Brand</label>
                                <input type="text" name="brand" value="<?php echo htmlspecialchars($product['brand'] ?? ''); ?>" placeholder="Enter brand name">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Price (ZMW) *</label>
                                <input type="number" name="price" step="0.01" min="0" 
                                       value="<?php echo $product['price']; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Stock Quantity *</label>
                                <input type="number" name="stock_quantity" min="0" 
                                       value="<?php echo $product['stock_quantity']; ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>SKU</label>
                            <input type="text" name="sku" value="<?php echo htmlspecialchars($product['sku'] ?? ''); ?>" placeholder="Enter SKU">
                        </div>
                        
                        <div class="form-group">
                            <label>Description *</label>
                            <textarea name="description" rows="5" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h2><i class="fas fa-image"></i> Product Image</h2>
                        
                        <div class="current-image">
                            <img src="../<?php echo $product['image_url'] ?: 'https://placehold.co/400x300'; ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 onerror="this.src='https://placehold.co/400x300'">
                        </div>
                        
                        <div class="form-group">
                            <label>Upload New Image</label>
                            <input type="file" name="image" accept="image/*">
                            <small style="display: block; margin-top: 5px; color: #64748b;">
                                Leave empty to keep current image. Supported formats: JPG, PNG, GIF
                            </small>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h2><i class="fas fa-cog"></i> Product Settings</h2>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status">
                                    <option value="active" <?php echo $product['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $product['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="out_of_stock" <?php echo $product['status'] == 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Featured Product</label>
                                <div class="checkbox-group" style="padding-top: 10px;">
                                    <input type="checkbox" name="is_featured" id="is_featured" 
                                           <?php echo $product['is_featured'] ? 'checked' : ''; ?>>
                                    <label for="is_featured" style="margin: 0;">Mark as featured</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-submit">
                        <i class="fas fa-save"></i> Update Product
                    </button>
                </form>
            </div>
            
            <div class="product-stats">
                <h2><i class="fas fa-chart-bar"></i> Product Statistics</h2>
                
                <div class="seller-info">
                    <p><strong>Seller:</strong> <?php echo htmlspecialchars($product['seller_name']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($product['seller_email']); ?></p>
                    <p><strong>Category:</strong> <?php echo htmlspecialchars($product['category_name']); ?></p>
                    <p><strong>Created:</strong> <?php echo date('F j, Y', strtotime($product['created_at'])); ?></p>
                </div>
                
                <div class="stat-item">
                    <span class="stat-label">
                        <i class="fas fa-shopping-cart"></i> Times Ordered
                    </span>
                    <span class="stat-value"><?php echo $product_stats['times_ordered']; ?></span>
                </div>
                
                <div class="stat-item">
                    <span class="stat-label">
                        <i class="fas fa-box"></i> Units Sold
                    </span>
                    <span class="stat-value"><?php echo $product_stats['units_sold']; ?></span>
                </div>
                
                <div class="stat-item">
                    <span class="stat-label">
                        <i class="fas fa-dollar-sign"></i> Total Revenue
                    </span>
                    <span class="stat-value">ZMW <?php echo number_format($product_stats['total_revenue'], 2); ?></span>
                </div>
                
                <div class="stat-item">
                    <span class="stat-label">
                        <i class="fas fa-star"></i> Average Rating
                    </span>
                    <span class="stat-value">
                        <?php echo number_format($product_stats['avg_rating'], 1); ?> / 5.0
                    </span>
                </div>
                
                <div class="stat-item">
                    <span class="stat-label">
                        <i class="fas fa-comment"></i> Reviews
                    </span>
                    <span class="stat-value"><?php echo $product_stats['review_count']; ?></span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>