<?php
// config.php - Database connection, helpers, and authentication utilities
// Minimal secure-ish helper collection for Barangay Konek

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine web root (useful for asset URLs). When the app is in a subfolder
// (e.g. http://localhost/barangay-konek), this will be '/barangay-konek'.
// If at web root, dirname may return '/' — keep that as empty string so
// links like '/public/...' won't incorrectly point outside the project.
if (!defined('WEB_ROOT')) {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $scriptDir = rtrim($scriptDir, '/');
    if ($scriptDir === '') {
        // Running at web root
        define('WEB_ROOT', '');
    } else {
        define('WEB_ROOT', $scriptDir);
    }
}

// Database credentials - adjust for your local XAMPP/MySQL setup
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'barangay_konek');

// Create mysqli connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_errno) {
    http_response_code(500);
    die('Database connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// Role constants (match database usertype table)
define('ROLE_SUPERADMIN', 1);
define('ROLE_ADMIN', 2);
define('ROLE_STAFF', 3);
define('ROLE_USER', 4);

// Helper: run prepared statements and return result
function db_query($sql, $types = '', $params = []) {
    global $conn;
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("DB prepare failed: " . $conn->error . " -- SQL: " . $sql);
        return false;
    }
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        error_log("DB execute failed: " . $stmt->error);
        return false;
    }
    $res = $stmt->get_result();
    $stmt->close();
    return $res;
}

// Lightweight flash messages
function flash_set($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}
function flash_get() {
    $m = $_SESSION['flash_message'] ?? '';
    $t = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    return ['message' => $m, 'type' => $t];
}

// Simple HTML esc
function e($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

// Authentication: Note - system uses SHA256 for password hashing per project requirement.
// In a production system, prefer password_hash() / password_verify().
function hash_password_sha256($password) {
    return hash('sha256', (string)$password);
}

function register_user($username, $password, $usertype_id = ROLE_USER) {
    global $conn;
    $username = trim($username);
    if ($username === '' || $password === '') return ['success' => false, 'message' => 'Invalid username or password.'];

    // check existing
    $res = db_query('SELECT id FROM users WHERE username = ?', 's', [$username]);
    if ($res && $res->num_rows > 0) return ['success' => false, 'message' => 'Username already exists.'];

    $pw = hash_password_sha256($password);
    $stmt = $conn->prepare('INSERT INTO users (username, password_hash, usertype_id) VALUES (?, ?, ?)');
    if (!$stmt) return ['success' => false, 'message' => 'DB prepare failed.'];
    $stmt->bind_param('ssi', $username, $pw, $usertype_id);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        return ['success' => false, 'message' => 'DB error: ' . $err];
    }
    $user_id = $stmt->insert_id;
    $stmt->close();
    return ['success' => true, 'user_id' => $user_id];
}

function login_user($username, $password) {
    global $conn;
    $username = trim($username);
    $pw = hash_password_sha256($password);
    $stmt = $conn->prepare('SELECT id, username, usertype_id FROM users WHERE username = ? AND password_hash = ? AND deleted_at IS NULL LIMIT 1');
    if (!$stmt) return ['success' => false, 'message' => 'DB prepare failed.'];
    $stmt->bind_param('ss', $username, $pw);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
        $user = $res->fetch_assoc();
        // set session
        $_SESSION['user_id'] = intval($user['id']);
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = intval($user['usertype_id']);
        return ['success' => true, 'user' => $user];
    }
    return ['success' => false, 'message' => 'Invalid credentials.'];
}

function logout_user() {
    session_unset();
    session_destroy();
}

function require_login() {
    if (empty($_SESSION['user_id'])) {
        header('Location: index.php?nav=login');
        exit;
    }
}

function current_user_id() {
    return intval($_SESSION['user_id'] ?? 0);
}

function current_user_role() {
    return intval($_SESSION['role'] ?? 0);
}

function require_role(array $allowed) {
    $role = current_user_role();
    if (!in_array($role, $allowed, true)) {
        http_response_code(403);
        die('Forbidden: insufficient privileges.');
    }
}

// Activity log helper
function activity_log($user_id, $action, $reference_table = null, $reference_id = null) {
    global $conn;
    $stmt = $conn->prepare('INSERT INTO activity_log (user_id, action, reference_table, reference_id) VALUES (?, ?, ?, ?)');
    if (!$stmt) return false;
    $stmt->bind_param('issi', $user_id, $action, $reference_table, $reference_id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

// Safe file upload helper - stores in storage/app/private/requests
function ensure_storage_dir() {
    $base = __DIR__ . '/storage/app/private/requests';
    if (!is_dir($base)) {
        mkdir($base, 0775, true);
    }
    return $base;
}

function save_uploaded_file(array $file, $filename_prefix = '') {
    $dir = ensure_storage_dir();
    $orig = basename($file['name']);
    $ext = pathinfo($orig, PATHINFO_EXTENSION);
    $name = $filename_prefix . time() . '_' . bin2hex(random_bytes(6)) . ($ext ? '.' . $ext : '');
    $dest = $dir . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return false;
    }
    // return relative path to storage
    return 'storage/app/private/requests/' . $name;
}

// Close tag intentionally omitted
