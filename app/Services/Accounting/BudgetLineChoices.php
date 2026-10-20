<?php

namespace App\Services\Accounting;

use App\Models\BudgetLine;
use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Which categories an income or expense may actually be recorded against.
 *
 * A category is only usable if it reaches a line of the Income & Expenditure
 * sheet. The join is default_account_mappings: a category resolves to a chart
 * account and a budget line, and BudgetActualsService routes the money with
 * COALESCE(transaction.budget_line_id, mapping.budget_line_id). A category
 * with no line therefore produces money that appears on no line of the sheet
 * at all — the service counts it as "unallocated" and it is reported nowhere.
 *
 * Three things about this data that the obvious implementation gets wrong:
 *
 *  - EVERY mapping row has status = 0. Filtering mappings on an active flag
 *    would empty every dropdown on both screens.
 *
 *  - A category's line is not always in the section you would expect. One
 *    expense category maps to a line in the capital section. So the heading
 *    is taken from the line the category actually points at, never from the
 *    section the screen happens to be about.
 *
 *  - Some lines carrying categories sit under no heading at all — among them
 *    FINANCIAL EXPENSES and OTHER EXPENSES, both in daily use. Grouping
 *    strictly by heading would make them unselectable and quietly break
 *    expense entry, so they are grouped under NO_HEADING instead. This is the
 *    same treatment BudgetLine::inTreeOrder() gives them on the sheet, for
 *    the same reason: dropping a line loses money.
 */
class BudgetLineChoices
{
    /** The group for lines that are filed under no heading. */
    public const NO_HEADING = 0;

    public const INCOME = 'income_category';
    public const EXPENSE = 'expense_category';

    /**
     * The headings an admin may choose from, each with how many categories
     * are filed under it.
     *
     * A heading with no usable category is left out: offering it only leads
     * to an empty category dropdown and no explanation. 33 of the 53
     * expenditure lines carry no category at all.
     *
     * @return Collection<int, array{id: int, code: string, name: string, label: string, count: int}>
     */
    public function headings(string $mappingType): Collection
    {
        $grouped = $this->categories($mappingType)->groupBy('heading_id');

        $headings = collect();

        foreach ($grouped as $headingId => $categories) {
            $headingId = (int) $headingId;
            $first = $categories->first();

            $headings->push([
                'id' => $headingId,
                'code' => $first['heading_code'],
                'name' => $first['heading_name'],
                'label' => $first['heading_label'],
                'count' => $categories->count(),
            ]);
        }

        // Real headings in sheet order; the un-headed group last, because it
        // is a residue rather than a part of the structure.
        return $headings
            ->sortBy(fn ($heading) => ($heading['id'] === self::NO_HEADING ? '1' : '0')
                . '|' . $heading['code'])
            ->values();
    }

    /**
     * Every category that reaches a line of the sheet, with the heading it is
     * filed under and the line it posts to.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function categories(string $mappingType): Collection
    {
        $lines = BudgetLine::all()->keyBy('id');

        return $this->mappedCategories($mappingType)
            ->map(function ($row) use ($lines) {
                $line = $lines->get((int) $row->budget_line_id);

                if (!$line || !$this->linePostable($line)) {
                    return null;
                }

                $heading = $line->parent_id ? $lines->get((int) $line->parent_id) : null;

                // A heading that has been retired or deleted does not file
                // anything: the line stands on its own rather than vanishing.
                if ($heading && (!$heading->is_header || !$heading->status)) {
                    $heading = null;
                }

                return [
                    'id' => (int) $row->id,
                    'title' => $row->title,
                    'line_id' => $line->id,
                    'line_code' => $line->code,
                    'line_name' => $line->name,
                    'line_label' => $line->code . ' — ' . $line->name,
                    'section' => $line->section,
                    'heading_id' => $heading->id ?? self::NO_HEADING,
                    'heading_code' => $heading->code ?? '',
                    'heading_name' => $heading->name ?? __('Not filed under a heading'),
                    'heading_label' => $heading
                        ? $heading->code . ' — ' . $heading->name
                        : __('Not filed under a heading'),
                ];
            })
            ->filter()
            ->sortBy([['heading_code', 'asc'], ['line_code', 'asc'], ['title', 'asc']])
            ->values();
    }

    /** The categories filed under one heading. */
    public function categoriesUnder(string $mappingType, int $headingId): Collection
    {
        return $this->categories($mappingType)
            ->where('heading_id', $headingId)
            ->values();
    }

    /** The budget line a category posts to, or null if it reaches none. */
    public function lineFor(string $mappingType, $categoryId): ?BudgetLine
    {
        $mapping = DB::table('default_account_mappings')
            ->where('mapping_type', $mappingType)
            ->where('category_id', $categoryId)
            ->whereNotNull('budget_line_id')
            ->first();

        if (!$mapping) {
            return null;
        }

        $line = BudgetLine::find($mapping->budget_line_id);

        return $line && $this->linePostable($line) ? $line : null;
    }

