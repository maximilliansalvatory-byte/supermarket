<?php
/**
 * Dual Supermarket Management System - Customer Homepage
 */
$page_title = "Fresh Organic Groceries";
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Fetch active categories
$stmt_cats = $pdo->prepare("SELECT * FROM categories WHERE status = 'active' ORDER BY id ASC LIMIT 5");
$stmt_cats->execute();
$categories = $stmt_cats->fetchAll();

// Fetch featured active products
$stmt_featured = $pdo->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE p.featured = 1 AND p.status = 'active' 
    ORDER BY p.id DESC LIMIT 4"
);
$stmt_featured->execute();
$featured_products = $stmt_featured->fetchAll();

// Fetch newest arrivals active products
$stmt_new = $pdo->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 'active' 
    ORDER BY p.id DESC LIMIT 4
");
$stmt_new->execute();
$new_products = $stmt_new->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Hero Banner Section -->
<div class="container mt-4">
    <div class="hero-section shadow-sm text-dark position-relative">
        <div class="row align-items-center position-relative z-2">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <span class="badge bg-success bg-opacity-25 text-success fw-bold px-3 py-2 rounded-pill mb-3" style="font-size: 0.85rem; border: 1px solid rgba(25,135,84,0.15)">🥕 100% FARM FRESH DELIVERIES</span>
                <h1 class="display-4 fw-extrabold text-dark mb-3" style="font-weight: 800; color: var(--text-main) !important; line-height: 1.15;">
                    Healthy Organic <br><span class="text-success">Groceries</span> Right at Your Door.
                </h1>
                <p class="lead text-muted mb-4 small" style="font-size: 1.1rem;">
                    Premium natural fresh foods, locally sourced dairy, artisanal breads, and fresh organic greens harvested daily just for you.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="shop.php" class="btn btn-primary-gradient px-4 py-2.5 rounded-pill shadow-sm fw-bold">Shop Catalog Now</a>
                    <a href="shop.php?featured=1" class="btn btn-outline-success px-4 py-2.5 rounded-pill shadow-sm fw-bold bg-white border-2">Explore Featured</a>
                </div>
            </div>
            <div class="col-lg-6 text-center d-none d-lg-block position-relative">
                <div class="rounded-5 overflow-hidden shadow-lg border border-white border-4" style="transform: rotate(2deg); max-width: 480px; margin: 0 auto;">
                    <img src="<?php echo get_placeholder_svg('Fresh Harvest Organic Box', 480, 360, 24); ?>" class="img-fluid" alt="Fresh harvest">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Categories Circle Grid -->
<div class="container my-5">
    <div class="text-center mb-5">
        <span class="text-success fw-bold small text-uppercase letter-spacing">Browse Categories</span>
        <h2 class="fw-bold text-dark mt-1" style="color: var(--text-main) !important;">Explore Our Fresh Aisles</h2>
    </div>
    
    <div class="row row-cols-2 row-cols-sm-3 row-cols-md-5 g-4 justify-content-center">
        <?php foreach ($categories as $cat): ?>
            <div class="col">
                <a href="shop.php?category=<?php echo $cat['slug']; ?>" class="text-decoration-none text-dark">
                    <div class="category-circle-card shadow-sm border border-secondary-subtle">
                        <span class="fs-2 mb-2">
                            <?php 
                            // Render aesthetic emojis matching category slugs
                            switch ($cat['slug']) {
                                case 'fresh-fruits': echo '🍎'; break;
                                case 'vegetables': echo '🥦'; break;
                                case 'dairy-eggs': echo '🥛'; break;
                                case 'bakery-bread': echo '🥖'; break;
                                case 'beverages': echo '🥤'; break;
                                default: echo '📦'; break;
                            }
                            ?>
                        </span>
                        <h6 class="fw-bold mb-0 small text-truncate" style="max-width: 110px;"><?php echo sanitize($cat['name']); ?></h6>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Featured Products Section -->
