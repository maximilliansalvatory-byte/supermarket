<?php
/**
 * Dual Supermarket Management System - Customer Registration
 */
$page_title = "Register";
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// If already logged in, redirect to index
if (is_logged_in()) {
    header("Location: index.php");
    exit;
}

$errors = [];
$name = $email = $phone = $address = $city = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF verification
    verify_csrf_post_or_die();
    
    // Capture and sanitize input
    $name = sanitize($_POST['name']);
    $email = strtolower(trim($_POST['email']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $city = sanitize($_POST['city']);
    
    // Field validations
    if (empty($name)) $errors[] = "Name is required.";
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!is_valid_email($email)) {
        $errors[] = "Invalid email format.";
    }
    
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    
    // Check if email already exists in database
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "An account with this email address already exists.";
        }
    }
    
    // Insert new user if no errors
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $verification_token = bin2hex(random_bytes(32));
            
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, password, role, phone, address, city, status, verification_token, email_verified) 
                VALUES (?, ?, ?, 'customer', ?, ?, ?, 'active', ?, 0)
            ");
            
            $stmt->execute([
                $name,
                $email,
                $hashed_password,
                $phone,
                $address,
                $city,
                $verification_token
            ]);
            
            // Registration success - set up a simulation page session parameter to display the fake email link
            $_SESSION['simulation_email'] = $email;
            $_SESSION['simulation_token'] = $verification_token;
            $_SESSION['flash_success'] = "Registration successful! A simulated verification email has been dispatched.";
            
            header("Location: verify.php?simulated=1");
            exit;
        } catch (PDOException $e) {
            $errors[] = "Database Error: Failed to register user. " . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden glass-panel">
                <div class="p-4 bg-success text-white text-center" style="background: linear-gradient(135deg, #198754 0%, #10b981 100%) !important;">
                    <span class="fs-1">🌱</span>
                    <h3 class="fw-bold mt-2">Create Account</h3>
                    <p class="mb-0 small text-white-50">Join FreshCart and enjoy organic groceries delivered today</p>
                </div>
                <div class="card-body p-4 p-md-5">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger border-0 rounded-3 shadow-sm mb-4">
                            <ul class="mb-0 small">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form action="register.php" method="POST" class="needs-validation">
                        <?php echo csrf_field(); ?>
                        
                        <div class="mb-3">
                            <label for="name" class="form-label small fw-bold">Full Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-person text-muted"></i></span>
                                <input type="text" class="form-control bg-light border-start-0 py-2" id="name" name="name" value="<?php echo sanitize($name); ?>" required placeholder="e.g. John Doe">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label small fw-bold">Email Address <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
                                <input type="email" class="form-control bg-light border-start-0 py-2" id="email" name="email" value="<?php echo sanitize($email); ?>" required placeholder="e.g. john@example.com">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label small fw-bold">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
                                    <input type="password" class="form-control bg-light border-start-0 py-2" id="password" name="password" required placeholder="Minimum 6 chars">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label small fw-bold">Confirm Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-shield-lock text-muted"></i></span>
                                    <input type="password" class="form-control bg-light border-start-0 py-2" id="confirm_password" name="confirm_password" required placeholder="Re-type password">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label small fw-bold">Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-telephone text-muted"></i></span>
                                <input type="text" class="form-control bg-light border-start-0 py-2" id="phone" name="phone" value="<?php echo sanitize($phone); ?>" placeholder="e.g. +1 234 567 8900">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="address" class="form-label small fw-bold">Delivery Address</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-geo-alt text-muted"></i></span>
                                    <input type="text" class="form-control bg-light border-start-0 py-2" id="address" name="address" value="<?php echo sanitize($address); ?>" placeholder="Street name and apartment">
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="city" class="form-label small fw-bold">City</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-building text-muted"></i></span>
                                    <input type="text" class="form-control bg-light border-start-0 py-2" id="city" name="city" value="<?php echo sanitize($city); ?>" placeholder="City">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="terms" required checked>
                            <label class="form-check-label small text-muted" for="terms">
                                I agree to the <a href="#" class="text-success text-decoration-none fw-semibold">Terms of Service</a> & <a href="#" class="text-success text-decoration-none fw-semibold">Privacy Policy</a>
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary-gradient w-100 py-2.5 rounded-pill shadow-sm fw-bold">Sign Up Account</button>
                    </form>
                    
                    <div class="text-center mt-4">
                        <span class="small text-muted">Already have an account? <a href="login.php" class="text-success text-decoration-none fw-bold">Log In here</a></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
