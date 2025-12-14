<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware/auth.php';

require_login();
require_role([ROLE_USER, ROLE_STAFF, ROLE_ADMIN, ROLE_SUPERADMIN]);

$user_id = current_user_id();
$user_role = current_user_role();
$barangay_id = current_user_barangay_id();

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'soft_delete') {
    if ($user_role === ROLE_USER) {
        $complaint_id = intval($_POST['complaint_id'] ?? 0);
        
        if ($complaint_id > 0) {
            // Verify complaint belongs to user and is open
            $verify_stmt = $conn->prepare('SELECT id, complaint_status_id FROM complaint WHERE id = ? AND user_id = ? AND complaint_status_id = 1');
            if ($verify_stmt) {
                $verify_stmt->bind_param('ii', $complaint_id, $user_id);
                $verify_stmt->execute();
                $verify_res = $verify_stmt->get_result();
                
                if ($verify_res->num_rows > 0) {
                    // Perform soft delete
                    $delete_stmt = $conn->prepare('UPDATE complaint SET deleted_at = NOW() WHERE id = ?');
                    if ($delete_stmt) {
                        $delete_stmt->bind_param('i', $complaint_id);
                        if ($delete_stmt->execute()) {
                            activity_log($user_id, 'Deleted complaint', 'complaint', $complaint_id);
                            flash_set('Complaint deleted successfully.', 'success');
                        } else {
                            flash_set('Failed to delete complaint.', 'error');
                        }
                        $delete_stmt->close();
                    }
                } else {
                    flash_set('Cannot delete this complaint. Only open complaints can be deleted.', 'error');
                }
                $verify_stmt->close();
            }
        }
    }
    
    header('Location: index.php?nav=manage-complaints&filter=' . urlencode($_GET['filter'] ?? 'all') . '&q=' . urlencode($_GET['q'] ?? ''));
    exit;
}

// Page title based on role
$pageTitle = $user_role === ROLE_USER ? 'My Complaints' : 'Manage Complaints';

// Get search/filter params
$q = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all'; // all, open, in_progress, resolved, closed
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query based on role
$where = [];
$bind_params = [];
$bind_types = '';

// Role-based scoping
if ($user_role === ROLE_USER) {
    $where[] = 'c.user_id = ?';
    $bind_params[] = $user_id;
    $bind_types .= 'i';
} else if (in_array($user_role, [ROLE_STAFF, ROLE_ADMIN])) {
    $where[] = 'c.barangay_id = ?';
    $bind_params[] = $barangay_id;
    $bind_types .= 'i';
    // All complaints from barangay shown
} else if ($user_role === ROLE_SUPERADMIN) {
    // Can see all complaints (no WHERE clause)
}

// Search
if ($q !== '') {
    $where[] = '(c.title LIKE ? OR c.description LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ?)';
    $search_term = "%$q%";
    array_push($bind_params, $search_term, $search_term, $search_term, $search_term);
    $bind_types .= 'ssss';
}

// Filter by status
if (in_array($filter, ['open', 'in_progress', 'resolved', 'closed'], true)) {
    $status_map = ['open'=>1, 'in_progress'=>2, 'resolved'=>3, 'closed'=>4];
    $where[] = 'c.complaint_status_id = ?';
    $bind_params[] = $status_map[$filter];
    $bind_types .= 'i';
}

// Exclude soft-deleted complaints
$where[] = 'c.deleted_at IS NULL';

$where_sql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

// Priority requests for staff (open complaints) and admin (resolved complaints)
$priority_complaints = [];
if ($user_role === ROLE_STAFF) {
    $priority_sql = "
        SELECT c.id, c.user_id, c.title, c.description, c.complaint_status_id, c.is_anonymous,
               c.created_at, cs.name as status_name, p.first_name, p.last_name
        FROM complaint c
        LEFT JOIN complaint_status cs ON c.complaint_status_id = cs.id
        LEFT JOIN users u ON c.user_id = u.id
        LEFT JOIN profile p ON u.id = p.user_id
        WHERE c.barangay_id = ? AND c.complaint_status_id = 1 AND c.deleted_at IS NULL
        ORDER BY c.created_at DESC
    ";
    $priority_stmt = $conn->prepare($priority_sql);
    if ($priority_stmt) {
        $priority_stmt->bind_param('i', $barangay_id);
        $priority_stmt->execute();
        $priority_res = $priority_stmt->get_result();
        while ($row = $priority_res->fetch_assoc()) {
            $priority_complaints[] = $row;
        }
        $priority_stmt->close();
    }
} else if ($user_role === ROLE_ADMIN) {
    $priority_sql = "
        SELECT c.id, c.user_id, c.title, c.description, c.complaint_status_id, c.is_anonymous,
               c.created_at, cs.name as status_name, p.first_name, p.last_name
        FROM complaint c
        LEFT JOIN complaint_status cs ON c.complaint_status_id = cs.id
        LEFT JOIN users u ON c.user_id = u.id
        LEFT JOIN profile p ON u.id = p.user_id
        WHERE c.barangay_id = ? AND c.complaint_status_id = 3 AND c.deleted_at IS NULL
        ORDER BY c.created_at DESC
    ";
    $priority_stmt = $conn->prepare($priority_sql);
    if ($priority_stmt) {
        $priority_stmt->bind_param('i', $barangay_id);
        $priority_stmt->execute();
        $priority_res = $priority_stmt->get_result();
        while ($row = $priority_res->fetch_assoc()) {
            $priority_complaints[] = $row;
        }
        $priority_stmt->close();
    }
}

