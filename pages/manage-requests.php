<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware/auth.php';

require_login();
require_role([ROLE_USER, ROLE_STAFF, ROLE_ADMIN, ROLE_SUPERADMIN]);

$user_id = current_user_id();
$user_role = current_user_role();
$barangay_id = current_user_barangay_id();

// Page title based on role
$pageTitle = $user_role === ROLE_USER ? 'My Requests' : 'Manage Document Requests';

// Get search/filter params
$q = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all'; // all, pending(1), approved(2), rejected(3), completed(4)
$doc_filter = isset($_GET['doc']) ? intval($_GET['doc']) : 0;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query based on role
$where = [];
$bind_params = [];
$bind_types = '';

// Role-based scoping
if ($user_role === ROLE_USER) {
    $where[] = 'r.user_id = ?';
    $bind_params[] = $user_id;
    $bind_types .= 'i';
} else if (in_array($user_role, [ROLE_STAFF, ROLE_ADMIN])) {
    $where[] = 'r.barangay_id = ?';
    $bind_params[] = $barangay_id;
    $bind_types .= 'i';
    // All requests from barangay shown (no status restriction for listing)
}

// Search
if ($q !== '') {
    $where[] = '(dt.name LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR u.username LIKE ?)';
    $search_term = "%$q%";
    array_push($bind_params, $search_term, $search_term, $search_term, $search_term);
    $bind_types .= 'ssss';
}

// Filter
if (in_array($filter, ['pending','approved','rejected','completed'], true)) {
    $status_map = ['pending'=>1,'approved'=>2,'rejected'=>3,'completed'=>4];
    $where[] = 'r.request_status_id = ?';
    $bind_params[] = $status_map[$filter];
    $bind_types .= 'i';
}

// Document type filter
if ($doc_filter > 0) {
    $where[] = 'r.document_type_id = ?';
    $bind_params[] = $doc_filter;
    $bind_types .= 'i';
}

$where_sql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

// Fetch priority requests for staff (pending) and admin (approved)
$priority_requests = [];
if ($user_role === ROLE_STAFF) {
    // Staff priority: their claimed pending requests
    $priority_sql = "
        SELECT r.id, r.user_id, r.document_type_id, r.request_status_id, r.claimed_by,
               r.created_at, dt.name as doc_type, rs.name as status_name,
               p.first_name, p.last_name, u.username, u.deleted_at as user_deleted_at
        FROM request r
        LEFT JOIN document_type dt ON r.document_type_id = dt.id
        LEFT JOIN request_status rs ON r.request_status_id = rs.id
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN profile p ON u.id = p.user_id
        WHERE r.barangay_id = ? AND r.request_status_id = 1 AND r.claimed_by = ?
        ORDER BY r.created_at DESC
    ";
    $priority_stmt = $conn->prepare($priority_sql);
    if ($priority_stmt) {
        $priority_stmt->bind_param('ii', $barangay_id, $user_id);
        $priority_stmt->execute();
        $priority_res = $priority_stmt->get_result();
        while ($row = $priority_res->fetch_assoc()) {
            $priority_requests[] = $row;
        }
        $priority_stmt->close();
    }
} else if ($user_role === ROLE_ADMIN) {
    // Admin priority: approved requests
    $priority_sql = "
        SELECT r.id, r.user_id, r.document_type_id, r.request_status_id, r.claimed_by,
               r.created_at, dt.name as doc_type, rs.name as status_name,
               p.first_name, p.last_name, u.username, u.deleted_at as user_deleted_at
        FROM request r
        LEFT JOIN document_type dt ON r.document_type_id = dt.id
        LEFT JOIN request_status rs ON r.request_status_id = rs.id
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN profile p ON u.id = p.user_id
        WHERE r.barangay_id = ? AND r.request_status_id = 2
        ORDER BY r.created_at DESC
    ";
    $priority_stmt = $conn->prepare($priority_sql);
    if ($priority_stmt) {
        $priority_stmt->bind_param('i', $barangay_id);
        $priority_stmt->execute();
        $priority_res = $priority_stmt->get_result();
        while ($row = $priority_res->fetch_assoc()) {
            $priority_requests[] = $row;
        }
        $priority_stmt->close();
    }
}

// Count total for pagination (excluding priority items from count)
$count_sql = "SELECT COUNT(*) as total
    FROM request r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN profile p ON u.id = p.user_id
    LEFT JOIN document_type dt ON r.document_type_id = dt.id
    $where_sql";
    
