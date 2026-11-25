<?php
/**
 * Exam Schedule Overview: a semester type (First Semester, …) and any number
 * of its semesters, so one exam period's Year 1 and Year 2 papers show and
 * print together. Rolled back.
 *
 *   php scripts/exam_schedule_overview_test.php
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Semester;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$pass = 0; $fail = 0;
function check($label, $ok, $detail = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ok    $label\n"; }
    else { $fail++; echo "  FAIL  $label" . ($detail !== '' ? "   [$detail]" : '') . "\n"; }
}
app('session.store')->start();
Auth::guard('web')->login(App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->where('status', '1')->first());
$get = function (array $q) use ($kernel) {
    $r = Illuminate\Http\Request::create('/admin/routine/exam-schedule-overview', 'GET', $q);
    $r->setLaravelSession(app('session.store'));
    $res = $kernel->handle($r);
    return [$res->getStatusCode(), (string) $res->getContent()];
};

$y1 = Semester::find(1);
$y2 = Semester::where('status', 1)->get()->first(fn ($s) => $s->id !== 1 && $s->type_name === 'FIRST SEMESTER');
$second = Semester::where('status', 1)->get()->first(fn ($s) => $s->type_name === 'SECOND SEMESTER');
$type = DB::table('exam_types')->where('status', 1)->value('id');

DB::beginTransaction();
try {
    check('semester types read the same with "- Y1" and "- Year 1"', $y1->type_name === 'FIRST SEMESTER' && $y2 && $y2->type_name === 'FIRST SEMESTER'
        && (new Semester(['title' => 'FIRST SEMESTER - Year 2']))->type_name === 'FIRST SEMESTER' && (new Semester(['title' => 'FIRST RESIT SEMESTER - Y2']))->type_name === 'FIRST RESIT SEMESTER');

    DB::table('exam_routines')->where('session_id', 5)->where('exam_type_id', $type)->delete();
    $paper = function ($sem, $code, $date) use ($type) {
        return DB::table('exam_routines')->insertGetId(['program_id' => 6, 'session_id' => 5, 'semester_id' => $sem, 'section_id' => 1, 'exam_type_id' => $type,
            'subject_id' => DB::table('subjects')->where('code', $code)->value('id'), 'date' => $date, 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 1,
            'created_at' => now(), 'updated_at' => now()]);
    };
    $paper($y1->id, 'ENG1101H', '2027-02-01');
    $paper($y2->id, 'MGT1101H', '2027-02-02');
    if ($second) { $paper($second->id, 'FRE101', '2027-06-01'); }
    $count = fn ($html) => preg_match('/(\d+)\s*<\/[^>]+>\s*<[^>]+>\s*(?:Total )?Exams/i', $html, $m) ? (int) $m[1] : substr_count($html, 'ENG1101H') + substr_count($html, 'MGT1101H') + substr_count($html, 'FRE101');
    $has = fn ($html, $code) => str_contains($html, $code);

    [$st, $html] = $get(['session' => 5, 'exam_type' => $type]);
    check('the page opens with a semester type and a multi-select of semesters', $st === 200 && str_contains($html, 'name="semester_type"') && str_contains($html, 'name="semester[]"'), $st);
    check('with no semester chosen it shows the whole session', $has($html, 'ENG1101H') && $has($html, 'MGT1101H') && (!$second || $has($html, 'FRE101')));

    [$st, $html] = $get(['session' => 5, 'exam_type' => $type, 'semester_type' => 'FIRST SEMESTER', 'all' => 1]);
    check('"First Semester" shows Year 1 and Year 2 papers together', $has($html, 'ENG1101H') && $has($html, 'MGT1101H') && (!$second || !$has($html, 'FRE101')));
    check('and the printed heading names the type', str_contains($html, 'First Semester'));

    [$st, $html] = $get(['session' => 5, 'exam_type' => $type, 'semester_type' => 'FIRST SEMESTER', 'semester' => [$y2->id]]);
    check('ticking one semester narrows to it', !$has($html, 'ENG1101H') && $has($html, 'MGT1101H'));

    [$st, $html] = $get(['session' => 5, 'exam_type' => $type, 'semester' => $y1->id]);
    check('an old single-semester link still works', $st === 200 && $has($html, 'ENG1101H') && !$has($html, 'MGT1101H'));
} catch (\Throwable $e) {
    $fail++;
    echo '  FAIL  exception: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    DB::rollBack();
}
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
