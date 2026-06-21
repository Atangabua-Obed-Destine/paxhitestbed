# Staff Assignment System - Complete Documentation

## Overview
The **Staff Assignment System** allows HR or administrators to assign specific staff members to particular faculties, programs, and courses. Once assigned, staff members will **only** have access to their assigned areas, providing fine-grained access control beyond role-based permissions.

---

## Key Features

### 1. **Hierarchical Access Control**
- **Faculty Level**: Assign staff to entire faculties
- **Program Level**: Assign staff to specific programs within faculties
- **Course Level**: Assign staff to specific courses within programs

### 2. **Access Logic**
- **Super Admin**: Always has access to everything (no restrictions)
- **Staff with NO assignments**: Has default role-based access to all areas
- **Staff WITH assignments**: Restricted to ONLY assigned faculties/programs/courses

### 3. **Multiple Assignments**
- Staff can be assigned to multiple faculties simultaneously
- Each faculty can have different program/course selections
- "Add Faculty" button allows adding multiple faculty assignments

### 4. **Class Routine Integration**
- System detects existing class routine assignments
- Warns when restricting access for staff with active class routines
- Requires confirmation before overwriting existing access

### 5. **Smart Filtering**
- All admin dropdowns automatically filter based on staff assignments
- Faculty, Program, Course, and Student lists respect assignments
- AJAX filters also apply staff assignment restrictions

---

## Database Structure

### `staff_assignments` Table
```sql
CREATE TABLE staff_assignments (
    id BIGINT UNSIGNED PRIMARY KEY,
    user_id BIGINT UNSIGNED,              -- Staff member ID
    assignable_type VARCHAR(255),         -- 'App\Models\Faculty', 'App\Models\Program', 'App\Models\Subject'
    assignable_id BIGINT UNSIGNED,        -- ID of faculty/program/course
    created_by BIGINT UNSIGNED NULLABLE,  -- Who created the assignment
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY (user_id, assignable_type, assignable_id)
);
```

---

## Permissions

The system adds 4 new permissions:

| Permission | Description | Access Level |
|------------|-------------|--------------|
| `staff-assignment-index` | View staff assignments | Read |
| `staff-assignment-create` | Create new assignments | Create |
| `staff-assignment-edit` | Edit existing assignments | Update |
| `staff-assignment-delete` | Remove assignments | Delete |

**Assign these permissions to HR or Admin roles** via the Role Management page.

---

## Usage Guide

### **For HR/Admins: Assigning Staff**

1. **Navigate to Staff Assignment**
   - Go to: `Admin Panel → Human Resources → Staff Assign`
   - URL: `http://localhost/paxhitest/admin/staff-assignment`

2. **Create New Assignment**
   - Click "Assign Staff" button
   - Select the staff member from dropdown
   - Choose a faculty

3. **Configure Access**
   - **Leave Programs empty** = Staff can access ALL programs in that faculty
   - **Select specific programs** = Staff can only access selected programs
   - **Leave Courses empty** = Staff can access ALL courses in selected programs
   - **Select specific courses** = Staff can only access selected courses

4. **Add Multiple Faculties**
   - Click "Add Another Faculty" button
   - Configure access for additional faculty
   - Repeat as needed

5. **Save Assignment**
   - Review all selections
   - Read warning about access restrictions
   - Click "Save Assignments"

### **For Staff: Understanding Your Access**

Once assigned:
- You will **only** see faculties/programs/courses you're assigned to
- All dropdowns will automatically filter
- You cannot access unassigned areas
- Contact HR to request access changes

---

## Technical Implementation

### **1. Service Class: `StaffAssignmentService`**

Located: `app/Services/StaffAssignmentService.php`

**Key Methods:**

```php
// Check if user is super admin
StaffAssignmentService::isSuperAdmin($userId);

// Check if user has any assignments
StaffAssignmentService::hasAssignments($userId);

// Get accessible IDs
StaffAssignmentService::getAccessibleFacultyIds($userId);
StaffAssignmentService::getAccessibleProgramIds($userId);
StaffAssignmentService::getAccessibleCourseIds($userId);

// Filter queries (use in controllers)
StaffAssignmentService::filterFaculties($query);
StaffAssignmentService::filterPrograms($query);
StaffAssignmentService::filterCourses($query);

// Check specific access
StaffAssignmentService::canAccessFaculty($facultyId, $userId);
StaffAssignmentService::canAccessProgram($programId, $userId);
StaffAssignmentService::canAccessCourse($courseId, $userId);
```

### **2. Updated Controllers**

The following controllers now respect staff assignments:

- `FacultyController` - Filters faculty list
- `ProgramController` - Filters programs and faculties
- `SubjectController` - Filters courses, programs, and faculties
- `ClassRoutineController` - Filters faculties and programs
- `FilterController` - AJAX filters apply restrictions

**Example Usage in Controller:**

```php
use App\Services\StaffAssignmentService;

public function index()
{
    // Apply staff assignment filter
    $query = Faculty::where('status', 1);
    $query = StaffAssignmentService::filterFaculties($query);
    $faculties = $query->get();
    
    return view('admin.faculty.index', compact('faculties'));
}
```

### **3. Routes**

