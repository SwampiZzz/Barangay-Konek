<?php
require_once __DIR__ . '/../config.php';
require_login();
require_role([ROLE_SUPERADMIN]);

$pageTitle = 'Barangay Overview';

// Get all barangays with stats
$barangays = [];
$res = db_query('SELECT b.*, c.name as city_name, COUNT(DISTINCT u.id) as user_count FROM barangay b LEFT JOIN city c ON b.city_id = c.id LEFT JOIN profile p ON b.id = p.barangay_id LEFT JOIN users u ON p.user_id = u.id WHERE b.deleted_at IS NULL GROUP BY b.id ORDER BY b.name ASC');
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $barangays[] = $row;
    }
}

require_once __DIR__ . '/../public/header.php';
?>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-map-marked-alt"></i> Barangay Overview</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBarangayModal">
            <i class="fas fa-plus me-2"></i>Add Barangay
        </button>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (count($barangays) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Barangay Name</th>
                                <th>City</th>
                                <th>Residents</th>
                                <th>Contact</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($barangays as $brgy): ?>
                                <tr>
                                    <td><?php echo e($brgy['id']); ?></td>
                                    <td><?php echo e($brgy['name']); ?></td>
                                    <td><?php echo e($brgy['city_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo intval($brgy['user_count']); ?></td>
                                    <td><?php echo e($brgy['contact_number'] ?? 'N/A'); ?></td>
                                    <td>
                                        <a href="#" class="btn btn-sm btn-info">View</a>
                                        <a href="#" class="btn btn-sm btn-warning">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted text-center py-4">No barangays registered yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Create Barangay Modal -->
<div class="modal fade" id="createBarangayModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Barangay</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="#">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Barangay Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Number</label>
                        <input type="text" class="form-control" name="contact_number">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Barangay</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
