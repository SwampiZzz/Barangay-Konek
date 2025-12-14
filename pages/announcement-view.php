<?php
require_once __DIR__ . '/../config.php';

$page_title = 'Announcement';
$user_id = !empty($_SESSION['user_id']) ? current_user_id() : null;
$user_role = $user_id ? current_user_role() : null;
$user_barangay_id = $user_id ? current_user_barangay_id() : null;

$errors = [];
$announcement_id = intval($_GET['view'] ?? 0);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $user_id) {
    $action = $_POST['action'];
    
    try {
        $conn->begin_transaction();
        
        switch ($action) {
            case 'add_comment':
                $comment_announcement_id = intval($_POST['announcement_id'] ?? 0);
                $message = trim($_POST['message'] ?? '');
                
                if (empty($message)) {
                    throw new Exception('Comment cannot be empty.');
                }
                
                if (strlen($message) > 1000) {
                    throw new Exception('Comment is too long (max 1000 characters).');
                }
                
                // Verify announcement exists and is accessible
                $verify_stmt = $conn->prepare('SELECT id FROM announcement WHERE id = ? AND barangay_id = ? AND deleted_at IS NULL');
                $verify_stmt->bind_param('ii', $comment_announcement_id, $user_barangay_id);
                $verify_stmt->execute();
                $verify_res = $verify_stmt->get_result();
                
                if ($verify_res->num_rows === 0) {
                    throw new Exception('Announcement not found.');
                }
                $verify_stmt->close();
                
                $insert_stmt = $conn->prepare('INSERT INTO announcement_comment (announcement_id, user_id, message, created_at) VALUES (?, ?, ?, NOW())');
                $insert_stmt->bind_param('iis', $comment_announcement_id, $user_id, $message);
                $insert_stmt->execute();
                $insert_stmt->close();
                
                activity_log($user_id, 'Added comment to announcement', 'announcement', $comment_announcement_id);
                $conn->commit();
                flash_set('Comment posted successfully.', 'success');
                header('Location: index.php?nav=announcements&view=' . $comment_announcement_id);
                exit;
                break;
                
            case 'delete_comment':
                $comment_id = intval($_POST['comment_id'] ?? 0);
                
                $verify_stmt = $conn->prepare('SELECT id, user_id, announcement_id FROM announcement_comment WHERE id = ? AND deleted_at IS NULL');
                $verify_stmt->bind_param('i', $comment_id);
                $verify_stmt->execute();
                $verify_res = $verify_stmt->get_result();
                
                if ($verify_res->num_rows === 0) {
                    throw new Exception('Comment not found.');
                }
                
                $comment_data = $verify_res->fetch_assoc();
                $comment_ann_id = $comment_data['announcement_id'];
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
                $conn->commit();
                flash_set('Comment deleted successfully.', 'success');
                header('Location: index.php?nav=announcements&view=' . $comment_ann_id);
                exit;
                break;
                
            default:
                throw new Exception('Invalid action.');
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log('Announcement Comment Action Error: ' . $e->getMessage());
        $errors[] = $e->getMessage();
    }
}

// Fetch announcement details
$announcement = null;
if ($announcement_id) {
    $query = '
        SELECT 
            a.id, a.title, a.content, a.image_path, a.created_at, a.updated_at,
            a.user_id, a.barangay_id,
            u.username,
            p.first_name, p.last_name, p.email
        FROM announcement a
        LEFT JOIN users u ON a.user_id = u.id
        LEFT JOIN profile p ON u.id = p.user_id
        WHERE a.id = ? AND a.deleted_at IS NULL
    ';
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $announcement_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        $announcement = $res->fetch_assoc();
        
        // Check barangay access
        if ($user_role !== ROLE_SUPERADMIN && $announcement['barangay_id'] != $user_barangay_id) {
            $announcement = null;
        }
    }
    $stmt->close();
}

if (!$announcement) {
    header('Location: index.php?nav=announcements');
    exit;
}

// Fetch comments
$comments = [];
$comment_query = '
    SELECT 
        c.id, c.message, c.created_at, c.deleted_at,
        c.user_id,
        u.username,
        p.first_name, p.last_name, p.email
    FROM announcement_comment c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN profile p ON u.id = p.user_id
    WHERE c.announcement_id = ?
    ORDER BY c.created_at ASC
';

$comment_stmt = $conn->prepare($comment_query);
$comment_stmt->bind_param('i', $announcement_id);
$comment_stmt->execute();
$comment_res = $comment_stmt->get_result();

