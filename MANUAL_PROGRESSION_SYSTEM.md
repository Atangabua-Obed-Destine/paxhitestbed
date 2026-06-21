# Manual Progression System

## Overview

Replaced automatic semester progression with a student-initiated progression system. Students now have full control over when they progress to the next semester, with transparency about their accomplishments.

## Key Features

### 1. **Progression Button in Header**
- Appears in student header when eligible for progression
- Animated badge indicator draws attention
- Hidden when not eligible
- Available at all times for student convenience

### 2. **Eligibility Checking**
- Automatic background check on every page load
- Checks both regular semester and resit semester eligibility
- Returns structured data with progression type and requirements

### 3. **Progression Modal**
- Shows comprehensive semester accomplishments summary
- Displays different information based on progression type

**For Regular Progression:**
- Current semester and target semester
- Semester GPA
- Credits earned vs attempted
- List of all semester courses with grades
- Requirements met checklist

**For Resit Progression:**
- Current semester status
- Resit semester details
- Failed courses requiring resit
- Requirements met checklist

### 4. **Student Control**
- Student reviews accomplishments
- Can cancel modal to wait
- Must explicitly click "Proceed with Progression"
- System only progresses after confirmation

## Technical Implementation

### Backend Components

#### 1. ProgressionEligibilityService
**Location:** `app/Services/Student/ProgressionEligibilityService.php`

**Purpose:** Determines if student is eligible for progression and builds summary data

**Key Methods:**
```php
// Main entry point - checks eligibility
public function checkEligibility(Student $student): array

// Check if can progress to resit semester
protected function checkResitSemesterEligibility(StudentEnroll $enrollment): array

// Check if can progress to regular semester
protected function checkRegularSemesterEligibility(StudentEnroll $enrollment): array

// Build resit progression summary
protected function buildResitProgressionSummary(StudentEnroll $enrollment, array $result): array

// Build regular progression summary with GPA, credits, courses
protected function buildRegularProgressionSummary(StudentEnroll $enrollment, array $result): array
```

**Return Structure:**
```php
[
    'eligible' => true|false,
    'type' => 'resit'|'regular'|null,
    'enrollment_id' => int,
    'target_session_id' => int,
    'target_semester_id' => int,
    'target_semester_title' => string,
    'summary' => [
        'current_semester' => string,
        'current_session' => string,
        'progression_type' => string,
        'message' => string,
        'requirements' => array,
        // For regular progression:
        'semester_gpa' => string,
        'credits_attempted' => string,
        'credits_earned' => string,
        'courses' => array,
        // For resit progression:
        'failed_courses' => array,
    ]
]
```

**Eligibility Logic:**

**Resit Semester:**
- Student has scheduled resit exams
- Resit semester configured for program
- Failed courses not yet passed

**Regular Semester:**
- All marks published for current semester
- No failed courses pending resit
- All declined resit decisions made
- No active resit requests blocking progression

#### 2. ProgressionController
**Location:** `app/Http/Controllers/Student/ProgressionController.php`

**Endpoints:**

**GET `/student/progression/check-eligibility`**
- Returns JSON with eligibility status
- Called via AJAX on page load
- Shows/hides progression button based on response

**POST `/student/progression/proceed`**
- Processes student-initiated progression
- Re-validates eligibility before processing
- Handles both regular and resit progression
- Returns JSON with success status and redirect URL

**Request Parameters:**
```php
[
    'type' => 'regular'|'resit', // Required
    '_token' => string           // CSRF token
]
```

**Success Response:**
```php
[
    'success' => true,
    'message' => 'Successfully progressed to...',
    'redirect' => 'url'
]
```

**Error Response:**
```php
[
    'success' => false,
    'message' => 'Error message'
]
```

#### 3. Routes
**Location:** `routes/web.php` (Student middleware group)

```php
// Progression Routes
Route::get('progression/check-eligibility', 'ProgressionController@checkEligibility')
    ->name('progression.check');
Route::post('progression/proceed', 'ProgressionController@proceed')
    ->name('progression.proceed');
```

**Middleware Applied:**
- Authentication (student guard)
- Platform fee verification
- Student enrollment check

### Frontend Components

