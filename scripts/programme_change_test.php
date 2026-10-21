<?php
/**
 * Moving a student to another programme, and putting their fees right.
 *
 * Writes happen inside transactions that are rolled back; the last section
 * proves the database was left as it was found.
 *
 * The check that matters most is that a fee already paid against is never
 * reduced. Everything else here is about tidiness; that one is about money the
 * school has already banked. A student who moves to a cheaper programme after
 * paying keeps the charge they agreed to - they are not refunded by a dropdown.
 *
 * Run: php scripts/programme_change_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Fee;
use App\Models\Program;
use App\Models\ProgramSemesterFee;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Services\Academic\ProgrammeChange;
use App\Services\FeePaymentState;
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

$service = app(ProgrammeChange::class);

// Two programmes that are both configured, so comparisons are meaningful.
$configured = ProgramSemesterFee::where('status', 1)->distinct()->pluck('program_id');

// A student who may actually be moved under the school's rule: first semester
// of year one, nothing published anywhere. Picking any enrolment would make
// every case below fail on the eligibility rule instead of the one being
// tested.
$subject = StudentEnroll::with(['student', 'semester'])
    ->whereIn('program_id', $configured)
    ->whereHas('fees')
    ->whereHas('semester', fn ($q) => $q->where('semester_type', 1)->where('year', 1))
    ->get()
    ->first(function ($enrolment) {
        return App\Models\SubjectMarking::where('workflow_state', 'published')
            ->whereHas('studentEnroll', fn ($q) => $q->where('student_id', $enrolment->student_id))
            ->doesntExist();
    });

if (!$subject) {
    echo "no enrolment on a configured programme with fees\n";
    exit(1);
}

// Inside the same faculty: a programme change may not cross faculties, so a
// cross-faculty target would make every money case below fail for the wrong
// reason.
$subjectFaculty = optional(Program::find($subject->program_id))->faculty_id;

$targetId = $configured->first(function ($id) use ($subject, $subjectFaculty) {
    return (int) $id !== (int) $subject->program_id
        && (int) optional(Program::find($id))->faculty_id === (int) $subjectFaculty;
});

if (!$targetId) {
    echo "only one configured programme; nothing to move to\n";
    exit(1);
}

printf("subject: enrolment #%d, student %s, %s -> %s\n",
    $subject->id,
    optional($subject->student)->student_id,
    optional(Program::find($subject->program_id))->title,
    optional(Program::find($targetId))->title);

// ---------------------------------------------------------------------------
section('A fee already paid against is never reduced');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    $fee = $subject->fees()->first();
    $semesterId = $subject->semester_id;

    // Make the fee expensive and paid, and the target programme cheap.
    $fee->update(['fee_amount' => 200000, 'paid_amount' => 120000, 'status' => 2]);

    $target = ProgramSemesterFee::firstOrNew([
        'program_id' => $targetId,
        'semester_id' => $semesterId,
        'fees_category_id' => $fee->category_id,
    ]);
    $target->amount = 90000;
    $target->status = 1;
    $target->save();

    $plan = $service->feePlan($subject->fresh(), (int) $targetId);
    $line = $plan->first(fn ($l) => $l['fee'] && (int) $l['fee']->id === (int) $fee->id);

    check('the cheaper programme does not lower a paid fee',
        $line && $line['action'] === 'keep' && abs($line['new'] - 200000) < 0.005,
        $line ? 'action ' . $line['action'] . ', new ' . $line['new'] : 'no line for the fee');

    $service->apply($subject->fresh(), (int) $targetId, 'test: cheaper programme', null);

    $after = Fee::find($fee->id);

    check('and after the move the amount still stands',
        abs((float) $after->fee_amount - 200000) < 0.005,
        'fee_amount is ' . $after->fee_amount);

    check('and the payment is untouched',
        abs((float) $after->paid_amount - 120000) < 0.005,
        'paid_amount is ' . $after->paid_amount);
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('A dearer programme raises the fee to match');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    $fee = $subject->fees()->first();
    $fee->update(['fee_amount' => 100000, 'paid_amount' => 50000, 'status' => 2]);

    $target = ProgramSemesterFee::firstOrNew([
        'program_id' => $targetId,
        'semester_id' => $subject->semester_id,
        'fees_category_id' => $fee->category_id,
    ]);
    $target->amount = 175000;
    $target->status = 1;
    $target->save();

    $expected = 175000.0;

    $result = $service->apply($subject->fresh(), (int) $targetId, 'test: dearer programme', null);
    $after = Fee::find($fee->id);

    check('the move went ahead', $result['moved'] === true,
        implode('; ', $result['blockers']));

    check('the fee is raised to exactly the new programme\'s figure',
        abs((float) $after->fee_amount - $expected) < 0.005,
        'fee_amount is ' . $after->fee_amount . ', expected ' . $expected);

    check('and what was already paid is still recorded',
        (float) $after->paid_amount >= 50000,
        'paid_amount is ' . $after->paid_amount);

    check('the fee no longer reads as settled',
        (int) $after->status !== 1,
        'status ' . $after->status . ' - a fee raised above what was paid must show money owing');
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('An untouched fee is simply replaced, either way');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    $fee = $subject->fees()->first();

    // Genuinely untouched: no payment, no receipt, no credit.
    $fee->update(['fee_amount' => 180000, 'paid_amount' => 0, 'status' => 0]);
    $fee->paymentReceipts()->delete();

    $fresh = Fee::find($fee->id);

    check('the fee counts as untouched', FeePaymentState::isUntouched($fresh),
        implode(' ', FeePaymentState::evidence($fresh)));

    $target = ProgramSemesterFee::firstOrNew([
        'program_id' => $targetId,
        'semester_id' => $subject->semester_id,
        'fees_category_id' => $fee->category_id,
    ]);
    $target->amount = 95000;   // cheaper, which a PAID fee would refuse
    $target->status = 1;
    $target->save();

    $plan = $service->feePlan($subject->fresh(), (int) $targetId);
    $line = $plan->first(fn ($l) => $l['fee'] && (int) $l['fee']->id === (int) $fee->id);

    check('an untouched fee is replaced rather than kept',
        $line && $line['action'] === 'replace',
        $line ? 'action ' . $line['action'] : 'no line');

    $service->apply($subject->fresh(), (int) $targetId, 'test: untouched, cheaper', null);

    check('and it takes the new programme\'s lower figure',
        abs((float) Fee::find($fee->id)->fee_amount - 95000) < 0.005,
        'fee_amount is ' . Fee::find($fee->id)->fee_amount
            . ' - before any money moves a fee is just a number');
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('A fee the new programme charges and the student lacks is raised');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    // Make a category genuinely missing: take one of the student's own fees
    // away inside this rolled-back transaction, so the new programme charges
    // something they have no fee for.
    $victim = $subject->fees()->first();
    $spare = $victim ? DB::table('fees_categories')->where('id', $victim->category_id)->first() : null;

    if ($spare) {
        $victim->paymentReceipts()->delete();
        $victim->delete();
    }

    if (!$spare) {
        skip('the student has no fee to make missing');
    } else {
        $target = ProgramSemesterFee::firstOrNew([
            'program_id' => $targetId,
            'semester_id' => $subject->semester_id,
            'fees_category_id' => $spare->id,
        ]);
        $target->amount = 64000;
        $target->status = 1;
        $target->save();

        $before = Fee::whereHas('studentEnroll',
            fn ($q) => $q->where('student_id', $subject->student_id))->count();

        $plan = $service->feePlan($subject->fresh(), (int) $targetId);
        $added = $plan->where('action', 'add');

        check('the preview says it would be added', $added->isNotEmpty());

        $service->apply($subject->fresh(), (int) $targetId, 'test: missing fee', null);

        $raised = Fee::where('student_enroll_id', $subject->id)
            ->where('category_id', $spare->id)
            ->first();

        check('and it is raised at the new programme\'s amount',
            $raised && abs((float) $raised->fee_amount - 64000) < 0.005,
            $raised ? 'amount ' . $raised->fee_amount : 'no fee was raised');

        check('exactly one fee was added',
            Fee::whereHas('studentEnroll',
                fn ($q) => $q->where('student_id', $subject->student_id))->count() === $before + 1,
            'a duplicate charge is the hazard here');
    }
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('Both instalments are found, across enrolments');
// ---------------------------------------------------------------------------
// 62 of 73 students have their first and second instalment on different
// enrolment rows, so a per-enrolment scan finds one and misses the other.
$split = DB::select("
    SELECT se.student_id, COUNT(DISTINCT f.student_enroll_id) enrolls
    FROM fees f
    JOIN student_enrolls se ON se.id = f.student_enroll_id
    JOIN fees_categories fc ON fc.id = f.category_id
    WHERE fc.is_first_installment = 1 OR fc.is_second_installment = 1
    GROUP BY se.student_id HAVING enrolls > 1 LIMIT 1");

if (!$split) {
    skip('no student whose instalments sit on more than one enrolment');
} else {
    $enrol = StudentEnroll::where('student_id', $split[0]->student_id)
        ->whereIn('program_id', $configured)
        ->first();

    if (!$enrol) {
        skip('that student is not on a configured programme');
    } else {
        $found = $service->feesOf($enrol);

        $expected = Fee::whereHas('studentEnroll', function ($q) use ($enrol) {
            $q->where('student_id', $enrol->student_id)->where('program_id', $enrol->program_id);
        })->where('status', '!=', '3')->count();

        check('every fee of the programme is collected, not just this enrolment\'s',
            $found->count() === $expected,
            'found ' . $found->count() . ', expected ' . $expected);

        check('and they span more than one enrolment',
            $found->pluck('student_enroll_id')->unique()->count() > 1
            || $expected <= 1,
            'the whole reason the scan is by student');
    }
}

// ---------------------------------------------------------------------------
section('The move refuses what it should');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    // A course the target programme does not teach.
    $taught = DB::table('program_subject')->where('program_id', $targetId)->pluck('subject_id');
    $stranger = DB::table('subjects')->whereNotIn('id', $taught->all() ?: [0])->first();

    if (!$stranger) {
        skip('every subject is taught by the target programme');
    } else {
        $subject->subjects()->syncWithoutDetaching([$stranger->id]);

        $blockers = $service->blockers($subject->fresh(), (int) $targetId);

        check('an incompatible registered course refuses the move',
            $blockers !== [],
            'the move must not strand a course on a programme that does not teach it');

        check('and the refusal names the course',
            collect($blockers)->contains(fn ($b) => str_contains($b, $stranger->code)),
            implode(' | ', $blockers));

        $before = $subject->fresh()->program_id;
        $result = $service->apply($subject->fresh(), (int) $targetId, 'test: should refuse', null);

        check('and nothing is written', $result['moved'] === false
            && (int) StudentEnroll::find($subject->id)->program_id === (int) $before);
    }
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('A permitted move leaves the record whole');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    $fresh = $subject->fresh();

    $enrolCountBefore = StudentEnroll::where('student_id', $fresh->student_id)->count();
    $courseCountBefore = $fresh->subjects()->count();
    $marksBefore = DB::table('subject_markings')->where('student_enroll_id', $fresh->id)->count();
    $attendanceBefore = DB::table('student_attendances')->where('student_enroll_id', $fresh->id)->count();

    $previewed = $service->proposedMatricule($fresh, (int) $targetId);
    $result = $service->apply($fresh, (int) $targetId, 'test: clean move', null);

    if (!$result['moved']) {
        skip('the clean move was refused: ' . implode('; ', $result['blockers']));
    } else {
        $after = StudentEnroll::find($subject->id);

        check('the enrolment is repointed, not replaced',
            (int) $after->program_id === (int) $targetId && $after->id === $subject->id);

        check('the student still has the same number of enrolments',
            StudentEnroll::where('student_id', $fresh->student_id)->count() === $enrolCountBefore,
            'a move must not leave the student on two programmes at once');

        check('where they came from is recorded',
            (int) $after->previous_program_id === (int) $subject->program_id
            && $after->is_program_change);

        check('registered courses are untouched',
            $after->subjects()->count() === $courseCountBefore);

        check('every course is one the new programme teaches',
            $service->coursePlan($after, (int) $targetId)['orphans']->isEmpty());

        check('marks and attendance are untouched',
            DB::table('subject_markings')->where('student_enroll_id', $after->id)->count() === $marksBefore
            && DB::table('student_attendances')->where('student_enroll_id', $after->id)->count() === $attendanceBefore);

        check('the matricule previewed is the matricule saved',
            $previewed && $after->matricule === $previewed,
            'previewed ' . $previewed . ', saved ' . $after->matricule);

        check('and the student\'s own id agrees with it',
            Student::find($fresh->student_id)->student_id === $after->matricule,
            'these used to diverge for ever after a change');
    }
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('The payment test lives in one place');
// ---------------------------------------------------------------------------
$root = dirname(__DIR__);

check('FeePaymentState is what decides whether a fee is untouched',
    is_file($root . '/app/Services/FeePaymentState.php'));

check('and a pending receipt counts as paid against',
    (function () {
        $fee = Fee::whereHas('paymentReceipts',
            fn ($q) => $q->where('verification_status', 'pending'))->first();

        return !$fee || !FeePaymentState::isUntouched($fee);
    })(),
    'money handed over but not yet verified is still money handed over');

// ---------------------------------------------------------------------------
section('Only at the very beginning of the programme');
// ---------------------------------------------------------------------------
// The rule exists because of a real case: a student was moved in her second
// semester with seventeen published markings, and a transcript issued three
// weeks earlier went on naming the matricule and programme she no longer had.
$later = StudentEnroll::with('semester')
    ->whereIn('program_id', $configured)
    ->whereHas('semester', fn ($q) => $q->where('semester_type', '!=', 1))
    ->first();

if (!$later) {
    skip('no enrolment outside a first semester to test against');
} else {
    $otherInFaculty = Program::where('status', '1')
        ->where('faculty_id', optional(Program::find($later->program_id))->faculty_id)
        ->where('id', '!=', $later->program_id)
        ->first();

    if (!$otherInFaculty) {
        skip('that enrolment has no sibling programme to move to');
    } else {
        $reasons = $service->blockers($later, (int) $otherInFaculty->id);

        check('a second-semester enrolment cannot be moved',
            collect($reasons)->contains(fn ($r) => str_contains($r, 'first semester')),
            implode(' | ', $reasons));
    }
}

// Published results anywhere, not only on the enrolment being moved: a
// year-two student has year-one transcripts, and those go stale the same way.
$withPublished = StudentEnroll::with('semester')
    ->whereIn('program_id', $configured)
    ->whereHas('semester', fn ($q) => $q->where('semester_type', 1)->where('year', 1))
    ->get()
    ->first(function ($enrolment) {
        return App\Models\SubjectMarking::where('workflow_state', 'published')
            ->whereHas('studentEnroll', fn ($q) => $q->where('student_id', $enrolment->student_id))
            ->exists();
    });

if (!$withPublished) {
    skip('no first-semester enrolment whose student has published results');
} else {
    $sibling = Program::where('status', '1')
        ->where('faculty_id', optional(Program::find($withPublished->program_id))->faculty_id)
        ->where('id', '!=', $withPublished->program_id)
        ->first();

    if (!$sibling) {
        skip('that student has no sibling programme to move to');
    } else {
        $reasons = $service->blockers($withPublished, (int) $sibling->id);

        check('a student with published results cannot be moved, even in the first semester',
            collect($reasons)->contains(fn ($r) => str_contains($r, 'published result')),
            implode(' | ', $reasons));

        DB::beginTransaction();

        try {
            $before = $withPublished->program_id;
            $result = $service->apply($withPublished, (int) $sibling->id, 'test: has published results', null);

            check('and the move writes nothing',
                $result['moved'] === false
                && (int) StudentEnroll::find($withPublished->id)->program_id === (int) $before);
        } finally {
            DB::rollBack();
        }
    }
}

check('the subject of this suite is one the rule permits',
    $service->blockers($subject->fresh(), (int) $targetId) === []
    || !collect($service->blockers($subject->fresh(), (int) $targetId))
        ->contains(fn ($r) => str_contains($r, 'first semester') || str_contains($r, 'published result')),
    'otherwise every case above fails on eligibility rather than on what it tests');

// ---------------------------------------------------------------------------
section('A student may only move within their own faculty');
// ---------------------------------------------------------------------------
// Across faculties is a different act - different curriculum, different
// matricule series, usually a fresh admission rather than a transfer - and
// this screen reconciles fees, not admissions.
$elsewhere = Program::where('status', '1')
    ->where('faculty_id', '!=', $subjectFaculty)
    ->whereNotNull('faculty_id')
    ->first();

if (!$elsewhere) {
    skip('every programme is in the same faculty');
} else {
    $reasons = $service->blockers($subject->fresh(), (int) $elsewhere->id);

    check('a programme in another faculty is refused',
        $reasons !== [],
        'moving to ' . $elsewhere->title . ' was allowed');

    check('and the refusal names both faculties',
        collect($reasons)->contains(fn ($r) => str_contains($r, 'faculty')),
        implode(' | ', $reasons));

    DB::beginTransaction();

    try {
        $before = StudentEnroll::find($subject->id)->program_id;
        $result = $service->apply($subject->fresh(), (int) $elsewhere->id, 'test: across faculties', null);

        check('and the move writes nothing',
            $result['moved'] === false
            && (int) StudentEnroll::find($subject->id)->program_id === (int) $before);
    } finally {
        DB::rollBack();
    }

    // Same faculty must still be allowed, or the rule has gone too far.
    $sibling = Program::where('status', '1')
        ->where('faculty_id', $subjectFaculty)
        ->where('id', '!=', $subject->program_id)
        ->first();

    if (!$sibling) {
        skip('the student\'s faculty has no other programme');
    } else {
        check('a programme in the same faculty is not refused on faculty grounds',
            !collect($service->blockers($subject->fresh(), (int) $sibling->id))
                ->contains(fn ($r) => str_contains($r, 'same faculty')),
            'moving within the faculty must stay possible');
    }
}

$enrollView = (string) file_get_contents($root . '/resources/views/admin/single-enroll/index.blade.php');

check('the dropdown offers only the student\'s own faculty',
    str_contains($enrollView, '->where(' . "'faculty_id', " . '$pcFaculty)'),
    'the admin should never be offered a choice the server would refuse');

// Matched on the exclusion itself rather than on the variable that holds the
// enrolment: the variable was renamed when the modal was made safe to render
// with no student selected, and a check pinned to the old name failed while
// the behaviour was untouched.
check('and never the programme they are already on',
    (bool) preg_match('/->where\(\s*\'id\',\s*\'!=\',\s*optional\(\$\w+\)->program_id\s*\)/', $enrollView));

// ---------------------------------------------------------------------------
section('The screen is gated and does what it says');
// ---------------------------------------------------------------------------
$kernel = app(Illuminate\Contracts\Http\Kernel::class);

$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();

$call = function (string $method, string $path, array $body, $as) use ($kernel) {
    app('session.store')->start();
    Illuminate\Support\Facades\Auth::guard('web')->login($as);

    $request = Illuminate\Http\Request::create($path, $method,
        $method === 'POST' ? array_merge($body, ['_token' => csrf_token()]) : $body);
    $request->setLaravelSession(app('session.store'));

    try {
        return $kernel->handle($request);
    } catch (\Throwable $e) {
        return null;
    }
};

if (!$admin) {
    skip('no Super Admin to drive the screen as');
} else {
    $response = $call('GET', '/admin/student/single-enroll/programme-change/preview',
        ['enrolment' => $subject->id, 'program' => $targetId], $admin);

    check('the preview endpoint answers', $response && $response->getStatusCode() === 200,
        'status ' . ($response ? $response->getStatusCode() : 'exception'));

    $payload = $response ? json_decode($response->getContent(), true) : null;

    check('and returns the decision, not just a number',
        is_array($payload) && array_key_exists('fees', $payload)
        && array_key_exists('blockers', $payload) && array_key_exists('matricule', $payload),
        'keys: ' . (is_array($payload) ? implode(', ', array_keys($payload)) : 'not json'));

    check('every fee line says why',
        collect($payload['fees'] ?? [])->every(fn ($f) => !empty($f['why'])));

    // The preview must not write. Ever.
    $feesBefore = Fee::count();
    $enrolBefore = StudentEnroll::find($subject->id)->program_id;

    $call('GET', '/admin/student/single-enroll/programme-change/preview',
        ['enrolment' => $subject->id, 'program' => $targetId], $admin);

    check('previewing changes nothing at all',
        Fee::count() === $feesBefore
        && (int) StudentEnroll::find($subject->id)->program_id === (int) $enrolBefore);

    // A reason that is too short is refused by validation.
    DB::beginTransaction();

    try {
        $before = StudentEnroll::find($subject->id)->program_id;

        $call('POST', '/admin/student/single-enroll/programme-change',
            ['enrolment' => $subject->id, 'program' => $targetId, 'reason' => 'x'], $admin);

        check('a one-word reason is refused',
            (int) StudentEnroll::find($subject->id)->program_id === (int) $before,
            'the reason is kept on the enrolment and on every fee it changes');
    } finally {
        DB::rollBack();
    }
}

// Deliberately a user who CAN already use this screen and lacks only the
// programme-change permission - otherwise the controller's own guard refuses
// them first, and the route's own permission could be deleted unnoticed.
$plain = App\User::get()->first(fn ($u) => $u->can('student-enroll-single')
    && !$u->can('student-enroll-programme-change'));

if (!$plain) {
    skip('no user holds student-enroll-single without programme-change');
} else {
    $response = $call('GET', '/admin/student/single-enroll/programme-change/preview',
        ['enrolment' => $subject->id, 'program' => $targetId], $plain);

    check('without the permission the preview is refused by middleware',
        $response && in_array($response->getStatusCode(), [401, 403], true),
        'status ' . ($response ? $response->getStatusCode() : 'exception'));

    DB::beginTransaction();

    try {
        $before = StudentEnroll::find($subject->id)->program_id;

        $response = $call('POST', '/admin/student/single-enroll/programme-change',
            ['enrolment' => $subject->id, 'program' => $targetId,
             'reason' => 'no permission should stop this'], $plain);

        check('and so is the move itself',
            $response && in_array($response->getStatusCode(), [401, 403], true)
            && (int) StudentEnroll::find($subject->id)->program_id === (int) $before,
            'status ' . ($response ? $response->getStatusCode() : 'exception'));
    } finally {
        DB::rollBack();
    }
}

$view = (string) file_get_contents($root . '/resources/views/admin/single-enroll/index.blade.php');

check('the action is behind its permission in the view too',
    str_contains($view, "@can('student-enroll-programme-change')"));

check('and the submit starts disabled',
    str_contains($view, 'id="pcSubmit" disabled'),
    'it must not be pressable before the preview is on screen');

// ---------------------------------------------------------------------------
section('Nothing was left behind');
// ---------------------------------------------------------------------------
$final = StudentEnroll::find($subject->id);

check('the subject enrolment is as it was found',
    (int) $final->program_id === (int) $subject->program_id
    && $final->matricule === $subject->matricule,
    'programme ' . $final->program_id . ', matricule ' . $final->matricule);

check('no probe fee survived',
    !Fee::where('note', 'LIKE', '%programme change%')
        ->where('created_at', '>=', now()->subMinutes(10))->exists());

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";

exit($failed > 0 ? 1 : 0);
