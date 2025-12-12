<?php
require_once __DIR__ . '/../config.php';
require_login();

$pageTitle = 'Manage Users';

// Only barangay admins manage users
$role = current_user_role();
if ($role !== ROLE_ADMIN) {
    $_SESSION['alert_type'] = 'danger';
    $_SESSION['alert_message'] = 'Access denied. Only barangay admins can manage users.';
    header('Location: ' . WEB_ROOT . '/index.php?nav=admin-dashboard');
    exit;
}

$user_id = current_user_id();

// AJAX: Verify admin password before confirming role changes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_admin_password') {
    header('Content-Type: application/json');
    $admin_password = trim($_POST['admin_password'] ?? '');
    if ($admin_password === '') {
        echo json_encode(['ok' => false, 'message' => 'Password is required.']);
        exit;
    }
    $pwdRes = db_query('SELECT password_hash FROM users WHERE id = ?', 'i', [$user_id]);
    if (!$pwdRes || $pwdRes->num_rows === 0) {
        echo json_encode(['ok' => false, 'message' => 'Unable to verify admin identity.']);
        exit;
    }
    $hash = $pwdRes->fetch_assoc()['password_hash'] ?? '';
    $entered = hash('sha256', $admin_password);
    if ($entered !== $hash) {
        echo json_encode(['ok' => false, 'message' => 'Invalid admin password.']);
        exit;
    }
    echo json_encode(['ok' => true]);
    exit;
}

// Admin's barangay
$admin_barangay_res = db_query('SELECT id, name FROM barangay WHERE admin_user_id = ?', 'i', [$user_id]);
if (!$admin_barangay_res || $admin_barangay_res->num_rows === 0) {
    $_SESSION['alert_type'] = 'warning';
    $_SESSION['alert_message'] = 'You are not assigned as an admin to any barangay.';
    header('Location: ' . WEB_ROOT . '/index.php?nav=admin-dashboard');
    exit;
}
$admin_barangay = $admin_barangay_res->fetch_assoc();
$barangay_id = intval($admin_barangay['id']);
$barangay_name = $admin_barangay['name'];

// Auto-load user modal if requested from verification-management
$auto_user_modal = isset($_GET['auto_user_modal']) ? intval($_GET['auto_user_modal']) : 0;

// Handle role change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_role') {
    $target_user_id = intval($_POST['user_id'] ?? 0);
    $new_role_id = intval($_POST['new_role'] ?? 0);
    $admin_password = trim($_POST['admin_password'] ?? '');
    
    if ($target_user_id && in_array($new_role_id, [3, 4], true)) {
        // Require admin password
        if ($admin_password === '') {
            $_SESSION['alert_type'] = 'danger';
            $_SESSION['alert_message'] = 'Admin password is required to change roles.';
            header('Location: ' . WEB_ROOT . '/index.php?nav=manage-users&filter=' . htmlspecialchars($_GET['filter'] ?? 'all'));
            exit;
        }

        // Verify admin password (schema uses password_hash SHA-256)
        $pwdRes = db_query('SELECT password_hash FROM users WHERE id = ?', 'i', [$user_id]);
        if (!$pwdRes || $pwdRes->num_rows === 0) {
            $_SESSION['alert_type'] = 'danger';
            $_SESSION['alert_message'] = 'Unable to verify admin identity.';
            header('Location: ' . WEB_ROOT . '/index.php?nav=manage-users&filter=' . htmlspecialchars($_GET['filter'] ?? 'all'));
            exit;
        }
        $hash = $pwdRes->fetch_assoc()['password_hash'] ?? '';
        // Compare using SHA-256 to match seeding in database.sql
        $entered = hash('sha256', $admin_password);
        if ($entered !== $hash) {
            $_SESSION['alert_type'] = 'danger';
            $_SESSION['alert_message'] = 'Invalid admin password.';
            header('Location: ' . WEB_ROOT . '/index.php?nav=manage-users&filter=' . htmlspecialchars($_GET['filter'] ?? 'all'));
            exit;
        }
        // Verify user exists and belongs to this barangay
        $verify = db_query('SELECT u.id FROM users u LEFT JOIN profile p ON u.id = p.user_id WHERE u.id = ? AND p.barangay_id = ?', 'ii', [$target_user_id, $barangay_id]);
        
        if ($verify && $verify->num_rows > 0) {
            $update = db_query('UPDATE users SET usertype_id = ? WHERE id = ?', 'ii', [$new_role_id, $target_user_id]);
            if ($update) {
                $role_name = $new_role_id === 3 ? 'Staff' : 'Resident';
                $_SESSION['alert_type'] = 'success';
                $_SESSION['alert_message'] = 'User role changed to ' . $role_name . ' successfully.';
            }
        }
    }
    header('Location: ' . WEB_ROOT . '/index.php?nav=manage-users&filter=' . htmlspecialchars($_GET['filter'] ?? 'all'));
    exit;
}

