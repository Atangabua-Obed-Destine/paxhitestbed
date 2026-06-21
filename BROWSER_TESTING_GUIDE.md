# 🧪 BROWSER TESTING GUIDE - Multi-Program Enrollment System

## 📋 TESTING CHECKLIST

### ✅ Pre-Testing Setup (5 minutes)

1. **Verify System is Running**
   ```powershell
   # Start XAMPP Apache and MySQL
   # Navigate to http://localhost/paxhitest
   ```

2. **Run Automated Tests**
   ```powershell
   cd c:\xampp\htdocs\paxhitest
   php test_multi_enrollment_system.php
   ```
   Expected: All 7 tests should pass ✅

3. **Create Test Masters Program** (One-time setup)
   ```sql
   -- Open phpMyAdmin → paxhitest database → SQL tab
   -- Run this query to convert one program to Masters level:
   
   UPDATE programs 
   SET academic_level = 'M' 
   WHERE id = 2;  -- Change ID to an existing program
   
   -- Verify:
   SELECT id, title, academic_level FROM programs WHERE academic_level = 'M';
   ```

---

## 🎓 STUDENT PORTAL TESTING

### Test 1: Student Login (Single Enrollment)
**Expected Behavior**: Students with only ONE enrollment should bypass program selection

**Steps**:
1. Navigate to: `http://localhost/paxhitest/student/login`
2. Login with student credentials that has ONLY one enrollment
3. ✅ **Expected**: Redirected directly to dashboard
4. ✅ **Expected**: No program selection page shown
5. ✅ **Expected**: Header shows NO program switcher dropdown

**Test Data**: Use student with student_id: `10000003` (or any with 1 enrollment)

---

### Test 2: Program Selection (Multi-Enrollment)
**Expected Behavior**: Students with MULTIPLE enrollments see program selection page

**Steps**:
1. Navigate to: `http://localhost/paxhitest/student/login`
2. Login with student credentials: `348522` (has 5 enrollments)
3. ✅ **Expected**: Redirected to `/student/select-program` page
4. ✅ **Expected**: See beautiful gradient page with program cards
5. ✅ **Expected**: Each card shows:
   - Program title
   - Matricule number
   - Level badge (Undergraduate/Masters/Doctoral)
   - Faculty name
   - Session and semester
6. Click on any program card or "Select This Program" button
7. ✅ **Expected**: AJAX loading animation
8. ✅ **Expected**: Redirected to dashboard
9. ✅ **Expected**: Selected program is now active

**Screenshot Points**:
- [ ] Program selection page layout
- [ ] Program cards with level badges
- [ ] Hover effect on cards

---

### Test 3: Program Switcher Dropdown
**Expected Behavior**: Multi-enrollment students see switcher in header

**Steps**:
1. After selecting a program (from Test 2)
2. Look at top-right header navigation
3. ✅ **Expected**: See "Current Program" dropdown showing matricule
4. Click on the dropdown
5. ✅ **Expected**: See list of all enrollments with:
   - Program titles
   - Matricule numbers
   - Level badges (color-coded)
   - Faculty information
   - Current program highlighted with checkmark
6. Click on a different program in dropdown
7. ✅ **Expected**: Page refreshes automatically
8. ✅ **Expected**: New program is now selected
9. ✅ **Expected**: Dashboard data updates to new program

**Screenshot Points**:
- [ ] Header dropdown closed state
- [ ] Header dropdown open state
- [ ] Level badge colors

---

### Test 4: Student Profile Page
**Expected Behavior**: Profile shows current enrollment matricule

**Steps**:
1. While logged in as student
2. Navigate to: Profile → My Profile
3. ✅ **Expected**: Academic Information section shows:
   - Matricule (large, colored)
   - Level badge
   - Program, session, semester for CURRENT enrollment
4. Switch program using header dropdown
5. Refresh profile page
6. ✅ **Expected**: Matricule and details updated to new enrollment

**Screenshot Points**:
- [ ] Profile page with matricule display
- [ ] Level badge in profile

---

### Test 5: Student Transcript
**Expected Behavior**: Transcript shows current enrollment matricule

**Steps**:
1. Navigate to: Academic → Transcript
2. ✅ **Expected**: Top of page shows:
   - Current enrollment matricule
   - Level badge
   - Program name
3. Switch program and check again
4. ✅ **Expected**: Matricule changes to new enrollment

---

### Test 6: Course Registration
**Expected Behavior**: Course registration shows matricule

**Steps**:
1. Navigate to: Academic → Course Registration
2. ✅ **Expected**: Basic info section shows matricule with level badge
3. ✅ **Expected**: Correct program and enrollment details

---

### Test 7: Fees Payment
**Expected Behavior**: Fee pages show matricule

