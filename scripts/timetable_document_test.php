<?php
/**
 * Timetable breaks and the printed timetables. Rolled back.
 *
 * - The printout follows the school's own timetable: one sheet per specialty
 *   (programmes with the same week together), days down the side, time slots
 *   across, the midday break as one cell down every day, Friday's club
 *   activities in Friday's row, a two-slot class spanning both columns.
 * - Breaks show on the class timetable, the per-class print and the student's
 *   week, only on days with lessons; the student can print their own sheet.
 *
 *   php scripts/timetable_document_test.php
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ClassRoutine;
use App\Models\CourseGroup;
use App\Models\Semester;
use App\Models\Session;
use App\Models\Subject;
use App\Models\TimetableBreak;
use App\Services\Academic\CourseGroups;
use App\Services\Academic\TimetableDocument;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$pass = 0; $fail = 0;
function check($label, $ok, $detail = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ok    $label\n"; }
    else { $fail++; echo "  FAIL  $label" . ($detail !== '' ? "   [$detail]" : '') . "\n"; }
}
app('session.store')->start();
$get = function (string $guard, $user, string $method, string $path, array $data = []) use ($kernel) {
    Auth::guard($guard)->login($user);
    if ($method !== 'GET') { $data['_token'] = csrf_token(); }
    $r = Illuminate\Http\Request::create($path, $method, $data);
    $r->setLaravelSession(app('session.store'));
    $res = $kernel->handle($r);
    return [$res->getStatusCode(), (string) $res->getContent(), $res->headers->get('location')];
};
$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->where('status', '1')->first();

DB::beginTransaction();
try {
    DB::table('timetable_breaks')->delete();
    DB::table('subjects')->update(['course_group_id' => null]); DB::table('course_groups')->delete();
    $g = CourseGroup::create(['title' => 'Principles of Management', 'status' => 1]);
    $age = Subject::where('code', 'AGE118H')->first(); $mgt = Subject::where('code', 'MGT1101H')->first();
    CourseGroups::setMembers($g, [$age->id, $mgt->id]);

    TimetableBreak::create(['title' => 'Break (Holy Mass)', 'start_time' => '12:00', 'end_time' => '13:00', 'days' => null, 'status' => 1]);
    TimetableBreak::create(['title' => 'Club Activities (Compulsory)', 'start_time' => '16:00', 'end_time' => '17:00', 'days' => [7], 'status' => 1]);

    // A clean 2026/2027 first semester: ACC and MKT share a week, CPT has its own.
    ClassRoutine::where('session_id', 5)->where('semester_id', 1)->delete();
    $add = fn ($p, $code, $day, $s, $e) => ClassRoutine::create(['teacher_id' => 14, 'subject_id' => Subject::where('code', $code)->value('id'), 'room_id' => 1,
        'session_id' => 5, 'program_id' => $p, 'semester_id' => 1, 'section_id' => 1, 'day' => $day, 'start_time' => "$s:00", 'end_time' => "$e:00", 'status' => 1]);
    foreach ([6, 7] as $p) {
        $add($p, 'MGT1101H', 4, '08:00', '10:00'); $add($p, 'ENG1101H', 4, '10:00', '12:00');
        $add($p, 'FRE101', 4, '15:00', '17:00'); $add($p, 'ECO1102H', 7, '08:00', '10:00'); $add($p, 'INS1101H', 7, '13:00', '16:00');
    }
    $add(1, 'AGE118H', 4, '08:00', '10:00'); $add(1, 'AGE113', 5, '10:00', '12:00'); $add(1, 'AGE114', 7, '13:00', '15:00');

    echo "\n== The printed timetable ==\n";
    $sheets = (new TimetableDocument())->sheets(Session::find(5), Semester::find(1));
    $spec = collect($sheets)->pluck('specialty');
    check('programmes with the same week share one sheet', count($sheets) === 2 && $spec->contains(fn ($s) => str_contains($s, 'ACCOUNTANCY') && str_contains($s, 'MARKETING')), $spec->implode(' | '));
    $acc = collect($sheets)->first(fn ($s) => str_contains($s['specialty'], 'ACCOUNTANCY'));
    check('headed as the school writes it', $acc['heading'] === 'TIME TABLE YEAR I' && $acc['programme_line'] === 'HIGHER NATIONAL DIPLOMA (HND) PROGRAMME' && str_contains($acc['semester_line'], '2026/2027 ACADEMIC YEAR'), json_encode([$acc['heading'], $acc['semester_line']]));
    check('days down the side, Tuesday then Friday', array_column($acc['rows'], 'day') === ['Tuesday', 'Friday'], json_encode(array_column($acc['rows'], 'day')));
    check('time slots across, the break among them', array_map(fn ($c) => $c['start'] . '-' . $c['end'], $acc['columns']) === ['08:00-10:00', '10:00-12:00', '12:00-13:00', '13:00-15:00', '15:00-16:00', '16:00-17:00'],
        json_encode(array_map(fn ($c) => $c['start'] . '-' . $c['end'], $acc['columns'])));
    $tall = collect($acc['rows'][0]['cells'])->firstWhere('type', 'tall-break');
    check('the midday break is one cell down every day', $tall && $tall['rowspan'] === 2 && $tall['title'] === 'Break (Holy Mass)' && !collect($acc['rows'][1]['cells'])->contains('type', 'tall-break'));
    $fri = collect($acc['rows'][1]['cells']);
    check('club activities sit in Friday\'s 4–5 slot', $fri->contains(fn ($c) => $c['type'] === 'break' && str_contains($c['title'], 'Club')));
    $french = collect($acc['rows'][0]['cells'])->first(fn ($c) => $c['type'] === 'class' && $c['items'][0]->codes === ['FRE101']);
    check('a 3–5 class spans both slots', $french && $french['colspan'] === 2);
    $ins = $fri->first(fn ($c) => $c['type'] === 'class' && $c['items'][0]->codes === ['INS1101H']);
    check('a 1–4 class spans 1–3 and 3–4', $ins && $ins['colspan'] === 2);
    check('every row fills the week exactly', collect($acc['rows'])->every(function ($row, $i) use ($acc) {
        $span = collect($row['cells'])->sum('colspan') + ($i > 0 ? 1 : 0);   // the tall break belongs to the first row
        return $span === count($acc['columns']);
    }));
    $one = (new TimetableDocument())->sheets(Session::find(5), Semester::find(1), 1);
    check('asked for one programme, it prints that programme\'s sheet', count($one) === 1 && str_contains($one[0]['specialty'], 'CROP'));

    [$st, $html] = $get('web', $admin, 'GET', '/admin/routine/class-routine-document', ['session' => 5, 'semester' => 1]);
    check('the print page opens', $st === 200, $st);
    check('and shows both sheets with their breaks', substr_count($html, 'class="sheet"') === 2 && str_contains($html, 'Break (Holy Mass)') && str_contains($html, 'SPECIALTY'));
    [$st, $html] = $get('web', $admin, 'GET', '/admin/routine/class-routine-document');
    check('without a semester it asks for one', $st === 200 && str_contains($html, 'Choose a session and a semester'));

    echo "\n== Breaks on the other timetables ==\n";
    [$st, $html] = $get('web', $admin, 'GET', '/admin/routine/class-routine', ['program' => 6, 'session' => 5, 'semester' => 1, 'section' => 1]);
    check('the class timetable shows the break row', $st === 200 && str_contains($html, 'ctt-break') && str_contains($html, 'Break (Holy Mass)'), $st);
    [$st, $html] = $get('web', $admin, 'POST', '/admin/routine/class-routine/print', ['program' => 6, 'session' => 5, 'semester' => 1, 'section' => 1]);
    check('the per-class print shows it too', $st === 200 && str_contains($html, 'class="brk"'), $st);
    [$st, $html] = $get('web', $admin, 'GET', '/admin/routine/timetable-break');
    check('the breaks screen lists both', $st === 200 && str_contains($html, 'Club Activities') && str_contains($html, 'Friday'));
    $get('web', $admin, 'POST', '/admin/routine/timetable-break', ['title' => 'Assembly', 'start_time' => '07:30', 'end_time' => '08:00', 'days' => [3]]);
    check('a break can be added', TimetableBreak::where('title', 'Assembly')->exists());

    // The student side: a student of ACC in 2026/2027, first semester.
    $enroll = DB::table('student_enrolls')->where('program_id', 6)->whereIn('status', [1, 2])->orderByDesc('id')->first();
    if ($enroll) {
        DB::table('student_enrolls')->where('id', $enroll->id)->update(['session_id' => 5, 'semester_id' => 1, 'section_id' => 1]);
        $student = App\Models\Student::find($enroll->student_id);
        session(['selected_enrollment_id' => $enroll->id]);
        DB::table('settings')->exists();
        [$st, $html, $loc] = $get('student', $student, 'GET', '/student/class-routine');
        if ($st === 302) {
            echo "  skip  the student pages (this student is sent to $loc first)\n";
        } else {
            check('the student sees the break between classes', $st === 200 && str_contains($html, 'tt-break') && str_contains($html, 'Break (Holy Mass)'), $st);
            check('but not on a day with no lessons (Monday)', !preg_match('/id="tt-panel-3".{0,3000}Break \(Holy Mass\)/s', $html) || str_contains($html, 'No classes on this day'));
            check('and can print their timetable', str_contains($html, 'Print my timetable'));
            [$st, $html] = $get('student', $student, 'GET', '/student/class-routine/document');
            check('their printout is their specialty\'s sheet', $st === 200 && substr_count($html, 'class="sheet"') === 1 && str_contains($html, 'ACCOUNTANCY'), $st);
        }
    }
} catch (\Throwable $e) {
    $fail++;
    echo '  FAIL  exception: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    DB::rollBack();
}
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
