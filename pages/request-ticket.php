<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware/auth.php';

require_login();

$request_id = intval($_GET['id'] ?? 0);
if ($request_id <= 0) {
    flash_set('Invalid request ID.', 'error');
    header('Location: index.php?nav=manage-requests');
    exit;
}

$user_id = current_user_id();
$user_role = current_user_role();
$barangay_id = current_user_barangay_id();

// Debug: Check if barangay_id is being retrieved for staff/admin
if (in_array($user_role, [ROLE_STAFF, ROLE_ADMIN]) && $barangay_id === null) {
    flash_set('Error: Your account is not assigned to a barangay. Contact administrator.', 'error');
    header('Location: index.php?nav=' . ($user_role === ROLE_STAFF ? 'staff-dashboard' : 'admin-dashboard'));
    exit;
}

// Fetch request details
$stmt = $conn->prepare('
    SELECT r.*, dt.name as doc_type, dt.description as doc_type_desc, rs.name as status_name, 
           u.username, p.first_name, p.middle_name, p.last_name, p.suffix, p.email, 
           p.contact_number, p.birthdate, b.name as barangay_name, s.name as sex_name
    FROM request r
    LEFT JOIN document_type dt ON r.document_type_id = dt.id
    LEFT JOIN request_status rs ON r.request_status_id = rs.id
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN profile p ON u.id = p.user_id
    LEFT JOIN sex s ON p.sex_id = s.id
    LEFT JOIN barangay b ON r.barangay_id = b.id
    WHERE r.id = ?
');
if (!$stmt) die('DB prepare failed');
$stmt->bind_param('i', $request_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    flash_set('Request not found.', 'error');
    header('Location: index.php?nav=manage-requests');
    exit;
}
$request = $result->fetch_assoc();
$stmt->close();

// Access control
if ($user_role === ROLE_USER) {
    if ($request['user_id'] != $user_id) {
        http_response_code(403);
        die('You do not have permission to view this request.');
    }
} else if (in_array($user_role, [ROLE_STAFF, ROLE_ADMIN])) {
    // Check if user has a barangay assigned
    if ($barangay_id === null) {
        http_response_code(403);
        die('Your staff/admin account is not assigned to a barangay. Contact your administrator.');
    }
    // Check if request belongs to user's barangay
    if (intval($request['barangay_id']) !== intval($barangay_id)) {
        http_response_code(403);
        die('You can only view requests from your barangay.');
    }
} else if ($user_role === ROLE_SUPERADMIN) {
    // Superadmin can view all requests
    // No restrictions
}

// Fetch requirement submissions
$requirement_submissions = [];
$req_stmt = $conn->prepare('
    SELECT drs.*, dr.label, dr.requirement_type, dr.field_type, dr.is_required
    FROM document_requirement_submission drs
    JOIN document_requirement dr ON drs.requirement_id = dr.id
    WHERE drs.request_id = ?
    ORDER BY dr.sort_order, dr.id
');
if ($req_stmt) {
    $req_stmt->bind_param('i', $request_id);
    $req_stmt->execute();
    $req_result = $req_stmt->get_result();
    while ($row = $req_result->fetch_assoc()) {
        $requirement_submissions[] = $row;
    }
    $req_stmt->close();
}

// Fetch attachments
$attachments = [];
$res = db_query('SELECT id, name, file_path, uploaded_at FROM requested_document WHERE request_id = ? AND deleted_at IS NULL ORDER BY uploaded_at DESC', 'i', [$request_id]);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $attachments[] = $row;
    }
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    
    // STAFF ACTIONS
    if ($user_role === ROLE_STAFF) {
        if ($action === 'claim' && intval($request['request_status_id']) === 1) {
            $stmt = $conn->prepare('UPDATE request SET claimed_by = ? WHERE id = ? AND request_status_id = 1');
            if ($stmt) {
                $stmt->bind_param('ii', $user_id, $request_id);
                if ($stmt->execute()) {
                    activity_log($user_id, 'Claimed request', 'request', $request_id);
                    flash_set('Request claimed successfully.', 'success');
                } else {
                    flash_set('Failed to claim request.', 'error');
                }
                $stmt->close();
            }
        } else if ($action === 'unclaim' && intval($request['request_status_id']) === 1 && intval($request['claimed_by']) === $user_id) {
            $stmt = $conn->prepare('UPDATE request SET claimed_by = NULL WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $request_id);
                if ($stmt->execute()) {
                    activity_log($user_id, 'Unclaimed request', 'request', $request_id);
                    flash_set('Request unclaimed.', 'success');
                } else {
                    flash_set('Failed to unclaim request.', 'error');
                }
                $stmt->close();
            }
        } else if ($action === 'approve' && intval($request['request_status_id']) === 1) {
            // Validate file upload
            if (empty($_FILES['processed_document']['name'])) {
                flash_set('Please upload the processed document before approving.', 'error');
            } else {
                $upload_dir = __DIR__ . '/../storage/app/private/requests/';
                $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
                $max_file_size = 10 * 1024 * 1024; // 10MB
                
                $file_name = $_FILES['processed_document']['name'];
                $file_tmp = $_FILES['processed_document']['tmp_name'];
                $file_error = $_FILES['processed_document']['error'];
                $file_size = $_FILES['processed_document']['size'];
                
                if ($file_error !== UPLOAD_ERR_OK) {
                    flash_set('File upload error occurred.', 'error');
                } else if ($file_size > $max_file_size) {
                    flash_set('File exceeds 10MB limit.', 'error');
                } else {
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    if (!in_array($file_ext, $allowed_extensions)) {
                        flash_set('Invalid file type. Only PDF, JPG, PNG allowed.', 'error');
                    } else {
                        // Save file
                        $unique_name = 'processed_' . $request_id . '_' . time() . '_' . uniqid() . '.' . $file_ext;
                        $file_path = 'storage/app/private/requests/' . $unique_name;
                        
                        if (move_uploaded_file($file_tmp, $upload_dir . $unique_name)) {
                            $stmt = $conn->prepare('UPDATE request SET request_status_id = 2, document_path = ?, updated_at = NOW() WHERE id = ?');
                            if ($stmt) {
                                $stmt->bind_param('si', $file_path, $request_id);
                                if ($stmt->execute()) {
                                    activity_log($user_id, 'Approved request and uploaded processed document', 'request', $request_id);
                                    notify_admin($barangay_id, 'Request Approved', 'Request #' . str_pad($request_id, 5, '0', STR_PAD_LEFT) . ' has been approved.');
                                    notify_resident($request['user_id'], 'Request Approved', 'Your request #' . str_pad($request_id, 5, '0', STR_PAD_LEFT) . ' has been approved.');
                                    flash_set('Request approved successfully and forwarded to admin.', 'success');
                                } else {
                                    flash_set('Failed to approve request.', 'error');
                                    unlink($upload_dir . $unique_name);
                                }
                                $stmt->close();
                            }
                        } else {
                            flash_set('Failed to save uploaded file.', 'error');
                        }
                    }
                }
            }
        } else if ($action === 'reject' && intval($request['request_status_id']) === 1) {
            $remarks = trim($_POST['remarks'] ?? '');
            if (empty($remarks)) {
                flash_set('Please provide a reason for rejection.', 'error');
            } else {
                $stmt = $conn->prepare('UPDATE request SET request_status_id = 3, remarks = ?, updated_at = NOW() WHERE id = ?');
                if ($stmt) {
                    $stmt->bind_param('si', $remarks, $request_id);
                    if ($stmt->execute()) {
                        activity_log($user_id, 'Rejected request', 'request', $request_id);
                        notify_resident($request['user_id'], 'Request Rejected', 'Your request needs revision. Please resubmit.');
                        flash_set('Request rejected. Resident has been notified.', 'success');
                    } else {
                        flash_set('Failed to reject request.', 'error');
                    }
                    $stmt->close();
                }
            }
        }
    }
    
    // ADMIN ACTIONS
    else if ($user_role === ROLE_ADMIN) {
        if ($action === 'complete' && intval($request['request_status_id']) === 2) {
            // Admin can complete with optional final PDF upload.
            if (!empty($_FILES['final_document']['name'])) {
                $upload_dir = __DIR__ . '/../storage/app/private/requests/';
                $allowed_extensions = ['pdf'];
                $max_file_size = 10 * 1024 * 1024; // 10MB
                
                $file_name = $_FILES['final_document']['name'];
                $file_tmp = $_FILES['final_document']['tmp_name'];
                $file_error = $_FILES['final_document']['error'];
                $file_size = $_FILES['final_document']['size'];
                
                if ($file_error !== UPLOAD_ERR_OK) {
                    flash_set('File upload error occurred.', 'error');
                } else if ($file_size > $max_file_size) {
                    flash_set('File exceeds 10MB limit.', 'error');
                } else {
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    if (!in_array($file_ext, $allowed_extensions)) {
                        flash_set('Final document must be in PDF format.', 'error');
                    } else {
                        // Delete old processed document when new final document is uploaded
                        if (!empty($request['document_path'])) {
                            $old_file = __DIR__ . '/../' . $request['document_path'];
                            if (file_exists($old_file)) {
                                unlink($old_file);
                            }
                        }
                        
                        // Save new final document
                        $unique_name = 'final_' . $request_id . '_' . time() . '_' . uniqid() . '.pdf';
                        $file_path = 'storage/app/private/requests/' . $unique_name;
                        
                        if (move_uploaded_file($file_tmp, $upload_dir . $unique_name)) {
                            $stmt = $conn->prepare('UPDATE request SET request_status_id = 4, document_path = ?, updated_at = NOW() WHERE id = ?');
                            if ($stmt) {
                                $stmt->bind_param('si', $file_path, $request_id);
                                if ($stmt->execute()) {
                                    activity_log($user_id, 'Completed request - Final document uploaded', 'request', $request_id);
                                    notify_resident($request['user_id'], 'Request Completed', 'Your request #' . str_pad($request_id, 5, '0', STR_PAD_LEFT) . ' is complete and ready for download.');
                                    flash_set('Request marked as completed. User can now download the document.', 'success');
                                } else {
                                    flash_set('Failed to complete request.', 'error');
                                    unlink($upload_dir . $unique_name);
                                }
                                $stmt->close();
                            }
                        } else {
                            flash_set('Failed to save uploaded file.', 'error');
                        }
                    }
                }
            } else {
                // No new upload; use staff-processed document as final.
                if (!empty($request['document_path'])) {
                    $stmt = $conn->prepare('UPDATE request SET request_status_id = 4, updated_at = NOW() WHERE id = ?');
                    if ($stmt) {
                        $stmt->bind_param('i', $request_id);
                        if ($stmt->execute()) {
                            activity_log($user_id, 'Completed request - Using staff processed document as final', 'request', $request_id);
                            notify_resident($request['user_id'], 'Request Completed', 'Your request #' . str_pad($request_id, 5, '0', STR_PAD_LEFT) . ' is complete and ready for download.');
                            flash_set('Request completed. Staff-processed document used as final.', 'success');
                        } else {
                            flash_set('Failed to complete request.', 'error');
                        }
                        $stmt->close();
                    }
                } else {
                    flash_set('No document available to finalize. Please upload the final PDF.', 'error');
                }
            }
        } else if ($action === 'reject_approved' && intval($request['request_status_id']) === 2) {
            $remarks = trim($_POST['remarks'] ?? '');
            if (empty($remarks)) {
                flash_set('Please provide a reason.', 'error');
            } else {
                // Delete the processed document uploaded by staff (file replacement rule)
                if (!empty($request['document_path'])) {
                    $old_file = __DIR__ . '/../' . $request['document_path'];
                    if (file_exists($old_file)) {
                        unlink($old_file);
                    }
                }
                
                $stmt = $conn->prepare('UPDATE request SET request_status_id = 1, remarks = ?, claimed_by = NULL, document_path = NULL, updated_at = NOW() WHERE id = ?');
                if ($stmt) {
                    $stmt->bind_param('si', $remarks, $request_id);
                    if ($stmt->execute()) {
                        activity_log($user_id, 'Rejected approved request - Returned to staff', 'request', $request_id);
                        notify_staff($barangay_id, 'Request Returned', 'Request #' . str_pad($request_id, 5, '0', STR_PAD_LEFT) . ' returned to pending.');
                        flash_set('Request returned to staff for reprocessing.', 'success');
                    } else {
                        flash_set('Failed to return request.', 'error');
                    }
                    $stmt->close();
                }
            }
        }
    }
    
    // RESIDENT ACTIONS
    else if ($user_role === ROLE_USER) {
        if ($action === 'submit_revision' && intval($request['request_status_id']) === 3) {
            $upload_dir = __DIR__ . '/../storage/app/private/requests/';
            $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
            $max_file_size = 5 * 1024 * 1024;
            
            $has_errors = false;
            $has_changes = false;
            
            // Process requirement submissions for updates
            foreach ($requirement_submissions as $req_sub) {
                if ($req_sub['submission_type'] === 'file') {
                    $field_key = 'requirement_' . $req_sub['requirement_id'];
                    
                    // Check if user uploaded a replacement file
                    if (isset($_FILES[$field_key]) && $_FILES[$field_key]['error'] !== UPLOAD_ERR_NO_FILE) {
                        $file_name = $_FILES[$field_key]['name'];
                        $file_tmp = $_FILES[$field_key]['tmp_name'];
                        $file_error = $_FILES[$field_key]['error'];
                        $file_size = $_FILES[$field_key]['size'];
                        
                        if ($file_error !== UPLOAD_ERR_OK) {
                            flash_set("File upload error for {$req_sub['label']}.", 'error');
                            $has_errors = true;
                            continue;
                        }
                        if ($file_size > $max_file_size) {
                            flash_set("{$req_sub['label']} exceeds 5MB limit.", 'error');
                            $has_errors = true;
                            continue;
                        }
                        
                        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        if (!in_array($file_ext, $allowed_extensions)) {
                            flash_set("Invalid file type for {$req_sub['label']}.", 'error');
                            $has_errors = true;
                            continue;
                        }
                        
                        // Delete old file (FILE REPLACEMENT RULE)
                        if (!empty($req_sub['file_path'])) {
                            $old_file = __DIR__ . '/../' . $req_sub['file_path'];
                            if (file_exists($old_file)) {
                                unlink($old_file);
                            }
                        }
                        
                        // Upload new file
                        $unique_name = 'req_' . $request_id . '_' . $req_sub['requirement_id'] . '_' . time() . '_' . uniqid() . '.' . $file_ext;
                        $file_path = 'storage/app/private/requests/' . $unique_name;
                        
                        if (move_uploaded_file($file_tmp, $upload_dir . $unique_name)) {
                            // Update submission
                            $update_stmt = $conn->prepare('UPDATE document_requirement_submission SET file_name = ?, file_path = ?, file_type = ?, submitted_at = NOW() WHERE id = ?');
                            if ($update_stmt) {
                                $update_stmt->bind_param('sssi', $file_name, $file_path, $file_ext, $req_sub['id']);
                                if ($update_stmt->execute()) {
                                    $has_changes = true;
                                }
                                $update_stmt->close();
                            }
                        } else {
                            flash_set("Failed to upload file for {$req_sub['label']}.", 'error');
                            $has_errors = true;
                        }
                    }
                } else if ($req_sub['submission_type'] === 'text') {
                    $field_key = 'requirement_' . $req_sub['requirement_id'];
                    if (isset($_POST[$field_key])) {
                        $new_value_raw = $_POST[$field_key];
                        $new_value = is_string($new_value_raw) ? trim($new_value_raw) : '';
                        if ($new_value === '') {
                            // Blank input: keep previous value (no change)
                            // Do nothing
                        } else if ($new_value !== (string)$req_sub['text_value']) {
                            // Update text value when changed
                            $update_stmt = $conn->prepare('UPDATE document_requirement_submission SET text_value = ?, submitted_at = NOW() WHERE id = ?');
                            if ($update_stmt) {
                                $update_stmt->bind_param('si', $new_value, $req_sub['id']);
                                if ($update_stmt->execute()) {
                                    $has_changes = true;
                                }
                                $update_stmt->close();
                            }
                        }
                    }
                }
            }
            
            if (!$has_errors) {
                if (!$has_changes) {
                    flash_set('Please change at least one field or upload a new file before resubmitting.', 'error');
                } else {
                // Update request back to pending
                $stmt = $conn->prepare('UPDATE request SET request_status_id = 1, remarks = NULL, updated_at = NOW() WHERE id = ?');
                if ($stmt) {
                    $stmt->bind_param('i', $request_id);
                    if ($stmt->execute()) {
                        activity_log($user_id, 'Resubmitted request after revision', 'request', $request_id);
                        notify_staff($barangay_id, 'Request Resubmitted', 'Request #' . str_pad($request_id, 5, '0', STR_PAD_LEFT) . ' has been revised and resubmitted.');
                        flash_set('Request resubmitted successfully and is now under review.', 'success');
                        header("Location: index.php?nav=request-ticket&id=$request_id");
                        exit;
                    } else {
                        flash_set('Failed to update request status.', 'error');
                    }
                    $stmt->close();
                }
                }
            }
        }
    }

    // STAFF cannot revise the processed file after submission (no-op)
    
    if (flash_get('success')) {
        header('Location: index.php?nav=request-ticket&id=' . $request_id);
        exit;
    }
}

