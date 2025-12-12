<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware/auth.php';

require_login();
require_role([ROLE_STAFF, ROLE_ADMIN, ROLE_SUPERADMIN]);

 $pageTitle = 'Manage Requests';

// Get search/filter params
$q = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all'; // all, pending(1), approved(2), rejected(3), completed(4)
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query
$where = [];
$bind_params = [];
$bind_types = '';

if ($q !== '') {
    $where[] = '(dt.name LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR u.username LIKE ?)';
    $search_term = "%$q%";
    array_push($bind_params, $search_term, $search_term, $search_term, $search_term);
    $bind_types .= 'ssss';
}

if (in_array($filter, ['pending','approved','rejected','completed'], true)) {
    $status_map = ['pending'=>1,'approved'=>2,'rejected'=>3,'completed'=>4];
    $where[] = 'r.request_status_id = ?';
    $bind_params[] = $status_map[$filter];
    $bind_types .= 'i';
}

$where_sql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count total for pagination
$count_sql = "SELECT COUNT(*) as total
    FROM request r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN profile p ON u.id = p.user_id
    LEFT JOIN document_type dt ON r.document_type_id = dt.id
    $where_sql";
$count_stmt = $conn->prepare($count_sql);
if ($count_stmt && !empty($bind_params)) {
    $count_stmt->bind_param($bind_types, ...$bind_params);
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
           p.first_name, p.last_name, u.username
    FROM request r
    LEFT JOIN document_type dt ON r.document_type_id = dt.id
    LEFT JOIN request_status rs ON r.request_status_id = rs.id
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN profile p ON u.id = p.user_id
    $where_sql
    ORDER BY r.created_at DESC
    LIMIT $per_page OFFSET $offset
";

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

// Status counts for pills
$count_query = "SELECT 
    SUM(CASE WHEN request_status_id = 1 THEN 1 ELSE 0 END) AS pending_count,
    SUM(CASE WHEN request_status_id = 2 THEN 1 ELSE 0 END) AS approved_count,
    SUM(CASE WHEN request_status_id = 3 THEN 1 ELSE 0 END) AS rejected_count,
    SUM(CASE WHEN request_status_id = 4 THEN 1 ELSE 0 END) AS completed_count,
    COUNT(*) AS total_count
    FROM request";
$count_res = db_query($count_query);
$counts = $count_res ? $count_res->fetch_assoc() : [
    'pending_count' => 0,
    'approved_count' => 0,
    'rejected_count' => 0,
    'completed_count' => 0,
    'total_count' => 0,
];

require_once __DIR__ . '/../public/header.php';
?>

<div class="container my-5">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="mb-2"><i class="fas fa-tasks me-2"></i>Manage Document Requests</h2>
            <p class="text-muted mb-0">Browse and manage all document requests</p>
        </div>
        <div class="col-md-4">
            <form class="input-group" method="get" action="<?php echo WEB_ROOT; ?>/index.php">
                <input type="hidden" name="nav" value="manage-requests">
                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search name, @username, or document type...">
                <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <ul class="nav nav-pills card-header-pills mb-0">
                <?php $base = WEB_ROOT . '/index.php?nav=manage-requests&q=' . urlencode($q); ?>
                <li class="nav-item"><a class="nav-link <?php echo $filter==='all'?'active':''; ?>" href="<?php echo $base; ?>&filter=all">All <span class="badge <?php echo $filter==='all'?'bg-light text-dark':'bg-secondary'; ?> ms-1"><?php echo $counts['total_count'] ?? 0; ?></span></a></li>
                <li class="nav-item"><a class="nav-link <?php echo $filter==='pending'?'active':''; ?>" href="<?php echo $base; ?>&filter=pending">Pending <span class="badge <?php echo $filter==='pending'?'bg-light text-dark':'bg-warning text-dark'; ?> ms-1"><?php echo $counts['pending_count'] ?? 0; ?></span></a></li>
                <li class="nav-item"><a class="nav-link <?php echo $filter==='approved'?'active':''; ?>" href="<?php echo $base; ?>&filter=approved">Approved <span class="badge <?php echo $filter==='approved'?'bg-light text-dark':'bg-info'; ?> ms-1"><?php echo $counts['approved_count'] ?? 0; ?></span></a></li>
                <li class="nav-item"><a class="nav-link <?php echo $filter==='rejected'?'active':''; ?>" href="<?php echo $base; ?>&filter=rejected">Rejected <span class="badge <?php echo $filter==='rejected'?'bg-light text-dark':'bg-danger'; ?> ms-1"><?php echo $counts['rejected_count'] ?? 0; ?></span></a></li>
                <li class="nav-item"><a class="nav-link <?php echo $filter==='completed'?'active':''; ?>" href="<?php echo $base; ?>&filter=completed">Completed <span class="badge <?php echo $filter==='completed'?'bg-light text-dark':'bg-success'; ?> ms-1"><?php echo $counts['completed_count'] ?? 0; ?></span></a></li>
            </ul>
        </div>
        <div class="card-body p-0">
            <?php if (count($requests) === 0): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted mb-0">No requests found for this filter.</p>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($requests as $r): ?>
                        <div class="list-group-item list-group-item-action">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center">
                                        <?php 
                                            $uid = intval($r['user_id'] ?? 0);
                                            $picDir = __DIR__ . '/../storage/app/private/profile_pics/';
                                            $picWeb = WEB_ROOT . '/storage/app/private/profile_pics/';
                                            $picName = 'user_' . $uid . '.jpg';
                                            $picPath = $picDir . $picName;
                                        ?>
                                        <?php if ($uid && file_exists($picPath)): ?>
                                            <img src="<?php echo $picWeb . $picName; ?>" alt="Profile" class="rounded-circle me-3" style="width:46px;height:46px;object-fit:cover;">
                                        <?php else: ?>
                                            <div class="avatar-circle bg-primary text-white me-3" style="width: 46px; height: 46px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 16px;">
                                                <?php echo strtoupper(substr($r['first_name'] ?? 'U', 0, 1) . substr($r['last_name'] ?? 'N', 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')); ?></h6>
                                            <small class="text-muted"><i class="fas fa-user me-1"></i>@<?php echo htmlspecialchars($r['username']); ?> • <?php echo htmlspecialchars($r['doc_type'] ?? 'Unknown Document'); ?></small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted d-block"><i class="far fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($r['created_at'])); ?></small>
                                    <small class="text-muted"><i class="far fa-clock me-1"></i><?php echo date('h:i A', strtotime($r['created_at'])); ?></small>
                                </div>
                                <div class="col-md-2 text-center">
                                    <?php $sid = intval($r['request_status_id']);
                                    $badge = $sid === 1 ? 'warning text-dark' : ($sid === 2 ? 'info' : ($sid === 3 ? 'danger' : 'success')); ?>
                                    <span class="badge bg-<?php echo $badge; ?> fs-6"><?php echo htmlspecialchars($r['status_name']); ?></span>
                                </div>
                                <div class="col-md-1 text-end">
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#requestModal" onclick='loadRequestData(<?php echo json_encode($r); ?>)'>
                                        <i class="fas fa-eye me-1"></i>View
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
                        $base_url = WEB_ROOT . '/index.php?nav=manage-requests&filter=' . urlencode($filter) . '&q=' . urlencode($q);
                        
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
