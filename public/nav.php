<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$role = intval($_SESSION['role'] ?? 0);
$user_id = intval($_SESSION['user_id'] ?? 0);

// Get profile picture
$profilePicDir = __DIR__ . '/../storage/app/private/profile_pics/';
$profilePicWeb = (defined('WEB_ROOT') ? WEB_ROOT : '') . '/storage/app/private/profile_pics/';
$profilePicName = 'user_' . $user_id . '.jpg';
$profilePicPath = $profilePicDir . $profilePicName;
$profilePicUrl = file_exists($profilePicPath) ? $profilePicWeb . $profilePicName : (defined('WEB_ROOT') ? WEB_ROOT : '') . '/public/assets/img/default-avatar.png';
?>

<!-- Primary bar: brand + auth/profile -->
<nav class="navbar navbar-expand-lg navbar-dark" style="background:#0b3d91;">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center fw-semibold text-white" href="<?php echo WEB_ROOT; ?>/index.php">
            <img src="<?php echo WEB_ROOT; ?>/public/assets/img/Barangay-Konek-Logo-Only.png" alt="Barangay Konek" style="height:32px;" class="me-2">
            <span>Barangay Konek</span>
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
                        <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#loginModal"><i class="fas fa-sign-in-alt me-1"></i> Login</button>
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
<nav class="navbar navbar-expand-lg navbar-light border-bottom" style="background:#f2f5fa;">
    <div class="container-fluid">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSecondary">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSecondary">
            <ul class="navbar-nav me-auto">
                <?php if ($role === ROLE_USER): ?>
                    <li class="nav-item"><a class="nav-link" href="<?php echo WEB_ROOT; ?>/index.php?nav=user-dashboard">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo WEB_ROOT; ?>/index.php?nav=request-list">Requests</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo WEB_ROOT; ?>/index.php?nav=complaint-list">Complaints</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo WEB_ROOT; ?>/index.php?nav=announcements">Announcements</a></li>
                <?php elseif ($role === ROLE_STAFF): ?>
                    <li class="nav-item"><a class="nav-link" href="<?php echo WEB_ROOT; ?>/index.php?nav=staff-dashboard">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo WEB_ROOT; ?>/index.php?nav=manage-requests">Manage Requests</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo WEB_ROOT; ?>/index.php?nav=manage-complaints">Manage Complaints</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo WEB_ROOT; ?>/index.php?nav=announcements">Announcements</a></li>
                <?php elseif ($role === ROLE_ADMIN): ?>
                    <li class="nav-item"><a class="nav-link" href="<?php echo WEB_ROOT; ?>/index.php?nav=admin-dashboard">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo WEB_ROOT; ?>/index.php?nav=manage-requests">Manage Requests</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo WEB_ROOT; ?>/index.php?nav=manage-complaints">Manage Complaints</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo WEB_ROOT; ?>/index.php?nav=announcements">Announcements</a></li>
                <?php elseif ($role === ROLE_SUPERADMIN): ?>
                    <li class="nav-item"><a class="nav-link" href="<?php echo WEB_ROOT; ?>/index.php?nav=superadmin-dashboard">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo WEB_ROOT; ?>/index.php?nav=admin-management">Admin Management</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo WEB_ROOT; ?>/index.php?nav=barangay-overview">Barangay Overview</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo WEB_ROOT; ?>/index.php?nav=activity-logs">Activity Logs</a></li>
                <?php endif; ?>
            </ul>
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
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Login</button>
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
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Suffix</label>
                            <input type="text" class="form-control" name="suffix">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Sex</label>
                            <select name="sex_id" class="form-select">
                                <option value="1">Male</option>
                                <option value="2">Female</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Birthdate</label>
                            <input type="date" class="form-control" name="birthdate">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="text" class="form-control" name="contact_number">
                        </div>
                    </div>

                    <hr>
                    <h6>Account Credentials</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" name="password_confirm" required>
                        </div>
                    </div>

                    <input type="hidden" name="action" value="register">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Register</button>
                </div>
            </form>
        </div>
    </div>
</div>
