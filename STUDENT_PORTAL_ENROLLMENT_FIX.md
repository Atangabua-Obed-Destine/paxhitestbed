# STUDENT PORTAL ENROLLMENT SELECTION FIX

## Problem
When students switched programs in the student portal (`http://localhost/paxhitest/student/`), most views were ignoring the selected enrollment and only showing data for the current active session enrollment. This prevented students from viewing data for:
- Inactive enrollments
- Past semester enrollments
- Different program enrollments

## Root Cause
Most student-facing controllers were using this problematic pattern:

```php
$session = Session::where('status', '1')->where('current', '1')->first();
$enroll = StudentEnroll::where('student_id', $student_id)
                ->where('session_id', $session->id)
                ->where('status', '1')  // ← Always filters active only!
                ->first();
```

They ignored the `selected_enrollment_id` stored in session by the `SelectEnrollmentMiddleware`.

## Controllers Fixed

### 1. DashboardController.php
**Location:** `app/Http/Controllers/Student/DashboardController.php`

**Changes:**
- Removed `->where('status', '1')` filter when retrieving selected enrollment
- Added `->orderBy('id', 'desc')` to fallback query for latest enrollment
- Now respects `session('selected_enrollment_id')` for both active and inactive enrollments

**Lines Changed:** 61-83

**Before:**
```php
$enroll = StudentEnroll::where('id', $selectedEnrollmentId)
                ->where('student_id', $student_id)
                ->where('status', '1')  // ← Blocked inactive enrollments
                ->first();
```

**After:**
```php
$enroll = StudentEnroll::where('id', $selectedEnrollmentId)
                ->where('student_id', $student_id)
                ->first();  // ← Now works with inactive enrollments
```

### 2. ClassRoutineController.php
**Location:** `app/Http/Controllers/Student/ClassRoutineController.php`

**Changes:**
- Replaced current session query with `selected_enrollment_id` logic
- Removed `->where('status', '1')` filter
- Added fallback to current session for backward compatibility
- Added empty collection fallback

**Lines Changed:** 34-64

**Impact:** Students can now view class routines for any selected enrollment (active or inactive)

### 3. AttendanceController.php
**Location:** `app/Http/Controllers/Student/AttendanceController.php`

**Changes:**
- Replaced current session query with `selected_enrollment_id` logic
- Removed `->where('status', '1')` filter
- Added fallback to current session
- Added empty collection fallback for attendances

**Lines Changed:** 53-80

**Impact:** Students can view attendance records for any selected enrollment

### 4. ExamRoutineController.php
**Location:** `app/Http/Controllers/Student/ExamRoutineController.php`

**Changes:**
- Replaced current session query with `selected_enrollment_id` logic
- Removed `->where('status', '1')` filter
- Added fallback to current session
- Added empty collection fallback for exam routines

**Lines Changed:** 44-78

**Impact:** Students can view exam schedules for any selected enrollment

## Controllers That Already Work Correctly

### ExamResultController.php
- Already gets all enrollments for the student: `StudentEnroll::where('student_id', $student->id)->get()`
- Filters by user-selected session/semester parameters
- **No changes needed**

### CourseRegistrationController.php
- Uses `$student->currentEnroll` relationship
- Already respects enrollment selection through the model relationship
- **No changes needed**

### LeaveController.php
- Queries by student_id only: `StudentLeave::where('student_id', $student_id)`
- Not enrollment-specific
- **No changes needed**

### AssignmentController.php
- Filters by student_id with optional session/semester parameters
- Gets all enrollments: `StudentEnroll::where('student_id', $user->id)`
- **No changes needed**

### ResitController.php
- Gets enrollment based on user-provided request parameters
- Not tied to current session or selected enrollment
- **No changes needed**

### DownloadCenterController.php
- Queries by student_id only
- Not enrollment-specific
- **No changes needed**

## Implementation Pattern

All fixed controllers now follow this standard pattern:

```php
// Get selected enrollment from session (set by SelectEnrollmentMiddleware)
$selectedEnrollmentId = session('selected_enrollment_id');
$student_id = Auth::guard('student')->user()->id;

$enroll = null;
if($selectedEnrollmentId) {
    $enroll = StudentEnroll::where('id', $selectedEnrollmentId)
                    ->where('student_id', $student_id)
                    ->first();  // ← No status filter!
}

// Fallback: If no enrollment found via session, try current session (backward compatibility)
if(!$enroll) {
    $session = Session::where('status', '1')->where('current', '1')->first();
    if(isset($session)){
        $enroll = StudentEnroll::where('student_id', $student_id)
                        ->where('session_id', $session->id)
                        ->orderBy('id', 'desc')
                        ->first();  // ← No status filter!
    }
}
```

## How It Works

1. **Middleware Sets Enrollment:**
   - `SelectEnrollmentMiddleware` runs on all student routes
   - Stores `selected_enrollment_id` in session
   - Groups by unique matricule to avoid duplicates

2. **Controllers Use Session:**
   - Controllers check `session('selected_enrollment_id')` first
   - Query enrollment by ID (works for both active and inactive)
   - Fallback to current session if no selection exists

3. **Program Switcher:**
   - Student clicks dropdown in portal header
   - AJAX call to `ProgramSelectorController::switchProgram()`
   - Updates `session('selected_enrollment_id')`
   - Page reloads with new enrollment data

## Testing Checklist

Test with a student who has multiple enrollments (e.g., progressed through multiple semesters):

- [ ] Dashboard shows correct enrollment data
- [ ] Class routine shows correct schedule for selected enrollment
- [ ] Attendance shows correct records for selected enrollment
- [ ] Exam routine shows correct schedule for selected enrollment
- [ ] Exam results work (already working)
- [ ] Course registration works (already working)
- [ ] Program switcher dropdown shows all enrollments (active + inactive)
- [ ] Switching programs updates all views
- [ ] Inactive enrollments display with "Inactive" badge
- [ ] Active enrollments display with "Active" badge

## URLs Affected

Fixed views that now respect enrollment selection:
- `http://localhost/paxhitest/student/` (Dashboard)
- `http://localhost/paxhitest/student/class-routine`
- `http://localhost/paxhitest/student/exam-routine`
- `http://localhost/paxhitest/student/attendance`

Already working correctly:
- `http://localhost/paxhitest/student/exam-results`
- `http://localhost/paxhitest/student/course-registration`
- `http://localhost/paxhitest/student/leave`
- `http://localhost/paxhitest/student/assignment`
- `http://localhost/paxhitest/student/download`
- `http://localhost/paxhitest/student/resit`

## Key Benefits

1. **Multi-Enrollment Support:** Students with multiple programs can view data for each enrollment
2. **Historical Data Access:** Students can view past semester data (inactive enrollments)
3. **Consistent Behavior:** All views respect the selected enrollment
4. **Status Display:** Status badges (Active/Inactive) help users understand enrollment state
5. **Backward Compatible:** Fallback to current session ensures existing functionality preserved
