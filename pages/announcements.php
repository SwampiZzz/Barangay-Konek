<?php
require_once __DIR__ . '/../config.php';

$page_title = 'Announcements';
$user_id = !empty($_SESSION['user_id']) ? current_user_id() : null;
$user_role = $user_id ? current_user_role() : null;
$user_barangay_id = $user_id ? current_user_barangay_id() : null;

$errors = [];
$success_message = '';

// Check if viewing single announcement
if (isset($_GET['view'])) {
    require_once __DIR__ . '/announcement-view.php';
    exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $user_id) {
    $action = $_POST['action'];
    
    try {
        $conn->begin_transaction();
        
        switch ($action) {
            case 'create_announcement':
                // Only staff and admin can create
                if (!in_array($user_role, [ROLE_STAFF, ROLE_ADMIN])) {
                    throw new Exception('Unauthorized action.');
                }
                
                $title = trim($_POST['title'] ?? '');
                $content = trim($_POST['content'] ?? '');
                
                if (empty($title) || empty($content)) {
                    throw new Exception('Title and content are required.');
                }
                
                if (strlen($title) < 5 || strlen($title) > 255) {
                    throw new Exception('Title must be between 5 and 255 characters.');
                }
                
                if (strlen($content) < 10) {
                    throw new Exception('Content must be at least 10 characters.');
                }
                
                // Handle image upload
                $image_path = null;
                if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    // Check for upload errors
                    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                        $upload_errors = [
                            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
                        ];
                        throw new Exception($upload_errors[$_FILES['image']['error']] ?? 'Unknown upload error');
                    }
                    
                    $upload_dir = __DIR__ . '/../storage/app/private/announcements/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (!in_array($file_ext, $allowed_ext)) {
                        throw new Exception('Invalid image format. Only JPG, PNG, and GIF allowed.');
                    }
                    
                    if ($_FILES['image']['size'] > 5242880) { // 5MB
                        throw new Exception('Image size must not exceed 5MB.');
                    }
                    
                    // Validate MIME type
                    $mime_type = mime_content_type($_FILES['image']['tmp_name']);
                    if (!in_array($mime_type, ['image/jpeg', 'image/png', 'image/gif'])) {
                        throw new Exception('Invalid image type.');
                    }
                    
                    $filename = 'announcement_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_ext;
                    $target_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                        throw new Exception('Failed to upload image.');
                    }
                    
                    $image_path = 'storage/app/private/announcements/' . $filename;
                }
                
                $stmt = $conn->prepare('INSERT INTO announcement (title, content, image_path, user_id, barangay_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
                $stmt->bind_param('sssii', $title, $content, $image_path, $user_id, $user_barangay_id);
                $stmt->execute();
                $announcement_id = $conn->insert_id;
                $stmt->close();
                
                activity_log($user_id, 'Created announcement', 'announcement', $announcement_id);
                $success_message = 'Announcement created successfully.';
                break;
                
            case 'edit_announcement':
                if (!in_array($user_role, [ROLE_STAFF, ROLE_ADMIN])) {
                    throw new Exception('Unauthorized action.');
                }
                
                $announcement_id = intval($_POST['announcement_id'] ?? 0);
                $title = trim($_POST['title'] ?? '');
                $content = trim($_POST['content'] ?? '');
                
                // Validate inputs
                if (empty($title) || empty($content)) {
                    throw new Exception('Title and content are required.');
                }
                
                if (strlen($title) < 5 || strlen($title) > 255) {
                    throw new Exception('Title must be between 5 and 255 characters.');
                }
                
                if (strlen($content) < 10) {
                    throw new Exception('Content must be at least 10 characters.');
                }
                
                // Verify ownership
                $verify_stmt = $conn->prepare('SELECT id, user_id, image_path FROM announcement WHERE id = ? AND barangay_id = ? AND deleted_at IS NULL');
                $verify_stmt->bind_param('ii', $announcement_id, $user_barangay_id);
                $verify_stmt->execute();
                $verify_res = $verify_stmt->get_result();
                
                if ($verify_res->num_rows === 0) {
                    throw new Exception('Announcement not found.');
                }
                
                $ann_data = $verify_res->fetch_assoc();
                $verify_stmt->close();
                
                // Only creator or admin can edit
                if ($ann_data['user_id'] != $user_id && $user_role !== ROLE_ADMIN) {
                    throw new Exception('You can only edit your own announcements.');
                }
                
                $image_path = $ann_data['image_path'];
                
                // Validate announcement ID
                if ($announcement_id <= 0) {
                    throw new Exception('Invalid announcement ID.');
                }
                
                // Handle new image upload
                if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                        throw new Exception('Failed to upload image. Please try again.');
                    }
                    
                    // Delete old image if exists
                    if ($image_path && file_exists(__DIR__ . '/../' . $image_path)) {
                        @unlink(__DIR__ . '/../' . $image_path);
                    }
                    
                    $upload_dir = __DIR__ . '/../storage/app/private/announcements/';
                    $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (!in_array($file_ext, $allowed_ext)) {
                        throw new Exception('Invalid image format.');
                    }
                    
                    if ($_FILES['image']['size'] > 5242880) {
                        throw new Exception('Image size must not exceed 5MB.');
                    }
                    
                    $mime_type = mime_content_type($_FILES['image']['tmp_name']);
                    if (!in_array($mime_type, ['image/jpeg', 'image/png', 'image/gif'])) {
                        throw new Exception('Invalid image type.');
                    }
                    
                    $filename = 'announcement_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_ext;
                    $target_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                        throw new Exception('Failed to upload image.');
                    }
                    
                    $image_path = 'storage/app/private/announcements/' . $filename;
                }
                
                $update_stmt = $conn->prepare('UPDATE announcement SET title = ?, content = ?, image_path = ?, updated_at = NOW() WHERE id = ?');
                $update_stmt->bind_param('sssi', $title, $content, $image_path, $announcement_id);
                $update_stmt->execute();
                $update_stmt->close();
                
                activity_log($user_id, 'Updated announcement', 'announcement', $announcement_id);
                $success_message = 'Announcement updated successfully.';
                break;
                
            case 'delete_announcement':
                if (!in_array($user_role, [ROLE_STAFF, ROLE_ADMIN])) {
                    throw new Exception('Unauthorized action.');
                }
                
                $announcement_id = intval($_POST['announcement_id'] ?? 0);
                
                if ($announcement_id <= 0) {
                    throw new Exception('Invalid announcement ID.');
                }
                
                $verify_stmt = $conn->prepare('SELECT id, user_id FROM announcement WHERE id = ? AND barangay_id = ? AND deleted_at IS NULL');
                $verify_stmt->bind_param('ii', $announcement_id, $user_barangay_id);
                $verify_stmt->execute();
                $verify_res = $verify_stmt->get_result();
                
                if ($verify_res->num_rows === 0) {
                    throw new Exception('Announcement not found.');
                }
                
                $ann_data = $verify_res->fetch_assoc();
                $verify_stmt->close();
                
                if ($ann_data['user_id'] != $user_id && $user_role !== ROLE_ADMIN) {
                    throw new Exception('You can only delete your own announcements.');
                }
                
                $delete_stmt = $conn->prepare('UPDATE announcement SET deleted_at = NOW() WHERE id = ?');
                $delete_stmt->bind_param('i', $announcement_id);
                $delete_stmt->execute();
                $delete_stmt->close();
                
                activity_log($user_id, 'Deleted announcement', 'announcement', $announcement_id);
                $success_message = 'Announcement deleted successfully.';
                break;
                
            case 'add_comment':
                $announcement_id = intval($_POST['announcement_id'] ?? 0);
                $message = trim($_POST['message'] ?? '');
                
                if (empty($message)) {
                    throw new Exception('Comment cannot be empty.');
                }
                
                if (strlen($message) > 1000) {
                    throw new Exception('Comment is too long (max 1000 characters).');
                }
                
                // Verify announcement exists and is accessible
                $verify_stmt = $conn->prepare('SELECT id FROM announcement WHERE id = ? AND barangay_id = ? AND deleted_at IS NULL');
                $verify_stmt->bind_param('ii', $announcement_id, $user_barangay_id);
                $verify_stmt->execute();
                $verify_res = $verify_stmt->get_result();
                
                if ($verify_res->num_rows === 0) {
                    throw new Exception('Announcement not found.');
                }
                $verify_stmt->close();
                
                $insert_stmt = $conn->prepare('INSERT INTO announcement_comment (announcement_id, user_id, message, created_at) VALUES (?, ?, ?, NOW())');
                $insert_stmt->bind_param('iis', $announcement_id, $user_id, $message);
                $insert_stmt->execute();
                $insert_stmt->close();
                
                activity_log($user_id, 'Added comment to announcement', 'announcement', $announcement_id);
                $success_message = 'Comment posted successfully.';
                break;
                
            case 'delete_comment':
                $comment_id = intval($_POST['comment_id'] ?? 0);
                
                $verify_stmt = $conn->prepare('SELECT id, user_id FROM announcement_comment WHERE id = ? AND deleted_at IS NULL');
                $verify_stmt->bind_param('i', $comment_id);
                $verify_stmt->execute();
                $verify_res = $verify_stmt->get_result();
                
                if ($verify_res->num_rows === 0) {
                    throw new Exception('Comment not found.');
                }
                
                $comment_data = $verify_res->fetch_assoc();
                $verify_stmt->close();
                
                // Only comment owner or admin can delete
                if ($comment_data['user_id'] != $user_id && !in_array($user_role, [ROLE_ADMIN, ROLE_SUPERADMIN])) {
                    throw new Exception('You can only delete your own comments.');
                }
                
                $delete_stmt = $conn->prepare('UPDATE announcement_comment SET deleted_at = NOW() WHERE id = ?');
                $delete_stmt->bind_param('i', $comment_id);
                $delete_stmt->execute();
                $delete_stmt->close();
                
                activity_log($user_id, 'Deleted comment from announcement', 'announcement_comment', $comment_id);
                $success_message = 'Comment deleted successfully.';
                break;
                
            default:
                throw new Exception('Invalid action.');
        }
        
        $conn->commit();
        flash_set($success_message, 'success');
        header('Location: index.php?nav=announcements');
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log('Announcement Action Error: ' . $e->getMessage());
        $errors[] = $e->getMessage();
    }
}

