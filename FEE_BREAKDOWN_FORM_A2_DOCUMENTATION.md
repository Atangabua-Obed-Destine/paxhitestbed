# Fee Breakdown Feature for Form A2 - Documentation

## Overview
This feature adds the ability to configure detailed fee breakdowns for **first installment fees** in **Type 1 (First) semesters**. The breakdown data will be used to generate Form A2 documents for students.

## When It Applies
- **Semester Type**: Type 1 (First Semester - e.g., 1st Semester, 3rd Semester, etc.)
- **Fee Category**: First Installment fees only (`is_first_installment = 1`)
- **Requirement**: Breakdown total **must equal** the fee amount

## Database Structure

### New Table: `program_semester_fee_breakdowns`
```sql
CREATE TABLE program_semester_fee_breakdowns (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    program_semester_fee_id BIGINT (FK to program_semester_fees),
    title VARCHAR(255),              -- e.g., "Tuition Fee"
    amount DECIMAL(10,2),            -- e.g., 150000.00
    order INT DEFAULT 0,             -- Display order
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Model: `ProgramSemesterFeeBreakdown`
- **Relationships**:
  - `belongsTo(ProgramSemesterFee)`
  
### Updated Model: `ProgramSemesterFee`
- **New Relationship**:
  - `hasMany(ProgramSemesterFeeBreakdown)` - Ordered by `order` column

## User Interface

### Creating Fee Configuration

1. **Navigate to**: http://localhost/paxhitest/admin/program-semester-fee/create

2. **Select Semester Type 1**: When you select "Semester Type 1", the system identifies all first semesters across years.

3. **Load Fee Categories**: Click "Load Fee Categories" to load installment categories.

4. **Enter Fee Amount**: 
   - Select a first installment category
   - Enter the total fee amount (e.g., 312,500)

5. **Breakdown Section Appears**: 
   - Automatically shows when:
     - ✓ Semester Type = 1 (Type 1)
     - ✓ Category is First Installment
     - ✓ Amount > 0

6. **Add Breakdown Items**:
   - Click "Add Item" button
   - Enter title (e.g., "Tuition Fee", "Registration Fee", "Library Fee")
   - Enter amount for each item
   - Add more items as needed

7. **Validation**:
   - **Real-time display** shows:
     - Breakdown Total
     - Fee Amount
     - Validation status (✓ Valid / ⚠ Warning / ✗ Error)
   
   - **Visual Feedback**:
     - 🟢 Green badge: Total matches fee amount
     - 🟡 Yellow badge: Total less than fee amount
     - 🔴 Red badge: Total exceeds fee amount

8. **Form Submission**:
   - Client-side validation prevents submission if totals don't match
   - Server-side validation ensures data integrity

### Viewing Breakdowns

**In Index Page** (http://localhost/paxhitest/admin/program-semester-fee/index):
- Fees with breakdowns show a "View Breakdown" button
- Click to see modal with:
  - Program and Semester info
  - List of all breakdown items
  - Total amount

### Editing Fee Configuration

1. Click edit button on any fee configuration
2. If eligible (Type 1 + First Installment):
   - Breakdown section appears below amount field
   - Existing breakdowns are pre-loaded
   - Can add/remove/modify items
   - Same validation rules apply

## Technical Implementation

### Controller: `ProgramSemesterFeeController`

**Store Method** (Create):
```php
// After creating ProgramSemesterFee
if ($semesterType == 1 && $category->is_first_installment == 1) {
    // Delete existing breakdowns (for updates)
    $fee->breakdowns()->delete();
    
    // Create new breakdowns
    foreach ($request->breakdown_titles_{$index} as $bIndex => $title) {
        if (!empty($title) && !empty($breakdownAmounts[$bIndex])) {
            ProgramSemesterFeeBreakdown::create([
                'program_semester_fee_id' => $fee->id,
                'title' => $title,
                'amount' => $breakdownAmounts[$bIndex],
                'order' => $bIndex + 1,
            ]);
        }
    }
}
```

**Update Method** (Edit):
```php
// Validate breakdown totals
if ($programSemesterFee->feesCategory->is_first_installment == 1 && 
    $programSemesterFee->semester->semester_type == 1) {
    
    $breakdownTotal = array_sum($request->breakdown_amounts);
    
    if (abs($breakdownTotal - $request->amount) > 0.01) {
        throw new \Exception("Breakdown total must equal fee amount");
    }
    
    // Delete and recreate breakdowns
    $programSemesterFee->breakdowns()->delete();
    // ... create new ones
}
```

### Frontend: `create.blade.php`

**Dynamic Behavior**:
```javascript
// Show breakdown section when conditions are met
$('.amount-input').on('input', function() {
    var isFirstInstallment = checkbox.data('is-first-installment') == 1;
    var semesterType = $('#semester_type').val();
    var amount = parseFloat($(this).val()) || 0;
    
    if (isFirstInstallment && semesterType == '1' && amount > 0) {
        // Show breakdown section
        breakdownSection.show();
        
        // Add initial item if none exist
        if (breakdownItems.length === 0) {
            addBreakdownItem(categoryIndex);
        }
    }
});
```

**Validation**:
```javascript
$('#feeConfigForm').on('submit', function(e) {
    if (semesterType == '1') {
        // Check each first installment category
        $('.category-checkbox:checked').each(function() {
            if (isFirstInstallment) {
                var breakdownTotal = calculateTotal();
                
                if (Math.abs(breakdownTotal - feeAmount) > 0.01) {
                    e.preventDefault();
                    alert('Breakdown total must equal fee amount');
                    return false;
                }
            }
        });
    }
});
```

## Business Logic

### Why Only Type 1 & First Installment?

1. **Form A2 Purpose**: 
   - Form A2 is generated when a student pays their **first installment** in **Year 1**
   - Only applicable to first semester enrollments

2. **Type 1 Semesters**:
   - Type 1 = First semesters (1st, 3rd, 5th, 7th, etc.)
   - These are entry points where Form A2 is relevant

3. **First Installment Only**:
   - Only the first payment requires detailed breakdown
   - Subsequent installments don't need this detail

### Validation Rules

1. **Required Fields** (if breakdown exists):
   - All breakdown titles must be filled
   - All breakdown amounts must be > 0
   - At least one breakdown item required

2. **Amount Matching**:
   - Sum of breakdown amounts **must equal** fee amount
   - Tolerance: ±0.01 (for floating-point precision)

3. **Server-Side Protection**:
   - Database transaction ensures atomicity
   - Validation exception rolls back entire operation
   - Error messages clearly indicate the problem

## Data Flow

### Creating Configuration

1. **User Input**:
   ```
   Program: HND Banking
   Semester Type: Type 1
   Category: First Installment
   Amount: 312,500
   Breakdowns:
     - Tuition Fee: 250,000
     - Registration Fee: 50,000
     - Library Fee: 12,500
   ```

2. **Database Operations**:
   ```sql
   -- Create fee configuration
   INSERT INTO program_semester_fees (
       program_id, semester_id, fees_category_id, amount
   ) VALUES (1, 1, 4, 312500);
   
   -- Create breakdowns
   INSERT INTO program_semester_fee_breakdowns VALUES
       (1, 1, 'Tuition Fee', 250000, 1),
       (1, 1, 'Registration Fee', 50000, 2),
       (1, 1, 'Library Fee', 12500, 3);
   ```

3. **Applies to All Type 1 Semesters**:
   - Same breakdowns created for:
     - 1st Semester Year 1
     - 1st Semester Year 2
     - 1st Semester Year 3
     - etc.

### Retrieving for Form A2

```php
// When generating Form A2 for a student
$enrollment = $student->currentEnroll;

