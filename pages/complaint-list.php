<?php
require_once __DIR__ . '/../config.php';
require_login();
require_role([ROLE_USER]);

$pageTitle = 'My Complaints';
$user_id = current_user_id();

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Count total
$count_res = db_query('SELECT COUNT(*) as total FROM complaint WHERE user_id = ? AND deleted_at IS NULL', 'i', [$user_id]);
$total_complaints = $count_res ? $count_res->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_complaints / $per_page);

// Get user's complaints
$complaints = [];
$res = db_query('SELECT c.*, cs.name as status_name FROM complaint c LEFT JOIN complaint_status cs ON c.complaint_status_id = cs.id WHERE c.user_id = ? AND c.deleted_at IS NULL ORDER BY c.created_at DESC LIMIT ' . $per_page . ' OFFSET ' . $offset, 'i', [$user_id]);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $complaints[] = $row;
    }
}

require_once __DIR__ . '/../public/header.php';
?>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-exclamation-circle"></i> My Complaints</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createComplaintModal">
            <i class="fas fa-plus me-2"></i>Submit Complaint
        </button>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (count($complaints) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
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
                                        <a href="index.php?nav=complaint-detail&id=<?php echo $complaint['id']; ?>" class="btn btn-sm btn-info">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="mt-3">
                            <nav aria-label="Complaints pagination">
                                <ul class="pagination pagination-sm justify-content-center">
                                    <?php 
                                    $base_url = WEB_ROOT . '/index.php?nav=complaint-list';
                                    
                                    if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo $base_url; ?>&page=<?php echo $page - 1; ?>">Previous</a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">Previous</span>
                                        </li>
                                    <?php endif;
                                    
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    if ($start_page > 1): ?>
                                        <li class="page-item"><a class="page-link" href="<?php echo $base_url; ?>&page=1">1</a></li>
                                        <?php if ($start_page > 2): ?>
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php endif;
                                    endif;
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="<?php echo $base_url; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor;
                                    
                                    if ($end_page < $total_pages): 
                                        if ($end_page < $total_pages - 1): ?>
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                        <li class="page-item"><a class="page-link" href="<?php echo $base_url; ?>&page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a></li>
                                    <?php endif;
                                    
                                    if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo $base_url; ?>&page=<?php echo $page + 1; ?>">Next</a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">Next</span>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p class="text-muted text-center py-4">No complaints yet. Submit one using the button above.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Create Complaint Modal -->
<div class="modal fade" id="createComplaintModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Submit a Complaint</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="#">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="5" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Complaint</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