<div class="container my-5 pt-3">
    <div class="d-flex justify-content-between align-items-end mb-4 border-bottom pb-3 border-secondary-subtle">
        <div>
            <span class="text-success fw-bold small text-uppercase">Curated for You</span>
            <h2 class="fw-bold text-dark mt-1 mb-0" style="color: var(--text-main) !important;">Featured Highlights</h2>
        </div>
        <a href="shop.php?featured=1" class="btn btn-sm btn-outline-success rounded-pill fw-semibold border-2 px-3">View All <i class="bi bi-arrow-right-short"></i></a>
    </div>
    
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 g-4">
        <?php if (empty($featured_products)): ?>
            <div class="col-12 text-center py-5">
                <p class="text-muted">No featured products found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($featured_products as $prod): 
                $prod_images = json_decode($prod['images'], true) ?: [];
                $main_image = !empty($prod_images) ? BASE_URL . '/uploads/products/' . $prod_images[0] : get_placeholder_svg($prod['name'], 300, 300, 16);
            ?>
                <div class="col">
                    <div class="card hover-lift-card">
                        <div class="position-relative overflow-hidden" style="height: 220px;">
                            <!-- Badges overlays -->
                            <div class="position-absolute top-0 start-0 m-3 z-3 d-flex flex-column gap-1">
                                <span class="badge badge-featured shadow-sm">Featured</span>
                                <?php if ($prod['sale_price']): ?>
                                    <span class="badge badge-sale shadow-sm">SALE</span>
                                <?php endif; ?>
                            </div>
                            
                            <a href="product.php?slug=<?php echo $prod['slug']; ?>">
                                <img src="<?php echo $main_image; ?>" class="card-img-top w-100 h-100 object-fit-cover transition-transform" style="object-fit: cover;" alt="<?php echo sanitize($prod['name']); ?>">
                            </a>
                        </div>
                        
                        <div class="card-body p-4 d-flex flex-column">
                            <span class="text-muted small mb-1"><?php echo sanitize($prod['category_name']); ?></span>
                            <h5 class="fw-bold mb-2">
                                <a href="product.php?slug=<?php echo $prod['slug']; ?>" class="text-decoration-none text-dark hover-success" style="color: var(--text-main) !important;">
                                    <?php echo sanitize($prod['name']); ?>
                                </a>
                            </h5>
                            
                            <!-- Rating stars representation -->
                            <div class="text-warning small mb-3">
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <span class="text-muted small ms-1">(4.9)</span>
                            </div>
                            
                            <div class="mt-auto d-flex justify-content-between align-items-center">
                                <div>
                                    <?php if ($prod['sale_price']): ?>
                                        <span class="text-danger fw-bold fs-5"><?php echo format_price($prod['sale_price']); ?></span>
                                        <span class="text-muted text-decoration-line-through small ms-1"><?php echo format_price($prod['price']); ?></span>
                                    <?php else: ?>
                                        <span class="text-dark fw-bold fs-5" style="color: var(--text-main) !important;"><?php echo format_price($prod['price']); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($prod['stock'] > 0): ?>
                                    <button type="button" onclick="addToCart(<?php echo $prod['id']; ?>, 1)" class="btn btn-sm btn-success rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;" title="Add to Cart">
                                        <i class="bi bi-plus-lg fs-5"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary small px-2 py-1 border border-secondary-subtle">Sold Out</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Fresh Arrivals Section -->
