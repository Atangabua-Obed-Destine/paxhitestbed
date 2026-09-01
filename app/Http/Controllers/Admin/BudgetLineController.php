<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BudgetLine;
use App\Models\ChartOfAccount;
use App\Models\DefaultAccountMapping;
use App\Models\ExpenseCategory;
use App\Models\Faculty;
use App\Models\IncomeCategory;
use App\Services\BudgetReconciliationService;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Managing the lines of the Income & Expenditure sheet.
 *
 * The lines were seeded from the diocesan form and, until this screen existed,
 * could only be changed by editing a seeder — which meant the Bursar could not
 * change their own budget structure without a developer.
 *
 * Two rules shape everything here:
 *
 *  - A line that money already references is never destroyed. Budgeted figures,
 *    tagged expenses and category mappings all point at line ids, so deleting
 *    one would orphan money or silently break the sheet↔ledger reconciliation.
 *    Retiring (status = 0) takes it off the sheet while leaving history intact.
 *  - Lines that came from the diocesan form are marked and warned about rather
 *    than locked. Renaming one breaks comparability with the form every other
 *    institution files, which the Bursar should be told — not prevented from
 *    doing when there is a good reason.
 */
class BudgetLineController extends Controller
{
    protected BudgetReconciliationService $reconciliation;

    public function __construct(BudgetReconciliationService $reconciliation)
    {
        $this->reconciliation = $reconciliation;

        $this->middleware('permission:budget-line-view', ['only' => ['index']]);
        $this->middleware('permission:budget-line-create', ['only' => ['store']]);
        // storeCategory and linkCategory change where money lands on the sheet,
        // so they are edits like any other. They were reachable with only
        // budget-line-view, which let a read-only user repoint a category from
        // one budget line to another. storeCategory additionally checks the
        // category-create permission inside, so this route cannot be used to
        // get round the permissions on the category screens themselves.
        $this->middleware('permission:budget-line-edit', ['only' => [
            'update', 'toggle', 'reorder', 'autoSort', 'storeCategory', 'linkCategory',
        ]]);
        $this->middleware('permission:budget-line-delete', ['only' => ['destroy']]);
    }

    public function index()
    {
        $lines = BudgetLine::with('faculty')
            ->orderByRaw("FIELD(section, 'income', 'expenditure', 'capital')")
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        // Grouped as a tree, not a flat list. sort_order alone is a single run
        // of numbers across a whole section, so a line typed in with the wrong
        // number could sit above its own heading. Ordering by the tree makes
        // the heading structure the thing that decides position, and the
        // numbers merely the order within it.
        $grouped = collect(['income', 'expenditure', 'capital'])
            ->mapWithKeys(function (string $section) use ($lines) {
                return [$section => $this->treeOrdered($lines->where('section', $section))];
            });

        return view('admin.budget-line.index', [
            'title' => __('Budget Lines'),
            'lines' => $lines,
            'grouped' => $grouped,
            'headers' => $lines->where('is_header', true),
            'faculties' => Faculty::orderBy('title')->get(),
            'accountsByLine' => $this->reconciliation->accountsByLine(),
            'categoriesByLine' => $this->reconciliation->categoriesByLine(),
            'usage' => $this->usage(),

            // Everything the mapping modal needs, gathered once rather than
            // queried per row: 66 lines would otherwise mean 66 lookups.
            'accountOptions' => $this->accountOptions(),
            // Existing activity names, so a second line joins one by picking
            // it rather than by retyping it and silently making a new one.
            'profitCentres' => BudgetLine::whereNotNull('profit_centre')
                ->where('profit_centre', '!=', '')
                ->distinct()->orderBy('profit_centre')->pluck('profit_centre'),
            'linkableCategories' => $this->linkableCategories(),
            'suggestions' => $lines->reject->is_header->mapWithKeys(function (BudgetLine $line) {
                return [$line->id => $this->suggestedAccountsFor($line)];
            }),
        ]);
    }

