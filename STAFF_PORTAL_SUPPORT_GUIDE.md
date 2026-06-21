# PAX Higher Institute - Staff Portal Support Guide
## AI Chatbot Training Documentation v1.0

**Portal URL:** http://localhost/paxhitestbed/admin  
**Last Updated:** December 17, 2025

---

# TABLE OF CONTENTS

1. [Getting Started](#1-getting-started)
2. [Dashboard Overview](#2-dashboard-overview)
3. [Student Management](#3-student-management)
4. [Academic Management](#4-academic-management)
5. [Examination System](#5-examination-system)
6. [Attendance Management](#6-attendance-management)
7. [Fees & Financial Management](#7-fees--financial-management)
8. [Human Resources](#8-human-resources)
9. [Library System](#9-library-system)
10. [Communication Tools](#10-communication-tools)
11. [Reports & Analytics](#11-reports--analytics)
12. [System Settings](#12-system-settings)
13. [Security Features](#13-security-features)
14. [Common Error Messages & Solutions](#14-common-error-messages--solutions)
15. [Frequently Asked Questions (FAQ)](#15-frequently-asked-questions-faq)
16. [Troubleshooting Scenarios](#16-troubleshooting-scenarios)
17. [Escalation Guidelines](#17-escalation-guidelines)

---

# 1. GETTING STARTED

## 1.1 Login Process

### How to Login
1. Navigate to: `http://localhost/paxhitestbed/admin`
2. Enter your **Email Address**
3. Enter your **Password**
4. Click **Login**

### First-Time Login
- Default credentials are provided by the System Administrator
- You will be prompted to change your password on first login
- Enable Two-Factor Authentication (2FA) for enhanced security

### Two-Factor Authentication (2FA)
If 2FA is enabled:
1. After entering credentials, you'll see a verification screen
2. Open your authenticator app (Google Authenticator, Authy, etc.)
3. Enter the 6-digit code displayed
4. Click **Verify**

## 1.2 Common Login Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| "Invalid credentials" | Wrong email or password | Reset password via "Forgot Password" link |
| "Account is blocked" | Too many failed attempts or admin action | Contact System Administrator |
| "Session expired" | Inactive for too long | Login again; session timeout is 120 minutes |
| 2FA code not working | Time sync issue | Ensure device time is synchronized |
| Blank page after login | Cache issue | Clear browser cache and cookies |

## 1.3 Password Recovery

1. Click **"Forgot Password"** on login page
2. Enter your registered email address
3. Check your email for reset link
4. Click the link and set a new password
5. Password requirements:
   - Minimum 8 characters
   - At least one uppercase letter
   - At least one number
   - At least one special character

## 1.4 Navigation Overview

### Main Menu Structure
The sidebar contains the following main sections:
- **Dashboard** - Overview and statistics
- **Admission** - Student applications and registration
- **Student** - Student records management
- **Academic** - Programs, courses, schedules
- **Examination** - Exams, marking, results
- **Attendance** - Student and staff attendance
- **Fees Collection** - Financial transactions
- **HR** - Staff management and payroll
- **Library** - Book circulation
- **Communication** - Notices, emails, SMS
- **Reports** - Various system reports
- **Settings** - System configuration

### Quick Tips
- Click the **hamburger menu (☰)** to collapse/expand sidebar
- Use **breadcrumbs** at the top for navigation history
- **Search bar** (if available) for quick access to features
- Click your **profile picture** for account settings and logout

---

# 2. DASHBOARD OVERVIEW

## 2.1 Dashboard Widgets

The dashboard displays key metrics:

| Widget | Description |
|--------|-------------|
| Total Students | Active enrolled students count |
| Total Staff | Active employees count |
| Total Programs | Available study programs |
| Fees Collection | Today's/Monthly collection summary |
| Recent Applications | Latest admission applications |
| Upcoming Events | Scheduled events and deadlines |
| Quick Actions | Shortcuts to common tasks |

## 2.2 Understanding Dashboard Statistics

### Student Statistics
- **Active Students**: Currently enrolled students
- **New Admissions**: Students admitted this session
- **Graduated**: Students who completed their programs
- **On Leave**: Students on approved leave

### Financial Overview
- **Today's Collection**: Fees received today
- **Monthly Collection**: Current month total
- **Pending Fees**: Outstanding student balances
- **Overdue Fees**: Fees past due date

---

# 3. STUDENT MANAGEMENT

## 3.1 Student Registration

### Creating a New Student

**Navigation:** Admission → Student → Add New

1. Fill in required fields:
   - First Name, Last Name
   - Date of Birth
   - Gender
   - Email, Phone
   - Address details
   
2. Academic Information:
   - Select Faculty
   - Select Program
   - Select Session
   - Select Semester
   - Select Section
   - Select Batch

3. Upload Documents:
   - Photo (300x300 pixels recommended)
   - Signature
   - Required certificates

4. Click **Save**

### Auto-Generated Fields
- **Student ID**: Automatically generated based on program code
- **Matricule**: Generated upon enrollment confirmation

## 3.2 Student Search & Filters

### Search Options
- By Student ID / Matricule
- By Name
- By Program
- By Session
- By Semester
- By Status

### Export Options
- Export to Excel
- Export to PDF
- Print list

## 3.3 Editing Student Information

**Navigation:** Student → Student List → Click Edit (pencil icon)

### Editable Fields
- Personal information
- Contact details
- Guardian information
- Academic details (with restrictions)
- Photo and documents

### Restricted Changes
- Student ID cannot be changed after creation
- Program changes require transfer process
- Completed semester grades are locked

## 3.4 Student ID Cards

### Printing ID Cards

**Navigation:** Admission → ID Card

1. Use filters to find students:
   - Select Faculty
   - Select Program
   - Select Session
   - Select Semester
   
2. Select students using checkboxes
3. Click **Print Selected**
4. ID cards open in new window for printing

### ID Card Photo Update

1. Click on the student's photo thumbnail
2. In the modal, click **Choose File**
3. Select a new photo (JPG, PNG, max 3MB)
4. Click **Save**
5. Photo updates immediately

### Common ID Card Issues

| Issue | Solution |
|-------|----------|
| Photo not showing | Ensure photo is uploaded in student profile |
| Validity period wrong | Check program's validity_years setting |
| Print layout broken | Use Chrome browser, check print settings |
| Barcode not scanning | Ensure high print quality |

## 3.5 Student Status Management

### Status Types
- **Active**: Currently enrolled
- **Inactive**: Temporarily suspended
- **Graduated**: Completed program
- **Transferred Out**: Moved to another institution
- **Withdrawn**: Left the program
- **Dismissed**: Academic dismissal

### Changing Student Status

**Navigation:** Student → Status Types

1. Open student profile
2. Click **Edit Status**
3. Select new status
4. Add reason/notes
5. Click **Update**

## 3.6 Student Enrollment

### Single Enrollment

**Navigation:** Student → Single Enroll

1. Search for student
2. Select Program, Session, Semester, Section
3. Select subjects/courses
4. Verify credit limits
5. Confirm enrollment

### Group Enrollment

**Navigation:** Student → Group Enroll

1. Select source criteria (current semester students)
2. Select target semester
3. Preview students to be enrolled
4. Click **Enroll All**

### Subject Add/Drop

**Navigation:** Student → Subject Add/Drop

Students can add or drop courses within the add/drop period:

1. Search for student
2. View current enrollment
3. **Add**: Select additional subjects
4. **Drop**: Click drop icon (with confirmation)
5. System checks:
   - Credit limits
   - Prerequisites
   - Schedule conflicts

---

# 4. ACADEMIC MANAGEMENT

## 4.1 Program Management

### Creating a Program

**Navigation:** Academic → Programs → Add New

Required fields:
- Program Title
- Short Code (e.g., BSCS, MBA)
- Faculty
- Academic Level (Undergraduate, Masters, Doctorate)
- Duration (years)
- Total Credits
- Validity Years (for ID cards)

### Program Settings
- Semesters per year
- Maximum credits per semester
- Minimum credits per semester
- Grading scale

## 4.2 Subject/Course Management

### Adding a Course

**Navigation:** Academic → Subjects → Add New

1. Enter course details:
   - Title
   - Course Code
   - Credits
   - Contact Hours
   - Description

2. Assign to programs:
   - Select Program
   - Select Semester (when offered)
   - Mark as Core/Elective

## 4.3 Class Routine/Timetable

### Creating Class Schedule

**Navigation:** Academic → Class Routine

1. Select Program, Semester, Section
2. Click **Add Routine**
3. Select:
   - Day
   - Time slot
   - Subject
   - Teacher
   - Room
4. Click **Save**

### Viewing Schedules
- **By Section**: See all classes for a section
- **By Teacher**: See teacher's weekly schedule
- **By Room**: See room occupancy

### Conflict Detection
The system automatically detects:
- Teacher double-booking
- Room double-booking
- Student schedule conflicts

## 4.4 Academic Sessions

### Creating a New Session

**Navigation:** Academic → Sessions → Add New

1. Enter Session Title (e.g., "2024-2025")
2. Set Start and End dates
3. Assign to programs
4. Set as current session if applicable

---

# 5. EXAMINATION SYSTEM

## 5.1 Exam Configuration

### Setting Up Exams

**Navigation:** Examination → Exam → Add New

1. Enter Exam Name
2. Select Exam Type
3. Set Date Range
4. Assign to Programs/Semesters

### Exam Types
- First Semester Exams
- Second Semester Exams
- Resit Examinations
- Supplementary Exams

## 5.2 Exam Routine/Schedule

### Creating Exam Schedule

**Navigation:** Examination → Exam Routine

1. Select Exam
2. Add entries:
   - Date and Time
   - Subject
   - Room
   - Duration
3. Publish when complete

## 5.3 Admit Cards

### Generating Admit Cards

**Navigation:** Examination → Admit Card

1. Select Exam
2. Select Program/Semester
3. Preview students
4. Click **Generate**

### Admit Card Requirements
Students must meet:
- Fee payment status (configurable)
- Minimum attendance (configurable)
- No disciplinary holds

## 5.4 Exam Marking (Subject Marking)

### Entering Grades

**Navigation:** Examination → Subject Marking

1. Select Subject
2. Select Semester/Section
3. Enter marks for each component:
   - Continuous Assessment (CA)
   - Mid-term
   - Final Exam
4. System auto-calculates:
   - Total Score
   - Grade
   - Grade Points

### Marking Workflow

```
Draft → Submit for Review → Approved → Published
```

| Status | Description | Actions Available |
|--------|-------------|-------------------|
| Draft | Initial entry | Edit, Delete |
| Under Review | Submitted for HOD review | Approve, Reject |
| Approved | HOD approved | Publish |
| Published | Visible to students | Unpublish (with permission) |

### Grade Scale (Example)

| Score Range | Grade | Points | Remarks |
|-------------|-------|--------|---------|
| 80-100 | A | 4.0 | Excellent |
| 70-79 | B+ | 3.5 | Very Good |
| 60-69 | B | 3.0 | Good |
| 55-59 | C+ | 2.5 | Above Average |
| 50-54 | C | 2.0 | Average |
| 45-49 | D | 1.5 | Below Average |
| 40-44 | E | 1.0 | Pass |
| 0-39 | F | 0.0 | Fail |

## 5.5 Resit Examinations

### Resit Request Processing

**Navigation:** Examination → Resit Requests

1. View pending resit requests
2. Verify:
   - Original grade (must be failed)
   - Payment status
   - Eligibility
3. Approve or Reject with reason

### Resit Settings

**Navigation:** Examination → Resit Settings

Configure:
- Maximum resit attempts
- Resit fee amount
- Eligible grade range
- Registration deadline

## 5.6 Result Publication

### Publishing Results

**Navigation:** Examination → Results

1. Select Exam/Semester
2. Verify all subjects are marked
3. Generate result summary
4. Click **Publish**

### Result Reports
- Individual transcripts
- Class-wise results
- Program statistics
- Pass/Fail analysis

---

# 6. ATTENDANCE MANAGEMENT

## 6.1 Student Attendance

### Marking Attendance

**Navigation:** Attendance → Student Attendance

1. Select Subject
2. Select Date
3. Select Class/Session
4. Mark students as:
   - Present (P)
   - Absent (A)
   - Late (L)
   - Excused (E)
5. Click **Save**

### Bulk Attendance
- Import from Excel
- Mark all present/absent
- Copy from previous session

## 6.2 Class Sessions & Logbook

### Starting a Class Session

**Navigation:** Attendance → Class Sessions

1. Click **Start Session**
2. Select Subject
3. Session timer begins
4. Students can check in via QR code
5. Take notes during class
6. Click **End Session**

### Class Kiosk Mode

For classroom display:
1. Go to Attendance → Class Kiosk
2. Select the ongoing session
3. Display QR code on projector
4. Students scan to mark attendance

### Logbook Completion

After class:
1. Review attendance
2. Add session notes
3. Record topics covered
4. Submit logbook entry

## 6.3 Attendance Reports

**Navigation:** Reports → Attendance Reports

Available reports:
- Student attendance percentage
- Subject-wise attendance
- Date-range analysis
- Low attendance alerts

### Attendance Thresholds
- **Green**: 75%+ attendance
- **Yellow**: 60-74% attendance
- **Red**: Below 60% attendance

---

# 7. FEES & FINANCIAL MANAGEMENT

## 7.1 Fee Structure Setup

### Creating Fee Categories

**Navigation:** Fees → Fee Categories

Examples:
- Tuition Fee
- Registration Fee
- Laboratory Fee
- Library Fee
- Sports Fee
- Examination Fee

### Fee Masters

**Navigation:** Fees → Fee Master

1. Create fee structure:
   - Select Program
   - Select Semester
   - Add fee components
   - Set amounts
2. Set due dates
3. Configure late fees

## 7.2 Assigning Fees to Students

### Quick Assign

**Navigation:** Fees → Quick Assign

1. Select criteria:
   - Program
   - Session
   - Semester
2. Select Fee Master
3. Preview affected students
4. Click **Assign**

### Individual Assignment

**Navigation:** Fees → Student Fees

1. Search for student
2. Click **Add Fee**
3. Select fee type
4. Enter amount
5. Set due date
6. Save

## 7.3 Receiving Payments

### Quick Received

**Navigation:** Fees → Quick Received

1. Search student by ID or name
2. View outstanding fees
3. Select fees to pay
4. Enter payment details:
   - Amount received
   - Payment method (Cash, Card, Mobile Money, Bank Transfer)
   - Reference number
5. Print receipt

### Payment Methods
- **Cash**: Direct cash payment
- **Bank Transfer**: Transfer with reference
- **Mobile Money**: MTN, Orange, etc.
- **Card**: Debit/Credit cards
- **Online**: Payment gateway transactions

## 7.4 Payment Plans (Installments)

### Creating Payment Plan

**Navigation:** Fees → Payment Plans

1. Select student
2. Select unpaid fees
3. Define installments:
   - Number of payments
   - Amount per installment
   - Due dates
4. Student receives plan notification

### Managing Payment Plans
- Track installment payments
- Send reminders for upcoming dues
- Apply late fees for missed installments

## 7.5 Fee Reports

**Navigation:** Reports → Fee Reports

Available reports:
- **Collection Summary**: Daily/Monthly/Yearly
- **Outstanding Fees**: All unpaid balances
- **Payment History**: Transaction log
- **Fee Defaulters**: Students with overdue fees
- **Category-wise Collection**: By fee type

## 7.6 Student Credits

### Understanding Credits
Credits occur when:
- Overpayment made
- Fee waiver applied after payment
- Scholarship awarded after payment

### Managing Credits

**Navigation:** Fees → Student Credits

1. View student's credit balance
2. Options:
   - **Apply to Fees**: Use credit for future fees
   - **Request Refund**: Process refund request
   - **Transfer**: Move to another student (rare cases)

### Refund Workflow

```
Request → Pending Approval → Approved → Processed
```

## 7.7 Discounts & Scholarships

### Creating Discounts

**Navigation:** Fees → Fee Discounts

Types:
- Percentage-based (e.g., 25% off tuition)
- Fixed amount (e.g., 50,000 XAF off)
- Full waiver

### Applying Discounts

1. Open student's fee record
2. Click **Apply Discount**
3. Select discount type
4. Enter amount/percentage
5. Add reason
6. Save

---

# 8. HUMAN RESOURCES

## 8.1 Staff Management

### Adding New Staff

**Navigation:** HR → Staff → Add New

Required information:
- Personal details
- Contact information
- Employment details:
  - Designation
  - Department
  - Join Date
  - Employment Type
- Qualifications
- Bank details (for payroll)
- Photo and documents

### Staff ID Generation
Staff IDs are auto-generated based on:
- Department code
- Sequential number
- Year of joining

## 8.2 Staff Assignment

### Assigning Teachers to Courses

**Navigation:** HR → Staff Assignment

1. Select Staff member
2. Assign to:
   - Faculty
   - Program
   - Subject(s)
   - Semester
3. Set as:
   - Course Instructor
   - HOD
   - Program Coordinator

### Workload View
- View teacher's total assigned credits
- Check for overload
- Balance assignments

## 8.3 Payroll Management

### Processing Payroll

**Navigation:** HR → Payroll

1. Select Month/Year
2. Select Department or All Staff
3. System calculates:
   - Basic Salary
   - Allowances
   - Deductions
   - Taxes
   - Net Pay
4. Review and Approve
5. Generate payslips

### Salary Components

| Component | Type | Description |
|-----------|------|-------------|
| Basic Salary | Earning | Base pay |
| Housing Allowance | Earning | Housing support |
| Transport Allowance | Earning | Transport support |
| Tax | Deduction | Income tax |
| Pension | Deduction | Retirement contribution |
| Loan Deduction | Deduction | Staff loan repayment |

### Payslip Generation
- Individual payslips
- Bulk generation
- Email delivery option
- Print option

## 8.4 Leave Management

### Leave Types
- Annual Leave
- Sick Leave
- Maternity/Paternity Leave
- Study Leave
- Compassionate Leave
- Unpaid Leave

### Processing Leave Requests

**Navigation:** HR → Leave Management

1. View pending requests
2. Check:
   - Leave balance
   - Workload coverage
   - Supporting documents (if required)
3. Approve or Reject
4. Add notes
5. Employee is notified

## 8.5 Staff Attendance

### Daily Attendance

**Navigation:** HR → Staff Attendance → Daily

Methods:
- Manual entry
- Biometric import
- QR scanner

### Attendance Reports
- Daily attendance sheet
- Monthly summary
- Late arrival tracking
- Absence reports

---

# 9. LIBRARY SYSTEM

## 9.1 Book Management

### Adding Books

**Navigation:** Library → Books → Add New

Enter:
- Title
- Author(s)
- ISBN
- Publisher
- Edition
- Category
- Number of copies
- Location/Shelf

### Book Categories
- Textbooks
- Reference
- Journals
- Periodicals
- Fiction
- Non-Fiction

### Book Import
1. Download template
2. Fill book details
3. Upload Excel file
4. Review and confirm

## 9.2 Library Members

### Member Types
- Students (auto-enrolled)
- Staff (auto-enrolled)
- External members (manual registration)

### Library Card Settings

**Navigation:** Library → Card Settings

Configure:
- Card validity period
- Maximum books allowed
- Loan duration
- Fine per day

## 9.3 Book Issue/Return

### Issuing Books

**Navigation:** Library → Issue Book

1. Scan or enter member ID
2. Verify member status
3. Scan or enter book barcode
4. System checks:
   - Book availability
   - Member's current loans
   - Outstanding fines
5. Confirm issue
6. Print receipt

### Returning Books

**Navigation:** Library → Return Book

1. Scan book barcode
2. System calculates:
   - Return status (on-time/late)
   - Fine amount (if late)
3. Collect fine if applicable
4. Confirm return
5. Book becomes available

### Renewals
- Members can renew up to 2 times
- Cannot renew if reserved by another member
- Cannot renew if overdue

## 9.4 E-Library

### Digital Books

**Navigation:** E-Library → Books

Features:
- Upload digital content (PDF, EPUB)
- Internet Archive integration
- Search and filter
- Download tracking
- AI-powered recommendations

### Internet Archive Integration
1. Search Internet Archive catalog
2. Preview book details
3. Add to E-Library
4. Students can access via embedded reader

---

# 10. COMMUNICATION TOOLS

## 10.1 Email Notifications

### Sending Bulk Email

**Navigation:** Communication → Email Notify

1. Compose email:
   - Subject
   - Body (with rich text editor)
   - Attachments
2. Select recipients:
   - All Students
   - By Program
   - By Semester
   - Individual selection
3. Preview
4. Send

### Email Templates
Pre-configured templates for:
- Fee reminders
- Exam notifications
- Result publication
- General announcements

## 10.2 SMS Notifications

### Sending SMS

**Navigation:** Communication → SMS Notify

1. Compose message (160 characters)
2. Select recipients
3. Preview cost
4. Send

### SMS Gateway Configuration
Supported gateways:
- Twilio
- Vonage
- TextLocal
- Africa's Talking
- SMS Country

## 10.3 Notices & Announcements

### Creating Notices

**Navigation:** Communication → Notices

1. Enter notice details:
   - Title
   - Content
   - Category
   - Publish Date
   - Expiry Date
2. Target audience:
   - All
   - Students only
   - Staff only
   - Specific programs
3. Publish

### Notice Categories
- Academic
- Administrative
- Events
- Examinations
- Fees
- General

## 10.4 Events & Calendar

### Creating Events

**Navigation:** Communication → Events

1. Enter event details:
   - Title
   - Description
   - Date and Time
   - Venue
   - Event Type
2. Add to calendar
3. Send notifications (optional)

### Calendar View
- Monthly/Weekly/Daily views
- Color-coded by event type
- Export to iCal

---

# 11. REPORTS & ANALYTICS

## 11.1 Available Reports

### Student Reports
- **Student List**: Complete student directory
- **Student Progress**: Academic performance
- **Course Students**: Students per course
- **Attendance Report**: Attendance statistics

### Financial Reports
- **Fees Collection**: Payment receipts
- **Outstanding Fees**: Unpaid balances
- **Income/Expense**: Financial summary
- **Budget Reports**: Budget vs Actual

### Academic Reports
- **Result Analysis**: Pass/Fail rates
- **Grade Distribution**: Grade statistics
- **Semester Results**: Consolidated results

### HR Reports
- **Staff List**: Employee directory
- **Salary Reports**: Payroll summary
- **Leave Reports**: Leave statistics
- **Attendance Reports**: Staff attendance

## 11.2 Generating Reports

### Standard Process
1. Navigate to specific report
2. Set filters:
   - Date range
   - Program
   - Department
   - etc.
3. Click **Generate**
4. View on screen
5. Export options:
   - PDF
   - Excel
   - Print

### Scheduled Reports
Configure automatic report generation:
- Daily summaries
- Weekly digests
- Monthly compilations

---

# 12. SYSTEM SETTINGS

## 12.1 General Settings

**Navigation:** Settings → General Settings

Configure:
- Institution Name
- Address
- Contact Information
- Logo
- Favicon
- Currency
- Date Format
- Time Zone

## 12.2 Academic Settings

### Session Settings
- Current session
- Session dates
- Academic calendar

### Grading Settings
- Grade scales
- GPA calculation method
- Pass marks

## 12.3 Fee Settings

**Navigation:** Settings → Payment Settings

Configure:
- Payment gateways
- Receipt format
- Late fee rules
- Partial payment options

## 12.4 Email/SMS Settings

**Navigation:** Settings → Mail/SMS Settings

Configure:
- SMTP settings
- SMS gateway credentials
- Default sender information

## 12.5 Role & Permission Management

**Navigation:** Settings → Roles

### Creating Roles
1. Click **Add Role**
2. Enter Role Name
3. Select Permissions
4. Save

### Assigning Roles
1. Go to User Management
2. Select User
3. Assign Role
4. Save

### Permission Categories
- Student Management
- Academic Management
- Examination
- Fees
- HR
- Library
- Reports
- Settings

---

# 13. SECURITY FEATURES

## 13.1 Two-Factor Authentication

### Enabling 2FA

**Navigation:** Profile → Security

1. Click **Enable 2FA**
2. Scan QR code with authenticator app
3. Enter verification code
4. Save backup codes

### Disabling 2FA
Requires admin approval for security

## 13.2 Security Dashboard

**Navigation:** Security → Dashboard

View:
- Login attempts
- Failed logins
- Active sessions
- Security alerts

## 13.3 User Management

**Navigation:** Security → Users

Actions:
- View user list
- Block/Unblock users
- Force password reset
- View login history

## 13.4 IP Management

### IP Blocking

**Navigation:** Security → Blocked IPs

Block suspicious IPs:
1. Enter IP address
2. Add reason
3. Set duration (temporary/permanent)
4. Block

### IP Whitelist

**Navigation:** Security → IP Whitelist

Add trusted IPs for:
- Admin access
- API access
- Bypass rate limiting

## 13.5 Audit Trail

**Navigation:** Audit Trail

View:
- All system activities
- User actions
- Data changes
- Login/Logout events

Export audit logs for compliance.

---

# 14. COMMON ERROR MESSAGES & SOLUTIONS

## 14.1 Authentication Errors

| Error Message | Cause | Solution |
|---------------|-------|----------|
| "Invalid credentials" | Wrong email/password | Verify credentials; use password reset |
| "Account locked" | Multiple failed attempts | Wait 30 minutes or contact admin |
| "Session expired" | Timeout | Login again |
| "Unauthorized access" | Missing permissions | Contact admin for role update |
| "2FA required" | 2FA enabled but not completed | Enter authenticator code |

## 14.2 Data Entry Errors

| Error Message | Cause | Solution |
|---------------|-------|----------|
| "Student ID already exists" | Duplicate registration | Search for existing record |
| "Email already taken" | Duplicate email | Use different email |
| "Required field missing" | Form validation | Fill all required fields |
| "Invalid date format" | Date format mismatch | Use DD/MM/YYYY format |
| "File too large" | Upload size exceeded | Compress file or use smaller file |
| "Invalid file type" | Wrong file format | Check accepted formats |

## 14.3 Financial Errors

| Error Message | Cause | Solution |
|---------------|-------|----------|
| "Insufficient balance" | Credit balance too low | Check student account |
| "Fee already paid" | Duplicate payment attempt | Verify payment status |
| "Payment failed" | Gateway issue | Retry or use alternative method |
| "Receipt not found" | Missing transaction | Check transaction history |

## 14.4 Academic Errors

| Error Message | Cause | Solution |
|---------------|-------|----------|
| "Maximum credits exceeded" | Too many courses | Drop a course |
| "Prerequisite not met" | Missing prerequisite course | Complete prerequisite first |
| "Schedule conflict" | Overlapping classes | Choose different section/time |
| "Enrollment closed" | Past deadline | Contact registrar |
| "Results already published" | Editing locked results | Requires special permission |

## 14.5 System Errors

| Error Message | Cause | Solution |
|---------------|-------|----------|
| "500 Internal Server Error" | Server issue | Report to IT; try again later |
| "404 Page Not Found" | Invalid URL | Check URL; clear cache |
| "Connection timeout" | Network issue | Check internet connection |
| "Database error" | Database issue | Report to IT support |

---

# 15. FREQUENTLY ASKED QUESTIONS (FAQ)

## General Questions

### Q: How do I change my password?
**A:** Go to Profile → Change Password. Enter your current password, then new password twice. Click Update.

### Q: How do I update my profile picture?
**A:** Go to Profile → Edit Profile. Click on current photo, upload new image (300x300 pixels recommended), and save.

### Q: Why can't I see certain menu items?
**A:** Menu visibility depends on your assigned role and permissions. Contact your administrator if you need additional access.

### Q: How do I logout from all devices?
**A:** Go to Profile → Security → Active Sessions. Click "Logout All Other Sessions."

## Student Management

### Q: How do I find a specific student?
**A:** Use the search bar on Student List page. You can search by:
- Student ID
- Matricule
- Name
- Email
- Phone number

### Q: How do I transfer a student to another program?
**A:** Go to Admission → Student Transfer. Select student, choose new program, enter reason, and submit. Dean's approval may be required.

### Q: How do I print multiple ID cards at once?
**A:** Go to Admission → ID Card. Use filters to find students, check the boxes, and click "Print Selected."

### Q: A student's photo is not showing on their ID card. What should I do?
**A:** 
1. Verify photo is uploaded in student profile
2. Check file format (JPG, PNG only)
3. Ensure file is under 3MB
4. Use the inline photo update feature on ID Card page

## Academic Questions

### Q: How do I add a new course to a program?
**A:** Go to Academic → Subjects → Add New. Fill course details, then go to program settings to assign the subject to specific semesters.

### Q: How do I change class schedule/routine?
**A:** Go to Academic → Class Routine. Select the entry, click Edit, make changes, and Save. Check for conflicts.

### Q: A teacher left mid-semester. How do I reassign courses?
**A:** Go to HR → Staff Assignment. Remove old assignment, create new assignment for replacement teacher.

## Examination Questions

### Q: How do I enter marks for a subject?
**A:** Go to Examination → Subject Marking. Select subject, semester, section. Enter marks in the grade sheet, then Save.

### Q: Results are wrong. How do I correct them?
**A:** 
- If Draft/Review status: Edit directly
- If Published: Request unpublish (requires approval), make changes, republish

### Q: A student missed their exam. How do I handle this?
**A:** Mark as absent in exam attendance. Student may need to apply for resit examination.

### Q: How do I generate transcripts?
**A:** Go to Examination → Marksheets. Select student or batch, choose semester or cumulative, click Generate.

## Fee Questions

### Q: How do I record a payment?
**A:** Go to Fees → Quick Received. Search student, select fees to pay, enter amount and payment method, confirm.

### Q: How do I apply a scholarship/discount?
**A:** Go to Fees → Student Fees. Find student, click Add Discount, select type, enter amount, save.

### Q: A student overpaid. What do I do?
**A:** Overpayment creates a credit balance. Go to Fees → Student Credits to view and manage (apply to future fees or process refund).

### Q: How do I set up a payment plan?
**A:** Go to Fees → Payment Plans. Select student, choose unpaid fees, define installment schedule, save.

## Technical Questions

### Q: The system is slow. What can I do?
**A:** 
1. Clear browser cache
2. Try a different browser (Chrome recommended)
3. Check internet connection
4. Report to IT if issue persists

### Q: I can't upload files. What's wrong?
**A:**
1. Check file size (max varies by type)
2. Check file format
3. Try different browser
4. Clear browser cache

### Q: Reports are not loading. What should I do?
**A:**
1. Reduce date range
2. Add more specific filters
3. Try during off-peak hours
4. Report to IT support

---

# 16. TROUBLESHOOTING SCENARIOS

## Scenario 1: Student Cannot Find Their Results

**Symptoms:** Student reports results not visible in their portal.

**Troubleshooting Steps:**
1. Verify student's enrollment status
2. Check if results are published (Examination → Results)
3. Verify student is in correct semester/section
4. Check if there are any holds on student account
5. Verify subject marking status (must be "Published")

**Resolution:**
- If not published: Publish results
- If wrong enrollment: Correct enrollment
- If hold exists: Resolve hold issue

---

## Scenario 2: Payment Not Reflecting

**Symptoms:** Student paid but fee still shows as unpaid.

**Troubleshooting Steps:**
1. Check payment verification queue
2. Verify payment receipt/reference number
3. Check if payment was for correct fee
4. Verify payment amount matches

**Resolution:**
- If pending verification: Approve payment
- If wrong fee: Apply payment to correct fee
- If partial: Record as partial payment

---

## Scenario 3: Teacher Cannot Enter Grades

**Symptoms:** Teacher unable to access marking page or save grades.

**Troubleshooting Steps:**
1. Check teacher's staff assignment
2. Verify subject-teacher mapping
3. Check marking deadline
4. Verify user permissions

**Resolution:**
- If no assignment: Create staff assignment
- If deadline passed: Extend deadline (admin)
- If permission issue: Update role

---

## Scenario 4: ID Card Print Quality Issues

**Symptoms:** ID cards printing blurry or with missing elements.

**Troubleshooting Steps:**
1. Check original photo quality
2. Verify browser print settings
3. Check printer settings (300 DPI recommended)
4. Preview before printing

**Resolution:**
- Upload higher quality photo
- Adjust print settings
- Use Chrome browser
- Select correct paper size

---

## Scenario 5: Cannot Enroll Student in Course

**Symptoms:** Error when trying to enroll student.

**Troubleshooting Steps:**
1. Check enrollment deadline
2. Verify credit limits
3. Check prerequisites
4. Check schedule conflicts
5. Verify fee payment status

**Resolution:**
- Extend deadline if needed
- Adjust credit limits
- Complete prerequisites first
- Resolve schedule conflicts

---

## Scenario 6: Email Notifications Not Sending

**Symptoms:** Emails not being received by recipients.

**Troubleshooting Steps:**
1. Check SMTP settings
2. Verify sender email
3. Check spam/junk folders
4. Review email queue
5. Test with single recipient

**Resolution:**
- Update SMTP credentials
- Use verified sender domain
- Check mail server logs
- Contact IT for server issues

---

## Scenario 7: Attendance Data Missing

**Symptoms:** Attendance records not showing for certain dates.

**Troubleshooting Steps:**
1. Verify attendance was marked
2. Check correct subject/section selected
3. Verify date range
4. Check user who marked attendance

**Resolution:**
- Enter missing attendance
- Correct subject/section selection
- Expand date range
- Verify with class teacher

---

## Scenario 8: Budget Allocation Issues

**Symptoms:** Cannot allocate budget or amounts not matching.

**Troubleshooting Steps:**
1. Check fiscal year settings
2. Verify budget status (must be Active)
3. Check available balance
4. Verify expense categories

**Resolution:**
- Set correct fiscal year
- Activate budget
- Request budget increase
- Add missing expense categories

---

## Scenario 9: Login Issues After Password Reset

**Symptoms:** User cannot login after resetting password.

**Troubleshooting Steps:**
1. Verify reset email was received
2. Check reset link expiry (24 hours)
3. Verify password requirements met
4. Check for account blocks

**Resolution:**
- Resend reset email
- Generate new reset link
- Use compliant password
- Unblock account if needed

---

## Scenario 10: Duplicate Student Records

**Symptoms:** Same student appears multiple times.

**Troubleshooting Steps:**
1. Compare student IDs
2. Check admission dates
3. Verify enrollment status
4. Review all records for accuracy

**Resolution:**
- Merge records (admin only)
- Archive duplicate
- Correct enrollment
- Document in audit trail

---

# 17. ESCALATION GUIDELINES

## When to Escalate

### Level 1 (AI/Chatbot) Handles:
- General navigation questions
- FAQ answers
- Basic troubleshooting
- Password reset guidance
- Status inquiries
- Standard procedures explanation

### Escalate to Level 2 (Human Support) When:
- User request requires data modification
- Security-related issues
- Financial discrepancies
- System errors/bugs
- Complex academic issues
- Policy exceptions needed
- User is frustrated or confused after basic help
- Issue involves sensitive personal data

### Escalate to Level 3 (IT/Admin) When:
- System-wide outages
- Database issues
- Server problems
- Security breaches
- Integration failures
- Major bugs affecting multiple users

## Escalation Process

### For Users (via Chatbot):
1. Chatbot attempts resolution
2. If unresolved, chatbot offers human support option
3. User provides:
   - Name and User ID
   - Brief description of issue
   - Steps already tried
4. Ticket created automatically
5. User receives ticket number
6. Support team notified

### Escalation Information to Collect:
```
- User Name:
- User ID/Email:
- Role:
- Issue Category:
- Issue Description:
- Error Messages (if any):
- Steps to Reproduce:
- Screenshots (if available):
- Urgency Level: [Low/Medium/High/Critical]
- Preferred Contact Method:
```

### Priority Levels

| Priority | Description | Response Time | Examples |
|----------|-------------|---------------|----------|
| Critical | System down, data loss risk | 1 hour | Server crash, security breach |
| High | Major feature unusable | 4 hours | Payments not working, cannot enter grades |
| Medium | Feature partially working | 1 business day | Report not generating, minor bug |
| Low | Question or minor issue | 2-3 business days | How-to questions, feature requests |

### Support Contact Information

**IT Support:**
- Email: it-support@paxhi.org
- Phone: [Internal Extension]
- Hours: Monday-Friday, 8:00 AM - 5:00 PM

**Academic Support:**
- Email: registrar@paxhi.org
- Phone: [Internal Extension]

**Financial Support:**
- Email: finance@paxhi.org
- Phone: [Internal Extension]

---

# APPENDIX A: KEYBOARD SHORTCUTS

| Shortcut | Action |
|----------|--------|
| Ctrl + S | Save form |
| Ctrl + P | Print |
| Ctrl + F | Find on page |
| Esc | Close modal/Cancel |
| Enter | Submit form |
| Tab | Next field |
| Shift + Tab | Previous field |

---

# APPENDIX B: GLOSSARY

| Term | Definition |
|------|------------|
| **Matricule** | Student registration number |
| **CA** | Continuous Assessment |
| **GPA** | Grade Point Average |
| **HOD** | Head of Department |
| **Resit** | Re-examination for failed courses |
| **Semester** | Academic term (usually 4-6 months) |
| **Session** | Academic year |
| **Credit** | Course weight unit |
| **Transcript** | Official academic record |
| **Batch** | Group of students admitted together |
| **Faculty** | Academic division (e.g., Science, Arts) |
| **2FA** | Two-Factor Authentication |
| **OHADA** | Organization for Harmonization of Business Law in Africa |

---

# APPENDIX C: SYSTEM REQUIREMENTS

### Recommended Browser
- Google Chrome (latest version)
- Mozilla Firefox (latest version)
- Microsoft Edge (latest version)

### Minimum Requirements
- Screen resolution: 1366x768
- JavaScript enabled
- Cookies enabled
- Stable internet connection

### Not Supported
- Internet Explorer
- Opera Mini
- Mobile browsers (limited support)

---

# APPENDIX D: DATA BACKUP & RECOVERY

### Automatic Backups
- Daily database backups
- Weekly full system backups
- 30-day retention period

### Requesting Data Recovery
1. Submit request to IT Support
2. Provide:
   - Type of data needed
   - Approximate date of loss
   - Reason for recovery
3. IT will assess and recover if possible

---

# VERSION HISTORY

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | December 17, 2025 | Initial documentation release |

---

*This documentation is maintained by the PAX Higher Institute IT Department. For updates or corrections, contact it-support@paxhi.org*
