<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth_check.php';

require_login();

$database = new Database();
$db = $database->getConnection();

$order_id = isset($_GET['order_id']) ? $_GET['order_id'] : 0;

// Get order details
$query = "SELECT o.*, u.full_name, u.email 
          FROM orders o 
          JOIN users u ON o.user_id = u.id 
          WHERE o.id = ? AND o.user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: index.php");
    exit();
}

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $payment_method = $_POST['payment_method'];
    $transaction_id = 'TXN' . strtoupper(uniqid());
    
    // Process payment based on method
    $payment_successful = false;
    
    switch ($payment_method) {
        case 'mobile_money':
            // Integrate with mobile money API (MTN, Airtel, etc.)
            $phone_number = $_POST['phone_number'];
            // Simulate payment processing
            $payment_successful = true;
            break;
            
        case 'credit_card':
            // Integrate with payment gateway (Stripe, PayPal, etc.)
            $card_number = $_POST['card_number'];
            $expiry = $_POST['expiry'];
            $cvv = $_POST['cvv'];
            // Simulate payment processing
            $payment_successful = true;
            break;
            
        case 'bank_transfer':
            // Process bank transfer
            $bank_name = $_POST['bank_name'];
            $account_number = $_POST['account_number'];
            // Simulate payment processing
            $payment_successful = true;
            break;
            
        case 'cash_on_delivery':
            // Cash on delivery doesn't require immediate payment
            $payment_successful = true;
            break;
    }
    
    if ($payment_successful) {
        // Record payment
        $payment_query = "INSERT INTO payments (order_id, transaction_id, amount, payment_method, status) 
                          VALUES (?, ?, ?, ?, 'completed')";
        $payment_stmt = $db->prepare($payment_query);
        $payment_stmt->execute([$order_id, $transaction_id, $order['total_amount'], $payment_method]);
        
        // Update order status
        $update_query = "UPDATE orders SET payment_status = 'paid', status = 'processing' WHERE id = ?";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute([$order_id]);
        
        $_SESSION['success'] = "Payment successful! Your order is being processed.";
        header("Location: order_confirmation.php?order_id=" . $order_id);
        exit();
    } else {
        $error = "Payment failed. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Msika Premium</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fa; }
        
        .payment-container {
            max-width: 600px;
            margin: 40px auto;
            padding: 20px;
        }
        .payment-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .payment-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f4f8;
        }
        .payment-header i {
            font-size: 3rem;
            color: #D32F2F;
            margin-bottom: 15px;
        }
        .order-summary {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .summary-row.total {
            font-weight: 700;
            font-size: 1.2rem;
            border-top: 2px solid #e2e8f0;
            padding-top: 10px;
            margin-top: 10px;
        }
        .payment-methods {
            margin-bottom: 30px;
        }
        .payment-method {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 10px;
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
            margin-right: 10px;
        }
        .payment-method i {
            font-size: 1.5rem;
            margin-right: 15px;
            color: #64748b;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            font-size: 1rem;
        }
        .btn-pay {
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
        }
        .btn-pay:hover {
            background: #B71C1C;
            transform: translateY(-2px);
        }
        .secure-badge {
            text-align: center;
            margin-top: 20px;
            color: #64748b;
            font-size: 0.9rem;
        }
        .secure-badge i {
            color: #10b981;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="payment-card">
            <div class="payment-header">
                <i class="fas fa-lock"></i>
                <h1>Secure Payment</h1>
                <p>Order #<?php echo $order['order_number']; ?></p>
            </div>
            
            <?php if(isset($error)): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <div class="order-summary">
                <div class="summary-row">
                    <span>Order Total:</span>
                    <span>ZMW <?php echo number_format($order['total_amount'], 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Shipping:</span>
                    <span>ZMW 50.00</span>
                </div>
                <div class="summary-row total">
                    <span>Total Amount:</span>
                    <span>ZMW <?php echo number_format($order['total_amount'] + 50, 2); ?></span>
                </div>
            </div>
            
            <form method="POST" action="">
                <div class="payment-methods">
                    <h3 style="margin-bottom: 15px;">Select Payment Method</h3>
                    
                    <label class="payment-method">
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
                
                <div id="payment_fields">
                    <!-- Dynamic payment fields will be shown here -->
                </div>
                
                <button type="submit" class="btn-pay">
                    <i class="fas fa-lock"></i> Pay ZMW <?php echo number_format($order['total_amount'] + 50, 2); ?>
                </button>
            </form>
            
            <div class="secure-badge">
                <i class="fas fa-shield-alt"></i> Your payment is secured with 256-bit encryption
            </div>
        </div>
    </div>
    
    <script>
        // Dynamic payment fields based on selected method
        document.querySelectorAll('input[name="payment_method"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                var method = this.value;
                var fieldsDiv = document.getElementById('payment_fields');
                
                switch(method) {
                    case 'mobile_money':
                        fieldsDiv.innerHTML = `
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="tel" name="phone_number" required placeholder="Enter mobile money number">
                            </div>
                        `;
                        break;
                    case 'credit_card':
                        fieldsDiv.innerHTML = `
                            <div class="form-group">
                                <label>Card Number</label>
                                <input type="text" name="card_number" required placeholder="1234 5678 9012 3456">
                            </div>
                            <div class="form-group">
                                <label>Expiry Date</label>
                                <input type="text" name="expiry" required placeholder="MM/YY">
                            </div>
                            <div class="form-group">
                                <label>CVV</label>
                                <input type="password" name="cvv" required placeholder="123" maxlength="3">
                            </div>
                        `;
                        break;
                    case 'bank_transfer':
                        fieldsDiv.innerHTML = `
                            <div class="form-group">
                                <label>Bank Name</label>
                                <input type="text" name="bank_name" required placeholder="Enter bank name">
                            </div>
                            <div class="form-group">
                                <label>Account Number</label>
                                <input type="text" name="account_number" required placeholder="Enter account number">
                            </div>
                        `;
                        break;
                    case 'cash_on_delivery':
                        fieldsDiv.innerHTML = `
                            <p style="padding: 15px; background: #fef3c7; border-radius: 8px;">
                                <i class="fas fa-info-circle"></i> You will pay when your order is delivered.
                            </p>
                        `;
                        break;
                }
            });
        });
        
        // Trigger initial display
        document.querySelector('input[name="payment_method"]:checked').dispatchEvent(new Event('change'));
    </script>
</body>
</html>