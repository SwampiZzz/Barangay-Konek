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

// Filters
$filter = $_GET['filter'] ?? 'all'; // all, user, staff, admin
$q = trim($_GET['q'] ?? '');
$role_map = ['user'=>4, 'staff'=>3, 'admin'=>2]; // from usertype table

$where = ' WHERE p.barangay_id = ' . $barangay_id . ' AND u.usertype_id != 1'; // Exclude super admin (usertype_id=1)
if (in_array($filter, ['user','staff','admin'], true)) {
    $where .= ' AND u.usertype_id = ' . $role_map[$filter];
}
if ($q !== '') {
    $safe = addslashes($q);
    $safe = str_replace(['%','_'], ['\\%','\\_'], $safe);
    $where .= " AND (CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) LIKE '%$safe%'"
           .  " OR u.username LIKE '%$safe%' OR p.email LIKE '%$safe%')";
}

// Fetch users
$query = "SELECT u.id as user_id, u.username, u.usertype_id, u.created_at, 
                 p.first_name, p.middle_name, p.last_name, p.email, p.contact_number, p.birthdate,
                 uv.verification_status_id, uv.verified_at, uv.remarks, vs.name as status_name, ut.name as usertype_name
          FROM users u
          LEFT JOIN profile p ON u.id = p.user_id
          LEFT JOIN user_verification uv ON u.id = uv.user_id
          LEFT JOIN verification_status vs ON uv.verification_status_id = vs.id
          LEFT JOIN usertype ut ON u.usertype_id = ut.id
          $where
          ORDER BY u.created_at DESC";

$res = db_query($query);
$users = [];
if ($res) {
    while ($row = $res->fetch_assoc()) $users[] = $row;
}

require_once __DIR__ . '/../public/header.php';
?>

<div class="container my-5">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="mb-2"><i class="fas fa-users me-2"></i>Manage Users</h2>
            <p class="text-muted mb-0">Residents of <strong><?php echo htmlspecialchars($barangay_name); ?></strong></p>
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
        <div class="card-header bg-light">
            <?php $base = WEB_ROOT . '/index.php?nav=manage-users&q=' . urlencode($q); ?>
            <ul class="nav nav-pills card-header-pills mb-0">
                <li class="nav-item"><a class="nav-link <?php echo $filter==='all'?'active':''; ?>" href="<?php echo $base; ?>&filter=all">All Users</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $filter==='user'?'active':''; ?>" href="<?php echo $base; ?>&filter=user">Residents</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $filter==='staff'?'active':''; ?>" href="<?php echo $base; ?>&filter=staff">Staff</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $filter==='admin'?'active':''; ?>" href="<?php echo $base; ?>&filter=admin">Admins</a></li>
            </ul>
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
                                            <img src="<?php echo $picWeb . $picName; ?>" alt="Profile" class="rounded-circle me-3" style="width:46px;height:46px;object-fit:cover;">
                                        <?php else: ?>
                                            <div class="avatar-circle bg-primary text-white me-3" style="width: 46px; height: 46px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 16px;">
                                                <?php echo strtoupper(substr($u['first_name'] ?? 'U', 0, 1) . substr($u['last_name'] ?? 'N', 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars(($u['first_name'] ?? '') . ' ' . ($u['middle_name'] ?? '') . ' ' . ($u['last_name'] ?? '')); ?></h6>
                                            <small class="text-muted"><i class="fas fa-user me-1"></i>@<?php echo htmlspecialchars($u['username']); ?></small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted d-block">Account Type</small>
                                    <small class="text-muted fw-semibold"><?php echo htmlspecialchars(ucfirst($u['usertype_name'] ?? 'Unknown')); ?></small>
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
                                        <span class="badge bg-info fs-6">N/A</span>
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
    </div>
</div>

<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user me-2"></i>User Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <small class="text-muted d-block mb-1">Full Name</small>
                        <div class="fw-semibold" id="usrName"></div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block mb-1">Username</small>
                        <div class="fw-semibold" id="usrUsername"></div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block mb-1">Email</small>
                        <div class="fw-semibold" id="usrEmail"></div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block mb-1">Contact</small>
                        <div class="fw-semibold" id="usrContact"></div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block mb-1">Birthdate</small>
                        <div class="fw-semibold" id="usrBirth"></div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block mb-1">Account Type</small>
                        <div class="fw-semibold" id="usrRole"></div>
                    </div>
                    <div class="col-12" id="usrVerificationSection" style="display:none;">
                        <hr>
                        <small class="text-muted d-block mb-1">Verification Status</small>
                        <div id="usrStatus" class="mb-2"></div>
                        <div id="usrRemarksRow" style="display:none;">
                            <small class="text-muted d-block mb-1">Remarks</small>
                            <div class="alert alert-danger mb-0" id="usrRemarks"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function loadUserData(u){
    const full = [u.first_name||'', u.middle_name||'', u.last_name||''].join(' ').replace(/\s+/g,' ').trim();
    document.getElementById('usrName').textContent = full || 'Unknown';
    document.getElementById('usrUsername').textContent = '@' + (u.username||'');
    document.getElementById('usrEmail').textContent = u.email || 'N/A';
    document.getElementById('usrContact').textContent = u.contact_number || 'N/A';
    document.getElementById('usrBirth').textContent = u.birthdate ? new Date(u.birthdate).toLocaleDateString() : 'N/A';
    document.getElementById('usrRole').textContent = u.usertype_name ? u.usertype_name.charAt(0).toUpperCase() + u.usertype_name.slice(1) : 'Unknown';
    
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
    } else {
        verSection.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
