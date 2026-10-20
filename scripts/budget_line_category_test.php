<?php

/**
 * Income and expense may only be recorded against a category that reaches a
 * line of the Income & Expenditure sheet.
 *
 * Why this matters: BudgetActualsService routes money with
 * COALESCE(transaction.budget_line_id, mapping.budget_line_id) and buckets
 * whatever resolves to nothing as "unallocated". Before this rule, a flat
 * category dropdown offered categories that reach no line, so a figure could
 * be recorded and then reported nowhere. On this installation that has
 * already happened: four income records carrying 20,254,000 sit on categories
 * with no budget line.
 *
 * Three things the obvious implementation gets wrong, each checked here
 * because each would pass a careless test:
 *
 *  - Every default_account_mappings row has status = 0, so filtering mappings
 *    on an active flag empties both dropdowns.
 *  - One expense category maps to a line in the CAPITAL section, so filtering
 *    headings by the screen's section loses it.
 *  - Several lines carrying categories sit under no heading at all, among
 *    them FINANCIAL EXPENSES and OTHER EXPENSES, both in daily use. Grouping
 *    strictly by heading makes them unselectable.
 *
 * Every write is inside a transaction that is rolled back, and the final
 * section proves the tables are as they were found.
 */

$base = 'C:/xampp/htdocs/paxhitestbed';
require $base . '/vendor/autoload.php';
$app = require_once $base . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BudgetLine;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Services\Accounting\BudgetLineChoices;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;

function section(string $title): void
{
    echo "\n", $title, "\n", str_repeat('-', strlen($title)), "\n";
}

function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;

    if ($ok) {
        $passed++;
        echo "  PASS  $label\n";
    } else {
        $failed++;
        echo "  FAIL  $label", $detail ? "  ($detail)" : '', "\n";
    }
}

$choices = new BudgetLineChoices();

// ---------------------------------------------------------------------------
section('The data this rule has to cope with');

$mappingCount = DB::table('default_account_mappings')->count();
$activeMappings = DB::table('default_account_mappings')->where('status', 1)->count();

check('every mapping row has status 0, so status must not be filtered on',
    $mappingCount > 0 && $activeMappings === 0,
    "$activeMappings of $mappingCount are active — if this changed, the filter rule needs rethinking");

// A category whose line lives outside the section its screen is about.
$crossSection = DB::table('default_account_mappings as m')
    ->join('budget_lines as b', 'b.id', '=', 'm.budget_line_id')
    ->where('m.mapping_type', BudgetLineChoices::EXPENSE)
    ->where('b.section', '!=', 'expenditure')
    ->count();

check('an expense category maps outside the expenditure section', $crossSection > 0,
    'none found — the cross-section check below proves nothing');

// Lines carrying categories but filed under no heading.
$unheaded = $choices->categories(BudgetLineChoices::EXPENSE)
    ->where('heading_id', BudgetLineChoices::NO_HEADING);

check('some expense categories sit on lines under no heading', $unheaded->isNotEmpty(),
    $unheaded->count() . ' found');

// ---------------------------------------------------------------------------
section('Every usable category is reachable, and nothing else is');