**Steps**:
1. Navigate to: Fees → My Fees
2. Check any fee payment page
3. ✅ **Expected**: Matricule displayed with level badge
4. ✅ **Expected**: Program information matches selected enrollment

---

## 👨‍💼 ADMIN PORTAL TESTING

### Test 8: Create New Student (Matricule Generation)
**Expected Behavior**: New student automatically gets matricule

**Steps**:
1. Login as admin: `http://localhost/paxhitest/admin/login`
2. Navigate to: Student Information → Admission → Create Student
3. Fill all required fields:
   - Student information
   - Select program (Undergraduate - level 'A')
   - Select batch (2025)
   - Select faculty
4. Submit form
5. ✅ **Expected**: Student created successfully
6. Navigate to: Student Information → Student List
7. Find the newly created student
8. ✅ **Expected**: Student shows matricule format: `PAX25[Faculty][NNN]A`
   - Example: `PAX25BF001A` for Business & Finance
9. ✅ **Expected**: Level badge shows "UG" (Undergraduate)

**Test Data**:
- First Name: Test
- Last Name: Student
- Program: Any Undergraduate program (academic_level = 'A')
- Batch: 2025

**Screenshot Points**:
- [ ] Student creation form
- [ ] Student list with new matricule
- [ ] Level badge display

---

### Test 9: Enroll Student to Masters (Level Transition)
**Expected Behavior**: Enrolling to Masters generates NEW matricule

**Steps**:
1. Login as admin
2. Navigate to: Student Information → Single Enroll
3. Select the test student created in Test 8
4. ✅ **Expected**: See current matricule (PAX25BF001A) displayed
5. Select the Masters program (academic_level = 'M')
6. Fill session, semester, section
7. Submit enrollment
8. ✅ **Expected**: Success message
9. Navigate to student details or list
10. ✅ **Expected**: NEW matricule generated: `PAX25M[Faculty][NNN]`
    - Example: `PAX25MBF001` (no 'A' suffix for Masters)
11. ✅ **Expected**: Level badge shows "MS" (Masters)
12. Login as this student
13. ✅ **Expected**: See program selection page with TWO programs:
    - Undergraduate with `PAX25BF001A`
    - Masters with `PAX25MBF001`

**Screenshot Points**:
- [ ] Single enroll page showing matricules
- [ ] Student with two enrollments
- [ ] Different matricule formats

---

### Test 10: Student List View
**Expected Behavior**: Student list shows matricules with badges

**Steps**:
1. Navigate to: Student Information → Student List
2. ✅ **Expected**: Student ID column shows matricules (not student_id)
3. ✅ **Expected**: Each student has level badge
4. ✅ **Expected**: Colors are correct:
   - Green badge = Undergraduate
   - Orange badge = Masters
   - Purple badge = Doctoral
5. Filter students by program
6. ✅ **Expected**: Matricules and badges display correctly

---

### Test 11: ID Card Printing
**Expected Behavior**: ID card shows enrollment matricule

**Steps**:
1. Navigate to: Student Information → Student ID Card
2. Select the test student
3. Click "Print ID Card"
4. ✅ **Expected**: ID card shows matricule (not internal student_id)
5. ✅ **Expected**: QR code contains matricule
6. ✅ **Expected**: Faculty and program info correct

**Screenshot Points**:
- [ ] ID card print preview
- [ ] Matricule on card
- [ ] QR code

---

### Test 12: Marksheet Printing
**Expected Behavior**: Marksheet shows enrollment matricule

**Steps**:
1. Navigate to: Examination → Marksheet
2. Select session, semester, program
3. Generate marksheet for any student
4. ✅ **Expected**: Marksheet header shows matricule
5. ✅ **Expected**: Level badge displayed
6. ✅ **Expected**: Correct format for level

---

### Test 13: Attendance Sheet
**Expected Behavior**: Attendance shows matricules

**Steps**:
1. Navigate to: Attendance → Student Attendance
2. Select class and date
3. ✅ **Expected**: Student list shows matricules with badges
4. ✅ **Expected**: Level identification visible

---

### Test 14: Exam Attendance
**Expected Behavior**: Exam attendance shows matricules

**Steps**:
1. Navigate to: Examination → Exam Attendance
2. Select exam and section
3. ✅ **Expected**: Student list shows matricules
4. ✅ **Expected**: Level badges present
5. Take attendance
6. ✅ **Expected**: Saves successfully with matricule reference

---

### Test 15: Subject Marking
**Expected Behavior**: Marking sheet shows matricules

**Steps**:
1. Navigate to: Examination → Subject Marking
2. Select subject and section
3. ✅ **Expected**: Student list shows matricules with badges
4. Enter marks for any student
5. ✅ **Expected**: Saves successfully

---

## 🔍 EDGE CASES & ERROR TESTING

