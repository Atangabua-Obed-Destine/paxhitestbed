# Program Swap Implementation Documentation

## Overview
This document describes the comprehensive program swap validation and tracking system implemented for the single-enroll module. The system provides safeguards, warnings, and audit trails when staff changes a student's program during enrollment.

## Date Implemented
November 13, 2025

## Problem Statement
Previously, the single-enroll system allowed program changes without:
- Validation or warnings about consequences
- Tracking of why programs were changed
- Checking for unpaid fees, enrolled subjects, or academic records
- Comparing credit requirements between programs
- Audit trail for program changes

## Solution Components

### 1. Database Changes

#### Migration: `2025_11_13_082329_add_program_change_reason_to_student_enrolls`
Added fields to `student_enrolls` table:
- `previous_program_id` (integer, nullable) - References the program student was changing from
- `program_change_reason` (text, nullable) - Staff-provided reason for the change
- `is_program_change` (boolean, default 0) - Flag indicating if this enrollment involved a program change

### 2. Program Swap Validation Service

#### File: `app/Services/ProgramSwapService.php`

**Purpose**: Comprehensive validation service that checks all aspects of a program change.

**Key Method**: `validateProgramSwap($studentId, $newProgramId)`

**Returns**:
```php
[
    'can_swap' => bool,
    'is_program_change' => bool,
    'warnings' => array,    // Issues that should be reviewed
    'blockers' => array,    // Critical issues (none currently, but extensible)
    'info' => array,        // General information about the change
    'old_program' => Program,
    'new_program' => Program,
]
```

**Validation Checks**:

1. **Unpaid Fees**
   - Checks all student enrollments for unpaid fees
   - Calculates total due amount
   - Shows category, semester, amount, and due date for each unpaid fee
   - Severity: HIGH

2. **Pending Payment Receipts**
   - Checks for payment receipts with 'pending' verification status
   - Shows total amount pending verification
   - Severity: MEDIUM

3. **Active Payment Plans**
   - Checks for active installment payment plans
   - Shows installment progress and next due dates
   - Severity: HIGH

4. **Currently Enrolled Subjects**
   - Lists all subjects in current enrollment
   - Shows total credit hours
   - Warns about potential loss of progress
   - Severity: HIGH

5. **Recorded Attendance**
   - Counts attendance records in current enrollment
   - Severity: MEDIUM

6. **Exam Marks**
   - Lists subjects with recorded exam marks
   - Shows total marks for transparency
   - Severity: HIGH

7. **Assignment Submissions**
   - Counts assignment submissions in current enrollment
   - Severity: MEDIUM

8. **Credit Limit Comparison**
   - Compares max credit limits between old and new programs
   - Considers program, session, and faculty configurations
   - Shows if limits differ

9. **Faculty Change Detection**
   - Detects if student is moving to different faculty
   - Shows old and new faculty names

### 3. Controller Enhancements

#### File: `app/Http/Controllers/Admin/StudentSingleEnrollController.php`

**New Method**: `validateProgramSwap(Request $request)`
- AJAX endpoint for real-time validation
- Returns JSON with validation results
- Route: `POST /admin/student/single-enroll/validate-program-swap`

**Enhanced Method**: `store(Request $request)`

**Changes**:
1. Added validation for `program_change_reason` (required if program changes)
2. Detects program change by comparing student's current program_id with selected program
3. Stores program change tracking data:
   - `previous_program_id`
   - `is_program_change`
   - `program_change_reason`
4. Shows appropriate success message based on whether program changed
5. Added rollback in catch block
6. Added detailed error logging

### 4. Model Updates

#### File: `app/Models/StudentEnroll.php`

**Added to $fillable**:
- `previous_program_id`
- `program_change_reason`
- `is_program_change`

**New Relationship**:
```php
public function previousProgram()
{
    return $this->belongsTo(Program::class, 'previous_program_id');
}
```

### 5. UI Enhancements

#### File: `resources/views/admin/single-enroll/index.blade.php`

**Visual Indicators**:

