<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/middleware/auth.php';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $role = current_user_role();
    $is_verified = is_user_verified($user_id);
    
    echo \"User ID: $user_id\n\";
    echo \"Role: $role (ROLE_USER=\" . ROLE_USER . \")\n\";
    echo \"Is Verified: \" . ($is_verified ? 'YES' : 'NO') . \"\n\";
    
    // Check database directly
    $stmt = $conn->prepare('SELECT verification_status_id FROM user_verification WHERE user_id = ? LIMIT 1');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    echo \"Verification status from DB: \" . ($row ? $row['verification_status_id'] : 'NULL') . \"\n\";
} else {
    echo \"Not logged in\n\";
}
