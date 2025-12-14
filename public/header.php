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
    <link rel="icon" type="image/png" href="<?php echo WEB_ROOT; ?>/public/assets/img/Barangay-Konek-Logo-Only.png">
    <link rel="shortcut icon" href="<?php echo WEB_ROOT; ?>/public/assets/img/Barangay-Konek-Logo-Only.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo WEB_ROOT; ?>/public/assets/css/style.css">
    <script>
        window.APP_ROOT = <?php echo json_encode(WEB_ROOT ?? ''); ?>;
        window.AUTH_URL = <?php echo json_encode((WEB_ROOT ?? '') . '/middleware/auth.php'); ?>;
    </script>
</head>
<body>
    <!-- Top Navigation -->
        <?php require_once __DIR__ . '/nav.php'; ?>

    <main class="flex-grow-1">