while ($row = $comment_res->fetch_assoc()) {
    $comments[] = $row;
}
$comment_stmt->close();

require_once __DIR__ . '/../public/header.php';

// Display flash messages
$flash = flash_get();
?>

<div class="container my-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-11 col-lg-9 col-xl-8">
            <!-- Back Button -->
            <div class="mb-3">
                <a href="index.php?nav=announcements" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Announcements
                </a>
            </div>

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
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <?php if (count($errors) === 1): ?>
                        <?php echo htmlspecialchars($errors[0]); ?>
                    <?php else: ?>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Announcement Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <?php if (!empty($announcement['image_path'])): ?>
                    <img src="<?php echo htmlspecialchars($announcement['image_path']); ?>" class="card-img-top rounded-top-3" alt="Announcement Image" style="max-height:400px; object-fit:cover;" onerror="this.style.display='none'">
                <?php endif; ?>
                
                <div class="card-body p-4">
                    <!-- Title -->
                    <div class="mb-3">
                        <h2 class="fw-bold mb-0" style="color: #1a1a1a; font-size: 1.85rem;"><?php echo htmlspecialchars($announcement['title']); ?></h2>
                    </div>

                    <!-- Author Card -->
                    <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
                        <?php 
                        $author_first = strtoupper(substr($announcement['first_name'] ?? 'U', 0, 1));
                        $author_last = strtoupper(substr($announcement['last_name'] ?? 'N', 0, 1));
                        $author_color_index = intval($announcement['user_id'] ?? 0) % 6;
                        $author_colors = ['#0d6efd', '#198754', '#17a2b8', '#ffc107', '#dc3545', '#6c757d'];
                        $author_color = $author_colors[$author_color_index];
                        
                        // Check for profile picture
                        $author_pic_path = __DIR__ . '/../storage/app/private/profile_pics/user_' . $announcement['user_id'] . '.jpg';
                        $author_has_pic = file_exists($author_pic_path);
                        $author_pic_url = (defined('WEB_ROOT') ? WEB_ROOT : '') . '/storage/app/private/profile_pics/user_' . $announcement['user_id'] . '.jpg';
                        ?>
                        <div class="flex-shrink-0">
                            <?php if ($author_has_pic): ?>
                                <img src="<?php echo htmlspecialchars($author_pic_url); ?>" 
                                     class="rounded-circle shadow-sm" 
                                     style="width: 48px; height: 48px; object-fit: cover;" 
                                     alt="Profile"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="rounded-circle text-white d-none align-items-center justify-content-center fw-bold shadow-sm" 
                                     style="width: 48px; height: 48px; font-size: 18px; background: linear-gradient(135deg, <?php echo $author_color; ?>, <?php echo $author_color; ?>dd);">
                                    <?php echo $author_first . $author_last; ?>
                                </div>
                            <?php else: ?>
                                <div class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold shadow-sm" 
                                     style="width: 48px; height: 48px; font-size: 18px; background: linear-gradient(135deg, <?php echo $author_color; ?>, <?php echo $author_color; ?>dd);">
                                    <?php echo $author_first . $author_last; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-semibold" style="color: #2c3e50; font-size: 1.05rem;">
                                <?php echo htmlspecialchars(trim($announcement['first_name'] . ' ' . $announcement['last_name']) ?: $announcement['username']); ?>
                            </div>
                            <div class="text-muted" style="font-size: 0.9rem;">
                                <i class="far fa-clock me-1"></i>
                                <?php echo date('F j, Y \a\t g:i A', strtotime($announcement['created_at'])); ?>
                                <?php if ($announcement['updated_at'] && $announcement['updated_at'] != $announcement['created_at']): ?>
                                    <span class="ms-2">· <i class="fas fa-edit me-1"></i>Edited</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($user_id && (($user_role === ROLE_ADMIN) || ($announcement['user_id'] == $user_id && in_array($user_role, [ROLE_STAFF, ROLE_ADMIN])))): ?>
                            <div class="d-flex gap-2 flex-shrink-0">
                                <button class="btn btn-sm btn-warning" 
                                        data-id="<?php echo $announcement['id']; ?>"
                                        data-title="<?php echo htmlspecialchars($announcement['title']); ?>"
                                        data-content="<?php echo htmlspecialchars($announcement['content']); ?>"
                                        onclick="editAnnouncement(this.dataset.id, this.dataset.title, this.dataset.content)" 
                                        title="Edit announcement">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" action="index.php?nav=announcements" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                                    <input type="hidden" name="action" value="delete_announcement">
                                    <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete announcement">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Content -->
                    <div class="announcement-content" style="font-size: 1.02rem; line-height: 1.7; color: #2c3e50;">
                        <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                    </div>
                </div>
            </div>

            <!-- Comments Section -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-bold" style="color: #2c3e50;">
                        <i class="far fa-comments me-2" style="color: #0d6efd;"></i>
                        Comments (<?php echo count($comments); ?>)
                    </h5>
                </div>
                
                <div class="card-body p-3 p-md-4">
                    <?php if ($user_id): ?>
                        <!-- Add Comment Form -->
                        <div class="mb-4 pb-4 border-bottom">
                            <form method="POST" action="index.php?nav=announcements&view=<?php echo $announcement_id; ?>" id="commentForm">
                                <input type="hidden" name="action" value="add_comment">
                                <input type="hidden" name="announcement_id" value="<?php echo $announcement_id; ?>">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold" style="color: #2c3e50;">Add a comment</label>
                                    <textarea class="form-control shadow-sm" name="message" rows="3" placeholder="Share your thoughts..." required maxlength="1000" id="commentInput" style="border: 1px solid #dee2e6; resize: none;"></textarea>
                                    <div class="d-flex justify-content-between mt-2">
                                        <small class="text-muted">
                                            <span id="charCount">0</span>/1000 characters
                                        </small>
                                        <small class="text-muted" id="charWarning" style="display:none; color: #dc3545 !important;">
                                            <i class="fas fa-exclamation-circle me-1"></i>Character limit reached
                                        </small>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary shadow-sm" id="commentSubmitBtn">
                                    <i class="fas fa-paper-plane me-2"></i>Post Comment
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-4">
                            <i class="fas fa-info-circle me-2"></i>Please <a href="index.php?nav=login" class="alert-link">log in</a> to post a comment.
                        </div>
                    <?php endif; ?>

                    <!-- Comment List -->
                    <?php if (count($comments) > 0): ?>
                        <div class="comment-list">
                            <?php foreach ($comments as $index => $comment): ?>
                                <div class="comment-item d-flex gap-3 mb-3 <?php echo $index < count($comments) - 1 ? 'pb-3 border-bottom' : ''; ?>">
                                    <div class="flex-shrink-0">
                                        <?php 
                                        $comment_user_id = intval($comment['user_id'] ?? 0);
                                        $first_initial = strtoupper(substr($comment['first_name'] ?? 'U', 0, 1));
                                        $last_initial = strtoupper(substr($comment['last_name'] ?? 'N', 0, 1));
                                        $color_index = $comment_user_id % 6;
                                        $gradient_colors = [
                                            ['#0d6efd', '#0a58ca'],
                                            ['#198754', '#146c43'],
                                            ['#17a2b8', '#138496'],
                                            ['#ffc107', '#ffca2c'],
                                            ['#dc3545', '#b02a37'],
                                            ['#6c757d', '#545b62']
                                        ];
                                        $color_pair = $gradient_colors[$color_index];
                                        
                                        // Check for profile picture
                                        $comment_pic_path = __DIR__ . '/../storage/app/private/profile_pics/user_' . $comment_user_id . '.jpg';
                                        $comment_has_pic = file_exists($comment_pic_path);
                                        $comment_pic_url = (defined('WEB_ROOT') ? WEB_ROOT : '') . '/storage/app/private/profile_pics/user_' . $comment_user_id . '.jpg';
                                        ?>
                                        <?php if ($comment_has_pic): ?>
                                            <img src="<?php echo htmlspecialchars($comment_pic_url); ?>" 
                                                 class="rounded-circle shadow-sm" 
                                                 style="width: 40px; height: 40px; object-fit: cover;" 
                                                 alt="Profile"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="rounded-circle text-white d-none align-items-center justify-content-center fw-bold shadow-sm" 
                                                 style="width: 40px; height: 40px; font-size: 15px; background: linear-gradient(135deg, <?php echo $color_pair[0]; ?>, <?php echo $color_pair[1]; ?>);">
                                                <?php echo $first_initial . $last_initial; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold shadow-sm" 
                                                 style="width: 40px; height: 40px; font-size: 15px; background: linear-gradient(135deg, <?php echo $color_pair[0]; ?>, <?php echo $color_pair[1]; ?>);">
                                                <?php echo $first_initial . $last_initial; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <strong class="d-block" style="color: #2c3e50; font-size: 0.95rem;">
                                                    <?php echo htmlspecialchars(trim($comment['first_name'] . ' ' . $comment['last_name']) ?: $comment['username']); ?>
                                                </strong>
                                                <small class="text-muted" style="font-size: 0.85rem;">
                                                    <i class="far fa-clock me-1"></i>
                                                    <?php echo date('M j, Y \a\t g:i A', strtotime($comment['created_at'])); ?>
                                                </small>
                                            </div>
                                            <?php if ($user_id && (($comment['user_id'] == $user_id) || in_array($user_role, [ROLE_ADMIN, ROLE_SUPERADMIN]))): ?>
                                                <?php if (!$comment['deleted_at']): ?>
                                                    <form method="POST" action="index.php?nav=announcements&view=<?php echo $announcement_id; ?>" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this comment?');">
                                                        <input type="hidden" name="action" value="delete_comment">
                                                        <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete comment" style="padding: 0.25rem 0.5rem;">
                                                            <i class="fas fa-trash" style="font-size: 0.85rem;"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="comment-message" style="line-height: 1.6;">
                                            <?php if ($comment['deleted_at']): ?>
                                                <em class="text-muted"><i class="fas fa-ban me-1"></i>This comment has been removed.</em>
                                            <?php else: ?>
                                                <?php echo nl2br(htmlspecialchars($comment['message'])); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="far fa-comments fa-3x text-muted mb-3 opacity-25"></i>
                            <p class="text-muted mb-0">No comments yet. Be the first to comment!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Announcement Modal -->