// Fetch announcements
$announcements = [];
$show_announcements = true;

// Get filter parameters
$q = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'date_desc'; // date_desc, date_asc, title_asc, comments_desc
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 9; // 3 columns x 3 rows
$offset = ($page - 1) * $per_page;

// Check access permissions
if (!$user_id) {
    $show_announcements = false;
    $errors[] = 'Please log in to view announcements.';
} elseif (!$user_barangay_id && $user_role !== ROLE_SUPERADMIN) {
    $show_announcements = false;
    $errors[] = 'Your account is not associated with a barangay. Please contact support.';
} elseif ($user_barangay_id || $user_role === ROLE_SUPERADMIN) {
    
    // Build WHERE clause
    $where = ' WHERE a.deleted_at IS NULL';
    
    if ($user_role !== ROLE_SUPERADMIN) {
        $where .= ' AND a.barangay_id = ' . intval($user_barangay_id);
    }
    
    // Add search filter
    if ($q !== '') {
        $safe = $conn->real_escape_string($q);
        $safe = str_replace(['%', '_'], ['\\%', '\\_'], $safe);
        $where .= " AND (a.title LIKE '%$safe%' OR a.content LIKE '%$safe%' OR CONCAT_WS(' ', p.first_name, p.last_name) LIKE '%$safe%' OR u.username LIKE '%$safe%')";
    }
    
    // Count total for pagination
    $count_query = "
        SELECT COUNT(*) as total
        FROM announcement a
        LEFT JOIN users u ON a.user_id = u.id
        LEFT JOIN profile p ON u.id = p.user_id
        $where
    ";
    
    $count_res = $conn->query($count_query);
    $total_announcements = $count_res ? $count_res->fetch_assoc()['total'] : 0;
    $total_pages = ceil($total_announcements / $per_page);
    
    // Build main query
    $query = "
        SELECT 
            a.id, a.title, a.content, a.image_path, a.created_at, a.updated_at,
            a.user_id,
            u.username,
            p.first_name, p.last_name,
            (SELECT COUNT(*) FROM announcement_comment WHERE announcement_id = a.id AND deleted_at IS NULL) as comment_count
        FROM announcement a
        LEFT JOIN users u ON a.user_id = u.id
        LEFT JOIN profile p ON u.id = p.user_id
        $where
        ORDER BY ";
    
    // Apply sorting
    switch ($sort) {
        case 'date_asc':
            $query .= "a.created_at ASC";
            break;
        case 'title_asc':
            $query .= "a.title ASC";
            break;
        case 'comments_desc':
            $query .= "comment_count DESC, a.created_at DESC";
            break;
        case 'date_desc':
        default:
            $query .= "a.created_at DESC";
            break;
    }
    
    $query .= " LIMIT $per_page OFFSET $offset";
    
    $res = $conn->query($query);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $announcements[] = $row;
        }
    }
}

