<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware/auth.php';

require_login();
require_role([ROLE_ADMIN, ROLE_SUPERADMIN]);

$pageTitle = 'Manage Document Types';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        // Basic validations
        $errors = [];
        if ($name === '') {
            $errors[] = 'Name is required.';
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = 'Name must be between 2 and 100 characters.';
        }
        if ($description !== '' && mb_strlen($description) > 255) {
            $errors[] = 'Description cannot exceed 255 characters.';
        }

        $requirements = $_POST['requirements'] ?? [];
        // Enforce at least one valid requirement
        $valid_req_types = ['document_upload', 'text_input'];
        $valid_field_types = ['text','email','number','textarea','date'];
        $clean_requirements = [];
        foreach ($requirements as $idx => $req) {
            $req_type = trim($req['type'] ?? '');
            $req_label = trim($req['label'] ?? '');
            $req_desc = trim($req['description'] ?? '');
            $field_type = trim($req['field_type'] ?? 'text');
            $is_required = isset($req['required']) ? 1 : 0;

            if ($req_label === '' || $req_type === '') { continue; }
            if (!in_array($req_type, $valid_req_types, true)) { $errors[] = 'Invalid requirement type.'; continue; }
            if ($req_type === 'text_input' && !in_array($field_type, $valid_field_types, true)) { $errors[] = 'Invalid field type for text input.'; continue; }
            if (mb_strlen($req_label) > 100) { $errors[] = 'Requirement label cannot exceed 100 characters.'; continue; }
            if ($req_desc !== '' && mb_strlen($req_desc) > 255) { $errors[] = 'Requirement description cannot exceed 255 characters.'; continue; }

            $clean_requirements[] = [
                'type' => $req_type,
                'label' => $req_label,
                'description' => $req_desc,
                'field_type' => $req_type === 'text_input' ? $field_type : 'text',
                'is_required' => $is_required,
                'sort_order' => $idx,
            ];
        }
        if (count($clean_requirements) === 0) {
            $errors[] = 'At least one requirement is required.';
        }

        if (!empty($errors)) {
            flash_set(implode(' ', $errors), 'error');
            header('Location: ' . WEB_ROOT . '/index.php?nav=manage-document-types');
            exit;
        }

        if ($name !== '') {
            global $conn;
            $stmt = $conn->prepare('INSERT INTO document_type (name, description) VALUES (?, ?)');
            if ($stmt) {
                $stmt->bind_param('ss', $name, $description);
                if ($stmt->execute()) {
                    $doc_type_id = $conn->insert_id;
                    // Add validated requirements
                    foreach ($clean_requirements as $req) {
                        db_query(
                            'INSERT INTO document_requirement (document_type_id, requirement_type, label, description, field_type, is_required, form_id, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                            'issssiii',
                            [$doc_type_id, $req['type'], $req['label'], $req['description'], $req['field_type'], $req['is_required'], NULL, $req['sort_order']]
                        );
                    }
                    
                    flash_set('Document type added successfully.', 'success');
                } else {
                    flash_set('Failed to add document type.', 'error');
                }
                $stmt->close();
            }
        }
        header('Location: ' . WEB_ROOT . '/index.php?nav=manage-document-types');
        exit;
        
    } else if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if ($id > 0) {
            // Basic validations
            $errors = [];
            if ($name === '') {
                $errors[] = 'Name is required.';
            } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
                $errors[] = 'Name must be between 2 and 100 characters.';
            }
            if ($description !== '' && mb_strlen($description) > 255) {
                $errors[] = 'Description cannot exceed 255 characters.';
            }

            $requirements = $_POST['requirements'] ?? [];
            $valid_req_types = ['document_upload', 'text_input'];
            $valid_field_types = ['text','email','number','textarea','date'];
            $clean_requirements = [];
            foreach ($requirements as $idx => $req) {
                $req_type = trim($req['type'] ?? '');
                $req_label = trim($req['label'] ?? '');
                $req_desc = trim($req['description'] ?? '');
                $field_type = trim($req['field_type'] ?? 'text');
                $is_required = isset($req['required']) ? 1 : 0;

                if ($req_label === '' || $req_type === '') { continue; }
                if (!in_array($req_type, $valid_req_types, true)) { $errors[] = 'Invalid requirement type.'; continue; }
                if ($req_type === 'text_input' && !in_array($field_type, $valid_field_types, true)) { $errors[] = 'Invalid field type for text input.'; continue; }
                if (mb_strlen($req_label) > 100) { $errors[] = 'Requirement label cannot exceed 100 characters.'; continue; }
                if ($req_desc !== '' && mb_strlen($req_desc) > 255) { $errors[] = 'Requirement description cannot exceed 255 characters.'; continue; }

                $clean_requirements[] = [
                    'type' => $req_type,
                    'label' => $req_label,
                    'description' => $req_desc,
                    'field_type' => $req_type === 'text_input' ? $field_type : 'text',
                    'is_required' => $is_required,
                    'sort_order' => $idx,
                ];
            }
            if (count($clean_requirements) === 0) {
                $errors[] = 'At least one requirement is required.';
            }
            if (!empty($errors)) {
                flash_set(implode(' ', $errors), 'error');
                header('Location: ' . WEB_ROOT . '/index.php?nav=manage-document-types');
                exit;
            }

            global $conn;
            $stmt = $conn->prepare('UPDATE document_type SET name = ?, description = ? WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('ssi', $name, $description, $id);
                if ($stmt->execute()) {
                    // Delete old requirements
                    db_query('DELETE FROM document_requirement WHERE document_type_id = ?', 'i', [$id]);
                    
                    // Add validated requirements
                    foreach ($clean_requirements as $req) {
                        db_query(
                            'INSERT INTO document_requirement (document_type_id, requirement_type, label, description, field_type, is_required, form_id, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                            'issssiii',
                            [$id, $req['type'], $req['label'], $req['description'], $req['field_type'], $req['is_required'], NULL, $req['sort_order']]
                        );
                    }
                    
                    flash_set('Document type updated successfully.', 'success');
                } else {
                    flash_set('Failed to update document type.', 'error');
                }
                $stmt->close();
            }
        }
        header('Location: ' . WEB_ROOT . '/index.php?nav=manage-document-types');
        exit;
        
    } else if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            db_query('DELETE FROM document_type WHERE id = ?', 'i', [$id]);
            flash_set('Document type deleted.', 'success');
        }
        header('Location: ' . WEB_ROOT . '/index.php?nav=manage-document-types');
        exit;
    }
}