#### 1. Progression Button
**Location:** `resources/views/student/layouts/master.blade.php` (Header navbar)

**HTML Structure:**
```html
<li id="progression-button-container" style="display: none;">
    <a href="#" id="progression-button" 
       data-bs-toggle="modal" 
       data-bs-target="#progressionModal" 
       class="btn btn-sm btn-success position-relative">
        <i class="fas fa-arrow-circle-up"></i> Ready to Progress
        <span class="position-absolute top-0 start-100 translate-middle 
                     p-2 bg-danger border border-light rounded-circle 
                     pulse-animation">
            <span class="visually-hidden">New progression available</span>
        </span>
    </a>
</li>
```

**Features:**
- Hidden by default (`display: none`)
- Shown via JavaScript when eligible
- Animated pulsing badge indicator
- Gentle bounce animation on button
- Triggers progression modal on click

**Placement:** Between program switcher and notifications dropdown

#### 2. Progression Modal
**Location:** `resources/views/student/layouts/master.blade.php` (Before footer)

**Structure:**
```html
<div class="modal fade" id="progressionModal">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5>Semester Progression</h5>
            </div>
            <div class="modal-body" id="progressionModalBody">
                <!-- Dynamically populated with eligibility data -->
            </div>
            <div class="modal-footer" id="progressionModalFooter">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" id="proceedProgressionBtn">
                    Proceed with Progression
                </button>
            </div>
        </div>
    </div>
</div>
```

**Modal Content (Dynamically Generated):**

**Regular Progression:**
- Alert with success message
- Current vs target semester cards
- Academic stats cards (GPA, Credits Earned, Credits Attempted)
- Course list table with grades
- Requirements checklist

**Resit Progression:**
- Alert with success message
- Current vs target semester cards
- Failed courses list
- Requirements checklist

#### 3. JavaScript Functionality

**Eligibility Checking:**
```javascript
function checkProgressionEligibility() {
    $.ajax({
        url: '{{ route("progression.check") }}',
        method: 'GET',
        success: function(response) {
            if (response.eligible) {
                eligibilityData = response;
                $('#progression-button-container').fadeIn();
            } else {
                $('#progression-button-container').hide();
            }
        }
    });
}
```

**Called on:** `$(document).ready()`

**Modal Population:**
```javascript
$('#progressionModal').on('show.bs.modal', function() {
    // Build HTML from eligibilityData
    // Different content for regular vs resit
    // Display summary, GPA, courses, requirements
});
```

**Progression Processing:**
```javascript
$('#proceedProgressionBtn').click(function() {
    $.ajax({
        url: '{{ route("progression.proceed") }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            type: eligibilityData.type
        },
        success: function(response) {
            // Show success message
            // Redirect after 2 seconds
        },
        error: function(xhr) {
            // Show error message
            // Re-enable button
        }
    });
});
```

### Disabled Components

#### Auto-Progression Calls (Commented Out)

**1. ResitController.php**
- `cancel()` method - Line ~387
- `decline()` method - Line ~489

**Before:**
```php
$this->checkAndNotifyProgression($enrollment);
```

**After:**
```php
// Auto-progression disabled - students now progress manually via header button
// $this->checkAndNotifyProgression($enrollment);
```

**2. ResitRequestWorkflowService.php**
- `transition()` method - Line ~167

**Before:**
```php
if ($toState === ResitRequest::STATE_SCHEDULED) {
    $this->checkAndTriggerResitSemesterProgression($request);
}
```

**After:**
```php
// Auto-progression disabled - students now progress manually via header button
// if ($toState === ResitRequest::STATE_SCHEDULED) {
//     $this->checkAndTriggerResitSemesterProgression($request);
// }
```

**Note:** The progression service methods themselves (`checkAndNotifyProgression`, `checkAndTriggerResitSemesterProgression`) are **kept intact** but no longer automatically called. They may still be used for manual progression logic or admin tools.

## User Flow

### 1. Student Login
```
Login → Dashboard loads → AJAX checks eligibility
```

### 2. Eligible for Progression
```
Eligibility check succeeds
↓
Progression button appears in header with pulsing badge
↓
Student notices button (animated, attention-grabbing)
```