<div class="container my-5 py-3">
    <div class="d-flex justify-content-between align-items-end mb-4 border-bottom pb-3 border-secondary-subtle">
        <div>
            <span class="text-success fw-bold small text-uppercase">New Stock</span>
            <h2 class="fw-bold text-dark mt-1 mb-0" style="color: var(--text-main) !important;">Fresh Arrivals</h2>
        </div>
        <a href="shop.php" class="btn btn-sm btn-outline-success rounded-pill fw-semibold border-2 px-3">View All <i class="bi bi-arrow-right-short"></i></a>
    </div>
    
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 g-4">
        <?php foreach ($new_products as $prod): 
            $prod_images = json_decode($prod['images'], true) ?: [];
            $main_image = !empty($prod_images) ? BASE_URL . '/uploads/products/' . $prod_images[0] : get_placeholder_svg($prod['name'], 300, 300, 16);
        ?>
            <div class="col">
                <div class="card hover-lift-card">
                    <div class="position-relative overflow-hidden" style="height: 220px;">
                        <?php if ($prod['sale_price']): ?>
                            <span class="position-absolute top-0 start-0 m-3 z-3 badge badge-sale shadow-sm">SALE</span>
                        <?php endif; ?>
                        
                        <a href="product.php?slug=<?php echo $prod['slug']; ?>">
                            <img src="<?php echo $main_image; ?>" class="card-img-top w-100 h-100 object-fit-cover transition-transform" style="object-fit: cover;" alt="<?php echo sanitize($prod['name']); ?>">
                        </a>
                    </div>
                    
                    <div class="card-body p-4 d-flex flex-column">
                        <span class="text-muted small mb-1"><?php echo sanitize($prod['category_name']); ?></span>
                        <h5 class="fw-bold mb-2 text-truncate">
                            <a href="product.php?slug=<?php echo $prod['slug']; ?>" class="text-decoration-none text-dark hover-success" style="color: var(--text-main) !important;">
                                <?php echo sanitize($prod['name']); ?>
                            </a>
                        </h5>
                        
                        <div class="text-warning small mb-3">
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-half"></i>
                            <span class="text-muted small ms-1">(4.7)</span>
                        </div>
                        
                        <div class="mt-auto d-flex justify-content-between align-items-center">
                            <div>
                                <?php if ($prod['sale_price']): ?>
                                    <span class="text-danger fw-bold fs-5"><?php echo format_price($prod['sale_price']); ?></span>
                                    <span class="text-muted text-decoration-line-through small ms-1"><?php echo format_price($prod['price']); ?></span>
                                <?php else: ?>
                                    <span class="text-dark fw-bold fs-5" style="color: var(--text-main) !important;"><?php echo format_price($prod['price']); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($prod['stock'] > 0): ?>
                                <button type="button" onclick="addToCart(<?php echo $prod['id']; ?>, 1)" class="btn btn-sm btn-success rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;" title="Add to Cart">
                                    <i class="bi bi-plus-lg fs-5"></i>
                                </button>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary small px-2 py-1 border border-secondary-subtle">Sold Out</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Promo Section -->
<div class="container my-5 pt-3">
    <div class="row g-4">
        <div class="col-md-6">
            <div class="p-5 rounded-4 text-white shadow-sm border" style="background: linear-gradient(135deg, #111827 0%, #1f2937 100%);">
                <span class="text-success fw-bold small text-uppercase">Weekly Special Offer</span>
                <h3 class="fw-bold mt-2">Organic Greens 25% Off</h3>
                <p class="small text-white-50 mb-4">Stock up on vitamins with premium, locally harvested pesticide-free lettuce, kale, broccoli, and spinach crowns.</p>
                <a href="shop.php?category=vegetables" class="btn btn-success rounded-pill px-4 fw-bold">Get Fresh Greens</a>
            </div>
        </div>
        <div class="col-md-6">
            <div class="p-5 rounded-4 text-white shadow-sm border" style="background: linear-gradient(135deg, #0f5132 0%, #146c43 100%);">
                <span class="text-warning fw-bold small text-uppercase">Fast Delivery</span>
                <h3 class="fw-bold mt-2">Free Same-Day Delivery</h3>
                <p class="small text-white-50 mb-4">Get your organic supermarket goods delivered directly within 2 hours. Free on all orders exceeding $50.</p>
                <a href="shop.php" class="btn btn-warning text-dark rounded-pill px-4 fw-bold">Start Shopping</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