1. **Program Change Alert** (shown when program changes)
   - Yellow warning banner
   - Explains that program change requires justification

2. **Dynamic Validation Warnings Section**
   - Fetched via AJAX when program changes
   - Shows:
     - Critical issues (blockers) - red alerts
     - Important warnings - yellow alerts
     - General information - blue alerts
   - Expandable details for each warning type

3. **Program Change Reason Field**
   - Text area (3 rows)
   - Required when program changes
   - Hidden when continuing in same program
   - Placeholder text guides staff
   - Audit trail notation

**JavaScript Functionality**:

1. **Program Change Detection**
   ```javascript
   $('#program').on('change', function() {
       // Compares with original program
       // Shows/hides warnings accordingly
   })
   ```

2. **Real-time Validation**
   ```javascript
   function fetchProgramValidation(programId) {
       // AJAX call to validation endpoint
       // Displays results dynamically
   }
   ```

3. **Enhanced Confirmation Modal**
   - Dynamic title (changes for program swaps)
   - Dynamic header color (red for program changes)
   - Shows all validation warnings
   - Shows provided reason
   - Maintains original enrollment check content

#### File: `resources/views/admin/single-enroll/confirm.blade.php`

**Changes**:
- Added IDs to modal header and body for dynamic updates
- Made modal title dynamic with span
- Modal now populated by JavaScript based on enrollment type

### 6. Route Changes

#### File: `routes/web.php`

**Added Route**:
```php
Route::post('student/single-enroll/validate-program-swap', 'StudentSingleEnrollController@validateProgramSwap')
    ->name('single-enroll.validate-swap');
```

**Placement**: Before the resource route to avoid conflicts

## User Workflow

### Normal Enrollment (Same Program)
1. Staff selects student
2. Fills in session, semester, section, subjects
3. Clicks "Enroll" button
4. Confirmation modal shows standard checks
5. Staff confirms enrollment

### Program Change Enrollment
1. Staff selects student
2. **Changes program dropdown**
3. **Yellow warning banner appears**
4. **System fetches validation data via AJAX**
5. **Detailed warnings displayed:**
   - Unpaid fees
   - Enrolled subjects
   - Exam marks
   - Attendance records
   - Payment plans
   - Credit limit changes
   - Faculty changes
6. **Program change reason field appears (required)**
7. Staff must provide detailed reason
8. Fills in session, semester, section, subjects
9. Clicks "Enroll" button
10. **Enhanced modal shows:**
    - Red header (danger)
    - "Program Change Confirmation" title
    - All validation warnings repeated
    - Provided reason
    - Standard enrollment checks
11. Staff reviews carefully and confirms
12. System:
    - Creates new enrollment with new program_id
    - Stores previous_program_id
    - Stores program_change_reason
    - Sets is_program_change = true
    - Updates student's program_id
    - Shows success message indicating program change

## Data Integrity

### Historical Accuracy
- Each `student_enroll` record maintains its own `program_id` (historical record)
- Previous enrollments remain unchanged
- `previous_program_id` tracks the change
- `program_change_reason` provides audit trail

### Query Examples

**Find all program changes**:
```sql
SELECT * FROM student_enrolls WHERE is_program_change = 1;
```

**Find students who changed from specific program**:
```sql
SELECT * FROM student_enrolls WHERE previous_program_id = 5;
```

**Get program change history for student**:
```sql
SELECT se.id, se.created_at, 
       old_prog.title as old_program, 
       new_prog.title as new_program,
       se.program_change_reason
FROM student_enrolls se
LEFT JOIN programs old_prog ON se.previous_program_id = old_prog.id
LEFT JOIN programs new_prog ON se.program_id = new_prog.id
WHERE se.student_id = 12133 AND se.is_program_change = 1
ORDER BY se.created_at DESC;
```

## Validation Severity Levels

### HIGH (Red - Critical)
- Unpaid fees
- Active payment plans
- Enrolled subjects with credit hours
- Exam marks recorded

These indicate significant financial or academic implications.

### MEDIUM (Yellow - Important)
- Pending payment verifications
- Attendance records
- Assignment submissions

