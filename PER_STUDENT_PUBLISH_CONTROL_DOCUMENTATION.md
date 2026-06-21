# Per-Student Result Publish Control System

## Overview
This feature allows administrators to unpublish and republish individual student results independently from the section-level workflow. This is essential for handling grade appeals, corrections, investigations, and other exceptional cases.

---

## Key Features

### ✅ Individual Control
- **Unpublish**: Hide a specific student's result while keeping the rest of the class published
- **Republish**: Make a previously unpublished result visible again
- **Audit Trail**: Complete logging of all unpublish/republish actions

### ✅ Smart Visibility Logic
```
Student sees result when:
├── No override (NULL)
│   └── Section workflow_state = "published" ✓
├── Override = TRUE
│   └── Force published (always visible) ✓
└── Override = FALSE
    └── Force unpublished (always hidden) ✗
```

---

## Database Schema

### Modified Table: `subject_markings`
```sql
-- New columns added
is_published_override    BOOLEAN NULL     -- NULL=follow workflow, TRUE=force show, FALSE=force hide
unpublish_reason        TEXT NULL         -- Required reason for unpublishing
unpublished_by          BIGINT NULL       -- User who unpublished
unpublished_at          TIMESTAMP NULL    -- When unpublished
republished_by          BIGINT NULL       -- User who republished
republished_at          TIMESTAMP NULL    -- When republished
```

### New Table: `subject_marking_publish_logs`
Complete audit trail for all publish override actions.

```sql
id                      BIGINT PRIMARY
subject_marking_id      BIGINT FOREIGN KEY
action                  ENUM('unpublish', 'republish')
reason                  TEXT NULL
performed_by            BIGINT FOREIGN KEY (users)
previous_state          VARCHAR
new_state               VARCHAR
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

---

## User Interface

### Admin Marking Page
**Location**: `http://localhost/paxhitest/admin/exam/subject-marking`

#### New Column: "Publish Control"
Each student row now shows:

1. **Status Badge**:
   - 🔓 **Published** (green) - Following workflow, visible to student
   - 🔒 **Unpublished** (red) - Manually hidden from student
   - 🔓 **Force Published** (green) - Manually visible regardless of workflow

2. **Action Buttons**:
   - **Unpublish** (yellow) - Only shown when section is published and student result is visible
   - **Republish** (green) - Only shown when student result is unpublished

#### Unpublish Modal
- **Required**: Reason (min 10 chars, max 500 chars)
- **Warning**: Alerts admin that result will be hidden from student
- **Examples**: Grade appeal pending, Exam misconduct investigation, Missing coursework

#### Republish Modal
- **Optional**: Reason for republishing
- **Info**: Confirms result will become visible again

---

## Permissions

### New Permission
```
Name: subject-marking-unpublish
Group: Course Final
Title: Unpublish Individual Results
```

### Who Can Use This?
1. **Super Admin** - Always allowed
2. **HOD** (Head of Department) - By role
3. **Exam Officer** - By role
4. **Academic Dean** - By role
5. **Users with permission** - `subject-marking-unpublish`

### Assignment
Grant permission via:
```
Admin → Roles & Permissions → Assign to specific roles
```

---

## API Endpoints

### Unpublish Student Result
```http
POST /admin/exam/subject-marking/{subject_marking}/unpublish
```

**Parameters**:
- `reason` (required, string, 10-500 chars)

**Response**: Redirects back with success/error message

**Validation**:
- ✅ Section must be published
- ✅ Student result must not already be unpublished
- ✅ User must have permission

---

### Republish Student Result
```http
POST /admin/exam/subject-marking/{subject_marking}/republish
```

**Parameters**:
- `reason` (optional, string, max 500 chars)

**Response**: Redirects back with success/error message

**Validation**:
- ✅ Student result must be unpublished
- ✅ User must have permission

---

## Model Methods

### SubjectMarking Model

#### `getIsVisibleToStudentAttribute()` (Accessor)
Returns boolean indicating if result should be visible to student.

