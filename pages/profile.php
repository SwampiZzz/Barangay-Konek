
<?php
require_once __DIR__ . '/../config.php';
require_login();
$pageTitle = 'My Profile';
$user_id = current_user_id();
$profile = get_user_profile($user_id);
$role = current_user_role();
$username = $_SESSION['username'] ?? '';

// Initialize alert messages - check session first
$alert_type = '';
$alert_message = '';
$alert_tab = ''; // Track which tab the alert belongs to

// Retrieve alert from session if it exists
if (isset($_SESSION['alert_type']) && isset($_SESSION['alert_message'])) {
    $alert_type = $_SESSION['alert_type'];
    $alert_message = $_SESSION['alert_message'];
    $alert_tab = $_SESSION['alert_tab'] ?? ''; // Get the tab this alert belongs to
    // Clear the session alert after retrieving it (one-time display)
    unset($_SESSION['alert_type']);
    unset($_SESSION['alert_message']);
    unset($_SESSION['alert_tab']);
}

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

// Get user password from database for validation
$user_res = db_query('SELECT password_hash FROM users WHERE id = ?', 'i', [$user_id]);
$user_row = $user_res ? $user_res->fetch_assoc() : [];
$db_password = $user_row['password_hash'] ?? '';

// Debug: Check if password hash exists
if (empty($db_password)) {
    error_log("WARNING: No password hash found for user ID: " . $user_id);
}

// Handle Change Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['alert_type'] = 'danger';
        $_SESSION['alert_message'] = 'All password fields are required.';
        $_SESSION['alert_tab'] = 'account';
    } elseif (empty($db_password)) {
        $_SESSION['alert_type'] = 'danger';
        $_SESSION['alert_message'] = 'Error: Password hash not found in database. Please contact support.';
        $_SESSION['alert_tab'] = 'account';
    } elseif (hash('sha256', $old_password) !== $db_password) {
        $_SESSION['alert_type'] = 'danger';
        $_SESSION['alert_message'] = 'Current password is incorrect.';
        $_SESSION['alert_tab'] = 'account';
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['alert_type'] = 'danger';
        $_SESSION['alert_message'] = 'New password and confirm password do not match.';
        $_SESSION['alert_tab'] = 'account';
    } elseif (strlen($new_password) < 8) {
        $_SESSION['alert_type'] = 'danger';
        $_SESSION['alert_message'] = 'New password must be at least 8 characters long.';
        $_SESSION['alert_tab'] = 'account';
    } else {
        $hashed = hash('sha256', $new_password);
        $update = db_query('UPDATE users SET password_hash = ? WHERE id = ?', 'si', [$hashed, $user_id]);
        if ($update) {
            $_SESSION['alert_type'] = 'success';
            $_SESSION['alert_message'] = 'Password changed successfully!';
            $_SESSION['alert_tab'] = 'account';
        } else {
            $_SESSION['alert_type'] = 'danger';
            $_SESSION['alert_message'] = 'Failed to update password. Please try again.';
            $_SESSION['alert_tab'] = 'account';
        }
    }
    header('Location: ' . WEB_ROOT . '/index.php?nav=profile&tab=account');
    exit;
}

// Handle Update Username
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_username'])) {
    $new_username = trim($_POST['username'] ?? '');
    $current_password = $_POST['current_password_username'] ?? '';
    
    // Get fresh username from database to ensure we have the correct current value
    $current_user_res = db_query('SELECT username FROM users WHERE id = ?', 'i', [$user_id]);
    $current_user_row = $current_user_res ? $current_user_res->fetch_assoc() : [];
    $db_username = $current_user_row['username'] ?? '';
    
    if (empty($new_username) || empty($current_password)) {
        $_SESSION['alert_type'] = 'danger';
        $_SESSION['alert_message'] = 'Username and password are required.';
        $_SESSION['alert_tab'] = 'account';
    } elseif (empty($db_password)) {
        $_SESSION['alert_type'] = 'danger';
        $_SESSION['alert_message'] = 'Error: Password hash not found in database. Please contact support.';
        $_SESSION['alert_tab'] = 'account';
    } elseif (strtolower($new_username) === strtolower($db_username)) {
        $_SESSION['alert_type'] = 'danger';
        $_SESSION['alert_message'] = 'New username must be different from your current username (' . htmlspecialchars($db_username) . ').';
        $_SESSION['alert_tab'] = 'account';
    } elseif (hash('sha256', $current_password) !== $db_password) {
        $_SESSION['alert_type'] = 'danger';
        $_SESSION['alert_message'] = 'Current password is incorrect.';
        $_SESSION['alert_tab'] = 'account';
    } else {
        // Check if username already exists
        $check = db_query('SELECT id FROM users WHERE username = ? AND id != ?', 'si', [$new_username, $user_id]);
        if ($check && $check->num_rows > 0) {
            $_SESSION['alert_type'] = 'danger';
            $_SESSION['alert_message'] = 'This username is already taken. Please choose a different one.';
            $_SESSION['alert_tab'] = 'account';
        } else {
            $update = db_query('UPDATE users SET username = ? WHERE id = ?', 'si', [$new_username, $user_id]);
            if ($update) {
                $_SESSION['username'] = $new_username;
                $_SESSION['alert_type'] = 'success';
                $_SESSION['alert_message'] = 'Username updated successfully to: ' . htmlspecialchars($new_username);
                $_SESSION['alert_tab'] = 'account';
            } else {
                $_SESSION['alert_type'] = 'danger';
                $_SESSION['alert_message'] = 'Failed to update username. Please try again.';
                $_SESSION['alert_tab'] = 'account';
            }
        }
    }
    header('Location: ' . WEB_ROOT . '/index.php?nav=profile&tab=account');
    exit;
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

