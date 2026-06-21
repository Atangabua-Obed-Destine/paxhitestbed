# "Don't Resit Course" Feature Implementation

## Overview
This feature allows students to explicitly **decline** resitting a failed course. Once they decline all failed courses (or pass resits for others), they become eligible for automatic semester progression without needing to retake those courses.

## Business Logic

### Previous Flow
1. Student fails a course (< 50%)
2. Student **must** request a resit OR remain stuck (no progression)
3. If they cancel a resit request, they can only request again

### New Flow
1. Student fails a course (< 50%)
2. Student has **two choices**:
   - **Request Resit**: Pay fee and retake the course
   - **Don't Resit Course**: Accept the failing grade and move forward
3. Once all failed courses are either:
   - Passed via resit, OR
   - Explicitly declined
4. Student is eligible for **automatic progression** to next semester

## Technical Implementation

### 1. New Workflow State Added
**File**: `app/Models/ResitRequest.php`

```php
public const STATE_DECLINED = 'declined'; // Student chose not to resit
```

This is a permanent state indicating the student made a conscious decision NOT to resit the course.

### 2. New Controller Method: `decline()`
**File**: `app/Http/Controllers/Student/ResitController.php`

**Route**: `POST /student/resit/decline`

**Logic**:
- Validates student enrollment and subject
- Checks if resit request already exists
- Creates a new ResitRequest with:
  - `workflow_state = 'declined'`
  - `fee_amount = 0` (no fee)
  - `payment_status = 'waived'`
  - `notes = 'Student declined to resit this course'`
- Triggers auto-progression check
- Shows success message

**Security**:
- Verifies enrollment belongs to logged-in student
- Prevents declining if active request exists (must cancel first)
- Prevents duplicate declines

### 3. Updated Auto-Progression Logic
**File**: `app/Services/Academic/SemesterProgressionService.php`

**Method**: `passedAllCourses(StudentEnroll $enrollment)`

**New Logic**:
```php
foreach ($subjects as $subject) {
    $marking = SubjectMarking::find(...);
    
    if (round($marking->total_marks) < 50) { // Failed
        // Check if student declined to resit
        $hasDeclinedResit = ResitRequest::where('workflow_state', STATE_DECLINED)
            ->exists();
        
        if (!$hasDeclinedResit) {
            return false; // Cannot progress yet
        }
    }
}
return true; // All courses passed OR declined
```

**Eligibility Criteria** (unchanged):
1. ✅ All course marks published
2. ✅ All courses either passed (≥50%) OR declined
3. ✅ No active resit requests (requested, awaiting_payment, etc.)
4. ✅ Next semester exists in program structure

### 4. Updated User Interface
**File**: `resources/views/student/resit/index.blade.php`

**Visual Changes**:

#### Failed Courses Table - Action Column
```php
// No existing request - Show BOTH buttons
<button class="btn btn-sm btn-primary">
    <i class="fas fa-paper-plane"></i> Request Resit
</button>

<button class="btn btn-sm btn-warning">
    <i class="fas fa-ban"></i> Don't Resit Course
</button>

// Already declined - Show message
<span class="text-muted">
    <i class="fas fa-ban"></i> Declined to Resit
</span>
```

#### Status Badge Colors
- **Declined**: Orange/Warning badge with text "Declined"
- Shows in status column alongside other states

### 5. Route Added
**File**: `routes/web.php`

```php
Route::post('resit/decline', 'ResitController@decline')->name('resit.decline');
```

## User Experience

### Student Perspective

#### Scenario 1: Student Fails Multiple Courses
1. Navigate to **Resit** page
2. Select session/semester with failed courses
3. See table with failed courses showing **two buttons**:
   - "Request Resit" (blue)
   - "Don't Resit Course" (orange/yellow)

#### Scenario 2: Student Declines All Failed Courses
1. Click "Don't Resit Course" for Course A
2. Confirmation: *"Are you sure you do NOT want to resit this course? This means you accept the failing grade and will move forward without retaking this course."*
3. Click OK
4. Success message: *"You have successfully declined to resit this course"*
5. Repeat for Course B, Course C, etc.
6. After declining last course: **Auto-progression notification** appears!
   - *"Congratulations! You have been automatically progressed to Semester 2. Please register your courses from the Course Registration page."*

#### Scenario 3: Mixed Approach
1. Student fails 3 courses: Math, Physics, Chemistry
2. **Requests resit** for Math (pays fee, takes exam)
3. **Declines** Physics and Chemistry
4. Once Math resit result is published (if passed):
   - System checks: Math passed ✅, Physics declined ✅, Chemistry declined ✅
   - **Auto-progression triggered!**

### Visual Indicators

#### Status Column Shows:
- **"Not Requested"** (light gray) - No action taken yet
- **"Requested"** (gray) - Resit requested, pending payment
- **"Awaiting Payment"** (yellow) - Fee assigned, waiting for payment
- **"Declined"** (orange) - Student chose not to resit
- **"Approved"** (blue) - Resit approved, waiting for schedule
- **"Scheduled"** (green) - Resit exam scheduled
- **"Cancelled"** (dark gray) - Student cancelled their request

#### Action Column Shows:
- **Two buttons** - If no request exists
- **"Cancel Request"** button - If request is cancellable
- **"Declined to Resit"** text - If already declined
- **"Cannot Cancel"** - If request past cancellable stage

## Database Impact

### ResitRequest Records
When student clicks "Don't Resit Course", a record is created:

```sql
INSERT INTO resit_requests (
    student_enroll_id,
    subject_id,
    session_id,
    workflow_state,
    fee_amount,
    payment_status,
    notes,
    state_changed_at
) VALUES (
    123,                    -- Student enrollment ID
    456,                    -- Subject ID
    789,                    -- Session ID
    'declined',             -- NEW STATE
    0.00,                   -- No fee
    'waived',               -- No payment required
    'Student declined to resit this course',
    NOW()
);
```

