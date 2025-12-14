# Document Request Workflow - Implementation Guide

## Current State Analysis
The system has basic workflow but is missing:
1. File upload when staff approves (staff should upload processed document)
2. File upload when admin completes (admin should upload final document)  
3. File replacement logic (delete old file when new one uploaded)
4. Display of requirement submissions in ticket view
5. Proper notifications and activity logging
6. Modern, polished UI

## Required Changes

### 1. Staff Approve Action - Add Document Upload
**Location:** `pages/request-ticket.php` around line 95

**Current Code:**
```php
$stmt = $conn->prepare('UPDATE request SET request_status_id = 2, updated_at = NOW() WHERE id = ?');
```

**New Code Should:**
- Check for uploaded file in `$_FILES['processed_document']`
- Validate file (PDF, JPG, PNG, max 10MB)
- Save file with unique name: `processed_{request_id}_{timestamp}_{uniqid}.{ext}`
- Update request: `document_path = ?` in addition to status change
- On success: Log activity, notify admin AND user
- On error: Show clear error message

### 2. Admin Complete Action - Add Document Upload
**Location:** `pages/request-ticket.php` around line 132

**Current Code:**
```php
$stmt = $conn->prepare('UPDATE request SET request_status_id = 4, updated_at = NOW() WHERE id = ?');
```

**New Code Should:**
- Check for uploaded file in `$_FILES['final_document']`
- Validate file (PDF only, max 10MB)
- DELETE old document_path file if exists (file replacement rule)
- Save new file with unique name: `final_{request_id}_{timestamp}_{uniqid}.pdf`
- Update request with new document_path
- On success: Log activity, notify user document is ready
- On error: Show clear error message

### 3. Admin Reject Action - Delete Processed Document
**Location:** `pages/request-ticket.php` around line 149

**Add Before Update:**
```php
// Delete the processed document uploaded by staff
if (!empty($request['document_path'])) {
    $old_file = __DIR__ . '/../' . $request['document_path'];
    if (file_exists($old_file)) {
        unlink($old_file);
    }
}
```

**Update Statement:**
```php
$stmt = $conn->prepare('UPDATE request SET request_status_id = 1, remarks = ?, claimed_by = NULL, document_path = NULL, updated_at = NOW() WHERE id = ?');
```

### 4. User Resubmit - File Replacement Logic
**Location:** `pages/request-ticket.php` around line 170

**Enhancement Needed:**
- Loop through requirement_submissions
- For each file-type requirement, check if user uploaded replacement in `$_FILES['requirement_{req_id}']`
- If replacement exists:
  - Delete old file from `file_path`
  - Upload new file
  - Update document_requirement_submission with new file_name, file_path, file_type
- For text-type requirements, check `$_POST['requirement_{req_id}']` for updates
- Log all changes

### 5. Display Requirement Submissions
**Location:** `pages/request-ticket.php` - Add new section in HTML

**Query to Add:**
```php
// Fetch requirement submissions
$requirement_submissions = [];
$req_stmt = $conn->prepare('
    SELECT drs.*, dr.label, dr.requirement_type, dr.field_type, dr.is_required
    FROM document_requirement_submission drs
    JOIN document_requirement dr ON drs.requirement_id = dr.id
    WHERE drs.request_id = ?
    ORDER BY dr.sort_order, dr.id
');
```

**HTML to Add:**
```html
<div class="card mb-4">
    <div class="card-header bg-light">
        <h6 class="mb-0"><i class="fas fa-list-check me-2"></i>Submitted Requirements</h6>
    </div>
    <div class="card-body">
        <?php foreach ($requirement_submissions as $req): ?>
            <div class="requirement-item mb-3">
                <label class="fw-600"><?php echo htmlspecialchars($req['label']); ?></label>
                <?php if ($req['submission_type'] === 'file'): ?>
                    <div class="file-display">
                        <i class="fas fa-file-pdf text-danger"></i>
                        <?php echo htmlspecialchars($req['file_name']); ?>
                        <a href="<?php echo WEB_ROOT . '/' . $req['file_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
                    </div>
                <?php else: ?>
                    <div class="text-value"><?php echo htmlspecialchars($req['text_value']); ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
```

