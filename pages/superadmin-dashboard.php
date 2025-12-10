<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware/auth.php';

require_login();
require_role([ROLE_SUPERADMIN]);

$pageTitle = 'Superadmin Dashboard';

require_once __DIR__ . '/../public/header.php';
?>

<div class="container my-5">
    <h1 class="mb-4"><i class="fas fa-crown"></i> Superadmin Dashboard</h1>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h6 class="card-title">Total Requests</h6>
                    <h3><?php echo db_query('SELECT COUNT(*) as cnt FROM request')->fetch_assoc()['cnt'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h6 class="card-title">Completed</h6>
                    <h3><?php echo db_query('SELECT COUNT(*) as cnt FROM request WHERE request_status_id = 4')->fetch_assoc()['cnt'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h6 class="card-title">Activity Logs</h6>
                    <h3><?php echo db_query('SELECT COUNT(*) as cnt FROM activity_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)')->fetch_assoc()['cnt'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h6 class="card-title">Active Users</h6>
                    <h3><?php echo db_query('SELECT COUNT(*) as cnt FROM users WHERE deleted_at IS NULL')->fetch_assoc()['cnt'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">System Overview</h5>
        </div>
        <div class="card-body">
            <a href="index.php?nav=activity-logs" class="btn btn-primary me-2">
                <i class="fas fa-history"></i> View Activity Logs
            </a>
            <a href="index.php?nav=manage-requests" class="btn btn-secondary">
                <i class="fas fa-tasks"></i> Manage Requests
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
