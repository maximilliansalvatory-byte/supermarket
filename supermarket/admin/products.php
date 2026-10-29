<?php
/**
 * Dual Supermarket Management System - Administrative Product CRUD
 */
$page_title = "Product Management";
require_once __DIR__ . '/../includes/admin_header.php';

$errors = [];
$success = "";

// Capture operation parameters
$action = isset($_GET['action']) ? sanitize($_GET['action']) : 'list';
$prod_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
$filter = isset($_GET['filter']) ? sanitize($_GET['filter']) : 'all';

// 1. DELETE ACTION
if ($action === 'delete' && $prod_id > 0) {
    $token = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '';
    if (!validate_csrf($token)) {
        $_SESSION['flash_error'] = "CSRF Verification failed. Product deletion blocked.";
        header("Location: products.php");
        exit;
    }
    
    try {
        // Fetch product to remove images from disk
        $stmt_p = $pdo->prepare("SELECT name, images FROM products WHERE id = ?");
        $stmt_p->execute([$prod_id]);
        $prod_data = $stmt_p->fetch();
        
        if ($prod_data) {
            $stmt_del = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt_del->execute([$prod_id]);
            
            // Delete associated images
            $images = json_decode($prod_data['images'], true) ?: [];
            foreach ($images as $img) {
                $file_p = __DIR__ . '/../uploads/products/' . $img;
                if (file_exists($file_p)) {
                    @unlink($file_p);
                }
            }
            
            log_admin_action($pdo, $_SESSION['user_id'], 'Product Deleted', "Product '{$prod_data['name']}' (ID: {$prod_id}) deleted permanently.");
            $_SESSION['flash_success'] = "Product deleted successfully.";
        }
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "Failed to delete product: " . $e->getMessage();
    }
    header("Location: products.php");
    exit;
}