    /**
     * May an income or expense be recorded against this category?
     *
     * The dropdown is a convenience; this is the rule. A request can always
     * name a category the dropdown never offered.
     */
    public function isUsable(string $mappingType, $categoryId): bool
    {
        if (!$categoryId) {
            return false;
        }

        $model = $mappingType === self::INCOME ? IncomeCategory::class : ExpenseCategory::class;

        $category = $model::find($categoryId);

        if (!$category || (string) $category->status !== '1') {
            return false;
        }

        return (bool) $this->lineFor($mappingType, $categoryId);
    }

    /**
     * The active categories that cannot be used, and why.
     *
     * Reported rather than hidden: a category that silently disappears from a
     * dropdown is a support call, and on this installation ten income
     * categories are in that state while four of them already carry money.
     *
     * @return Collection<int, array{id: int, title: string, reason: string}>
     */
    public function unlinked(string $mappingType): Collection
    {
        $model = $mappingType === self::INCOME ? IncomeCategory::class : ExpenseCategory::class;

        $mappings = DB::table('default_account_mappings')
            ->where('mapping_type', $mappingType)
            ->get()->keyBy('category_id');

        $lines = BudgetLine::all()->keyBy('id');

        return $model::where('status', '1')->orderBy('title')->get()
            ->map(function ($category) use ($mappings, $lines) {
                $mapping = $mappings->get($category->id);

                if (!$mapping) {
                    return [
                        'id' => $category->id,
                        'title' => $category->title,
                        'reason' => __('It has no account mapping at all.'),
                    ];
                }

                if (!$mapping->budget_line_id) {
                    return [
                        'id' => $category->id,
                        'title' => $category->title,
                        'reason' => __('It is mapped to accounts but to no budget line.'),
                    ];
                }

                $line = $lines->get((int) $mapping->budget_line_id);

                if (!$line) {
                    return [
                        'id' => $category->id,
                        'title' => $category->title,
                        'reason' => __('The budget line it points at no longer exists.'),
                    ];
                }

                if ($line->is_header) {
                    return [
                        'id' => $category->id,
                        'title' => $category->title,
                        'reason' => __('It points at :line, which is a heading. A heading totals the lines beneath it and carries no money of its own.', [
                            'line' => $line->code . ' — ' . $line->name,
                        ]),
                    ];
                }

                if (!$line->status) {
                    return [
                        'id' => $category->id,
                        'title' => $category->title,
                        'reason' => __('The budget line it points at, :line, has been retired.', [
                            'line' => $line->code . ' — ' . $line->name,
                        ]),
                    ];
                }

                return null;
            })
            ->filter()
            ->values();
    }

    /**
     * What the screen needs to render the two dropdowns and explain itself.
     *
     * $keep is a category that must stay selectable even if it is not usable —
     * the one already on the record being edited. Without it, a record saved
     * before this rule existed could not be edited at all, and on this
     * installation four income records carrying 20,254,000 are in that state.
     *
     * @return array<string, mixed>
     */
    public function formData(string $mappingType, $keep = null): array
    {
        $categories = $this->categories($mappingType);
        $headings = $this->headings($mappingType);
        $unlinked = $this->unlinked($mappingType);

        $kept = null;

        if ($keep && !$categories->contains('id', (int) $keep)) {
            $model = $mappingType === self::INCOME ? IncomeCategory::class : ExpenseCategory::class;
            $category = $model::find($keep);

            if ($category) {
                $reason = $unlinked->firstWhere('id', $category->id)['reason']
                    ?? __('It does not reach any line of the Income & Expenditure sheet.');

                $kept = [
                    'id' => $category->id,
                    'title' => $category->title,
                    'reason' => $reason,
                ];
            }
        }

        return [
            'bl_headings' => $headings,
            'bl_categories' => $categories,
            'bl_unlinked' => $unlinked,
            'bl_kept' => $kept,
            'bl_no_heading' => self::NO_HEADING,
        ];
    }

    /** A line can carry money only if it exists, is active and is not a heading. */
    protected function linePostable(BudgetLine $line): bool
    {
        return !$line->is_header && (bool) $line->status;
    }

    /**
     * Mapped categories, joined in the database.
     *
     * Note what is NOT filtered: default_account_mappings.status. Every row in
     * that table has status 0 on this installation, so treating it as an
     * active flag would return nothing at all.
     */
    protected function mappedCategories(string $mappingType): Collection
    {
        $table = $mappingType === self::INCOME ? 'income_categories' : 'expense_categories';

        return collect(DB::table($table . ' as c')
            ->join('default_account_mappings as m', function ($join) use ($mappingType) {
                $join->on('m.category_id', '=', 'c.id')
                    ->where('m.mapping_type', '=', $mappingType);
            })
            ->whereNotNull('m.budget_line_id')
            ->where('c.status', '1')
            ->select('c.id', 'c.title', 'm.budget_line_id')
            ->orderBy('c.title')
            ->get());
    }
}
