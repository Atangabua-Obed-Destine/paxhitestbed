# Audit Trail System Documentation

## Overview

The Audit Trail system tracks all important activities in your school management system, including:
- Mark submissions and updates
- Fee payments
- Student enrollments
- Attendance records
- And any other model changes you want to track

## Features

✅ **Automatic Logging**: Automatically logs create, update, and delete operations on models
✅ **User Tracking**: Records which user performed the action
✅ **Change Tracking**: Stores old and new values for comparison
✅ **IP & User Agent**: Captures IP address and browser information
✅ **Filtering & Search**: Advanced filtering by user, event type, model, date range, etc.
✅ **Export**: Export audit logs to CSV
✅ **Permissions**: Role-based access control for viewing logs
✅ **Custom Descriptions**: Ability to customize audit log descriptions per model

## Files Created

### Database
- `database/migrations/2025_10_07_181615_create_audit_logs_table.php` - Migration file

### Models
- `app/Models/AuditLog.php` - Main audit log model
- `app/Traits/Auditable.php` - Trait for automatic logging

### Controllers
- `app/Http/Controllers/Admin/AuditLogController.php` - Handles all audit log operations

### Views
- `resources/views/admin/audit-log/index.blade.php` - List view with filters
- `resources/views/admin/audit-log/show.blade.php` - Detail view

### Routes
Added in `routes/web.php`:
```php
Route::prefix('audit-log')->group(function () {
    Route::get('/', 'AuditLogController@index')->name('audit-log.index');
    Route::get('/show/{id}', 'AuditLogController@show')->name('audit-log.show');
    Route::get('/export', 'AuditLogController@export')->name('audit-log.export');
    Route::get('/stats', 'AuditLogController@stats')->name('audit-log.stats');
});
```

## Usage

### 1. Adding Audit Logging to a Model

To enable automatic audit logging on any model, simply add the `Auditable` trait:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class YourModel extends Model
{
    use Auditable;
    
    // Your model code...
}
```

This will automatically log:
- **Create**: When a new record is created
- **Update**: When a record is modified
- **Delete**: When a record is deleted

### 2. Custom Audit Descriptions

You can customize the audit log description by adding a `getAuditDescription` method to your model:

```php
/**
 * Get custom audit description.
 *
 * @param string $event
 * @return string
 */
public function getAuditDescription($event)
{
    if ($event === 'created') {
        return "Custom description for creation";
    } elseif ($event === 'updated') {
        return "Custom description for update";
    } elseif ($event === 'deleted') {
        return "Custom description for deletion";
    }

    return "Default description";
}
```

### 3. Manual Audit Logging

For custom events (like login, logout, or specific actions), use the `customAuditLog` method:

```php
// In your controller
$model->customAuditLog(
    'fee_payment',                              // Event name
    "Fee payment of $500 received",             // Description
    ['status' => 'unpaid'],                     // Old values (optional)
    ['status' => 'paid', 'amount' => 500]       // New values (optional)
);
```

### 4. Models Already Configured

The following models have been configured with audit logging:

#### SubjectMarking
- Tracks mark submissions and updates
- Shows student name, subject, and total marks in description

#### Fee
- Tracks fee assignments and payments
- Shows student name, category, and amounts in description

#### StudentEnroll
- Tracks student enrollments
- Shows student name, program, and semester in description

#### StudentAttendance
- Tracks attendance marking
- Shows student name, subject, attendance status, and date

### 5. Adding Audit Logging to More Models

To add audit logging to additional models:

1. Open the model file (e.g., `app/Models/Application.php`)
2. Add the `Auditable` trait:

```php
use App\Traits\Auditable;

class Application extends Model
{
    use Auditable;
    
