<?php
/**
 * Dual Supermarket Management System - Configuration File
 * Handles database connection, session safety settings, CSRF, and global constants.
 */

// Define environment constants
define('DB_HOST', 'localhost');
define('DB_NAME', 'supermarket_db');
define('DB_USER', 'root');
define('DB_PASS', ''); // Standard default for XAMPP on Windows
define('APP_NAME', 'FreshCart Supermarket');
define('BASE_URL', '/supermarket');

// Security: Configure session settings before starting session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    
    // In local development HTTP is standard, so secure cookie is conditional
    $isSecure = false;
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        $isSecure = true;
        ini_set('session.cookie_secure', 1);
    }
    
    session_start();
}

// Secure session regeneration logic (regenerate ID every 15 minutes or on privilege shift)
if (!isset($_SESSION['last_regeneration'])) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
} else {
    $interval = 15 * 60; // 15 minutes
    if (time() - $_SESSION['last_regeneration'] > $interval) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// Database Connection using PDO
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // If the database does not exist or connection fails, we catch it and display a gorgeous, friendly setup screen.
    // This allows the application to gracefully guide the user to run schema.sql rather than crash blindly.
    die("
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Database Setup Needed - " . APP_NAME . "</title>
            <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
            <link href='https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap' rel='stylesheet'>
            <style>
                body {
                    font-family: 'Outfit', sans-serif;
                    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .setup-card {
                    background: rgba(255, 255, 255, 0.85);
                    backdrop-filter: blur(15px);
                    border-radius: 20px;
                    border: 1px solid rgba(255, 255, 255, 0.25);
                    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
                    max-width: 600px;
                    padding: 40px;
                }
                .highlight-icon {
                    font-size: 4rem;
                    color: #198754;
                }
            </style>
        </head>
        <body>
            <div class='setup-card text-center container shadow-lg'>
                <div class='highlight-icon mb-3'>🌱</div>
                <h2 class='fw-bold text-dark mb-3'>Database Connection Error</h2>
                <p class='text-muted mb-4'>
                    We could not connect to your MySQL database. Please make sure that your database server (e.g. XAMPP, WAMP) is running, and the database <code>" . DB_NAME . "</code> exists.
                </p>
                <div class='alert alert-info text-start small mb-4'>
                    <strong>How to solve this:</strong><br>
                    1. Create a database named <code>" . DB_NAME . "</code> in phpMyAdmin or MySQL shell.<br>
                    2. Import the schema file located in: <code>/supermarket/schema.sql</code>.<br>
                    3. If you use a different MySQL username/password, edit <code>/supermarket/includes/config.php</code>.
                </div>
                <p class='small text-secondary'>Error Details: " . htmlspecialchars($e->getMessage()) . "</p>
                <a href='' class='btn btn-success px-4 py-2 mt-2 rounded-pill fw-semibold shadow-sm'>Try Again</a>
            </div>
        </body>
        </html>
    ");
}

// CSRF Protection Helpers
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Generates an HTML hidden field containing the CSRF token.
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
}

/**
 * Validates the submitted CSRF token matches the session one.
 */
function validate_csrf($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Validates CSRF for POST requests automatically.
 */
function verify_csrf_post_or_die() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        if (!validate_csrf($token)) {
            http_response_code(403);
            die("Error: Invalid or missing CSRF token. Request blocked.");
        }
    }
}