if (count($priority_requests) > 0) {
    $priority_ids = array_column($priority_requests, 'id');
    $placeholders = implode(',', array_fill(0, count($priority_ids), '?'));
    $count_sql .= " AND r.id NOT IN ($placeholders)";
}

$count_bind_params = $bind_params;
$count_bind_types = $bind_types;

if (count($priority_requests) > 0) {
    $count_bind_types .= str_repeat('i', count($priority_ids));
    $count_bind_params = array_merge($count_bind_params, $priority_ids);
}

$count_stmt = $conn->prepare($count_sql);
if ($count_stmt && !empty($count_bind_params)) {
    $count_stmt->bind_param($count_bind_types, ...$count_bind_params);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_requests = $count_result->fetch_assoc()['total'];
    $count_stmt->close();
} else if ($count_stmt) {
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_requests = $count_result->fetch_assoc()['total'];
    $count_stmt->close();
} else {
    $total_requests = 0;
}
$total_pages = ceil($total_requests / $per_page);

$sql = "
    SELECT r.id, r.user_id, r.document_type_id, r.request_status_id, r.claimed_by,
           r.created_at, dt.name as doc_type, rs.name as status_name,
           p.first_name, p.last_name, u.username, u.deleted_at as user_deleted_at
    FROM request r
    LEFT JOIN document_type dt ON r.document_type_id = dt.id
    LEFT JOIN request_status rs ON r.request_status_id = rs.id
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN profile p ON u.id = p.user_id
    $where_sql
    ";
    
// Exclude priority items from main list if they exist
if (count($priority_requests) > 0) {
    $priority_ids = array_column($priority_requests, 'id');
    $placeholders = implode(',', array_fill(0, count($priority_ids), '?'));
    $sql .= " AND r.id NOT IN ($placeholders)";
    
    $bind_types .= str_repeat('i', count($priority_ids));
    $bind_params = array_merge($bind_params, $priority_ids);
}

$sql .= " ORDER BY r.created_at DESC LIMIT $per_page OFFSET $offset";

$stmt = $conn->prepare($sql);
if (!$stmt) die('DB prepare failed');
if (!empty($bind_params)) {
    $stmt->bind_param($bind_types, ...$bind_params);
}
$stmt->execute();
$res = $stmt->get_result();
$requests = [];
while ($row = $res->fetch_assoc()) {
    $requests[] = $row;
}
$stmt->close();

// Status counts for pills based on role - scoped to barangay
$count_query_where = '';
if ($user_role === ROLE_USER) {
    $count_query_where = 'WHERE user_id = ' . intval($user_id);
} else if (in_array($user_role, [ROLE_STAFF, ROLE_ADMIN])) {
    $count_query_where = 'WHERE barangay_id = ' . intval($barangay_id);
}

$count_query = "SELECT 
    SUM(CASE WHEN request_status_id = 1 THEN 1 ELSE 0 END) AS pending_count,
    SUM(CASE WHEN request_status_id = 2 THEN 1 ELSE 0 END) AS approved_count,
    SUM(CASE WHEN request_status_id = 3 THEN 1 ELSE 0 END) AS rejected_count,
    SUM(CASE WHEN request_status_id = 4 THEN 1 ELSE 0 END) AS completed_count,
    COUNT(*) AS total_count
    FROM request $count_query_where";
$count_res = db_query($count_query);
$counts = $count_res ? $count_res->fetch_assoc() : [
    'pending_count' => 0,
    'approved_count' => 0,
    'rejected_count' => 0,
    'completed_count' => 0,
    'total_count' => 0,
];