### 6. UI Enhancements

**Status Timeline Component:**
```html
<div class="timeline">
    <div class="timeline-item <?php echo $status >= 1 ? 'completed' : ''; ?>">
        <div class="timeline-marker"></div>
        <div class="timeline-content">
            <h6>Submitted</h6>
            <small><?php echo date('M d, Y h:i A', strtotime($request['created_at'])); ?></small>
        </div>
    </div>
    <div class="timeline-item <?php echo $status >= 2 ? 'completed' : ''; ?>">
        <div class="timeline-marker"></div>
        <div class="timeline-content">
            <h6>Staff Approved</h6>
        </div>
    </div>
    <div class="timeline-item <?php echo $status >= 4 ? 'completed' : ''; ?>">
        <div class="timeline-marker"></div>
        <div class="timeline-content">
            <h6>Admin Completed</h6>
        </div>
    </div>
</div>
```

**Action Panels for Each Role:**

Staff Panel (status = 1):
- File upload field for processed document
- Approve button (requires file)
- Reject button with remarks textarea

Admin Panel (status = 2):
- Download staff-processed document link
- File upload field for final document  
- Complete button (requires file)
- Reject to staff button with remarks

User Panel (status = 3):
- Show rejection remarks
- Edit form for each requirement submission
- File upload fields for document requirements
- Text input fields for text requirements
- Resubmit button

User Panel (status = 4):
- Download final document button
- Success message

### 7. CSS Additions

```css
/* Timeline Component */
.timeline {
    position: relative;
    padding: 1rem 0;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 20px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e5e7eb;
}

.timeline-item {
    position: relative;
    padding-left: 60px;
    margin-bottom: 2rem;
}

.timeline-marker {
    position: absolute;
    left: 10px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #e5e7eb;
    border: 3px solid white;
}

.timeline-item.completed .timeline-marker {
    background: #10b981;
}

.timeline-item.active .timeline-marker {
    background: #0b3d91;
    box-shadow: 0 0 0 4px rgba(11, 61, 145, 0.2);
}

/* Requirement Display */
.requirement-item {
    padding: 1rem;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    background: #f9fafb;
}

.file-display {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: white;
    border-radius: 4px;
    margin-top: 0.5rem;
}

/* Status Badge Improvements */
.status-badge-large {
    padding: 0.75rem 1.5rem;
    font-size: 1rem;
    font-weight: 600;
    border-radius: 8px;
}

/* Action Cards */
.action-card {
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.action-card.primary {
    border-color: #0b3d91;
    background: #f0f4f8;
}

.action-card.danger {
    border-color: #ef4444;
    background: #fef2f2;
}

.action-card.success {
    border-color: #10b981;
    background: #f0fdf4;
}
```

## Implementation Priority

1. **HIGH PRIORITY** (Blocking workflow):
   - Staff approve with document upload
   - Admin complete with document upload
   - File replacement logic

2. **MEDIUM PRIORITY** (User experience):
   - Display requirement submissions
   - User resubmit with file replacement
   - UI improvements

3. **LOW PRIORITY** (Nice to have):
   - Activity timeline
   - Advanced notifications
   - Download statistics

## Testing Checklist

- [ ] Staff can approve request with document upload
- [ ] Admin can complete request with final document upload
- [ ] Admin can reject back to staff (file deleted)
- [ ] User can resubmit with file replacements (old files deleted)
- [ ] Requirement submissions display correctly
- [ ] Notifications sent on status changes
- [ ] Activity logs record all actions
- [ ] Access control enforced for all roles
- [ ] File size and type validation works
- [ ] Download links work for completed requests
- [ ] Error messages are clear and helpful

## Files to Modify

1. `pages/request-ticket.php` - Main workflow logic
2. `public/assets/css/style.css` - UI styling
3. Test after each change to ensure workflow integrity
