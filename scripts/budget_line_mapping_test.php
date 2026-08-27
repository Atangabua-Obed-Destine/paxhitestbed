<?php
/**
 * A budget line can be given a category without leaving the budget line screen.
 *
 * A line stays at zero until a category points at it, and that link could only
 * be made at Accounting > Mapping Settings, with the category itself created on
 * a third screen. Since the lines are already named the way the category would
 * be — "401 Travelling", "621 Donations / Grants" — both are now done here.
 *
 * What has to hold:
 *   - creating from a line makes the category AND the mapping, in one go;
 *   - an income line never produces a fee category (those are billable to
 *     students, which is not a side effect this screen should have);
 *   - the suggested accounts follow the house rule — one side is always cash —
 *     and name the sibling they were borrowed from, so they get checked;
 *   - linking an existing category MOVES it, and says which line it leaves;
 *   - a heading is refused: it holds no money of its own;
 *   - the category permissions on the dedicated screens still bind here.
 *
 * Usage: php scripts/budget_line_mapping_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Admin\BudgetLineController;
use App\Models\BudgetLine;
use App\Models\ChartOfAccount;
use App\Models\DefaultAccountMapping;
use App\Models\ExpenseCategory;
use App\Models\FeesCategory;
use App\Models\IncomeCategory;
use App\Services\BudgetReconciliationService;
use App\User;
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

$controller = app(BudgetLineController::class);

function invoke($controller, string $method, ...$args)
{
    $r = new ReflectionMethod(BudgetLineController::class, $method);
    $r->setAccessible(true);

    return $r->invoke($controller, ...$args);
}

$admin = User::whereHas('roles')->first() ?: User::first();
Auth::guard('web')->login($admin);

$service = app(BudgetReconciliationService::class);

echo "\n== Which category kind a line calls for ==\n";

$income = BudgetLine::where('section', 'income')->where('is_header', false)->first();
$expense = BudgetLine::where('section', 'expenditure')->where('is_header', false)->first();
$capital = BudgetLine::where('section', 'capital')->where('is_header', false)->first();

check('an income line calls for an income category', invoke($controller, 'categoryTypeFor', $income) === 'income_category');
check('an expenditure line calls for an expense category', invoke($controller, 'categoryTypeFor', $expense) === 'expense_category');
if ($capital) {
    check('a capital line calls for an expense category', invoke($controller, 'categoryTypeFor', $capital) === 'expense_category');
    check('a capital line posts to class 2', invoke($controller, 'accountClassFor', $capital) === 2);
}
check('an income line posts to class 7', invoke($controller, 'accountClassFor', $income) === 7);
check('an expenditure line posts to class 6', invoke($controller, 'accountClassFor', $expense) === 6);

echo "\n== The suggested accounts ==\n";

$cashId = invoke($controller, 'defaultCashAccountId');
$cash = ChartOfAccount::find($cashId);
check('a cash account is identified', $cash !== null);
check(
    'and it really is a cash account (class 5)',
    $cash && (int) $cash->class_number === 5,
    $cash ? ('class ' . $cash->class_number . ' ' . $cash->account_code) : 'none'
);

// The house rule, without exception in the existing 28 rows: cash is the debit
// for income and the credit for expenditure.
$s = invoke($controller, 'suggestedAccountsFor', $income);
check('income suggests cash on the debit side', $s['debit_account_id'] === $cashId);
$s = invoke($controller, 'suggestedAccountsFor', $expense);
check('expenditure suggests cash on the credit side', $s['credit_account_id'] === $cashId);

// A line whose siblings are all unmapped must get nothing rather than a guess.
$reached = DefaultAccountMapping::whereNotNull('budget_line_id')->pluck('budget_line_id')->unique();
$lonely = BudgetLine::where('is_header', false)
    ->whereNull('faculty_id')
    ->whereNotIn('id', $reached)
    ->get()
    ->first(function (BudgetLine $l) use ($reached) {
        if (!$l->parent_id) {
            return true;
        }
        $sibs = BudgetLine::where('parent_id', $l->parent_id)->pluck('id');

        return !DefaultAccountMapping::whereIn('budget_line_id', $sibs)->exists();
    });

if ($lonely) {
    $s = invoke($controller, 'suggestedAccountsFor', $lonely);
    check(
        'a line with nothing to learn from gets no guess (' . $lonely->code . ')',
        $s['from_sibling'] === false
            && ($lonely->section === 'income' ? $s['credit_account_id'] : $s['debit_account_id']) === null
    );
}

$withSibling = BudgetLine::where('is_header', false)->whereNotIn('id', $reached)->get()
    ->first(function (BudgetLine $l) {
        if (!$l->parent_id) {
            return false;
        }
        $sibs = BudgetLine::where('parent_id', $l->parent_id)->where('id', '!=', $l->id)->pluck('id');

        return DefaultAccountMapping::whereIn('budget_line_id', $sibs)->exists();
    });

if ($withSibling) {
    $s = invoke($controller, 'suggestedAccountsFor', $withSibling);
    check('a borrowed guess is flagged as borrowed', $s['from_sibling'] === true);
    // A suggestion that hides its source gets accepted; one that shows its
    // working gets checked. "Meetings and Seminars" borrowing the account from
    // "Telephone / Postage" looks plausible and is wrong.
    check('and it names the line it was borrowed from', !empty($s['sibling_label']), json_encode($s));
}

echo "\n== Creating a category from a line ==\n";

$target = BudgetLine::where('section', 'expenditure')->where('is_header', false)
    ->whereNotIn('id', $reached)->first();

if (!$target) {
    echo "  ..  every expenditure line is already mapped; skipping\n";
} else {
    DB::beginTransaction();
    try {
        $feeBefore = FeesCategory::count();
        $expenseAccount = ChartOfAccount::where('class_number', 6)->where('is_active', 1)->first();

        $request = Illuminate\Http\Request::create('/x', 'POST', [
            'title' => 'ZZ Test ' . $target->name,
            'debit_account_id' => $expenseAccount->id,
            'credit_account_id' => $cashId,
        ]);
        $controller->storeCategory($request, $target->id);

        $category = ExpenseCategory::where('title', 'ZZ Test ' . $target->name)->first();
        check('the expense category is created', $category !== null);

        if ($category) {
            check('it is active, so it can be used at once', (string) $category->status === '1');
            check('it has a slug', !empty($category->slug));

            $mapping = DefaultAccountMapping::where('mapping_type', 'expense_category')
                ->where('category_id', $category->id)->first();

            check('a mapping is created with it', $mapping !== null);
            check('and it points at the line', $mapping && (int) $mapping->budget_line_id === $target->id);
            check('with the accounts given', $mapping && (int) $mapping->debit_account_id === $expenseAccount->id);
        }

        check('no fee category was created', FeesCategory::count() === $feeBefore);

        // The whole point: the line is no longer empty.
        $after = app(BudgetReconciliationService::class)->categoriesByLine();
        check('the line now reports what feeds it', !empty($after[$target->id]));
    } finally {
        DB::rollBack();
    }

    check('the test category left nothing behind', ExpenseCategory::where('title', 'like', 'ZZ Test%')->doesntExist());
}

echo "\n== An income line never creates a fee category ==\n";

$incomeTarget = BudgetLine::where('section', 'income')->where('is_header', false)
    ->whereNotIn('id', $reached)->first();

if ($incomeTarget) {
    DB::beginTransaction();
    try {
        $feeBefore = FeesCategory::count();
        $incomeAccount = ChartOfAccount::where('class_number', 7)->where('is_active', 1)->first();

        $request = Illuminate\Http\Request::create('/x', 'POST', [
            'title' => 'ZZ Income ' . $incomeTarget->name,
            'debit_account_id' => $cashId,
            'credit_account_id' => $incomeAccount->id,
        ]);
        $controller->storeCategory($request, $incomeTarget->id);

        check('an income category is created', IncomeCategory::where('title', 'like', 'ZZ Income%')->exists());
        check('and no fee category is', FeesCategory::count() === $feeBefore);
    } finally {
        DB::rollBack();
    }
}

echo "\n== Linking an existing category moves it ==\n";

$mapping = DefaultAccountMapping::whereNotNull('budget_line_id')->whereNotNull('category_id')->first();
$moveTo = BudgetLine::where('is_header', false)->where('id', '!=', $mapping->budget_line_id)
    ->whereNotIn('id', $reached)->first();

if ($mapping && $moveTo) {
    DB::beginTransaction();
    try {
        $from = (int) $mapping->budget_line_id;

        $request = Illuminate\Http\Request::create('/x', 'POST', ['mapping_id' => $mapping->id]);
        $controller->linkCategory($request, $moveTo->id);

        $mapping->refresh();
        check('the category now feeds the new line', (int) $mapping->budget_line_id === $moveTo->id);
        check('and no longer the old one', (int) $mapping->budget_line_id !== $from);

        $byLine = app(BudgetReconciliationService::class)->categoriesByLine();
        check('the old line loses it', empty($byLine[$from]) || !collect($byLine[$from])->contains('id', $mapping->category_id));
    } finally {
        DB::rollBack();
    }
}

echo "\n== What must be refused ==\n";

$header = BudgetLine::where('is_header', true)->first();
DB::beginTransaction();
try {
    $before = ExpenseCategory::count();
    $account = ChartOfAccount::where('class_number', 6)->where('is_active', 1)->first();

    $request = Illuminate\Http\Request::create('/x', 'POST', [
        'title' => 'ZZ Should Not Exist',
        'debit_account_id' => $account->id,
        'credit_account_id' => $cashId,
    ]);
    $controller->storeCategory($request, $header->id);

    check('a heading cannot be given a category', ExpenseCategory::count() === $before);
} finally {
    DB::rollBack();
}

// A name already taken would otherwise create a second category with the same
// title, and the operator would have two things called "Travelling".
$existing = ExpenseCategory::first();
$dupTarget = BudgetLine::where('section', 'expenditure')->where('is_header', false)->first();
DB::beginTransaction();
try {
    $before = ExpenseCategory::count();
    $account = ChartOfAccount::where('class_number', 6)->where('is_active', 1)->first();

    $request = Illuminate\Http\Request::create('/x', 'POST', [
        'title' => $existing->title,
        'debit_account_id' => $account->id,
        'credit_account_id' => $cashId,
    ]);
    $controller->storeCategory($request, $dupTarget->id);

    check('a duplicate category name is refused', ExpenseCategory::count() === $before);
} finally {
    DB::rollBack();
}

echo "\n== The screen ==\n";

$kernel = app(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/admin/budget-line', 'GET');
$request->setLaravelSession(app('session.store'));
$response = $kernel->handle($request);
$html = $response->getContent();

check('the budget line screen renders', $response->getStatusCode() === 200, 'status ' . $response->getStatusCode());
check('the mapping modal is present', strpos($html, 'id="mapModal"') !== false);
check('the direction rule is explained', strpos($html, 'The link runs one way') !== false);

// Faculty-tagged lines are fed by enrolment routing, not by a mapping, so they
// are not part of the unmapped reckoning. Two of the occurrences below are CSS
// rules rather than buttons.
$unmapped = BudgetLine::active()->where('is_header', false)
    ->whereNull('faculty_id')
    ->whereNotIn('id', $reached)
    ->count();
check(
    'every genuinely unfed line offers a way to fix it (' . $unmapped . ')',
    (substr_count($html, 'bl-unmapped-btn') - 2) === $unmapped,
    (substr_count($html, 'bl-unmapped-btn') - 2) . ' buttons for ' . $unmapped . ' lines'
);
check(
    'mapped lines say what feeds them',
    substr_count($html, 'Fed by') > 0,
    substr_count($html, 'Fed by') . ' shown'
);

echo "\n== Lines fed by faculty routing, not by a mapping ==\n";

// One "First Instalment" category cannot name four tuition lines, so
// BudgetActualsService::addFees() routes tuition by
// fee -> enrolment -> programme -> faculty instead. Judging "is this fed?" on
// the mapping alone told three of those lines they would show zero while they
// were carrying millions.
$facultyLines = BudgetLine::whereNotNull('faculty_id')->orderBy('code')->get();
check('there are faculty-tagged lines to check', $facultyLines->isNotEmpty());

$budget = \App\Models\Budget::where('is_institutional', true)->orderByDesc('id')->first();
$actuals = app(\App\Services\BudgetActualsService::class)->forPeriod(
    optional($budget->start_date)->format('Y-m-d'),
    optional($budget->end_date)->format('Y-m-d')
);

$silentlyRich = [];
foreach ($facultyLines as $line) {
    $hasMapping = DefaultAccountMapping::where('budget_line_id', $line->id)->exists();
    $amount = (float) ($actuals['lines'][$line->id] ?? 0);

    // The premise of the whole fix: money arrives with no mapping in sight.
    if (!$hasMapping && $amount > 0) {
        $silentlyRich[] = $line->code . ' (' . number_format($amount) . ')';
    }
}
check(
    'a line with no mapping still collects tuition',
    $silentlyRich !== [],
    'none found — the faculty routing may have changed'
);

foreach ($facultyLines as $line) {
    $pos = strpos($html, '>' . $line->name . '<');
    $chunk = $pos === false ? '' : substr($html, $pos, 2200);

    check(
        $line->code . ' says how it is really fed',
        strpos($chunk, 'bl-faculty-fed') !== false
    );
    check(
        $line->code . ' is not told it will show zero',
        strpos($chunk, 'bl-unmapped-btn') === false
    );
}

check(
    'the tuition exception is explained on the screen',
    strpos($html, 'Tuition is the exception') !== false
);

echo "\n== Where a mapping came from ==\n";

$byLine = app(BudgetReconciliationService::class)->categoriesByLine();
$flat = collect($byLine)->flatten(1);

check('provenance is reported for every feeding category', $flat->every(fn ($f) => array_key_exists('seeded', $f)));
// Only category-backed mappings can appear here: payroll maps by ACCOUNT and
// carries no category, so it is reached through the ledger rather than through
// categoriesByLine().
check(
    'the seeded mappings are marked as supplied',
    $flat->where('seeded', true)->count() === DefaultAccountMapping::whereNull('created_by')
        ->whereNotNull('budget_line_id')
        ->whereNotNull('category_id')
        ->count()
);
check('the screen shows the badge', strpos($html, 'bl-origin-system') !== false);

// Read as "Capital contributionsystem", the badge looked like part of the
// category's name, and worse, like a padlock. It is neither.
check(
    'the badge does not run into the category name',
    strpos($html, '</span><span class="bl-origin') === false
        && strpos($html, 'class="bl-feeder"') !== false
);
check(
    'the badge says supplied, not something that sounds locked',
    strpos($html, '>supplied<') !== false
);

echo "\n== A supplied mapping is not a fixed one ==\n";

// A fed line used to offer nothing at all, which is what made a supplied
// mapping look unchangeable. Every fed line now opens the same modal.
$fedLines = BudgetLine::active()->where('is_header', false)
    ->whereIn('id', $reached)
    ->count();

check(
    'every fed line offers a way to change it (' . $fedLines . ')',
    (substr_count($html, 'bl-change-btn') - 2) === $fedLines,
    (substr_count($html, 'bl-change-btn') - 2) . ' buttons for ' . $fedLines . ' fed lines'
);
check(
    'the modal names what already feeds the line',
    strpos($html, 'id="mapCurrent"') !== false
);
check(
    'and says a supplied mapping can be changed',
    strpos($html, 'can be changed like any other') !== false
);

// A mapping made by a person must read as configured, not as shipped.
DB::beginTransaction();
try {
    $any = DefaultAccountMapping::whereNotNull('budget_line_id')->first();
    $any->created_by = $admin->id;
    $any->save();

    $after = app(BudgetReconciliationService::class)->categoriesByLine();
    $entry = collect($after[$any->budget_line_id] ?? [])->firstWhere('id', $any->category_id);

    check('a mapping touched by a person reads as configured', $entry && $entry['seeded'] === false);
} finally {
    DB::rollBack();
}

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