// Check if user is verified (status_id = 2 means verified)
$is_verified = !empty($verification['verification_status_id']) && $verification['verification_status_id'] == 2;

// Clean up invalid birthdates (0000-00-00)
if (!empty($profile['birthdate']) && ($profile['birthdate'] === '0000-00-00' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $profile['birthdate']))) {
    $profile['birthdate'] = '';
}

// Handle Update Profile (First Name, Last Name, Email, Contact, etc.)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile' && !isset($_FILES['profile_pic'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    $sex_id = intval($_POST['sex_id'] ?? 0);
    $birthdate = $_POST['birthdate'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    
    // Basic validation
    if (empty($first_name) || empty($middle_name) || empty($last_name)) {
        $_SESSION['alert_type'] = 'danger';
        $_SESSION['alert_message'] = 'First name, middle name, and last name are required.';
        $_SESSION['alert_tab'] = 'profile';
    } elseif ($is_verified) {
        // Verified users can only edit email and contact number
        // Check if they tried to edit protected fields
        $old_first_name = $profile['first_name'] ?? '';
        $old_middle_name = $profile['middle_name'] ?? '';
        $old_last_name = $profile['last_name'] ?? '';
        $old_suffix = $profile['suffix'] ?? '';
        $old_sex_id = $profile['sex_id'] ?? 0;
        $old_birthdate = $profile['birthdate'] ?? '';
        
        if ($first_name !== $old_first_name || $middle_name !== $old_middle_name || $last_name !== $old_last_name || $suffix !== $old_suffix || 
            $sex_id !== intval($old_sex_id) || $birthdate !== $old_birthdate) {
            $_SESSION['alert_type'] = 'danger';
            $_SESSION['alert_message'] = 'Your account is verified. You can only edit email address and contact number. Other personal information cannot be changed.';
            $_SESSION['alert_tab'] = 'profile';
        } elseif (empty($email)) {
            $_SESSION['alert_type'] = 'danger';
            $_SESSION['alert_message'] = 'Email address is required.';
            $_SESSION['alert_tab'] = 'profile';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['alert_type'] = 'danger';
            $_SESSION['alert_message'] = 'Please enter a valid email address.';
            $_SESSION['alert_tab'] = 'profile';
        } elseif (empty($contact_number)) {
            $_SESSION['alert_type'] = 'danger';
            $_SESSION['alert_message'] = 'Contact number is required.';
            $_SESSION['alert_tab'] = 'profile';
        } elseif (!preg_match('/^[0-9+\-\(\)\s]+$/', $contact_number)) {
            $_SESSION['alert_type'] = 'danger';
            $_SESSION['alert_message'] = 'Please enter a valid contact number.';
            $_SESSION['alert_tab'] = 'profile';
        } else {
            // Check if email is unique (excluding current user's email if unchanged)
            $old_email = $profile['email'] ?? '';
            if ($email !== $old_email) {
                $email_check = db_query('SELECT id FROM profile WHERE email = ? AND user_id != ?', 'si', [$email, $user_id]);
                if ($email_check && $email_check->num_rows > 0) {
                    $_SESSION['alert_type'] = 'danger';
                    $_SESSION['alert_message'] = 'This email address is already in use by another account.';
                    $_SESSION['alert_tab'] = 'profile';
                } else {
                    // Update only email and contact_number for verified users
                    $update = db_query('UPDATE profile SET email = ?, contact_number = ? WHERE user_id = ?', 
                        'ssi', 
                        [$email, $contact_number, $user_id]);
                    if ($update) {
                        $_SESSION['alert_type'] = 'success';
                        $_SESSION['alert_message'] = 'Profile updated successfully! (Email and contact number only)';
                        $_SESSION['alert_tab'] = 'profile';
                    } else {
                        $_SESSION['alert_type'] = 'danger';
                        $_SESSION['alert_message'] = 'Failed to update profile. Please try again.';
                        $_SESSION['alert_tab'] = 'profile';
                    }
                }
            } else {
                // Email unchanged, just update contact_number
                $update = db_query('UPDATE profile SET contact_number = ? WHERE user_id = ?', 
                    'si', 
                    [$contact_number, $user_id]);
                if ($update) {
                    $_SESSION['alert_type'] = 'success';
                    $_SESSION['alert_message'] = 'Profile updated successfully! (Contact number only)';
                    $_SESSION['alert_tab'] = 'profile';
                } else {
                    $_SESSION['alert_type'] = 'danger';
                    $_SESSION['alert_message'] = 'Failed to update profile. Please try again.';
                    $_SESSION['alert_tab'] = 'profile';
                }
            }
        }
    } elseif ($sex_id <= 0) {
        $_SESSION['alert_type'] = 'danger';
        $_SESSION['alert_message'] = 'Please select your gender.';
        $_SESSION['alert_tab'] = 'profile';
    } elseif (empty($birthdate)) {
        $_SESSION['alert_type'] = 'danger';
        $_SESSION['alert_message'] = 'Birthdate is required.';
        $_SESSION['alert_tab'] = 'profile';
    } elseif (empty($email)) {
        $_SESSION['alert_type'] = 'danger';
        $_SESSION['alert_message'] = 'Email address is required.';
        $_SESSION['alert_tab'] = 'profile';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['alert_type'] = 'danger';
        $_SESSION['alert_message'] = 'Please enter a valid email address.';
        $_SESSION['alert_tab'] = 'profile';
    } elseif (empty($contact_number)) {
        $_SESSION['alert_type'] = 'danger';
        $_SESSION['alert_message'] = 'Contact number is required.';
        $_SESSION['alert_tab'] = 'profile';
    } elseif (!preg_match('/^[0-9+\-\(\)\s]+$/', $contact_number)) {
        $_SESSION['alert_type'] = 'danger';
        $_SESSION['alert_message'] = 'Please enter a valid contact number.';
        $_SESSION['alert_tab'] = 'profile';
    } else {
        // Check if email is unique (excluding current user's email if unchanged)
        $old_email = $profile['email'] ?? '';
        if ($email !== $old_email) {
            $email_check = db_query('SELECT id FROM profile WHERE email = ? AND user_id != ?', 'si', [$email, $user_id]);
            if ($email_check && $email_check->num_rows > 0) {
                $_SESSION['alert_type'] = 'danger';
                $_SESSION['alert_message'] = 'This email address is already in use by another account.';
                $_SESSION['alert_tab'] = 'profile';
            } else {
                // Unverified users can edit all fields
                $update = db_query('UPDATE profile SET first_name = ?, middle_name = ?, last_name = ?, suffix = ?, sex_id = ?, birthdate = ?, email = ?, contact_number = ? WHERE user_id = ?', 
                    'ssssisssi', 
                    [$first_name, $middle_name, $last_name, $suffix, $sex_id, $birthdate, $email, $contact_number, $user_id]);
                if ($update) {
                    $_SESSION['alert_type'] = 'success';
                    $_SESSION['alert_message'] = 'Profile updated successfully!';
                    $_SESSION['alert_tab'] = 'profile';
                } else {
                    $_SESSION['alert_type'] = 'danger';
                    $_SESSION['alert_message'] = 'Failed to update profile. Please try again.';
                    $_SESSION['alert_tab'] = 'profile';
                }
            }
        } else {
            // Email unchanged, update all fields
            $update = db_query('UPDATE profile SET first_name = ?, middle_name = ?, last_name = ?, suffix = ?, sex_id = ?, birthdate = ?, email = ?, contact_number = ? WHERE user_id = ?', 
                'ssssisssi', 
                [$first_name, $middle_name, $last_name, $suffix, $sex_id, $birthdate, $email, $contact_number, $user_id]);
            if ($update) {
                $_SESSION['alert_type'] = 'success';
                $_SESSION['alert_message'] = 'Profile updated successfully!';
                $_SESSION['alert_tab'] = 'profile';
            } else {
                $_SESSION['alert_type'] = 'danger';
                $_SESSION['alert_message'] = 'Failed to update profile. Please try again.';
                $_SESSION['alert_tab'] = 'profile';
            }
        }
    }
    header('Location: ' . WEB_ROOT . '/index.php?nav=profile&tab=profile');
    exit;
}

