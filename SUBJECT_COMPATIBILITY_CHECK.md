# Subject Compatibility Check for Program Changes

## Feature Overview
When changing a student's program, the system now validates that enrolled subjects are compatible with the new program. This prevents data integrity issues and ensures students don't lose subjects inadvertently.

## Implementation Date
November 13, 2025

## How It Works

### Validation Logic

When a staff member selects a different program for a student:

1. **System fetches** student's currently enrolled subjects
2. **System checks** if these subjects exist in the new program's curriculum
3. **System categorizes** subjects as:
   - **Compatible**: Subject exists in new program ✅
   - **Incompatible**: Subject does NOT exist in new program ❌

### Decision Rules

#### Scenario A: All Subjects Compatible
- **Result**: ✅ Program change **ALLOWED**
- **Action**: Shows success message
- **Display**: Green info box listing compatible subjects
- **Behavior**: Staff can proceed with enrollment
- **Note**: System handles subject management automatically (no manual transfer needed)

#### Scenario B: Some/All Subjects Incompatible
- **Result**: ❌ Program change **BLOCKED**
- **Action**: Disables "Enroll" button
- **Display**: Red critical error box with:
  - Number of incompatible subjects
  - Table listing each incompatible subject (code, title, credits)
  - Action required message
- **Required Action**: Staff must drop incompatible subjects first using Subject Add/Drop module
- **Behavior**: Cannot proceed until subjects are dropped

## User Interface

### Visual Indicators

#### When Compatible ✅
```
ℹ️ Good news: All 5 enrolled subject(s) exist in the new program and will be managed by the system.

Compatible Subjects:
- CS101 - Introduction to Programming (3 Credits)
- CS102 - Data Structures (4 Credits)
- ...
```

#### When Incompatible ❌
```
🚫 CRITICAL: Cannot Change Program

Cannot change program: Student has 3 enrolled subject(s) that do not exist in the new program. 
These subjects must be dropped first.

┌────────────┬─────────────────────────────┬─────────┐
│ Code       │ Subject Title               │ Credits │
├────────────┼─────────────────────────────┼─────────┤
│ BA201      │ Business Analytics          │ 3       │
│ BA202      │ Financial Accounting        │ 4       │
│ BA203      │ Marketing Principles        │ 3       │
└────────────┴─────────────────────────────┴─────────┘

⚠️ Action Required: Please drop these subjects using the Subject Add/Drop module 
before changing programs.

[Enroll Button - DISABLED - Grayed Out]
```

### Button States

**Enabled State** (Compatible):
- Green background
- Clickable
- Shows "Enroll" text

**Disabled State** (Incompatible):
- Gray background
- Not clickable
- Shows "Enroll" text
- Cursor changes to "not-allowed"
- Alert shown if clicked

## Technical Implementation

### Backend Validation

#### Service: `ProgramSwapService`

**New Method**: `checkSubjectCompatibility($enrolledSubjectIds, $newProgramId)`

**Logic**:
1. Query `program_subject` table for new program's subjects
2. Use `array_intersect()` to find compatible subjects
3. Use `array_diff()` to find incompatible subjects
4. Fetch subject details from `subjects` table
5. Return counts and details

**Returns**:
```php
[
    'compatible_count' => int,
    'incompatible_count' => int,
    'compatible_subjects' => array,
    'incompatible_subjects' => array
]
```

**Modified Method**: `validateProgramSwap()`

**Changes**:
- Calls `checkSubjectCompatibility()` when student has enrolled subjects
- If incompatible subjects found:
  - Sets `can_swap = false`
  - Adds to `blockers` array with severity "critical"
  - Includes action required message
- If all subjects compatible:
  - Adds to `info` array
  - Shows success message

#### Controller: `StudentSingleEnrollController`

**Modified Method**: `store()`

**Server-Side Validation**:
```php
if ($isProgramChange) {
    $validation = $swapService->validateProgramSwap($student->id, $request->program);
    
    if (!$validation['can_swap'] && !empty($validation['blockers'])) {
        // Show error and redirect back
        return redirect()->back()->withInput();
    }
}
```

**Purpose**: 
- Prevents form submission bypass
- Ensures validation even if JavaScript disabled
- Double-checks before database changes

### Frontend Validation

#### JavaScript: `displayValidationWarnings()`

**Enhanced Logic**:
```javascript
if (hasBlockers) {
    // Show critical error box
    // Display incompatible subjects in table
    // Show action required message
    // Disable enroll button
    $('#enrollButton').prop('disabled', true).addClass('disabled');
} else {
    // Enable enroll button
    $('#enrollButton').prop('disabled', false).removeClass('disabled');
}
```

#### JavaScript: `$('#enrollButton').on('click')`

**Button Click Handler**:
1. Checks if button is disabled
2. Shows alert if blockers present
3. Validates reason (if program changing)
4. Double-checks `can_swap` status
5. Only shows modal if validation passes

### Database Queries

**Check Subject Compatibility**:
```sql
-- Get subjects in new program
SELECT subject_id 
FROM program_subject 
WHERE program_id = :newProgramId

-- Check which enrolled subjects exist
SELECT id, code, title, credit_hour
FROM subjects
WHERE id IN (:enrolledSubjectIds)
  AND id IN (
    SELECT subject_id 
    FROM program_subject 
    WHERE program_id = :newProgramId
  )
```

