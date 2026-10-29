<?php
/**
 * Dual Supermarket Management System - Customer Email Verification Simulation
 */
$page_title = "Verify Email";
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$message = "";
$status = "info"; // info, success, danger
$token_activated = false;

// If token provided in URL
if (isset($_GET['token'])) {
    $token = sanitize($_GET['token']);
    
    // Find the user matching the token
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE verification_token = ? AND email_verified = 0");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Activate user
        $stmt_update = $pdo->prepare("UPDATE users SET email_verified = 1, verification_token = NULL WHERE id = ?");
        $stmt_update->execute([$user['id']]);
        
        $message = "Congratulations, <strong>" . sanitize($user['name']) . "</strong>! Your email address (<strong>" . sanitize($user['email']) . "</strong>) has been verified. You can now log in.";
        $status = "success";
        $token_activated = true;
        
        // Remove simulation values from session
        unset($_SESSION['simulation_email']);
        unset($_SESSION['simulation_token']);
    } else {
        $message = "Invalid or expired verification token. Please double check the link or request a new one.";
        $status = "danger";
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <!-- Main verification outcome display -->
            <?php if (isset($_GET['token'])): ?>
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden text-center glass-panel">
                    <div class="card-body p-5">
                        <?php if ($status === 'success'): ?>
                            <div class="mb-4 text-success display-1"><i class="bi bi-patch-check-fill animate-bounce"></i></div>
                            <h3 class="fw-bold text-success">Verification Successful!</h3>
                        <?php else: ?>
                            <div class="mb-4 text-danger display-1"><i class="bi bi-x-circle-fill"></i></div>
                            <h3 class="fw-bold text-danger">Verification Failed</h3>
                        <?php endif; ?>
                        
                        <p class="lead text-muted mt-3 mb-4 small"><?php echo $message; ?></p>
                        
                        <?php if ($token_activated): ?>
                            <a href="login.php" class="btn btn-primary-gradient px-5 py-2.5 rounded-pill shadow-sm fw-bold">Proceed to Log In</a>
                        <?php else: ?>
                            <a href="register.php" class="btn btn-outline-secondary px-5 py-2.5 rounded-pill shadow-sm fw-bold">Back to Sign Up</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Simulated Inbox Visual Tool -->
            <?php if (isset($_GET['simulated']) && isset($_SESSION['simulation_token'])): ?>
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden mt-4 border-start border-4 border-success">
                    <div class="card-header bg-success-subtle py-3 px-4">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fs-4">📬</span>
                            <div>
                                <h6 class="mb-0 fw-bold text-success-emphasis">Simulated Mail Server Sandbox</h6>
                                <small class="text-muted">Local testing helper sandbox (Simulating SMTP)</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4 bg-light-subtle">
                        <div class="p-3 border rounded-3 bg-white shadow-sm mb-4">
                            <div class="border-bottom pb-2 mb-3 small text-muted">
                                <strong>From:</strong> accounts@freshcart.com <br>
                                <strong>To:</strong> <?php echo sanitize($_SESSION['simulation_email']); ?> <br>
                                <strong>Subject:</strong> Activate your FreshCart Supermarket Account!
                            </div>
                            <p class="small text-dark">Hello,</p>
                            <p class="small text-dark">Thank you for registering at <strong>FreshCart Supermarket</strong>! Please complete your registration by verifying your email address using the link below:</p>
                            
                            <div class="text-center my-4">
                                <a href="verify.php?token=<?php echo $_SESSION['simulation_token']; ?>" class="btn btn-success btn-sm px-4 py-2 rounded-pill fw-bold shadow-sm">Verify Email Address</a>
                            </div>
                            
                            <p class="small text-muted mb-0">If the button doesn't work, copy/paste this URL in your browser: <br>
                                <a href="verify.php?token=<?php echo $_SESSION['simulation_token']; ?>" class="text-break small text-success"><?php echo $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/customer/verify.php?token=' . $_SESSION['simulation_token']; ?></a>
                            </p>
                        </div>
                        <div class="alert alert-info text-start small mb-0 py-2 border-0 rounded-3">
                            <i class="bi bi-info-circle-fill me-1"></i> Since you are in a sandbox development environment, you can click the button in this mock email box above to instantly activate this account.
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!isset($_GET['token']) && !isset($_GET['simulated'])): ?>
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden text-center glass-panel p-5">
                    <div class="text-warning display-2"><i class="bi bi-envelope-exclamation-fill"></i></div>
                    <h3 class="fw-bold mt-3">Email Activation Necessary</h3>
                    <p class="text-muted small mt-2">You must click the unique activation link sent to your inbox to enable purchase operations.</p>
                    <a href="index.php" class="btn btn-outline-success px-4 py-2 rounded-pill mt-3">Return Home</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
