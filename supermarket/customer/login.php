<?php
/**
 * Dual Supermarket Management System - Customer Authentication Portal
 */
$page_title = "Log In";
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// If already logged in, redirect to catalog index
if (is_logged_in()) {
    header("Location: index.php");
    exit;
}

$errors = [];
$email = "";

// Check if remember_me cookie exists and load email
$remembered_email = isset($_COOKIE['remember_email']) ? $_COOKIE['remember_email'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    verify_csrf_post_or_die();
    
    $email = strtolower(trim($_POST['email']));
    $password = $_POST['password'];
    $remember_me = isset($_POST['remember_me']);
    
    if (empty($email)) $errors[] = "Email is required.";
    if (empty($password)) $errors[] = "Password is required.";
    
    if (empty($errors)) {
        // 1. Rate Limiting Check
        if (!check_login_rate_limit($pdo, $email)) {
            $errors[] = "Too many failed login attempts. Please wait 10 minutes before trying again.";
        } else {
            // 2. Fetch User Record
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            // 3. Verify Password & State
            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] === 'blocked') {
                    $errors[] = "Your account has been blocked. Please contact support.";
                    register_login_failure($pdo, $email);
                } elseif ($user['email_verified'] == 0) {
                    // Save info to display simulation helper box
                    $_SESSION['simulation_email'] = $user['email'];
                    $_SESSION['simulation_token'] = $user['verification_token'];
                    
                    $errors[] = "Your email address is unverified. <a href='verify.php?simulated=1' class='fw-bold text-danger'>Activate Account here</a>.";
                    register_login_failure($pdo, $email);
                } else {
                    // SUCCESS - Log user in
                    clear_login_failures($pdo, $email);
                    
                    // Session initialization
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['last_regeneration'] = time();
                    
                    // Handle "Remember Me" Cookie
                    if ($remember_me) {
                        setcookie('remember_email', $email, time() + (30 * 24 * 60 * 60), '/', '', false, true); // 30 days
                    } else {
                        if (isset($_COOKIE['remember_email'])) {
                            setcookie('remember_email', '', time() - 3600, '/');
                        }
                    }
                    
                    // Transfer session guest cart items to database cart if exists
                    if (isset($_SESSION['guest_cart']) && !empty($_SESSION['guest_cart'])) {
                        foreach ($_SESSION['guest_cart'] as $p_id => $qty) {
                            $stmt_c = $pdo->prepare("
                                INSERT INTO cart (user_id, product_id, quantity) 
                                VALUES (?, ?, ?) 
                                ON DUPLICATE KEY UPDATE quantity = quantity + ?
                            ");
                            $stmt_c->execute([$user['id'], $p_id, $qty, $qty]);
                        }
                        unset($_SESSION['guest_cart']); // Clear session cart
                    }
                    
                    $_SESSION['flash_success'] = "Welcome back, " . sanitize($user['name']) . "!";
                    
                    // Redirect based on role
                    if ($user['role'] === 'admin') {
                        header("Location: " . BASE_URL . "/admin/index.php");
                    } else {
                        header("Location: index.php");
                    }
                    exit;
                }
            } else {
                $errors[] = "Invalid email or password combination.";
                register_login_failure($pdo, $email);
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-7">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden glass-panel">
                <div class="p-4 bg-success text-white text-center" style="background: linear-gradient(135deg, #198754 0%, #10b981 100%) !important;">
                    <span class="fs-1">🛒</span>
                    <h3 class="fw-bold mt-2">Sign In</h3>
                    <p class="mb-0 small text-white-50">Log into FreshCart Customer Portal</p>
                </div>
                <div class="card-body p-4 p-md-5">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger border-0 rounded-3 shadow-sm mb-4">
                            <ul class="mb-0 small p-0 ps-3">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form action="login.php" method="POST">
                        <?php echo csrf_field(); ?>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label small fw-bold">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
                                <input type="email" class="form-control bg-light border-start-0 py-2" id="email" name="email" value="<?php echo sanitize(empty($email) ? $remembered_email : $email); ?>" required placeholder="Enter email...">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label small fw-bold">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
                                <input type="password" class="form-control bg-light border-start-0 py-2" id="password" name="password" required placeholder="Enter password...">
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me" <?php echo !empty($remembered_email) ? 'checked' : ''; ?>>
                                <label class="form-check-label small text-muted" for="remember_me">
                                    Remember me
                                </label>
                            </div>
                            <a href="#" class="small text-success text-decoration-none fw-semibold">Forgot Password?</a>
                        </div>
                        
                        <button type="submit" class="btn btn-primary-gradient w-100 py-2.5 rounded-pill shadow-sm fw-bold">Log In</button>
                    </form>
                    
                    <div class="text-center mt-4">
                        <span class="small text-muted">Don't have an account? <a href="register.php" class="text-success text-decoration-none fw-bold">Sign Up here</a></span>
                    </div>
                </div>
            </div>
            
            <div class="card border-0 shadow-sm rounded-3 mt-4 text-center bg-info bg-opacity-10 border-start border-3 border-info">
                <div class="card-body p-3">
                    <h6 class="fw-bold mb-1 text-info-emphasis">🔑 Quick Sandbox Accounts:</h6>
                    <div class="small text-muted">
                        <strong>Customer:</strong> <code>john@gmail.com</code> / <code>password123</code> <br>
                        <strong>Administrator:</strong> <code>admin@supermarket.com</code> / <code>password123</code>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