## User Workflow

### Staff Process

#### When All Subjects Compatible:
1. Select student
2. Change program dropdown
3. ✅ See green success message
4. Fill in session, semester, section
5. Select subjects
6. Provide program change reason
7. Click "Enroll" button
8. Review confirmation modal
9. Confirm enrollment
10. ✅ Success!

#### When Subjects Incompatible:
1. Select student
2. Change program dropdown
3. ❌ See red critical error
4. Review table of incompatible subjects
5. Note which subjects to drop
6. **Navigate to Subject Add/Drop module**
7. **Drop incompatible subjects**
8. **Return to Single Enroll**
9. Select student again
10. Change program dropdown
11. ✅ Now see success (or fewer incompatible subjects)
12. Continue with enrollment

## Data Integrity

### What This Prevents

❌ **Without Validation**:
- Student enrolled in BA subjects switches to CS program
- BA subjects remain in enrollment record
- Student appears enrolled in subjects not in their program
- Reports show incorrect data
- Student can't access course materials
- Transcript has mixed programs

✅ **With Validation**:
- System detects BA subjects incompatible with CS program
- Blocks program change
- Forces staff to clean up enrollment first
- Ensures data consistency
- Prevents orphaned subject enrollments

### Database Relationships

```
students
  └─ student_enrolls (current)
       ├─ program_id (must be valid)
       └─ student_enroll_subject (pivot)
            └─ subject_id
                 └─ program_subject (must match program_id)
```

**Validation ensures**: `student_enroll.program_id` ↔️ `program_subject.program_id` alignment

## Testing Scenarios

### Test Case 1: No Enrolled Subjects
- **Given**: Student has no currently enrolled subjects
- **When**: Change program
- **Then**: No blocker, program change allowed

### Test Case 2: All Subjects Compatible
- **Given**: Student enrolled in CS101, CS102, MATH101
- **When**: Change to "Advanced CS Program" (has all three subjects)
- **Then**: Green success, program change allowed

### Test Case 3: Some Subjects Incompatible
- **Given**: Student enrolled in CS101, BA201, BA202
- **When**: Change to "Pure CS Program" (only has CS101)
- **Then**: Red blocker, shows BA201 and BA202 as incompatible

### Test Case 4: All Subjects Incompatible
- **Given**: Student enrolled in BA201, BA202, BA203
- **When**: Change to "CS Program" (has none of these)
- **Then**: Red blocker, shows all 3 subjects as incompatible

### Test Case 5: After Dropping Subjects
- **Given**: Student had incompatible subjects, staff dropped them
- **When**: Return and try program change again
- **Then**: Green success, program change allowed

### Test Case 6: JavaScript Disabled
- **Given**: User has JavaScript disabled
- **When**: Try to submit with incompatible subjects
- **Then**: Server-side validation catches it, shows error

## Files Modified

1. **`app/Services/ProgramSwapService.php`**
   - Added `checkSubjectCompatibility()` method
   - Modified `getCurrentEnrolledSubjects()` to include subject IDs
   - Modified `validateProgramSwap()` to check compatibility
   - Added blocker for incompatible subjects
   - Added info for compatible subjects

2. **`app/Http/Controllers/Admin/StudentSingleEnrollController.php`**
   - Added server-side validation before enrollment
   - Checks `can_swap` status
   - Blocks submission if incompatible subjects exist
   - Shows error message with blocker details

3. **`resources/views/admin/single-enroll/index.blade.php`**
   - Enhanced `displayValidationWarnings()` function
   - Added blocker display with table format
   - Added button disable/enable logic
   - Added click handler validation
   - Added CSS for disabled button state
   - Added double-check in button click handler

## Configuration

No configuration required. Feature works automatically when:
- Student has active enrollment
- Student has enrolled subjects
- Staff attempts to change program

## Future Enhancements (Optional)

1. **Subject Mapping**: Define equivalent subjects between programs
2. **Partial Transfer**: Auto-transfer compatible subjects, prompt for incompatible
3. **Bulk Drop**: Add "Drop All Incompatible" button
4. **Subject Suggestions**: Suggest replacement subjects in new program
5. **Approval Workflow**: Require approval for program changes with many incompatible subjects
6. **Audit Report**: Track which subjects were dropped during program changes
7. **Student Notification**: Auto-email student about program change and dropped subjects

## Troubleshooting

### Issue: Compatible subjects showing as incompatible
**Cause**: Subject not properly linked to new program in `program_subject` table
**Solution**: Add subject to new program's curriculum via Enroll Subject module

### Issue: Button remains disabled even with compatible subjects
**Cause**: Other blockers present (unpaid fees, etc.)
**Solution**: Resolve all blockers, validation re-checks on program change

### Issue: Validation not running
**Cause**: JavaScript error or route not found
**Solution**: Check browser console, verify route exists, clear caches

## Support

For questions or issues:
1. Verify subject is in new program's curriculum
2. Check JavaScript console for errors
3. Review Laravel logs for server-side errors
4. Confirm Subject Add/Drop module is accessible

---

**Documentation Version**: 1.0  
**Last Updated**: November 13, 2025  
**Feature Status**: Active ✅
