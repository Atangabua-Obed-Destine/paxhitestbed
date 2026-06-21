# Resit Inheritance Quick Reference

## What Gets Inherited?

| Data Type | Inherited? | Details |
|-----------|-----------|---------|
| StudentAttendance | ✅ YES | All class attendance records |
| CA Exam Marks | ✅ YES | Exams where `is_final = 0` |
| Exam Attendance | ✅ YES | Individual exam attendance |
| Final Exam Marks | ❌ NO | Student must retake |
| SubjectMarking | ✅ YES | Created in DRAFT with CA components |
| SubjectMarkingExamStates | ✅ YES | CA exam states (PUBLISHED), no final exam state |

## Key Requirements

1. **Resit semester** must have `parent_semester_id` set
2. **ExamType** records must have `is_final` properly set:
   - `is_final = 0`: CA components (inherited)
   - `is_final = 1`: Final exams (NOT inherited)
3. **Parent enrollment** must exist for same student/program

## How to Use

```php
$service = new SemesterProgressionService();

$resitEnrollment = $service->progressToResitSemester(
    $parentEnrollment,    // Current enrollment
    $resitSemester,       // Must have parent_semester_id
    $sessionId,           // Session for resit
    $scheduledCourses     // Array of ['subject_id' => X]
);
```

**Inheritance happens automatically** inside this method.

## What Teachers See

**Before Final Exam:**
- SubjectMarking: DRAFT state
- Attendance: ✅ (inherited)
- CA Marks: ✅ (inherited)
- Exam Marks: 0 (pending)
- Total: Partial

**After Final Exam:**
- Teacher enters final exam marks
- System calculates total
- Teacher publishes result

## What Students See

- Previous CA marks (read-only)
- Previous attendance (read-only)
- Final exam: PENDING
- Total: Partial (until final exam graded)

## Verification Checklist

After progression, verify:
- [ ] Resit enrollment created
- [ ] Attendance records present
- [ ] CA exam marks present  
- [ ] Final exam marks ABSENT
- [ ] SubjectMarking in DRAFT
- [ ] No duplicate records

## Testing

Run test script:
```bash
php test_direct_inheritance.php
```

Expected output:
```
✅ ALL INHERITANCE WORKING CORRECTLY!
- Resit attendance: 2 (expected 2)
- Resit CA exams: 1 (expected 1)
- Resit final exams: 0 (expected 0)
```

## Troubleshooting

### No data inherited?
1. Check `parent_semester_id` is set
2. Check parent enrollment exists
3. Check parent has data to inherit
4. Check Laravel logs

### Wrong data inherited?
1. Verify `ExamType.is_final` values
2. Check exam type assignments on exam records

### Duplicates?
- Should NOT happen (duplicate prevention built-in)
- If happens, check logs for errors

## Log Locations

Check `storage/logs/` for:
- `Successfully inherited parent semester data` (info)
- Warning messages about missing data
- Error messages with stack traces

## Database Queries

Check inherited data:
```sql
-- Inherited attendance
SELECT * FROM student_attendances 
WHERE student_enroll_id = <resit_enrollment_id>
AND note LIKE '%Inherited from parent%';

-- Inherited CA marks
SELECT e.* FROM exams e
JOIN exam_types et ON e.exam_type_id = et.id
WHERE e.student_enroll_id = <resit_enrollment_id>
AND et.is_final = 0;

-- Should be ZERO final exams
SELECT COUNT(*) FROM exams e
JOIN exam_types et ON e.exam_type_id = et.id
WHERE e.student_enroll_id = <resit_enrollment_id>
AND et.is_final = 1;
-- Result should be 0
```

## Common Scenarios

### Scenario 1: Student Failed Final Only
- **Parent**: CA: 30/40, Final: 20/60, Total: 50/100 (FAIL)
- **Resit**: CA: 30/40 (inherited), Final: TBD
- **Outcome**: Student retakes final, needs 20+ to pass

### Scenario 2: Student Failed Everything
- **Parent**: CA: 10/40, Final: 15/60, Total: 25/100 (FAIL)
- **Resit**: CA: 10/40 (inherited), Final: TBD
- **Outcome**: Student retakes final, needs 40+ to pass (unlikely)

### Scenario 3: No Parent Data
- **Parent**: New enrollment, no marks yet
- **Resit**: Nothing to inherit
- **Outcome**: Student starts fresh (same as before feature)

## Feature Status

✅ **IMPLEMENTED & TESTED**

- Code: `app/Services/Academic/SemesterProgressionService.php`
- Tests: `test_direct_inheritance.php` ✅ PASSED
- Docs: `RESIT_INHERITANCE_DOCUMENTATION.md`

## Support

For issues, check:
1. This quick reference
2. Full documentation: `RESIT_INHERITANCE_DOCUMENTATION.md`
3. System analysis: `ACADEMIC_SYSTEM_COMPREHENSIVE_ANALYSIS.md`
4. Laravel logs: `storage/logs/`
