<?php
require_once __DIR__ . '/../config.php';

// This file is ONLY for AJAX auth endpoints (login/register).
// Other pages should use helper functions like require_login() and require_role() from config.php instead.

// Only execute if this file is being requested directly as an endpoint (not included by other pages)
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME']) ?? '') {
    // Set response type
    header('Content-Type: application/json; charset=utf-8');

    // Only allow POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    $action = trim($_POST['action'] ?? '');

    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = ($_POST['password'] ?? '');
        
        if ($username === '' || $password === '') {
            echo json_encode(['success' => false, 'message' => 'Missing credentials.']);
            exit;
        }
        
        $res = login_user($username, $password);
        if ($res['success']) {
            // Get the user's role from session (set by login_user)
            $role = intval($_SESSION['role'] ?? ROLE_USER);
            $res['role'] = $role;
        }
        echo json_encode($res);
        exit;
    }

    if ($action === 'register') {
        $username = trim($_POST['username'] ?? '');
        $password = ($_POST['password'] ?? '');
        $password_confirm = ($_POST['password_confirm'] ?? '');
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
            echo json_encode($reg);
            exit;
        }
        
        $user_id = intval($reg['user_id']);

        // Insert profile
        global $conn;
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

    // Unknown action
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}