| Method | URL | Route Name | Description |
|--------|-----|------------|-------------|
| GET | `/admin/staff-assignment` | `admin.staff-assignment.index` | List assignments |
| GET | `/admin/staff-assignment/create` | `admin.staff-assignment.create` | Create form |
| POST | `/admin/staff-assignment` | `admin.staff-assignment.store` | Store new assignment |
| GET | `/admin/staff-assignment/{id}/edit` | `admin.staff-assignment.edit` | Edit form |
| PUT | `/admin/staff-assignment/{id}` | `admin.staff-assignment.update` | Update assignment |
| DELETE | `/admin/staff-assignment/{id}` | `admin.staff-assignment.destroy` | Delete assignment |
| POST | `/admin/staff-assignment/get-programs` | `admin.staff-assignment.get-programs` | AJAX: Get programs |
| POST | `/admin/staff-assignment/get-courses` | `admin.staff-assignment.get-courses` | AJAX: Get courses |

---

## Warnings & Indicators

### **Class Routine Warning**
When editing assignments for staff with existing class routines:

```
⚠️ Class Routine Detected!
This staff member has existing class routine assignments.
Updating their assignments will restrict their access to ONLY the selected areas.

☑️ I understand that this will restrict access and may affect existing class routines
```

User must check the confirmation box before saving.

### **Info Messages**

**On Index Page:**
> Staff assignments restrict access to specific faculties, programs, and courses. Staff without assignments have default role-based access to all areas.

**On Create Page:**
> Once assignments are made, this staff will ONLY have access to the assigned faculties, programs, and courses. If they have existing class routines in other areas, they will lose access to them.

---

## Testing & Verification

### **Run Test Script**
```bash
cd c:\xampp\htdocs\paxhitest
php test-staff-assignment.php
```

**Expected Output:**
```
✓ Database: staff_assignments table created
✓ Permissions: 4 permissions seeded
✓ Models: StaffAssignment with relationships
✓ Service: StaffAssignmentService with access control
✓ Routes: 8 routes registered
✓ Controller: StaffAssignmentController created
✓ Views: 3 views (index, create, edit)
✓ Sidebar: Menu added under Human Resources

🎉 Staff Assignment System is ready!
```

### **Manual Testing Steps**

1. **Test Super Admin Access**
   - Login as Super Admin
   - Verify you can see ALL faculties/programs/courses
   - Create staff assignment for another user

2. **Test Staff with No Assignments**
   - Login as regular staff
   - Verify they see all areas (default role access)

3. **Test Staff with Assignments**
   - Create assignment for a staff member
   - Assign them to specific faculty/programs
   - Login as that staff member
   - Verify they ONLY see assigned areas

4. **Test Multiple Faculty Assignment**
   - Assign staff to 2+ faculties
   - Verify they see all assigned areas

5. **Test Class Routine Warning**
   - Create class routine for staff member
   - Edit their assignments
   - Verify warning appears
   - Confirm and save

---

## Troubleshooting

### **Issue: Staff Can't See Staff Assignment Menu**
**Solution:** Grant `staff-assignment-index` permission to their role

### **Issue: Staff Still Sees Everything After Assignment**
**Possible Causes:**
1. User has "Super Admin" role (bypasses restrictions)
2. Permissions not cleared - logout and login again
3. Browser cache - clear cache and refresh

### **Issue: AJAX Dropdowns Not Filtering**
**Solution:** Verify FilterController includes:
```php
use App\Services\StaffAssignmentService;
$query = StaffAssignmentService::filterPrograms($query);
```

### **Issue: Assignment Creation Fails**
**Check:**
1. Faculty/Program/Course exists and status = 1
2. User exists and is not Student/Applicant
3. No duplicate assignments (unique constraint)

---

## Security Considerations

1. **Super Admin Bypass**: Super Admin role always has full access (by design)
2. **Default Access**: Staff without assignments have full role-based access
3. **Cascade Delete**: Deleting a staff member removes all their assignments
4. **Permission Protected**: All routes require appropriate permissions

---

## Future Enhancements

Potential improvements:
- [ ] Bulk assignment tool (assign multiple staff at once)
- [ ] Import/Export assignments via Excel
- [ ] Assignment templates (copy assignments between staff)
- [ ] Time-based assignments (temporary access)
- [ ] Assignment history/audit log
- [ ] Assignment expiration dates
- [ ] Email notifications when assignments change

---

## Files Modified/Created

### **Created Files:**
1. `database/migrations/2025_10_24_070523_create_staff_assignments_table.php`
2. `database/seeders/StaffAssignmentPermissionsSeeder.php`
3. `app/Models/StaffAssignment.php`
4. `app/Services/StaffAssignmentService.php`
5. `app/Http/Controllers/Admin/StaffAssignmentController.php`
6. `resources/views/admin/staff-assignment/index.blade.php`
7. `resources/views/admin/staff-assignment/create.blade.php`
8. `resources/views/admin/staff-assignment/edit.blade.php`
9. `test-staff-assignment.php`

### **Modified Files:**
1. `routes/web.php` - Added staff assignment routes
2. `app/User.php` - Added staffAssignments relationship
3. `resources/views/admin/layouts/inc/sidebar.blade.php` - Added menu item
4. `app/Http/Controllers/Admin/FacultyController.php` - Added filtering
5. `app/Http/Controllers/Admin/ProgramController.php` - Added filtering
6. `app/Http/Controllers/Admin/SubjectController.php` - Added filtering
7. `app/Http/Controllers/Admin/ClassRoutineController.php` - Added filtering
8. `app/Http/Controllers/FilterController.php` - Added filtering to AJAX

---

## Support

For questions or issues:
1. Check this documentation
2. Run test script: `php test-staff-assignment.php`
3. Check Laravel logs: `storage/logs/laravel.log`
4. Verify permissions are assigned to roles
5. Ensure database migration ran successfully

---

**System Version:** 1.0  
**Last Updated:** October 24, 2025  
**Status:** ✅ Production Ready