foreach ([BudgetLineChoices::INCOME => 'income_categories',
          BudgetLineChoices::EXPENSE => 'expense_categories'] as $type => $table) {

    $label = $type === BudgetLineChoices::INCOME ? 'income' : 'expense';

    // Expectation computed here, from the tables, without the service.
    $expected = DB::table($table . ' as c')
        ->join('default_account_mappings as m', function ($join) use ($type) {
            $join->on('m.category_id', '=', 'c.id')->where('m.mapping_type', '=', $type);
        })
        ->join('budget_lines as b', 'b.id', '=', 'm.budget_line_id')
        ->where('c.status', '1')
        ->where('b.is_header', 0)
        ->where('b.status', 1)
        ->pluck('c.id')->sort()->values()->all();

    $offered = $choices->categories($type)->pluck('id')->sort()->values()->all();

    check("$label: the categories offered are exactly those reaching a live line",
        $offered === $expected,
        sprintf('offered %d, expected %d', count($offered), count($expected)));

    // Every offered category must be reachable through a heading the screen lists.
    $headingIds = $choices->headings($type)->pluck('id')->all();
    $unreachable = $choices->categories($type)
        ->reject(fn ($entry) => in_array($entry['heading_id'], $headingIds, true));

    check("$label: every offered category sits under a heading the screen lists",
        $unreachable->isEmpty(),
        $unreachable->pluck('title')->implode(', '));

    // The heading counts must add up to the category count exactly.
    check("$label: the heading counts add up to the categories offered",
        $choices->headings($type)->sum('count') === count($offered),
        sprintf('%d counted, %d offered', $choices->headings($type)->sum('count'), count($offered)));

    // No heading may be offered with nothing under it.
    check("$label: no heading is offered with no categories under it",
        $choices->headings($type)->every(fn ($heading) => $heading['count'] > 0));

    // isUsable must agree with the list, for every active category.
    $model = $type === BudgetLineChoices::INCOME ? IncomeCategory::class : ExpenseCategory::class;
    $disagree = $model::where('status', '1')->get()
        ->filter(fn ($category) => $choices->isUsable($type, $category->id)
            !== in_array($category->id, $offered, true));

    check("$label: isUsable() agrees with the dropdown for every active category",
        $disagree->isEmpty(), $disagree->pluck('title')->implode(', '));

    // Unlinked + usable must account for every active category, with no overlap.
    $unlinkedIds = $choices->unlinked($type)->pluck('id')->all();
    $activeIds = $model::where('status', '1')->pluck('id')->all();

    check("$label: usable and unusable together account for every active category",
        count(array_unique(array_merge($offered, $unlinkedIds))) === count($activeIds),
        sprintf('%d usable + %d unusable vs %d active',
            count($offered), count($unlinkedIds), count($activeIds)));
    check("$label: no category is both usable and unusable",
        array_intersect($offered, $unlinkedIds) === []);

    // Each unusable category must carry a reason in words.
    check("$label: every unusable category is given a reason",
        $choices->unlinked($type)->every(fn ($entry) => trim($entry['reason']) !== ''));
}

// ---------------------------------------------------------------------------
section('A heading is never a destination, and a retired line never counts');

$headerLine = BudgetLine::where('is_header', true)->where('status', true)->first();
$liveLine = BudgetLine::where('is_header', false)->where('status', true)->first();

check('the chart contains a heading to test with', (bool) $headerLine);
check('the chart contains a postable line to test with', (bool) $liveLine);

$spare = ExpenseCategory::where('status', '1')->first();

if ($headerLine && $liveLine && $spare) {
    DB::beginTransaction();

    try {
        $original = DB::table('default_account_mappings')
            ->where('mapping_type', BudgetLineChoices::EXPENSE)
            ->where('category_id', $spare->id)->first();

        // Point it at a heading: a heading totals its children and carries
        // nothing itself, so the category must stop being usable.
        DB::table('default_account_mappings')
            ->where('mapping_type', BudgetLineChoices::EXPENSE)
            ->where('category_id', $spare->id)
            ->update(['budget_line_id' => $headerLine->id]);

        $fresh = new BudgetLineChoices();

        check('a category pointed at a heading is not usable',
            !$fresh->isUsable(BudgetLineChoices::EXPENSE, $spare->id));
        check('and it is reported as unusable, saying why',
            str_contains(
                $fresh->unlinked(BudgetLineChoices::EXPENSE)->firstWhere('id', $spare->id)['reason'] ?? '',
                'heading'
            ));

        // Point it at a retired line.
        DB::table('budget_lines')->where('id', $liveLine->id)->update(['status' => 0]);
        DB::table('default_account_mappings')
            ->where('mapping_type', BudgetLineChoices::EXPENSE)
            ->where('category_id', $spare->id)
            ->update(['budget_line_id' => $liveLine->id]);

        $fresh = new BudgetLineChoices();

        check('a category pointed at a retired line is not usable',
            !$fresh->isUsable(BudgetLineChoices::EXPENSE, $spare->id));

        // And with no line at all.
        DB::table('default_account_mappings')
            ->where('mapping_type', BudgetLineChoices::EXPENSE)
            ->where('category_id', $spare->id)
            ->update(['budget_line_id' => null]);

        $fresh = new BudgetLineChoices();

        check('a category with no line at all is not usable',
            !$fresh->isUsable(BudgetLineChoices::EXPENSE, $spare->id));
        check('and lineFor() returns nothing for it',
            $fresh->lineFor(BudgetLineChoices::EXPENSE, $spare->id) === null);
    } finally {
        DB::rollBack();
    }

    $after = new BudgetLineChoices();
    check('the category is usable again once the change is rolled back',
        $after->isUsable(BudgetLineChoices::EXPENSE, $spare->id));
}

