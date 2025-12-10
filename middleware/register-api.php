<?php
// Log errors to file for debugging
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../storage/logs/api_errors.log');

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$action = trim($_GET['action'] ?? '');

if ($action === 'get_provinces') {
    $res = db_query('SELECT id, name FROM province ORDER BY name ASC');
    if ($res) {
        $provinces = [];
        while ($row = $res->fetch_assoc()) {
            $provinces[] = $row;
        }
        echo json_encode(['success' => true, 'provinces' => $provinces]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch provinces']);
    }
    exit;
}

if ($action === 'get_cities') {
    $province_id = intval($_GET['province_id'] ?? 0);
    if (!$province_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid province']);
        exit;
    }
    
    $res = db_query('SELECT id, name FROM city WHERE province_id = ? ORDER BY name ASC', 'i', [$province_id]);
    if ($res) {
        $cities = [];
        while ($row = $res->fetch_assoc()) {
            $cities[] = $row;
        }
        echo json_encode(['success' => true, 'cities' => $cities]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch cities']);
    }
    exit;
}

if ($action === 'get_barangays') {
    $city_id = intval($_GET['city_id'] ?? 0);
    if (!$city_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid city']);
        exit;
    }
    
    // Only show barangays that have an admin user registered
    // Check if there's a user with role=2 (admin) whose profile.barangay_id matches
    $sql = 'SELECT DISTINCT b.id, b.name 
            FROM barangay b
            INNER JOIN profile p ON b.id = p.barangay_id
            INNER JOIN users u ON p.user_id = u.id
            WHERE b.city_id = ? 
            AND b.deleted_at IS NULL 
            AND u.usertype_id = 2
            AND u.deleted_at IS NULL
            ORDER BY b.name ASC';
    
    $res = db_query($sql, 'i', [$city_id]);
    if ($res) {
        $barangays = [];
        while ($row = $res->fetch_assoc()) {
            $barangays[] = ['id' => $row['id'], 'name' => $row['name']];
        }
        echo json_encode(['success' => true, 'barangays' => $barangays]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch barangays']);
    }
    exit;
}

if ($action === 'check_email') {
    $email = trim($_GET['email'] ?? '');
    if (!$email) {
        echo json_encode(['success' => false, 'message' => 'Email is required']);
        exit;
    }
    
    $res = db_query('SELECT id FROM profile WHERE email = ?', 's', [$email]);
    if ($res && $res->num_rows > 0) {
        echo json_encode(['success' => false, 'exists' => true]);
    } else {
        echo json_encode(['success' => true, 'exists' => false]);
    }
    exit;
}

// Unknown action
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unknown action']);
exit;
