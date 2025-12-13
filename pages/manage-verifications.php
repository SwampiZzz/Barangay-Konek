<?php
require_once __DIR__ . '/../config.php';
require_login();
$pageTitle = 'Manage Verifications';

$user_id = current_user_id();

// Check if user is admin
$role = current_user_role();
if ($role !== ROLE_ADMIN) {
    $_SESSION['alert_type'] = 'danger';
    $_SESSION['alert_message'] = 'Access denied. Only barangay admins can manage verifications.';
    header('Location: ' . WEB_ROOT . '/index.php?nav=admin-dashboard');
    exit;
}

// Get the admin's barangay (from profile table)
$admin_barangay_res = db_query('SELECT b.id, b.name FROM barangay b JOIN profile p ON b.id = p.barangay_id WHERE p.user_id = ?', 'i', [$user_id]);
if (!$admin_barangay_res || $admin_barangay_res->num_rows === 0) {
    $_SESSION['alert_type'] = 'warning';
    $_SESSION['alert_message'] = 'You are not assigned as an admin to any barangay.';
    header('Location: ' . WEB_ROOT . '/index.php?nav=admin-dashboard');
    exit;
}
$admin_barangay = $admin_barangay_res->fetch_assoc();
$barangay_id = $admin_barangay['id'];
$barangay_name = $admin_barangay['name'];

$alert_type = '';
$alert_message = '';

// Retrieve alert from session if it exists
if (isset($_SESSION['alert_type']) && isset($_SESSION['alert_message'])) {
    $alert_type = $_SESSION['alert_type'];
    $alert_message = $_SESSION['alert_message'];
    unset($_SESSION['alert_type']);
    unset($_SESSION['alert_message']);
}

// Get filter parameter
$filter = $_GET['filter'] ?? 'all'; // pending, verified, rejected, all
$q = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'date_desc'; // date_desc, date_asc, name_asc
$auto_review_modal = isset($_GET['auto_review_modal']) ? intval($_GET['auto_review_modal']) : 0;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;
$status_map = ['pending' => 1, 'verified' => 2, 'rejected' => 3];

// Handle Approve/Reject Verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $verification_id = intval($_POST['verification_id'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');
    
    if (empty($verification_id)) {
        $_SESSION['alert_type'] = 'danger';
        $_SESSION['alert_message'] = 'Invalid verification ID.';
    } elseif ($action === 'approve') {
        // Approve verification
        $approve = db_query(
            'UPDATE user_verification SET verification_status_id = 2, verified_by = ?, verified_at = NOW() WHERE id = ?',
            'ii',
            [$user_id, $verification_id]
        );
        if ($approve) {
            $_SESSION['alert_type'] = 'success';
            $_SESSION['alert_message'] = 'User verification approved successfully!';
        } else {
            $_SESSION['alert_type'] = 'danger';
            $_SESSION['alert_message'] = 'Failed to approve verification.';
        }
    } elseif ($action === 'reject') {
        // Reject verification - require remarks
        if (empty($remarks)) {
            $_SESSION['alert_type'] = 'danger';
            $_SESSION['alert_message'] = 'Remarks are required when rejecting a verification.';
        } else {
            $reject = db_query(
                'UPDATE user_verification SET verification_status_id = 3, remarks = ?, verified_by = ?, verified_at = NOW() WHERE id = ?',
                'sii',
                [$remarks, $user_id, $verification_id]
            );
            if ($reject) {
                $_SESSION['alert_type'] = 'success';
                $_SESSION['alert_message'] = 'User verification rejected. User has been notified and can resubmit.';
            } else {
                $_SESSION['alert_type'] = 'danger';
                $_SESSION['alert_message'] = 'Failed to reject verification.';
            }
        }
    }
    
    header('Location: ' . WEB_ROOT . '/index.php?nav=manage-verifications&filter=' . $filter);
    exit;
}

// Build query based on filter - filter by admin's barangay
$where = ' WHERE p.barangay_id = ' . intval($barangay_id);
if ($filter !== 'all' && isset($status_map[$filter])) {
    $where .= ' AND uv.verification_status_id = ' . $status_map[$filter];
}

