# Staff Assignment Testing Guide

## Important: How Staff Restrictions Work

Staff assignments **only restrict the logged-in staff member**, not administrators viewing their assignments.

### Current Test Case

**Staff:** Diran Ndimbe (ID: 0003)
**Assigned To:**
- Faculty: FACULTY OF BUSINESS AND FINANCE
- Programs: HND ACCOUNTANCY, HND BANKING AND FINANCE, HND INTERNATIONAL TRADE

### Testing Steps

#### ✅ Correct Way to Test:
1. **Logout** from your current admin account
2. **Login as Staff ID 0003** (Diran Ndimbe)
3. Navigate to any page that shows faculties/programs:
   - Faculty list page
   - Program list page  
   - Class routine creation
   - Any dropdown with faculties/programs
4. **Expected Result:** You should ONLY see:
   - 1 Faculty (FACULTY OF BUSINESS AND FINANCE)
   - 3 Programs (the assigned ones)

#### ❌ Common Mistake:
- Logging in as Super Admin/HR and expecting to see restrictions
- **Why it doesn't work:** Super Admins always see ALL data (they need to manage everything)
- HR users also see all data (they need to create assignments for everyone)

### Who Sees What?

| User Role | What They See |
|-----------|---------------|
| **Super Admin** | ALL faculties, programs, courses (always) |
| **HR/Admin** | ALL faculties, programs, courses (to manage assignments) |
| **Staff WITH assignments** | ONLY assigned faculties, programs, courses |
| **Staff WITHOUT assignments** | ALL faculties, programs, courses (default role access) |

### Verification Commands

Run this to check what staff ID 0003 should see:
```bash
php test-staff-restriction.php
```

Expected output:
- Accessible Faculty IDs: [6]
- Accessible Program IDs: [32,34,35]
- Filtered Faculties: 1
- Filtered Programs: 3

### Testing Different Scenarios

#### Scenario 1: Staff with Full Faculty Access
- Assign: Faculty only (no specific programs)
- Result: Staff sees the faculty AND all its programs

#### Scenario 2: Staff with Specific Programs
- Assign: Faculty + specific programs
- Result: Staff sees the faculty AND only assigned programs

#### Scenario 3: Staff with Specific Courses
- Assign: Faculty + programs + specific courses
- Result: Staff sees only assigned courses for those programs

#### Scenario 4: Staff with No Assignments
- Assign: Nothing
- Result: Staff sees everything (default role-based access)

### Common Testing Areas

1. **Faculty Dropdown** - Any page with faculty selection
2. **Program Dropdown** - Any page with program selection  
3. **Course/Subject Dropdown** - Subject selection pages
4. **Class Routine** - When creating/editing class routines
5. **Reports** - Fee reports, student reports filtered by faculty/program
6. **Student Management** - Viewing students by program

### Troubleshooting

**Problem:** "I assigned a staff but they still see everything"
- **Solution:** Check if they have Super Admin role. Super Admins bypass all restrictions.

**Problem:** "Programs don't show after selecting faculty"
- **Solution:** This was a bug in the service (now fixed). Clear cache: `php artisan cache:clear`

**Problem:** "Staff can't access anything after assignment"
- **Solution:** Make sure you assigned at least one faculty, program, or course.

### Technical Notes

The filtering is applied in:
- `StaffAssignmentService::filterFaculties()`
- `StaffAssignmentService::filterPrograms()`
- `StaffAssignmentService::filterCourses()`

Controllers using the filter:
- `FacultyController`
- `ProgramController`
- `SubjectController`
- `ClassRoutineController`
- `FilterController` (AJAX endpoints)

---

**Last Updated:** October 24, 2025
**System Status:** ✅ Working correctly - Service fixed, filtering active