    // Optional: Add custom description
    public function getAuditDescription($event)
    {
        $studentName = $this->student_name ?? 'Unknown';
        
        if ($event === 'created') {
            return "New application submitted by {$studentName}";
        } elseif ($event === 'updated') {
            return "Application updated for {$studentName} (Status: {$this->status})";
        }
        
        return "Application was {$event}";
    }
}
```

## Permissions

Add these permissions to your roles:

- `audit-log-view` - View audit logs
- `audit-log-export` - Export audit logs to CSV

You can add these permissions through the Role management section in your admin panel.

## Accessing the Audit Trail

1. Login to admin panel
2. Click on "Audit Trail" in the sidebar menu
3. Use filters to narrow down the logs:
   - Filter by User
   - Filter by Event Type (created, updated, deleted, etc.)
   - Filter by Model (Student, Fee, etc.)
   - Search by description, IP, or URL
   - Filter by date range
4. Click "View" button to see detailed changes
5. Click "Export" to download filtered logs as CSV

## Event Types

The system tracks the following event types:

- `created` - New record created
- `updated` - Record modified
- `deleted` - Record deleted
- `mark_submitted` - Exam marks submitted
- `mark_updated` - Exam marks updated
- `fee_payment` - Fee payment received
- `student_enrolled` - Student enrolled
- `attendance_marked` - Attendance marked
- `logged_in` - User logged in
- `logged_out` - User logged out

## Examples of Custom Events

### Example 1: Login Tracking

In your `LoginController`:

```php
use App\Models\AuditLog;

public function login(Request $request)
{
    // Your login logic...
    
    AuditLog::create([
        'user_id' => auth()->id(),
        'user_type' => get_class(auth()->user()),
        'event' => 'logged_in',
        'auditable_type' => 'App\User',
        'auditable_id' => auth()->id(),
        'ip_address' => $request->ip(),
        'user_agent' => $request->userAgent(),
        'url' => $request->fullUrl(),
        'description' => auth()->user()->name . ' logged in to the system',
    ]);
}
```

### Example 2: Mark Publication

In your exam marking controller:

```php
$marking->customAuditLog(
    'mark_published',
    "Marks published for {$subject->subject_name} - {$semester->semester_name}",
    ['publish_date' => null],
    ['publish_date' => now()]
);
```

## Database Schema

The `audit_logs` table structure:

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| user_id | bigint | User who performed the action |
| user_type | string | User model type |
| event | string | Type of event (created, updated, etc.) |
| auditable_type | string | Model class name |
| auditable_id | bigint | Model ID |
| old_values | text | JSON of old values |
| new_values | text | JSON of new values |
| ip_address | string | User's IP address |
| user_agent | string | Browser/device info |
| url | string | URL where action occurred |
| description | text | Human-readable description |
| created_at | timestamp | When the log was created |
| updated_at | timestamp | When the log was updated |

## Best Practices

1. **Selective Logging**: Don't audit every model. Focus on critical data like:
   - Financial records (Fees, Transactions)
   - Academic records (Marks, Grades, Certificates)
   - Student records (Enrollments, Applications)
   - User management (Roles, Permissions)

2. **Exclude Sensitive Data**: The trait automatically excludes passwords and tokens

3. **Cleanup Old Logs**: Consider implementing a cleanup policy:
   ```php
   // In a scheduled command
   AuditLog::where('created_at', '<', now()->subMonths(12))->delete();
   ```

4. **Performance**: The system uses database indexes for optimal performance

5. **Custom Descriptions**: Always provide meaningful custom descriptions for better readability

## Troubleshooting

### Logs Not Appearing

1. Ensure the migration has been run: `php artisan migrate`
2. Check that the model has the `Auditable` trait
3. Verify the user is authenticated when making changes
4. Check permissions for viewing audit logs

### Performance Issues

1. Add indexes to frequently filtered columns
2. Implement log archiving for old records
3. Use date range filters when viewing logs

## Future Enhancements

Consider adding:
- Dashboard widget showing recent activities
- Real-time notifications for critical events
- More detailed filtering options
- Restore functionality for deleted records
- User activity timeline
- Automated suspicious activity alerts

## Support

For questions or issues, please contact your system administrator.

---

**Version**: 1.0  
**Last Updated**: October 7, 2025  
**Author**: School Management System Development Team
