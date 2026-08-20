<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BudgetLine;
use App\Models\Faculty;
use App\Services\BudgetReconciliationService;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $this->middleware('permission:budget-line-edit', ['only' => ['update', 'toggle']]);
        $this->middleware('permission:budget-line-delete', ['only' => ['destroy']]);
    }

    public function index()
    {
        $lines = BudgetLine::with('faculty')
            ->orderByRaw("FIELD(section, 'income', 'expenditure', 'capital')")
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        return view('admin.budget-line.index', [
            'title' => __('Budget Lines'),
            'lines' => $lines,
            'grouped' => $lines->groupBy('section'),
            'headers' => $lines->where('is_header', true),
            'faculties' => Faculty::orderBy('title')->get(),
            'accountsByLine' => $this->reconciliation->accountsByLine(),
            'usage' => $this->usage(),
        ]);
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
}
