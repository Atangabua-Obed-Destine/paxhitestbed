<?php

namespace App\Services\Accounting;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Support\PaymentMethod;
use App\Support\TransactionWindow;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The income and expenditure screens, and the document they print.
 *
 * One definition of "the filter the user is looking at", used by the screen
 * and by the report it prints. They were two copies of the same code in two
 * controllers, and the screens had already drifted apart once; a report that
 * resolved the filter its own way would be worse than no report at all,
 * because it would be a signed document that quietly disagreed with the
 * screen it was printed from.
 *
 * Nothing here paginates. The screen hands every matching row to DataTables
 * and lets it page in the browser; this report renders every row it was given
 * straight into the document. That is the whole of the fix for "the export
 * only contains one page" — a document built from the database cannot be
 * truncated by what the browser happened to be showing.
 */
class TransactionReport
{
    /** Which side of the sheet. Income and expenditure are never pooled. */
    public const INCOME = 'income';
    public const EXPENSE = 'expense';

    /**
     * The filter, read once, from the request.
     *
     * @return array{title: ?string, categories: array<int, int>, window: array}
     */
    public function filter(Request $request): array
    {
        // Category: any number of them. None chosen means every category. A
        // single value from an old link or a bookmark still works, which is
        // why this reads through (array) rather than demanding category[].
        $categories = collect((array) $request->input('category', []))
            ->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();

        return [
            'title' => $request->filled('title') ? $request->input('title') : null,
            'categories' => $categories,

            // The window the screen opens on: the exercice the books are
            // actually keeping, not the last twelve months counted back from
            // today, which belongs to no set of accounts.
            'window' => TransactionWindow::resolve(
                $request->input('start_date'),
                $request->input('end_date'),
                $request->boolean('all')
            ),
        ];
    }