```php
// Usage
if ($marking->is_visible_to_student) {
    // Show result
}
```

#### `getPublishStatusBadge()` (Helper)
Returns array with badge display information:

```php
[
    'text' => 'Unpublished',
    'class' => 'badge-danger',
    'icon' => 'fa-lock'
]
```

---

## Student-Side Visibility

### Affected Views
1. **Student Transcript** (`resources/views/student/transcript/index.blade.php`)
2. **Student Dashboard** (`resources/views/student/dashboard/index.blade.php`)
3. **Course Registration** (`resources/views/student/course-registration/index.blade.php`)

### Implementation
All student result queries now check:
```php
$mark->is_visible_to_student && (publish_date/time check)
```

**Dashboard Controller Filter**:
```php
->where(function($query) {
    $query->where(function($q) {
        // No override, follow workflow
        $q->whereNull('is_published_override')
          ->where('workflow_state', 'published');
    })->orWhere(function($q) {
        // Override is TRUE (force published)
        $q->where('is_published_override', true);
    });
})
```

---

## Common Use Cases

### Scenario 1: Grade Appeal
1. Student submits grade appeal for Math 101
2. Admin navigates to subject marking for Math 101
3. Clicks **Unpublish** on student's row
4. Enters reason: "Grade appeal pending review - disputed final exam calculation"
5. Result immediately hidden from student portal
6. After appeal resolved, click **Republish**

### Scenario 2: Academic Misconduct Investigation
1. Exam committee flags potential plagiarism
2. Admin unpublishes result with reason: "Academic misconduct investigation in progress"
3. Investigation concludes - no violation found
4. Admin republishes with reason: "Investigation concluded - no violation"

### Scenario 3: Missing Coursework Submission
1. Student submits late assignment after results published
2. Marks need recalculation
3. Admin unpublishes: "Late assignment submission requires mark recalculation"
4. After updating marks, republish: "Marks updated with late submission"

### Scenario 4: Data Entry Error
1. Wrong marks entered for student
2. Admin unpublishes: "Data entry error correction in progress"
3. Corrects marks in system
4. Republishes: "Corrected data entry error"

---

## Audit Trail Access

### View Publish Logs
```php
$logs = SubjectMarkingPublishLog::where('subject_marking_id', $markingId)
    ->with('performer')
    ->orderBy('created_at', 'desc')
    ->get();

foreach($logs as $log) {
    echo "{$log->action} by {$log->performer->name} at {$log->created_at}";
    echo "Reason: {$log->reason}";
}
```

### Log Information Includes
- Action type (unpublish/republish)
- Performed by (user)
- Timestamp
- Reason
- Previous state
- New state

---

## Testing Checklist

### ✅ Functional Tests
1. **Unpublish Student**:
   - [ ] Section is published
   - [ ] Click unpublish button
   - [ ] Modal opens
   - [ ] Enter reason (test validation: min 10 chars)
   - [ ] Submit
   - [ ] Success message appears
   - [ ] Student result hidden from portal
   - [ ] Badge changes to "Unpublished" (red)

2. **Republish Student**:
   - [ ] Student is unpublished
   - [ ] Click republish button
   - [ ] Modal opens
   - [ ] Enter optional reason
   - [ ] Submit
   - [ ] Success message appears
   - [ ] Student result visible again
   - [ ] Badge changes to "Published" (green)

3. **Permission Tests**:
   - [ ] Super admin can unpublish/republish
   - [ ] HOD can unpublish/republish
   - [ ] Regular teacher cannot (permission denied)

4. **Validation Tests**:
   - [ ] Cannot unpublish if section not published
   - [ ] Cannot unpublish already unpublished student
   - [ ] Cannot republish already published student
   - [ ] Reason validation (min/max length)

5. **Audit Log Tests**:
   - [ ] Unpublish action logged
   - [ ] Republish action logged
   - [ ] User tracked correctly
   - [ ] Timestamp recorded
   - [ ] Reason saved

