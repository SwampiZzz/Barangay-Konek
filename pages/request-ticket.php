<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware/auth.php';

require_login();

$request_id = intval($_GET['id'] ?? 0);
if ($request_id <= 0) {
    flash_set('Invalid request ID.', 'error');
    header('Location: index.php?nav=request-list');
    exit;
}

$user_id = current_user_id();
$user_role = current_user_role();

// Fetch request details
$stmt = $conn->prepare('
    SELECT r.*, dt.name as doc_type, rs.name as status_name, 
           u.username, p.first_name, p.last_name
    FROM request r
    LEFT JOIN document_type dt ON r.document_type_id = dt.id
    LEFT JOIN request_status rs ON r.request_status_id = rs.id
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN profile p ON u.id = p.user_id
    WHERE r.id = ?
');
if (!$stmt) die('DB prepare failed');
$stmt->bind_param('i', $request_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    flash_set('Request not found.', 'error');
    header('Location: index.php?nav=request-list');
    exit;
}
$request = $result->fetch_assoc();
$stmt->close();

// Ownership check: User sees only their requests; Staff/Admin/Superadmin can see all in their barangay
if ($user_role === ROLE_USER && $request['user_id'] != $user_id) {
    http_response_code(403);
    die('Forbidden');
}

// Fetch attachments
$attachments = [];
$res = db_query('SELECT id, name, file_path FROM requested_document WHERE request_id = ? AND deleted_at IS NULL ORDER BY uploaded_at DESC', 'i', [$request_id]);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $attachments[] = $row;
    }
}

$pageTitle = 'Request #' . str_pad($request_id, 5, '0', STR_PAD_LEFT);
require_once __DIR__ . '/../public/header.php';
?>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-file-alt"></i> Request #<?php echo str_pad($request_id, 5, '0', STR_PAD_LEFT); ?></h2>
        <a href="index.php?nav=request-list" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <!-- Request Details Card -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Request Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Document Type:</strong> <?php echo e($request['doc_type']); ?></p>
                            <p><strong>Requested By:</strong> <?php echo e(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? '')); ?></p>
                            <p><strong>Created:</strong> <?php echo date('M d, Y H:i', strtotime($request['created_at'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Status:</strong>
                                <?php
                                $status_id = intval($request['request_status_id']);
                                $badge = $status_id === 1 ? 'warning' : ($status_id === 2 ? 'info' : ($status_id === 3 ? 'danger' : 'success'));
                                ?>
                                <span class="badge bg-<?php echo $badge; ?> fs-6">
                                    <?php echo e($request['status_name']); ?>
                                </span>
                            </p>
                            <p><strong>Last Updated:</strong> <?php echo date('M d, Y H:i', strtotime($request['updated_at'])); ?></p>
                        </div>
                    </div>
                    <?php if (!empty($request['remarks'])): ?>
                        <hr>
                        <p><strong>Remarks:</strong></p>
                        <p class="text-muted"><?php echo e($request['remarks']); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Attachments Card -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Attachments & Documents</h5>
                </div>
                <div class="card-body">
                    <?php if (count($attachments) > 0): ?>
                        <div class="list-group">
                            <?php foreach ($attachments as $att): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-file"></i> <?php echo e($att['name']); ?>
                                    </div>
                                    <a href="<?php echo e($att['file_path']); ?>" class="btn btn-sm btn-primary" download>
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No documents uploaded yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Action Panel (Right Sidebar) -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    <?php if ($user_role === ROLE_USER && intval($request['request_status_id']) === 3): ?>
                        <!-- User can submit revision if rejected -->
                        <button class="btn btn-primary w-100 mb-2" data-bs-toggle="modal" data-bs-target="#revisionModal">
                            <i class="fas fa-redo"></i> Submit Revision
                        </button>
                    <?php elseif (in_array($user_role, [ROLE_STAFF, ROLE_ADMIN, ROLE_SUPERADMIN]) && intval($request['request_status_id']) === 1): ?>
                        <!-- Staff/Admin can approve/reject pending requests -->
                        <button class="btn btn-success w-100 mb-2" data-action="approve">
                            <i class="fas fa-check"></i> Approve
                        </button>
                        <button class="btn btn-danger w-100 mb-2" data-bs-toggle="modal" data-bs-target="#rejectModal">
                            <i class="fas fa-times"></i> Reject
                        </button>
                    <?php elseif (in_array($user_role, [ROLE_ADMIN, ROLE_SUPERADMIN]) && intval($request['request_status_id']) === 2): ?>
                        <!-- Admin/Superadmin can complete approved requests -->
                        <button class="btn btn-success w-100 mb-2" data-action="complete">
                            <i class="fas fa-check-double"></i> Complete Request
                        </button>
                    <?php else: ?>
                        <p class="text-muted small">No actions available for this request status.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0">Request Timeline</h6>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-marker bg-warning"></div>
                            <strong>Pending</strong>
                            <p class="text-muted small">Created on <?php echo date('M d, Y', strtotime($request['created_at'])); ?></p>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-info"></div>
                            <strong>In Review</strong>
                            <p class="text-muted small">Awaiting staff approval</p>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-success"></div>
                            <strong>Completed</strong>
                            <p class="text-muted small">Document ready for pickup</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Revision Modal -->
<div class="modal fade" id="revisionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Submit Revision</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="revisionForm" method="POST">
                <div class="modal-body">
                    <p>Upload your revised documents below:</p>
                    <input type="hidden" name="action" value="submit_revision">
                    <input type="hidden" name="request_id" value="<?php echo intval($request_id); ?>">
                    <div class="mb-3">
                        <label class="form-label">Choose Files</label>
                        <input type="file" class="form-control" name="documents[]" multiple required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Revision</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="rejectForm" method="POST">
                <div class="modal-body">
                    <p>Provide a reason for rejection:</p>
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="request_id" value="<?php echo intval($request_id); ?>">
                    <div class="mb-3">
                        <label class="form-label">Reason for Rejection</label>
                        <textarea class="form-control" name="remarks" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.timeline {
    padding: 0;
    list-style: none;
}
.timeline-item {
    padding-bottom: 1rem;
    padding-left: 2.5rem;
    position: relative;
}
.timeline-marker {
    width: 0.75rem;
    height: 0.75rem;
    border-radius: 50%;
    position: absolute;
    left: 0;
    top: 0.25rem;
}
</style>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
