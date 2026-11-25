<?php
/**
 * The student dashboard (redesign) and the programme switcher: programmes a
 * student has left (every enrolment inactive) and inactive programmes are not
 * offered, cannot be switched to, and an old selection pointing at one is
 * dropped. Rolled back.
 *
 *   php scripts/student_dashboard_switch_test.php
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\StudentEnroll;
use App\Support\EnrolmentOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$pass = 0; $fail = 0;
function check($label, $ok, $detail = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ok    $label\n"; }
    else { $fail++; echo "  FAIL  $label" . ($detail !== '' ? "   [$detail]" : '') . "\n"; }
}
$http = function (string $guard, $who, string $method, string $uri, array $data = [], array $session = []) use ($kernel) {
    app()->forgetScopedInstances();
    Auth::guard('web')->logout(); Auth::guard('student')->logout();
    app('session.store')->flush();
    app('session.store')->start();
    foreach ($session as $k => $v) { app('session.store')->put($k, $v); }
    Auth::guard($guard)->login($who);
    $data['_token'] = app('session.store')->token();
    $r = Illuminate\Http\Request::create($uri, $method, $data);
    $r->setLaravelSession(app('session.store'));
    $res = $kernel->handle($r);
    return [$res->getStatusCode(), (string) $res->getContent(), $res];
};

DB::beginTransaction();
try {
    // A student who may use the portal (platform fee settled), with courses.
    $svc = app(App\Services\PlatformFeeAccess::class);
    // ...and who actually reaches the dashboard (fees and first instalment in order).
    $student = null;
    foreach (App\Models\Student::where('status', '1')->whereHas('currentEnroll')->orderByDesc('id')->get()->filter(fn ($s) => $svc->grantsAccess($s)) as $candidate) {
        [$st] = $http('student', $candidate, 'GET', '/student/dashboard');
        if ($st === 200) { $student = $candidate; break; }
    }
    if (!$student) { throw new RuntimeException('No student on this database can reach the dashboard.'); }
    $active = StudentEnroll::where('student_id', $student->id)->where('status', '<>', '0')->orderByDesc('id')->first();

    echo "\n=== the switch rule ===\n";
    // A second programme the student was moved off: every enrolment inactive.
    $otherProgram = App\Models\Program::where('id', '<>', $active->program_id)->where('status', '1')->first();
    $left = $active->replicate();
    $left->program_id = $otherProgram->id; $left->matricule = 'TESTLEFT001'; $left->status = '0'; $left->save();
    $all = StudentEnroll::where('student_id', $student->id)->with('program')->get();
    $offered = EnrolmentOrder::switchable($all);
    check('a programme the student left is not offered', !$offered->contains('matricule', 'TESTLEFT001'));
    check('their active programme is offered', $offered->contains('matricule', $active->matricule));
    check('it cannot be switched to', !EnrolmentOrder::canSwitchTo($left, $all));
    check('their active programme can', EnrolmentOrder::canSwitchTo($active, $all));

    // A second programme they are active on, but the programme itself is inactive.
    $third = App\Models\Program::where('id', '<>', $active->program_id)->where('id', '<>', $otherProgram->id)->where('status', '1')->first();
    $closedProg = $active->replicate();
    $closedProg->program_id = $third->id; $closedProg->matricule = 'TESTCLOSED01'; $closedProg->status = '1'; $closedProg->save();
    DB::table('programs')->where('id', $third->id)->update(['status' => '0']);
    $all = StudentEnroll::where('student_id', $student->id)->with('program')->get();
    check('an inactive programme is not offered', !EnrolmentOrder::switchable($all)->contains('matricule', 'TESTCLOSED01'));
    DB::table('programs')->where('id', $third->id)->update(['status' => '1']);
    $all = StudentEnroll::where('student_id', $student->id)->with('program')->get();
    check('once active again, it is offered', EnrolmentOrder::switchable($all)->contains('matricule', 'TESTCLOSED01'));
    DB::table('programs')->where('id', $third->id)->update(['status' => '0']);

    $only = collect([$left->fresh('program')]);
    check('a record with nothing active still gets a programme (no empty page)', EnrolmentOrder::switchable($only)->count() === 1);

    echo "\n=== through the portal ===\n";
    [$st, $html] = $http('student', $student, 'GET', '/student/select-program');
    check('the choose-a-programme page does not list the programme they left', !str_contains($html, 'TESTLEFT001') && !str_contains($html, 'TESTCLOSED01'), $st);
    [$st, $html, $res] = $http('student', $student, 'POST', '/student/switch-program', ['enrollment_id' => $left->id], ['selected_enrollment_id' => $active->id]);
    check('switching to it is refused', app('session.store')->get('selected_enrollment_id') == $active->id, 'session=' . app('session.store')->get('selected_enrollment_id'));
    [$st, , $res] = $http('student', $student, 'POST', '/student/switch-program', ['enrollment_id' => $left->id], ['selected_enrollment_id' => $active->id]);
    check('and says so (not a silent success)', in_array($st, [302, 403], true), $st);
    [$st, $html] = $http('student', $student, 'GET', '/student/dashboard', [], ['selected_enrollment_id' => $left->id]);
    $sel = app('session.store')->get('selected_enrollment_id');
    check('an old selection on a programme they left is dropped', $sel != $left->id, 'session=' . $sel);

    echo "\n=== the dashboard ===\n";
    // The fake enrolments would make the platform-fee check look at them.
    StudentEnroll::whereIn('id', [$left->id, $closedProg->id])->delete();
    // A class on today's timetable for the student's class.
    $today = App\Http\Controllers\Student\ClassRoutineController::todayNumber();
    $subject = App\Models\Subject::where('status', '1')->first();
    DB::table('class_routines')->insert(['teacher_id' => App\User::where('status', '1')->value('id'), 'subject_id' => $subject->id, 'room_id' => DB::table('class_rooms')->value('id') ?? 1,
        'session_id' => $active->session_id, 'program_id' => $active->program_id, 'semester_id' => $active->semester_id, 'section_id' => $active->section_id,
        'start_time' => '23:58:00', 'end_time' => '23:59:00', 'day' => $today, 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
    [$st, $html, $res] = $http('student', $student, 'GET', '/student/dashboard', [], ['selected_enrollment_id' => $active->id]);
    if ($st === 302) { echo '        (redirect to ' . $res->headers->get('Location') . ' for student ' . $student->id . ")
"; }
    check('the dashboard opens', $st === 200, $st . ' ' . ($st === 302 ? $http_last_location = '' : ''));
    check('it is the new design', str_contains($html, 'et-mast') && str_contains($html, 'Your studies at a glance'));
    check('today\'s classes now show (the old query never matched a day)', str_contains($html, $subject->code) && str_contains($html, 'Your classes today'));
    check('it has the Messages panel', str_contains($html, 'Open the mailbox'));
    check('the old month calendar is gone', !str_contains($html, "id='calendar'") && !str_contains($html, 'id="calendar"'));
    check('the course overview is there', str_contains($html, 'Your courses, semester by semester'));

    $admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->where('status', '1')->first();
    [$st, $html] = $http('web', $admin, 'GET', '/admin');
    check('the staff dashboard opens with its Messages panel', $st === 200 && str_contains($html, 'Open the mailbox') && str_contains($html, 'et-mast'), $st);
} catch (\Throwable $e) {
    $fail++;
    echo '  FAIL  exception: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    DB::rollBack();
}
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
