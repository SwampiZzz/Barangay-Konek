<?php
require_once __DIR__ . '/../config.php';
require_login();
require_role([ROLE_SUPERADMIN]);

$page_title = 'Admin Management';
$errors = [];
$success_message = '';

// Get database connection
global $conn;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create_admin') {
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $password_confirm = trim($_POST['password_confirm'] ?? '');
            $first_name = trim($_POST['first_name'] ?? '');
            $middle_name = trim($_POST['middle_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $contact_number = trim($_POST['contact_number'] ?? '');
            $barangay_id = intval($_POST['barangay_id'] ?? 0);
            
            // Validation
            if (empty($username) || strlen($username) < 3) {
                throw new Exception('Username must be at least 3 characters long.');
            }
            if (empty($password) || strlen($password) < 6) {
                throw new Exception('Password must be at least 6 characters long.');
            }
            if ($password !== $password_confirm) {
                throw new Exception('Passwords do not match.');
            }
            if (empty($first_name)) {
                throw new Exception('First name is required.');
            }
            if (empty($last_name)) {
                throw new Exception('Last name is required.');
            }
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format.');
            }
            if ($barangay_id === 0) {
                throw new Exception('Please select a barangay.');
            }
            
            // Check if username already exists
            $check_stmt = $conn->prepare('SELECT id FROM users WHERE username = ?');
            $check_stmt->bind_param('s', $username);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                throw new Exception('Username already exists.');
            }
            $check_stmt->close();
            
            // Check if email already exists (if provided)
            if (!empty($email)) {
                $email_check = $conn->prepare('SELECT id FROM profile WHERE email = ?');
                $email_check->bind_param('s', $email);
                $email_check->execute();
                if ($email_check->get_result()->num_rows > 0) {
                    throw new Exception('Email already exists.');
                }
                $email_check->close();
            }
            
            // Create user account
            $conn->begin_transaction();
            
            $password_hash = hash('sha256', $password);
            $usertype_id = ROLE_ADMIN; // 2
            
            $insert_user = $conn->prepare('INSERT INTO users (username, password_hash, usertype_id) VALUES (?, ?, ?)');
            $insert_user->bind_param('ssi', $username, $password_hash, $usertype_id);
            $insert_user->execute();
            $new_user_id = $conn->insert_id;
            $insert_user->close();
            
            // Create profile
            $insert_profile = $conn->prepare('INSERT INTO profile (first_name, middle_name, last_name, email, contact_number, user_id, barangay_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $insert_profile->bind_param('sssssii', $first_name, $middle_name, $last_name, $email, $contact_number, $new_user_id, $barangay_id);
            $insert_profile->execute();
            $insert_profile->close();
            
            // Log activity
            activity_log(current_user_id(), 'Created admin account for ' . $username, 'users', $new_user_id);
            
            $conn->commit();
            flash_set('Admin account created successfully.', 'success');
            header('Location: index.php?nav=admin-management');
            exit;
            
        } elseif ($action === 'delete_admin') {
            $admin_id = intval($_POST['admin_id'] ?? 0);
            
            if ($admin_id === 0) {
                throw new Exception('Invalid admin ID.');
            }
            
            // Prevent deleting yourself
            if ($admin_id === current_user_id()) {
                throw new Exception('You cannot delete your own account.');
            }
            
            $conn->begin_transaction();
            
            // Soft delete user
            $delete_stmt = $conn->prepare('UPDATE users SET deleted_at = NOW() WHERE id = ? AND usertype_id = ?');
            $usertype = ROLE_ADMIN;
            $delete_stmt->bind_param('ii', $admin_id, $usertype);
            $delete_stmt->execute();
            $delete_stmt->close();
            
            // Log activity
            activity_log(current_user_id(), 'Deleted admin account (ID: ' . $admin_id . ')', 'users', $admin_id);
            
            $conn->commit();
            flash_set('Admin account deleted successfully.', 'success');
            header('Location: index.php?nav=admin-management');
            exit;
        }
    } catch (Exception $e) {
        if ($conn->connect_error === null) {
            $conn->rollback();
        }
        $errors[] = $e->getMessage();
    }
}

