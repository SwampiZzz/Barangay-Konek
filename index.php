<?php
require_once __DIR__ . '/config.php';

$nav = trim($_GET['nav'] ?? '');

// Helper: choose default nav based on role
function default_nav_for_role($role) {
    if ($role === ROLE_SUPERADMIN) return 'superadmin-dashboard';
    if ($role === ROLE_ADMIN) return 'admin-dashboard';
    if ($role === ROLE_STAFF) return 'staff-dashboard';
    return 'user-dashboard';
}

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
    'register-api' => 'middleware/register-api.php',
    'user-dashboard' => 'pages/user-dashboard.php',
    'create-request' => 'pages/create-request.php',
    'request-list' => 'pages/request-list.php',
    'request-ticket' => 'pages/request-ticket.php',
    'complaint-list' => 'pages/complaint-list.php',
    'announcements' => 'pages/announcements.php',
    'manage-requests' => 'pages/manage-requests.php',
    'manage-complaints' => 'pages/manage-complaints.php',
    'staff-dashboard' => 'pages/staff-dashboard.php',
    'admin-dashboard' => 'pages/admin-dashboard.php',
    'manage-document-types' => 'pages/manage-document-types.php',
    'superadmin-dashboard' => 'pages/superadmin-dashboard.php',
    'admin-management' => 'pages/admin-management.php',
    'barangay-overview' => 'pages/barangay-overview.php',
    'activity-logs' => 'pages/activity-logs.php',
    'profile' => 'pages/profile.php',
];

// If no nav specified, redirect logged-in users to their dashboard; otherwise show public home
if (empty($nav)) {
    if (!empty($_SESSION['user_id'])) {
        $role = current_user_role();
        $target = default_nav_for_role($role);
        header('Location: index.php?nav=' . $target);
        exit;
    }
    $pageTitle = 'Home';
    require_once __DIR__ . '/public/header.php';
    require_once __DIR__ . '/pages/home.php';
    require_once __DIR__ . '/public/footer.php';
    exit;
}

// If a nav is provided, route to the registered page
// If the requested page is public (login/register) and the user is already logged in,
// redirect them to their dashboard instead of showing the auth forms.
if (!empty($_SESSION['user_id']) && in_array($nav, ['login', 'register'], true)) {
    $role = current_user_role();
    $target = default_nav_for_role($role);
    header('Location: index.php?nav=' . $target);
    exit;
}

$page_file = __DIR__ . '/' . ($pages[$nav] ?? '');
if ($page_file && $page_file !== __DIR__ . '/' && file_exists($page_file)) {
    include $page_file;
} else {
    http_response_code(404);
    $pageTitle = 'Not Found';
    require_once __DIR__ . '/public/header.php';
    echo '<div class="container mt-5"><h1>Page Not Found</h1><p>Nav: ' . htmlspecialchars($nav) . '</p><p>File: ' . htmlspecialchars($page_file) . '</p></div>';
    require_once __DIR__ . '/public/footer.php';
}