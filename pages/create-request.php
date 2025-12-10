<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware/auth.php';

require_login();
require_role([ROLE_USER]); // Only residents can create

$pageTitle = 'Create New Request';
$user_id = current_user_id();
$profile = get_user_profile($user_id);
$barangay_id = $profile['barangay_id'] ?? 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doc_type_id = intval($_POST['document_type_id'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');
    
    if (!$doc_type_id) {
        flash_set('Please select a document type.', 'error');
    } else {
        global $conn;
        $stmt = $conn->prepare('INSERT INTO request (user_id, document_type_id, remarks, barangay_id, request_status_id) VALUES (?, ?, ?, ?, 1)');
        if ($stmt) {
            $stmt->bind_param('iisi', $user_id, $doc_type_id, $remarks, $barangay_id);
            if ($stmt->execute()) {
                $request_id = $stmt->insert_id;
                activity_log($user_id, 'Created request', 'request', $request_id);
                flash_set('Request created successfully!', 'success');
                header('Location: index.php?nav=request-ticket&id=' . $request_id);
                exit;
            } else {
                flash_set('Failed to create request.', 'error');
            }
            $stmt->close();
        }
    }
}

// Fetch available document types
$doc_types = [];
$res = db_query('SELECT id, name, description FROM document_type ORDER BY name');
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $doc_types[] = $row;
    }
}

require_once __DIR__ . '/../public/header.php';
?>

<div class="container my-5">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <h2 class="mb-4"><i class="fas fa-file-alt"></i> Create New Document Request</h2>

            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="document_type_id" class="form-label">Document Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="document_type_id" name="document_type_id" required>
                                <option value="">-- Select Document Type --</option>
                                <?php foreach ($doc_types as $dt): ?>
                                    <option value="<?php echo $dt['id']; ?>">
                                        <?php echo e($dt['name']); ?>
                                        <?php if ($dt['description']): ?>
                                            - <?php echo e($dt['description']); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="remarks" class="form-label">Additional Remarks (Optional)</label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="4" placeholder="Any special instructions or notes..."></textarea>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane"></i> Create Request
                            </button>
                            <a href="index.php?nav=request-list" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to My Requests
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