    /**
     * The rows that filter selects, newest first.
     *
     * The category is eager-loaded because the document names it on every
     * line; without that, a thousand-row report is a thousand queries and the
     * request times out rather than printing.
     */
    public function rows(string $kind, array $filter): Collection
    {
        $model = $kind === self::EXPENSE ? Expense::class : Income::class;

        $query = $model::with('category')
            ->when($filter['window']['start'], fn ($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($filter['window']['end'], fn ($q, $d) => $q->whereDate('date', '<=', $d));

        if ($filter['title'] !== null) {
            $query->where('title', 'LIKE', '%' . $filter['title'] . '%');
        }

        if ($filter['categories'] !== []) {
            $query->whereIn('category_id', $filter['categories']);
        }

        return $query->orderBy('date', 'desc')->orderBy('id', 'desc')->get();
    }

    /**
     * What the screen's band shows: the count, the money, and how it moved.
     *
     * Bucketed from rows already fetched, so it costs no extra query and can
     * never disagree with the table it sits above.
     */
    public function summarise($rows): array
    {
        $methods = [];

        foreach ($rows as $row) {
            $key = $row->payment_method === null ? 0 : (int) $row->payment_method;

            if (!isset($methods[$key])) {
                $methods[$key] = [
                    'label' => $key === 0 ? __('acc_not_recorded') : PaymentMethod::label($key),
                    'count' => 0,
                    'total' => 0.0,
                ];
            }

            $methods[$key]['count']++;
            $methods[$key]['total'] += (float) $row->amount;
        }

        // Biggest first: what the money mostly came through is the thing worth
        // reading, not whichever method happens to be numbered lowest.
        uasort($methods, fn ($a, $b) => $b['total'] <=> $a['total']);

        return [
            'count' => $rows->count(),
            'total' => (float) $rows->sum('amount'),
            'methods' => $methods,
        ];
    }

    /**
     * Everything the printed report says, built from the rows already loaded.
     *
     * Every breakdown below is computed from the SAME collection the itemised
     * table prints. They are therefore guaranteed to add to the same total,
     * which is the one property a reader checks first and the one a second
     * query would eventually break.
     */
    public function build(string $kind, array $filter, Collection $rows): array
    {
        $summary = $this->summarise($rows);
        $total = $summary['total'];

        return [
            'kind' => $kind,
            'filter' => $filter,
            'rows' => $rows,
            'summary' => $summary,
            'headline' => $this->headline($rows, $summary),
            'by_category' => $this->share($this->group($rows, fn ($row) => [
                'key' => $row->category_id,
                'label' => $row->category->title ?? __('acc_not_recorded'),
            ]), $total),
            'by_method' => $this->share($this->group($rows, fn ($row) => [
                'key' => $row->payment_method === null ? 0 : (int) $row->payment_method,
                'label' => $row->payment_method === null
                    ? __('acc_not_recorded')
                    : PaymentMethod::label((int) $row->payment_method),
            ]), $total),
            'by_month' => $this->months($rows),
        ];
    }

    /**
     * The four figures at the top.
     *
     * The largest single entry is here because an average over a handful of
     * records says very little on its own: a month whose total is one large
     * transfer and nine small ones reads completely differently from ten
     * even ones, and the average alone cannot tell them apart.
     */
    protected function headline(Collection $rows, array $summary): array
    {
        $largest = $rows->sortByDesc(fn ($row) => (float) $row->amount)->first();

        return [
            'count' => $summary['count'],
            'total' => $summary['total'],
            'average' => $summary['count'] > 0 ? $summary['total'] / $summary['count'] : 0.0,
            'largest' => $largest ? (float) $largest->amount : 0.0,
            'largest_title' => $largest->title ?? null,
            'largest_date' => $largest->date ?? null,

            // The dates the rows actually span, which is not the window: a
            // report run over a whole year whose entries stop in March should
            // say March, not December.
            'first' => $rows->min('date'),
            'last' => $rows->max('date'),
        ];
    }

    /**
     * One bucket per key, biggest first.
     *
     * @param  callable(object): array{key: mixed, label: string}  $by
     */
    protected function group(Collection $rows, callable $by): array
    {
        $out = [];

        foreach ($rows as $row) {
            ['key' => $key, 'label' => $label] = $by($row);
            $key = $key === null ? '0' : (string) $key;

            if (!isset($out[$key])) {
                $out[$key] = ['label' => $label, 'count' => 0, 'total' => 0.0];
            }

            $out[$key]['count']++;
            $out[$key]['total'] += (float) $row->amount;
        }

        uasort($out, fn ($a, $b) => $b['total'] <=> $a['total']);

        return array_values($out);
    }

    /**
     * Each bucket's share of the whole, as a percentage.
     *
     * Guarded against a zero total rather than left to produce a division by
     * zero on an empty report, which is exactly when somebody prints one.
     */
    protected function share(array $buckets, float $total): array
    {
        foreach ($buckets as $i => $bucket) {
            $buckets[$i]['share'] = $total > 0 ? round($bucket['total'] / $total * 100, 1) : 0.0;
        }

        return $buckets;
    }

    /**
     * Month by month, oldest first, with every month in between.
     *
     * Empty months are filled in deliberately. A list that skips them draws a
     * flat line through a gap and hides the month nothing came in, which on a
     * collection report is the month worth asking about.
     */
    protected function months(Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $buckets = [];

        foreach ($rows as $row) {
            $key = Carbon::parse($row->date)->format('Y-m');

            if (!isset($buckets[$key])) {
                $buckets[$key] = ['count' => 0, 'total' => 0.0];
            }

            $buckets[$key]['count']++;
            $buckets[$key]['total'] += (float) $row->amount;
        }

        $cursor = Carbon::parse($rows->min('date'))->startOfMonth();
        $last = Carbon::parse($rows->max('date'))->startOfMonth();
        $out = [];

        while ($cursor->lessThanOrEqualTo($last)) {
            $key = $cursor->format('Y-m');

            $out[] = [
                'key' => $key,
                'label' => $cursor->format('M Y'),
                'count' => $buckets[$key]['count'] ?? 0,
                'total' => $buckets[$key]['total'] ?? 0.0,
            ];

            $cursor->addMonth();
        }

        return $out;
    }

    /**
     * The filter in words, for the document to state before any figure.
     *
     * A filtered report that does not say what it was filtered to is the most
     * dangerous thing on this screen: it is a total, under a letterhead, that
     * looks like all the money. So the document says what it covers, and says
     * it in the same place whether or not a filter is set.
     *
     * @return array<int, array{label: string, value: string}>
     */
    public function describe(string $kind, array $filter): array
    {
        $window = $filter['window'];

        $period = $window['start'] && $window['end']
            ? Carbon::parse($window['start'])->format('j M Y') . ' – '
                . Carbon::parse($window['end'])->format('j M Y')
            : __('All time');

        // "Period", not "Date": this row is a range, and a reader scanning a
        // cover block reads the label before the value.
        $lines = [
            ['label' => __('Period'), 'value' => $period],
        ];

        $all = $kind === self::EXPENSE
            ? ExpenseCategory::where('status', '1')->count()
            : IncomeCategory::where('status', '1')->count();

        if ($filter['categories'] === []) {
            $lines[] = [
                'label' => __('field_category'),
                'value' => trans_choice(
                    'All :count category|All :count categories',
                    $all,
                    ['count' => $all]
                ),
            ];
        } else {
            $chosen = $kind === self::EXPENSE
                ? ExpenseCategory::whereIn('id', $filter['categories'])->orderBy('title')->pluck('title')
                : IncomeCategory::whereIn('id', $filter['categories'])->orderBy('title')->pluck('title');

            $lines[] = [
                'label' => __('field_category'),
                'value' => $chosen->implode(', ') . ' (' . __(':chosen of :all', [
                    'chosen' => $chosen->count(), 'all' => $all,
                ]) . ')',
            ];
        }

        if ($filter['title'] !== null) {
            $lines[] = ['label' => __('field_title'), 'value' => '"' . $filter['title'] . '"'];
        }

        return $lines;
    }

    /**
     * Whether the figures below are the whole of this period's money.
     *
     * Drives the one sentence that stops the report being mistaken for a
     * complete statement of income or expenditure.
     */
    public function isFiltered(array $filter): bool
    {
        return $filter['categories'] !== [] || $filter['title'] !== null;
    }
}
