<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

check_admin_access();

$database = new Database();
$db = $database->getConnection();

// Get date range filter
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Sales report
$sales_query = "SELECT DATE(created_at) as sale_date, 
                COUNT(*) as total_orders, 
                SUM(total_amount) as total_sales,
                AVG(total_amount) as avg_order_value
                FROM orders 
                WHERE payment_status = 'paid' 
                AND DATE(created_at) BETWEEN ? AND ?
                GROUP BY DATE(created_at) 
                ORDER BY sale_date DESC";
$sales_stmt = $db->prepare($sales_query);
$sales_stmt->execute([$date_from, $date_to]);
$sales_data = $sales_stmt->fetchAll(PDO::FETCH_ASSOC);

// Top products
$top_products_query = "SELECT p.id, p.name, p.price, 
                       SUM(oi.quantity) as total_quantity,
                       SUM(oi.subtotal) as total_revenue,
                       COUNT(DISTINCT o.id) as order_count
                       FROM products p 
                       JOIN order_items oi ON p.id = oi.product_id 
                       JOIN orders o ON oi.order_id = o.id 
                       WHERE o.payment_status = 'paid'
                       GROUP BY p.id 
                       ORDER BY total_revenue DESC 
                       LIMIT 10";
$top_products_stmt = $db->query($top_products_query);
$top_products = $top_products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Top sellers
$top_sellers_query = "SELECT u.id, u.full_name, u.email,
                      COUNT(DISTINCT o.id) as order_count,
                      SUM(oi.subtotal) as total_revenue,
                      COUNT(DISTINCT p.id) as product_count
                      FROM users u 
                      JOIN products p ON u.id = p.seller_id 
                      JOIN order_items oi ON p.id = oi.product_id 
                      JOIN orders o ON oi.order_id = o.id 
                      WHERE o.payment_status = 'paid' AND u.role = 'seller'
                      GROUP BY u.id 
                      ORDER BY total_revenue DESC 
                      LIMIT 10";
$top_sellers_stmt = $db->query($top_sellers_query);
$top_sellers = $top_sellers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Category performance
$category_performance = "SELECT c.name, 
                         COUNT(DISTINCT p.id) as product_count,
                         SUM(oi.quantity) as items_sold,
                         SUM(oi.subtotal) as total_revenue
                         FROM categories c 
                         LEFT JOIN products p ON c.id = p.category_id 
                         LEFT JOIN order_items oi ON p.id = oi.product_id 
                         LEFT JOIN orders o ON oi.order_id = o.id AND o.payment_status = 'paid'
                         GROUP BY c.id 
                         ORDER BY total_revenue DESC";
$category_stmt = $db->query($category_performance);
$category_data = $category_stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary statistics
$total_revenue = array_sum(array_column($sales_data, 'total_sales'));
$total_orders = array_sum(array_column($sales_data, 'total_orders'));
$avg_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Msika Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .admin-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card i {
            font-size: 2rem;
            color: #D32F2F;
            margin-bottom: 10px;
        }
        .stat-card h3 {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }
        .stat-card p {
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .report-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .report-card h2 {
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
            margin-bottom: 30px;
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
            font-size: 0.9rem;
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
        
        .btn-export {
            padding: 10px 20px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        @media (max-width: 768px) {
            .report-grid {
                grid-template-columns: 1fr;
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
            <li><a href="products.php"><i class="fas fa-box"></i> Manage Products</a></li>
            <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> Manage Orders</a></li>
            <li><a href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
            <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
            <li><a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="admin-main">
        <div class="admin-header">
            <h1><i class="fas fa-chart-bar"></i> Reports & Analytics</h1>
            <button class="btn-export" onclick="window.print()">
                <i class="fas fa-download"></i> Export Report
            </button>
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
                <i class="fas fa-receipt"></i>
                <h3>ZMW <?php echo number_format($avg_order_value, 2); ?></h3>
                <p>Avg Order Value</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <h3><?php echo count($top_sellers); ?></h3>
                <p>Active Sellers</p>
            </div>
        </div>
        
        <div class="report-grid">
            <div class="report-card">
                <h2>Sales Trend</h2>
                <canvas id="salesChart"></canvas>
            </div>
            
            <div class="report-card">
                <h2>Category Performance</h2>
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
        
        <div class="table-container">
            <h2 style="margin-bottom: 20px;">Top Selling Products</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Quantity Sold</th>
                        <th>Total Revenue</th>
                        <th>Orders</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($top_products as $product): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td>ZMW <?php echo number_format($product['price'], 2); ?></td>
                        <td><?php echo $product['total_quantity']; ?></td>
                        <td>ZMW <?php echo number_format($product['total_revenue'], 2); ?></td>
                        <td><?php echo $product['order_count']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="table-container">
            <h2 style="margin-bottom: 20px;">Top Sellers</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Seller</th>
                        <th>Products</th>
                        <th>Orders</th>
                        <th>Total Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($top_sellers as $seller): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($seller['full_name']); ?></strong><br>
                            <small><?php echo htmlspecialchars($seller['email']); ?></small>
                        </td>
                        <td><?php echo $seller['product_count']; ?></td>
                        <td><?php echo $seller['order_count']; ?></td>
                        <td>ZMW <?php echo number_format($seller['total_revenue'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        // Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesData = <?php echo json_encode(array_reverse($sales_data)); ?>;
        
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
        
        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryData = <?php echo json_encode($category_data); ?>;
        
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: categoryData.map(d => d.name),
                datasets: [{
                    data: categoryData.map(d => d.total_revenue || 0),
                    backgroundColor: [
                        '#D32F2F', '#3b82f6', '#10b981', '#f59e0b',
                        '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
    </script>
</body>
</html>