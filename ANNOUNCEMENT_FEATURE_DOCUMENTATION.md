# Announcement Feature Documentation

## Overview
A site-wide announcement system with admin CRUD management and a moving ticker display on the public site.

---

## Features Implemented

### 1. **Admin CRUD Interface**
- **Location**: Admin Portal → Front Web → Announcements
- **URL**: `/admin/web/announcement`
- **Capabilities**:
  - Create, Read, Update, Delete announcements
  - Rich text editor (TinyMCE) for formatted messages
  - Language-specific or global announcements
  - Schedule with start/end dates
  - Active/Inactive status toggle

### 2. **Public Display**
- **Location**: Top of all public pages (above header)
- **Behavior**: 
  - Smooth CSS animation scrolling ticker
  - Pauses on hover
  - Responsive (faster on mobile)
  - Only shows active announcements within scheduled dates

### 3. **Database Structure**
- **Table**: `announcements`
- **Fields**:
  - `id` - Primary key
  - `language_id` - Foreign key to languages (nullable for global)
  - `message` - Text field for announcement content (HTML supported)
  - `start_date` - Date (nullable, announcement starts displaying)
  - `end_date` - Date (nullable, announcement stops displaying)
  - `status` - TinyInt (1=active, 0=inactive)
  - `created_at`, `updated_at` - Timestamps

---

## Usage Guide

### For Administrators

#### Creating an Announcement
1. Navigate to Admin → Front Web → Announcements
2. Click "Create" button
3. Fill in the form:
   - **Language**: Select language or leave blank for all languages
   - **Message**: Enter announcement text (HTML formatting available via editor)
   - **Start Date**: When to begin showing (leave empty to show immediately)
   - **End Date**: When to stop showing (leave empty for no expiry)
   - **Status**: Set to "Active" to display
4. Click "Save"