**Key Points**:
- No fee is generated (fee_amount = 0)
- Payment status is 'waived' (not applicable)
- Workflow state is 'declined' (permanent decision)
- Record exists to track the decision (for reporting/auditing)

## Reporting Implications

### Admin Reports Can Now Show:
1. **Students who declined resits** by semester
2. **Progression rate** with/without resits
3. **Course pass/fail/decline statistics**
4. **Financial impact**: Declined vs. Requested resits

### Example Query:
```sql
-- Count students who declined resits per semester
SELECT 
    s.title as semester,
    COUNT(DISTINCT rr.student_enroll_id) as students_declined
FROM resit_requests rr
JOIN student_enrolls se ON rr.student_enroll_id = se.id
JOIN semesters s ON se.semester_id = s.id
WHERE rr.workflow_state = 'declined'
GROUP BY s.id, s.title;
```

## Permissions & Security

### Existing Permissions Used:
- Students can only decline their own courses
- Admin cannot force a decline (student decision only)
- Once declined, cannot be undone (permanent decision)

### Validation Checks:
1. ✅ Student owns the enrollment
2. ✅ Subject exists in enrollment
3. ✅ No active resit request exists
4. ✅ Course actually failed (< 50%)

## Testing Scenarios

### Test Case 1: Decline Single Failed Course
1. Student fails Math (45%)
2. Navigate to Resit page
3. Click "Don't Resit Course" for Math
4. Verify:
   - Success message appears
   - Status shows "Declined"
   - Auto-progression notification (if no other failed courses)

### Test Case 2: Decline After Cancelling Request
1. Student requests resit for Physics
2. Student cancels the request
3. Student clicks "Don't Resit Course"
4. Verify:
   - New declined record created
   - Old cancelled record remains (audit trail)
   - Auto-progression check runs

### Test Case 3: Cannot Decline Active Request
1. Student requests resit for Chemistry
2. Fee is paid
3. Try to click "Don't Resit Course"
4. Verify:
   - Button not visible (only "Cannot Cancel" shown)
   - Must wait for resit completion or contact admin

### Test Case 4: Mixed Resit/Decline Progression
1. Student fails 3 courses: A, B, C
2. Request resit for A
3. Decline B and C
4. Verify:
   - No auto-progression (A still pending)
5. Complete resit for A (pass)
6. Verify:
   - Auto-progression triggered
   - Student moved to next semester

## Files Modified

### Core Logic
1. ✅ `app/Models/ResitRequest.php` - Added STATE_DECLINED constant
2. ✅ `app/Http/Controllers/Student/ResitController.php` - Added decline() method
3. ✅ `app/Services/Academic/SemesterProgressionService.php` - Updated passedAllCourses() logic

### Routes
4. ✅ `routes/web.php` - Added resit.decline route

### Views
5. ✅ `resources/views/student/resit/index.blade.php` - Added "Don't Resit Course" button and declined state display

## Migration Created
6. ✅ `database/migrations/2025_11_17_221144_add_declined_workflow_state_documentation.php` - Documentation migration

## Backward Compatibility

### Existing Data
- All existing resit requests remain unchanged
- Only new actions create declined records
- No migration needed (workflow_state already supports any string)

### Existing Features
- Cancel functionality unchanged
- Request resit functionality unchanged
- Payment flow unchanged
- Admin approval workflow unchanged

## Important Notes

### Decision is Permanent
Once a student clicks "Don't Resit Course":
- They **cannot undo** this decision
- The course remains failed on their transcript
- They accept the failing grade and move forward
- Admin cannot reverse (would need database intervention)

### Academic Implications
- **Failing grades remain on transcript**
- **GPA is affected** by the failing grade
- Student progresses with incomplete knowledge
- May affect graduation requirements depending on institution policy

### Use Cases
This feature is useful when:
- Student wants to **move forward** despite failures
- Course is not critical to degree requirements
- Student will retake in a future term
- Financial constraints prevent resit fees
- Time constraints make resitting impractical

## Success Metrics

### To Measure Implementation Success:
1. **Adoption Rate**: % of failed students who decline vs. request resit
2. **Progression Time**: Average days to progression (with/without declines)
3. **Financial Impact**: Lost resit fee revenue vs. faster student progression
4. **Completion Rate**: Do students who decline complete their degrees?

## Next Steps (Optional Enhancements)

### Future Improvements:
1. **Undo Mechanism**: Allow declining within 24 hours (with admin approval)
2. **Notification System**: Warn students of GPA impact before declining
3. **Advisor Approval**: Require academic advisor approval for declines
4. **Limit Declines**: Maximum number of declined courses per semester/program
5. **Report Dashboard**: Admin view of decline statistics and trends

---

## Quick Reference

### For Students:
- **Navigate**: Resit → Select Session/Semester
- **Decline**: Click orange "Don't Resit Course" button
- **Confirm**: Read warning and confirm decision
- **Result**: Automatic progression if all failures resolved

### For Admins:
- **View Declined**: Check resit_requests table where workflow_state = 'declined'
- **Reports**: Use CourseComplete or Transcript pages to see impact
- **Override**: Not possible via UI (requires database access)

### For Developers:
- **State**: ResitRequest::STATE_DECLINED
- **Method**: ResitController@decline()
- **Route**: POST /student/resit/decline
- **Progression**: SemesterProgressionService->passedAllCourses()

---

**Implementation Date**: November 17, 2025  
**Status**: ✅ Complete and Ready for Testing
