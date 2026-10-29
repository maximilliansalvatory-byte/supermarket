<?php
/**
 * Dual Supermarket Management System - Administrative Login Portal (CORRECTED)
 */
session_start();
$page_title = "Admin Login";

// Stub for missing includes - in real app, require the actual files
if (!function_exists('is_logged_in')) {
    function is_logged_in() { return isset($_SESSION['user_id']); }
    function is_admin() { return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'; }
    function verify_csrf_post_or_die() { /* Basic check - implement real CSRF in production */ }
    function csrf_field() { return '<input type="hidden" name="csrf_token" value="' . bin2hex(random_bytes(32)) . '">'; }
}

// Simple demo PDO stub (replace with real DB connection)
try {
    $pdo = new PDO('sqlite::memory:'); // Or use your real DSN
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Demo: Create in-memory admin user for testing
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, name TEXT, email TEXT UNIQUE, password TEXT, role TEXT, status TEXT)");
    $hashed = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT OR IGNORE INTO users (name, email, password, role, status) VALUES ('Admin User', 'admin@supermarket.com', '$hashed', 'admin', 'active')");
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

if (is_logged_in() && is_admin()) {
    header("Location: index.php");
    exit;
}

$errors = [];
$email = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_post_or_die();
    
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    
    if (empty($email)) $errors[] = "Email address is required.";
    if (empty($password)) $errors[] = "Password is required.";
    
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            if ($user['role'] !== 'admin') {
                $errors[] = "Access Denied. You do not have administrative privileges.";
            } elseif ($user['status'] === 'blocked') {
                $errors[] = "Administrative account has been suspended.";
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['last_regeneration'] = time();
                
                $_SESSION['flash_success'] = "Welcome back, " . htmlspecialchars($user['name']) . ".";
                header("Location: index.php");
                exit;
            }
        } else {
            $errors[] = "Invalid administrative credentials.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - FreshCart Controls</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f1f5f9;
        }
        .login-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            max-width: 420px;
            width: 100%;
        }
    </style>
</head>
<body>
<div class="login-card p-4 p-md-5 container shadow-lg">
    <div class="text-center mb-4">
        <span class="fs-1">🌱</span>
        <h3 class="fw-bold text-white mt-2">FreshCart Admin</h3>
        <p class="small text-muted mb-0">System Control Portal Access</p>
    </div>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger border-0 rounded-3 small p-2.5 mb-4">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <form action="login.php" method="POST">
        <?= csrf_field(); ?>
        <div class="mb-3">
            <label for="email" class="form-label text-muted small fw-bold">Admin Email Address</label>
            <div class="input-group">
                <span class="input-group-text bg-secondary bg-opacity-10 border-0 text-white"><i class="bi bi-envelope"></i></span>
                <input type="email" id="email" name="email" class="form-control border-0 bg-secondary bg-opacity-25 text-white py-2" required placeholder="admin@supermarket.com" value="<?= htmlspecialchars($email) ?>">
            </div>
        </div>
        
        <div class="mb-4">
            <label for="password" class="form-label text-muted small fw-bold">Access Password</label>
            <div class="input-group">
                <span class="input-group-text bg-secondary bg-opacity-10 border-0 text-white"><i class="bi bi-lock"></i></span>
                <input type="password" id="password" name="password" class="form-control border-0 bg-secondary bg-opacity-25 text-white py-2" required placeholder="Enter password...">
            </div>
        </div>
        
        <button type="submit" class="btn btn-success w-100 py-2.5 rounded-pill fw-bold shadow-lg mt-2 border-0" style="background: linear-gradient(135deg, #10b981 0%, #198754 100%)">
            <i class="bi bi-shield-check me-1"></i> Authenticate Access
        </button>
    </form>
    
    <div class="text-center mt-4">
        <a href="../customer/index.php" class="text-muted small text-decoration-none">← Return to Main Shop</a>
    </div>
</div>
</body>
</html>