// Apply search filter (name, username, email)
if ($q !== '') {
    $safe = addslashes($q);
    $safe = str_replace(['%', '_'], ['\\%','\\_'], $safe);
    $where .= " AND (CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) LIKE '%$safe%'"
            . " OR u.username LIKE '%$safe%'"
            . " OR p.email LIKE '%$safe%')";
}

// Count total for pagination
$count_query = "SELECT COUNT(*) as total
FROM user_verification uv
LEFT JOIN profile p ON uv.user_id = p.user_id
$where";
$count_res = db_query($count_query);
$total_verifications = $count_res ? $count_res->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_verifications / $per_page);

// Get all verifications with user info
$query = "SELECT 
    uv.id as verification_id,
    uv.user_id,
    uv.filename,
    uv.verification_status_id,
    uv.submitted_at,
    uv.remarks,
    uv.verified_at,
    vs.name as status_name,
    u.username,
    p.first_name,
    p.middle_name,
    p.last_name,
    p.email,
    p.contact_number,
    p.birthdate,
    b.name as barangay_name,
    admin.username as verified_by_admin,
    ap.first_name as admin_first_name,
    ap.last_name as admin_last_name,
    ap.email as admin_email
FROM user_verification uv
LEFT JOIN verification_status vs ON uv.verification_status_id = vs.id
LEFT JOIN users u ON uv.user_id = u.id
LEFT JOIN profile p ON uv.user_id = p.user_id
LEFT JOIN barangay b ON p.barangay_id = b.id
LEFT JOIN users admin ON uv.verified_by = admin.id
LEFT JOIN profile ap ON admin.id = ap.user_id
$where
ORDER BY ";

// Apply sorting
switch ($sort) {
    case 'date_asc': $query .= "uv.submitted_at ASC"; break;
    case 'name_asc': $query .= "CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) ASC"; break;
    case 'date_desc':
    default: $query .= "uv.submitted_at DESC"; break;
}

$query .= " LIMIT $per_page OFFSET $offset";

$result = db_query($query);
$verifications = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $verifications[] = $row;
    }
}
$showing_verifications = count($verifications);

// Count by status for tabs
$count_query = "SELECT 
    SUM(CASE WHEN uv.verification_status_id = 1 THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN uv.verification_status_id = 2 THEN 1 ELSE 0 END) as verified_count,
    SUM(CASE WHEN uv.verification_status_id = 3 THEN 1 ELSE 0 END) as rejected_count,
    COUNT(*) as total_count
FROM user_verification uv
LEFT JOIN profile p ON uv.user_id = p.user_id
WHERE p.barangay_id = " . intval($barangay_id);

$count_result = db_query($count_query);
$counts = $count_result ? $count_result->fetch_assoc() : ['pending_count' => 0, 'verified_count' => 0, 'rejected_count' => 0, 'total_count' => 0];

require_once __DIR__ . '/../public/header.php';
?>

<style>
.lightbox {
    display: none;
    position: fixed;
    z-index: 2000;
    padding-top: 50px;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.95);
}

.lightbox-content {
    margin: 0;
    display: block;
    width: 100%;
    height: 100vh;
    object-fit: contain;
    animation: zoomIn 0.3s ease-in-out;
}

