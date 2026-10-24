<?php

/**
 * The payroll taxes match the DGI barème, and the books balance.
 *
 * WHAT WAS WRONG. The income tax group held six brackets carrying isolated
 * sample points -- 89 900-90 000, 109 900-110 000, and so on -- with holes
 * between them. Bracket selection ignores max_amount and takes the band below,
 * so every salary that did not land exactly on a sample point was charged the
 * previous point's figure, and nothing above 180 000 was charged more than
 * 7 929. Against the barème that over-withheld from most of the staff and
 * under-withheld from one by 532 a month. Separately, the tax stored on a
 * payroll was whatever the browser posted: the server never worked it out, so
 * one payroll holds 37 160 that nothing can re-derive.
 *
 * WHAT HOLDS NOW.
 *
 *  - The configured brackets reproduce the printed barème, at points inside
 *    every band and either side of every boundary.
 *  - No salary can fall in a hole: every one selects a band, across the whole
 *    range in the barème's own 250 steps.
 *  - The derived taxes follow their rules: the council tax is a tenth of the
 *    income tax, the housing fund 1% of pay with 1.5% from the employer, and
 *    the social contribution never exceeds its monthly ceiling.
 *  - The server, not the page, decides the tax. A posted figure that does not
 *    match is refused, and what is stored is what the rates give.
 *  - A payroll and its itemisation are written together, and the itemisation
 *    adds up to the tax charged.
 *  - Each tax is owed to the right authority: social contributions to 431,
 *    State withholdings to 443, and together they account for the whole.
 *  - A month with no rates in force refuses the payroll instead of charging
 *    nothing.
 *
 * Writes happen inside transactions that are rolled back.
 */

$base = 'C:/xampp/htdocs/paxhitestbed';
require $base . '/vendor/autoload.php';
$app = require_once $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Payroll;
use App\Models\TaxGroup;
use App\Models\TaxSetting;
use App\Services\PayrollTaxBreakdownService;
use Carbon\Carbon;
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
        echo "  FAIL  $label", $detail !== '' ? "  ($detail)" : '', "\n";
    }
}

/**
 * Resolved by code, not written as literal ids.
 *
 * These were the numbers 1, 4, 5, 142 and 145, which are this installation's
 * auto-increment values and nobody else's. That made the suite unrunnable on
 * another school — and it broke here the moment the groups were rebuilt, when
 * the audio-visual group stopped being number 5 and the checks started reading
 * an empty group and calling it a failure.
 *
 * The code and the OHADA account number are the stable keys, and they are what
 * the seeder and the command both key on.
 */
define('GROUP_PIT', (int) DB::table('tax_groups')->where('code', 'PIT')->value('id'));
define('GROUP_CFC', (int) DB::table('tax_groups')->where('code', 'HLF')->value('id'));
define('GROUP_CRTV', (int) DB::table('tax_groups')->where('code', 'CRTV')->value('id'));
define('ACCOUNT_SOCIAL', (int) DB::table('chart_of_accounts')->where('account_code', '431')->value('id'));
define('ACCOUNT_STATE', (int) DB::table('chart_of_accounts')->where('account_code', '443')->value('id'));

$month = Carbon::parse('2026-10-01');
$breakdown = app(PayrollTaxBreakdownService::class);
$pit = TaxGroup::find(GROUP_PIT);

/** Employee and employer totals for a salary, through the live engine. */
$charge = function (float $salary) use ($breakdown, $month): array {
    return $breakdown->totals($breakdown->forSalary($salary, $month));
};

/** One named line of the breakdown, or null. */
$lineFor = function (float $salary, string $needle) use ($breakdown, $month): ?array {
    foreach ($breakdown->forSalary($salary, $month) as $line) {
        if (stripos($line['label'], $needle) !== false) {
            return $line;
        }
    }

    return null;
};

// ---------------------------------------------------------------------------
section('The brackets reproduce the printed barème');

