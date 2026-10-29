<?php
/**
 * Dual Supermarket Management System - Product Catalog (Shop)
 */
$page_title = "Shop Catalog";
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Capture filtering parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category_slug = isset($_GET['category']) ? sanitize($_GET['category']) : '';
$min_price = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? (float)$_GET['min_price'] : 0.0;
$max_price = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (float)$_GET['max_price'] : 100.0;
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'newest';
$featured_only = isset($_GET['featured']) ? 1 : 0;

// Base queries construction
$query = "
    SELECT p.*, c.name as category_name, c.slug as category_slug 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 'active'
";
$params = [];

// Apply Search Filter
if (!empty($search)) {
    $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Apply Category Filter
if (!empty($category_slug)) {
    $query .= " AND c.slug = ?";
    $params[] = $category_slug;
}

// Apply Price Filters
if ($min_price > 0) {
    $query .= " AND COALESCE(p.sale_price, p.price) >= ?";
    $params[] = $min_price;
}
if ($max_price > 0 && $max_price < 100) {
    $query .= " AND COALESCE(p.sale_price, p.price) <= ?";
    $params[] = $max_price;
}

// Apply Featured Filter
if ($featured_only) {
    $query .= " AND p.featured = 1";
}

// Apply Sorting
switch ($sort) {
    case 'price-asc':
        $query .= " ORDER BY COALESCE(p.sale_price, p.price) ASC";
        break;
    case 'price-desc':
        $query .= " ORDER BY COALESCE(p.sale_price, p.price) DESC";
        break;
    case 'featured':
        $query .= " ORDER BY p.featured DESC, p.id DESC";
        break;
    case 'newest':
    default:
        $query .= " ORDER BY p.id DESC";
        break;
}

// Execute Catalog Fetch
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Fetch all active categories with product count for sidebar selector
$categories_count_stmt = $pdo->query("
    SELECT c.*, COUNT(p.id) as product_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
    WHERE c.status = 'active' 
    GROUP BY c.id 
    ORDER BY c.name ASC
");
$categories_list = $categories_count_stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-5">
    <div class="row g-4">
        <!-- Sidebar Filters -->
        <div class="col-lg-3">
            <div class="filters-sidebar shadow-sm glass-panel border-secondary-subtle">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h5 class="fw-bold mb-0 text-dark" style="color: var(--text-main) !important;"><i class="bi bi-funnel-fill text-success me-2"></i>Filters</h5>
                    <a href="shop.php" class="small text-decoration-none text-muted hover-success fw-semibold">Reset All</a>
                </div>
                
                <form action="shop.php" method="GET">
                    <!-- Search Input -->
                    <div class="mb-4">
                        <label class="form-label small fw-bold">Search Catalog</label>
                        <div class="input-group">
                            <input type="text" name="search" class="form-control border-end-0 py-2 bg-light-subtle" value="<?php echo sanitize($search); ?>" placeholder="Search product...">
                            <button type="submit" class="input-group-text bg-light-subtle border-start-0 text-muted"><i class="bi bi-search"></i></button>
                        </div>
                    </div>
                    
                    <!-- Categories Selector -->
                    <div class="mb-4">
                        <label class="form-label small fw-bold mb-2">Category Aisles</label>
                        <div class="d-flex flex-column gap-2">
                            <a href="shop.php?search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>" class="small text-decoration-none d-flex justify-content-between align-items-center <?php echo empty($category_slug) ? 'text-success fw-bold' : 'text-muted'; ?>">
                                <span>All Categories</span>
                            </a>
                            <?php foreach ($categories_list as $c_item): ?>
                                <a href="shop.php?category=<?php echo $c_item['slug']; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&min_price=<?php echo $min_price; ?>&max_price=<?php echo $max_price; ?>" class="small text-decoration-none d-flex justify-content-between align-items-center <?php echo $category_slug === $c_item['slug'] ? 'text-success fw-bold' : 'text-muted'; ?>">
                                    <span><?php echo sanitize($c_item['name']); ?></span>
                                    <span class="badge bg-light-subtle border text-secondary rounded-pill font-monospace" style="font-size: 0.75rem;"><?php echo $c_item['product_count']; ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!empty($category_slug)): ?>
                            <input type="hidden" name="category" value="<?php echo $category_slug; ?>">
                        <?php endif; ?>
                    </div>
                    
                    <!-- Price Range slider -->
                    <div class="mb-4">
                        <label class="form-label small fw-bold mb-2">Price Boundaries</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light border-secondary-subtle">$</span>
                                    <input type="number" step="0.01" name="min_price" class="form-control border-secondary-subtle" value="<?php echo $min_price; ?>" placeholder="Min">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light border-secondary-subtle">$</span>
                                    <input type="number" step="0.01" name="max_price" class="form-control border-secondary-subtle" value="<?php echo $max_price; ?>" placeholder="Max">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sorting check values mapping -->
                    <input type="hidden" name="sort" value="<?php echo $sort; ?>">
                    <?php if ($featured_only): ?>
                        <input type="hidden" name="featured" value="1">
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-success btn-sm w-100 py-2 rounded-pill fw-bold shadow-sm mt-2">Apply Filters</button>
                </form>
            </div>
        </div>
        
        <!-- Products Results grid -->
        <div class="col-lg-9">
            <!-- Grid Header toolbar -->
            <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white" style="background-color: var(--card-bg) !important; border: 1px solid var(--border-color) !important;">
                <div class="card-body p-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <span class="text-muted small">Showing <strong class="text-dark" style="color: var(--text-main) !important;"><?php echo count($products); ?></strong> active groceries</span>
                        <?php if (!empty($category_slug)): ?>
                            <span class="badge bg-success bg-opacity-10 text-success ms-2 px-2.5 py-1.5 border border-success-subtle rounded-pill">Aisle: <?php echo str_replace('-', ' ', $category_slug); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-flex align-items-center gap-2">
                        <span class="small text-muted text-nowrap">Sort By:</span>
                        <select onchange="location = this.value;" class="form-select form-select-sm border-secondary-subtle rounded-3" style="width: 170px;">
                            <?php 
                            $url_base = "shop.php?category=" . urlencode($category_slug) . "&search=" . urlencode($search) . "&min_price=" . $min_price . "&max_price=" . $max_price;
                            ?>
                            <option value="<?php echo $url_base; ?>&sort=newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest Arrivals</option>
                            <option value="<?php echo $url_base; ?>&sort=price-asc" <?php echo $sort === 'price-asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="<?php echo $url_base; ?>&sort=price-desc" <?php echo $sort === 'price-desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="<?php echo $url_base; ?>&sort=featured" <?php echo $sort === 'featured' ? 'selected' : ''; ?>>Featured Items</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Products Card Grid -->
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-4">
                <?php if (empty($products)): ?>
                    <div class="col-12 text-center py-5">
                        <div class="fs-1 mb-3">🍃</div>
                        <h4 class="fw-bold">No groceries match your search</h4>
                        <p class="text-muted small">Try adjusting your filters, pricing ranges, or keywords search.</p>
                        <a href="shop.php" class="btn btn-success btn-sm rounded-pill px-4 mt-2 shadow-sm">View All Groceries</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($products as $prod): 
                        $prod_images = json_decode($prod['images'], true) ?: [];
                        $main_image = !empty($prod_images) ? BASE_URL . '/uploads/products/' . $prod_images[0] : get_placeholder_svg($prod['name'], 300, 300, 16);
                    ?>
                        <div class="col">
                            <div class="card hover-lift-card">
                                <div class="position-relative overflow-hidden" style="height: 200px;">
                                    <?php if ($prod['sale_price']): ?>
                                        <span class="position-absolute top-0 start-0 m-3 z-3 badge badge-sale shadow-sm">SALE</span>
                                    <?php endif; ?>
                                    <?php if ($prod['featured']): ?>
                                        <span class="position-absolute top-0 end-0 m-3 z-3 badge badge-featured shadow-sm">Featured</span>
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
                                        <i class="bi bi-star-fill"></i>
                                        <span class="text-muted small ms-1">(4.8)</span>
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
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