$pageTitle = 'Request #' . str_pad($request_id, 5, '0', STR_PAD_LEFT);
require_once __DIR__ . '/../public/header.php';
?>

<div class="container-fluid my-4 px-3 px-md-4">
    <div style="max-width: 1400px; margin-left: auto; margin-right: auto;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="fas fa-file-alt"></i> Request #<?php echo str_pad($request_id, 5, '0', STR_PAD_LEFT); ?></h2>
                <p class="text-muted small mb-0">Document Type: <strong><?php echo e($request['doc_type']); ?></strong></p>
            </div>
            <a href="index.php?nav=manage-requests" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>

        <div class="row g-4">
            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- Request Overview Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        
                        <!-- User Profile & Document Info -->
                        <div class="row align-items-center g-4">
                            <!-- User Profile Section -->
                            <div class="col-lg-8">
                                <div class="d-flex align-items-start gap-3">
                                    <?php 
                                        $requester_id = intval($request['user_id']);
                                        $picDir = __DIR__ . '/../storage/app/private/profile_pics/';
                                        $picWeb = WEB_ROOT . '/storage/app/private/profile_pics/';
                                        $picName = 'user_' . $requester_id . '.jpg';
                                        $picPath = $picDir . $picName;
                                        ?>
                                    <?php if (file_exists($picPath)): ?>
                                        <img src="<?php echo $picWeb . $picName; ?>" alt="Profile" class="rounded-circle shadow-sm" style="width: 70px; height: 70px; object-fit: cover;">
                                        <?php else: ?>
                                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center shadow-sm" style="width: 70px; height: 70px; font-size: 1.8rem; font-weight: bold;">
                                            <?php echo strtoupper(substr($request['first_name'] ?? 'U', 0, 1) . substr($request['last_name'] ?? 'N', 0, 1)); ?>
                                        </div>
                                        <?php endif; ?>
                                        <div class="flex-grow-1">
                                            <h5 class="mb-1 fw-700" style="color: #111827;">
                                                <?php 
                                                $full_name = trim(($request['first_name'] ?? '') . ' ' . ($request['middle_name'] ?? '') . ' ' . ($request['last_name'] ?? '') . ' ' . ($request['suffix'] ?? ''));
                                                echo e($full_name);
                                            ?>
                                        </h5>
                                        <div class="text-muted mb-2" style="font-size: 0.85rem;">
                                            <i class="fas fa-at me-1"></i><?php echo e($request['username']); ?>
                                            <span class="mx-2">•</span>
                                            <i class="fas fa-map-marker-alt me-1"></i><?php echo e($request['barangay_name']); ?>
                                        </div>
                                        
                                        <!-- Contact Details (Compact) -->
                                        <div class="d-flex flex-wrap gap-3 text-muted" style="font-size: 0.8rem;">
                                            <?php if (!empty($request['email'])): ?>
                                                <span><i class="fas fa-envelope me-1"></i><?php echo e($request['email']); ?></span>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($request['contact_number'])): ?>
                                            <span><i class="fas fa-phone me-1"></i><?php echo e($request['contact_number']); ?></span>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($request['birthdate'])): ?>
                                                <span>
                                                <i class="fas fa-birthday-cake me-1"></i>
                                                <?php 
                                                    $age = date_diff(date_create($request['birthdate']), date_create('today'))->y;
                                                    echo $age . ' yrs';
                                                    if (!empty($request['sex_name'])) {
                                                        echo ' • ' . e($request['sex_name']);
                                                    }
                                                ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            
                            <!-- Date & Document Type Section -->
                            <div class="col-lg-4">
                                <div class="text-end mb-3">
                                    <small class="text-muted d-block mb-1" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px;">Submitted</small>
                                    <div class="fw-600" style="color: #374151; font-size: 0.9rem;">
                                        <?php echo date('M d, Y', strtotime($request['created_at'])); ?>
                                    </div>
                                    <small class="text-muted" style="font-size: 0.85rem;"><?php echo date('g:i A', strtotime($request['created_at'])); ?></small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Status Badge -->
                        <div class="d-flex justify-content-end align-items-end mb-4">
                            <div class="text-end">
                                <?php
                                $status_id = intval($request['request_status_id']);
                                $badge = $status_id === 1 ? 'warning' : ($status_id === 2 ? 'info' : ($status_id === 3 ? 'danger' : 'success'));
                                $icon = $status_id === 1 ? 'hourglass-half' : ($status_id === 2 ? 'check-circle' : ($status_id === 3 ? 'times-circle' : 'check-double'));
                                ?>
                                <small class="text-muted d-block mb-1" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px;">Status</small>
                                <span class="badge bg-<?php echo $badge; ?>" style="padding: 0.7rem 1.3rem; font-size: 1rem; font-weight: 600;">
                                    <i class="fas fa-<?php echo $icon; ?> me-2"></i><?php echo e($request['status_name']); ?>
                                </span>
                            </div>
                        </div>
                        <hr class="my-4">
                        
                        <!-- Document Type -->
                        <div class="d-flex align-items-center gap-3 p-3" style="background: linear-gradient(135deg, #f0f4f8 0%, #e8eef5 100%); border-radius: 8px; border-left: 4px solid #0b3d91;">
                            <i class="fas fa-file-alt text-primary" style="font-size: 2rem;"></i>
                            <div>
                                <small class="text-muted d-block mb-1" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px;">Document Type</small>
                                <h6 class="mb-0 fw-700" style="color: #111827;"><?php echo e($request['doc_type']); ?></h6>
                                <?php if (!empty($request['doc_type_desc'])): ?>
                                    <small class="text-muted" style="font-size: 0.8rem;"><?php echo e($request['doc_type_desc']); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($request['remarks']) && $status_id !== 4): ?>
                            <div class="alert alert-warning border-0 shadow-sm mt-4 mb-0" role="alert" style="border-left: 4px solid #ffc107 !important;">
                                <div class="d-flex gap-2">
                                    <i class="fas fa-exclamation-triangle" style="font-size: 1.2rem; color: #856404; margin-top: 2px;"></i>
                                    <div class="flex-grow-1">
                                        <div class="fw-600 mb-1" style="color: #856404; font-size: 0.9rem;">Remarks / Rejection Reason</div>
                                        <div style="color: #856404; font-size: 0.85rem;"><?php echo nl2br(e($request['remarks'])); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>


                <!-- Requirement Submissions Card -->
                <?php if (count($requirement_submissions) > 0 && $status_id !== 4): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-success text-white fw-600">
                        <i class="fas fa-list-check me-2"></i>Submitted Requirements
                    </div>
                    <div class="card-body">
                        <?php foreach ($requirement_submissions as $req_sub): ?>
                            <div class="requirement-item mb-3 p-3" style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px;">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <label class="fw-600 mb-0" style="color: #374151;">
                                        <?php echo htmlspecialchars($req_sub['label']); ?>
                                        <?php if ($req_sub['is_required']): ?>
                                            <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </label>
                                    <span class="badge bg-secondary" style="font-size: 0.75rem;">
                                        <?php echo $req_sub['submission_type'] === 'file' ? 'File Upload' : 'Text Input'; ?>
                                    </span>
                                </div>
                                
                                <?php if ($req_sub['submission_type'] === 'file'): ?>
                                    <div class="file-display d-flex align-items-center gap-3 p-3 mt-2" style="background: white; border-radius: 4px;">
                                        <i class="fas fa-file-pdf text-danger" style="font-size: 1.5rem;"></i>
                                        <div class="flex-grow-1">
                                            <div class="fw-600" style="color: #111827; font-size: 0.9rem;">
                                                <?php echo htmlspecialchars($req_sub['file_name']); ?>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo strtoupper($req_sub['file_type']); ?> • 
                                                Uploaded: <?php echo date('M d, Y h:i A', strtotime($req_sub['submitted_at'])); ?>
                                            </small>
                                        </div>
                                        <a href="<?php echo WEB_ROOT . '/' . $req_sub['file_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i>View
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="text-value p-3 mt-2" style="background: white; border-radius: 4px; border-left: 3px solid #0b3d91;">
                                        <?php echo nl2br(htmlspecialchars($req_sub['text_value'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Documents Card -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-info text-white fw-600">
                        <i class="fas fa-paperclip me-2"></i>Documents
                    </div>
                    <div class="card-body">
                        <?php 
                        $docRelPath = $request['document_path'] ?? '';
                        $docAbsPath = !empty($docRelPath) ? (__DIR__ . '/../' . $docRelPath) : '';
                        $docExists = !empty($docRelPath) && file_exists($docAbsPath);
                        $showProcessed = !empty($docRelPath) && ($user_role !== ROLE_USER || ($user_role === ROLE_USER && $status_id >= 4));
                        $hasAny = $showProcessed || count($attachments) > 0;
                        ?>
                        <?php if ($hasAny): ?>
                            <div class="list-group list-group-flush">
                                <?php if ($showProcessed): ?>
                                    <div class="list-group-item px-0 py-3 d-flex justify-content-between align-items-center border-0 border-bottom">
                                        <div>
                                            <div class="fw-600">
                                                <i class="fas fa-file-signature me-2"></i><?php echo ($status_id >= 4 ? 'Final Document' : 'Processed Document'); ?>
                                            </div>
                                            <small class="text-muted">
                                                <?php if ($status_id >= 4): ?>Ready for download<?php else: ?>Awaiting admin completion<?php endif; ?>
                                            </small>
                                        </div>
                                        <?php if ($docExists): ?>
                                            <div class="d-flex gap-2">
                                                <a href="<?php echo WEB_ROOT . '/' . $docRelPath; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <a href="<?php echo WEB_ROOT . '/' . $docRelPath; ?>" class="btn btn-sm btn-primary" download>
                                                    <i class="fas fa-download"></i> Download
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-exclamation-triangle me-1"></i>File not found</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php foreach ($attachments as $att): ?>
                                    <div class="list-group-item px-0 py-3 d-flex justify-content-between align-items-center border-0 border-bottom">
                                        <div>
                                            <div class="fw-600">
                                                <i class="fas fa-file me-2"></i><?php echo e($att['name']); ?>
                                            </div>
                                            <small class="text-muted">Uploaded: <?php echo date('M d, Y g:i A', strtotime($att['uploaded_at'])); ?></small>
                                        </div>
                                        <a href="<?php echo WEB_ROOT . '/' . e($att['file_path']); ?>" class="btn btn-sm btn-primary" download>
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0"><i class="fas fa-inbox me-2"></i>No documents uploaded yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Actions Sidebar -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm sticky-top" style="top: 20px;">
                    <div class="card-header bg-warning text-dark fw-600">
                        <i class="fas fa-tasks me-2"></i>Actions
                    </div>
                    <div class="card-body">
                        <?php
                        $status_id = intval($request['request_status_id']);
                        $can_act = false;
                        
                        // RESIDENT (completed) - Download document
                        if ($user_role === ROLE_USER && $status_id === 4):
                            $can_act = true;
                        ?>
                            <div class="alert alert-success mb-3">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Request Completed!</strong>
                                <p class="mb-0 mt-0 small">Your document is ready for download.</p>
                            </div>
                        <?php endif;
                        
                        // RESIDENT (rejected)
                        if ($user_role === ROLE_USER && $status_id === 3):
                            $can_act = true;
                        ?>
                            <div class="alert alert-danger mb-3">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Revision Required</strong>
                            </div>
                            <button class="btn btn-warning w-100" data-bs-toggle="modal" data-bs-target="#revisionModal">
                                <i class="fas fa-redo me-2"></i>Submit Revision
                            </button>
                        <?php endif;
                        
                        // STAFF (pending)
                        if ($user_role === ROLE_STAFF && $status_id === 1):
                            $can_act = true;
                            $is_claimed = intval($request['claimed_by']) === $user_id;
                            $is_claimed_other = intval($request['claimed_by']) > 0;
                        ?>
                            <div class="mb-3">
                                <?php if (!$is_claimed_other): ?>
                                    <form method="POST" class="mb-2">
                                        <input type="hidden" name="action" value="claim">
                                        <button type="submit" class="btn btn-success w-100">
                                            <i class="fas fa-hand-paper me-2"></i>Claim Request
                                        </button>
                                    </form>
                                <?php elseif ($is_claimed): ?>
                                    <div class="alert alert-success mb-3 small">
                                        <i class="fas fa-check-circle me-2"></i>Claimed by you
                                    </div>
                                    <form method="POST" class="mb-3">
                                        <input type="hidden" name="action" value="unclaim">
                                        <button type="submit" class="btn btn-secondary w-100">
                                            <i class="fas fa-times me-2"></i>Unclaim
                                        </button>
                                    </form>
                                    <button class="btn btn-success w-100 mb-2" data-bs-toggle="modal" data-bs-target="#approveModal">
                                        <i class="fas fa-check me-2"></i>Approve
                                    </button>
                                    <button class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                        <i class="fas fa-times me-2"></i>Reject
                                    </button>
                                <?php else: ?>
                                    <div class="alert alert-warning small">
                                        <i class="fas fa-exclamation-triangle me-2"></i>Already claimed
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif;
                        
                        // ADMIN (approved)
                        if ($user_role === ROLE_ADMIN && $status_id === 2):
                            $can_act = true;
                        ?>
                            <p class="text-muted small mb-3">Ready for final review and completion.</p>
                            <button class="btn btn-success w-100 mb-2" data-bs-toggle="modal" data-bs-target="#completeModal">
                                <i class="fas fa-check-double me-2"></i>Complete Request
                            </button>
                            <button class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#rejectApprovedModal">
                                <i class="fas fa-arrow-left me-2"></i>Return to Staff
                            </button>
                        <?php endif; ?>

                        <?php 
                        /* Staff can no longer replace processed document after submission */
                        if (!$can_act): ?>
                            <p class="text-muted small mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                <?php 
                                if ($user_role === ROLE_USER && $status_id === 1) {
                                    echo 'Pending review by barangay staff.';
                                } else if ($user_role === ROLE_USER && $status_id === 2) {
                                    echo 'Being finalized by admin.';
                                } else if ($user_role === ROLE_USER && $status_id === 4) {
                                    echo 'Complete! Download your document.';
                                } else {
                                    echo 'No actions available.';
                                }
                                ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Request Timeline -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-header text-white fw-600" style="background: linear-gradient(135deg, #0b3d91 0%, #0d6efd 100%);">
                        <i class="fas fa-tasks me-2"></i>Request Progress Timeline
                    </div>
                    <div class="card-body p-4">
                        <div class="timeline-vertical">
                            <!-- Step 1: Submitted -->
                            <div class="timeline-step completed">
                                <div class="timeline-icon" style="background: linear-gradient(135deg, #6c757d 0%, #495057 100%);">
                                    <i class="fas fa-paper-plane"></i>
                                </div>
                                <div class="timeline-content">
                                    <strong class="d-block" style="color: #111827;">1. Submitted</strong>
                                    <small class="text-muted"><?php echo date('M d, Y • h:i A', strtotime($request['created_at'])); ?></small>
                                    <div class="mt-1">
                                        <span class="badge bg-secondary" style="font-size: 0.65rem;">
                                            <i class="fas fa-check me-1"></i>Complete
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 2: Staff Review -->
                            <div class="timeline-step <?php echo ($status_id === 1 || $status_id === 3) ? 'active' : ($status_id >= 2 ? 'completed' : ''); ?>" style="--status-color: <?php echo $status_id === 3 ? '#dc3545' : '#0dcaf0'; ?>;">
                                <div class="timeline-icon" style="background: <?php echo $status_id === 3 ? 'linear-gradient(135deg, #dc3545 0%, #c82333 100%)' : ($status_id >= 2 ? 'linear-gradient(135deg, #198754 0%, #20c997 100%)' : 'linear-gradient(135deg, #0dcaf0 0%, #0d6efd 100%)'); ?>;">
                                    <i class="fas <?php echo $status_id === 3 ? 'fa-times' : 'fa-user-check'; ?>"></i>
                                </div>
                                <div class="timeline-content">
                                    <strong class="d-block" style="color: #111827;">2. Staff Review</strong>
                                    <small class="text-muted">
                                        <?php if ($status_id === 3): ?>
                                            Resident revising submission
                                        <?php elseif ($status_id === 1): ?>
                                            Awaiting staff review
                                        <?php elseif ($status_id === 2): ?>
                                            Approved and forwarded to admin
                                        <?php elseif ($status_id >= 4): ?>
                                            Review completed
                                        <?php endif; ?>
                                    </small>
                                    <?php if ($status_id >= 2 && $status_id !== 3): ?>
                                        <div class="mt-1">
                                            <span class="badge bg-success" style="font-size: 0.65rem;">
                                                <i class="fas fa-check me-1"></i>Complete
                                            </span>
                                        </div>
                                    <?php elseif ($status_id === 1): ?>
                                        <div class="mt-1">
                                            <span class="badge bg-info" style="font-size: 0.65rem;">
                                                <i class="fas fa-hourglass-half me-1"></i>In Progress
                                            </span>
                                        </div>
                                    <?php elseif ($status_id === 3): ?>
                                        <div class="mt-1">
                                            <span class="badge bg-danger" style="font-size: 0.65rem;">
                                                <i class="fas fa-exclamation-circle me-1"></i>Awaiting Revision
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($status_id === 2 && !empty($request['document_path'])): ?>
                                        <div class="mt-1">
                                            <span class="badge bg-primary" style="font-size: 0.65rem;">
                                                <i class="fas fa-file me-1"></i>Document ready
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Step 3: Admin Review -->
                            <div class="timeline-step <?php echo $status_id === 2 ? 'active' : ($status_id >= 4 ? 'completed' : ''); ?>">
                                <div class="timeline-icon" style="background: <?php echo $status_id >= 4 ? 'linear-gradient(135deg, #198754 0%, #20c997 100%)' : ($status_id === 2 ? 'linear-gradient(135deg, #0dcaf0 0%, #0d6efd 100%)' : 'linear-gradient(135deg, #6c757d 0%, #495057 100%)'); ?>;">
                                    <i class="fas fa-user-shield"></i>
                                </div>
                                <div class="timeline-content">
                                    <strong class="d-block" style="color: #111827;">3. Admin Review</strong>
                                    <small class="text-muted">
                                        <?php if ($status_id < 2): ?>
                                            Waiting for staff approval
                                        <?php elseif ($status_id === 2): ?>
                                            Final review in progress
                                        <?php elseif ($status_id >= 4): ?>
                                            Final check completed
                                        <?php endif; ?>
                                    </small>
                                    <?php if ($status_id >= 4): ?>
                                        <div class="mt-1">
                                            <span class="badge bg-success" style="font-size: 0.65rem;">
                                                <i class="fas fa-check me-1"></i>Complete
                                            </span>
                                        </div>
                                    <?php elseif ($status_id === 2): ?>
                                        <div class="mt-1">
                                            <span class="badge bg-info" style="font-size: 0.65rem;">
                                                <i class="fas fa-hourglass-half me-1"></i>In Progress
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Step 4: Completed -->
                            <div class="timeline-step <?php echo $status_id >= 4 ? 'completed' : ''; ?>">
                                <div class="timeline-icon" style="background: <?php echo $status_id >= 4 ? 'linear-gradient(135deg, #198754 0%, #20c997 100%)' : 'linear-gradient(135deg, #6c757d 0%, #495057 100%)'; ?>;">
                                    <i class="fas fa-<?php echo $status_id >= 4 ? 'check-double' : 'lock'; ?>"></i>
                                </div>
                                <div class="timeline-content">
                                    <strong class="d-block" style="color: #111827;">4. Completed</strong>
                                    <small class="text-muted">
                                        <?php if ($status_id >= 4): ?>
                                            Ready for download
                                        <?php else: ?>
                                            Pending completion
                                        <?php endif; ?>
                                    </small>
                                    <?php if ($status_id >= 4): ?>
                                        <div class="mt-1">
                                            <span class="badge bg-success" style="font-size: 0.65rem;">
                                                <i class="fas fa-check me-1"></i>Complete
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODALS -->
<?php if ($user_role === ROLE_USER && $status_id === 3): ?>
<div class="modal fade" id="revisionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title text-dark"><i class="fas fa-edit me-2"></i>Revise & Resubmit Requirements</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-danger mb-4">
                        <strong><i class="fas fa-exclamation-triangle me-2"></i>Rejection Remarks:</strong>
                        <p class="mb-0 mt-2"><?php echo nl2br(htmlspecialchars($request['remarks'])); ?></p>
                    </div>
                    
                    <p class="mb-3"><strong>Update your requirements below (leave unchanged fields empty):</strong></p>
                    <input type="hidden" name="action" value="submit_revision">
                    
                    <?php foreach ($requirement_submissions as $req_sub): ?>
                        <div class="mb-4 p-3" style="background: #f9fafb; border-radius: 6px; border: 1px solid #e5e7eb;">
                            <label class="form-label fw-600">
                                <?php echo htmlspecialchars($req_sub['label']); ?>
                                <?php if ($req_sub['is_required']): ?>
                                    <span class="text-danger">*</span>
                                <?php endif; ?>
                            </label>
                            
                            <?php if ($req_sub['submission_type'] === 'file'): ?>
                                <div class="mb-2">
                                    <small class="text-muted d-block mb-2">
                                        Current: <?php echo htmlspecialchars($req_sub['file_name']); ?>
                                    </small>
                                    <input type="file" class="form-control" name="requirement_<?php echo $req_sub['requirement_id']; ?>" accept=".pdf,.jpg,.jpeg,.png">
                                    <small class="text-muted">Leave empty to keep current file. Upload to replace.</small>
                                </div>
                            <?php else: ?>
                                <div class="mb-2">
                                    <small class="text-muted d-block mb-2">
                                        Current value: <?php echo htmlspecialchars($req_sub['text_value']); ?>
                                    </small>
                                    <?php if ($req_sub['field_type'] === 'textarea'): ?>
                                        <textarea class="form-control" name="requirement_<?php echo $req_sub['requirement_id']; ?>" rows="3" placeholder="Enter new value or leave empty to keep current"></textarea>
                                    <?php else: ?>
                                        <input type="<?php echo htmlspecialchars($req_sub['field_type'] ?? 'text'); ?>" class="form-control" name="requirement_<?php echo $req_sub['requirement_id']; ?>" placeholder="Enter new value or leave empty to keep current">
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>Resubmit</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($user_role === ROLE_STAFF && $status_id === 1 && intval($request['claimed_by']) === $user_id): ?>
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i>Approve Request</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Required:</strong> Upload the processed document before approving. This will forward the request to admin for final review.
                    </div>
                    <input type="hidden" name="action" value="approve">
                    <div class="mb-3">
                        <label class="form-label fw-600">Processed Document <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" name="processed_document" required accept=".pdf,.jpg,.jpeg,.png">
                        <small class="text-muted">Upload the document you have processed (PDF, JPG, PNG • Max 10MB)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check me-1"></i>Approve & Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title"><i class="fas fa-times-circle me-2"></i>Request Revision</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="alert alert-warning border-0" style="background-color: #fff3cd;">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Request Revision from Resident</strong>
                        <p class="mb-0 mt-2 small">The resident will be notified to resubmit the required documents or information. Please provide clear instructions on what needs to be corrected.</p>
                    </div>
                    <input type="hidden" name="action" value="reject">
                    <div class="mb-0">
                        <label class="form-label fw-600">Reason for Revision <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="remarks" rows="5" required placeholder="Be specific about what needs to be corrected:&#10;• Missing or unclear documents&#10;• Incorrect information&#10;• Additional requirements needed" style="resize: none;"></textarea>
                        <small class="text-muted">Provide clear and specific instructions to help the resident understand what needs to be fixed.</small>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-arrow-left me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-paper-plane me-2"></i>Send Revision Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php /* Staff replace modal removed: post-submission changes are not allowed */ ?>

<?php if ($user_role === ROLE_ADMIN && $status_id === 2): ?>
<div class="modal fade" id="completeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-check-double me-2"></i>Complete Request</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Final Step:</strong> Optionally upload a final document (PDF only). If you don't upload, the staff-processed document will be used as the final document.
                    </div>
                    
                    <?php if (!empty($request['document_path'])): ?>
                        <div class="mb-3 p-3" style="background: #f0f4f8; border-radius: 6px;">
                            <small class="text-muted d-block mb-1">Current Staff-Processed Document:</small>
                            <a href="<?php echo WEB_ROOT . '/' . $request['document_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye me-1"></i>View Current Document
                            </a>
                            <small class="text-muted d-block mt-2">Leave the file field empty to use this as the final document. Upload a new PDF to replace it.</small>
                        </div>
                    <?php endif; ?>
                    
                    <input type="hidden" name="action" value="complete">
                    <div class="mb-3">
                        <label class="form-label fw-600">Final Document (PDF) <span class="text-secondary">(Optional)</span></label>
                        <input type="file" class="form-control" name="final_document" accept=".pdf">
                        <small class="text-muted">Upload a new final document in PDF format to replace the staff-processed document • Max 10MB</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check-double me-1"></i>Complete Request</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="rejectApprovedModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Return to Staff</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <p>Return request to staff for revision:</p>
                    <input type="hidden" name="action" value="reject_approved">
                    <div class="mb-3">
                        <label class="form-label fw-600">Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="remarks" rows="4" required placeholder="Explain why this needs revision..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Return</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
/* Timeline Styles */
.timeline-vertical {
    position: relative;
    padding: 1rem 1rem;
}

.timeline-step {
    position: relative;
    padding-left: 4rem;
    padding-bottom: 2.5rem;
    opacity: 0.5;
    transition: opacity 0.3s ease;
}

.timeline-step:last-child {
    padding-bottom: 0;
}

.timeline-step.completed,
.timeline-step.active {
    opacity: 1;
}

.timeline-step::before {
    content: '';
    position: absolute;
    left: 1.35rem;
    top: 3rem;
    bottom: -0.8rem;
    width: 2px;
    background: linear-gradient(to bottom, #d4d4d8 0%, #d4d4d8 100%);
}

.timeline-step:last-child::before {
    display: none;
}

.timeline-step.completed::before {
    background: linear-gradient(to bottom, #198754 0%, #198754 100%);
}

.timeline-step.active::before {
    background: linear-gradient(to bottom, #0dcaf0 0%, #0d6efd 100%);
}

.timeline-icon {
    position: absolute;
    left: 0;
    top: 0;
    width: 2.7rem;
    height: 2.7rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
    border: 3px solid #fff;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.timeline-step.completed .timeline-icon {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(25, 135, 84, 0.3);
}

.timeline-step.active .timeline-icon {
    animation: pulse 2s infinite;
    box-shadow: 0 4px 12px rgba(13, 202, 240, 0.3);
}

@keyframes pulse {
    0%, 100% {
        box-shadow: 0 4px 12px rgba(13, 202, 240, 0.3), 0 0 0 0 rgba(13, 202, 240, 0.5);
    }
    50% {
        box-shadow: 0 4px 12px rgba(13, 202, 240, 0.3), 0 0 0 10px rgba(13, 202, 240, 0);
    }
}

.timeline-content {
    padding-top: 0.2rem;
}

.timeline-content strong {
    color: #212529;
    font-size: 0.95rem;
    font-weight: 600;
}

.timeline-content small {
    font-size: 0.8rem;
    line-height: 1.5;
}
</style>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