// Read off the barème itself. Chosen to sit inside each of the five bands and
// immediately either side of all four boundaries, which is where a wrong rate
// or a wrong threshold shows up first. The table rounds to the franc.
$bareme = [
    63250 => 0,            // below the threshold
    63500 => 12,           // the first franc of tax
    90000 => 1755,
    100000 => 2413,
    110000 => 3071,
    120000 => 3729,
    170000 => 7019,
    180000 => 7677,
    316500 => 16659,       // band 1, just inside the edge
    316750 => 16680,       // band 2, just past it
    443250 => 29165,
    500000 => 38500,
    696500 => 70824,
    1000000 => 140717,
    1785750 => 321675,     // band 5, past the social ceiling
    3000000 => 619167,
];

foreach ($bareme as $salary => $expected) {
    $got = (int) round($pit->calculateTax($salary)['amount']);

    check(sprintf('income tax on %s is %s', number_format($salary), number_format($expected)),
        abs($got - $expected) <= 1, 'got ' . number_format($got));
}

// ---------------------------------------------------------------------------
section('No salary can fall in a hole');

$holes = [];
$unbanded = [];

for ($salary = 0; $salary <= 3200000; $salary += 250) {
    if ($pit->gapFor((float) $salary) !== null) {
        $holes[] = $salary;
    }

    if ($salary >= 63500 && $pit->applicableBracket((float) $salary) === null) {
        $unbanded[] = $salary;
    }
}

check('no gap anywhere from 0 to 3,200,000, in the barème\'s own 250 steps',
    $holes === [], count($holes) . ' gaps, first at ' . number_format($holes[0] ?? 0));
check('every taxable salary selects a band',
    $unbanded === [], count($unbanded) . ' without one');

$staff = App\User::where('status', '1')->where('basic_salary', '>', 0)->get();

check('there is staff to check against', $staff->count() > 0);

$staffHoles = $staff->filter(fn ($u) => $pit->gapFor((float) $u->basic_salary) !== null);

check('no current salary falls in a hole', $staffHoles->isEmpty(),
    $staffHoles->pluck('name')->implode(', '));

// The tax must never go down as pay goes up: that is what the old sample-point
// configuration did at every hole, and it is the shape of the original error.
$previous = -1;
$reversals = [];

for ($salary = 0; $salary <= 3200000; $salary += 250) {
    $tax = (float) $pit->calculateTax($salary)['amount'];

    if ($tax < $previous - 0.01) {
        $reversals[] = $salary;
    }

    $previous = $tax;
}

check('tax never falls as pay rises', $reversals === [],
    count($reversals) . ' reversals, first at ' . number_format($reversals[0] ?? 0));

// ---------------------------------------------------------------------------
section('The derived taxes follow their own rules');

foreach ([70000, 100000, 180000, 500000, 1000000, 2000000] as $salary) {
    $tax = (float) $pit->calculateTax($salary)['amount'];
    $cac = $lineFor((float) $salary, 'Council');

    check(sprintf('council tax on %s is a tenth of the income tax', number_format($salary)),
        $cac !== null && abs($cac['employee'] - $tax / 10) <= 1,
        sprintf('income tax %s, council %s', number_format($tax, 2),
            $cac === null ? 'absent' : number_format($cac['employee'], 2)));
}

// It used to be gated on salary (62 000 - 5 000 000), so a salary above five
// million escaped the surcharge entirely.
$highCac = $lineFor(8000000.0, 'Council');
$highPit = (float) $pit->calculateTax(8000000)['amount'];

check('the council tax still applies above five million',
    $highCac !== null && abs($highCac['employee'] - $highPit / 10) <= 1,
    $highCac === null ? 'no council tax charged' : number_format($highCac['employee'], 2));

foreach ([60000, 100000, 1000000] as $salary) {
    $cfc = $lineFor((float) $salary, 'Housing');

    check(sprintf('housing fund on %s is 1%% employee and 1.5%% employer', number_format($salary)),
        $cfc !== null
        && abs($cfc['employee'] - $salary * 0.01) <= 1
        && abs($cfc['employer'] - $salary * 0.015) <= 1,
        $cfc === null ? 'absent' : sprintf('%s / %s',
            number_format($cfc['employee'], 2), number_format($cfc['employer'], 2)));
}

