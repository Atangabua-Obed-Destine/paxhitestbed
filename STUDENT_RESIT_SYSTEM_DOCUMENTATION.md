# Student Resit Request System Documentation

## Overview
This document describes the implementation of the student resit request system that allows students to request resits for failed courses through their portal.

## Implementation Date
October 22, 2025

## Features Implemented

### 1. Student Portal - Resit Request Module
**Location:** `http://localhost/paxhitest/student/resit`

#### Main Features:
- **Search Interface:** Students can search for failed courses by Session and Semester
- **Failed Course Display:** Shows all courses with marks < 50% where all exam types are published
- **Request Submission:** One-click request submission with automatic fee assignment
- **Request History:** View all past resit requests and their status
- **Payment Status Tracking:** See payment status for each resit request

#### Key Components:

##### Controller
**File:** `app/Http/Controllers/Student/ResitController.php`

**Methods:**
1. `index()` - Main page showing search form and failed courses
2. `store()` - Process resit request submission
3. `history()` - Show student's resit request history
4. `getFailedCourses()` - Helper to identify failed courses
5. `isFullyPublished()` - Verify all exam types are published

##### Views
**Files:**
- `resources/views/student/resit/index.blade.php` - Main resit request page
- `resources/views/student/resit/history.blade.php` - Request history page

##### Routes
**File:** `routes/web.php`
```php
Route::get('resit', 'ResitController@index')->name('resit.index');
Route::post('resit/store', 'ResitController@store')->name('resit.store');
Route::get('resit/history', 'ResitController@history')->name('resit.history');
```

##### Sidebar Menu
**File:** `resources/views/student/layouts/inc/sidebar.blade.php`
- Added "Resits" menu item with icon (fas fa-redo-alt)
- Menu appears between Transcript and Profile sections

### 2. Fee Category Enhancement
**Location:** `http://localhost/paxhitest/admin/fees-category`

#### Database Changes:
**Migration:** `database/migrations/2025_10_22_042334_add_is_resit_to_fees_categories_table.php`
- Added `is_resit` boolean column (default: 0)

#### Model Changes:
**File:** `app/Models/FeesCategory.php`
- Added `is_resit` to fillable array

#### UI Changes:
**Files:**
- `resources/views/admin/fees-category/index.blade.php` - Create form with checkbox
- `resources/views/admin/fees-category/edit.blade.php` - Edit modal with checkbox
- Added "Type" column to display "Resit Fee" or "Regular Fee" badges

#### Controller Changes:
**File:** `app/Http/Controllers/Admin/FeesCategoryController.php`
- Updated `store()` method to save `is_resit` value
- Updated `update()` method to update `is_resit` value

### 3. Resit Fee Service Enhancement
**File:** `app/Services/Resit/ResitFeeService.php`

#### Key Improvements:
1. **Category Resolution:**
   - First checks for categories with `is_resit = 1`
   - Falls back to slug-based lookup for backward compatibility
   - Auto-creates category with `is_resit = 1` if needed

2. **Fee Amount Determination:**
   - New method `determineFeeAmount()` with intelligent fee lookup
   - Checks FeesMaster for program/session/semester specific amounts
   - Falls back to configured default fee (150.00)

3. **Fee Assignment:**
   - Automatically assigns resit fee when request is created
   - Uses ResitRequest model event (booted method)
   - Sets due date based on config (default: 7 days)

## Business Logic

### Failed Course Criteria
A course is considered failed and eligible for resit if:
1. **Total marks < 50%**
2. **All exam types are published** for that subject marking
3. **Publish date/time has passed** (marks are visible to student)
4. **No existing resit request** for that course

### Validation in isFullyPublished()
```php
- Subject marking workflow_state == 'published'
- Subject marking publish_date + publish_time <= current datetime
- All active exam types have published exam states
- Each exam type's publish_date + publish_time <= current datetime
```

