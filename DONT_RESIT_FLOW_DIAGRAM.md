# Don't Resit Course - User Flow Diagram

## Scenario: Student Has Failed Courses

```
┌─────────────────────────────────────────────────────────────────┐
│                    Student Fails Course(s)                       │
│                      (Total Marks < 50%)                         │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│          Student Navigates to Resit Page                         │
│      http://localhost/paxhitest/student/resit                    │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│             Select Session & Semester                            │
│        (Filters to show failed courses only)                     │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│               Failed Courses Table Displayed                     │
│  ┌───────────────────────────────────────────────────┐          │
│  │ Course  │  Marks  │  Grade  │  Status  │  Action  │          │
│  ├───────────────────────────────────────────────────┤          │
│  │ Math    │   45%   │   F     │ Not Req  │ [Buttons]│          │
│  │ Physics │   38%   │   F     │ Not Req  │ [Buttons]│          │
│  └───────────────────────────────────────────────────┘          │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                 Student Has Two Choices                          │
│                                                                   │
│    ┌──────────────────────┐      ┌───────────────────────┐      │
│    │   Request Resit      │      │  Don't Resit Course   │      │
│    │   (Blue Button)      │      │  (Orange Button)      │      │
│    └──────────┬───────────┘      └───────────┬───────────┘      │
│               │                               │                  │
└───────────────┼───────────────────────────────┼──────────────────┘
                │                               │
                ▼                               ▼
┌───────────────────────────┐   ┌──────────────────────────────┐
│   REQUEST RESIT PATH      │   │   DON'T RESIT PATH           │
│   (Existing Flow)         │   │   (NEW FEATURE)              │
└───────────────────────────┘   └──────────────────────────────┘
                │                               │
                ▼                               ▼
┌───────────────────────────┐   ┌──────────────────────────────┐
│ 1. Fee Assigned           │   │ 1. Confirmation Dialog       │
│ 2. Payment Required       │   │    "Are you sure you do NOT  │
│ 3. Finance Review         │   │     want to resit?"          │
│ 4. Approval Workflow      │   └──────────────┬───────────────┘
│ 5. Exam Scheduled         │                  │
│ 6. Take Resit Exam        │                  ▼
│ 7. Results Published      │   ┌──────────────────────────────┐
└───────────┬───────────────┘   │ 2. Create ResitRequest       │
            │                   │    - workflow_state='declined'│
            │                   │    - fee_amount=0             │
            │                   │    - payment_status='waived'  │
            │                   └──────────────┬───────────────┘
            │                                  │
            │                                  ▼
            │                   ┌──────────────────────────────┐
            │                   │ 3. Success Message           │
            │                   │    "Successfully declined"   │
            │                   └──────────────┬───────────────┘
            │                                  │
            │                                  ▼
            │                   ┌──────────────────────────────┐
            │                   │ 4. Check All Failed Courses  │
            │                   │    Are they all:             │
            │                   │    - Passed, OR              │
            │                   │    - Declined?               │
            │                   └──────────────┬───────────────┘
            │                                  │
            │                                  │ YES
            │                                  ▼
            │                   ┌──────────────────────────────┐
            │                   │ 5. AUTO-PROGRESSION!         │
            │                   │    "Congratulations! You've  │
            │                   │     been progressed to       │
            │                   │     Semester 2"              │
            │                   └──────────────────────────────┘
            │
            ▼
┌───────────────────────────────────────────────────────────────┐
│              BOTH PATHS CONVERGE                              │
│                                                               │
│  If ALL failed courses are resolved (passed OR declined):    │
│  → Student automatically progresses to next semester          │
│                                                               │
│  If SOME courses still unresolved:                           │
│  → Student remains in current semester                       │
└───────────────────────────────────────────────────────────────┘
```

## Decision Tree

```
Student fails course
    │
    ├─ Does student want to retake?
    │   │
    │   ├─ YES → Request Resit
    │   │   │
    │   │   ├─ Pay fee
    │   │   ├─ Wait for approval
    │   │   ├─ Take exam
    │   │   └─ Get result
    │   │       │
    │   │       ├─ Pass → Progress ✅
    │   │       └─ Fail → Stuck ❌
    │   │
    │   └─ NO → Don't Resit Course (NEW!)
    │       │
    │       ├─ No fee
    │       ├─ Accept failing grade
    │       └─ Progress ✅ (if all other courses resolved)
    │
    └─ Are ALL failed courses resolved?
        │
        ├─ YES → Auto-Progression ✅
        └─ NO → Remain in semester ❌
```

## State Transitions