// The barème puts the employer at 2.5% of pay, which is the housing fund's
// 1.5% and the employment fund's 1%.
foreach ([100000, 180000, 500000] as $salary) {
    $cfc = $lineFor((float) $salary, 'Housing');
    $fne = $lineFor((float) $salary, 'Employment');
    $both = ($cfc['employer'] ?? 0) + ($fne['employer'] ?? 0);

    check(sprintf('the employer pays 2.5%% of %s to those two funds', number_format($salary)),
        abs($both - $salary * 0.025) <= 1, number_format($both, 2));
}

// ---------------------------------------------------------------------------
section('The social contribution respects its ceiling');

$capAmount = 75000;
$capBase = 1785715;

$below = $lineFor((float) ($capBase - 1), 'Insurance');
$above = $lineFor((float) $capBase, 'Insurance');
$wayAbove = $lineFor(10000000.0, 'Insurance');

check('just below the ceiling it is still 4.2%',
    $below !== null && abs($below['employee'] - ($capBase - 1) * 0.042) <= 1,
    $below === null ? 'absent' : number_format($below['employee'], 2));
check('at the ceiling it is the flat amount',
    $above !== null && abs($above['employee'] - $capAmount) <= 1,
    $above === null ? 'absent' : number_format($above['employee'], 2));
check('far above the ceiling it is still the flat amount',
    $wayAbove !== null && abs($wayAbove['employee'] - $capAmount) <= 1,
    $wayAbove === null ? 'absent' : number_format($wayAbove['employee'], 2));

// Exactly one of the two rows may apply, or the contribution doubles.
$overCap = [];

for ($salary = 1700000; $salary <= 1900000; $salary += 250) {
    $line = $lineFor((float) $salary, 'Insurance');

    if ($line !== null && $line['employee'] > $capAmount + 1) {
        $overCap[] = $salary;
    }
}

check('the contribution never exceeds the ceiling across the changeover',
    $overCap === [], count($overCap) . ' salaries over it');

// ---------------------------------------------------------------------------
section('Every tax is owed to the right authority');

$lines = $breakdown->forSalary(180000.0, $month);

check('every line names a liability account',
    collect($lines)->every(fn ($l) => !empty($l['liability_account_id'])),
    collect($lines)->filter(fn ($l) => empty($l['liability_account_id']))->pluck('label')->implode(', '));

$social = collect($lines)->filter(fn ($l) => $l['liability_account_id'] == ACCOUNT_SOCIAL);
$state = collect($lines)->filter(fn ($l) => $l['liability_account_id'] == ACCOUNT_STATE);

check('the social contributions are owed to 431', $social->count() > 0);
check('the State withholdings are owed to 443', $state->count() > 0);

$totals = $breakdown->totals($lines);
$split = $social->sum('employee') + $social->sum('employer')
    + $state->sum('employee') + $state->sum('employer');

check('the two authorities account for the whole charge',
    abs($split - ($totals['employee'] + $totals['employer'])) < 0.01,
    sprintf('%s vs %s', number_format($split, 2),
        number_format($totals['employee'] + $totals['employer'], 2)));

// ---------------------------------------------------------------------------
section('The server decides the tax, not the page');

$source = file_get_contents($base . '/app/Http/Controllers/Admin/PayrollController.php');

check('the stored tax is not the posted one',
    !preg_match("/'tax'\s*=>\s*\\\$request->tax/", $source),
    "store() still writes \$request->tax");
check('store() recomputes it',
    preg_match('/function store.*?authoritativeTax\(/s', $source) === 1);
check('update() recomputes it too',
    preg_match('/function update.*?authoritativeTax\(/s', $source) === 1);
check('a figure that disagrees is refused',
    str_contains($source, 'does not match the configured rates'));
