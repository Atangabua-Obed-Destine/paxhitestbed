<?php
/**
 * Course groups: one course taught to several programmes under each
 * programme's own code (AGE118H for the agric programmes, MGT1101H for
 * business, "Principles of Management").
 *
 * Checks, through the real screens where there is one, inside a transaction
 * that is rolled back:
 *   - the rules: a group needs two courses, a course is in one group only, and
 *     no programme may take two courses of a group;
 *   - the Course groups screen saves, edits and removes a group (removing only
 *     unlinks: no course, class or mark is touched);
 *   - grouped courses are the same course to the timetable clash check, the
 *     lecturer's timetable, class sessions and their joint lookups;
 *   - a joint class or joint exam for a grouped course gives each programme its
 *     own code of the group;
 *   - one mark sheet lists every programme of the group, and each mark is saved
 *     on the student's own course, refused while any course on the sheet has
 *     no mark distribution.
 *
 *   php scripts/course_groups_test.php
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ClassRoutine;
use App\Models\ClassSession;
use App\Models\CourseGroup;
use App\Models\Subject;
use App\Services\Academic\ClassTimetable;
use App\Services\Academic\CourseGroups;
use App\Support\TeacherTimetable;
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
$call = function (string $method, string $path, array $data = []) use ($kernel) {
    app('flasher.storage_manager')->clear();
    app('session.store')->forget('errors');
    if ($method !== 'GET') { $data['_token'] = csrf_token(); }
    $request = Illuminate\Http\Request::create($path, $method, $data);
    $request->setLaravelSession(app('session.store'));
    $request->headers->set('referer', 'http://localhost/admin/academic/course-group');
    CourseGroups::forget();
    return $kernel->handle($request);
};
$errors = fn () => collect(optional(session('errors'))->all())->implode(' | ');

$age = Subject::where('code', 'AGE118H')->first();   // Principles of Management, agric programmes
$mgt = Subject::where('code', 'MGT1101H')->first();  // Principles of Management, business programmes
$other = Subject::where('code', 'AGE118')->first();  // Micro-economics: an agric programme takes it beside AGE118H
if (!$age || !$mgt || !$other) { echo "  FAIL  the testbed courses AGE118H, MGT1101H and AGE118 are needed\n"; exit(1); }

DB::beginTransaction();
try {
    DB::table('subjects')->update(['course_group_id' => null]);
    DB::table('course_groups')->delete();
    CourseGroups::forget();

    echo "\n== The rules ==\n";
    check('one course is not a group', CourseGroups::check([$age->id]) !== []);
    check('AGE118H with MGT1101H may form a group', CourseGroups::check([$age->id, $mgt->id]) === [], implode(' | ', CourseGroups::check([$age->id, $mgt->id])));
    $twice = CourseGroups::check([$age->id, $other->id]);
    check('two courses one programme takes are refused', $twice !== [] && str_contains(implode(' ', $twice), 'takes both'), implode(' | ', $twice));
    check('an ungrouped course is only itself', CourseGroups::memberIds($age->id) === [$age->id] && !CourseGroups::same($age->id, $mgt->id));

    echo "\n== The Course groups screen ==\n";
    $r = $call('GET', '/admin/academic/course-group');
    check('the list opens', $r->getStatusCode() === 200, $r->getStatusCode());
    check('and suggests AGE118H with MGT1101H (same title, same level)', str_contains((string) $r->getContent(), 'AGE118H') && str_contains((string) $r->getContent(), 'Group these'));
    $r = $call('GET', '/admin/academic/course-group/create', ['subjects' => [$age->id, $mgt->id]]);
    check('the form opens with the suggested courses ticked', $r->getStatusCode() === 200 && substr_count((string) $r->getContent(), 'checked') >= 2);

    $call('POST', '/admin/academic/course-group', ['title' => 'Principles of Management', 'subjects' => [$age->id, $mgt->id, $other->id]]);
    check('a group with two courses of one programme is not saved', CourseGroup::count() === 0 && str_contains($errors(), 'takes both'), $errors());
    $call('POST', '/admin/academic/course-group', ['title' => 'Principles of Management', 'subjects' => [$age->id, $mgt->id]]);
    $g = CourseGroup::first();
    check('the group is saved with its two courses', $g && $g->subjects()->count() === 2, $errors());
    check('both courses now answer as the same course', CourseGroups::same($age->id, $mgt->id) && count(CourseGroups::memberIds($mgt->id)) === 2);
    check('each keeps its own code and credits', Subject::find($age->id)->code === 'AGE118H' && (string) Subject::find($mgt->id)->credit_hour === (string) $mgt->credit_hour);

    $second = Subject::where('code', 'ECO1101H')->first();
    $call('POST', '/admin/academic/course-group', ['title' => 'Another', 'subjects' => [$mgt->id, $second->id]]);
    check('a course already in a group cannot join another', CourseGroup::count() === 1 && str_contains($errors(), 'already in the group'), $errors());

    $call('PUT', '/admin/academic/course-group/' . $g->id, ['title' => 'Principles of Management (HND)', 'subjects' => [$age->id, $mgt->id]]);
    check('the group can be renamed', CourseGroup::find($g->id)->title === 'Principles of Management (HND)', $errors());

    echo "\n== Teaching: one lecture, not a clash ==\n";
    $base = ['teacher_id' => 14, 'room_id' => 1, 'session_id' => 5, 'semester_id' => 1, 'section_id' => 1, 'day' => 4, 'start_time' => '10:00:00', 'end_time' => '12:00:00', 'status' => 1];
    $r1 = ClassRoutine::create($base + ['program_id' => 1, 'subject_id' => $age->id]);
    $r2 = ClassRoutine::create($base + ['program_id' => 6, 'subject_id' => $mgt->id]);
    $rows = ClassRoutine::with(['subject', 'room', 'program', 'teacher'])->whereIn('id', [$r1->id, $r2->id])->get();
    $clash = (new ClassTimetable())->conflicts($rows);
    check('the clash check sees one joint lecture (no room or lecturer clash)', empty($clash), json_encode($clash));
    $tt = new TeacherTimetable(ClassRoutine::with(['subject', 'room', 'program', 'semester', 'section'])->whereIn('id', [$r1->id, $r2->id])->get());
    check('the lecturer\'s timetable shows one lecture with both codes', $tt->slots->count() === 1 && str_contains($tt->slots->first()->code, 'AGE118H') && str_contains($tt->slots->first()->code, 'MGT1101H'),
        $tt->slots->pluck('code')->implode(', '));

    DB::table('subjects')->where('id', $mgt->id)->update(['course_group_id' => null]); CourseGroups::forget();
    $clash = (new ClassTimetable())->conflicts($rows);
    check('without the group the same two classes are a double-booking', !empty($clash));
    DB::table('subjects')->where('id', $mgt->id)->update(['course_group_id' => $g->id]); CourseGroups::forget();

    $sess = fn ($p, $s) => ClassSession::create(['class_routine_id' => $p === 1 ? $r1->id : $r2->id, 'teacher_id' => 14, 'subject_id' => $s, 'program_id' => $p, 'session_id' => 5, 'semester_id' => 1, 'section_id' => 1,
        'date' => '2027-01-12', 'scheduled_start_time' => '10:00:00', 'scheduled_end_time' => '12:00:00', 'status' => ClassSession::STATUS_IN_PROGRESS]);
    try {
        $s1 = $sess(1, $age->id); $s2 = $sess(6, $mgt->id);
        $joint = ClassSession::where('teacher_id', 14)->where('date', '2027-01-12')->sameCourseAs($age->id)->where('scheduled_start_time', '10:00:00')->pluck('id')->sort()->values()->all();
        check('the joint-session lookup finds both programmes\' sessions', $joint == collect([$s1->id, $s2->id])->sort()->values()->all(), json_encode($joint));
        $s1->end();
        check('ending the class ends both programmes\' sessions', ClassSession::find($s2->id)->status !== ClassSession::STATUS_IN_PROGRESS, ClassSession::find($s2->id)->status);
        ClassSession::find($s1->id)->syncLecturerAttendance();
        ClassSession::find($s2->id)->syncLecturerAttendance();
        $hours = DB::table('staff_hourly_attendances')->where('user_id', 14)->where('date', '2027-01-12')->count();
        check('the lecturer is credited one hour for the joint lecture, not one per programme', $hours === 1, $hours . ' records');
    } catch (\Throwable $e) {
        check('class sessions can be written for the check', false, $e->getMessage());
    }

    // The class register for one lecturer's joint course covers every programme.
    $filter = App\Support\CourseClassFilter::fromRequest(Illuminate\Http\Request::create('/', 'GET', ['session' => 2, 'subject' => $age->id]));
    $register = app(App\Services\Academic\CourseRoster::class)->build($filter, $age);
    $regProgrammes = collect($register['students'])->map(fn ($s) => is_array($s) ? ($s['program_id'] ?? $s['programme'] ?? null) : ($s->program_id ?? $s->programme ?? null))->filter()->unique()->values();
    $regCount = count($register['students']);
    $expectedReg = DB::table('student_enroll_subject as ses')->join('student_enrolls as e', 'e.id', 'ses.student_enroll_id')
        ->whereIn('ses.subject_id', [$age->id, $mgt->id])->where('e.session_id', 2)->distinct()->count('e.student_id');
    check('the class register lists the students of both codes', $regCount === $expectedReg, $regCount . ' of ' . $expectedReg);

    echo "\n== Joint class and joint exam: each programme gets its own code ==\n";
    $before = ClassRoutine::count();
    $call('POST', '/admin/routine/class-routine-joint', ['programs' => [1, 6], 'session' => 5, 'semester' => 1, 'section' => 1, 'subject' => $mgt->id,
        'teacher' => 14, 'room' => 1, 'day' => 6, 'start_time' => '08:00', 'end_time' => '10:00']);
    $made = ClassRoutine::where('session_id', 5)->where('day', 6)->where('start_time', '08:00')->whereIn('program_id', [1, 6])->pluck('subject_id', 'program_id');
    check('the agric programme gets AGE118H, business gets MGT1101H', ClassRoutine::count() === $before + 2 && (int) ($made[1] ?? 0) === $age->id && (int) ($made[6] ?? 0) === $mgt->id, json_encode($made) . ' ' . $errors());

    $type = DB::table('exam_types')->where('status', 1)->orderBy('id')->value('id');
    $call('POST', '/admin/routine/exam-routine-joint', ['programs' => [1, 6], 'session' => 5, 'semester' => 1, 'section' => 1, 'subject' => $age->id, 'type' => $type,
        'date' => '2027-02-01', 'start_time' => '08:00', 'end_time' => '10:00', 'teachers' => [14], 'rooms' => [1]]);
    $exams = DB::table('exam_routines')->where('session_id', 5)->where('date', '2027-02-01')->whereIn('program_id', [1, 6])->pluck('subject_id', 'program_id');
    check('the joint exam gives each programme its own paper code', (int) ($exams[1] ?? 0) === $age->id && (int) ($exams[6] ?? 0) === $mgt->id, json_encode($exams) . ' ' . $errors());

    echo "\n== One mark sheet for the group ==\n";
    $ca = DB::table('exam_types')->where('is_final', 0)->where('status', 1)->value('id');
    // A clean continuous-assessment round for last year's two classes.
    $live = fn ($sid) => DB::table('student_enroll_subject as ses')->join('student_enrolls as e', 'e.id', 'ses.student_enroll_id')->join('students as st', 'st.id', 'e.student_id')
        ->where('ses.subject_id', $sid)->where('e.session_id', 2)->whereIn('e.status', [1, 2])->where('st.status', 1)->distinct()->pluck('e.matricule');
    $expected = $live($age->id)->merge($live($mgt->id))->unique()->count();
    DB::table('exams')->whereIn('subject_id', [$age->id, $mgt->id])->where('exam_type_id', $ca)->delete();
    $sheet = ['program' => 1, 'session' => 2, 'semester' => 1, 'section' => 1, 'subject' => $age->id, 'type' => $ca, 'cross_program' => 1];

    $html = (string) $call('GET', '/admin/exam/exam-marking', $sheet)->getContent();
    $rows = DB::table('exams')->whereIn('subject_id', [$age->id, $mgt->id])->where('exam_type_id', $ca)->get();
    check('opening the sheet makes each student\'s row on their own course', $rows->where('subject_id', $age->id)->count() > 0 && $rows->where('subject_id', $mgt->id)->count() > 0,
        $rows->groupBy('subject_id')->map->count()->toJson());
    check('the sheet says it is a course group and shows both codes', str_contains($html, 'course group') && str_contains($html, 'MGT1101H'));
    $onSheet = preg_match_all('/name="marks\[(\d+)\]"/', $html, $m) ? array_map('intval', $m[1]) : [];
    check('every student of both programmes is on the one sheet', count(array_unique($onSheet)) === $expected, count(array_unique($onSheet)) . ' of ' . $expected);

    $byCourse = DB::table('exams')->whereIn('id', $onSheet)->pluck('subject_id', 'id');
    $marks = []; foreach ($onSheet as $i => $id) { $marks[$id] = 10 + ($i % 15); }

    $cfg = DB::table('exam_type_contributions')->where('subject_id', $mgt->id)->get();
    DB::table('exam_type_contributions')->where('subject_id', $mgt->id)->delete();
    $rc = DB::table('result_contributions')->where('subject_id', $mgt->id)->get();
    DB::table('result_contributions')->where('subject_id', $mgt->id)->delete();
    $call('POST', '/admin/exam/exam-marking', $sheet + ['marks' => $marks]);
    check('saving is refused while MGT1101H has no mark distribution', DB::table('exams')->whereIn('id', $onSheet)->whereNotNull('achieve_marks')->count() === 0);
    foreach ($cfg as $c) { DB::table('exam_type_contributions')->insert((array) $c); }
    foreach ($rc as $c) { DB::table('result_contributions')->insert((array) $c); }

    $call('POST', '/admin/exam/exam-marking', $sheet + ['marks' => $marks]);
    $saved = DB::table('exams')->whereIn('id', $onSheet)->pluck('achieve_marks', 'id');
    check('one save records every mark', collect($marks)->every(fn ($v, $id) => (float) ($saved[$id] ?? -1) === (float) $v), $saved->filter(fn ($v) => $v === null)->count() . ' blank');
    check('each mark stays on the student\'s own course', DB::table('exams')->whereIn('id', $onSheet)->pluck('subject_id', 'id')->all() == $byCourse->all());
    $totals = DB::table('subject_markings as sm')->join('exams as x', function ($j) use ($ca) { $j->on('x.student_enroll_id', 'sm.student_enroll_id')->on('x.subject_id', 'sm.subject_id')->where('x.exam_type_id', $ca); })
        ->whereIn('x.id', $onSheet)->distinct()->pluck('sm.subject_id');
    check('course totals are kept per programme course (both codes have totals)', $totals->contains($age->id) && $totals->contains($mgt->id), $totals->toJson());

    echo "\n== Removing the group ==\n";
    $call('DELETE', '/admin/academic/course-group/' . $g->id);
    check('the group is gone and its courses are separate again', CourseGroup::count() === 0 && Subject::whereNotNull('course_group_id')->count() === 0);
    check('nothing else changed: the classes and marks are still there', ClassRoutine::find($r1->id) && DB::table('exams')->whereIn('id', $onSheet)->whereNotNull('achieve_marks')->count() === count($onSheet));
} catch (\Throwable $e) {
    $fail++;
    echo '  FAIL  exception: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    DB::rollBack();
}

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