<?php if ($user_id && (($user_role === ROLE_ADMIN) || ($announcement['user_id'] == $user_id && in_array($user_role, [ROLE_STAFF, ROLE_ADMIN])))): ?>
<div class="modal fade" id="editAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-edit me-2" style="color: #ffc107;"></i>Edit Announcement
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?nav=announcements" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_announcement">
                <input type="hidden" name="announcement_id" id="edit_announcement_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-600">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" id="edit_title" required minlength="5" maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Content <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="content" id="edit_content" rows="5" required minlength="10"></textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-600">Replace Image (optional)</label>
                        <input type="file" class="form-control" name="image" accept="image/jpeg,image/png,image/gif">
                        <small class="text-muted">Leave empty to keep current image.</small>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark px-4 fw-600">
                        <i class="fas fa-save me-2"></i>Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editAnnouncement(id, title, content) {
    document.getElementById('edit_announcement_id').value = id;
    document.getElementById('edit_title').value = title;
    document.getElementById('edit_content').value = content;
    
    const modal = new bootstrap.Modal(document.getElementById('editAnnouncementModal'));
    modal.show();
}

// Character counter for comment with warning
const commentInput = document.getElementById('commentInput');
const charCount = document.getElementById('charCount');
const charWarning = document.getElementById('charWarning');
const commentSubmitBtn = document.getElementById('commentSubmitBtn');