@keyframes zoomIn {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.lightbox-caption {
    margin: auto;
    display: block;
    width: 100%;
    text-align: center;
    color: #ccc;
    padding: 10px 0;
    height: 50px;
    font-size: 0.95rem;
}

.lightbox-close {
    position: absolute;
    top: 20px;
    right: 30px;
    color: #f1f1f1;
    font-size: 40px;
    font-weight: bold;
    cursor: pointer;
    transition: 0.3s;
    line-height: 1;
}

.lightbox-close:hover {
    color: #fff;
}

.lightbox-controls {
    position: absolute;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 10px;
    z-index: 2001;
}

.lightbox-btn {
    background-color: rgba(255, 255, 255, 0.2);
    color: #f1f1f1;
    border: 1px solid #f1f1f1;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.9rem;
    transition: background-color 0.3s;
}

.lightbox-btn:hover {
    background-color: rgba(255, 255, 255, 0.4);
}

.document-preview-image {
    cursor: zoom-in;
    transition: transform 0.2s;
}

.document-preview-image:hover {
    transform: scale(1.02);
}
</style>

<div class="container my-5">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="mb-2"><i class="fas fa-certificate me-2"></i>Manage Verifications</h2>
            <p class="text-muted mb-0">Review and approve/reject user verification documents for <strong><?php echo htmlspecialchars($barangay_name); ?></strong></p>
        </div>
        <div class="col-md-4">
            <form class="input-group" method="get" action="<?php echo WEB_ROOT; ?>/index.php">
                <input type="hidden" name="nav" value="manage-verifications">
                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search name, @username, or email...">
                <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </div>

    <?php if (!empty($alert_message)): ?>
        <div class="alert alert-<?php echo $alert_type; ?> alert-dismissible fade show mb-4" role="alert">
            <?php if ($alert_type === 'success'): ?>
                <i class="fas fa-check-circle me-2"></i>
            <?php else: ?>
                <i class="fas fa-exclamation-circle me-2"></i>
            <?php endif; ?>
            <?php echo htmlspecialchars($alert_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Status Tabs -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <div>
                <?php $base = WEB_ROOT . '/index.php?nav=manage-verifications&q=' . urlencode($q) . '&sort=' . htmlspecialchars($sort); ?>
                <ul class="nav nav-pills card-header-pills mb-0" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo ($filter === 'all') ? 'active' : ''; ?>" href="<?php echo $base; ?>&filter=all">
                            All
                            <span class="badge <?php echo ($filter === 'all') ? 'bg-light text-dark' : 'bg-secondary'; ?> ms-2"><?php echo $counts['total_count'] ?? 0; ?></span>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo ($filter === 'pending') ? 'active' : ''; ?>" href="<?php echo $base; ?>&filter=pending">
                            Pending
                            <span class="badge <?php echo ($filter === 'pending') ? 'bg-light text-dark' : 'bg-warning text-dark'; ?> ms-2"><?php echo $counts['pending_count'] ?? 0; ?></span>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo ($filter === 'verified') ? 'active' : ''; ?>" href="<?php echo $base; ?>&filter=verified">
                            Verified
                            <span class="badge <?php echo ($filter === 'verified') ? 'bg-light text-dark' : 'bg-success'; ?> ms-2"><?php echo $counts['verified_count'] ?? 0; ?></span>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo ($filter === 'rejected') ? 'active' : ''; ?>" href="<?php echo $base; ?>&filter=rejected">
                            Rejected
                            <span class="badge <?php echo ($filter === 'rejected') ? 'bg-light text-dark' : 'bg-danger'; ?> ms-2"><?php echo $counts['rejected_count'] ?? 0; ?></span>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="ms-3">
                <form class="d-flex align-items-center" method="get" action="<?php echo WEB_ROOT; ?>/index.php" style="gap: 8px;">
                    <input type="hidden" name="nav" value="manage-verifications">
                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($q); ?>">
                    <label for="sortBy" class="form-label mb-0" style="font-size: 0.9rem; font-weight: 500;">Sort:</label>
                    <select id="sortBy" name="sort" class="form-select form-select-sm" style="width: 150px;" onchange="this.form.submit()">
                        <option value="date_desc" <?php echo $sort === 'date_desc' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="date_asc" <?php echo $sort === 'date_asc' ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                    </select>
                </form>
            </div>
        </div>

        <div class="card-body p-0">
            <!-- Verification List -->
            <?php if (empty($verifications)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted mb-0">No verifications found for this filter.</p>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($verifications as $v): ?>
                        <div class="list-group-item list-group-item-action">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center">
                                        <?php 
                                            $uid = intval($v['user_id']);
                                            $picDir = __DIR__ . '/../storage/app/private/profile_pics/';
                                            $picWeb = WEB_ROOT . '/storage/app/private/profile_pics/';
                                            $picName = 'user_' . $uid . '.jpg';
                                            $picPath = $picDir . $picName;
                                        ?>
                                        <?php if (file_exists($picPath)): ?>
                                            <img src="<?php echo $picWeb . $picName; ?>" alt="Profile" class="rounded-circle me-3" style="width:50px;height:50px;object-fit:cover;">
                                        <?php else: ?>
                                            <div class="avatar-circle bg-primary text-white me-3" style="width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 18px;">
                                                <?php echo strtoupper(substr($v['first_name'], 0, 1) . substr($v['last_name'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($v['first_name'] . ' ' . $v['middle_name'] . ' ' . $v['last_name']); ?></h6>
                                            <small class="text-muted">
                                                <i class="fas fa-user me-1"></i>@<?php echo htmlspecialchars($v['username']); ?>
                                                <span class="mx-2">•</span>
                                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($v['email'] ?? 'N/A'); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted d-block">
                                        <i class="far fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($v['submitted_at'])); ?>
                                    </small>
                                    <small class="text-muted">
                                        <i class="far fa-clock me-1"></i><?php echo date('h:i A', strtotime($v['submitted_at'])); ?>
                                    </small>
                                </div>
                                <div class="col-md-2 text-center">
                                    <?php if ($v['verification_status_id'] == 1): ?>
                                        <span class="badge bg-warning text-dark fs-6"><i class="fas fa-hourglass-half me-1"></i>Pending</span>
                                    <?php elseif ($v['verification_status_id'] == 2): ?>
                                        <span class="badge bg-success fs-6"><i class="fas fa-check-circle me-1"></i>Verified</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger fs-6"><i class="fas fa-times-circle me-1"></i>Rejected</span>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-1 text-end">
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#reviewModal" 
                                            onclick='loadVerificationData(<?php echo json_encode($v); ?>)'>
                                        <i class="fas fa-clipboard-check me-1"></i>Review
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-light">
                <nav aria-label="Verification pagination">
                    <ul class="pagination pagination-sm mb-0 justify-content-center">
                        <?php 
                        $base_url = WEB_ROOT . '/index.php?nav=manage-verifications&filter=' . urlencode($filter) . '&q=' . urlencode($q) . '&sort=' . urlencode($sort);
                        
                        // Previous button
                        if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo $base_url; ?>&page=<?php echo $page - 1; ?>">Previous</a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link">Previous</span>
                            </li>
                        <?php endif;
                        
                        // Page numbers
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1): ?>
                            <li class="page-item"><a class="page-link" href="<?php echo $base_url; ?>&page=1">1</a></li>
                            <?php if ($start_page > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif;
                        endif;
                        
                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo $base_url; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor;
                        
                        if ($end_page < $total_pages): 
                            if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item"><a class="page-link" href="<?php echo $base_url; ?>&page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a></li>
                        <?php endif;
                        
                        // Next button
                        if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo $base_url; ?>&page=<?php echo $page + 1; ?>">Next</a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link">Next</span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Single Verification Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center w-100">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width:44px;height:44px;">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="modal-title mb-0">Verification Ticket <span class="text-muted">#<span id="modalVerificationId"></span></span></h5>
                        <small class="text-muted">Review the submission details and take action</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body pt-3">
                <!-- Document Preview - Full Width Top -->
                <div class="card border-0 shadow-sm mb-4" id="modalPreviewCard">
                    <div class="card-header bg-light py-2">
                        <small class="text-muted"><i class="fas fa-file me-1"></i>Verification Document</small>
                    </div>
                    <div class="card-body p-0 text-center" id="modalDocumentPreview"></div>
                </div>

                <!-- User Info & Details -->
                <div class="row g-4">
                    <!-- User Information Card -->
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <img id="modalAvatarImg" src="#" alt="Profile" class="rounded-circle me-3" style="width:60px;height:60px;object-fit:cover;display:none;" onerror="this.style.display='none'; document.getElementById('modalAvatar').style.display='flex';">
                                    <div class="avatar-circle bg-primary text-white me-3" id="modalAvatar" style="width: 60px; height: 60px; border-radius: 50%; display: none; align-items: center; justify-content: center; font-weight: bold; font-size: 24px;"></div>
                                    <div>
                                        <h5 class="mb-0" id="modalFullName"></h5>
                                        <small class="text-muted" id="modalUsername"></small>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <small class="text-muted d-block mb-1">Email</small>
                                        <div id="modalEmail" class="fw-semibold"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted d-block mb-1">Contact</small>
                                        <div id="modalContact" class="fw-semibold"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted d-block mb-1">Birthdate</small>
                                        <div id="modalBirthdate" class="fw-semibold"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted d-block mb-1">Barangay</small>
                                        <div id="modalBarangay" class="fw-semibold"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submission Details Card -->
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="mb-3"><i class="fas fa-history me-2 text-info"></i>Submission Details</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <small class="text-muted d-block mb-1">File Name</small>
                                        <div id="modalFilename" class="fw-semibold"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted d-block mb-1">Submitted</small>
                                        <div id="modalSubmitted" class="fw-semibold"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted d-block mb-1">Current Status</small>
                                        <div id="modalStatus"></div>
                                    </div>
                                    <div id="modalVerifiedSection" style="display: none;" class="col-md-6">
                                        <small class="text-muted d-block mb-1">Review Date</small>
                                        <div id="modalReviewDate" class="fw-semibold"></div>
                                    </div>
                                    <div id="modalReviewedByRow" style="display: none;" class="col-12">
                                        <small class="text-muted d-block mb-1">Reviewed By</small>
                                        <div id="modalReviewedBy" class="fw-semibold"></div>
                                    </div>
                                    <div id="modalRemarksSection" style="display: none;" class="col-12">
                                        <small class="text-muted d-block mb-2">Admin Remarks</small>
                                        <div class="alert alert-danger mb-0 py-2" id="modalRemarks"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- View User Button -->
                    <div class="col-12">
                        <button type="button" class="btn btn-outline-primary w-100" id="viewUserBtn" onclick="redirectToUserModal()">
                            <i class="fas fa-arrow-left me-1"></i> View User in Manage Users
                        </button>
                    </div>

                    <!-- Action Form -->
                    <div class="col-12">
                        <div class="card border-0 shadow-sm" id="modalActionCard" style="display: none;">
                            <div class="card-body">
                                <h6 class="mb-3"><i class="fas fa-gavel me-2"></i>Review Decision</h6>
                                <div class="alert alert-info small mb-3" id="statusChangeNotice" style="display:none;">
                                    <i class="fas fa-info-circle me-1"></i>This verification is already processed. You can change the status if needed.
                                </div>
                                <form method="post" id="reviewForm" novalidate>
                                    <input type="hidden" name="verification_id" id="formVerificationId">
                                    <input type="hidden" id="formUserId">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Remarks</label>
                                        <textarea class="form-control" name="remarks" id="remarksInput" rows="4" placeholder="Add notes. Required when rejecting."></textarea>
                                        <div class="invalid-feedback">Remarks are required to reject a verification.</div>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" name="action" value="approve" class="btn btn-success flex-grow-1" id="approveBtn">
                                            <i class="fas fa-check-circle me-2"></i>Approve
                                        </button>
                                        <button type="submit" name="action" value="reject" class="btn btn-outline-danger flex-grow-1" id="rejectBtn">
                                            <i class="fas fa-times-circle me-2"></i>Reject
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-load modal if requested from manage-users
const autoReviewId = <?php echo $auto_review_modal; ?>;
if (autoReviewId > 0) {
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            // Find and load the verification data
            const allVerifications = <?php echo json_encode($verifications); ?>;
            const verification = allVerifications.find(v => v.verification_id == autoReviewId);
            if (verification && typeof bootstrap !== 'undefined') {
                const modalElement = document.getElementById('reviewModal');
                if (modalElement) {
                    loadVerificationData(verification);
                    const reviewModal = new bootstrap.Modal(modalElement, {
                        backdrop: 'static',
                        keyboard: true
                    });
                    reviewModal.show();
                }
            }
        }, 300);
    });
}

function loadVerificationData(data) {
    // Basic Info
    document.getElementById('modalVerificationId').textContent = data.verification_id;
    document.getElementById('modalFullName').textContent = data.first_name + ' ' + data.middle_name + ' ' + data.last_name;
    document.getElementById('modalUsername').textContent = '@' + data.username;
    document.getElementById('modalEmail').textContent = data.email || 'N/A';
    document.getElementById('modalContact').textContent = data.contact_number || 'N/A';
    document.getElementById('modalBirthdate').textContent = data.birthdate ? new Date(data.birthdate).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A';
    document.getElementById('modalBarangay').textContent = data.barangay_name || 'N/A';
    
    // Avatar
    const initials = (data.first_name?.charAt(0) || '').toUpperCase() + (data.last_name?.charAt(0) || '').toUpperCase();
    const avatarEl = document.getElementById('modalAvatar');
    const avatarImg = document.getElementById('modalAvatarImg');
    avatarEl.textContent = initials || '';
    const profilePicBase = '<?php echo WEB_ROOT; ?>/storage/app/private/profile_pics';
    avatarImg.src = profilePicBase + '/user_' + data.user_id + '.jpg';
    avatarImg.style.display = 'block';
    avatarEl.style.display = 'none';
    
    // Document Info
    document.getElementById('modalFilename').textContent = data.filename || 'N/A';
    document.getElementById('modalSubmitted').textContent = new Date(data.submitted_at).toLocaleString('en-US', { 
        year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' 
    });
    
    // Status Badge
    let statusHTML = '';
    if (data.verification_status_id == 1) {
        statusHTML = '<span class="badge bg-warning text-dark fs-6"><i class="fas fa-hourglass-half me-1"></i>Pending Review</span>';
    } else if (data.verification_status_id == 2) {
        statusHTML = '<span class="badge bg-success fs-6"><i class="fas fa-check-circle me-1"></i>Verified</span>';
    } else {
        statusHTML = '<span class="badge bg-danger fs-6"><i class="fas fa-times-circle me-1"></i>Rejected</span>';
    }
    document.getElementById('modalStatus').innerHTML = statusHTML;
    
    // Show/Hide Verified Section
    if (data.verification_status_id != 1) {
        document.getElementById('modalVerifiedSection').style.display = 'block';
        document.getElementById('modalReviewedByRow').style.display = 'block';
        
        // Display reviewer's fullname and email (hide username for security)
        const reviewerName = (data.admin_first_name || '') + ' ' + (data.admin_last_name || '');
        const reviewerEmail = data.admin_email || 'N/A';
        document.getElementById('modalReviewedBy').innerHTML = `<div>${reviewerName.trim() || 'System'}</div><small class="text-muted">${reviewerEmail}</small>`;
        
        document.getElementById('modalReviewDate').textContent = data.verified_at ? new Date(data.verified_at).toLocaleString() : 'N/A';
    } else {
        document.getElementById('modalVerifiedSection').style.display = 'none';
        document.getElementById('modalReviewedByRow').style.display = 'none';
    }
    
    // Show/Hide Remarks Section
    if (data.verification_status_id == 3 && data.remarks) {
        document.getElementById('modalRemarksSection').style.display = 'block';
        document.getElementById('modalRemarks').textContent = data.remarks;
    } else {
        document.getElementById('modalRemarksSection').style.display = 'none';
    }
    
    // Document Preview
    const ext = data.filename ? data.filename.split('.').pop().toLowerCase() : '';
    const previewEl = document.getElementById('modalDocumentPreview');
    
    if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) {
        const imgUrl = `<?php echo WEB_ROOT; ?>/storage/app/private/requests/${data.filename}`;
        previewEl.innerHTML = `<img src="${imgUrl}" class="img-fluid w-100 document-preview-image" style="max-height: 600px; object-fit: contain; cursor: zoom-in;" onclick="openLightbox('${imgUrl}', '${data.filename}')"><div class="py-2"><small class="text-muted"><i class="fas fa-search-plus me-1"></i>Click image to zoom</small></div>`;
    } else {
        const fileUrl = `<?php echo WEB_ROOT; ?>/storage/app/private/requests/${data.filename}`;
        previewEl.innerHTML = `<div class="py-5">
            <i class="fas fa-file fa-4x text-muted mb-3"></i>
            <p class="text-muted mb-2">Preview not available</p>
            <div class="d-flex justify-content-center gap-2">
                <a class="btn btn-primary" href="${fileUrl}" target="_blank" rel="noopener">Open File</a>
                <a class="btn btn-outline-secondary" href="${fileUrl}" download>Download</a>
            </div>
            <small class="text-muted d-block mt-2">File type: ${ext ? ext.toUpperCase() : 'UNKNOWN'}</small>
        </div>`;
    }
    
    // Show Action Form for all statuses (allow status changes)
    document.getElementById('modalActionCard').style.display = 'block';
    document.getElementById('formVerificationId').value = data.verification_id;
    document.getElementById('formUserId').value = data.user_id;
    document.getElementById('reviewForm').dataset.userId = data.user_id;
    
    // Show notice if already processed
    const statusNotice = document.getElementById('statusChangeNotice');
    if (data.verification_status_id != 1) {
        statusNotice.style.display = 'block';
    } else {
        statusNotice.style.display = 'none';
    }
}

// Redirect to user modal in manage-users
function redirectToUserModal() {
    const userId = document.getElementById('reviewForm').dataset.userId;
    if (userId) {
        window.location.href = '<?php echo WEB_ROOT; ?>/index.php?nav=manage-users&auto_user_modal=' + userId;
    }
}

// Lightbox functions
function openLightbox(src, filename) {
    const lightbox = document.getElementById('imageLightbox');
    const lightboxImg = document.getElementById('lightboxImage');
    const lightboxCaption = document.getElementById('lightboxCaption');
    
    if (lightbox && lightboxImg) {
        lightboxImg.src = src;
        lightboxCaption.textContent = 'File: ' + filename;
        lightbox.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
}

function closeLightbox() {
    const lightbox = document.getElementById('imageLightbox');
    if (lightbox) {
        lightbox.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

function downloadLightboxImage() {
    const lightboxImg = document.getElementById('lightboxImage');
    if (lightboxImg && lightboxImg.src) {
        const a = document.createElement('a');
        a.href = lightboxImg.src;
        a.download = document.getElementById('lightboxCaption').textContent.replace('File: ', '');
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }
}

// Close lightbox when clicking outside image
document.addEventListener('DOMContentLoaded', function() {
    const lightbox = document.getElementById('imageLightbox');
    if (lightbox) {
        lightbox.addEventListener('click', function(event) {
            if (event.target === lightbox) {
                closeLightbox();
            }
        });
        
        // Allow pressing Escape to close lightbox
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && lightbox.style.display === 'block') {
                closeLightbox();
            }
        });
    }
});
</script>

<script>
// Inline validation: require remarks when rejecting, confirmation when approving
(function(){
  const form = document.getElementById('reviewForm');
  if (!form) return;
  form.addEventListener('submit', function(e){
    const submitter = e.submitter || null;
    if (!submitter) return; // unsafe older browsers
    const action = submitter.value;
    
    if (action === 'approve') {
      // Double-check confirmation for approval
      if (!confirm('Are you sure you want to approve this verification request? The user will be granted full access to the system.')) {
        e.preventDefault();
        return;
      }
    } else if (action === 'reject') {
      const remarks = document.getElementById('remarksInput');
      if (remarks && remarks.value.trim() === '') {
        e.preventDefault();
        remarks.classList.add('is-invalid');
        remarks.focus();
        return;
      } else if (remarks) {
        remarks.classList.remove('is-invalid');
      }
      // Double-check confirmation for rejection
      if (!confirm('Are you sure you want to reject this verification? The user will be notified and can resubmit with corrections.')) {
        e.preventDefault();
        return;
      }
    }
  });
})();
</script>

<!-- Lightbox Modal for Image Zoom -->
<div id="imageLightbox" class="lightbox">
    <span class="lightbox-close" onclick="closeLightbox()">&times;</span>
    <img class="lightbox-content" id="lightboxImage" src="" alt="Document preview">
    <div class="lightbox-caption" id="lightboxCaption"></div>
    <div class="lightbox-controls">
        <button class="lightbox-btn" onclick="downloadLightboxImage()">
            <i class="fas fa-download me-1"></i>Download
        </button>
    </div>
</div>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
