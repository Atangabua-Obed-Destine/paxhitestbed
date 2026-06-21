# PAXHI Academic Management System - Comprehensive Analysis

## Document Purpose
This document provides a complete understanding of the academic system's architecture, data flow, business logic, relationships, and policies across all major modules.

---

## TABLE OF CONTENTS
1. [System Architecture Overview](#1-system-architecture-overview)
2. [Core Academic Entities & Relationships](#2-core-academic-entities--relationships)
3. [Module-by-Module Deep Dive](#3-module-by-module-deep-dive)
4. [Academic Workflow & Lifecycle](#4-academic-workflow--lifecycle)
5. [Data Flow & Integration Points](#5-data-flow--integration-points)
6. [Business Rules & Policies](#6-business-rules--policies)
7. [Key Services & Their Roles](#7-key-services--their-roles)
8. [Security & Access Control](#8-security--access-control)

---

## 1. SYSTEM ARCHITECTURE OVERVIEW

### 1.1 Technology Stack
- **Framework**: Laravel 10 (PHP 8.1+)
- **Database**: MySQL
- **Authentication**: Multi-guard (web for admin/staff, student for students)
- **Authorization**: Spatie Permission package
- **UI**: AdminLTE, Bootstrap, Chart.js
- **Excel**: Maatwebsite Excel
- **Notifications**: Flasher (flash messages)

### 1.2 Multi-Guard Architecture
```
├── Admin/Staff Guard (web)
│   ├── Roles: super-admin, admin, teacher, finance, etc.
│   └── Controllers: app/Http/Controllers/Admin/*
│
└── Student Guard (student)
    ├── Authentication: Student model
    └── Controllers: app/Http/Controllers/Student/*
```

### 1.3 Core Middleware
- **select.enrollment**: Ensures student has selected active enrollment/program
- **check.platform.fee**: Verifies platform fee payment before accessing student portal
- **XSS**: Cross-site scripting protection
- **Permission middleware**: Role-based access control

---

## 2. CORE ACADEMIC ENTITIES & RELATIONSHIPS

### 2.1 Hierarchical Structure
```
Faculty
  └── Program
       └── Session (Academic Year)
            └── Semester
                 └── Section
                      └── EnrollSubject (Course Offerings)
                           └── Subjects
```

### 2.2 Student Enrollment Hierarchy
```
Student
  └── StudentEnroll (Multiple possible - multi-program support)
       ├── program_id
       ├── session_id
       ├── semester_id
       ├── section_id
       ├── matricule (unique per enrollment)
       ├── Subjects (many-to-many via student_enroll_subject)
       ├── SubjectMarking (grades)
       ├── Exam records
       ├── Attendance records
       ├── Fees
       └── ResitRequests
```

### 2.3 Key Model Relationships

#### StudentEnroll Model
```php
// Purpose: Represents ONE enrollment of a student in a specific program/session/semester
// A student can have MULTIPLE enrollments (multi-program support)

Relationships:
- belongsTo: Student, Program, Session, Semester, Section
- belongsToMany: Subjects (via student_enroll_subject pivot)
- hasMany: SubjectMarking, Exam, StudentAttendance, Fee, ResitRequest
- Custom Accessor: getMatriculeAttribute() - enrollment-specific matricule or fallback to student_id
```

#### SubjectMarking Model
```php
// Purpose: Stores final marks for a student in a course after all exam types are completed

Workflow States:
- draft → submitted → checked → approved → published

Key Fields:
- exam_marks, attendances, assignments, activities (CA components)
- total_marks (final calculated mark)
- workflow_state (publishing workflow)
- is_published_override (NULL=follow workflow, TRUE=force publish, FALSE=force unpublish)
- publish_date, publish_time (when to show to students)
- resolved_exam_weight, resolved_ca_weight (snapshot of contribution weights)

Visibility Logic:
- Visible to student IF:
  * is_published_override === true (forced publish), OR
  * is_published_override === null AND workflow_state === 'published' AND publish_date/time passed
```

#### ResitRequest Model
```php
// Purpose: Tracks student requests to retake failed courses

Workflow States:
- requested → awaiting_payment → finance_review → approved → scheduled
- Special states: rejected, cancelled, declined

State Meanings:
- requested: Initial student request
- awaiting_payment: Fee assigned, waiting for payment
- finance_review: Payment made, under finance verification
- approved: Payment verified, request approved
- scheduled: Resit session/semester assigned, enrollment created
- declined: Student chose NOT to resit (explicit decision to carry failed grade)
- cancelled: Student withdrew request (before approval)
- rejected: Admin rejected request

Key Fields:
- resit_session_id, resit_semester_id, resit_enroll_id (populated when scheduled)
- fee_id (linked fee record)
- payment_status: pending, partial, paid, waived, cancelled
```

#### Semester Model
```php
// Purpose: Represents academic terms

Key Fields:
- year (1-8): Which academic year
- semester_type (1=First Semester, 2=Second Semester)
- is_resit (boolean): TRUE for dedicated resit semesters
- parent_semester_id: Links resit semester to its corresponding regular semester
- status: Active/Inactive

Types:
1. Regular Semesters: is_resit = 0
   Example: "FIRST SEMESTER Y1" (year=1, type=1)
   
2. Resit Semesters: is_resit = 1
   Example: "1ST RESIT SEMESTER Y1" (year=1, type=1, parent_semester_id=X)
   Purpose: For students retaking failed courses from regular semester X
```

#### EnrollSubject Model
```php
// Purpose: Defines which courses are offered in a specific program/semester/section
// This is the "course catalog" that determines what subjects students can take

Structure:
- Unique combination: program_id + semester_id + section_id
- Many-to-many with Subjects (via enroll_subject_subject pivot)

Usage: 
- Admin creates course offerings using this
- Student course registration pulls from this
- Validates course availability when enrolling students
```

---

## 3. MODULE-BY-MODULE DEEP DIVE

### 3.1 ENROLL SUBJECT MODULE
**Route**: `/admin/academic/enroll-subject`  
**Controller**: `Admin\EnrollSubjectController`

**Purpose**: Define course offerings for each program/semester/section combination

**Workflow**:
1. Admin selects Faculty → Program → Semester → Section
2. System shows available subjects for that program
3. Admin checks which subjects to offer in that section/semester
4. System creates/updates EnrollSubject record with selected subjects

**Business Rules**:
- One EnrollSubject per program+semester+section combination
- Subjects must belong to the selected program
- Section must be configured for the selected program/semester (via ProgramSemesterSection)
- Only active subjects/semesters/sections can be enrolled

**Data Flow**:
```
EnrollSubject
  ↓ (defines available courses)
StudentEnroll.subjects
  ↓ (course registration)
Student takes courses
  ↓ (grading)
SubjectMarking (marks)
  ↓ (if failed)
ResitRequest
```

---

### 3.2 CLASS ROUTINE MODULE
**Route**: `/admin/routine/class-routine`  
**Controller**: `Admin\ClassRoutineController`

**Purpose**: Schedule when and where courses are taught

**Key Features**:
- Filter by: Faculty, Program, Session, Semester, Section
- Grouped by semester year (Year 1, Year 2, etc.)
- Weekly schedule (Monday-Sunday)
- Staff assignment filtering (teachers only see their courses unless admin)

**Data Structure**:
```php
ClassRoutine:
- teacher_id: Who teaches
- subject_id: Which course
- room_id: Where (ClassRoom)
- session_id, program_id, semester_id, section_id: Context
- day: 1-7 (Sunday-Saturday)
- start_time, end_time: When
- status: Active/Inactive
```

**Permissions**:
- `class-routine-view`: View schedules
- `class-routine-create`: Create/edit routines
- `class-routine-print`: Print schedules
- `class-routine-teacher`: View teacher-specific schedule

**Staff Assignment Integration**:
- If staff assignment exists for user: Filters by assigned faculties/programs/courses
- If NO staff assignment AND not super-admin: Only shows courses where user is teacher
- Super-admin: Sees everything

---

### 3.3 STUDENT ATTENDANCE MODULE
**Route**: `/admin/student-attendance`  
**Controller**: `Admin\StudentAttendanceController`

**Purpose**: Track student attendance for individual class sessions

**Workflow**:
1. Teacher/Admin selects: Faculty → Program → Session → Semester → Section → Subject → Date
2. System loads all enrolled students for that subject
3. Teacher marks: Present, Absent, Late, Excused
4. System saves StudentAttendance records

**Data Structure**:
```php
StudentAttendance:
- student_enroll_id: Which enrollment
- subject_id: Which course
- attendance_date: When
- attendance: 1=Present, 0=Absent, 2=Late, 3=Excused
- status: Active/Inactive
```

**Report Features** (`/admin/student-attendance-report`):
- View attendance percentage per student per subject
- Filter by date range, session, semester
- Shows: Total classes, attended, percentage
- Used for attendance contribution in final marks

**Import Feature**:
- Excel import support for bulk attendance entry
- Format: Student ID, Date, Attendance status

---

### 3.4 EXAM MODULE (Multi-Component)

#### 3.4.1 Exam Routine
**Route**: `/admin/exam/exam-routine` (no direct route found, likely under different naming)  
**Controller**: `Admin\ExamRoutineController`

**Purpose**: Schedule exam sessions

**Structure**:
```php
ExamRoutine:
- exam_type_id: Which exam (CA Test, Quiz, Final Exam, etc.)
- session_id, program_id, semester_id, section_id, subject_id
- date, start_time, end_time
- rooms (many-to-many): Where exam is held
- users (many-to-many): Invigilators
```

**Exam Types** (ExamType model):
- Each exam type has a contribution % (e.g., CA Test 20%, Final 60%)
- Multiple exam types can exist (Quiz, Mid-term, Final, etc.)
- Contribution is used in SubjectMarking calculation

#### 3.4.2 Exam Attendance
**Route**: `/admin/exam/exam-attendance`  
**Controller**: `Admin\ExamAttendanceController`

**Purpose**: Track who attended which exam

**Workflow**:
- Similar to student attendance but for exams
- Links to ExamRoutine
- Used to determine exam eligibility

**Eligibility Settings**: `/admin/exam/attendance-eligibility`
- Configure minimum attendance % required to sit exams
- Can set per program/semester

#### 3.4.3 Exam Marking
**Route**: `/admin/exam/exam-marking`  
**Controller**: `Admin\ExamMarkingController`

**Purpose**: Enter exam marks for specific exam types

**Workflow**:
1. Select: Faculty → Program → Session → Semester → Section → Subject → Exam Type
2. System shows enrolled students
3. Teacher enters marks for that exam type
4. Marks stored in Exam model

**Data Structure**:
```php
Exam:
- student_enroll_id
- subject_id
- exam_type_id
- marks: Score for this exam
- status: Published/Unpublished
```

**Integration with Subject Marking**:
- Exam marks from different exam types are aggregated
- Each exam type's contribution is applied
- Total exam component goes into SubjectMarking.exam_marks

#### 3.4.4 Subject Marking (Final Marks)
**Route**: `/admin/exam/subject-marking`  
**Controller**: `Admin\SubjectMarkingController`

**Purpose**: Calculate and publish final course marks

**Complete Workflow**:

1. **Data Collection Phase**:
   - Exam marks from various exam types (via Exam model)
   - Attendance percentage (from StudentAttendance)
   - Assignment scores (from StudentAssignment)
   - Activity/CA marks

2. **Calculation Phase** (done by AssessmentWeightService):
   ```
   Component Weights (from ResultContribution):
   - Exam Weight (e.g., 60%)
   - CA Weight (e.g., 30%)  
   - Attendance Weight (e.g., 10%)
   
   Sub-components:
   - Within Exam: Different exam types with their contributions
   - Within CA: Assignments + Activities
   
   Total Marks = (Exam × Exam%) + (CA × CA%) + (Attendance × Attendance%)
   ```

3. **Workflow States** (SubjectMarkingWorkflowService):
   ```
   draft → submitted → checked → approved → published
   ```
   
   **State Transitions**:
   - **draft**: Initial state, marks being entered
   - **submitted**: Teacher submits for review (requires permission: subject-marking-submit)
   - **checked**: Checked by department/coordinator (requires: subject-marking-check)
   - **approved**: Approved by HOD/admin (requires: subject-marking-approve)
   - **published**: Released to students (requires: subject-marking-publish)

4. **Publication Control**:
   - **is_published_override** field:
     * NULL: Follow workflow (only visible if state='published' AND publish_date/time passed)
     * TRUE: Force visible regardless of workflow (override publish)
     * FALSE: Force hidden regardless of workflow (unpublish specific student)
   
   - **Publish Date/Time**: When students can see marks
   - **Unpublish**: Can hide specific student's marks (e.g., academic integrity investigation)
   - **Republish**: Restore visibility after unpublishing

5. **Validation**:
   - Marks must be within 0-100 range
   - All exam types should have marks entered
   - Weight contributions must sum to 100%

**Key Fields in SubjectMarking**:
```php
- exam_marks: Total exam component (weighted)
- attendances: Attendance component score (weighted)
- assignments: Assignment component (weighted)
- activities: Activities/CA component (weighted)
- total_marks: Final mark (sum of all components)
- resolved_exam_weight: Snapshot of exam weight used (for historical accuracy)
- resolved_ca_weight: Snapshot of CA weight used
- validated: Boolean indicating if calculations are verified
```

**Access Control**:
- Teachers can only mark courses they teach (unless super-admin or staff assignment)
- Different permissions for each workflow transition
- Staff assignment filtering applies (if configured)

---

### 3.5 STUDENT RESIT MODULE
**Route**: `/student/resit`  
**Controller**: `Student\ResitController`

**Purpose**: Allow students to request retaking failed courses

**Complete Workflow**:

1. **Failed Course Detection**:
   - Student views failed courses from any session/semester
   - System checks SubjectMarking where total_marks < 50%
   - Only shows courses where marks are FULLY PUBLISHED:
     * workflow_state = 'published'
     * publish_date/time has passed
     * is_published_override != false

2. **Eligibility Checks**:
   ```php
   Course is eligible for resit IF:
   - Marks < 50% (failed)
   - Marks are published and visible
   - Course is NOT currently being retaken (carry-over check)
   - No DECLINED resit request exists (student can't change mind)
   - No active resit request exists (requested/approved/scheduled)
   ```

3. **Student Actions**:
   
   **A. Request Resit**:
   - Student selects failed course
   - System creates ResitRequest (state: requested)
   - ResitFeeService automatically:
     * Creates Fee record (from FeesCategory with is_resit=1)
     * Changes state to: awaiting_payment
     * Sets fee_amount (from FeesMaster or default config)

   **B. Decline Resit**:
   - Student explicitly chooses NOT to resit
   - Creates ResitRequest with state: declined
   - No fee assigned
   - **Critical**: Once declined, student CANNOT request resit for that course again
   - Purpose: Allows semester progression while carrying failed grade

4. **Payment Workflow**:
   ```
   awaiting_payment
     ↓ (student pays fee)
   finance_review
     ↓ (finance verifies payment)
   approved
     ↓ (admin assigns resit session/semester)
   scheduled
   ```

5. **Resit Scheduling** (Admin side):
   - Admin reviews approved resit requests
   - Assigns resit_session_id and resit_semester_id
   - System creates new StudentEnroll for resit semester
   - Enrolls student in failed courses
   - Links via resit_enroll_id

6. **Student Can Cancel**:
   - Only in states: requested, awaiting_payment
   - Sets state to: cancelled
   - Cancels associated fee (if unpaid)

7. **Carry-Over vs Resit**:
   - **Carry-Over**: Student re-registers for course in NEXT regular semester
     * Happens via normal course registration
     * Appears in current enrollment's subjects
     * NO resit request needed
   - **Resit**: Student takes course in DEDICATED resit semester
     * Requires resit request
     * Separate enrollment created
     * Fee charged

**Special Semester Handling**:
- **Resit Semesters** (`semester.is_resit = 1`):
  * Students CANNOT request new resits from resit semester failures
  * UI shows message: "You are in a resit semester. Resit requests are not allowed."
  * Must complete resit semester first

**Integration with Progression**:
- Student CANNOT progress to next semester if:
  * Failed courses exist without resit request
  * Active resit requests exist (must cancel or complete)
- Student CAN progress if:
  * All courses passed (≥50%)
  * All failed courses have declined OR scheduled resits

---

### 3.6 STUDENT TRANSCRIPT MODULE
**Route**: `/student/transcript`  
**Controller**: `Student\TranscriptController`

**Purpose**: Display academic record and GPA analysis

**Components**:

1. **Summary Card**:
   - Student info, program, photo
   - **Credits Attempted**: Unique courses (excludes retake duplicates)
   - **Credits Earned**: Unique passed courses (≥50%)
   - **CGPA**: Total quality points ÷ CGPA credits (includes ALL attempts)
   
2. **Semester-by-Semester Breakdown**:
   - Groups by session and semester
   - For each course shows:
     * Code, Title, Credits
     * Grade Letter, Grade Point
     * Credits Earned
     * Quality Points (grade_point × credits)
   - Semester totals:
     * Credits Attempted (unique courses)
     * Credits Earned (unique passed)
     * Semester GPA
   
3. **GPA Trend Analysis** (Chart.js):
   - Line chart showing Semester GPA and Cumulative GPA over time
   - Performance cards:
     * Current CGPA
     * Highest Semester GPA
     * Performance Trend (improving/declining/stable)
     * Credits Attempted (cumulative unique)
     * Credits Earned (cumulative unique)
   - Insights:
     * Performance feedback based on CGPA
     * Completion rate (earned/attempted %)
   - Tooltips show credits attempted/earned per semester

**Critical CGPA Calculation Logic**:
```php
// IMPORTANT: Retakes are handled specially

For Credits Attempted/Earned (Transcript Display):
- Count each course ONCE even if retaken multiple times
- Track unique courses by subject_id across ALL semesters
- If course retaken: Update pass status, don't add credits again

For CGPA Calculation:
- Count ALL attempts INCLUDING retakes
- Example: Failed Math (3 credits, F=0.0) → Retook Math (3 credits, B=3.0)
  * Credits Attempted (display): 3
  * CGPA Credits (calculation): 6
  * Quality Points: 0 + 9 = 9
  * CGPA: 9/6 = 1.5

This is STANDARD university practice where:
- Retakes affect GPA (both attempts count)
- Credits for graduation only count unique courses
```

**Program Filtering**:
- Multi-program students see separate transcripts per program
- Selected via SelectEnrollmentMiddleware
- GPA trends filtered by selected program only

**Mark Visibility**:
- Only shows marks where:
  * is_visible_to_student = true (checks override and workflow)
  * publish_date/time has passed

---

## 4. ACADEMIC WORKFLOW & LIFECYCLE

### 4.1 Complete Student Journey

```
┌─────────────────────────────────────────────────────┐
│ 1. ENROLLMENT PHASE                                 │
│    - Student applies and is admitted                │
│    - Admin creates StudentEnroll record             │
│    - Assigns: Program, Session, Semester, Section   │
│    - Generates matricule                            │
└─────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────┐
│ 2. COURSE REGISTRATION                              │
│    - Student selects courses from EnrollSubject     │
│    - System attaches subjects to StudentEnroll      │
│    - Prerequisites checked (if configured)          │
│    - Credit limit enforced (if configured)          │
└─────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────┐
│ 3. TEACHING PHASE                                   │
│    - ClassRoutine defines class schedule            │
│    - Attendance tracked per class                   │
│    - Assignments given and submitted                │
│    - Activities/CA conducted                        │
└─────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────┐
│ 4. EXAMINATION PHASE                                │
│    - ExamRoutine schedules exams                    │
│    - ExamAttendance tracks who sat exams            │
│    - ExamMarking records exam scores                │
│    - Multiple exam types (CA, Mid, Final)           │
└─────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────┐
│ 5. MARKING & GRADING                                │
│    - SubjectMarking aggregates all components       │
│    - Workflow: draft → submitted → checked →        │
│      approved → published                           │
│    - Total marks calculated with weights            │
│    - Grade assigned based on Grade scale            │
└─────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────┐
│ 6. RESULTS PUBLICATION                              │
│    - Admin sets publish date/time                   │
│    - Students view transcript when published        │
│    - CGPA calculated                                │
└─────────────────────────────────────────────────────┘
                        ↓
         ┌──────────────┴──────────────┐
         │                             │
    PASSED (≥50%)                FAILED (<50%)
         │                             │
         ↓                             ↓
┌─────────────────┐        ┌─────────────────────────┐
│ 7A. PROGRESSION │        │ 7B. RESIT PROCESS       │
│ - Check eligible│        │ - Student requests resit│
│ - Progress to   │        │ - OR declines resit     │
│   next semester │        │ - OR carry-over         │
└─────────────────┘        └─────────────────────────┘
                                     ↓
                           ┌─────────────────────────┐
                           │ RESIT PATH:             │
                           │ - Pay resit fee         │
                           │ - Admin schedules       │
                           │ - Enroll in resit sem   │
                           │ - Retake course         │
                           │ - New marks recorded    │
                           │ - Both attempts in CGPA │
                           └─────────────────────────┘
```

### 4.2 Semester Progression Logic

**Implemented in**: `SemesterProgressionService`

**Regular Semester Progression**:
```php
Student can progress to next semester IF:
1. All course marks are published
2. AND (
     ALL courses passed (≥50%)
     OR
     All failed courses have resit requests (declined/scheduled)
   )
3. AND no active pending resit requests
4. AND next semester exists in program structure
```

**Resit Semester Progression**:
```php
Student in resit semester progresses when:
1. All resit course marks published
2. Automatically progress to NEXT REGULAR semester
   (Does NOT require passing - graduation blocked if failed)

Example:
- Failed "SECOND SEMESTER Y1" → Resit in "2ND RESIT SEMESTER Y1"
- After resit marks published → Progress to "FIRST SEMESTER Y2"
```

**Progression Calculation**:
```php
Current: Year 1, First Semester (year=1, type=1)
  → Next: Year 1, Second Semester (year=1, type=2)

Current: Year 1, Second Semester (year=1, type=2)
  → Next: Year 2, First Semester (year=2, type=1)
```

**Manual Progression** (via header button):
- Auto-progression disabled in latest version
- Students manually trigger progression when ready
- System validates eligibility
- Shows progression modal with details

---

## 5. DATA FLOW & INTEGRATION POINTS

### 5.1 Mark Calculation Flow

```
Attendance Tracking
    ↓
StudentAttendance records
    ↓ (calculate %)
Attendance Score
    ↓
    
Exam Sessions
    ↓
ExamMarking per ExamType
    ↓ (aggregate with weights)
Total Exam Score
    ↓
    
Assignments/Activities
    ↓
StudentAssignment records
    ↓ (calculate)
CA Score
    ↓
    ↓──────────────┐
AssessmentWeightService (applies weights)
    ↓
SubjectMarking.total_marks
    ↓ (compare with Grade scale)
Final Grade Letter & Points
    ↓
Quality Points (grade_point × credits)
    ↓
CGPA Calculation
```

### 5.2 Fee Integration Flow

```
StudentEnroll created
    ↓
FeesMaster (per program/semester)
    ↓
Fee records created
    ↓
Student pays fees
    ↓ (payment verified)
FeePayment records
    ↓
Fee.status updated
    
RESIT PATH:
ResitRequest created
    ↓
ResitFeeService.ensureFee()
    ↓
Fee created (category.is_resit=1)
    ↓
State: awaiting_payment
    ↓ (payment)
State: approved
    ↓
ResitEnroll created
```

### 5.3 Staff Assignment Integration

**Purpose**: Restrict staff to specific faculties/programs/courses

**Applies to**:
- Faculty dropdown filtering
- Program dropdown filtering
- Subject/Course dropdown filtering
- Attendance marking
- Exam marking
- Subject marking

**Logic** (StaffAssignmentService):
```php
IF user has StaffAssignment records:
  → Filter queries to assigned faculties/programs/courses
ELSE IF user is super-admin:
  → No filtering, see all
ELSE:
  → Only see items where user is direct teacher/owner
```

---

## 6. BUSINESS RULES & POLICIES

### 6.1 Grading Policies

**Pass/Fail Threshold**: 50%
- Marks ≥ 50%: Pass
- Marks < 50%: Fail

**Grade Scale** (configurable via Grade model):
```
Example:
A:  85-100 (4.0 points)
B:  75-84  (3.0 points)
C:  65-74  (2.5 points)
D:  50-64  (2.0 points)
F:  0-49   (0.0 points)
```

**CGPA Calculation**:
```
CGPA = Σ(Quality Points) / Σ(CGPA Credits)

Where:
- Quality Points = Grade Point × Credits
- CGPA Credits = ALL attempts including retakes
- Credits Attempted (display) = Unique courses only
```

### 6.2 Resit Policies

**Eligibility**:
- Course mark < 50%
- Marks fully published
- No active resit request for that course
- Not currently retaking as carry-over

**Fee Policy**:
- Resit fee determined by FeesCategory (is_resit=1)
- Fee can be program-specific (via FeesMaster)
- Default fee from config('resit.default_fee')

**Deadline Policy**:
- Fee due date: assign_date + config('resit.fee_due_days', 7) days
- Payment deadline enforced (configurable)

**Declined Policy**:
- Once student declines, CANNOT request again
- Allows semester progression with failed grade
- Graduation will be blocked until resolved

**Carry-Over Policy**:
- Student can retake in next regular semester WITHOUT resit request
- No additional fee
- Counted as part of normal course registration
- System detects via: student has active enrollment with that subject

### 6.3 Attendance Policies

**Attendance Types**:
- 1: Present
- 0: Absent
- 2: Late
- 3: Excused

**Exam Eligibility**:
- Minimum attendance % required (configurable per program)
- Managed via ExamAttendanceSetting
- Can block student from sitting exam if below threshold

**Attendance Contribution**:
- Contributes to final marks (configurable %, e.g., 10%)
- Calculated as: (classes_attended / total_classes) × 100

### 6.4 Progression Policies

**Regular Semester**:
- Must pass all courses OR have resit plan for failures
- Cannot have pending resit requests
- Next semester must exist in program structure

**Resit Semester**:
- Automatically progress after marks published
- Does NOT require passing resits
- Graduation blocked if failed resits

**Manual Override**:
- Auto-progression disabled
- Students use progression button in header
- Admin can manually create enrollments

### 6.5 Multi-Program Policies

**Student can enroll in multiple programs simultaneously**:
- Each enrollment has unique matricule
- Separate transcripts per program
- Separate fees per program
- GPA calculated separately
- Student switches between programs via SelectEnrollmentMiddleware

**Program Change**:
- Tracked via: is_program_change, previous_program_id, program_change_reason
- Creates audit trail

---

## 7. KEY SERVICES & THEIR ROLES

### 7.1 AssessmentWeightService
**Purpose**: Calculate component weights for final marks

**Methods**:
- `resolveWeights()`: Get exam/CA/attendance weights for a subject
- `calculateTotalMarks()`: Apply weights to components
- Uses ResultContribution model for weights

### 7.2 SubjectMarkingWorkflowService
**Purpose**: Manage state transitions in marking workflow

**States**: draft → submitted → checked → approved → published

**Methods**:
- `transition()`: Move to next state
- `canTransition()`: Check if user has permission
- Logs all transitions in SubjectMarkingWorkflowLog

### 7.3 SemesterProgressionService
**Purpose**: Handle semester progression logic

**Key Methods**:
- `checkProgressionEligibility()`: Validate if can progress
- `attemptAutomaticProgression()`: Create new enrollment
- `checkResitSemesterProgression()`: Handle resit→regular progression
- `findNextSemester()`: Calculate next semester (year/type logic)

### 7.4 ResitFeeService
**Purpose**: Manage resit fee creation and synchronization

**Methods**:
- `ensureFee()`: Create fee if doesn't exist
- `syncFromFee()`: Update resit request from fee status
- `autoApproveIfSettled()`: Auto-approve when fee paid
- Uses FeesCategory with is_resit=1

### 7.5 ResitRequestWorkflowService
**Purpose**: Manage resit request state transitions

**Methods**:
- `transition()`: Change workflow state
- Validates state transitions
- Logs all changes in ResitRequestWorkflowLog

### 7.6 StaffAssignmentService
**Purpose**: Filter queries by staff assignments

**Methods**:
- `filterFaculties()`: Restrict faculty dropdown
- `filterPrograms()`: Restrict program dropdown
- `filterCourses()`: Restrict subject dropdown
- Applied to all major modules (attendance, marking, routine)

### 7.7 ResultContributionService
**Purpose**: Manage assessment component weights

**Methods**:
- `getSubjectContributions()`: Get course-specific weights
- Returns: exam types, attendance %, assignment %, activity %
- Supports per-subject override or program defaults

---

## 8. SECURITY & ACCESS CONTROL

### 8.1 Permission System

**Format**: `{module}-{action}`

**Examples**:
- `semester-view`, `semester-create`, `semester-edit`, `semester-delete`
- `exam-marking`, `exam-result`
- `subject-marking-submit`, `subject-marking-check`, `subject-marking-approve`, `subject-marking-publish`
- `enroll-subject-view`, `enroll-subject-create`

### 8.2 Role Hierarchy

**super-admin**:
- Full system access
- Can see all faculties/programs/students
- NOT filtered by staff assignments

**admin**:
- Administrative functions
- May have staff assignment restrictions

**teacher**:
- Limited to assigned courses
- Filtered by: teacher_id OR staff assignments
- Can mark, submit results

**finance**:
- Fee management
- Payment verification
- Cannot access academic marking

**student**:
- Own data only
- Restricted by select.enrollment middleware
- Cannot access admin routes

### 8.3 Data Access Patterns

**Student Guard**:
```php
// Always filter by authenticated student
$student = Auth::guard('student')->user();
StudentEnroll::where('student_id', $student->id)

// Multi-program: Filter by selected enrollment
$selectedEnrollmentId = session('selected_enrollment_id');
```

**Admin Guard**:
```php
// Check if super-admin
$user = User::whereHas('roles', function($q) {
    $q->where('slug', 'super-admin');
})->find(Auth::id());

// Apply staff assignment filter if not super-admin
if (!$superAdmin) {
    StaffAssignmentService::filterPrograms($query);
}
```

### 8.4 Audit Trail

**Auditable Trait**:
- Applied to major models
- Tracks: created_by, updated_by
- Logs all changes
- Custom descriptions per model

**Workflow Logs**:
- SubjectMarkingWorkflowLog
- ResitRequestWorkflowLog
- Track state changes, actors, timestamps

---

## 9. KEY POLICIES SUMMARY

### ✅ DO's:
1. Always filter by selected enrollment for multi-program students
2. Check mark visibility before displaying to students
3. Validate progression eligibility before creating new enrollments
4. Track unique courses for credit counting (no duplicate credits)
5. Include ALL attempts (including retakes) in CGPA calculation
6. Enforce staff assignments for non-admin users
7. Log all workflow state transitions
8. Verify fee payment before approving resits

### ❌ DON'Ts:
1. Don't show unpublished marks to students
2. Don't allow resit requests in resit semesters
3. Don't count retake credits twice in transcripts
4. Don't auto-progress without checking eligibility
5. Don't allow declined resit to be re-requested
6. Don't bypass payment for resits (unless waived)
7. Don't show other students' data (strict filtering)

---

## 10. INTEGRATION SUMMARY

**This system integrates**:
- ✅ Enrollment Management (StudentEnroll)
- ✅ Course Offerings (EnrollSubject)
- ✅ Scheduling (ClassRoutine, ExamRoutine)
- ✅ Attendance Tracking (StudentAttendance)
- ✅ Assessment & Grading (Exam, SubjectMarking)
- ✅ Transcript & CGPA (TranscriptController)
- ✅ Resit Management (ResitRequest, ResitFeeService)
- ✅ Fee Management (Fee, FeePayment)
- ✅ Semester Progression (SemesterProgressionService)
- ✅ Multi-Program Support (Multiple enrollments per student)
- ✅ Staff Assignment Filtering
- ✅ Workflow Management (State machines)

**All modules are tightly integrated with**:
- Shared hierarchical structure (Faculty → Program → Session → Semester → Section)
- Common authentication/authorization
- Unified data model
- Consistent business rules

---

**END OF COMPREHENSIVE ANALYSIS**

**Document Version**: 1.0  
**Last Updated**: {{ date }}  
**Analyst**: GitHub Copilot (Claude Sonnet 4.5)