// Get all admin users
$admins = [];
$query = '
    SELECT 
        u.id, u.username, u.created_at, u.updated_at,
        p.first_name, p.last_name, p.email, p.contact_number,
        b.name as barangay_name
    FROM users u
    LEFT JOIN profile p ON u.id = p.user_id
    LEFT JOIN barangay b ON p.barangay_id = b.id
    WHERE u.usertype_id = ? AND u.deleted_at IS NULL
    ORDER BY u.created_at DESC
';
$stmt = $conn->prepare($query);
$usertype = ROLE_ADMIN;
$stmt->bind_param('i', $usertype);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $admins[] = $row;
}
$stmt->close();

// Get provinces, cities, and barangays for cascading dropdowns
$provinces = [];
$province_query = 'SELECT id, name FROM province ORDER BY name ASC';
$prov_res = $conn->query($province_query);
if ($prov_res) {
    while ($prov_row = $prov_res->fetch_assoc()) {
        $provinces[] = $prov_row;
    }
}

$cities = [];
$city_query = 'SELECT id, name, province_id FROM city ORDER BY name ASC';
$city_res = $conn->query($city_query);
if ($city_res) {
    while ($city_row = $city_res->fetch_assoc()) {
        $cities[] = $city_row;
    }
}

$barangays = [];
$barangay_query = 'SELECT id, name, city_id FROM barangay WHERE deleted_at IS NULL ORDER BY name ASC';
$bar_res = $conn->query($barangay_query);
if ($bar_res) {
    while ($bar_row = $bar_res->fetch_assoc()) {
        $barangays[] = $bar_row;
    }
}

require_once __DIR__ . '/../public/header.php';

// Display flash messages
$flash = flash_get();
?>