// 2. TOGGLE FEATURED / STATUS
if (($action === 'toggle-featured' || $action === 'toggle-status') && $prod_id > 0) {
    $token = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '';
    if (!validate_csrf($token)) {
        $_SESSION['flash_error'] = "CSRF Verification failed.";
        header("Location: products.php");
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT name, featured, status FROM products WHERE id = ?");
    $stmt->execute([$prod_id]);
    $prod = $stmt->fetch();
    
    if ($prod) {
        if ($action === 'toggle-featured') {
            $new_val = $prod['featured'] ? 0 : 1;
            $stmt_up = $pdo->prepare("UPDATE products SET featured = ? WHERE id = ?");
            $stmt_up->execute([$new_val, $prod_id]);
            
            log_admin_action($pdo, $_SESSION['user_id'], 'Product Featured Toggle', "Product '{$prod['name']}' featured changed to {$new_val}.");
            $_SESSION['flash_success'] = "Product featured state updated.";
        } else {
            $new_val = ($prod['status'] === 'active') ? 'inactive' : 'active';
            $stmt_up = $pdo->prepare("UPDATE products SET status = ? WHERE id = ?");
            $stmt_up->execute([$new_val, $prod_id]);
            
            log_admin_action($pdo, $_SESSION['user_id'], 'Product Status Toggle', "Product '{$prod['name']}' status changed to {$new_val}.");
            $_SESSION['flash_success'] = "Product status updated.";
        }
    }
    header("Location: products.php");
    exit;
}

// 3. CREATE / EDIT SUBMIT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_post_or_die();
    
    $category_id = (int)$_POST['category_id'];
    $name = sanitize($_POST['name']);
    $slug = slugify($_POST['slug']);
    $description = sanitize($_POST['description']);
    $price = (float)$_POST['price'];
    $sale_price = !empty($_POST['sale_price']) ? (float)$_POST['sale_price'] : null;
    $stock = (int)$_POST['stock'];
    $featured = isset($_POST['featured']) ? 1 : 0;
    $status = sanitize($_POST['status'] ?? 'active');
    
    // Validations
    if ($category_id <= 0) $errors[] = "Category aisle assignment is required.";
    if (empty($name)) $errors[] = "Product name is required.";
    if (empty($slug)) $errors[] = "URL slug is required.";
    if ($price <= 0) $errors[] = "Original retail price must exceed $0.00.";
    if ($sale_price !== null && $sale_price >= $price) $errors[] = "Discount sale price must be strictly lower than original price.";
    if ($stock < 0) $errors[] = "Initial stock cannot represent negative inventory.";
    
    // Duplication checking
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM products WHERE slug = ? AND id != ?");
    $stmt_check->execute([$slug, $prod_id]);
    if ($stmt_check->fetchColumn() > 0) {
        $errors[] = "This URL slug already belongs to another product. Pick a unique name.";
    }
    
    if (empty($errors)) {
        if ($prod_id > 0) {
            // EDIT PRODUCT
            $stmt_old = $pdo->prepare("SELECT images, name FROM products WHERE id = ?");
            $stmt_old->execute([$prod_id]);
            $old_p = $stmt_old->fetch();
            
            // Multiple images upload helper execution
            $images_json = handle_product_images_upload($_FILES['images'] ?? null, $old_p['images']);
            
            try {
                $stmt_up = $pdo->prepare("
                    UPDATE products 
                    SET category_id = ?, name = ?, slug = ?, description = ?, price = ?, sale_price = ?, stock = ?, images = ?, featured = ?, status = ? 
                    WHERE id = ?
                ");
                $stmt_up->execute([
                    $category_id,
                    $name,
                    $slug,
                    $description,
                    $price,
                    $sale_price,
                    $stock,
                    $images_json,
                    $featured,
                    $status,
                    $prod_id
                ]);
                
                log_admin_action($pdo, $_SESSION['user_id'], 'Product Modified', "Product '{$old_p['name']}' (ID: {$prod_id}) modified to '{$name}'.");
                $_SESSION['flash_success'] = "Product updated successfully.";
                header("Location: products.php");
                exit;
            } catch (PDOException $e) {
                $errors[] = "Database update error: " . $e->getMessage();
            }
        } else {
            // CREATE PRODUCT
            $images_json = handle_product_images_upload($_FILES['images'] ?? null);
            
            try {
                $stmt_in = $pdo->prepare("
                    INSERT INTO products (category_id, name, slug, description, price, sale_price, stock, images, featured, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt_in->execute([
                    $category_id,
                    $name,
                    $slug,
                    $description,
                    $price,
                    $sale_price,
                    $stock,
                    $images_json,
                    $featured,
                    $status
                ]);
                
                log_admin_action($pdo, $_SESSION['user_id'], 'Product Created', "New product '{$name}' created in category ID: {$category_id}.");
                $_SESSION['flash_success'] = "New product created successfully.";
                header("Location: products.php");
                exit;
            } catch (PDOException $e) {
                $errors[] = "Database insert error: " . $e->getMessage();
            }
        }
    }
}

// Fetch categories for select dropdown
$categories_stmt = $pdo->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name ASC");
$categories_list = $categories_stmt->fetchAll();

// Fetch single product for edit form
$edit_prod = null;
if ($action === 'edit' && $prod_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$prod_id]);
    $edit_prod = $stmt->fetch();
}

// Build list query based on stock filters
$query = "
    SELECT p.*, c.name as category_name 
    FROM products p 
    JOIN categories c ON p.category_id = c.id
";

if ($filter === 'low-stock') {
    $query .= " WHERE p.stock <= 15";
}
$query .= " ORDER BY p.id DESC";

$products_list = $pdo->query($query)->fetchAll();
?>

<!-- Action Toolbar header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0 text-dark" style="color: var(--admin-text-main) !important;">Inventory Stockroom</h3>
    <?php if ($action === 'list'): ?>
        <div class="d-flex align-items-center gap-2">
            <a href="products.php?filter=low-stock" class="btn btn-sm btn-outline-danger px-3 rounded-pill <?php echo $filter === 'low-stock' ? 'active' : ''; ?>"><i class="bi bi-exclamation-octagon me-1"></i> Stock Alerts</a>
            <a href="products.php" class="btn btn-sm btn-outline-secondary px-3 rounded-pill <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
            <a href="products.php?action=create" class="btn btn-primary-gradient px-4 rounded-pill fw-bold"><i class="bi bi-plus-circle-fill me-1"></i> Add Product</a>
        </div>
    <?php else: ?>
        <a href="products.php" class="btn btn-outline-success px-4 rounded-pill fw-bold border-2 bg-white"><i class="bi bi-arrow-left-short"></i> Cancel & List</a>
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
     FORM VIEW (CREATE / EDIT)
     ======================================================== -->
<?php if ($action === 'create' || $action === 'edit'): 
    $form_title = ($action === 'edit') ? "Edit Product: " . sanitize($edit_prod['name']) : "Add New Product Stock";
    $val_name = ($action === 'edit') ? $edit_prod['name'] : '';
    $val_slug = ($action === 'edit') ? $edit_prod['slug'] : '';
    $val_cat = ($action === 'edit') ? $edit_prod['category_id'] : 0;
    $val_price = ($action === 'edit') ? $edit_prod['price'] : '';
    $val_sale = ($action === 'edit') ? $edit_prod['sale_price'] : '';
    $val_stock = ($action === 'edit') ? $edit_prod['stock'] : 0;
    $val_desc = ($action === 'edit') ? $edit_prod['description'] : '';
    $val_feat = ($action === 'edit') ? $edit_prod['featured'] : 0;
    $val_status = ($action === 'edit') ? $edit_prod['status'] : 'active';
?>
    <div class="card border-0 shadow-sm rounded-4 admin-glass-card p-4 p-md-5">
        <h5 class="fw-bold mb-4 text-dark" style="color: var(--admin-text-main) !important;"><i class="bi bi-box-fill text-success me-2"></i><?php echo $form_title; ?></h5>
        
        <form action="products.php?id=<?php echo $prod_id; ?>" method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="prodName" class="form-label small fw-bold">Grocery Name <span class="text-danger">*</span></label>
                    <input type="text" id="prodName" name="name" class="form-control py-2.5 border-secondary-subtle" value="<?php echo sanitize($val_name); ?>" required placeholder="e.g. Fresh Lemons" onkeyup="generateProdSlug(this.value)">
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="prodSlug" class="form-label small fw-bold">URL Slug <span class="text-danger">*</span></label>
                    <input type="text" id="prodSlug" name="slug" class="form-control py-2.5 border-secondary-subtle" value="<?php echo sanitize($val_slug); ?>" required placeholder="e.g. fresh-lemons">
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="prodCategory" class="form-label small fw-bold">Category Aisle <span class="text-danger">*</span></label>
                    <select id="prodCategory" name="category_id" class="form-select py-2.5 border-secondary-subtle" required>
                        <option value="">-- Choose Aisle --</option>
                        <?php foreach ($categories_list as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $val_cat == $cat['id'] ? 'selected' : ''; ?>><?php echo sanitize($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="prodPrice" class="form-label small fw-bold">Retail Price ($) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text border-secondary-subtle bg-light">$</span>
                        <input type="number" step="0.01" id="prodPrice" name="price" class="form-control py-2.5 border-secondary-subtle" value="<?php echo $val_price; ?>" required placeholder="4.99">
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="prodSale" class="form-label small fw-bold">Discount Sale Price ($) <span class="text-muted">(Optional)</span></label>
                    <div class="input-group">
                        <span class="input-group-text border-secondary-subtle bg-light">$</span>
                        <input type="number" step="0.01" id="prodSale" name="sale_price" class="form-control py-2.5 border-secondary-subtle" value="<?php echo $val_sale; ?>" placeholder="3.49">
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="prodStock" class="form-label small fw-bold">Quantity in Stock <span class="text-danger">*</span></label>
                    <input type="number" id="prodStock" name="stock" class="form-control py-2.5 border-secondary-subtle" value="<?php echo $val_stock; ?>" required placeholder="100">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="prodImages" class="form-label small fw-bold">Product Images <span class="text-muted">(You can upload multiple files)</span></label>
                    <input type="file" id="prodImages" name="images[]" multiple class="form-control border-secondary-subtle">
                    <?php if ($action === 'edit' && $edit_prod['images']): 
                        $images_arr = json_decode($edit_prod['images'], true) ?: [];
                    ?>
                        <div class="mt-2 d-flex gap-2">
                            <?php foreach ($images_arr as $img): ?>
                                <div class="rounded border p-1 shadow-sm position-relative bg-white" style="width: 50px; height: 50px;">
                                    <img src="<?php echo BASE_URL . '/uploads/products/' . $img; ?>" class="w-100 h-100 object-fit-cover" style="object-fit: cover;">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label for="prodStatus" class="form-label small fw-bold">Status</label>
                    <select id="prodStatus" name="status" class="form-select py-2.5 border-secondary-subtle">
                        <option value="active" <?php echo $val_status === 'active' ? 'selected' : ''; ?>>Active (Visible)</option>
                        <option value="inactive" <?php echo $val_status === 'inactive' ? 'selected' : ''; ?>>Inactive (Hidden)</option>
                    </select>
                </div>
                
                <div class="col-md-3 mb-3 d-flex align-items-end pb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="prodFeatured" name="featured" <?php echo $val_feat ? 'checked' : ''; ?>>
                        <label class="form-check-label small fw-bold" for="prodFeatured">Featured Highlight</label>
                    </div>
                </div>
            </div>
            
            <div class="mb-4">
                <label for="prodDesc" class="form-label small fw-bold">Product Description</label>
                <textarea id="prodDesc" name="description" rows="4" class="form-control border-secondary-subtle" placeholder="Detailed product dimensions, packaging types, organic statements, allergens..."><?php echo sanitize($val_desc); ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary-gradient px-5 py-2.5 rounded-pill shadow-sm fw-bold">Commit Stock Settings</button>
        </form>
    </div>

    <script>
    /**
     * Auto slugify product names during inputs
     */
    function generateProdSlug(val) {
        const slugInput = document.getElementById('prodSlug');
        slugInput.value = val.toLowerCase()
            .replace(/[^\w ]+/g, '')
            .replace(/ +/g, '-');
    }
    </script>

<!-- ========================================================
     LIST VIEW (DEFAULT)
     ======================================================== -->
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 admin-glass-card p-4">
        <div class="table-responsive">
            <table class="table align-middle admin-table mb-0">
                <thead>
                    <tr class="text-muted">
                        <th scope="col">Visual Thumb</th>
                        <th scope="col">Name</th>
                        <th scope="col">Aisle</th>
                        <th scope="col">Price</th>
                        <th scope="col" class="text-center">Inventory</th>
                        <th scope="col" class="text-center">Featured</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products_list as $prod): 
                        $images_arr = json_decode($prod['images'], true) ?: [];
                        $img_path = !empty($images_arr) ? BASE_URL . '/uploads/products/' . $images_arr[0] : get_placeholder_svg($prod['name'], 80, 80, 10);
                    ?>
                        <tr class="<?php echo $prod['stock'] <= 15 ? 'table-warning bg-opacity-25' : ''; ?>">
                            <td>
                                <div class="rounded-3 overflow-hidden border bg-light shadow-sm" style="width: 50px; height: 50px;">
                                    <img src="<?php echo $img_path; ?>" class="w-100 h-100 object-fit-cover" style="object-fit: cover;" alt="<?php echo sanitize($prod['name']); ?>">
                                </div>
                            </td>
                            <td>
                                <strong class="text-dark" style="color: var(--admin-text-main) !important;"><?php echo sanitize($prod['name']); ?></strong> <br>
                                <code class="small text-muted" style="font-size:0.7rem;"><?php echo sanitize($prod['slug']); ?></code>
                            </td>
                            <td><span class="badge bg-light border text-secondary rounded-pill py-1.5 px-2.5"><?php echo sanitize($prod['category_name']); ?></span></td>
                            <td>
                                <?php if ($prod['sale_price']): ?>
                                    <strong class="text-danger"><?php echo format_price($prod['sale_price']); ?></strong> <br>
                                    <small class="text-muted text-decoration-line-through fs-6" style="font-size:0.75rem;"><?php echo format_price($prod['price']); ?></small>
                                <?php else: ?>
                                    <strong class="text-dark" style="color: var(--admin-text-main) !important;"><?php echo format_price($prod['price']); ?></strong>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($prod['stock'] > 15): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle font-monospace rounded-pill px-2.5 py-1"><?php echo $prod['stock']; ?> units</span>
                                <?php elseif ($prod['stock'] > 0): ?>
                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning-subtle font-monospace rounded-pill px-2.5 py-1" title="Restock recommended"><?php echo $prod['stock']; ?> warning</span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle font-monospace rounded-pill px-2.5 py-1">Depleted / 0</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($prod['featured']): ?>
                                    <a href="products.php?action=toggle-featured&id=<?php echo $prod['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-sm text-warning" title="Toggle Featured"><i class="bi bi-star-fill"></i></a>
                                <?php else: ?>
                                    <a href="products.php?action=toggle-featured&id=<?php echo $prod['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-sm text-secondary opacity-50" title="Toggle Featured"><i class="bi bi-star"></i></a>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($prod['status'] === 'active'): ?>
                                    <a href="products.php?action=toggle-status&id=<?php echo $prod['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 text-decoration-none" title="Disable">Active</a>
                                <?php else: ?>
                                    <a href="products.php?action=toggle-status&id=<?php echo $prod['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2.5 py-1.5 text-decoration-none" title="Activate">Disabled</a>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="products.php?action=edit&id=<?php echo $prod['id']; ?>" class="btn btn-sm btn-light btn-admin-action border text-primary" title="Edit Product"><i class="bi bi-pencil-fill"></i></a>
                                    <a href="products.php?action=delete&id=<?php echo $prod['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-sm btn-light btn-admin-action border text-danger" onclick="return confirm('Are you sure you want to permanently delete product \'<?php echo addslashes($prod['name']); ?>\'?')" title="Delete Product"><i class="bi bi-trash-fill"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
