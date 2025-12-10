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

// Map navigation to pages with their required roles
// Format: 'nav' => ['file' => 'path/to/file.php', 'roles' => [ROLE_*, ...] or null for public]
$pages = [
    // Public pages
    'login' => ['file' => 'pages/login.php', 'roles' => null],
    'register' => ['file' => 'pages/register.php', 'roles' => null],
    'register-api' => ['file' => 'middleware/register-api.php', 'roles' => null],
    'terms-of-service' => ['file' => 'pages/terms-of-service.php', 'roles' => null],
    'privacy-policy' => ['file' => 'pages/privacy-policy.php', 'roles' => null],
    'announcements' => ['file' => 'pages/announcements.php', 'roles' => null],
    
    // User pages
    'user-dashboard' => ['file' => 'pages/user-dashboard.php', 'roles' => [ROLE_USER]],
    'create-request' => ['file' => 'pages/create-request.php', 'roles' => [ROLE_USER]],
    'request-list' => ['file' => 'pages/request-list.php', 'roles' => [ROLE_USER]],
    'request-ticket' => ['file' => 'pages/request-ticket.php', 'roles' => [ROLE_USER]],
    'complaint-list' => ['file' => 'pages/complaint-list.php', 'roles' => [ROLE_USER]],
    'profile' => ['file' => 'pages/profile.php', 'roles' => [ROLE_USER, ROLE_STAFF, ROLE_ADMIN, ROLE_SUPERADMIN]],
    
    // Staff pages
    'staff-dashboard' => ['file' => 'pages/staff-dashboard.php', 'roles' => [ROLE_STAFF]],
    'manage-requests' => ['file' => 'pages/manage-requests.php', 'roles' => [ROLE_STAFF, ROLE_ADMIN]],
    'manage-complaints' => ['file' => 'pages/manage-complaints.php', 'roles' => [ROLE_STAFF, ROLE_ADMIN]],
    'manage-document-types' => ['file' => 'pages/manage-document-types.php', 'roles' => [ROLE_ADMIN]],
    
    // Admin pages
    'admin-dashboard' => ['file' => 'pages/admin-dashboard.php', 'roles' => [ROLE_ADMIN]],
    'barangay-overview' => ['file' => 'pages/barangay-overview.php', 'roles' => [ROLE_ADMIN]],
    'activity-logs' => ['file' => 'pages/activity-logs.php', 'roles' => [ROLE_ADMIN]],
    
    // Superadmin pages (can access everything)
    'superadmin-dashboard' => ['file' => 'pages/superadmin-dashboard.php', 'roles' => [ROLE_SUPERADMIN]],
    'admin-management' => ['file' => 'pages/admin-management.php', 'roles' => [ROLE_SUPERADMIN]],
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

$page_file = __DIR__ . '/' . ($pages[$nav]['file'] ?? '');
$page_config = $pages[$nav] ?? null;

// Check if page exists in routing map
if (!$page_config || !file_exists($page_file)) {
    http_response_code(404);
    $pageTitle = 'Not Found';
    require_once __DIR__ . '/public/header.php';
    echo '<div class="container mt-5"><h1>Page Not Found</h1></div>';
    require_once __DIR__ . '/public/footer.php';
    exit;
}

// Check if page requires authentication
if ($page_config['roles'] !== null) {
    // Page is restricted - require login
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . WEB_ROOT . '/index.php');
        exit;
    }
    
    // Check if user has required role
    $user_role = current_user_role();
    if (!in_array($user_role, $page_config['roles'], true)) {
        // User doesn't have permission - redirect to their dashboard
        redirect_to_dashboard();
    }
}

// Include the page
include $page_file;
    require_once __DIR__ . '/public/footer.php';
}