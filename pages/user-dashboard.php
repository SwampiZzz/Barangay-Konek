<?php
require_once __DIR__ . '/../config.php';

require_login();
require_role([ROLE_USER]);

$pageTitle = 'Dashboard';
$user_id = current_user_id();

// Get user profile
$res = db_query('SELECT * FROM profile WHERE user_id = ?', 'i', [$user_id]);
$profile = $res ? $res->fetch_assoc() : [];

// Get verification status
$is_verified = is_user_verified($user_id);
$verification_status = '';
if (!$is_verified) {
    $res = db_query('SELECT vs.name FROM user_verification uv JOIN verification_status vs ON uv.verification_status_id = vs.id WHERE uv.user_id = ? LIMIT 1', 'i', [$user_id]);
    if ($res) {
        $v = $res->fetch_assoc();
        $verification_status = $v['name'] ?? 'pending';
    }
}

// Get recent requests
$res = db_query('SELECT r.id, r.request_status_id, rs.name as status_name, dt.name as doc_type FROM request r LEFT JOIN request_status rs ON r.request_status_id = rs.id LEFT JOIN document_type dt ON r.document_type_id = dt.id WHERE r.user_id = ? ORDER BY r.created_at DESC LIMIT 5', 'i', [$user_id]);
$recent_requests = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $recent_requests[] = $row;
    }
}

// Get recent complaints
$res = db_query('SELECT c.id, c.complaint_status_id, cs.name as status_name, c.title FROM complaint c LEFT JOIN complaint_status cs ON c.complaint_status_id = cs.id WHERE c.user_id = ? ORDER BY c.created_at DESC LIMIT 5', 'i', [$user_id]);
$recent_complaints = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $recent_complaints[] = $row;
    }
}

// Get recent announcements from user's barangay
$barangay_id = current_user_barangay_id();
$res = db_query('SELECT id, title, created_at FROM announcement WHERE barangay_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 3', 'i', [$barangay_id]);
$announcements = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $announcements[] = $row;
    }
}

// Helper to safely count results
function user_dashboard_count($sql, $types = '', $params = [], $fallback = 0) {
    $res = db_query($sql, $types, $params);
    if ($res && ($row = $res->fetch_assoc()) && isset($row['cnt'])) {
        return intval($row['cnt']);
    }
    return $fallback;
}

// Counts
$pending_requests = user_dashboard_count('SELECT COUNT(*) as cnt FROM request WHERE user_id = ? AND request_status_id = 1', 'i', [$user_id]);
$completed_requests = user_dashboard_count('SELECT COUNT(*) as cnt FROM request WHERE user_id = ? AND request_status_id = 4', 'i', [$user_id]);
$total_complaints = user_dashboard_count('SELECT COUNT(*) as cnt FROM complaint WHERE user_id = ?', 'i', [$user_id]);

require_once __DIR__ . '/../public/header.php';
?>

