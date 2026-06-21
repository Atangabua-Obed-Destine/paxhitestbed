# STUDENT PORTAL - AI SUPPORT CHATBOT GUIDE

## Purpose
This comprehensive guide is designed to train AI chatbot models to provide first-level support for students using the PAXHI Student Portal (http://localhost/paxhitestbed/student/). The chatbot should be able to answer common questions, guide users through processes, identify issues, and escalate to human support when needed.

---

## TABLE OF CONTENTS

1. [Portal Overview](#1-portal-overview)
2. [Authentication & Login](#2-authentication--login)
3. [Program Selection (Multi-Enrollment)](#3-program-selection-multi-enrollment)
4. [Platform Fee Payment](#4-platform-fee-payment)
5. [Dashboard](#5-dashboard)
6. [Course Registration](#6-course-registration)
7. [Form A2 (Admission Confirmation)](#7-form-a2-admission-confirmation)
8. [Form A3 (Official Documents)](#8-form-a3-official-documents)
9. [Fees & Payments](#9-fees--payments)
10. [Payment Plans](#10-payment-plans)
11. [Manual Payment](#11-manual-payment)
12. [Multi-Fee Payments](#12-multi-fee-payments)
13. [Resit Applications](#13-resit-applications)
14. [Semester Progression](#14-semester-progression)
15. [Transcript](#15-transcript)
16. [Exam Results](#16-exam-results)
17. [Class Routine](#17-class-routine)
18. [Exam Routine](#18-exam-routine)
19. [Attendance](#19-attendance)
20. [Class Hub (Live Classes)](#20-class-hub-live-classes)
21. [Assignments](#21-assignments)
22. [Library](#22-library)
23. [E-Library](#23-e-library)
24. [Notices](#24-notices)
25. [Leave Applications](#25-leave-applications)
26. [Downloads](#26-downloads)
27. [Profile Management](#27-profile-management)
28. [Error Messages Reference](#28-error-messages-reference)
29. [Frequently Asked Questions](#29-frequently-asked-questions)
30. [Troubleshooting Guide](#30-troubleshooting-guide)
31. [Escalation Guidelines](#31-escalation-guidelines)

---

## 1. PORTAL OVERVIEW

### What is the Student Portal?
The PAXHI Student Portal is a self-service platform where students can:
- View academic progress (grades, CGPA, transcript)
- Register for courses each semester
- View and pay fees
- Access class schedules and exam routines
- Submit assignments
- Apply for resit examinations
- Download official documents
- Access the e-library and learning resources
- Interact with live classes through Class Hub

### Portal URL
**Main URL:** `http://localhost/paxhitestbed/student/`

### Key Concepts

#### Enrollment
An enrollment represents a student's registration in a specific:
- Academic Session (e.g., "2024/2025")
- Program (e.g., "Bachelor of Computer Science")
- Semester (e.g., "First Semester Year 1")
- Section (e.g., "Section A")

Students may have **multiple enrollments** if they are pursuing multiple programs simultaneously or have different program tracks.

#### Matricule
A unique student identification number assigned per program enrollment. Students with multiple programs will have multiple matricules.

#### Session
An academic year (e.g., "2024/2025 Academic Session").

#### Semester
A subdivision of the academic year (e.g., First Semester, Second Semester, or Resit Semester).

#### Platform Fee
A mandatory system usage fee that must be paid before accessing most portal features.

---

## 2. AUTHENTICATION & LOGIN

### Login Process

**URL:** `http://localhost/paxhitestbed/student/login`

**Step-by-Step:**
1. Navigate to the student portal login page
2. Enter your registered email address
3. Enter your password
4. Click "Login"

### Two-Factor Authentication (2FA)

If 2FA is enabled (by the institution), students will:
1. Receive a verification code via email after successful password entry
2. Be redirected to a verification page
3. Enter the 6-digit code received via email
4. Complete login after code verification

**Common 2FA Issues:**

| Issue | Cause | Solution |
|-------|-------|----------|
| Code not received | Email delay or spam filter | Check spam folder, wait 2-3 minutes, request new code |
| Code expired | Codes expire after a set time | Request a new code |
| Invalid code | Typo or wrong code | Re-check and enter correctly |

### Password Reset

**Process:**
1. Click "Forgot Password?" on login page
2. Enter your registered email
3. Receive password reset link via email
4. Click the link and set a new password

**Q: I didn't receive the password reset email?**
A: Check your spam/junk folder. If not there, ensure you're using the correct email address. Contact administration if the issue persists.

### Login Errors

| Error Message | Cause | Solution |
|---------------|-------|----------|
| "These credentials do not match our records" | Wrong email or password | Verify email address, reset password if forgotten |
| "Too many login attempts" | Multiple failed attempts | Wait 3 minutes before trying again |
| "Your account has been blocked" | Security lockout | Contact IT support immediately |
| "Email not verified" | Account pending verification | Check email for verification link |

---

## 3. PROGRAM SELECTION (MULTI-ENROLLMENT)

### When This Applies
Students enrolled in multiple programs or program tracks will see the program selection screen after login.

**URL:** `http://localhost/paxhitestbed/student/select-program`

### How It Works

1. After login, if you have multiple enrollments, you'll see the Program Selection page
2. Each card shows:
   - Program name
   - Academic level (Bachelor's, Master's, etc.)
   - Matricule number
   - Current semester
   - Current session
3. Click on the program card to select it
4. All subsequent pages will show data for the selected program

### Switching Programs

**Q: How do I switch between my programs?**
A: Look for the program switcher dropdown in the top navigation bar. Click it to see your available programs and select a different one.

**Q: Why don't I see the program selector?**
A: You only have one program enrollment. The system automatically selects it.

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| Can't see one of my programs | Enrollment may be inactive | Contact Registrar's office |
| Wrong program shows on dashboard | Selected wrong program | Use program switcher in navigation |
| "No active enrollment found" | All enrollments are inactive | Contact Academic Affairs |

---

## 4. PLATFORM FEE PAYMENT

### What is the Platform Fee?
A mandatory fee required to access portal features each academic session. Until this fee is paid and verified, most portal features are locked.

**URL:** `http://localhost/paxhitestbed/student/platform-fee`

### Payment Process

1. Navigate to Platform Fee Payment page
2. View the required fee amount
3. Make payment through the institution's bank account
4. Upload your payment receipt:
   - Click "Upload Receipt"
   - Select receipt file (JPG, JPEG, PNG, or PDF)
   - Enter payment date
   - Add optional notes
   - Submit

### Payment Status

| Status | Description | What to Do |
|--------|-------------|------------|
| **Pending** | Receipt uploaded, awaiting verification | Wait for admin review (typically 1-2 business days) |
| **Approved** | Payment verified | You can access all portal features |
| **Rejected** | Payment not verified | Check admin notes, reupload correct receipt |
| **Not Paid** | No payment submitted | Make payment and upload receipt |

### Common Questions

**Q: Why can't I access my dashboard?**
A: Your platform fee hasn't been paid or verified. Go to the Platform Fee page.

**Q: My payment was rejected. Why?**
A: Check the admin notes on your payment. Common reasons:
- Receipt unclear or unreadable
- Payment amount doesn't match
- Wrong bank account
- Duplicate submission

**Q: How long does verification take?**
A: Typically 1-2 business days. Contact Finance if longer.

**Q: I paid but can't upload receipt (file too large)?**
A: Resize your image or use PDF format. Maximum file size is 2MB.

---

## 5. DASHBOARD

### Overview
The Dashboard is your portal homepage showing key academic and financial information at a glance.

**URL:** `http://localhost/paxhitestbed/student/dashboard`

### Dashboard Components

#### 1. Academic Performance Cards
- **CGPA**: Your cumulative grade point average
- **Credits Earned**: Total validated course credits
- **Credits Attempted**: Total credits from all registered courses
- **Current Semester GPA**: GPA for the current semester

#### 2. Quick Stats
- Upcoming assignments
- Pending fees
- Today's classes
- Unread notices

#### 3. Calendar
Interactive calendar showing:
- Class schedules
- Exam dates
- Assignment deadlines
- Events

#### 4. GPA Trend Chart
Visual representation of your GPA progression across semesters.

#### 5. Recent Activity
- Latest grades published
- New assignments
- Recent payments
- Upcoming deadlines

### Understanding Your CGPA

**How CGPA is Calculated:**
```
CGPA = Total Quality Points / Total Credits Attempted

Where:
Quality Points = Grade Point × Credit Hours
```

**Grade Scale:**
| Grade | Points | Percentage |
|-------|--------|------------|
| A+ | 4.0 | 90-100 |
| A | 4.0 | 85-89 |
| A- | 3.7 | 80-84 |
| B+ | 3.3 | 75-79 |
| B | 3.0 | 70-74 |
| B- | 2.7 | 65-69 |
| C+ | 2.3 | 60-64 |
| C | 2.0 | 55-59 |
| C- | 1.7 | 50-54 |
| F | 0.0 | Below 50 |

### Common Questions

**Q: Why is my CGPA different from what I calculated?**
A: CGPA includes ALL attempts including retakes. Each retake contributes to the denominator.

**Q: Why don't I see all my courses on the dashboard?**
A: Dashboard shows data for the currently selected program. Use the program switcher if you have multiple enrollments.

**Q: Why are some features locked?**
A: Platform fee not paid or first installment not paid. Check Fees section.

---

## 6. COURSE REGISTRATION

### Overview
Course Registration allows you to add or drop courses for your current semester.

**URL:** `http://localhost/paxhitestbed/student/course-registration`

### Registration Process

#### Adding Courses
1. Go to Course Registration
2. View available courses in the "Available Courses" section
3. Click "Add" next to courses you want to register
4. Confirm your selection

#### Dropping Courses
1. View your registered courses in "My Courses"
2. Click "Drop" next to the course to remove
3. Confirm the action

### Course Eligibility

**Courses Shown:**
- Current semester courses for your program
- Failed courses from previous semesters (if retake allowed)
- Carry-over courses from previous years

**Courses NOT Shown:**
- Already validated courses (passed with ≥50%)
- Courses from future semesters
- Courses outside your program

### Credit Limits

Each semester has minimum and maximum credit limits:
- **Minimum**: Usually 12 credits
- **Maximum**: Usually 24 credits (varies by program)

**Q: Why can't I add more courses?**
A: You've reached your maximum credit limit for the semester.

### Course Status

| Status | Meaning |
|--------|---------|
| **Registered** | Course added successfully |
| **Validated** | Course passed (≥50%) - cannot retake |
| **Failed** | Course failed (<50%) - can register again |
| **Dropped** | Course removed from registration |

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| "Maximum credits exceeded" | Over credit limit | Drop a course first |
| Course not showing | Already validated or wrong semester | Check transcript for status |
| "Registration closed" | Registration period ended | Contact Academic Affairs |
| Can't drop a course | Drop deadline passed | Contact Academic Affairs |

### Resit Semester Note
During resit semesters, course registration is disabled. Resit courses are handled through the Resit Applications module.

---

## 7. FORM A2 (ADMISSION CONFIRMATION)

### What is Form A2?
Form A2 is an official document confirming your admission and first installment payment. It typically includes:
- Student details (name, matricule)
- Program information
- Fee breakdown paid
- Institutional signatures

**URL:** `http://localhost/paxhitestbed/student/form-a2`

### How to Download

1. Navigate to Form A2 page
2. Ensure your first installment is paid
3. Click "Download PDF" or "Print"

### Prerequisites
- First installment fee must be fully paid
- Payment must be verified by administration

### Common Questions

**Q: Why can't I download Form A2?**
A: Your first installment hasn't been paid or verified. Check Fees section.

**Q: My Form A2 shows incorrect information?**
A: Contact the Registrar's office with specific corrections needed.

**Q: Is Form A2 an official document?**
A: Yes, it can be used as proof of enrollment and fee payment.

---

## 8. FORM A3 (OFFICIAL DOCUMENTS)

### What is Form A3?
Form A3 provides access to various official academic documents.

**URL:** `http://localhost/paxhitestbed/student/form-a3`

### Available Documents

Documents may include:
- Admission letter
- Course enrollment certificate
- Fee payment receipts
- Academic calendar
- Program curriculum
- Examination policies

### How to Download

1. Go to Form A3 Docs page
2. Browse available documents
3. Click "Download" or "View" for the desired document

### Common Issues

| Issue | Solution |
|-------|----------|
| Document not loading | Refresh page, try different browser |
| Can't download PDF | Check if pop-ups are blocked |
| Missing document | Contact Academic Affairs |

---

## 9. FEES & PAYMENTS

### Overview
The Fees module shows all fees assigned to your enrollment and their payment status.

**URL:** `http://localhost/paxhitestbed/student/fees`

### Fee Categories

| Category | Description |
|----------|-------------|
| **First Installment** | Initial payment due at semester start |
| **Second Installment** | Follow-up payment if fees are split |
| **Tuition Fee** | Main academic fee |
| **Registration Fee** | Per-semester registration |
| **Library Fee** | Library access fee |
| **Lab Fee** | Laboratory usage (if applicable) |
| **Resit Fee** | Fee for resit examinations |

### Fee Status

| Status | Meaning | Color |
|--------|---------|-------|
| **Pending** | Not yet paid | Red |
| **Paid** | Fully paid | Green |
| **Partial** | Partially paid | Yellow |
| **Cancelled** | Fee cancelled by admin | Grey |

### Filtering Fees

Use filters to view fees by:
- Academic Session
- Semester
- Fee Category
- Payment Status

### Fee Summary

The page shows:
- **Total Fees**: Sum of all assigned fees
- **Total Paid**: Amount paid so far
- **Balance Due**: Remaining amount to pay

### Payment Options

1. **Online Payment** (if enabled)
   - Click "Pay Now" on a fee
   - Select payment method
   - Complete payment through gateway

2. **Manual Payment**
   - Pay through bank
   - Upload receipt via Manual Payment

3. **Payment Plan**
   - Request installment plan for large fees
   - Pay in smaller portions over time

### Common Questions

**Q: Why do I see fees from previous semesters?**
A: These are unpaid balances. All fees must be cleared.

**Q: The fee amount seems wrong?**
A: Contact Finance office. Fees are set based on program and semester.

**Q: How do I get a fee receipt?**
A: Go to the specific fee and click "Print Receipt" after payment.

**Q: Why is first installment required?**
A: First installment confirms your enrollment for the semester.

---

## 10. PAYMENT PLANS

### What is a Payment Plan?
Payment plans allow you to pay a large fee in smaller installments over time.

**URL:** `http://localhost/paxhitestbed/student/payment-plan`

### Viewing Payment Plans

1. Go to Payment Plans page
2. See all your active and completed plans
3. View upcoming installment due dates
4. Check payment progress

### Payment Plan Dashboard

Shows:
- **Active Plans**: Plans currently being paid
- **Total Paid**: Amount paid across all plans
- **Total Remaining**: Outstanding balance
- **Overdue Installments**: Installments past due date

### Making Installment Payments

1. Find the installment to pay
2. Click "Pay Installment"
3. Enter payment details and upload receipt
4. Submit for verification

### Installment Status

| Status | Meaning |
|--------|---------|
| **Pending** | Not yet due or awaiting payment |
| **Partial** | Partially paid |
| **Paid** | Fully paid |
| **Overdue** | Past due date, unpaid |

### Common Questions

**Q: Can I pay ahead of schedule?**
A: Yes, you can pay future installments early.

**Q: What happens if I miss a due date?**
A: The installment becomes "Overdue". Pay as soon as possible to avoid penalties.

**Q: How do I request a payment plan?**
A: Contact Finance office. Plans are created by administrators.

---

## 11. MANUAL PAYMENT

### What is Manual Payment?
Manual Payment allows you to upload bank payment receipts for fees that you've paid offline.

**URL:** `http://localhost/paxhitestbed/student/manual-payment`

### Process

1. Make payment at the bank to the institution's account
2. Keep your payment receipt/slip
3. Go to Manual Payment in the portal
4. Find the fee you paid for
5. Click "Upload Receipt"
6. Fill in:
   - Payment amount
   - Payment date
   - Payment method
   - Upload receipt image/PDF
   - Add notes (optional)
7. Submit for verification

### Supported File Types
- JPG, JPEG, PNG images
- PDF documents
- Maximum size: 2MB

### Verification Process

1. **Submitted**: Receipt uploaded, waiting for review
2. **Approved**: Payment verified, fee marked as paid
3. **Rejected**: Payment not verified (see admin notes)

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| "File too large" | Over 2MB | Compress or resize image |
| "Invalid file type" | Wrong format | Use JPG, PNG, or PDF |
| Receipt rejected | Unclear or incorrect | Upload clearer image, verify amount |
| "Pending multi-payment" | Fee in multi-payment | Wait for multi-payment verification |

### Tips for Successful Verification
- Ensure receipt is clear and readable
- Amount on receipt should match fee amount
- Include all pages if receipt has multiple pages
- Include bank stamp/seal if available

---

## 12. MULTI-FEE PAYMENTS

### What is Multi-Fee Payment?
Allows you to pay multiple fees in a single bank transaction and upload one receipt for all.

**URL:** `http://localhost/paxhitestbed/student/multi-payment`

### When to Use
- Paying multiple fees at once (e.g., tuition + library + lab)
- Consolidating payments to reduce bank visits
- Paying fees across different semesters

### Process

1. Go to Multi-Fee Payments
2. Select all fees you're paying together
3. Note the total amount
4. Make single bank payment for the total
5. Upload your receipt
6. Submit for verification

### Important Notes
- All selected fees must be unpaid or partially paid
- Receipt amount should match or exceed total of selected fees
- Once submitted, you cannot make individual payments for those fees until verified

### Common Questions

**Q: Can I mix fees from different semesters?**
A: Yes, as long as they're all unpaid fees for your enrollment.

**Q: My multi-payment was rejected. What now?**
A: Check admin notes, then resubmit with corrections or pay fees individually.

---

## 13. RESIT APPLICATIONS

### What is a Resit?
A resit examination is a second attempt at a course you failed (scored below 50%).

**URL:** `http://localhost/paxhitestbed/student/resit`

### Viewing Failed Courses

1. Go to Resit Applications
2. Select the session and semester
3. View your failed courses with:
   - Course name and code
   - Original marks obtained
   - Grade received

### Applying for Resit

1. Find the failed course
2. Click "Apply for Resit"
3. Select the resit session (when you want to retake)
4. Confirm application
5. Pay the resit fee (if required)

### Resit Application Status

| Status | Meaning |
|--------|---------|
| **Pending** | Application submitted, awaiting approval |
| **Approved** | Resit approved, you can take the exam |
| **Rejected** | Application denied (see reason) |
| **Cancelled** | You cancelled the application |
| **Completed** | Resit taken, results published |

### Viewing Resit History

1. Go to Resit Applications
2. Scroll to "My Resit Requests" section
3. View all past and current resit applications

### Cancelling a Resit Application

1. Find the pending/approved resit
2. Click "Cancel"
3. Confirm cancellation
4. Note: Refund policy varies by institution

### Common Questions

**Q: Why don't I see my failed course?**
A: Possible reasons:
- Marks not yet published
- Course is from a different program
- Already applied for resit
- Currently registered in another semester

**Q: Can I resit if I'm in a regular semester?**
A: Depends on institution policy. Some allow resits during regular semesters.

**Q: What's a resit semester?**
A: A dedicated semester for retaking failed courses, separate from regular semesters.

**Q: How many times can I resit?**
A: Check your program's examination policy. Typically limited to 2-3 attempts.

### Resit Semester Note
If you're currently in a resit semester:
- Course registration is disabled
- Your resit courses are determined by approved resit applications
- Regular course features may be limited

---

## 14. SEMESTER PROGRESSION

### What is Semester Progression?
Moving from one semester to the next after meeting academic requirements.

### Automatic vs. Manual Progression

**Automatic**: System progresses students who meet all requirements
**Manual**: Student initiates progression through the portal

### Checking Eligibility

1. Dashboard shows progression alerts when eligible
2. Or check the Progression feature when available

### Progression Requirements

Typical requirements include:
- All fees paid for current semester
- Platform fee paid
- No administrative holds
- Meeting GPA requirements (if applicable)
- Completing required courses

### Progression Types

1. **Regular Progression**: Moving to next regular semester
2. **Resit Progression**: Moving to a resit semester to retake failed courses

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| "Not eligible for progression" | Requirements not met | Check fees, grades, holds |
| "No next semester configured" | Admin setup issue | Contact Academic Affairs |
| Stuck in current semester | Progression not processed | Contact Registrar |

---

## 15. TRANSCRIPT

### Overview
View your complete academic record including all courses, grades, and GPA calculations.

**URL:** `http://localhost/paxhitestbed/student/transcript`

### Transcript Contents

#### Per Semester
- Session and semester name
- All registered courses
- Credits per course
- Marks obtained
- Grade earned
- Grade points

#### Summary
- Semester GPA
- Cumulative GPA (CGPA)
- Total credits attempted
- Total credits earned

### Understanding the Transcript

**Columns Explained:**
| Column | Meaning |
|--------|---------|
| Course Code | Unique identifier (e.g., CSC101) |
| Course Title | Full name of the course |
| Credit Hours | Weight of the course |
| Marks | Percentage score (0-100) |
| Grade | Letter grade (A, B, C, etc.) |
| Points | Grade point for this course |

### GPA Calculation

**Semester GPA**: Total quality points ÷ Credits for that semester

**CGPA**: Total quality points (all semesters) ÷ Total credits attempted

### Published vs. Unpublished Grades

- Only **published** grades appear on the transcript
- Grades are published after:
  - All marks are entered
  - Results are verified
  - Publish date/time is reached

### Common Questions

**Q: Why is my transcript empty?**
A: No grades have been published yet. Wait for results.

**Q: Why are some courses missing?**
A: Either not registered, or grades not yet published.

**Q: Can I download my transcript?**
A: Use browser print function. Official transcripts require formal request.

**Q: Why does my CGPA look wrong?**
A: All attempts count, including failed courses and retakes.

---

## 16. EXAM RESULTS

### Overview
View your examination results organized by exam type.

**URL:** `http://localhost/paxhitestbed/student/exam-results`

### Result Types

| Type | Description |
|------|-------------|
| **CA** | Continuous Assessment (quizzes, tests) |
| **Mid-Semester** | Mid-term examination |
| **Final Exam** | End-of-semester examination |
| **Practical** | Lab/practical examination |

### Viewing Results

1. Go to Exam Results
2. Select session and semester
3. View results by course
4. See breakdown by exam type

### Result Visibility

Results are visible when:
- Marks are entered by lecturer
- Results are published by admin
- Publish date/time has passed

### Common Questions

**Q: Why can't I see my results?**
A: Results not yet published. Check with course lecturer or wait for publish date.

**Q: My marks seem incorrect?**
A: Contact the course lecturer first, then Academic Affairs if unresolved.

**Q: Why do I see only partial marks?**
A: Some exam components may not be published yet.

---

## 17. CLASS ROUTINE

### Overview
View your weekly class schedule.

**URL:** `http://localhost/paxhitestbed/student/class-routine`

### Schedule Display

Shows for each class:
- Day and time
- Course name
- Lecturer
- Room/venue
- Section (if applicable)

### Viewing Options
- Weekly view
- Daily view
- Filter by course

### Common Questions

**Q: My schedule shows no classes?**
A: Either not configured for your semester, or no classes scheduled.

**Q: There's a conflict (two classes same time)?**
A: Contact Academic Affairs immediately.

---

## 18. EXAM ROUTINE

### Overview
View your examination schedule.

**URL:** `http://localhost/paxhitestbed/student/exam-routine`

### Schedule Information

Each exam entry shows:
- Date and time
- Course name and code
- Exam type (CA, Mid-semester, Final)
- Duration
- Venue/hall
- Seat number (if assigned)

### Important Notes
- Arrive 30 minutes before exam time
- Bring student ID
- Check venue carefully

### Common Questions

**Q: Why don't I see my exam schedule?**
A: Exam routine not yet published for your semester.

**Q: The venue changed, where do I get updates?**
A: Check notices or contact your department.

---

## 19. ATTENDANCE

### Overview
View your class attendance records.

**URL:** `http://localhost/paxhitestbed/student/attendance`

### Attendance Display

Shows:
- Course name
- Total classes held
- Classes attended
- Attendance percentage
- Status (Good/Warning/Critical)

### Attendance Thresholds

| Percentage | Status |
|------------|--------|
| 75%+ | Good (Green) |
| 50-74% | Warning (Yellow) |
| Below 50% | Critical (Red) |

### Impact of Low Attendance
- May affect exam eligibility
- Could result in course failure
- Check program policy

### Common Questions

**Q: Why is my attendance low when I attended classes?**
A: Attendance may not have been marked. Contact your lecturer.

**Q: Can I dispute attendance?**
A: Contact the course lecturer with evidence.

---

## 20. CLASS HUB (LIVE CLASSES)

### Overview
Interactive platform for live class engagement during scheduled sessions.

**URL:** `http://localhost/paxhitestbed/student/class-hub`

### Features

#### Today's Classes
- View all classes scheduled for today
- See which are live, upcoming, or completed
- Join live sessions

#### Live Session Features
- Real-time chat with classmates
- Ask questions to lecturer
- Send alerts (connectivity issues, etc.)
- Take personal notes
- Mark attendance (if delegated)

#### Class History
- View past class sessions
- Access session recordings (if available)
- Review topics covered
- Read session logbooks

#### My Notes
- View all notes taken during classes
- Organize by course and date
- Export or print notes

### Joining a Live Session

1. Go to Class Hub
2. Find the "LIVE" session
3. Click "Join"
4. Participate using available features

### Common Questions

**Q: Why don't I see any live classes?**
A: Either no classes scheduled now, or outside class time.

**Q: Chat not working?**
A: Refresh the page. Check internet connection.

**Q: How do I take notes during class?**
A: Use the Notes tab in the live session view. Notes auto-save.

---

## 21. ASSIGNMENTS

### Overview
View and submit assignments for your courses.

**URL:** `http://localhost/paxhitestbed/student/assignment`

### Viewing Assignments

1. Go to Assignments page
2. See all assignments for your enrolled courses
3. Filter by course, status, or due date

### Assignment Information

Each assignment shows:
- Course name
- Title and description
- Due date and time
- Status (Pending, Submitted, Graded)
- Marks (if graded)

### Submitting Assignments

1. Click on the assignment
2. Read instructions carefully
3. Upload your file(s)
4. Add comments (optional)
5. Click Submit

### File Requirements
- Allowed types: PDF, DOC, DOCX, PPT, ZIP (varies by assignment)
- Maximum file size: Usually 10MB
- File name should be clear

### Assignment Status

| Status | Meaning |
|--------|---------|
| **Pending** | Not yet submitted |
| **Submitted** | Successfully submitted |
| **Late** | Submitted after deadline |
| **Graded** | Marked by lecturer |
| **Returned** | Requires resubmission |

### Common Questions

**Q: Can I resubmit an assignment?**
A: Depends on lecturer settings. Check with your lecturer.

**Q: My submission failed?**
A: Check file size and format. Try again with smaller file.

**Q: I submitted late. Will it count?**
A: Depends on lecturer policy. Late submissions may have penalties.

---

## 22. LIBRARY

### Overview
View library-related information including borrowed books.

**URL:** `http://localhost/paxhitestbed/student/library`

### Features

- View currently borrowed books
- See due dates
- Check borrowing history
- View fines (if any)

### Borrowing Limits
- Maximum books: Usually 3-5 (varies)
- Loan period: Usually 14-21 days

### Common Questions

**Q: How do I borrow a book?**
A: Physical borrowing at the library. This portal shows your records.

**Q: I have a fine showing?**
A: Pay at the library or Finance office. Unpaid fines may block services.

---

## 23. E-LIBRARY

### Overview
Digital library with e-books, academic resources, and AI-powered features.

**URL:** `http://localhost/paxhitestbed/student/e-library`

### Features

#### Browse Books
- Search by title, author, subject
- Filter by category, language
- Sort by popularity, rating, date

#### Reading Books
- Online reader for PDFs and eBooks
- Progress tracking
- Bookmarking
- Note-taking

#### My Favorites
- Save books to favorites
- Quick access to preferred materials

#### Reading History
- Track what you've read
- Resume where you left off

### AI Features

#### AI Recommendations
- Personalized book suggestions based on:
  - Your reading history
  - Your program
  - Popular among peers

#### Reading Insights
- Reading statistics
- Time spent reading
- Books completed
- Progress analysis

### Common Questions

**Q: How do I access external resources?**
A: Some e-books link to external databases. Follow the provided links.

**Q: Can I download e-books?**
A: Depends on the book's license. Some allow download, others are view-only.

**Q: AI recommendations not showing?**
A: Read some books first to generate recommendations.

---

## 24. NOTICES

### Overview
Official announcements and notices from the institution.

**URL:** `http://localhost/paxhitestbed/student/notice`

### Notice Types
- Academic announcements
- Exam schedules
- Fee deadlines
- Events
- General information

### Viewing Notices
- Latest notices appear first
- Click to read full content
- Important notices may be highlighted

### Common Questions

**Q: How do I know about urgent notices?**
A: Check portal regularly. Critical notices may also be emailed.

---

## 25. LEAVE APPLICATIONS

### Overview
Apply for academic leave of absence.

**URL:** `http://localhost/paxhitestbed/student/leave`

### Applying for Leave

1. Go to Leave Applications
2. Click "Apply for Leave"
3. Fill in:
   - Leave type (medical, personal, etc.)
   - Start and end date
   - Reason
   - Supporting documents (if required)
4. Submit application

### Leave Status

| Status | Meaning |
|--------|---------|
| **Pending** | Awaiting approval |
| **Approved** | Leave granted |
| **Rejected** | Leave denied (see reason) |
| **Cancelled** | You cancelled the request |

### Common Questions

**Q: How long does approval take?**
A: Usually 2-5 working days, depending on leave type.

**Q: Can I cancel my leave?**
A: Only if still pending. Contact Academic Affairs for approved leaves.

---

## 26. DOWNLOADS

### Overview
Access downloadable materials and resources.

**URL:** `http://localhost/paxhitestbed/student/download`

### Available Downloads
- Course materials
- Handouts
- Policies and guidelines
- Forms and templates
- Academic calendars

### How to Download
1. Browse available files
2. Click download icon
3. File downloads to your device

---

## 27. PROFILE MANAGEMENT

### Overview
View and update your personal information.

**URL:** `http://localhost/paxhitestbed/student/profile`

### Profile Sections

#### Personal Information
- Name
- Date of birth
- Gender
- Address
- Photo

#### Contact Information
- Email address
- Phone number
- Emergency contact

#### Academic Information (View only)
- Program
- Matricule
- Enrollment date
- Current semester

### Changing Email

1. Go to Profile → Account
2. Click "Change Email"
3. Enter new email
4. Verify via link sent to new email

### Changing Password

1. Go to Profile → Account
2. Click "Change Password"
3. Enter current password
4. Enter new password (twice)
5. Save changes

### Password Requirements
- Minimum 8 characters
- At least one uppercase letter
- At least one number
- At least one special character

### Common Questions

**Q: I can't edit my name?**
A: Personal details require official request to Registrar.

**Q: How do I update my photo?**
A: Contact administration. Some institutions allow direct upload.

---

## 28. ERROR MESSAGES REFERENCE

### Authentication Errors

| Error | Meaning | Solution |
|-------|---------|----------|
| "Invalid credentials" | Wrong email/password | Check credentials, reset password |
| "Account locked" | Too many failed attempts | Wait 3 minutes or contact IT |
| "Session expired" | Login timeout | Log in again |
| "Unauthorized access" | No permission | Contact administration |

### Payment Errors

| Error | Meaning | Solution |
|-------|---------|----------|
| "Payment verification failed" | Receipt rejected | Check admin notes, reupload |
| "File too large" | Exceeds 2MB | Compress file |
| "Invalid file type" | Wrong format | Use JPG, PNG, or PDF |
| "Duplicate payment" | Already submitted | Check payment status |

### Academic Errors

| Error | Meaning | Solution |
|-------|---------|----------|
| "No enrollment found" | No active enrollment | Contact Registrar |
| "Course registration closed" | Period ended | Contact Academic Affairs |
| "Maximum credits exceeded" | Over limit | Drop a course |
| "Course already validated" | Already passed | No action needed |

### System Errors

| Error | Meaning | Solution |
|-------|---------|----------|
| "500 Internal Server Error" | System issue | Wait and retry, report if persists |
| "Page not found (404)" | Invalid URL | Use navigation menu |
| "Access denied (403)" | No permission | Platform fee may be unpaid |
| "Service unavailable" | Maintenance | Wait for restoration |

---

## 29. FREQUENTLY ASKED QUESTIONS

### Account & Login

**Q: I forgot my password. What do I do?**
A: Click "Forgot Password" on login page, enter your email, and follow the reset link.

**Q: My account is locked. How do I unlock it?**
A: Wait 3 minutes and try again. If still locked, contact IT support.

**Q: Can I access from my phone?**
A: Yes, the portal is mobile-responsive. Use any modern browser.

### Fees & Payments

**Q: What payment methods are accepted?**
A: Bank transfer, mobile money (if enabled), and cash at designated points.

**Q: My payment receipt was rejected. What should I do?**
A: Read the admin notes, get a clearer receipt or correct amount, and resubmit.

**Q: Can I pay in installments?**
A: Yes, request a payment plan through Finance office.

**Q: How do I get a receipt for my payment?**
A: Print from the Fees section after payment is verified.

### Academics

**Q: How do I register for courses?**
A: Go to Course Registration, add courses from available list.

**Q: My grades are not showing?**
A: Grades appear after publication. Check with lecturer or wait.

**Q: How do I apply for resit?**
A: Go to Resit Applications, find the failed course, and apply.

**Q: What's the passing grade?**
A: 50% (C grade). Below is failure.

### Documents

**Q: How do I get my transcript?**
A: View in portal. Official transcript requires formal request to Registrar.

**Q: What is Form A2?**
A: Official admission confirmation document showing fee payment.

### Technical

**Q: Portal is slow. What should I do?**
A: Clear browser cache, try different browser, check internet connection.

**Q: File upload keeps failing?**
A: Check file size (max 2MB for receipts, 10MB for assignments) and format.

**Q: Page not loading correctly?**
A: Clear cache, refresh, or try incognito mode.

---

## 30. TROUBLESHOOTING GUIDE

### Login Issues

**Problem: Can't log in despite correct credentials**
1. Clear browser cache and cookies
2. Try incognito/private mode
3. Check if Caps Lock is on
4. Try a different browser
5. Request password reset
6. Contact IT if all fails

**Problem: 2FA code not received**
1. Wait 2-3 minutes
2. Check spam/junk folder
3. Verify email address is correct
4. Request new code
5. Contact IT if persists

### Page Loading Issues

**Problem: Pages loading slowly**
1. Check internet connection
2. Close other browser tabs
3. Clear browser cache
4. Try different browser
5. Try at different time (off-peak hours)

**Problem: Page shows error**
1. Refresh the page
2. Clear cache
3. Log out and log in again
4. Try different browser
5. Report to IT if persists

### Payment Issues

**Problem: Receipt upload fails**
1. Check file size (max 2MB)
2. Use JPG, PNG, or PDF format
3. Rename file (remove special characters)
4. Compress image if too large
5. Try different browser

**Problem: Payment verified but fee still shows unpaid**
1. Refresh the page
2. Log out and back in
3. Wait 24 hours for sync
4. Contact Finance

### Academic Issues

**Problem: Can't see my courses**
1. Check if correct program selected
2. Verify enrollment status
3. Check if registration period open
4. Contact Academic Affairs

**Problem: Grades missing**
1. Check publish date (future date means not yet)
2. Verify marks were entered
3. Contact course lecturer
4. Wait for exam board

### General Steps for Any Issue

1. **Refresh**: Press F5 or click refresh
2. **Clear Cache**: Browser settings → Clear data
3. **Different Browser**: Try Chrome, Firefox, or Edge
4. **Incognito Mode**: Opens clean session
5. **Re-login**: Log out completely, then back in
6. **Report**: If problem persists, note error message and report

---

## 31. ESCALATION GUIDELINES

### When to Escalate to Human Support

The AI chatbot should escalate when:

1. **Technical Issues**
   - System errors (500, 502, 503)
   - Database errors
   - Features completely non-functional
   - Security concerns

2. **Account Issues**
   - Account locked beyond self-service
   - Account compromised
   - Unable to verify identity
   - Account deletion requests

3. **Financial Issues**
   - Payment discrepancies not resolved
   - Refund requests
   - Fee disputes
   - Financial aid questions

4. **Academic Issues**
   - Grade disputes
   - Course approval requests
   - Program changes
   - Academic appeals

5. **Personal Data Changes**
   - Name changes
   - Date of birth corrections
   - Program transfers
   - ID corrections

### Escalation Information to Collect

Before escalating, gather:
- Student's full name
- Matricule number
- Email address
- Program and semester
- Specific issue description
- Steps already tried
- Error messages (if any)
- Screenshots (if possible)

### Support Contacts

| Issue Type | Department | Contact Method |
|------------|------------|----------------|
| Technical | IT Support | it@institution.edu |
| Financial | Finance Office | finance@institution.edu |
| Academic | Academic Affairs | academic@institution.edu |
| Enrollment | Registrar | registrar@institution.edu |
| General | Student Affairs | studentaffairs@institution.edu |

### Response Time Expectations

| Priority | Response Time |
|----------|---------------|
| Critical (system down) | 1-2 hours |
| High (can't use essential feature) | 4-8 hours |
| Medium (non-critical issue) | 24-48 hours |
| Low (general inquiry) | 2-5 business days |

---

## APPENDIX A: NAVIGATION QUICK REFERENCE

### Main Menu Items

| Menu Item | URL Path | Description |
|-----------|----------|-------------|
| Dashboard | /student/dashboard | Home page with overview |
| Class Routine | /student/class-routine | Weekly schedule |
| Exam Routine | /student/exam-routine | Exam schedule |
| Exam Results | /student/exam-results | View marks and grades |
| Course Registration | /student/course-registration | Add/drop courses |
| Form A2 | /student/form-a2 | Admission confirmation |
| Form A3 Docs | /student/form-a3 | Official documents |
| Attendance | /student/attendance | Attendance records |
| Class Hub | /student/class-hub | Live class interaction |
| Leave | /student/leave | Leave applications |
| Fees | /student/fees | Fee statements |
| Manual Payment | /student/manual-payment | Upload payment receipts |
| Multi-Fee Payment | /student/multi-payment | Pay multiple fees |
| Payment Plans | /student/payment-plan | Installment plans |
| Library | /student/library | Physical library |
| E-Library | /student/e-library | Digital library |
| Notices | /student/notice | Announcements |
| Assignments | /student/assignment | View/submit assignments |
| Downloads | /student/download | Downloadable resources |
| Transcript | /student/transcript | Academic record |
| Resits | /student/resit | Resit applications |
| Profile | /student/profile | Personal information |

---

## APPENDIX B: GRADE SCALE REFERENCE

| Letter Grade | Grade Point | Percentage Range | Description |
|--------------|-------------|------------------|-------------|
| A+ | 4.0 | 90-100% | Excellent |
| A | 4.0 | 85-89% | Excellent |
| A- | 3.7 | 80-84% | Very Good |
| B+ | 3.3 | 75-79% | Good |
| B | 3.0 | 70-74% | Good |
| B- | 2.7 | 65-69% | Above Average |
| C+ | 2.3 | 60-64% | Average |
| C | 2.0 | 55-59% | Average |
| C- | 1.7 | 50-54% | Pass |
| F | 0.0 | Below 50% | Fail |

---

## APPENDIX C: FEE CATEGORY CODES

| Code | Category | Description |
|------|----------|-------------|
| FI | First Installment | Initial semester payment |
| SI | Second Installment | Secondary semester payment |
| TF | Tuition Fee | Main academic fee |
| RF | Registration Fee | Enrollment processing |
| LF | Library Fee | Library services |
| LAB | Laboratory Fee | Lab usage |
| RST | Resit Fee | Resit examination |
| PLAT | Platform Fee | System access fee |

---

## APPENDIX D: COMMON KEYBOARD SHORTCUTS

| Shortcut | Action |
|----------|--------|
| F5 | Refresh page |
| Ctrl+F | Find on page |
| Ctrl+P | Print |
| Ctrl++ | Zoom in |
| Ctrl+- | Zoom out |
| Ctrl+0 | Reset zoom |
| Tab | Move to next field |
| Enter | Submit form/button |

---

## APPENDIX E: MOBILE ACCESS TIPS

1. **Responsive Design**: Portal works on mobile browsers
2. **Recommended Browsers**: Chrome, Safari, Firefox
3. **Orientation**: Landscape may work better for tables
4. **File Upload**: Can upload directly from phone camera
5. **Notifications**: Enable browser notifications for alerts
6. **Bookmarks**: Save login page for quick access

---

## Document Information

- **Version**: 1.0
- **Last Updated**: January 2025
- **Applicable To**: PAXHI Student Portal
- **Audience**: AI Chatbot Training / Support Staff Reference
- **Maintainer**: IT Department

---

*This document is intended for training AI support models and as a reference for human support staff. It should be updated when portal features change.*
