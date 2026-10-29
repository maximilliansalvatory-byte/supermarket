<?php
/**
 * Dual Supermarket Management System - Administrative Order Pipeline Management
 */
$page_title = "Manage Orders";
require_once __DIR__ . '/../includes/admin_header.php';

$errors = [];
$success = "";

// Capture actions
$action = isset($_GET['action']) ? sanitize($_GET['action']) : 'list';
$order_id = isset($_GET['order_id']) && is_numeric($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

// 1. UPDATE ORDER STATUS (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update' && $order_id > 0) {
    verify_csrf_post_or_die();
    
    $new_status = sanitize($_POST['status'] ?? '');
    $new_payment_status = sanitize($_POST['payment_status'] ?? '');
    
    // Validate states
    $valid_statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
    $valid_payment_statuses = ['Pending', 'Paid', 'Failed', 'Refunded'];
    
    if (!in_array($new_status, $valid_statuses)) $errors[] = "Invalid order status.";
    if (!in_array($new_payment_status, $valid_payment_statuses)) $errors[] = "Invalid payment status.";
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Get original order state to check for transition to/from Cancelled
            $stmt_orig = $pdo->prepare("SELECT order_number, status, payment_status FROM orders WHERE id = ?");
            $stmt_orig->execute([$order_id]);
            $original_order = $stmt_orig->fetch();
            
            if ($original_order) {
                $old_status = $original_order['status'];
                $old_payment = $original_order['payment_status'];
                $order_number = $original_order['order_number'];
                
                // Inventory adjustment logic
                if ($old_status !== 'Cancelled' && $new_status === 'Cancelled') {
                    // Transitioning TO Cancelled: restore stock
                    $stmt_items = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
                    $stmt_items->execute([$order_id]);
                    $items = $stmt_items->fetchAll();
                    
                    $stmt_up_stock = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                    foreach ($items as $item) {
                        $stmt_up_stock->execute([$item['quantity'], $item['product_id']]);
                    }
                } elseif ($old_status === 'Cancelled' && $new_status !== 'Cancelled') {
                    // Transitioning FROM Cancelled back to Active: check and deduct stock
                    $stmt_items = $pdo->prepare("SELECT product_id, quantity, name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
                    $stmt_items->execute([$order_id]);
                    $items = $stmt_items->fetchAll();
                    
                    // First check availability
                    $stmt_check = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
                    foreach ($items as $item) {
                        $stmt_check->execute([$item['product_id']]);
                        $curr_stock = (int)$stmt_check->fetchColumn();
                        if ($item['quantity'] > $curr_stock) {
                            throw new Exception("Cannot revert Cancelled status: Insufficient stock for '" . $item['name'] . "' (Needs {$item['quantity']}, has {$curr_stock}).");
                        }
                    }
                    
                    // If OK, deduct stock
                    $stmt_down_stock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                    foreach ($items as $item) {
                        $stmt_down_stock->execute([$item['quantity'], $item['product_id']]);
                    }
                }
                
                // Update order record
                $stmt_up = $pdo->prepare("UPDATE orders SET status = ?, payment_status = ? WHERE id = ?");
                $stmt_up->execute([$new_status, $new_payment_status, $order_id]);
                
                // Commit changes
                $pdo->commit();
                
                log_admin_action(
                    $pdo, 
                    $_SESSION['user_id'], 
                    'Order Updated', 
                    "Order #{$order_number} status updated from '{$old_status}' to '{$new_status}' (Payment: '{$old_payment}' to '{$new_payment_status}')."
                );
                
                $_SESSION['flash_success'] = "Order status updated successfully.";
                header("Location: orders.php?action=view&order_id=" . $order_id);
                exit;
            } else {
                throw new Exception("Order not found.");
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

// Fetch single order details if viewing
$order = null;
$order_items = [];
if ($action === 'view' && $order_id > 0) {
    $stmt_ord = $pdo->prepare("
        SELECT o.*, u.name as customer_name, u.email as customer_email 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.id = ?
    ");
    $stmt_ord->execute([$order_id]);
    $order = $stmt_ord->fetch();
    
    if ($order) {
        $stmt_items = $pdo->prepare("
            SELECT oi.*, p.name as product_name, p.images as product_images, c.name as category_name 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id 
            JOIN categories c ON p.category_id = c.id 
            WHERE oi.order_id = ?
        ");
        $stmt_items->execute([$order_id]);
        $order_items = $stmt_items->fetchAll();
    } else {
        $_SESSION['flash_error'] = "Requested order not found.";
        header("Location: orders.php");
        exit;
    }
}

// Fetch filter settings
$filter_status = isset($_GET['status']) ? sanitize($_GET['status']) : 'All';
$filter_payment = isset($_GET['payment']) ? sanitize($_GET['payment']) : 'All';
$search_query = isset($_GET['search']) ? trim(sanitize($_GET['search'])) : '';

// Base query for lists
$sql_list = "
    SELECT o.*, u.name as customer_name, u.email as customer_email 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE 1=1
";
$params_list = [];

if ($filter_status !== 'All') {
    $sql_list .= " AND o.status = ?";
    $params_list[] = $filter_status;
}

if ($filter_payment !== 'All') {
    $sql_list .= " AND o.payment_status = ?";
    $params_list[] = $filter_payment;
}

if (!empty($search_query)) {
    $sql_list .= " AND (o.order_number LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $params_list[] = "%$search_query%";
    $params_list[] = "%$search_query%";
    $params_list[] = "%$search_query%";
}

$sql_list .= " ORDER BY o.id DESC";
$stmt_list = $pdo->prepare($sql_list);
$stmt_list->execute($params_list);
$orders_list = $stmt_list->fetchAll();
?>

<!-- Title Action bar -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0 text-dark" style="color: var(--admin-text-main) !important;">
        <?php echo $action === 'view' ? "Order Invoice: #" . sanitize($order['order_number']) : "Order Pipeline Manager"; ?>
    </h3>
    <?php if ($action === 'view'): ?>
        <a href="orders.php" class="btn btn-outline-success px-4 rounded-pill fw-bold border-2 bg-white"><i class="bi bi-arrow-left-short"></i> Back to Orders</a>
    <?php endif; ?>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger border-0 rounded-3 shadow-sm mb-4">
        <ul class="mb-0 small ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?php echo $err; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- ========================================================
     DETAILED INVOICE & STATUS TRANSITION VIEW
     ======================================================== -->
<?php if ($action === 'view' && $order): 
    $status_color = 'bg-secondary';
    if ($order['status'] === 'Processing') $status_color = 'bg-primary';
    if ($order['status'] === 'Shipped') $status_color = 'bg-info';
    if ($order['status'] === 'Delivered') $status_color = 'bg-success';
    if ($order['status'] === 'Cancelled') $status_color = 'bg-danger';

    $pay_color = 'bg-secondary-subtle text-secondary';
    if ($order['payment_status'] === 'Paid') $pay_color = 'bg-success-subtle text-success border-success-subtle';
    if ($order['payment_status'] === 'Failed') $pay_color = 'bg-danger-subtle text-danger border-danger-subtle';
    if ($order['payment_status'] === 'Refunded') $pay_color = 'bg-warning-subtle text-warning border-warning-subtle';
?>
    <div class="row g-4">
        <!-- Main Invoice Area -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 admin-glass-card p-4 p-md-5">
                <div class="d-flex justify-content-between flex-wrap gap-3 pb-4 border-bottom">
                    <div>
                        <span class="badge bg-success bg-opacity-25 text-success mb-2 px-2.5 py-1">FRESHCART RETAILS</span>
                        <h4 class="fw-bold mb-1" style="color: var(--admin-text-main) !important;">Invoice #<?php echo sanitize($order['order_number']); ?></h4>
                        <span class="text-muted small">Placed on: <?php echo date('M d, Y \a\t h:i A', strtotime($order['created_at'])); ?></span>
                    </div>
                    <div class="text-lg-end">
                        <span class="badge <?php echo $status_color; ?> rounded-pill px-3 py-1.5 fs-7 mb-2 d-inline-block"><?php echo $order['status']; ?></span> <br>
                        <span class="badge <?php echo $pay_color; ?> border rounded-pill px-3 py-1.5 fs-7 d-inline-block">Payment: <?php echo $order['payment_status']; ?></span>
                    </div>
                </div>

                <!-- Shipping Details Info Grid -->
                <div class="row g-4 my-4">
                    <div class="col-md-6 border-end border-secondary-subtle">
                        <h6 class="fw-bold text-success mb-3"><i class="bi bi-person-circle me-2"></i>Customer Information</h6>
                        <table class="table table-borderless table-sm small mb-0">
                            <tr>
                                <td class="text-muted ps-0" style="width: 80px;">Name:</td>
                                <td class="fw-semibold text-dark" style="color: var(--admin-text-main) !important;"><?php echo sanitize($order['customer_name']); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">Email:</td>
                                <td><code><?php echo sanitize($order['customer_email']); ?></code></td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">Phone:</td>
                                <td><?php echo sanitize($order['phone']); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6 ps-md-4">
                        <h6 class="fw-bold text-success mb-3"><i class="bi bi-truck me-2"></i>Shipping Details</h6>
                        <table class="table table-borderless table-sm small mb-0">
                            <tr>
                                <td class="text-muted ps-0" style="width: 80px;">City:</td>
                                <td class="fw-semibold text-dark" style="color: var(--admin-text-main) !important;"><?php echo sanitize($order['city']); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">Address:</td>
                                <td><?php echo nl2br(sanitize($order['address'])); ?></td>
                            </tr>
                            <?php if (!empty($order['notes'])): ?>
                                <tr>
                                    <td class="text-muted ps-0">Notes:</td>
                                    <td class="text-danger-emphasis bg-danger bg-opacity-10 p-2 rounded small"><?php echo nl2br(sanitize($order['notes'])); ?></td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>

                <!-- Products Table -->
                <h6 class="fw-bold text-dark mb-3" style="color: var(--admin-text-main) !important;"><i class="bi bi-cart3 me-2"></i>Purchased Items</h6>
                <div class="table-responsive">
                    <table class="table align-middle small">
                        <thead>
                            <tr class="text-muted table-light">
                                <th scope="col" style="width: 60px;">Image</th>
                                <th scope="col">Product Item</th>
                                <th scope="col">Price</th>
                                <th scope="col" class="text-center">Qty</th>
                                <th scope="col" class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $calc_subtotal = 0.0;
                            foreach ($order_items as $item):
                                $sub = $item['price'] * $item['quantity'];
                                $calc_subtotal += $sub;
                                
                                // Image resolving
                                $images = json_decode($item['product_images'], true) ?: [];
                                if (!empty($images) && file_exists(__DIR__ . '/../uploads/products/' . $images[0])) {
                                    $img_src = BASE_URL . '/uploads/products/' . $images[0];
                                } else {
                                    $img_src = get_placeholder_svg($item['product_name'], 80, 80, 10);
                                }
                            ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo $img_src; ?>" class="rounded-3 border object-fit-cover" style="width: 45px; height: 45px;" alt="">
                                    </td>
                                    <td>
                                        <strong class="text-dark" style="color: var(--admin-text-main) !important;"><?php echo sanitize($item['product_name']); ?></strong> <br>
                                        <span class="text-muted small" style="font-size:0.75rem;"><?php echo sanitize($item['category_name']); ?></span>
                                    </td>
                                    <td><?php echo format_price($item['price']); ?></td>
                                    <td class="text-center font-monospacefw-bold">x<?php echo $item['quantity']; ?></td>
                                    <td class="text-end fw-bold text-dark" style="color: var(--admin-text-main) !important;"><?php echo format_price($sub); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <!-- Financial summaries -->
                            <?php 
                                $calc_tax = $calc_subtotal * 0.05;
                                // We check if total has delivery rate (if total - subtotal - tax > 1, that's delivery)
                                $diff = $order['total'] - $calc_subtotal - $calc_tax;
                                $calc_delivery = ($diff > 1.0) ? $diff : 0.0;
                            ?>
                            <tr class="border-0">
                                <td colspan="3" class="border-0"></td>
                                <td class="text-end text-muted border-0 py-1">Subtotal:</td>
                                <td class="text-end text-dark border-0 py-1" style="color: var(--admin-text-main) !important;"><?php echo format_price($calc_subtotal); ?></td>
                            </tr>
                            <tr class="border-0">
                                <td colspan="3" class="border-0"></td>
                                <td class="text-end text-muted border-0 py-1">Vat (5%):</td>
                                <td class="text-end text-dark border-0 py-1" style="color: var(--admin-text-main) !important;"><?php echo format_price($calc_tax); ?></td>
                            </tr>
                            <tr class="border-0">
                                <td colspan="3" class="border-0"></td>
                                <td class="text-end text-muted border-0 py-1">Delivery Charge:</td>
                                <td class="text-end text-success border-0 py-1"><?php echo $calc_delivery === 0.0 ? 'FREE' : format_price($calc_delivery); ?></td>
                            </tr>
                            <tr class="border-0">
                                <td colspan="3" class="border-0"></td>
                                <td class="text-end text-muted border-0 fw-bold fs-6 pt-3">Grand Total:</td>
                                <td class="text-end text-success border-0 fw-bold fs-5 pt-3"><?php echo format_price($order['total']); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sidebar Pipeline controls -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 admin-glass-card p-4">
                <h5 class="fw-bold mb-4 text-dark" style="color: var(--admin-text-main) !important;"><i class="bi bi-gear-fill text-success me-2"></i>Status Pipeline</h5>
                
                <form action="orders.php?action=update&order_id=<?php echo $order['id']; ?>" method="POST">
                    <?php echo csrf_field(); ?>
                    
                    <div class="mb-4">
                        <label for="orderStatusSelect" class="form-label small fw-bold">Order Shipment Pipeline</label>
                        <select id="orderStatusSelect" name="status" class="form-select py-2.5 border-secondary-subtle">
                            <option value="Pending" <?php echo $order['status'] === 'Pending' ? 'selected' : ''; ?>>Pending (Unfulfilled)</option>
                            <option value="Processing" <?php echo $order['status'] === 'Processing' ? 'selected' : ''; ?>>Processing (Preparing)</option>
                            <option value="Shipped" <?php echo $order['status'] === 'Shipped' ? 'selected' : ''; ?>>Shipped (Dispatched)</option>
                            <option value="Delivered" <?php echo $order['status'] === 'Delivered' ? 'selected' : ''; ?>>Delivered (Completed)</option>
                            <option value="Cancelled" <?php echo $order['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled (Restores Inventory)</option>
                        </select>
                        <small class="text-muted d-block mt-1">Cancelling an order automatically returns ordered product items back to catalog stocks.</small>
                    </div>
                    
                    <div class="mb-4">
                        <label for="paymentStatusSelect" class="form-label small fw-bold">Payment Clearing Status</label>
                        <select id="paymentStatusSelect" name="payment_status" class="form-select py-2.5 border-secondary-subtle">
                            <option value="Pending" <?php echo $order['payment_status'] === 'Pending' ? 'selected' : ''; ?>>Pending (Awaiting payment)</option>
                            <option value="Paid" <?php echo $order['payment_status'] === 'Paid' ? 'selected' : ''; ?>>Paid (Cleared)</option>
                            <option value="Failed" <?php echo $order['payment_status'] === 'Failed' ? 'selected' : ''; ?>>Failed (Declined)</option>
                            <option value="Refunded" <?php echo $order['payment_status'] === 'Refunded' ? 'selected' : ''; ?>>Refunded (Returned)</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary-gradient w-100 py-3 rounded-pill fw-bold shadow-sm">
                        <i class="bi bi-save me-1"></i> Update Pipeline States
                    </button>
                </form>
            </div>
        </div>
    </div>

<!-- ========================================================
     MASTER ORDERS LIST VIEW
     ======================================================== -->
<?php else: ?>
    <!-- Filter Sidebar Panels & Search Bar -->
    <div class="card border-0 shadow-sm rounded-4 admin-glass-card p-4 mb-4">
        <form action="orders.php" method="GET" class="row g-3">
            <div class="col-lg-4 col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 border-secondary-subtle"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 border-secondary-subtle py-2.5" placeholder="Order ID or customer name..." value="<?php echo sanitize($search_query); ?>">
                </div>
            </div>
            
            <div class="col-lg-3 col-md-3">
                <select name="status" class="form-select py-2.5 border-secondary-subtle">
                    <option value="All" <?php echo $filter_status === 'All' ? 'selected' : ''; ?>>All Shipment States</option>
                    <option value="Pending" <?php echo $filter_status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Processing" <?php echo $filter_status === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                    <option value="Shipped" <?php echo $filter_status === 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
                    <option value="Delivered" <?php echo $filter_status === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                    <option value="Cancelled" <?php echo $filter_status === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            
            <div class="col-lg-3 col-md-3">
                <select name="payment" class="form-select py-2.5 border-secondary-subtle">
                    <option value="All" <?php echo $filter_payment === 'All' ? 'selected' : ''; ?>>All Payment States</option>
                    <option value="Pending" <?php echo $filter_payment === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Paid" <?php echo $filter_payment === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="Failed" <?php echo $filter_payment === 'Failed' ? 'selected' : ''; ?>>Failed</option>
                    <option value="Refunded" <?php echo $filter_payment === 'Refunded' ? 'selected' : ''; ?>>Refunded</option>
                </select>
            </div>
            
            <div class="col-lg-2 col-md-12 d-flex gap-2">
                <button type="submit" class="btn btn-success-gradient w-100 py-2.5 rounded-pill fw-bold shadow-sm"><i class="bi bi-filter"></i> Filter</button>
                <a href="orders.php" class="btn btn-outline-secondary py-2.5 rounded-circle border" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>

    <!-- Master orders table -->
    <div class="card border-0 shadow-sm rounded-4 admin-glass-card p-4">
        <?php if (empty($orders_list)): ?>
            <div class="text-center py-5 text-muted">
                <span class="fs-1 d-block mb-3">📦</span>
                <h5 class="fw-semibold">No Orders Recorded</h5>
                <p class="small mb-0">No transaction orders match your search parameters.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle admin-table mb-0">
                    <thead>
                        <tr class="text-muted">
                            <th scope="col">Order Number</th>
                            <th scope="col">Customer Account</th>
                            <th scope="col">Created Date</th>
                            <th scope="col">Total Payable</th>
                            <th scope="col" class="text-center">Payment</th>
                            <th scope="col" class="text-center">Order Status</th>
                            <th scope="col" class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders_list as $ord): 
                            $status_badge = 'bg-secondary';
                            if ($ord['status'] === 'Processing') $status_badge = 'bg-primary';
                            if ($ord['status'] === 'Shipped') $status_badge = 'bg-info';
                            if ($ord['status'] === 'Delivered') $status_badge = 'bg-success';
                            if ($ord['status'] === 'Cancelled') $status_badge = 'bg-danger';

                            $pay_badge = 'bg-secondary-subtle text-secondary';
                            if ($ord['payment_status'] === 'Paid') $pay_badge = 'bg-success-subtle text-success border-success-subtle';
                            if ($ord['payment_status'] === 'Failed') $pay_badge = 'bg-danger-subtle text-danger border-danger-subtle';
                            if ($ord['payment_status'] === 'Refunded') $pay_badge = 'bg-warning-subtle text-warning border-warning-subtle';
                        ?>
                            <tr>
                                <td>
                                    <strong class="text-success font-monospace"><?php echo sanitize($ord['order_number']); ?></strong>
                                </td>
                                <td>
                                    <strong class="text-dark" style="color: var(--admin-text-main) !important;"><?php echo sanitize($ord['customer_name']); ?></strong> <br>
                                    <code class="small text-muted" style="font-size:0.7rem;"><?php echo sanitize($ord['customer_email']); ?></code>
                                </td>
                                <td>
                                    <span class="small text-muted"><?php echo date('M d, Y h:i A', strtotime($ord['created_at'])); ?></span>
                                </td>
                                <td class="fw-bold text-dark" style="color: var(--admin-text-main) !important;">
                                    <?php echo format_price($ord['total']); ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?php echo $pay_badge; ?> border rounded-pill px-2.5 py-1 small"><?php echo $ord['payment_status']; ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?php echo $status_badge; ?> rounded-pill px-2.5 py-1 small"><?php echo $ord['status']; ?></span>
                                </td>
                                <td class="text-end">
                                    <a href="orders.php?action=view&order_id=<?php echo $ord['id']; ?>" class="btn btn-sm btn-light btn-admin-action border text-success" title="View & Manage Invoice"><i class="bi bi-eye-fill"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
