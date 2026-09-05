<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth_check.php';

// Check if user is logged in
require_login();

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Get user details
$user_query = "SELECT * FROM users WHERE id = ?";
$user_stmt = $db->prepare($user_query);
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Get cart items
$cart_query = "SELECT c.*, p.name, p.price, p.image_url, p.stock_quantity, p.seller_id,
               u.full_name as seller_name
               FROM carts c 
               JOIN products p ON c.product_id = p.id 
               JOIN users u ON p.seller_id = u.id
               WHERE c.user_id = ?";
$cart_stmt = $db->prepare($cart_query);
$cart_stmt->execute([$user_id]);
$cart_items = $cart_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$shipping_fee = 50.00; // Flat shipping rate
$tax_rate = 0.16; // 16% VAT
$tax_amount = $subtotal * $tax_rate;
$total_amount = $subtotal + $shipping_fee + $tax_amount;

// Handle checkout
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['place_order'])) {
    $shipping_address = sanitize_input($_POST['shipping_address']);
    $billing_address = sanitize_input($_POST['billing_address'] ?? $shipping_address);
    $payment_method = sanitize_input($_POST['payment_method']);
    $notes = sanitize_input($_POST['notes'] ?? '');
    
    // Validate cart is not empty
    if (count($cart_items) == 0) {
        $_SESSION['error'] = "Your cart is empty!";
        header("Location: cart.php");
        exit();
    }
    
    // Validate stock
    foreach ($cart_items as $item) {
        if ($item['quantity'] > $item['stock_quantity']) {
            $_SESSION['error'] = "Insufficient stock for " . $item['name'];
            header("Location: cart.php");
            exit();
        }
    }
    
    try {
        // Begin transaction
        $db->beginTransaction();
        
        // Create order
        $order_number = 'ORD' . date('Ymd') . strtoupper(uniqid());
        $order_query = "INSERT INTO orders (order_number, user_id, total_amount, shipping_address, billing_address, payment_method, notes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
        $order_stmt = $db->prepare($order_query);
        $order_stmt->execute([
            $order_number, 
            $user_id, 
            $total_amount, 
            $shipping_address, 
            $billing_address, 
            $payment_method,
            $notes
        ]);
        $order_id = $db->lastInsertId();
        
        // Insert order items and update stock
        foreach ($cart_items as $item) {
            $item_subtotal = $item['price'] * $item['quantity'];
            
            // Insert order item
            $item_query = "INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) 
                           VALUES (?, ?, ?, ?, ?)";
            $item_stmt = $db->prepare($item_query);
            $item_stmt->execute([
                $order_id, 
                $item['product_id'], 
                $item['quantity'], 
                $item['price'], 
                $item_subtotal
            ]);
            
            // Update product stock
            $stock_query = "UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?";
            $stock_stmt = $db->prepare($stock_query);
            $stock_stmt->execute([$item['quantity'], $item['product_id']]);
            
            // Update product status if out of stock
            $status_query = "UPDATE products SET status = 'out_of_stock' WHERE id = ? AND stock_quantity <= 0";
            $status_stmt = $db->prepare($status_query);
            $status_stmt->execute([$item['product_id']]);
        }
        
        // Create payment record
        $transaction_id = 'TXN' . strtoupper(uniqid());
        $payment_query = "INSERT INTO payments (order_id, transaction_id, amount, payment_method, status) 
                          VALUES (?, ?, ?, ?, 'pending')";
        $payment_stmt = $db->prepare($payment_query);
        $payment_stmt->execute([$order_id, $transaction_id, $total_amount, $payment_method]);
        
        // Clear cart
        $clear_query = "DELETE FROM carts WHERE user_id = ?";
        $clear_stmt = $db->prepare($clear_query);
        $clear_stmt->execute([$user_id]);
        
        // Commit transaction
        $db->commit();
        
        // Redirect to payment page
        header("Location: payment.php?order_id=" . $order_id);
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollBack();
        $_SESSION['error'] = "Checkout failed: " . $e->getMessage();
        header("Location: cart.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Msika Premium</title>
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
            color: #1e293b;
        }
        
        .checkout-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
        }
        
        .checkout-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .checkout-header h1 {
            font-size: 2rem;
            color: #0f172a;
            margin-bottom: 10px;
        }
        .checkout-header p {
            color: #64748b;
        }
        
        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }
        
        .checkout-form {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        .form-section h2 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            color: #0f172a;
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
        
        .order-summary {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        .order-summary h2 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            color: #0f172a;
        }
        
        .cart-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .cart-item img {
            width: 60px;
            height: 60px;
            object-fit: contain;
            background: #f8fafc;
            border-radius: 8px;
        }
        .cart-item-info {
            flex: 1;
        }
        .cart-item-name {
            font-weight: 500;
            margin-bottom: 5px;
        }
        .cart-item-price {
            color: #64748b;
            font-size: 0.9rem;
        }
        .cart-item-total {
            font-weight: 600;
            text-align: right;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
        }
        .summary-row.total {
            font-size: 1.3rem;
            font-weight: 700;
            border-top: 2px solid #e2e8f0;
            margin-top: 10px;
            padding-top: 20px;
        }
        
        .payment-methods {
            display: grid;
            gap: 10px;
            margin-top: 15px;
        }
        .payment-method {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .payment-method:hover {
            border-color: #D32F2F;
        }
        .payment-method.selected {
            border-color: #D32F2F;
            background: #fef2f2;
        }
        .payment-method input[type="radio"] {
            margin-right: 5px;
        }
        .payment-method i {
            font-size: 1.3rem;
            color: #64748b;
        }
        
        .btn-place-order {
            width: 100%;
            padding: 15px;
            background: #D32F2F;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
        }
        .btn-place-order:hover {
            background: #B71C1C;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(211,47,47,0.3);
        }
        
        .secure-checkout {
            text-align: center;
            margin-top: 20px;
            color: #64748b;
            font-size: 0.9rem;
        }
        .secure-checkout i {
            color: #10b981;
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
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        @media (max-width: 768px) {
            .checkout-grid {
                grid-template-columns: 1fr;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            .order-summary {
                position: static;
            }
        }
    </style>
</head>
<body>
    <div class="checkout-container">
        <div class="checkout-header">
            <h1><i class="fas fa-lock" style="color: #D32F2F;"></i> Secure Checkout</h1>
            <p>Complete your order securely</p>
        </div>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> 
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <?php if(count($cart_items) > 0): ?>
        <div class="checkout-grid">
            <div class="checkout-form">
                <form method="POST" action="" id="checkoutForm">
                    <div class="form-section">
                        <h2><i class="fas fa-user"></i> Contact Information</h2>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Full Name *</label>
                                <input type="text" value="<?php echo htmlspecialchars($user['full_name']); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Email *</label>
                                <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Phone Number *</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h2><i class="fas fa-map-marker-alt"></i> Shipping Address</h2>
                        <div class="form-group">
                            <label>Full Address *</label>
                            <textarea name="shipping_address" rows="3" required placeholder="Enter your complete shipping address"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h2><i class="fas fa-credit-card"></i> Payment Method</h2>
                        <div class="payment-methods">
                            <label class="payment-method selected">
                                <input type="radio" name="payment_method" value="mobile_money" checked>
                                <i class="fas fa-mobile-alt"></i>
                                <span>Mobile Money</span>
                            </label>
                            <label class="payment-method">
                                <input type="radio" name="payment_method" value="credit_card">
                                <i class="fas fa-credit-card"></i>
                                <span>Credit/Debit Card</span>
                            </label>
                            <label class="payment-method">
                                <input type="radio" name="payment_method" value="bank_transfer">
                                <i class="fas fa-university"></i>
                                <span>Bank Transfer</span>
                            </label>
                            <label class="payment-method">
                                <input type="radio" name="payment_method" value="cash_on_delivery">
                                <i class="fas fa-money-bill-wave"></i>
                                <span>Cash on Delivery</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h2><i class="fas fa-sticky-note"></i> Additional Notes</h2>
                        <div class="form-group">
                            <label>Order Notes (Optional)</label>
                            <textarea name="notes" rows="3" placeholder="Any special instructions for your order"></textarea>
                        </div>
                    </div>
                    
                    <button type="submit" name="place_order" class="btn-place-order">
                        <i class="fas fa-lock"></i> Place Order - ZMW <?php echo number_format($total_amount, 2); ?>
                    </button>
                    
                    <div class="secure-checkout">
                        <i class="fas fa-shield-alt"></i> Your payment is secured with 256-bit encryption
                    </div>
                </form>
            </div>
            
            <div class="order-summary">
                <h2>Order Summary</h2>
                
                <?php foreach($cart_items as $item): ?>
                <div class="cart-item">
                    <img src="<?php echo $item['image_url'] ?: 'https://placehold.co/60x60'; ?>" 
                         alt="<?php echo htmlspecialchars($item['name']); ?>"
                         onerror="this.src='https://placehold.co/60x60'">
                    <div class="cart-item-info">
                        <div class="cart-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                        <div class="cart-item-price">
                            ZMW <?php echo number_format($item['price'], 2); ?> × <?php echo $item['quantity']; ?>
                        </div>
                        <div class="cart-item-price" style="font-size: 0.8rem; color: #94a3b8;">
                            Seller: <?php echo htmlspecialchars($item['seller_name']); ?>
                        </div>
                    </div>
                    <div class="cart-item-total">
                        ZMW <?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div style="margin-top: 20px;">
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span>ZMW <?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Shipping</span>
                        <span>ZMW <?php echo number_format($shipping_fee, 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>VAT (16%)</span>
                        <span>ZMW <?php echo number_format($tax_amount, 2); ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Total</span>
                        <span>ZMW <?php echo number_format($total_amount, 2); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 60px; background: white; border-radius: 15px;">
            <i class="fas fa-shopping-cart" style="font-size: 4rem; color: #cbd5e1;"></i>
            <h2 style="margin: 20px 0;">Your cart is empty</h2>
            <p style="margin-bottom: 20px;">Browse our products and add items to your cart</p>
            <a href="index.php" style="display: inline-block; padding: 12px 30px; background: #D32F2F; color: white; text-decoration: none; border-radius: 8px;">
                Continue Shopping
            </a>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Payment method selection
        document.querySelectorAll('.payment-method').forEach(function(method) {
            method.addEventListener('click', function() {
                document.querySelectorAll('.payment-method').forEach(function(m) {
                    m.classList.remove('selected');
                });
                this.classList.add('selected');
            });
        });
        
        // Form validation
        document.getElementById('checkoutForm')?.addEventListener('submit', function(e) {
            const phone = this.querySelector('input[name="phone"]');
            const address = this.querySelector('textarea[name="shipping_address"]');
            
            if (!phone.value.trim()) {
                e.preventDefault();
                alert('Please enter your phone number');
                phone.focus();
                return;
            }
            
            if (!address.value.trim()) {
                e.preventDefault();
                alert('Please enter your shipping address');
                address.focus();
                return;
            }
        });
    </script>
</body>
</html>