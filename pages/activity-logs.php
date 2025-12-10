<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware/auth.php';

require_login();
require_role([ROLE_SUPERADMIN]);

$pageTitle = 'Activity Logs';

// Get activity logs
$res = db_query('SELECT al.*, u.username FROM activity_log al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 100', '', []);
$logs = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $logs[] = $row;
    }
}

require_once __DIR__ . '/../public/header.php';
?>

<div class="container my-5">
    <h2 class="mb-4"><i class="fas fa-history"></i> Activity Logs</h2>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date/Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Reference</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></td>
                            <td><?php echo e($log['username'] ?? 'System'); ?></td>
                            <td><?php echo e($log['action']); ?></td>
                            <td>
                                <?php if ($log['reference_table']): ?>
                                    <?php echo e($log['reference_table']); ?> #<?php echo intval($log['reference_id']); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