### ✅ Student Portal Tests
1. **Transcript View**:
   - [ ] Published results visible
   - [ ] Unpublished results hidden
   - [ ] CGPA calculation excludes unpublished courses

2. **Dashboard View**:
   - [ ] Recent results exclude unpublished
   - [ ] Republished results reappear

3. **Edge Cases**:
   - [ ] Student with all results unpublished
   - [ ] Mixed published/unpublished results
   - [ ] Multiple unpublish/republish cycles

---

## Maintenance

### Database Cleanup (if needed)
```sql
-- View all unpublished students
SELECT sm.id, s.name, sm.unpublish_reason, sm.unpublished_at
FROM subject_markings sm
JOIN student_enrolls se ON sm.student_enroll_id = se.id
JOIN students s ON se.student_id = s.id
WHERE sm.is_published_override = 0;

-- View audit logs
SELECT * FROM subject_marking_publish_logs 
ORDER BY created_at DESC 
LIMIT 50;
```

### Migration Rollback (Emergency Only)
```bash
# Find migration batch
php artisan migrate:status

# Rollback specific migrations
php artisan migrate:rollback --step=2
```

**⚠️ Warning**: Rolling back will lose all unpublish/republish data and audit logs!

---

## Files Modified/Created

### Database
- ✅ `2025_11_10_022046_add_publish_override_columns_to_subject_marking_table.php`
- ✅ `2025_11_10_022115_create_subject_marking_publish_logs_table.php`

### Models
- ✅ `app/Models/SubjectMarking.php` (updated)
- ✅ `app/Models/SubjectMarkingPublishLog.php` (new)

### Controllers
- ✅ `app/Http/Controllers/Admin/SubjectMarkingController.php` (updated)
- ✅ `app/Http/Controllers/Student/DashboardController.php` (updated)

### Views
- ✅ `resources/views/admin/subject-marking/marking.blade.php` (updated)
- ✅ `resources/views/student/transcript/index.blade.php` (updated)

### Routes
- ✅ `routes/web.php` (updated)

### Seeders
- ✅ `database/seeders/PermissionSeeder.php` (updated)

---

## Support & Troubleshooting

### Common Issues

**Issue 1**: Unpublish button not showing
- **Cause**: Section not published yet
- **Solution**: Publish the section first via bulk transition

**Issue 2**: Permission denied error
- **Cause**: User lacks required permission
- **Solution**: Grant `subject-marking-unpublish` permission or assign HOD/Exam Officer role

**Issue 3**: Student still sees result after unpublish
- **Cause**: Cache not cleared
- **Solution**: Run `php artisan cache:clear` and refresh student portal

**Issue 4**: Reason validation fails
- **Cause**: Reason too short (< 10 chars)
- **Solution**: Provide more detailed reason (minimum 10 characters)

---

## Security Considerations

1. **Permission-Based**: Only authorized users can unpublish/republish
2. **Audit Trail**: All actions logged with user and timestamp
3. **Required Reason**: Unpublishing requires explanation (accountability)
4. **No Student Access**: Students cannot see unpublish reasons or request unpublishing
5. **State Validation**: Cannot unpublish unpublished results (prevents duplicate actions)

---

## Future Enhancements (Optional)

- [ ] Email notification to student when result unpublished/republished
- [ ] Bulk unpublish for multiple students
- [ ] Time-limited unpublishing (auto-republish after X days)
- [ ] Student notification with generic message ("Result under review")
- [ ] HOD approval required for unpublishing (two-step process)
- [ ] Export audit logs to CSV/PDF
- [ ] Dashboard widget showing currently unpublished results count

---

## Conclusion

The per-student publish control system provides:
- ✅ **Flexibility**: Handle exceptional cases without affecting entire class
- ✅ **Accountability**: Complete audit trail with required reasons
- ✅ **Security**: Permission-based access control
- ✅ **Transparency**: Clear status badges and action logs
- ✅ **Student Experience**: Seamless - they simply don't see unpublished results

**Status**: ✅ Fully Implemented & Ready for Production

**Last Updated**: November 10, 2025