// Handle Verification File Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['verification_file']) && $_FILES['verification_file']['error'] === UPLOAD_ERR_OK) {
    $verification_dir = __DIR__ . '/../storage/app/private/requests/';
    if (!is_dir($verification_dir)) mkdir($verification_dir, 0755, true);
    
    $file = $_FILES['verification_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // Check if already verified
    if (!empty($verification['verification_status_id']) && $verification['verification_status_id'] == 2) {
        $_SESSION['alert_type'] = 'warning';
        $_SESSION['alert_message'] = 'Your account is already verified. No further documents can be submitted.';
        $_SESSION['alert_tab'] = 'verification';
    } elseif (!in_array($ext, $allowed)) {
        $_SESSION['alert_type'] = 'danger';
        $_SESSION['alert_message'] = 'Invalid file type. Allowed: ' . implode(', ', $allowed);
        $_SESSION['alert_tab'] = 'verification';
    } elseif ($file['size'] > $max_size) {
        $_SESSION['alert_type'] = 'danger';
        $_SESSION['alert_message'] = 'File size exceeds 5MB limit.';
        $_SESSION['alert_tab'] = 'verification';
    } else {
        $filename = 'verification_' . $user_id . '_' . time() . '.' . $ext;
        $filepath = $verification_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Get profile_id
            $profile_res = db_query('SELECT id FROM profile WHERE user_id = ?', 'i', [$user_id]);
            $profile_id = 0;
            if ($profile_res && $row = $profile_res->fetch_assoc()) {
                $profile_id = $row['id'];
            }
            
            // Create or update verification record
            $check = db_query('SELECT id, filename FROM user_verification WHERE user_id = ?', 'i', [$user_id]);
            if ($check && $check->num_rows > 0) {
                // Update existing (resubmission after rejection)
                $existing = $check->fetch_assoc();
                $oldFilename = $existing['filename'] ?? '';
                $update = db_query('UPDATE user_verification SET filename = ?, verification_status_id = 1, submitted_at = NOW(), remarks = NULL, verified_at = NULL, verified_by = NULL WHERE user_id = ?', 'si', [$filename, $user_id]);
                $success = $update;
                // If update succeeded, remove previous uploaded file
                if ($success && !empty($oldFilename) && $oldFilename !== $filename) {
                    $oldPath = $verification_dir . $oldFilename;
                    if (file_exists($oldPath)) {
                        @unlink($oldPath);
                    }
                }
            } else {
                // Create new verification record
                $insert = db_query('INSERT INTO user_verification (user_id, profile_id, filename, verification_status_id, submitted_at) VALUES (?, ?, ?, 1, NOW())', 'iis', [$user_id, $profile_id, $filename]);
                $success = $insert;
            }
            
            if ($success) {
                $_SESSION['alert_type'] = 'success';
                $_SESSION['alert_message'] = 'Verification document submitted successfully! It will be reviewed by the administrator. You will be notified once the review is complete.';
                $_SESSION['alert_tab'] = 'verification';
                
                // Refresh verification status
                $vres = db_query('SELECT v.*, vs.name as status_name FROM user_verification v LEFT JOIN verification_status vs ON v.verification_status_id = vs.id WHERE v.user_id = ?', 'i', [$user_id]);
                if ($vres) $verification = $vres->fetch_assoc();
            } else {
                $_SESSION['alert_type'] = 'danger';
                $_SESSION['alert_message'] = 'Failed to save verification record. Please try again.';
                $_SESSION['alert_tab'] = 'verification';
            }
        } else {
            $_SESSION['alert_type'] = 'danger';
            $_SESSION['alert_message'] = 'Failed to upload file. Please try again.';
            $_SESSION['alert_tab'] = 'verification';
        }
    }
    header('Location: ' . WEB_ROOT . '/index.php?nav=profile&tab=verification');
    exit;
}

