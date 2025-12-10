<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware/auth.php';

require_login();
require_role([ROLE_USER]);

$pageTitle = 'Dashboard';
$user_id = current_user_id();
// Get user profile
$res = db_query('SELECT * FROM profile WHERE user_id = ?', 'i', [$user_id]);
$profile = $res ? $res->fetch_assoc() : [];

// Get recent requests
$res = db_query('SELECT r.id, r.request_status_id, rs.name as status_name, dt.name as doc_type FROM request r LEFT JOIN request_status rs ON r.request_status_id = rs.id LEFT JOIN document_type dt ON r.document_type_id = dt.id WHERE r.user_id = ? ORDER BY r.created_at DESC LIMIT 5', 'i', [$user_id]);
$recent_requests = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $recent_requests[] = $row;
    }
}

require_once __DIR__ . '/../public/header.php';
?>

<div class="container my-5">
    <h1 class="mb-4"><i class="fas fa-home"></i> Welcome, <?php echo e($profile['first_name'] ?? $_SESSION['username']); ?>!</h1>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h6 class="card-title">Total Requests</h6>
                    <h3><?php echo db_query('SELECT COUNT(*) as cnt FROM request WHERE user_id = ?', 'i', [$user_id])->fetch_assoc()['cnt'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h6 class="card-title">Pending</h6>
                    <h3><?php echo db_query('SELECT COUNT(*) as cnt FROM request WHERE user_id = ? AND request_status_id = 1', 'i', [$user_id])->fetch_assoc()['cnt'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h6 class="card-title">Approved</h6>
                    <h3><?php echo db_query('SELECT COUNT(*) as cnt FROM request WHERE user_id = ? AND request_status_id = 2', 'i', [$user_id])->fetch_assoc()['cnt'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h6 class="card-title">Completed</h6>
                    <h3><?php echo db_query('SELECT COUNT(*) as cnt FROM request WHERE user_id = ? AND request_status_id = 4', 'i', [$user_id])->fetch_assoc()['cnt'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Recent Requests</h5>
                </div>
                <div class="card-body">
                    <?php if (count($recent_requests) > 0): ?>
                        <div class="list-group">
                            <?php foreach ($recent_requests as $req): ?>
                                <a href="index.php?nav=request-ticket&id=<?php echo $req['id']; ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex justify-content-between">
                                        <h6 class="mb-1"><?php echo e($req['doc_type']); ?></h6>
                                        <span class="badge bg-<?php $status_id = intval($req['request_status_id']); echo $status_id === 1 ? 'warning' : ($status_id === 2 ? 'info' : ($status_id === 3 ? 'danger' : 'success')); ?>">
                                            <?php echo e($req['status_name']); ?>
                                        </span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No requests yet. <a href="index.php?nav=create-request">Create one now!</a></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <a href="index.php?nav=create-request" class="btn btn-primary w-100 mb-2">
                        <i class="fas fa-plus"></i> New Request
                    </a>
                    <a href="index.php?nav=request-list" class="btn btn-secondary w-100">
                        <i class="fas fa-list"></i> View All Requests
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
