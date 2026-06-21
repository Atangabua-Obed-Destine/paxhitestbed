# Excel vs System Data Model Comparison

## Excel File: FBMS-HND First Semester CA & EXAM Sheet.xlsx

### Sheet Structure Overview

| Sheet Name | Purpose | System Equivalent |
|------------|---------|-------------------|
| **FBMS-HND LEVEL ONE** | Course mark entry (per course, per student) | `course-marksheet` in ResultsSummaryController |
| **FBMS-HND LEVEL TWO** | Same for Level 2 | `course-marksheet` |
| **Sheet1 (2)** | Frequency Distribution Summary | `getCourseResults()` - Frequency Distribution table |
| **RESULTS** | Student Results Summary (all courses per student) | ✅ **IMPLEMENTED** - `student-results-summary` |
| **CODDING FOR LECTURES** | Reference/lookup data | `subjects` table + `class_routines` |

---

## ✅ Implementation Status (Updated December 19, 2025)

### NEW: Student Results Summary Report

A new report matching the Excel "RESULTS" sheet has been implemented:

**URL:** `http://localhost/paxhitestbed/admin/exam/results-summary/student-results-summary`

**Features:**
- Matrix view showing all courses per student
- Per-course columns: Att (Attendance), CA, EX (Exam), TOT (Total), Grad (Grade)
- Summary columns: TCR (Total Credits Registered), TCE (Total Credits Earned), GPA, Pass/Fail counts
- Color-coded grades (green for pass, red for fail)
- Compact/Full view toggle for large datasets
- Excel export matching the template format
- Course performance overview with pass rates
- Print-friendly layout

**Route:** `GET admin/exam/results-summary/student-results-summary`
**Export Route:** `POST admin/exam/results-summary/export-student-results-summary`

---

## Sheet-by-Sheet Comparison

### 1. FBMS-HND LEVEL ONE/TWO (Course Marksheet)

**Excel Structure:**
| Column | Description | Max Value |
|--------|-------------|-----------|
| S/N | Serial Number | - |
| MAT. NO | Matricule Number | - |
| NAME | Student Name | - |
| Exam Codes | Course Code + Anonymous Number | - |
| Att/10 | Attendance Marks | 10 |
| CA/30 | CA Marks (excluding attendance) | 30 |
| Tot/40 | Total CA (Att + CA) | 40 |
| Sign In | Exam sign in | - |
| Sign Out | Exam sign out | - |
| Exam/60 | Final Exam Marks | 60 |
| Total/100 | Overall Total | 100 |
| Grades | Letter Grade | - |
| Remarks | Pass/Fail | - |

**System Equivalent (`course-marksheet`):**
| Field | Source | ✅/❌ |
|-------|--------|-------|
| S/N | Serial counter | ✅ |
| MAT. NO | `student_enroll.matricule` | ✅ |
| NAME | `students.first_name + last_name` | ✅ |
| Exam Code | `script_codes.anonymous_code` | ✅ |
| Att/10 | Calculated from `student_attendance` | ✅ |
| CA/30 | `assignments + activities + ca_exams` | ✅ |
| Tot/40 | Sum of above | ✅ |
| Sign In | **NOT IN SYSTEM** | ❌ |
| Sign Out | **NOT IN SYSTEM** | ❌ |
| Exam/60 | `exams.achieve_marks` where `is_final=true` | ✅ |
| Total/100 | `subject_marking.total_marks` | ✅ |
| Grades | Calculated from `grades` table | ✅ |
| Remarks | Pass/Fail based on 50 threshold | ✅ |

**Missing Data: Sign In/Sign Out** - These are exam hall entry/exit times not currently tracked.

---

### 2. Sheet1 (2) - Frequency Distribution Summary

**Excel Structure:**
| Field | Description |
|-------|-------------|
| Course Code | Subject code |
| Course Title | Subject title |
| Credit Value | Credit hours |
| Status | C/E/UR |
| Lecturer(s) | From class_routines |
| % CC | Course Coverage |
| Cand. Reg. | Candidates Registered |
| Cand. Exam. | Candidates Examined |
| No. Passed | Pass count |
| No. Failed | Fail count |
| % Pass | Pass rate |
| % Fail | Fail rate |
| A to F | Grade distribution |
| Mean | Average marks |

