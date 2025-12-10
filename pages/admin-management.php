<?php
require_once __DIR__ . '/../config.php';
require_login();
require_role([ROLE_SUPERADMIN]);

$pageTitle = 'Admin Management';

// Get all admin users
$admins = [];
$res = db_query('SELECT u.*, ut.name as role_name, p.first_name, p.last_name FROM users u LEFT JOIN usertype ut ON u.usertype_id = ut.id LEFT JOIN profile p ON u.id = p.user_id WHERE u.usertype_id IN (2) AND u.deleted_at IS NULL ORDER BY u.created_at DESC');
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $admins[] = $row;
    }
}

require_once __DIR__ . '/../public/header.php';
?>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-users-cog"></i> Admin Management</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAdminModal">
            <i class="fas fa-user-plus me-2"></i>Create Admin Account
        </button>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (count($admins) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admins as $admin): ?>
                                <tr>
                                    <td><?php echo e($admin['id']); ?></td>
                                    <td><?php echo e($admin['username']); ?></td>
                                    <td><?php echo e($admin['first_name'] . ' ' . $admin['last_name']); ?></td>
                                    <td><?php echo e($admin['role_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($admin['created_at'])); ?></td>
                                    <td>
                                        <a href="#" class="btn btn-sm btn-warning">Edit</a>
                                        <a href="#" class="btn btn-sm btn-danger">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted text-center py-4">No admin accounts found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Create Admin Modal -->
<div class="modal fade" id="createAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Admin Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="#">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">First Name</label>
                        <input type="text" class="form-control" name="first_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-control" name="last_name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