### Test 16: Session Persistence
**Steps**:
1. Login as multi-enrollment student
2. Select Program A
3. Navigate to various pages (profile, fees, transcript)
4. Close browser tab (but keep browser open)
5. Open new tab to: `http://localhost/paxhitest/student/dashboard`
6. ✅ **Expected**: Still on Program A (session persists)
7. Logout and login again
8. ✅ **Expected**: See program selection page again (session cleared)

---

### Test 17: Backward Compatibility
**Steps**:
1. Find student enrollment WITHOUT matricule (22 enrollments have NULL matricule)
2. View in admin portal
3. ✅ **Expected**: Shows fallback student_id (no errors)
4. View in student portal (if applicable)
5. ✅ **Expected**: Shows student_id as matricule

---

### Test 18: Multiple Browser Windows
**Steps**:
1. Login as multi-enrollment student in Browser Window 1
2. Select Program A
3. Open new browser window (Window 2) with same student
4. ✅ **Expected**: Window 2 shows program selection
5. In Window 2, select Program B
6. Refresh Window 1
7. ✅ **Expected**: Window 1 now shows Program B (session updated)

---

## 📊 TESTING RESULTS TEMPLATE

```
Date: [DATE]
Tester: [NAME]
Browser: [Chrome/Firefox/Safari/Edge]
Version: [BROWSER VERSION]

STUDENT PORTAL:
[ ] Test 1: Single enrollment login - PASS/FAIL
[ ] Test 2: Program selection - PASS/FAIL
[ ] Test 3: Program switcher - PASS/FAIL
[ ] Test 4: Profile page - PASS/FAIL
[ ] Test 5: Transcript - PASS/FAIL
[ ] Test 6: Course registration - PASS/FAIL
[ ] Test 7: Fees payment - PASS/FAIL

ADMIN PORTAL:
[ ] Test 8: Create new student - PASS/FAIL
[ ] Test 9: Masters enrollment - PASS/FAIL
[ ] Test 10: Student list - PASS/FAIL
[ ] Test 11: ID card - PASS/FAIL
[ ] Test 12: Marksheet - PASS/FAIL
[ ] Test 13: Attendance - PASS/FAIL
[ ] Test 14: Exam attendance - PASS/FAIL
[ ] Test 15: Subject marking - PASS/FAIL

EDGE CASES:
[ ] Test 16: Session persistence - PASS/FAIL
[ ] Test 17: Backward compatibility - PASS/FAIL
[ ] Test 18: Multiple windows - PASS/FAIL

ISSUES FOUND:
1. [Description] - [Severity: Critical/High/Medium/Low]
2. [Description] - [Severity: Critical/High/Medium/Low]

OVERALL STATUS: PASS / FAIL WITH ISSUES / FAIL
```

---

## 🐛 COMMON ISSUES & SOLUTIONS

### Issue: "Session expired" or continuous redirect to login
**Solution**: Clear browser cookies and cache, login again

### Issue: Program selection page shows empty cards
**Solution**: Ensure enrollments have relationships loaded (`with('program')`)

### Issue: Matricule not showing, displays student_id
**Solution**: 
1. Check if enrollment has matricule in database
2. Verify accessor is working: `php artisan tinker` → `StudentEnroll::first()->matricule`

### Issue: Level badges not color-coded
**Solution**: Check if program has `academic_level` field set ('A'/'M'/'D')

### Issue: Can't switch programs
**Solution**: 
1. Check CSRF token is valid
2. Verify middleware is applied to routes
3. Check JavaScript console for errors

---

## 📸 SCREENSHOT REQUIREMENTS

For documentation and UAT, capture screenshots of:

1. ✅ Program selection page (student portal)
2. ✅ Program switcher dropdown (expanded)
3. ✅ Student profile with matricule
4. ✅ Student list in admin (showing badges)
5. ✅ ID card print with matricule
6. ✅ Marksheet with matricule
7. ✅ Attendance sheet with badges
8. ✅ Two enrollments for same student (different matricules)

---

## ⏱️ ESTIMATED TESTING TIME

- **Student Portal Tests (1-7)**: 30 minutes
- **Admin Portal Tests (8-15)**: 45 minutes
- **Edge Cases (16-18)**: 15 minutes
- **Screenshot Capture**: 15 minutes

**Total**: ~2 hours for complete testing

---

## ✅ SIGN-OFF

**Tested By**: ___________________________  
**Date**: ___________________________  
**Signature**: ___________________________  

**Approved By**: ___________________________  
**Date**: ___________________________  
**Signature**: ___________________________  

---

**Ready to deploy to production**: YES / NO / WITH CONDITIONS

**Conditions** (if applicable):
_____________________________________________________________
_____________________________________________________________

---

END OF TESTING GUIDE
