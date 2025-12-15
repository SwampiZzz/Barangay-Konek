<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware/auth.php';

require_login();
require_role([ROLE_SUPERADMIN]);

$pageTitle = 'Activity Logs';

// Filters and pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = isset($_GET['per_page']) ? min(100, max(10, intval($_GET['per_page']))) : 25;
$offset = ($page - 1) * $perPage;

$filterAction = isset($_GET['action']) ? trim($_GET['action']) : '';
$filterUser = isset($_GET['user']) ? trim($_GET['user']) : '';
$dateFrom = isset($_GET['from']) ? trim($_GET['from']) : '';
$dateTo = isset($_GET['to']) ? trim($_GET['to']) : '';

// Build dynamic where clause safely
$where = [];
$types = '';
$params = [];

if ($filterAction !== '') {
    $where[] = 'al.action LIKE ?';
    $types .= 's';
    $params[] = '%' . $filterAction . '%';
}
if ($filterUser !== '') {
    $where[] = 'u.username LIKE ?';
    $types .= 's';
    $params[] = '%' . $filterUser . '%';
}
// Validate dates (YYYY-MM-DD) and include time range
if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $where[] = 'al.created_at >= ?';
    $types .= 's';
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $where[] = 'al.created_at <= ?';
    $types .= 's';
    $params[] = $dateTo . ' 23:59:59';
}

$whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count total for pagination
$countSql = 'SELECT COUNT(*) as cnt FROM activity_log al LEFT JOIN users u ON al.user_id = u.id ' . $whereSql;
$countRes = db_query($countSql, $types, $params);
$total = 0;
if ($countRes && ($row = $countRes->fetch_assoc()) && isset($row['cnt'])) {
    $total = intval($row['cnt']);
}
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

// Fetch logs with filters
$sql = 'SELECT al.*, u.username, u.deleted_at as user_deleted_at
        FROM activity_log al
        LEFT JOIN users u ON al.user_id = u.id
        ' . $whereSql . '
        ORDER BY al.created_at DESC
        LIMIT ? OFFSET ?';

$queryTypes = $types . 'ii';
$queryParams = array_merge($params, [ $perPage, $offset ]);

$res = db_query($sql, $queryTypes, $queryParams);
$logs = [];
$queryError = null;
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $logs[] = $row;
    }
} else {
    $queryError = 'Failed to load activity logs. Please try again.';
}

require_once __DIR__ . '/../public/header.php';
?>

<div class="container my-5">
    <div class="d-flex align-items-center mb-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center me-2" style="width:36px;height:36px;background:linear-gradient(135deg,#4facfe 0%,#00f2fe 100%);">
            <i class="fas fa-history text-white" style="font-size:1rem;"></i>
        </div>
        <div>
            <h2 class="mb-0">Activity Logs</h2>
            <small class="text-muted">Track system events and actions</small>
        </div>
    </div>

    <div class="card mb-3 shadow-sm">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Action</label>
                    <input type="text" name="action" value="<?php echo e($filterAction); ?>" class="form-control" placeholder="e.g. login, create">
                </div>
                <div class="col-md-3">
                    <label class="form-label">User</label>
                    <input type="text" name="user" value="<?php echo e($filterUser); ?>" class="form-control" placeholder="username">
                </div>
                <div class="col-md-3">
                    <label class="form-label">From</label>
                    <input type="date" name="from" value="<?php echo e($dateFrom); ?>" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To</label>
                    <input type="date" name="to" value="<?php echo e($dateTo); ?>" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Per Page</label>
                    <select name="per_page" class="form-select">
                        <?php foreach ([25,50,75,100] as $opt): ?>
                            <option value="<?php echo $opt; ?>" <?php echo ($perPage===$opt?'selected':''); ?>><?php echo $opt; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-9 d-flex align-items-end justify-content-end">
                    <button type="submit" class="btn btn-primary me-2"><i class="fas fa-search me-1"></i> Filter</button>
                    <a href="activity-logs.php" class="btn btn-outline-secondary"><i class="fas fa-redo me-1"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($queryError): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo e($queryError); ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 20%;">Date/Time</th>
                        <th style="width: 25%;">User</th>
                        <th style="width: 35%;">Action</th>
                        <th style="width: 20%;">Reference</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">No activity found for the selected filters.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="far fa-clock text-muted me-2"></i>
                                        <div>
                                            <div class="fw-600"><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></div>
                                            <small class="text-muted"><?php echo date('l', strtotime($log['created_at'])); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    if (!empty($log['user_deleted_at'])) {
                                        echo '<span class="text-muted fst-italic"><i class="fas fa-user-slash me-1"></i>[Deleted User]</span>';
                                    } else {
                                        $u = $log['username'] ?? 'System';
                                        echo '<span class="log-user"><i class="fas fa-user-circle me-1" style="color:#9ca3af;"></i>' . e($u) . '</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $action = trim($log['action'] ?? '');
                                    $badgeClass = 'bg-secondary';
                                    if (stripos($action, 'login') !== false) $badgeClass = 'bg-success';
                                    else if (stripos($action, 'create') !== false || stripos($action, 'add') !== false) $badgeClass = 'bg-primary';
                                    else if (stripos($action, 'update') !== false || stripos($action, 'edit') !== false) $badgeClass = 'bg-info text-dark';
                                    else if (stripos($action, 'delete') !== false || stripos($action, 'remove') !== false) $badgeClass = 'bg-danger';
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?> me-2">Action</span>
                                    <span class="text-dark"><?php echo e($action); ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($log['reference_table'])): ?>
                                        <?php echo e($log['reference_table']); ?>
                                        <?php if (!empty($log['reference_id'])): ?>
                                            <span class="log-ref-id">#<?php echo intval($log['reference_id']); ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-muted">Showing page <?php echo $page; ?> of <?php echo $totalPages; ?> • Total: <?php echo $total; ?></small>
            <nav>
                <ul class="pagination mb-0">
                    <?php 
                    // Helper to build page URL preserving filters
                    function pageUrl($p) {
                        $qs = $_GET;
                        $qs['page'] = $p;
                        return 'activity-logs.php?' . http_build_query($qs);
                    }
                    ?>
                    <li class="page-item <?php echo ($page<=1?'disabled':''); ?>">
                        <a class="page-link" href="<?php echo ($page<=1?'#':pageUrl($page-1)); ?>">Prev</a>
                    </li>
                    <li class="page-item <?php echo ($page>=$totalPages?'disabled':''); ?>">
                        <a class="page-link" href="<?php echo ($page>=$totalPages?'#':pageUrl($page+1)); ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>

<style>
    .fw-600 { font-weight: 600; }
    .log-user { font-weight: 500; }
    .log-ref-id { font-weight: 600; color: #6c757d; }
    .table thead th { font-weight: 600; letter-spacing: .2px; }
    .table tbody tr:hover { background-color: #f8fafc; }
    .card .form-label { font-weight: 600; }
    .card .btn { padding: .375rem .75rem; }
    @media (max-width: 768px) {
        .table thead { display: none; }
        .table tbody tr { display: block; padding: .75rem; border-bottom: 1px solid #eee; }
        .table tbody td { display: flex; justify-content: space-between; }
    }
</style>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
