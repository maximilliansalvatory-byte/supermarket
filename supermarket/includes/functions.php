<?php
/**
 * Dual Supermarket Management System - Core Utilities & Functions
 * Includes sanitization, form validation, auth, session checks, and visual generators.
 */

// If config.php has not been loaded, we fail early to prevent orphan execution
if (!defined('APP_NAME')) {
    die("Direct access prohibited.");
}

/**
 * Sanitize text output for prevention of XSS.
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Generates a clean URL slug from string.
 */
function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    if (empty($text)) {
        return 'n-a';
    }
    return $text;
}

/**
 * Validates whether an email structure is syntactically sound.
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Check if current user is logged in.
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

/**
 * Check if the logged in user is an admin.
 */
function is_admin() {
    return is_logged_in() && $_SESSION['user_role'] === 'admin';
}

/**
 * Requires the user to be logged in, otherwise redirects to login.
 */
function require_login($redirect_to = '../customer/login.php') {
    if (!is_logged_in()) {
        $_SESSION['flash_error'] = "You must log in to access this page.";
        header("Location: $redirect_to");
        exit;
    }
}

/**
 * Requires user to be admin, otherwise redirects to admin login.
 */
function require_admin($redirect_to = '../admin/login.php') {
    if (!is_admin()) {
        $_SESSION['flash_error'] = "Access denied. Administrator privileges required.";
        header("Location: $redirect_to");
        exit;
    }
}

/**
 * Retrieve full information of currently logged in user.
 */
function get_logged_in_user($pdo) {
    if (!is_logged_in()) return null;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Log administrative operations to audit database.
 */
function log_admin_action($pdo, $admin_id, $action, $details) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$admin_id, $action, $details, $ip]);
}

/**
 * Simulated Rate Limiting for Login attempts.
 * Allows max 5 login failures from email + IP inside 3 minutes, or blocks for 10 minutes.
 */
function check_login_rate_limit($pdo, $email) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    // Clear old login attempts (older than 10 minutes)
    $pdo->prepare("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 10 MINUTE)")->execute();
    
    // Count failed attempts
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE email = ? AND ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 3 MINUTE)");
    $stmt->execute([$email, $ip]);
    $attempts = $stmt->fetchColumn();
    
    if ($attempts >= 5) {
        return false; // Limit exceeded
    }
    return true; // Limit okay
}

/**
 * Register a failed login attempt.
 */
function register_login_failure($pdo, $email) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt = $pdo->prepare("INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)");
    $stmt->execute([$email, $ip]);
}

/**
 * Clear failed login attempts on successful login.
 */
function clear_login_failures($pdo, $email) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE email = ? AND ip_address = ?");
    $stmt->execute([$email, $ip]);
}

/**
 * Generates an extremely attractive SVG-based gradient vector background representation
 * of a category or product to maintain premium aesthetics when actual images are missing.
 * Colors are generated dynamically based on the string name.
 */
function get_placeholder_svg($name, $width = 300, $height = 300, $font_size = 20) {
    // Generate beautiful complementary HSL colors based on the MD5 hash of the string name.
    $hash = md5($name);
    $hue1 = hexdec(substr($hash, 0, 4)) % 360;
    $hue2 = ($hue1 + 140) % 360;
    
    // Clean, crisp name extraction
    $initials = mb_strtoupper(mb_substr($name, 0, 2));
    
    // Modern SVG markup with a rich gradient and premium glassmorphic border elements
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" viewBox="0 0 '.$width.' '.$height.'" style="background: linear-gradient(135deg, hsl('.$hue1.', 80%, 45%), hsl('.$hue2.', 80%, 30%)); border-radius:12px;">';
    $svg .= '<defs>';
    $svg .= '<filter id="glow"><feGaussianBlur stdDeviation="15" result="coloredBlur"/><feMerge><feMergeNode in="coloredBlur"/><feMergeNode in="SourceGraphic"/></feMerge></filter>';
    $svg .= '</defs>';
    // Decorative glassmorphic circular elements in background
    $svg .= '<circle cx="'.($width * 0.8).'" cy="'.($height * 0.2).'" r="'.($width * 0.25).'" fill="rgba(255,255,255,0.08)" />';
    $svg .= '<circle cx="'.($width * 0.2).'" cy="'.($height * 0.85).'" r="'.($width * 0.15).'" fill="rgba(255,255,255,0.08)" />';
    // Centered label name & initials
    $svg .= '<text x="50%" y="45%" text-anchor="middle" fill="#ffffff" font-family="\'Outfit\', \'Segoe UI\', sans-serif" font-weight="700" font-size="'.($font_size * 2.2).'px" opacity="0.95" letter-spacing="1">'.$initials.'</text>';
    $svg .= '<text x="50%" y="65%" text-anchor="middle" fill="rgba(255,255,255,0.8)" font-family="\'Outfit\', \'Segoe UI\', sans-serif" font-weight="500" font-size="'.$font_size.'px" letter-spacing="0.5">'.sanitize($name).'</text>';
    $svg .= '</svg>';
    
    return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
}

/**
 * Formats a decimal currency quantity into premium aesthetic currency layout.
 */
function format_price($number) {
    return '$' . number_format((float)$number, 2, '.', ',');
}

/**
 * Handle multiple image uploads for products.
 * Validates file sizes (max 5MB), extensions (jpg, jpeg, png, webp),
 * and creates secure random filenames, moving files to the uploads directory.
 * Returns a JSON array string representing the uploaded images.
 */
function handle_product_images_upload($files_array, $existing_images_json = '[]') {
    $uploaded_images = json_decode($existing_images_json, true) ?: [];
    
    if (!isset($files_array['name']) || empty($files_array['name'][0])) {
        return json_encode($uploaded_images);
    }
    
    $target_dir = __DIR__ . '/../uploads/products/';
    
    // Check and create directory if missing
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
    $max_size = 5 * 1024 * 1024; // 5 MB
    
    for ($i = 0; $i < count($files_array['name']); $i++) {
        $file_name = $files_array['name'][$i];
        $file_tmp  = $files_array['tmp_name'][$i];
        $file_size = $files_array['size'][$i];
        $file_err  = $files_array['error'][$i];
        
        if ($file_err === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed_extensions)) {
                if ($file_size <= $max_size) {
                    $new_filename = uniqid('prod_', true) . '.' . $ext;
                    $target_file = $target_dir . $new_filename;
                    
                    if (move_uploaded_file($file_tmp, $target_file)) {
                        $uploaded_images[] = $new_filename;
                    }
                }
            }
        }
    }
    
    return json_encode($uploaded_images);
}

/**
 * Handles uploading a single banner image for category.
 */
function handle_category_image_upload($file, $existing_image = null) {
    if (!isset($file['name']) || empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return $existing_image;
    }
    
    $target_dir = __DIR__ . '/../uploads/';
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (in_array($ext, $allowed_extensions) && $file['size'] <= 5 * 1024 * 1024) {
        $new_filename = 'cat_' . uniqid('', true) . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $target_dir . $new_filename)) {
            // Delete old file if exists
            if ($existing_image && file_exists($target_dir . $existing_image)) {
                @unlink($target_dir . $existing_image);
            }
            return $new_filename;
        }
    }
    
    return $existing_image;
}
