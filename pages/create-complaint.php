<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware/auth.php';

require_login();
require_role([ROLE_USER]);

$user_id = current_user_id();
$barangay_id = current_user_barangay_id();

// Get user profile for display
$user_info = [];
$user_stmt = $conn->prepare('SELECT first_name, last_name FROM profile WHERE user_id = ?');
if ($user_stmt) {
    $user_stmt->bind_param('i', $user_id);
    $user_stmt->execute();
    $user_res = $user_stmt->get_result();
    if ($user_res->num_rows > 0) {
        $user_info = $user_res->fetch_assoc();
    }
    $user_stmt->close();
}

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_complaint') {
    try {
        // Validate inputs
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
        
        if (empty($title)) {
            $errors[] = 'Title is required.';
        } elseif (strlen($title) < 5) {
            $errors[] = 'Title must be at least 5 characters long.';
        } elseif (strlen($title) > 255) {
            $errors[] = 'Title cannot exceed 255 characters.';
        }
        
        if (empty($description)) {
            $errors[] = 'Description is required.';
        } elseif (strlen($description) < 10) {
            $errors[] = 'Description must be at least 10 characters long.';
        } elseif (strlen($description) > 5000) {
            $errors[] = 'Description cannot exceed 5000 characters.';
        }
        
        // Handle file uploads
        $uploaded_files = [];
        if (!empty($_FILES['attachments']['name'][0])) {
            $upload_dir = __DIR__ . '/../storage/app/private/complaints/';
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    $errors[] = 'Failed to create upload directory.';
                }
            }
            
            $max_file_size = 5 * 1024 * 1024; // 5MB
            $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
            
            for ($i = 0; $i < count($_FILES['attachments']['name']); $i++) {
                if (empty($_FILES['attachments']['name'][$i])) {
                    continue;
                }
                
                $file_name = $_FILES['attachments']['name'][$i];
                $file_size = $_FILES['attachments']['size'][$i];
                $file_tmp = $_FILES['attachments']['tmp_name'][$i];
                $file_error = $_FILES['attachments']['error'][$i];
                
                // Check for upload errors
                if ($file_error !== UPLOAD_ERR_OK) {
                    switch ($file_error) {
                        case UPLOAD_ERR_INI_SIZE:
                        case UPLOAD_ERR_FORM_SIZE:
                            $errors[] = "File '$file_name' exceeds maximum size of 5MB.";
                            break;
                        case UPLOAD_ERR_PARTIAL:
                            $errors[] = "File '$file_name' was partially uploaded. Please try again.";
                            break;
                        case UPLOAD_ERR_NO_FILE:
                            break;
                        default:
                            $errors[] = "File '$file_name' upload failed.";
                    }
                    continue;
                }
                
                // Validate file size
                if ($file_size > $max_file_size) {
                    $errors[] = "File '$file_name' exceeds maximum size of 5MB.";
                    continue;
                }
                
                // Validate file extension
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                if (!in_array($file_ext, $allowed_extensions)) {
                    $errors[] = "File '$file_name' has unsupported format. Allowed: PDF, JPG, PNG.";
                    continue;
                }
                
                // Validate MIME type
                $mime_type = mime_content_type($file_tmp);
                
                $allowed_mimes = ['application/pdf', 'image/jpeg', 'image/png'];
                if (!in_array($mime_type, $allowed_mimes)) {
                    $errors[] = "File '$file_name' is not a valid PDF or image file.";
                    continue;
                }
                
                // Generate unique filename
                $unique_filename = 'complaint_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
                $file_path = $upload_dir . $unique_filename;
                
                if (move_uploaded_file($file_tmp, $file_path)) {
                    $uploaded_files[] = [
                        'filename' => $unique_filename,
                        'original_name' => $file_name,
                        'mime_type' => $mime_type
                    ];
                } else {
                    $errors[] = "Failed to save file '$file_name'. Please try again.";
                }
            }
        }
        
        // If there are validation errors, don't proceed
        if (!empty($errors)) {
            throw new Exception('Validation failed.');
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert complaint
            $insert_stmt = $conn->prepare('
                INSERT INTO complaint (user_id, barangay_id, title, description, is_anonymous, complaint_status_id, created_at)
                VALUES (?, ?, ?, ?, ?, 1, NOW())
            ');
            
            if (!$insert_stmt) {
                throw new Exception('Database error: ' . $conn->error);
            }
            
            $insert_stmt->bind_param('iissi', $user_id, $barangay_id, $title, $description, $is_anonymous);
            
            if (!$insert_stmt->execute()) {
                throw new Exception('Failed to create complaint: ' . $insert_stmt->error);
            }
            
            $complaint_id = $conn->insert_id;
            $insert_stmt->close();
            
            // Insert attachments
            if (!empty($uploaded_files)) {
                $attach_stmt = $conn->prepare('
                    INSERT INTO complaint_attachment (complaint_id, file_path, uploaded_at)
                    VALUES (?, ?, NOW())
                ');
                
                if (!$attach_stmt) {
                    throw new Exception('Database error: ' . $conn->error);
                }
                
                foreach ($uploaded_files as $file) {
                    $file_rel_path = 'storage/app/private/complaints/' . $file['filename'];
                    $attach_stmt->bind_param('is', $complaint_id, $file_rel_path);
                    
                    if (!$attach_stmt->execute()) {
                        throw new Exception('Failed to save attachment: ' . $attach_stmt->error);
                    }
                }
                $attach_stmt->close();
            }
            
            // Log activity
            activity_log($user_id, 'Created complaint', 'complaint', $complaint_id);
            
            // Commit transaction
            $conn->commit();
            
            $success = true;
            flash_set('Complaint submitted successfully! You can now track its status.', 'success');
            header('Location: index.php?nav=manage-complaints');
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log('Create Complaint Error: ' . $e->getMessage());
            $errors[] = 'An error occurred while saving your complaint. Please try again.';
        }
        
    } catch (Exception $e) {
        error_log('Create Complaint Exception: ' . $e->getMessage());
        $errors[] = 'An unexpected error occurred. Please try again.';
    }
}

