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
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100" style="border-top:3px solid #0dcaf0;">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h2 class="h1 mb-0" style="color:#0dcaf0;">
                                    <?php echo $activity_24h; ?>
                                </h2>
                                <i class="fas fa-history opacity-25" style="color:#0dcaf0; font-size:2.5rem;"></i>
                            </div>
                            <p class="text-muted small mb-3 fw-600">Activity (24h)</p>
                            <a href="index.php?nav=activity-logs" class="btn btn-sm w-100" style="background-color:#0dcaf0; border-color:#0dcaf0; color:#0b3d91;">
                                <i class="fas fa-chart-line me-2"></i>View Logs
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100" style="border-top:3px solid #6f42c1;">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h2 class="h1 mb-0" style="color:#6f42c1;">
                                    <?php echo $admin_count; ?>
                                </h2>
                                <i class="fas fa-user-shield opacity-25" style="color:#6f42c1; font-size:2.5rem;"></i>
                            </div>
                            <p class="text-muted small mb-3 fw-600">Barangay Admins</p>
                            <a href="index.php?nav=admin-management" class="btn btn-sm w-100" style="background-color:#6f42c1; border-color:#6f42c1; color:white;">
                                <i class="fas fa-user-plus me-2"></i>Manage
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Overview -->
        <div class="row g-3 mb-5">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4 text-center">
                        <i class="fas fa-map-marked-alt mb-3" style="font-size: 2rem; color: #0d6efd;"></i>
                        <h3 class="mb-1 fw-bold"><?php echo $barangay_count; ?></h3>
                        <small class="text-muted fw-600">Total Barangays</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4 text-center">
                        <i class="fas fa-user-shield mb-3" style="font-size: 2rem; color: #6f42c1;"></i>
                        <h3 class="mb-1 fw-bold"><?php echo $admin_count; ?></h3>
                        <small class="text-muted fw-600">Barangay Admins</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4 text-center">
                        <i class="fas fa-users mb-3" style="font-size: 2rem; color: #fd7e14;"></i>
                        <h3 class="mb-1 fw-bold"><?php echo $active_users; ?></h3>
                        <small class="text-muted fw-600">Registered Users</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Management Tools -->
        <div>
            <h5 class="mb-4 fw-bold" style="color: #2c3e50;">
                <i class="fas fa-tools me-2" style="color: #0d6efd;"></i>Management Tools
            </h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <a href="index.php?nav=admin-management" class="text-decoration-none">
                        <div class="card border-0 shadow-sm h-100 hover-card-link">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                        <i class="fas fa-user-shield text-white" style="font-size: 1.3rem;"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 fw-bold">Admin Management</h6>
                                        <small class="text-muted">Create & manage admins</small>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-primary"><?php echo $admin_count; ?> Active</span>
                                    <i class="fas fa-arrow-right text-muted"></i>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="index.php?nav=barangay-overview" class="text-decoration-none">
                        <div class="card border-0 shadow-sm h-100 hover-card-link">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                        <i class="fas fa-map-marked-alt text-white" style="font-size: 1.3rem;"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 fw-bold">Barangay Overview</h6>
                                        <small class="text-muted">View all barangays</small>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-danger"><?php echo $barangay_count; ?> Locations</span>
                                    <i class="fas fa-arrow-right text-muted"></i>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="index.php?nav=activity-logs" class="text-decoration-none">
                        <div class="card border-0 shadow-sm h-100 hover-card-link">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                        <i class="fas fa-history text-white" style="font-size: 1.3rem;"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 fw-bold">Activity Logs</h6>
                                        <small class="text-muted">System audit trail</small>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-info text-dark"><?php echo $activity_24h; ?> Today</span>
                                    <i class="fas fa-arrow-right text-muted"></i>
                                </div>
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
    .fw-bold { font-weight: 700; }
    .hover-card-link { 
        transition: all 0.3s ease; 
        border: 1px solid transparent;
    }
    .hover-card-link:hover { 
        transform: translateY(-5px);
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.15) !important;
        border-color: #dee2e6;
    }
    .opacity-25 {
        opacity: 0.25;
    }
</style>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
