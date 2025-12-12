<?php
require_once __DIR__ . '/../config.php';
require_login();
require_role([ROLE_STAFF, ROLE_ADMIN]);

$pageTitle = 'Manage Complaints';
$user_id = current_user_id();

// Filters
$q = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all'; // all, open, in_progress, resolved, closed

// Build query
$where = ' WHERE c.deleted_at IS NULL';
if ($q !== '') {
    $safe = addslashes($q);
    $safe = str_replace(['%','_'], ['\\%','\\_'], $safe);
    $where .= " AND (c.title LIKE '%$safe%' OR u.username LIKE '%$safe%' OR c.description LIKE '%$safe%')";
}
if (in_array($filter, ['open','in_progress','resolved','closed'], true)) {
    $map = ['open'=>1,'in_progress'=>2,'resolved'=>3,'closed'=>4];
    $where .= ' AND c.complaint_status_id = ' . $map[$filter];
}

$sql = 'SELECT c.*, cs.name as status_name, u.username FROM complaint c '
     . 'LEFT JOIN complaint_status cs ON c.complaint_status_id = cs.id '
     . 'LEFT JOIN users u ON c.user_id = u.id '
     . $where . ' ORDER BY c.created_at DESC';

$complaints = [];
$res = db_query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) $complaints[] = $row;
}

require_once __DIR__ . '/../public/header.php';
?>

<div class="container my-5">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="mb-2"><i class="fas fa-clipboard-list me-2"></i>Manage Complaints</h2>
            <p class="text-muted mb-0">Track, review, and resolve complaints</p>
        </div>
        <div class="col-md-4">
            <form class="input-group" method="get" action="<?php echo WEB_ROOT; ?>/index.php">
                <input type="hidden" name="nav" value="manage-complaints">
                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search title, @username, or description...">
                <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <?php $base = WEB_ROOT . '/index.php?nav=manage-complaints&q=' . urlencode($q); ?>
            <ul class="nav nav-pills card-header-pills mb-0">
                <li class="nav-item"><a class="nav-link <?php echo $filter==='all'?'active':''; ?>" href="<?php echo $base; ?>&filter=all">All</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $filter==='open'?'active':''; ?>" href="<?php echo $base; ?>&filter=open">Open</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $filter==='in_progress'?'active':''; ?>" href="<?php echo $base; ?>&filter=in_progress">In Progress</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $filter==='resolved'?'active':''; ?>" href="<?php echo $base; ?>&filter=resolved">Resolved</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $filter==='closed'?'active':''; ?>" href="<?php echo $base; ?>&filter=closed">Closed</a></li>
            </ul>
        </div>
        <div class="card-body p-0">
            <?php if (count($complaints) === 0): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted mb-0">No complaints found for this filter.</p>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($complaints as $c): ?>
                        <div class="list-group-item list-group-item-action">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center">
                                        <?php 
                                            $uid = intval($c['user_id'] ?? 0);
                                            $picDir = __DIR__ . '/../storage/app/private/profile_pics/';
                                            $picWeb = WEB_ROOT . '/storage/app/private/profile_pics/';
                                            $picName = 'user_' . $uid . '.jpg';
                                            $picPath = $picDir . $picName;
                                        ?>
                                        <?php if ($uid && file_exists($picPath)): ?>
                                            <img src="<?php echo $picWeb . $picName; ?>" alt="Profile" class="rounded-circle me-3" style="width:40px;height:40px;object-fit:cover;">
                                        <?php else: ?>
                                            <div class="avatar-circle bg-primary text-white me-3" style="width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px;">
                                                <?php echo strtoupper(substr($c['username'] ?? 'U', 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($c['title'] ?? 'Untitled Complaint'); ?></h6>
                                            <small class="text-muted"><i class="fas fa-user me-1"></i>@<?php echo htmlspecialchars($c['username']); ?></small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted d-block"><i class="far fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($c['created_at'])); ?></small>
                                    <small class="text-muted"><i class="far fa-clock me-1"></i><?php echo date('h:i A', strtotime($c['created_at'])); ?></small>
                                </div>
                                <div class="col-md-2 text-center">
                                    <?php $sid = intval($c['complaint_status_id']);
                                        $badge = $sid===1?'warning text-dark':($sid===2?'info':($sid===3?'success':'secondary')); ?>
                                    <span class="badge bg-<?php echo $badge; ?> fs-6"><?php echo htmlspecialchars($c['status_name']); ?></span>
                                </div>
                                <div class="col-md-1 text-end">
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#complaintModal" onclick='loadComplaintData(<?php echo json_encode($c); ?>)'>
                                        <i class="fas fa-eye me-1"></i>View
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Complaint Modal -->
<div class="modal fade" id="complaintModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-flag me-2"></i>Complaint #<span id="cmpId"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <small class="text-muted d-block mb-1">Title</small>
                    <div class="fw-semibold" id="cmpTitle"></div>
                </div>
                <div class="mb-3">
                    <small class="text-muted d-block mb-1">Submitted By</small>
                    <div class="fw-semibold" id="cmpUser"></div>
                </div>
                <div class="mb-3">
                    <small class="text-muted d-block mb-1">Status</small>
                    <div id="cmpStatus"></div>
                </div>
                <div>
                    <small class="text-muted d-block mb-1">Description</small>
                    <div id="cmpDesc" style="white-space: pre-wrap;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function loadComplaintData(c){
    document.getElementById('cmpId').textContent = c.id;
    document.getElementById('cmpTitle').textContent = c.title || 'Untitled Complaint';
    document.getElementById('cmpUser').textContent = '@' + (c.username||'');
    const sid = parseInt(c.complaint_status_id, 10);
    let badge='secondary', label=c.status_name||'Unknown';
    if (sid===1) badge='warning text-dark';
    else if (sid===2) badge='info';
    else if (sid===3) badge='success';
    else if (sid===4) badge='secondary';
    document.getElementById('cmpStatus').innerHTML = '<span class="badge bg-'+badge+' fs-6">'+label+'</span>';
    document.getElementById('cmpDesc').textContent = c.description || 'No description provided.';
}
</script>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