```
Failed Course States:

┌─────────────────┐
│  No Request     │ ← Initial state
└────────┬────────┘
         │
         ├─────────────────────┬───────────────────┐
         │                     │                   │
         ▼                     ▼                   ▼
┌─────────────────┐  ┌──────────────────┐  ┌─────────────────┐
│   Requested     │  │    Declined      │  │   (No action)   │
│   (blue path)   │  │  (orange path)   │  │  (stuck here)   │
└────────┬────────┘  └──────────────────┘  └─────────────────┘
         │                     │
         ▼                     │
┌─────────────────┐            │
│ Awaiting Payment│            │
└────────┬────────┘            │
         │                     │
         ├─ Cancel ────────────┤ (can request again)
         │                     │
         ▼                     │
┌─────────────────┐            │
│ Finance Review  │            │
└────────┬────────┘            │
         │                     │
         ▼                     │
┌─────────────────┐            │
│    Approved     │            │
└────────┬────────┘            │
         │                     │
         ▼                     │
┌─────────────────┐            │
│    Scheduled    │            │
└────────┬────────┘            │
         │                     │
         ▼                     │
┌─────────────────┐            │
│  (Take Exam)    │            │
└────────┬────────┘            │
         │                     │
         ▼                     ▼
┌──────────────────────────────────┐
│      Auto-Progression Check      │
│                                  │
│  All courses passed OR declined? │
│           ├─ Yes → Progress      │
│           └─ No  → Stay          │
└──────────────────────────────────┘
```

## Example Scenarios

### Scenario A: All Declined
```
Student fails: Math, Physics, Chemistry
Action: Decline all 3 courses
Result: ✅ Immediate auto-progression
Time: < 1 minute
Cost: 0 FCFA
```

### Scenario B: Mixed Approach
```
Student fails: Math, Physics, Chemistry
Action: 
  - Request resit for Math (important)
  - Decline Physics
  - Decline Chemistry
Result: 
  - Pay fee for Math only
  - After Math resit passed → ✅ Auto-progression
Time: ~2 weeks (for resit schedule)
Cost: 1 resit fee
```

### Scenario C: All Requested
```
Student fails: Math, Physics, Chemistry
Action: Request resit for all 3
Result:
  - Pay 3 resit fees
  - Take 3 resit exams
  - If all pass → ✅ Auto-progression
Time: ~2-4 weeks
Cost: 3 resit fees
```

### Scenario D: Strategic Decision
```
Student fails: 
  - Core course (Math): 35%
  - Elective (Art): 48%
  
Strategy:
  - Request resit for Math (must pass)
  - Decline Art (accept F, move on)
  
Result:
  - Pay 1 fee instead of 2
  - Focus on important course
  - ✅ Progress after Math resit
```

## User Interface Visual

```
┌─────────────────────────────────────────────────────────────────────┐
│                    Failed Courses - Semester 1                       │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  Code  │ Course Name │ Marks │ Grade │     Status     │   Action     │
│  ──────┼─────────────┼───────┼───────┼────────────────┼─────────────│
│  MTH1  │ Calculus I  │  45%  │   F   │ Not Requested  │ [Request]   │
│        │             │       │       │                │ [Don't Resit]│
│  ──────┼─────────────┼───────┼───────┼────────────────┼─────────────│
│  PHY1  │ Physics I   │  38%  │   F   │ Declined       │ Declined ✘  │
│  ──────┼─────────────┼───────┼───────┼────────────────┼─────────────│
│  CHM1  │ Chemistry I │  42%  │   F   │ Awaiting Pay   │ [Cancel]    │
└─────────────────────────────────────────────────────────────────────┘

Legend:
  [Request] - Blue button to request resit (costs money)
  [Don't Resit] - Orange button to decline resit (no cost)
  [Cancel] - Red button to cancel existing request
  Declined ✘ - Gray text, decision already made
```

## Benefits Summary

### For Students:
- ✅ **Choice**: Not forced to resit every failed course
- ✅ **Cost Savings**: No fee for declined courses
- ✅ **Time Savings**: Progress faster without waiting for resit schedules
- ✅ **Flexibility**: Can focus on important courses only

### For Institution:
- ✅ **Better Flow**: Students don't get stuck indefinitely
- ✅ **Reduced Bottleneck**: Fewer students waiting for resit schedules
- ✅ **Clear Audit Trail**: Every decision is recorded
- ✅ **Transparent Process**: Students make informed choices

### For Administration:
- ✅ **Less Paperwork**: Declined courses need no scheduling
- ✅ **Better Reporting**: Track student decisions and patterns
- ✅ **Resource Optimization**: Focus on students who want to resit
- ✅ **Clear Records**: Database tracks every declined course
