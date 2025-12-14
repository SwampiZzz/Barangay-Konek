<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware/auth.php';

require_login();
require_role([ROLE_USER]); // Only residents can create

$pageTitle = 'Create New Request';
$user_id = current_user_id();
$profile = get_user_profile($user_id);
$barangay_id = $profile['barangay_id'] ?? 0;

// Upload directory
$upload_dir = __DIR__ . '/../storage/app/private/requests/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Allowed file types
$allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
$allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
$max_file_size = 5 * 1024 * 1024; // 5MB

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doc_type_id = intval($_POST['document_type_id'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');
    
    // Validation
    $errors = [];
    if (!$doc_type_id) {
        $errors[] = 'Please select a document type.';
    }
    
    // Fetch document type and validate requirements
    $doc_type = null;
    if ($doc_type_id > 0) {
        $dt_res = db_query('SELECT id, name FROM document_type WHERE id = ?', 'i', [$doc_type_id]);
        if ($dt_res && ($doc_type = $dt_res->fetch_assoc())) {
            // Fetch requirements for validation
            $reqs_res = db_query(
                'SELECT id, requirement_type, label, is_required FROM document_requirement WHERE document_type_id = ? ORDER BY sort_order',
                'i',
                [$doc_type_id]
            );
            $requirements = [];
            if ($reqs_res) {
                while ($req = $reqs_res->fetch_assoc()) {
                    $requirements[] = $req;
                }
            }
            
            // Validate text input requirements
            foreach ($requirements as $req) {
                if ($req['requirement_type'] === 'text_input' && $req['is_required']) {
                    $field_value = trim($_POST["req_{$req['id']}"] ?? '');
                    if (empty($field_value)) {
                        $errors[] = "Required field: {$req['label']}";
                    }
                }
            }
        }
    }
    
    if (!empty($errors)) {
        flash_set(implode(' ', $errors), 'error');
    } else {
        global $conn;
        // Create request record
        $stmt = $conn->prepare('INSERT INTO request (user_id, document_type_id, remarks, barangay_id, request_status_id) VALUES (?, ?, ?, ?, 1)');
        if ($stmt) {
            $stmt->bind_param('iisi', $user_id, $doc_type_id, $remarks, $barangay_id);
            if ($stmt->execute()) {
                $request_id = $stmt->insert_id;
                $success = true;
                
                // Process text input requirements
                if (!empty($requirements)) {
                    foreach ($requirements as $req) {
                        if ($req['requirement_type'] === 'text_input') {
                            $field_value = trim($_POST["req_{$req['id']}"] ?? '');
                            if (!empty($field_value) || $req['is_required']) {
                                $submission_type = 'text';
                                $sub_stmt = $conn->prepare(
                                    'INSERT INTO document_requirement_submission (request_id, requirement_id, submission_type, text_value, submitted_at) VALUES (?, ?, ?, ?, NOW())'
                                );
                                if ($sub_stmt) {
                                    $sub_stmt->bind_param('iiss', $request_id, $req['id'], $submission_type, $field_value);
                                    if (!$sub_stmt->execute()) {
                                        $success = false;
                                        $errors[] = 'Failed to save requirement data.';
                                    }
                                    $sub_stmt->close();
                                }
                            }
                        } else if ($req['requirement_type'] === 'document_upload') {
                            // Handle file uploads for this specific requirement
                            $field_key = "req_{$req['id']}-input";
                            if (isset($_FILES[$field_key]) && $_FILES[$field_key]['error'] !== UPLOAD_ERR_NO_FILE) {
                                $file_name = $_FILES[$field_key]['name'];
                                $file_tmp = $_FILES[$field_key]['tmp_name'];
                                $file_error = $_FILES[$field_key]['error'];
                                $file_size = $_FILES[$field_key]['size'];
                                
                                if ($file_error !== UPLOAD_ERR_OK) {
                                    $success = false;
                                    $errors[] = "File upload error for {$req['label']}.";
                                    continue;
                                }
                                if ($file_size > $max_file_size) {
                                    $success = false;
                                    $errors[] = "File for {$req['label']} exceeds 5MB limit.";
                                    continue;
                                }
                                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                                if (!in_array($file_ext, $allowed_extensions)) {
                                    $success = false;
                                    $errors[] = "File type {$file_ext} not allowed for {$req['label']}. Use PDF, JPG, or PNG.";
                                    continue;
                                }
                                
                                // Save file
                                $unique_name = 'req_' . $request_id . '_' . $req['id'] . '_' . time() . '_' . uniqid() . '.' . $file_ext;
                                $file_path = 'storage/app/private/requests/' . $unique_name;
                                if (move_uploaded_file($file_tmp, $upload_dir . $unique_name)) {
                                    $submission_type = 'file';
                                    $sub_stmt = $conn->prepare(
                                        'INSERT INTO document_requirement_submission (request_id, requirement_id, submission_type, file_name, file_path, file_type, submitted_at) VALUES (?, ?, ?, ?, ?, ?, NOW())'
                                    );
                                    if ($sub_stmt) {
                                        $sub_stmt->bind_param('iissss', $request_id, $req['id'], $submission_type, $file_name, $file_path, $file_ext);
                                        if (!$sub_stmt->execute()) {
                                            $success = false;
                                            $errors[] = "Failed to save {$req['label']} file metadata.";
                                        }
                                        $sub_stmt->close();
                                    }
                                } else {
                                    $success = false;
                                    $errors[] = "Failed to upload file for {$req['label']}.";
                                }
                            } else if ($req['is_required']) {
                                $success = false;
                                $errors[] = "{$req['label']} is required.";
                            }
                        }
                    }
                }
                
                // Also process any files uploaded via the generic files[] input (fallback)
                if ($success && isset($_FILES['files']) && is_array($_FILES['files']['tmp_name'])) {
                    for ($i = 0; $i < count($_FILES['files']['tmp_name']); $i++) {
                        $file_name = $_FILES['files']['name'][$i];
                        $file_tmp = $_FILES['files']['tmp_name'][$i];
                        $file_error = $_FILES['files']['error'][$i];
                        $file_size = $_FILES['files']['size'][$i];
                        
                        if (empty($file_name) || $file_error === UPLOAD_ERR_NO_FILE) continue;
                        if ($file_error !== UPLOAD_ERR_OK) { 
                            $success = false;
                            $errors[] = "File upload error for {$file_name}."; 
                            break; 
                        }
                        if ($file_size > $max_file_size) { 
                            $success = false;
                            $errors[] = "File {$file_name} exceeds 5MB limit."; 
                            break; 
                        }
                        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        if (!in_array($file_ext, $allowed_extensions)) { 
                            $success = false;
                            $errors[] = "File type {$file_ext} not allowed. Use PDF, JPG, or PNG."; 
                            break; 
                        }
                        // Save file
                        $unique_name = 'req_' . $request_id . '_' . time() . '_' . uniqid() . '.' . $file_ext;
                        $file_path = 'storage/app/private/requests/' . $unique_name;
                        if (move_uploaded_file($file_tmp, $upload_dir . $unique_name)) {
                            $doc_stmt = $conn->prepare('INSERT INTO requested_document (name, request_id, file_path, file_type, uploaded_at) VALUES (?, ?, ?, ?, NOW())');
                            if ($doc_stmt) {
                                $doc_stmt->bind_param('siss', $file_name, $request_id, $file_path, $file_ext);
                                if (!$doc_stmt->execute()) { 
                                    $success = false;
                                    $errors[] = 'Failed to save file metadata.'; 
                                    break; 
                                }
                                $doc_stmt->close();
                            }
                        } else { 
                            $success = false;
                            $errors[] = "Failed to upload file {$file_name}."; 
                            break; 
                        }
                    }
                }
                
                if ($success && empty($errors)) {
                    activity_log($user_id, 'Created request with documents', 'request', $request_id);
                    flash_set('Request created successfully!', 'success');
                    header('Location: index.php?nav=manage-requests');
                    exit;
                } else {
                    // Delete request if processing failed
                    $delete_stmt = $conn->prepare('DELETE FROM request WHERE id = ?');
                    if ($delete_stmt) {
                        $delete_stmt->bind_param('i', $request_id);
                        $delete_stmt->execute();
                        $delete_stmt->close();
                    }
                    if (!empty($errors)) {
                        flash_set(implode(' ', $errors), 'error');
                    }
                }
            } else {
                flash_set('Failed to create request.', 'error');
            }
            $stmt->close();
        }
    }
}

// Fetch available document types and their requirements
$doc_types = [];
$res = db_query('SELECT id, name, description FROM document_type ORDER BY name');
if ($res) {
    while ($row = $res->fetch_assoc()) {
        // Fetch requirements for this document type
        $requirements = [];
        $reqs_res = db_query(
            'SELECT id, requirement_type, label, description, field_type, is_required FROM document_requirement WHERE document_type_id = ? ORDER BY sort_order',
            'i',
            [$row['id']]
        );
        if ($reqs_res) {
            while ($req = $reqs_res->fetch_assoc()) {
                $requirements[] = $req;
            }
        }
        $row['requirements'] = $requirements;
        $doc_types[] = $row;
    }
}

require_once __DIR__ . '/../public/header.php';
?>

<div class="container my-4">
    <div class="row">
        <div class="col-lg-8 offset-lg-2">
            <!-- Page Header -->
            <div class="mb-4">
                <h2 class="mb-2" style="font-weight: 700; color: #1f2937; font-size: 1.75rem;">
                    <i class="fas fa-file-alt text-primary me-2"></i>Document Request
                </h2>
                <p class="text-muted mb-0" style="font-size: 0.9rem;">Submit the required documents for your request</p>
            </div>

            <!-- Flash Messages -->
            <?php 
            $flash = flash_get();
            if (!empty($flash['message'])): 
            ?>
                <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert" style="border-left: 4px solid; margin-bottom: 2rem;">
                    <i class="fas fa-<?php echo $flash['type'] === 'error' ? 'exclamation-circle' : 'check-circle'; ?> me-2"></i>
                    <?php echo e($flash['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Form Card -->
            <div class="card border-0 shadow-sm" style="border-top: 4px solid #0d6efd; overflow: hidden;">
                <div class="card-body p-4">
                    <form method="POST" enctype="multipart/form-data">
                        <!-- Step 1: Document Type Selection -->
                        <div id="step-1" class="mb-4">
                            <label for="document_type_id" class="form-label fw-600 d-block mb-3" style="color: #1f2937; font-size: 1rem;">
                                <span style="display: inline-block; width: 28px; height: 28px; background: #0d6efd; color: white; border-radius: 50%; text-align: center; line-height: 28px; font-weight: 700; margin-right: 0.5rem;">1</span>
                                What document type do you need?
                            </label>
                            <select class="form-select" id="document_type_id" name="document_type_id" required style="border: 1px solid #dee2e6; padding: 0.625rem 0.875rem; width: 100%; max-width: 100%;">
                                <option value="">-- Select Document Type --</option>
                                <?php foreach ($doc_types as $dt): ?>
                                    <option value="<?php echo $dt['id']; ?>" title="<?php echo e($dt['name']) . ($dt['description'] ? ' - ' . e($dt['description']) : ''); ?>">
                                        <?php echo e($dt['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="d-block mt-2" style="color: #6b7280; font-size: 0.85rem;">
                                <?php 
                                    $desc = '';
                                    foreach ($doc_types as $dt) {
                                        if ($dt['description']) {
                                            $desc = $dt['description'];
                                            break;
                                        }
                                    }
                                    echo $desc ? "Select a document type to see requirements" : "";
                                ?>
                            </small>
                        </div>

                        <!-- Step 2: Requirements Form (Hidden until type selected) -->
                        <div id="step-2" style="display: none;">
                            <!-- Dynamic Requirements Container -->
                            <div id="requirementsForm" style="margin-bottom: 2rem;"></div>

                            <!-- Remarks -->
                            <div style="margin-bottom: 1.5rem;">
                                <label for="remarks" class="form-label fw-600 d-block mb-2" style="color: #1f2937; font-size: 0.95rem;">
                                    Additional Remarks <span class="text-muted" style="font-weight: 400; font-size: 0.85rem;">(Optional)</span>
                                </label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="3" placeholder="Add any special instructions or notes..." style="resize: none; border: 1px solid #dee2e6;"></textarea>
                            </div>

                            <!-- Action Buttons -->
                            <div class="d-flex gap-2 pt-2">
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <i class="fas fa-paper-plane me-1"></i>Submit Request
                                </button>
                                <a href="index.php?nav=manage-requests" class="btn btn-outline-secondary flex-grow-1">
                                    <i class="fas fa-arrow-left me-1"></i>Cancel
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Styles -->
<style>
.form-control, .form-select {
    border: 1px solid #dee2e6;
    padding: 0.625rem 0.875rem;
    font-size: 0.95rem;
}

.form-control:focus, .form-select:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.1);
}

.requirement-question {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.requirement-question:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.requirement-label {
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.requirement-description {
    font-size: 0.85rem;
    color: #6b7280;
    margin-bottom: 0.75rem;
}

.upload-zone {
    border: 2px dashed #dee2e6;
    border-radius: 0.5rem;
    padding: 1.5rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background-color: #f9fafb;
}

.upload-zone:hover {
    border-color: #0d6efd;
    background-color: rgba(13, 110, 253, 0.03);
}

.upload-zone.dragover {
    border-color: #0d6efd;
    background-color: rgba(13, 110, 253, 0.08);
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.2);
}

.upload-zone i {
    font-size: 2rem;
    color: #9ca3af;
    margin-bottom: 0.5rem;
}

.file-item-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background-color: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 0.375rem;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.file-item-row i {
    margin-right: 0.5rem;
    color: #9ca3af;
}

.btn {
    padding: 0.625rem 1.25rem;
    font-size: 0.95rem;
    font-weight: 500;
}

.btn-primary {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

.btn-primary:hover {
    background-color: #0b5ed7;
    border-color: #0a58ca;
}

.btn-outline-secondary {
    color: #6c757d;
    border-color: #dee2e6;
}

.btn-outline-secondary:hover {
    background-color: #e2e3e5;
    border-color: #adb5bd;
}

.btn-sm {
    padding: 0.4rem 0.75rem;
    font-size: 0.85rem;
}

@media (max-width: 768px) {
    .card-body {
        padding: 1rem;
    }
    
    .container {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeSelect = document.getElementById('document_type_id');
    const step1 = document.getElementById('step-1');
    const step2 = document.getElementById('step-2');
    const requirementsForm = document.getElementById('requirementsForm');
    
    // Store doc types data
    const docTypesData = {};
    <?php foreach ($doc_types as $dt): ?>
    docTypesData[<?php echo intval($dt['id']); ?>] = <?php echo json_encode($dt['requirements'] ?? []); ?>;
    <?php endforeach; ?>

    // When document type is selected, build the form
    typeSelect.addEventListener('change', function() {
        requirementsForm.innerHTML = '';
        step2.style.display = 'none';
        
        const selectedId = this.value;
        if (!selectedId) return;
        
        const reqs = docTypesData[selectedId] || [];
        if (!reqs || reqs.length === 0) return;
        
        step2.style.display = 'block';
        let stepNumber = 2;
        
        // Build Q&A form from requirements
        reqs.forEach((req, idx) => {
            const fieldId = `req_${req.id}`;
            const required = req.is_required ? '<span class="text-danger">*</span>' : '';
            
            let inputField = '';
            let questionText = req.label;
            
            if (req.requirement_type === 'document_upload') {
                inputField = buildFileUploadField(fieldId, req);
                questionText = `Upload: ${req.label}`;
            } else if (req.requirement_type === 'text_input') {
                inputField = buildTextInputField(fieldId, req);
                questionText = `${req.label}`;
            }
            
            const description = req.description ? `<div class="requirement-description">${req.description}</div>` : '';
            
            const questionDiv = document.createElement('div');
            questionDiv.className = 'requirement-question';
            questionDiv.innerHTML = `
                <div class="requirement-label">
                    <span style="display: inline-block; width: 24px; height: 24px; background: #f3f4f6; border-radius: 50%; text-align: center; line-height: 24px; font-weight: 600; font-size: 0.85rem; flex-shrink: 0;">${stepNumber}</span>
                    <span>${questionText} ${required}</span>
                </div>
                ${description}
                ${inputField}
            `;
            
            requirementsForm.appendChild(questionDiv);
            
            // Attach drag-drop listeners if it's a file upload field
            if (req.requirement_type === 'document_upload') {
                setTimeout(() => attachFileUploadListeners(fieldId), 0);
            }
            
            stepNumber++;
        });
    });
    
    function buildTextInputField(fieldId, req) {
        const required = req.is_required ? 'required' : '';
        const fieldType = req.field_type || 'text';
        
        let inputHtml = '';
        if (fieldType === 'textarea') {
            inputHtml = `<textarea class="form-control" id="${fieldId}" name="${fieldId}" placeholder="Enter your response..." rows="3" style="resize: none;" ${required}></textarea>`;
        } else if (fieldType === 'email') {
            inputHtml = `<input type="email" class="form-control" id="${fieldId}" name="${fieldId}" placeholder="name@example.com" ${required}>`;
        } else if (fieldType === 'number') {
            inputHtml = `<input type="number" class="form-control" id="${fieldId}" name="${fieldId}" placeholder="0" ${required}>`;
        } else if (fieldType === 'date') {
            inputHtml = `<input type="date" class="form-control" id="${fieldId}" name="${fieldId}" ${required}>`;
        } else {
            inputHtml = `<input type="text" class="form-control" id="${fieldId}" name="${fieldId}" placeholder="Enter your response..." ${required}>`;
        }
        
        return inputHtml;
    }
    
    function buildFileUploadField(fieldId, req) {
        return `
            <div class="upload-zone" id="${fieldId}-zone" onclick="document.getElementById('${fieldId}-input').click();">
                <i class="fas fa-cloud-upload-alt"></i>
                <p style="margin-bottom: 0.5rem; color: #4b5563; font-weight: 500; font-size: 0.9rem;">Click to upload or drag and drop</p>
                <p style="margin-bottom: 0; color: #9ca3af; font-size: 0.85rem;">PDF, JPG, PNG (Max 5MB)</p>
                <input type="file" id="${fieldId}-input" name="${fieldId}-input" class="d-none" accept=".pdf,.jpg,.jpeg,.png">
                <div id="${fieldId}-list" class="mt-3"></div>
            </div>
        `;
    }
    
    function attachFileUploadListeners(fieldId) {
        const fileInput = document.getElementById(`${fieldId}-input`);
        const uploadZone = document.getElementById(`${fieldId}-zone`);
        const fileListContainer = document.getElementById(`${fieldId}-list`);
        
        if (!fileInput || !uploadZone) return;
        
        // Click to upload
        fileInput.addEventListener('change', () => updateFileDisplay(fieldId));
        
        // Drag and drop
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });
        
        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragover');
        });
        
        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            fileInput.files = e.dataTransfer.files;
            updateFileDisplay(fieldId);
        });
    }
    
    function updateFileDisplay(fieldId) {
        const fileInput = document.getElementById(`${fieldId}-input`);
        const fileListContainer = document.getElementById(`${fieldId}-list`);
        
        fileListContainer.innerHTML = '';
        if (!fileInput.files || fileInput.files.length === 0) return;
        
        for (let i = 0; i < fileInput.files.length; i++) {
            const file = fileInput.files[i];
            const sizeInMb = (file.size / 1024 / 1024).toFixed(2);
            const isValid = sizeInMb <= 5;
            
            const fileRow = document.createElement('div');
            fileRow.className = 'file-item-row';
            fileRow.style.borderColor = isValid ? '#e5e7eb' : '#f87171';
            fileRow.style.backgroundColor = isValid ? '#f9fafb' : '#fee2e2';
            
            fileRow.innerHTML = `
                <span>
                    <i class="fas fa-file ${isValid ? 'text-muted' : 'text-danger'}"></i>
                    <span style="color: ${isValid ? '#374151' : '#dc2626'}; font-weight: 500;">${file.name}</span>
                    <small class="text-muted">(${sizeInMb}MB)</small>
                    ${!isValid ? '<span style="color: #dc2626; font-weight: 600; margin-left: 0.5rem;">Exceeds 5MB</span>' : ''}
                </span>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeUploadFile('${fieldId}', ${i})" style="padding: 0.3rem 0.6rem;">
                    <i class="fas fa-trash" style="font-size: 0.8rem;"></i>
                </button>
            `;
            
            fileListContainer.appendChild(fileRow);
        }
    }
    
    window.removeUploadFile = function(fieldId, index) {
        const fileInput = document.getElementById(`${fieldId}-input`);
        const dt = new DataTransfer();
        for (let i = 0; i < fileInput.files.length; i++) {
            if (i !== index) dt.items.add(fileInput.files[i]);
        }
        fileInput.files = dt.files;
        updateFileDisplay(fieldId);
    };
});
</script>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