    /**
     * Chart accounts to choose from, grouped by OHADA class.
     *
     * Only the classes a sheet line can legitimately post to: 7 for income, 6
     * for expenditure, 2 for capital, and 5 for the cash side of either.
     *
     * @return array<int, array<int, array{id: int, label: string}>>
     */
    protected function accountOptions(): array
    {
        return ChartOfAccount::whereIn('class_number', [2, 5, 6, 7])
            ->where('is_active', 1)
            ->orderBy('account_code')
            ->get(['id', 'account_code', 'account_name', 'class_number'])
            ->groupBy('class_number')
            ->map(function ($accounts) {
                return $accounts->map(function (ChartOfAccount $account) {
                    return [
                        'id' => $account->id,
                        'label' => $account->account_code . ' ' . $account->account_name,
                    ];
                })->values()->all();
            })
            ->all();
    }

    /**
     * Every category that could be pointed at a line.
     *
     * Built from the CATEGORY tables, not from the mappings. Looping over
     * mappings meant a category appeared only once it already had one — so a
     * category created on the Expense or Income Category screen was invisible
     * here, which is precisely when somebody comes looking for it.
     *
     * A category with no mapping is a first-class row with a null mapping_id;
     * linking it creates the mapping, which is why that case has to ask for the
     * accounts.
     *
     * @return array<int, array>
     */
    protected function linkableCategories(): array
    {
        $current = $this->reconciliation->lineByCategory();

        $tables = [
            'fee_category' => 'fees_categories',
            'income_category' => 'income_categories',
            'expense_category' => 'expense_categories',
        ];

        $rows = [];

        foreach ($tables as $type => $table) {
            $records = DB::table($table . ' as c')
                ->leftJoin('default_account_mappings as m', function ($join) use ($type) {
                    $join->on('m.category_id', '=', 'c.id')
                        ->where('m.mapping_type', '=', $type);
                })
                ->orderBy('c.title')
                ->get(['c.id', 'c.title', 'm.id as mapping_id']);

            foreach ($records as $record) {
                $now = $current[$type . ':' . $record->id] ?? null;

                $rows[] = [
                    'mapping_id' => $record->mapping_id ? (int) $record->mapping_id : null,
                    'type' => $type,
                    'id' => (int) $record->id,
                    'name' => $record->title,
                    'current_line_id' => $now['line_id'] ?? null,
                    'current_line' => $now ? ($now['code'] . ' ' . $now['name']) : null,
                ];
            }
        }

        return $rows;
    }

    /** Does a category of this type and id still exist? */
    protected function categoryExists(string $type, int $id): bool
    {
        $table = [
            'fee_category' => 'fees_categories',
            'income_category' => 'income_categories',
            'expense_category' => 'expense_categories',
        ][$type] ?? null;

        return $table ? DB::table($table)->where('id', $id)->exists() : false;
    }

    /**
     * Which kinds of category may feed a line, by the half of the sheet it sits in.
     *
     * An expense category on an income line would post spending into the income
     * total — the sheet would report money arriving that had actually left. The
     * income side takes fee categories too, because line 610 is genuinely fed by
     * the fee category "First Instalment".
     *
     * @return array<int, string>
     */
    public static function categoryTypesForSection(string $section): array
    {
        return $section === BudgetLine::SECTION_INCOME
            ? ['fee_category', 'income_category']
            : ['expense_category'];
    }

