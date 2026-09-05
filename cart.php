<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$database = new Database();
$db = $database->getConnection();

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart'])) {
    if (!isset($_SESSION['user_id'])) {
        // Redirect to login with return URL
        $_SESSION['redirect_after_login'] = 'cart.php';
        header("Location: auth/login.php");
        exit();
    }
    
    $user_id = $_SESSION['user_id'];
    $product_id = $_POST['product_id'];
    $quantity = isset($_POST['quantity']) ? $_POST['quantity'] : 1;
    
    // Check if product exists and has stock
    $product_query = "SELECT * FROM products WHERE id = ? AND status = 'active'";
    $product_stmt = $db->prepare($product_query);
    $product_stmt->execute([$product_id]);
    $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        // Check if item already in cart
        $cart_query = "SELECT * FROM carts WHERE user_id = ? AND product_id = ?";
        $cart_stmt = $db->prepare($cart_query);
        $cart_stmt->execute([$user_id, $product_id]);
        $existing_cart = $cart_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_cart) {
            // Update quantity
            $new_quantity = $existing_cart['quantity'] + $quantity;
            $update_query = "UPDATE carts SET quantity = ? WHERE user_id = ? AND product_id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$new_quantity, $user_id, $product_id]);
        } else {
            // Insert new cart item
            $insert_query = "INSERT INTO carts (user_id, product_id, quantity) VALUES (?, ?, ?)";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->execute([$user_id, $product_id, $quantity]);
        }
        
        $_SESSION['success'] = "Product added to cart successfully!";
    } else {
        $_SESSION['error'] = "Product not found or out of stock!";
    }
    
    header("Location: cart.php");
    exit();
}

// Get cart items
$cart_items = [];
$total_amount = 0;

if (isset($_SESSION['user_id'])) {
    $cart_query = "SELECT c.*, p.name, p.price, p.image_url, p.stock_quantity 
                   FROM carts c 
                   JOIN products p ON c.product_id = p.id 
                   WHERE c.user_id = ?";
    $cart_stmt = $db->prepare($cart_query);
    $cart_stmt->execute([$_SESSION['user_id']]);
    $cart_items = $cart_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($cart_items as $item) {
        $total_amount += $item['price'] * $item['quantity'];
    }
}

// Display cart page (simplified for testing)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Msika Premium</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <h1>Your Shopping Cart</h1>
    
    <?php if(isset($_SESSION['success'])): ?>
        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 20px;">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['error'])): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 20px;">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <?php if(count($cart_items) > 0): ?>
        <table border="1" style="width: 100%; margin: 20px;">
            <tr>
                <th>Product</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Total</th>
            </tr>
            <?php foreach($cart_items as $item): ?>
            <tr>
                <td>
                    <img src="<?php echo $item['image_url']; ?>" width="50" height="50" style="object-fit: contain;">
                    <?php echo htmlspecialchars($item['name']); ?>
                </td>
                <td>ZMW <?php echo number_format($item['price'], 2); ?></td>
                <td><?php echo $item['quantity']; ?></td>
                <td>ZMW <?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <h2>Total: ZMW <?php echo number_format($total_amount, 2); ?></h2>
        <a href="checkout.php">Proceed to Checkout</a>
    <?php else: ?>
        <p>Your cart is empty. <a href="index.php">Continue shopping</a></p>
    <?php endif; ?>
</body>
</html>