// Helper function to display user name or [Deleted User]
function displayUserName($row) {
    if (!empty($row['user_deleted_at'])) {
        return '<span class="text-muted fst-italic">[Deleted User]</span>';
    }
    return htmlspecialchars(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
}

require_once __DIR__ . '/../public/header.php';

// Load document types for filter select
$doc_types = [];
$dtRes = db_query('SELECT id, name FROM document_type ORDER BY name');
if ($dtRes) {
    while ($row = $dtRes->fetch_assoc()) { $doc_types[] = $row; }
}
?>

<div class="container my-5">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="mb-2"><i class="fas fa-tasks me-2"></i><?php echo $pageTitle; ?></h2>
            <p class="text-muted mb-0">
                <?php 
                if ($user_role === ROLE_USER) {
                    echo 'Track and manage your document requests';
                } else if ($user_role === ROLE_STAFF) {
                    echo 'Review and process pending requests in your barangay';
                } else if ($user_role === ROLE_ADMIN) {
                    echo 'Manage and finalize approved requests';
                } else {
                    echo 'Monitor all requests across barangays';
                }
                ?>
            </p>
        </div>
        <div class="col-md-4">
            <form class="input-group" method="get" action="<?php echo WEB_ROOT; ?>/index.php">
                <input type="hidden" name="nav" value="manage-requests">
                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                <input type="hidden" name="doc" value="<?php echo htmlspecialchars($doc_filter); ?>">
                <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search...">
                <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
            </form>
            <?php if ($user_role === ROLE_USER): ?>
                <a href="index.php?nav=create-request" class="btn btn-success mt-2 w-100">
                    <i class="fas fa-plus me-2"></i>New Request
                </a>
            <?php endif; ?>
            <?php if ($user_role === ROLE_ADMIN): ?>
                <a href="index.php?nav=manage-document-types" class="btn btn-outline-primary mt-2 w-100">
                    <i class="fas fa-cog me-2"></i>Manage Document Types
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <ul class="nav nav-pills card-header-pills mb-0">
                    <?php $base = WEB_ROOT . '/index.php?nav=manage-requests&q=' . urlencode($q) . '&doc=' . urlencode($doc_filter); ?>
                    <li class="nav-item"><a class="nav-link <?php echo $filter==='all'?'active':''; ?>" href="<?php echo $base; ?>&filter=all">All <span class="badge <?php echo $filter==='all'?'bg-light text-dark':'bg-secondary'; ?> ms-1"><?php echo $counts['total_count'] ?? 0; ?></span></a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $filter==='pending'?'active':''; ?>" href="<?php echo $base; ?>&filter=pending">Pending <span class="badge <?php echo $filter==='pending'?'bg-light text-dark':'bg-warning text-dark'; ?> ms-1"><?php echo $counts['pending_count'] ?? 0; ?></span></a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $filter==='approved'?'active':''; ?>" href="<?php echo $base; ?>&filter=approved">Approved <span class="badge <?php echo $filter==='approved'?'bg-light text-dark':'bg-info'; ?> ms-1"><?php echo $counts['approved_count'] ?? 0; ?></span></a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $filter==='rejected'?'active':''; ?>" href="<?php echo $base; ?>&filter=rejected">Rejected <span class="badge <?php echo $filter==='rejected'?'bg-light text-dark':'bg-danger'; ?> ms-1"><?php echo $counts['rejected_count'] ?? 0; ?></span></a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $filter==='completed'?'active':''; ?>" href="<?php echo $base; ?>&filter=completed">Completed <span class="badge <?php echo $filter==='completed'?'bg-light text-dark':'bg-success'; ?> ms-1"><?php echo $counts['completed_count'] ?? 0; ?></span></a></li>
                </ul>
                <form method="get" action="<?php echo WEB_ROOT; ?>/index.php" class="ms-2">
                    <input type="hidden" name="nav" value="manage-requests">
                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($q); ?>">
                    <select class="form-select form-select-sm" name="doc" onchange="this.form.submit()" style="min-width: 180px;">
                        <option value="0">All Types</option>
                        <?php foreach ($doc_types as $dt): ?>
                            <option value="<?php echo intval($dt['id']); ?>" <?php echo ($doc_filter === intval($dt['id'])) ? 'selected' : ''; ?>><?php echo htmlspecialchars($dt['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>
        <div class="card-body p-0">
            <?php 
            // Show priority section for staff (pending claimed) and admin (approved)
            if (count($priority_requests) > 0): ?>
                <div style="background: linear-gradient(135deg, #fff3cd 0%, #fff8e1 100%); border-bottom: 2px solid #ffc107; padding: 1rem;">
                    <h6 class="mb-2 fw-600" style="color: #856404;">
                        <i class="fas fa-star me-2"></i>
                        <?php if ($user_role === ROLE_STAFF): ?>
                            My Pending Requests (<?php echo count($priority_requests); ?>)
                        <?php else: ?>
                            Approved Requests (<?php echo count($priority_requests); ?>)
                        <?php endif; ?>
                    </h6>
                    <small class="text-muted" style="color: #856404;">
                        <?php if ($user_role === ROLE_STAFF): ?>
                            Pending tickets you're working on
                        <?php else: ?>
                            Ready for final processing
                        <?php endif; ?>
                    </small>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach ($priority_requests as $r): ?>
                    <div class="list-group-item list-group-item-action p-3" style="border-left: 4px solid #ffc107; background-color: #fffbf0;">
                            <div class="d-flex align-items-center justify-content-between gap-4" style="flex-wrap: wrap; row-gap: 1rem;">
                                <!-- Left: Document Badge + Info -->
                                <div class="d-flex align-items-center gap-3" style="flex: 0 1 45%; min-width: 0;">
                                    <div class="doc-type-badge d-flex align-items-center justify-content-center flex-shrink-0" style="width: 56px; height: 56px; border-radius: 10px; font-size: 1.5rem; box-shadow: 0 2px 8px rgba(255, 193, 7, 0.2); background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%);">
                                        <i class="fas fa-file-alt text-white"></i>
                                    </div>
                                    <div style="min-width: 0; flex-grow: 1;">
                                        <h6 class="mb-2 fw-700" style="color: #111827; font-size: 0.98rem; margin: 0;">
                                            <?php echo htmlspecialchars($r['doc_type'] ?? 'Unknown Document'); ?>
                                        </h6>
                                        <small class="text-muted d-block mb-1" style="font-size: 0.8rem;">
                                            <i class="fas fa-user-circle me-1" style="color: #9ca3af;"></i><?php echo displayUserName($r); ?>
                                        </small>
                                        <small class="text-muted d-block" style="font-size: 0.8rem;">
                                            <i class="far fa-clock me-1" style="color: #9ca3af;"></i><?php echo date('M d, Y • h:i A', strtotime($r['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>

                                <!-- Center: Request ID -->
                                <div class="flex-shrink-0" style="text-align: center; flex: 0 0 auto;">
                                    <small class="text-muted d-block mb-1" style="font-weight: 500; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">Request ID</small>
                                    <code class="fw-600 d-block" style="color: #ffc107; font-size: 0.9rem; background-color: #fff8e1; padding: 0.4rem 0.6rem; border-radius: 4px; display: inline-block; white-space: nowrap;">#<?php echo str_pad($r['id'], 6, '0', STR_PAD_LEFT); ?></code>
                                </div>

                                <!-- Center-Right: Status Badge -->
                                <div class="flex-shrink-0" style="flex: 0 0 auto;">
                                    <?php $sid = intval($r['request_status_id']);
                                    $badge_colors = [
                                        1 => 'bg-warning text-dark',  // pending
                                        2 => 'bg-info text-white',    // approved
                                        3 => 'bg-danger text-white',  // rejected
                                        4 => 'bg-success text-white'  // completed
                                    ];
                                    $badge = $badge_colors[$sid] ?? 'bg-secondary text-white'; ?>
                                    <span class="badge <?php echo $badge; ?>" style="padding: 0.55rem 0.9rem; font-size: 0.8rem; font-weight: 600; white-space: nowrap; display: inline-block;">
                                        <?php echo htmlspecialchars($r['status_name']); ?>
                                    </span>
                                </div>

                                <!-- Right: Action Button -->
                                <div class="flex-shrink-0" style="flex: 0 0 auto;">
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#requestModal" onclick='loadRequestData(<?php echo json_encode($r); ?>)' style="padding: 0.5rem 1rem; font-size: 0.85rem; font-weight: 500; white-space: nowrap;">
                                        <i class="fas fa-arrow-right me-1"></i>View
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="border-bottom: 1px solid #e5e7eb; padding: 1rem; background-color: #f9f9f9; text-align: center;">
                    <small class="text-muted">All other requests below</small>
                </div>
            <?php endif; ?>
            
            <?php if (count($priority_requests) === 0 && count($requests) === 0): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted mb-0">No requests found for this filter.</p>
                </div>
            <?php elseif (count($requests) > 0): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($requests as $r): ?>
                    <div class="list-group-item list-group-item-action p-3" style="border-left: 4px solid <?php echo (count($priority_requests) > 0) ? '#6c757d' : '#0b3d91'; ?>;">
                        <div class="d-flex align-items-center justify-content-between gap-4" style="flex-wrap: wrap; row-gap: 1rem;">
                                <!-- Left: Document Badge + Info (takes moderate space) -->
                                <div class="d-flex align-items-center gap-3" style="flex: 0 1 45%; min-width: 0;">
                                    <div class="doc-type-badge d-flex align-items-center justify-content-center flex-shrink-0" style="width: 56px; height: 56px; border-radius: 10px; font-size: 1.5rem; box-shadow: 0 2px 8px rgba(11, 61, 145, 0.15); background: linear-gradient(135deg, #0b3d91 0%, #1e5cc8 100%);">
                                        <i class="fas fa-file-alt text-white"></i>
                                    </div>
                                    <div style="min-width: 0; flex-grow: 1;">
                                        <h6 class="mb-2 fw-700" style="color: #111827; font-size: 0.98rem; margin: 0;">
                                            <?php echo htmlspecialchars($r['doc_type'] ?? 'Unknown Document'); ?>
                                        </h6>
                                        <small class="text-muted d-block mb-1" style="font-size: 0.8rem;">
                                            <i class="fas fa-user-circle me-1" style="color: #9ca3af;"></i><?php echo displayUserName($r); ?>
                                        </small>
                                        <small class="text-muted d-block" style="font-size: 0.8rem;">
                                            <i class="far fa-clock me-1" style="color: #9ca3af;"></i><?php echo date('M d, Y • h:i A', strtotime($r['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>

                                <!-- Center: Request ID -->
                                <div class="flex-shrink-0" style="text-align: center; flex: 0 0 auto;">
                                    <small class="text-muted d-block mb-1" style="font-weight: 500; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">Request ID</small>
                                    <code class="fw-600 d-block" style="color: #0b3d91; font-size: 0.9rem; background-color: #f0f4f8; padding: 0.4rem 0.6rem; border-radius: 4px; display: inline-block; white-space: nowrap;">#<?php echo str_pad($r['id'], 6, '0', STR_PAD_LEFT); ?></code>
                                </div>

                                <!-- Center-Right: Status Badge -->
                                <div class="flex-shrink-0" style="flex: 0 0 auto;">
                                    <?php $sid = intval($r['request_status_id']);
                                    $badge_colors = [
                                        1 => 'bg-warning text-dark',  // pending
                                        2 => 'bg-info text-white',    // approved
                                        3 => 'bg-danger text-white',  // rejected
                                        4 => 'bg-success text-white'  // completed
                                    ];
                                    $badge = $badge_colors[$sid] ?? 'bg-secondary text-white'; ?>
                                    <span class="badge <?php echo $badge; ?>" style="padding: 0.55rem 0.9rem; font-size: 0.8rem; font-weight: 600; white-space: nowrap; display: inline-block;">
                                        <?php echo htmlspecialchars($r['status_name']); ?>
                                    </span>
                                </div>

                                <!-- Right: Action Button -->
                                <div class="flex-shrink-0" style="flex: 0 0 auto;">
                                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#requestModal" onclick='loadRequestData(<?php echo json_encode($r); ?>)' style="padding: 0.5rem 1rem; font-size: 0.85rem; font-weight: 500; white-space: nowrap;">
                                        <i class="fas fa-arrow-right me-1"></i>View
                                    </button>
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
                <nav aria-label="Requests pagination">
                    <ul class="pagination pagination-sm mb-0 justify-content-center">
                        <?php 
                        $base_url = WEB_ROOT . '/index.php?nav=manage-requests&filter=' . urlencode($filter) . '&q=' . urlencode($q) . '&doc=' . urlencode($doc_filter);
                        
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
</div>

<!-- Request Details Modal -->
<div class="modal fade" id="requestModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-ticket-alt me-2"></i>Request #<span id="reqId"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <small class="text-muted d-block mb-1">Requester</small>
                        <div class="fw-semibold" id="reqName"></div>
                        <div class="text-muted small" id="reqUsername"></div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block mb-1">Document Type</small>
                        <div class="fw-semibold" id="reqDoc"></div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block mb-1">Status</small>
                        <div id="reqStatus"></div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block mb-1">Submitted</small>
                        <div class="fw-semibold" id="reqDate"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a id="reqOpenTicket" class="btn btn-primary"><i class="fas fa-external-link-alt me-1"></i>Open Ticket</a>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function loadRequestData(r) {
    document.getElementById('reqId').textContent = r.id;
    const full = ((r.first_name||'') + ' ' + (r.last_name||'')).trim();
    document.getElementById('reqName').textContent = full || 'Unknown';
    document.getElementById('reqUsername').textContent = '@' + (r.username||'');
    document.getElementById('reqDoc').textContent = r.doc_type || 'N/A';
    const d = new Date(r.created_at.replace(' ', 'T'));
    document.getElementById('reqDate').textContent = d.toLocaleString();
    const sid = parseInt(r.request_status_id, 10);
    let badge = 'secondary', label = r.status_name || 'Unknown';
    if (sid === 1) badge = 'warning text-dark';
    else if (sid === 2) badge = 'info';
    else if (sid === 3) badge = 'danger';
    else if (sid === 4) badge = 'success';
    document.getElementById('reqStatus').innerHTML = '<span class="badge bg-' + badge + ' fs-6">' + label + '</span>';
    document.getElementById('reqOpenTicket').href = '<?php echo WEB_ROOT; ?>/index.php?nav=request-ticket&id=' + r.id;
}
</script>

<?php require_once __DIR__ . '/../public/footer.php'; ?>

