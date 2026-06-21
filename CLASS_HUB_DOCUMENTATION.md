# Student Class Hub Feature Documentation

## Overview

The Student Class Hub is a comprehensive real-time class interaction system that allows students to engage with live class sessions, take notes, receive alerts, and communicate with peers during classes.

## Features

### For Students

1. **Today's Classes Dashboard**
   - View all scheduled classes for the current day
   - See live class indicators with pulse animation
   - Quick access to join live class rooms
   - View clock-in status for each class

2. **Live Class Room**
   - Real-time chat with classmates (WebSocket-powered)
   - Personal note-taking with auto-save
   - Important alerts/reminders section
   - Class information display (topic, duration, lecturer)
   - Presence list showing who's online

3. **Class History**
   - Browse all past class sessions
   - Filter by subject, date range, and status
   - Search by topic or subject name
   - View attendance status for each session

4. **My Notes**
   - View all personal notes across all classes
   - Filter by subject
   - Search within notes
   - Export notes to text file
   - Copy individual notes
   - Delete notes

### For Administrators/HOD

1. **Class Hub Reports Dashboard**
   - Overview statistics (sessions, messages, alerts, notes)
   - Top sessions by engagement
   - Top subjects by chat activity
   - Alert type distribution
   - Recent engagement activity

2. **Session Detail Reports**
   - Detailed engagement metrics per session
   - Top contributors
   - Message activity timeline chart
   - Pinned messages
   - All alerts and questions

3. **Export Functionality**
   - Export engagement data to CSV

## Database Schema

### New Tables Created

1. **class_session_messages**
   - Stores real-time chat messages
   - Fields: id, class_session_id, student_enroll_id, message, message_type, is_pinned, is_deleted, created_at, updated_at, deleted_at

2. **student_class_notes**
   - Stores personal notes per student per class
   - Fields: id, class_session_id, student_enroll_id, content, is_synced, created_at, updated_at

3. **class_session_alerts**
   - Stores important alerts/reminders flagged by students
   - Fields: id, class_session_id, created_by_enroll_id, title, description, alert_type, is_pinned, upvote_count, created_at, updated_at

4. **class_session_alert_upvotes**
   - Tracks upvotes on alerts
   - Fields: id, alert_id, student_enroll_id, created_at

5. **class_session_questions**
   - Stores questions submitted to lecturer
   - Fields: id, class_session_id, student_enroll_id, question, answer, is_anonymous, is_answered, answered_by, answered_at, created_at, updated_at

### Modified Tables

- **class_sessions**: Added `is_chat_enabled`, `last_message_at`, `message_count` columns

## File Structure

### Controllers

- `app/Http/Controllers/Student/ClassHubController.php` - Main student controller
- `app/Http/Controllers/Admin/ClassHubReportController.php` - Admin reports controller

### Models

- `app/Models/StudentClassNote.php`
- `app/Models/ClassSessionMessage.php`
- `app/Models/ClassSessionAlert.php`
- `app/Models/ClassSessionAlertUpvote.php`
- `app/Models/ClassSessionQuestion.php`

### Views

**Student Views:**
- `resources/views/student/class-hub/index.blade.php` - Today's classes
- `resources/views/student/class-hub/live-room.blade.php` - Live class room
- `resources/views/student/class-hub/history.blade.php` - Class history
- `resources/views/student/class-hub/my-notes.blade.php` - Personal notes
- `resources/views/student/class-hub/session-details.blade.php` - Session details

**Admin Views:**
- `resources/views/admin/class-hub-reports/index.blade.php` - Reports dashboard
- `resources/views/admin/class-hub-reports/session-detail.blade.php` - Session detail report

### Events (Broadcasting)

- `app/Events/ClassSessionMessageSent.php` - Real-time message event
- `app/Events/ClassSessionAlertCreated.php` - Real-time alert event

### Routes

**Student Routes (`routes/web.php`):**
```
student/class-hub/                    - Today's classes
student/class-hub/live-room/{id}      - Join live room
student/class-hub/history             - Class history
student/class-hub/my-notes            - My notes
student/class-hub/session/{id}        - Session details
student/class-hub/export-notes        - Export notes
student/class-hub/session/{id}/message - Send message (POST)
student/class-hub/session/{id}/messages - Get messages
student/class-hub/session/{id}/note   - Save note (POST)
student/class-hub/notes/{id}          - Delete note (DELETE)
student/class-hub/session/{id}/alert  - Create alert (POST)
student/class-hub/alert/{id}/upvote   - Upvote alert (POST)
student/class-hub/session/{id}/question - Submit question (POST)
```

**Admin Routes:**
```
admin/class-hub-reports               - Reports dashboard
admin/class-hub-reports/session/{id}  - Session detail
admin/class-hub-reports/export        - Export data
```

## Permissions

| Permission | Description |
|------------|-------------|
| class-hub-reports-view | View Class Hub engagement reports |
| class-hub-reports-export | Export Class Hub report data |

## Real-time Features (WebSocket)

The live chat uses Laravel Echo with Pusher for real-time communication:

1. **Presence Channel**: `class-session.{id}` - Shows online students
2. **Message Broadcasting**: Messages are broadcast to all channel members
3. **Alert Broadcasting**: New alerts are broadcast in real-time

### Configuration Required

Ensure your `.env` file has Pusher credentials:
```
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=your_cluster
```

## Menu Locations

### Student Sidebar
- **Class Hub** (with submenu)
  - Today's Classes
  - Class History
  - My Notes

### Admin Sidebar
- **Students → Attendance → Class Hub Reports**

## Dashboard Widget

The student dashboard (`student/dashboard/index.blade.php`) now includes:
- Live class indicator when classes are in progress
- Quick "Join Class Hub" button for live classes

## Setup Instructions

1. **Run Migration**
   ```bash
   php artisan migrate
   ```

2. **Run Permissions Script**
   ```bash
   php assign_class_hub_permissions.php
   ```

3. **Clear Caches**
   ```bash
   php artisan route:clear
   php artisan view:clear
   php artisan cache:clear
   ```

4. **Configure Broadcasting** (for real-time features)
   - Set up Pusher or another broadcasting driver
   - Install Laravel Echo in frontend

## Usage Flow

### Student Flow

1. Student logs in and sees dashboard with live class indicator
2. Clicks "Join Class Hub" or navigates via sidebar
3. On Today's Classes page, sees all scheduled classes
4. Clicks "Enter Live Room" for active class
5. In live room:
   - Participates in real-time chat
   - Takes personal notes (auto-saved)
   - Creates/upvotes important alerts
   - Views class information
6. After class, accesses notes via "My Notes"
7. Reviews class history via "Class History"

### Admin/HOD Flow

1. Navigates to Students → Attendance → Class Hub Reports
2. Views engagement statistics across all sessions
3. Filters by session, semester, program, date range
4. Clicks on specific session for detailed analysis
5. Exports data for further analysis

## Technical Notes

1. **Auto-save Notes**: Notes are automatically saved every 30 seconds and on manual save
2. **Message Polling**: When WebSocket is unavailable, falls back to polling every 5 seconds
3. **Presence Updates**: Student presence is tracked via presence channels
4. **Soft Deletes**: Messages use soft deletes for moderation
5. **Upvote Tracking**: Prevents duplicate upvotes per student

## Future Enhancements

1. File/image sharing in chat
2. Screen sharing integration
3. Poll/quiz creation by lecturers
4. Voice messages
5. AI-powered chat moderation
6. Automatic note summarization
7. Integration with video conferencing
