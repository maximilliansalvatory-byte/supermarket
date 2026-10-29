<?php
/**
 * Dual Supermarket Management System - Administrative Sales Analytics & Reports
 */
$page_title = "Sales Analytics Reports";
require_once __DIR__ . '/../includes/admin_header.php';

// ========================================================
// TIMEFRAME HANDLER & DATE BOUNDARIES
// ========================================================
$preset = isset($_GET['preset']) ? sanitize($_GET['preset']) : '7days';

$date_from = '';
$date_to = date('Y-m-d');

if ($preset === '7days') {
    $date_from = date('Y-m-d', strtotime('-6 days'));
} elseif ($preset === '30days') {
    $date_from = date('Y-m-d', strtotime('-29 days'));
} elseif ($preset === 'month') {
    $date_from = date('Y-m-01');
} elseif ($preset === 'ytd') {
    $date_from = date('Y-01-01');
} elseif ($preset === 'custom') {
    $date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-d', strtotime('-6 days'));
    $date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');
} else {
    $preset = '7days';
    $date_from = date('Y-m-d', strtotime('-6 days'));
}

// Ensure proper date order
if ($date_from > $date_to) {
    $temp = $date_from;
    $date_from = $date_to;
    $date_to = $temp;
}

// ========================================================
// CORE SALES PERFORMANCE METRICS
// ========================================================

