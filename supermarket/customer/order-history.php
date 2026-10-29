<?php
/**
 * Dual Supermarket Management System - Order History & Visual Delivery Tracking
 */
$page_title = "My Orders";
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check auth
require_login();

// Retrieve all customer orders in reverse order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();

// Check if specific order highlighted for timeline visual tracker
$highlighted_order = null;
$highlight_number = isset($_GET['highlight']) ? sanitize($_GET['highlight']) : '';

if (!empty($highlight_number)) {
    $stmt_h = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? AND order_number = ?");
    $stmt_h->execute([$_SESSION['user_id'], $highlight_number]);
    $highlighted_order = $stmt_h->fetch();
} elseif (!empty($orders)) {
    // Default to newest order for visual tracking display
    $highlighted_order = $orders[0];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-5">
    <div class="row g-5">
        
        <!-- VISUAL TIMELINE ORDER TRACKER PANEL -->
        <?php if ($highlighted_order): 
            $status = $highlighted_order['status'];
            
            // Map statuses to numeric progress stages
            $stage_num = 1;
            if ($status === 'Processing') $stage_num = 2;
            if ($status === 'Shipped') $stage_num = 3;
            if ($status === 'Delivered') $stage_num = 4;
            if ($status === 'Cancelled') $stage_num = 0;
        ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white p-4 p-md-5 border-secondary-subtle" style="background-color: var(--card-bg) !important; border: 1px solid var(--border-color) !important;">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center border-bottom pb-3 mb-4">
                        <div>
                            <span class="text-success fw-bold small text-uppercase">Real-Time Delivery tracking</span>
                            <h4 class="fw-bold mb-0 text-dark" style="color: var(--text-main) !important;">Order Tracking: <strong class="text-success"><?php echo sanitize($highlighted_order['order_number']); ?></strong></h4>
                        </div>
                        <div class="mt-2 mt-md-0 text-md-end">
                            <span class="small text-muted">Created On: <?php echo date('M d, Y h:i A', strtotime($highlighted_order['created_at'])); ?></span> <br>
                            <span class="small text-muted">Grand Total: <strong class="text-dark" style="color: var(--text-main) !important;"><?php echo format_price($highlighted_order['total']); ?></strong></span>
                        </div>
                    </div>
                    
                    <?php if ($stage_num === 0): ?>
                        <div class="alert alert-danger border-0 rounded-3 shadow-sm py-4 text-center mb-0">
                            <div class="fs-1 mb-2">❌</div>
                            <h5 class="fw-bold mb-1">This order has been Cancelled</h5>
                            <p class="small mb-0 text-danger-emphasis">If you have any questions or require support, please contact FreshCart customer helpline.</p>
                        </div>
                    <?php else: ?>
                        <!-- Visual Timeline component -->
                        <div class="timeline-stages mt-4 px-md-5">
                            <!-- Stage 1: Pending -->
                            <div class="timeline-stage <?php echo $stage_num >= 1 ? ($stage_num == 1 ? 'active' : 'completed') : ''; ?>">
                                <div class="timeline-circle shadow-sm"><i class="bi bi-clock-fill"></i></div>
                                <h6 class="fw-bold small text-dark mt-2" style="color: var(--text-main) !important;">Placed</h6>
                                <span class="text-muted d-none d-md-block" style="font-size:0.75rem;">Awaiting review</span>
                            </div>
                            
                            <!-- Stage 2: Processing -->
                            <div class="timeline-stage <?php echo $stage_num >= 2 ? ($stage_num == 2 ? 'active' : 'completed') : ''; ?>">
                                <div class="timeline-circle shadow-sm"><i class="bi bi-box-fill"></i></div>
                                <h6 class="fw-bold small text-dark mt-2" style="color: var(--text-main) !important;">Packing</h6>
                                <span class="text-muted d-none d-md-block" style="font-size:0.75rem;">Preparing parcel</span>
                            </div>
                            
                            <!-- Stage 3: Shipped -->
                            <div class="timeline-stage <?php echo $stage_num >= 3 ? ($stage_num == 3 ? 'active' : 'completed') : ''; ?>">
                                <div class="timeline-circle shadow-sm"><i class="bi bi-truck"></i></div>
                                <h6 class="fw-bold small text-dark mt-2" style="color: var(--text-main) !important;">Transit</h6>
                                <span class="text-muted d-none d-md-block" style="font-size:0.75rem;">Shipped out</span>
                            </div>
                            
                            <!-- Stage 4: Delivered -->
                            <div class="timeline-stage <?php echo $stage_num >= 4 ? 'completed' : ''; ?>">
                                <div class="timeline-circle shadow-sm"><i class="bi bi-house-heart-fill"></i></div>
                                <h6 class="fw-bold small text-dark mt-2" style="color: var(--text-main) !important;">Delivered</h6>
                                <span class="text-muted d-none d-md-block" style="font-size:0.75rem;">Arrived safely</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- HISTORY TABLE LISTING -->
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white p-4 p-md-5 border-secondary-subtle" style="background-color: var(--card-bg) !important; border: 1px solid var(--border-color) !important;">
                <h4 class="fw-bold mb-4 text-dark" style="color: var(--text-main) !important;"><i class="bi bi-clock-history text-success me-2"></i>Purchase Order History</h4>
                
                <?php if (empty($orders)): ?>
                    <div class="text-center py-5">
                        <div class="fs-1 mb-2">📦</div>
                        <h5 class="fw-bold">No orders found</h5>
                        <p class="text-muted small">You haven't purchased anything yet. Visit our shop aisles today!</p>
                        <a href="shop.php" class="btn btn-success btn-sm rounded-pill px-4 mt-2">Go to Shop</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr class="text-muted small border-bottom">
                                    <th scope="col" class="pb-3 border-0">Order Number</th>
                                    <th scope="col" class="pb-3 border-0">Date Placed</th>
                                    <th scope="col" class="pb-3 border-0">Total</th>
                                    <th scope="col" class="pb-3 border-0">Delivery Status</th>
                                    <th scope="col" class="pb-3 border-0">Payment</th>
                                    <th scope="col" class="pb-3 border-0 text-center">Track</th>
                                    <th scope="col" class="pb-3 border-0 text-end">Items</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $ord): 
                                    $status_badge = 'bg-secondary';
                                    if ($ord['status'] === 'Processing') $status_badge = 'bg-primary';
                                    if ($ord['status'] === 'Shipped') $status_badge = 'bg-info';
                                    if ($ord['status'] === 'Delivered') $status_badge = 'bg-success';
                                    if ($ord['status'] === 'Cancelled') $status_badge = 'bg-danger';
                                    
                                    $pay_badge = 'bg-warning-subtle text-warning border-warning-subtle';
                                    if ($ord['payment_status'] === 'Paid') $pay_badge = 'bg-success-subtle text-success border-success-subtle';
                                    if ($ord['payment_status'] === 'Failed') $pay_badge = 'bg-danger-subtle text-danger border-danger-subtle';
                                    if ($ord['payment_status'] === 'Refunded') $pay_badge = 'bg-secondary-subtle text-secondary border-secondary-subtle';
                                ?>
                                    <tr class="border-bottom">
                                        <td class="py-3 fw-bold text-success border-0">
                                            <?php echo sanitize($ord['order_number']); ?>
                                        </td>
                                        <td class="py-3 text-muted small border-0">
                                            <?php echo date('M d, Y', strtotime($ord['created_at'])); ?>
                                        </td>
                                        <td class="py-3 fw-bold text-dark border-0" style="color: var(--text-main) !important;">
                                            <?php echo format_price($ord['total']); ?>
                                        </td>
                                        <td class="py-3 border-0">
                                            <span class="badge <?php echo $status_badge; ?> px-2.5 py-1.5 rounded-pill shadow-sm" style="font-size:0.75rem;"><?php echo $ord['status']; ?></span>
                                        </td>
                                        <td class="py-3 border-0">
                                            <span class="badge <?php echo $pay_badge; ?> px-2.5 py-1.5 border" style="font-size:0.75rem;"><?php echo $ord['payment_status']; ?></span>
                                        </td>
                                        <td class="py-3 border-0 text-center">
                                            <a href="order-history.php?highlight=<?php echo urlencode($ord['order_number']); ?>" class="btn btn-sm btn-outline-success rounded-pill px-3 shadow-sm py-1 font-monospace" style="font-size:0.75rem;">
                                                <i class="bi bi-geo-fill me-1"></i> Track Cargo
                                            </a>
                                        </td>
                                        <td class="py-3 border-0 text-end">
                                            <button type="button" class="btn btn-sm btn-light border rounded-circle shadow-sm" data-bs-toggle="collapse" data-bs-target="#items-ord-<?php echo $ord['id']; ?>" aria-expanded="false" title="Expand Items List">
                                                <i class="bi bi-chevron-expand"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    
                                    <!-- Collapsible Order Items Container -->
                                    <tr id="items-ord-<?php echo $ord['id']; ?>" class="collapse border-bottom bg-light bg-opacity-25">
                                        <td colspan="7" class="p-4 border-0">
                                            <div class="p-3 border rounded-3 bg-white shadow-sm">
                                                <h6 class="fw-bold mb-3 text-dark small" style="color: var(--text-main) !important;"><i class="bi bi-info-circle text-success me-1"></i>Order Item Breakdown:</h6>
                                                
                                                <?php 
                                                // Fetch items for this order
                                                $stmt_oi = $pdo->prepare("
                                                    SELECT oi.quantity, oi.price, p.name, p.slug 
                                                    FROM order_items oi
                                                    JOIN products p ON oi.product_id = p.id
                                                    WHERE oi.order_id = ?
                                                ");
                                                $stmt_oi->execute([$ord['id']]);
                                                $order_items = $stmt_oi->fetchAll();
                                                ?>
                                                <div class="row g-2">
                                                    <?php foreach ($order_items as $oi): ?>
                                                        <div class="col-md-6 small">
                                                            <div class="d-flex justify-content-between border-bottom pb-2">
                                                                <span class="text-muted"><strong class="text-success">x<?php echo $oi['quantity']; ?></strong> <?php echo sanitize($oi['name']); ?></span>
                                                                <span class="fw-bold text-dark" style="color: var(--text-main) !important;"><?php echo format_price($oi['price'] * $oi['quantity']); ?></span>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                
                                                <div class="mt-3 text-muted small d-flex flex-wrap gap-3">
                                                    <span><strong>Delivery City:</strong> <?php echo sanitize($ord['city']); ?></span>
                                                    <span><strong>Shipping Address:</strong> <?php echo sanitize($ord['address']); ?></span>
                                                    <?php if ($ord['notes']): ?>
                                                        <span><strong>Instructions:</strong> "<?php echo sanitize($ord['notes']); ?>"</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</div>
