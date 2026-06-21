# Audit Trail Permissions

## Overview
The Audit Trail feature is protected by two permissions:
- **audit-log-view** - Allows viewing audit logs
- **audit-log-export** - Allows exporting audit logs to CSV

## Permissions Details

| Permission | Group | Description |
|------------|-------|-------------|
| `audit-log-view` | Audit Trail | View audit log list and individual log details |
| `audit-log-export` | Audit Trail | Export filtered audit logs to CSV file |

## Installation

The permissions have already been added to your system. They were:
1. Added to `PermissionSeeder.php` for future fresh installations
2. Created using `AuditPermissionSeeder.php` and granted to Admin roles

## Assigning Permissions to Roles

### Via Admin Panel
1. Go to **Settings > Roles & Permissions**
2. Select or create a role
3. Find "Audit Trail" section
4. Check the permissions you want to grant:
   - ☑ View - Allows access to audit log pages
   - ☑ Export - Allows downloading CSV reports

### Via Seeder (Already Done)
The seeder automatically granted both permissions to:
- ✓ Super Admin role
- ✓ Admin role

## Controller Protection

The `AuditLogController` has middleware protecting the routes:

```php
$this->middleware('permission:audit-log-view', ['only' => ['index', 'show']]);
$this->middleware('permission:audit-log-export', ['only' => ['export']]);
```

## View Protection

### Sidebar Menu
The Audit Trail menu item is only visible to users with either permission:
```blade
@canany(['audit-log-view', 'audit-log-export'])
    <li class="nav-item">
        <a href="{{ route('admin.audit-log.index') }}">Audit Trail</a>
    </li>
@endcanany
```

### Export Button
The export button is only shown to users with export permission:
```blade
@can('audit-log-export')
    <a href="{{ route('admin.audit-log.export') }}" class="btn btn-success">
        Export
    </a>
@endcan
```

## Testing Permissions

### Test as Admin (Has All Permissions)
- Login as admin user
- Navigate to `/admin/audit-log`
- You should see the audit log list
- Export button should be visible

### Test as Limited User
1. Create a test role (e.g., "Auditor")
2. Grant only `audit-log-view` permission
3. Assign role to a test user
4. Login as that user
5. Navigate to audit log - should work
6. Export button should be hidden

### Test Restricted Access
1. Create a role without audit permissions
2. Assign to a test user
3. Login as that user
4. Try to access `/admin/audit-log`
5. Should get 403 Forbidden or redirected

## Granting Permissions to Other Roles

If you want to grant audit trail access to other roles:

```php
// In tinker or seeder
$role = Role::findByName('Teacher');
$role->givePermissionTo('audit-log-view');

// Or grant both
$role->givePermissionTo(['audit-log-view', 'audit-log-export']);
```

Or use the admin panel:
1. Settings > Roles & Permissions
2. Edit the role
3. Check "Audit Trail" permissions
4. Save

## Security Recommendations

1. **View Permission**: Grant to supervisors, admitors, and management who need to review system activities
2. **Export Permission**: Grant only to senior management and compliance officers who need reports
3. **Regular Review**: Periodically review who has these permissions
4. **Audit the Auditors**: Even audit log access is logged for accountability

## Common Use Cases

| Role | View | Export | Reason |
|------|:----:|:------:|--------|
| Super Admin | ✓ | ✓ | Full system access |
| Admin | ✓ | ✓ | System management |
| Accountant | ✓ | ✓ | Financial auditing |
| Teacher | ✓ | ✗ | Review student-related changes |
| Data Entry | ✗ | ✗ | No audit access needed |

## Troubleshooting

### Issue: Can't see Audit Trail menu
**Solution**: Check if user has `audit-log-view` or `audit-log-export` permission

### Issue: 403 Forbidden error
**Solution**: User needs `audit-log-view` permission. Grant it via Settings > Roles

### Issue: Export button not showing
**Solution**: User needs `audit-log-export` permission specifically

### Issue: Permissions not working after adding
**Solution**: Clear cache with `php artisan permission:cache-reset`

## Files Modified

- `app/Http/Controllers/Admin/AuditLogController.php` - Added middleware
- `database/seeders/PermissionSeeder.php` - Added permissions for fresh installs
- `database/seeders/AuditPermissionSeeder.php` - One-time permission seeder
- `resources/views/admin/audit-log/index.blade.php` - Already had @can checks
- `resources/views/admin/layouts/inc/sidebar.blade.php` - Already had @canany check

## References

- Laravel Permissions: https://spatie.be/docs/laravel-permission
- Permission Middleware: https://spatie.be/docs/laravel-permission/basic-usage/middleware