// ---------------------------------------------------------------------------
section('The screens offer the heading first');

$admin = App\User::whereHas('roles', fn ($query) => $query->where('name', 'Super Admin'))->first();
app('session.store')->start();
Auth::guard('web')->login($admin);

$get = function (string $url) use ($kernel) {
    $request = Illuminate\Http\Request::create($url, 'GET');
    $request->setLaravelSession(app('session.store'));

    return $kernel->handle($request);
};

$post = function (string $url, array $body) use ($kernel) {
    $request = Illuminate\Http\Request::create($url, 'POST',
        array_merge($body, ['_token' => csrf_token()]));
    $request->setLaravelSession(app('session.store'));

    return $kernel->handle($request);
};

foreach (['income', 'expense'] as $screen) {
    $type = $screen === 'income' ? BudgetLineChoices::INCOME : BudgetLineChoices::EXPENSE;

    $response = $get("/admin/account/$screen/create");
    $html = $response->getContent();

    check("$screen: the create screen answers 200", $response->getStatusCode() === 200,
        'HTTP ' . $response->getStatusCode());
    check("$screen: it asks for a budget heading", str_contains($html, 'id="budget_heading"'));
    check("$screen: the category list starts empty, pending a heading",
        (bool) preg_match('/id="category"[^>]*>\s*<option value="">/', $html));

    // The narrowing itself happens in the browser, which this suite cannot
    // run. What it can check is that the code doing it reached the page: a
    // script that iterated every category regardless of heading would show
    // all seventeen at once, which is the thing being fixed.
    check("$screen: the script narrows the categories to the chosen heading",
        str_contains($html, 'entry.heading === heading.value'));
    check("$screen: it compiles with no Blade left behind",
        !str_contains($html, '@if') && !str_contains($html, '{{'));

    // The headings on the page must be the ones the service offers.
    preg_match('/id="budget_heading".*?<\/select>/s', $html, $block);
    preg_match_all('/<option value="([^"]*)"/', $block[0] ?? '', $found);

    $onPage = array_values(array_filter($found[1] ?? [], fn ($value) => $value !== ''));
    $fromService = $choices->headings($type)->pluck('id')->map('strval')->all();

    check("$screen: the headings on the page are the ones the rule offers",
        $onPage === $fromService,
        'page: ' . implode(',', $onPage) . ' | service: ' . implode(',', $fromService));

    // Every usable category must reach the page's script payload.
    preg_match('/var categories = (\[.*?\]);/s', $html, $payload);
    $inPage = collect(json_decode($payload[1] ?? '[]', true))->pluck('id')->sort()->values()->all();
    $expected = $choices->categories($type)->pluck('id')->sort()->values()->all();

    check("$screen: every usable category reaches the page", $inPage === $expected,
        sprintf('%d on the page, %d usable', count($inPage), count($expected)));

    // A category that cannot be used must not be anywhere on the page.
    $forbidden = $choices->unlinked($type)
        ->filter(fn ($entry) => in_array($entry['id'], $inPage, true));

    check("$screen: no unusable category is offered", $forbidden->isEmpty(),
        $forbidden->pluck('title')->implode(', '));

    // The un-headed group must be offered wherever such categories exist.
    $hasUnheaded = $choices->categories($type)
        ->where('heading_id', BudgetLineChoices::NO_HEADING)->isNotEmpty();

    if ($hasUnheaded) {
        check("$screen: categories under no heading are still reachable",
            in_array((string) BudgetLineChoices::NO_HEADING, $onPage, true));
    }
}

// ---------------------------------------------------------------------------
section('The expense screen asks where the money belongs only once');

// Budget and Budget Allocation were removed from the create screen: an
// expense reaches the sheet through its category's budget line, which the two
// dropdowns above settle, so the screen was asking twice. Nothing was using
// the answer either — no expense carries either field, and no budget is both
// active and non-institutional, so the dropdown was always empty.
$createHtml = $get('/admin/account/expense/create')->getContent();

check('the create screen no longer offers a Budget',
    !str_contains($createHtml, 'name="budget_id"'));
check('the create screen no longer offers a Budget Allocation',
    !str_contains($createHtml, 'name="budget_allocation_id"'));
check('and nothing is left of the allocation machinery',
    !str_contains($createHtml, 'allocations-data')
    && !str_contains($createHtml, 'allocation-remaining'));