#### Scheduling Examples
- **Immediate & Indefinite**: Leave both dates empty, set status=Active
- **Starts Now, Ends Later**: Leave start_date empty, set end_date to future date
- **Future Campaign**: Set start_date to future, end_date to campaign end
- **Past Announcement**: Set both dates in the past (won't display)

#### Tips
- Use bold (`<strong>`) and italic (`<em>`) for emphasis
- Keep messages concise for readability in the ticker
- Test inactive first, then activate when ready
- Multiple active announcements will scroll continuously

### For Developers

#### Display Logic
Announcements display when ALL conditions are met:
1. `status = 1` (Active)
2. Current date >= `start_date` (or start_date is NULL)
3. Current date <= `end_date` (or end_date is NULL)
4. `language_id` matches current language OR `language_id` is NULL

#### Query Example
```php
$today = now()->toDateString();
$announcements = Announcement::where('status', 1)
    ->where(function($q) use ($today){
        $q->whereNull('start_date')->orWhere('start_date', '<=', $today);
    })
    ->where(function($q) use ($today){
        $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
    })
    ->where(function($q){
        $q->where('language_id', Language::version()->id)->orWhereNull('language_id');
    })
    ->orderByDesc('start_date')
    ->get();
```

#### Files Modified/Created
- `database/migrations/2025_11_03_000000_create_announcements_table.php`
- `app/Models/Web/Announcement.php`
- `app/Http/Controllers/Admin/Web/AnnouncementController.php`
- `resources/views/admin/web/announcement/` (index, create, edit)
- `resources/views/admin/layouts/inc/sidebar.blade.php` (menu entry)
- `resources/views/web/layouts/master.blade.php` (ticker display)
- `app/Http/Controllers/Web/HomeController.php` (load announcements)
- `routes/web.php` (announcement routes)
- `database/seeders/AnnouncementSeeder.php` (sample data)
- `database/seeders/AnnouncementPermissionSeeder.php` (permissions)
- `tests/Feature/AnnouncementTest.php` (test suite)

---

## Permissions

Required permissions for admin access:
- `announcement-view` - View announcements list
- `announcement-create` - Create new announcements
- `announcement-edit` - Edit existing announcements
- `announcement-delete` - Delete announcements

**Setup**: Run `php artisan db:seed --class=AnnouncementPermissionSeeder`

---

## Sample Data

To populate with sample announcements for testing:
```bash
php artisan db:seed --class=AnnouncementSeeder
```

This creates 6 sample announcements:
1. Active, no end date (always shows)
2. Time-bound (ends in 30 days)
3. Future (starts in 5 days)
4. Past (expired, won't show)
5. Inactive (draft, won't show)
6. Global (all languages)

---

## Testing

### Manual Testing
1. **Admin CRUD**:
   - Login to admin portal
   - Go to Front Web → Announcements
   - Create/Edit/Delete announcements
   - Verify permissions work

2. **Public Display**:
   - Visit homepage: `http://localhost/paxhitest/home`
   - Verify ticker appears at top
   - Hover to pause animation
   - Check mobile responsiveness

3. **Date Filtering**:
   - Create announcement with future start date
   - Verify it doesn't show yet
   - Change start date to past
   - Verify it now shows

### Automated Tests
Test file: `tests/Feature/AnnouncementTest.php`

Covers:
- ✓ Admin can view index
- ✓ Admin can create announcement
- ✓ Admin can edit announcement
- ✓ Admin can delete announcement
- ✓ Active announcements display on homepage
- ✓ Inactive announcements don't display
- ✓ Expired announcements don't display
- ✓ Future announcements don't display
- ✓ Validation rules work
- ✓ Permissions are enforced

**To run** (requires PHPUnit):
```bash
php artisan test --filter=AnnouncementTest
# or
./vendor/bin/phpunit --filter=AnnouncementTest
```

---

## Customization

### Styling
Edit in `resources/views/web/layouts/master.blade.php`:
- **Background color**: `background:#fff8e1`
- **Text color**: `color:#333`
- **Animation speed**: `animation: scroll-left 30s`
- **Mobile speed**: `animation-duration: 20s`

### Animation
Current: CSS `@keyframes` for smooth, accessible scrolling
- Pauses on hover automatically
- No deprecated HTML tags
- Mobile-responsive

### Message Formatting
TinyMCE editor allows:
- Bold, italic, underline
- Links
- Lists
- Basic HTML tags

For security, consider sanitizing HTML on save.

---

## Troubleshooting

### Announcements not showing
1. Check announcement status is "Active" (1)
2. Verify dates: start_date ≤ today ≤ end_date
3. Clear view cache: `php artisan view:clear`
4. Check browser console for CSS/JS errors

### Admin can't access
1. Verify permissions: `announcement-view`, etc.
2. Check user role has permissions
3. Run permission seeder if needed

### Ticker not animating
1. Clear browser cache
2. Check CSS loaded properly
3. Verify no JavaScript errors
4. Test in different browser

---

## Future Enhancements

Potential improvements:
- [ ] Priority/ordering system for multiple announcements
- [ ] Target specific user roles (students, staff, public)
- [ ] Dismissible announcements with cookies
- [ ] Click-through tracking
- [ ] Email/SMS notifications
- [ ] Announcement categories
- [ ] Preview before publishing
- [ ] Scheduled publishing queue
- [ ] Multi-line announcements with carousel
- [ ] Admin dashboard widget showing active announcements

---

## Security Considerations

1. **XSS Prevention**: 
   - Currently allows HTML in messages for admin flexibility
   - Consider sanitizing HTML on input
   - Or restrict to safe tags only

2. **Permissions**: 
   - All routes protected by permission middleware
   - Only authorized admins can manage announcements

3. **Validation**:
   - Message required
   - End date must be after start date
   - Status must be 0 or 1

---

## API Integration (Future)

If exposing announcements via API:

```php
// routes/api.php
Route::get('/announcements/active', function() {
    $today = now()->toDateString();
    return Announcement::where('status', 1)
        ->where(function($q) use ($today){
            $q->whereNull('start_date')->orWhere('start_date', '<=', $today);
        })
        ->where(function($q) use ($today){
            $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
        })
        ->get();
});
```

---

## Support

For questions or issues:
1. Check this documentation
2. Review code comments in controller/model
3. Check Laravel logs: `storage/logs/laravel.log`
4. Test with sample data seeder

---

**Last Updated**: November 3, 2025  
**Version**: 1.0  
**Author**: Development Team
