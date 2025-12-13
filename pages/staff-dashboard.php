<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware/auth.php';

require_login();
require_role([ROLE_STAFF]);

$pageTitle = 'Staff Dashboard';

// Barangay scope for staff
$user_id = current_user_id();
$barangay_res = db_query('SELECT b.id, b.name FROM barangay b JOIN profile p ON b.id = p.barangay_id WHERE p.user_id = ?', 'i', [$user_id]);
$barangay = $barangay_res ? $barangay_res->fetch_assoc() : null;
$barangay_id = $barangay['id'] ?? null;

// Helpers to safely fetch counts
function scoped_count($sql, $types, $params, $fallback = 0) {
    $res = db_query($sql, $types, $params);
    if ($res && ($row = $res->fetch_assoc()) && isset($row['cnt'])) {
        return intval($row['cnt']);
    }
    return $fallback;
}

$pending_requests = $barangay_id ? scoped_count(
    'SELECT COUNT(*) as cnt FROM request WHERE request_status_id = 1 AND user_id IN (SELECT id FROM users WHERE id IN (SELECT user_id FROM profile WHERE barangay_id = ?))',
    'i',
    [$barangay_id]
) : 0;

$in_progress_requests = $barangay_id ? scoped_count(
    'SELECT COUNT(*) as cnt FROM request WHERE request_status_id = 2 AND user_id IN (SELECT id FROM users WHERE id IN (SELECT user_id FROM profile WHERE barangay_id = ?))',
    'i',
    [$barangay_id]
) : 0;

$completed_today = $barangay_id ? scoped_count(
    'SELECT COUNT(*) as cnt FROM request WHERE request_status_id = 4 AND DATE(updated_at) = CURDATE() AND user_id IN (SELECT id FROM users WHERE id IN (SELECT user_id FROM profile WHERE barangay_id = ?))',
    'i',
    [$barangay_id]
) : 0;

$open_complaints = $barangay_id ? scoped_count(
    'SELECT COUNT(*) as cnt FROM complaint WHERE complaint_status_id IN (1,2) AND user_id IN (SELECT id FROM users WHERE id IN (SELECT user_id FROM profile WHERE barangay_id = ?))',
    'i',
    [$barangay_id]
) : 0;

$resolved_complaints = $barangay_id ? scoped_count(
    'SELECT COUNT(*) as cnt FROM complaint WHERE complaint_status_id NOT IN (1,2) AND user_id IN (SELECT id FROM users WHERE id IN (SELECT user_id FROM profile WHERE barangay_id = ?))',
    'i',
    [$barangay_id]
) : 0;

$total_requests = $barangay_id ? scoped_count(
    'SELECT COUNT(*) as cnt FROM request WHERE user_id IN (SELECT id FROM users WHERE id IN (SELECT user_id FROM profile WHERE barangay_id = ?))',
    'i',
    [$barangay_id]
) : 0;

// Fetch recent announcements
$announcements = [];
if ($barangay_id) {
    $ann_res = db_query('SELECT id, title, created_at FROM announcement WHERE barangay_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 5', 'i', [$barangay_id]);
    if ($ann_res) {
        while ($row = $ann_res->fetch_assoc()) {
            $announcements[] = $row;
        }
    }
}

require_once __DIR__ . '/../public/header.php';
?>

