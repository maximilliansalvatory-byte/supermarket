<?php
/**
 * Dual Supermarket Management System - Administrative Main Dashboard
 */
$page_title = "Admin Dashboard";
require_once __DIR__ . '/../includes/admin_header.php';

// ========================================================
// CALCULATE STATISTICAL METRICS
// ========================================================

// 1. Total Sales Metrics
$stmt_sales = $pdo->query("SELECT SUM(total) FROM orders WHERE payment_status = 'Paid' AND status != 'Cancelled'");
$total_sales = (float)$stmt_sales->fetchColumn();

// 2. Orders Today Metrics
$stmt_today_ord = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = DATE(NOW())");
$orders_today = (int)$stmt_today_ord->fetchColumn();

// 3. Registered Customers Metrics
$stmt_cust = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'");
$total_customers = (int)$stmt_cust->fetchColumn();

// 4. Low Stock alert items metrics (stock <= 15)
$stmt_low_stock = $pdo->query("SELECT COUNT(*) FROM products WHERE stock <= 15 AND status = 'active'");
$low_stock_metric = (int)$stmt_low_stock->fetchColumn();

// ========================================================
// RETRIEVE TABLES FOR METRICS
// ========================================================

// 1. Low stock products details
$stmt_low_list = $pdo->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE p.stock <= 15 AND p.status = 'active' 
    ORDER BY p.stock ASC LIMIT 5
");
$low_stock_items = $stmt_low_list->fetchAll();