// Filters
$filter = $_GET['filter'] ?? 'all'; // all, user, staff
$q = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'date_desc'; // date_desc, date_asc, name_asc, role_asc
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;
$role_map = ['user'=>4, 'staff'=>3]; // from usertype table (admins excluded)

$where = ' WHERE p.barangay_id = ' . $barangay_id . ' AND u.usertype_id IN (3,4)'; // Only staff (3) and users (4)
if (in_array($filter, ['user','staff'], true)) {
    $where .= ' AND u.usertype_id = ' . $role_map[$filter];
}
if ($q !== '') {
    $safe = addslashes($q);
    $safe = str_replace(['%','_'], ['\\%','\\_'], $safe);
    $where .= " AND (CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) LIKE '%$safe%'"
           .  " OR u.username LIKE '%$safe%' OR p.email LIKE '%$safe%')";
}

// Count total for pagination
$count_query = "SELECT COUNT(*) as total
          FROM users u
          LEFT JOIN profile p ON u.id = p.user_id
          $where";
$count_res = db_query($count_query);
$total_users = $count_res ? $count_res->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_users / $per_page);

// Fetch users
$query = "SELECT u.id as user_id, u.username, u.usertype_id, u.created_at, 
                 p.first_name, p.middle_name, p.last_name, p.email, p.contact_number, p.birthdate,
                 uv.id as verification_id, uv.verification_status_id, uv.verified_at, uv.remarks, vs.name as status_name, ut.name as usertype_name
          FROM users u
          LEFT JOIN profile p ON u.id = p.user_id
          LEFT JOIN user_verification uv ON u.id = uv.user_id
          LEFT JOIN verification_status vs ON uv.verification_status_id = vs.id
          LEFT JOIN usertype ut ON u.usertype_id = ut.id
          $where
          ORDER BY ";

// Apply sorting
switch ($sort) {
    case 'date_asc': $query .= "u.created_at ASC"; break;
    case 'name_asc': $query .= "CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) ASC"; break;
    case 'role_asc': $query .= "u.usertype_id ASC, u.created_at DESC"; break;
    case 'date_desc':
    default: $query .= "u.created_at DESC"; break;
}

$query .= " LIMIT $per_page OFFSET $offset";

$res = db_query($query);
$users = [];
if ($res) {
    while ($row = $res->fetch_assoc()) $users[] = $row;
}
$showing_users = count($users);

require_once __DIR__ . '/../public/header.php';
?>

