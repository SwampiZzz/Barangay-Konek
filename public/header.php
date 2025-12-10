<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!defined('WEB_ROOT')) {
    define('WEB_ROOT', '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - Barangay Konek' : 'Barangay Konek'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo WEB_ROOT; ?>/public/assets/css/style.css">
</head>
<body>
    <!-- Top Navigation -->
        <?php require_once __DIR__ . '/nav.php'; ?>

    <!-- Flash Messages -->
    <?php 
    $flash = flash_get();
    if (!empty($flash['message'])): 
    ?>
        <div class="container-fluid mt-3">
            <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : ($flash['type'] === 'success' ? 'success' : 'info'); ?> alert-dismissible fade show" role="alert">
                <?php echo e($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Bottom Navigation (if logged in) -->
    <?php if (!empty($_SESSION['user_id'])): ?>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarBottom">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarBottom">
                <ul class="navbar-nav">
                    <?php 
                    $role = intval($_SESSION['role'] ?? 0);
                    if ($role === 4): // User/Resident
                    ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo WEB_ROOT; ?>/index.php?nav=dashboard">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo WEB_ROOT; ?>/index.php?nav=request-list">My Requests</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo WEB_ROOT; ?>/index.php?nav=create-request">New Request</a>
                        </li>
                    <?php elseif ($role === 3): // Staff
                    ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo WEB_ROOT; ?>/index.php?nav=staff-dashboard">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo WEB_ROOT; ?>/index.php?nav=manage-requests">Manage Requests</a>
                        </li>
                    <?php elseif ($role === 2): // Admin
                    ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo WEB_ROOT; ?>/index.php?nav=admin-dashboard">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo WEB_ROOT; ?>/index.php?nav=manage-requests">Manage Requests</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo WEB_ROOT; ?>/index.php?nav=manage-document-types">Document Types</a>
                        </li>
                    <?php elseif ($role === 1): // Superadmin
                    ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo WEB_ROOT; ?>/index.php?nav=superadmin-dashboard">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo WEB_ROOT; ?>/index.php?nav=activity-logs">Activity Logs</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <?php endif; ?>

    <main class="flex-grow-1">
