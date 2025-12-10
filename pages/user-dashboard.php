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

require_once __DIR__ . '/../public/header.php';
?>

<style>
    .dashboard-stat-card {
        background: white;
        border-radius: 0.5rem;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        border-top: 4px solid;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .dashboard-stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 60px;
        height: 60px;
        background: currentColor;
        opacity: 0.05;
        border-radius: 50%;
        transform: translate(15px, -15px);
    }

    .dashboard-stat-card:hover {
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        transform: translateY(-2px);
    }

    .dashboard-card {
        background: white;
        border-radius: 0.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .dashboard-card:hover {
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    .dashboard-card-header {
        border-bottom: 2px solid;
        padding: 1.25rem;
        background: linear-gradient(135deg, rgba(26, 84, 144, 0.05), rgba(26, 84, 144, 0.02));
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .dashboard-card-header h5 {
        color: #1a5490;
        font-weight: 700;
        margin: 0;
        font-size: 1.1rem;
    }

    .dashboard-card-header i {
        font-size: 1.25rem;
    }

    .verification-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .verification-badge.verified {
        background: linear-gradient(135deg, #d4edda, #c3e6cb);
        color: #155724;
        border: 1px solid #b1dfbb;
    }

    .verification-badge.pending {
        background: linear-gradient(135deg, #fff3cd, #ffeeba);
        color: #856404;
        border: 1px solid #ffeaa7;
    }

    .stat-number {
        font-weight: 700;
        font-size: 2.5rem;
        margin: 0.75rem 0 0 0;
        line-height: 1;
        color: #1a5490 !important;
    }

    .stat-label {
        color: #666 !important;
        font-size: 0.85rem;
        margin: 0;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: block;
    }

    .action-btn {
        padding: 0.875rem 1rem;
        border-radius: 0.375rem;
        text-decoration: none;
        text-align: center;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.2s ease;
        display: block;
        border: none;
        cursor: pointer;
    }

    .action-btn-primary {
        background: linear-gradient(135deg, #1a5490, #153d7a);
        color: white;
    }

    .action-btn-primary:hover {
        background: linear-gradient(135deg, #153d7a, #0f2a57);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(26, 84, 144, 0.3);
    }

    .action-btn-danger {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        color: white;
    }

    .action-btn-danger:hover {
        background: linear-gradient(135deg, #b91c1c, #991b1b);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
    }

    .action-btn-outline {
        background: white;
        color: #1a5490;
        border: 2px solid #1a5490;
    }

    .action-btn-outline:hover {
        background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(26, 84, 144, 0.2);
    }

    .request-item {
        padding: 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.375rem;
        border-left: 4px solid #1a5490;
        transition: all 0.2s ease;
        display: block;
        text-decoration: none;
        color: inherit;
        background: white;
    }

    .request-item:hover {
        background: linear-gradient(135deg, #f8f9fa, #f0f1f5);
        border-left-color: #153d7a;
        transform: translateX(4px);
        box-shadow: 0 2px 8px rgba(26, 84, 144, 0.1);
    }

    .request-title {
        color: #1a5490;
        font-weight: 600;
        margin: 0 0 0.25rem 0;
        font-size: 0.95rem;
    }

    .request-meta {
        color: #999;
        font-size: 0.85rem;
    }

    .status-badge {
        display: inline-block;
        padding: 0.375rem 0.75rem;
        border-radius: 0.25rem;
        font-size: 0.8rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .status-pending {
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        color: #92400e;
        border: 1px solid #fcd34d;
    }

    .status-processing {
        background: linear-gradient(135deg, #dbeafe, #bfdbfe);
        color: #1e40af;
        border: 1px solid #93c5fd;
    }

    .status-rejected {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        color: #991b1b;
        border: 1px solid #fca5a5;
    }

    .status-completed {
        background: linear-gradient(135deg, #dcfce7, #bbf7d0);
        color: #166534;
        border: 1px solid #86efac;
    }

    .announcement-item {
        padding-bottom: 1.25rem;
        border-bottom: 1px solid #e5e7eb;
        transition: all 0.2s ease;
    }

    .announcement-item:last-child {
        border-bottom: none;
    }

    .announcement-item:hover {
        padding-left: 0.5rem;
    }

    .announcement-title {
        color: #1a5490;
        font-weight: 700;
        margin: 0 0 0.5rem 0;
        font-size: 0.95rem;
    }

    .announcement-date {
        color: #999;
        font-size: 0.85rem;
    }

    .complaint-item {
        padding: 0.875rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.375rem;
        border-left: 4px solid #dc2626;
        transition: all 0.2s ease;
        background: white;
    }

    .complaint-item:hover {
        background: linear-gradient(135deg, #fef2f2, #fee2e2);
        transform: translateX(2px);
        box-shadow: 0 2px 8px rgba(220, 38, 38, 0.1);
    }

    .complaint-title {
        color: #1a5490;
        font-weight: 600;
        margin: 0 0 0.25rem 0;
        font-size: 0.85rem;
    }
</style>

<div class="container-fluid" style="background: linear-gradient(135deg, #f8f9fa 0%, #f0f1f5 100%); min-height: 100vh; padding: 2rem 0;">
    <div class="container">
        <!-- Header Section -->
        <div class="mb-5">
            <div style="border-bottom: 3px solid #1a5490; padding-bottom: 1.5rem;">
                <h1 style="color: #1a5490; font-weight: 700; font-size: 2.25rem; margin-bottom: 0.5rem;">
                    <i class="fas fa-home" style="margin-right: 0.75rem;"></i>Dashboard
                </h1>
                <p style="color: #666; margin: 0; font-size: 1rem;">Welcome back, <strong><?php echo e($profile['first_name'] ?? $_SESSION['username']); ?></strong></p>
            </div>
        </div>

        <!-- Verification Status Alert -->
        <?php if (!$is_verified): ?>
            <div style="background: linear-gradient(135deg, #fff3cd, #ffeeba); border-left: 5px solid #ffc107; padding: 1.25rem; border-radius: 0.5rem; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(255, 193, 7, 0.2);">
                <div style="display: flex; align-items: flex-start; gap: 1rem;">
                    <i class="fas fa-info-circle" style="color: #856404; margin-top: 0.2rem; flex-shrink: 0; font-size: 1.25rem;"></i>
                    <div style="color: #856404;">
                        <strong style="font-weight: 700; font-size: 1rem;">Verification Required</strong>
                        <p style="margin: 0.5rem 0 0 0; font-size: 0.95rem;">Your account is awaiting verification. Status: <strong style="font-weight: 700;"><?php echo e(ucfirst($verification_status)); ?></strong></p>
                        <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem;">Complete your verification to unlock requests and complaints.</p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div style="background: linear-gradient(135deg, #d4edda, #c3e6cb); border-left: 5px solid #28a745; padding: 1.25rem; border-radius: 0.5rem; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(40, 167, 69, 0.2);">
                <div style="display: flex; align-items: center; gap: 0.75rem; color: #155724; font-weight: 600;">
                    <i class="fas fa-check-circle" style="font-size: 1.25rem;"></i>
                    Account Verified
                </div>
            </div>
        <?php endif; ?>

        <!-- Stats Cards Row -->
        <div class="row mb-5 g-3">
            <div class="col-md-6 col-lg-3">
                <div class="dashboard-stat-card" style="border-top-color: #1a5490;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; position: relative; z-index: 1;">
                        <div>
                            <p class="stat-label">Total Requests</p>
                            <h2 class="stat-number" style="color: #1a5490;"><?php echo db_query('SELECT COUNT(*) as cnt FROM request WHERE user_id = ?', 'i', [$user_id])->fetch_assoc()['cnt'] ?? 0; ?></h2>
                        </div>
                        <i class="fas fa-file-alt" style="font-size: 2rem; opacity: 0.15;"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="dashboard-stat-card" style="border-top-color: #f59e0b;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; position: relative; z-index: 1;">
                        <div>
                            <p class="stat-label">Pending</p>
                            <h2 class="stat-number" style="color: #f59e0b;"><?php echo db_query('SELECT COUNT(*) as cnt FROM request WHERE user_id = ? AND request_status_id = 1', 'i', [$user_id])->fetch_assoc()['cnt'] ?? 0; ?></h2>
                        </div>
                        <i class="fas fa-clock" style="font-size: 2rem; opacity: 0.15;"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="dashboard-stat-card" style="border-top-color: #dc2626;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; position: relative; z-index: 1;">
                        <div>
                            <p class="stat-label">Complaints</p>
                            <h2 class="stat-number" style="color: #dc2626;"><?php echo db_query('SELECT COUNT(*) as cnt FROM complaint WHERE user_id = ?', 'i', [$user_id])->fetch_assoc()['cnt'] ?? 0; ?></h2>
                        </div>
                        <i class="fas fa-exclamation-circle" style="font-size: 2rem; opacity: 0.15;"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="dashboard-stat-card" style="border-top-color: #28a745;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; position: relative; z-index: 1;">
                        <div>
                            <p class="stat-label">Status</p>
                            <div style="margin-top: 0.5rem;">
                                <?php if ($is_verified): ?>
                                    <span class="verification-badge verified"><i class="fas fa-check me-1"></i>Verified</span>
                                <?php else: ?>
                                    <span class="verification-badge pending"><i class="fas fa-clock me-1"></i>Pending</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <i class="fas fa-shield-alt" style="font-size: 2rem; opacity: 0.15;"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Row -->
        <div class="row g-3 mb-4">
            <!-- Recent Requests -->
            <div class="col-lg-8">
                <div class="dashboard-card">
                    <div class="dashboard-card-header" style="border-bottom-color: #1a5490;">
                        <i class="fas fa-file-alt" style="color: #1a5490;"></i>
                        <h5>Recent Requests</h5>
                    </div>
                    <div style="padding: 1.5rem;">
                        <?php if (count($recent_requests) > 0): ?>
                            <div style="display: flex; flex-direction: column; gap: 1rem;">
                                <?php foreach ($recent_requests as $req): ?>
                                    <a href="index.php?nav=request-ticket&id=<?php echo $req['id']; ?>" class="request-item">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <div>
                                                <h6 class="request-title"><?php echo e($req['doc_type']); ?></h6>
                                                <small class="request-meta">Request ID: <?php echo $req['id']; ?></small>
                                            </div>
                                            <span class="status-badge status-<?php $status_id = intval($req['request_status_id']); echo $status_id === 1 ? 'pending' : ($status_id === 2 ? 'processing' : ($status_id === 3 ? 'rejected' : 'completed')); ?>">
                                                <?php echo e($req['status_name']); ?>
                                            </span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p style="color: #999; text-align: center; margin: 1.5rem 0;">No requests yet. <a href="index.php?nav=create-request" style="color: #1a5490; text-decoration: none; font-weight: 700;">Create one now</a></p>
                        <?php endif; ?>
                    </div>
                    <div style="border-top: 1px solid #e5e7eb; padding: 1rem 1.5rem; background: linear-gradient(135deg, rgba(26, 84, 144, 0.03), transparent);">
                        <a href="index.php?nav=request-list" style="color: #1a5490; text-decoration: none; font-weight: 700; font-size: 0.9rem;"><i class="fas fa-arrow-right me-1"></i>View All Requests</a>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="col-lg-4">
                <div class="dashboard-card">
                    <div class="dashboard-card-header" style="border-bottom-color: #1a5490;">
                        <i class="fas fa-thunderbolt" style="color: #1a5490;"></i>
                        <h5>Quick Actions</h5>
                    </div>
                    <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 0.875rem;">
                        <?php if ($is_verified): ?>
                            <a href="index.php?nav=create-request" class="action-btn action-btn-primary">
                                <i class="fas fa-plus me-2"></i>New Request
                            </a>
                            <a href="index.php?nav=complaint-list" class="action-btn action-btn-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>File Complaint
                            </a>
                        <?php else: ?>
                            <p style="color: #999; font-size: 0.9rem; margin: 0; text-align: center; padding: 0.5rem;">Complete verification to submit requests or complaints.</p>
                        <?php endif; ?>
                        <a href="index.php?nav=request-list" class="action-btn action-btn-outline">
                            <i class="fas fa-list me-2"></i>View Requests
                        </a>
                        <a href="index.php?nav=profile" class="action-btn" style="background: white; color: #666; border: 2px solid #d1d5db; transition: all 0.2s ease;" onmouseover="this.style.backgroundColor='#f8f9fa'; this.style.borderColor='#9ca3af';" onmouseout="this.style.backgroundColor='white'; this.style.borderColor='#d1d5db';">
                            <i class="fas fa-user me-2"></i>My Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Announcements and Complaints Row -->
        <div class="row g-3">
            <!-- Announcements -->
            <div class="col-lg-8">
                <div class="dashboard-card">
                    <div class="dashboard-card-header" style="border-bottom-color: #1a5490;">
                        <i class="fas fa-megaphone" style="color: #1a5490;"></i>
                        <h5>Recent Announcements</h5>
                    </div>
                    <div style="padding: 1.5rem;">
                        <?php if (count($announcements) > 0): ?>
                            <div style="display: flex; flex-direction: column; gap: 0;">
                                <?php foreach ($announcements as $ann): ?>
                                    <div class="announcement-item">
                                        <h6 class="announcement-title"><?php echo e($ann['title']); ?></h6>
                                        <small class="announcement-date"><i class="fas fa-calendar-alt me-1"></i><?php echo date('F d, Y', strtotime($ann['created_at'])); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p style="color: #999; text-align: center; margin: 1.5rem 0;">No announcements at the moment.</p>
                        <?php endif; ?>
                    </div>
                    <div style="border-top: 1px solid #e5e7eb; padding: 1rem 1.5rem; background: linear-gradient(135deg, rgba(26, 84, 144, 0.03), transparent);">
                        <a href="index.php?nav=announcements" style="color: #1a5490; text-decoration: none; font-weight: 700; font-size: 0.9rem;"><i class="fas fa-arrow-right me-1"></i>View All Announcements</a>
                    </div>
                </div>
            </div>

            <!-- Recent Complaints -->
            <div class="col-lg-4">
                <div class="dashboard-card">
                    <div class="dashboard-card-header" style="border-bottom-color: #dc2626;">
                        <i class="fas fa-exclamation" style="color: #dc2626;"></i>
                        <h5 style="color: #dc2626;">Recent Complaints</h5>
                    </div>
                    <div style="padding: 1.5rem;">
                        <?php if (count($recent_complaints) > 0): ?>
                            <div style="display: flex; flex-direction: column; gap: 0.875rem;">
                                <?php foreach ($recent_complaints as $comp): ?>
                                    <div class="complaint-item">
                                        <h6 class="complaint-title"><?php echo e(substr($comp['title'], 0, 45)); ?><?php echo strlen($comp['title']) > 45 ? '...' : ''; ?></h6>
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem;">
                                            <small class="request-meta">ID: <?php echo $comp['id']; ?></small>
                                            <span class="status-badge status-<?php $status_id = intval($comp['complaint_status_id']); echo $status_id === 1 ? 'pending' : ($status_id === 2 ? 'processing' : ($status_id === 3 ? 'rejected' : 'completed')); ?>">
                                                <?php echo e($comp['status_name']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p style="color: #999; text-align: center; margin: 1.5rem 0; font-size: 0.9rem;">No complaints filed.</p>
                        <?php endif; ?>
                    </div>
                    <div style="border-top: 1px solid #e5e7eb; padding: 1rem 1.5rem; background: linear-gradient(135deg, rgba(220, 38, 38, 0.03), transparent);">
                        <a href="index.php?nav=complaint-list" style="color: #dc2626; text-decoration: none; font-weight: 700; font-size: 0.9rem;"><i class="fas fa-arrow-right me-1"></i>View All</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
