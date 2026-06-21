# Course Registration - Quick Reference Guide

## For Students

### How It Works Now

When you visit **http://localhost/paxhitest/student/course-registration**, the system will:

1. **Check Your Current Semester**
   - If it's a **Resit Semester**: ⛔ You cannot register courses yourself
   - If it's a **Normal Semester**: ✅ You can register appropriate courses

2. **Show Only Relevant Courses**
   - ✓ Courses from your current semester
   - ✓ Failed courses from previous years (same semester type, marks < 50%)
   - ✗ Courses you've already passed (marks ≥ 50%)
   - ✗ Wrong semester type courses

### Example Scenarios

#### Scenario A: Year 2, First Semester
**You're in**: Year 2, First Semester, Section A

**You can register**:
- All Year 2, First Semester courses
- Year 1, First Semester courses (only if you failed them)

**You CANNOT register**:
- Second Semester courses (any year)
- First Semester courses you already passed

#### Scenario B: Resit Semester
**You're in**: 1st Resit Semester Y2

**Result**: 🚫 Course registration blocked
- You'll see a warning message
- Registration form is hidden
- Contact admin for course assignments

### Understanding the Display

#### Subject Dropdown Shows:
```
ACC1101H - Principles of Accounting (Compulsory, 3 credits)
ENG1101H - Functional English I (Optional, 2 credits)
PHI1101 - The Human Person (University Requirement, 2 credits)
```

#### Subject Types:
- **Compulsory**: Required for graduation
- **Optional**: Choose based on interests
- **University Requirement**: Required by university policy

---

## For Administrators

### Setting Up Course Registration

#### Step 1: Configure Semesters
**Location**: http://localhost/paxhitest/admin/academic/semester

**Important Fields**:
- **Year**: Academic year level (1, 2, 3, 4)
- **Semester Type**: 
  - 1 = First Semester
  - 2 = Second Semester
- **Is Resit**: Check this for resit semesters

#### Step 2: Assign Courses to Semesters
**Location**: http://localhost/paxhitest/admin/academic/enroll-subject

**How it works**:
1. Select Program
2. Select Semester
3. Select Section
4. Choose subjects for that combination

**Example**:
```
Program: HND ACCOUNTANCY
Semester: FIRST SEMESTER Y1
Section: General
Subjects: [Select all Year 1, First Semester courses]
```

#### Step 3: Student Enrollment
Students must be enrolled in a semester before they can register courses.

### Semester Type Logic

#### First Semester (type=1):
- Shows Year 1 First Semester courses
- Shows Year 2 First Semester courses
- Shows Year 3 First Semester courses
- **Never shows Second Semester courses**

#### Second Semester (type=2):
- Shows Year 1 Second Semester courses
- Shows Year 2 Second Semester courses
- Shows Year 3 Second Semester courses
- **Never shows First Semester courses**

### Resit Semester Policy

When `is_resit = 1`:
- ✅ Students can VIEW their registered courses
- ✅ Students can see their marks
- ❌ Students CANNOT register new courses
- ❌ Students CANNOT drop courses
- 👤 **Admin must handle all course assignments**

**Use Case**: During resit periods, only students who failed can retake specific courses. Admins manually assign these via enroll-subject.

---

## For Developers

### Key Logic Points

#### Filtering Query (Simplified):
```php
// 1. Get current semester info
$currentSemesterType = $currentEnroll->semester->semester_type;
$currentYear = $currentEnroll->semester->year;

// 2. Find all semesters of same type, same or lower year
$eligibleSemesters = Semester::where('semester_type', $currentSemesterType)
    ->where('year', '<=', $currentYear)
    ->get();

// 3. Get subjects for those semesters
$eligibleSubjects = EnrollSubject::whereIn('semester_id', $eligibleSemesters->pluck('id'))
    ->where('program_id', $currentEnroll->program_id)
    ->where('section_id', $currentEnroll->section_id)
    ->get();

// 4. Filter out validated courses
$validatedSubjectIds = SubjectMarking::whereHas('studentEnroll', ...)
    ->where('total_marks', '>=', 50)
    ->pluck('subject_id');

$availableSubjects = $eligibleSubjects->whereNotIn('id', $validatedSubjectIds);
```

