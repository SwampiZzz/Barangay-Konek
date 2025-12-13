<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware/auth.php';

require_login();
require_role([ROLE_SUPERADMIN]);

$pageTitle = 'Superadmin Dashboard';

// Helper to safely count
function sa_count($sql, $types = '', $params = [], $fallback = 0) {
    $res = db_query($sql, $types, $params);
    if ($res && ($row = $res->fetch_assoc()) && isset($row['cnt'])) return intval($row['cnt']);
    return $fallback;
}

$total_requests = sa_count('SELECT COUNT(*) as cnt FROM request');
$completed_requests = sa_count('SELECT COUNT(*) as cnt FROM request WHERE request_status_id = 4');
$activity_24h = sa_count('SELECT COUNT(*) as cnt FROM activity_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)');
$active_users = sa_count('SELECT COUNT(*) as cnt FROM users WHERE deleted_at IS NULL');
$barangay_count = sa_count('SELECT COUNT(*) as cnt FROM barangay');
$admin_count = sa_count('SELECT COUNT(*) as cnt FROM users WHERE usertype_id = 2 AND deleted_at IS NULL');

require_once __DIR__ . '/../public/header.php';
?>

<div class="container-fluid my-4 px-3 px-md-4">
    <div style="max-width: 1200px; margin-left: auto; margin-right: auto;">
        <div class="mb-4">
            <h1 class="h2 mb-1 fw-600">Superadmin Dashboard</h1>
            <p class="text-muted small mb-0">System-wide overview • <?php echo $barangay_count; ?> Barangays • <?php echo $admin_count; ?> Admins</p>
        </div>

        <!-- Quick Stats -->
        <div class="mb-5">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100" style="border-top:3px solid #0d6efd;">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h2 class="h1 mb-0 text-primary"><?php echo $total_requests; ?></h2>
                                <i class="fas fa-clipboard-list text-primary opacity-10" style="font-size:2.5rem;"></i>
                            </div>
                            <p class="text-muted small mb-3 fw-600">Total Requests</p>
                            <a href="index.php?nav=manage-requests" class="btn btn-sm btn-primary w-100">Manage</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100" style="border-top:3px solid #198754;">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h2 class="h1 mb-0 text-success"><?php echo $completed_requests; ?></h2>
                                <i class="fas fa-check-circle text-success opacity-10" style="font-size:2.5rem;"></i>
                            </div>
                            <p class="text-muted small mb-3 fw-600">Completed</p>
                            <a href="index.php?nav=manage-requests&filter=completed" class="btn btn-sm btn-success w-100">Review</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100" style="border-top:3px solid #0dcaf0;">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h2 class="h1 mb-0" style="color:#0dcaf0;">
                                    <?php echo $activity_24h; ?>
                                </h2>
                                <i class="fas fa-history" style="color:#0dcaf0; font-size:2.5rem;"></i>
                            </div>
                            <p class="text-muted small mb-3 fw-600">Activity (24h)</p>
                            <a href="index.php?nav=activity-logs" class="btn btn-sm" style="background-color:#0dcaf0; border-color:#0dcaf0; color:#0b3d91;">View Logs</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100" style="border-top:3px solid #fd7e14;">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h2 class="h1 mb-0" style="color:#fd7e14;">
                                    <?php echo $active_users; ?>
                                </h2>
                                <i class="fas fa-users" style="color:#fd7e14; font-size:2.5rem;"></i>
                            </div>
                            <p class="text-muted small mb-3 fw-600">Active Users</p>
                            <a href="index.php?nav=manage-users" class="btn btn-sm" style="background-color:#fd7e14; border-color:#fd7e14; color:white;">View Users</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Overview -->
        <div class="row g-3 mb-5">
            <div class="col-md-4">
                <div class="card border-0 bg-light h-100">
                    <div class="card-body p-3">
                        <small class="text-muted d-block mb-2 fw-600">Barangays</small>
                        <h4 class="mb-0"><?php echo $barangay_count; ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 bg-light h-100">
                    <div class="card-body p-3">
                        <small class="text-muted d-block mb-2 fw-600">Admins</small>
                        <h4 class="mb-0"><?php echo $admin_count; ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 bg-light h-100">
                    <div class="card-body p-3">
                        <small class="text-muted d-block mb-2 fw-600">Requests (Completed %)</small>
                        <h4 class="mb-0"><?php echo ($total_requests > 0) ? round(($completed_requests / $total_requests) * 100) . '%' : '0%'; ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tools -->
        <div>
            <h6 class="text-muted fw-600 mb-3">Tools</h6>
            <div class="row g-2">
                <div class="col-md-6">
                    <a href="index.php?nav=admin-management" class="card border-0 text-decoration-none text-dark h-100 hover-card-link">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-user-shield text-primary me-3"></i>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">Admin Management</h6>
                                    <small class="text-muted">Manage admins</small>
                                </div>
                                <i class="fas fa-chevron-right text-muted small"></i>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6">
                    <a href="index.php?nav=barangay-overview" class="card border-0 text-decoration-none text-dark h-100 hover-card-link">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-map-marker-alt text-danger me-3"></i>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">Barangay Overview</h6>
                                    <small class="text-muted">View barangays</small>
                                </div>
                                <i class="fas fa-chevron-right text-muted small"></i>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6">
                    <a href="index.php?nav=activity-logs" class="card border-0 text-decoration-none text-dark h-100 hover-card-link">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-history text-info me-3"></i>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">Activity Logs</h6>
                                    <small class="text-muted">System audit</small>
                                </div>
                                <i class="fas fa-chevron-right text-muted small"></i>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6">
                    <a href="index.php?nav=manage-requests" class="card border-0 text-decoration-none text-dark h-100 hover-card-link">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-tasks text-warning me-3"></i>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">Requests</h6>
                                    <small class="text-muted">All requests</small>
                                </div>
                                <i class="fas fa-chevron-right text-muted small"></i>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .fw-600 { font-weight: 600; }
    .hover-card-link { transition: all 0.2s ease; border: 1px solid #e9ecef; }
    .hover-card-link:hover { background-color: #f8f9fa !important; border-color: #dee2e6; }
</style>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