// Count total for pagination (excluding priority items)
$count_sql = "SELECT COUNT(*) as total
    FROM complaint c
    LEFT JOIN complaint_status cs ON c.complaint_status_id = cs.id
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN profile p ON u.id = p.user_id
    $where_sql";

if (count($priority_complaints) > 0) {
    $priority_ids = array_column($priority_complaints, 'id');
    $placeholders = implode(',', array_fill(0, count($priority_ids), '?'));
    $count_sql .= " AND c.id NOT IN ($placeholders)";
}

$count_bind_params = $bind_params;
$count_bind_types = $bind_types;

if (count($priority_complaints) > 0) {
    $count_bind_types .= str_repeat('i', count($priority_ids));
    $count_bind_params = array_merge($count_bind_params, $priority_ids);
}

$count_stmt = $conn->prepare($count_sql);
if ($count_stmt && !empty($count_bind_params)) {
    $count_stmt->bind_param($count_bind_types, ...$count_bind_params);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_complaints = $count_result->fetch_assoc()['total'];
    $count_stmt->close();
} else if ($count_stmt) {
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_complaints = $count_result->fetch_assoc()['total'];
    $count_stmt->close();
} else {
    $total_complaints = 0;
}
$total_pages = ceil($total_complaints / $per_page);

// Main query excluding priority items
$sql = "
    SELECT c.id, c.user_id, c.title, c.description, c.complaint_status_id, c.is_anonymous,
           c.created_at, cs.name as status_name, p.first_name, p.last_name
    FROM complaint c
    LEFT JOIN complaint_status cs ON c.complaint_status_id = cs.id
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN profile p ON u.id = p.user_id
    $where_sql
";

if (count($priority_complaints) > 0) {
    $priority_ids = array_column($priority_complaints, 'id');
    $placeholders = implode(',', array_fill(0, count($priority_ids), '?'));
    $sql .= " AND c.id NOT IN ($placeholders)";
    
    $bind_types .= str_repeat('i', count($priority_ids));
    $bind_params = array_merge($bind_params, $priority_ids);
}

$sql .= " ORDER BY c.created_at DESC LIMIT $per_page OFFSET $offset";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log('Manage Complaints SQL Error: ' . $conn->error);
    error_log('SQL: ' . $sql);
    die('Database error occurred. Please contact administrator.');
}
if (!empty($bind_params)) {
    $stmt->bind_param($bind_types, ...$bind_params);
}
$stmt->execute();
$res = $stmt->get_result();
$complaints = [];
while ($row = $res->fetch_assoc()) {
    $complaints[] = $row;
}
$stmt->close();

// Status counts
$count_query_where = '';
if ($user_role === ROLE_USER) {
    $count_query_where = 'WHERE user_id = ' . intval($user_id) . ' AND deleted_at IS NULL';
} else if (in_array($user_role, [ROLE_STAFF, ROLE_ADMIN])) {
    $count_query_where = 'WHERE barangay_id = ' . intval($barangay_id) . ' AND deleted_at IS NULL';
} else if ($user_role === ROLE_SUPERADMIN) {
    $count_query_where = 'WHERE deleted_at IS NULL';
}

$count_query = "SELECT 
    SUM(CASE WHEN complaint_status_id = 1 THEN 1 ELSE 0 END) AS open_count,
    SUM(CASE WHEN complaint_status_id = 2 THEN 1 ELSE 0 END) AS in_progress_count,
    SUM(CASE WHEN complaint_status_id = 3 THEN 1 ELSE 0 END) AS resolved_count,
    SUM(CASE WHEN complaint_status_id = 4 THEN 1 ELSE 0 END) AS closed_count,
    COUNT(*) AS total_count
    FROM complaint $count_query_where";
$count_res = db_query($count_query);
$counts = $count_res ? $count_res->fetch_assoc() : [
    'open_count' => 0,
    'in_progress_count' => 0,
    'resolved_count' => 0,
    'closed_count' => 0,
    'total_count' => 0,
];

