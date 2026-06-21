# Staff Assignment Course Filtering - Fix Summary

## Issue
Courses were not showing in dropdowns when a program was selected, even though faculty and program filtering was working.

## Root Cause
The `StaffAssignmentService::getAccessibleCourseIds()` method was trying to use a direct `program_id` column on the subjects table, but the system uses a many-to-many relationship through the `program_subject` pivot table.

## SQL Error
```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'program_id' in 'where clause'
```

## Solution Applied

### Fixed File: `app/Services/StaffAssignmentService.php`

**Before (Incorrect):**
```php
$hasSpecificCourses = StaffAssignment::where('user_id', $userId)
    ->where('assignable_type', Subject::class)
    ->whereIn('assignable_id', function($query) use ($programId) {
        $query->select('id')
            ->from('subjects')
            ->where('program_id', $programId); // ❌ This column doesn't exist!
    })
    ->exists();

if (!$hasSpecificCourses) {
    $programCourses = Subject::where('program_id', $programId)
        ->pluck('id')->toArray(); // ❌ This fails too!
    $courseIds = array_merge($courseIds, $programCourses);
}
```

**After (Correct):**
```php
// Get all subject IDs for this program from the pivot table
$programSubjectIds = \DB::table('program_subject')
    ->where('program_id', $programId)
    ->pluck('subject_id')
    ->toArray();

$hasSpecificCourses = StaffAssignment::where('user_id', $userId)
    ->where('assignable_type', Subject::class)
    ->whereIn('assignable_id', $programSubjectIds) // ✅ Using pivot table data
    ->exists();

if (!$hasSpecificCourses) {
    $courseIds = array_merge($courseIds, $programSubjectIds); // ✅ Direct merge
}
```

## Database Structure
The system uses a many-to-many relationship:
- Table: `program_subject` (pivot table)
- Columns: `program_id`, `subject_id`
- A subject can belong to multiple programs
- A program can have multiple subjects

## Test Results

### Staff: Diran Ndimbe (ID: 0003)
**Assignments:**
- Faculty: FACULTY OF BUSINESS AND FINANCE
- Program: HND ACCOUNTANCY

**Access Results:**
- Total Faculties in System: 7
- Accessible Faculties: **1** ✅
- Total Programs in System: 32
- Accessible Programs: **1** ✅
- Total Courses in System: 66
- Accessible Courses: **41** ✅

**Sample Accessible Courses:**
- Principles of Accounting (ACC11O1H)
- OHADA Financial Accounting I (ACC11O2H)
- Principles of Management (MGT1101H)
- Statistics and Business Mathematics (ECO1102H)
- Functional English I (ENG1101H)
- Computer Science I (COM1201H)
- Cost and Management Accounting II (ACC2103H)
- And 34 more...

## Components Already Using Filter

✅ **FilterController** - AJAX endpoint for course dropdowns:
```php
public function filterSubject(Request $request)
{
    $rows = Subject::where('status', 1);
    $rows->with('programs')->whereHas('programs', function ($query) use ($data){
        $query->where('program_id', $data['program']);
    });
    
    // Apply staff assignment filter
    $rows = StaffAssignmentService::filterCourses($rows);
    
    $subjects = $rows->orderBy('code', 'asc')->get();
    return response()->json($subjects);
}
```

## Testing Instructions

### Browser Test:
1. **Logout** from Super Admin account
2. **Login** as staff ID 0003
3. Navigate to any page with course selection (Reports, Attendance, Exam Marking, etc.)
4. Select Faculty: "FACULTY OF BUSINESS AND FINANCE"
5. Select Program: "HND ACCOUNTANCY"
6. **Expected:** Course dropdown should now show 41 courses

### Command Line Test:
```bash
php test-staff-restriction.php
```

Expected output:
```
Filtered Courses for Staff: 41
  - Principles of Accounting (ACC11O1H)
  - OHADA Financial Accounting I (ACC11O2H)
  [... 39 more courses ...]
```

## Status
✅ **FIXED** - Courses now properly filter based on staff assignments

## Related Files Updated
1. ✅ `app/Services/StaffAssignmentService.php` - Fixed `getAccessibleCourseIds()`
2. ✅ `app/Http/Controllers/FilterController.php` - Already had filter applied
3. ✅ `test-staff-restriction.php` - Added course testing

## Technical Notes
- The fix properly handles the many-to-many relationship using `DB::table('program_subject')`
- The pivot table is queried directly to get subject IDs for each program
- This maintains consistency with how the system stores program-subject relationships
- No changes needed to database structure or migrations

---

**Date:** October 24, 2025  
**Status:** ✅ Resolved  
**Impact:** Staff assignment restrictions now work for Faculties, Programs, AND Courses