// Handle Delete Account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_password'])) {
    $delete_password = $_POST['delete_password'] ?? '';
    
    if (empty($delete_password)) {
        $_SESSION['alert_type'] = 'danger';
        $_SESSION['alert_message'] = 'Password is required to delete account.';
        $_SESSION['alert_tab'] = 'delete';
    } elseif (empty($db_password)) {
        $_SESSION['alert_type'] = 'danger';
        $_SESSION['alert_message'] = 'Error: Password hash not found in database. Please contact support.';
        $_SESSION['alert_tab'] = 'delete';
    } elseif (hash('sha256', $delete_password) !== $db_password) {
        $_SESSION['alert_type'] = 'danger';
        $_SESSION['alert_message'] = 'Incorrect password. Account not deleted.';
        $_SESSION['alert_tab'] = 'delete';
    } else {
        // Soft delete or hard delete - adjust based on your preference
        $delete = db_query('UPDATE users SET deleted_at = NOW() WHERE id = ?', 'i', [$user_id]);
        if ($delete) {
            // Log out user
            session_destroy();
            header('Location: ' . WEB_ROOT . '/index.php?logout=success');
            exit;
        } else {
            $_SESSION['alert_type'] = 'danger';
            $_SESSION['alert_message'] = 'Failed to delete account. Please try again.';
            $_SESSION['alert_tab'] = 'delete';
        }
    }
    header('Location: ' . WEB_ROOT . '/index.php?nav=profile&tab=delete');
    exit;
}

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
                    <button class="nav-link" id="account-tab" data-bs-toggle="tab" data-bs-target="#account" type="button" role="tab">Account & Security</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="verification-tab" data-bs-toggle="tab" data-bs-target="#verification" type="button" role="tab">Verification</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link text-danger" id="delete-tab" data-bs-toggle="tab" data-bs-target="#delete" type="button" role="tab">Delete Account</button>
                </li>
            </ul>
            <div class="tab-content" id="profileTabContent">
                <!-- Profile Tab -->
                <div class="tab-pane fade show active" id="profile" role="tabpanel">
                    <?php if (!empty($alert_message) && $alert_tab === 'profile'): ?>
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
                    
                    <div class="row mb-4">
                        <div class="col-md-12 text-center">
                            <form method="post" action="#" enctype="multipart/form-data">
                                <div class="position-relative d-inline-block">
                                    <label for="profilePicInput" style="cursor:pointer;" title="Click to change profile picture">
                                        <img src="<?php echo $profilePicUrl; ?>" alt="Profile Picture" class="rounded-circle shadow-sm" style="width: 150px; height: 150px; object-fit: cover; border: 4px solid #0b3d91;">
                                        <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-primary p-2" style="font-size:1.1em;">
                                            <i class="fas fa-camera"></i>
                                        </span>
                                    </label>
                                    <input type="file" id="profilePicInput" class="form-control d-none" name="profile_pic" accept="image/jpeg,image/png,image/jpg" onchange="this.form.submit()">
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Click image to upload/change</small>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <h5 class="mb-3"><i class="fas fa-user-edit me-2"></i>Personal Information</h5>
                    <?php if ($is_verified): ?>
                        <div class="alert alert-info alert-dismissible fade show mb-3" role="alert">
                            <i class="fas fa-lock me-2"></i>
                            <strong>Account Verified:</strong> Your account has been verified. You can only edit your email address and contact number. Other personal information is locked.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <form method="post" action="#" autocomplete="off">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="first_name" value="<?php echo e($profile['first_name'] ?? ''); ?>" placeholder="Juan" required <?php if ($is_verified) echo 'disabled'; ?>>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Middle Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="middle_name" value="<?php echo e($profile['middle_name'] ?? ''); ?>" placeholder="Cruz" required <?php if ($is_verified) echo 'disabled'; ?>>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="last_name" value="<?php echo e($profile['last_name'] ?? ''); ?>" placeholder="Dela Cruz" required <?php if ($is_verified) echo 'disabled'; ?>>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Suffix</label>
                                <input type="text" class="form-control" name="suffix" value="<?php echo e($profile['suffix'] ?? ''); ?>" placeholder="Jr., Sr., III" <?php if ($is_verified) echo 'disabled'; ?>>
                                <div class="form-text">Optional</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Sex <span class="text-danger">*</span></label>
                                <select class="form-select" name="sex_id" required <?php if ($is_verified) echo 'disabled'; ?>>
                                    <option value="">Select Gender</option>
                                    <option value="1" <?php if (($profile['sex_id'] ?? null) == 1) echo 'selected'; ?>>Male</option>
                                    <option value="2" <?php if (($profile['sex_id'] ?? null) == 2) echo 'selected'; ?>>Female</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Birthdate <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="birthdate" value="<?php echo e($profile['birthdate'] ?? ''); ?>" max="<?php echo date('Y-m-d'); ?>" required <?php if ($is_verified) echo 'disabled'; ?>>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Barangay</label>
                                <input type="text" class="form-control" value="<?php echo e($barangay_name ?: 'Not specified'); ?>" disabled>
                                <div class="form-text">Contact administrator to change barangay</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" name="email" value="<?php echo e($profile['email'] ?? ''); ?>" placeholder="your.email@example.com" required>
                                </div>
                                <div class="form-text">Used for notifications and password recovery</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contact Number <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    <input type="text" class="form-control" name="contact_number" value="<?php echo e($profile['contact_number'] ?? ''); ?>" placeholder="+63 XXX XXX XXXX" pattern="[0-9+\-\(\)\s]+" required>
                                </div>
                                <div class="form-text">Format: +63 XXX XXX XXXX or 09XX XXX XXXX</div>
                            </div>
                        </div>
                        <div class="mt-4 d-flex justify-content-between align-items-center">
                            <small class="text-muted"><i class="fas fa-asterisk text-danger" style="font-size:0.5em;"></i> Required fields</small>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
                <!-- Account & Security Tab -->
                <div class="tab-pane fade" id="account" role="tabpanel">
                    <?php if (!empty($alert_message) && $alert_tab === 'account'): ?>
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
                    
                    <h5 class="mb-3">Account Information</h5>
                    <form method="post" action="#">
                        <div class="mb-3">
                            <label class="form-label">Current Username</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($username); ?>" disabled>
                            <div class="form-text">Your current username</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Username</label>
                            <input type="text" class="form-control" name="username" placeholder="Enter new username" required>
                            <div class="form-text">Must be unique and different from current username</div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Current Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="current_password_username" placeholder="Confirm your current password" required>
                            <div class="form-text">Required to update username for security</div>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary" name="update_username" value="1">Update Username</button>
                        </div>
                    </form>
                    
                    <hr class="my-4">
                    
                    <h5 class="mb-3">Change Password</h5>
                    <form method="post" action="#">
                        <div class="mb-3">
                            <label class="form-label">Current Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="old_password" placeholder="Enter your current password" required>
                            <div class="form-text">Required to verify your identity</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="new_password" placeholder="Enter new password" required>
                            <div class="form-text">Choose a strong password</div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="confirm_password" placeholder="Re-enter new password" required>
                            <div class="form-text">Must match the new password above</div>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-warning" name="change_password">
                                <i class="fas fa-key me-2"></i>Change Password
                            </button>
                        </div>
                    </form>
                </div>
                <!-- Verification Tab -->
                <div class="tab-pane fade" id="verification" role="tabpanel">
                    <?php if (!empty($alert_message) && $alert_tab === 'verification'): ?>
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
                    
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <h5 class="mb-3"><i class="fas fa-certificate me-2"></i>Account Verification</h5>
                            
                            <?php 
                            $verification_status_id = $verification['verification_status_id'] ?? 0;
                            $status_name = $verification['status_name'] ?? 'Not Submitted';
                            $verification_remarks = $verification['remarks'] ?? '';
                            $document_file = $verification['filename'] ?? '';
                            ?>
                            
                            <!-- Verification Status Card -->
                            <div class="card mb-4" style="border-left: 4px solid <?php 
                                if ($verification_status_id == 0) {
                                    echo '#6c757d'; // Not submitted - gray
                                } elseif ($verification_status_id == 2) {
                                    echo '#28a745'; // Verified - green
                                } elseif ($verification_status_id == 3) {
                                    echo '#dc3545'; // Rejected - red
                                } else {
                                    echo '#ffc107'; // Pending - yellow
                                }
                            ?>;">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-auto">
                                            <?php if ($verification_status_id == 0): ?>
                                                <i class="fas fa-file-upload fa-2x text-secondary"></i>
                                            <?php elseif ($verification_status_id == 2): ?>
                                                <i class="fas fa-check-circle fa-2x text-success"></i>
                                            <?php elseif ($verification_status_id == 3): ?>
                                                <i class="fas fa-times-circle fa-2x text-danger"></i>
                                            <?php else: ?>
                                                <i class="fas fa-hourglass-half fa-2x text-warning"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col">
                                            <h6 class="mb-1">
                                                Status: 
                                                <span class="badge <?php 
                                                    if ($verification_status_id == 0) {
                                                        echo 'bg-secondary';
                                                    } elseif ($verification_status_id == 2) {
                                                        echo 'bg-success';
                                                    } elseif ($verification_status_id == 3) {
                                                        echo 'bg-danger';
                                                    } else {
                                                        echo 'bg-warning text-dark';
                                                    }
                                                ?>">
                                                    <?php echo htmlspecialchars(ucfirst($status_name)); ?>
                                                </span>
                                            </h6>
                                            <p class="text-muted mb-0 small">
                                                <?php 
                                                if ($verification_status_id == 0) {
                                                    echo 'You have not submitted any verification document yet. Please upload your document below to start the verification process.';
                                                } elseif ($verification_status_id == 2) {
                                                    echo 'Your account has been verified by the administrator.';
                                                } elseif ($verification_status_id == 3) {
                                                    echo 'Your submission was rejected. Please review the remarks below and resubmit.';
                                                } else {
                                                    echo 'Your verification is pending review by the administrator.';
                                                }
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Rejection Remarks (if rejected) -->
                            <?php if ($verification_status_id == 3 && !empty($verification_remarks)): ?>
                                <div class="alert alert-warning mb-4">
                                    <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Admin Remarks</h6>
                                    <p class="mb-0"><?php echo htmlspecialchars($verification_remarks); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Document Upload Form (visible for unverified users) -->
                            <?php if ($verification_status_id != 2): ?>
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="fas fa-file-upload me-2"></i>
                                            <?php echo ($verification_status_id == 3) ? 'Resubmit Verification Document' : 'Submit Verification Document'; ?>
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($verification_status_id == 1 && !empty($document_file)): ?>
                                            <div class="alert alert-info mb-3">
                                                <i class="fas fa-clock me-2"></i>
                                                <strong>Pending Review:</strong> Your document has been submitted and is awaiting review. You cannot upload a new document while your submission is pending.
                                            </div>
                                        <?php endif; ?>
                                        
                                        <form method="post" enctype="multipart/form-data" action="#" <?php if ($verification_status_id == 1 && !empty($document_file)) echo 'style="display:none;"'; ?>>
                                            <div class="mb-3">
                                                <label class="form-label">Verification Document <span class="text-danger">*</span></label>
                                                <div class="input-group mb-2">
                                                    <input type="file" class="form-control" id="verificationFile" name="verification_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                                                </div>
                                                <div class="form-text">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    <strong>Allowed formats:</strong> PDF, JPG, PNG, DOC, DOCX<br>
                                                    <strong>Max size:</strong> 5MB<br>
                                                    <small class="text-muted">Please ensure your document is clear and readable.</small>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3" id="filePreview" style="display:none;">
                                                <div class="alert alert-light border">
                                                    <strong>Selected file:</strong> <span id="fileName"></span>
                                                </div>
                                            </div>
                                            
                                            <div class="mt-4">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-paper-plane me-2"></i>
                                                    <?php echo ($verification_status_id == 3) ? 'Resubmit Document' : 'Submit for Verification'; ?>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <strong>Account Verified:</strong> Your account is verified. You have full access to all features.
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Verification Info Sidebar -->
                        <div class="col-md-4">
                            <div class="card bg-light mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>Verification Process</h6>
                                </div>
                                <div class="card-body small">
                                    <ol class="mb-0">
                                        <li>Upload your verification document</li>
                                        <li>Administrator reviews your submission</li>
                                        <li>You'll receive approval or rejection with remarks</li>
                                        <li>If rejected, revise and resubmit</li>
                                        <li>Account becomes verified once approved</li>
                                    </ol>
                                </div>
                            </div>
                            
                            <div class="card bg-light mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="fas fa-id-card me-2"></i>Valid ID Documents</h6>
                                </div>
                                <div class="card-body small">
                                    <p class="mb-2"><strong>Primary IDs:</strong></p>
                                    <ul class="mb-2">
                                        <li>Philippine Passport</li>
                                        <li>Driver's License</li>
                                        <li>PhilSys ID (National ID)</li>
                                        <li>SSS ID (UMID)</li>
                                        <li>GSIS e-Card</li>
                                        <li>Voter's ID</li>
                                        <li>PRC ID</li>
                                    </ul>
                                    <p class="mb-2"><strong>Secondary IDs:</strong></p>
                                    <ul class="mb-0">
                                        <li>Postal ID</li>
                                        <li>TIN ID</li>
                                        <li>Senior Citizen ID</li>
                                        <li>PWD ID</li>
                                        <li>Barangay Clearance</li>
                                        <li>Police Clearance</li>
                                        <li>School/Company ID</li>
                                    </ul>
                                    <p class="mt-2 mb-0 text-muted fst-italic">
                                        <small>Note: Make sure your ID is clear, not expired, and shows your full name.</small>
                                    </p>
                                </div>
                            </div>
                            
                            <?php if (!empty($document_file)): ?>
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-file me-2"></i>Current Document</h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="small text-muted mb-2">File: <strong><?php echo htmlspecialchars($document_file); ?></strong></p>
                                        <p class="small text-muted mb-0">
                                            Status: 
                                            <strong>
                                                <?php 
                                                echo ($verification_status_id == 2) ? 'Approved' : 
                                                    (($verification_status_id == 3) ? 'Rejected' : 'Pending');
                                                ?>
                                            </strong>
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!-- Delete Account Tab -->
                <div class="tab-pane fade" id="delete" role="tabpanel">
                    <?php if (!empty($alert_message) && $alert_tab === 'delete'): ?>
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
                    
                    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                                <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Danger Zone</h5>
                                <p class="mb-2">Deleting your account is a <strong>permanent action</strong> that cannot be undone. Once deleted:</p>
                                <ul class="mb-3">
                                    <li>You will immediately be logged out</li>
                                    <li>All your personal information will be marked as deleted</li>
                                    <li>Your account cannot be recovered</li>
                                    <li>You will need to create a new account to access the system again</li>
                                </ul>
                                <p class="mb-0 small text-muted">Please ensure you have saved any important information before proceeding.</p>
                            </div>
                            
                            <div class="card border-danger">
                                <div class="card-header bg-danger text-white">
                                    <h6 class="mb-0"><i class="fas fa-trash me-2"></i>Delete Account</h6>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted mb-4">To delete your account, please enter your password below and confirm your decision:</p>
                                    
                                    <form id="deleteAccountForm" method="post" action="#">
                                        <div class="mb-4">
                                            <label class="form-label">Current Password <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control" name="delete_password" placeholder="Enter your password" required>
                                            <div class="form-text">Your password is required to verify this request</div>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="confirmDelete" required>
                                                <label class="form-check-label" for="confirmDelete">
                                                    I understand that deleting my account is permanent and cannot be undone
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="confirmDataLoss" required>
                                                <label class="form-check-label" for="confirmDataLoss">
                                                    I have saved all important information and accept data loss
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-4 d-flex gap-2 justify-content-end">
                                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('confirmDelete').checked = false; document.getElementById('confirmDataLoss').checked = false;">
                                                Cancel
                                            </button>
                                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" id="deleteBtn">
                                                <i class="fas fa-trash me-2"></i>Delete My Account
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Confirmation Modal -->
                    <div class="modal fade" id="confirmDeleteModal" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-danger">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title"><i class="fas fa-exclamation-circle me-2"></i>Confirm Account Deletion</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="mb-3"><strong>This action cannot be undone!</strong></p>
                                    <p class="mb-3">You are about to permanently delete your account. This will:</p>
                                    <ul class="mb-3">
                                        <li>Log you out immediately</li>
                                        <li>Delete all your data</li>
                                        <li>Prevent you from logging in again</li>
                                    </ul>
                                    <p class="mb-0 text-muted"><small>Type <strong>"DELETE"</strong> below to confirm:</small></p>
                                    <div class="mt-2 mb-3">
                                        <input type="text" class="form-control form-control-lg text-uppercase text-center" id="deleteConfirmText" placeholder="Type DELETE to confirm" maxlength="6">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn" disabled>
                                        <i class="fas fa-trash me-2"></i>Permanently Delete Account
                                    </button>
                                </div>
                            </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Handle tab navigation from URL hash or query parameter
