<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$role = intval($_SESSION['role'] ?? 0);
$user_id = intval($_SESSION['user_id'] ?? 0);

// Check if user is verified (only matters for regular users)
$is_verified = true;
$barangay_info = ['barangay_name' => '', 'city_name' => '', 'province_name' => ''];
if ($role === ROLE_USER && $user_id > 0) {
    require_once __DIR__ . '/../config.php';
    $is_verified = is_user_verified($user_id);
} elseif (($role === ROLE_ADMIN || $role === ROLE_STAFF) && $user_id > 0) {
    require_once __DIR__ . '/../config.php';
}

// Get user's location info (barangay, municipality, province)
if ($user_id > 0) {
    require_once __DIR__ . '/../config.php';
    $loc_res = db_query('SELECT b.name as barangay_name, c.name as city_name, p.name as province_name 
                        FROM profile pr 
                        LEFT JOIN barangay b ON pr.barangay_id = b.id 
                        LEFT JOIN city c ON b.city_id = c.id 
                        LEFT JOIN province p ON c.province_id = p.id 
                        WHERE pr.user_id = ?', 'i', [$user_id]);
    if ($loc_res && ($loc_row = $loc_res->fetch_assoc())) {
        $barangay_info = [
            'barangay_name' => $loc_row['barangay_name'] ?? '',
            'city_name' => $loc_row['city_name'] ?? '',
            'province_name' => $loc_row['province_name'] ?? ''
        ];
    }
}

// Get profile picture
$profilePicDir = __DIR__ . '/../storage/app/private/profile_pics/';
$profilePicWeb = (defined('WEB_ROOT') ? WEB_ROOT : '') . '/storage/app/private/profile_pics/';
$profilePicName = 'user_' . $user_id . '.jpg';
$profilePicPath = $profilePicDir . $profilePicName;
$profilePicUrl = file_exists($profilePicPath) ? $profilePicWeb . $profilePicName : (defined('WEB_ROOT') ? WEB_ROOT : '') . '/public/assets/img/default-avatar.png';
?>

<!-- Primary bar: brand + auth/profile -->
<nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, rgba(255,255,255,1) 0%, rgba(255,255,255,1) 45%, #0b3d91 27%, #0b3d91 100%);">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center fw-semibold" href="<?php echo WEB_ROOT; ?>/index.php" style="color: #0b3d91;">
            <img src="<?php echo WEB_ROOT; ?>/public/assets/img/Barangay-Konek-Logo-Only.png" alt="Barangay Konek" style="height:42px;" class="me-2">
            <span style="text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Barangay Konek</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarTop">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarTop">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <?php if (!empty($_SESSION['user_id'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white d-flex align-items-center" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="<?php echo $profilePicUrl; ?>" alt="Profile" class="rounded-circle me-2" style="width:32px; height:32px; object-fit:cover; border:2px solid #fff;">
                            <span><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                            <li><a class="dropdown-item" href="<?php echo WEB_ROOT; ?>/index.php?nav=profile"><i class="fas fa-id-badge me-2"></i>Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo WEB_ROOT; ?>/index.php?nav=logout"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item me-2">
                        <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#loginModal"><i class="fas fa-sign-in-alt me-1"></i> Sign in</button>
                    </li>
                    <li class="nav-item">
                        <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#registerModal"><i class="fas fa-user-plus me-1"></i> Register</button>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<?php if (!empty($_SESSION['user_id'])): ?>
<!-- Secondary bar: role-based navigation -->
<nav class="navbar navbar-expand-lg navbar-light border-bottom py-1" style="background:#f2f5fa; font-size: 0.9rem;">
    <div class="container-fluid">
        <button class="navbar-toggler navbar-toggler-sm" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSecondary">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSecondary">
            <ul class="navbar-nav me-auto">
                <?php if ($role === ROLE_USER): ?>
                    <li class="nav-item"><a class="nav-link py-2" href="<?php echo WEB_ROOT; ?>/index.php?nav=user-dashboard">Dashboard</a></li>
                    <li class="nav-item">
                        <a class="nav-link py-2 <?php echo !$is_verified ? 'disabled' : ''; ?>" href="<?php echo $is_verified ? WEB_ROOT . '/index.php?nav=manage-requests' : '#'; ?>" <?php echo !$is_verified ? 'style="cursor: not-allowed; opacity: 0.6;" title="Verify your account to access requests"' : ''; ?>>Requests</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-2 <?php echo !$is_verified ? 'disabled' : ''; ?>" href="<?php echo $is_verified ? WEB_ROOT . '/index.php?nav=manage-complaints' : '#'; ?>" <?php echo !$is_verified ? 'style="cursor: not-allowed; opacity: 0.6;" title="Verify your account to access complaints"' : ''; ?>>Complaints</a>
                    </li>
                    <li class="nav-item"><a class="nav-link py-2" href="<?php echo WEB_ROOT; ?>/index.php?nav=announcements">Announcements</a></li>
                <?php elseif ($role === ROLE_STAFF): ?>
                    <li class="nav-item"><a class="nav-link py-2" href="<?php echo WEB_ROOT; ?>/index.php?nav=staff-dashboard">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link py-2" href="<?php echo WEB_ROOT; ?>/index.php?nav=manage-users">Users</a></li>
                    <li class="nav-item"><a class="nav-link py-2" href="<?php echo WEB_ROOT; ?>/index.php?nav=manage-requests">Requests</a></li>
                    <li class="nav-item"><a class="nav-link py-2" href="<?php echo WEB_ROOT; ?>/index.php?nav=manage-complaints">Complaints</a></li>
                    <li class="nav-item"><a class="nav-link py-2" href="<?php echo WEB_ROOT; ?>/index.php?nav=announcements">Announcements</a></li>
                <?php elseif ($role === ROLE_ADMIN): ?>
                    <li class="nav-item"><a class="nav-link py-2" href="<?php echo WEB_ROOT; ?>/index.php?nav=admin-dashboard">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link py-2" href="<?php echo WEB_ROOT; ?>/index.php?nav=manage-users">Users</a></li>
                    <li class="nav-item"><a class="nav-link py-2" href="<?php echo WEB_ROOT; ?>/index.php?nav=manage-verifications">Verifications</a></li>
                    <li class="nav-item"><a class="nav-link py-2" href="<?php echo WEB_ROOT; ?>/index.php?nav=manage-requests">Requests</a></li>
                    <li class="nav-item"><a class="nav-link py-2" href="<?php echo WEB_ROOT; ?>/index.php?nav=manage-complaints">Complaints</a></li>
                    <li class="nav-item"><a class="nav-link py-2" href="<?php echo WEB_ROOT; ?>/index.php?nav=announcements">Announcements</a></li>
                <?php elseif ($role === ROLE_SUPERADMIN): ?>
                    <li class="nav-item"><a class="nav-link py-2" href="<?php echo WEB_ROOT; ?>/index.php?nav=superadmin-dashboard">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link py-2" href="<?php echo WEB_ROOT; ?>/index.php?nav=admin-management">Admin Management</a></li>
                    <li class="nav-item"><a class="nav-link py-2" href="<?php echo WEB_ROOT; ?>/index.php?nav=barangay-overview">Barangay Overview</a></li>
                    <li class="nav-item"><a class="nav-link py-2" href="<?php echo WEB_ROOT; ?>/index.php?nav=activity-logs">Activity Logs</a></li>
                <?php endif; ?>
            </ul>
            <?php if (!empty($barangay_info['barangay_name'])): ?>
            <div class="navbar-text ms-auto text-muted small" style="font-size: 0.85rem;">
                <i class="fas fa-map-marker-alt me-1"></i>
                <span class="fw-600"><?php echo htmlspecialchars($barangay_info['barangay_name']); ?></span>
                <?php if (!empty($barangay_info['city_name'])): ?>
                    <span class="text-muted"> • <?php echo htmlspecialchars($barangay_info['city_name']); ?></span>
                <?php endif; ?>
                <?php if (!empty($barangay_info['province_name'])): ?>
                    <span class="text-muted">, <?php echo htmlspecialchars($barangay_info['province_name']); ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</nav>
<?php endif; ?>

<!-- Login Modal -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Login</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="loginForm">
                <div class="modal-body">
                    <div id="loginAlert"></div>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <input type="hidden" name="action" value="login">
                    <div class="text-end small text-muted">
                        Need an account? <a href="#" data-switch-target="registerModal">Register here</a>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="small text-muted">
                        <a href="index.php?nav=terms-of-service" target="_blank" class="text-muted text-decoration-none">Terms of Service</a> | 
                        <a href="index.php?nav=privacy-policy" target="_blank" class="text-muted text-decoration-none">Privacy Policy</a>
                    </div>
                    <div class="ms-auto">
                        <button type="button" class="btn btn-secondary-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Login</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Register Modal -->
<div class="modal fade" id="registerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Register</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="registerForm">
                <div class="modal-body">
                    <div id="registerAlert"></div>
                    
                    <h6 class="mb-3">Personal Information</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="first_name" placeholder="Juan" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Middle Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="middle_name" placeholder="Cruz" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="last_name" placeholder="Dela Cruz" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Suffix</label>
                            <input type="text" class="form-control" name="suffix" placeholder="Jr., Sr., III">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Sex <span class="text-danger">*</span></label>
                            <select name="sex_id" class="form-select" required>
                                <option value="">-- Select --</option>
                                <option value="1">Male</option>
                                <option value="2">Female</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Birthdate <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="birthdate" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="text" class="form-control" name="contact_number" placeholder="09XX-XXX-XXXX">
                        </div>
                    </div>
                    <hr>
                    <h6 class="mb-3 mt-4">Barangay Location</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Province <span class="text-danger">*</span></label>
                            <select name="province_id" id="provinceSelect" class="form-select" required>
                                <option value="">-- Select Province --</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">City <span class="text-danger">*</span></label>
                            <select name="city_id" id="citySelect" class="form-select" required>
                                <option value="">-- Select City --</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Barangay <span class="text-danger">*</span></label>
                            <select name="barangay_id" id="barangaySelect" class="form-select" required>
                                <option value="">-- Select Barangay --</option>
                            </select>
                        </div>
                    </div>

                    <div class="alert alert-info small mb-3">
                        <i class="bi bi-info-circle me-1"></i><strong>Note:</strong> If your barangay is not in the list, your barangay is not yet using this service.
                    </div>
                    <hr>
                    <h6 class="mb-3 mt-4">Account Credentials</h6>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Username (for login) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="registerPassword" name="password" required>
                                <button class="btn btn-secondary" type="button" id="toggleRegisterPassword">
                                    <i class="fas fa-eye" id="registerPasswordIcon"></i>
                                </button>
                            </div>
                            <small class="text-muted">Min. 8 characters</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="password_confirm" required>
                        </div>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="termsCheck" required>
                        <label class="form-check-label" for="termsCheck">
                            I agree to the <a href="index.php?nav=terms-of-service" target="_blank">Terms of Service</a> and <a href="index.php?nav=privacy-policy" target="_blank">Privacy Policy</a>
                        </label>
                    </div>

                    <div class="alert alert-info small mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> After registration, you will need to log in and complete the verification process. Your account will be verified by the barangay administrator before you can access all system features.
                    </div>

                    <input type="hidden" name="action" value="register">
                        <div class="text-end small text-muted">
                            Already have an account? <a href="#" data-switch-target="loginModal">Login here</a>
                        </div>
                </div>
                <div class="modal-footer">
                    <div class="small text-muted">
                        <a href="index.php?nav=terms-of-service" target="_blank" class="text-muted text-decoration-none">Terms of Service</a> | 
                        <a href="index.php?nav=privacy-policy" target="_blank" class="text-muted text-decoration-none">Privacy Policy</a>
                    </div>
                    <div class="ms-auto">
                        <button type="button" class="btn btn-secondary-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Register</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