### Resit Request Workflow States
1. **requested** - Initial state when student submits
2. **awaiting_payment** - Automatically set when fee is assigned
3. **approved** - Auto-approved when payment is settled
4. **scheduled** - Admin assigns resit session/semester
5. **rejected** - Admin rejects the request

### Payment Status
1. **pending** - Fee assigned, not paid
2. **partial** - Partially paid
3. **paid** - Fully paid
4. **waived** - Fee waived by admin
5. **cancelled** - Fee cancelled

## User Interface

### Student Portal - Resit Request Page

#### Search Form
- **Session Dropdown:** Select academic session
- **Semester Dropdown:** Select semester
- **Search Button:** Load failed courses
- **My Requests Button:** Navigate to request history

#### Failed Courses Table
Columns:
- # (Row number)
- Course Code
- Course Title
- Credits
- Total Marks (with danger badge if < 50%)
- Grade (from grade table)
- Status (existing request status or "Not Requested")
- Action (Request Resit button or "Already Requested")

#### Request Confirmation
- JavaScript confirmation: "Are you sure you want to request a resit for this course? A resit fee will be assigned."
- On success: "Resit request submitted successfully. Fee has been assigned."

#### Recent Requests Summary
Shows last 5 requests with:
- Subject name
- Session
- Request date
- Workflow status
- Payment status

### Student Portal - Request History Page

#### Features:
- DataTable with sorting/searching
- All resit requests displayed
- Detailed information including:
  - Subject code and title
  - Program
  - Session and semester
  - Request date/time
  - Resit session (if scheduled)
  - Workflow status with badges
  - Payment status with badges
  - Fee amount
  - Notes (viewable in modal)

## Security & Validation

### Request Validation
1. **Enrollment Verification:** Confirms enrollment belongs to authenticated student
2. **Duplicate Prevention:** Checks for existing requests
3. **Failed Status Verification:** Confirms marks are < 50%
4. **Published Status Verification:** Ensures all exam types are published
5. **Category Validation:** Verifies resit fee category exists and is active

### Authorization
- All routes protected by `auth:student` middleware
- XSS protection enabled
- CSRF tokens on all forms

## Admin Integration

### Viewing Resit Requests
**Location:** `http://localhost/paxhitest/admin/exam/resit-requests`
- Admin can see all student resit requests
- Can filter by session and workflow state
- Can transition requests through workflow states
- Can assign resit session/semester

### Fee Management
**Location:** `http://localhost/paxhitest/admin/fees-student-quick-assign`
- Resit fees automatically appear in student fee list
- Linked to resit_requests table via `fee_id`
- Can be viewed, modified, or waived by admin

## Configuration

### Environment Variables
```env
RESIT_DEFAULT_FEE=150.00
RESIT_FEE_CATEGORY_SLUG=resit-fee
RESIT_FEE_CATEGORY_NAME=Resit Fee
RESIT_FEE_DUE_DAYS=7
```

### Config File
**Location:** `config/resit.php`
- Default fee: 150.00
- Fee category slug: 'resit-fee'
- Fee due days: 7

## Database Schema

### Tables Modified/Used
1. **resit_requests** - Stores all resit requests
2. **fees** - Stores assigned resit fees
3. **fees_categories** - Now has `is_resit` flag
4. **fees_masters** - Used for fee amount lookup
5. **subject_markings** - Source of failed course data
6. **subject_marking_exam_states** - Verification of published status

### Key Relationships
```
ResitRequest
├── belongsTo: StudentEnroll
├── belongsTo: Subject
├── belongsTo: Session (original)
├── belongsTo: Session (resit)
├── belongsTo: Semester (resit)
├── belongsTo: Fee
└── belongsTo: User (approver)

Fee
└── hasOne: ResitRequest

FeesCategory
└── hasMany: Fee (via resit fees)
```

## Testing Checklist

