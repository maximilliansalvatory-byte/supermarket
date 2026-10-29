<?php
session_start();

// Force full admin session
$_SESSION['admin_id']     = 1;
$_SESSION['admin_email']  = 'admin@supermarket.com';
$_SESSION['role']         = 'admin';
$_SESSION['admin_name']   = 'System Administrator';
$_SESSION['admin_logged_in'] = true;   // Add this line

// Redirect to dashboard
header("Location: index.php");
exit;
?>