<div class="container my-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1" style="color: #2c3e50;">
                <i class="fas fa-user-shield me-3" style="color: #0d6efd;"></i>Admin Management
            </h2>
            <p class="text-muted mb-0">Manage superadmin accounts and permissions</p>
        </div>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#createAdminModal">
            <i class="fas fa-user-plus me-2"></i>Create Admin Account
        </button>
    </div>

    <!-- Flash Messages -->
    <?php if ($flash && !empty($flash['message'])): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show mb-4 shadow-sm">
            <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
            <?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Error Messages -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4 shadow-sm">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <?php if (count($errors) === 1): ?>
                <?php echo htmlspecialchars($errors[0]); ?>
            <?php else: ?>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Admin List Card -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-bottom py-3">
            <h5 class="mb-0 fw-bold" style="color: #2c3e50;">
                <i class="fas fa-users me-2" style="color: #0d6efd;"></i>
                Admin Accounts (<?php echo count($admins); ?>)
            </h5>
        </div>
        <div class="card-body p-0">
            <?php if (count($admins) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr style="color: #2c3e50;">
                                <th class="py-3 px-4"><i class="fas fa-hashtag me-2" style="color: #0d6efd;"></i>ID</th>
                                <th class="py-3 px-4"><i class="fas fa-user me-2" style="color: #0d6efd;"></i>Username</th>
                                <th class="py-3 px-4"><i class="fas fa-id-card me-2" style="color: #0d6efd;"></i>Name</th>
                                <th class="py-3 px-4"><i class="fas fa-envelope me-2" style="color: #0d6efd;"></i>Email</th>
                                <th class="py-3 px-4"><i class="fas fa-map-marker-alt me-2" style="color: #0d6efd;"></i>Barangay</th>
                                <th class="py-3 px-4"><i class="fas fa-calendar-alt me-2" style="color: #0d6efd;"></i>Created</th>
                                <th class="py-3 px-4 text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admins as $admin): ?>
                                <tr style="border-bottom: 1px solid #e9ecef;">
                                    <td class="py-3 px-4">
                                        <span class="badge bg-light text-dark"><?php echo $admin['id']; ?></span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <code style="background: #f0f0f0; padding: 0.25rem 0.5rem; border-radius: 4px;"><?php echo htmlspecialchars($admin['username']); ?></code>
                                    </td>
                                    <td class="py-3 px-4" style="font-weight: 500; color: #2c3e50;">
                                        <?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <?php if (!empty($admin['email'])): ?>
                                            <a href="mailto:<?php echo htmlspecialchars($admin['email']); ?>" style="color: #0d6efd; text-decoration: none;">
                                                <?php echo htmlspecialchars($admin['email']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted"><em>Not set</em></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <?php if (!empty($admin['barangay_name'])): ?>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($admin['barangay_name']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted"><em>Not assigned</em></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-muted" style="font-size: 0.9rem;">
                                        <?php echo date('M j, Y', strtotime($admin['created_at'])); ?>
                                    </td>
                                    <td class="py-3 px-4 text-end">
                                        <div class="btn-group" role="group">
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this admin account? This action cannot be undone.');">
                                                <input type="hidden" name="action" value="delete_admin">
                                                <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete admin">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-user-shield fa-3x text-muted mb-3 opacity-25"></i>
                    <p class="text-muted mb-0">No admin accounts found. Create one to get started.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Create Admin Modal -->
<div class="modal fade" id="createAdminModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header bg-light border-bottom">
                <h5 class="modal-title fw-bold" style="color: #2c3e50;">
                    <i class="fas fa-user-plus me-2" style="color: #0d6efd;"></i>Create Admin Account
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="createAdminForm" onsubmit="return validateForm();">
                <input type="hidden" name="action" value="create_admin">
                <div class="modal-body">
                    <!-- Alert for form errors -->
                    <div id="formAlert" class="alert alert-dismissible fade show d-none" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <span id="alertMessage"></span>
                        <button type="button" class="btn-close" onclick="document.getElementById('formAlert').classList.add('d-none')"></button>
                    </div>

                    <h6 class="mb-3" style="color: #2c3e50; font-weight: 600;">
                        <i class="fas fa-user me-2" style="color: #0d6efd;"></i>Personal Information
                    </h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="first_name" required maxlength="255" placeholder="Juan">
                            <small class="text-muted d-block mt-1">Required field</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" class="form-control" name="middle_name" maxlength="255" placeholder="Cruz">
                            <small class="text-muted d-block mt-1">Optional</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="last_name" required maxlength="255" placeholder="Dela Cruz">
                            <small class="text-muted d-block mt-1">Required field</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" required maxlength="255" placeholder="admin@example.com">
                            <small class="text-muted d-block mt-1">Valid email required</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" name="contact_number" maxlength="25" placeholder="09XX-XXX-XXXX">
                            <small class="text-muted d-block mt-1">Optional</small>
                        </div>
                    </div>

                    <hr>
                    <h6 class="mb-3 mt-4" style="color: #2c3e50; font-weight: 600;">
                        <i class="fas fa-map-location-dot me-2" style="color: #0d6efd;"></i>Barangay Location
                    </h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Province <span class="text-danger">*</span></label>
                            <select class="form-select" id="adminProvinceSelect" name="province_id" required>
                                <option value="">-- Select Province --</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">City <span class="text-danger">*</span></label>
                            <select class="form-select" id="adminCitySelect" name="city_id" required>
                                <option value="">-- Select City --</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Barangay <span class="text-danger">*</span></label>
                            <select class="form-select" id="adminBarangaySelect" name="barangay_id" required>
                                <option value="">-- Select Barangay --</option>
                            </select>
                            <small class="text-muted d-block mt-1">Admin's jurisdiction</small>
                        </div>
                    </div>

                    <hr>
                    <h6 class="mb-3 mt-4" style="color: #2c3e50; font-weight: 600;">
                        <i class="fas fa-lock me-2" style="color: #0d6efd;"></i>Account Credentials
                    </h6>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Username (for login) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="username" id="username" required minlength="3" maxlength="100" placeholder="Enter username">
                            <small class="text-muted d-block mt-1">Min 3 characters, unique identifier</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" required minlength="6" placeholder="Enter password">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small class="text-muted d-block mt-1">Min 6 characters</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="6" placeholder="Re-enter password">
                            <small class="text-muted d-block mt-1" id="passwordMatch">Passwords will be checked on submit</small>
                        </div>
                    </div>

                    <div class="alert alert-info small mb-0" style="background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 6px;">
                        <i class="fas fa-info-circle me-2" style="color: #0d6efd;"></i>
                        <strong>What's included:</strong> The new admin account will have full access to manage their assigned barangay's operations, users, requests, and announcements.
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold" id="submitBtn">
                        <i class="fas fa-save me-2"></i>Create Admin
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Admin Modal -->
<div class="modal fade" id="editAdminModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-user-edit me-2" style="color: #ffc107;"></i>Edit Admin Account
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-tools me-2"></i>
                    <strong>Coming Soon:</strong> Edit functionality will allow you to update admin details. For now, delete and recreate the account if changes are needed.
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Create Admin Modal - Cascading Location Dropdowns & Form Validation
document.addEventListener('DOMContentLoaded', function() {
    const createAdminModalEl = document.getElementById('createAdminModal');
    
    if (!createAdminModalEl) return;
    
    // Load provinces when modal opens
    createAdminModalEl.addEventListener('show.bs.modal', function() {
        const provinceSelect = document.getElementById('adminProvinceSelect');
        
        // Only load provinces once (on first modal open)
        if (provinceSelect && provinceSelect.options.length === 1) {
            loadProvinces(provinceSelect);
        }
    });

    // Province change: load cities
    document.getElementById('adminProvinceSelect').addEventListener('change', function() {
        const provinceId = this.value;
        const citySelect = document.getElementById('adminCitySelect');
        const barangaySelect = document.getElementById('adminBarangaySelect');
        
        // Reset dependent dropdowns
        resetDropdown(citySelect, '-- Select City --');
        resetDropdown(barangaySelect, '-- Select Barangay --');
        
        if (provinceId) {
            loadCities(provinceId, citySelect);
        }
    });

    // City change: load barangays
    document.getElementById('adminCitySelect').addEventListener('change', function() {
        const cityId = this.value;
        const barangaySelect = document.getElementById('adminBarangaySelect');
        
        resetDropdown(barangaySelect, '-- Select Barangay --');
        
        if (cityId) {
            loadBarangays(cityId, barangaySelect);
        }
    });
    
    // Real-time password validation
    const passwordField = document.getElementById('password');
    const passwordConfirmField = document.getElementById('password_confirm');
    const passwordMatch = document.getElementById('passwordMatch');
    
    if (passwordConfirmField) {
        passwordConfirmField.addEventListener('input', function() {
            validatePasswordMatch(passwordField, this, passwordMatch);
        });
        
        passwordField.addEventListener('input', function() {
            if (passwordConfirmField.value) {
                validatePasswordMatch(this, passwordConfirmField, passwordMatch);
            }
        });
    }
    
    // Reset form when modal is hidden
    createAdminModalEl.addEventListener('hidden.bs.modal', function() {
        resetCreateAdminForm();
    });
});

// Load provinces from API
function loadProvinces(selectElement) {
    setDropdownLoading(selectElement, true);
    
    fetch('index.php?nav=register-api&action=get_provinces', {
        method: 'GET',
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.json();
    })
    .then(data => {
        if (data.success && data.provinces) {
            populateDropdown(selectElement, data.provinces, '-- Select Province --');
        } else {
            throw new Error(data.message || 'Failed to load provinces');
        }
    })
    .catch(error => {
        console.error('Error loading provinces:', error);
        showAlert('Failed to load provinces. Please refresh and try again.', 'warning');
        resetDropdown(selectElement, '-- Select Province --');
    })
    .finally(() => {
        setDropdownLoading(selectElement, false);
    });
}

// Load cities from API
function loadCities(provinceId, selectElement) {
    setDropdownLoading(selectElement, true);
    
    fetch(`index.php?nav=register-api&action=get_cities&province_id=${provinceId}`, {
        method: 'GET',
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.json();
    })
    .then(data => {
        if (data.success && data.cities) {
            if (data.cities.length === 0) {
                showAlert('No cities found for the selected province.', 'info');
            }
            populateDropdown(selectElement, data.cities, '-- Select City --');
        } else {
            throw new Error(data.message || 'Failed to load cities');
        }
    })
    .catch(error => {
        console.error('Error loading cities:', error);
        showAlert('Failed to load cities. Please try selecting a different province.', 'warning');
        resetDropdown(selectElement, '-- Select City --');
    })
    .finally(() => {
        setDropdownLoading(selectElement, false);
    });
}

// Load barangays from API (all barangays, not just those with admins)
function loadBarangays(cityId, selectElement) {
    setDropdownLoading(selectElement, true);
    
    fetch(`index.php?nav=register-api&action=get_barangays&city_id=${cityId}&show_all=true`, {
        method: 'GET',
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.json();
    })
    .then(data => {
        if (data.success && data.barangays) {
            if (data.barangays.length === 0) {
                showAlert('No barangays found for the selected city.', 'info');
            }
            populateDropdown(selectElement, data.barangays, '-- Select Barangay --');
        } else {
            throw new Error(data.message || 'Failed to load barangays');
        }
    })
    .catch(error => {
        console.error('Error loading barangays:', error);
        showAlert('Failed to load barangays. Please try selecting a different city.', 'warning');
        resetDropdown(selectElement, '-- Select Barangay --');
    })
    .finally(() => {
        setDropdownLoading(selectElement, false);
    });
}

// Helper: Populate dropdown with options
function populateDropdown(selectElement, items, placeholder) {
    selectElement.innerHTML = `<option value="">${placeholder}</option>`;
    items.forEach(item => {
        const option = document.createElement('option');
        option.value = item.id;
        option.textContent = item.name;
        selectElement.appendChild(option);
    });
}

// Helper: Reset dropdown to initial state
function resetDropdown(selectElement, placeholder) {
    selectElement.innerHTML = `<option value="">${placeholder}</option>`;
    selectElement.disabled = false;
}

// Helper: Set dropdown loading state
function setDropdownLoading(selectElement, isLoading) {
    if (isLoading) {
        selectElement.disabled = true;
        selectElement.innerHTML = `<option value="">Loading...</option>`;
    } else {
        selectElement.disabled = false;
    }
}

// Helper: Validate password match
function validatePasswordMatch(passwordField, confirmField, matchElement) {
    const password = passwordField.value;
    const confirm = confirmField.value;
    
    if (!password || !confirm) {
        matchElement.textContent = 'Passwords will be checked on submit';
        matchElement.style.color = '#6c757d';
        return;
    }
    
    if (password === confirm) {
        matchElement.textContent = '✓ Passwords match';
        matchElement.style.color = '#198754';
    } else {
        matchElement.textContent = '✗ Passwords do not match';
        matchElement.style.color = '#dc3545';
    }
}

// Helper: Reset create admin form
function resetCreateAdminForm() {
    const form = document.getElementById('createAdminForm');
    const formAlert = document.getElementById('formAlert');
    const submitBtn = document.getElementById('submitBtn');
    const passwordMatch = document.getElementById('passwordMatch');
    
    form.reset();
    formAlert.classList.add('d-none');
    submitBtn.disabled = false;
    submitBtn.innerHTML = '<i class="fas fa-save me-2"></i>Create Admin';
    
    // Reset location dropdowns (keep provinces loaded)
    document.getElementById('adminCitySelect').innerHTML = '<option value="">-- Select City --</option>';
    document.getElementById('adminBarangaySelect').innerHTML = '<option value="">-- Select Barangay --</option>';
    
    passwordMatch.textContent = 'Passwords will be checked on submit';
    passwordMatch.style.color = '#6c757d';
}

// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = event.target.closest('button').querySelector('i');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Show alert message in modal
function showAlert(message, type = 'danger') {
    const alertDiv = document.getElementById('formAlert');
    const messageSpan = document.getElementById('alertMessage');
    
    if (!alertDiv || !messageSpan) return;
    
    alertDiv.classList.remove('alert-danger', 'alert-warning', 'alert-success', 'alert-info', 'd-none');
    alertDiv.classList.add('alert-' + type);
    messageSpan.textContent = message;
    
    // Scroll to alert
    alertDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// Form validation
function validateForm() {
    try {
        const form = document.getElementById('createAdminForm');
        const username = form.querySelector('input[name="username"]').value.trim();
        const firstName = form.querySelector('input[name="first_name"]').value.trim();
        const lastName = form.querySelector('input[name="last_name"]').value.trim();
        const email = form.querySelector('input[name="email"]').value.trim();
        const password = document.getElementById('password').value;
        const passwordConfirm = document.getElementById('password_confirm').value;
        const provinceSelect = document.getElementById('adminProvinceSelect');
        const citySelect = document.getElementById('adminCitySelect');
        const barangaySelect = document.getElementById('adminBarangaySelect');
        
        // Personal information validation
        if (!firstName || !lastName) {
            showAlert('First name and last name are required.', 'warning');
            return false;
        }
        
        if (firstName.length < 2 || lastName.length < 2) {
            showAlert('First name and last name must be at least 2 characters long.', 'warning');
            return false;
        }
        
        // Email validation
        if (!email) {
            showAlert('Email address is required.', 'warning');
            return false;
        }
        
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showAlert('Please enter a valid email address.', 'warning');
            return false;
        }
        
        // Location validation
        if (!provinceSelect || !provinceSelect.value || provinceSelect.value === '0') {
            showAlert('Please select a province.', 'warning');
            return false;
        }
        
        if (!citySelect || !citySelect.value || citySelect.value === '0') {
            showAlert('Please select a city.', 'warning');
            return false;
        }
        
        if (!barangaySelect || !barangaySelect.value || barangaySelect.value === '0') {
            showAlert('Please select a barangay.', 'warning');
            return false;
        }
        
        // Username validation
        if (!username || username.length < 3) {
            showAlert('Username must be at least 3 characters long.', 'warning');
            return false;
        }
        
        if (!/^[a-zA-Z0-9_.-]+$/.test(username)) {
            showAlert('Username can only contain letters, numbers, underscores, dots, and hyphens.', 'warning');
            return false;
        }
        
        // Password validation
        if (!password || password.length < 6) {
            showAlert('Password must be at least 6 characters long.', 'warning');
            return false;
        }
        
        if (password !== passwordConfirm) {
            showAlert('Passwords do not match. Please check and try again.', 'warning');
            return false;
        }
        
        // Show loading state
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating account...';
        
        return true;
        
    } catch (error) {
        console.error('Validation error:', error);
        showAlert('An error occurred during validation. Please try again.', 'danger');
        return false;
    }
}
</script>

<style>
.table tbody tr:hover {
    background-color: #f8f9fa;
}

.btn-group .btn {
    border-color: #dee2e6;
}

.btn-group .btn:hover {
    background-color: #f0f0f0;
}

.modal-content {
    border: 1px solid #e9ecef;
}
</style>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