<div class="container-fluid my-4 px-3 px-md-4">
    <div style="max-width: 1200px; margin-left: auto; margin-right: auto;">
        <div class="mb-4">
            <h1 class="h2 mb-1 fw-600">Welcome back<?php echo !empty($profile['first_name']) ? ', ' . htmlspecialchars($profile['first_name']) : ''; ?></h1>
            <p class="text-muted small mb-2">Verification: <?php echo $is_verified ? '<span class="text-success fw-600">Verified</span>' : '<span class="text-danger fw-600">Not Verified</span>'; ?></p>
            <?php if (!$is_verified): ?>
                <div class="alert alert-warning mb-0" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>Your account is not verified. Submit your ID to access all services.
                    <a href="<?php echo WEB_ROOT; ?>/index.php?nav=profile&tab=verification" class="alert-link">Go to verification</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Announcements -->
        <div class="mb-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-bullhorn text-success me-2"></i>
                            <h6 class="mb-0 fw-600">Latest Announcements</h6>
                        </div>
                        <a href="index.php?nav=announcements" class="btn btn-sm btn-outline-success">View All</a>
                    </div>
                    <?php if (empty($announcements)): ?>
                        <p class="text-muted mb-0">No announcements yet.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($announcements as $a): ?>
                                <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-600"><?php echo htmlspecialchars($a['title']); ?></div>
                                        <small class="text-muted"><?php echo date('M d, Y', strtotime($a['created_at'])); ?></small>
                                    </div>
                                    <a href="index.php?nav=announcements" class="btn btn-sm btn-outline-primary">Open</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick stats -->
        <div class="mb-5">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100" style="border-top:3px solid #ffc107;">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h2 class="h1 mb-0 text-warning"><?php echo $pending_requests; ?></h2>
                                <i class="fas fa-hourglass-half text-warning opacity-10" style="font-size:2.5rem;"></i>
                            </div>
                            <p class="text-muted small mb-3 fw-600">Pending Requests</p>
                            <a href="index.php?nav=manage-requests" class="btn btn-sm btn-warning w-100">Track</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100" style="border-top:3px solid #198754;">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h2 class="h1 mb-0 text-success">
                                    <?php echo $completed_requests; ?>
                                </h2>
                                <i class="fas fa-check-circle text-success opacity-10" style="font-size:2.5rem;"></i>
                            </div>
                            <p class="text-muted small mb-3 fw-600">Completed Requests</p>
                            <a href="index.php?nav=manage-requests" class="btn btn-sm btn-success w-100">View</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100" style="border-top:3px solid #fd7e14;">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h2 class="h1 mb-0" style="color:#fd7e14;">
                                    <?php echo $total_complaints; ?>
                                </h2>
                                <i class="fas fa-comments opacity-10" style="color:#fd7e14; font-size:2.5rem;"></i>
                            </div>
                            <p class="text-muted small mb-3 fw-600">My Complaints</p>
                            <a href="index.php?nav=complaint-list" class="btn btn-sm w-100" style="background-color:#fd7e14; border-color:#fd7e14; color:white;">Manage</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Requests and Complaints -->
        <div class="mb-5">
            <div class="row g-4">
                <!-- Recent Requests -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-list-ul text-primary me-2"></i>
                                <h6 class="mb-0 fw-600">Recent Requests</h6>
                            </div>
                            <?php if (empty($recent_requests)): ?>
                                <p class="text-muted mb-0">No recent requests.</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recent_requests as $req): ?>
                                        <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="fw-600"><?php echo htmlspecialchars($req['doc_type'] ?? 'Document'); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($req['status_name'] ?? ''); ?></small>
                                            </div>
                                            <a href="index.php?nav=request-ticket&id=<?php echo $req['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Complaints -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-exclamation-circle text-danger me-2"></i>
                                <h6 class="mb-0 fw-600">Recent Complaints</h6>
                            </div>
                            <?php if (empty($recent_complaints)): ?>
                                <p class="text-muted mb-0">No recent complaints.</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recent_complaints as $comp): ?>
                                        <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="fw-600"><?php echo htmlspecialchars($comp['title'] ?? 'Complaint'); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($comp['status_name'] ?? ''); ?></small>
                                            </div>
                                            <a href="index.php?nav=complaint-list" class="btn btn-sm btn-outline-primary">View</a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="mb-5">
            <h6 class="text-muted fw-600 mb-3">Quick Actions</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <a href="index.php?nav=create-request" class="card border-0 text-decoration-none text-dark h-100 hover-card-link">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-plus-circle text-primary me-3"></i>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">Create Request</h6>
                                    <small class="text-muted">Start a new document request</small>
                                </div>
                                <i class="fas fa-chevron-right text-muted small"></i>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="index.php?nav=manage-requests" class="card border-0 text-decoration-none text-dark h-100 hover-card-link">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-file-alt text-warning me-3"></i>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">My Requests</h6>
                                    <small class="text-muted">Track status</small>
                                </div>
                                <i class="fas fa-chevron-right text-muted small"></i>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="index.php?nav=complaint-list" class="card border-0 text-decoration-none text-dark h-100 hover-card-link">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-comments text-info me-3"></i>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">My Complaints</h6>
                                    <small class="text-muted">View and follow-up</small>
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
                        <div class="card-body p-3">
