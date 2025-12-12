<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware/auth.php';

require_login();
require_role([ROLE_ADMIN]);

$pageTitle = 'Admin Dashboard';

// Get admin's barangay
$user_id = current_user_id();
$barangay_res = db_query('SELECT id, name FROM barangay WHERE admin_user_id = ?', 'i', [$user_id]);
$barangay = $barangay_res ? $barangay_res->fetch_assoc() : null;
$barangay_id = $barangay['id'] ?? null;

// Fetch all stats at once
$pending_verifications = db_query('SELECT COUNT(*) as cnt FROM user_verification WHERE verification_status_id = 1 AND user_id IN (SELECT id FROM users WHERE id IN (SELECT user_id FROM profile WHERE barangay_id = ?))', 'i', [$barangay_id])->fetch_assoc()['cnt'] ?? 0;
$pending_requests = db_query('SELECT COUNT(*) as cnt FROM request WHERE request_status_id = 1 AND user_id IN (SELECT id FROM users WHERE id IN (SELECT user_id FROM profile WHERE barangay_id = ?))', 'i', [$barangay_id])->fetch_assoc()['cnt'] ?? 0;
$open_complaints = db_query('SELECT COUNT(*) as cnt FROM complaint WHERE complaint_status_id IN (1, 2) AND user_id IN (SELECT id FROM users WHERE id IN (SELECT user_id FROM profile WHERE barangay_id = ?))', 'i', [$barangay_id])->fetch_assoc()['cnt'] ?? 0;
$total_residents = db_query('SELECT COUNT(*) as cnt FROM users WHERE usertype_id = 4 AND id IN (SELECT user_id FROM profile WHERE barangay_id = ?)', 'i', [$barangay_id])->fetch_assoc()['cnt'] ?? 0;
$total_staff = db_query('SELECT COUNT(*) as cnt FROM users WHERE usertype_id = 3 AND id IN (SELECT user_id FROM profile WHERE barangay_id = ?)', 'i', [$barangay_id])->fetch_assoc()['cnt'] ?? 0;
$total_requests = db_query('SELECT COUNT(*) as cnt FROM request WHERE user_id IN (SELECT id FROM users WHERE id IN (SELECT user_id FROM profile WHERE barangay_id = ?))', 'i', [$barangay_id])->fetch_assoc()['cnt'] ?? 0;

require_once __DIR__ . '/../public/header.php';
?>

