<?php

/**
 * The platform access fee: once per academic year, not once per enrolment.
 *
 * A student gets a new enrolment for every semester — first semester, its resit
 * semester, second semester, its resit semester. The fee used to be looked up by
 * student_enroll_id, so progressing put the portal back behind the paywall in a
 * year the student had already paid for. Several students here hold five
 * enrolments in one session and would have been asked five times.
 *
 * Every write happens inside a transaction that is rolled back, so this can be
 * run against real data.
 *
 *   php scripts/platform_fee_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\PlatformFeeExemption;
use App\Models\PlatformFeePayment;
use App\Models\PlatformFeeSetting;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Services\PlatformFeeAccess;

$passed = 0;
$failed = 0;
$skipped = 0;

function check(string $what, bool $ok, string $detail = ''): void
{
    global $passed, $failed;

    if ($ok) {
        $passed++;
        echo "  PASS  $what\n";
    } else {
        $failed++;
        echo "  FAIL  $what" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

function skip(string $why): void
{
    global $skipped;
    $skipped++;
    echo "  SKIP  $why\n";
}

function section(string $title): void
{
    echo "\n" . $title . "\n";
}

$access = app(PlatformFeeAccess::class);

// Exemptions record who granted them, and that column is a foreign key.
$author = \App\User::first();

/** A student holding several enrolments inside one academic session. */
$row = DB::table('student_enrolls')
    ->select('student_id', 'session_id', DB::raw('count(*) as c'))
    ->whereNotNull('session_id')
    ->groupBy('student_id', 'session_id')
    ->havingRaw('count(*) > 1')
    ->orderByDesc('c')
    ->first();

if (!$row) {
    echo "no student holds more than one enrolment in a session; nothing to prove here\n";
    exit(0);
}

$student = Student::find($row->student_id);
$enrollments = StudentEnroll::where('student_id', $student->id)
    ->where('session_id', $row->session_id)->orderBy('id')->get();

echo $student->student_id . ' holds ' . $enrollments->count()
    . " enrolments in one academic session\n";

// ---------------------------------------------------------------------------
section('One payment covers the whole academic year');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    $setting = PlatformFeeSetting::first();

    if (!$setting) {
        $setting = PlatformFeeSetting::create(['is_enabled' => 1, 'fee_amount' => 5000]);
    } else {
        $setting->update(['is_enabled' => 1]);
    }

    // Nothing paid yet: the portal asks.
    PlatformFeePayment::whereIn('student_enroll_id', $enrollments->pluck('id'))->delete();
    PlatformFeeExemption::whereIn('student_enroll_id', $enrollments->pluck('id'))->delete();
    PlatformFeeExemption::where('session_id', $row->session_id)->delete();

    check('with nothing paid, the portal is closed', !$access->grantsAccess($student->fresh()));

    // Paid once, from the FIRST enrolment of the year.
    PlatformFeePayment::create([
        'student_enroll_id' => $enrollments->first()->id,
        'session_id' => $row->session_id,
        'fee_amount' => 5000,
        'paid_amount' => 5000,
        'status' => 'approved',
        'payment_date' => now()->toDateString(),
    ]);

    check('paying once opens the portal', $access->grantsAccess($student->fresh()));

    // Now check it from every other enrolment in the year — this is the bit
    // that used to fail, because each enrolment was asked separately.
    $closedOn = [];

    foreach ($enrollments as $enrollment) {
        if (!$access->hasPaid($student, $enrollment)) {
            $closedOn[] = $enrollment->id;
        }
    }

    check('and it stays open from every enrolment in that year',
        empty($closedOn), 'still asking on enrolment(s) ' . implode(', ', $closedOn));

    check('the payment found is the approved one',
        optional($access->paymentFor($student, $enrollments->last()))->status === 'approved');
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('A receipt nobody has verified does not open the portal');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    PlatformFeeSetting::first()->update(['is_enabled' => 1]);
    PlatformFeePayment::whereIn('student_enroll_id', $enrollments->pluck('id'))->delete();
    PlatformFeeExemption::whereIn('student_enroll_id', $enrollments->pluck('id'))->delete();
    PlatformFeeExemption::where('exemption_type', 'session')
        ->where('session_id', $row->session_id)->delete();

    PlatformFeePayment::create([
        'student_enroll_id' => $enrollments->first()->id,
        'session_id' => $row->session_id,
        'fee_amount' => 5000,
        'paid_amount' => 5000,
        'status' => 'pending',
        'payment_date' => now()->toDateString(),
    ]);

    check('an unverified receipt is not treated as paid',
        !$access->hasPaid($student, $enrollments->last()));
    check('and the portal stays closed', !$access->grantsAccess($student->fresh()));
    check('but the receipt is still shown, so they see what they are waiting on',
        optional($access->paymentFor($student, $enrollments->last()))->status === 'pending');

    // Rejected is no better than pending.
    PlatformFeePayment::whereIn('student_enroll_id', $enrollments->pluck('id'))
        ->update(['status' => 'rejected']);

    check('a rejected receipt does not open it either',
        !$access->hasPaid($student, $enrollments->last()));
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('A new academic year is charged again');
// ---------------------------------------------------------------------------
$otherSession = DB::table('student_enrolls')
    ->where('student_id', $student->id)
    ->where('session_id', '!=', $row->session_id)
    ->whereNotNull('session_id')
    ->value('session_id');

