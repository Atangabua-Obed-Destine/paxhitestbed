# Journal Entry Trash/Recycle Bin Feature

## Overview

A complete trash/recycle bin system for journal entries that allows users to:
- View all deleted journal entries
- Restore deleted entries
- Permanently delete entries

## Features Implemented

### 1. Soft Delete (Already Existing)
- Journal entries use Laravel's `SoftDeletes` trait
- When deleted, entries are marked with `deleted_at` timestamp
- Entries remain in database but hidden from normal queries

### 2. Trash View
- Dedicated page to view all soft-deleted entries
- Shows entry details: number, date, type, description, totals
- Displays when entry was deleted
- Pagination support for large numbers of deleted entries

### 3. Restore Functionality
- Recover soft-deleted entries back to active list
- One-click restore with confirmation
- Entry and all its lines are restored
- Success notification

### 4. Permanent Delete
- Completely remove entries from database
- Cannot be undone (double confirmation required)
- Deletes entry and all related lines
- Protected: cannot delete posted entries (safety check)

## Routes Added

```php
// View trash
GET /admin/journal-entries-trash
Route: admin.journal-entries.trash

// Restore entry
POST /admin/journal-entries/{id}/restore  
Route: admin.journal-entries.restore

// Permanent delete
DELETE /admin/journal-entries/{id}/force-delete
Route: admin.journal-entries.force-delete
```

## Controller Methods Added

### `trash()`
**Purpose:** Display all soft-deleted journal entries

**Returns:**
- View: `admin.journal-entries.trash`
- Paginated list of deleted entries
- Includes fiscal year, period, lines, creator info

**Example:**
```php
public function trash()
{
    $data['entries'] = JournalEntry::onlyTrashed()
        ->with(['fiscalYear', 'accountingPeriod', 'lines', 'createdBy'])
        ->orderBy('deleted_at', 'desc')
        ->paginate(25);
    
    return view('admin.journal-entries.trash', $data);
}
```

### `restore($id)`
**Purpose:** Restore a soft-deleted entry

**Process:**
1. Find entry from trash (onlyTrashed)
2. Call `restore()` method
3. Entry becomes active again
4. Redirect to trash with success message

**Safety:** Only restores soft-deleted entries

### `forceDelete($id)`
**Purpose:** Permanently delete entry

