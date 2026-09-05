<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

check_seller_access();

$database = new Database();
$db = $database->getConnection();
$seller_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize_input($_POST['name']);
    $category_id = $_POST['category_id'];
    $description = sanitize_input($_POST['description']);
    $price = $_POST['price'];
    $stock_quantity = $_POST['stock_quantity'];
    $brand = sanitize_input($_POST['brand'] ?? '');
    $sku = sanitize_input($_POST['sku'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Handle image upload
    $image_url = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $image_url = upload_image($_FILES['image']);
        if (!$image_url) {
            $error = "Failed to upload image. Please try again.";
        }
    }
    
    if (!isset($error)) {
        // Generate slug
        $slug = generate_slug($name);
        
        // Check if slug exists
        $check_slug = "SELECT id FROM products WHERE slug = ?";
        $stmt = $db->prepare($check_slug);
        $stmt->execute([$slug]);
        
        if ($stmt->rowCount() > 0) {
            $slug .= '-' . uniqid();
        }
        
        // Insert product
        $query = "INSERT INTO products (seller_id, category_id, name, slug, description, price, stock_quantity, image_url, brand, sku, status) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$seller_id, $category_id, $name, $slug, $description, $price, $stock_quantity, $image_url, $brand, $sku, $status])) {
            $_SESSION['success'] = "Product added successfully!";
            header("Location: products.php");
            exit();
        } else {
            $error = "Failed to add product. Please try again.";
        }
    }
}

// Get categories
$categories_query = "SELECT * FROM categories WHERE status = 'active' ORDER BY name";
$categories_stmt = $db->query($categories_query);
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - Seller Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f4f8; }
        
        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
        }
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f4f8;
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
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            font-size: 1rem;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #D32F2F;
            box-shadow: 0 0 0 3px rgba(211,47,47,0.1);
        }
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: #D32F2F;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-submit:hover {
            background: #B71C1C;
            transform: translateY(-2px);
        }
        .btn-back {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 20px;
            background: #64748b;
            color: white;
            text-decoration: none;
            border-radius: 5px;
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
    </style>
</head>
<body>
    <div class="container">
        <a href="products.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Products
        </a>
        
        <div class="form-container">
            <div class="form-header">
                <h1><i class="fas fa-plus-circle"></i> Add New Product</h1>
                <p>Fill in the details below to add a new product to your store</p>
            </div>
            
            <?php if(isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Product Name *</label>
                    <input type="text" name="name" required placeholder="Enter product name">
                </div>
                
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category_id" required>
                        <option value="">Select category</option>
                        <?php foreach($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Price (ZMW) *</label>
                    <input type="number" name="price" step="0.01" min="0" required placeholder="0.00">
                </div>
                
                <div class="form-group">
                    <label>Stock Quantity *</label>
                    <input type="number" name="stock_quantity" min="0" required placeholder="0">
                </div>
                
                <div class="form-group">
                    <label>Description *</label>
                    <textarea name="description" rows="5" required placeholder="Describe your product..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Brand</label>
                    <input type="text" name="brand" placeholder="Enter brand name (optional)">
                </div>
                
                <div class="form-group">
                    <label>SKU</label>
                    <input type="text" name="sku" placeholder="Enter SKU (optional)">
                </div>
                
                <div class="form-group">
                    <label>Product Image</label>
                    <input type="file" name="image" accept="image/*">
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Add Product
                </button>
            </form>
        </div>
    </div>
</body>
</html>