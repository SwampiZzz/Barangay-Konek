<?php
/**
 * Access Control Middleware
 * Handles role-based access control and barangay-specific data filtering
 */

// Ensure user is logged in
if (empty($_SESSION['user_id'])) {
    header('Location: ' . WEB_ROOT . '/index.php');
    exit;
}

$current_user_id = current_user_id();
$current_role = current_user_role();
$current_barangay_id = current_user_barangay_id();

/**
 * Get the barangay filter clause for SQL queries
 * - Superadmin: no filter (can see all barangays)
 * - Others: filter by their barangay_id
 */
function get_barangay_filter($table_alias = '') {
    if (is_superadmin()) {
        return '1=1'; // No filter for superadmin
    }
    
    $barangay_id = current_user_barangay_id();
    $field = $table_alias ? "{$table_alias}.barangay_id" : 'barangay_id';
    return "{$field} = " . intval($barangay_id);
}

/**
 * Prepare a WHERE clause that includes barangay filter
 * Usage: get_barangay_where_clause('b') for table alias 'b'
 */
function get_barangay_where_clause($table_alias = '') {
    $filter = get_barangay_filter($table_alias);
    return $filter ? "WHERE " . $filter : '';
}

/**
 * Check if user can access a specific barangay's data
 */
function user_can_access_barangay($barangay_id) {
    return can_access_barangay($barangay_id);
}

/**
 * Get user's barangay ID
 */
function get_user_barangay_id() {
    return current_user_barangay_id();
}

/**
 * Check if current user is admin for their barangay
 */
function user_is_barangay_admin() {
    return is_admin() || is_superadmin();
}

/**
 * Check if current user is staff or higher in their barangay
 */
function user_is_staff_or_higher() {
    return is_staff() || is_admin() || is_superadmin();
}

/**
 * Verify user has access to view data from a specific barangay
 * If not, redirect to dashboard
 */
function require_barangay_access($barangay_id) {
    if (!user_can_access_barangay($barangay_id)) {
        redirect_to_dashboard();
    }
}
