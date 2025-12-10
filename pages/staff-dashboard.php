<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware/auth.php';

require_login();
require_role([ROLE_STAFF]);

$pageTitle = 'Staff Dashboard';

require_once __DIR__ . '/../public/header.php';
?>

<div class="container my-5">
    <h1 class="mb-4"><i class="fas fa-briefcase"></i> Staff Dashboard</h1>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h6 class="card-title">Pending Requests</h6>
                    <h3><?php echo db_query('SELECT COUNT(*) as cnt FROM request WHERE request_status_id = 1')->fetch_assoc()['cnt'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h6 class="card-title">In Progress</h6>
                    <h3><?php echo db_query('SELECT COUNT(*) as cnt FROM request WHERE request_status_id = 2')->fetch_assoc()['cnt'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h6 class="card-title">Completed Today</h6>
                    <h3><?php echo db_query('SELECT COUNT(*) as cnt FROM request WHERE request_status_id = 4 AND DATE(updated_at) = CURDATE()')->fetch_assoc()['cnt'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Manage Requests</h5>
        </div>
        <div class="card-body">
            <a href="index.php?nav=manage-requests" class="btn btn-primary">
                <i class="fas fa-tasks"></i> View All Requests
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
