# Student Portal AI Chatbot Training Dataset
## Comprehensive Documentation for First-Level Support

**Version:** 1.0  
**Last Updated:** December 9, 2025  
**Purpose:** Train AI chatbot for first-level student support

---

## Table of Contents

1. [Authentication & Account Security](#1-authentication--account-security)
2. [Dashboard & Academic Overview](#2-dashboard--academic-overview)
3. [Course Registration](#3-course-registration)
4. [Fees & Payments](#4-fees--payments)
5. [Class Hub (Live Classes)](#5-class-hub-live-classes)
6. [Exam Results & Transcript](#6-exam-results--transcript)
7. [Resit Management](#7-resit-management)
8. [Attendance](#8-attendance)
9. [Library & E-Library](#9-library--e-library)
10. [Profile Management](#10-profile-management)
11. [Leave Application](#11-leave-application)
12. [Notices & Assignments](#12-notices--assignments)
13. [Forms (A2 & A3)](#13-forms-a2--a3)
14. [Program Switching (Multi-Program Students)](#14-program-switching-multi-program-students)
15. [Escalation Triggers](#15-escalation-triggers)
16. [Common Error Messages](#16-common-error-messages)

---

## 1. Authentication & Account Security

### Login Process

**How it works:**
- Students login using email + password
- Optional Two-Factor Authentication (2FA) via email code
- Login throttling: 5 failed attempts = 3-minute lockout

**Common Questions & Answers:**

**Q: I forgot my password. How do I reset it?**
> A: Click "Forgot Password" on the login page, enter your registered email address, and check your inbox for a password reset link. The link expires after a set period. If you don't receive the email, check your spam folder.

**Q: I'm locked out after too many failed login attempts. What do I do?**
> A: Wait 3 minutes and try again. The system temporarily locks accounts after 5 failed attempts to protect your security. If problems persist, contact the IT helpdesk.

**Q: I received a verification code but it's not working.**
> A: Verification codes expire quickly. Request a new code and enter it immediately. Make sure you're entering the most recent code received.

**Q: How do I enable Two-Factor Authentication (2FA)?**
> A: 2FA is configured by the institution. If it's enabled, you'll automatically receive a verification code to your email after entering your password. Contact IT if you need to update your email for 2FA.

**Q: My email for login is wrong. How do I change it?**
> A: Login to your account, go to Profile, and use the "Change Email" option. Note: Changing your email will log you out and you'll need to login with the new email.

### Issues Requiring Escalation:
- Account completely inaccessible after lockout period
- Email not on file in the system
- 2FA codes never arriving (email delivery issues)
- Account suspended or deactivated

---

## 2. Dashboard & Academic Overview

### What Students See

**Dashboard Components:**
- **CGPA (Cumulative GPA):** Overall academic performance
- **Credits Earned:** Total validated credits
- **Credits Attempted:** Total credits registered
- **Graduation Eligibility:** Whether student has met all requirements
- **Course Breakdown:** Performance by semester

**How CGPA is Calculated:**
- Only courses with published marks are included
- Uses weighted average: (Credits × GPA Points) / Total Credits
- Matches transcript calculation exactly
- Excludes exempted courses from GPA calculation

**Common Questions & Answers:**

**Q: My CGPA on the dashboard doesn't match what I calculated. Why?**
> A: The dashboard uses the same formula as your official transcript. Possible reasons for difference:
> 1. Some marks may not be published yet (professors need to release them)
> 2. Exempted courses are excluded from GPA calculation
> 3. Only completed courses with final grades are included
> 4. Check your transcript for the detailed breakdown

**Q: What does "Graduation Eligibility: Not Eligible" mean?**
> A: This means you haven't yet met all requirements for graduation. Common requirements include:
> - Completing all required credits
> - Passing all mandatory courses
> - Achieving minimum CGPA
> - Clearing all financial obligations
> Contact your academic advisor for specific requirements.

**Q: Why don't I see any data on my dashboard?**
> A: This usually happens when:
> 1. You're new and haven't completed any semester yet
> 2. Your enrollment isn't set - use the program switcher if you have multiple programs
> 3. Marks haven't been published yet
> If you've been enrolled for at least one semester, contact the Registrar.

**Q: What's the difference between Credits Earned and Credits Attempted?**
> A: 
> - **Credits Attempted:** All courses you registered for
> - **Credits Earned:** Only courses you successfully passed/validated
> Failed courses count as attempted but not earned.

---

## 3. Course Registration

### How Course Registration Works

**Process:**
1. Registration opens during specific periods
2. Students see available courses based on their program/semester
3. Credit limits apply (minimum and maximum per semester)
4. System validates prerequisites and excludes already-passed courses
5. Carry-over courses (failed courses) are automatically included

**Key Rules:**
- Cannot register for courses already validated (passed)
- Cannot exceed program's maximum credit limit
- Cannot go below program's minimum credit limit
- Carry-over courses take priority
- Some courses may require prerequisites

**Common Questions & Answers:**

**Q: Why can't I see certain courses in my available courses list?**
> A: Courses are filtered based on:
> 1. Your current semester and program
> 2. Courses you've already passed are hidden
> 3. Prerequisite requirements may not be met
> 4. Course may not be offered this semester
> Contact your department if you believe a course is missing.

**Q: What are carry-over courses?**
> A: These are courses you previously failed (marks below 50%) that you must re-register for. They appear automatically in your course list. You cannot deselect mandatory carry-over courses.

**Q: I'm getting "Maximum credit limit exceeded" error. What do I do?**
> A: Each program has a maximum credit limit per semester. To fix:
> 1. Remove some elective courses
> 2. Note that carry-over courses count toward your limit
> 3. Contact the Registrar if you need special permission to exceed the limit

**Q: I'm getting "Minimum credit limit not met" error. What do I do?**
> A: You must register for at least the minimum required credits. Add more courses to meet the minimum. If there aren't enough courses available, contact your department.

**Q: How do I drop a course after registration?**
> A: Course drops are typically handled by the Registrar's office within the add/drop period. Contact them directly with your request. Note: There may be deadlines and consequences for late drops.

**Q: When does course registration open/close?**
> A: Registration periods are announced by the institution. Check the notices section or contact the Registrar's office for specific dates.

### Issues Requiring Escalation:
- System showing wrong courses for program
- Prerequisites not being recognized
- Registration period issues
- Credit limit adjustment requests
- Course not appearing when it should

---

## 4. Fees & Payments

### Fee Structure

**How Fees Work:**
- Fees are assigned per enrollment (program/session/semester)
- Categories include: Tuition, Lab Fees, Library, Exam Fees, etc.
- Discounts may apply based on eligibility
- Late payment fines are calculated automatically
- Payment plans allow installment payments

### Payment Methods

1. **Manual Payment (Receipt Upload)**
   - Make payment at bank/mobile money
   - Upload receipt with details
   - Wait for admin verification
   - Status: Pending → Verified/Rejected

2. **Multi-Payment**
   - Pay multiple fee items at once
   - Distribute payment across fees (oldest first by default)
   - Upload single receipt for all
   - Admin verifies and distributes

3. **Payment Plans**
   - Break large fees into installments
   - Each installment has its own due date
   - Upload receipts per installment
   - Track overdue installments

**Common Questions & Answers:**

**Q: How do I pay my fees?**
> A: 
> 1. Go to "Fees Report" to see your outstanding fees
> 2. Make payment via bank transfer or mobile money using the institution's payment details
> 3. Go to "Manual Payment" and upload your receipt
> 4. Wait for admin verification (usually 24-48 hours)
> 5. Once verified, your fee status will update

**Q: My payment was verified but my fee balance didn't update. Why?**
> A: Contact the Bursary/Finance office with:
> - Your payment receipt
> - The verification confirmation
> - The specific fee that should have been updated
> This requires human verification of records.

**Q: I uploaded a wrong receipt. How do I fix it?**
> A: If the payment is still "Pending", you may be able to cancel and resubmit. If it's already processed, contact the Finance office to correct the error.

**Q: What is a payment plan?**
> A: A payment plan allows you to pay a large fee in smaller installments over time. Each installment has:
> - Amount due
> - Due date
> - Status (pending/partial/paid/overdue)
> You can view your payment plans under "Payment Plans" in the menu.

**Q: I see "Overdue Installments". What does this mean?**
> A: You have installment payments past their due date. This may:
> - Incur late fees
> - Affect your access to certain services
> - Impact exam eligibility
> Pay overdue installments as soon as possible.

**Q: Why is there a fine on my fee?**
> A: Fines are applied automatically when:
> - Payment is made after the due date
> - Fine rate depends on how many days late
> - Fine can be fixed amount or percentage
> Contact Finance office if you believe the fine is incorrect.

**Q: How do I check if my payment was verified?**
> A: Go to "Manual Payment" or "Multi-Payment" and check the status column:
> - **Pending**: Waiting for admin review
> - **Verified/Approved**: Payment accepted
> - **Rejected**: Payment not accepted (check reason)

**Q: Can I get a discount on my fees?**
> A: Discounts are applied automatically based on:
> - Eligibility criteria set by the institution
> - Application within discount period
> Contact the Finance office to check your discount eligibility.

### Issues Requiring Escalation:
- Payment verified but balance not updated
- Incorrect fee amounts
- Disputed fines
- Payment plan setup/modification requests
- Receipt verification taking too long (>48 hours)
- Wrong fee assigned to account

---

## 5. Class Hub (Live Classes)

### How Class Hub Works

**Features:**
- View today's classes
- Join live class sessions
- Clock in/out for attendance
- Participate in class chat
- Take personal notes
- Create alerts/reminders
- Ask questions (can be anonymous)
- View class history

**Class Session Status:**
- **Pending:** Scheduled but not started
- **In Progress:** Live and active
- **Completed:** Session ended

**Attendance Tracking:**
- Automatic clock-in when joining
- Late detection (after grace period)
- Class rep may mark attendance for others

**Common Questions & Answers:**

**Q: How do I join a live class?**
> A: 
> 1. Go to "Class Hub" → "Today's Classes"
> 2. Look for classes with "In Progress" status
> 3. Click "Join Class" or the session to enter
> 4. You'll be automatically clocked in

**Q: Why can't I see my classes?**
> A: Check the following:
> 1. Make sure you have the correct program selected (use program switcher)
> 2. Classes only appear for your current semester
> 3. Classes must be scheduled for today to appear in "Today's Classes"
> 4. Check "History" for past classes

**Q: How does attendance work in Class Hub?**
> A: 
> - You're marked present when you join a live class
> - There's a grace period (usually 15 minutes) for "on time" marking
> - After grace period, you're marked "late"
> - Class rep may also mark your attendance via scanner

**Q: Can I take notes during class?**
> A: Yes! Each class has a personal notes section. Your notes are:
> - Private (only you can see them)
> - Automatically saved
> - Searchable from "My Notes" section
> - Exportable to text file

**Q: What are class alerts?**
> A: Alerts are reminders students can create for the class:
> - Assignment deadlines
> - Exam dates
> - Important announcements
> - Other students can upvote helpful alerts

**Q: How do I ask a question in class?**
> A: Use the Q&A feature during a live class:
> 1. Click "Ask Question"
> 2. Type your question
> 3. Choose if you want it anonymous
> 4. Other students can upvote good questions
> 5. Teacher can address and mark questions as answered

**Q: What is a "Class Representative" role in Class Hub?**
> A: Teachers can delegate responsibilities to a class rep:
> - **Logbook Access:** Fill in class topics and summaries
> - **Attendance Access:** Mark attendance for classmates via scanner
> This is temporary and session-specific.

**Q: Why can't I chat in the class?**
> A: The teacher may have disabled chat for that session. This is a teacher setting you cannot change.

**Q: I was marked absent but I attended. What do I do?**
> A: Contact your teacher or the class rep if one was assigned. If the issue persists, contact the Registrar with:
> - The class date and subject
> - Evidence of attendance if available

### Joint Classes Note:
When multiple programs share a class (joint classes), all students see the same chat and alerts regardless of their specific program.

### Issues Requiring Escalation:
- Incorrect attendance status after attending
- Unable to access classes that should be available
- Technical issues with live room
- Class rep delegation issues

---

## 6. Exam Results & Transcript

### Viewing Exam Results

**How It Works:**
- Results appear only after being published by teachers
- Publication requires both mark entry and official release
- Filter results by session, semester, and exam type

**Result Status:**
- Only "PUBLISHED" results are visible
- Results have publish date/time controls
- Teachers must complete the workflow before students see marks

**Common Questions & Answers:**

**Q: Why can't I see my exam results?**
> A: Results are only visible when:
> 1. The teacher has entered and finalized marks
> 2. The results have been officially published
> 3. The publish date has passed
> Check back later or contact your teacher if you believe results should be available.

**Q: What do the grades mean?**
> A: Common grade scale:
> - A (80-100): Excellent
> - B+ (70-79): Very Good
> - B (60-69): Good
> - C+ (55-59): Satisfactory
> - C (50-54): Pass
> - F (0-49): Fail
> Grade scales may vary by institution.

**Q: My marks don't look right. Who do I contact?**
> A: Contact your course teacher first. If unresolved, contact:
> 1. Department Head
> 2. Registrar's Office
> Keep your exam materials as reference.

**Q: How do I access my transcript?**
> A: Go to "Transcript" in the menu to view:
> - All completed semesters with grades
> - GPA per semester
> - CGPA trend chart
> - Credit summary
> For official transcripts, contact the Registrar's office.

**Q: Why does my transcript show different marks than what I calculated?**
> A: Transcripts show:
> - Only published and finalized marks
> - May exclude certain course types (exemptions, audits)
> - Uses official institution formulas

### Issues Requiring Escalation:
- Marks appear incorrect
- Missing course from transcript
- Official transcript requests
- Grade disputes

---

## 7. Resit Management

### How Resits Work

**Process:**
1. Failed courses (< 50%) are identified as resit-eligible
2. Students can request resit registration
3. Resit exams are scheduled separately
4. Resit marks replace original failed marks

**Progression System:**
- Students may need to clear resits before progressing
- Some programs allow conditional progression
- Progression eligibility is checked automatically

**Common Questions & Answers:**

**Q: Which courses am I eligible to resit?**
> A: Go to "Resits" to see:
> - Failed courses from previous semesters
> - Resit request status
> - Resit exam schedule (if available)

**Q: How do I request a resit?**
> A: 
> 1. Go to "Resits" section
> 2. Find the failed course
> 3. Click "Request Resit"
> 4. Pay any resit fees if required
> 5. Wait for approval

**Q: What happens if I fail a resit?**
> A: Policies vary by institution:
> - You may need to repeat the course entirely
> - There may be a limit on resit attempts
> - Contact your academic advisor for your specific situation

**Q: Can I progress to the next semester with failed courses?**
> A: This depends on:
> - Number of failed courses/credits
> - Your program's progression rules
> - Institution policy
> Check the "Progression" section or contact the Registrar.

**Q: How do resit marks affect my GPA?**
> A: When you pass a resit:
> - The resit mark replaces the failed mark
> - Your GPA is recalculated
> - Some institutions cap resit marks

### Issues Requiring Escalation:
- Resit not appearing when expected
- Resit fee disputes
- Progression blocked incorrectly
- Resit mark not updating

---

## 8. Attendance

### Traditional Attendance (Non-Class Hub)

**How It Works:**
- Teachers mark attendance during classes
- Status: Present, Absent, Late
- View by month/year

**Common Questions & Answers:**

**Q: How do I check my attendance record?**
> A: Go to "Attendance" in the menu:
> 1. Select month and year
> 2. View attendance calendar
> 3. See present/absent/late counts

**Q: Why don't I see attendance records?**
> A: Possible reasons:
> 1. No enrollment selected - use program switcher
> 2. Attendance not marked by teacher yet
> 3. You're viewing wrong month/year

**Q: My attendance is marked wrong. How do I correct it?**
> A: Contact your teacher to request correction. Bring evidence if you have it (such as notes from that day).

### Issues Requiring Escalation:
- Systematic attendance errors
- Attendance affecting academic standing

---

## 9. Library & E-Library

### Physical Library

**Features:**
- View books issued to you
- See due dates
- Track return status
- Check for overdue items

**Common Questions & Answers:**

**Q: How do I see which books I've borrowed?**
> A: Go to "Library" in the menu to see all books issued to your account with:
> - Book title
> - Issue date
> - Due date
> - Return status

**Q: I don't see the Library option. Why?**
> A: You may not have a library membership. Visit the library to create your membership account.

### E-Library (Digital Library)

**Features:**
- Browse digital books by category
- Search by title, author, publisher
- Read books online
- Download books (if enabled)
- Add to favorites
- Track reading progress
- AI-powered features:
  - Book recommendations based on reading history
  - Book summaries
  - Natural language search
  - Reading insights

**Common Questions & Answers:**

**Q: How do I access the E-Library?**
> A: Go to "E-Library" → "Browse Books" to:
> - Search for books
> - Filter by category
> - Sort by popularity, rating, or recent

**Q: How do I download a book?**
> A: Not all books are downloadable. If available:
> 1. Open the book details page
> 2. Look for "Download" button
> 3. Only local (uploaded) books support download
> External/linked books must be read online.

**Q: What are AI Recommendations?**
> A: Based on your reading history and favorites, AI suggests books you might enjoy. The more you read, the better the recommendations.

**Q: How do I rate/review a book?**
> A: On the book's detail page:
> 1. Click on the rating stars
> 2. Write an optional review
> 3. Submit

### Issues Requiring Escalation:
- Book not accessible
- Download errors
- Incorrect library records

---

## 10. Profile Management

### What Can Be Updated

**View Only:**
- Basic information (set by institution)
- Student ID/Matricule
- Program details

**Can Update:**
- Profile photo
- Contact information (phone, address)
- Emergency contact

**Requires Logout:**
- Email change (will need to login with new email)
- Password change (will be logged out)

**Common Questions & Answers:**

**Q: How do I change my password?**
> A: 
> 1. Go to Profile
> 2. Click "Change Password"
> 3. Enter current password
> 4. Enter new password (twice)
> 5. Submit - you'll be logged out
> 6. Login with new password

**Q: How do I change my email?**
> A: 
> 1. Go to Profile
> 2. Click "Change Email"
> 3. Enter new email address
> 4. Enter your password to confirm
> 5. Submit - you'll be logged out
> 6. Login with new email

**Q: I can't update my name/date of birth. Why?**
> A: Personal information changes require official documentation. Contact the Registrar's office with supporting documents.

**Q: How do I upload a profile photo?**
> A: 
> 1. Go to Profile
> 2. Click on the profile photo or "Change Photo"
> 3. Upload image (JPG/PNG, usually max 2MB)
> 4. Save changes

### Issues Requiring Escalation:
- Name/DOB corrections needed
- Email issues (can't change, no access to old email)
- Account information wrong

---

## 11. Leave Application

### How Leave Works

**Types of Leave:**
- Sick leave
- Family emergency
- Personal/Other

**Process:**
1. Submit leave application with dates
2. Attach supporting documents if required
3. Wait for approval
4. Check status in leave history

**Common Questions & Answers:**

**Q: How do I apply for leave?**
> A: 
> 1. Go to "Leave Application"
> 2. Click "Apply for Leave"
> 3. Fill in dates and reason
> 4. Attach documents if required
> 5. Submit

**Q: How do I check my leave status?**
> A: Go to "Leave Application" to see all your leave requests with:
> - Status (Pending/Approved/Rejected)
> - Dates applied for
> - Approver comments

**Q: What documents do I need for sick leave?**
> A: Usually:
> - Medical certificate from licensed doctor
> - Include dates covered
> Check with your department for specific requirements.

**Q: My leave was rejected. What now?**
> A: Check the rejection reason. You may:
> - Resubmit with correct information/documents
> - Contact your department head for clarification
> - Appeal through proper channels

### Issues Requiring Escalation:
- Leave affecting academic standing
- Leave approval delays
- Emergency leave situations

---

## 12. Notices & Assignments

### Notices

**How It Works:**
- Institution/department posts notices
- Filtered by your program
- Read status tracked
- Attachments may be included

**Common Questions & Answers:**

**Q: How do I view notices?**
> A: Go to "Notices" in the menu. Notices are sorted by date with newest first. Click on a notice to see full details.

**Q: Why don't I see a notice my classmate sees?**
> A: Notices can be program-specific. If you're in different programs, you may see different notices. Use the program switcher if you have multiple programs.

### Assignments

**How It Works:**
- Teachers post assignments with due dates
- Upload your work before deadline
- View assignment details and attachments
- Track submission status

**Common Questions & Answers:**

**Q: How do I submit an assignment?**
> A: 
> 1. Go to "Assignments"
> 2. Find the assignment
> 3. Click to view details
> 4. Upload your file (PDF, DOCX, ZIP, XLSX, PPT - max 20MB)
> 5. Submit before the deadline

**Q: I submitted the wrong file. Can I resubmit?**
> A: Policy varies. Some assignments allow resubmission, others don't. Contact your teacher immediately if you submitted incorrectly.

**Q: I missed the deadline. Can I still submit?**
> A: Depends on teacher settings. Late submissions may:
> - Not be allowed
> - Be accepted with penalties
> - Require teacher approval
> Contact your teacher for extensions.

### Issues Requiring Escalation:
- Technical issues with file upload
- Assignment not appearing
- Extension requests
- Grade disputes

---

## 13. Forms (A2 & A3)

### Form A2 (Admission Confirmation)

**Purpose:** Official document confirming admission and first payment

**Contains:**
- Student information
- Program details
- Fee breakdown for first installment
- Total amount in words and figures

**Common Questions & Answers:**

**Q: What is Form A2?**
> A: Form A2 is your Admission Confirmation document that shows you've paid the first installment and confirmed your enrollment.

**Q: How do I download Form A2?**
> A: Go to "Form A2" in the menu and click "Download" or "Preview".

### Form A3 (Course Registration Confirmation)

**Purpose:** Official document confirming registered courses for a semester

**Contains:**
- Student and program info
- List of registered courses
- Credit totals
- Semester details

**Common Questions & Answers:**

**Q: What is Form A3?**
> A: Form A3 is your Course Registration Confirmation showing all courses you're registered for in a semester.

**Q: How do I get Form A3?**
> A: 
> 1. Go to "Form A3" in the menu
> 2. Filter by session/semester if needed
> 3. Click on the form to view/download
> Form A3 is generated automatically after you register courses.

**Q: Why don't I see Form A3 for a semester?**
> A: Form A3 requires:
> 1. Completed course registration
> 2. At least one course registered
> If you've registered but don't see it, wait a few minutes or refresh.

### Issues Requiring Escalation:
- Form information incorrect
- Form not generating
- Need official stamped copies

---

## 14. Program Switching (Multi-Program Students)

### For Students in Multiple Programs

**How It Works:**
- Students enrolled in multiple programs have a switcher
- Switching changes the context for all portal features
- Each program has its own:
  - Dashboard stats
  - Course registration
  - Fees
  - Transcript
  - Attendance

**Common Questions & Answers:**

**Q: I'm in two programs. How do I switch between them?**
> A: Look for the program switcher:
> 1. Usually in the header/navbar
> 2. Shows your current program
> 3. Click to see all your enrollments
> 4. Select the program you want to view

**Q: Why do I see different fees/courses when I switch programs?**
> A: Each program has its own:
> - Fee structure
> - Course curriculum
> - Semester schedule
> Switching programs changes the context for everything you see.

**Q: I should have multiple programs but only see one. Why?**
> A: Contact the Registrar's office. Your enrollments may:
> - Not be linked to your account
> - Be in a different status
> - Need to be activated

### Issues Requiring Escalation:
- Programs not appearing
- Switching not working
- Wrong program showing as default

---

## 15. Escalation Triggers

### When to Escalate to Human Support

The AI chatbot should escalate when:

**Authentication Issues:**
- [ ] Account locked and reset not working
- [ ] No access to registered email
- [ ] Account suspended/deactivated
- [ ] 2FA completely not working after troubleshooting

**Financial Issues:**
- [ ] Payment verified but balance not updated
- [ ] Incorrect fee amounts
- [ ] Fine disputes
- [ ] Refund requests
- [ ] Payment plan modifications
- [ ] Payment took over 48 hours to verify

**Academic Issues:**
- [ ] Grade disputes
- [ ] Transcript corrections
- [ ] Course registration errors
- [ ] Progression issues
- [ ] Resit not appearing
- [ ] Attendance affecting grades

**Technical Issues:**
- [ ] Consistent errors after troubleshooting
- [ ] Data not loading repeatedly
- [ ] Upload failures persisting
- [ ] Class Hub technical issues

**Documentation Issues:**
- [ ] Name/DOB corrections
- [ ] Official document requests
- [ ] Form errors

**Urgent Situations:**
- [ ] Exam access blocked
- [ ] Academic deadline issues
- [ ] Emergency leave
- [ ] Safety concerns

### Escalation Response Template:

> "I understand your concern. This issue requires assistance from our support team. Please contact:
> 
> **[Department Name]**  
> Email: [email]  
> Phone: [phone]  
> Office: [location]  
> Hours: [hours]
> 
> Please have ready:
> - Your student ID
> - A description of the issue
> - Any relevant screenshots or documents
> 
> Reference Number: [Generate if applicable]"

---

## 16. Common Error Messages

### Authentication Errors

| Error | Meaning | Solution |
|-------|---------|----------|
| "Too many login attempts" | 5+ failed logins | Wait 3 minutes, then try again |
| "Invalid credentials" | Wrong email/password | Check email spelling, reset password if needed |
| "Account not verified" | Email not confirmed | Check email for verification link |
| "Verification code expired" | 2FA code too old | Request new code |

### Fee Errors

| Error | Meaning | Solution |
|-------|---------|----------|
| "Duplicate pending receipt" | Already submitted receipt pending | Wait for current receipt to be processed |
| "File too large" | Receipt file exceeds limit | Compress file, use smaller image |
| "Invalid file type" | Wrong format | Use JPG, PNG, or PDF |

### Course Registration Errors

| Error | Meaning | Solution |
|-------|---------|----------|
| "Maximum credit limit exceeded" | Over semester limit | Remove some courses |
| "Minimum credit limit not met" | Under semester limit | Add more courses |
| "Prerequisite not met" | Missing required course | Complete prerequisite first |
| "Course already validated" | Already passed | Cannot re-register passed courses |

### Class Hub Errors

| Error | Meaning | Solution |
|-------|---------|----------|
| "No enrollment found" | Program not selected | Use program switcher |
| "You do not have access" | Wrong program/semester | Check enrollment |
| "Chat is disabled" | Teacher turned off chat | Contact teacher if needed |

### General Errors

| Error | Meaning | Solution |
|-------|---------|----------|
| "Session expired" | Logged out | Login again |
| "404 Not Found" | Page doesn't exist | Check URL, use menu |
| "500 Server Error" | System issue | Wait and retry, report if persists |
| "Enrollment not found" | No active enrollment | Use program switcher or contact Registrar |

---

## Quick Reference: Navigation Paths

| Feature | Menu Path |
|---------|-----------|
| Dashboard | Dashboard |
| Class Routine | Academics → Class Routine |
| Exam Routine | Academics → Exam Routine |
| Exam Results | Academics → Exam Results |
| Course Registration | Academics → Course Registration |
| Form A2 | Documents → Form A2 |
| Form A3 | Documents → Form A3 Documents |
| Attendance | Academics → Attendance |
| Class Hub | Class Hub → Today's Classes |
| Class History | Class Hub → History |
| My Notes | Class Hub → My Notes |
| Leave | Leave Application |
| Fees Report | Fees → Fees Report |
| Manual Payment | Fees → Manual Payment |
| Multi-Payment | Fees → Multi-Payment |
| Payment Plans | Fees → Payment Plans |
| Library | Library |
| E-Library | E-Library → Browse Books |
| Favorites | E-Library → Favorites |
| Reading History | E-Library → History |
| AI Recommendations | E-Library → AI Recommendations |
| Notices | Notices |
| Assignments | Assignments |
| Downloads | Downloads |
| Transcript | Transcript |
| Resits | Resits |
| Profile | Profile |

---

## Chatbot Conversation Examples

### Example 1: Fee Inquiry
**User:** "How do I pay my fees?"  
**Bot:** "To pay your fees:
1. First, check your outstanding fees at Fees → Fees Report
2. Make payment via bank transfer or mobile money using the institution's payment details
3. Go to Fees → Manual Payment and upload your receipt
4. Wait for admin verification (usually 24-48 hours)
5. Once verified, your fee status will update

Would you like me to explain any of these steps in more detail?"

### Example 2: Missing Grades
**User:** "I can't see my exam results"  
**Bot:** "Exam results are only visible after they've been published by your teacher. This requires:
1. The teacher to enter and finalize marks
2. Official release/publication of results
3. The publish date to have passed

If your classmates can see results but you cannot, please ensure you have the correct program selected using the program switcher in the top menu.

If you believe your results should be available, please contact your course teacher or the Registrar's office."

### Example 3: Escalation Needed
**User:** "I paid my fees a week ago but my balance still shows unpaid"  
**Bot:** "I'm sorry to hear your payment hasn't reflected. Since this has been over 48 hours, this needs to be handled by our Finance team.

Please contact the Bursary/Finance Office with:
- Your student ID
- Payment receipt (reference number, date, amount)
- The specific fee that should have been updated

Finance Office: [Contact Details]

Is there anything else I can help you with?"

---

## Document Metadata

**System:** PAX Academic Management System  
**Portal:** Student Portal  
**Documentation Scope:** First-Level AI Support  
**Modules Covered:** 14  
**Last Code Review:** December 2025  
**Controllers Analyzed:** 25+

---

*End of AI Chatbot Training Dataset*
