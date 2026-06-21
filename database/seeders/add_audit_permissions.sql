-- Add Audit Trail Permissions
-- Run this SQL directly in your database or use: php artisan db:seed --class=AuditPermissionSeeder

INSERT INTO `permissions` (`name`, `guard_name`, `group`, `title`, `created_at`, `updated_at`) VALUES
('audit-log-view', 'web', 'Audit Trail', 'View', NOW(), NOW()),
('audit-log-export', 'web', 'Audit Trail', 'Export', NOW(), NOW());

-- Grant these permissions to the Super Admin role (adjust role_id if needed)
-- Find your Super Admin or Admin role ID first:
-- SELECT id, name FROM roles;

-- Then run (replace ? with your admin role id):
-- INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) 
-- SELECT id, ? FROM permissions WHERE name IN ('audit-log-view', 'audit-log-export');
