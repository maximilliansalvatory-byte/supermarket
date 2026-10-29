<?php
/**
 * Dual Supermarket Management System - Customer Profile & Preferences
 */
$page_title = "My Profile";
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Auth check
require_login();

$errors = [];
$success_msg = "";

// Handle Profile Details Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    verify_csrf_post_or_die();
    
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $city = sanitize($_POST['city']);
    
    if (empty($name)) {
        $errors[] = "Name field cannot be left blank.";
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, address = ?, city = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $address, $city, $_SESSION['user_id']]);
            
            $_SESSION['user_name'] = $name; // Update active session variable
            $success_msg = "Your profile information has been successfully updated.";
        } catch (PDOException $e) {
            $errors[] = "Failed to update profile info. Database error: " . $e->getMessage();
        }
    }
}

// Handle Secure Password Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    verify_csrf_post_or_die();
    
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $errors[] = "All password fields are required.";
    }
    
    if (empty($errors)) {
        // Fetch current password hash
        $stmt_hash = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt_hash->execute([$_SESSION['user_id']]);
        $hash = $stmt_hash->fetchColumn();
        
        if (!password_verify($current_password, $hash)) {
            $errors[] = "The current password you entered is incorrect.";
        } elseif (strlen($new_password) < 6) {
            $errors[] = "Your new password must be at least 6 characters long.";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "The new password and confirmation password do not match.";
        }
    }
    
    if (empty($errors)) {
        try {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt_up = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt_up->execute([$new_hash, $_SESSION['user_id']]);
            
            $success_msg = "Your password has been successfully updated.";
        } catch (PDOException $e) {
            $errors[] = "Failed to update password. Database error: " . $e->getMessage();
        }
    }
}

// Re-retrieve fresh user dataset
$user = get_logged_in_user($pdo);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-5">
    <h2 class="fw-bold mb-4 text-dark" style="color: var(--text-main) !important;"><span class="text-success">My</span> Profile & Preferences</h2>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger border-0 rounded-3 shadow-sm mb-4">
            <ul class="mb-0 small ps-3">
                <?php foreach ($errors as $err): ?>
                    <li><?php echo $err; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success border-0 rounded-3 shadow-sm mb-4 py-3">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill text-success fs-4"></i>
                <div class="fw-semibold text-success-emphasis"><?php echo $success_msg; ?></div>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="row g-4">
        <!-- Details Card -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white p-4 p-md-5 border-secondary-subtle" style="background-color: var(--card-bg) !important; border: 1px solid var(--border-color) !important;">
                <h4 class="fw-bold mb-4 text-dark" style="color: var(--text-main) !important;"><i class="bi bi-person-gear text-success me-2"></i>Account Information</h4>
                
                <form action="profile.php" method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Email Address</label>
                        <input type="email" class="form-control bg-light border-0 py-2.5" value="<?php echo sanitize($user['email']); ?>" readonly>
                        <small class="text-muted fs-6" style="font-size:0.75rem;">Registered email address cannot be changed in client portal.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label small fw-bold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" class="form-control py-2.5 border-secondary-subtle" value="<?php echo sanitize($user['name']); ?>" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label small fw-bold">Phone Number</label>
                            <input type="text" id="phone" name="phone" class="form-control py-2.5 border-secondary-subtle" value="<?php echo sanitize($user['phone']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="city" class="form-label small fw-bold">Default City</label>
                            <input type="text" id="city" name="city" class="form-control py-2.5 border-secondary-subtle" value="<?php echo sanitize($user['city']); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="address" class="form-label small fw-bold">Default Shipping Address</label>
                        <textarea id="address" name="address" rows="3" class="form-control border-secondary-subtle" placeholder="Street address details for deliveries..."><?php echo sanitize($user['address']); ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary-gradient px-4 py-2.5 rounded-pill shadow-sm fw-bold">Update Account Details</button>
                </form>
            </div>
        </div>
        
        <!-- Secure Password Update Card -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white p-4 p-md-5 border-secondary-subtle" style="background-color: var(--card-bg) !important; border: 1px solid var(--border-color) !important;">
                <h4 class="fw-bold mb-4 text-dark" style="color: var(--text-main) !important;"><i class="bi bi-shield-lock text-success me-2"></i>Security Settings</h4>
                
                <form action="profile.php" method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="change_password" value="1">
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label small fw-bold">Current Password</label>
                        <input type="password" id="current_password" name="current_password" class="form-control py-2.5 border-secondary-subtle" required placeholder="Enter current password...">
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label small fw-bold">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-control py-2.5 border-secondary-subtle" required placeholder="Min 6 characters...">
                    </div>
                    
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label small fw-bold">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control py-2.5 border-secondary-subtle" required placeholder="Re-type new password...">
                    </div>
                    
                    <button type="submit" class="btn btn-outline-success px-4 py-2.5 rounded-pill fw-bold border-2 bg-white">Change Access Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
