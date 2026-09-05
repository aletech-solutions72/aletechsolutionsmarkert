<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth_check.php';

require_login();

$database = new Database();
$db = $database->getConnection();

$order_id = isset($_GET['order_id']) ? $_GET['order_id'] : 0;

// Get order details
$query = "SELECT o.*, p.transaction_id, p.payment_method as payment_details
          FROM orders o 
          LEFT JOIN payments p ON o.id = p.order_id 
          WHERE o.id = ? AND o.user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: index.php");
    exit();
}

// Get order items
$items_query = "SELECT oi.*, p.name, p.image_url 
                FROM order_items oi 
                JOIN products p ON oi.product_id = p.id 
                WHERE oi.order_id = ?";
$items_stmt = $db->prepare($items_query);
$items_stmt->execute([$order_id]);
$order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - Msika Premium</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fa; }
        
        .confirmation-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
        }
        .confirmation-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success-icon {
            text-align: center;
            margin-bottom: 30px;
        }
        .success-icon i {
            font-size: 5rem;
            color: #10b981;
        }
        .order-details {
            margin-bottom: 30px;
        }
        .order-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .order-header h1 {
            color: #10b981;
            margin-bottom: 10px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .items-list {
            margin-top: 20px;
        }
        .order-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            gap: 15px;
        }
        .order-item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }
        .item-details {
            flex: 1;
        }
        .total-section {
            margin-top: 20px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 8px;
        }
        .btn-continue {
            display: inline-block;
            padding: 15px 30px;
            background: #D32F2F;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 20px;
            transition: all 0.3s;
        }
        .btn-continue:hover {
            background: #B71C1C;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <div class="confirmation-card">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            
            <div class="order-header">
                <h1>Order Confirmed!</h1>
                <p>Thank you for your purchase. Your order has been placed successfully.</p>
            </div>
            
            <div class="order-details">
                <h2 style="margin-bottom: 20px;">Order Details</h2>
                
                <div class="info-row">
                    <span>Order Number:</span>
                    <strong><?php echo $order['order_number']; ?></strong>
                </div>
                <div class="info-row">
                    <span>Transaction ID:</span>
                    <strong><?php echo $order['transaction_id']; ?></strong>
                </div>
                <div class="info-row">
                    <span>Order Date:</span>
                    <strong><?php echo date('F j, Y', strtotime($order['created_at'])); ?></strong>
                </div>
                <div class="info-row">
                    <span>Payment Method:</span>
                    <strong><?php echo ucfirst($order['payment_details']); ?></strong>
                </div>
                <div class="info-row">
                    <span>Order Status:</span>
                    <strong><?php echo ucfirst($order['status']); ?></strong>
                </div>
                <div class="info-row">
                    <span>Payment Status:</span>
                    <strong><?php echo ucfirst($order['payment_status']); ?></strong>
                </div>
            </div>
            
            <div class="items-list">
                <h3>Items Ordered</h3>
                <?php foreach($order_items as $item): ?>
                <div class="order-item">
                    <img src="<?php echo $item['image_url'] ?: 'https://placehold.co/60x60'; ?>" 
                         alt="<?php echo htmlspecialchars($item['name']); ?>">
                    <div class="item-details">
                        <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                        <p>Quantity: <?php echo $item['quantity']; ?></p>
                    </div>
                    <div>
                        <strong>ZMW <?php echo number_format($item['subtotal'], 2); ?></strong>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="total-section">
                <div class="info-row">
                    <span>Subtotal:</span>
                    <strong>ZMW <?php echo number_format($order['total_amount'], 2); ?></strong>
                </div>
                <div class="info-row">
                    <span>Shipping:</span>
                    <strong>ZMW 50.00</strong>
                </div>
                <div class="info-row" style="font-size: 1.2rem;">
                    <span>Total:</span>
                    <strong>ZMW <?php echo number_format($order['total_amount'] + 50, 2); ?></strong>
                </div>
            </div>
            
            <div style="text-align: center;">
                <a href="index.php" class="btn-continue">
                    <i class="fas fa-shopping-bag"></i> Continue Shopping
                </a>
            </div>
        </div>
    </div>
</body>
</html>