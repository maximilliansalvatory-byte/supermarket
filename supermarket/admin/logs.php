<?php
/**
 * Dual Supermarket Management System - Administrative Security Audit Logs View
 */
$page_title = "Administrative Action Logs";
require_once __DIR__ . '/../includes/admin_header.php';

// Pagination settings
$limit = 15; // logs per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// Capture filters
$search = isset($_GET['search']) ? trim(sanitize($_GET['search'])) : '';
$action_filter = isset($_GET['action_filter']) ? sanitize($_GET['action_filter']) : 'All';

// Assemble query segments
$sql_where = " WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql_where .= " AND (l.details LIKE ? OR l.action LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $search_val = "%$search%";
    $params[] = $search_val;
    $params[] = $search_val;
    $params[] = $search_val;
    $params[] = $search_val;
}

if ($action_filter !== 'All') {
    $sql_where .= " AND l.action = ?";
    $params[] = $action_filter;
}

// Calculate totals & bounds
try {
    $stmt_tot = $pdo->prepare("
        SELECT COUNT(*) 
        FROM admin_logs l 
        JOIN users u ON l.admin_id = u.id 
        $sql_where
    ");
    $stmt_tot->execute($params);
    $total_rows = (int)$stmt_tot->fetchColumn();
} catch (PDOException $e) {
    $total_rows = 0;
}

$total_pages = ceil($total_rows / $limit);
if ($total_pages < 1) $total_pages = 1;
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $limit;

// Fetch logs
$logs_list = [];
try {
    $stmt_list = $pdo->prepare("
        SELECT l.*, u.name as admin_name, u.email as admin_email 
        FROM admin_logs l 
        JOIN users u ON l.admin_id = u.id 
        $sql_where 
        ORDER BY l.id DESC 
        LIMIT $limit OFFSET $offset
    ");
    $stmt_list->execute($params);
    $logs_list = $stmt_list->fetchAll();
} catch (PDOException $e) {
    $_SESSION['flash_error'] = "Failed to load audit logs: " . $e->getMessage();
}

// Retrieve list of unique actions for category filtering
$unique_actions = [];
try {
    $stmt_acts = $pdo->query("SELECT DISTINCT action FROM admin_logs ORDER BY action ASC");
    $unique_actions = $stmt_acts->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Graceful fallback
}
?>

<!-- Title Banner Area -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0 text-dark" style="color: var(--admin-text-main) !important;">Security Audit Trail Logs</h3>
    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2 fw-semibold border">Total Logs: <?php echo $total_rows; ?></span>
</div>

<!-- Filters Panel -->
<div class="card border-0 shadow-sm rounded-4 admin-glass-card p-4 mb-4">
    <form action="logs.php" method="GET" class="row g-3">
        <div class="col-lg-5 col-md-6">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 border-secondary-subtle"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-start-0 border-secondary-subtle py-2.5" placeholder="Search operational text details, admin names..." value="<?php echo sanitize($search); ?>">
            </div>
        </div>
        
        <div class="col-lg-4 col-md-4">
            <select name="action_filter" class="form-select py-2.5 border-secondary-subtle">
                <option value="All" <?php echo $action_filter === 'All' ? 'selected' : ''; ?>>All Action Categories</option>
                <?php foreach ($unique_actions as $act): ?>
                    <option value="<?php echo sanitize($act); ?>" <?php echo $action_filter === $act ? 'selected' : ''; ?>><?php echo sanitize($act); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-lg-3 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-success-gradient w-100 py-2.5 rounded-pill fw-bold shadow-sm"><i class="bi bi-filter"></i> Search</button>
            <a href="logs.php" class="btn btn-outline-secondary py-2.5 rounded-circle border" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
        </div>
    </form>
</div>

<!-- Chronological Grid Table -->
<div class="card border-0 shadow-sm rounded-4 admin-glass-card p-4 mb-4">
    <?php if (empty($logs_list)): ?>
        <div class="text-center py-5 text-muted">
            <span class="fs-1 d-block mb-3">🔍</span>
            <h5 class="fw-semibold">No Audit Records Found</h5>
            <p class="small mb-0">No historical records match your current filter parameters.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle admin-table mb-0">
                <thead>
                    <tr class="text-muted">
                        <th scope="col" style="width: 180px;">Timestamp</th>
                        <th scope="col" style="width: 180px;">Action Category</th>
                        <th scope="col">Event Logs Detail Summary</th>
                        <th scope="col" style="width: 220px;">Operator details</th>
                        <th scope="col" style="width: 140px;">Remote IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs_list as $log): 
                        // Color badges for typical categories
                        $act_badge = 'bg-secondary';
                        if (str_contains($log['action'], 'Create') || str_contains($log['action'], 'Added')) $act_badge = 'bg-success';
                        if (str_contains($log['action'], 'Mod') || str_contains($log['action'], 'Update')) $act_badge = 'bg-primary';
                        if (str_contains($log['action'], 'Delete') || str_contains($log['action'], 'Banned')) $act_badge = 'bg-danger';
                        if (str_contains($log['action'], 'Login')) $act_badge = 'bg-info text-dark';
                    ?>
                        <tr>
                            <td>
                                <span class="small text-muted font-monospace" style="font-size:0.8rem;"><?php echo date('M d, Y h:i:s A', strtotime($log['created_at'])); ?></span>
                            </td>
                            <td>
                                <span class="badge <?php echo $act_badge; ?> rounded-pill px-2.5 py-1 small"><?php echo sanitize($log['action']); ?></span>
                            </td>
                            <td>
                                <span class="small text-dark fw-medium" style="color: var(--admin-text-main) !important;"><?php echo sanitize($log['details']); ?></span>
                            </td>
                            <td>
                                <strong class="text-dark d-block" style="color: var(--admin-text-main) !important;"><?php echo sanitize($log['admin_name']); ?></strong>
                                <code class="small text-muted" style="font-size:0.7rem;"><?php echo sanitize($log['admin_email']); ?></code>
                            </td>
                            <td>
                                <span class="badge bg-light border text-secondary font-monospace" style="font-size:0.75rem;"><?php echo sanitize($log['ip_address']); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Custom Pagination Widget Controls -->
<?php if ($total_pages > 1): ?>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 p-3 bg-white border rounded-4 shadow-sm" style="background-color: var(--admin-glass-bg) !important; border: 1px solid var(--admin-border-color) !important;">
        <span class="small text-muted">Showing logs page <strong><?php echo $page; ?></strong> of <strong><?php echo $total_pages; ?></strong> (Records: <?php echo $total_rows; ?>)</span>
        
        <nav aria-label="Page navigation">
            <ul class="pagination pagination-sm mb-0 align-items-center gap-1">
                <!-- First Page -->
                <li class="page-item <?php echo $page === 1 ? 'disabled' : ''; ?>">
                    <a class="page-link rounded-pill px-3 py-1.5 border" href="logs.php?page=1&search=<?php echo urlencode($search); ?>&action_filter=<?php echo urlencode($action_filter); ?>" aria-label="First">
                        <i class="bi bi-chevron-double-left"></i>
                    </a>
                </li>
                
                <!-- Previous Page -->
                <li class="page-item <?php echo $page === 1 ? 'disabled' : ''; ?>">
                    <a class="page-link rounded-pill px-3 py-1.5 border" href="logs.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&action_filter=<?php echo urlencode($action_filter); ?>" aria-label="Previous">
                        <i class="bi bi-chevron-left"></i> Prev
                    </a>
                </li>
                
                <!-- Current indicator -->
                <li class="px-2 fw-bold small text-success">
                    <?php echo $page; ?> / <?php echo $total_pages; ?>
                </li>

                <!-- Next Page -->
                <li class="page-item <?php echo $page === $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link rounded-pill px-3 py-1.5 border" href="logs.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&action_filter=<?php echo urlencode($action_filter); ?>" aria-label="Next">
                        Next <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
                
                <!-- Last Page -->
                <li class="page-item <?php echo $page === $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link rounded-pill px-3 py-1.5 border" href="logs.php?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&action_filter=<?php echo urlencode($action_filter); ?>" aria-label="Last">
                        <i class="bi bi-chevron-double-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