### Student Portal Testing
- [ ] Login as student
- [ ] Navigate to Resits menu
- [ ] Select session and semester with failed courses
- [ ] Verify only courses with < 50% marks show
- [ ] Verify only published courses show
- [ ] Submit resit request
- [ ] Verify success message
- [ ] Check fee is automatically assigned
- [ ] Verify request appears in history
- [ ] Try to request same course again (should fail)
- [ ] Check payment status updates when fee is paid

### Admin Portal Testing
- [ ] View resit requests in admin panel
- [ ] Verify student requests appear
- [ ] Check fee is linked correctly
- [ ] Transition request through workflow states
- [ ] Assign resit session/semester
- [ ] Verify student sees updated status

### Fee Category Testing
- [ ] Create new fee category with "Is Resit Fee" checked
- [ ] Verify it displays as "Resit Fee" in table
- [ ] Edit existing category and toggle resit flag
- [ ] Verify ResitFeeService uses category with is_resit=1

## Troubleshooting

### Common Issues

**Issue:** No failed courses showing
- **Check:** Marks are < 50%
- **Check:** Marks are published (workflow_state = 'published')
- **Check:** All exam types are published
- **Check:** Publish date/time has passed

**Issue:** Fee not assigned automatically
- **Check:** Resit fee category exists with is_resit=1
- **Check:** Category status is active
- **Check:** ResitFeeService ensureFee() method is triggered
- **Check:** ResitRequest model has booted() method with event

**Issue:** Student can't see resit menu
- **Check:** Sidebar file updated correctly
- **Check:** Student is authenticated
- **Check:** Routes are registered

**Issue:** Fee amount is 0
- **Check:** FeesMaster entry exists for the category
- **Check:** config/resit.php has default_fee set
- **Check:** RESIT_DEFAULT_FEE in .env file

## Future Enhancements

### Potential Improvements
1. **Email Notifications:** Notify students when requests are approved/rejected
2. **Bulk Requests:** Allow students to request multiple resits at once
3. **Fee Calculator:** Show fee amount before submission
4. **Request Comments:** Allow students to add notes to their requests
5. **Document Upload:** Attach supporting documents to requests
6. **Deadline Management:** Set request deadlines per session
7. **Approval Workflow:** Multi-level approval process
8. **SMS Notifications:** SMS alerts for status changes
9. **Payment Integration:** Direct payment from resit request page
10. **Analytics Dashboard:** Track resit trends and success rates

## Related Files

### Controllers
- `app/Http/Controllers/Student/ResitController.php`
- `app/Http/Controllers/Admin/FeesCategoryController.php`
- `app/Http/Controllers/Admin/ResitRequestController.php`

### Models
- `app/Models/ResitRequest.php`
- `app/Models/FeesCategory.php`
- `app/Models/Fee.php`
- `app/Models/SubjectMarking.php`
- `app/Models/SubjectMarkingExamState.php`

### Services
- `app/Services/Resit/ResitFeeService.php`
- `app/Services/Resit/ResitRequestWorkflowService.php`

### Views
- `resources/views/student/resit/index.blade.php`
- `resources/views/student/resit/history.blade.php`
- `resources/views/student/layouts/inc/sidebar.blade.php`
- `resources/views/admin/fees-category/index.blade.php`
- `resources/views/admin/fees-category/edit.blade.php`
- `resources/views/admin/resit-requests/index.blade.php`

### Migrations
- `database/migrations/2025_10_22_042334_add_is_resit_to_fees_categories_table.php`
- `database/migrations/2025_10_12_000005_create_resit_requests_table.php`
- `database/migrations/2025_10_14_000007_add_fee_relation_to_resit_requests.php`
- `database/migrations/2025_10_14_000010_add_resit_semester_to_resit_requests.php`

### Routes
- `routes/web.php` (lines 738-741 - Student resit routes)

### Config
- `config/resit.php`

## Support

For questions or issues with the resit request system, please contact the development team or refer to this documentation.

---

**Last Updated:** October 22, 2025
**Version:** 1.0
**Author:** Development Team
