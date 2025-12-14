<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware/auth.php';

require_login();
require_role([ROLE_USER]); // Only users can edit their own complaints

$complaint_id = intval($_GET['id'] ?? 0);
if ($complaint_id <= 0) {
    flash_set('Invalid complaint ID.', 'error');
    header('Location: index.php?nav=manage-complaints');
    exit;
}

$user_id = current_user_id();
$profile = get_user_profile($user_id);
$barangay_id = $profile['barangay_id'] ?? 0;

// Fetch complaint
$stmt = $conn->prepare('SELECT * FROM complaint WHERE id = ? AND user_id = ? AND complaint_status_id = 1');
if (!$stmt) die('DB prepare error: ' . $conn->error);
$stmt->bind_param('ii', $complaint_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();
$complaint = $res->fetch_assoc();
$stmt->close();

if (!$complaint) {
    flash_set('Complaint not found or cannot be edited. Only open complaints can be modified.', 'error');
    header('Location: index.php?nav=manage-complaints');
    exit;
}

// Allowed file types and limits
$allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
$allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
$max_file_size = 5 * 1024 * 1024; // 5MB
$upload_dir = __DIR__ . '/../storage/app/private/complaints/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Fetch current attachments
$attachments = [];
$attach_stmt = $conn->prepare('SELECT * FROM complaint_attachment WHERE complaint_id = ? ORDER BY uploaded_at DESC');
if ($attach_stmt) {
    $attach_stmt->bind_param('i', $complaint_id);
    $attach_stmt->execute();
    $attach_res = $attach_stmt->get_result();
    while ($row = $attach_res->fetch_assoc()) {
        $attachments[] = $row;
    }
    $attach_stmt->close();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete_attachment') {
        // Delete a specific attachment
        $attachment_id = intval($_POST['attachment_id'] ?? 0);
        if ($attachment_id > 0) {
            // Verify attachment belongs to this complaint
            $verify_stmt = $conn->prepare('SELECT file_path FROM complaint_attachment WHERE id = ? AND complaint_id = ?');
            $verify_stmt->bind_param('ii', $attachment_id, $complaint_id);
            $verify_stmt->execute();
            $verify_res = $verify_stmt->get_result();
            if ($verify_row = $verify_res->fetch_assoc()) {
                $file_path = $verify_row['file_path'];
                
                // Delete file from storage
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
                
                // Delete database record
                $delete_stmt = $conn->prepare('DELETE FROM complaint_attachment WHERE id = ?');
                $delete_stmt->bind_param('i', $attachment_id);
                if ($delete_stmt->execute()) {
                    activity_log($user_id, 'Deleted complaint attachment', 'complaint', $complaint_id);
                    flash_set('Attachment removed.', 'success');
                } else {
                    flash_set('Failed to remove attachment.', 'error');
                }
                $delete_stmt->close();
            }
            $verify_stmt->close();
        }
        // Redirect to refresh
        header('Location: index.php?nav=edit-complaint&id=' . $complaint_id);
        exit;
    }
    
    // Handle complaint update
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
    
    $errors = [];
    
    if (empty($title)) {
        $errors[] = 'Title is required.';
    } elseif (mb_strlen($title) < 3 || mb_strlen($title) > 255) {
        $errors[] = 'Title must be between 3 and 255 characters.';
    }
    
    if (empty($description)) {
        $errors[] = 'Description is required.';
    } elseif (mb_strlen($description) < 10 || mb_strlen($description) > 5000) {
        $errors[] = 'Description must be between 10 and 5000 characters.';
    }
    
    // Check for new files
    $new_files = [];
    if (!empty($_FILES['attachments']['name'][0])) {
        $file_count = count($_FILES['attachments']['name']);
        for ($i = 0; $i < $file_count; $i++) {
            $file_name = $_FILES['attachments']['name'][$i];
            $file_tmp = $_FILES['attachments']['tmp_name'][$i];
            $file_error = $_FILES['attachments']['error'][$i];
            $file_size = $_FILES['attachments']['size'][$i];
            
            // Skip empty slots
            if ($file_error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            
            // Validate
            if ($file_error !== UPLOAD_ERR_OK) {
                $errors[] = "Upload error for '$file_name'.";
                continue;
            }
            
            if ($file_size > $max_file_size) {
                $errors[] = "File '$file_name' exceeds 5MB limit.";
                continue;
            }
            
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            if (!in_array($file_ext, $allowed_extensions)) {
                $errors[] = "File type '$file_ext' not allowed (PDF, JPG, PNG only).";
                continue;
            }
            
            $new_files[] = [
                'name' => $file_name,
                'tmp_name' => $file_tmp,
                'ext' => $file_ext
            ];
        }
    }
    
    if (!empty($errors)) {
        flash_set(implode(' ', $errors), 'error');
    } else {
        // Update complaint
        $update_stmt = $conn->prepare('UPDATE complaint SET title = ?, description = ?, is_anonymous = ?, updated_at = NOW() WHERE id = ?');
        if ($update_stmt) {
            $update_stmt->bind_param('ssii', $title, $description, $is_anonymous, $complaint_id);
            if ($update_stmt->execute()) {
                // Upload new files
                $upload_success = true;
                foreach ($new_files as $file) {
                    $unique_filename = time() . '_' . uniqid() . '.' . $file['ext'];
                    $full_path = $upload_dir . $unique_filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $full_path)) {
                        $insert_stmt = $conn->prepare('INSERT INTO complaint_attachment (complaint_id, file_path, uploaded_at) VALUES (?, ?, NOW())');
                        $insert_stmt->bind_param('is', $complaint_id, $full_path);
                        if (!$insert_stmt->execute()) {
                            $upload_success = false;
                            unlink($full_path);
                        }
                        $insert_stmt->close();
                    } else {
                        $upload_success = false;
                    }
                }
                
                if ($upload_success) {
                    activity_log($user_id, 'Updated complaint', 'complaint', $complaint_id);
                    flash_set('Complaint updated successfully.', 'success');
                    header('Location: index.php?nav=complaint-ticket&id=' . $complaint_id);
                    exit;
                } else {
                    flash_set('Complaint updated but some files failed to upload.', 'warning');
                }
            } else {
                flash_set('Failed to update complaint.', 'error');
            }
            $update_stmt->close();
        }
    }
}

