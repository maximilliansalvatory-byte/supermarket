<?php
require_once __DIR__ . '/../includes/config.php'; // or admin_header if needed

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;

$query = "SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id";
$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(p.name LIKE ? OR p.slug LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($category > 0) {
    $where[] = "p.category_id = ?";
    $params[] = $category;
}

if ($where) $query .= " WHERE " . implode(" AND ", $where);
$query .= " ORDER BY p.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products_list = $stmt->fetchAll();
?>

<div class="table-responsive">
    <table class="table align-middle admin-table mb-0">
        <thead>
            <tr>
                <th>Visual</th>
                <th>Name</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Featured</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products_list)): ?>
                <tr><td colspan="8" class="text-center py-5">No products found.</td></tr>
            <?php else: foreach ($products_list as $prod): ?>
                <!-- Paste your original <tr> row code here -->
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>