// 1. Gross Revenue
$stmt_rev = $pdo->prepare("
    SELECT SUM(total) 
    FROM orders 
    WHERE payment_status = 'Paid' AND status != 'Cancelled' AND DATE(created_at) BETWEEN ? AND ?
");
$stmt_rev->execute([$date_from, $date_to]);
$gross_revenue = (float)$stmt_rev->fetchColumn();

// 2. Order Volume
$stmt_cnt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM orders 
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$stmt_cnt->execute([$date_from, $date_to]);
$order_count = (int)$stmt_cnt->fetchColumn();

// 3. Units Sold
$stmt_units = $pdo->prepare("
    SELECT SUM(oi.quantity) 
    FROM order_items oi 
    JOIN orders o ON oi.order_id = o.id 
    WHERE o.payment_status = 'Paid' AND o.status != 'Cancelled' AND DATE(o.created_at) BETWEEN ? AND ?
");
$stmt_units->execute([$date_from, $date_to]);
$units_sold = (int)$stmt_units->fetchColumn();

// 4. Average Order Value
$aov = $order_count > 0 ? ($gross_revenue / $order_count) : 0.0;

// ========================================================
// GRAPH 1: DAILY REVENUE LINE CHART
// ========================================================
$stmt_trend = $pdo->prepare("
    SELECT DATE(created_at) as date_val, SUM(total) as daily_total 
    FROM orders 
    WHERE payment_status = 'Paid' AND status != 'Cancelled' AND DATE(created_at) BETWEEN ? AND ? 
    GROUP BY DATE(created_at) 
    ORDER BY DATE(created_at) ASC
");
$stmt_trend->execute([$date_from, $date_to]);
$trend_data = $stmt_trend->fetchAll();

$sales_by_date = [];
foreach ($trend_data as $row) {
    $sales_by_date[$row['date_val']] = (float)$row['daily_total'];
}

$chart_labels = [];
$chart_values = [];

// Determine interval based on timeframe size
$start_dt = new DateTime($date_from);
$end_dt = new DateTime($date_to);
$diff_days = $end_dt->diff($start_dt)->days;

// Day-by-day mapping for standard smaller intervals
$period = new DatePeriod($start_dt, new DateInterval('P1D'), $end_dt->modify('+1 day'));
foreach ($period as $dt) {
    $d_str = $dt->format('Y-m-d');
    $chart_labels[] = $dt->format('M d');
    $chart_values[] = isset($sales_by_date[$d_str]) ? $sales_by_date[$d_str] : 0.0;
}

$chart_labels_json = json_encode($chart_labels);
$chart_values_json = json_encode($chart_values);

// ========================================================
// GRAPH 2: CATEGORY POPULARITY DOUGHNUT
// ========================================================
$stmt_cat = $pdo->prepare("
    SELECT c.name as category_name, SUM(oi.quantity) as total_qty 
    FROM order_items oi 
    JOIN orders o ON oi.order_id = o.id 
    JOIN products p ON oi.product_id = p.id 
    JOIN categories c ON p.category_id = c.id 
    WHERE o.payment_status = 'Paid' AND o.status != 'Cancelled' AND DATE(o.created_at) BETWEEN ? AND ? 
    GROUP BY c.id 
    ORDER BY total_qty DESC
");
$stmt_cat->execute([$date_from, $date_to]);
$category_data = $stmt_cat->fetchAll();

$cat_labels = [];
$cat_values = [];
foreach ($category_data as $row) {
    $cat_labels[] = $row['category_name'];
    $cat_values[] = (int)$row['total_qty'];
}
$cat_labels_json = json_encode($cat_labels);
$cat_values_json = json_encode($cat_values);

// ========================================================
// TABLES DATA: TOP PRODUCTS & RECENT HIGH VALUE ORDERS
// ========================================================

// 1. Top products
$stmt_top = $pdo->prepare("
    SELECT p.name as product_name, c.name as category_name, SUM(oi.quantity) as units_sold, SUM(oi.quantity * oi.price) as gross_val 
    FROM order_items oi 
    JOIN orders o ON oi.order_id = o.id 
    JOIN products p ON oi.product_id = p.id 
    JOIN categories c ON p.category_id = c.id 
    WHERE o.payment_status = 'Paid' AND o.status != 'Cancelled' AND DATE(o.created_at) BETWEEN ? AND ? 
    GROUP BY p.id 
    ORDER BY units_sold DESC 
    LIMIT 5
");
$stmt_top->execute([$date_from, $date_to]);
$top_products = $stmt_top->fetchAll();

// 2. High value orders
$stmt_high = $pdo->prepare("
    SELECT o.*, u.name as customer_name 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.payment_status = 'Paid' AND o.status != 'Cancelled' AND DATE(o.created_at) BETWEEN ? AND ? 
    ORDER BY o.total DESC 
    LIMIT 5
");
$stmt_high->execute([$date_from, $date_to]);
$high_orders = $stmt_high->fetchAll();
?>

<!-- Advanced Filters Widget Panel -->
<div class="card border-0 shadow-sm rounded-4 admin-glass-card p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <!-- Quick Preset selection triggers -->
        <div class="d-flex flex-wrap gap-2">
            <a href="reports.php?preset=7days" class="btn btn-sm rounded-pill px-3 py-2 fw-semibold <?php echo $preset === '7days' ? 'btn-success' : 'btn-light border'; ?>">Last 7 Days</a>
            <a href="reports.php?preset=30days" class="btn btn-sm rounded-pill px-3 py-2 fw-semibold <?php echo $preset === '30days' ? 'btn-success' : 'btn-light border'; ?>">Last 30 Days</a>
            <a href="reports.php?preset=month" class="btn btn-sm rounded-pill px-3 py-2 fw-semibold <?php echo $preset === 'month' ? 'btn-success' : 'btn-light border'; ?>">This Month</a>
            <a href="reports.php?preset=ytd" class="btn btn-sm rounded-pill px-3 py-2 fw-semibold <?php echo $preset === 'ytd' ? 'btn-success' : 'btn-light border'; ?>">Year to Date</a>
        </div>
        
        <!-- Custom date picking form -->
        <form action="reports.php" method="GET" class="d-flex align-items-center gap-2 flex-wrap">
            <input type="hidden" name="preset" value="custom">
            <div class="d-flex align-items-center gap-1.5 small font-monospace">
                <input type="date" name="date_from" class="form-control form-control-sm border-secondary-subtle" value="<?php echo $date_from; ?>" required>
                <span class="text-muted px-1">to</span>
                <input type="date" name="date_to" class="form-control form-control-sm border-secondary-subtle" value="<?php echo $date_to; ?>" required>
            </div>
            <button type="submit" class="btn btn-sm btn-success px-3 fw-bold shadow-sm rounded-pill"><i class="bi bi-calendar2-range"></i> Load</button>
        </form>
    </div>
    
    <div class="border-top mt-3 pt-2 text-muted small d-flex align-items-center gap-2">
        <i class="bi bi-info-circle-fill text-success"></i>
        <span>Reporting timeframe: <strong><?php echo date('F d, Y', strtotime($date_from)); ?></strong> to <strong><?php echo date('F d, Y', strtotime($date_to)); ?></strong></span>
    </div>
</div>

<!-- Gross Performance Indicators Grid -->
<div class="row g-4 mb-4">
    <!-- Card 1: Gross Sales Revenue -->
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card-gradient shadow-sm border-0">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold">Gross Revenue</span>
                    <h3 class="fw-bold mb-0 text-success mt-1"><?php echo format_price($gross_revenue); ?></h3>
                </div>
                <div class="admin-icon-circle bg-success bg-opacity-10 text-success"><i class="bi bi-wallet2"></i></div>
            </div>
        </div>
    </div>
    
    <!-- Card 2: Orders Total -->
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card-gradient shadow-sm border-0">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold">Orders Fulfilled</span>
                    <h3 class="fw-bold mb-0 text-primary mt-1"><?php echo $order_count; ?></h3>
                </div>
                <div class="admin-icon-circle bg-primary bg-opacity-10 text-primary"><i class="bi bi-receipt"></i></div>
            </div>
        </div>
    </div>
    
    <!-- Card 3: AOV (Average Order Value) -->
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card-gradient shadow-sm border-0">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold">Average Order Value</span>
                    <h3 class="fw-bold mb-0 text-warning-emphasis mt-1"><?php echo format_price($aov); ?></h3>
                </div>
                <div class="admin-icon-circle bg-warning bg-opacity-10 text-warning-emphasis"><i class="bi bi-calculator"></i></div>
            </div>
        </div>
    </div>
    
    <!-- Card 4: Total Units Sold -->
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card-gradient shadow-sm border-0">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold">Products Sold</span>
                    <h3 class="fw-bold mb-0 text-danger mt-1"><?php echo $units_sold; ?> <span class="small text-muted" style="font-size:0.75rem;">units</span></h3>
                </div>
                <div class="admin-icon-circle bg-danger bg-opacity-10 text-danger"><i class="bi bi-cart-check"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Graphical Analytics Plots -->
<div class="row g-4 mb-4">
    <!-- Line Chart: Daily revenue performance -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 admin-glass-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-4">
                <h5 class="fw-bold mb-0 text-dark" style="color: var(--admin-text-main) !important;"><i class="bi bi-graph-up-arrow text-success me-2"></i>Revenue Flow Trend</h5>
                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2.5 py-1">Timeframe Revenue</span>
            </div>
            
            <div style="height: 320px; width: 100%;">
                <canvas id="revenueTrendChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Doughnut Chart: Category popularity -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 admin-glass-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-4">
                <h5 class="fw-bold mb-0 text-dark" style="color: var(--admin-text-main) !important;"><i class="bi bi-pie-chart text-success me-2"></i>Category Volume</h5>
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2.5 py-1">Units share</span>
            </div>
            
            <div class="position-relative" style="height: 250px; width: 100%;">
                <canvas id="categorySalesChart"></canvas>
            </div>
            <div id="categoryChartNoData" class="text-center py-5 text-muted small d-none">
                🌱 No category sales recorded in this timeframe.
            </div>
        </div>
    </div>
</div>

<!-- Leaderboards: Top Selling Products & High Value Orders -->
<div class="row g-4">
    <!-- Top Products -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 admin-glass-card p-4 h-100">
            <h5 class="fw-bold text-dark border-bottom pb-3 mb-3" style="color: var(--admin-text-main) !important;"><i class="bi bi-trophy text-success me-2"></i>Top Product Performers</h5>
            <?php if (empty($top_products)): ?>
                <div class="text-center py-5 text-muted small">
                    🌱 No sales records for this period.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 small">
                        <thead>
                            <tr class="text-muted table-light">
                                <th scope="col" class="py-2">Product</th>
                                <th scope="col" class="py-2">Category</th>
                                <th scope="col" class="py-2 text-center">Units</th>
                                <th scope="col" class="py-2 text-end">Gross Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_products as $idx => $prod): ?>
                                <tr class="border-bottom">
                                    <td class="py-2.5">
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-circle me-1.5 font-monospace" style="width:22px; height:22px; display:inline-flex; align-items:center; justify-content:center; font-size:0.75rem;"><?php echo $idx + 1; ?></span>
                                        <strong class="text-dark" style="color: var(--admin-text-main) !important;"><?php echo sanitize($prod['product_name']); ?></strong>
                                    </td>
                                    <td class="py-2.5 text-muted"><?php echo sanitize($prod['category_name']); ?></td>
                                    <td class="py-2.5 text-center font-monospace fw-bold">x<?php echo $prod['units_sold']; ?></td>
                                    <td class="py-2.5 text-end text-success fw-bold"><?php echo format_price($prod['gross_val']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Premium High Value Orders -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 admin-glass-card p-4 h-100">
            <h5 class="fw-bold text-dark border-bottom pb-3 mb-3" style="color: var(--admin-text-main) !important;"><i class="bi bi-cash-stack text-success me-2"></i>Recent High-Value Purchases</h5>
            <?php if (empty($high_orders)): ?>
                <div class="text-center py-5 text-muted small">
                    🌱 No transactions records for this period.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 small">
                        <thead>
                            <tr class="text-muted table-light">
                                <th scope="col" class="py-2">Order ID</th>
                                <th scope="col" class="py-2">Customer</th>
                                <th scope="col" class="py-2">Fulfillment</th>
                                <th scope="col" class="py-2 text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($high_orders as $ord): 
                                $status_badge = 'bg-secondary';
                                if ($ord['status'] === 'Processing') $status_badge = 'bg-primary';
                                if ($ord['status'] === 'Shipped') $status_badge = 'bg-info';
                                if ($ord['status'] === 'Delivered') $status_badge = 'bg-success';
                                if ($ord['status'] === 'Cancelled') $status_badge = 'bg-danger';
                            ?>
                                <tr class="border-bottom">
                                    <td class="py-2.5 font-monospace fw-bold"><a href="orders.php?action=view&order_id=<?php echo $ord['id']; ?>" class="text-success text-decoration-none"><?php echo sanitize($ord['order_number']); ?></a></td>
                                    <td class="py-2.5 text-dark" style="color: var(--admin-text-main) !important;"><?php echo sanitize($ord['customer_name']); ?></td>
                                    <td class="py-2.5"><span class="badge <?php echo $status_badge; ?> rounded-pill px-2 py-0.5" style="font-size:0.7rem;"><?php echo $ord['status']; ?></span></td>
                                    <td class="py-2.5 text-end fw-bold text-dark" style="color: var(--admin-text-main) !important;"><?php echo format_price($ord['total']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Render Sales Trend Line Chart (using global helper in admin.js)
    const trendLabels = <?php echo $chart_labels_json; ?>;
    const trendValues = <?php echo $chart_values_json; ?>;
    renderSalesChart('revenueTrendChart', trendLabels, trendValues, 'Gross Sales Revenue ($)');

    // 2. Render Category Share Doughnut Chart
    const catLabels = <?php echo $cat_labels_json; ?>;
    const catValues = <?php echo $cat_values_json; ?>;
    
    if (catValues.length === 0) {
        document.getElementById('categorySalesChart').classList.add('d-none');
        document.getElementById('categoryChartNoData').classList.remove('d-none');
    } else {
        const catCanvas = document.getElementById('categorySalesChart');
        if (catCanvas) {
            const catCtx = catCanvas.getContext('2d');
            
            // Design vibrant theme complementary colors for HSL variables
            const bgColors = [
                '#198754', // Fresh Emerald
                '#0d6efd', // Deep Blue
                '#0dcaf0', // Light Teal
                '#ffc107', // Warm Amber
                '#dc3545', // Coral Red
                '#6f42c1', // Royal Violet
                '#fd7e14'  // Sunset Orange
            ];

            new Chart(catCtx, {
                type: 'doughnut',
                data: {
                    labels: catLabels,
                    datasets: [{
                        data: catValues,
                        backgroundColor: bgColors.slice(0, catValues.length),
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: {
                                    family: "'Outfit', sans-serif",
                                    size: 11
                                },
                                usePointStyle: true,
                                padding: 15
                            }
                        },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            titleColor: '#ffffff',
                            bodyColor: '#ffffff',
                            padding: 10,
                            cornerRadius: 6,
                            callbacks: {
                                label: function(context) {
                                    return ' ' + context.label + ': ' + context.raw + ' units sold';
                                }
                            }
                        }
                    },
                    cutout: '65%'
                }
            });
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
