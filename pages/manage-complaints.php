<?php
require_once __DIR__ . '/../config.php';
require_login();
require_role([ROLE_STAFF, ROLE_ADMIN]);

$pageTitle = 'Manage Complaints';
$user_id = current_user_id();

// Get all complaints for the barangay
$complaints = [];
$res = db_query('SELECT c.*, cs.name as status_name, u.username FROM complaint c LEFT JOIN complaint_status cs ON c.complaint_status_id = cs.id LEFT JOIN users u ON c.user_id = u.id WHERE c.deleted_at IS NULL ORDER BY c.created_at DESC');
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $complaints[] = $row;
    }
}

require_once __DIR__ . '/../public/header.php';
?>

<div class="container my-5">
    <h2 class="mb-4"><i class="fas fa-tasks"></i> Manage Complaints</h2>

    <div class="card">
        <div class="card-body">
            <?php if (count($complaints) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($complaints as $complaint): ?>
                                <tr>
                                    <td><?php echo e($complaint['id']); ?></td>
                                    <td><?php echo e($complaint['username']); ?></td>
                                    <td><?php echo e($complaint['title'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            $sid = intval($complaint['complaint_status_id']); 
                                            echo $sid === 1 ? 'warning' : ($sid === 2 ? 'info' : ($sid === 3 ? 'success' : 'secondary')); 
                                        ?>">
                                            <?php echo e($complaint['status_name']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($complaint['created_at'])); ?></td>
                                    <td>
                                        <a href="#" class="btn btn-sm btn-info">View</a>
                                        <a href="#" class="btn btn-sm btn-warning">Update Status</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted text-center py-4">No complaints submitted yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
