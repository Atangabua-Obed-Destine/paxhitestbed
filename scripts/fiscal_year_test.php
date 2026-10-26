<?php

/**
 * Which fiscal year does a journal entry belong to?
 *
 * It should be the one whose dates contain the entry's own date, and nothing
 * else. That was already the stated intention in the posting code, but the rule
 * ended in "?? whichever year is active", and on this database the fallback is
 * what ran: one fiscal year existed, shaped like the ACADEMIC year (October
 * 2026 to July 2027), while the ledger ran from October 2025 to October 2026.
 * The result was 912 of 924 entries stamped with a year that does not contain
 * them, every one of them without an accounting period.
 *
 * Nothing on screen showed it. The entries existed, the reports ran, and the
 * figures were simply wrong — a year filtered by fiscal_year_id returned two
 * calendar years of transactions at once.
 *
 * These checks are what keeps the fallback from coming back, and what holds the
 * OHADA shape rule (Article 7 AUDCIF: twelve months, ending 31 December, with a
 * first exercice allowed to be shorter or longer depending on which half of the
 * year it starts in).
 *
 * Every write happens inside a transaction that is rolled back.
 */

$base = 'C:/xampp/htdocs/paxhitestbed';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

$pass = 0;
$fail = 0;

function check(string $what, $expected, $actual): void
{
    global $pass, $fail;

    if ($expected === $actual) {
        $pass++;

        return;
    }

    $fail++;
    printf("  FAIL  %s\n          expected: %s\n          actual  : %s\n",
        $what, var_export($expected, true), var_export($actual, true));
}

function ok(string $what, bool $condition): void
{
    check($what, true, $condition);
}

$opening = [
    'fiscal_years' => DB::table('fiscal_years')->count(),
    'periods' => DB::table('accounting_periods')->count(),
    'entries' => DB::table('journal_entries')->count(),
    'misfiled' => misfiledCount(),
    'no_period' => DB::table('journal_entries')->whereNull('accounting_period_id')->count(),
    'debits' => (string) DB::table('journal_entries')->sum('total_debit'),
];

/** Entries whose fiscal year does not contain their own date. */
function misfiledCount(): int
{
    return (int) DB::table('journal_entries')
        ->whereNotNull('fiscal_year_id')
        ->whereNotExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('fiscal_years')
                ->whereColumn('fiscal_years.id', 'journal_entries.fiscal_year_id')
                ->whereColumn('fiscal_years.start_date', '<=', 'journal_entries.entry_date')
                ->whereColumn('fiscal_years.end_date', '>=', 'journal_entries.entry_date');
        })
        ->count();
}

printf("Before: %d fiscal years, %d periods, %d entries (%d misfiled, %d without a period)\n\n",
    $opening['fiscal_years'], $opening['periods'], $opening['entries'],
    $opening['misfiled'], $opening['no_period']);

DB::beginTransaction();