<div class="container-fluid my-4 px-3 px-md-4">
    <div style="max-width: 1200px; margin-left: auto; margin-right: auto;">
        <div class="mb-4">
            <h1 class="h2 mb-1 fw-600">Staff Dashboard</h1>
            <p class="text-muted small mb-0">
                <?php echo $barangay ? htmlspecialchars($barangay['name']) : 'Barangay'; ?> • Requests today: <?php echo $completed_today; ?> • Open complaints: <?php echo $open_complaints; ?>
            </p>
            <?php if (!$barangay_id): ?>
                <div class="alert alert-warning mt-3 mb-0" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>You are not assigned to any barangay. Please contact your administrator.
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Stats -->
        <div class="mb-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100" style="border-top: 3px solid #ffc107;">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h2 class="h1 mb-0 text-warning"><?php echo $pending_requests; ?></h2>
                                <i class="fas fa-hourglass-half text-warning opacity-10" style="font-size: 2.5rem;"></i>
                            </div>
                            <p class="text-muted small mb-3 fw-600">Pending Requests</p>
                            <a href="index.php?nav=manage-requests&filter=pending" class="btn btn-sm btn-warning w-100">Process</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100" style="border-top: 3px solid #0dcaf0;">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h2 class="h1 mb-0" style="color:#0dcaf0;">
                                    <?php echo $in_progress_requests; ?>
                                </h2>
                                <i class="fas fa-tasks" style="color:#0dcaf0; font-size: 2.5rem;"></i>
                            </div>
                            <p class="text-muted small mb-3 fw-600">In Progress</p>
                            <a href="index.php?nav=manage-requests&filter=in_progress" class="btn btn-sm w-100" style="background-color:#0dcaf0; border-color:#0dcaf0; color:#0b3d91;">View</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100" style="border-top: 3px solid #198754;">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h2 class="h1 mb-0 text-success"><?php echo $completed_today; ?></h2>
                                <i class="fas fa-check-circle text-success opacity-10" style="font-size: 2.5rem;"></i>
                            </div>
                            <p class="text-muted small mb-3 fw-600">Completed Today</p>
                            <a href="index.php?nav=manage-requests&filter=completed" class="btn btn-sm btn-success w-100">Review</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Complaints & Totals -->
        <div class="mb-5">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100" style="border-top: 3px solid #fd7e14;">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h2 class="h1 mb-0" style="color:#fd7e14;"><?php echo $open_complaints; ?></h2>
                                <i class="fas fa-exclamation-triangle" style="color:#fd7e14; font-size:2.5rem;"></i>
                            </div>
                            <p class="text-muted small mb-3 fw-600">Complaints Open</p>
                            <a href="index.php?nav=manage-complaints&filter=open" class="btn btn-sm w-100" style="background-color:#fd7e14; border-color:#fd7e14; color:white;">Manage</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body p-3">
                            <small class="text-muted d-block mb-2 fw-600">Resolved Complaints</small>
                            <h4 class="mb-0"><?php echo $resolved_complaints; ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body p-3">
                            <small class="text-muted d-block mb-2 fw-600">Total Requests</small>
                            <h4 class="mb-0"><?php echo $total_requests; ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Announcements -->
        <div class="mb-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-bullhorn text-success me-2"></i>
                            <h6 class="mb-0 fw-600">Recent Announcements</h6>
                        </div>
                        <a href="index.php?nav=manage-announcements" class="btn btn-sm btn-outline-success">View</a>
                    </div>
                    <?php if (empty($announcements)): ?>
                        <p class="text-muted mb-0">No announcements yet. <a href="index.php?nav=manage-announcements" class="text-success fw-600">Create one now</a></p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($announcements as $a): ?>
                                <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-600"><?php echo htmlspecialchars($a['title']); ?></div>
                                        <small class="text-muted"><?php echo date('M d, Y', strtotime($a['created_at'])); ?></small>
                                    </div>
                                    <a href="index.php?nav=manage-announcements" class="btn btn-sm btn-outline-primary">Edit</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tools -->
        <div>
            <h6 class="text-muted fw-600 mb-3">Tools</h6>
            <div class="row g-2">
                <div class="col-md-4">
                    <a href="index.php?nav=manage-requests" class="card border-0 text-decoration-none text-dark h-100 hover-card-link">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-file-alt text-warning me-3"></i>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">Requests</h6>
                                    <small class="text-muted">View and process</small>
                                </div>
                                <i class="fas fa-chevron-right text-muted small"></i>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="index.php?nav=manage-complaints" class="card border-0 text-decoration-none text-dark h-100 hover-card-link">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-comments text-info me-3"></i>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">Complaints</h6>
                                    <small class="text-muted">Track issues</small>
                                </div>
                                <i class="fas fa-chevron-right text-muted small"></i>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="index.php?nav=manage-users" class="card border-0 text-decoration-none text-dark h-100 hover-card-link">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-users text-primary me-3"></i>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">Users</h6>
                                    <small class="text-muted">View accounts</small>
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
