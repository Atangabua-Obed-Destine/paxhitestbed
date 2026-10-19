<?php
/**
 * The live-classroom feature: the lecturer kiosk and the student class hub.
 *
 * Writes happen inside transactions that are rolled back; the last section
 * proves the database was left as it was found.
 *
 * The check that matters most is "the register tells the truth". Attendance
 * taken at the kiosk writes through to student_attendances - the same table
 * /admin/student-attendance and every report read. A student who sat through
 * the whole class and walked out without scanning was being recorded ABSENT
 * there, overwriting the Present written when they scanned in. Everything else
 * in this suite is about who may act; that one is about whether the register
 * means anything at all.
 *
 * Run: php scripts/class_session_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ClassSession;
use App\Models\StudentAttendance;
use App\Models\StudentClassAttendance;
use App\Models\StudentEnroll;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;
$skipped = 0;

function section(string $title): void
{
    echo "\n" . $title . "\n" . str_repeat('-', strlen($title)) . "\n";
}

function check(string $what, bool $ok, string $why = ''): void
{
    global $passed, $failed;

    if ($ok) {
        $passed++;
        echo "  PASS  " . $what . "\n";
    } else {
        $failed++;
        echo "  FAIL  " . $what . ($why ? "\n        " . $why : '') . "\n";
    }
}

function skip(string $what): void
{
    global $skipped;
    $skipped++;
    echo "  SKIP  " . $what . "\n";
}

$root = dirname(__DIR__);

// A session with a student attached, to drive the lifecycle on.
$subject = ClassSession::with('studentAttendances')
    ->whereHas('studentAttendances')
    ->orderByDesc('id')
    ->first();

if (!$subject) {
    echo "no class session with attendance rows to work from\n";
    exit(1);
}

$lecturer = User::find($subject->teacher_id);

if (!$lecturer) {
    echo "the subject session has no lecturer\n";
    exit(1);
}

echo "subject: session #{$subject->id}, lecturer #{$lecturer->id}, "
    . $subject->studentAttendances->count() . " student(s)\n";

// ---------------------------------------------------------------------------
section('The register tells the truth');
// ---------------------------------------------------------------------------
// Start a class, clock a student in, end the class without them scanning out.
// They attended; the register must say so.
DB::beginTransaction();

try {
    $session = ClassSession::find($subject->id);
    $session->update([
        'status' => ClassSession::STATUS_IN_PROGRESS,
        'actual_start_time' => now(),
        'actual_end_time' => null,
    ]);

    $attendance = $session->studentAttendances()->first();
    $attendance->update([
        'clock_in_time' => null,
        'clock_out_time' => null,
        'has_clocked_out' => false,
        'status' => StudentClassAttendance::STATUS_ABSENT,
    ]);

    // They scan in.
    $attendance->refresh()->clockIn('test', '127.0.0.1');

    $onClockIn = StudentAttendance::where('student_enroll_id', $attendance->student_enroll_id)
        ->where('subject_id', $session->subject_id)
        ->where('date', $session->date)
        ->first();

    check('scanning in records the student present',
        $onClockIn && (int) $onClockIn->attendance === 1,
        $onClockIn ? 'attendance = ' . $onClockIn->attendance : 'no register row was written');

    // The class ends. They never scanned out - most students do not.
    $session->refresh()->end(now());

    $afterEnd = StudentAttendance::where('student_enroll_id', $attendance->student_enroll_id)
        ->where('subject_id', $session->subject_id)
        ->where('date', $session->date)
        ->first();

    check('and ending the class leaves them present, not absent',
        $afterEnd && (int) $afterEnd->attendance === 1,
        'attendance = ' . ($afterEnd->attendance ?? 'none') . ', note "'
            . ($afterEnd->note ?? '') . '" - a student who attended must not be '
            . 'recorded absent because they did not scan out');

    $finalRow = StudentClassAttendance::find($attendance->id);

    check('the class record shows them clocked out, not incomplete',
        $finalRow && $finalRow->has_clocked_out
        && $finalRow->status !== StudentClassAttendance::STATUS_INCOMPLETE,
        'status ' . ($finalRow->status ?? '?') . ', has_clocked_out '
            . var_export($finalRow->has_clocked_out ?? null, true));

    // A genuine absentee must still be recorded absent.
    $never = $session->studentAttendances()->whereNull('clock_in_time')->first();

    if (!$never) {
        skip('no never-clocked-in student on this session to check absence with');
    } else {
        $absentRow = StudentAttendance::where('student_enroll_id', $never->student_enroll_id)
            ->where('subject_id', $session->subject_id)
            ->where('date', $session->date)
            ->first();

        check('someone who never came is still recorded absent',
            $absentRow && (int) $absentRow->attendance === 2,
            'attendance = ' . ($absentRow->attendance ?? 'none'));
    }
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('A lecturer can only act on their own class');
// ---------------------------------------------------------------------------
$other = User::where('id', '!=', $lecturer->id)
    ->whereHas('roles')
    ->get()
    ->first(fn ($u) => $u->can('class-session-create'));

$post = function (string $path, array $body, User $as) use ($kernel) {
    app('session.store')->start();
    Auth::guard('web')->login($as);

    $request = Request::create($path, 'POST', array_merge($body, ['_token' => csrf_token()]));
    $request->setLaravelSession(app('session.store'));

    try {
        return $kernel->handle($request);
    } catch (\Throwable $e) {
        return null;
    }
};

$get = function (string $path, User $as) use ($kernel) {
    app('session.store')->start();
    Auth::guard('web')->login($as);

    $request = Request::create($path, 'GET');
    $request->setLaravelSession(app('session.store'));

    try {
        return $kernel->handle($request);
    } catch (\Throwable $e) {
        return null;
    }
};

if (!$other) {
    skip('no second user holding class-session-create to test against');
} else {
    DB::beginTransaction();

    try {
        $session = ClassSession::find($subject->id);
        $session->update(['status' => ClassSession::STATUS_IN_PROGRESS, 'actual_start_time' => now()]);
        $attendance = $session->studentAttendances()->first();
        $attendance->update(['clock_in_time' => now(), 'has_clocked_out' => false]);

        $before = $session->fresh()->status;

        // The one that matters: ending someone else's class.
        $response = $post('/admin/class-session/approve-early-clockout', [
            'attendance_id' => $attendance->id,
            'approval_type' => 'end_class',
        ], $other);

        check('another lecturer cannot end this class through the clock-out approval',
            $response && $response->getStatusCode() === 403,
            'status ' . ($response ? $response->getStatusCode() : 'exception'));

        check('and the class is still running',
            ClassSession::find($session->id)->status === $before,
            'status became ' . ClassSession::find($session->id)->status);

        $response = $post('/admin/class-session/scan', [
            'session_id' => $session->id,
            'student_id' => $attendance->matricule,
        ], $other);

        check('another lecturer cannot scan students into this class',
            $response && $response->getStatusCode() === 403,
            'status ' . ($response ? $response->getStatusCode() : 'exception'));

        foreach (['students', 'stats', 'activity'] as $endpoint) {
            $response = $get('/admin/class-session/' . $session->id . '/' . $endpoint, $other);

            check('another lecturer cannot read this class\'s ' . $endpoint,
                $response && $response->getStatusCode() === 403,
                'status ' . ($response ? $response->getStatusCode() : 'exception'));
        }
    } finally {
        DB::rollBack();
    }
}

// Somebody with no class-session permission at all.
$outsider = User::get()->first(fn ($u) => !$u->can('class-session-create') && !$u->can('class-session-view'));

if (!$outsider) {
    skip('no staff user without class-session permissions to test against');
} else {
    DB::beginTransaction();

    try {
        $session = ClassSession::find($subject->id);
        $session->update(['status' => ClassSession::STATUS_IN_PROGRESS, 'actual_start_time' => now()]);
        $attendance = $session->studentAttendances()->first();

        $response = $post('/admin/class-session/approve-early-clockout', [
            'attendance_id' => $attendance->id,
            'approval_type' => 'end_class',
        ], $outsider);

        check('a staff account with no class-session permission is refused outright',
            $response && in_array($response->getStatusCode(), [401, 403], true),
            'status ' . ($response ? $response->getStatusCode() : 'exception')
                . ' - this endpoint carried no permission at all');

        $response = $get('/admin/class-session/' . $session->id . '/activity', $outsider);

        check('and cannot read the class chat and questioners',
            $response && in_array($response->getStatusCode(), [401, 403], true),
            'status ' . ($response ? $response->getStatusCode() : 'exception'));
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('Every kiosk action is behind a permission');
// ---------------------------------------------------------------------------
$controller = (string) file_get_contents($root . '/app/Http/Controllers/Admin/ClassSessionController.php');

preg_match_all('/public function (\w+)\s*\(/', $controller, $methods);
preg_match_all("/'only' => \[([^\]]*)\]/", $controller, $lists);

$gated = [];

foreach ($lists[1] as $list) {
    foreach (explode(',', $list) as $one) {
        $one = trim($one, " '\"\n\r\t");
        if ($one !== '') {
            $gated[$one] = true;
        }
    }
}

// Routed actions only - helpers and the constructor are not endpoints.
$routed = [];

foreach (explode("\n", (string) file_get_contents($root . '/routes/web.php')) as $line) {
    if (preg_match("/ClassSessionController@(\w+)/", $line, $m)) {
        $routed[$m[1]] = true;
    }
}

$ungated = array_values(array_diff(array_keys($routed), array_keys($gated)));

check('no routed kiosk action is left without a permission',
    $ungated === [],
    'ungated: ' . implode(', ', $ungated));

// ---------------------------------------------------------------------------
section('A student can only reach their own class');
// ---------------------------------------------------------------------------
$hub = (string) file_get_contents($root . '/app/Http/Controllers/Student/ClassHubController.php');

check('there is one shared access rule rather than a copy per action',
    substr_count($hub, 'private function studentMayAccess') === 1);

foreach (['sendMessage', 'saveNote', 'createAlert', 'submitQuestion',
          'sessionDetails', 'getMessages', 'upvoteAlert', 'liveRoom'] as $method) {
    $start = strpos($hub, 'public function ' . $method . '(');

    if ($start === false) {
        skip($method . ' not found');
        continue;
    }

    $body = substr($hub, $start, 2600);

    check($method . ' checks the session belongs to the student',
        str_contains($body, 'studentMayAccess'));
}

// And prove it on a real request.
$student = App\Models\Student::whereHas('studentEnrolls')->get()
    ->first(fn ($s) => $s->studentEnrolls->isNotEmpty());

if (!$student) {
    skip('no enrolled student to test the hub as');
} else {
    $enrollment = $student->studentEnrolls->first();

    // A session that is definitely not this student's class.
    $foreign = ClassSession::where('program_id', '!=', $enrollment->program_id)->first();

    if (!$foreign) {
        skip('no session on another programme to test access against');
    } else {
        app('session.store')->start();
        Auth::guard('student')->login($student);
        session(['selected_enrollment_id' => $enrollment->id]);

        $request = Request::create(
            '/student/class-hub/session/' . $foreign->id . '/message',
            'POST',
            ['_token' => csrf_token(), 'session_id' => $foreign->id, 'message' => 'probe']
        );
        $request->setLaravelSession(app('session.store'));

        $before = DB::table('class_session_messages')->where('class_session_id', $foreign->id)->count();

        try {
            $response = $kernel->handle($request);
            $status = $response->getStatusCode();
        } catch (\Throwable $e) {
            $status = 0;
        }

        $after = DB::table('class_session_messages')->where('class_session_id', $foreign->id)->count();

        check('a student cannot post into another programme\'s class',
            $status === 403 || $status === 302,
            'status ' . $status);

        check('and nothing was written',
            $after === $before,
            $before . ' -> ' . $after);
    }
}

// ---------------------------------------------------------------------------
section('Student text cannot execute on the kiosk');
// ---------------------------------------------------------------------------
// The XSS middleware is a whitelist strip_tags that permits <img>, so an
// onerror payload survives input filtering; these pages render polled content
// through innerHTML, where it would run in the lecturer's session.
$kioskView = (string) file_get_contents($root . '/resources/views/admin/class-session/kiosk.blade.php');
$liveRoom = (string) file_get_contents($root . '/resources/views/student/class-hub/live-room.blade.php');

check('the kiosk has an escape helper', str_contains($kioskView, 'function escapeText('));
check('and the live room has one', str_contains($liveRoom, 'function escapeText('));

foreach ([
    'the chat message' => '${escapeText(msg.message)}',
    'the question text' => '${escapeText(q.question)}',
    'the alert title' => '${escapeText(a.title)}',
    'the student name in the roster' => '${escapeText(student.name)}',
] as $what => $needle) {
    check('the kiosk escapes ' . $what, str_contains($kioskView, $needle));
}

check('the live room escapes the chat message',
    str_contains($liveRoom, '${escapeText(msg.message)}'));

check('no question text is interpolated into an inline handler',
    !str_contains($kioskView, 'selectQuestionToAnswer(' . '$' . '{q.id}'),
    'a double quote or backslash broke out of that attribute');

// ---------------------------------------------------------------------------
section('The fee gate is a choice, and the lecturer can see it');
// ---------------------------------------------------------------------------
check('the setting exists',
    DB::table('attendance_settings')->where('key', 'class_hub_requires_fees')->exists());

check('and it defaults to the behaviour that shipped',
    (string) App\Models\AttendanceSetting::getValue('class_hub_requires_fees', '1') === '1',
    'unpaid students stay locked out unless the school says otherwise');

$routeMiddleware = collect(app('router')->getRoutes()->getRoutes())
    ->first(fn ($r) => $r->getName() === 'student.class-hub.index');

check('the class hub runs through the gate',
    $routeMiddleware && in_array('class.hub.fees', $routeMiddleware->gatherMiddleware())
        || ($routeMiddleware && collect($routeMiddleware->gatherMiddleware())
            ->contains(fn ($m) => str_contains((string) $m, 'ClassHubFeeGate'))),
    $routeMiddleware ? implode(', ', $routeMiddleware->gatherMiddleware()) : 'route not found');

check('and not through the unconditional fee middlewares any more',
    $routeMiddleware && !collect($routeMiddleware->gatherMiddleware())
        ->contains(fn ($m) => str_contains((string) $m, 'CheckFirstInstallment')));

$kioskController = (string) file_get_contents($root . '/app/Http/Controllers/Admin/ClassSessionController.php');

check('the roster tells the lecturer who the portal is blocking',
    substr_count($kioskController, 'portal_blocked_by_fees') === 2,
    'found ' . substr_count($kioskController, 'portal_blocked_by_fees')
        . ' - getStudents has two payload builders and both need it');

check('and the kiosk shows it',
    str_contains($kioskView, 'portal_blocked_by_fees'));

// ---------------------------------------------------------------------------
section('A session left open overnight still gets closed');
// ---------------------------------------------------------------------------
$command = (string) file_get_contents($root . '/app/Console/Commands/EndExpiredClassSessions.php');

check('the nightly command looks at earlier days too',
    str_contains($command, "where('date', '<=', \$dateString)"),
    'an exact date match let an overnight session fall out of scope for ever');

$stale = ClassSession::where('status', ClassSession::STATUS_IN_PROGRESS)
    ->where('date', '<', now()->format('Y-m-d'))
    ->count();

if ($stale > 0) {
    echo "  WARN  " . $stale . " session(s) are still open from an earlier day; "
        . "run class-sessions:end-expired to close them\n";
}

// ---------------------------------------------------------------------------
section('Starting a class is all-or-nothing');
// ---------------------------------------------------------------------------
$startBody = substr($kioskController, strpos($kioskController, 'public function startSession'), 4000);

check('startSession opens a transaction',
    str_contains($startBody, 'DB::beginTransaction()'));

check('and the guards return before it opens',
    strpos($startBody, 'Unauthorized to start this session') < strpos($startBody, 'DB::beginTransaction()'),
    'an early return inside a transaction leaves it open on the connection');

check('and it no longer hands the exception text to the browser',
    !str_contains($startBody, "'Failed to start session: ' . \$e->getMessage()"));

// ---------------------------------------------------------------------------
section('Nothing was left behind');
// ---------------------------------------------------------------------------
$final = ClassSession::find($subject->id);

check('the subject session is as it was found',
    $final->status === $subject->status,
    $subject->status . ' -> ' . $final->status);

check('its attendance rows are as they were found',
    $final->studentAttendances()->count() === $subject->studentAttendances->count());

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";

exit($failed > 0 ? 1 : 0);
