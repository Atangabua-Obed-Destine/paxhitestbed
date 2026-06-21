<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
|--------------------------------------------------------------------------
| Class Session Presence Channel
|--------------------------------------------------------------------------
|
| Students can join a class session channel to participate in real-time
| chat and receive alerts. Authorization checks if the student is enrolled
| in a subject that has this class session.
|
*/
Broadcast::channel('class-session.{classSessionId}', function ($user, $classSessionId) {
    // Check if user is authenticated as student
    if (!auth('student')->check()) {
        return false;
    }
    
    $student = auth('student')->user();
    $enrollmentId = session('student_enrollment_id');
    
    if (!$enrollmentId) {
        return false;
    }
    
    // Get the class session
    $classSession = \App\Models\ClassSession::find($classSessionId);
    
    if (!$classSession) {
        return false;
    }
    
    // Get the student's enrollment to check section
    $enrollment = \App\Models\StudentEnroll::with('student', 'section')->find($enrollmentId);
    
    if (!$enrollment) {
        return false;
    }
    
    // Check if class session's subject is assigned to student's section
    // (or if it's linked via program semester)
    $isEnrolled = \App\Models\ClassSession::where('id', $classSessionId)
        ->where(function($q) use ($enrollment) {
            // Check if student's section is linked to this class session's subject
            $q->where('section_id', $enrollment->section_id)
              ->orWhereHas('subject', function($sq) use ($enrollment) {
                  $sq->whereHas('enrolledSubjects', function($esq) use ($enrollment) {
                      $esq->where('student_enroll_id', $enrollment->id);
                  });
              });
        })
        ->exists();
    
    if ($isEnrolled) {
        // Return user data for presence channel
        return [
            'id' => $enrollmentId,
            'name' => $enrollment->student->first_name . ' ' . $enrollment->student->last_name,
            'photo' => $enrollment->student->photo,
        ];
    }
    
    return false;
});