// Exercised rather than grepped: the figure this returns is what goes into the
// books, and a month with no income tax in force must raise rather than come
// back as zero. An undated group from an earlier setup answers for every
// month, so this is also the check that the refusal looks at the income tax
// specifically instead of at "any group at all".
$authority = new ReflectionMethod(App\Http\Controllers\Admin\PayrollController::class, 'authoritativeTax');
$authority->setAccessible(true);
$controller = (new ReflectionClass(App\Http\Controllers\Admin\PayrollController::class))
    ->newInstanceWithoutConstructor();

$earliest = TaxSetting::where('tax_group_id', GROUP_PIT)->where('status', 1)->min('effective_from');
$beforeRates = Carbon::parse($earliest)->subMonth()->startOfMonth()->toDateString();

$refused = false;
$charged = null;

try {
    $charged = $authority->invoke($controller, (int) $staff->first()->id, 180000.0, $beforeRates);
} catch (\RuntimeException $e) {
    $refused = str_contains($e->getMessage(), 'in force');
}

check('a month with no income tax in force is refused, not charged nothing',
    $refused, $charged === null ? 'raised the wrong error' : 'charged ' . number_format($charged['employee'], 2));

check('and a month that does have rates is charged',
    $authority->invoke($controller, (int) $staff->first()->id, 180000.0, $month->toDateString())['employee'] > 0);

// Teeth: break the table on purpose and prove the refusal has them.
//
// The screen has warned about an uncovered salary for a while and never
// stopped anyone, so the payroll was generated and the warning scrolled past.
// This is the check that the warning is now a refusal — and it is worth
// having precisely because the table is contiguous today, so nothing else
// would notice if the refusal quietly stopped working.
DB::beginTransaction();

try {
    $band = TaxSetting::where('tax_group_id', GROUP_PIT)->where('status', 1)
        ->orderBy('min_amount')->first();
    $ceiling = (float) $band->max_amount;

    $band->max_amount = $ceiling - 20000;
    $band->save();

    $insideHole = $ceiling - 10000;

    $refusedGap = false;
    $chargedAnyway = null;

    try {
        $chargedAnyway = $authority->invoke($controller,
            (int) $staff->first()->id, $insideHole, $month->toDateString());
    } catch (\RuntimeException $e) {
        $refusedGap = str_contains($e->getMessage(), 'not covered');
    }

    check('pay in an uncovered range is refused, not charged the band below',
        $refusedGap,
        $chargedAnyway === null
            ? 'raised the wrong error'
            : 'charged ' . number_format($chargedAnyway['employee'], 2));

    // And pay that is still covered must go through, or the refusal is simply
    // blocking everything.
    $stillWorks = true;

    try {
        $authority->invoke($controller, (int) $staff->first()->id, 100000.0, $month->toDateString());
    } catch (\RuntimeException $e) {
        $stillWorks = false;
    }

    check('while pay that is still covered goes through', $stillWorks);
} finally {
    DB::rollBack();
}

check('the table is whole again after the rollback',
    TaxGroup::find(GROUP_PIT)->coverageGaps()['gaps'] === []);

$afterRestore = $authority->invoke($controller,
    (int) $staff->first()->id, 180000.0, $month->toDateString());

check('and the charge is back to what it was',
    abs($afterRestore['employee'] - 21005) < 1, number_format($afterRestore['employee'], 2));
check('the breakdown is written with the payroll',
    preg_match('/function store.*?->record\(\$payroll\)/s', $source) === 1);
check('a paid payroll cannot be edited',
    str_contains($source, 'has been paid and can no longer be edited'));
check('a failure in store() rolls back',
    preg_match('/catch\(\\\\Throwable \$e\)\{\s*.*?DB::rollBack\(\)/s', $source) === 1);

// ---------------------------------------------------------------------------
section('A payroll and its itemisation agree');

DB::beginTransaction();