DB::beginTransaction();

try {
    PlatformFeeSetting::first()->update(['is_enabled' => 1]);
    PlatformFeePayment::whereIn('student_enroll_id',
        StudentEnroll::where('student_id', $student->id)->pluck('id'))->delete();

    PlatformFeePayment::create([
        'student_enroll_id' => $enrollments->first()->id,
        'session_id' => $row->session_id,
        'fee_amount' => 5000,
        'paid_amount' => 5000,
        'status' => 'approved',
        'payment_date' => now()->toDateString(),
    ]);

    if (!$otherSession) {
        // Build the next year's enrolment, so the rule can be shown rather than
        // assumed. Rolled back with everything else.
        $nextSession = DB::table('sessions')->where('id', '!=', $row->session_id)->value('id');

        if (!$nextSession) {
            skip('this database has only one academic session');
        } else {
            $next = StudentEnroll::create([
                'student_id' => $student->id,
                'matricule' => $enrollments->first()->matricule,
                'program_id' => $enrollments->first()->program_id,
                'session_id' => $nextSession,
                'semester_id' => $enrollments->first()->semester_id,
                'section_id' => $enrollments->first()->section_id,
                'status' => 1,
            ]);

            check('last year\'s payment does not cover the new year',
                !$access->hasPaid($student, $next));
        }
    } else {
        $next = StudentEnroll::where('student_id', $student->id)
            ->where('session_id', $otherSession)->first();

        check('last year\'s payment does not cover the new year',
            !$access->hasPaid($student, $next));
    }
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('Exemptions last the year too');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    PlatformFeeSetting::first()->update(['is_enabled' => 1]);
    PlatformFeePayment::whereIn('student_enroll_id', $enrollments->pluck('id'))->delete();
    PlatformFeeExemption::whereIn('student_enroll_id', $enrollments->pluck('id'))->delete();

    // This database carries a live session exemption for 2025/2026, which
    // excuses everybody in it. Left in place it would make every check below
    // pass whatever the student-exemption rule did.
    PlatformFeeExemption::where('exemption_type', 'session')
        ->where('session_id', $row->session_id)->delete();

    // Granted against the first enrolment of the year.
    PlatformFeeExemption::create([
        'exemption_type' => 'student',
        'student_enroll_id' => $enrollments->first()->id,
        'status' => 1,
        'reason' => 'suite: excused for the year',
        'created_by' => $author->id ?? null,
    ]);

    $lapsedOn = [];

    foreach ($enrollments as $enrollment) {
        if (!$access->isExempt($student, $enrollment)) {
            $lapsedOn[] = $enrollment->id;
        }
    }

    check('a student exemption does not lapse when they progress',
        empty($lapsedOn), 'lapsed on enrolment(s) ' . implode(', ', $lapsedOn));
    check('and it opens the portal', $access->grantsAccess($student->fresh()));

    // A whole-session exemption excuses everyone in it.
    PlatformFeeExemption::whereIn('student_enroll_id', $enrollments->pluck('id'))->delete();
    PlatformFeeExemption::create([
        'exemption_type' => 'session',
        'session_id' => $row->session_id,
        'status' => 1,
        'reason' => 'suite: the year is free',
        'created_by' => $author->id ?? null,
    ]);

    check('a session exemption excuses the year', $access->grantsAccess($student->fresh()));
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('The fee switched off asks nobody for anything');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    PlatformFeeSetting::first()->update(['is_enabled' => 0]);
    PlatformFeePayment::whereIn('student_enroll_id', $enrollments->pluck('id'))->delete();
    PlatformFeeExemption::whereIn('student_enroll_id', $enrollments->pluck('id'))->delete();
    PlatformFeeExemption::where('session_id', $row->session_id)->delete();

    check('the portal is open when the fee is disabled', $access->grantsAccess($student->fresh()));
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('School-wide: nobody would be asked twice in one year');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    PlatformFeeSetting::first()->update(['is_enabled' => 1]);

    $wouldBeAskedAgain = 0;
    $pairs = 0;

    foreach (DB::table('student_enrolls')->select('student_id', 'session_id')
        ->whereNotNull('session_id')
        ->groupBy('student_id', 'session_id')
        ->havingRaw('count(*) > 1')->get() as $pair) {
        $one = Student::find($pair->student_id);

        if (!$one) {
            continue;
        }

        $pairs++;
        $theirs = StudentEnroll::where('student_id', $one->id)
            ->where('session_id', $pair->session_id)->orderBy('id')->get();

        // Pretend they paid from their first enrolment of that year.
        $payment = PlatformFeePayment::create([
            'student_enroll_id' => $theirs->first()->id,
            'session_id' => $pair->session_id,
            'fee_amount' => 5000,
            'paid_amount' => 5000,
            'status' => 'approved',
            'payment_date' => now()->toDateString(),
        ]);

        foreach ($theirs as $enrollment) {
            if (!$access->hasPaid($one, $enrollment)) {
                $wouldBeAskedAgain++;
                break;
            }
        }

        $payment->delete();
    }

    check("having paid once, none of the {$pairs} students with several enrolments in a year is asked again",
        $wouldBeAskedAgain === 0, $wouldBeAskedAgain . ' would be');
} finally {
    DB::rollBack();
}

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";

exit($failed > 0 ? 1 : 0);
