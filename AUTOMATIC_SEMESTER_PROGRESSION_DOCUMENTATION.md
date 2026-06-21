# Automatic Semester Progression System

## Overview

The Automatic Semester Progression System enables students to automatically progress to the next semester when they complete all course requirements successfully. The system intelligently manages progression based on academic performance, resit status, and program structure.

## Core Features

### 1. Automatic Progression Logic

**Progression Triggers:**
- **All marks published**: Every course in current semester must have published marks
- **All courses passed**: Student must achieve ≥50% in ALL courses
- **No active resit requests**: Student cannot have pending/active resit requests
- **Next semester exists**: System verifies next semester is available for the program

**Progression Process:**
- System creates new `StudentEnroll` record for next semester
- **Does NOT automatically register courses** - student must register manually
- Uses same matricule as current enrollment
- Maintains same session and program
- Sets enrollment status to Active

### 2. Semester Ordering Intelligence

**System uses `year` and `semester_type` fields:**
- Year 1, First Semester (year=1, semester_type=1)
- Year 1, Second Semester (year=1, semester_type=2)
- Year 2, First Semester (year=2, semester_type=1)
- Year 2, Second Semester (year=2, semester_type=2)
- And so on...

**Progression Path:**
- First Semester → Second Semester (same year)
- Second Semester → First Semester (next year)
- Skips resit semesters (`is_resit = 0`)

### 3. Resit Request Cancellation

**Students can cancel resit requests:**
- **Cancellable States**: `requested`, `awaiting_payment`
- **Non-Cancellable States**: `finance_review`, `approved`, `scheduled`, `rejected`
- Cancellation automatically triggers progression eligibility check