**System Equivalent (`getCourseResults()`):**
| Field | Status | Notes |
|-------|--------|-------|
| Course Code | ✅ | `subjects.code` |
| Course Title | ✅ | `subjects.title` |
| Credit Value | ✅ | `subjects.credit_hour` |
| Status | ✅ | `subjects.subject_type` mapped to C/E/UR |
| Lecturer(s) | ✅ | From `class_routines.teacher` |
| % CC | ✅ | **JUST FIXED** - `calculateCourseCoverage()` |
| Cand. Reg. | ✅ | Count from `student_enroll` |
| Cand. Exam. | ✅ | Count where attendance = 1 |
| No. Passed | ✅ | Count where total >= 50 |
| No. Failed | ✅ | Count where total < 50 |
| % Pass | ✅ | Calculated |
| % Fail | ✅ | Calculated |
| A to F | ✅ | From `grades` table distribution |
| Mean | ✅ | Average of total_marks |

**STATUS: ✅ COMPLETE** - All fields match after Course Coverage fix.

---

### 3. RESULTS Sheet - Student Results Summary

**Excel Structure:**
```
Header:
- Row 1-4: Institution, Faculty, Department, Semester info
- Row 5-8: Column headers

Per Student Row:
| S/N | Mat No. | Name | Per Course (repeating): Att | CA | EX | TOT | Grad |

Courses: BFI1101H, BFI1102H, INS1101H, MGT1101H, MKT1101H, ECO1101H, 
         MAT1101H, ECO1102H, ACC1102H, ACC1105, FRE1101H, ACC1101H, ENG1101H
```

Each course has 5 columns:
- **Att** - Attendance marks
- **CA** - CA marks
- **EX** - Exam marks  
- **TOT** - Total marks
- **Grad** - Letter grade

**System Equivalent:** 

✅ **IMPLEMENTED** - `student-results-summary` route

The system now has:
1. `course-marksheet` - Shows all students for ONE course
2. `student-results-summary` - Shows ALL courses for all students (matrix view)
3. `department-report` - Shows course summaries (aggregate stats)

**Features implemented:**
- Student-by-course matrix
- Per-student summary: TCR, TCE, GPA, Pass/Fail count
- Course performance statistics
- Excel export in template format
- Compact/Full view toggle
- Print-friendly layout

---

### 4. Summary Columns (GPA/TCE/TCR)

**Excel Status:** NOT PRESENT in the Excel file

The RESULTS sheet does NOT include:
- GPA calculation
- Total Credits Earned (TCE)
- Total Credits Registered (TCR)
- Cumulative data

**System Status:**
The system calculates these in student transcript/results slip, but not in the Results Summary module.

---

## Data Model Mapping

### Marks Breakdown

| Excel Field | System Table | Column |
|-------------|-------------|--------|
| Attendance | `subject_marking` | `attendances` |
| Attendance | `student_attendance` | Calculated from `attendance` field |
| Assignments | `subject_marking` | `assignments` |
| Activities | `subject_marking` | `activities` |
| CA Exam | `exams` | `achieve_marks` where `exam_type.is_final = false` |
| Final Exam | `exams` | `achieve_marks` where `exam_type.is_final = true` |
| Total | `subject_marking` | `total_marks` |
| Grade | Calculated | From `grades` table based on `total_marks` |

### Key Relationships

```
students
  └── student_enroll (per session/semester)
        ├── exams (per subject, per exam_type)
        │     └── exam_type (is_final: true/false)
        ├── subject_marking (per subject)
        └── student_attendance (per date, per subject)
```

---

## Recommendations

### 1. ✅ Implemented
- Course marksheet (FBMS-HND LEVEL ONE equivalent)
- Frequency Distribution Summary (Sheet1 (2) equivalent)
- Pass/Fail calculation (fixed to use 50 threshold)
- Course Coverage calculation
- **Student Results Summary (RESULTS sheet equivalent)** - NEW!

### 2. ❌ Remaining Missing Features

#### Sign In/Sign Out (Low Priority)
- Exam hall entry/exit times
- Would require new fields in `exams` table:
  - `sign_in_time`
  - `sign_out_time`

### 3. 🔄 Enhancement Opportunities

#### Excel Export Improvements
- Add more formatting options
- Support for custom template uploads

---

## Next Steps

1. ✅ ~~Create Student Results Summary report~~ - DONE
2. **Verify Sign In/Sign Out requirements** - Are these actually needed?
3. **Test with actual data** - Validate all calculations
4. **User feedback** - Gather input on report layout