// 2. Recent orders lists
$stmt_rec_ord = $pdo->query("
    SELECT o.*, u.name as customer_name 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    ORDER BY o.id DESC LIMIT 5
");
$recent_orders = $stmt_rec_ord->fetchAll();

// 3. Recent Admin action logs
$stmt_rec_logs = $pdo->query("
    SELECT l.*, u.name as admin_name 
    FROM admin_logs l 
    JOIN users u ON l.admin_id = u.id 
    ORDER BY l.id DESC LIMIT 3
");
$recent_logs = $stmt_rec_logs->fetchAll();

// ========================================================
// RETRIEVE CHART DATA (Daily Sales for the last 7 Days)
// ========================================================
$chart_labels = [];
$chart_values = [];

for ($i = 6; $i >= 0; $i--) {
    $date_val = date('Y-m-d', strtotime("-$i days"));
    $label_val = date('D, M d', strtotime("-$i days"));
    
    $stmt_c = $pdo->prepare("SELECT SUM(total) FROM orders WHERE DATE(created_at) = ? AND payment_status = 'Paid' AND status != 'Cancelled'");
    $stmt_c->execute([$date_val]);
    $sales_val = (float)$stmt_c->fetchColumn();
    
    $chart_labels[] = $label_val;
    $chart_values[] = $sales_val;
}

$chart_labels_json = json_encode($chart_labels);
$chart_values_json = json_encode($chart_values);
?>

<div class="row g-4 mb-4">
    <!-- Stat 1: Total Sales -->
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card-gradient shadow-sm">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold">Total Revenue</span>
                    <h3 class="fw-bold mb-0 text-success mt-1"><?php echo format_price($total_sales); ?></h3>
                </div>
                <div class="admin-icon-circle bg-success bg-opacity-10 text-success"><i class="bi bi-wallet2"></i></div>
            </div>
        </div>
    </div>
    
    <!-- Stat 2: Orders Today -->
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card-gradient shadow-sm">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold">Orders Today</span>
                    <h3 class="fw-bold mb-0 text-primary mt-1"><?php echo $orders_today; ?></h3>
                </div>
                <div class="admin-icon-circle bg-primary bg-opacity-10 text-primary"><i class="bi bi-receipt"></i></div>
            </div>
        </div>
    </div>
    
    <!-- Stat 3: Low Stock Alerts -->
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card-gradient shadow-sm">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold">Low Stock Products</span>
                    <h3 class="fw-bold mb-0 text-danger mt-1"><?php echo $low_stock_metric; ?></h3>
                </div>
                <div class="admin-icon-circle bg-danger bg-opacity-10 text-danger"><i class="bi bi-exclamation-triangle"></i></div>
            </div>
        </div>
    </div>
    
    <!-- Stat 4: Registered Customers -->
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card-gradient shadow-sm">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold">Total Customers</span>
                    <h3 class="fw-bold mb-0 text-dark mt-1" style="color: var(--admin-text-main) !important;"><?php echo $total_customers; ?></h3>
                </div>
                <div class="admin-icon-circle bg-dark bg-opacity-10 text-dark"><i class="bi bi-people"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Chart: Sales performance trend -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 admin-glass-card p-4">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-4">
                <h5 class="fw-bold mb-0 text-dark" style="color: var(--admin-text-main) !important;"><i class="bi bi-graph-up-arrow text-success me-2"></i>Weekly Revenue Performance</h5>
                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2.5 py-1">Last 7 Days</span>
            </div>
            
            <div style="height: 300px; width: 100%;">
                <canvas id="salesTrendChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Low Stock Quick Panel -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 admin-glass-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                <h5 class="fw-bold mb-0 text-danger"><i class="bi bi-patch-exclamation text-danger me-2"></i>Inventory Alerts</h5>
                <a href="products.php?filter=low-stock" class="small text-decoration-none text-muted fw-semibold">View All</a>
            </div>
            
            <?php if (empty($low_stock_items)): ?>
                <div class="text-center py-5 text-muted small">
                    🌱 All product stocks are healthy!
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($low_stock_items as $item): ?>
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-2">
                            <div class="text-truncate" style="max-width: 70%;">
                                <strong class="d-block small text-dark" style="color: var(--admin-text-main) !important;"><?php echo sanitize($item['name']); ?></strong>
                                <span class="text-muted small" style="font-size:0.75rem;"><?php echo sanitize($item['category_name']); ?></span>
                            </div>
                            <span class="badge <?php echo $item['stock'] === 0 ? 'bg-danger' : 'bg-warning text-dark'; ?> font-monospace rounded-pill px-2.5 py-1">
                                <?php echo $item['stock']; ?> left
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Recent Orders List -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 admin-glass-card p-4">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                <h5 class="fw-bold mb-0 text-dark" style="color: var(--admin-text-main) !important;"><i class="bi bi-clock-history text-success me-2"></i>Recent Orders Activity</h5>
                <a href="orders.php" class="small text-decoration-none text-muted fw-semibold">Manage Orders</a>
            </div>
            
            <div class="table-responsive">
                <table class="table align-middle mb-0 small">
                    <thead>
                        <tr class="text-muted border-bottom">
                            <th scope="col" class="pb-2 border-0">Order ID</th>
                            <th scope="col" class="pb-2 border-0">Customer</th>
                            <th scope="col" class="pb-2 border-0">Grand Total</th>
                            <th scope="col" class="pb-2 border-0">Status</th>
                            <th scope="col" class="pb-2 border-0 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_orders as $ord): 
                            $status_badge = 'bg-secondary';
                            if ($ord['status'] === 'Processing') $status_badge = 'bg-primary';
                            if ($ord['status'] === 'Shipped') $status_badge = 'bg-info';
                            if ($ord['status'] === 'Delivered') $status_badge = 'bg-success';
                            if ($ord['status'] === 'Cancelled') $status_badge = 'bg-danger';
                        ?>
                            <tr class="border-bottom">
                                <td class="py-2.5 fw-bold text-success border-0"><?php echo sanitize($ord['order_number']); ?></td>
                                <td class="py-2.5 border-0"><?php echo sanitize($ord['customer_name']); ?></td>
                                <td class="py-2.5 fw-bold border-0"><?php echo format_price($ord['total']); ?></td>
                                <td class="py-2.5 border-0"><span class="badge <?php echo $status_badge; ?> rounded-pill px-2.5 py-1"><?php echo $ord['status']; ?></span></td>
                                <td class="py-2.5 border-0 text-end">
                                    <a href="orders.php?order_id=<?php echo $ord['id']; ?>&action=view" class="btn btn-sm btn-outline-success rounded-pill px-2.5 py-0.5 border" style="font-size:0.75rem;">Manage</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity Audit Logs -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 admin-glass-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                <h5 class="fw-bold mb-0 text-dark" style="color: var(--admin-text-main) !important;"><i class="bi bi-journal-text text-success me-2"></i>Audit Trail Logs</h5>
                <a href="logs.php" class="small text-decoration-none text-muted fw-semibold">Audit Full Log</a>
            </div>
            
            <div class="d-flex flex-column gap-3">
                <?php foreach ($recent_logs as $log): ?>
                    <div class="border-bottom pb-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="badge bg-success bg-opacity-10 text-success px-2 py-0.5 rounded-pill" style="font-size:0.7rem;"><?php echo sanitize($log['action']); ?></span>
                            <span class="text-muted font-monospace" style="font-size:0.65rem;"><?php echo date('M d, h:i A', strtotime($log['created_at'])); ?></span>
                        </div>
                        <p class="small text-muted mb-0 mt-1" style="font-size:0.8rem;"><?php echo sanitize($log['details']); ?></p>
                        <small class="text-secondary" style="font-size:0.65rem;">Operator: <?php echo sanitize($log['admin_name']); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Render sales performance graph
    const labels = <?php echo $chart_labels_json; ?>;
    const values = <?php echo $chart_values_json; ?>;
    renderSalesChart('salesTrendChart', labels, values);
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
