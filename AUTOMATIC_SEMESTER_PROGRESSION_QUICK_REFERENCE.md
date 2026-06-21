# Automatic Semester Progression - Quick Reference

## How It Works

### Student Automatically Progresses When:
1. ✅ ALL course marks are published
2. ✅ ALL courses passed (≥50%)
3. ✅ NO active resit requests
4. ✅ Next semester exists in program structure

### Progression Happens Automatically:
- **When**: After admin publishes last course marks
- **What**: Creates new enrollment for next semester
- **Note**: Student must still register courses manually

## Student Actions

### View Resit Requests:
```
📍 http://localhost/paxhitest/student/resit
📍 http://localhost/paxhitest/student/resit/history
```

### Cancel Resit Request:
- **Can Cancel**: `Requested`, `Awaiting Payment` states
- **Cannot Cancel**: `Approved`, `Scheduled`, `Rejected` states
- **Effect**: May trigger automatic progression if all courses passed

### Check Enrollment Status:
```
📍 http://localhost/paxhitest/student/dashboard
📍 http://localhost/paxhitest/student/course-registration
```

## Admin Actions

### Publish Course Marks:
```
📍 http://localhost/paxhitest/admin/exam/subject-marking
```
**Result**: Triggers automatic progression check

### Verify Progression:
```sql
-- Check student enrollments
SELECT * FROM student_enrolls 
WHERE student_id = ? 
ORDER BY semester_id DESC;
```

### Check Logs:
```bash
tail -f storage/logs/laravel.log | grep "progression"
```

## Semester Progression Path

### Undergraduate (4 years, 8 semesters):
```
Year 1, First Semester  (year=1, type=1)
Year 1, Second Semester (year=1, type=2)
Year 2, First Semester  (year=2, type=1)
Year 2, Second Semester (year=2, type=2)
Year 3, First Semester  (year=3, type=1)
Year 3, Second Semester (year=3, type=2)
Year 4, First Semester  (year=4, type=1)
Year 4, Second Semester (year=4, type=2)
```

## Troubleshooting

### Student Not Progressing?

**Check 1**: All marks published?
```php
Visit: admin/exam/subject-marking
Verify: All courses show "Published" status
```

**Check 2**: Any failed courses?
```php
Check: Student transcript
Look for: Any grade < 50%
```

**Check 3**: Active resit requests?
```php
Visit: student/resit/history
Check: Any request in Requested/Awaiting Payment/Approved/Scheduled state
Action: Student must cancel all resit requests
```

**Check 4**: Next semester configured?
```php
Visit: admin/academic/enroll-subject
Verify: Next semester has courses assigned for the program
```

## Key Rules

### Progression Requirements:
- ✅ 50% minimum in ALL courses
- ✅ Zero active resit requests
- ✅ All exam types published
- ✅ Publish date/time passed
- ✅ Next semester exists
- ✅ Next semester has enrolled subjects

### Resit Cancellation:
- 🟢 **Cancellable**: Requested, Awaiting Payment
- 🔴 **Not Cancellable**: Finance Review, Approved, Scheduled, Rejected

### Automatic Process:
- 🤖 Runs automatically on mark publish
- 🤖 Runs automatically on resit cancel
- 🤖 Creates enrollment silently
- 🤖 Logs all progression events
- 📧 Shows notification to student

## Important Notes

### ⚠️ What System DOES:
- ✅ Creates enrollment for next semester
- ✅ Maintains same matricule
- ✅ Sets enrollment as Active
- ✅ Logs progression event
- ✅ Shows notification

### ⚠️ What System DOES NOT:
- ❌ Register courses automatically
- ❌ Skip prerequisites
- ❌ Bypass failed course requirements
- ❌ Allow progression with active resits
- ❌ Create duplicate enrollments

## Quick Commands

### Find Student Progression History:
```sql
SELECT 
    se.id,
    se.matricule,
    p.title as program,
    sem.title as semester,
    se.created_at as enrolled_on
FROM student_enrolls se
JOIN programs p ON p.id = se.program_id
JOIN semesters sem ON sem.id = se.semester_id
WHERE se.student_id = ?
ORDER BY sem.year ASC, sem.semester_type ASC;
```

### Check Failed Courses:
```sql
SELECT 
    s.code,
    s.title,
    sm.total_marks,
    g.title as grade
FROM subject_marking sm
JOIN subjects s ON s.id = sm.subject_id
LEFT JOIN grades g ON sm.total_marks BETWEEN g.min_mark AND g.max_mark
WHERE sm.student_enroll_id = ?
AND ROUND(sm.total_marks) < 50;
```

### Find Blocking Resit Requests:
```sql
SELECT 
    rr.id,
    s.code,
    s.title,
    rr.workflow_state,
    rr.payment_status
FROM resit_requests rr
JOIN subjects s ON s.id = rr.subject_id
WHERE rr.student_enroll_id = ?
AND rr.workflow_state IN ('requested', 'awaiting_payment', 'finance_review', 'approved', 'scheduled');
```

## Contact & Support

### For Implementation Issues:
- Check: `AUTOMATIC_SEMESTER_PROGRESSION_DOCUMENTATION.md`
- Review: Laravel logs in `storage/logs/laravel.log`
- Debug: Enable query logging for database checks

### For Academic Policy Questions:
- Review eligibility rules in documentation
- Verify semester configuration
- Check program semester assignments
- Confirm enroll_subject data

## Version Information

**Feature**: Automatic Semester Progression
**Implemented**: November 2025
**Components**: Service, Controllers, Views, Routes
**Dependencies**: Existing semester/enrollment/marking system
