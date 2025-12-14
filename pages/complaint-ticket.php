<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware/auth.php';

require_login();
require_role([ROLE_USER, ROLE_STAFF, ROLE_ADMIN, ROLE_SUPERADMIN]);

$user_id = current_user_id();
$user_role = current_user_role();
$user_barangay_id = current_user_barangay_id();

$complaint_id = intval($_GET['id'] ?? 0);

if ($complaint_id <= 0) {
    flash_set('Invalid complaint ID.', 'error');
    header('Location: index.php?nav=manage-complaints');
    exit;
}

$errors = [];
$success_message = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        $conn->begin_transaction();
        
        switch ($action) {
            case 'add_comment':
                // Users and Staff can add comments
                $message = trim($_POST['message'] ?? '');
                
                if (empty($message)) {
                    throw new Exception('Message cannot be empty.');
                }
                
                if (strlen($message) > 2000) {
                    throw new Exception('Message is too long (max 2000 characters).');
                }
                
                // Verify user has access to this complaint
                $verify_stmt = $conn->prepare('
                    SELECT id, user_id, barangay_id 
                    FROM complaint 
                    WHERE id = ? AND deleted_at IS NULL
                ');
                $verify_stmt->bind_param('i', $complaint_id);
                $verify_stmt->execute();
                $verify_res = $verify_stmt->get_result();
                
                if ($verify_res->num_rows === 0) {
                    throw new Exception('Complaint not found.');
                }
                
                $complaint_data = $verify_res->fetch_assoc();
                $verify_stmt->close();
                
                // Check access
                $has_access = false;
                if ($user_role === ROLE_USER && $complaint_data['user_id'] == $user_id) {
                    $has_access = true;
                } elseif (in_array($user_role, [ROLE_STAFF, ROLE_ADMIN]) && $complaint_data['barangay_id'] == $user_barangay_id) {
                    $has_access = true;
                } elseif ($user_role === ROLE_SUPERADMIN) {
                    $has_access = true;
                }
                
                if (!$has_access) {
                    throw new Exception('You do not have permission to comment on this complaint.');
                }
                
                // Insert comment
                $insert_stmt = $conn->prepare('
                    INSERT INTO complaint_comment (complaint_id, user_id, message, created_at)
                    VALUES (?, ?, ?, NOW())
                ');
                $insert_stmt->bind_param('iis', $complaint_id, $user_id, $message);
                $insert_stmt->execute();
                $insert_stmt->close();
                
                activity_log($user_id, 'Added comment to complaint', 'complaint', $complaint_id);
                $success_message = 'Message posted successfully.';
                break;
                
            case 'mark_in_progress':
                // Staff only - open → in_progress
                if ($user_role !== ROLE_STAFF) {
                    throw new Exception('Unauthorized action.');
                }
                
                $verify_stmt = $conn->prepare('
                    SELECT id, complaint_status_id, barangay_id 
                    FROM complaint 
                    WHERE id = ? AND barangay_id = ? AND complaint_status_id = 1 AND deleted_at IS NULL
                ');
                $verify_stmt->bind_param('ii', $complaint_id, $user_barangay_id);
                $verify_stmt->execute();
                $verify_res = $verify_stmt->get_result();
                
                if ($verify_res->num_rows === 0) {
                    throw new Exception('Complaint not found or cannot be updated.');
                }
                $verify_stmt->close();
                
                $update_stmt = $conn->prepare('UPDATE complaint SET complaint_status_id = 2, updated_at = NOW() WHERE id = ?');
                $update_stmt->bind_param('i', $complaint_id);
                $update_stmt->execute();
                $update_stmt->close();
                
                activity_log($user_id, 'Marked complaint as in progress', 'complaint', $complaint_id);
                $success_message = 'Complaint marked as in progress.';
                break;
                
            case 'mark_resolved':
                // Staff only - in_progress → resolved
                if ($user_role !== ROLE_STAFF) {
                    throw new Exception('Unauthorized action.');
                }
                
                $remarks = trim($_POST['resolution_remarks'] ?? '');
                if (empty($remarks)) {
                    throw new Exception('Resolution remarks are required.');
                }
                
                $verify_stmt = $conn->prepare('
                    SELECT id, complaint_status_id, barangay_id 
                    FROM complaint 
                    WHERE id = ? AND barangay_id = ? AND complaint_status_id = 2 AND deleted_at IS NULL
                ');
                $verify_stmt->bind_param('ii', $complaint_id, $user_barangay_id);
                $verify_stmt->execute();
                $verify_res = $verify_stmt->get_result();
                
                if ($verify_res->num_rows === 0) {
                    throw new Exception('Complaint not found or not in progress.');
                }
                $verify_stmt->close();
                
                $update_stmt = $conn->prepare('
                    UPDATE complaint 
                    SET complaint_status_id = 3, remarks = ?, updated_at = NOW() 
                    WHERE id = ?
                ');
                $update_stmt->bind_param('si', $remarks, $complaint_id);
                $update_stmt->execute();
                $update_stmt->close();
                
                activity_log($user_id, 'Marked complaint as resolved', 'complaint', $complaint_id);
                $success_message = 'Complaint marked as resolved.';
                break;
                
            case 'confirm_resolution':
                // Admin only - resolved → closed
                if ($user_role !== ROLE_ADMIN) {
                    throw new Exception('Unauthorized action.');
                }
                
                $verify_stmt = $conn->prepare('
                    SELECT id, complaint_status_id, barangay_id 
                    FROM complaint 
                    WHERE id = ? AND barangay_id = ? AND complaint_status_id = 3 AND deleted_at IS NULL
                ');
                $verify_stmt->bind_param('ii', $complaint_id, $user_barangay_id);
                $verify_stmt->execute();
                $verify_res = $verify_stmt->get_result();
                
                if ($verify_res->num_rows === 0) {
                    throw new Exception('Complaint not found or not resolved.');
                }
                $verify_stmt->close();
                
                $update_stmt = $conn->prepare('
                    UPDATE complaint 
                    SET complaint_status_id = 4, updated_at = NOW() 
                    WHERE id = ?
                ');
                $update_stmt->bind_param('i', $complaint_id);
                $update_stmt->execute();
                $update_stmt->close();
                
                activity_log($user_id, 'Confirmed and closed complaint', 'complaint', $complaint_id);
                $success_message = 'Complaint has been confirmed and closed.';
                break;
                
            case 'reopen_complaint':
                // Admin only - resolved → in_progress
                if ($user_role !== ROLE_ADMIN) {
                    throw new Exception('Unauthorized action.');
                }
                
                $verify_stmt = $conn->prepare('
                    SELECT id, complaint_status_id, barangay_id 
                    FROM complaint 
                    WHERE id = ? AND barangay_id = ? AND complaint_status_id = 3 AND deleted_at IS NULL
                ');
                $verify_stmt->bind_param('ii', $complaint_id, $user_barangay_id);
                $verify_stmt->execute();
                $verify_res = $verify_stmt->get_result();
                
                if ($verify_res->num_rows === 0) {
                    throw new Exception('Complaint not found or cannot be reopened.');
                }
                $verify_stmt->close();
                
                $update_stmt = $conn->prepare('
                    UPDATE complaint 
                    SET complaint_status_id = 2, updated_at = NOW() 
                    WHERE id = ?
                ');
                $update_stmt->bind_param('i', $complaint_id);
                $update_stmt->execute();
                $update_stmt->close();
                
                activity_log($user_id, 'Reopened complaint for further action', 'complaint', $complaint_id);
                $success_message = 'Complaint has been reopened for staff to continue handling.';
                break;
                
            default:
                throw new Exception('Invalid action.');
        }
        
        $conn->commit();
        flash_set($success_message, 'success');
        header('Location: index.php?nav=complaint-ticket&id=' . $complaint_id);
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log('Complaint Action Error: ' . $e->getMessage());
        $errors[] = $e->getMessage();
    }
}

// Fetch complaint details
$complaint = null;
$attachments = [];

$stmt = $conn->prepare('
    SELECT 
        c.id, c.user_id, c.barangay_id, c.title, c.description, c.is_anonymous,
        c.complaint_status_id, c.remarks,
        c.created_at, c.updated_at,
        cs.name as status_name,
        p.first_name, p.last_name,
        b.name as barangay_name
    FROM complaint c
    LEFT JOIN complaint_status cs ON c.complaint_status_id = cs.id
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN profile p ON u.id = p.user_id
    LEFT JOIN barangay b ON c.barangay_id = b.id
    WHERE c.id = ? AND c.deleted_at IS NULL
');

if (!$stmt) {
    die('Database error: ' . $conn->error);
}

$stmt->bind_param('i', $complaint_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    flash_set('Complaint not found.', 'error');
    header('Location: index.php?nav=manage-complaints');
    exit;
}

$complaint = $res->fetch_assoc();
$stmt->close();

// Access control check
$can_view = false;

if ($user_role === ROLE_USER) {
    // Users can only view their own complaints
    $can_view = ($complaint['user_id'] == $user_id);
} elseif (in_array($user_role, [ROLE_STAFF, ROLE_ADMIN])) {
    // Staff/Admin can view complaints from their barangay
    $can_view = ($complaint['barangay_id'] == $user_barangay_id);
} elseif ($user_role === ROLE_SUPERADMIN) {
    // Superadmin can view all
    $can_view = true;
}

if (!$can_view) {
    flash_set('You do not have permission to view this complaint.', 'error');
    header('Location: index.php?nav=manage-complaints');
    exit;
}

// Fetch attachments
$attach_stmt = $conn->prepare('
    SELECT id, file_path, uploaded_at
    FROM complaint_attachment
    WHERE complaint_id = ?
    ORDER BY uploaded_at ASC
');

if ($attach_stmt) {
    $attach_stmt->bind_param('i', $complaint_id);
    $attach_stmt->execute();
    $attach_res = $attach_stmt->get_result();
    while ($row = $attach_res->fetch_assoc()) {
        $attachments[] = $row;
    }
    $attach_stmt->close();
}

// Fetch comments/messages
$comments = [];
$comment_stmt = $conn->prepare('
    SELECT 
        cc.id, cc.message, cc.created_at,
        u.username, u.usertype_id,
        p.first_name, p.last_name
    FROM complaint_comment cc
    LEFT JOIN users u ON cc.user_id = u.id
    LEFT JOIN profile p ON u.id = p.user_id
    WHERE cc.complaint_id = ?
    ORDER BY cc.created_at ASC
');

if ($comment_stmt) {
    $comment_stmt->bind_param('i', $complaint_id);
    $comment_stmt->execute();
    $comment_res = $comment_stmt->get_result();
    while ($row = $comment_res->fetch_assoc()) {
        $comments[] = $row;
    }
    $comment_stmt->close();
}

// Status badge configuration
$status_badges = [
    1 => ['class' => 'bg-danger', 'icon' => 'fa-exclamation-circle', 'text' => 'Open'],
    2 => ['class' => 'bg-info', 'icon' => 'fa-hourglass-half', 'text' => 'In Progress'],
    3 => ['class' => 'bg-warning text-dark', 'icon' => 'fa-check-circle', 'text' => 'Resolved'],
    4 => ['class' => 'bg-success', 'icon' => 'fa-lock', 'text' => 'Closed']
];

$current_status = $complaint['complaint_status_id'];
$status_config = $status_badges[$current_status] ?? ['class' => 'bg-secondary', 'icon' => 'fa-question', 'text' => 'Unknown'];

$page_title = 'Complaint Details';
require_once __DIR__ . '/../public/header.php';
?>

<div class="container my-4">
    <!-- Breadcrumb Navigation -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="index.php?nav=manage-complaints" class="text-decoration-none">My Complaints</a></li>
            <li class="breadcrumb-item active">Complaint #<?php echo $complaint['id']; ?></li>
        </ol>
    </nav>

    <!-- Header with Title and Status -->
    <div class="d-flex justify-content-between align-items-start gap-3 mb-5">
        <div class="flex-grow-1">
            <h1 class="h2 fw-bold mb-2 text-dark">
                <i class="fas fa-ticket-alt me-3" style="color: #0d6efd;"></i><?php echo htmlspecialchars($complaint['title']); ?>
            </h1>
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <span class="badge <?php echo $status_config['class']; ?> p-2 fs-6">
                    <i class="fas <?php echo $status_config['icon']; ?> me-2"></i><?php echo $status_config['text']; ?>
                </span>
                <p class="text-muted mb-0 small">
                    <i class="far fa-calendar me-2"></i>
                    <?php echo date('F j, Y \a\t g:i A', strtotime($complaint['created_at'])); ?>
                    <?php if (!$complaint['is_anonymous'] || in_array($user_role, [ROLE_STAFF, ROLE_ADMIN, ROLE_SUPERADMIN])): ?>
                        <span class="ms-2">•</span> <i class="far fa-user me-2 ms-2"></i><?php echo htmlspecialchars($complaint['first_name'] . ' ' . $complaint['last_name']); ?>
                    <?php else: ?>
                        <span class="ms-2 badge bg-secondary">Anonymous</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <h5 class="alert-heading mb-3">
                <i class="fas fa-exclamation-circle me-2"></i>Errors Encountered
            </h5>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li class="mb-2"><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Main Content Column -->
        <div class="col-lg-8">
            <!-- Complaint Details Card -->
            <div class="card border-0 shadow-sm mb-4 rounded-3">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="fas fa-file-alt me-2" style="color: #0d6efd;"></i>Complaint Details
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div>
                        <p class="text-muted small text-uppercase letter-spacing fw-600 mb-2">
                            <i class="far fa-file-alt me-2"></i>Description
                        </p>
                        <p class="mb-0 text-dark">
                            <?php echo htmlspecialchars($complaint['description']); ?>
                        </p>
                    </div>
                    <?php if (!empty($complaint['remarks']) && $current_status >= 3): ?>
                        <div class="mt-4">
                            <p class="text-muted small text-uppercase letter-spacing fw-600 mb-2">
                                <i class="fas fa-user-shield me-2"></i>Staff Resolution
                            </p>
                            <div style="background: linear-gradient(135deg, #e7f3ff 0%, #f0f7ff 100%); border-left: 4px solid #0d6efd; padding: 0.75rem 1rem;">
                                <p class="mb-0 text-dark" style="font-size: 0.95rem;">
                                    <?php echo htmlspecialchars($complaint['remarks']); ?>
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Attachments Card -->
            <?php if (!empty($attachments)): ?>
                <div class="card border-0 shadow-sm mb-4 rounded-3">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="mb-0 fw-bold text-dark">
                            <i class="fas fa-paperclip me-2" style="color: #6c757d;"></i>Attachments (<?php echo count($attachments); ?>)
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="list-group list-group-flush">
                            <?php foreach ($attachments as $attachment): ?>
                                <?php
                                $file_path = __DIR__ . '/../' . $attachment['file_path'];
                                $file_exists = file_exists($file_path);
                                $file_size = $file_exists ? filesize($file_path) : 0;
                                $file_name = basename($attachment['file_path']);
                                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                                
                                $icon = 'fa-file';
                                $icon_color = 'text-secondary';
                                if ($file_ext === 'pdf') {
                                    $icon = 'fa-file-pdf';
                                    $icon_color = 'text-danger';
                                } elseif (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                    $icon = 'fa-file-image';
                                    $icon_color = 'text-info';
                                }
                                ?>
                                <div class="list-group-item border-0 py-3 px-0">
                                    <div class="d-flex justify-content-between align-items-center gap-3">
                                        <div class="d-flex align-items-center gap-3 flex-grow-1 min-w-0">
                                            <div class="flex-shrink-0">
                                                <i class="fas <?php echo $icon; ?> fa-xl <?php echo $icon_color; ?>"></i>
                                            </div>
                                            <div class="flex-grow-1 min-w-0">
                                                <p class="mb-2 fw-600 text-truncate text-dark">
                                                    <?php echo htmlspecialchars($file_name); ?>
                                                </p>
                                                <small class="text-muted">
                                                    <i class="fas fa-database me-1"></i>
                                                    <?php echo $file_exists ? number_format($file_size / 1024, 2) . ' KB' : 'File not found'; ?>
                                                    <span class="ms-3">
                                                        <i class="far fa-calendar me-1"></i>
                                                        <?php echo date('M j, Y g:i A', strtotime($attachment['uploaded_at'])); ?>
                                                    </span>
                                                </small>
                                            </div>
                                        </div>
                                        <?php if ($file_exists): ?>
                                            <a href="<?php echo htmlspecialchars($attachment['file_path']); ?>" 
                                               class="btn btn-sm btn-outline-primary flex-shrink-0" 
                                               download="<?php echo htmlspecialchars($file_name); ?>">
                                                <i class="fas fa-download me-1"></i>Download
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Messages/Comments Section -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="fas fa-comments me-2" style="color: #198754;"></i>Messages & Updates (<?php echo count($comments); ?>)
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="messages-container" style="max-height: 550px; overflow-y: auto;">
                        <?php if (empty($comments)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-inbox fa-3x d-block mb-3 opacity-50"></i>
                                <p class="mb-0">No messages yet. Start the conversation!</p>
                            </div>
                        <?php else: ?>
                            <div class="comments-list space-y-3">
                                <?php foreach ($comments as $comment): ?>
                                    <?php
                                    $is_staff_message = in_array($comment['usertype_id'], [ROLE_STAFF, ROLE_ADMIN, ROLE_SUPERADMIN]);
                                    $commenter_name = htmlspecialchars(trim($comment['first_name'] . ' ' . $comment['last_name']));
                                    if (empty($commenter_name)) {
                                        $commenter_name = htmlspecialchars($comment['username']);
                                    }
                                    ?>
                                    <div class="comment-item mb-4">
                                        <div class="d-flex gap-3 align-items-flex-start">
                                            <div class="comment-avatar flex-shrink-0">
                                                <div class="rounded-circle p-2" style="width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; background: <?php echo $is_staff_message ? '#e7f3ff' : '#f8f9fa'; ?>;">
                                                    <i class="fas <?php echo $is_staff_message ? 'fa-user-shield' : 'fa-user'; ?>" 
                                                       style="color: <?php echo $is_staff_message ? '#0d6efd' : '#6c757d'; ?>; font-size: 1.2rem;"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center gap-2 mb-2">
                                                    <strong class="text-dark"><?php echo $commenter_name; ?></strong>
                                                    <?php if ($is_staff_message): ?>
                                                        <span class="badge bg-primary px-2 py-1" style="font-size: 0.65rem;">
                                                            <i class="fas fa-shield-alt me-1"></i>Staff
                                                        </span>
                                                    <?php endif; ?>
                                                    <small class="text-muted ms-auto">
                                                        <i class="far fa-clock me-1"></i>
                                                        <?php echo date('M j, Y g:i A', strtotime($comment['created_at'])); ?>
                                                    </small>
                                                </div>
                                                <div class="comment-body p-3 rounded-2" 
                                                     style="background: <?php echo $is_staff_message ? '#e7f3ff' : '#f8f9fa'; ?>; 
                                                            border-left: 3px solid <?php echo $is_staff_message ? '#0d6efd' : '#dee2e6'; ?>;
                                                            word-wrap: break-word;">
                                                    <?php echo nl2br(htmlspecialchars($comment['message'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Add Comment Form -->
                    <?php if ($current_status != 4): // Not closed ?>
                        <div class="border-top mt-4 pt-4">
                            <form method="POST" id="commentForm">
                                <input type="hidden" name="action" value="add_comment">
                                <div class="mb-3">
                                    <label for="message" class="form-label fw-600 text-dark mb-2">
                                        <i class="fas fa-pen me-2" style="color: #198754;"></i>Add a Message
                                    </label>
                                    <textarea 
                                        class="form-control border-0 bg-light rounded-2" 
                                        id="message" 
                                        name="message" 
                                        rows="3"
                                        maxlength="2000"
                                        placeholder="<?php echo $user_role === ROLE_USER ? 'Ask a question or provide additional information...' : 'Reply to the complainant or provide updates...'; ?>"
                                        required
                                        style="resize: none; font-size: 0.95rem;"
                                    ></textarea>
                                    <small class="text-muted d-block mt-2">
                                        <span id="charCount">0</span>/2000 characters
                                    </small>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success py-2 fw-600">
                                        <i class="fas fa-paper-plane me-2"></i>Send Message
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success mt-4 border-0 mb-0">
                            <i class="fas fa-lock me-2"></i>This complaint is closed. Messaging is disabled.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar Column -->
        <div class="col-lg-4">
            <!-- Status Timeline Card -->
            <div class="card border-0 shadow-sm mb-4 rounded-3">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="fas fa-stream me-2" style="color: #6f42c1;"></i>Status Timeline
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="timeline">
                        <!-- Open -->
                        <div class="timeline-item <?php echo $current_status >= 1 ? 'active' : ''; ?>">
                            <div class="timeline-marker">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <div class="timeline-content">
                                <h6 class="mb-1 fw-600">Open</h6>
                                <small class="text-muted">
                                    <?php echo date('M j, Y', strtotime($complaint['created_at'])); ?>
                                </small>
                            </div>
                        </div>

                        <!-- In Progress -->
                        <div class="timeline-item <?php echo $current_status >= 2 ? 'active' : ''; ?>">
                            <div class="timeline-marker">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <div class="timeline-content">
                                <h6 class="mb-1 fw-600">In Progress</h6>
                                <small class="text-muted">
                                    <?php echo $current_status >= 2 ? 'Being handled by staff' : 'Pending'; ?>
                                </small>
                            </div>
                        </div>

                        <!-- Resolved -->
                        <div class="timeline-item <?php echo $current_status >= 3 ? 'active' : ''; ?>">
                            <div class="timeline-marker">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="timeline-content">
                                <h6 class="mb-1 fw-600">Resolved</h6>
                                <small class="text-muted">
                                    <?php echo $current_status >= 3 ? 'Resolution provided' : 'Pending'; ?>
                                </small>
                            </div>
                        </div>

                        <!-- Closed -->
                        <div class="timeline-item <?php echo $current_status >= 4 ? 'active' : ''; ?>">
                            <div class="timeline-marker">
                                <i class="fas fa-lock"></i>
                            </div>
                            <div class="timeline-content">
                                <h6 class="mb-1 fw-600">Closed</h6>
                                <small class="text-muted">
                                    <?php echo $current_status >= 4 ? 'Complaint finalized' : 'Pending'; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions Card -->
            <div class="card border-0 shadow-sm mb-4 rounded-3">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="fas fa-bolt me-2" style="color: #ffc107;"></i>Actions
                    </h5>
                </div>
                <div class="card-body p-4">
                    <?php if ($current_status == 4): ?>
                        <div class="alert alert-success border-0 mb-0">
                            <i class="fas fa-check-circle me-2"></i>
                            This complaint is closed and read-only.
                        </div>
                    <?php elseif ($current_status == 3 && $user_role === ROLE_USER): ?>
                        <div class="alert alert-info border-0 mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Your complaint has been resolved. Admin will close it soon.
                        </div>
                    <?php endif; ?>

                    <!-- User Actions -->
                    <?php if ($user_role === ROLE_USER && $complaint['user_id'] == $user_id): ?>
                        <?php if ($current_status == 1): ?>
                            <div class="d-grid gap-2">
                                <a href="index.php?nav=edit-complaint&id=<?php echo $complaint['id']; ?>" class="btn btn-primary py-2 fw-600">
                                    <i class="fas fa-edit me-2"></i>Edit Complaint
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Staff Actions -->
                    <?php if ($user_role === ROLE_STAFF): ?>
                        <?php if ($current_status == 1): ?>
                            <form method="POST" class="d-grid gap-2">
                                <input type="hidden" name="action" value="mark_in_progress">
                                <button type="submit" class="btn btn-info text-white py-2 fw-600">
                                    <i class="fas fa-play-circle me-2"></i>Mark as In Progress
                                </button>
                            </form>
                        <?php elseif ($current_status == 2): ?>
                            <button type="button" class="btn btn-warning text-dark w-100 py-2 fw-600" data-bs-toggle="modal" data-bs-target="#resolveModal">
                                <i class="fas fa-check-circle me-2"></i>Mark as Resolved
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Admin Actions -->
                    <?php if ($user_role === ROLE_ADMIN): ?>
                        <?php if ($current_status == 3): ?>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-success py-2 fw-600" data-bs-toggle="modal" data-bs-target="#confirmModal">
                                    <i class="fas fa-lock me-2"></i>Confirm & Close
                                </button>
                                <button type="button" class="btn btn-outline-warning py-2 fw-600" data-bs-toggle="modal" data-bs-target="#reopenModal">
                                    <i class="fas fa-undo me-2"></i>Return to Staff
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Info Card -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="fas fa-info-circle me-2" style="color: #0d6efd;"></i>Details
                    </h5>
                </div>
                <div class="card-body p-4">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted fw-600 ps-0">ID</td>
                            <td class="text-end pe-0">
                                <span class="badge bg-light text-dark">#<?php echo $complaint['id']; ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-600 ps-0">Barangay</td>
                            <td class="text-end pe-0 text-dark"><?php echo htmlspecialchars($complaint['barangay_name']); ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-600 ps-0">Status</td>
                            <td class="text-end pe-0"><span class="badge <?php echo $status_config['class']; ?>"><?php echo $status_config['text']; ?></span></td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-600 ps-0">Submitted</td>
                            <td class="text-end pe-0 text-dark"><?php echo date('M j, Y', strtotime($complaint['created_at'])); ?></td>
                        </tr>
                        <?php if (!empty($complaint['updated_at']) && $complaint['updated_at'] != $complaint['created_at']): ?>
                        <tr>
                            <td class="text-muted fw-600 ps-0">Updated</td>
                            <td class="text-end pe-0 text-dark"><?php echo date('M j, Y', strtotime($complaint['updated_at'])); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Resolve Modal (Staff) -->
<div class="modal fade" id="resolveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <form method="POST">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-check-circle me-2" style="color: #ffc107;"></i>Mark as Resolved
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="mark_resolved">
                    <div class="mb-0">
                        <label for="resolution_remarks" class="form-label fw-600 mb-2">Resolution Details</label>
                        <textarea 
                            class="form-control border-1 rounded-2" 
                            id="resolution_remarks" 
                            name="resolution_remarks" 
                            rows="4"
                            placeholder="Describe how the complaint was resolved..."
                            required
                            style="min-height: 120px; font-size: 0.95rem;"
                        ></textarea>
                        <small class="text-muted d-block mt-2">
                            <i class="fas fa-info-circle me-1"></i>
                            Explain what actions were taken to resolve this complaint.
                        </small>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark fw-600 px-4">
                        <i class="fas fa-check-circle me-2"></i>Mark Resolved
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Confirm Modal (Admin) -->
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <form method="POST">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-lock me-2" style="color: #198754;"></i>Confirm & Close
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="confirm_resolution">
                    <div class="alert alert-success border-0 mb-3">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Ready to finalize this complaint?</strong>
                    </div>
                    <p class="text-muted mb-0">
                        Once closed, no further changes can be made to this complaint. This action cannot be undone.
                    </p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-600 px-4">
                        <i class="fas fa-lock me-2"></i>Confirm & Close
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reopen Modal (Admin) -->
<div class="modal fade" id="reopenModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <form method="POST">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-undo me-2" style="color: #ffc107;"></i>Return to Staff
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="reopen_complaint">
                    <div class="alert alert-warning border-0 mb-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Return this complaint to staff?</strong>
                    </div>
                    <p class="text-muted mb-0">
                        This will move the complaint back to "In Progress" status for staff to handle further actions.
                    </p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark fw-600 px-4">
                        <i class="fas fa-undo me-2"></i>Return to Staff
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Timeline Styling */
.timeline {
    position: relative;
    padding-left: 35px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 12px;
    top: 12px;
    bottom: 12px;
    width: 2px;
    background: linear-gradient(to bottom, #0d6efd 0%, #0d6efd 25%, #198754 50%, #ffc107 75%, #28a745 100%);
    border-radius: 10px;
}

.timeline-item {
    position: relative;
    padding-bottom: 28px;
    opacity: 0.5;
    transition: all 0.3s ease;
}

.timeline-item:hover {
    opacity: 0.75;
}

.timeline-item.active {
    opacity: 1;
}

.timeline-item:last-child {
    padding-bottom: 0;
}

.timeline-marker {
    position: absolute;
    left: -30px;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #fff;
    border: 2px solid #dee2e6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    color: #6c757d;
    transition: all 0.3s ease;
    top: 2px;
}

.timeline-item.active .timeline-marker {
    width: 32px;
    height: 32px;
    left: -32px;
    background: #fff;
    border-width: 3px;
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
    color: #fff;
    font-size: 13px;
}

.timeline-item:nth-child(1).active .timeline-marker {
    background: #0d6efd;
    border-color: #0d6efd;
}

.timeline-item:nth-child(2).active .timeline-marker {
    background: #0d6efd;
    border-color: #0d6efd;
}

.timeline-item:nth-child(3).active .timeline-marker {
    background: #ffc107;
    border-color: #ffc107;
    color: #212529;
}

.timeline-item:nth-child(4).active .timeline-marker {
    background: #28a745;
    border-color: #28a745;
}

.timeline-content {
    padding-top: 2px;
}

.timeline-content h6 {
    margin: 0;
    font-size: 0.95rem;
    color: #212529;
}

.timeline-content small {
    font-size: 0.8rem;
}

/* Message Container */
.messages-container {
    scroll-behavior: smooth;
}

.messages-container::-webkit-scrollbar {
    width: 6px;
}

.messages-container::-webkit-scrollbar-track {
    background: #f1f3f5;
    border-radius: 10px;
}

.messages-container::-webkit-scrollbar-thumb {
    background: #ced4da;
    border-radius: 10px;
}

.messages-container::-webkit-scrollbar-thumb:hover {
    background: #adb5bd;
}

/* Comment Items */
.comment-item {
    animation: fadeInUp 0.3s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(15px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.comment-avatar {
    flex-shrink: 0;
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.comment-body {
    word-break: break-word;
    line-height: 1.6;
    font-size: 0.95rem;
    color: #212529;
}

/* Space Utility */
.space-y-3 > * + * {
    margin-top: 1rem;
}

/* Button Enhancements */
.btn {
    transition: all 0.2s ease;
    border: none;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.btn-primary:hover {
    box-shadow: 0 2px 8px rgba(13, 110, 253, 0.3);
}

.btn-success:hover {
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
}

/* Card Enhancements */
.card {
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08) !important;
}

/* Form Styling */
.form-control, .form-select {
    border: 1px solid #dee2e6;
    border-radius: 0.5rem;
    transition: all 0.2s ease;
}

.form-control:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
}

/* Alert Styling */
.alert {
    border-radius: 0.5rem;
}

/* Modal Enhancements */
.modal-content {
    border-radius: 1rem;
}

.modal-header {
    background: linear-gradient(135deg, #f8f9fa, #f1f3f5);
}

/* Responsive Improvements */
@media (max-width: 768px) {
    .container {
        padding-left: 1rem;
        padding-right: 1rem;
    }
    
    .row.g-4 {
        gap: 1.5rem !important;
    }
    
    h1.h2 {
        font-size: 1.5rem;
    }
}

/* Breadcrumb Styling */
.breadcrumb {
    background: transparent;
    padding: 0;
    margin-bottom: 1.5rem;
}

.breadcrumb-item a {
    color: #0d6efd;
    font-weight: 500;
}

.breadcrumb-item.active {
    color: #6c757d;
    font-weight: 500;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Character counter for comment form
    const messageTextarea = document.getElementById('message');
    const charCount = document.getElementById('charCount');
    
    if (messageTextarea && charCount) {
        // Initialize counter
        updateCharCounter();
        
        messageTextarea.addEventListener('input', updateCharCounter);
        
        function updateCharCounter() {
            const length = messageTextarea.value.length;
            charCount.textContent = length;
            
            // Change color based on character count
            if (length > 1900) {
                charCount.style.color = '#dc3545'; // Red
                charCount.style.fontWeight = '700';
            } else if (length > 1700) {
                charCount.style.color = '#ffc107'; // Orange
                charCount.style.fontWeight = '600';
            } else if (length > 1500) {
                charCount.style.color = '#6c757d'; // Gray
                charCount.style.fontWeight = 'normal';
            } else {
                charCount.style.color = '#6c757d'; // Gray
                charCount.style.fontWeight = 'normal';
            }
        }
    }
    
    // Auto-scroll to latest comment when page loads
    const commentsContainer = document.querySelector('.messages-container');
    if (commentsContainer && commentsContainer.scrollHeight > commentsContainer.clientHeight) {
        // Delay scroll to ensure DOM is fully rendered
        setTimeout(function() {
            commentsContainer.scrollTop = commentsContainer.scrollHeight;
        }, 100);
    }
    
    // Form submission feedback
    const commentForm = document.getElementById('commentForm');
    if (commentForm) {
        commentForm.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
