<?php
/**
 * Dual Supermarket Management System - Administrative Category CRUD
 */
$page_title = "Category Management";
require_once __DIR__ . '/../includes/admin_header.php';

$errors = [];
$success = "";

// Capture operations (create, edit, delete, toggle)
$action = isset($_GET['action']) ? sanitize($_GET['action']) : 'list';
$cat_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. DELETE ACTION
if ($action === 'delete' && $cat_id > 0) {
    // Verify CSRF via GET parameter check (or direct form submission, but a secure GET token is standard)
    $token = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '';
    if (!validate_csrf($token)) {
        $_SESSION['flash_error'] = "CSRF Verification failed. Category delete blocked.";
        header("Location: categories.php");
        exit;
    }
    
    try {
        // Fetch existing category to delete image
        $stmt_cat = $pdo->prepare("SELECT name, image FROM categories WHERE id = ?");
        $stmt_cat->execute([$cat_id]);
        $cat_data = $stmt_cat->fetch();
        
        if ($cat_data) {
            $stmt_del = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt_del->execute([$cat_id]);
            
            // Delete image if exists
            if ($cat_data['image'] && file_exists(__DIR__ . '/../uploads/' . $cat_data['image'])) {
                @unlink(__DIR__ . '/../uploads/' . $cat_data['image']);
            }
            
            log_admin_action($pdo, $_SESSION['user_id'], 'Category Deleted', "Category '{$cat_data['name']}' (ID: {$cat_id}) deleted permanently.");
            $_SESSION['flash_success'] = "Category successfully deleted.";
        }
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "Failed to delete category: " . $e->getMessage();
    }
    header("Location: categories.php");
    exit;
}

// 2. TOGGLE STATUS ACTION
if ($action === 'toggle' && $cat_id > 0) {
    $token = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '';
    if (!validate_csrf($token)) {
        $_SESSION['flash_error'] = "CSRF Verification failed.";
        header("Location: categories.php");
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT name, status FROM categories WHERE id = ?");
    $stmt->execute([$cat_id]);
    $cat = $stmt->fetch();
    
    if ($cat) {
        $new_status = ($cat['status'] === 'active') ? 'inactive' : 'active';
        $stmt_up = $pdo->prepare("UPDATE categories SET status = ? WHERE id = ?");
        $stmt_up->execute([$new_status, $cat_id]);
        
        log_admin_action($pdo, $_SESSION['user_id'], 'Category Status Toggle', "Category '{$cat['name']}' status changed from {$cat['status']} to {$new_status}.");
        $_SESSION['flash_success'] = "Category status changed to {$new_status}.";
    }
    header("Location: categories.php");
    exit;
}

// 3. CREATE / EDIT SUBMIT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_post_or_die();
    
    $name = sanitize($_POST['name']);
    $slug = slugify($_POST['slug']);
    $status = sanitize($_POST['status'] ?? 'active');
    
    if (empty($name)) $errors[] = "Category name is required.";
    if (empty($slug)) $errors[] = "Category URL slug is required.";
    
    // Check if slug is duplicated
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE slug = ? AND id != ?");
    $stmt_check->execute([$slug, $cat_id]);
    if ($stmt_check->fetchColumn() > 0) {
        $errors[] = "This URL slug already belongs to another category. Please choose a unique name.";
    }
    
    if (empty($errors)) {
        if ($cat_id > 0) {
            // EDIT CATEGORY
            $stmt_old = $pdo->prepare("SELECT image, name FROM categories WHERE id = ?");
            $stmt_old->execute([$cat_id]);
            $old_cat = $stmt_old->fetch();
            
            // Handle image upload
            $image_name = handle_category_image_upload($_FILES['image'] ?? null, $old_cat['image']);
            
            try {
                $stmt_up = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, image = ?, status = ? WHERE id = ?");
                $stmt_up->execute([$name, $slug, $image_name, $status, $cat_id]);
                
                log_admin_action($pdo, $_SESSION['user_id'], 'Category Modified', "Category '{$old_cat['name']}' (ID: {$cat_id}) updated to '{$name}'.");
                $_SESSION['flash_success'] = "Category updated successfully.";
                header("Location: categories.php");
                exit;
            } catch (PDOException $e) {
                $errors[] = "Database update failed: " . $e->getMessage();
            }
        } else {
            // CREATE CATEGORY
            $image_name = handle_category_image_upload($_FILES['image'] ?? null);
            
            try {
                $stmt_in = $pdo->prepare("INSERT INTO categories (name, slug, image, status) VALUES (?, ?, ?, ?)");
                $stmt_in->execute([$name, $slug, $image_name, $status]);
                
                log_admin_action($pdo, $_SESSION['user_id'], 'Category Created', "New category '{$name}' created successfully.");
                $_SESSION['flash_success'] = "New category created successfully.";
                header("Location: categories.php");
                exit;
            } catch (PDOException $e) {
                $errors[] = "Database insert failed: " . $e->getMessage();
            }
        }
    }
}

// Fetch single category for editing form
$edit_cat = null;
if ($action === 'edit' && $cat_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$cat_id]);
    $edit_cat = $stmt->fetch();
}

