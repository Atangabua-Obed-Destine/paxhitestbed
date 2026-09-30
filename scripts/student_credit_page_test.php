<?php
/**
 * The buttons on a student credit's page, and what it says.
 *
 * Every button there opened its dialog with jQuery's $(...).modal('show'), which
 * belongs to Bootstrap 4. This project runs Bootstrap 5.2.2, where that method
 * does not exist, so Apply to a Fee — and Request, Approve, Reject and Pay the
 * Refund with it — did nothing at all when clicked. Reported from use as
 * "nothing happens".
 *
 * The page was also written against translation keys that were never added, so
 * it read "credit_details" and "apply_to_fee" instead of English.
 *
 * Nothing here writes.
 *
 * Usage: php scripts/student_credit_page_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\StudentCredit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

$kernel = app(Illuminate\Contracts\Http\Kernel::class);

$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->where('status', '1')->first()
    ?: App\User::where('is_admin', 1)->where('status', '1')->first();
Auth::guard('web')->login($admin);

$render = function (string $url) use ($kernel) {
    $request = Request::create($url, 'GET');
    $request->setLaravelSession(app('session.store'));
    $response = $kernel->handle($request);

    return [$response->getStatusCode(), (string) $response->getContent()];
};

$credit = StudentCredit::whereIn('status', ['available', 'partially_applied'])->where('remaining_amount', '>', 0)->first()
    ?? StudentCredit::first();

if (!$credit) {
    echo "No student credit in this database — nothing to test.\n";
    exit(1);
}

[$status, $html] = $render('/admin/student-credits/' . $credit->id);

echo "\nCredit #{$credit->id}, {$credit->remaining_amount} remaining\n";

echo "\n== The buttons open their dialogs ==\n";

check('the page renders', $status === 200, "status $status");

// Bootstrap 5 opens a dialog from the markup; the jQuery call it replaced is a
// Bootstrap 4 method that simply is not there.
foreach ([
    'applyToFeeBtn' => 'applyToFeeModal',
    'requestRefundBtn' => 'requestRefundModal',
    'approveRefundBtn' => 'approveRefundModal',
    'rejectRefundBtn' => 'rejectRefundModal',
    'processRefundBtn' => 'processRefundModal',
] as $button => $modal) {
    if (!str_contains($html, 'id="' . $button . '"')) {
        // Refund buttons only appear at the right point in the refund's life.
        continue;
    }

    check("{$button} is wired to its dialog",
        (bool) preg_match('/id="' . $button . '"[^>]*data-bs-toggle="modal"[^>]*data-bs-target="#' . $modal . '"/', $html),
        'button found but not wired');
}

// Read from the views themselves: the rendered page also carries the layout,
// which has its own guarded fallback that is not this page's business.
$views = [
    'show' => file_get_contents(__DIR__ . '/../resources/views/admin/student-credits/show.blade.php'),
    'list' => file_get_contents(__DIR__ . '/../resources/views/admin/student-credits/index.blade.php'),
];

foreach ($views as $name => $source) {
    check("the {$name} page calls no Bootstrap 4 modal method",
        !str_contains($source, ".modal('show')") && !str_contains($source, '.modal("show")'));
    check("and closes its dialogs the Bootstrap 5 way on the {$name} page",
        !str_contains($source, 'data-dismiss="modal"'));
}
check('and the dialogs close the Bootstrap 5 way',
    !str_contains($html, 'data-dismiss="modal"') && str_contains($html, 'data-bs-dismiss="modal"'));

echo "\n== It reads as English ==\n";

$view = file_get_contents(__DIR__ . '/../resources/views/admin/student-credits/show.blade.php');
preg_match_all('/__\(\'([a-z0-9_]+)\'\)/', $view, $keys);
$lang = json_decode(file_get_contents(__DIR__ . '/../resources/lang/en.json'), true);
$missing = array_values(array_unique(array_filter($keys[1], fn ($k) => !isset($lang[$k]))));

check('every label on the page has English behind it', $missing === [],
    count($missing) . ' missing: ' . implode(', ', array_slice($missing, 0, 8)));

$text = preg_replace('/\s+/', ' ', strip_tags($html));
check('so the page shows words, not keys',
    !str_contains($text, 'credit_details') && !str_contains($text, 'apply_to_fee') && !str_contains($text, 'no_unpaid_fees_found'),
    'raw keys still on the page');

echo "\n== A credit with nowhere to go ==\n";

// A student whose fees are all settled: the dialog must say so plainly rather
// than offer an empty list.
$stranded = StudentCredit::whereIn('status', ['available', 'partially_applied'])
    ->where('remaining_amount', '>', 0)->get()
    ->first(function ($c) {
        $enrollIds = App\Models\StudentEnroll::where('student_id', $c->student_id)->pluck('id');

        return !App\Models\Fee::withCreditMovedOut()->whereIn('student_enroll_id', $enrollIds)
            ->get()->contains(fn ($f) => $f->net_remaining_balance > 0.005);
    });

if (!$stranded) {
    echo "  SKIP  every student holding credit has a fee with a balance\n";
} else {
    [, $strandedHtml] = $render('/admin/student-credits/' . $stranded->id);
    $strandedText = preg_replace('/\s+/', ' ', strip_tags($strandedHtml));

    check('it says there is no fee to apply the credit to',
        str_contains($strandedText, 'no fee with a balance'), 'message missing');
    check('and says what can be done with it instead',
        str_contains($strandedText, 'refunded') || str_contains($strandedText, 'next fee'));
}

echo "\n$passed passed, $failed failed\n";

exit($failed > 0 ? 1 : 0);
