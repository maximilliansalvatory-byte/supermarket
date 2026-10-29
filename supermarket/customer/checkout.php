<?php
/**
 * Dual Supermarket Management System - Secure Checkout
 */
$page_title = "Checkout Order";
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Force authentication
require_login();

// Retrieve user info
$user = get_logged_in_user($pdo);

// Retrieve cart items count and validation
$stmt_cart = $pdo->prepare("
    SELECT c.quantity, p.id, p.name, p.price, p.sale_price, p.stock 
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = ? AND p.status = 'active'
");
$stmt_cart->execute([$_SESSION['user_id']]);
$cart_items = $stmt_cart->fetchAll();

if (empty($cart_items)) {
    $_SESSION['flash_error'] = "Your shopping cart is empty. Please add items before checking out.";
    header("Location: shop.php");
    exit;
}

// Calculate totals
$subtotal = 0.0;
foreach ($cart_items as $item) {
    $price = $item['sale_price'] ?: $item['price'];
    $subtotal += ($price * $item['quantity']);
}
$tax = $subtotal * 0.05;
$delivery = ($subtotal > 50.0) ? 0.0 : 4.99;
$grand_total = $subtotal + $tax + $delivery;

$errors = [];
$address = $user['address'] ?? '';
$phone = $user['phone'] ?? '';
$city = $user['city'] ?? '';
$notes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_post_or_die();
    
    $address = sanitize($_POST['address']);
    $phone = sanitize($_POST['phone']);
    $city = sanitize($_POST['city']);
    $notes = sanitize($_POST['notes']);
    $payment_method = sanitize($_POST['payment_method'] ?? 'cod');
    
    if (empty($address)) $errors[] = "Shipping address is required.";
    if (empty($phone)) $errors[] = "Contact phone number is required.";
    if (empty($city)) $errors[] = "Delivery city is required.";
    
    // Verify stock availability one final time before starting database writes
    foreach ($cart_items as $c_item) {
        if ($c_item['quantity'] > $c_item['stock']) {
            $errors[] = "Insufficient stock for " . sanitize($c_item['name']) . ". Only {$c_item['stock']} left. Please update your cart.";
        }
    }
    
    if (empty($errors)) {
        try {
            // Begin Transaction to maintain atomicity and prevent concurrency corruption
            $pdo->beginTransaction();
            
            // 1. Generate Order details
            $order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            
            // If payment_method is COD, payment status Pending. If Credit card, we simulate Paid.
            $payment_status = ($payment_method === 'card') ? 'Paid' : 'Pending';
            
            $stmt_ord = $pdo->prepare("
                INSERT INTO orders (user_id, order_number, total, status, payment_status, address, phone, city, notes) 
                VALUES (?, ?, ?, 'Pending', ?, ?, ?, ?, ?)
            ");
            $stmt_ord->execute([
                $_SESSION['user_id'],
                $order_number,
                $grand_total,
                $payment_status,
                $address,
                $phone,
                $city,
                $notes
            ]);
            
            $order_id = $pdo->lastInsertId();
            
            // 2. Transfer cart products and decrement stocks
            $stmt_item = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            $stmt_stock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            
            foreach ($cart_items as $c_item) {
                $price = $c_item['sale_price'] ?: $c_item['price'];
                
                // Write item
                $stmt_item->execute([$order_id, $c_item['id'], $c_item['quantity'], $price]);
                
                // Decrement stock
                $stmt_stock->execute([$c_item['quantity'], $c_item['id']]);
            }
            
            // 3. Purge user cart
            $stmt_clear = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt_clear->execute([$_SESSION['user_id']]);
            
            // Commit Transaction
            $pdo->commit();
            
            $_SESSION['flash_success'] = "Thank you! Your order <strong>{$order_number}</strong> has been received successfully.";
            header("Location: order-history.php?highlight={$order_number}");
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Failed to process checkout due to database error: " . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-5">
    <div class="row g-5">
        <!-- Billing Details Form -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white p-4 p-md-5 border-secondary-subtle" style="background-color: var(--card-bg) !important; border: 1px solid var(--border-color) !important;">
                <h4 class="fw-bold mb-4 text-dark" style="color: var(--text-main) !important;"><i class="bi bi-truck text-success me-2"></i>Shipping & Billing details</h4>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger border-0 rounded-3 shadow-sm mb-4">
                        <ul class="mb-0 small ps-3">
                            <?php foreach ($errors as $err): ?>
                                <li><?php echo $err; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form action="checkout.php" method="POST">
                    <?php echo csrf_field(); ?>
                    
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="form-label small fw-bold">Customer Name</label>
                            <input type="text" class="form-control bg-light border-0 py-2.5" value="<?php echo sanitize($user['name']); ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label small fw-bold">Contact Phone Number <span class="text-danger">*</span></label>
                            <input type="text" id="phone" name="phone" class="form-control py-2.5 border-secondary-subtle" value="<?php echo sanitize($phone); ?>" required placeholder="e.g. +1 555 1234">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="city" class="form-label small fw-bold">Delivery City <span class="text-danger">*</span></label>
                            <input type="text" id="city" name="city" class="form-control py-2.5 border-secondary-subtle" value="<?php echo sanitize($city); ?>" required placeholder="e.g. Oakwood">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label small fw-bold">Full Shipping Address <span class="text-danger">*</span></label>
                        <textarea id="address" name="address" rows="3" class="form-control border-secondary-subtle" required placeholder="Street address, building number, apartment/suite..."><?php echo sanitize($address); ?></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label for="notes" class="form-label small fw-bold">Delivery Instructions (Optional)</label>
                        <textarea id="notes" name="notes" rows="2" class="form-control border-secondary-subtle" placeholder="e.g. Ring bell, leave near the porch, gate code 1234..."><?php echo sanitize($notes); ?></textarea>
                    </div>
                    
                    <h5 class="fw-bold mb-3 border-top pt-4 text-dark" style="color: var(--text-main) !important;"><i class="bi bi-wallet2 text-success me-2"></i>Payment Selection</h5>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 d-flex align-items-center gap-3 bg-light-subtle pointer shadow-sm" onclick="document.getElementById('payCod').checked = true">
                                <input type="radio" name="payment_method" value="cod" id="payCod" checked class="form-check-input text-success">
                                <div>
                                    <label for="payCod" class="fw-bold mb-0 pointer">Cash on Delivery</label>
                                    <small class="text-muted d-block" style="font-size:0.75rem;">Pay upon cargo arrival</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 d-flex align-items-center gap-3 bg-light-subtle pointer shadow-sm" onclick="document.getElementById('payCard').checked = true">
                                <input type="radio" name="payment_method" value="card" id="payCard" class="form-check-input text-success">
                                <div>
                                    <label for="payCard" class="fw-bold mb-0 pointer">Credit / Debit Card</label>
                                    <small class="text-muted d-block" style="font-size:0.75rem;">Simulate instant online pay</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary-gradient w-100 py-3 rounded-pill fw-bold shadow-md"><i class="bi bi-lock-fill me-1"></i> Place Secure Order (<?php echo format_price($grand_total); ?>)</button>
                </form>
            </div>
        </div>
        
        <!-- Cart Items Summary Review -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white p-4 border-secondary-subtle" style="background-color: var(--card-bg) !important; border: 1px solid var(--border-color) !important;">
                <h5 class="fw-bold text-dark pb-3 border-bottom mb-3" style="color: var(--text-main) !important;">Review Items</h5>
                
                <div class="d-flex flex-column gap-3 mb-4" style="max-height: 280px; overflow-y: auto;">
                    <?php foreach ($cart_items as $item): 
                        $price = $item['sale_price'] ?: $item['price'];
                    ?>
                        <div class="d-flex justify-content-between align-items-center gap-3">
                            <div class="d-flex align-items-center gap-2 text-truncate" style="max-width: 75%;">
                                <span class="badge bg-success bg-opacity-25 text-success rounded-pill font-monospace" style="font-size:0.75rem;">x<?php echo $item['quantity']; ?></span>
                                <span class="small fw-semibold text-truncate text-dark" style="color: var(--text-main) !important;"><?php echo sanitize($item['name']); ?></span>
                            </div>
                            <span class="small fw-bold text-nowrap"><?php echo format_price($price * $item['quantity']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="border-top pt-3 border-secondary-subtle small text-muted">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <span class="fw-semibold text-dark" style="color: var(--text-main) !important;"><?php echo format_price($subtotal); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Est. Vat (5%):</span>
                        <span class="fw-semibold text-dark" style="color: var(--text-main) !important;"><?php echo format_price($tax); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Delivery Rate:</span>
                        <span class="fw-semibold text-success"><?php echo $delivery === 0.0 ? 'FREE' : format_price($delivery); ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-between border-top pt-3 fs-5 fw-bold text-dark" style="color: var(--text-main) !important;">
                        <span>Payable Total:</span>
                        <span class="text-success"><?php echo format_price($grand_total); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