    /**
     * One section's lines as a heading-first tree.
     *
     * Returns ['line' => BudgetLine, 'children' => Collection] rather than
     * decorating the models: an attribute set on an Eloquent model becomes a
     * column it will try to write on the next save, and these models are handed
     * straight to a view that also offers an edit form.
     *
     * A line whose heading is missing surfaces at the top level rather than
     * dropping off the screen — invisible is worse than out of place.
     *
     * @param  \Illuminate\Support\Collection<int, BudgetLine> $lines
     * @return \Illuminate\Support\Collection<int, array{line: BudgetLine, children: \Illuminate\Support\Collection}>
     */
    protected function treeOrdered($lines)
    {
        $lines = $lines->values();
        $ids = $lines->pluck('id')->all();

        $childrenOf = $lines
            ->filter(function (BudgetLine $line) use ($ids) {
                return $line->parent_id && in_array($line->parent_id, $ids, true);
            })
            ->sortBy([['sort_order', 'asc'], ['code', 'asc']])
            ->groupBy('parent_id');

        return $lines
            ->filter(function (BudgetLine $line) use ($ids) {
                return !$line->parent_id || !in_array($line->parent_id, $ids, true);
            })
            ->sortBy([['sort_order', 'asc'], ['code', 'asc']])
            ->map(function (BudgetLine $line) use ($childrenOf) {
                return [
                    'line' => $line,
                    'children' => $childrenOf->get($line->id, collect())->values(),
                ];
            })
            ->values();
    }

    /**
     * Persist a new running order, as dragged.
     *
     * The browser posts the lines of one section in the order they now appear,
     * each with the heading it now sits under. Both facts are written together
     * because they are one fact: where a line is on the sheet.
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'section' => ['required', Rule::in([
                BudgetLine::SECTION_INCOME,
                BudgetLine::SECTION_EXPENDITURE,
                BudgetLine::SECTION_CAPITAL,
            ])],
            'order' => ['required', 'array', 'min:1'],
            'order.*.id' => ['required', 'integer', 'exists:budget_lines,id'],
            'order.*.parent_id' => ['nullable', 'integer', 'exists:budget_lines,id'],
        ]);

        $section = $validated['section'];

        // Only ever reorder within the section that was dragged. A payload
        // naming a line from another section is a bug or a forged request, and
        // silently moving it would corrupt a sheet nobody was looking at.
        $lines = BudgetLine::where('section', $section)
            ->get()
            ->keyBy('id');

        $seen = [];
        $position = 0;
        $errors = [];

        DB::transaction(function () use ($validated, $lines, &$seen, &$position, &$errors) {
            foreach ($validated['order'] as $entry) {
                $id = (int) $entry['id'];
                $parentId = isset($entry['parent_id']) && $entry['parent_id'] !== null
                    ? (int) $entry['parent_id']
                    : null;

                $line = $lines->get($id);
                if (!$line || in_array($id, $seen, true)) {
                    $errors[] = $id;
                    continue;
                }
                $seen[] = $id;

                if ($parentId !== null) {
                    $parent = $lines->get($parentId);

                    // A heading is a top-level thing; nothing files under a
                    // line that is not one, and nothing files under itself.
                    if (!$parent || !$parent->is_header || $parentId === $id) {
                        $parentId = null;
                    }
                }

                // A heading never files under another heading: the sheet is two
                // levels deep, and the Income & Expenditure statement is built
                // on that assumption.
                if ($line->is_header) {
                    $parentId = null;
                }

                $position += 10;

                if ((int) $line->sort_order !== $position || $line->parent_id !== $parentId) {
                    $line->sort_order = $position;
                    $line->parent_id = $parentId;
                    $line->save();
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => __('Order saved.'),
            'reordered' => count($seen),
            'skipped' => $errors,
        ]);
    }

    /**
     * Renumber a section by code, keeping the heading structure.
     *
     * The diocesan codes already encode the intended order (401 sits under the
     * 400 heading), so for a sheet that has drifted this restores the form's
     * own sequence without anyone dragging seventy lines by hand.
     */
    public function autoSort(Request $request)
    {
        $validated = $request->validate([
            'section' => ['required', Rule::in([
                BudgetLine::SECTION_INCOME,
                BudgetLine::SECTION_EXPENDITURE,
                BudgetLine::SECTION_CAPITAL,
            ])],
        ]);

        $lines = BudgetLine::where('section', $validated['section'])->get();

        $childrenOf = $lines
            ->filter(function (BudgetLine $line) {
                return (bool) $line->parent_id;
            })
            ->sortBy('code', SORT_NATURAL)
            ->groupBy('parent_id');

        $topLevel = $lines
            ->filter(function (BudgetLine $line) {
                return !$line->parent_id;
            })
            ->sortBy('code', SORT_NATURAL);

        $position = 0;

        DB::transaction(function () use ($topLevel, $childrenOf, &$position) {
            foreach ($topLevel as $line) {
                $position += 10;
                $line->sort_order = $position;
                $line->save();

                foreach ($childrenOf->get($line->id, collect()) as $child) {
                    $position += 10;
                    $child->sort_order = $position;
                    $child->save();
                }
            }
        });

        Flasher::addSuccess(__('Section sorted by code.'), __('msg_success'));

        return redirect()->route('admin.budget-line.index');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        // Anything added here is by definition not on the diocesan form.
        $data['is_local'] = true;
        $data['status'] = $request->boolean('status', true);

        BudgetLine::create($data);

        Flasher::addSuccess(__('Budget line added.'), __('msg_success'));

        return redirect()->route('admin.budget-line.index');
    }

