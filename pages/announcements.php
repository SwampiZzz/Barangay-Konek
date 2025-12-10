<?php
require_once __DIR__ . '/../config.php';
require_login();

$pageTitle = 'Announcements';
$user_id = current_user_id();
$role = current_user_role();

// Get user's barangay
$profile = get_user_profile($user_id);
$barangay_id = $profile['barangay_id'] ?? null;

// Get announcements for user's barangay
$announcements = [];
if ($barangay_id) {
    $res = db_query('SELECT a.*, u.username FROM announcement a LEFT JOIN users u ON a.user_id = u.id WHERE a.barangay_id = ? AND a.deleted_at IS NULL ORDER BY a.created_at DESC', 'i', [$barangay_id]);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $announcements[] = $row;
        }
    }
}

require_once __DIR__ . '/../public/header.php';
?>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-bullhorn"></i> Announcements</h2>
        <?php if (in_array($role, [ROLE_STAFF, ROLE_ADMIN, ROLE_SUPERADMIN])): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                <i class="fas fa-plus me-2"></i>Create Announcement
            </button>
        <?php endif; ?>
    </div>

    <div class="row">
        <?php if (count($announcements) > 0): ?>
            <?php foreach ($announcements as $ann): ?>
                <div class="col-md-6 mb-3">
                    <div class="card h-100">
                        <?php if (!empty($ann['image_path'])): ?>
                            <img src="<?php echo e($ann['image_path']); ?>" class="card-img-top" alt="Announcement Image" style="height:200px; object-fit:cover;">
                        <?php endif; ?>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo e($ann['title']); ?></h5>
                            <p class="card-text"><?php echo nl2br(e($ann['content'])); ?></p>
                            <p class="text-muted small">Posted by <?php echo e($ann['username'] ?? 'Admin'); ?> on <?php echo date('M d, Y', strtotime($ann['created_at'])); ?></p>
                        </div>
                        <?php if (in_array($role, [ROLE_ADMIN, ROLE_SUPERADMIN]) || ($role === ROLE_STAFF && $ann['user_id'] == $user_id)): ?>
                            <div class="card-footer">
                                <a href="#" class="btn btn-sm btn-warning">Edit</a>
                                <a href="#" class="btn btn-sm btn-danger">Delete</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <p class="text-muted text-center py-4">No announcements available.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Announcement Modal -->
<?php if (in_array($role, [ROLE_STAFF, ROLE_ADMIN, ROLE_SUPERADMIN])): ?>
<div class="modal fade" id="createAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="#" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Content</label>
                        <textarea class="form-control" name="content" rows="5" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Image (optional)</label>
                        <input type="file" class="form-control" name="image" accept="image/*">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Announcement</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