// Fetch categories list
$stmt_list = $pdo->query("
    SELECT c.*, COUNT(p.id) as product_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id 
    GROUP BY c.id 
    ORDER BY c.id DESC
");
$categories_list = $stmt_list->fetchAll();
?>

<!-- Render Header Action bar -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0 text-dark" style="color: var(--admin-text-main) !important;">Category Shelves</h3>
    <?php if ($action === 'list'): ?>
        <a href="categories.php?action=create" class="btn btn-primary-gradient px-4 rounded-pill fw-bold"><i class="bi bi-plus-circle-fill me-1"></i> Add Category</a>
    <?php else: ?>
        <a href="categories.php" class="btn btn-outline-success px-4 rounded-pill fw-bold border-2 bg-white"><i class="bi bi-arrow-left-short"></i> Cancel & List</a>
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
    $form_title = ($action === 'edit') ? "Edit Category: " . sanitize($edit_cat['name']) : "Create New Category";
    $val_name = ($action === 'edit') ? $edit_cat['name'] : '';
    $val_slug = ($action === 'edit') ? $edit_cat['slug'] : '';
    $val_status = ($action === 'edit') ? $edit_cat['status'] : 'active';
?>
    <div class="card border-0 shadow-sm rounded-4 admin-glass-card p-4 p-md-5 max-w-700">
        <h5 class="fw-bold mb-4 text-dark" style="color: var(--admin-text-main) !important;"><i class="bi bi-tags-fill text-success me-2"></i><?php echo $form_title; ?></h5>
        
        <form action="categories.php?id=<?php echo $cat_id; ?>" method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="catName" class="form-label small fw-bold">Category Title <span class="text-danger">*</span></label>
                    <input type="text" id="catName" name="name" class="form-control py-2.5 border-secondary-subtle" value="<?php echo sanitize($val_name); ?>" required placeholder="e.g. Dairy & Eggs" onkeyup="generateCatSlug(this.value)">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="catSlug" class="form-label small fw-bold">URL Slug <span class="text-danger">*</span></label>
                    <input type="text" id="catSlug" name="slug" class="form-control py-2.5 border-secondary-subtle" value="<?php echo sanitize($val_slug); ?>" required placeholder="e.g. dairy-eggs">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="catImage" class="form-label small fw-bold">Category Banner Image</label>
                    <input type="file" id="catImage" name="image" class="form-control border-secondary-subtle">
                    <?php if ($action === 'edit' && $edit_cat['image']): ?>
                        <div class="mt-2 small text-muted">
                            Current image: <code><?php echo sanitize($edit_cat['image']); ?></code>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="catStatus" class="form-label small fw-bold">Active Status</label>
                    <select id="catStatus" name="status" class="form-select py-2.5 border-secondary-subtle">
                        <option value="active" <?php echo $val_status === 'active' ? 'selected' : ''; ?>>Active (Visible on Frontend)</option>
                        <option value="inactive" <?php echo $val_status === 'inactive' ? 'selected' : ''; ?>>Inactive (Hidden)</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary-gradient px-5 py-2.5 rounded-pill shadow-sm fw-bold mt-3">Save Category changes</button>
        </form>
    </div>

    <script>
    /**
     * Instantly auto-generates clean slugs during category title inputs
     */
    function generateCatSlug(val) {
        const slugInput = document.getElementById('catSlug');
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
                        <th scope="col">Visual Cover</th>
                        <th scope="col">Name</th>
                        <th scope="col">URL Slug</th>
                        <th scope="col" class="text-center">Groceries Count</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories_list as $cat): 
                        $img_path = $cat['image'] ? BASE_URL . '/uploads/' . $cat['image'] : get_placeholder_svg($cat['name'], 80, 50, 9);
                    ?>
                        <tr>
                            <td>
                                <div class="rounded-3 overflow-hidden border bg-light" style="width: 70px; height: 45px;">
                                    <img src="<?php echo $img_path; ?>" class="w-100 h-100 object-fit-cover" style="object-fit: cover;" alt="<?php echo sanitize($cat['name']); ?>">
                                </div>
                            </td>
                            <td><strong class="text-dark" style="color: var(--admin-text-main) !important;"><?php echo sanitize($cat['name']); ?></strong></td>
                            <td><code class="text-muted small"><?php echo sanitize($cat['slug']); ?></code></td>
                            <td class="text-center">
                                <span class="badge bg-light border text-secondary rounded-pill font-monospace" style="font-size:0.75rem;"><?php echo $cat['product_count']; ?> products</span>
                            </td>
                            <td class="text-center">
                                <?php if ($cat['status'] === 'active'): ?>
                                    <a href="categories.php?action=toggle&id=<?php echo $cat['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 text-decoration-none" title="Click to disable">Active</a>
                                <?php else: ?>
                                    <a href="categories.php?action=toggle&id=<?php echo $cat['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2.5 py-1 text-decoration-none" title="Click to activate">Disabled</a>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="categories.php?action=edit&id=<?php echo $cat['id']; ?>" class="btn btn-sm btn-light btn-admin-action border text-primary" title="Edit Category"><i class="bi bi-pencil-fill"></i></a>
                                    <a href="categories.php?action=delete&id=<?php echo $cat['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-sm btn-light btn-admin-action border text-danger" onclick="return confirm('Are you sure you want to delete category \'<?php echo addslashes($cat['name']); ?>\'? This will cascade delete all assigned products!')" title="Delete Category"><i class="bi bi-trash-fill"></i></a>
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