document.addEventListener('DOMContentLoaded', function() {
    // Handle tab parameter from URL
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    
    let targetTab = null;
    
    if (tabParam) {
        // If tab parameter is provided, use it
        const tabButton = document.querySelector(`button[data-bs-target="#${tabParam}"]`);
        if (tabButton) {
            targetTab = tabButton;
        }
    } else {
        // Otherwise, fall back to hash if present
        const hash = window.location.hash;
        if (hash) {
            targetTab = document.querySelector(`button[data-bs-target="${hash}"]`);
        }
    }
    
    if (targetTab) {
        // Ensure Bootstrap is fully loaded before creating tab instance
        if (typeof bootstrap !== 'undefined') {
            try {
                const tab = new bootstrap.Tab(targetTab);
                tab.show();
                
                // Scroll to the tab content with a slight delay to ensure tab is switched
                setTimeout(() => {
                    const scrollTarget = tabParam ? `#${tabParam}` : window.location.hash;
                    const targetElement = document.querySelector(scrollTarget);
                    if (targetElement) {
                        // Scroll to the main content, not to the tab itself
                        const pageTitle = document.querySelector('h2');
                        if (pageTitle) {
                            pageTitle.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    }
                }, 250);
            } catch (e) {
                console.error('Error switching tab:', e);
            }
        }
    }
    
    // File preview for verification upload
    const fileInput = document.getElementById('verificationFile');
    const filePreview = document.getElementById('filePreview');
    const fileName = document.getElementById('fileName');
    
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                fileName.textContent = this.files[0].name + ' (' + (this.files[0].size / 1024 / 1024).toFixed(2) + ' MB)';
                filePreview.style.display = 'block';
            } else {
                filePreview.style.display = 'none';
            }
        });
    }
    
    // Delete account confirmation modal functionality
    const deleteConfirmText = document.getElementById('deleteConfirmText');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const deleteAccountForm = document.getElementById('deleteAccountForm');
    const confirmDelete = document.getElementById('confirmDelete');
    const confirmDataLoss = document.getElementById('confirmDataLoss');
    
    if (deleteConfirmText && confirmDeleteBtn) {
        // Enable/disable confirm button based on text input
        deleteConfirmText.addEventListener('input', function() {
            confirmDeleteBtn.disabled = this.value.toUpperCase() !== 'DELETE';
        });
        
        // Handle final confirmation click
        confirmDeleteBtn.addEventListener('click', function() {
            if (deleteAccountForm && deleteConfirmText.value.toUpperCase() === 'DELETE') {
                // Submit the hidden form that was prepared earlier
                if (confirmDelete.checked && confirmDataLoss.checked) {
                    // Create and submit the form
                    deleteAccountForm.submit();
                }
            }
        });
    }
    
    // Validate checkboxes before showing modal
    const deleteBtn = document.getElementById('deleteBtn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function(e) {
            if (!confirmDelete.checked || !confirmDataLoss.checked) {
                e.preventDefault();
                alert('Please read and accept both confirmations before proceeding.');
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
