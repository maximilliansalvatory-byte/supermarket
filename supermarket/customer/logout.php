<?php
/**
 * Dual Supermarket Management System - Customer Log Out Handler
 */
require_once __DIR__ . '/../includes/config.php';

// Safe destroy of user session parameters
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Start a new blank session just to store the success logout notification
session_start();
$_SESSION['flash_success'] = "You have been logged out successfully.";

header("Location: login.php");
exit;
