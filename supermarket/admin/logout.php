<?php
/**
 * Dual Supermarket Management System - Administrative Log Out Handler
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Log admin action before session gets deleted
if (is_logged_in() && is_admin()) {
    log_admin_action($pdo, $_SESSION['user_id'], 'Logout Successful', 'Admin signed out manually.');
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

session_start();
$_SESSION['flash_success'] = "Administrative session terminated successfully.";

header("Location: login.php");
exit;