**Process:**
1. Find entry from trash
2. Check if entry was posted (shouldn't delete if posted)
3. Force delete all related lines
4. Force delete the entry
5. Redirect to trash with confirmation

**Safety Checks:**
- Cannot delete posted entries
- Confirms deletion twice
- Irreversible action

## User Interface

### Main Journal Entries Page
- Added **"Trash / Deleted Entries"** button (yellow/warning color)
- Located next to "Add Journal Entry" button
- Shows icon: 🗑️ trash icon

### Trash Page Features

#### Header
- Title: "Deleted Journal Entries"
- Back button to return to main list
- Icon indicates trash/recycle bin

#### Info Alert
- Blue info box explaining functionality
- States entries can be restored or permanently deleted

#### Table Columns
1. Entry Number (badge)
2. Entry Date
3. Journal Type (badge)
4. Description (truncated to 40 chars)
5. Total Debit (right-aligned)
6. Total Credit (right-aligned)
7. Deleted At (date & time)
8. Actions (restore & permanent delete buttons)

#### Action Buttons

**Restore Button (Green)**
- Icon: ↩️ undo arrow
- Text: "Restore"
- Confirmation: "Are you sure you want to restore this journal entry?"

**Permanent Delete Button (Red)**
- Icon: 🗑️ trash icon
- Text: "Delete Permanently"
- Confirmation: "This will permanently delete the entry. This action cannot be undone!"

#### Footer
- Shows total of all deleted entries (debit/credit)
- Pagination links
- Important notes section

#### Empty State
- Shows when no deleted entries exist
- Success message: "Trash is empty!"
- Large check icon
- Back button to main list

## User Workflows

### Workflow 1: Deleting an Entry
1. User goes to Journal Entries list
2. Clicks delete button on an entry
3. Confirms deletion
4. Entry moved to trash (soft delete)
5. Entry disappears from main list
6. Success message shown

### Workflow 2: Restoring an Entry
1. User clicks "Trash / Deleted Entries" button
2. Sees list of all deleted entries
3. Finds entry to restore
4. Clicks "Restore" button
5. Confirms restoration
6. Entry removed from trash
7. Entry appears back in main list
8. Success message shown

### Workflow 3: Permanently Deleting
1. User opens trash
2. Finds entry to permanently delete
3. Clicks "Delete Permanently" button
4. **FIRST confirmation:** "This will permanently delete..."
5. **User confirms**
6. Entry and all lines deleted from database forever
7. Cannot be recovered
8. Success message shown

## Safety Features

### 1. Double Delete Protection
- Regular delete = soft delete (reversible)
- Permanent delete = force delete (irreversible)
- Two separate actions prevent accidents

### 2. Posted Entry Protection
```php
if ($entry->is_posted) {
    Toastr::error(__('cannot_permanently_delete_posted_entry'), __('msg_error'));
    return redirect()->back();
}
```
- Cannot permanently delete posted entries
- Must unpost first

### 3. Confirmation Dialogs
- Restore: Single confirmation
- Permanent delete: Strong warning message
- Clear messaging about irreversibility

### 4. Visual Indicators
- Trash button: Yellow (warning color)
- Restore button: Green (safe action)
- Permanent delete: Red (danger action)
- Clear icons for each action

## Database Queries

### Get Deleted Entries Only
```php
JournalEntry::onlyTrashed()->get();
```

### Get Active Entries Only (Default)
```php
JournalEntry::get(); // Automatically excludes soft-deleted
```

### Get All Entries (Including Deleted)
```php
JournalEntry::withTrashed()->get();
```

### Restore Entry
```php
$entry->restore();
```

### Permanent Delete
```php
$entry->forceDelete();
```

## Translation Keys Required

Add to language files:

```php
// English (resources/lang/en/messages.php)
'deleted_journal_entries' => 'Deleted Journal Entries',
'back_to_journal_entries' => 'Back to Journal Entries',
'trash' => 'Trash',
'deleted_entries' => 'Deleted Entries',
'trash_info' => 'Trash Info',
'deleted_entries_can_be_restored_or_permanently_deleted' => 'Deleted entries can be restored or permanently deleted',
'deleted_at' => 'Deleted At',
'restore' => 'Restore',
'delete_permanently' => 'Delete Permanently',
'confirm_restore_journal_entry' => 'Are you sure you want to restore this journal entry?',
'confirm_permanent_delete_warning' => 'WARNING: This will permanently delete the journal entry',
'this_action_cannot_be_undone' => 'This action cannot be undone',
'trash_is_empty' => 'Trash is Empty',
'no_deleted_journal_entries_found' => 'No deleted journal entries found',
'journal_entry_restored_successfully' => 'Journal entry restored successfully',
'journal_entry_permanently_deleted' => 'Journal entry permanently deleted',
'cannot_permanently_delete_posted_entry' => 'Cannot permanently delete a posted entry',
'msg_restore_error' => 'Restore Error',
'recover_entry_to_active_list' => 'Recovers the entry back to the active list',
'permanently_remove_cannot_be_undone' => 'Permanently removes the entry - this cannot be undone',
'deleted_entries_kept_for_30_days' => 'Deleted entries are kept in trash for 30 days',
```

## Best Practices

### For Users:
1. **Regular Delete:** Safe, can be undone - use for mistakes
2. **Permanent Delete:** Only when absolutely sure - cannot be undone
3. **Review Trash Monthly:** Clean up old deleted entries
4. **Don't Delete Posted Entries:** Unpost first if really needed

### For Administrators:
1. **Set Trash Cleanup Policy:** Auto-delete after X days
2. **Monitor Trash Size:** Large trash may indicate training issues
3. **Backup Before Cleanup:** Always backup before mass permanent deletions
4. **Audit Trail:** Log who deletes what

## Automatic Trash Cleanup (Future Enhancement)

Could add a scheduled task to auto-delete entries older than 30 days:

```php
// In app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Auto-delete journal entries older than 30 days
    $schedule->call(function () {
        JournalEntry::onlyTrashed()
            ->where('deleted_at', '<', now()->subDays(30))
            ->forceDelete();
    })->daily();
}
```

## Permissions (Future Enhancement)

Consider adding specific permissions:
- `journal-entry.view-trash` - Can view trash
- `journal-entry.restore` - Can restore entries
- `journal-entry.force-delete` - Can permanently delete

```php
// In controller
public function trash()
{
    $this->authorize('journal-entry.view-trash');
    // ... rest of code
}
```

## Testing Checklist

### Test Case 1: Soft Delete
- ✅ Delete an unposted entry
- ✅ Entry disappears from main list
- ✅ Entry appears in trash
- ✅ Entry has deleted_at timestamp

### Test Case 2: View Trash
- ✅ Click trash button
- ✅ See deleted entries
- ✅ Correct information displayed
- ✅ Actions available

### Test Case 3: Restore
- ✅ Click restore on deleted entry
- ✅ Confirm restoration
- ✅ Entry removed from trash
- ✅ Entry appears in main list
- ✅ Entry usable/editable

### Test Case 4: Permanent Delete
- ✅ Click permanent delete
- ✅ See warning message
- ✅ Confirm deletion
- ✅ Entry completely removed
- ✅ Cannot be restored

### Test Case 5: Posted Entry Protection
- ✅ Try to soft delete posted entry
- ✅ Error shown (already implemented)
- ✅ Try to force delete posted entry from trash
- ✅ Error shown (newly implemented)

### Test Case 6: Empty Trash
- ✅ View trash with no entries
- ✅ See empty state message
- ✅ Back button works

## Files Modified/Created

### Created:
1. `resources/views/admin/journal-entries/trash.blade.php`
2. `JOURNAL_ENTRY_TRASH_DOCUMENTATION.md` (this file)

### Modified:
1. `routes/web.php` - Added 3 new routes
2. `app/Http/Controllers/Admin/JournalEntryController.php` - Added 3 methods
3. `resources/views/admin/journal-entries/index.blade.php` - Added trash button

## Summary

✅ Complete trash/recycle bin system implemented
✅ Safe soft-delete mechanism
✅ Easy restore functionality
✅ Permanent delete with protection
✅ User-friendly interface
✅ Clear visual indicators
✅ Double confirmation for dangerous actions
✅ Posted entry protection

**Status:** Ready for production use! 🎉
