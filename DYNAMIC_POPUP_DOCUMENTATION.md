# Dynamic Popups Marketing Feature Documentation

## Overview

The Dynamic Popups feature allows administrators to create, manage, and display promotional popup messages across different areas of the PAXHI Academic Management System. This includes the front website, student portal, applicant portal, admin portal, and login pages.

## Features

### 1. Popup Management (Admin)
- **Create/Edit/Delete** dynamic popups with full CRUD functionality
- **Image upload** with automatic resizing (512×280px)
- **Button customization** - configurable text, color, and link
- **Target areas** - control where popups appear (all, front web, student portal, applicant portal, admin portal, login pages)
- **Display frequency** - once per session, once per day, once ever, or always
- **Position control** - center (modal), top-left, top-right, bottom-left, bottom-right
- **Scheduling** - set start and end dates for campaigns
- **Priority system** - higher priority popups show first (0-100)
- **Pinning** - pin important popups to always show first
- **Dismissible option** - control whether users can close the popup
- **Status toggle** - activate/deactivate popups instantly
- **Bulk operations** - bulk delete functionality
- **Live preview** - see popup appearance while editing

### 2. Frontend Display
- **Session-based dismissal** - dismissed popups don't reappear in the same session
- **Queue system** - popups show one at a time, next shows after closing current
- **Smart filtering** - respects frequency settings (session, day, ever)
- **Responsive design** - works on all screen sizes
- **Smooth animations** - fade in/out with scale effects

## File Structure

```
app/
├── Models/
│   └── DynamicPopup.php                    # Eloquent model with scopes and helpers
├── Http/Controllers/Admin/
│   └── DynamicPopupController.php          # CRUD controller
│
resources/views/
├── admin/dynamic-popup/
│   ├── index.blade.php                     # List view with filters
│   ├── create.blade.php                    # Create form with live preview
│   └── edit.blade.php                      # Edit form with live preview
├── components/
│   └── dynamic-popup.blade.php             # Frontend popup component
│
database/migrations/
└── 2026_01_06_000001_create_dynamic_popups_table.php
│
routes/
├── web.php                                 # Admin routes (Marketing section)
└── api.php                                 # API routes for popup fetching
```

## Database Schema

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| title | varchar(100) | Popup title |
| summary | text | Popup description/body text |
| image | varchar(255) | Image filename |
| button_text | varchar(50) | CTA button text |
| button_color | varchar(10) | Button background color (hex) |
| button_text_color | enum | 'light' or 'dark' |
| link | varchar(500) | Button link URL |
| target_areas | json | Array of target areas |
| display_frequency | enum | 'once_session', 'once_day', 'once_ever', 'always' |
| popup_position | enum | 'center', 'top-left', 'top-right', 'bottom-left', 'bottom-right' |
| start_date | datetime | When popup becomes active |
| end_date | datetime | When popup expires |
| priority | tinyint | 0-100, higher shows first |
| is_dismissible | boolean | Can users close it? |
| is_pinned | boolean | Pin to top of queue |
| status | boolean | Active/Inactive |
| created_by | bigint | FK to users |
| updated_by | bigint | FK to users |
| timestamps | - | created_at, updated_at |

## Routes

### Admin Routes (requires auth)
```
GET    /admin/marketing/dynamic-popup              # List all popups
GET    /admin/marketing/dynamic-popup/create       # Create form
POST   /admin/marketing/dynamic-popup              # Store new popup
GET    /admin/marketing/dynamic-popup/{id}         # Show popup details
GET    /admin/marketing/dynamic-popup/{id}/edit    # Edit form
PUT    /admin/marketing/dynamic-popup/{id}         # Update popup
DELETE /admin/marketing/dynamic-popup/{id}         # Delete popup
POST   /admin/marketing/dynamic-popup/{id}/toggle-status   # Toggle active/inactive
POST   /admin/marketing/dynamic-popup/{id}/toggle-pinned   # Toggle pinned status
POST   /admin/marketing/dynamic-popup/bulk-delete  # Bulk delete
```

### API Routes (public)
```
GET    /api/popups/{area}        # Get active popups for a specific area
POST   /api/popups/{id}/dismiss  # Mark popup as dismissed (session)
```

## Permissions

The feature uses the following permissions:
- `dynamic-popup-view` - View popup list
- `dynamic-popup-create` - Create new popups
- `dynamic-popup-edit` - Edit existing popups
- `dynamic-popup-delete` - Delete popups

Run the permissions script to assign these to the Admin role:
```
http://localhost/paxhitestbed/assign_dynamic_popup_permissions.php
```

## Target Areas

| Area Code | Description |
|-----------|-------------|
| all | Shows everywhere |
| front_web | Public website pages |
| student_portal | Student dashboard |
| applicant_portal | Application form |
| admin_portal | Admin dashboard |
| login_pages | All login screens |

## Display Frequency Options

| Frequency | Description |
|-----------|-------------|
| once_session | Show once per browser session |
| once_day | Show once per calendar day |
| once_ever | Show once per browser (localStorage) |
| always | Show every page load |

## Usage

### Including in Layouts

The popup component is already included in:
- `resources/views/web/layouts/master.blade.php` (front_web)
- `resources/views/student/layouts/master.blade.php` (student_portal)
- `resources/views/admin/layouts/master.blade.php` (admin_portal)
- `resources/views/auth/layouts/master.blade.php` (login_pages)
- `resources/views/application/apply.blade.php` (applicant_portal)

To include in other layouts:
```blade
@include('components.dynamic-popup', ['area' => 'front_web'])
```

### Creating a Popup (Admin)

1. Navigate to **Marketing > Dynamic Popups**
2. Click **Add New**
3. Fill in the form:
   - Title (required)
   - Summary (optional)
   - Upload image (optional, 512×280px recommended)
   - Configure button (optional)
   - Select target areas
   - Choose display frequency
   - Set scheduling (optional)
   - Set priority (0-100)
   - Enable/disable options
4. Preview in the sidebar
5. Click **Save Popup**

### Best Practices

1. **Image sizing**: Use 512×280px images for best results
2. **Title length**: Keep under 50 characters
3. **Summary length**: Keep under 200 characters
4. **Button text**: Keep concise (2-4 words)
5. **Priority**: Use 50+ for important campaigns
6. **Frequency**: Use "once_session" for most promotions
7. **Dismissible**: Always enable unless critical announcement
8. **Scheduling**: Set end dates for time-limited campaigns

## Technical Notes

- Images are stored in `public/uploads/dynamic-popups/`
- Images are automatically resized using Intervention Image
- Popup dismissal state is stored in browser sessionStorage (session) and localStorage (day/ever)
- The frontend component uses vanilla JavaScript for broad compatibility
- The API endpoint returns popups sorted by priority and pinned status
