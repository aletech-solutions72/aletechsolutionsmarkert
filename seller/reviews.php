<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

check_seller_access();

$database = new Database();
$db = $database->getConnection();
$seller_id = $_SESSION['user_id'];

// Handle review response
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['respond_review'])) {
    $review_id = $_POST['review_id'];
    $response = sanitize_input($_POST['response']);
    
    // Add seller response to review (if response column exists)
    $query = "UPDATE reviews SET seller_response = ?, responded_at = NOW() WHERE id = ?";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$response, $review_id])) {
        $_SESSION['success'] = "Response added successfully!";
    }
    
    header("Location: reviews.php");
    exit();
}

// Get all reviews for seller's products
$query = "SELECT r.*, p.name as product_name, p.image_url, u.full_name as reviewer_name,
          DATEDIFF(NOW(), r.created_at) as days_ago
          FROM reviews r 
          JOIN products p ON r.product_id = p.id 
          JOIN users u ON r.user_id = u.id 
          WHERE p.seller_id = ?
          ORDER BY r.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$seller_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get review statistics
$total_reviews = count($reviews);
$avg_rating = $total_reviews > 0 ? array_sum(array_column($reviews, 'rating')) / $total_reviews : 0;
$five_star = count(array_filter($reviews, fn($r) => $r['rating'] == 5));
$four_star = count(array_filter($reviews, fn($r) => $r['rating'] == 4));
$three_star = count(array_filter($reviews, fn($r) => $r['rating'] == 3));
$two_star = count(array_filter($reviews, fn($r) => $r['rating'] == 2));
$one_star = count(array_filter($reviews, fn($r) => $r['rating'] == 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews - Seller Dashboard</title>
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
        }
        
        .rating-summary {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 30px;
            flex-wrap: wrap;
        }
        .avg-rating {
            text-align: center;
        }
        .avg-rating h1 {
            font-size: 3rem;
            color: #D32F2F;
        }
        .avg-rating .stars {
            color: #f59e0b;
            font-size: 1.5rem;
        }
        .rating-bars {
            flex: 1;
            min-width: 200px;
        }
        .rating-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }
        .rating-bar .label {
            width: 60px;
        }
        .rating-bar .bar {
            flex: 1;
            height: 10px;
            background: #e2e8f0;
            border-radius: 5px;
            overflow: hidden;
        }
        .rating-bar .bar .fill {
            height: 100%;
            background: #f59e0b;
        }
        .rating-bar .count {
            width: 30px;
        }
        
        .reviews-list {
            display: grid;
            gap: 20px;
        }
        .review-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .review-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        .reviewer-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #D32F2F;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
        }
        .reviewer-info {
            flex: 1;
        }
        .reviewer-name {
            font-weight: 600;
            margin-bottom: 3px;
        }
        .review-date {
            font-size: 0.85rem;
            color: #64748b;
        }
        .review-stars {
            color: #f59e0b;
        }
        .review-content {
            margin-bottom: 15px;
            color: #334155;
        }
        .product-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .product-info img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 5px;
        }
        .respond-form {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
        .respond-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            margin-bottom: 10px;
            resize: vertical;
        }
        .btn-respond {
            padding: 8px 16px;
            background: #D32F2F;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
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
            <li><a href="inventory.php"><i class="fas fa-warehouse"></i> Inventory</a></li>
            <li><a href="analytics.php"><i class="fas fa-chart-line"></i> Analytics</a></li>
            <li><a href="reviews.php" class="active"><i class="fas fa-star"></i> Reviews</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="seller-main">
        <div class="seller-header">
            <h1><i class="fas fa-star"></i> Customer Reviews</h1>
            <p>Total Reviews: <?php echo $total_reviews; ?></p>
        </div>
        
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <div class="rating-summary">
            <div class="avg-rating">
                <h1><?php echo number_format($avg_rating, 1); ?></h1>
                <div class="stars">
                    <?php for($i = 1; $i <= 5; $i++): ?>
                        <i class="fas fa-star<?php echo $i <= round($avg_rating) ? '' : '-o'; ?>"></i>
                    <?php endfor; ?>
                </div>
                <p>Average Rating</p>
            </div>
            
            <div class="rating-bars">
                <div class="rating-bar">
                    <span class="label">5 Stars</span>
                    <div class="bar">
                        <div class="fill" style="width: <?php echo $total_reviews > 0 ? ($five_star / $total_reviews) * 100 : 0; ?>%;"></div>
                    </div>
                    <span class="count"><?php echo $five_star; ?></span>
                </div>
                <div class="rating-bar">
                    <span class="label">4 Stars</span>
                    <div class="bar">
                        <div class="fill" style="width: <?php echo $total_reviews > 0 ? ($four_star / $total_reviews) * 100 : 0; ?>%;"></div>
                    </div>
                    <span class="count"><?php echo $four_star; ?></span>
                </div>
                <div class="rating-bar">
                    <span class="label">3 Stars</span>
                    <div class="bar">
                        <div class="fill" style="width: <?php echo $total_reviews > 0 ? ($three_star / $total_reviews) * 100 : 0; ?>%;"></div>
                    </div>
                    <span class="count"><?php echo $three_star; ?></span>
                </div>
                <div class="rating-bar">
                    <span class="label">2 Stars</span>
                    <div class="bar">
                        <div class="fill" style="width: <?php echo $total_reviews > 0 ? ($two_star / $total_reviews) * 100 : 0; ?>%;"></div>
                    </div>
                    <span class="count"><?php echo $two_star; ?></span>
                </div>
                <div class="rating-bar">
                    <span class="label">1 Star</span>
                    <div class="bar">
                        <div class="fill" style="width: <?php echo $total_reviews > 0 ? ($one_star / $total_reviews) * 100 : 0; ?>%;"></div>
                    </div>
                    <span class="count"><?php echo $one_star; ?></span>
                </div>
            </div>
        </div>
        
        <div class="reviews-list">
            <?php foreach($reviews as $review): ?>
            <div class="review-card">
                <div class="review-header">
                    <div class="reviewer-avatar">
                        <?php echo strtoupper(substr($review['reviewer_name'], 0, 1)); ?>
                    </div>
                    <div class="reviewer-info">
                        <div class="reviewer-name"><?php echo htmlspecialchars($review['reviewer_name']); ?></div>
                        <div class="review-date">
                            <?php echo $review['days_ago'] == 0 ? 'Today' : $review['days_ago'] . ' days ago'; ?>
                        </div>
                    </div>
                    <div class="review-stars">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <div class="product-info">
                    <img src="<?php echo $review['image_url'] ?: 'https://placehold.co/40x40'; ?>" 
                         alt="<?php echo htmlspecialchars($review['product_name']); ?>">
                    <span><?php echo htmlspecialchars($review['product_name']); ?></span>
                </div>
                
                <div class="review-content">
                    <?php echo htmlspecialchars($review['comment']); ?>
                </div>
                
                <?php if(isset($review['seller_response']) && $review['seller_response']): ?>
                <div style="background: #f0f9ff; padding: 10px; border-radius: 8px; margin-top: 10px;">
                    <strong>Your Response:</strong><br>
                    <?php echo htmlspecialchars($review['seller_response']); ?>
                </div>
                <?php else: ?>
                <div class="respond-form">
                    <form method="POST" action="">
                        <input type="hidden" name="respond_review" value="1">
                        <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                        <textarea name="response" rows="3" placeholder="Write your response..."></textarea>
                        <button type="submit" class="btn-respond">
                            <i class="fas fa-reply"></i> Respond
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            
            <?php if(count($reviews) == 0): ?>
            <div style="text-align: center; padding: 60px; background: white; border-radius: 10px;">
                <i class="fas fa-star" style="font-size: 4rem; color: #cbd5e1;"></i>
                <h2 style="margin: 20px 0;">No Reviews Yet</h2>
                <p>Your products haven't received any reviews yet.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>