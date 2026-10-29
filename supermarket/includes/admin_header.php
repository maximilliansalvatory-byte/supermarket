<?php
/**
 * Dual Supermarket Management System - Administrative Layout Header
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Strict Admin Gate check
require_admin();

// Retrieve Admin data
$admin_user = get_logged_in_user($pdo);

// Retrieve count of low stock items for visual alerts (stock <= 15)
$stmt_stock_alert = $pdo->query("SELECT COUNT(*) FROM products WHERE stock <= 15 AND status = 'active'");
$low_stock_count = (int)$stmt_stock_alert->fetchColumn();

// Define current page slug to apply active class in sidebar links
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <title><?php echo isset($page_title) ? sanitize($page_title) . " - Admin Panel" : "FreshCart Controls"; ?></title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Chart.js (for beautiful sales reports rendering) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Administrative Custom Stylesheet -->
    <link href="<?php echo BASE_URL; ?>/assets/css/admin.css" rel="stylesheet">
</head>
<body>

<!-- Sidebar navigation panel -->
<div class="admin-sidebar" id="adminSidebar">
    <div class="p-4 d-flex align-items-center gap-2 admin-sidebar-brand">
        <span class="fs-3">🌱</span>
        <span class="fs-4 fw-bold">FreshCart</span>
    </div>
    
    <div class="mt-4">
        <a href="<?php echo BASE_URL; ?>/admin/index.php" class="admin-nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="<?php echo BASE_URL; ?>/admin/products.php" class="admin-nav-link <?php echo $current_page === 'products.php' ? 'active' : ''; ?>">
            <i class="bi bi-box-seam"></i>
            <span>Products</span>
            <?php if ($low_stock_count > 0): ?>
                <span class="badge bg-danger ms-auto rounded-pill font-monospace" style="font-size:0.75rem;"><?php echo $low_stock_count; ?></span>
            <?php endif; ?>
        </a>
        
        <a href="<?php echo BASE_URL; ?>/admin/categories.php" class="admin-nav-link <?php echo $current_page === 'categories.php' ? 'active' : ''; ?>">
            <i class="bi bi-tags"></i>
            <span>Categories</span>
        </a>
        
        <a href="<?php echo BASE_URL; ?>/admin/orders.php" class="admin-nav-link <?php echo $current_page === 'orders.php' ? 'active' : ''; ?>">
            <i class="bi bi-receipt"></i>
            <span>Orders</span>
        </a>
        
        <a href="<?php echo BASE_URL; ?>/admin/customers.php" class="admin-nav-link <?php echo $current_page === 'customers.php' ? 'active' : ''; ?>">
            <i class="bi bi-people"></i>
            <span>Customers</span>
        </a>
        
        <a href="<?php echo BASE_URL; ?>/admin/reports.php" class="admin-nav-link <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
            <i class="bi bi-graph-up-arrow"></i>
            <span>Sales Reports</span>
        </a>
        
        <a href="<?php echo BASE_URL; ?>/admin/logs.php" class="admin-nav-link <?php echo $current_page === 'logs.php' ? 'active' : ''; ?>">
            <i class="bi bi-journal-text"></i>
            <span>Activity Logs</span>
        </a>
        
        <hr class="mx-3 my-4 rgba-white" style="opacity: 0.1;">
        
        <a href="<?php echo BASE_URL; ?>/customer/index.php" class="admin-nav-link" target="_blank">
            <i class="bi bi-globe-americas"></i>
            <span>Store Frontend</span>
        </a>
        
        <a href="<?php echo BASE_URL; ?>/admin/logout.php" class="admin-nav-link text-danger-emphasis">
            <i class="bi bi-box-arrow-right"></i>
            <span>Sign Out</span>
        </a>
    </div>
</div>

<!-- Main content wrapping block -->
<div class="admin-workspace">
    <!-- Topbar Header -->
    <header class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary-subtle">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light d-lg-none shadow-sm rounded-circle border" id="adminSidebarToggle">
                <i class="bi bi-list"></i>
            </button>
            <h4 class="fw-bold mb-0 text-dark" style="color: var(--admin-text-main) !important;">Control Dashboard</h4>
        </div>
        
        <div class="d-flex align-items-center gap-3">
            <!-- Theme Switcher -->
            <button class="btn btn-light shadow-sm rounded-circle border" id="adminThemeToggle" title="Toggle Light/Dark Theme">
                <i class="bi bi-moon-stars" id="adminThemeToggleIcon"></i>
            </button>
            
            <!-- Quick Alerts Bell -->
            <div class="dropdown">
                <button class="btn btn-light shadow-sm rounded-circle border position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-bell"></i>
                    <?php if ($low_stock_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle p-1.5 bg-danger border border-light rounded-circle"></span>
                    <?php endif; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg p-2 rounded-3 mt-2" style="width:260px;">
                    <li class="p-2 border-bottom"><h6 class="fw-bold mb-0 small">Notifications</h6></li>
                    <?php if ($low_stock_count > 0): ?>
                        <li class="p-2">
                            <a href="<?php echo BASE_URL; ?>/admin/products.php?filter=low-stock" class="dropdown-item rounded-2 py-2 small d-flex align-items-center gap-2">
                                <span class="badge bg-danger rounded-pill">⚠️</span>
                                <div>
                                    <strong class="d-block text-danger">Low Stock Warning</strong>
                                    <span class="text-muted" style="font-size:0.75rem;"><?php echo $low_stock_count; ?> items under threshold</span>
                                </div>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="p-3 text-center text-muted small">No notifications</li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <!-- Profile dropdown -->
            <div class="dropdown">
                <button class="btn btn-light shadow-sm rounded-pill border px-3 py-1.5 d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="rounded-circle bg-success bg-opacity-25 text-success d-flex align-items-center justify-content-center" style="width:26px; height:26px; font-weight:bold; font-size:0.8rem;">A</div>
                    <span class="fw-semibold small d-none d-sm-inline"><?php echo sanitize($admin_user['name']); ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg p-2 rounded-3 mt-2">
                    <li><a class="dropdown-item rounded-2 py-2 small" href="<?php echo BASE_URL; ?>/customer/profile.php" target="_blank"><i class="bi bi-person-circle text-muted me-2"></i> My Account</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item rounded-2 py-2 small text-danger" href="<?php echo BASE_URL; ?>/admin/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Log Out</a></li>
                </ul>
            </div>
        </div>
    </header>

    <!-- Administrative Page Flash System alerts -->
    <div class="container-fluid p-0">
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
