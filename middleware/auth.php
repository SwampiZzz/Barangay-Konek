<?php
// Log errors to file for debugging
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../storage/logs/auth_errors.log');
ini_set('display_errors', '1');
error_reporting(E_ALL);

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
        try {
            $username = trim($_POST['username'] ?? '');
            $password = ($_POST['password'] ?? '');
            $password_confirm = ($_POST['password_confirm'] ?? '');
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $suffix = trim($_POST['suffix'] ?? '') ?: '';  // empty string instead of null
            $sex_id = intval($_POST['sex_id'] ?? 0);  // use 0 instead of null
            if ($sex_id === 0) $sex_id = null;  // but convert 0 to null for database
            $email = trim($_POST['email'] ?? '');
            $contact_number = trim($_POST['contact_number'] ?? '');
            $birthdate = trim($_POST['birthdate'] ?? '') ?: null;
            $barangay_id = intval($_POST['barangay_id'] ?? 0) ?: null;

            if ($username === '' || $password === '' || $password_confirm === '' || !$birthdate) {
                echo json_encode(['success' => false, 'message' => 'Please fill required fields.']);
                exit;
            }
            
            if ($password !== $password_confirm) {
                echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
                exit;
            }

            // Validate birthdate (must be 18+)
            if ($birthdate) {
                $birthDateTime = DateTime::createFromFormat('Y-m-d', $birthdate);
                if ($birthDateTime === false) {
                    echo json_encode(['success' => false, 'message' => 'Invalid birthdate format.']);
                    exit;
                }
                
                $today = new DateTime();
                $age = $today->diff($birthDateTime)->y;
                
                if ($age < 18) {
                    echo json_encode(['success' => false, 'message' => 'You must be at least 18 years old to register.']);
                    exit;
                }
            }

            // Validate contact number format (09xx-xxx-xxxx)
            if ($contact_number && !preg_match('/^09\d{2}-\d{3}-\d{4}$/', $contact_number)) {
                echo json_encode(['success' => false, 'message' => 'Contact number must follow format: 09XX-XXX-XXXX']);
                exit;
            }

            // Validate email uniqueness
            if ($email) {
                global $conn;
                $stmt = $conn->prepare('SELECT id FROM profile WHERE email = ?');
                if (!$stmt) {
                    throw new Exception('DB prepare error: ' . $conn->error);
                }
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'This email is already registered.']);
                    exit;
                }
                $stmt->close();
            }

            // Create user
            $reg = register_user($username, $password, ROLE_USER);
            if (!$reg['success']) {
                echo json_encode($reg);
                exit;
            }
            
            $user_id = intval($reg['user_id']);

            // Insert profile with barangay_id
            global $conn;
            
            // Build values for insertion
            $last_name_val = !empty($last_name) ? "'" . $conn->real_escape_string($last_name) . "'" : 'NULL';
            $first_name_val = !empty($first_name) ? "'" . $conn->real_escape_string($first_name) . "'" : 'NULL';
            $suffix_val = !empty($suffix) ? "'" . $conn->real_escape_string($suffix) . "'" : 'NULL';
            $sex_id_val = ($sex_id && $sex_id > 0) ? intval($sex_id) : 'NULL';
            $email_val = !empty($email) ? "'" . $conn->real_escape_string($email) . "'" : 'NULL';
            $contact_number_val = !empty($contact_number) ? "'" . $conn->real_escape_string($contact_number) . "'" : 'NULL';
            $birthdate_val = !empty($birthdate) ? "'" . $conn->real_escape_string($birthdate) . "'" : 'NULL';
            $barangay_id_val = ($barangay_id && $barangay_id > 0) ? intval($barangay_id) : 'NULL';
            
            error_log("Inserting profile: user_id=$user_id, last_name=$last_name_val, first_name=$first_name_val, email=$email_val, barangay_id=$barangay_id_val");
            
            // Use direct SQL with escaped values
            $sql = "INSERT INTO profile (user_id, last_name, first_name, suffix, sex_id, email, contact_number, birthdate, barangay_id) VALUES ($user_id, $last_name_val, $first_name_val, $suffix_val, $sex_id_val, $email_val, $contact_number_val, $birthdate_val, $barangay_id_val)";
            
            if (!$conn->query($sql)) {
                error_log('Profile insert error: ' . $conn->error);
                throw new Exception('Profile insert error: ' . $conn->error);
            }
            
            error_log('Profile inserted successfully for user_id=' . $user_id);

            // Log activity (but don't fail if this errors)
            @activity_log($user_id, 'Registered new account', 'users', $user_id);

            echo json_encode(['success' => true, 'message' => 'Registration successful']);
            exit;
        } catch (Exception $e) {
            error_log('Registration error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'An error occurred during registration. Please try again.']);
            exit;
        }
    }

    // Unknown action
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}
