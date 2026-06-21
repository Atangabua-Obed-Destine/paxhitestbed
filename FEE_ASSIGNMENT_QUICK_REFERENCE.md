# Fee Assignment Quick Reference

## Overview
The system now has **TWO different fee assignment strategies** based on how students enter the institution:

---

## 1. Regular Enrollment (Year-Based)

### When Used:
- New student registration (`/admin/student/create`)
- Application approval (`/admin/application`)
- Semester progression (`/admin/progression`)

### Logic:
**Assigns fees for ALL semesters of the academic year at once**

### Example:
```
Student enrolls in Year 1, First Semester
→ Gets fees for:
  ✓ Year 1 First Semester (30 days due)
  ✓ Year 1 Second Semester (60 days due)
```

### Implementation:
- `StudentController::autoAssignProgramSemesterFees()`
- `ApplicationController::autoAssignProgramSemesterFees()`
- `SemesterProgressionService::autoAssignProgramSemesterFees()`

---

## 2. Transfer-In Enrollment (Smart Filtering)

### When Used:
- Student transfer-in (`/admin/admission/student-transfer-in`)

### Logic:
**Assigns fees ONLY for current and future semesters (skips past semesters)**

### Example:
```
Student transfers into Year 1, Second Semester
→ Gets fees for:
  ✗ Year 1 First Semester (SKIPPED - already completed)
  ✓ Year 1 Second Semester (30 days due)
```

### Implementation:
- `StudentTransferInController::autoAssignTransferInFees()`

---

## Comparison Table

| Scenario | Regular Enrollment | Transfer-In |
|----------|-------------------|-------------|
| **Enroll in Y1 Sem 1** | Y1 Sem 1 + Y1 Sem 2 | Y1 Sem 1 + Y1 Sem 2 |
| **Enroll in Y1 Sem 2** | Y1 Sem 1 + Y1 Sem 2 | Y1 Sem 2 ONLY ✓ |
| **Enroll in Y2 Sem 1** | Y2 Sem 1 + Y2 Sem 2 | Y2 Sem 1 + Y2 Sem 2 |
| **Enroll in Y2 Sem 2** | Y2 Sem 1 + Y2 Sem 2 | Y2 Sem 2 ONLY ✓ |

---

## Key Difference

### Regular: "Assign all year fees"
- Assumption: Student will attend all semesters of the year
- Use: Normal enrollment workflow

### Transfer-In: "Skip past semesters"
- Assumption: Student already completed past semesters elsewhere
- Use: Transfer student workflow

---

## Testing

### Test Regular Enrollment:
```bash
# Shows year-based assignment for all semesters
php test_year_based_fees.php
```

### Test Transfer-In:
```bash
# Shows smart filtering that skips past semesters
php test_transfer_in_fees.php
```

### Compare Both:
```bash
# Side-by-side comparison
php compare_fee_assignment.php
```

---

## Configuration

Both approaches use the same fee configuration:
- **Location:** `/admin/program-semester-fee`
- **Setup:** Configure fees for each semester individually
- **Applies to:** Both regular and transfer students

The difference is in **which fees are assigned**, not the fee amounts.

---

## Due Dates

### Regular Enrollment:
- First semester of year: 30 days
- Second semester of year: 60 days
- Third+ semester: 60 days

### Transfer-In:
- Current enrollment semester: 30 days
- Future semesters: 60 days
- Past semesters: Not assigned (skipped)

---

## Notes Field

### Regular:
```
"Auto-assigned for SECOND SEMESTER Y1 (Year 1)"
```

### Transfer-In:
```
"Auto-assigned for SECOND SEMESTER Y1 (Year 1) - Transfer In"
```

The "Transfer In" marker helps identify transfer students.

---

## Common Questions

**Q: Why do transfer students get fewer fees?**
A: They already completed past semesters at another institution.

**Q: What if a transfer student enrolls at the start of a year?**
A: They get the same fees as regular students (all year semesters).

**Q: Can I manually add fees for past semesters?**
A: Yes, use `/admin/fees-student` to manually add fees if needed.

**Q: What about resit semesters?**
A: Both approaches skip resit semesters (no auto-assignment).

---

## Summary

✅ **Regular Enrollment** = Year-based (all semesters)
✅ **Transfer-In** = Smart filtering (current + future only)
✅ Both prevent duplicates
✅ Both use configurable fees
✅ Both have comprehensive logging