### 3. Review Accomplishments
```
Student clicks "Ready to Progress" button
↓
Modal opens with loading spinner
↓
Modal populated with semester summary:
  - Current semester: Level 1 Semester 1
  - Target semester: Level 1 Semester 2
  - GPA: 3.45
  - Credits Earned: 28 / 30
  - Course grades table
  - Requirements checklist (all marked complete)
```

### 4. Student Decision
```
Student reviews information
↓
EITHER:
  - Clicks "Cancel" → Modal closes, no changes made
  - Can review again later via button
OR:
  - Clicks "Proceed with Progression" → Processing begins
```

### 5. Progression Processing
```
Button disabled with spinner
↓
POST request to progression endpoint
↓
Backend validates eligibility again
↓
Processes progression (regular or resit)
↓
Returns success response
↓
Modal shows success message with checkmark
↓
Redirects to dashboard after 2 seconds
↓
Student now enrolled in new semester
↓
Button disappears (no longer eligible)
```

## Benefits

### 1. **Student Control**
- No unexpected automatic progression
- Students choose when to proceed
- Can wait if they need time to prepare

### 2. **Transparency**
- Clear view of accomplishments
- Understand why eligible for progression
- See GPA, credits, grades before proceeding

### 3. **Informed Decisions**
- Review failed courses before resit semester
- See target semester details
- Understand requirements met

### 4. **Better UX**
- Visual feedback with animated button
- Clear modal with organized information
- Loading states and success messages
- Error handling with helpful messages

### 5. **Academic Awareness**
- Students review their performance
- Conscious participation in progression
- Better engagement with academic status

## Testing Checklist

### Regular Progression
- [ ] Button appears when all marks published
- [ ] Button hidden when marks not published
- [ ] Button hidden with failed courses pending resit
- [ ] Modal shows correct GPA
- [ ] Modal shows correct credits
- [ ] Modal lists all semester courses
- [ ] Grades display correctly
- [ ] Requirements checklist accurate
- [ ] Proceed button works
- [ ] Progression completes successfully
- [ ] Redirects to dashboard
- [ ] Button disappears after progression

### Resit Progression
- [ ] Button appears with scheduled resit exams
- [ ] Modal shows resit semester details
- [ ] Failed courses listed correctly
- [ ] Requirements checklist accurate
- [ ] Proceed button works
- [ ] Resit enrollment created
- [ ] Student enrolled in resit semester
- [ ] Button disappears after progression

### Error Handling
- [ ] Error message if eligibility lost
- [ ] Error message if progression fails
- [ ] Button re-enabled after error
- [ ] Can retry after error
- [ ] Network errors handled gracefully

### UI/UX
- [ ] Button animation works
- [ ] Badge pulses correctly
- [ ] Modal opens smoothly
- [ ] Loading spinner shows
- [ ] Modal content formats correctly
- [ ] Success animation displays
- [ ] Redirect timing appropriate
- [ ] Mobile responsive

## Configuration

### No Configuration Required
System works automatically based on:
- Mark publication status
- Resit exam scheduling
- Program semester configuration
- Student enrollment status

### To Re-enable Auto-Progression (If Needed)
1. Uncomment progression calls in `ResitController.php` (lines ~387, ~489)
2. Uncomment progression call in `ResitRequestWorkflowService.php` (line ~167)
3. Optionally hide progression button in header
4. Note: Both systems can coexist if needed

## Files Modified

### Created Files
1. `app/Services/Student/ProgressionEligibilityService.php` (NEW)
2. `app/Http/Controllers/Student/ProgressionController.php` (NEW)

### Modified Files
1. `routes/web.php` - Added progression routes
2. `resources/views/student/layouts/master.blade.php` - Added button, modal, and JavaScript
3. `app/Http/Controllers/Student/ResitController.php` - Disabled auto-progression calls
4. `app/Services/Resit/ResitRequestWorkflowService.php` - Disabled auto-progression call

## Database Impact

**No database changes required.**

System uses existing tables:
- `students`
- `student_enrolls`
- `semesters`
- `sessions`
- `subject_marks`
- `resit_requests`
- `program_semesters`

## API Endpoints

### GET /student/progression/check-eligibility
**Purpose:** Check if student is eligible for progression