if (commentInput && charCount) {
    commentInput.addEventListener('input', function() {
        const length = this.value.length;
        charCount.textContent = length;
        
        // Show warning when approaching limit
        if (length >= 900) {
            charCount.style.color = '#dc3545';
            charCount.style.fontWeight = 'bold';
            if (charWarning) charWarning.style.display = 'block';
        } else {
            charCount.style.color = '';
            charCount.style.fontWeight = '';
            if (charWarning) charWarning.style.display = 'none';
        }
    });
}

// Auto-dismiss flash messages
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert:not(.alert-danger)');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
    
    // Smooth scroll to comments if coming from comment post
    if (window.location.hash === '#comments') {
        const commentsSection = document.querySelector('.comment-list');
        if (commentsSection) {
            commentsSection.scrollIntoView({ behavior: 'smooth' });
        }
    }
});
</script>

<style>
.announcement-content {
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.comment-item {
    transition: background-color 0.2s ease;
}

.comment-item:hover {
    background-color: #f8f9fa;
    border-radius: 10px;
    margin-left: -12px;
    margin-right: -12px;
    padding-left: 12px !important;
    padding-right: 12px !important;
    padding-top: 4px !important;
    padding-bottom: 4px !important;
}

.fw-600 {
    font-weight: 600;
}

.comment-message {
    color: #2c3e50;
}
</style>
<?php endif; ?>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
