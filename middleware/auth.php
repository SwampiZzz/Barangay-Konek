<?php
require_once __DIR__ . '/../config.php';

function require_login() {
    if (empty($_SESSION['user_id'])) {
        header('Location: index.php?nav=login');
        exit;
    }
}

function require_role($allowed_roles) {
    if (empty($_SESSION['user_id'])) {
        header('Location: index.php?nav=login');
        exit;
    }
    $role = intval($_SESSION['role'] ?? 0);
    if (!in_array($role, (array)$allowed_roles, true)) {
        http_response_code(403);
        die('Access Denied: insufficient privileges.');
    }
}

function get_user_profile($user_id = null) {
    global $conn;
    $user_id = $user_id ?? current_user_id();
    $stmt = $conn->prepare('SELECT p.*, u.username FROM profile p JOIN users u ON p.user_id = u.id WHERE p.user_id = ? LIMIT 1');
    if (!$stmt) return null;
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $profile = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $profile;
}

function get_user_barangay() {
    $profile = get_user_profile();
    return $profile['barangay_id'] ?? 0;
}

// If this file is executed directly, act as an AJAX auth endpoint (login/register).
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($username === '' || $password === '') {
            echo json_encode(['success' => false, 'message' => 'Missing credentials.']);
            exit;
        }
        $res = login_user($username, $password);
        if ($res['success']) {
            echo json_encode(['success' => true, 'message' => 'Login successful']);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => $res['message'] ?? 'Invalid credentials']);
            exit;
        }
    } elseif ($action === 'register') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $suffix = trim($_POST['suffix'] ?? '');
        $sex_id = intval($_POST['sex_id'] ?? 0) ?: null;
        $email = trim($_POST['email'] ?? '');
        $contact_number = trim($_POST['contact_number'] ?? '');
        $birthdate = trim($_POST['birthdate'] ?? null);

        if ($username === '' || $password === '' || $password_confirm === '') {
            echo json_encode(['success' => false, 'message' => 'Please fill required fields.']);
            exit;
        }
        if ($password !== $password_confirm) {
            echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
            exit;
        }

        // Create user
        $reg = register_user($username, $password, ROLE_USER);
        if (!$reg['success']) {
            echo json_encode(['success' => false, 'message' => $reg['message']]);
            exit;
        }
        $user_id = intval($reg['user_id']);

        // Insert profile (types: last, first, suffix, sex_id, email, contact_number, birthdate, user_id)
        $stmt = $conn->prepare('INSERT INTO profile (last_name, first_name, suffix, sex_id, email, contact_number, birthdate, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'DB error preparing profile insert.']);
            exit;
        }
        $types = 'sssisssi';
        $stmt->bind_param($types, $last_name, $first_name, $suffix, $sex_id, $email, $contact_number, $birthdate, $user_id);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            echo json_encode(['success' => false, 'message' => 'Failed to save profile: ' . $err]);
            exit;
        }
        $stmt->close();

        activity_log($user_id, 'Registered new account', 'users', $user_id);

        echo json_encode(['success' => true, 'message' => 'Registration successful']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}