try {
    $subject = $staff->firstWhere('basic_salary', '>', 100000) ?: $staff->last();
    $salary = (float) $subject->basic_salary;
    $authoritative = $charge($salary);

    $payroll = Payroll::create([
        'user_id' => $subject->id,
        'basic_salary' => $salary,
        'salary_type' => 1,
        'total_earning' => $salary,
        'total_allowance' => 0,
        'bonus' => 0,
        'total_deduction' => 0,
        'gross_salary' => $salary,
        'tax' => round($authoritative['employee'], 2),
        'employer_tax' => round($authoritative['employer'], 2),
        'net_salary' => round($salary - $authoritative['employee'], 2),
        'total_cost' => round($salary + $authoritative['employer'], 2),
        'salary_month' => $month->toDateString(),
        'status' => 0,
        'created_by' => $subject->id,
    ]);

    $recorded = $breakdown->record($payroll);

    check('the breakdown reconciles with the payroll', $recorded['reconciled']);
    check('a line was written for each tax charged', $recorded['written'] > 0,
        $recorded['written'] . ' lines');

    $stored = DB::table('payroll_tax_lines')->where('payroll_id', $payroll->id);

    check('the lines sum to the tax withheld',
        abs((float) $stored->sum('employee_amount') - (float) $payroll->tax) < 0.01,
        sprintf('%s vs %s', number_format((float) $stored->sum('employee_amount'), 2),
            number_format((float) $payroll->tax, 2)));
    check('and to the employer charge',
        abs((float) $stored->sum('employer_amount') - (float) $payroll->employer_tax) < 0.01,
        sprintf('%s vs %s', number_format((float) $stored->sum('employer_amount'), 2),
            number_format((float) $payroll->employer_tax, 2)));
    check('every line carries the authority it is owed to',
        $stored->whereNull('liability_account_id')->count() === 0);
    check('the net pay is the gross less the tax',
        abs((float) $payroll->net_salary - ($salary - (float) $payroll->tax)) < 0.01);
    check('the cost to the institution is the gross plus the employer charge',
        abs((float) $payroll->total_cost - ($salary + (float) $payroll->employer_tax)) < 0.01);

    // Itemising twice must not double the lines.
    $breakdown->record($payroll);

    check('recording it again replaces the lines rather than adding to them',
        DB::table('payroll_tax_lines')->where('payroll_id', $payroll->id)->count() === $recorded['written']);
} finally {
    DB::rollBack();
}

check('the test payroll was rolled back',
    Payroll::where('salary_month', $month->toDateString())->count() === 0,
    Payroll::where('salary_month', $month->toDateString())->count() . ' left behind');

// ---------------------------------------------------------------------------
section('The configuration is coherent');

$pitBrackets = TaxSetting::where('tax_group_id', GROUP_PIT)->where('status', 1)
    ->orderBy('min_amount')->get();

check('the income tax has five bands', $pitBrackets->count() === 5, $pitBrackets->count() . ' bands');
check('the first starts at zero', (float) $pitBrackets->first()->min_amount === 0.0);
check('they are all percentage bands', $pitBrackets->every(fn ($b) => $b->tax_type == 1));

$contiguous = true;

for ($i = 1; $i < $pitBrackets->count(); $i++) {
    if ((float) $pitBrackets[$i]->min_amount !== (float) $pitBrackets[$i - 1]->max_amount + 1) {
        $contiguous = false;
    }
}

check('each band begins where the one before it ends', $contiguous);

$crtv = TaxSetting::where('tax_group_id', GROUP_CRTV)->where('status', 1)->orderBy('min_amount')->get();

check('the audio-visual tax has eleven bands', $crtv->count() === 11, $crtv->count() . ' bands');
check('they are all fixed amounts', $crtv->every(fn ($b) => $b->tax_type == 2));
check('it reaches 13,000 at the top',
    abs((float) $crtv->last()->fixed_amount - 13000) < 0.01);

check('every active bracket is dated',
    TaxSetting::where('status', 1)->whereNull('effective_from')->count() === 0,
    TaxSetting::where('status', 1)->whereNull('effective_from')->count() . ' undated');
check('every active bracket names a liability account',
    TaxSetting::where('status', 1)->whereNull('liability_account_id')->count() === 0,
    TaxSetting::where('status', 1)->whereNull('liability_account_id')->count() . ' without one');