    public function update(Request $request, $id)
    {
        $line = BudgetLine::findOrFail($id);
        $data = $this->validated($request, $line);

        // is_local records where a line came from, so it is not editable —
        // a diocesan line does not become a local one by being renamed.
        unset($data['is_local']);
        $data['status'] = $request->boolean('status', true);

        // A line cannot be its own parent, nor a parent of its own parent.
        if (($data['parent_id'] ?? null) && $this->wouldLoop($line, (int) $data['parent_id'])) {
            return redirect()->back()->withInput()->withErrors([
                'parent_id' => __('That would make the line its own parent.'),
            ]);
        }

        $line->update($data);

        Flasher::addSuccess(__('Budget line updated.'), __('msg_success'));

        return redirect()->route('admin.budget-line.index');
    }

    /** Take a line off the sheet, or put it back, without touching history. */
    public function toggle($id)
    {
        $line = BudgetLine::findOrFail($id);
        $line->status = !$line->status;
        $line->save();

        Flasher::addSuccess(
            $line->status ? __('Budget line restored to the sheet.') : __('Budget line retired.'),
            __('msg_success')
        );

        return redirect()->route('admin.budget-line.index');
    }

    public function destroy($id)
    {
        $line = BudgetLine::findOrFail($id);
        $usage = $this->usage();
        $used = $usage[$line->id] ?? null;

        // Refuse rather than orphan. The reconciliation between the sheet and
        // the ledger depends on these references resolving.
        if ($used) {
            Flasher::addError(
                __('":line" cannot be deleted — :reasons. Retire it instead: it will leave the sheet and its history stays intact.', [
                    'line' => $line->label,
                    'reasons' => implode(', ', $used),
                ]),
                __('msg_error')
            );

            return redirect()->route('admin.budget-line.index');
        }

        if ($line->children()->exists()) {
            Flasher::addError(__('Move or delete the lines underneath this heading first.'), __('msg_error'));

            return redirect()->route('admin.budget-line.index');
        }

        $line->delete();

        Flasher::addSuccess(__('Budget line deleted.'), __('msg_success'));

        return redirect()->route('admin.budget-line.index');
    }

    /**
     * What references each line, so the screen can explain why one is undeletable.
     *
     * @return array<int, array<int, string>> keyed by budget line id
     */
    protected function usage(): array
    {
        $usage = [];

        $sources = [
            ['budget_allocations', __('it carries budgeted figures')],
            ['expenses', __('expenses are tagged to it')],
            ['incomes', __('income is tagged to it')],
            ['default_account_mappings', __('a category maps to it')],
        ];

        foreach ($sources as [$table, $reason]) {
            $rows = DB::table($table)
                ->whereNotNull('budget_line_id')
                ->select('budget_line_id', DB::raw('COUNT(*) as c'))
                ->groupBy('budget_line_id')
                ->pluck('c', 'budget_line_id');

            foreach ($rows as $lineId => $count) {
                $usage[(int) $lineId][] = $reason . ' (' . $count . ')';
            }
        }

        return $usage;
    }

