<?php
/**
 * Dual Supermarket Management System - Customer Base Header
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Safe checking block to prevent hijacking of inactive users
if (is_logged_in()) {
    // If account was blocked during active session, destroy session and force redirect
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_status = $stmt->fetchColumn();
    if ($user_status === 'blocked') {
        session_destroy();
        session_start();
        $_SESSION['flash_error'] = "Your account has been blocked by administrators.";
        header("Location: " . BASE_URL . "/customer/login.php");
        exit;
    }
}

// Fetch active categories for dropdown selection
$stmt_cats = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name ASC");
$header_categories = $stmt_cats->fetchAll();

// Dynamic cart items count calculation
$header_cart_count = 0;
if (is_logged_in()) {
    $stmt_cart = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
    $stmt_cart->execute([$_SESSION['user_id']]);
    $header_cart_count = (int)$stmt_cart->fetchColumn();
} else {
    // Session-based cart fallback for guest visitors
    if (isset($_SESSION['guest_cart'])) {
        foreach ($_SESSION['guest_cart'] as $pid => $qty) {
            $header_cart_count += $qty;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <title><?php echo isset($page_title) ? sanitize($page_title) . " - " . APP_NAME : APP_NAME; ?></title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom Style Sheet -->
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-custom sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo BASE_URL; ?>/customer/index.php">
            <span class="fs-3">🌱</span>
            <span>FreshCart</span>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarText" aria-controls="navbarText" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarText">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 align-items-center">
                <li class="nav-item">
                    <a class="nav-link fw-semibold px-3 text-secondary" href="<?php echo BASE_URL; ?>/customer/index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold px-3 text-secondary" href="<?php echo BASE_URL; ?>/customer/shop.php">Shop Catalog</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle fw-semibold px-3 text-secondary" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Categories
                    </a>
                    <ul class="dropdown-menu border-0 shadow-lg p-2 rounded-3">
                        <?php foreach ($header_categories as $h_cat): ?>
                            <li>
                                <a class="dropdown-menu-item dropdown-item rounded-2 py-2" href="<?php echo BASE_URL; ?>/customer/shop.php?category=<?php echo $h_cat['slug']; ?>">
                                    <?php echo sanitize($h_cat['name']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-success fw-semibold" href="<?php echo BASE_URL; ?>/customer/shop.php">All Categories</a></li>
                    </ul>
                </li>
            </ul>
            
            <div class="d-flex align-items-center gap-3 justify-content-center mt-3 mt-lg-0">
                <!-- Theme Toggle Button -->
                <button class="theme-toggle-btn shadow-sm" id="themeToggle" aria-label="Toggle light/dark theme">
                    <i class="bi bi-moon-stars-fill" id="themeToggleIcon"></i>
                </button>

                <!-- Search shortcut -->
                <form action="<?php echo BASE_URL; ?>/customer/shop.php" method="GET" class="d-none d-md-flex align-items-center position-relative">
                    <input type="text" name="search" placeholder="Search grocer..." class="form-control rounded-pill pe-5 ps-3 py-1 text-muted border-secondary-subtle" style="font-size: 0.9rem; max-width: 180px;">
                    <button type="submit" class="btn btn-sm position-absolute end-0 me-2 text-muted border-0"><i class="bi bi-search"></i></button>
                </form>

                <!-- Cart Button -->
                <a href="<?php echo BASE_URL; ?>/customer/cart.php" class="btn btn-light position-relative rounded-pill border-light-subtle shadow-sm px-3 d-flex align-items-center gap-2 text-secondary bg-white">
                    <i class="bi bi-basket-fill text-success fs-5"></i>
                    <span class="d-none d-lg-inline fw-semibold text-dark">Cart</span>
                    <span id="cartCountBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light">
                        <?php echo $header_cart_count; ?>
                    </span>
                </a>

                <!-- User Session controls -->
                <?php if (is_logged_in()): ?>
                    <div class="dropdown">
                        <button class="btn btn-primary-gradient dropdown-toggle px-3 py-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-fill-check me-1"></i> Hi, <?php echo sanitize(explode(' ', $_SESSION['user_name'])[0]); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg p-2 rounded-3 mt-2">
                            <li><a class="dropdown-item rounded-2 py-2" href="<?php echo BASE_URL; ?>/customer/profile.php"><i class="bi bi-person-badge me-2 text-success"></i> My Profile</a></li>
                            <li><a class="dropdown-item rounded-2 py-2" href="<?php echo BASE_URL; ?>/customer/order-history.php"><i class="bi bi-clock-history me-2 text-success"></i> Order History</a></li>
                            <?php if (is_admin()): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item rounded-2 py-2 text-primary fw-semibold" href="<?php echo BASE_URL; ?>/admin/index.php"><i class="bi bi-speedometer2 me-2"></i> Admin Panel</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item rounded-2 py-2 text-danger" href="<?php echo BASE_URL; ?>/customer/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Log Out</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>/customer/login.php" class="btn btn-outline-success rounded-pill fw-semibold px-4 border-2">Log In</a>
                    <a href="<?php echo BASE_URL; ?>/customer/register.php" class="btn btn-primary-gradient rounded-pill fw-semibold">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- System Flash Messages Alerts System -->
<div class="container mt-3">
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 py-3" role="alert">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill text-success fs-4"></i>
                <div>
                    <strong>Success!</strong> <?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 py-3" role="alert">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill text-danger fs-4"></i>
                <div>
                    <strong>Error:</strong> <?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
</div>
