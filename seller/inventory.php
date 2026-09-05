<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

check_seller_access();

$database = new Database();
$db = $database->getConnection();
$seller_id = $_SESSION['user_id'];

// Handle stock updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_stock'])) {
    $product_id = $_POST['product_id'];
    $new_stock = $_POST['new_stock'];
    
    // Verify product belongs to seller
    $verify_query = "SELECT id FROM products WHERE id = ? AND seller_id = ?";
    $verify_stmt = $db->prepare($verify_query);
    $verify_stmt->execute([$product_id, $seller_id]);
    
    if ($verify_stmt->rowCount() > 0) {
        $query = "UPDATE products SET stock_quantity = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$new_stock, $product_id])) {
            // Update status based on stock
            $status = $new_stock == 0 ? 'out_of_stock' : 'active';
            $update_status = "UPDATE products SET status = ? WHERE id = ?";
            $status_stmt = $db->prepare($update_status);
            $status_stmt->execute([$status, $product_id]);
            
            $_SESSION['success'] = "Stock updated successfully!";
        }
    }
    
    header("Location: inventory.php");
    exit();
}

// Handle bulk stock update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_update'])) {
    $product_ids = $_POST['product_ids'] ?? [];
    $stock_values = $_POST['stock_values'] ?? [];
    
    foreach ($product_ids as $key => $product_id) {
        $new_stock = $stock_values[$key];
        
        $query = "UPDATE products SET stock_quantity = ? WHERE id = ? AND seller_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$new_stock, $product_id, $seller_id]);
        
        // Update status
        $status = $new_stock == 0 ? 'out_of_stock' : 'active';
        $update_status = "UPDATE products SET status = ? WHERE id = ?";
        $status_stmt = $db->prepare($update_status);
        $status_stmt->execute([$status, $product_id]);
    }
    
    $_SESSION['success'] = "Bulk stock update successful!";
    header("Location: inventory.php");
    exit();
}

// Get inventory items
$query = "SELECT p.*, c.name as category_name,
          (SELECT COUNT(*) FROM order_items oi WHERE oi.product_id = p.id) as times_ordered,
          (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.product_id = p.id) as total_sold
          FROM products p 
          JOIN categories c ON p.category_id = c.id 
          WHERE p.seller_id = ?
          ORDER BY p.stock_quantity ASC";
$stmt = $db->prepare($query);
$stmt->execute([$seller_id]);
$inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get inventory statistics
$total_products = count($inventory);
$total_stock = array_sum(array_column($inventory, 'stock_quantity'));
$out_of_stock = count(array_filter($inventory, fn($i) => $i['stock_quantity'] == 0));
$low_stock = count(array_filter($inventory, fn($i) => $i['stock_quantity'] > 0 && $i['stock_quantity'] <= 10));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - Seller Dashboard</title>
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
        
        .inventory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        .inventory-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .product-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .product-name {
            font-weight: 600;
            margin-bottom: 10px;
        }
        .stock-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .stock-input {
            width: 80px;
            padding: 8px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            text-align: center;
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
        
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-in-stock { background: #d1fae5; color: #065f46; }
        .badge-low-stock { background: #fef3c7; color: #92400e; }
        .badge-out-of-stock { background: #fee2e2; color: #991b1b; }
        
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
        .btn-bulk {
            padding: 10px 20px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
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
            <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a></li>
            <li><a href="inventory.php" class="active"><i class="fas fa-warehouse"></i> Inventory</a></li>
            <li><a href="analytics.php"><i class="fas fa-chart-line"></i> Analytics</a></li>
            <li><a href="reviews.php"><i class="fas fa-star"></i> Reviews</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="seller-main">
        <div class="seller-header">
            <h1><i class="fas fa-warehouse"></i> Inventory Management</h1>
            <button class="btn-bulk" onclick="toggleBulkMode()">
                <i class="fas fa-edit"></i> Bulk Update
            </button>
        </div>
        
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-cards">
            <div class="stat-card">
                <i class="fas fa-box"></i>
                <h3><?php echo $total_products; ?></h3>
                <p>Total Products</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-cubes"></i>
                <h3><?php echo $total_stock; ?></h3>
                <p>Total Stock</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-exclamation-triangle"></i>
                <h3><?php echo $out_of_stock; ?></h3>
                <p>Out of Stock</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-bell"></i>
                <h3><?php echo $low_stock; ?></h3>
                <p>Low Stock</p>
            </div>
        </div>
        
        <form method="POST" action="" id="inventoryForm">
            <input type="hidden" name="bulk_update" value="1">
            <div class="inventory-grid">
                <?php foreach($inventory as $item): ?>
                <div class="inventory-card">
                    <img src="<?php echo $item['image_url'] ?: 'https://placehold.co/300x200'; ?>" 
                         alt="<?php echo htmlspecialchars($item['name']); ?>" 
                         class="product-image"
                         onerror="this.src='https://placehold.co/300x200'">
                    <div class="product-name"><?php echo htmlspecialchars($item['name']); ?></div>
                    <div class="stock-info">
                        <span>
                            <?php if($item['stock_quantity'] == 0): ?>
                                <span class="badge badge-out-of-stock">Out of Stock</span>
                            <?php elseif($item['stock_quantity'] <= 10): ?>
                                <span class="badge badge-low-stock">Low Stock</span>
                            <?php else: ?>
                                <span class="badge badge-in-stock">In Stock</span>
                            <?php endif; ?>
                        </span>
                        <span style="font-weight: 600;"><?php echo $item['stock_quantity']; ?> units</span>
                    </div>
                    
                    <div class="stock-info">
                        <span>Sold: <?php echo $item['total_sold']; ?></span>
                        <span>Orders: <?php echo $item['times_ordered']; ?></span>
                    </div>
                    
                    <input type="hidden" name="product_ids[]" value="<?php echo $item['id']; ?>">
                    <input type="number" name="stock_values[]" value="<?php echo $item['stock_quantity']; ?>" 
                           class="stock-input" min="0">
                    
                    <?php if(!isset($_POST['bulk_update'])): ?>
                    <form method="POST" action="" style="display: inline;">
                        <input type="hidden" name="update_stock" value="1">
                        <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                        <input type="hidden" name="new_stock" value="<?php echo $item['stock_quantity']; ?>" class="hidden-stock">
                        <button type="submit" class="btn-update">
                            <i class="fas fa-save"></i> Update
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div id="bulkActions" style="display: none; margin-top: 20px; text-align: center;">
                <button type="submit" class="btn-bulk">
                    <i class="fas fa-save"></i> Save All Changes
                </button>
            </div>
        </form>
    </div>
    
    <script>
        function toggleBulkMode() {
            var bulkActions = document.getElementById('bulkActions');
            var stockInputs = document.querySelectorAll('.stock-input');
            
            if (bulkActions.style.display === 'none') {
                bulkActions.style.display = 'block';
                stockInputs.forEach(function(input) {
                    input.disabled = false;
                });
            } else {
                bulkActions.style.display = 'none';
                stockInputs.forEach(function(input) {
                    input.disabled = true;
                });
            }
        }
        
        // Update hidden stock values when input changes
        document.querySelectorAll('.stock-input').forEach(function(input) {
            input.addEventListener('change', function() {
                var card = this.closest('.inventory-card');
                var hiddenStock = card.querySelector('.hidden-stock');
                if (hiddenStock) {
                    hiddenStock.value = this.value;
                }
            });
        });
    </script>
</body>
</html>