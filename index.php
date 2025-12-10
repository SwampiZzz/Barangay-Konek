<?php
require_once __DIR__ . '/config.php';

$nav = trim($_GET['nav'] ?? '');

// Logout handler
if ($nav === 'logout') {
    logout_user();
    flash_set('You have been logged out.', 'success');
    header('Location: index.php');
    exit;
}

// Map navigation to pages
$pages = [
    'login' => 'pages/login.php',
    'register' => 'pages/register.php',
    'dashboard' => 'pages/dashboard.php',
    'create-request' => 'pages/create-request.php',
    'request-list' => 'pages/request-list.php',
    'request-ticket' => 'pages/request-ticket.php',
    'manage-requests' => 'pages/manage-requests.php',
    'staff-dashboard' => 'pages/staff-dashboard.php',
    'admin-dashboard' => 'pages/admin-dashboard.php',
    'manage-document-types' => 'pages/manage-document-types.php',
    'superadmin-dashboard' => 'pages/superadmin-dashboard.php',
    'activity-logs' => 'pages/activity-logs.php',
    'profile' => 'pages/profile.php',
];

// If no nav specified, serve the public landing/home page
if (empty($nav)) {
    $pageTitle = 'Home';
    require_once __DIR__ . '/public/header.php';
    require_once __DIR__ . '/pages/home.php';
    require_once __DIR__ . '/public/footer.php';
    exit;
}

// If a nav is provided, route to the registered page
$page_file = __DIR__ . '/' . ($pages[$nav] ?? '');
if ($page_file && file_exists($page_file)) {
    include $page_file;
} else {
    http_response_code(404);
    $pageTitle = 'Not Found';
    require_once __DIR__ . '/public/header.php';
    echo '<div class="container mt-5"><h1>Page Not Found</h1></div>';
    require_once __DIR__ . '/public/footer.php';
}