// The local development tax is excluded from this barème by its own title and
// was deliberately left alone; it must still be contiguous from zero.
$tdl = TaxSetting::where('tax_group_id', 2)->where('status', 1)->orderBy('min_amount')->get();

check('the local development tax was left intact', $tdl->count() === 10, $tdl->count() . ' bands');
check('and still covers from zero', (float) $tdl->first()->min_amount === 0.0);

// ---------------------------------------------------------------------------
section('A new school installs with this configuration, not the demo one');

// The seeder used to wipe tax_settings and insert four brackets from the
// vendor template — 0-5,000 at 0%, 5,001-10,000 at 5%, and so on — with no tax
// groups at all. Every installation started from that and was hand-corrected,
// which is how one school came to charge somebody on 100,000 the tax due on
// 90,000. These checks are what stop it coming back.
$seederSource = file_get_contents($base . '/database/seeders/TaxSettingSeeder.php');

/**
 * The file with its comments removed.
 *
 * The seeder's docblock quotes the destructive line it replaced, so that the
 * next reader knows what was there and why it went. Grepping the raw file
 * therefore finds the very thing being checked for, in the explanation of its
 * own removal — which is how this check failed the first time it ran.
 */
$codeOnly = function (string $source): string {
    $out = '';

    foreach (token_get_all($source) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        $out .= is_array($token) ? $token[1] : $token;
    }

    return $out;
};

check('the seeder no longer wipes the table',
    !preg_match("/DB::table\('tax_settings'\)->delete\(\)/", $codeOnly($seederSource)),
    'a seeder that empties a live table loses a configuration somebody built');
check('and no longer carries the vendor demo brackets',
    !str_contains($seederSource, "'max_amount' => '5000.00'"));
check('it reads the one shared table rather than repeating the numbers',
    str_contains($seederSource, 'CameroonTaxTable'));

$commandSource = file_get_contents($base . '/app/Console/Commands/ConfigureCameroonTax.php');

check('and so does the command, so the two cannot drift apart',
    str_contains($commandSource, 'CameroonTaxTable::INCOME_TAX')
    && str_contains($commandSource, 'CameroonTaxTable::AUDIO_VISUAL'));

$databaseSeeder = file_get_contents($base . '/database/seeders/DatabaseSeeder.php');

check('the chart of accounts is seeded before the taxes that point at it',
    strpos($databaseSeeder, 'OhadaChartOfAccountsSeeder') !== false
    && strpos($databaseSeeder, 'OhadaChartOfAccountsSeeder') < strpos($databaseSeeder, 'TaxSettingSeeder::class'),
    'a tax with no liability account cannot be remitted to anybody');

// Now actually run it against an empty database and read what a school gets.
DB::beginTransaction();

