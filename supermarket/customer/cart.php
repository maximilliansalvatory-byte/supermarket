<?php
/**
 * Dual Supermarket Management System - Shopping Cart
 * Functions as both the page UI (GET) and the AJAX API endpoint (POST) for cart CRUD.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// ========================================================
// POST: AJAX API ENDPOINT
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Verify CSRF
    $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!validate_csrf($csrf_token)) {
        echo json_encode(['status' => 'error', 'message' => 'CSRF validation failed. Request blocked.']);
        exit;
    }
    
    $action = isset($_POST['action']) ? sanitize($_POST['action']) : '';
    $product_id = isset($_POST['product_id']) && is_numeric($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $quantity = isset($_POST['quantity']) && is_numeric($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    
    if ($product_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid product identifier.']);
        exit;
    }
    
    // Verify product exists and get stock
    $stmt_p = $pdo->prepare("SELECT id, name, price, sale_price, stock FROM products WHERE id = ? AND status = 'active'");
    $stmt_p->execute([$product_id]);
    $product = $stmt_p->fetch();
    
    if (!$product) {
        echo json_encode(['status' => 'error', 'message' => 'Requested grocery product is unavailable.']);
        exit;
    }
    
    $product_price = $product['sale_price'] ?: $product['price'];
    
    // Handle Actions
    switch ($action) {
        case 'add':
            // Check stock threshold
            if ($quantity > $product['stock']) {
                echo json_encode([
                    'status' => 'error', 
                    'message' => "Insufficient stock. Only {$product['stock']} units of " . sanitize($product['name']) . " are available."
                ]);
                exit;
            }
            
            if (is_logged_in()) {
                // Check if already in user database cart
                $stmt_check = $pdo->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
                $stmt_check->execute([$_SESSION['user_id'], $product_id]);
                $existing_qty = $stmt_check->fetchColumn();
                
                if ($existing_qty) {
                    $new_qty = $existing_qty + $quantity;
                    if ($new_qty > $product['stock']) {
                        echo json_encode([
                            'status' => 'error', 
                            'message' => "You already have {$existing_qty} in cart. Insufficient additional stock (max {$product['stock']})."
                        ]);
                        exit;
                    }
                    
                    $stmt_up = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
                    $stmt_up->execute([$new_qty, $_SESSION['user_id'], $product_id]);
                } else {
                    $stmt_in = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                    $stmt_in->execute([$_SESSION['user_id'], $product_id, $quantity]);
                }
            } else {
                // Guest Session Cart logic
                if (!isset($_SESSION['guest_cart'])) {
                    $_SESSION['guest_cart'] = [];
                }
                
                $existing_qty = isset($_SESSION['guest_cart'][$product_id]) ? $_SESSION['guest_cart'][$product_id] : 0;
                $new_qty = $existing_qty + $quantity;
                
                if ($new_qty > $product['stock']) {
                    echo json_encode([
                        'status' => 'error', 
                        'message' => "You already have {$existing_qty} in cart. Insufficient additional stock (max {$product['stock']})."
                    ]);
                    exit;
                }
                
                $_SESSION['guest_cart'][$product_id] = $new_qty;
            }
            
            // Recalculate totals for response
            $totals = calculate_cart_totals($pdo);
            echo json_encode([
                'status' => 'success',
                'message' => "Added " . sanitize($product['name']) . " successfully to cart!",
                'total_items' => $totals['total_items']
            ]);
            exit;
            
        case 'update':
            if ($quantity <= 0) {
                echo json_encode(['status' => 'error', 'message' => 'Quantity must be at least 1.']);
                exit;
            }
            
            if ($quantity > $product['stock']) {
                echo json_encode([
                    'status' => 'error', 
                    'message' => "Insufficient stock. Maximum available is {$product['stock']}.",
                    'current_stock' => $product['stock']
                ]);
                exit;
            }
            
            if (is_logged_in()) {
                $stmt_up = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
                $stmt_up->execute([$quantity, $_SESSION['user_id'], $product_id]);
            } else {
                $_SESSION['guest_cart'][$product_id] = $quantity;
            }
            
            $item_total = $product_price * $quantity;
            $totals = calculate_cart_totals($pdo);
            
            echo json_encode(array_merge([
                'status' => 'success',
                'message' => 'Cart updated successfully.',
                'item_total_formatted' => format_price($item_total)
            ], $totals));
            exit;
            
        case 'remove':
            if (is_logged_in()) {
                $stmt_del = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
                $stmt_del->execute([$_SESSION['user_id'], $product_id]);
            } else {
                if (isset($_SESSION['guest_cart'][$product_id])) {
                    unset($_SESSION['guest_cart'][$product_id]);
                }
            }
            
            $totals = calculate_cart_totals($pdo);
            echo json_encode(array_merge([
                'status' => 'success',
                'message' => 'Item removed from cart.'
            ], $totals));
            exit;
            
        default:
            echo json_encode(['status' => 'error', 'message' => 'Unsupported operational action.']);
            exit;
    }
}

// ========================================================
// HELPER: CALCULATE TOTALS
// ========================================================
function calculate_cart_totals($pdo) {
    $subtotal = 0.0;
    $total_items = 0;
    
    if (is_logged_in()) {
        $stmt = $pdo->prepare("
            SELECT c.quantity, p.price, p.sale_price 
            FROM cart c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.user_id = ? AND p.status = 'active'
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $items = $stmt->fetchAll();
        
        foreach ($items as $item) {
            $price = $item['sale_price'] ?: $item['price'];
            $subtotal += ($price * $item['quantity']);
            $total_items += $item['quantity'];
        }
    } else {
        if (isset($_SESSION['guest_cart']) && !empty($_SESSION['guest_cart'])) {
            foreach ($_SESSION['guest_cart'] as $p_id => $qty) {
                $stmt_p = $pdo->prepare("SELECT price, sale_price FROM products WHERE id = ? AND status = 'active'");
                $stmt_p->execute([$p_id]);
                $p = $stmt_p->fetch();
                if ($p) {
                    $price = $p['sale_price'] ?: $p['price'];
                    $subtotal += ($price * $qty);
                    $total_items += $qty;
                }
            }
        }
    }
    
    $tax = $subtotal * 0.05; // 5% simulated food vat
    $delivery = ($subtotal > 50.0 || $subtotal === 0.0) ? 0.0 : 4.99; // Free over $50
    $total = $subtotal + $tax + $delivery;
    
    return [
        'total_items' => $total_items,
        'subtotal' => $subtotal,
        'subtotal_formatted' => format_price($subtotal),
        'tax' => $tax,
        'tax_formatted' => format_price($tax),
        'delivery' => $delivery,
        'delivery_formatted' => $delivery === 0.0 ? 'FREE' : format_price($delivery),
        'total' => $total,
        'total_formatted' => format_price($total)
    ];
}

// ========================================================
// GET: STANDARD PAGE VIEW RENDERING
// ========================================================
$page_title = "My Shopping Cart";

// Retrieve all products currently in user/guest cart
$cart_items = [];
if (is_logged_in()) {
    $stmt_c = $pdo->prepare("
        SELECT c.quantity, p.id, p.name, p.slug, p.price, p.sale_price, p.stock, p.images, cat.name as category_name
        FROM cart c
        JOIN products p ON c.product_id = p.id
        JOIN categories cat ON p.category_id = cat.id
        WHERE c.user_id = ? AND p.status = 'active'
    ");
    $stmt_c->execute([$_SESSION['user_id']]);
    $cart_items = $stmt_c->fetchAll();
} else {
    if (isset($_SESSION['guest_cart']) && !empty($_SESSION['guest_cart'])) {
        foreach ($_SESSION['guest_cart'] as $p_id => $qty) {
            $stmt_c = $pdo->prepare("
                SELECT ? as quantity, p.id, p.name, p.slug, p.price, p.sale_price, p.stock, p.images, cat.name as category_name
                FROM products p
                JOIN categories cat ON p.category_id = cat.id
                WHERE p.id = ? AND p.status = 'active'
            ");
            $stmt_c->execute([$qty, $p_id]);
            $item = $stmt_c->fetch();
            if ($item) {
                $cart_items[] = $item;
            }
        }
    }
}

// Calculate initial totals for display
$initial_totals = calculate_cart_totals($pdo);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-5">
    <h2 class="fw-bold mb-4 text-dark" style="color: var(--text-main) !important;"><span class="text-success">Shopping</span> Cart</h2>
    
    <?php if (empty($cart_items)): ?>
        <div class="card border-0 shadow-lg rounded-4 overflow-hidden text-center glass-panel p-5">
            <div class="fs-1 mb-3">🧺</div>
            <h3 class="fw-bold">Your Cart is Empty</h3>
            <p class="text-muted small mt-2">Looks like you haven't added any farm-fresh groceries yet.</p>
            <a href="shop.php" class="btn btn-primary-gradient px-4 py-2 mt-3 rounded-pill fw-bold">Explore Groceries</a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <!-- Items Grid -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white p-3 border-secondary-subtle" style="background-color: var(--card-bg) !important; border: 1px solid var(--border-color) !important;">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr class="text-muted small border-bottom">
                                    <th scope="col" class="pb-3 border-0">Product Details</th>
                                    <th scope="col" class="pb-3 border-0">Unit Price</th>
                                    <th scope="col" class="pb-3 border-0">Quantity</th>
                                    <th scope="col" class="pb-3 border-0 text-end">Subtotal</th>
                                    <th scope="col" class="pb-3 border-0"></th>
                                </tr>
                            </thead>
                            <tbody class="cart-table-body">
                                <?php foreach ($cart_items as $item): 
                                    $item_images = json_decode($item['images'], true) ?: [];
                                    $item_img = !empty($item_images) ? BASE_URL . '/uploads/products/' . $item_images[0] : get_placeholder_svg($item['name'], 100, 100, 12);
                                    $price = $item['sale_price'] ?: $item['price'];
                                    $row_total = $price * $item['quantity'];
                                ?>
                                    <tr id="cart-row-<?php echo $item['id']; ?>" class="border-bottom">
                                        <td class="py-4 border-0">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="rounded-3 overflow-hidden border shadow-sm" style="width: 70px; height: 70px;">
                                                    <img src="<?php echo $item_img; ?>" class="w-100 h-100 object-fit-cover" style="object-fit: cover;" alt="<?php echo sanitize($item['name']); ?>">
                                                </div>
                                                <div>
                                                    <h6 class="fw-bold mb-1 text-dark" style="color: var(--text-main) !important;">
                                                        <a href="product.php?slug=<?php echo $item['slug']; ?>" class="text-decoration-none text-dark hover-success">
                                                            <?php echo sanitize($item['name']); ?>
                                                        </a>
                                                    </h6>
                                                    <small class="text-muted"><?php echo sanitize($item['category_name']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-4 border-0">
                                            <span class="fw-semibold"><?php echo format_price($price); ?></span>
                                            <?php if ($item['sale_price']): ?>
                                                <br><small class="text-muted text-decoration-line-through small" style="font-size:0.75rem;"><?php echo format_price($item['price']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 border-0">
                                            <div class="quantity-selector shadow-sm" style="max-width:105px;">
                                                <button type="button" class="quantity-btn p-1.5 px-2" onclick="adjustCartInput(<?php echo $item['id']; ?>, -1)">-</button>
                                                <input type="number" id="qty-input-<?php echo $item['id']; ?>" class="quantity-input" style="width:28px;" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['stock']; ?>" readonly>
                                                <button type="button" class="quantity-btn p-1.5 px-2" onclick="adjustCartInput(<?php echo $item['id']; ?>, 1)">+</button>
                                            </div>
                                        </td>
                                        <td class="py-4 border-0 text-end">
                                            <span id="item-total-<?php echo $item['id']; ?>" class="fw-bold text-dark" style="color: var(--text-main) !important;"><?php echo format_price($row_total); ?></span>
                                        </td>
                                        <td class="py-4 border-0 text-center">
                                            <button type="button" onclick="removeCartItem(<?php echo $item['id']; ?>)" class="btn btn-sm btn-link text-danger border-0 p-0 fs-5" title="Remove Item"><i class="bi bi-trash-fill"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Summary Column -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white p-4 border-secondary-subtle" style="background-color: var(--card-bg) !important; border: 1px solid var(--border-color) !important;">
                    <h5 class="fw-bold text-dark pb-3 border-bottom mb-3" style="color: var(--text-main) !important;">Order Summary</h5>
                    
                    <div class="d-flex justify-content-between mb-2 small text-muted">
                        <span>Items Subtotal:</span>
                        <span id="cartSubtotal" class="fw-semibold text-dark" style="color: var(--text-main) !important;"><?php echo $initial_totals['subtotal_formatted']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 small text-muted">
                        <span>Estimated Tax (5%):</span>
                        <span id="cartTax" class="fw-semibold text-dark" style="color: var(--text-main) !important;"><?php echo $initial_totals['tax_formatted']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3 small text-muted">
                        <span>Standard Delivery:</span>
                        <span id="cartDelivery" class="fw-semibold text-success"><?php echo $initial_totals['delivery_formatted']; ?></span>
                    </div>
                    
                    <div class="alert alert-warning py-2 px-3 small border-0 bg-success bg-opacity-10 text-success rounded-3 mb-4">
                        <i class="bi bi-truck me-1"></i> Add <strong>$50.00</strong> or more for <strong>FREE DELIVERY</strong>!
                    </div>
                    
                    <div class="d-flex justify-content-between pb-3 mb-4 border-top pt-3 border-secondary-subtle">
                        <strong class="text-dark" style="color: var(--text-main) !important;">Grand Total:</strong>
                        <strong id="cartTotal" class="text-success fs-5"><?php echo $initial_totals['total_formatted']; ?></strong>
                    </div>
                    
                    <?php if (is_logged_in()): ?>
                        <a href="checkout.php" class="btn btn-primary-gradient w-100 py-2.5 rounded-pill shadow-sm fw-bold">Proceed to Checkout <i class="bi bi-arrow-right-short fs-5"></i></a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-success w-100 py-2.5 rounded-pill shadow-sm fw-bold">Log In to Checkout</a>
                        <small class="text-center d-block text-muted mt-2 fs-6" style="font-size:0.75rem;">Authentication is secure and takes less than a minute.</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
/**
 * Handle adjustment triggers in Cart quantities row
 */
function adjustCartInput(productId, delta) {
    const input = document.getElementById(`qty-input-${productId}`);
    const minVal = parseInt(input.getAttribute('min')) || 1;
    const maxVal = parseInt(input.getAttribute('max')) || 99;
    let val = parseInt(input.value) || 1;
    
    val += delta;
    if (val < minVal) val = minVal;
    if (val > maxVal) val = maxVal;
    
    if (val !== parseInt(input.value)) {
        input.value = val;
        // Trigger AJAX Update
        updateCartQuantity(productId, val);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