<div class="container-fluid my-4 px-3 px-md-4">
    <div style="max-width: 1200px; margin-left: auto; margin-right: auto;">
        <!-- Header -->
        <div class="mb-5">
            <h1 class="h2 mb-1 fw-600">Dashboard</h1>
            <p class="text-muted small mb-0">
                <?php echo $barangay ? htmlspecialchars($barangay['name']) : 'Barangay'; ?> 
                • <?php echo $total_residents; ?> Residents 
                • <?php echo $total_staff; ?> Staff
            </p>
        </div>

        <!-- Action Items (Quick Stats with Actions) -->
        <div class="mb-5">
            <div class="row g-3">
                <!-- Pending Verifications -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100" style="border-top: 3px solid #dc3545; cursor: pointer; transition: all 0.2s ease;" onmouseover="this.style.boxShadow='0 0.5rem 1rem rgba(0, 0, 0, 0.1)'; this.style.transform='translateY(-1px)'" onmouseout="this.style.boxShadow='0 0.125rem 0.25rem rgba(0, 0, 0, 0.075)'; this.style.transform='translateY(0)'">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h2 class="h1 mb-0 text-danger"><?php echo $pending_verifications; ?></h2>
                                <i class="fas fa-id-card text-danger opacity-10" style="font-size: 2.5rem;"></i>
                            </div>
                            <p class="text-muted small mb-3" style="font-weight: 600;">Verifications Pending</p>
                            <a href="index.php?nav=manage-verifications&filter=pending" class="btn btn-sm btn-danger btn-sm w-100">Review</a>
                        </div>
                    </div>
                </div>

                <!-- Pending Requests -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100" style="border-top: 3px solid #ffc107; cursor: pointer; transition: all 0.2s ease;" onmouseover="this.style.boxShadow='0 0.5rem 1rem rgba(0, 0, 0, 0.1)'; this.style.transform='translateY(-1px)'" onmouseout="this.style.boxShadow='0 0.125rem 0.25rem rgba(0, 0, 0, 0.075)'; this.style.transform='translateY(0)'">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h2 class="h1 mb-0 text-warning"><?php echo $pending_requests; ?></h2>
                                <i class="fas fa-file-alt text-warning opacity-10" style="font-size: 2.5rem;"></i>
                            </div>
                            <p class="text-muted small mb-3" style="font-weight: 600;">Requests Pending</p>
                            <a href="index.php?nav=manage-requests&filter=pending" class="btn btn-sm btn-warning w-100">Process</a>
                        </div>
                    </div>
                </div>

                <!-- Open Complaints -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100" style="border-top: 3px solid #fd7e14; cursor: pointer; transition: all 0.2s ease;" onmouseover="this.style.boxShadow='0 0.5rem 1rem rgba(0, 0, 0, 0.1)'; this.style.transform='translateY(-1px)'" onmouseout="this.style.boxShadow='0 0.125rem 0.25rem rgba(0, 0, 0, 0.075)'; this.style.transform='translateY(0)'">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h2 class="h1 mb-0" style="color: #fd7e14;"><?php echo $open_complaints; ?></h2>
                                <i class="fas fa-exclamation-triangle opacity-10" style="color: #fd7e14; font-size: 2.5rem;"></i>
                            </div>
                            <p class="text-muted small mb-3" style="font-weight: 600;">Complaints Open</p>
                            <a href="index.php?nav=manage-complaints&filter=open" class="btn btn-sm w-100" style="background-color: #fd7e14; border-color: #fd7e14; color: white;">Manage</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overview Stats (Compact) -->
        <div class="mb-5">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body p-3">
                            <small class="text-muted d-block mb-2" style="font-weight: 600;">Total Requests</small>
                            <h4 class="mb-0"><?php echo $total_requests; ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body p-3">
                            <small class="text-muted d-block mb-2" style="font-weight: 600;">Verified Residents</small>
                            <h4 class="mb-0"><?php echo db_query('SELECT COUNT(*) as cnt FROM user_verification WHERE verification_status_id = 2 AND user_id IN (SELECT id FROM users WHERE id IN (SELECT user_id FROM profile WHERE barangay_id = ?))', 'i', [$barangay_id])->fetch_assoc()['cnt'] ?? 0; ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body p-3">
                            <small class="text-muted d-block mb-2" style="font-weight: 600;">Total Residents</small>
                            <h4 class="mb-0"><?php echo $total_residents; ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body p-3">
                            <small class="text-muted d-block mb-2" style="font-weight: 600;">Staff Members</small>
                            <h4 class="mb-0"><?php echo $total_staff; ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Management Links (Minimalist) -->
        <div>
            <h6 class="text-muted fw-600 mb-3">Tools</h6>
            <div class="row g-2">
                <div class="col-md-6">
                    <a href="index.php?nav=manage-verifications" class="card border-0 text-decoration-none text-dark h-100 hover-card-link">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-id-card text-danger me-3"></i>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">Verifications</h6>
                                    <small class="text-muted">Review IDs</small>
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
                                <i class="fas fa-file-alt text-warning me-3"></i>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">Requests</h6>
                                    <small class="text-muted">All requests</small>
                                </div>
                                <i class="fas fa-chevron-right text-muted small"></i>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6">
                    <a href="index.php?nav=manage-complaints" class="card border-0 text-decoration-none text-dark h-100 hover-card-link">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-comments text-info me-3"></i>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">Complaints</h6>
                                    <small class="text-muted">All complaints</small>
                                </div>
                                <i class="fas fa-chevron-right text-muted small"></i>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6">
                    <a href="index.php?nav=manage-users" class="card border-0 text-decoration-none text-dark h-100 hover-card-link">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-users text-primary me-3"></i>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">Users</h6>
                                    <small class="text-muted">Manage accounts</small>
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
    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    }
    
    .fw-600 {
        font-weight: 600;
    }
    
    .hover-card-link {
        transition: all 0.2s ease;
        border: 1px solid #e9ecef;
    }
    
    .hover-card-link:hover {
        background-color: #f8f9fa !important;
        border-color: #dee2e6;
    }
</style>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