try {
    DB::table('payroll_tax_lines')->delete();
    DB::table('tax_settings')->delete();
    DB::table('tax_groups')->delete();

    (new Database\Seeders\TaxSettingSeeder())->run();

    $seededGroups = DB::table('tax_groups')->pluck('code')->all();

    check('a fresh install gets the income tax', in_array('PIT', $seededGroups, true));
    check('the local development tax', in_array('LDT', $seededGroups, true));
    check('the housing fund', in_array('HLF', $seededGroups, true));
    check('the audio-visual tax', in_array('CRTV', $seededGroups, true));
    check('and the industrial accident group, switched off for the school to fill in',
        DB::table('tax_groups')->where('code', 'IA')->where('status', 0)->exists(),
        'it has no honest default rate, but the school must know it is owed');

    check('the social contribution, with its ceiling as two rows',
        DB::table('tax_settings')->whereNull('tax_group_id')
            ->where('title', 'LIKE', '%Social Insurance%')->count() === 2);
    check('the employment fund',
        DB::table('tax_settings')->whereNull('tax_group_id')
            ->where('title', 'LIKE', '%Employment Fund%')->exists());
    check('and the council surcharge, dependent on the income tax',
        DB::table('tax_settings')->whereNull('tax_group_id')->where('is_dependent', 1)
            ->where('depends_on_type', 'tax_group')->exists());

    $freshPit = TaxGroup::where('code', 'PIT')->first();
    $freshWrong = [];

    foreach (App\Support\Tax\CameroonTaxTable::checkpoints() as $salary => $expected) {
        $freshPit->unsetRelation('brackets');

        if (abs((int) round($freshPit->calculateTax($salary)['amount']) - $expected) > 1) {
            $freshWrong[] = number_format($salary);
        }
    }

    check('and the income tax it installs reproduces the barème at every checkpoint',
        $freshWrong === [], implode(', ', $freshWrong));

    check('every bracket it writes is dated',
        DB::table('tax_settings')->where('status', 1)->whereNull('effective_from')->count() === 0);
    check('and routed to a liability account',
        DB::table('tax_settings')->where('status', 1)->whereNull('liability_account_id')->count() === 0);

    // Running twice must not double anything, and must not clobber.
    $afterFirst = DB::table('tax_settings')->count();
    (new Database\Seeders\TaxSettingSeeder())->run();

    check('running it a second time changes nothing',
        DB::table('tax_settings')->count() === $afterFirst,
        sprintf('%d -> %d', $afterFirst, DB::table('tax_settings')->count()));
} finally {
    DB::rollBack();
}

check('the live configuration survived the simulation',
    TaxSetting::where('tax_group_id', GROUP_PIT)->where('status', 1)->count() === 5);

// ---------------------------------------------------------------------------
section('A school whose groups were made by hand is adopted, not duplicated');

// One school's income-tax group was coded "PIT." and titled "Personal Income
// Tax." — trailing full stops somebody typed. An exact match found neither, so
// the seeder would have created a SECOND income-tax group beside it; and
// PayrollTaxBreakdownService sums every effective group, so each member of
// staff would have been charged income tax twice with nothing on screen to say
// why. The group also carried fifteen bands of its own, and since selection
// takes the highest floor at or below the salary with max_amount never
// consulted, a leftover band would simply win for the larger salaries.
DB::beginTransaction();

