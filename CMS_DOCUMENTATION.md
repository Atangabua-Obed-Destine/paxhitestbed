# Programs & Faculties CMS Documentation

## Overview
A comprehensive Content Management System for managing Programs and Faculties on the PAX Higher Institute website.

## Admin Panel Access

### Programs Management
**URL:** `http://localhost/paxhitest/admin/academic/program`

**Features:**
- ✅ Create/Edit/Delete Programs
- ✅ Upload Featured Image & Banner Image
- ✅ Add Rich Text Descriptions (TinyMCE Editor)
- ✅ Manage Program Details (Duration, Credits, Requirements, Career Prospects)
- ✅ Link Programs to Faculties

### Faculties Management
**URL:** `http://localhost/paxhitest/admin/academic/faculty`

**Features:**
- ✅ Create/Edit/Delete Faculties
- ✅ Upload Featured Image, Banner Image & Dean Photo
- ✅ Add Rich Text Descriptions (TinyMCE Editor)
- ✅ Manage Dean Information (Name, Photo, Email, Phone)
- ✅ Add Contact Details & Website URL

## Frontend Display

### Programs Pages
- **List Page:** `http://localhost/paxhitest/programs`
- **Single Page:** `http://localhost/paxhitest/programs/{program-slug}`

### Faculties Pages
- **List Page:** `http://localhost/paxhitest/faculties`
- **Single Page:** `http://localhost/paxhitest/faculties/{faculty-slug}`

## Form Structure

### Programs Form (Tabbed Interface)

#### Tab 1: Basic Information
- Faculty (Required) - Dropdown selection
- Title (Required) - Program name
- Shortcode (Required) - Program code (e.g., "CS101")
- Duration - e.g., "3 Years", "4 Semesters"
- Credits - e.g., "120 Credits"

#### Tab 2: Content
- **Excerpt** - Short summary (plain text, ~150 words)
- **Full Description** - Detailed program information (WYSIWYG editor)
- **Entry Requirements** - Admission requirements (WYSIWYG editor)
- **Career Prospects** - Career opportunities for graduates (WYSIWYG editor)

#### Tab 3: Images
- **Featured Image** - Recommended: 800x600px (appears on program cards & detail page)
- **Banner Image** - Recommended: 1920x500px (header banner on detail page)

### Faculties Form (Tabbed Interface)

#### Tab 1: Basic Information
- Title (Required) - Faculty name
- Shortcode - Faculty code
- Status - Active/Inactive

#### Tab 2: Content
- **Excerpt** - Short summary (plain text)
- **Full Description** - Detailed faculty information (WYSIWYG editor)

#### Tab 3: Dean Information
- Dean Name - e.g., "Prof. John Doe"
- Email - Dean's email address
- Phone - Contact phone number
- Website URL - Faculty/Department website