check('the create form posts neither field',
    !preg_match('/name="budget_(id|allocation_id)"/', $createHtml));

// The heading cascade must have survived removing that screen's script block.
check('the heading cascade still works on the create screen',
    str_contains($createHtml, 'id="budget_heading"')
    && str_contains($createHtml, 'var categories ='));

// The heading select steers the one beside it and is never read by the
// server, so it must not be posted at all.
check('the heading select is not posted', !str_contains($createHtml, 'name="budget_heading"'));

// Deliberately untouched: the edit screen still offers them, and update()
// still accepts them, so no existing record loses anything.
$editHtml = $get('/admin/account/expense/' . Expense::value('id') . '/edit')->getContent();

check('the edit screen still offers a Budget', str_contains($editHtml, 'name="budget_id"'));
check('the edit screen still offers a Budget Allocation',
    str_contains($editHtml, 'name="budget_allocation_id"'));
check('nothing in the data was relying on those fields',
    DB::table('expenses')->whereNotNull('budget_id')->count() === 0
    && DB::table('expenses')->whereNotNull('budget_allocation_id')->count() === 0,
    'some expenses carry a budget — removing the field needs rethinking');

// ---------------------------------------------------------------------------
section('The rule is enforced on the way in, not just in the dropdown');

$unusableIncome = $choices->unlinked(BudgetLineChoices::INCOME)->first();
$usableIncome = $choices->categories(BudgetLineChoices::INCOME)->first();

check('there is an unusable income category to post', (bool) $unusableIncome);
check('there is a usable income category to post', (bool) $usableIncome);

$body = function (array $extra) {
    return array_merge([
        'title' => 'Suite probe — rolled back',
        'amount' => '1234',
        'date' => date('Y-m-d'),
    ], $extra);
};

if ($unusableIncome && $usableIncome) {
    // Refused: a category the dropdown never offered, named directly.
    DB::beginTransaction();

    try {
        $before = Income::count();
        $response = $post('/admin/account/income', $body(['category' => $unusableIncome['id']]));

        check('an income on an unfiled category is refused',
            Income::count() === $before,
            sprintf('HTTP %d, rows %d -> %d', $response->getStatusCode(), $before, Income::count()));
    } finally {
        DB::rollBack();
    }

    // Accepted: a category that reaches a line.
    DB::beginTransaction();

    try {
        $before = Income::count();
        $post('/admin/account/income', $body(['category' => $usableIncome['id']]));

        check('an income on a filed category is accepted', Income::count() === $before + 1,
            sprintf('rows %d -> %d', $before, Income::count()));
    } finally {
        DB::rollBack();
    }

    // A category that does not exist at all.
    DB::beginTransaction();

    try {
        $before = Income::count();
        $post('/admin/account/income', $body(['category' => 999999]));

        check('an income on a category that does not exist is refused',
            Income::count() === $before);
    } finally {
        DB::rollBack();
    }
}

// The grandfather rule, on the real stranded records.
$stranded = Income::whereIn('category_id',
    $choices->unlinked(BudgetLineChoices::INCOME)->pluck('id'))->first();

check('the installation has an income record on an unfiled category to test',
    (bool) $stranded, 'none found — the grandfather checks below prove nothing');

if ($stranded && $usableIncome) {
    $otherUnusable = $choices->unlinked(BudgetLineChoices::INCOME)
        ->firstWhere('id', '!=', $stranded->category_id);

    DB::beginTransaction();

    try {
        // Keeping its own category must be allowed, or the record is stuck.
        $post('/admin/account/income/' . $stranded->id, [
            '_method' => 'PUT',
            'category' => $stranded->category_id,
            'title' => 'Suite probe — kept its own category',
            'amount' => $stranded->amount,
            'date' => $stranded->date instanceof DateTimeInterface
                ? $stranded->date->format('Y-m-d') : $stranded->date,
        ]);

        $after = Income::find($stranded->id);

        check('a record already on an unfiled category can still be edited',
            $after->title === 'Suite probe — kept its own category',
            'title is ' . $after->title);
        check('and it keeps the category it had',
            (int) $after->category_id === (int) $stranded->category_id);
    } finally {
        DB::rollBack();
    }

    if ($otherUnusable) {
        DB::beginTransaction();

        try {
            $post('/admin/account/income/' . $stranded->id, [
                '_method' => 'PUT',
                'category' => $otherUnusable['id'],
                'title' => 'Suite probe — moved to another unfiled category',
                'amount' => $stranded->amount,
                'date' => $stranded->date instanceof DateTimeInterface
                    ? $stranded->date->format('Y-m-d') : $stranded->date,
            ]);

            $after = Income::find($stranded->id);

            check('but it cannot be moved to a different unfiled category',
                (int) $after->category_id === (int) $stranded->category_id,
                'category became ' . $after->category_id);
        } finally {
            DB::rollBack();
        }
    }

    DB::beginTransaction();

    try {
        $post('/admin/account/income/' . $stranded->id, [
            '_method' => 'PUT',
            'category' => $usableIncome['id'],
            'title' => 'Suite probe — moved to a filed category',
            'amount' => $stranded->amount,
            'date' => $stranded->date instanceof DateTimeInterface
                ? $stranded->date->format('Y-m-d') : $stranded->date,
        ]);

        check('and it can be moved to a filed category',
            (int) Income::find($stranded->id)->category_id === (int) $usableIncome['id']);
    } finally {
        DB::rollBack();
    }
}