<div class="container my-5">
    <?php if (!empty($_SESSION['alert_message'])): ?>
        <div class="alert alert-<?php echo htmlspecialchars($_SESSION['alert_type']); ?> alert-dismissible fade show mb-4" role="alert">
            <?php if ($_SESSION['alert_type'] === 'success'): ?>
                <i class="fas fa-check-circle me-2"></i>
            <?php else: ?>
                <i class="fas fa-exclamation-circle me-2"></i>
            <?php endif; ?>
            <?php echo htmlspecialchars($_SESSION['alert_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['alert_message']); unset($_SESSION['alert_type']); ?>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="mb-2"><i class="fas fa-users me-2"></i>Manage Users</h2>
            <p class="text-muted mb-0">Residents of <strong><?php echo htmlspecialchars($barangay_name); ?></strong></p>
            <small class="text-muted">Showing <?php echo $showing_users; ?> of <?php echo $total_users; ?> user<?php echo $total_users === 1 ? '' : 's'; ?></small>
        </div>
        <div class="col-md-4">
            <form class="input-group" method="get" action="<?php echo WEB_ROOT; ?>/index.php">
                <input type="hidden" name="nav" value="manage-users">
                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search name, @username, or email...">
                <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <div>
                <?php $base = WEB_ROOT . '/index.php?nav=manage-users&q=' . urlencode($q) . '&sort=' . htmlspecialchars($sort); ?>
                <ul class="nav nav-pills card-header-pills mb-0">
                    <li class="nav-item"><a class="nav-link <?php echo $filter==='all'?'active':''; ?>" href="<?php echo $base; ?>&filter=all">All Users</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $filter==='user'?'active':''; ?>" href="<?php echo $base; ?>&filter=user">Residents</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $filter==='staff'?'active':''; ?>" href="<?php echo $base; ?>&filter=staff">Staff</a></li>
                </ul>
            </div>
            <div class="ms-3">
                <form class="d-flex align-items-center" method="get" action="<?php echo WEB_ROOT; ?>/index.php" style="gap: 8px;">
                    <input type="hidden" name="nav" value="manage-users">
                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($q); ?>">
                    <label for="sortBy" class="form-label mb-0" style="font-size: 0.9rem; font-weight: 500;">Sort:</label>
                    <select id="sortBy" name="sort" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                        <option value="date_desc" <?php echo $sort === 'date_desc' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="date_asc" <?php echo $sort === 'date_asc' ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                        <option value="role_asc" <?php echo $sort === 'role_asc' ? 'selected' : ''; ?>>Role (Staff First)</option>
                    </select>
                </form>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($users)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted mb-0">No users found for this filter.</p>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($users as $u): ?>
                        <div class="list-group-item list-group-item-action">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center">
                                        <?php 
                                            $uid = intval($u['user_id'] ?? 0);
                                            $picDir = __DIR__ . '/../storage/app/private/profile_pics/';
                                            $picWeb = WEB_ROOT . '/storage/app/private/profile_pics/';
                                            $picName = 'user_' . $uid . '.jpg';
                                            $picPath = $picDir . $picName;
                                        ?>
                                            <?php if ($uid && file_exists($picPath)): ?>
                                                <img src="<?php echo $picWeb . $picName; ?>" alt="Profile" class="rounded-circle me-3" style="width:50px;height:50px;object-fit:cover;">
                                        <?php else: ?>
                                                <div class="avatar-circle bg-primary text-white me-3" style="width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 18px;">
                                                <?php echo strtoupper(substr($u['first_name'] ?? 'U', 0, 1) . substr($u['last_name'] ?? 'N', 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars(($u['first_name'] ?? '') . ' ' . ($u['middle_name'] ?? '') . ' ' . ($u['last_name'] ?? '')); ?></h6>
                                                <small class="text-muted">
                                                    <i class="fas fa-user me-1"></i>@<?php echo htmlspecialchars($u['username']); ?>
                                                    <span class="mx-2">•</span>
                                                    <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($u['email'] ?? 'N/A'); ?>
                                                </small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted d-block">Account Type</small>
                                        <small class="fw-semibold"><?php
                                            $utId = intval($u['usertype_id'] ?? 0);
                                            $typeLabel = ($utId === 4) ? 'Resident' : (($utId === 3) ? 'Staff' : 'Unknown');
                                            echo htmlspecialchars($typeLabel);
                                        ?></small>
                                </div>
                                <div class="col-md-2 text-center">
                                    <?php 
                                    $ut = intval($u['usertype_id'] ?? 0);
                                    // Only show verification status for regular users (usertype_id 4)
                                    if ($ut === 4):
                                        $vid = intval($u['verification_status_id'] ?? 0);
                                        $badge = $vid===2?'success':($vid===1?'warning text-dark':($vid===3?'danger':'secondary'));
                                        $label = $u['status_name'] ?? 'not submitted';
                                    ?>
                                        <span class="badge bg-<?php echo $badge; ?> fs-6"><?php echo htmlspecialchars($label); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary fs-6">N/A</span>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-1 text-end">
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick='loadUserData(<?php echo json_encode($u); ?>)'>
                                        <i class="fas fa-eye me-1"></i>View
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
                <nav aria-label="Users pagination">
                    <ul class="pagination pagination-sm mb-0 justify-content-center">
                        <?php 
                        $base_url = WEB_ROOT . '/index.php?nav=manage-users&filter=' . urlencode($filter) . '&q=' . urlencode($q) . '&sort=' . urlencode($sort);
                        
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

<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center w-100">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width:44px;height:44px;">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="modal-title mb-0">User Profile</h5>
                        <small class="text-muted">View and manage user account information</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body pt-3">
                <!-- Profile Picture Section -->
                <div class="text-center mb-4 pb-3 border-bottom">
                    <div id="usrPicContainer" style="display:none;">
                        <img id="usrPic" src="" alt="Profile" class="rounded-circle mb-3 shadow" style="width:100px;height:100px;object-fit:cover;">
                    </div>
                    <div id="usrInitials" class="mx-auto mb-3 shadow-sm" style="width:100px;height:100px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:32px;color:white;background-color:#007bff;"></div>
                    <h4 id="usrName" class="mb-1 fw-bold"></h4>
                    <p class="text-muted mb-0"><i class="fas fa-at me-1"></i><span id="usrUsername"></span></p>
                </div>

                <!-- User Information Section -->
                <h6 class="mb-3"><i class="fas fa-info-circle me-2 text-primary"></i>Account Information</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded">
                            <small class="text-muted d-block mb-1 fw-600"><i class="fas fa-envelope me-1"></i>Email</small>
                            <div class="fw-semibold" id="usrEmail"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded">
                            <small class="text-muted d-block mb-1 fw-600"><i class="fas fa-phone me-1"></i>Contact Number</small>
                            <div class="fw-semibold" id="usrContact"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded">
                            <small class="text-muted d-block mb-1 fw-600"><i class="fas fa-birthday-cake me-1"></i>Birthdate</small>
                            <div class="fw-semibold" id="usrBirth"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded">
                            <small class="text-muted d-block mb-1 fw-600"><i class="fas fa-user-tag me-1"></i>Account Type</small>
                            <div class="fw-semibold" id="usrRole"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded">
                            <small class="text-muted d-block mb-1 fw-600"><i class="fas fa-calendar-plus me-1"></i>Member Since</small>
                            <div class="fw-semibold" id="usrCreated"></div>
                        </div>
                    </div>
                </div>

                <!-- Verification Section (Residents Only) -->
                <div id="usrVerificationSection" style="display:none;" class="mb-4">
                    <h6 class="mb-3"><i class="fas fa-id-card me-2 text-info"></i>ID Verification Status</h6>
                    <div class="p-3 bg-light rounded mb-3">
                        <div class="row g-3">
                            <div class="col-12">
                                <small class="text-muted d-block mb-2 fw-600"><i class="fas fa-check-circle me-1"></i>Verification Status</small>
                                <div id="usrStatus" class="mb-0"></div>
                            </div>
                            <div id="usrRemarksRow" style="display:none;" class="col-12">
                                <small class="text-muted d-block mb-2 fw-600"><i class="fas fa-comment-alt me-1"></i>Admin Remarks</small>
                                <div class="alert alert-danger mb-0 py-2" id="usrRemarks"></div>
                            </div>
                        </div>
                    </div>
                    <a href="#" id="viewVerificationBtn" class="btn btn-info w-100">
                        <i class="fas fa-external-link-alt me-1"></i> View Verification Ticket
                    </a>
                </div>

                <!-- Role Management Section -->
                <h6 class="mb-3"><i class="fas fa-shield-alt me-2 text-warning"></i>Account Management</h6>
                <div class="mb-3">
                    <label for="adminPwdField" class="form-label">Admin Password</label>
                    <input type="password" id="adminPwdField" class="form-control" placeholder="Enter your password to authorize changes" aria-describedby="adminPwdHelp adminPwdError">
                    <div id="adminPwdHelp" class="form-text">Required to promote/demote users.</div>
                    <div id="adminPwdError" class="invalid-feedback"></div>
                </div>
                <div id="roleActionsContainer" class="d-flex gap-2">
                    <!-- Buttons will be inserted here -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-load user modal if requested from verification-management
const autoUserId = <?php echo $auto_user_modal; ?>;
if (autoUserId > 0) {
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            // Find and load the user data
            const allUsers = <?php echo json_encode($users); ?>;
            const user = allUsers.find(u => u.user_id == autoUserId);
            if (user && typeof bootstrap !== 'undefined') {
                const modalElement = document.getElementById('userModal');
                if (modalElement) {
                    loadUserData(user);
                    const userModal = new bootstrap.Modal(modalElement, {
                        backdrop: 'static',
                        keyboard: true
                    });
                    userModal.show();
                }
            }
        }, 300);
    });
}