require_once __DIR__ . '/../public/header.php';
?>

<div class="container my-5">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="mb-2"><i class="fas fa-comments me-2"></i><?php echo $pageTitle; ?></h2>
            <p class="text-muted mb-0">
                <?php 
                if ($user_role === ROLE_USER) {
                    echo 'View and track your submitted complaints';
                } else if ($user_role === ROLE_STAFF) {
                    echo 'Review and process complaints from your barangay';
                } else if ($user_role === ROLE_ADMIN) {
                    echo 'Oversee all complaints and confirm resolutions';
                } else {
                    echo 'Monitor complaints across all barangays';
                }
                ?>
            </p>
        </div>
        <div class="col-md-4">
            <form class="input-group" method="get" action="<?php echo WEB_ROOT; ?>/index.php">
                <input type="hidden" name="nav" value="manage-complaints">
                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search...">
                <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
            </form>
            <?php if ($user_role === ROLE_USER): ?>
                <a href="index.php?nav=create-complaint" class="btn btn-danger mt-2 w-100">
                    <i class="fas fa-plus me-2"></i>New Complaint
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <ul class="nav nav-pills card-header-pills mb-0">
                <?php $base = WEB_ROOT . '/index.php?nav=manage-complaints&q=' . urlencode($q); ?>
                <li class="nav-item"><a class="nav-link <?php echo $filter==='all'?'active':''; ?>" href="<?php echo $base; ?>&filter=all">All <span class="badge <?php echo $filter==='all'?'bg-light text-dark':'bg-secondary'; ?> ms-1"><?php echo $counts['total_count'] ?? 0; ?></span></a></li>
                <li class="nav-item"><a class="nav-link <?php echo $filter==='open'?'active':''; ?>" href="<?php echo $base; ?>&filter=open">Open <span class="badge <?php echo $filter==='open'?'bg-light text-dark':'bg-danger'; ?> ms-1"><?php echo $counts['open_count'] ?? 0; ?></span></a></li>
                <li class="nav-item"><a class="nav-link <?php echo $filter==='in_progress'?'active':''; ?>" href="<?php echo $base; ?>&filter=in_progress">In Progress <span class="badge <?php echo $filter==='in_progress'?'bg-light text-dark':'bg-info'; ?> ms-1"><?php echo $counts['in_progress_count'] ?? 0; ?></span></a></li>
                <li class="nav-item"><a class="nav-link <?php echo $filter==='resolved'?'active':''; ?>" href="<?php echo $base; ?>&filter=resolved">Resolved <span class="badge <?php echo $filter==='resolved'?'bg-light text-dark':'bg-warning text-dark'; ?> ms-1"><?php echo $counts['resolved_count'] ?? 0; ?></span></a></li>
                <li class="nav-item"><a class="nav-link <?php echo $filter==='closed'?'active':''; ?>" href="<?php echo $base; ?>&filter=closed">Closed <span class="badge <?php echo $filter==='closed'?'bg-light text-dark':'bg-success'; ?> ms-1"><?php echo $counts['closed_count'] ?? 0; ?></span></a></li>
            </ul>
        </div>
        <div class="card-body p-0">
            <!-- Priority Section -->
            <?php if (count($priority_complaints) > 0): ?>
                <div style="background: linear-gradient(135deg, #fff3cd 0%, #fff8e1 100%); border-bottom: 2px solid #ffc107; padding: 1rem;">
                    <h6 class="mb-2 fw-600" style="color: #856404;">
                        <i class="fas fa-star me-2"></i>
                        <?php if ($user_role === ROLE_STAFF): ?>
                            New Complaints (<?php echo count($priority_complaints); ?>)
                        <?php else: ?>
                            Awaiting Confirmation (<?php echo count($priority_complaints); ?>)
                        <?php endif; ?>
                    </h6>
                    <small class="text-muted" style="color: #856404;">
                        <?php if ($user_role === ROLE_STAFF): ?>
                            New complaints awaiting your review
                        <?php else: ?>
                            Resolved complaints awaiting your confirmation
                        <?php endif; ?>
                    </small>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach ($priority_complaints as $c): ?>
                        <div class="list-group-item p-3" style="border-left: 4px solid #ffc107;">
                            <div class="d-flex align-items-center justify-content-between gap-2" style="flex-wrap: wrap; row-gap: 0.75rem;">
                                <!-- Left: Icon + Info -->
                                <div class="d-flex align-items-center gap-2" style="flex: 1; min-width: 200px;">
                                    <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px; border-radius: 8px; font-size: 1.1rem; background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%);">
                                        <i class="fas fa-comments text-white"></i>
                                    </div>
                                    <div style="min-width: 0; flex: 1;">
                                        <h6 class="mb-1 fw-600" style="color: #111827; font-size: 0.92rem;">
                                            <?php echo htmlspecialchars(substr($c['title'], 0, 35)); ?>
                                        </h6>
                                        <small class="text-muted" style="font-size: 0.75rem;">
                                            <i class="far fa-clock me-1"></i><?php echo date('M d, Y', strtotime($c['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>

                                <!-- Right: Status & Buttons -->
                                <div class="d-flex align-items-center gap-2" style="flex-shrink: 0; flex-wrap: wrap;">
                                    <span class="badge bg-<?php 
                                        $status_map = [1 => 'danger', 2 => 'info', 3 => 'warning', 4 => 'success'];
                                        echo $status_map[$c['complaint_status_id']] ?? 'secondary';
                                    ?>" style="padding: 0.35rem 0.6rem; font-size: 0.7rem;">
                                        <?php echo htmlspecialchars($c['status_name']); ?>
                                    </span>
                                    <a href="index.php?nav=complaint-ticket&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-primary" style="padding: 0.35rem 0.65rem; font-size: 0.8rem;">View</a>
                                    <?php if ($user_role === ROLE_USER && $c['complaint_status_id'] === 1): ?>
                                        <a href="index.php?nav=edit-complaint&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-warning" style="padding: 0.35rem 0.65rem; font-size: 0.8rem;" title="Edit this complaint">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" action="index.php?nav=manage-complaints" style="display: inline;">
                                            <input type="hidden" name="action" value="soft_delete">
                                            <input type="hidden" name="complaint_id" value="<?php echo $c['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" style="padding: 0.35rem 0.65rem; font-size: 0.8rem;" title="Delete this complaint" onclick="return confirm('Delete this complaint? This action cannot be undone.');">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="border-bottom: 1px solid #e5e7eb; padding: 0.75rem; background-color: #f9fafb; text-align: center;">
                    <small class="text-muted">Other complaints</small>
                </div>
            <?php endif; ?>

            <!-- Regular List -->
            <?php if (count($priority_complaints) === 0 && count($complaints) === 0): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted mb-0">No complaints found.</p>
                </div>
            <?php elseif (count($complaints) > 0): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($complaints as $c): ?>
                        <div class="list-group-item p-3" style="border-left: 4px solid #0b3d91;">
                            <div class="d-flex align-items-center justify-content-between gap-2" style="flex-wrap: wrap; row-gap: 0.75rem;">
                                <!-- Left: Icon + Info -->
                                <div class="d-flex align-items-center gap-2" style="flex: 1; min-width: 200px;">
                                    <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px; border-radius: 8px; font-size: 1.1rem; background: linear-gradient(135deg, #0b3d91 0%, #1e5cc8 100%);">
                                        <i class="fas fa-comments text-white"></i>
                                    </div>
                                    <div style="min-width: 0; flex: 1;">
                                        <h6 class="mb-1 fw-600" style="color: #111827; font-size: 0.92rem;">
                                            <?php echo htmlspecialchars(substr($c['title'], 0, 35)); ?>
                                        </h6>
                                        <small class="text-muted" style="font-size: 0.75rem;">
                                            <i class="far fa-clock me-1"></i><?php echo date('M d, Y', strtotime($c['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>

                                <!-- Right: Status & Button -->
                                <div class="d-flex align-items-center gap-2" style="flex-shrink: 0;">
                                    <?php 
                                    $status_colors = [
                                        1 => 'danger',
                                        2 => 'info',
                                        3 => 'warning',
                                        4 => 'success'
                                    ];
                                    $color = $status_colors[$c['complaint_status_id']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>" style="padding: 0.35rem 0.6rem; font-size: 0.7rem;">
                                        <?php echo htmlspecialchars($c['status_name']); ?>
                                    </span>
                                    <a href="index.php?nav=complaint-ticket&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-primary" style="padding: 0.35rem 0.65rem; font-size: 0.8rem;">View</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-light">
                <nav aria-label="Complaints pagination">
                    <ul class="pagination pagination-sm mb-0 justify-content-center">
                        <?php 
                        $base_url = WEB_ROOT . '/index.php?nav=manage-complaints&filter=' . urlencode($filter) . '&q=' . urlencode($q);
                        
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
                        <?php endif;
                        
                        if ($start_page > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif;
                        
                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?php echo ($i === $page ? 'active' : ''); ?>">
                                <?php if ($i === $page): ?>
                                    <span class="page-link"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a class="page-link" href="<?php echo $base_url; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            </li>
                        <?php endfor;
                        
                        if ($end_page < $total_pages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif;
                        
                        if ($end_page < $total_pages): ?>
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
</div>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
