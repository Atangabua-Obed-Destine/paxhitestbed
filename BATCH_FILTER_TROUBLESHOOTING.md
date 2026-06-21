# Batch Filter Troubleshooting Guide

## Issue
Programme dropdowns (Program, Session, Semester, Section) are not populating automatically when Batch is selected.

## Changes Made

### 1. Script Load Order Fix
**Changed:** Moved `@include('common.js.batch_filter')` to load BEFORE the custom wizard script
**Why:** The batch filter event handlers must be attached before we can trigger the batch change event

**Before:**
```php
<script>
// Custom wizard code
</script>
@include('common.js.batch_filter')
```

**After:**
```php
@include('common.js.batch_filter')
<script>
// Custom wizard code
</script>
```

### 2. Added Wizard onInit Trigger
**Added:** Batch change trigger in wizard's `onInit` callback
**Why:** Ensures the trigger happens right after the wizard completes initialization and form is visible

```javascript
onInit: function(event, currentIndex) {
    toggleFinishButton(currentIndex);
    // Trigger batch filter after wizard initializes
    setTimeout(function() {
        const $batch = $('.batch');
        if ($batch.length && $batch.val()) {
            console.log('Wizard initialized - triggering batch change:', $batch.val());
            $batch.trigger('change');
        }
    }, 100);
}
```

### 3. Added Console Logging
**Added:** Debug console.log statements
**Why:** To help identify where the issue occurs

## Testing Steps

### Step 1: Open Browser Console
1. Visit: `http://localhost/paxhitest/admin/admission/student/create`
2. Press `F12` to open Developer Tools
3. Go to the **Console** tab

### Step 2: Check for Errors
Look for any of these errors:
- `jQuery is not defined`
- `$.ajax is not a function`
- `$(...).steps is not a function`
- CSRF token errors
- 404 errors for route 'filter-batch'

### Step 3: Check Console Logs
You should see:
```
Wizard initialized - triggering batch change: [batch_id]
```

If you see this log but dropdowns still don't populate, the issue is with the AJAX call.

### Step 4: Manual Test
1. Select a different batch from the dropdown manually
2. Check if the Program dropdown populates
3. If it works manually but not automatically, the issue is with the trigger timing

### Step 5: Check Network Tab
1. Go to **Network** tab in Developer Tools
2. Select a batch
3. Look for POST request to `/filter-batch`
4. Check the response - should contain array of programs

## Common Issues & Solutions

### Issue 1: "Cannot read property 'trigger' of undefined"
**Cause:** jQuery not loaded or batch element not found
**Solution:** 
- Check that jQuery is loaded before all scripts
- Verify the batch dropdown has class="batch"

### Issue 2: AJAX request returns 419 (CSRF Token Mismatch)
**Cause:** CSRF token not set properly
**Solution:**
```html
<!-- Add to head section -->
<meta name="csrf-token" content="{{ csrf_token() }}">
```

### Issue 3: Route 'filter-batch' not found (404)
**Cause:** Route not defined or named incorrectly
**Solution:** Check `routes/web.php` for:
```php
Route::post('filter-batch', [SomeController::class, 'filterBatch'])->name('filter-batch');
```

### Issue 4: Dropdowns populate but show "Select" only
**Cause:** Response format incorrect or empty
**Solution:** Check controller returns proper format:
```php
return response()->json([
    ['id' => 1, 'title' => 'Program 1'],
    ['id' => 2, 'title' => 'Program 2'],
]);
```

### Issue 5: Works on page refresh but not after validation error
**Cause:** Old values not triggering properly
**Solution:** The `data-selected` attributes should preserve old values:
```html
<select class="form-control program" name="program" data-selected="{{ old('program') }}">
```

## Quick Debug Script

Add this to the page to test batch filter manually:

```javascript
// Run in browser console after page loads
$(function() {
    setTimeout(function() {
        console.log('=== Batch Filter Debug ===');
        console.log('Batch element:', $('.batch').length > 0 ? 'Found' : 'NOT FOUND');
        console.log('Batch value:', $('.batch').val());
        console.log('Program element:', $('.program').length > 0 ? 'Found' : 'NOT FOUND');
        console.log('Batch change handler:', typeof $('.batch').data('events')?.change !== 'undefined' ? 'Attached' : 'NOT ATTACHED');
        
        // Manual trigger
        console.log('Attempting manual trigger...');
        $('.batch').trigger('change');
    }, 1000);
});
```

## Expected Behavior

### On Page Load
1. Wizard initializes (form becomes visible)
2. After 100ms, batch change is triggered
3. AJAX request sent to `/filter-batch`
4. Program dropdown populated with response
5. If old values exist, program change triggers automatically
6. Session & Semester dropdowns populate
7. If old semester exists, section dropdown populates

### On Manual Batch Change
1. User selects batch
2. Program dropdown clears and shows "Select"
3. AJAX request sent
4. Program options populated
5. Other dropdowns reset to "Select"

### Cascade Behavior
```
Batch Change → Triggers Program AJAX
Program Change → Triggers Session & Semester AJAX
Semester Change → Triggers Section AJAX
```

## File Locations

**Batch Filter Script:**
`resources/views/common/js/batch_filter.blade.php`

**Student Create Form:**
`resources/views/admin/student/create.blade.php`

**Routes (check these exist):**
- `filter-batch` - Returns programs for selected batch
- `filter-session` - Returns sessions for selected program
- `filter-semester` - Returns semesters for selected program
- `filter-section` - Returns sections for selected semester + program

## If Still Not Working

Try this alternative approach - move ALL initialization to a single place:

```javascript
$(function() {
    // Wait for everything to be ready
    setTimeout(function() {
        // Initialize wizard
        initWizard();
        initRelativeRepeater();
        initDocumentRepeater();
        initDistrictSelects();
        
        // After wizard is ready, trigger batch if has value
        setTimeout(function() {
            const $batch = $('.batch');
            if ($batch.val()) {
                console.log('Final trigger attempt:', $batch.val());
                $batch.trigger('change');
            }
        }, 200);
    }, 100);
});
```

## Contact Points

If the issue persists, check:
1. Browser console for JavaScript errors
2. Network tab for failed AJAX requests
3. Laravel logs for server-side errors: `storage/logs/laravel.log`
4. Check if batch has data: Are there records in the `batches` table?

## Success Indicators

✅ Batch dropdown has options
✅ Selecting batch populates program dropdown
✅ Selecting program populates session/semester
✅ Selecting semester populates section
✅ Console shows "Wizard initialized - triggering batch change"
✅ Network tab shows successful POST to filter-batch
✅ No JavaScript errors in console
✅ Old values work after validation errors
