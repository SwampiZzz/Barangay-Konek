
<?php
require_once __DIR__ . '/../config.php';
require_login();
$pageTitle = 'My Profile';
$user_id = current_user_id();
$profile = get_user_profile($user_id);
$role = current_user_role();
$username = $_SESSION['username'] ?? '';

// Handle profile picture upload
$profilePicDir = __DIR__ . '/../storage/app/private/profile_pics/';
$profilePicWeb = (defined('WEB_ROOT') ? WEB_ROOT : '') . '/storage/app/private/profile_pics/';
$profilePicName = 'user_' . $user_id . '.jpg';
$profilePicPath = $profilePicDir . $profilePicName;
$profilePicUrl = file_exists($profilePicPath) ? $profilePicWeb . $profilePicName : (defined('WEB_ROOT') ? WEB_ROOT : '') . '/public/assets/img/default-avatar.png';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
    $tmp = $_FILES['profile_pic']['tmp_name'];
    $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif'];
    if (in_array($ext, $allowed)) {
        move_uploaded_file($tmp, $profilePicDir . $profilePicName);
        $profilePicUrl = $profilePicWeb . $profilePicName;
    }
}

// Get barangay name if set
$barangay_name = '';
if (!empty($profile['barangay_id'])) {
    $res = db_query('SELECT name FROM barangay WHERE id = ?', 'i', [$profile['barangay_id']]);
    if ($res && $row = $res->fetch_assoc()) $barangay_name = $row['name'];
}

// Get verification status
$verification = null;
$vres = db_query('SELECT v.*, vs.name as status_name FROM user_verification v LEFT JOIN verification_status vs ON v.verification_status_id = vs.id WHERE v.user_id = ?', 'i', [$user_id]);
if ($vres) $verification = $vres->fetch_assoc();

require_once __DIR__ . '/../public/header.php';
?>
<div class="container my-5">
    <h2 class="mb-4"><i class="fas fa-user"></i> My Profile</h2>
    <div class="card shadow-lg">
        <div class="card-body">
            <ul class="nav nav-tabs mb-4" id="profileTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">Profile</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="account-tab" data-bs-toggle="tab" data-bs-target="#account" type="button" role="tab">Account</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="verification-tab" data-bs-toggle="tab" data-bs-target="#verification" type="button" role="tab">Verification</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">Security</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link text-danger" id="delete-tab" data-bs-toggle="tab" data-bs-target="#delete" type="button" role="tab">Delete Account</button>
                </li>
            </ul>
            <div class="tab-content" id="profileTabContent">
                <!-- Profile Tab -->
                <div class="tab-pane fade show active" id="profile" role="tabpanel">
                    <form method="post" action="#" enctype="multipart/form-data" autocomplete="off">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-3 text-center">
                                <div class="mb-2 position-relative">
                                    <label for="profilePicInput" style="cursor:pointer;">
                                        <img src="<?php echo $profilePicUrl; ?>" alt="Profile Picture" class="rounded-circle shadow" style="width: 110px; height: 110px; object-fit: cover; border: 3px solid #e3e6f0;">
                                        <span class="position-absolute top-0 end-0 translate-middle badge rounded-pill bg-secondary" style="font-size:1.1em;">
                                            <i class="fas fa-camera"></i>
                                        </span>
                                    </label>
                                    <input type="file" id="profilePicInput" class="form-control d-none" name="profile_pic" accept="image/*" onchange="this.form.submit()">
                                </div>
                                <div class="form-text">Click image to upload/change</div>
                            </div>
                            <div class="col-md-9">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">First Name</label>
                                        <input type="text" class="form-control" name="first_name" value="<?php echo e($profile['first_name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" class="form-control" name="last_name" value="<?php echo e($profile['last_name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Suffix</label>
                                        <input type="text" class="form-control" name="suffix" value="<?php echo e($profile['suffix'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Sex</label>
                                        <select class="form-select" name="sex_id">
                                            <option value="">Select</option>
                                            <option value="1" <?php if (($profile['sex_id'] ?? null) == 1) echo 'selected'; ?>>Male</option>
                                            <option value="2" <?php if (($profile['sex_id'] ?? null) == 2) echo 'selected'; ?>>Female</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Birthdate</label>
                                        <input type="date" class="form-control" name="birthdate" value="<?php echo e($profile['birthdate'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Barangay</label>
                                        <input type="text" class="form-control" value="<?php echo e($barangay_name); ?>" disabled>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" value="<?php echo e($profile['email'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Contact Number</label>
                                        <input type="text" class="form-control" name="contact_number" value="<?php echo e($profile['contact_number'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 text-end">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
                <!-- Account Tab -->
                <div class="tab-pane fade" id="account" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?php echo e($username); ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <input type="text" class="form-control" value="<?php echo e(ucfirst(array_search($role, [1=>'superadmin',2=>'admin',3=>'staff',4=>'user']))); ?>" disabled>
                        </div>
                    </div>
                </div>
                <!-- Verification Tab -->
                <div class="tab-pane fade" id="verification" role="tabpanel">
                    <div class="mb-3">
                        <label class="form-label">Verification Status</label>
                        <input type="text" class="form-control" value="<?php echo e($verification['status_name'] ?? 'Not Submitted'); ?>" disabled>
                    </div>
                    <form method="post" enctype="multipart/form-data" action="#">
                        <div class="mb-3">
                            <label class="form-label">Upload Verification Document</label>
                            <input type="file" class="form-control" name="verification_file">
                        </div>
                        <button type="submit" class="btn btn-success">Submit for Verification</button>
                    </form>
                </div>
                <!-- Security Tab -->
                <div class="tab-pane fade" id="security" role="tabpanel">
                    <form method="post" action="#">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Old Password</label>
                                <input type="password" class="form-control" name="old_password" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" name="new_password" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" name="confirm_password" required>
                            </div>
                        </div>
                        <div class="mt-4 text-end">
                            <button type="submit" class="btn btn-warning">Change Password</button>
                        </div>
                    </form>
                </div>
                <!-- Delete Account Tab -->
                <div class="tab-pane fade" id="delete" role="tabpanel">
                    <form method="post" action="#" onsubmit="return confirm('Are you sure you want to delete your account? This action cannot be undone.');">
                        <div class="alert alert-danger mb-3"><i class="fas fa-exclamation-triangle me-2"></i> Deleting your account is permanent and cannot be undone.</div>
                        <div class="mb-3">
                            <label class="form-label">Enter your password to confirm:</label>
                            <input type="password" class="form-control" name="delete_password" required>
                        </div>
                        <div class="mt-4 text-end">
                            <button type="submit" class="btn btn-danger">Delete My Account</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../public/footer.php'; ?>