**Authentication:** Required (student guard)

**Response:**
```json
{
    "eligible": true,
    "type": "regular",
    "enrollment_id": 123,
    "target_session_id": 5,
    "target_semester_id": 2,
    "target_semester_title": "Level 1 Semester 2",
    "summary": {
        "current_semester": "Level 1 Semester 1",
        "current_session": "2023/2024",
        "progression_type": "Regular Semester Progression",
        "semester_gpa": "3.45",
        "credits_attempted": "30",
        "credits_earned": "28",
        "message": "You are eligible to progress to Level 1 Semester 2!",
        "requirements": [
            "All marks have been published",
            "No failed courses pending resit",
            "All resit decisions made"
        ],
        "courses": [
            {
                "course_name": "Mathematics",
                "grade": "A",
                "gpa": "4.0",
                "credits": "3"
            }
        ]
    }
}
```

### POST /student/progression/proceed
**Purpose:** Process student-initiated progression

**Authentication:** Required (student guard)

**Request:**
```json
{
    "type": "regular",
    "_token": "csrf_token"
}
```

**Success Response:**
```json
{
    "success": true,
    "message": "Successfully progressed to Level 1 Semester 2",
    "redirect": "/student/dashboard"
}
```

**Error Response:**
```json
{
    "success": false,
    "message": "Error message explaining what went wrong"
}
```

## Troubleshooting

### Button Not Appearing

**Possible Causes:**
1. Student not eligible (marks not published, failed courses pending)
2. JavaScript error preventing AJAX call
3. Route not accessible
4. Authentication issue

**Debug Steps:**
1. Open browser console for JavaScript errors
2. Check network tab for AJAX request to `/student/progression/check-eligibility`
3. Verify response shows `eligible: false` and check summary message
4. Verify student has active enrollment
5. Check marks publication status

### Modal Not Opening

**Possible Causes:**
1. Bootstrap modal not initialized
2. Button click event not binding
3. Modal HTML not rendered

**Debug Steps:**
1. Check browser console for errors
2. Verify Bootstrap JS loaded
3. Inspect page source for modal HTML
4. Test manual modal trigger: `$('#progressionModal').modal('show')`

### Progression Failing

**Possible Causes:**
1. Eligibility changed between check and proceed
2. Database constraint violation
3. Service error during progression

**Debug Steps:**
1. Check browser console for error response
2. Check Laravel logs for exception details
3. Verify database state (enrollment, semester configuration)
4. Re-check eligibility manually

### Button Showing When Shouldn't

**Possible Causes:**
1. Eligibility logic incorrect
2. Stale eligibility data cached
3. JavaScript not hiding button properly

**Debug Steps:**
1. Clear browser cache and reload
2. Check eligibility endpoint response manually
3. Review eligibility logic in `ProgressionEligibilityService`
4. Verify mark publication status in database

## Future Enhancements

### Potential Additions
1. **Email notification** when student becomes eligible
2. **Countdown timer** before auto-hiding button
3. **Progress bar** showing semester completion percentage
4. **Downloadable transcript** of accomplishments
5. **Social share** of semester completion
6. **Animation effects** for modal transitions (confetti, etc.)
7. **Print summary** option for records
8. **Configuration toggle** to enable/disable manual vs auto progression
9. **Admin override** to manually progress students
10. **Bulk progression** tool for admins

### Analytics Opportunities
- Track time between eligibility and progression
- Monitor students who delay progression
- Identify patterns in progression timing
- Generate progression reports for admins

## Related Documentation

- `RESIT_SEMESTER_AUTO_PROGRESSION_COMPLETE.md` - Original auto-progression system
- `COURSE_REGISTRATION_ENHANCEMENT_SUMMARY.md` - Related semester features
- `GPA_TREND_VISUALIZATION.md` - GPA calculation used in summary

## Summary

The manual progression system gives students control and transparency over their academic progression. Instead of automatic progression happening invisibly, students now:
1. See a clear indicator when eligible
2. Review their accomplishments and requirements
3. Make an informed decision to proceed
4. Confirm their progression explicitly

This improves student engagement, academic awareness, and user experience while maintaining all the logic and validation of the previous automatic system.