These may require administrative coordination.

### INFO (Blue - Informational)
- Credit limit changes
- Faculty changes
- Program name change

These are informational for staff awareness.

## Security & Permissions

- Existing permission check maintained: `student-enroll-single`
- Only authorized staff can access single-enroll
- All program changes logged in audit trail (via Auditable trait)
- Reason field cannot be bypassed (required validation)

## Testing Checklist

### Scenario 1: Normal Enrollment (No Program Change)
- [ ] No warnings shown
- [ ] No reason field required
- [ ] Standard confirmation modal
- [ ] Enrollment succeeds
- [ ] `is_program_change` = 0

### Scenario 2: Program Change (Same Faculty)
- [ ] Warning banner appears
- [ ] Validation data fetched
- [ ] Warnings displayed correctly
- [ ] Reason field required
- [ ] Cannot submit without reason
- [ ] Enhanced modal shown
- [ ] Enrollment succeeds with tracking

### Scenario 3: Program Change (Different Faculty)
- [ ] Faculty change info shown
- [ ] All other validations work
- [ ] Tracking data saved correctly

### Scenario 4: Program Change with Unpaid Fees
- [ ] Unpaid fees warning shown
- [ ] Fee details listed (category, amount, due date)
- [ ] Staff can still proceed (warning, not blocker)
- [ ] Reason required

### Scenario 5: Program Change with Enrolled Subjects
- [ ] Subject list shown with credit hours
- [ ] Warning about potential progress loss
- [ ] Staff can proceed with reason

### Scenario 6: Program Change with Exam Marks
- [ ] Exam marks warning shown
- [ ] Subject details listed
- [ ] High severity warning displayed

### Scenario 7: JavaScript Disabled
- [ ] Form still functional (falls back to server-side)
- [ ] Validation on submission
- [ ] Reason field still required

## Future Enhancements (Optional)

1. **Blocker Conditions**: Add actual blocking conditions (e.g., failed prerequisites)
2. **Subject Transfer**: Auto-transfer compatible subjects to new program
3. **Email Notifications**: Notify student of program change
4. **Approval Workflow**: Require dean/registrar approval for program changes
5. **Credit Transfer Matrix**: Define which subjects transfer between programs
6. **Fee Adjustment**: Auto-adjust fees when program changes
7. **Report**: Program change analytics dashboard
8. **Student Portal**: Allow students to request program changes
9. **Document Upload**: Require supporting documents for program changes
10. **Rollback Feature**: Allow reversing program change within timeframe

## Files Modified

1. `app/Services/ProgramSwapService.php` - NEW
2. `app/Http/Controllers/Admin/StudentSingleEnrollController.php` - MODIFIED
3. `app/Models/StudentEnroll.php` - MODIFIED
4. `resources/views/admin/single-enroll/index.blade.php` - MODIFIED
5. `resources/views/admin/single-enroll/confirm.blade.php` - MODIFIED
6. `routes/web.php` - MODIFIED
7. `database/migrations/2025_11_13_082329_add_program_change_reason_to_student_enrolls.php` - NEW

## Maintenance Notes

- Keep `ProgramSwapService` validation methods in sync with business rules
- Update severity levels as institutional policies change
- Review program change reasons periodically for common patterns
- Consider adding validation for specific program-to-program transitions
- Monitor database size of `program_change_reason` field (currently TEXT type)

## Support Information

For questions or issues:
1. Check this documentation first
2. Review validation service logic in `ProgramSwapService.php`
3. Check JavaScript console for AJAX errors
4. Review Laravel logs for server-side errors
5. Verify permissions are set correctly

## Success Metrics

To measure effectiveness:
- Count program changes with `is_program_change = 1`
- Review program change reasons for quality
- Monitor if unpaid fees decrease after warnings
- Track if staff-provided reasons are meaningful
- Verify data integrity with historical queries

---

**Documentation Version**: 1.0  
**Last Updated**: November 13, 2025  
**Implemented By**: AI Assistant  
**Reviewed By**: Pending