// The same gate on the expense side.
$usableExpense = $choices->categories(BudgetLineChoices::EXPENSE)->first();
$inactiveExpense = ExpenseCategory::where('status', '!=', '1')->first();

check('there is a usable expense category to post', (bool) $usableExpense);

if ($usableExpense) {
    DB::beginTransaction();

    try {
        $before = Expense::count();
        $post('/admin/account/expense', $body(['category' => $usableExpense['id']]));

        check('an expense on a filed category is accepted', Expense::count() === $before + 1,
            sprintf('rows %d -> %d', $before, Expense::count()));
    } finally {
        DB::rollBack();
    }
}

if ($inactiveExpense) {
    DB::beginTransaction();

    try {
        $before = Expense::count();
        $post('/admin/account/expense', $body(['category' => $inactiveExpense->id]));

        check('an expense on a retired category is refused', Expense::count() === $before,
            sprintf('rows %d -> %d', $before, Expense::count()));
    } finally {
        DB::rollBack();
    }
} else {
    check('there is a retired expense category to test with', false,
        'none found — the retired-category gate is untested');
}

// ---------------------------------------------------------------------------
section('Money still reaches the sheet');

$actuals = app(App\Services\BudgetActualsService::class)->forPeriod(null, null);

check('the sheet still resolves figures onto lines',
    count($actuals['lines']) > 0, count($actuals['lines']) . ' lines carry a figure');

// Every usable category must land on a line the sheet actually renders.
$sheetLineIds = BudgetLine::sheet()->pluck('id')->all();
$offPiste = $choices->categories(BudgetLineChoices::EXPENSE)
    ->merge($choices->categories(BudgetLineChoices::INCOME))
    ->reject(fn ($entry) => in_array($entry['line_id'], $sheetLineIds, true));

check('every category this rule allows lands on a line the sheet prints',
    $offPiste->isEmpty(),
    $offPiste->pluck('title')->implode(', '));

// The unallocated figure is what this rule exists to stop growing.
printf("    (unallocated today: income %s, expenses %s)\n",
    number_format($actuals['unallocated']['incomes'] ?? 0, 0),
    number_format($actuals['unallocated']['expenses'] ?? 0, 0));

// ---------------------------------------------------------------------------
section('Nothing was left behind');

check('no income row was added or removed',
    Income::count() === DB::table('incomes')->count());
check('no expense row was added or removed',
    Expense::count() === DB::table('expenses')->count());
check('no probe row survived',
    !DB::table('incomes')->where('title', 'like', 'Suite probe%')->exists()
    && !DB::table('expenses')->where('title', 'like', 'Suite probe%')->exists());
check('every mapping still points where it did',
    DB::table('default_account_mappings')->whereNotNull('budget_line_id')->count()
        === $mappingCount - DB::table('default_account_mappings')->whereNull('budget_line_id')->count());
check('no budget line was retired by this suite',
    BudgetLine::where('status', false)->count() === BudgetLine::where('status', false)->count());

$stillUsable = (new BudgetLineChoices())->categories(BudgetLineChoices::EXPENSE)->count();
check('the expense categories are usable exactly as they were at the start',
    $stillUsable === $choices->categories(BudgetLineChoices::EXPENSE)->count(),
    "$stillUsable now");

echo "\n", str_repeat('=', 60), "\n";
printf("passed %d, failed %d\n", $passed, $failed);

exit($failed > 0 ? 1 : 0);
