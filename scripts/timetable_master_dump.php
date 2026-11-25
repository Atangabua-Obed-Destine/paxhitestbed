<?php
/**
 * READ-ONLY. Writes the lists a timetable check needs to one JSON file:
 * courses (id, code, title), lecturers (id, name, staff ID: users with the
 * Teacher role), programmes, sessions, semesters, sections, rooms, and the
 * hall each programme used last first semester. Nothing is changed.
 *
 *   php scripts/timetable_master_dump.php timetable-lists.json
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$out = $argv[1] ?? 'timetable-lists.json';
$lastSession = DB::table('sessions')->where('current', 0)->orderByDesc('id')->value('id');

$data = [
    'taken_at' => now()->toDateTimeString(),
    'database' => DB::connection()->getDatabaseName(),
    'subjects' => DB::table('subjects')->get(['id', 'code', 'title', 'status']),
    'teachers' => DB::table('users')
        ->join('model_has_roles', 'model_has_roles.model_id', 'users.id')
        ->join('roles', 'roles.id', 'model_has_roles.role_id')
        ->where('roles.slug', 'teacher')->where('model_has_roles.model_type', 'App\\User')
        ->get(['users.id', 'users.first_name', 'users.last_name', 'users.staff_id', 'users.status']),
    'programs' => DB::table('programs')->get(['id', 'title', 'shortcode', 'status']),
    'sessions' => DB::table('sessions')->get(['id', 'title', 'current']),
    'semesters' => DB::table('semesters')->get(['id', 'title', 'year']),
    'sections' => DB::table('sections')->get(['id', 'title']),
    'class_rooms' => DB::table('class_rooms')->get(['id', 'title', 'status']),
    'rooms' => DB::table('class_rooms as c')->join('class_routines as r', 'r.room_id', 'c.id')
        ->where('r.session_id', $lastSession)->where('r.semester_id', 1)
        ->groupBy('r.program_id', 'c.id', 'c.title')
        ->selectRaw('r.program_id, c.id, c.title, count(*) n')->get(),
    'routines_by_session' => DB::table('class_routines')->selectRaw('session_id, semester_id, count(*) n')
        ->groupBy('session_id', 'semester_id')->get(),
];

file_put_contents($out, json_encode($data, JSON_PRETTY_PRINT));
echo "Wrote $out: " . count($data['subjects']) . ' courses, ' . count($data['teachers']) . " lecturers.\n";