$pageTitle = 'Edit Complaint #' . str_pad($complaint_id, 5, '0', STR_PAD_LEFT);
require_once __DIR__ . '/../public/header.php';
?>

<div class="container my-5">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="d-flex align-items-center mb-4">
                <a href="index.php?nav=complaint-ticket&id=<?php echo $complaint_id; ?>" class="btn btn-outline-secondary btn-sm me-2">
                    <i class="fas fa-arrow-left me-1"></i>Back
                </a>
                <h2 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Complaint #<?php echo str_pad($complaint_id, 5, '0', STR_PAD_LEFT); ?></h2>
            </div>

            <!-- Flash Messages -->
            <?php 
            $flash = flash_get();
            if (!empty($flash['message'])): 
            ?>
                <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : ($flash['type'] === 'warning' ? 'warning' : 'success'); ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $flash['type'] === 'error' ? 'exclamation-circle' : 'check-circle'; ?> me-2"></i>
                    <?php echo e($flash['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" enctype="multipart/form-data">
                        <!-- Title -->
                        <div class="mb-3">
                            <label class="form-label fw-600">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($complaint['title']); ?>" required minlength="3" maxlength="255">
                            <small class="text-muted">Brief summary of your complaint</small>
                        </div>

                        <!-- Description -->
                        <div class="mb-3">
                            <label class="form-label fw-600">Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="6" required minlength="10" maxlength="5000"><?php echo htmlspecialchars($complaint['description']); ?></textarea>
                            <small class="text-muted">Detailed description of your complaint</small>
                        </div>

                        <!-- Anonymous Toggle -->
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="anonymous" name="is_anonymous" <?php echo $complaint['is_anonymous'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="anonymous">
                                    <strong>Submit anonymously</strong>
                                    <small class="d-block text-muted mt-1">Your identity will be hidden from the public but visible to barangay staff and admin</small>
                                </label>
                            </div>
                        </div>

                        <hr>

                        <!-- Current Attachments -->
                        <div class="mb-4">
                            <h6 class="fw-600 mb-3"><i class="fas fa-paperclip me-2"></i>Current Attachments</h6>
                            <?php if (count($attachments) > 0): ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($attachments as $att): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center p-3">
                                            <div>
                                                <i class="fas fa-file me-2"></i>
                                                <span><?php echo basename($att['file_path']); ?></span>
                                                <small class="d-block text-muted mt-1"><?php echo date('M d, Y h:i A', strtotime($att['uploaded_at'])); ?></small>
                                            </div>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete_attachment">
                                                <input type="hidden" name="attachment_id" value="<?php echo $att['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this attachment?')">
                                                    <i class="fas fa-trash me-1"></i>Remove
                                                </button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted small mb-0">No attachments yet</p>
                            <?php endif; ?>
                        </div>

                        <hr>

                        <!-- New Attachments -->
                        <div class="mb-4">
                            <h6 class="fw-600 mb-3"><i class="fas fa-cloud-upload-alt me-2"></i>Add New Attachments</h6>
                            <div class="mb-2">
                                <input type="file" name="attachments[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png" multiple>
                                <small class="text-muted d-block mt-2">
                                    <strong>Allowed formats:</strong> PDF, JPG, PNG (max 5MB per file)<br>
                                    You can upload multiple files at once
                                </small>
                            </div>
                        </div>

                        <hr>

                        <!-- Action Buttons -->
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="index.php?nav=complaint-ticket&id=<?php echo $complaint_id; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Info Box -->
            <div class="alert alert-info mt-4 border-start border-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Note:</strong> You can only edit complaints with <strong>Open</strong> status. Once a complaint is processed by staff, you can no longer edit it.
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
