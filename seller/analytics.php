<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

check_seller_access();

$database = new Database();
$db = $database->getConnection();
$seller_id = $_SESSION['user_id'];

// Get date range
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Sales data
$sales_query = "SELECT DATE(o.created_at) as sale_date, 
                COUNT(DISTINCT o.id) as total_orders,
                SUM(oi.subtotal) as total_sales,
                SUM(oi.quantity) as items_sold
                FROM orders o 
                JOIN order_items oi ON o.id = oi.order_id 
                JOIN products p ON oi.product_id = p.id 
                WHERE p.seller_id = ? AND o.payment_status = 'paid'
                AND DATE(o.created_at) BETWEEN ? AND ?
                GROUP BY DATE(o.created_at) 
                ORDER BY sale_date";
$sales_stmt = $db->prepare($sales_query);
$sales_stmt->execute([$seller_id, $date_from, $date_to]);
$sales_data = $sales_stmt->fetchAll(PDO::FETCH_ASSOC);

// Top products
$top_products_query = "SELECT p.id, p.name, p.price,
                       SUM(oi.quantity) as total_quantity,
                       SUM(oi.subtotal) as total_revenue,
                       COUNT(DISTINCT o.id) as order_count
                       FROM products p 
                       JOIN order_items oi ON p.id = oi.product_id 
                       JOIN orders o ON oi.order_id = o.id 
                       WHERE p.seller_id = ? AND o.payment_status = 'paid'
                       GROUP BY p.id 
                       ORDER BY total_revenue DESC 
                       LIMIT 5";
$top_products_stmt = $db->prepare($top_products_query);
$top_products_stmt->execute([$seller_id]);
$top_products = $top_products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Product views (if tracking is implemented)
$product_performance = "SELECT p.id, p.name, p.price, p.stock_quantity,
                        (SELECT COUNT(*) FROM order_items oi WHERE oi.product_id = p.id) as times_ordered,
                        (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.product_id = p.id) as units_sold,
                        (SELECT COALESCE(SUM(oi.subtotal), 0) FROM order_items oi WHERE oi.product_id = p.id) as total_revenue
                        FROM products p 
                        WHERE p.seller_id = ?
                        ORDER BY total_revenue DESC";
$performance_stmt = $db->prepare($product_performance);
$performance_stmt->execute([$seller_id]);
$product_performance = $performance_stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary statistics
$total_revenue = array_sum(array_column($sales_data, 'total_sales'));
$total_orders = array_sum(array_column($sales_data, 'total_orders'));
$total_items = array_sum(array_column($sales_data, 'items_sold'));
$avg_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Seller Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .chart-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .chart-card h2 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            color: #1e293b;
        }
        canvas {
            max-width: 100%;
            height: 300px;
        }
        
        .table-container {
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
        
        .date-filter {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .date-filter input {
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
        }
        .date-filter button {
            padding: 10px 20px;
            background: #D32F2F;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .chart-grid {
                grid-template-columns: 1fr;
            }
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
            <li><a href="analytics.php" class="active"><i class="fas fa-chart-line"></i> Analytics</a></li>
            <li><a href="reviews.php"><i class="fas fa-star"></i> Reviews</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="seller-main">
        <div class="seller-header">
            <h1><i class="fas fa-chart-line"></i> Analytics & Insights</h1>
        </div>
        
        <div class="date-filter">
            <form method="GET" action="" style="display: flex; gap: 15px; flex-wrap: wrap;">
                <div>
                    <label style="display: block; margin-bottom: 5px;">From Date</label>
                    <input type="date" name="date_from" value="<?php echo $date_from; ?>">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px;">To Date</label>
                    <input type="date" name="date_to" value="<?php echo $date_to; ?>">
                </div>
                <div style="display: flex; align-items: flex-end;">
                    <button type="submit">
                        <i class="fas fa-filter"></i> Apply Filter
                    </button>
                </div>
            </form>
        </div>
        
        <div class="stats-cards">
            <div class="stat-card">
                <i class="fas fa-dollar-sign"></i>
                <h3>ZMW <?php echo number_format($total_revenue, 2); ?></h3>
                <p>Total Revenue</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-shopping-cart"></i>
                <h3><?php echo $total_orders; ?></h3>
                <p>Total Orders</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-box"></i>
                <h3><?php echo $total_items; ?></h3>
                <p>Items Sold</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-receipt"></i>
                <h3>ZMW <?php echo number_format($avg_order_value, 2); ?></h3>
                <p>Avg Order Value</p>
            </div>
        </div>
        
        <div class="chart-grid">
            <div class="chart-card">
                <h2>Sales Trend</h2>
                <canvas id="salesChart"></canvas>
            </div>
            
            <div class="chart-card">
                <h2>Top Products</h2>
                <canvas id="productsChart"></canvas>
            </div>
        </div>
        
        <div class="table-container">
            <h2 style="margin-bottom: 20px;">Product Performance</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Units Sold</th>
                        <th>Times Ordered</th>
                        <th>Total Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($product_performance as $product): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td>ZMW <?php echo number_format($product['price'], 2); ?></td>
                        <td><?php echo $product['stock_quantity']; ?></td>
                        <td><?php echo $product['units_sold'] ?: 0; ?></td>
                        <td><?php echo $product['times_ordered'] ?: 0; ?></td>
                        <td>ZMW <?php echo number_format($product['total_revenue'] ?: 0, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        // Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesData = <?php echo json_encode($sales_data); ?>;
        
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: salesData.map(d => d.sale_date),
                datasets: [{
                    label: 'Daily Sales',
                    data: salesData.map(d => d.total_sales),
                    borderColor: '#D32F2F',
                    backgroundColor: 'rgba(211, 47, 47, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'ZMW ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Products Chart
        const productsCtx = document.getElementById('productsChart').getContext('2d');
        const productsData = <?php echo json_encode($top_products); ?>;
        
        new Chart(productsCtx, {
            type: 'bar',
            data: {
                labels: productsData.map(d => d.name),
                datasets: [{
                    label: 'Revenue',
                    data: productsData.map(d => d.total_revenue),
                    backgroundColor: '#D32F2F',
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'ZMW ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>