    /** Would setting this parent create a cycle? */
    protected function wouldLoop(BudgetLine $line, int $parentId): bool
    {
        if ($parentId === (int) $line->id) {
            return true;
        }

        $seen = [];
        $cursor = BudgetLine::find($parentId);
        while ($cursor) {
            if ((int) $cursor->id === (int) $line->id) {
                return true;
            }
            if (isset($seen[$cursor->id])) {
                return true;
            }
            $seen[$cursor->id] = true;
            $cursor = $cursor->parent_id ? BudgetLine::find($cursor->parent_id) : null;
        }

        return false;
    }

    protected function validated(Request $request, ?BudgetLine $line = null): array
    {
        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('budget_lines', 'code')->ignore($line?->id),
            ],
            'name' => ['required', 'string', 'max:191'],
            'name_fr' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:500'],
            'section' => ['required', Rule::in([
                BudgetLine::SECTION_INCOME,
                BudgetLine::SECTION_EXPENDITURE,
                BudgetLine::SECTION_CAPITAL,
            ])],
            'parent_id' => ['nullable', 'exists:budget_lines,id'],
            'faculty_id' => ['nullable', 'exists:faculties,id'],
            'profit_centre' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_header' => ['nullable', 'boolean'],
        ]);

        $data['is_header'] = $request->boolean('is_header');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        // A heading totals its children, so it can carry neither a figure of
        // its own nor a parent heading above it on this sheet.
        if ($data['is_header']) {
            $data['parent_id'] = null;
        }

        return $data;
    }

    /* ===================================================================
     |  Mapping a line to a category
     |
     |  A budget line stays at zero until a transaction category points at it,
     |  and that link could previously only be made at Accounting > Mapping
     |  Settings, with the category itself created on a third screen. Since the
     |  lines are already named the way the category would be ("401 Travelling",
     |  "621 Donations / Grants"), both can be done from here in one action.
     |===================================================================*/

    /** Which kind of category belongs on a line, from the half of the sheet it sits in. */
    protected function categoryTypeFor(BudgetLine $line): string
    {
        // Income lines create a plain income category, never a fee category: a
        // new active FeesCategory appears in Fees Master immediately and becomes
        // billable to students, which is not a side effect this screen should
        // have. An existing fee category can still be LINKED here.
        return $line->section === BudgetLine::SECTION_INCOME
            ? 'income_category'
            : 'expense_category';
    }

    /** The OHADA class the non-cash side of the entry should come from. */
    protected function accountClassFor(BudgetLine $line): int
    {
        switch ($line->section) {
            case BudgetLine::SECTION_INCOME:
                return 7;
            case BudgetLine::SECTION_CAPITAL:
                return 2;
            default:
                return 6;
        }
    }

    /**
     * Sensible debit and credit accounts for a line that has none yet.
     *
     * Every existing mapping follows one rule: one side is always cash — the
     * debit for income, the credit for expenditure — and only the other side
     * varies. So the operator has to choose one account, not two.
     *
     * For that varying side, a sibling under the same heading is the best
     * available guess, because a heading groups like with like. Where no
     * sibling is mapped the caller gets null and must choose; a wrong default
     * silently posts money to the wrong account, which is worse than an empty
     * picker.
     *
     * @return array{debit_account_id: ?int, credit_account_id: ?int, from_sibling: bool}
     */
    protected function suggestedAccountsFor(BudgetLine $line): array
    {
        $isIncome = $line->section === BudgetLine::SECTION_INCOME;
        $cashId = $this->defaultCashAccountId();

        $siblingAccountId = null;
        $siblingLine = null;

        if ($line->parent_id) {
            $siblingIds = BudgetLine::where('parent_id', $line->parent_id)
                ->where('id', '!=', $line->id)
                ->pluck('id');

            $sibling = DefaultAccountMapping::whereIn('budget_line_id', $siblingIds)
                ->whereNotNull('budget_line_id')
                ->latest('id')
                ->first();

            if ($sibling) {
                $siblingAccountId = $isIncome
                    ? $sibling->credit_account_id
                    : $sibling->debit_account_id;

                $siblingLine = BudgetLine::find($sibling->budget_line_id);
            }
        }

        return [
            'debit_account_id' => $isIncome ? $cashId : $siblingAccountId,
            'credit_account_id' => $isIncome ? $siblingAccountId : $cashId,
            // Where the guess came from, so the modal can show its working. A
            // sibling under the same heading is a starting point, not an
            // answer: "Meetings and Seminars" borrowing the account from
            // "Telephone / Postage" looks reasonable and is wrong. Naming the
            // source is what turns a silent default into a prompt to check.
            'from_sibling' => $siblingAccountId !== null,
            'sibling_label' => isset($siblingLine) && $siblingLine ? $siblingLine->label : null,
        ];
    }

    /**
     * The cash account the institution actually posts through.
     *
     * Taken from what the existing mappings use rather than a hardcoded code,
     * so an institution whose cash account is not 571 is not quietly given the
     * wrong one.
     */
    protected function defaultCashAccountId(): ?int
    {
        $mostUsed = DB::table('default_account_mappings')
            ->where('mapping_type', 'expense_category')
            ->whereNotNull('credit_account_id')
            ->selectRaw('credit_account_id, COUNT(*) as uses')
            ->groupBy('credit_account_id')
            ->orderByDesc('uses')
            ->value('credit_account_id');

        if ($mostUsed) {
            return (int) $mostUsed;
        }

        return ChartOfAccount::where('class_number', 5)
            ->where('is_active', 1)
            ->orderBy('account_code')
            ->value('id');
    }

    /** Create a category named after this line, and point it at the line. */
    public function storeCategory(Request $request, $id)
    {
        $line = BudgetLine::findOrFail($id);

        if ($line->is_header) {
            Flasher::addError(__('A heading totals the lines beneath it and carries no money of its own, so nothing maps to it.'), __('msg_error'));
            return redirect()->route('admin.budget-line.index');
        }

        $type = $this->categoryTypeFor($line);

        // This route must not become a way round the permissions on the
        // category screens themselves.
        $permission = $type === 'income_category' ? 'income-category-create' : 'expense-category-create';
        if (!Auth::user() || !Auth::user()->can($permission)) {
            Flasher::addError(__('You do not have permission to create that kind of category.'), __('msg_error'));
            return redirect()->route('admin.budget-line.index');
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'debit_account_id' => ['required', 'exists:chart_of_accounts,id'],
            'credit_account_id' => ['required', 'exists:chart_of_accounts,id', 'different:debit_account_id'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $model = $type === 'income_category' ? IncomeCategory::class : ExpenseCategory::class;

        if ($model::where('title', $data['title'])->exists()) {
            Flasher::addError(__('A category called ":title" already exists. Use "Link an existing category" instead.', [
                'title' => $data['title'],
            ]), __('msg_error'));

            return redirect()->route('admin.budget-line.index');
        }

        DB::transaction(function () use ($model, $type, $data, $line) {
            $category = new $model();
            $category->title = $data['title'];
            $category->slug = Str::slug($data['title'], '-');
            $category->description = $data['description'] ?? null;
            $category->status = '1';
            $category->save();

            // Keyed the same way Mapping Settings keys it, so the two screens
            // write the same row rather than two competing ones.
            DefaultAccountMapping::updateOrCreate(
                ['mapping_type' => $type, 'category_id' => $category->id],
                [
                    'debit_account_id' => $data['debit_account_id'],
                    'credit_account_id' => $data['credit_account_id'],
                    'budget_line_id' => $line->id,
                    'description' => $data['description'] ?? null,
                    'status' => 'active',
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]
            );
        });

        Flasher::addSuccess(__('":title" created and pointed at :line. Money coded to it will now appear on that line.', [
            'title' => $data['title'],
            'line' => $line->label,
        ]), __('msg_success'));

        return redirect()->route('admin.budget-line.index');
    }

    /** Point a category that already exists at this line. */
    public function linkCategory(Request $request, $id)
    {
        $line = BudgetLine::findOrFail($id);

        if ($line->is_header) {
            Flasher::addError(__('A heading totals the lines beneath it and carries no money of its own, so nothing maps to it.'), __('msg_error'));
            return redirect()->route('admin.budget-line.index');
        }

        $data = $request->validate([
            // Either an existing mapping to repoint, or a category that has
            // never been mapped and therefore needs one making.
            'mapping_id' => ['nullable', 'exists:default_account_mappings,id'],
            'category_type' => ['nullable', 'in:fee_category,income_category,expense_category'],
            'category_id' => ['nullable', 'integer'],
            'debit_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'credit_account_id' => ['nullable', 'exists:chart_of_accounts,id', 'different:debit_account_id'],
        ]);

        $mapping = !empty($data['mapping_id'])
            ? DefaultAccountMapping::find($data['mapping_id'])
            : null;

        if (!$mapping) {
            // A category with no mapping cannot simply be repointed — there is
            // nothing to point. One has to be made, and it needs accounts:
            // debit_account_id and credit_account_id are NOT NULL, and the
            // ledger posting is built from them, so a mapping without accounts
            // would let money reach the sheet and never the books.
            if (empty($data['category_type']) || empty($data['category_id'])) {
                Flasher::addError(__('Choose a category to point at this line.'), __('msg_error'));

                return redirect()->route('admin.budget-line.index');
            }

            if (!$this->categoryExists($data['category_type'], (int) $data['category_id'])) {
                Flasher::addError(__('That category no longer exists.'), __('msg_error'));

                return redirect()->route('admin.budget-line.index');
            }

            if (empty($data['debit_account_id']) || empty($data['credit_account_id'])) {
                Flasher::addError(
                    __('That category has never been mapped, so it needs a debit and a credit account before it can feed a line.'),
                    __('msg_error')
                );

                return redirect()->route('admin.budget-line.index');
            }

            // Only a category type that suits this half of the sheet. An expense
            // category on an income line would post spending into income.
            if (!in_array($data['category_type'], static::categoryTypesForSection($line->section), true)) {
                Flasher::addError(
                    __('A :type cannot feed a :section line.', [
                        'type' => str_replace('_', ' ', $data['category_type']),
                        'section' => $line->section,
                    ]),
                    __('msg_error')
                );

                return redirect()->route('admin.budget-line.index');
            }

            $mapping = new DefaultAccountMapping();
            $mapping->mapping_type = $data['category_type'];
            $mapping->category_id = (int) $data['category_id'];
            $mapping->debit_account_id = (int) $data['debit_account_id'];
            $mapping->credit_account_id = (int) $data['credit_account_id'];
            $mapping->status = 'active';
            $mapping->created_by = Auth::id();
        } elseif (!in_array($mapping->mapping_type, static::categoryTypesForSection($line->section), true)) {
            Flasher::addError(
                __('A :type cannot feed a :section line.', [
                    'type' => str_replace('_', ' ', $mapping->mapping_type),
                    'section' => $line->section,
                ]),
                __('msg_error')
            );

            return redirect()->route('admin.budget-line.index');
        }

        $previous = $mapping->budget_line_id
            ? BudgetLine::find($mapping->budget_line_id)
            : null;

        $mapping->budget_line_id = $line->id;
        $mapping->updated_by = Auth::id();
        $mapping->save();

        // Say what moved. Repointing a category takes its money OFF whatever
        // line it fed before, and that line will drop to zero on the next
        // sheet without anything else announcing it.
        $message = $previous && $previous->id !== $line->id
            ? __('Category moved from :from to :to. Figures follow it, so :from will now read zero.', [
                'from' => $previous->label,
                'to' => $line->label,
            ])
            : __('Category pointed at :to.', ['to' => $line->label]);

        Flasher::addSuccess($message, __('msg_success'));

        return redirect()->route('admin.budget-line.index');
    }

}