$showing_count = count($announcements);

require_once __DIR__ . '/../public/header.php';

// Display flash messages
$flash = flash_get();
?>

<div class="container my-4">
    <!-- Header Section -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold mb-2">
                <i class="fas fa-bullhorn me-2" style="color: #0d6efd;"></i>Announcements
            </h2>
            <?php if ($show_announcements && isset($total_announcements)): ?>
                <small class="text-muted">Showing <?php echo $showing_count; ?> of <?php echo $total_announcements; ?> announcement<?php echo $total_announcements === 1 ? '' : 's'; ?></small>
            <?php endif; ?>
        </div>
        <div class="col-md-6 text-end">
            <?php if ($user_id && in_array($user_role, [ROLE_STAFF, ROLE_ADMIN])): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                    <i class="fas fa-plus me-2"></i>Create Announcement
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Search and Sort Section -->
    <?php if ($show_announcements): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap" style="gap: 15px;">
                <form class="d-flex flex-grow-1" method="get" action="index.php" style="max-width: 400px;">
                    <input type="hidden" name="nav" value="announcements">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                    <input type="text" class="form-control me-2" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search announcements...">
                    <button class="btn btn-outline-secondary" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                    <?php if ($q !== ''): ?>
                        <a href="index.php?nav=announcements&sort=<?php echo htmlspecialchars($sort); ?>" class="btn btn-outline-danger ms-2" title="Clear search">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </form>
                
                <form class="d-flex align-items-center" method="get" action="index.php" style="gap: 8px;">
                    <input type="hidden" name="nav" value="announcements">
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($q); ?>">
                    <label for="sortBy" class="form-label mb-0" style="font-size: 0.9rem; font-weight: 500; white-space: nowrap;">Sort by:</label>
                    <select id="sortBy" name="sort" class="form-select form-select-sm" style="width: 180px;" onchange="this.form.submit()">
                        <option value="date_desc" <?php echo $sort === 'date_desc' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="date_asc" <?php echo $sort === 'date_asc' ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="title_asc" <?php echo $sort === 'title_asc' ? 'selected' : ''; ?>>Title (A-Z)</option>
                        <option value="comments_desc" <?php echo $sort === 'comments_desc' ? 'selected' : ''; ?>>Most Commented</option>
                    </select>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Flash Messages -->
    <?php if ($flash && !empty($flash['message'])): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'error' ? 'danger' : 'info'); ?> alert-dismissible fade show mb-3">
            <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
            <?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Error Messages -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-3">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Error:</strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <?php if (count($errors) === 1): ?>
                <?php echo htmlspecialchars($errors[0]); ?>
            <?php else: ?>
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <?php if (!$show_announcements): ?>
            <div class="col-12">
                <div class="alert alert-warning text-center">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php if (!$user_id): ?>
                        Please <a href="?nav=login" class="alert-link">log in</a> to view announcements.
                    <?php else: ?>
                        Your account is not associated with a barangay. Please contact support.
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif (count($announcements) > 0): ?>
            <?php foreach ($announcements as $ann): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm rounded-3 announcement-card">
                        <?php if (!empty($ann['image_path'])): ?>
                            <img src="<?php echo htmlspecialchars($ann['image_path']); ?>" class="card-img-top rounded-top-3" alt="Announcement Image" style="height:200px; object-fit:cover;" onerror="this.style.display='none'">
                        <?php endif; ?>
                        <div class="card-body">
                            <h5 class="card-title fw-bold"><?php echo htmlspecialchars($ann['title']); ?></h5>
                            <p class="card-text text-muted" style="font-size: 0.95rem;">
                                <?php 
                                $content = htmlspecialchars($ann['content']);
                                echo strlen($content) > 150 ? substr($content, 0, 150) . '...' : $content;
                                ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <small class="text-muted">
                                    <i class="far fa-user me-1"></i>
                                    <?php echo htmlspecialchars(trim($ann['first_name'] . ' ' . $ann['last_name']) ?: $ann['username']); ?>
                                </small>
                                <small class="text-muted">
                                    <i class="far fa-calendar me-1"></i>
                                    <?php echo date('M j, Y', strtotime($ann['created_at'])); ?>
                                </small>
                            </div>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="far fa-comments me-1"></i>
                                    <?php echo $ann['comment_count']; ?> <?php echo $ann['comment_count'] == 1 ? 'comment' : 'comments'; ?>
                                </small>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-top">
                            <div class="d-flex gap-2">
                                <a href="?nav=announcements&view=<?php echo $ann['id']; ?>" class="btn btn-sm btn-outline-primary flex-grow-1">
                                    <i class="fas fa-eye me-1"></i>View Details
                                </a>
                                <?php if ($user_id && (($user_role === ROLE_ADMIN) || ($ann['user_id'] == $user_id && in_array($user_role, [ROLE_STAFF, ROLE_ADMIN])))): ?>
                                    <button class="btn btn-sm btn-outline-warning" 
                                            data-id="<?php echo $ann['id']; ?>" 
                                            data-title="<?php echo htmlspecialchars($ann['title']); ?>" 
                                            data-content="<?php echo htmlspecialchars($ann['content']); ?>"
                                            onclick="editAnnouncement(this.dataset.id, this.dataset.title, this.dataset.content)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                                        <input type="hidden" name="action" value="delete_announcement">
                                        <input type="hidden" name="announcement_id" value="<?php echo $ann['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="text-center py-5">
                    <div class="mb-4">
                        <i class="fas fa-<?php echo $q !== '' ? 'search' : 'bullhorn'; ?> fa-4x text-muted opacity-25"></i>
                    </div>
                    <?php if ($q !== ''): ?>
                        <h5 class="text-muted mb-2">No Results Found</h5>
                        <p class="text-muted mb-3">No announcements match your search for "<?php echo htmlspecialchars($q); ?>"</p>
                        <a href="index.php?nav=announcements" class="btn btn-outline-primary">
                            <i class="fas fa-times me-2"></i>Clear Search
                        </a>
                    <?php else: ?>
                        <h5 class="text-muted mb-2">No Announcements Yet</h5>
                        <p class="text-muted mb-3">There are no announcements available at this time.</p>
                        <?php if ($user_id && in_array($user_role, [ROLE_STAFF, ROLE_ADMIN])): ?>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                                <i class="fas fa-plus me-2"></i>Create First Announcement
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Pagination -->
    <?php if ($show_announcements && isset($total_pages) && $total_pages > 1): ?>
        <nav aria-label="Announcements pagination" class="mt-4">
            <ul class="pagination justify-content-center">
                <?php 
                $base_url = 'index.php?nav=announcements&q=' . urlencode($q) . '&sort=' . htmlspecialchars($sort);
                
                // Previous button
                if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo $base_url; ?>&page=<?php echo $page - 1; ?>">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    </li>
                <?php else: ?>
                    <li class="page-item disabled">
                        <span class="page-link"><i class="fas fa-chevron-left"></i> Previous</span>
                    </li>
                <?php endif; ?>
                
                <?php
                // Page numbers logic
                $show_pages = 5; // Number of page links to show
                $start_page = max(1, $page - floor($show_pages / 2));
                $end_page = min($total_pages, $start_page + $show_pages - 1);
                
                // Adjust start if we're near the end
                if ($end_page - $start_page < $show_pages - 1) {
                    $start_page = max(1, $end_page - $show_pages + 1);
                }
                
                // First page
                if ($start_page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo $base_url; ?>&page=1">1</a>
                    </li>
                    <?php if ($start_page > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="<?php echo $base_url; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                
                <?php 
                // Last page
                if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo $base_url; ?>&page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a>
                    </li>
                <?php endif; ?>
                
                <!-- Next button -->
                <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo $base_url; ?>&page=<?php echo $page + 1; ?>">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                <?php else: ?>
                    <li class="page-item disabled">
                        <span class="page-link">Next <i class="fas fa-chevron-right"></i></span>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<!-- Create Announcement Modal -->
<?php if ($user_id && in_array($user_role, [ROLE_STAFF, ROLE_ADMIN])): ?>
<div class="modal fade" id="createAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-bullhorn me-2" style="color: #0d6efd;"></i>Create Announcement
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="createAnnouncementForm" onsubmit="return validateForm('create')">
                <input type="hidden" name="action" value="create_announcement">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-600">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" id="createTitle" required minlength="5" maxlength="255">
                        <small class="text-muted"><span id="createTitleCount">0</span>/255 characters</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Content <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="content" id="createContent" rows="5" required minlength="10"></textarea>
                        <small class="text-muted">Minimum 10 characters</small>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-600">Image (optional)</label>
                        <input type="file" class="form-control" name="image" id="createImageInput" accept="image/jpeg,image/png,image/gif" onchange="previewImage(this, 'createImagePreview')">
                        <small class="text-muted">JPG, PNG, or GIF. Max 5MB.</small>
                        <div id="createImagePreview" class="mt-2" style="display:none;">
                            <img src="" class="img-fluid rounded" style="max-height: 200px;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-600" id="createSubmitBtn">
                        <i class="fas fa-plus me-2"></i>Create
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Announcement Modal -->
<div class="modal fade" id="editAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-edit me-2" style="color: #ffc107;"></i>Edit Announcement
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="editAnnouncementForm" onsubmit="return validateForm('edit')">
                <input type="hidden" name="action" value="edit_announcement">
                <input type="hidden" name="announcement_id" id="edit_announcement_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-600">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" id="edit_title" required minlength="5" maxlength="255">
                        <small class="text-muted"><span id="editTitleCount">0</span>/255 characters</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Content <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="content" id="edit_content" rows="5" required minlength="10"></textarea>
                        <small class="text-muted">Minimum 10 characters</small>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-600">Replace Image (optional)</label>
                        <input type="file" class="form-control" name="image" id="editImageInput" accept="image/jpeg,image/png,image/gif" onchange="previewImage(this, 'editImagePreview')">
                        <small class="text-muted">Leave empty to keep current image.</small>
                        <div id="editImagePreview" class="mt-2" style="display:none;">
                            <img src="" class="img-fluid rounded" style="max-height: 200px;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark px-4 fw-600" id="editSubmitBtn">
                        <i class="fas fa-save me-2"></i>Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.announcement-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.announcement-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

.announcement-card .card-footer {
    transition: background-color 0.2s ease-in-out;
}

.announcement-card:hover .card-footer {
    background-color: #f8f9fa !important;
}

.announcement-card .btn {
    transition: all 0.2s ease-in-out;
}

.fw-600 {
    font-weight: 600;
}

.card-img-top {
    transition: transform 0.3s ease-in-out;
}

.announcement-card:hover .card-img-top {
    transform: scale(1.05);
}

.announcement-card {
    overflow: hidden;
}
</style>
<?php endif; ?>

<script>
<?php if ($user_id && in_array($user_role, [ROLE_STAFF, ROLE_ADMIN])): ?>
function editAnnouncement(id, title, content) {
    document.getElementById('edit_announcement_id').value = id;
    document.getElementById('edit_title').value = title;
    document.getElementById('edit_content').value = content;
    
    // Update character counter
    document.getElementById('editTitleCount').textContent = title.length;
    
    // Clear image preview
    document.getElementById('editImagePreview').style.display = 'none';
    document.getElementById('editImageInput').value = '';
    
    const modal = new bootstrap.Modal(document.getElementById('editAnnouncementModal'));
    modal.show();
}

function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    const previewImg = preview.querySelector('img');
    
    if (input.files && input.files[0]) {
        // Validate file size (5MB)
        if (input.files[0].size > 5242880) {
            alert('Image size must not exceed 5MB.');
            input.value = '';
            preview.style.display = 'none';
            return;
        }
        
        // Validate file type
        const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!validTypes.includes(input.files[0].type)) {
            alert('Please select a valid image file (JPG, PNG, or GIF).');
            input.value = '';
            preview.style.display = 'none';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.style.display = 'none';
    }
}

function validateForm(formType) {
    const submitBtn = document.getElementById(formType + 'SubmitBtn');
    const form = document.getElementById(formType + 'AnnouncementForm');
    
    // Show loading state
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
    
    // Allow form submission
    return true;
}
<?php endif; ?>

// Auto-dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert:not(.alert-danger)');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
    
    <?php if ($user_id && in_array($user_role, [ROLE_STAFF, ROLE_ADMIN])): ?>
    // Character counter for create form
    const createTitle = document.getElementById('createTitle');
    const createTitleCount = document.getElementById('createTitleCount');
    
    if (createTitle && createTitleCount) {
        createTitle.addEventListener('input', function() {
            createTitleCount.textContent = this.value.length;
            if (this.value.length > 255) {
                createTitleCount.classList.add('text-danger');
            } else {
                createTitleCount.classList.remove('text-danger');
            }
        });
    }
    
    // Character counter for edit form
    const editTitle = document.getElementById('edit_title');
    const editTitleCount = document.getElementById('editTitleCount');
    
    if (editTitle && editTitleCount) {
        editTitle.addEventListener('input', function() {
            editTitleCount.textContent = this.value.length;
            if (this.value.length > 255) {
                editTitleCount.classList.add('text-danger');
            } else {
                editTitleCount.classList.remove('text-danger');
            }
        });
    }
    
    // Reset modals on close
    const createModal = document.getElementById('createAnnouncementModal');
    if (createModal) {
        createModal.addEventListener('hidden.bs.modal', function() {
            document.getElementById('createAnnouncementForm').reset();
            document.getElementById('createImagePreview').style.display = 'none';
            document.getElementById('createTitleCount').textContent = '0';
            document.getElementById('createSubmitBtn').disabled = false;
            document.getElementById('createSubmitBtn').innerHTML = '<i class="fas fa-plus me-2"></i>Create';
        });
    }
    
    const editModal = document.getElementById('editAnnouncementModal');
    if (editModal) {
        editModal.addEventListener('hidden.bs.modal', function() {
            document.getElementById('editImagePreview').style.display = 'none';
            document.getElementById('editSubmitBtn').disabled = false;
            document.getElementById('editSubmitBtn').innerHTML = '<i class="fas fa-save me-2"></i>Update';
        });
    }
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
