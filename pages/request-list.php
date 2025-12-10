<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware/auth.php';

require_login();
require_role([ROLE_USER]);

$pageTitle = 'My Requests';
$user_id = current_user_id();

// Get search/filter params
$search = trim($_GET['search'] ?? '');
$status_filter = intval($_GET['status'] ?? 0);

// Build query
$where_clauses = ['r.user_id = ?'];
$bind_params = [$user_id];
$bind_types = 'i';

if (!empty($search)) {
    $where_clauses[] = '(dt.name LIKE ? OR rs.name LIKE ?)';
    $search_term = "%$search%";
    $bind_params[] = $search_term;
    $bind_params[] = $search_term;
    $bind_types .= 'ss';
}

if ($status_filter > 0) {
    $where_clauses[] = 'r.request_status_id = ?';
    $bind_params[] = $status_filter;
    $bind_types .= 'i';
}

$where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

$sql = "
    SELECT r.id, r.document_type_id, r.request_status_id, r.created_at, r.updated_at,
           dt.name as doc_type, rs.name as status_name
    FROM request r
    LEFT JOIN document_type dt ON r.document_type_id = dt.id
    LEFT JOIN request_status rs ON r.request_status_id = rs.id
    $where_sql
    ORDER BY r.created_at DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) die('DB prepare failed');
$stmt->bind_param($bind_types, ...$bind_params);
$stmt->execute();
$res = $stmt->get_result();
$requests = [];
while ($row = $res->fetch_assoc()) {
    $requests[] = $row;
}
$stmt->close();

require_once __DIR__ . '/../public/header.php';
?>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-list"></i> My Document Requests</h2>
        <a href="index.php?nav=create-request" class="btn btn-primary">
            <i class="fas fa-plus"></i> New Request
        </a>
    </div>

    <!-- Search & Filter -->
    <div class="card mb-4 bg-light">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <input type="hidden" name="nav" value="request-list">
                <div class="col-md-6">
                    <input type="text" name="search" class="form-control" placeholder="Search document type or status..." value="<?php echo e($search); ?>">
                </div>
                <div class="col-md-4">
                    <select name="status" class="form-select">
                        <option value="0">All Statuses</option>
                        <option value="1" <?php echo $status_filter === 1 ? 'selected' : ''; ?>>Pending</option>
                        <option value="2" <?php echo $status_filter === 2 ? 'selected' : ''; ?>>Approved</option>
                        <option value="3" <?php echo $status_filter === 3 ? 'selected' : ''; ?>>Rejected</option>
                        <option value="4" <?php echo $status_filter === 4 ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </form>
            <?php if (!empty($search) || $status_filter > 0): ?>
                <div class="mt-2">
                    <a href="index.php?nav=request-list" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Requests Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Request ID</th>
                        <th>Document Type</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($requests) > 0): ?>
                        <?php foreach ($requests as $req): ?>
                            <tr>
                                <td><strong>#<?php echo str_pad($req['id'], 5, '0', STR_PAD_LEFT); ?></strong></td>
                                <td><?php echo e($req['doc_type'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php
                                    $status_id = intval($req['request_status_id']);
                                    $badge = $status_id === 1 ? 'warning' : ($status_id === 2 ? 'info' : ($status_id === 3 ? 'danger' : 'success'));
                                    ?>
                                    <span class="badge bg-<?php echo $badge; ?>">
                                        <?php echo e($req['status_name']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($req['created_at'])); ?></td>
                                <td>
                                    <a href="index.php?nav=request-ticket&id=<?php echo intval($req['id']); ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No requests found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
