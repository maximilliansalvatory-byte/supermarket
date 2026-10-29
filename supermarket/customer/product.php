<?php
/**
 * Dual Supermarket Management System - Product Detail View
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$slug = isset($_GET['slug']) ? sanitize($_GET['slug']) : '';

if (empty($slug)) {
    $_SESSION['flash_error'] = "Product slug missing.";
    header("Location: shop.php");
    exit;
}

// Fetch main product details
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, c.slug as category_slug 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE p.slug = ? AND p.status = 'active'
");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    $_SESSION['flash_error'] = "Requested product could not be found or is currently inactive.";
    header("Location: shop.php");
    exit;
}

$page_title = $product['name'];

// Parse product images
$product_images = json_decode($product['images'], true) ?: [];
if (empty($product_images)) {
    // If no images exist, generate the beautiful SVG vector as standard fallback
    $main_image_src = get_placeholder_svg($product['name'], 500, 500, 24);
} else {
    $main_image_src = BASE_URL . '/uploads/products/' . $product_images[0];
}

// Fetch related items in same category (excluding current product)
$stmt_related = $pdo->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE p.category_id = ? AND p.id != ? AND p.status = 'active' 
    LIMIT 4
");
$stmt_related->execute([$product['category_id'], $product['id']]);
$related_products = $stmt_related->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-5">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb bg-transparent p-0 small">
            <li class="breadcrumb-item"><a href="index.php" class="text-success text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="shop.php" class="text-success text-decoration-none">Shop</a></li>
            <li class="breadcrumb-item"><a href="shop.php?category=<?php echo $product['category_slug']; ?>" class="text-success text-decoration-none"><?php echo sanitize($product['category_name']); ?></a></li>
            <li class="breadcrumb-item active text-muted" aria-current="page"><?php echo sanitize($product['name']); ?></li>
        </ol>
    </nav>
    
    <!-- Main Detail Row -->
    <div class="row g-5">
        <!-- Images Column -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white p-3 mb-3" style="background-color: var(--card-bg) !important; border: 1px solid var(--border-color) !important;">
                <div class="main-gallery-container text-center mb-3 bg-light rounded-3 overflow-hidden" style="height: 400px; display: flex; align-items: center; justify-content: center;">
                    <img id="mainGalleryImage" src="<?php echo $main_image_src; ?>" class="img-fluid h-100 w-100 object-fit-cover" style="object-fit: cover;" alt="<?php echo sanitize($product['name']); ?>">
                </div>
                
                <!-- Thumbnails -->
                <?php if (count($product_images) > 1): ?>
                    <div class="d-flex gap-2 justify-content-center flex-wrap mt-2">
                        <?php foreach ($product_images as $index => $img): ?>
                            <img src="<?php echo BASE_URL . '/uploads/products/' . $img; ?>" 
                                 onclick="updateGalleryImage(this)" 
                                 class="gallery-thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" 
                                 alt="Thumbnail <?php echo $index + 1; ?>">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Info Column -->
        <div class="col-md-6">
            <div class="h-100 d-flex flex-column justify-content-between">
                <div>
                    <!-- Badges Row -->
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="badge bg-success bg-opacity-10 text-success px-2.5 py-1.5 border border-success-subtle rounded-pill"><?php echo sanitize($product['category_name']); ?></span>
                        <?php if ($product['featured']): ?>
                            <span class="badge badge-featured shadow-sm">Featured Selection</span>
                        <?php endif; ?>
                        <?php if ($product['sale_price']): ?>
                            <span class="badge badge-sale shadow-sm">SALE SPECIAL</span>
                        <?php endif; ?>
                    </div>
                    
                    <h1 class="fw-bold text-dark mb-2" style="font-weight: 800; color: var(--text-main) !important;"><?php echo sanitize($product['name']); ?></h1>
                    
                    <!-- Review Summary -->
                    <div class="d-flex align-items-center gap-2 mb-4">
                        <div class="text-warning small">
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                        </div>
                        <span class="small fw-semibold text-success">5.0 <span class="text-muted">(12 customer reviews)</span></span>
                    </div>
                    
                    <!-- Pricing Container -->
                    <div class="p-3 bg-light rounded-3 mb-4 d-inline-block shadow-sm" style="background-color: rgba(25, 135, 84, 0.04) !important; border: 1px solid var(--border-color);">
                        <?php if ($product['sale_price']): ?>
                            <span class="text-danger fw-bold fs-3"><?php echo format_price($product['sale_price']); ?></span>
                            <span class="text-muted text-decoration-line-through fs-5 ms-2"><?php echo format_price($product['price']); ?></span>
                            <span class="badge bg-danger ms-2 px-2.5 py-1 rounded-pill">Save <?php echo format_price($product['price'] - $product['sale_price']); ?></span>
                        <?php else: ?>
                            <span class="text-dark fw-bold fs-3" style="color: var(--text-main) !important;"><?php echo format_price($product['price']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Description Block -->
                    <h6 class="fw-bold text-dark mt-2 mb-2" style="color: var(--text-main) !important;">Product Description</h6>
                    <p class="text-muted small mb-4" style="line-height: 1.6;">
                        <?php echo nl2br(sanitize($product['description'])); ?>
                    </p>
                    
                    <!-- Stock Indicators -->
                    <div class="mb-4">
                        <span class="small fw-bold">Availability:</span>
                        <?php if ($product['stock'] > 15): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle ms-2 px-2.5 py-1"><i class="bi bi-check-circle-fill me-1"></i> In Stock (<?php echo $product['stock']; ?> items available)</span>
                        <?php elseif ($product['stock'] > 0): ?>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle ms-2 px-2.5 py-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Low Stock Only (<?php echo $product['stock']; ?> left!)</span>
                        <?php else: ?>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-2 px-2.5 py-1"><i class="bi bi-x-circle-fill me-1"></i> Sold Out / Out of Stock</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Purchase Actions -->
                <div class="border-top pt-4 mt-2">
                    <?php if ($product['stock'] > 0): ?>
                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <div class="quantity-selector shadow-sm">
                                <button type="button" class="quantity-btn" onclick="adjustProductQty(-1)">-</button>
                                <input type="number" id="detailQtyInput" class="quantity-input" value="1" min="1" max="<?php echo $product['stock']; ?>" readonly>
                                <button type="button" class="quantity-btn" onclick="adjustProductQty(1)">+</button>
                            </div>
                            
                            <button type="button" onclick="triggerAddToCart(<?php echo $product['id']; ?>)" class="btn btn-primary-gradient px-4 py-2.5 rounded-pill shadow-md fw-bold flex-grow-1 flex-sm-grow-0 d-flex align-items-center gap-2 justify-content-center">
                                <i class="bi bi-cart-plus-fill fs-5"></i> Add to Shopping Cart
                            </button>
                        </div>
                    <?php else: ?>
                        <button class="btn btn-secondary py-2.5 rounded-pill w-100 fw-bold border-0 shadow-sm" disabled>Sold Out - Notify Me When Available</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Related Products recommendation -->
    <?php if (!empty($related_products)): ?>
        <div class="mt-5 pt-5 border-top border-secondary-subtle">
            <h3 class="fw-bold mb-4 text-dark" style="color: var(--text-main) !important;"><span class="text-success">Related</span> Items You Might Like</h3>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 g-4">
                <?php foreach ($related_products as $r_prod): 
                    $r_images = json_decode($r_prod['images'], true) ?: [];
                    $r_main_image = !empty($r_images) ? BASE_URL . '/uploads/products/' . $r_images[0] : get_placeholder_svg($r_prod['name'], 300, 300, 16);
                ?>
                    <div class="col">
                        <div class="card hover-lift-card">
                            <div class="position-relative overflow-hidden" style="height: 180px;">
                                <a href="product.php?slug=<?php echo $r_prod['slug']; ?>">
                                    <img src="<?php echo $r_main_image; ?>" class="card-img-top w-100 h-100 object-fit-cover" style="object-fit: cover;" alt="<?php echo sanitize($r_prod['name']); ?>">
                                </a>
                            </div>
                            <div class="card-body p-4 d-flex flex-column">
                                <h6 class="fw-bold mb-2">
                                    <a href="product.php?slug=<?php echo $r_prod['slug']; ?>" class="text-decoration-none text-dark hover-success" style="color: var(--text-main) !important;">
                                        <?php echo sanitize($r_prod['name']); ?>
                                    </a>
                                </h6>
                                <div class="mt-auto d-flex justify-content-between align-items-center">
                                    <span class="text-dark fw-bold" style="color: var(--text-main) !important;"><?php echo format_price($r_prod['sale_price'] ?: $r_prod['price']); ?></span>
                                    <a href="product.php?slug=<?php echo $r_prod['slug']; ?>" class="btn btn-sm btn-outline-success rounded-pill px-3">View Details</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
/**
 * Update the main product detail gallery image with the selected thumbnail.
 */
function updateGalleryImage(thumbElement) {
    // Deactivate previous
    document.querySelectorAll('.gallery-thumbnail').forEach(el => el.classList.remove('active'));
    
    // Activate current
    thumbElement.classList.add('active');
    
    // Set target image source
    document.getElementById('mainGalleryImage').src = thumbElement.src;
}

/**
 * Handle details quantity increments
 */
function adjustProductQty(delta) {
    const input = document.getElementById('detailQtyInput');
    const minVal = parseInt(input.getAttribute('min')) || 1;
    const maxVal = parseInt(input.getAttribute('max')) || 99;
    let val = parseInt(input.value) || 1;
    
    val += delta;
    if (val < minVal) val = minVal;
    if (val > maxVal) val = maxVal;
    
    input.value = val;
}

/**
 * Handle adding quantity to cart via dynamic selector
 */
function triggerAddToCart(productId) {
    const qty = parseInt(document.getElementById('detailQtyInput').value) || 1;
    addToCart(productId, qty);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