// Forms are no longer used as a requirement type

// Fetch document types with search, filter, and sort
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';

$doc_types = [];
$res = db_query('SELECT id, name, description FROM document_type ORDER BY name');

if ($res) {
    while ($row = $res->fetch_assoc()) {
        // Fetch requirements for this document type
        $requirements = [];
        $reqs_res = db_query(
            'SELECT id, requirement_type, label, description, field_type, is_required, form_id FROM document_requirement WHERE document_type_id = ? ORDER BY sort_order',
            'i',
            [$row['id']]
        );
        if ($reqs_res) {
            while ($req = $reqs_res->fetch_assoc()) {
                $requirements[] = $req;
            }
        }
        $row['requirements'] = $requirements;
        $doc_types[] = $row;
    }
}

// Apply search filter
if (!empty($search)) {
    $search_lower = strtolower($search);
    $doc_types = array_filter($doc_types, function($item) use ($search_lower) {
        return strpos(strtolower($item['name']), $search_lower) !== false ||
               strpos(strtolower($item['description']), $search_lower) !== false;
    });
}

// Apply requirement count filter
if ($filter !== 'all') {
    $doc_types = array_filter($doc_types, function($item) use ($filter) {
        $count = count($item['requirements']);
        if ($filter === 'no-req') return $count === 0;
        if ($filter === 'few-req') return $count > 0 && $count <= 3;
        if ($filter === 'many-req') return $count > 3;
        return true;
    });
}