function loadUserData(u){
    const full = [u.first_name||'', u.middle_name||'', u.last_name||''].join(' ').replace(/\s+/g,' ').trim();
    document.getElementById('usrName').textContent = full || 'Unknown';
    document.getElementById('usrUsername').textContent = u.username || '';
    document.getElementById('usrEmail').textContent = u.email || 'N/A';
    document.getElementById('usrContact').textContent = u.contact_number || 'N/A';
    document.getElementById('usrBirth').textContent = u.birthdate ? new Date(u.birthdate).toLocaleDateString('en-US', {year: 'numeric', month: 'long', day: 'numeric'}) : 'N/A';
    // Normalize role label: 4 -> Resident, 3 -> Staff
    const roleLabel = (parseInt(u.usertype_id || 0, 10) === 4) ? 'Resident' : ((parseInt(u.usertype_id || 0, 10) === 3) ? 'Staff' : 'Unknown');
    document.getElementById('usrRole').textContent = roleLabel;
    document.getElementById('usrCreated').textContent = u.created_at ? new Date(u.created_at).toLocaleDateString('en-US', {year: 'numeric', month: 'long', day: 'numeric'}) : 'Unknown';
    
    // Profile picture
    const uid = parseInt(u.user_id||0, 10);
    const picPath = '<?php echo WEB_ROOT; ?>/storage/app/private/profile_pics/user_' + uid + '.jpg';
    fetch(picPath, {method: 'HEAD'}).then(r => {
        if (r.ok) {
            document.getElementById('usrPicContainer').style.display = 'block';
            document.getElementById('usrInitials').style.display = 'none';
            document.getElementById('usrPic').src = picPath;
        } else {
            document.getElementById('usrPicContainer').style.display = 'none';
            document.getElementById('usrInitials').style.display = 'flex';
            document.getElementById('usrInitials').textContent = (u.first_name ? u.first_name[0] : 'U') + (u.last_name ? u.last_name[0] : 'N');
        }
    }).catch(e => {
        document.getElementById('usrPicContainer').style.display = 'none';
        document.getElementById('usrInitials').style.display = 'flex';
        document.getElementById('usrInitials').textContent = (u.first_name ? u.first_name[0] : 'U') + (u.last_name ? u.last_name[0] : 'N');
    });
    
    // Only show verification status for regular users (usertype_id 4)
    const ut = parseInt(u.usertype_id || 0, 10);
    const verSection = document.getElementById('usrVerificationSection');
    
    if (ut === 4) {
        verSection.style.display = 'block';
        const vid = parseInt(u.verification_status_id||0,10);
        let badge='secondary', label=u.status_name||'not submitted';
        if (vid===1) { badge='warning text-dark'; label='pending'; }
        else if (vid===2) { badge='success'; label='verified'; }
        else if (vid===3) { badge='danger'; label='rejected'; }
        document.getElementById('usrStatus').innerHTML = '<span class="badge bg-'+badge+' fs-6">'+label+'</span>';
        if (vid===3 && u.remarks) {
            document.getElementById('usrRemarksRow').style.display='block';
            document.getElementById('usrRemarks').textContent = u.remarks;
        } else {
            document.getElementById('usrRemarksRow').style.display='none';
        }
        // Only show verification redirect button if user has submitted verification
        const verBtn = document.getElementById('viewVerificationBtn');
        const verIdNum = parseInt(u.verification_id || 0, 10);
        if (verIdNum > 0) {
            verBtn.style.display = 'block';
            verBtn.href = '<?php echo WEB_ROOT; ?>/index.php?nav=manage-verifications&auto_review_modal=' + verIdNum;
        } else {
            verBtn.style.display = 'none';
        }
    } else {
        verSection.style.display = 'none';
    }
    
    // Role management actions
    populateRoleActions(u, ut);
}