#### Tab 4: Images
- **Featured Image** - Recommended: 800x600px (appears on faculty cards)
- **Banner Image** - Recommended: 1920x500px (header banner on detail page)
- **Dean Photo** - Recommended: 400x400px (dean's profile photo)

## Image Storage Locations

### Programs
- **Featured & Banner Images:** `public/uploads/programs/`
- Naming: `{timestamp}_featured_{original_name}` or `{timestamp}_banner_{original_name}`

### Faculties
- **Featured & Banner Images:** `public/uploads/faculties/`
- **Dean Photos:** `public/uploads/faculties/deans/`
- Naming: `{timestamp}_featured/banner/dean_{original_name}`

## Database Tables

### Programs Table
New fields added:
- `description` (TEXT) - Full HTML description
- `excerpt` (TEXT) - Short plain text summary
- `featured_image` (VARCHAR) - Featured image filename
- `banner_image` (VARCHAR) - Banner image filename
- `duration` (VARCHAR) - Program duration
- `credit` (VARCHAR) - Credits/points
- `requirements` (TEXT) - Entry requirements HTML
- `career_prospects` (TEXT) - Career information HTML

### Faculties Table
New fields added:
- `description` (TEXT) - Full HTML description
- `excerpt` (TEXT) - Short plain text summary
- `featured_image` (VARCHAR) - Featured image filename
- `banner_image` (VARCHAR) - Banner image filename
- `dean_name` (VARCHAR) - Dean's full name
- `dean_photo` (VARCHAR) - Dean's photo filename
- `email` (VARCHAR) - Faculty email
- `phone` (VARCHAR) - Contact phone
- `website` (VARCHAR) - Faculty website URL

## Technical Implementation

### Models Updated
- `app/Models/Program.php` - Added new fillable fields
- `app/Models/Faculty.php` - Added new fillable fields

### Controllers Updated
- `app/Http/Controllers/Admin/ProgramController.php` - Image upload handling, validation
- `app/Http/Controllers/Admin/FacultyController.php` - Image upload handling, validation
- `app/Http/Controllers/Web/HomeController.php` - Added programDetail() and facultyDetail() methods

### Views Created
- `resources/views/web/program-single.blade.php` - Program detail page
- `resources/views/web/faculty-single.blade.php` - Faculty detail page

### Views Updated
- `resources/views/admin/program/index.blade.php` - Full CMS form with tabs
- `resources/views/admin/program/edit.blade.php` - Edit modal with tabs
- `resources/views/admin/faculty/index.blade.php` - Full CMS form with tabs
- `resources/views/admin/faculty/edit.blade.php` - Edit modal with tabs
- `resources/views/web/programs.blade.php` - Fixed "View Details" button (now works!)

### Routes Added
```php
// Program Single Page
Route::get('/programs/{slug}', 'HomeController@programDetail')->name('program.single');

// Faculty Single Page
Route::get('/faculties/{slug}', 'HomeController@facultyDetail')->name('faculty.single');
```

## Usage Instructions

### Adding a New Program

1. Go to `http://localhost/paxhitest/admin/academic/program`
2. Fill in the **Basic Information** tab:
   - Select Faculty
   - Enter Title (e.g., "Computer Science")
   - Enter Shortcode (e.g., "CS")
   - Add Duration and Credits
3. Go to **Content** tab:
   - Write a short Excerpt
   - Add full Description using the rich text editor
   - List Entry Requirements
   - Describe Career Prospects
4. Go to **Images** tab:
   - Upload Featured Image (800x600px recommended)
   - Upload Banner Image (1920x500px recommended)
5. Click "Save"
6. View on frontend: `http://localhost/paxhitest/programs`
7. Click "View Details" to see the full page

### Adding a New Faculty

1. Go to `http://localhost/paxhitest/admin/academic/faculty`
2. Fill in the **Basic Information** tab:
   - Enter Faculty Title
   - Add Shortcode (optional)
3. Go to **Content** tab:
   - Write a short Excerpt
   - Add full Description
4. Go to **Dean Information** tab:
   - Add Dean Name
   - Enter Contact Email and Phone
   - Add Website URL (if available)
5. Go to **Images** tab:
   - Upload Featured Image
   - Upload Banner Image
   - Upload Dean Photo (400x400px recommended)
6. Click "Save"
7. View on frontend: `http://localhost/paxhitest/faculties`

## Features

### Frontend Program Detail Page
- ✅ Banner image with program title overlay
- ✅ Featured image display
- ✅ Overview/excerpt alert box
- ✅ Full description section
- ✅ Entry requirements card
- ✅ Career prospects card
- ✅ Sidebar with program info (faculty, duration, credits, code)
- ✅ "Apply Now" CTA button
- ✅ Contact info card
- ✅ Related programs list (from same faculty)

### Frontend Faculty Detail Page
- ✅ Banner image with faculty title overlay
- ✅ Featured image display
- ✅ Faculty description
- ✅ Dean information card with photo and contact details
- ✅ Programs offered in the faculty (card grid)
- ✅ Sidebar with faculty info and quick links
- ✅ "View Admissions" CTA button
- ✅ Contact card with email and phone

## Notes

- All images are validated for type (jpeg, png, jpg, gif) and size (max 2MB)
- Old images are automatically deleted when uploading new ones
- TinyMCE editor provides rich text formatting for descriptions
- All fields except Title (and Faculty for programs) are optional
- Slugs are automatically generated from titles
- Programs are linked to faculties (belongs to relationship)
- Faculties show count of associated programs

## Troubleshooting

### "View Details" Returns 404
✅ **FIXED** - Changed route name from 'course.single' to 'program.single'

### Images Not Uploading
- Check directory permissions on `public/uploads/programs/` and `public/uploads/faculties/`
- Verify file size is under 2MB
- Ensure file is one of: jpeg, png, jpg, gif

### TinyMCE Not Loading
- Check that `resources/views/admin/layouts/common/footer_script.blade.php` includes TinyMCE CDN
- Verify `.texteditor` class is applied to textareas

### Tab Navigation Not Working
- Ensure Bootstrap 5 JS is loaded
- Check that `data-bs-toggle="tab"` attributes are present

## Status: ✅ COMPLETE

All CMS functionality for Programs and Faculties is now fully implemented and ready to use!