**Cancel Button Locations:**
- Resit request index page (http://localhost/paxhitest/student/resit)
- Resit history page (http://localhost/paxhitest/student/resit/history)

### 4. Progression Eligibility Checks

**Requirement 1: All Marks Published**
```php
// Checks that every subject has:
- SubjectMarking exists
- workflow_state = 'published'
- publish_date/publish_time has passed
```

**Requirement 2: All Courses Passed**
```php
// Verifies:
- total_marks >= 50 for every course
- No F grades
```

**Requirement 3: No Active Resit Requests**
```php
// Blocks progression if student has resit requests in:
- requested
- awaiting_payment
- finance_review
- approved
- scheduled
```

**Requirement 4: Next Semester Available**
```php
// Verifies:
- Semester exists in database
- is_resit = 0 (not a resit semester)
- status = 1 (active)
- Assigned to student's program
- Has courses enrolled in enroll_subject table
```

## Technical Implementation

### Files Modified/Created

**New Service:**
- `app/Services/Academic/SemesterProgressionService.php` - Core progression logic

**Controllers Updated:**
- `app/Http/Controllers/Admin/SubjectMarkingController.php` - Hooks progression check after marking published
- `app/Http/Controllers/Student/ResitController.php` - Added cancel method

**Models Updated:**
- `app/Models/ResitRequest.php` - Added `STATE_CANCELLED` and `STATE_FINANCE_REVIEW` constants

**Routes Added:**
- `POST /student/resit/{id}/cancel` - Cancel resit request route

**Views Updated:**
- `resources/views/student/resit/index.blade.php` - Added cancel button
- `resources/views/student/resit/history.blade.php` - Added cancel button and action column

### Service Methods

#### `SemesterProgressionService::checkProgressionEligibility($enrollment)`

**Returns:**
```php
[
    'eligible' => bool,
    'reason' => string,
    'next_semester' => Semester|null
]
```

**Logic Flow:**
1. Check all courses have published marks
2. Check student passed all courses (≥50%)
3. Check no active resit requests
4. Find next semester based on year + semester_type
5. Verify semester exists in program enrollment structure

#### `SemesterProgressionService::progressToNextSemester($enrollment, $nextSemester)`

**Process:**
1. Begins database transaction
2. Checks if enrollment already exists for next semester
3. Creates new `StudentEnroll` record:
   - Same matricule
   - Same program
   - Same session
   - Same section
   - Next semester_id
   - Status = Active
4. Logs progression event
5. Commits transaction

**Returns:** `StudentEnroll|null`

#### `SemesterProgressionService::attemptAutomaticProgression($enrollment)`

**Combined method that:**
1. Checks eligibility
2. Progresses if eligible
3. Returns detailed result

**Returns:**
```php
[
    'progressed' => bool,
    'message' => string,
    'new_enrollment' => StudentEnroll|null
]
```

### Hook Points

**1. Subject Marking Publication (Admin):**
```php
// SubjectMarkingController::transition()
if ($request->input('state') === SubjectMarking::STATE_PUBLISHED) {
    $this->checkAutomaticProgression($subjectMarking);
}
```

**2. Resit Request Cancellation (Student):**
```php
// ResitController::cancel()
$progressionService->attemptAutomaticProgression($enrollment);
// Shows notification if student is progressed
```

## User Experience

### For Students

**Automatic Progression Notification:**
When marks are published and student is eligible:
```
"Congratulations! You have been automatically progressed to [Semester Name]. 
Please register your courses from the Course Registration page."
```

**After Cancelling Resit:**
If all conditions met:
```
"Congratulations! You have been automatically progressed to [Semester Name]. 
Please register your courses from the Course Registration page."
```

**Progression Blocked - Failed Courses:**
```
"Student has failed one or more courses. Must complete resit process first."
```

**Progression Blocked - Active Resits:**
```
"Student has pending resit requests. Must cancel all resit requests to progress."
```

### For Administrators

**Silent Background Process:**
- Progression happens automatically in background
- Logged in system logs
- No admin action required
- Can verify in student enrollment records

**Log Entry Example:**
```
"Student automatically progressed"
[
    'student_id' => 123,
    'from_semester' => 5,
    'to_semester' => 6,
    'new_enrollment_id' => 456
]
```

## Database Changes

### ResitRequest States Added:
```php
const STATE_FINANCE_REVIEW = 'finance_review';
const STATE_CANCELLED = 'cancelled';
```

### No Schema Changes Required:
- Uses existing `StudentEnroll` structure
- Uses existing `Semester` fields (year, semester_type, is_resit)
- Uses existing `EnrollSubject` verification

## Edge Cases Handled

### 1. Student Already Enrolled in Next Semester
```php
// System checks for existing enrollment
// Returns existing enrollment without creating duplicate
```

### 2. Next Semester Doesn't Exist
```php
// Common at end of program
// Progression blocked with message: 
// "No next semester available for this program"
```

### 3. Next Semester Not Configured
```php
// Semester exists but not in enroll_subject
// Progression blocked with message:
// "Next semester not configured for this program in enrollment structure"
```

### 4. Marks Published But Resit Pending
```php
// Student passed all courses
// But has active resit request from previous semester
// Progression blocked until resit cancelled
```

### 5. Student Cancels One Resit But Has Others
```php
// Cancelling one resit request
// Progression still blocked by remaining active resits
// Must cancel ALL resit requests
```

### 6. Transaction Failures
```php
// All database operations wrapped in transactions
// Automatic rollback on failure
// Error logged but doesn't break main flow
```

## Testing Scenarios

### Test 1: Normal Progression
1. Student completes all courses with ≥50%
2. Admin publishes all marks
3. System automatically creates next semester enrollment
4. Student sees success notification
5. Student can register courses for next semester

### Test 2: Failed Course - No Progression
1. Student fails one course (< 50%)
2. Admin publishes marks
3. System does NOT create next enrollment
4. Student must request resit
5. Progression blocked until resit resolved

### Test 3: Resit Cancellation Triggers Progression
1. Student has active resit request
2. Student passed all other courses
3. Student cancels resit request
4. System immediately checks eligibility
5. If eligible, progresses automatically

### Test 4: Multiple Enrollments
1. Student enrolled in multiple programs
2. Progression works independently per program
3. Each program tracks its own semester progression
4. No cross-program contamination

### Test 5: End of Program
1. Student in final semester
2. Passes all courses
3. System finds no next semester
4. Progression gracefully blocked
5. Student ready for graduation

## Configuration Requirements

### Program Setup (Admin)
1. **Semesters must be configured:**
   - Set correct `year` values (1, 2, 3, 4...)
   - Set correct `semester_type` (1=First, 2=Second)
   - Mark resit semesters with `is_resit = 1`

2. **Semesters must be assigned to programs:**
   - Use "Manage Semesters" in program settings
   - Ensure sequential semester progression exists

3. **Subjects must be enrolled:**
   - Visit: http://localhost/paxhitest/admin/academic/enroll-subject
   - Assign subjects to each program/semester/section
   - System verifies these exist before progression

### Grade System Configuration
1. **Passing grade must be ≥ 50%:**
   - System uses hard-coded 50% threshold
   - Adjust `SemesterProgressionService` if different threshold needed

2. **Grade scale must be configured:**
   - Required for mark percentage calculation
   - Used in failed course detection

## Monitoring & Logs

### Check Progression Events:
```bash
# Laravel log file
tail -f storage/logs/laravel.log | grep "automatically progressed"
```

### Verify Student Enrollments:
```sql
SELECT * FROM student_enrolls 
WHERE student_id = ? 
ORDER BY semester_id ASC;
```

### Check Resit Request Status:
```sql
SELECT * FROM resit_requests 
WHERE student_enroll_id = ? 
AND workflow_state NOT IN ('rejected', 'cancelled');
```

## API Reference

### Route: Cancel Resit Request
```
POST /student/resit/{id}/cancel
Auth: Student Guard
```

**Response:**
- Success flash message
- Progression notification if eligible
- Redirect to previous page

**Validation:**
- Resit request must belong to logged-in student
- Must be in cancellable state

### Service Usage Example:
```php
use App\Services\Academic\SemesterProgressionService;

$service = app(SemesterProgressionService::class);

// Check if eligible
$eligibility = $service->checkProgressionEligibility($enrollment);

if ($eligibility['eligible']) {
    // Attempt progression
    $result = $service->attemptAutomaticProgression($enrollment);
    
    if ($result['progressed']) {
        // Student was progressed
        $newEnrollment = $result['new_enrollment'];
    }
}
```

## Troubleshooting

### Issue: Student Not Progressing

**Check 1: All Marks Published?**
```php
// Verify workflow_state = 'published' for all subjects
SELECT sm.*, s.title as subject_title
FROM subject_marking sm
JOIN subjects s ON s.id = sm.subject_id
WHERE sm.student_enroll_id = ?
AND sm.workflow_state != 'published';
```

**Check 2: Any Failed Courses?**
```php
// Find courses with marks < 50%
SELECT sm.total_marks, s.title
FROM subject_marking sm
JOIN subjects s ON s.id = sm.subject_id
WHERE sm.student_enroll_id = ?
AND ROUND(sm.total_marks) < 50;
```

**Check 3: Active Resit Requests?**
```php
// Find pending resits
SELECT * FROM resit_requests
WHERE student_enroll_id = ?
AND workflow_state IN ('requested', 'awaiting_payment', 'finance_review', 'approved', 'scheduled');
```

**Check 4: Next Semester Exists?**
```php
// Verify semester configuration
$currentSemester = $enrollment->semester;
$nextYear = $currentSemester->semester_type == 1 ? $currentSemester->year : $currentSemester->year + 1;
$nextType = $currentSemester->semester_type == 1 ? 2 : 1;

// Should return a semester:
SELECT * FROM semesters
WHERE year = ? AND semester_type = ? AND is_resit = 0 AND status = 1;
```

**Check 5: Semester Has Enrolled Subjects?**
```php
// Verify enroll_subject configuration
SELECT * FROM enroll_subject
WHERE program_id = ? AND semester_id = ?;
```

### Issue: Duplicate Enrollments Created

**Prevention:**
- Service checks for existing enrollment before creating
- Uses database transaction for atomicity
- Logs all progression attempts

**Resolution:**
```sql
-- Find duplicates
SELECT student_id, program_id, semester_id, COUNT(*) as count
FROM student_enrolls
GROUP BY student_id, program_id, semester_id
HAVING count > 1;

-- Keep only most recent
DELETE FROM student_enrolls WHERE id NOT IN (
    SELECT MAX(id) FROM student_enrolls
    GROUP BY student_id, program_id, semester_id
);
```

### Issue: Student Can't Cancel Resit

**Cancellable only if:**
- State is 'requested' or 'awaiting_payment'
- Resit belongs to logged-in student

**Check:**
```php
$resitRequest->workflow_state; // Should be 'requested' or 'awaiting_payment'
$resitRequest->studentEnroll->student_id; // Should match Auth::guard('student')->id()
```

## Future Enhancements

### Potential Additions:
1. **Email Notifications**: Notify student when progressed
2. **Admin Dashboard Widget**: Show recent progressions
3. **Progression Report**: List of students progressed each semester
4. **Configurable Threshold**: Allow different passing percentages
5. **Graduation Detection**: Automatically flag students completing final semester
6. **Prerequisites Check**: Verify prerequisite courses before progression
7. **Bulk Progression**: Admin tool to manually trigger progression for multiple students

## Security Considerations

### Access Control:
- Student can only cancel their own resit requests
- Automatic progression runs server-side only
- No direct student control over progression
- All operations logged with user context

### Data Integrity:
- Database transactions ensure atomicity
- No orphaned enrollments created
- Existing enrollments detected and reused
- Rollback on any error

### Audit Trail:
- Progression events logged to Laravel log
- ResitRequest state changes tracked
- StudentEnroll creation timestamps preserved

## Conclusion

The Automatic Semester Progression System provides a streamlined academic journey for students while maintaining strict eligibility requirements. By intelligently managing progression based on academic performance and resit status, the system reduces administrative burden and provides clear pathways for student advancement.

**Key Benefits:**
- ✅ Automatic progression saves administrative time
- ✅ Clear eligibility rules ensure academic standards
- ✅ Resit cancellation provides flexibility
- ✅ Semester ordering respects academic structure
- ✅ Comprehensive eligibility checks prevent errors
- ✅ Transaction safety ensures data integrity
- ✅ Detailed logging aids troubleshooting

**Remember:**
- Students still must register courses manually
- Progression doesn't bypass course prerequisites
- Failed courses require resit resolution
- Active resits block progression
- System respects program semester structure