$firstInstallmentFee = ProgramSemesterFee::where('program_id', $enrollment->program_id)
    ->where('semester_id', $enrollment->semester_id)
    ->whereHas('feesCategory', function($q) {
        $q->where('is_first_installment', 1);
    })
    ->with('breakdowns')
    ->first();

if ($firstInstallmentFee && $firstInstallmentFee->breakdowns->count() > 0) {
    foreach ($firstInstallmentFee->breakdowns as $breakdown) {
        // Display in Form A2:
        // $breakdown->title . ': ' . $breakdown->amount
    }
}
```

## Error Handling

### Client-Side Errors

1. **Total Mismatch**:
   ```
   Alert: "Fee Breakdown Validation Failed:
   
   Breakdown total (312,000.00) must equal fee amount (312,500.00) 
   for First Installment"
   ```

2. **Empty Breakdowns**:
   ```
   Alert: "Please add at least one breakdown item for First Installment"
   ```

### Server-Side Errors

1. **Validation Exception**:
   ```php
   throw new \Exception(
       "Breakdown total ({$breakdownTotal}) must equal 
        fee amount ({$request->amount})"
   );
   ```

2. **Database Error**:
   ```php
   DB::rollBack();
   Flasher::addError('Error saving configuration: ' . $e->getMessage());
   ```

## Testing

### Test Scenarios

✓ **Test 1**: Create Type 1 + First Installment with valid breakdown
- Expected: Success, breakdowns saved

✓ **Test 2**: Create with mismatched totals
- Expected: Client-side alert prevents submission

✓ **Test 3**: Edit existing breakdown
- Expected: Old breakdowns deleted, new ones created

✓ **Test 4**: Create Type 2 (Second Semester) + First Installment
- Expected: No breakdown section shown (Type 2 not supported)

✓ **Test 5**: Create Type 1 + Second Installment
- Expected: No breakdown section shown (Second Installment not supported)

✓ **Test 6**: View breakdown in index
- Expected: Modal displays all items correctly

### Test Script

Run: `php test_fee_breakdown.php`

Checks:
- ✓ Database table exists
- ✓ All columns present
- ✓ Model relationships work
- ✓ CRUD operations function
- ✓ Data integrity maintained

## Future Enhancements

### Phase 1 (Current)
- ✓ Breakdown configuration UI
- ✓ Validation (client + server)
- ✓ Display in index page
- ✓ Edit functionality

### Phase 2 (Form A2 Generation)
- [ ] PDF template for Form A2
- [ ] Auto-fill breakdown data
- [ ] Generate on first payment
- [ ] Download/print functionality
- [ ] Email to student

### Phase 3 (Advanced Features)
- [ ] Breakdown templates (reusable)
- [ ] Bulk import breakdowns
- [ ] Breakdown history tracking
- [ ] Analytics/reports

## Security Considerations

1. **Authorization**: 
   - Requires `program-semester-fee-create` or `-edit` permission
   - Protected by middleware

2. **Input Validation**:
   - Sanitized on server-side
   - Type checking (numeric, string)
   - Max length constraints

3. **Database Transactions**:
   - Atomic operations
   - Rollback on error
   - Foreign key constraints

4. **XSS Protection**:
   - Laravel's Blade automatic escaping
   - Form validation rules

## Troubleshooting

### Issue: Breakdown section not appearing

**Check**:
1. Is semester_type = 1?
2. Is fee category `is_first_installment = 1`?
3. Is amount > 0?
4. Check browser console for JavaScript errors

### Issue: "Breakdown total must equal fee amount"

**Solution**:
1. Verify all breakdown amounts are entered
2. Use calculator to sum manually
3. Check for hidden/extra breakdown items
4. Ensure amounts are numeric (no commas)

### Issue: Breakdowns not saving

**Check**:
1. Database migration ran? (`php artisan migrate`)
2. Foreign key constraints satisfied?
3. Check Laravel logs: `storage/logs/laravel.log`
4. Verify request payload in browser DevTools

## Summary

This feature provides a robust, user-friendly way to configure fee breakdowns for Form A2 documents. It:

- ✓ Automatically detects applicable scenarios
- ✓ Provides real-time validation feedback
- ✓ Ensures data integrity with server-side checks
- ✓ Supports editing existing configurations
- ✓ Displays breakdowns clearly in the index
- ✓ Ready for Form A2 PDF generation

**Next Step**: Integrate with Form A2 PDF generation when ready.
