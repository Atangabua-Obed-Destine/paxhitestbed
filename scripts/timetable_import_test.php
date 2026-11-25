<?php
/**
 * timetable:import — a class timetable from a CSV, previewed before it saves.
 *
 * Rolled back. Checks: the preview writes nothing; --apply creates one class
 * per programme; a grouped course gives each programme its own code; a
 * programme that does not take the course is left out and named; unknown
 * names are left out; a slot the programme already uses is not doubled; a
 * second run adds nothing; a lecturer booked in two places is reported.
 *
 *   php scripts/timetable_import_test.php
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ClassRoutine;
use App\Models\CourseGroup;
use App\Models\Subject;
use App\Services\Academic\CourseGroups;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$pass = 0; $fail = 0;
function check($label, $ok, $detail = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ok    $label\n"; }
    else { $fail++; echo "  FAIL  $label" . ($detail !== '' ? "   [$detail]" : '') . "\n"; }
}
$csv = function (array $rows) {
    $f = tempnam(sys_get_temp_dir(), 'tt') . '.csv';
    $h = fopen($f, 'w');
    fwrite($h, "\xEF\xBB\xBF");   // Excel's byte-order mark must not break the header
    fputcsv($h, ['semester', 'programmes', 'day', 'start', 'end', 'course', 'lecturer', 'room', 'source']);
    foreach ($rows as $r) { fputcsv($h, $r); }
    fclose($h);
    return $f;
};
$run = function (string $file, bool $apply) {
    $code = Artisan::call('timetable:import', ['file' => $file, '--session' => '2026/2027', '--section' => 'All'] + ($apply ? ['--apply' => true] : []));
    return [$code, Artisan::output()];
};
$sem = DB::table('semesters')->where('id', 1)->value('title');
$at = fn ($p, $day, $start) => ClassRoutine::where('session_id', 5)->where('program_id', $p)->where('semester_id', 1)->where('section_id', 1)->where('day', $day)->where('start_time', $start)->first();

DB::beginTransaction();
try {
    DB::table('subjects')->update(['course_group_id' => null]);
    DB::table('course_groups')->delete();
    $age = Subject::where('code', 'AGE118H')->first(); $mgt = Subject::where('code', 'MGT1101H')->first();
    $g = CourseGroup::create(['title' => 'Principles of Management', 'status' => 1]);
    CourseGroups::setMembers($g, [$age->id, $mgt->id]);
    // A clean week for the test: Monday is free in 2026/2027 for both programmes.
    ClassRoutine::where('session_id', 5)->whereIn('program_id', [1, 6])->where('day', 3)->delete();
    $before = ClassRoutine::count();

    $file = $csv([
        [$sem, 'HND - CPT;HND - ACC', 'Monday', '08:00', '10:00', 'AGE118H', 'PAX0013', 'SMBS HALL', 'Principles of Management, joint'],
        [$sem, 'HND - CPT;HND - ACC', 'Monday', '10:00', '12:00', 'ENG1101H', 'PAX0010', 'SMBS HALL', 'one code shared by both'],
        [$sem, 'HND - CPT', 'Monday', '13:00', '15:00', 'ACC1101H', 'PAX0012', 'SCEDT HALL', 'CPT does not take this'],
        [$sem, 'HND - ACC', 'Monday', '13:00', '15:00', 'NOPE999', 'PAX0012', 'SMBS HALL', 'no such course'],
        [$sem, 'HND - ACC', 'Monday', '15:00', '17:00', 'ACC1101H', 'PAX9999', 'SMBS HALL', 'no such lecturer'],
        [$sem, 'HND - ACC', 'Monday', '08:00', '10:00', 'MKT1101H', 'PAX0012', 'SMBS HALL', 'ACC already has a class then'],
        [$sem, 'HND - CPT', 'Monday', '15:00', '17:00', 'AGE113', 'PAX0013', 'SCEDT HALL', 'lecturer clash below'],
        [$sem, 'HND - ACC', 'Monday', '15:00', '17:00', 'MKT1101H', 'PAX0013', 'SMBS HALL', 'same lecturer, other course, same time'],
    ]);

    echo "\n== Preview ==\n";
    [$exit, $out] = $run($file, false);
    check('the preview runs', $exit === 0, trim(substr($out, -300)));
    check('and writes nothing', ClassRoutine::count() === $before);
    check('it says how many classes it would add', str_contains($out, '6 classes to add'), trim(substr($out, 0, 900)));
    check('it names the programme that does not take the course', str_contains($out, 'HND - CPT does not take ACC1101H'));
    check('it names what it cannot find', str_contains($out, 'course "NOPE999"') && str_contains($out, 'lecturer "PAX9999"'));
    check('it will not put two classes in one slot', str_contains($out, 'HND - ACC already has'));
    check('it reports the lecturer booked in two places', str_contains($out, 'Clashes') && str_contains($out, 'MKT1101H'), trim(substr($out, -600)));

    echo "\n== Apply ==\n";
    [$exit, $out] = $run($file, true);
    check('--apply saves the classes', $exit === 0 && ClassRoutine::count() === $before + 6, (ClassRoutine::count() - $before) . ' added');
    check('the grouped course: CPT gets AGE118H, ACC gets MGT1101H', optional($at(1, 3, '08:00:00'))->subject_id == $age->id && optional($at(6, 3, '08:00:00'))->subject_id == $mgt->id);
    check('a shared code stays the same for both', optional($at(1, 3, '10:00:00'))->subject_id == optional($at(6, 3, '10:00:00'))->subject_id);
    check('lecturer, room and times are saved', ($r = $at(6, 3, '08:00:00')) && $r->teacher_id == 14 && DB::table('class_rooms')->where('id', $r->room_id)->value('title') === 'SMBS HALL' && $r->end_time === '10:00:00');
    check('the joint lecture is no clash to the timetable check', !collect((new App\Services\Academic\ClassTimetable())->conflicts(ClassRoutine::with(['subject', 'room', 'program', 'teacher'])->where('session_id', 5)->get()))
        ->only([$at(1, 3, '08:00:00')->id, $at(6, 3, '08:00:00')->id])->flatten(1)->whereIn('type', ['room', 'lecturer'])->count());

    echo "\n== Again ==\n";
    [$exit, $out] = $run($file, true);
    check('a second run adds nothing', ClassRoutine::count() === $before + 6 && str_contains($out, '0 classes to add') && str_contains($out, '6 already there'), trim(substr($out, 0, 400)));
} catch (\Throwable $e) {
    $fail++;
    echo '  FAIL  exception: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    DB::rollBack();
}
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
