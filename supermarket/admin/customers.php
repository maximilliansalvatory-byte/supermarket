<?php
/**
 * Dual Supermarket Management System - Administrative Customer Management
 */
$page_title = "Customer Accounts";
require_once __DIR__ . '/../includes/admin_header.php';

$errors = [];
$success = "";

// Capture operations (view, edit, toggle-block, delete)
$action = isset($_GET['action']) ? sanitize($_GET['action']) : 'list';
$cust_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. DELETE CUSTOMER ACTION
if ($action === 'delete' && $cust_id > 0) {
    $token = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '';
    if (!validate_csrf($token)) {
        $_SESSION['flash_error'] = "CSRF Verification failed. Account delete blocked.";
        header("Location: customers.php");
        exit;
    }
    
    // Prevent deleting oneself
    if ($cust_id === (int)$_SESSION['user_id']) {
        $_SESSION['flash_error'] = "You cannot delete your own administrative account.";
        header("Location: customers.php");
        exit;
    }
    
    try {
        $stmt_u = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
        $stmt_u->execute([$cust_id]);
        $user_data = $stmt_u->fetch();
        
        if ($user_data) {
            $stmt_del = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt_del->execute([$cust_id]);
            
            log_admin_action($pdo, $_SESSION['user_id'], 'Account Deleted', "User account '{$user_data['name']}' ({$user_data['email']}) deleted permanently.");
            $_SESSION['flash_success'] = "Customer account successfully deleted.";
        }
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "Failed to delete account: " . $e->getMessage();
    }
    header("Location: customers.php");
    exit;
}

// 2. TOGGLE BLOCK / ACTIVE ACTION
if ($action === 'toggle-block' && $cust_id > 0) {
    $token = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '';
    if (!validate_csrf($token)) {
        $_SESSION['flash_error'] = "CSRF Verification failed.";
        header("Location: customers.php");
        exit;
    }
    
    if ($cust_id === (int)$_SESSION['user_id']) {
        $_SESSION['flash_error'] = "You cannot block your own administrative account.";
        header("Location: customers.php");
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT name, status, role FROM users WHERE id = ?");
    $stmt->execute([$cust_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        $new_status = ($user['status'] === 'active') ? 'blocked' : 'active';
        $stmt_up = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt_up->execute([$new_status, $cust_id]);
        
        log_admin_action($pdo, $_SESSION['user_id'], 'Account Status Updated', "User '{$user['name']}' status changed from {$user['status']} to {$new_status}.");
        $_SESSION['flash_success'] = "User account changed to {$new_status}.";
    }
    header("Location: customers.php");
    exit;
}

// 3. EDIT FORM SUBMIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $cust_id > 0) {
    verify_csrf_post_or_die();
    
    $name = sanitize($_POST['name']);
    $email = strtolower(trim($_POST['email']));
    $role = sanitize($_POST['role'] ?? 'customer');
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $city = sanitize($_POST['city']);
    $status = sanitize($_POST['status'] ?? 'active');
    
    if (empty($name)) $errors[] = "Name is required.";
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!is_valid_email($email)) {
        $errors[] = "Invalid email format.";
    }
    
    // Check duplication
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
    $stmt_check->execute([$email, $cust_id]);
    if ($stmt_check->fetchColumn() > 0) {
        $errors[] = "This email is already registered to another user.";
    }
    
    if (empty($errors)) {
        try {
            $stmt_up = $pdo->prepare("
                UPDATE users SET name = ?, email = ?, role = ?, phone = ?, address = ?, city = ?, status = ? WHERE id = ?
            ");
            $stmt_up->execute([$name, $email, $role, $phone, $address, $city, $status, $cust_id]);
            
            log_admin_action($pdo, $_SESSION['user_id'], 'Account Modified', "User account '{$email}' (ID: {$cust_id}) edited by administrator.");
            $_SESSION['flash_success'] = "Customer profile details updated successfully.";
            header("Location: customers.php");
            exit;
        } catch (PDOException $e) {
            $errors[] = "Failed to update database: " . $e->getMessage();
        }
    }
}

// Fetch single record for editing form
$edit_user = null;
if ($action === 'edit' && $cust_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$cust_id]);
    $edit_user = $stmt->fetch();
}