// Apply sorting
switch ($sort) {
    case 'name_desc':
        usort($doc_types, function($a, $b) { return strcmp($b['name'], $a['name']); });
        break;
    case 'reqs':
        usort($doc_types, function($a, $b) {
            return count($b['requirements']) - count($a['requirements']);
        });
        break;
    case 'name':
    default:
        usort($doc_types, function($a, $b) { return strcmp($a['name'], $b['name']); });
        break;
}

require_once __DIR__ . '/../public/header.php';
?>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-file-alt text-primary"></i> Document Types</h2>
            <p class="text-muted small mb-0">Manage document types and their customizable requirements</p>
        </div>
        <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus me-2"></i> Add Document Type
        </button>
    </div>

    <!-- Flash Messages -->
    <?php 
    $flash = flash_get();
    if (!empty($flash['message'])): 
    ?>
        <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : ($flash['type'] === 'success' ? 'success' : 'info'); ?> alert-dismissible fade show" role="alert" style="border-left: 4px solid; margin-bottom: 1.5rem;">
            <i class="fas fa-<?php echo $flash['type'] === 'error' ? 'exclamation-circle' : 'check-circle'; ?> me-2"></i>
            <?php echo e($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Search, Filter, and Sort Controls -->
    <div class="card border-0 shadow-sm p-4 mb-4" style="background-color: #f8f9fa;">
        <div class="row g-3 align-items-end">
            <!-- Search -->
            <div class="col-12 col-md-5">
                <label class="form-label fw-600 mb-2"><i class="fas fa-search me-2 text-primary"></i>Search Document Types</label>
                <form method="GET" id="searchForm" class="d-flex gap-2">
                    <input type="text" class="form-control" name="search" id="searchInput" placeholder="Search by name or description..." value="<?php echo htmlspecialchars($search); ?>">
                    <?php if (!empty($search)): ?>
                        <a href="<?php echo WEB_ROOT; ?>/index.php?nav=manage-document-types" class="btn btn-outline-secondary" title="Clear search">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                    <input type="hidden" name="nav" value="manage-document-types">
                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                </form>
            </div>

            <!-- Filter -->
            <div class="col-12 col-md-3">
                <label class="form-label fw-600 mb-2"><i class="fas fa-funnel me-2 text-primary"></i>Filter</label>
                <select class="form-select" id="filterSelect" onchange="updateSearch()">
                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Requirements</option>
                    <option value="no-req" <?php echo $filter === 'no-req' ? 'selected' : ''; ?>>No Requirements</option>
                    <option value="few-req" <?php echo $filter === 'few-req' ? 'selected' : ''; ?>>1-3 Requirements</option>
                    <option value="many-req" <?php echo $filter === 'many-req' ? 'selected' : ''; ?>>4+ Requirements</option>
                </select>
            </div>

            <!-- Sort -->
            <div class="col-12 col-md-4">
                <label class="form-label fw-600 mb-2"><i class="fas fa-sort me-2 text-primary"></i>Sort By</label>
                <select class="form-select" id="sortSelect" onchange="updateSearch()">
                    <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name (A-Z)</option>
                    <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                    <option value="reqs" <?php echo $sort === 'reqs' ? 'selected' : ''; ?>>Most Requirements</option>
                </select>
            </div>
        </div>

        <!-- Results Summary -->
        <div class="mt-3">
            <small class="text-muted">
                <i class="fas fa-info-circle me-1"></i>
                Showing <strong><?php echo count($doc_types); ?></strong> document type<?php echo count($doc_types) !== 1 ? 's' : ''; ?>
                <?php if (!empty($search)): ?> matching "<strong><?php echo htmlspecialchars($search); ?></strong>"<?php endif; ?>
            </small>
        </div>
    </div>

    <div class="card border-0 shadow-sm" style="border-top: 4px solid #0d6efd; overflow: hidden;">
        <table class="table table-hover mb-0 align-middle table-doc-types">
            <thead class="table-light">
                <tr>
                    <th style="width: 20%; font-weight: 700; letter-spacing: 0.5px; padding-left: 1rem;">
                        <i class="fas fa-file-alt text-primary me-2"></i><span>Name</span>
                    </th>
                    <th style="width: 30%; font-weight: 700; letter-spacing: 0.5px;">Description</th>
                    <th style="width: 34%; font-weight: 700; letter-spacing: 0.5px;">Requirements</th>
                    <th style="width: 16%; font-weight: 700; letter-spacing: 0.5px; text-align: center; padding-right: 1rem;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($doc_types)): ?>
                    <tr>
                        <td colspan="4" class="text-center py-6">
                            <div class="text-muted">
                                <i class="fas fa-inbox fa-4x mb-3 d-block opacity-25"></i>
                                <p class="mb-3">No document types yet</p>
                                <a href="#" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
                                    <i class="fas fa-plus me-2"></i>Create One Now
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($doc_types as $dt): ?>
                        <tr class="border-bottom-subtle doc-type-row">
                            <td style="padding-left: 1rem;">
                                <div class="fw-700" style="color: #1f2937; font-size: 0.95rem;"><?php echo e($dt['name']); ?></div>
                                <small class="text-muted d-block mt-1" style="font-size: 0.8rem;">ID: <code style="background: #f3f4f6; padding: 2px 6px; border-radius: 3px;">#<?php echo intval($dt['id']); ?></code></small>
                            </td>
                            <td>
                                <span class="text-muted" style="font-size: 0.95rem; line-height: 1.4;">
                                    <?php echo e($dt['description'] ?? '—'); ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <?php if (!empty($dt['requirements'])): ?>
                                        <?php foreach ($dt['requirements'] as $req): ?>
                                            <span class="badge rounded-pill requirement-badge" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($req['label']); ?>" style="background-color: <?php echo getReqTypeColor($req['requirement_type']); ?>;">
                                                <i class="fas fa-<?php echo getReqTypeIcon($req['requirement_type']); ?> me-1"></i><?php echo htmlspecialchars(strlen($req['label']) > 20 ? substr($req['label'], 0, 17) . '...' : $req['label']); ?>
                                            </span>
                                        <?php endforeach; ?>
                                        <span class="badge bg-secondary text-white rounded-pill req-count-badge" data-bs-toggle="tooltip" title="Total requirements">
                                            <i class="fas fa-layer-group me-1"></i><?php echo count($dt['requirements']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-25 text-secondary rounded-pill">
                                            <i class="fas fa-circle-notch me-1"></i>No requirements
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="text-align: center; padding-right: 1rem;">
                                <div class="btn-group btn-group-sm action-buttons" role="group">
                                                <button class="btn btn-outline-primary btn-edit-doc" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editModal" 
                                                    title="Edit this document type"
                                                    data-id="<?php echo intval($dt['id']); ?>"
                                                    data-name="<?php echo htmlspecialchars($dt['name'], ENT_QUOTES); ?>"
                                                    data-desc="<?php echo htmlspecialchars($dt['description'] ?? '', ENT_QUOTES); ?>"
                                                    data-req="<?php echo htmlspecialchars(base64_encode(json_encode($dt['requirements'])), ENT_QUOTES); ?>">
                                        <i class="fas fa-edit me-1"></i><span class="d-none d-lg-inline">Edit</span>
                                    </button>
                                    <button class="btn btn-outline-danger" 
                                            title="Delete this document type" 
                                            onclick="confirmDelete(<?php echo intval($dt['id']); ?>, '<?php echo e($dt['name']); ?>')">
                                        <i class="fas fa-trash me-1"></i><span class="d-none d-lg-inline">Delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary bg-opacity-10 border-bottom">
                <div>
                    <h5 class="modal-title"><i class="fas fa-plus-circle text-primary me-2"></i>Add New Document Type</h5>
                    <small class="text-muted">Define document types with customizable requirements</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="addForm" novalidate>
                <div class="modal-body">
                    <!-- Error Alert -->
                    <div class="alert alert-danger alert-dismissible fade show d-none" id="addFormError" role="alert" style="border-left: 4px solid #dc3545;">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <span id="addFormErrorText"></span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label fw-600">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="addName" required minlength="2" maxlength="100" placeholder="e.g., Barangay Clearance">
                        <div class="invalid-feedback d-block" id="nameError"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-600">Description</label>
                        <textarea class="form-control" name="description" id="addDesc" maxlength="255" rows="2" placeholder="What is this document type for?" style="resize: none;"></textarea>
                        <small class="text-muted d-block mt-1">Maximum 255 characters</small>
                    </div>
                    
                    <div class="mb-0">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <label class="form-label fw-600 mb-0">Requirements</label>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRequirementRow('#addRequirementsList')">
                                <i class="fas fa-plus me-1"></i> Add Requirement
                            </button>
                        </div>
                        <div id="addRequirementsList" class="border rounded p-3 bg-light" style="max-height: 400px; overflow-y: auto;"></div>
                        <small class="text-muted d-block mt-2">
                            <i class="fas fa-info-circle me-1"></i>Add document uploads or text questions
                        </small>
                    </div>
                </div>
                
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Add Document Type
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning bg-opacity-10 border-bottom" style="border-top: 4px solid #ffc107;">
                <div>
                    <h5 class="modal-title"><i class="fas fa-edit text-warning me-2"></i>Edit Document Type</h5>
                    <small class="text-muted">Update document type and its requirements</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="editForm" novalidate>
                <div class="modal-body">
                    <!-- Error Alert -->
                    <div class="alert alert-danger alert-dismissible fade show d-none" id="editFormError" role="alert" style="border-left: 4px solid #dc3545;">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <span id="editFormErrorText"></span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="editId">
                    
                    <div class="mb-3">
                        <label class="form-label fw-600">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="editName" required minlength="2" maxlength="100">
                        <div class="invalid-feedback d-block" id="editNameError"></div>
                        <small class="text-muted d-block mt-1">2-100 characters required</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-600">Description</label>
                        <textarea class="form-control" name="description" id="editDesc" maxlength="255" rows="2" style="resize: none;"></textarea>
                        <small class="text-muted d-block mt-1">Maximum 255 characters</small>
                    </div>
                    
                    <div class="mb-0">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <label class="form-label fw-600 mb-0">Requirements</label>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRequirementRow('#editRequirementsList')">
                                <i class="fas fa-plus me-1"></i> Add Requirement
                            </button>
                        </div>
                        <div id="editRequirementsList" class="border rounded p-3 bg-light" style="max-height: 400px; overflow-y: auto;"></div>
                        <small class="text-muted d-block mt-2">
                            <i class="fas fa-info-circle me-1"></i>Add document uploads or text questions
                        </small>
                    </div>
                </div>
                
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save me-1"></i> Update Document Type
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Forms are not available as requirement type

// Requirement row template
function requirementRowTemplate(idx, data = {}) {
    const type = (data.requirement_type === 'text_input' || data.requirement_type === 'document_upload') ? data.requirement_type : 'document_upload';
    const label = data.label || '';
    const description = data.description || '';
    const fieldType = data.field_type || 'text';
    const isRequired = data.is_required ? 'checked' : '';
    const formId = data.form_id || '';
    
    return `
    <div class="card mb-2 requirement-row" style="border: 1px solid #e9ecef;">
        <div class="card-body p-3">
            <div class="row g-2 mb-2">
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-600">Requirement Type</label>
                    <select class="form-select form-select-sm requirement-type" name="requirements[${idx}][type]" onchange="updateFieldTypes(this)">
                        <option value="document_upload" ${type === 'document_upload' ? 'selected' : ''}>Document Upload</option>
                        <option value="text_input" ${type === 'text_input' ? 'selected' : ''}>Text Input</option>
                    </select>
                </div>
                <div class="col-12 col-md-8">
                    <label class="form-label small fw-600">Label/Name</label>
                    <input type="text" class="form-control form-control-sm" name="requirements[${idx}][label]" value="${escapeHtml(label)}" placeholder="e.g., Valid ID, Annual Income" maxlength="100">
                </div>
            </div>
            
            <div class="row g-2 mb-2">
                <div class="col-12">
                    <label class="form-label small fw-600">Description</label>
                    <input type="text" class="form-control form-control-sm" name="requirements[${idx}][description]" value="${escapeHtml(description)}" placeholder="Help text for users" maxlength="255">
                </div>
            </div>
            
            <div class="row g-2 mb-2">
                <div class="col-12 col-md-6">
                    <label class="form-label small fw-600">Field Type</label>
                    <select class="form-select form-select-sm field-type-select" name="requirements[${idx}][field_type]" ${type === 'text_input' ? '' : 'disabled'}>
                        <option value="text" ${fieldType === 'text' ? 'selected' : ''}>Text</option>
                        <option value="email" ${fieldType === 'email' ? 'selected' : ''}>Email</option>
                        <option value="number" ${fieldType === 'number' ? 'selected' : ''}>Number</option>
                        <option value="textarea" ${fieldType === 'textarea' ? 'selected' : ''}>Text Area</option>
                        <option value="date" ${fieldType === 'date' ? 'selected' : ''}>Date</option>
                    </select>
                </div>
                
            </div>
            
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="requirements[${idx}][required]" id="req_${idx}" ${isRequired}>
                <label class="form-check-label small" for="req_${idx}">
                    Required field
                </label>
            </div>
            
            <button type="button" class="btn btn-sm btn-outline-danger mt-2" onclick="removeRequirementRow(this)">
                <i class="fas fa-trash me-1"></i>Remove
            </button>
        </div>
    </div>`;
}

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

function addRequirementRow(containerId) {
    const container = document.querySelector(containerId);
    if (!container) return;
    
    const idx = container.querySelectorAll('.requirement-row').length;
    container.insertAdjacentHTML('beforeend', requirementRowTemplate(idx));
    
    // Re-initialize tooltips if using Bootstrap
    if (typeof bootstrap !== 'undefined') {
        const tooltipTriggerList = container.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltipTriggerList.forEach(tooltipTriggerEl => {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
}

function removeRequirementRow(btn) {
    const row = btn.closest('.requirement-row');
    if (row) {
        row.style.opacity = '0';
        row.style.transition = 'opacity 0.2s ease';
        setTimeout(() => row.remove(), 200);
    }
}

function updateFieldTypes(select) {
    const row = select.closest('.requirement-row');
    const type = select.value;
    const fieldTypeSelect = row.querySelector('.field-type-select');
    const formSelect = row.querySelector('.form-select-input');
    
    if (type === 'text_input') {
        fieldTypeSelect.disabled = false;
        if (formSelect) { formSelect.disabled = true; formSelect.value = ''; }
    } else {
        fieldTypeSelect.disabled = true;
        fieldTypeSelect.value = 'text';
        if (formSelect) { formSelect.disabled = true; formSelect.value = ''; }
    }
}

function setEditData(id, name, desc, requirements) {
    try {
        console.debug('setEditData()', { id, name, desc, requirements });
    } catch (e) {}
    document.getElementById('editId').value = id;
    document.getElementById('editName').value = name;
    document.getElementById('editDesc').value = desc;
    
    const list = document.getElementById('editRequirementsList');
    list.innerHTML = '';
    
    if (Array.isArray(requirements) && requirements.length > 0) {
        requirements.forEach((req, idx) => {
            list.insertAdjacentHTML('beforeend', requirementRowTemplate(idx, req));
        });
    } else {
        addRequirementRow('#editRequirementsList');
    }
    
    // Clear validation errors
    document.getElementById('editNameError').textContent = '';
    document.getElementById('editName').classList.remove('is-invalid');
    document.getElementById('editFormError').classList.add('d-none');
}

function confirmDelete(id, name) {
    if (confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Form validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    const nameInput = form.querySelector('input[name="name"]');
    const reqContainer = formId === 'addForm' ? document.querySelector('#addRequirementsList') : document.querySelector('#editRequirementsList');
    
    let nameError = null;
    if (formId === 'addForm') {
        nameError = document.getElementById('nameError');
    } else if (formId === 'editForm') {
        nameError = document.getElementById('editNameError');
    }
    
    if (!nameError) {
        console.error(`Error element not found for form ${formId}`);
        return true;
    }
    
    let isValid = true;
    const nameValue = nameInput.value.trim();
    
    if (!nameValue) {
        nameError.textContent = 'Document type name is required';
        nameInput.classList.add('is-invalid');
        isValid = false;
    } else if (nameValue.length < 2) {
        nameError.textContent = 'Name must be at least 2 characters';
        nameInput.classList.add('is-invalid');
        isValid = false;
    } else if (nameValue.length > 100) {
        nameError.textContent = 'Name cannot exceed 100 characters';
        nameInput.classList.add('is-invalid');
        isValid = false;
    } else {
        nameError.textContent = '';
        nameInput.classList.remove('is-invalid');
    }
    
    // Enforce at least one requirement row with non-empty label and type
    const rows = reqContainer ? reqContainer.querySelectorAll('.requirement-row') : [];
    const hasValidReq = Array.from(rows).some(r => {
        const typeSel = r.querySelector('.requirement-type');
        const labelInput = r.querySelector('input[name*="[label]"]');
        return typeSel && labelInput && typeSel.value && labelInput.value.trim().length > 0;
    });
    if (!hasValidReq) {
        // Show inline info near the container
        const info = document.createElement('div');
        info.className = 'text-danger small mt-2';
        info.textContent = 'Add at least one requirement (document upload or text).';
        const existingInfo = reqContainer ? reqContainer.parentElement.querySelector('.req-error-info') : null;
        if (!existingInfo && reqContainer && reqContainer.parentElement) {
            info.classList.add('req-error-info');
            reqContainer.parentElement.appendChild(info);
        }
        isValid = false;
    } else {
        const existingInfo = reqContainer ? reqContainer.parentElement.querySelector('.req-error-info') : null;
        if (existingInfo) existingInfo.remove();
    }

    // If invalid, show modal-level alert when present
    if (!isValid) {
        const alertId = formId === 'addForm' ? 'addFormError' : 'editFormError';
        const alertTextId = formId === 'addForm' ? 'addFormErrorText' : 'editFormErrorText';
        const errorDiv = document.getElementById(alertId);
        const textEl = document.getElementById(alertTextId);
        if (errorDiv && textEl) {
            textEl.textContent = 'Please fix the errors below and try again.';
            errorDiv.classList.remove('d-none');
            errorDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    return isValid;
}

function updateSearch() {
    const searchForm = document.getElementById('searchForm');
    const filterValue = document.getElementById('filterSelect').value;
    const sortValue = document.getElementById('sortSelect').value;
    
    searchForm.querySelector('input[name="filter"]').value = filterValue;
    searchForm.querySelector('input[name="sort"]').value = sortValue;
    
    searchForm.submit();
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    addRequirementRow('#addRequirementsList');
    
    const addForm = document.getElementById('addForm');
    const editForm = document.getElementById('editForm');
    
    if (addForm) {
        addForm.addEventListener('submit', (e) => {
            e.preventDefault();
            if (validateForm('addForm')) {
                addForm.submit();
            }
        });
        
        addForm.querySelector('input[name="name"]').addEventListener('input', function() {
            document.getElementById('nameError').textContent = '';
            this.classList.remove('is-invalid');
        });
    }
    
    if (editForm) {
        editForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const errorDiv = document.getElementById('editFormError');
            errorDiv.classList.add('d-none');
            
            if (validateForm('editForm')) {
                editForm.submit();
            } else {
                document.getElementById('editFormErrorText').textContent = 'Please fix the errors below and try again.';
                errorDiv.classList.remove('d-none');
                errorDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
        
        editForm.querySelector('input[name="name"]').addEventListener('input', function() {
            document.getElementById('editNameError').textContent = '';
            this.classList.remove('is-invalid');
            const allInvalid = editForm.querySelectorAll('.is-invalid').length === 0;
            if (allInvalid) {
                document.getElementById('editFormError').classList.add('d-none');
            }
        });
    }

    // Bind edit buttons to safely populate modal
    document.querySelectorAll('.btn-edit-doc').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = parseInt(btn.getAttribute('data-id'), 10);
            const name = btn.getAttribute('data-name') || '';
            const desc = btn.getAttribute('data-desc') || '';
            let requirements = [];
            try {
                const encoded = btn.getAttribute('data-req') || '';
                if (encoded) {
                    const json = atob(encoded);
                    requirements = JSON.parse(json);
                }
            } catch (e) {
                requirements = [];
            }
            setEditData(id, name, desc, requirements);
        });
    });
});
</script>

<style>
/* Table improvements */
.table-doc-types {
    margin-bottom: 0;
    width: 100%;
    table-layout: fixed;
}

.table-doc-types thead th {
    font-weight: 700;
    letter-spacing: 0.6px;
    text-transform: uppercase;
    font-size: 0.75rem;
    border-bottom: 2px solid #dee2e6;
    vertical-align: middle;
    padding: 1.1rem 0.75rem;
    background-color: #f8f9fa;
    color: #495057;
    overflow: hidden;
    text-overflow: ellipsis;
}

.table-doc-types tbody td {
    padding: 1.1rem 0.75rem;
    vertical-align: middle;
    border-bottom: 1px solid #e9ecef;
    overflow: hidden;
    text-overflow: ellipsis;
}

.table-doc-types tbody tr.doc-type-row {
    transition: background-color 0.2s ease, box-shadow 0.2s ease, transform 0.1s ease;
    border-bottom: 1px solid #e9ecef !important;
}

.table-doc-types tbody tr.doc-type-row:hover {
    background-color: rgba(13, 110, 253, 0.04) !important;
    box-shadow: inset 4px 0 0 rgba(13, 110, 253, 0.15), 0 2px 4px rgba(0, 0, 0, 0.04);
    transform: translateX(2px);
}

.table-doc-types td .fw-700 {
    color: #1f2937;
    display: block;
    margin-bottom: 0.25rem;
    font-weight: 700;
}

.table-doc-types td small.text-muted {
    font-size: 0.75rem;
    color: #6c757d;
}

.requirement-badge {
    white-space: nowrap;
    text-overflow: ellipsis;
    overflow: hidden;
    max-width: 160px;
    font-weight: 500;
    padding: 0.45rem 0.65rem !important;
    transition: all 0.2s ease;
    color: white !important;
}

.requirement-badge:hover {
    transform: translateY(-2px);
}

.req-count-badge {
    font-weight: 600;
    padding: 0.45rem 0.65rem !important;
    background-color: #6c757d !important;
    font-size: 0.85rem;
}

.table-doc-types td .text-muted {
    color: #6c757d;
    line-height: 1.5;
}

.action-buttons {
    gap: 0.35rem;
}

.action-buttons .btn {
    padding: 0.4rem 0.65rem;
    border-radius: 0.375rem;
    font-size: 0.85rem;
    transition: all 0.2s ease;
    font-weight: 500;
}

.action-buttons .btn-outline-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 4px rgba(13, 110, 253, 0.3);
    background-color: #0d6efd;
}

.action-buttons .btn-outline-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
    background-color: #dc3545;
}

/* Search, Filter, and Sort Controls */
.form-label {
    color: #495057;
}

.form-control,
.form-select {
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.form-control:focus,
.form-select:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.1);
}

.form-control::placeholder {
    color: #adb5bd;
    font-style: italic;
}

/* Alert styling */
.alert {
    border-radius: 0.5rem;
    border: none;
    font-weight: 500;
}

.alert-danger {
    background-color: #f8d7da;
    color: #842029;
}

.alert-success {
    background-color: #d1e7dd;
    color: #0f5132;
}

/* Requirement row styling */
.requirement-row {
    animation: slideIn 0.2s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.form-select-sm,
.form-control-sm {
    font-size: 0.875rem;
}

.card-body input,
.card-body select,
.card-body textarea {
    font-size: 0.875rem;
}
</style>

<?php 
// Helper functions for requirement type styling
function getReqTypeColor($type) {
    $colors = [
        'document_upload' => '#0d6efd',
        'text_input' => '#6f42c1',
    ];
    return $colors[$type] ?? '#6c757d';
}

function getReqTypeIcon($type) {
    $icons = [
        'document_upload' => 'file-upload',
        'text_input' => 'keyboard',
    ];
    return $icons[$type] ?? 'question-circle';
}
?>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