$page_title = 'Submit a Complaint';
require_once __DIR__ . '/../public/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Header -->
            <div class="mb-4">
                <h1 class="mb-2"><i class="fas fa-exclamation-circle me-2"></i>Submit a Complaint</h1>
                <p class="text-muted">Share your concerns with your barangay. Please provide detailed information to help us address your issue effectively.</p>
            </div>

            <!-- Alert Messages -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <h6 class="alert-heading mb-2"><i class="fas fa-exclamation-triangle me-2"></i>Please fix the following errors:</h6>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" enctype="multipart/form-data" class="card shadow-sm">
                <input type="hidden" name="action" value="create_complaint">

                <div class="card-body">
                    <!-- Title -->
                    <div class="mb-4">
                        <label for="title" class="form-label fw-600">Complaint Title <span class="text-danger">*</span></label>
                        <input 
                            type="text" 
                            class="form-control form-control-lg" 
                            id="title" 
                            name="title" 
                            placeholder="Brief summary of your complaint"
                            maxlength="255"
                            value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                            required
                        >
                        <small class="text-muted d-block mt-2">
                            <span id="titleCount">0</span>/255 characters
                        </small>
                    </div>

                    <!-- Description -->
                    <div class="mb-4">
                        <label for="description" class="form-label fw-600">Description <span class="text-danger">*</span></label>
                        <textarea 
                            class="form-control" 
                            id="description" 
                            name="description" 
                            placeholder="Provide detailed information about your complaint. What happened? When did it occur? Who is involved?"
                            rows="6"
                            maxlength="5000"
                            required
                        ><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        <small class="text-muted d-block mt-2">
                            <span id="descCount">0</span>/5000 characters
                        </small>
                        <div class="alert alert-info small mt-2 mb-0">
                            <i class="fas fa-lightbulb me-2"></i>
                            <strong>Tip:</strong> The more details you provide, the better we can assist you. Include dates, times, locations, and names if applicable.
                        </div>
                    </div>

                    <!-- Anonymous Toggle -->
                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="isAnonymous" name="is_anonymous">
                            <label class="form-check-label" for="isAnonymous">
                                <strong>Submit anonymously</strong>
                            </label>
                        </div>
                        <small class="text-muted d-block mt-2">
                            If checked, your name will not be visible to staff. However, your complaint will still be tracked and you can view its status using this account.
                        </small>
                    </div>

                    <hr>

                    <!-- Attachments -->
                    <div class="mb-4">
                        <label for="attachments" class="form-label fw-600">Attachments (Optional)</label>
                        <div class="input-group mb-3">
                            <input type="file" class="form-control" id="attachments" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png">
                            <span class="input-group-text"><i class="fas fa-paperclip"></i></span>
                        </div>
                        <small class="text-muted d-block mb-2">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Allowed formats:</strong> PDF, JPG, PNG
                        </small>
                        <small class="text-muted d-block mb-3">
                            <strong>Maximum size:</strong> 5MB per file
                        </small>

                        <!-- File Preview -->
                        <div id="filePreview" class="mb-3"></div>

                        <div class="alert alert-light border-start border-warning">
                            <i class="fas fa-camera me-2 text-warning"></i>
                            <strong>Helpful tip:</strong> You can attach photos, screenshots, or PDF documents to support your complaint. This helps the barangay understand and resolve your issue faster.
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-light d-flex gap-2 justify-content-between">
                    <a href="index.php?nav=manage-complaints" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Complaints
                    </a>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-paper-plane me-2"></i>Submit Complaint
                    </button>
                </div>
            </form>

            <!-- Help Section -->
            <div class="card mt-4">
                <div class="card-body">
                    <h6 class="card-title mb-3"><i class="fas fa-question-circle me-2"></i>Need Help?</h6>
                    <ul class="small text-muted mb-0">
                        <li><strong>Be specific:</strong> Include dates, times, and locations of the incident.</li>
                        <li><strong>Be factual:</strong> Describe what happened without exaggeration or opinion.</li>
                        <li><strong>Provide evidence:</strong> Attach relevant documents, photos, or screenshots.</li>
                        <li><strong>Stay professional:</strong> Use respectful language in your description.</li>
                        <li><strong>One issue per complaint:</strong> If you have multiple unrelated issues, submit separate complaints.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for file preview and character counting -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Character counter for title
    const titleInput = document.getElementById('title');
    const titleCount = document.getElementById('titleCount');
    titleInput?.addEventListener('input', function() {
        titleCount.textContent = this.value.length;
    });
    titleCount.textContent = titleInput?.value.length || 0;

    // Character counter for description
    const descInput = document.getElementById('description');
    const descCount = document.getElementById('descCount');
    descInput?.addEventListener('input', function() {
        descCount.textContent = this.value.length;
    });
    descCount.textContent = descInput?.value.length || 0;

    // File preview
    const attachmentsInput = document.getElementById('attachments');
    const filePreview = document.getElementById('filePreview');

    attachmentsInput?.addEventListener('change', function() {
        filePreview.innerHTML = '';

        if (this.files.length === 0) {
            return;
        }

        const fileList = document.createElement('div');
        fileList.className = 'list-group list-group-sm';

        Array.from(this.files).forEach((file, index) => {
            const fileItem = document.createElement('div');
            fileItem.className = 'list-group-item d-flex justify-content-between align-items-center';

            const fileInfo = document.createElement('div');
            fileInfo.className = 'd-flex align-items-center gap-2';

            // Determine icon based on file type
            let icon = 'fa-file';
            if (file.type === 'application/pdf') {
                icon = 'fa-file-pdf text-danger';
            } else if (file.type.startsWith('image/')) {
                icon = 'fa-file-image text-primary';
            }

            const iconEl = document.createElement('i');
            iconEl.className = `fas ${icon}`;

            const details = document.createElement('div');
            details.innerHTML = `
                <div class="small fw-600">${escapeHtml(file.name)}</div>
                <small class="text-muted">${(file.size / 1024).toFixed(2)} KB</small>
            `;

            fileInfo.appendChild(iconEl);
            fileInfo.appendChild(details);
            fileItem.appendChild(fileInfo);
            fileList.appendChild(fileItem);
        });

        filePreview.appendChild(fileList);
    });

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