#### Validation Check:
```php
// A course is "validated" when:
total_marks >= 50

// This means:
- 50% or higher = Passed/Validated
- Below 50% = Failed/Not Validated
```

#### Resit Semester Check:
```php
// Block registration if:
$currentEnroll->semester->is_resit == 1

// This blocks:
- Course registration (add)
- Course dropping (remove)
- All student modifications
```

---

## Database Schema Reference

### semesters table:
```sql
CREATE TABLE semesters (
    id INT PRIMARY KEY,
    title VARCHAR(191),
    year INT,                    -- Academic year: 1, 2, 3, 4
    semester_type TINYINT,       -- 1=First, 2=Second
    is_resit BOOLEAN,            -- 0=Normal, 1=Resit
    status BOOLEAN,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### enroll_subjects table:
```sql
CREATE TABLE enroll_subjects (
    id INT PRIMARY KEY,
    program_id INT,
    semester_id INT,
    section_id INT,
    -- Pivot: enroll_subject_subject links to subjects
);
```

### subject_markings table:
```sql
CREATE TABLE subject_markings (
    id INT PRIMARY KEY,
    student_enroll_id INT,
    subject_id INT,
    total_marks DECIMAL,         -- Used for validation check (>= 50)
    -- Other mark components...
);
```

---

## Testing Checklist

### Before Deployment:
- [ ] Test with Year 1 student (no previous courses)
- [ ] Test with Year 2+ student (has validated courses)
- [ ] Test with student in First Semester
- [ ] Test with student in Second Semester
- [ ] Test with student in Resit Semester
- [ ] Test credit hour limit enforcement
- [ ] Test dropping courses
- [ ] Test with no assigned courses (empty state)
- [ ] Test with all courses validated (empty state)
- [ ] Verify course type labels display correctly

### SQL Verification Queries:

```sql
-- Check semester configuration
SELECT id, title, year, semester_type, is_resit FROM semesters ORDER BY year, semester_type;

-- Check course assignments
SELECT 
    p.title AS program,
    s.title AS semester,
    sec.title AS section,
    COUNT(ess.subject_id) AS subject_count
FROM enroll_subjects es
JOIN programs p ON es.program_id = p.id
JOIN semesters s ON es.semester_id = s.id
JOIN sections sec ON es.section_id = sec.id
JOIN enroll_subject_subject ess ON es.id = ess.enroll_subject_id
GROUP BY es.id;

-- Check student validated courses
SELECT 
    st.student_id,
    st.first_name,
    st.last_name,
    s.code,
    s.title,
    sm.total_marks,
    CASE WHEN sm.total_marks >= 50 THEN 'Validated' ELSE 'Not Validated' END AS status
FROM students st
JOIN student_enrolls se ON st.id = se.student_id
JOIN subject_markings sm ON se.id = sm.student_enroll_id
JOIN subjects s ON sm.subject_id = s.id
WHERE st.id = ?
ORDER BY sm.total_marks DESC;
```

---

## Troubleshooting

### Problem: No courses showing
**Check**:
1. Is student enrolled? `student_enrolls.status = 1`
2. Is semester a resit? `semesters.is_resit = 0`
3. Are courses assigned? Check `enroll_subjects` table
4. Are all courses validated? Check `subject_markings.total_marks`

### Problem: Wrong courses showing
**Check**:
1. Semester type matches: `semesters.semester_type`
2. Year logic: Should show current year and below
3. Section matches: `enroll_subjects.section_id`

### Problem: Can't drop courses
**Check**:
1. Is it a resit semester? `semesters.is_resit = 1` blocks drops
2. Is course actually registered? Check `student_enroll_subject`

---

## Key URLs

- **Student Registration**: http://localhost/paxhitest/student/course-registration
- **Admin Semester Setup**: http://localhost/paxhitest/admin/academic/semester
- **Admin Course Assignment**: http://localhost/paxhitest/admin/academic/enroll-subject
- **Admin Student Enrollment**: http://localhost/paxhitest/admin/student/enroll

---

**Last Updated**: November 3, 2025
