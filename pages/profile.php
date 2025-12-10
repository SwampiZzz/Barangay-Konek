<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware/auth.php';

require_login();

$pageTitle = 'My Profile';
$user_id = current_user_id();
$profile = get_user_profile($user_id);

require_once __DIR__ . '/../public/header.php';
?>

<div class="container my-5">
    <h2 class="mb-4"><i class="fas fa-user"></i> My Profile</h2>

    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Personal Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>First Name:</strong> <?php echo e($profile['first_name'] ?? 'N/A'); ?></p>
                            <p><strong>Last Name:</strong> <?php echo e($profile['last_name'] ?? 'N/A'); ?></p>
                            <p><strong>Email:</strong> <?php echo e($profile['email'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Contact Number:</strong> <?php echo e($profile['contact_number'] ?? 'N/A'); ?></p>
                            <p><strong>Birthdate:</strong> <?php echo e($profile['birthdate'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Account Settings</h5>
                </div>
                <div class="card-body">
                    <p>
                        <strong>Username:</strong> <?php echo e($_SESSION['username'] ?? ''); ?>
                    </p>
                    <a href="index.php?nav=dashboard" class="btn btn-secondary">Back to Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