try {
    DB::table('tax_settings')->delete();
    DB::table('tax_groups')->delete();

    $handMade = DB::table('tax_groups')->insertGetId([
        'title' => 'Personal Income Tax.', 'code' => 'PIT.', 'status' => 1,
        'is_progressive' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    for ($i = 1; $i <= 15; $i++) {
        DB::table('tax_settings')->insert([
            'tax_group_id' => $handMade, 'title' => 'hand-made band ' . $i, 'bracket_order' => $i,
            'tax_type' => 2, 'paid_by' => 'employee', 'min_amount' => $i * 50000,
            'max_amount' => $i * 50000 + 49999, 'percentange' => 0, 'employer_percentage' => 0,
            'fixed_amount' => $i * 1000, 'employer_fixed_amount' => 0, 'max_no_taxable_amount' => 0,
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    (new Database\Seeders\TaxSettingSeeder())->run();

    $pitGroups = DB::table('tax_groups')
        ->whereIn('code', ['PIT', 'PIT.'])->count();

    check('the near-miss code is adopted rather than a second group created',
        $pitGroups === 1, $pitGroups . ' income-tax groups');
    check('and the school keeps its own group id',
        (int) DB::table('tax_groups')->where('code', 'PIT')->value('id') === $handMade);
    check('its title is left as the school wrote it',
        DB::table('tax_groups')->where('code', 'PIT')->value('title') === 'Personal Income Tax.',
        'adopting a group is not licence to rename it');
    check('the bands it had are replaced, not added to',
        DB::table('tax_settings')->where('tax_group_id', $handMade)->count() === 5,
        DB::table('tax_settings')->where('tax_group_id', $handMade)->count() . ' bands');

    $adopted = TaxGroup::where('code', 'PIT')->first();
    $adoptedWrong = [];

    foreach (App\Support\Tax\CameroonTaxTable::checkpoints() as $salary => $expected) {
        $adopted->unsetRelation('brackets');

        if (abs((int) round($adopted->calculateTax($salary)['amount']) - $expected) > 1) {
            $adoptedWrong[] = number_format($salary);
        }
    }

    check('and it then charges the barème exactly', $adoptedWrong === [],
        implode(', ', $adoptedWrong));

    // The duplicate would not have been visible in the group list alone: what
    // matters is that a salary is taxed once.
    $breakdown = app(PayrollTaxBreakdownService::class);
    $incomeTaxLines = collect($breakdown->forSalary(180000.0, Carbon::parse('2026-10-01')))
        ->filter(fn ($line) => stripos($line['label'], 'Income Tax') !== false);

    check('a salary is charged income tax exactly once', $incomeTaxLines->count() === 1,
        $incomeTaxLines->count() . ' income-tax lines on one payslip');
} finally {
    DB::rollBack();
}

check('the live configuration survived that too',
    TaxSetting::where('tax_group_id', GROUP_PIT)->where('status', 1)->count() === 5);

// ---------------------------------------------------------------------------
section('Correcting an established school checks for history first');

// Replacing a tax table is safe exactly while nothing has been charged under
// the old one. Once a payroll is paid, its lines name the bracket that produced
// them, so rewriting that bracket makes a payslip disagree with the rates it
// was supposedly calculated from — quietly, and after the fact.
check('the command reports what the installation already holds',
    str_contains($commandSource, 'What this installation already holds'));
check('and refuses when a payroll has been charged',
    str_contains($commandSource, 'i-know-there-is-history'));

DB::beginTransaction();

try {
    $bracketId = TaxSetting::where('tax_group_id', GROUP_PIT)->value('id');
    $someone = App\User::where('is_admin', 1)->first();

    $payrollId = DB::table('payrolls')->insertGetId([
        'user_id' => $someone->id, 'basic_salary' => 180000, 'salary_type' => 1,
        'total_earning' => 180000, 'total_allowance' => 0, 'bonus' => 0, 'total_deduction' => 0,
        'gross_salary' => 180000, 'tax' => 21005, 'employer_tax' => 12060,
        'net_salary' => 158995, 'total_cost' => 192060, 'salary_month' => '2026-09-01',
        'pay_date' => '2026-09-30', 'status' => 1, 'created_by' => $someone->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('payroll_tax_lines')->insert([
        'payroll_id' => $payrollId, 'user_id' => $someone->id, 'salary_month' => '2026-09-01',
        'tax_group_id' => GROUP_PIT, 'tax_setting_id' => $bracketId, 'label' => 'Personal Income Tax',
        'employee_amount' => 7677.33, 'employer_amount' => 0, 'liability_account_id' => ACCOUNT_STATE,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $bracketsBefore = TaxSetting::where('tax_group_id', GROUP_PIT)->where('status', 1)->count();
    $exitCode = Illuminate\Support\Facades\Artisan::call('tax:configure-cameroon', []);

    check('with a paid payroll present, the command refuses', $exitCode !== 0,
        'it ran anyway, which would rewrite what that payroll was charged under');
    check('and changes nothing',
        TaxSetting::where('tax_group_id', GROUP_PIT)->where('status', 1)->count() === $bracketsBefore);
} finally {
    DB::rollBack();
}

check('the brackets are intact after the rollback',
    TaxSetting::where('tax_group_id', GROUP_PIT)->where('status', 1)->count() === 5);

// ---------------------------------------------------------------------------
section('Nothing was left behind');

check('no stray payroll tax lines', DB::table('payroll_tax_lines')->count() === 0,
    DB::table('payroll_tax_lines')->count() . ' rows');
check('the staff count is unchanged',
    App\User::where('status', '1')->where('basic_salary', '>', 0)->count() === $staff->count());

echo "\n", str_repeat('=', 64), "\n";
printf("passed %d, failed %d\n", $passed, $failed);

exit($failed > 0 ? 1 : 0);