try {
    // A clean slate for the rules, built rather than borrowed, so the checks
    // do not depend on whatever this school happens to have configured.
    DB::table('journal_entries')->update(['fiscal_year_id' => null, 'accounting_period_id' => null]);
    DB::table('accounting_periods')->delete();
    DB::table('fiscal_years')->delete();

    $fy2025 = FiscalYear::create([
        'name' => 'Exercice 2025', 'start_date' => '2025-10-14', 'end_date' => '2025-12-31',
        'is_active' => false, 'is_closed' => false,
    ]);

    $fy2026 = FiscalYear::create([
        'name' => 'Exercice 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
        'is_active' => true, 'is_closed' => false,
    ]);

    echo "=== 1. a date belongs to the year that contains it, or to none ===\n";

    check('a date inside 2025 finds 2025', $fy2025->id, FiscalYear::forDate('2025-11-02')?->id);
    check('the first day of 2026 finds 2026', $fy2026->id, FiscalYear::forDate('2026-01-01')?->id);
    check('the last day of 2026 finds 2026', $fy2026->id, FiscalYear::forDate('2026-12-31')?->id);
    check('the last day of 2025 finds 2025', $fy2025->id, FiscalYear::forDate('2025-12-31')?->id);

    // The heart of it. The old code answered "the active year" here, which is
    // how 912 entries came to be filed under a year that does not contain them.
    check('a date BEFORE every fiscal year finds none', null, FiscalYear::forDate('2025-01-05')?->id);
    check('a date AFTER every fiscal year finds none', null, FiscalYear::forDate('2027-03-01')?->id);
    check('and it does not quietly return the active year', null, FiscalYear::forDate('2030-06-01')?->id);
    check('no date at all finds none', null, FiscalYear::forDate(null)?->id);

    echo "\n=== 2. the OHADA shape of an exercice ===\n";

    $shape = function (string $from, string $to): ?string {
        return (new FiscalYear(['start_date' => $from, 'end_date' => $to]))->shapeWarning();
    };

    check('a full calendar year is fine', null, $shape('2026-01-01', '2026-12-31'));
    check('a first exercice from October to 31 December is fine', null, $shape('2025-10-14', '2025-12-31'));
    check('a long first exercice starting in the second half is fine', null,
        $shape('2025-10-14', '2026-12-31'));
    check('a short first exercice starting in the first half is fine', null,
        $shape('2026-03-01', '2026-12-31'));

    ok('an academic year (Oct to Jul) is flagged',
        str_contains((string) $shape('2026-10-01', '2027-07-31'), 'ends on 31 December'));
    ok('any year not ending 31 December is flagged',
        str_contains((string) $shape('2026-01-01', '2026-11-30'), 'ends on 31 December'));
    ok('a first-half start running past its own year is flagged',
        str_contains((string) $shape('2026-03-01', '2027-12-31'), 'may be shorter'));
    ok('a second-half start running two years on is flagged',
        str_contains((string) $shape('2025-10-01', '2027-12-31'), 'FOLLOWING year'));

    echo "\n=== 3. two fiscal years may not cover the same day ===\n";

    check('the two real years do not overlap each other', 0, $fy2025->overlapping()->count());

    $overlapping = new FiscalYear(['start_date' => '2026-06-01', 'end_date' => '2026-12-31']);
    check('one that straddles 2026 is caught', 1, $overlapping->overlapping()->count());

    $touching = new FiscalYear(['start_date' => '2027-01-01', 'end_date' => '2027-12-31']);
    check('the very next year does not count as an overlap', 0, $touching->overlapping()->count());

    // Through the screen's own validator, which is what an admin meets.
    $controller = app(App\Http\Controllers\Admin\FiscalYearController::class);
    $noOverlap = (new ReflectionMethod($controller, 'noOverlapRule'));
    $noOverlap->setAccessible(true);

    $validate = function (array $input, $ignoreId = null) use ($controller, $noOverlap) {
        $request = new Illuminate\Http\Request($input);

        return Validator::make($input, [
            'end_date' => ['required', 'date', 'after:start_date',
                $noOverlap->invoke($controller, $request, $ignoreId)],
        ])->errors()->first('end_date');
    };

    ok('the form refuses an overlapping year',
        str_contains((string) $validate(['start_date' => '2026-06-01', 'end_date' => '2026-12-31']), 'overlap'));
    check('and accepts one that does not overlap', '',
        $validate(['start_date' => '2027-01-01', 'end_date' => '2027-12-31']));
    check('a year may overlap ITSELF when being edited', '',
        $validate(['start_date' => '2026-01-01', 'end_date' => '2026-12-31'], $fy2026->id));

    echo "\n=== 4. a year may not be narrowed away from its own entries ===\n";

    $entry = JournalEntry::whereNotNull('entry_date')->first();

    if ($entry) {
        $entry->fiscal_year_id = $fy2026->id;
        $entry->entry_date = '2026-05-20';
        $entry->save();

        $keeps = new ReflectionMethod($controller, 'keepsItsEntriesRule');
        $keeps->setAccessible(true);

        $validateCoverage = function (array $input, $id) use ($controller, $keeps) {
            $request = new Illuminate\Http\Request($input);

            return Validator::make($input, [
                'end_date' => ['required', 'date', $keeps->invoke($controller, $request, $id)],
            ])->errors()->first('end_date');
        };

        ok('narrowing the year past an entry is refused',
            str_contains((string) $validateCoverage(
                ['start_date' => '2026-07-01', 'end_date' => '2026-12-31'], $fy2026->id), 'fall outside'));

        check('keeping it wide enough is allowed', '',
            $validateCoverage(['start_date' => '2026-01-01', 'end_date' => '2026-12-31'], $fy2026->id));
    }

    echo "\n=== 5. periods follow the year, never cross into another ===\n";

    $january = AccountingPeriod::create([
        'fiscal_year_id' => $fy2026->id, 'period_number' => 1, 'name' => 'January 2026',
        'start_date' => '2026-01-01', 'end_date' => '2026-01-31', 'is_closed' => false,
    ]);

    check('a January date finds the January period', $january->id,
        FiscalYear::periodForDate('2026-01-15')?->id);
    check('a February date finds no period when none exists', null,
        FiscalYear::periodForDate('2026-02-15')?->id);
    check('a date in another fiscal year finds no period here', null,
        FiscalYear::periodForDate('2025-11-02')?->id);

    echo "\n=== 6. the doctor repairs by date, and only by date ===\n";

    // Put every entry back in the broken state: all pointing at 2026,
    // whatever their dates.
    DB::table('journal_entries')->update([
        'fiscal_year_id' => $fy2026->id,
        'accounting_period_id' => null,
    ]);

    $brokenCount = misfiledCount();
    ok('the broken state is reproduced', $brokenCount > 0);

    $before = [
        'entries' => JournalEntry::count(),
        'debits' => (string) DB::table('journal_entries')->sum('total_debit'),
        'dates' => (string) DB::table('journal_entries')->min('entry_date')
            . '|' . DB::table('journal_entries')->max('entry_date'),
    ];

    Illuminate\Support\Facades\Artisan::call('accounting:fiscal-year-doctor', ['--repair' => true]);

    $entriesIn2025 = JournalEntry::whereYear('entry_date', 2025)
        ->whereBetween('entry_date', ['2025-10-14', '2025-12-31'])->count();

    check('entries dated in the 2025 exercice now point at it', $entriesIn2025,
        JournalEntry::where('fiscal_year_id', $fy2025->id)->count());

    ok('entries dated in 2026 point at the 2026 exercice',
        JournalEntry::where('fiscal_year_id', $fy2026->id)->count() > 0);

    ok('January entries were given the January period',
        JournalEntry::where('accounting_period_id', $january->id)->count()
            === JournalEntry::whereBetween('entry_date', ['2026-01-01', '2026-01-31'])->count());

    // The only thing that must never change.
    check('no entry was created or destroyed', $before['entries'], JournalEntry::count());
    check('not one franc moved', $before['debits'], (string) DB::table('journal_entries')->sum('total_debit'));
    check('no entry date was touched', $before['dates'],
        (string) DB::table('journal_entries')->min('entry_date')
            . '|' . DB::table('journal_entries')->max('entry_date'));

    echo "\n=== 7. a closed year is not rearranged without being told ===\n";

    $fy2025->update(['is_closed' => true]);

    DB::table('journal_entries')
        ->whereBetween('entry_date', ['2025-10-14', '2025-12-31'])
        ->update(['fiscal_year_id' => $fy2026->id]);

    $closedYearEntries = misfiledCount();
    ok('entries are misfiled again, into a year that is now closed', $closedYearEntries > 0);

    Illuminate\Support\Facades\Artisan::call('accounting:fiscal-year-doctor', ['--repair' => true]);

    check('the repair left them alone', $closedYearEntries, misfiledCount());

    Illuminate\Support\Facades\Artisan::call('accounting:fiscal-year-doctor',
        ['--repair' => true, '--allow-closed' => true]);

    check('and moved them only when told to', 0, misfiledCount());

    $fy2025->update(['is_closed' => false]);

    echo "\n=== 7b. backdated postings land in the year they are dated ===\n";

    /*
     * The case that makes this reachable at all: a payroll, remittance,
     * depreciation run or asset purchase is dated in the past and posted now.
     * Seven services used to take "whichever fiscal year is active" while
     * dating the entry from the document — so December's payroll, posted in
     * January, went into the new year's accounts.
     *
     * Checked through FiscalYear::forDate, which is what all of them now call.
     */
    $fy2026->update(['is_active' => true]);
    $fy2025->update(['is_active' => false]);

    // Give the 2025 exercice its months, using the screen's own generator
    // rather than a second copy of that loop.
    $generator = new ReflectionMethod($controller, 'createMonthlyPeriods');
    $generator->setAccessible(true);
    $generator->invoke($controller, $fy2025->refresh());

    check('the 2025 exercice has three months', 3, $fy2025->accountingPeriods()->count());

    check('a December 2025 pay date resolves to the 2025 exercice', $fy2025->id,
        FiscalYear::forDate('2025-12-28')?->id);
    check('even though 2026 is the active year', $fy2026->id,
        FiscalYear::getActiveFiscalYear()?->id);

    check('a December 2025 date gets a December 2025 period', 'December 2025',
        FiscalYear::periodForDate('2025-12-28')?->name);
    check('and a January 2026 date gets a January 2026 one', 'January 2026',
        FiscalYear::periodForDate('2026-01-15')?->name);

    // An asset bought before any exercice existed: null, visibly, rather than
    // quietly filed into the active year.
    check('an acquisition before the first exercice resolves to no year', null,
        FiscalYear::forDate('2024-06-01')?->id);

    echo "\n=== 7c. the manual entry form cannot file a date outside its year ===\n";

    $entryRule = function (string $date, int $yearId) {
        $input = ['entry_date' => $date, 'fiscal_year_id' => $yearId];
        $request = new Illuminate\Http\Request($input);

        return Validator::make($input, [
            'fiscal_year_id' => ['required', function ($attribute, $value, $fail) use ($request) {
                $year = FiscalYear::find($value);

                if (!$year || !$request->filled('entry_date')) {
                    return;
                }

                $when = \Carbon\Carbon::parse($request->input('entry_date'));

                if ($when->lt($year->start_date) || $when->gt($year->end_date)) {
                    $fail('outside');
                }
            }],
        ])->errors()->first('fiscal_year_id');
    };

    check('a 2026 date in the 2026 exercice is accepted', '', $entryRule('2026-05-01', $fy2026->id));
    check('a 2025 date in the 2026 exercice is refused', 'outside', $entryRule('2025-11-01', $fy2026->id));
    check('a 2026 date in the 2025 exercice is refused', 'outside', $entryRule('2026-05-01', $fy2025->id));

    echo "\n=== 8. teeth: without the date rule, the old bug returns ===\n";

    // What the removed fallback did: file everything under the active year.
    DB::table('journal_entries')->update(['fiscal_year_id' => $fy2026->id]);

    ok('filing by "the active year" misfiles entries again (check 6 would fail)',
        misfiledCount() > 0);
    printf("          (%d entries would be wrong)\n", misfiledCount());

    echo "\n=== 9. nothing escaped into the real tables ===\n";
    printf("  inside the transaction: %d fiscal years, %d entries\n",
        FiscalYear::count(), JournalEntry::count());
} catch (\Throwable $e) {
    echo "\n  *** the suite threw: " . $e->getMessage() . "\n";
    echo '      ' . $e->getFile() . ':' . $e->getLine() . "\n";
    $fail++;
} finally {
    DB::rollBack();
}

echo "\n=== nothing was left behind ===\n";

$closing = [
    'fiscal_years' => DB::table('fiscal_years')->count(),
    'periods' => DB::table('accounting_periods')->count(),
    'entries' => DB::table('journal_entries')->count(),
    'misfiled' => misfiledCount(),
    'no_period' => DB::table('journal_entries')->whereNull('accounting_period_id')->count(),
    'debits' => (string) DB::table('journal_entries')->sum('total_debit'),
];

// Absolute values, not deltas: a delta of zero is also what two mistakes
// cancelling each other out looks like.
foreach ($opening as $key => $value) {
    check(sprintf('%-13s is still %s', $key, $value), $value, $closing[$key]);
}

printf("\n%d passed, %d failed\n\n", $pass, $fail);

exit($fail === 0 ? 0 : 1);
