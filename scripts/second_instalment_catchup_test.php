<?php
/**
 * The Second Instalment catch-up: raising the instalments never assigned.
 *
 * This tool bills students, so the checks are about who gets billed and for how
 * much: exactly the students with no Second Instalment for the year, at their
 * programme's configured amount, with their own credit settling what it can and
 * nothing reaching the ledger that was not cash.
 *
 * Who should be listed is worked out here from `fees`, `student_enrolls` and the
 * category flags directly, not through the service, so a mistake in the service
 * cannot agree with itself.
 *
 * Every write runs inside a transaction that is rolled back.
 *
 * Usage: php scripts/second_instalment_catchup_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Fee;
use App\Models\FeesCategory;
use App\Models\StudentCredit;
use App\Models\StudentEnroll;
use App\Services\SecondInstalmentCatchUp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;

    if ($ok) {
        $passed++;
        echo "  PASS  $label\n";
    } else {
        $failed++;
        echo "  FAIL  $label" . ($detail !== '' ? "\n          $detail" : '') . "\n";
    }
}

// Requests are built from plain paths, not url(): before any request has been
// handled, url() uses APP_URL, which carries the site's sub-folder here, and a
// fabricated request path with that folder matches no route.
$kernel = app(Illuminate\Contracts\Http\Kernel::class);
$catchUp = app(SecondInstalmentCatchUp::class);

$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->where('status', '1')->first()
    ?: App\User::where('is_admin', 1)->where('status', '1')->first();
Auth::guard('web')->login($admin);

$render = function (string $url) use ($kernel) {
    $request = Request::create($url, 'GET');
    $request->setLaravelSession(app('session.store'));
    $response = $kernel->handle($request);

    return [$response->getStatusCode(), (string) $response->getContent()];
};

$post = function (string $url, array $payload) use ($kernel) {
    $session = app('session.store');
    $request = Request::create($url, 'POST', $payload + ['_token' => $session->token()]);
    $request->headers->set('Accept', 'application/json');
    $request->setLaravelSession($session);
    $response = $kernel->handle($request);

    return [$response->getStatusCode(), (string) $response->getContent()];
};

$feeSnapshot = fn () => json_encode(DB::table('fees')->selectRaw('COUNT(*) n, SUM(fee_amount) due, SUM(paid_amount) paid')->first());
$creditSnapshot = fn () => json_encode(DB::table('student_credits')->selectRaw('COUNT(*) n, SUM(remaining_amount) remaining')->first());
$journalSnapshot = fn () => json_encode(DB::table('journal_entries')->selectRaw('COUNT(*) n, SUM(total_debit) debit')->first());

$second = FeesCategory::where('is_second_installment', 1)->first();

if (!$second) {
    echo "No category is marked as the second instalment — nothing to test.\n";
    exit(1);
}

// The year with the most students, so the suite has something to work with.
$sessionId = (int) DB::table('student_enrolls')->selectRaw('session_id, COUNT(DISTINCT student_id) n')
    ->groupBy('session_id')->orderByDesc('n')->value('session_id');

$base = "/admin/second-instalment-catchup?session_id={$sessionId}&program_id=0";

echo "\nYear under test: session #{$sessionId}, category '{$second->title}'\n";

// Who should be listed, worked out here rather than by the service.
$enrollsThisYear = DB::table('student_enrolls as e')
    ->join('semesters as s', 's.id', '=', 'e.semester_id')
    ->join('students as st', 'st.id', '=', 'e.student_id')
    ->where('e.session_id', $sessionId)->where('s.is_resit', 0)->where('st.status', '!=', 0)
    ->select('e.id', 'e.student_id')->get();

$haveSecond = DB::table('fees as f')
    ->join('student_enrolls as e', 'e.id', '=', 'f.student_enroll_id')
    ->where('e.session_id', $sessionId)->where('f.category_id', $second->id)
    ->distinct()->pluck('e.student_id')->all();

$expected = collect($enrollsThisYear)->pluck('student_id')->unique()
    ->reject(fn ($id) => in_array($id, $haveSecond))
    ->sort()->values()->all();

// ---------------------------------------------------------------------------

echo "\n== Who the catch-up lists ==\n";

$preview = $catchUp->preview($sessionId);

check('exactly the students with no second instalment for the year',
    $preview->pluck('student_id')->sort()->values()->all() === $expected,
    $preview->count() . ' listed, ' . count($expected) . ' expected');
check('each student appears once, however many enrolments they hold',
    $preview->pluck('student_id')->duplicates()->isEmpty());
check('a student who already has one is not listed',
    $haveSecond === [] || !array_intersect($preview->pluck('student_id')->all(), $haveSecond));

$disabled = App\Models\Student::where('status', 0)->pluck('id')->all();
check('disabled students are left out',
    $disabled === [] || !array_intersect($preview->pluck('student_id')->all(), $disabled));

if ($preview->isEmpty()) {
    echo "  SKIP  nobody needs a catch-up in this database; the rest cannot be exercised\n";
    echo "\n$passed passed, $failed failed\n";
    exit($failed > 0 ? 1 : 0);
}

// ---------------------------------------------------------------------------

echo "\n== Who is deliberately left out ==\n";

// These three cases are made here on purpose: this database has no disabled
// student and nobody enrolled only on a resit semester, so without fixtures a
// rule that ignored either would still look right.
$sample = $preview->firstWhere('blocked', null);

DB::beginTransaction();

try {
    App\Models\Student::where('id', $sample['student_id'])->update(['status' => 0]);
    check('a disabled student is left out',
        $catchUp->preview($sessionId)->firstWhere('student_id', $sample['student_id']) === null);
} finally {
    DB::rollBack();
}

DB::beginTransaction();

try {
    $resit = App\Models\Semester::where('is_resit', 1)->first()
        ?? App\Models\Semester::create(['title' => 'TEST RESIT', 'year' => 1, 'semester_type' => 1, 'is_resit' => 1, 'status' => '1']);

    // Move every regular enrolment this student holds for the year onto a resit
    // semester: they are then enrolled, but not on a semester fees belong to.
    StudentEnroll::where('student_id', $sample['student_id'])->where('session_id', $sessionId)
        ->update(['semester_id' => $resit->id]);

    check('a student enrolled only on a resit semester is left out',
        $catchUp->preview($sessionId)->firstWhere('student_id', $sample['student_id']) === null);
} finally {
    DB::rollBack();
}

DB::beginTransaction();

try {
    // Untouched and unpaid, so only the marker stands between it and deletion.
    $ordinary = new Fee();
    $ordinary->student_enroll_id = $sample['enroll_id'];
    $ordinary->category_id = $second->id;
    $ordinary->fee_amount = 1000;
    $ordinary->assign_date = now()->format('Y-m-d');
    $ordinary->due_date = now()->format('Y-m-d');
    $ordinary->status = 0;
    $ordinary->note = 'An ordinary fee, nothing to do with the catch-up';
    $ordinary->save();

    $result = $catchUp->undo([$ordinary->id], $admin->id);

    check('a fee this tool did not raise is refused by undo, and left alone',
        $result['removed'] === 0 && count($result['refused']) === 1 && Fee::find($ordinary->id) !== null,
        json_encode($result));
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------

echo "\n== The amounts it would raise ==\n";

$row = $preview->firstWhere('blocked', null);

$configured = DB::table('program_semester_fees')
    ->where('fees_category_id', $second->id)->where('status', 1)
    ->where('program_id', $row['program_id'])->value('amount');

check('the fee is the programme\'s configured second instalment',
    abs($row['amount'] - (float) $configured) < 0.01, "{$row['amount']} against {$configured}");
check('credit held by the student is shown against it',
    abs($row['credit'] - (float) StudentCredit::where('student_id', $row['student_id'])
        ->whereIn('status', ['available', 'partially_applied'])->sum('remaining_amount')) < 0.01);
check('what is left owing is the fee less that credit',
    abs($row['owing'] - max(0, $row['amount'] + $row['fine'] - $row['credit'])) < 0.01);
check('a due date is worked out for every billable row',
    $preview->whereNull('blocked')->every(fn ($r) => !empty($r['due_date'])));

// A programme with nothing configured cannot be billed, and says so.
DB::beginTransaction();

try {
    DB::table('program_semester_fees')->where('fees_category_id', $second->id)
        ->where('program_id', $row['program_id'])->update(['status' => 0]);

    $blocked = $catchUp->preview($sessionId)->firstWhere('student_id', $row['student_id']);

    check('with no configured amount the student is blocked, not guessed at',
        $blocked && $blocked['blocked'] !== null && $blocked['amount'] == 0, json_encode($blocked));

    $result = $catchUp->apply([$row['student_id']], $sessionId, $admin->id);
    check('and applying refuses them, raising nothing',
        $result['billed'] === 0 && count($result['failed']) === 1, json_encode($result));
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------

echo "\n== Raising the fees ==\n";

$before = ['fees' => $feeSnapshot(), 'credits' => $creditSnapshot(), 'journal' => $journalSnapshot()];
$billable = $preview->whereNull('blocked');
$withCredit = $billable->firstWhere('credit', '>', 0);

DB::beginTransaction();

try {
    $result = $catchUp->apply($billable->pluck('student_id')->all(), $sessionId, $admin->id);

    check('every billable student gets one fee', $result['billed'] === $billable->count() && $result['failed'] === [],
        json_encode($result));
    check('raised is the sum of the amounts previewed',
        abs($result['raised'] - $billable->sum(fn ($r) => $r['amount'] + $r['fine'])) < 0.01,
        $result['raised'] . ' against ' . $billable->sum(fn ($r) => $r['amount'] + $r['fine']));
    check('credit applied and left owing add up to what was raised',
        abs($result['raised'] - $result['credit_applied'] - $result['owing']) < 0.01, json_encode($result));

    $fees = Fee::whereIn('student_enroll_id', $billable->pluck('enroll_id'))->where('category_id', $second->id)->get();
    check('one fee per student, attached to their latest regular enrolment for the year',
        $fees->count() === $billable->count()
            && $fees->pluck('student_enroll_id')->sort()->values()->all() === $billable->pluck('enroll_id')->sort()->values()->all());
    check('each fee carries the note that says where it came from',
        $fees->every(fn ($fee) => str_starts_with((string) $fee->note, SecondInstalmentCatchUp::MARKER)));

    if ($withCredit) {
        $fee = Fee::where('student_enroll_id', $withCredit['enroll_id'])->where('category_id', $second->id)->first();
        check('a student holding credit has it applied to the new fee',
            $fee && abs((float) $fee->paid_amount - min($withCredit['credit'], $withCredit['amount'] + $withCredit['fine'])) < 0.01,
            'paid ' . optional($fee)->paid_amount . ' of credit ' . $withCredit['credit']);
        check('and their first instalment is no longer overpaid',
            !Fee::withCreditMovedOut()->whereIn('student_enroll_id', StudentEnroll::where('student_id', $withCredit['student_id'])->pluck('id'))
                ->get()->contains(fn ($f) => $f->isNetOverpaid()));
        check('their credit is spent, not still available',
            (float) StudentCredit::where('student_id', $withCredit['student_id'])
                ->whereIn('status', ['available', 'partially_applied'])->sum('remaining_amount')
                <= max(0, $withCredit['credit'] - ($withCredit['amount'] + $withCredit['fine'])) + 0.01);
    }

    // Credit is not cash: the ledger must not grow for a fee settled by credit.
    check('the ledger gains nothing from a fee settled by credit', $journalSnapshot() === $before['journal'],
        $journalSnapshot() . ' against ' . $before['journal']);
    check('and the fee credit audit still reports no overstatement',
        abs(app(App\Services\FeeCreditReconciliation::class)->ledgerExposure()['overstated']) < 0.01);

    $sheet = app(App\Services\BudgetReconciliationService::class)->reconcile();
    check('the budget sheet still reconciles', $sheet['agrees'] === true, json_encode($sheet['issues'] ?? null));

    // Running again finds nothing: they all have one now.
    check('run again, nobody is left to bill', $catchUp->preview($sessionId)->whereNull('blocked')->isEmpty());

    // What it raised is listed back, and can be taken off again.
    $raised = $catchUp->raised($sessionId);
    check('what it raised is listed back', $raised->count() === $billable->count());

    $untouched = $raised->where('removable', true);
    $undo = $catchUp->undo($untouched->pluck('fee_id')->all(), $admin->id);
    check('untouched fees can be taken back', $undo['removed'] === $untouched->count() && $undo['refused'] === []);

    if ($withCredit) {
        $settled = $raised->firstWhere('removable', false);
        check('a fee with credit against it is kept, and says why',
            $settled !== null, json_encode($raised->pluck('removable')->all()));

        if ($settled) {
            $refused = $catchUp->undo([$settled['fee_id']], $admin->id);
            check('and refusing it leaves the fee alone',
                $refused['removed'] === 0 && count($refused['refused']) === 1
                    && Fee::find($settled['fee_id']) !== null);
        }
    }
} finally {
    DB::rollBack();
}

check('the raising tests left the fees, credits and ledger as they found them',
    $feeSnapshot() === $before['fees'] && $creditSnapshot() === $before['credits'] && $journalSnapshot() === $before['journal']);

// ---------------------------------------------------------------------------

echo "\n== Only the students ticked ==\n";

DB::beginTransaction();

try {
    $one = $billable->first();
    $result = $catchUp->apply([$one['student_id']], $sessionId, $admin->id);

    check('applying for one student bills only that student', $result['billed'] === 1);
    check('and the others are still waiting',
        $catchUp->preview($sessionId)->whereNull('blocked')->count() === $billable->count() - 1);
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------

echo "\n== The production command ==\n";

$feesBefore = $feeSnapshot();

Artisan::call('fees:second-instalment-catchup', ['--session' => $sessionId]);
$dry = Artisan::output();

check('the dry run reports the students', str_contains($dry, (string) $billable->first()['matricule']));
check('and says it wrote nothing', str_contains($dry, 'Nothing was written'));
check('and truly wrote nothing', $feeSnapshot() === $feesBefore);

DB::beginTransaction();

try {
    Artisan::call('fees:second-instalment-catchup', ['--session' => $sessionId, '--apply' => true, '--force' => true]);
    check('with --apply --force it raises the fees', $catchUp->preview($sessionId)->whereNull('blocked')->isEmpty());
} finally {
    DB::rollBack();
}

check('and the command tests left the fees as they found them', $feeSnapshot() === $feesBefore);

// ---------------------------------------------------------------------------

echo "\n== The page ==\n";

[$status, $html] = $render($base);

check('Super Admin can open it', $status === 200, "status $status");
check('every billable student has a tick box', substr_count($html, 'class="tick-student"') === $billable->count(),
    substr_count($html, 'class="tick-student"') . ' boxes for ' . $billable->count() . ' students');
check('the totals are shown before anything is applied', str_contains($html, 'sum-owing'));
check('and it warns that this bills students', str_contains($html, 'This bills students'));

[, $menu] = $render('/admin/fees-credit-audit');
check('Super Admin sees it in the menu', str_contains($menu, 'second-instalment-catchup'));

$outsider = App\User::where('status', '1')->where('id', '!=', $admin->id)->get()
    ->first(fn ($u) => !$u->can('second-instalment-catchup-view') && !$u->can('second-instalment-catchup-apply'));

if (!$outsider) {
    echo "  SKIP  every user holds the catch-up permissions\n";
} else {
    Auth::guard('web')->login($outsider);

    DB::beginTransaction();

    try {
        [$status] = $render($base);
        check('a user without the permission is refused the page', $status === 403, "status $status");

        $feeState = $feeSnapshot();
        [$applyStatus] = $post('/admin/second-instalment-catchup/apply',
            ['session_id' => $sessionId, 'students' => [$billable->first()['student_id']]]);
        [$undoStatus] = $post('/admin/second-instalment-catchup/undo', ['fees' => [1]]);

        check('and is refused both actions', $applyStatus === 403 && $undoStatus === 403, "apply $applyStatus, undo $undoStatus");
        check('which write nothing', $feeSnapshot() === $feeState);
    } finally {
        DB::rollBack();
        Auth::guard('web')->login($admin);
    }
}

DB::beginTransaction();

try {
    [$status, $body] = $post('/admin/second-instalment-catchup/apply',
        ['session_id' => $sessionId, 'students' => [$billable->first()['student_id']]]);
    $json = json_decode($body, true);

    check('Super Admin: raising from the page works',
        $status === 200 && ($json['success'] ?? false) === true && ($json['result']['billed'] ?? 0) === 1,
        "status $status " . substr($body, 0, 200));
} finally {
    DB::rollBack();
}

check('and the page tests left the fees as they found them', $feeSnapshot() === $feesBefore);

echo "\n$passed passed, $failed failed\n";

exit($failed > 0 ? 1 : 0);
