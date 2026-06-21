# Testing Guide: Unpublish Feature Verification

## Issue Reported
Unpublished student result not reflecting in:
1. Subject marking page: `http://localhost/paxhitest/admin/exam/subject-marking?faculty=6&program=33&session=1&semester=0&section=0&subject=6`
2. Marksheet print: `http://localhost/paxhitest/admin/transcript/marksheet-print/6`
3. Marksheet download: `http://localhost/paxhitest/admin/transcript/marksheet-download/6`

## What Was Fixed

### Files Updated:
1. ✅ `resources/views/admin/marksheet/print.blade.php`
2. ✅ `resources/views/admin/marksheet/download.blade.php`
3. ✅ `resources/views/admin/marksheet/show.blade.php`
4. ✅ `resources/views/admin/marksheet/session-print.blade.php`
5. ✅ `resources/views/admin/marksheet/session-download.blade.php`
6. ✅ `resources/views/admin/marksheet/multi-print.blade.php`
7. ✅ `app/Http/Controllers/Admin/MarksheetController.php`

### Changes Made:
**Before:**
```php
if($mark->workflow_state === 'published' && (publish_date check)){
    // Show marks
}
```

**After:**
```php
if($mark->is_visible_to_student && (publish_date check)){
    // Show marks - respects unpublish override
}
```

---

## Testing Steps

### Step 1: Verify Unpublish Action
1. Navigate to: `http://localhost/paxhitest/admin/exam/subject-marking?faculty=6&program=33&session=1&semester=0&section=0&subject=6`
2. Find a student with published result
3. Look at **"Publish Control"** column (rightmost)
4. Click yellow **"Unpublish"** button
5. Enter reason: "Testing unpublish feature"
6. Click **"Unpublish Result"**
7. ✅ **Expected**: Badge changes to 🔒 "Unpublished" (red)

### Step 2: Verify Subject Marking Page
1. Stay on the same subject marking page
2. Hard refresh: `Ctrl + F5` (Windows) or `Cmd + Shift + R` (Mac)
3. ✅ **Expected**: Badge shows 🔒 "Unpublished" (red)
4. ✅ **Expected**: Republish button visible (green)

### Step 3: Verify Marksheet Print
1. Navigate to: `http://localhost/paxhitest/admin/transcript/marksheet-print/6`
2. Find the subject in the transcript
3. ✅ **Expected**: 
   - Grade shows as "N/A" or blank
   - Credits earned = 0 for that subject
   - Subject NOT counted in GPA calculation

### Step 4: Verify Marksheet Download
1. Navigate to: `http://localhost/paxhitest/admin/transcript/marksheet-download/6`
2. Find the subject in the transcript
3. ✅ **Expected**: 
   - Grade shows as "N/A" or blank
   - Credits earned = 0 for that subject
   - Subject NOT counted in GPA calculation

### Step 5: Verify Marksheet Show Page
1. Navigate to: `http://localhost/paxhitest/admin/transcript/marksheet/6` or click "View" button
2. Check the transcript display
3. ✅ **Expected**: Unpublished subject not visible or shows N/A

### Step 6: Republish and Verify
1. Go back to: `http://localhost/paxhitest/admin/exam/subject-marking?faculty=6&program=33&session=1&semester=0&section=0&subject=6`
2. Click green **"Republish"** button
3. Enter optional reason: "Testing complete"
4. Click **"Republish Result"**
5. ✅ **Expected**: Badge changes to 🔓 "Published" (green)
6. Revisit marksheet-print and marksheet-download
7. ✅ **Expected**: Grade and marks visible again

---

## Troubleshooting

### Issue: Badge not updating on subject marking page
**Cause**: Browser cache  
**Solution**: 
```
- Hard refresh: Ctrl + F5 (Windows) or Cmd + Shift + R (Mac)
- Clear browser cache
- Try incognito/private window
```

### Issue: Marks still showing in transcript
**Cause**: View cache not cleared  
**Solution**:
```bash
php artisan cache:clear
php artisan view:clear
php artisan config:clear
```

### Issue: Database not updated
**Check**:
```sql
SELECT id, student_enroll_id, subject_id, workflow_state, is_published_override, unpublish_reason
FROM subject_markings 
WHERE student_enroll_id = [YOUR_STUDENT_ENROLL_ID] 
  AND subject_id = 6;
```

**Expected Values**:
- `workflow_state` = "published"
- `is_published_override` = 0 (when unpublished) or NULL (when following workflow)
- `unpublish_reason` = "Testing unpublish feature" (when unpublished)

---

## SQL Verification Queries

### Check Unpublished Students
```sql
SELECT 
    sm.id,
    s.name AS student_name,
    sub.title AS subject_title,
    sm.workflow_state,
    sm.is_published_override,
    sm.unpublish_reason,
    sm.unpublished_at,
    u.name AS unpublished_by_user
FROM subject_markings sm
JOIN student_enrolls se ON sm.student_enroll_id = se.id
JOIN students s ON se.student_id = s.id
JOIN subjects sub ON sm.subject_id = sub.id
LEFT JOIN users u ON sm.unpublished_by = u.id
WHERE sm.is_published_override = 0
ORDER BY sm.unpublished_at DESC;
```

### Check Publish Logs
```sql
SELECT 
    pl.action,
    pl.reason,
    s.name AS student_name,
    sub.title AS subject_title,
    u.name AS performed_by_user,
    pl.created_at
FROM subject_marking_publish_logs pl
JOIN subject_markings sm ON pl.subject_marking_id = sm.id
JOIN student_enrolls se ON sm.student_enroll_id = se.id
JOIN students s ON se.student_id = s.id
JOIN subjects sub ON sm.subject_id = sub.id
JOIN users u ON pl.performed_by = u.id
ORDER BY pl.created_at DESC
LIMIT 10;
```

---

## Expected Behavior Summary

| Location | Unpublished | Published |
|----------|------------|-----------|
| Subject Marking Page | 🔒 Red badge "Unpublished" | 🔓 Green badge "Published" |
| Marksheet Print | Grade = N/A, Not in GPA | Grade shown, Counted in GPA |
| Marksheet Download | Grade = N/A, Not in GPA | Grade shown, Counted in GPA |
| Marksheet Show | Not visible or N/A | Visible with grade |
| Student Portal | NOT visible | Visible |

---

## Cache Clearing Commands

If changes don't appear immediately:

```bash
# Clear all Laravel caches
php artisan cache:clear
php artisan view:clear
php artisan config:clear
php artisan route:clear

# Restart server if using artisan serve
# Ctrl+C then: php artisan serve
```

---

## Browser Testing Tips

1. **Always use hard refresh** after unpublish/republish: `Ctrl + F5`
2. **Test in incognito mode** to avoid cache issues
3. **Check browser console** (F12) for JavaScript errors
4. **Verify AJAX requests** completed successfully in Network tab

---

## Success Criteria

✅ Unpublish button works and badge updates immediately  
✅ Marksheet print shows N/A for unpublished subject  
✅ Marksheet download shows N/A for unpublished subject  
✅ GPA calculation excludes unpublished subject  
✅ Republish button restores visibility  
✅ All actions logged in audit trail  

---

**Status**: All views updated to respect `is_published_override` flag  
**Cache**: Cleared  
**Ready for Testing**: ✅ Yes