// Fetch all registered customers list
$stmt_list = $pdo->query("
    SELECT u.*, COUNT(o.id) as order_count 
    FROM users u 
    LEFT JOIN orders o ON u.id = o.user_id 
    GROUP BY u.id 
    ORDER BY u.id DESC
");
$users_list = $stmt_list->fetchAll();
?>

<!-- Title Action banner bar -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0 text-dark" style="color: var(--admin-text-main) !important;">Customer Registers</h3>
    <?php if ($action !== 'list'): ?>
        <a href="customers.php" class="btn btn-outline-success px-4 rounded-pill fw-bold border-2 bg-white"><i class="bi bi-arrow-left-short"></i> Cancel & Return</a>
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
     FORM VIEW (EDIT)
     ======================================================== -->
<?php if ($action === 'edit' && $edit_user): ?>
    <div class="card border-0 shadow-sm rounded-4 admin-glass-card p-4 p-md-5 max-w-700">
        <h5 class="fw-bold mb-4 text-dark" style="color: var(--admin-text-main) !important;"><i class="bi bi-person-gear text-success me-2"></i>Modify Customer: <?php echo sanitize($edit_user['name']); ?></h5>
        
        <form action="customers.php?action=edit&id=<?php echo $cust_id; ?>" method="POST">
            <?php echo csrf_field(); ?>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="userName" class="form-label small fw-bold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" id="userName" name="name" class="form-control py-2.5 border-secondary-subtle" value="<?php echo sanitize($edit_user['name']); ?>" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="userEmail" class="form-label small fw-bold">Email Address <span class="text-danger">*</span></label>
                    <input type="email" id="userEmail" name="email" class="form-control py-2.5 border-secondary-subtle" value="<?php echo sanitize($edit_user['email']); ?>" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="userRole" class="form-label small fw-bold">Platform Role</label>
                    <select id="userRole" name="role" class="form-select py-2.5 border-secondary-subtle" <?php echo $cust_id === (int)$_SESSION['user_id'] ? 'disabled' : ''; ?>>
                        <option value="customer" <?php echo $edit_user['role'] === 'customer' ? 'selected' : ''; ?>>Customer</option>
                        <option value="admin" <?php echo $edit_user['role'] === 'admin' ? 'selected' : ''; ?>>System Administrator</option>
                    </select>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="userStatus" class="form-label small fw-bold">Account status</label>
                    <select id="userStatus" name="status" class="form-select py-2.5 border-secondary-subtle" <?php echo $cust_id === (int)$_SESSION['user_id'] ? 'disabled' : ''; ?>>
                        <option value="active" <?php echo $edit_user['status'] === 'active' ? 'selected' : ''; ?>>Active (Regular)</option>
                        <option value="inactive" <?php echo $edit_user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive (Unverified)</option>
                        <option value="blocked" <?php echo $edit_user['status'] === 'blocked' ? 'selected' : ''; ?>>Blocked (Banned)</option>
                    </select>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="userPhone" class="form-label small fw-bold">Phone Number</label>
                    <input type="text" id="userPhone" name="phone" class="form-control py-2.5 border-secondary-subtle" value="<?php echo sanitize($edit_user['phone']); ?>">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label for="userAddress" class="form-label small fw-bold">Shipping Address</label>
                    <input type="text" id="userAddress" name="address" class="form-control py-2.5 border-secondary-subtle" value="<?php echo sanitize($edit_user['address']); ?>">
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="userCity" class="form-label small fw-bold">City</label>
                    <input type="text" id="userCity" name="city" class="form-control py-2.5 border-secondary-subtle" value="<?php echo sanitize($edit_user['city']); ?>">
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary-gradient px-5 py-2.5 rounded-pill shadow-sm fw-bold mt-3">Update Customer account</button>
        </form>
    </div>

<!-- ========================================================
     LIST VIEW (DEFAULT)
     ======================================================== -->
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 admin-glass-card p-4">
        <div class="table-responsive">
            <table class="table align-middle admin-table mb-0">
                <thead>
                    <tr class="text-muted">
                        <th scope="col">Name / Credentials</th>
                        <th scope="col">Role</th>
                        <th scope="col">Contact</th>
                        <th scope="col">Address</th>
                        <th scope="col" class="text-center">Orders</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users_list as $u): 
                        $status_badge = 'bg-success-subtle text-success border-success-subtle';
                        if ($u['status'] === 'blocked') $status_badge = 'bg-danger-subtle text-danger border-danger-subtle';
                        if ($u['status'] === 'inactive') $status_badge = 'bg-secondary-subtle text-secondary border-secondary-subtle';
                        
                        $role_badge = $u['role'] === 'admin' ? 'bg-primary' : 'bg-secondary';
                    ?>
                        <tr class="<?php echo $u['status'] === 'blocked' ? 'table-danger bg-opacity-25' : ''; ?>">
                            <td>
                                <strong class="text-dark" style="color: var(--admin-text-main) !important;"><?php echo sanitize($u['name']); ?></strong> <br>
                                <code class="small text-muted" style="font-size:0.75rem;"><?php echo sanitize($u['email']); ?></code>
                            </td>
                            <td><span class="badge <?php echo $role_badge; ?> rounded-pill px-2.5 py-1"><?php echo $u['role']; ?></span></td>
                            <td><span class="small text-muted"><?php echo sanitize($u['phone'] ?: 'N/A'); ?></span></td>
                            <td>
                                <?php if ($u['address']): ?>
                                    <span class="small text-muted" style="font-size:0.8rem;"><?php echo sanitize($u['address']); ?>, <?php echo sanitize($u['city']); ?></span>
                                <?php else: ?>
                                    <span class="small text-secondary-emphasis font-monospace" style="font-size:0.75rem;">None saved</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light border text-secondary rounded-pill font-monospace" style="font-size:0.75rem;"><?php echo $u['order_count']; ?> orders</span>
                            </td>
                            <td class="text-center">
                                <?php if ($u['id'] === (int)$_SESSION['user_id']): ?>
                                    <span class="badge bg-success-subtle text-success border px-2.5 py-1.5">You</span>
                                <?php else: ?>
                                    <a href="customers.php?action=toggle-block&id=<?php echo $u['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="badge <?php echo $status_badge; ?> border px-2.5 py-1.5 text-decoration-none" title="Toggle block state"><?php echo $u['status']; ?></a>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="customers.php?action=edit&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-light btn-admin-action border text-primary" title="Modify Details"><i class="bi bi-pencil-fill"></i></a>
                                    <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                                        <a href="customers.php?action=delete&id=<?php echo $u['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-sm btn-light btn-admin-action border text-danger" onclick="return confirm('Are you sure you want to permanently delete customer account \'<?php echo addslashes($u['name']); ?>\'? This deletes their entire orders history!')" title="Delete Account"><i class="bi bi-trash-fill"></i></a>
                                    <?php endif; ?>
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