function populateRoleActions(u, ut) {
    const container = document.getElementById('roleActionsContainer');
    container.innerHTML = '';
    
    if (ut === 4) { // Resident
        const btn = document.createElement('button');
        btn.className = 'btn btn-warning w-100';
        btn.innerHTML = '<i class="fas fa-arrow-up me-1"></i> Promote to Staff';
        btn.onclick = () => confirmRoleChange(u.user_id, 3, 'Promote to Staff');
        container.appendChild(btn);
    } else if (ut === 3) { // Staff
        const btn = document.createElement('button');
        btn.className = 'btn btn-info w-100';
        btn.innerHTML = '<i class="fas fa-arrow-down me-1"></i> Demote to Resident';
        btn.onclick = () => confirmRoleChange(u.user_id, 4, 'Demote to Resident');
        container.appendChild(btn);
    }
}

async function confirmRoleChange(userId, newRoleId, actionName) {
    // First: check password field is filled and correct via AJAX
    const pwdField = document.getElementById('adminPwdField');
    const pwd = pwdField ? pwdField.value.trim() : '';
    if (!pwd) {
        if (pwdField) {
            pwdField.classList.add('is-invalid');
            const err = document.getElementById('adminPwdError');
            if (err) err.textContent = 'Password is required to perform this action.';
            pwdField.focus();
        }
        return;
    }

    try {
        const resp = await fetch('<?php echo WEB_ROOT; ?>/index.php?nav=manage-users', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'verify_admin_password', admin_password: pwd })
        });
        const data = await resp.json();
        if (!data.ok) {
            if (pwdField) {
                pwdField.classList.add('is-invalid');
                const err = document.getElementById('adminPwdError');
                if (err) err.textContent = data.message || 'Invalid admin password.';
                pwdField.focus();
            }
            return;
        }
        // Clear error state on success
        if (pwdField) {
            pwdField.classList.remove('is-invalid');
            const err = document.getElementById('adminPwdError');
            if (err) err.textContent = '';
        }
    } catch (e) {
        alert('Unable to verify password. Please try again.');
        return;
    }

    // Second: confirm the action
    if (!confirm('Are you sure you want to ' + actionName.toLowerCase() + ' this user?')) return;

    // Submit role change
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?php echo WEB_ROOT; ?>/index.php?nav=manage-users';
    
    const userIdInput = document.createElement('input');
    userIdInput.type = 'hidden';
    userIdInput.name = 'user_id';
    userIdInput.value = userId;
    
    const roleInput = document.createElement('input');
    roleInput.type = 'hidden';
    roleInput.name = 'new_role';
    roleInput.value = newRoleId;
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'change_role';

    const pwdInput = document.createElement('input');
    pwdInput.type = 'hidden';
    pwdInput.name = 'admin_password';
    pwdInput.value = pwd;
    
    form.appendChild(userIdInput);
    form.appendChild(roleInput);
    form.appendChild(actionInput);
    form.appendChild(pwdInput);
    document.body.appendChild(form);
    form.submit();
}
</script>

<style>
.fw-600 {
    font-weight: 600;
}
</style>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
