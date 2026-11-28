<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Equipment\Desk;
use App\Models\Equipment\Item;
use App\Models\Equipment\Loan;
use App\Models\FixedAsset;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Flasher\Laravel\Facade\Flasher;

/**
 * The list of lendable equipment, and its stickers.
 *
 * A sticker code can never be the same as somebody's staff ID or matricule:
 * the desk would not know whether the scan was a person or a projector.
 */
class EquipmentItemController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:equipment-manage|equipment-view|equipment-desk', ['only' => ['index']]);
        $this->middleware('permission:equipment-manage', ['except' => ['index']]);
    }

    public function index(Request $request)
    {
        $items = Item::with(['desk', 'openLoan'])
            ->when($request->filled('desk'), fn ($q) => $q->where('desk_id', $request->input('desk')))
            ->when($request->filled('state'), fn ($q) => $q->where('state', $request->input('state')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . trim($request->input('q')) . '%';
                $q->where(fn ($w) => $w->where('code', 'like', $term)->orWhere('name', 'like', $term)->orWhere('category', 'like', $term));
            })
            ->orderBy('code')
            ->get();

        return view('admin.equipment.items.index', [
            'title' => __('Equipment list'),
            'items' => $items,
            'desks' => Desk::orderBy('name')->get(),
            'states' => Item::stateLabels(),
            'filters' => $request->only(['desk', 'state', 'q']),
        ]);
    }

    public function create()
    {
        return view('admin.equipment.items.form', $this->formData(new Item(['state' => Item::ACTIVE]), __('Add equipment')));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = Auth::guard('web')->id();
        $item = Item::create($data);

        Flasher::addSuccess(__(':code added. Print its sticker from the list.', ['code' => $item->code]), __('msg_success'));

        return redirect()->route('admin.equipment.items.index');
    }

    public function edit(Item $item)
    {
        return view('admin.equipment.items.form', $this->formData($item, __('Edit :code', ['code' => $item->code])));
    }

    public function update(Request $request, Item $item)
    {
        $data = $this->validated($request, $item);
        if ($data['code'] !== $item->code && $item->openLoan()->exists()) {
            return back()->withInput()->withErrors(['code' => __('Check :code in before changing its code: its sticker is out with the borrower.', ['code' => $item->code])]);
        }
        $data['updated_by'] = Auth::guard('web')->id();
        $item->update($data);

        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->route('admin.equipment.items.index');
    }

    public function destroy(Item $item)
    {
        if ($item->openLoan()->exists()) {
            Flasher::addError(__(':code is out. Check it in before removing it.', ['code' => $item->code]), __('msg_error'));

            return back();
        }
        // Soft delete: its loans stay in the histories.
        $item->delete();
        Flasher::addSuccess(__(':code removed from the list. Its history is kept.', ['code' => $item->code]), __('msg_success'));

        return redirect()->route('admin.equipment.items.index');
    }

    /** A printable sheet of stickers: the chosen items, or all of them. */
    public function stickers(Request $request)
    {
        $ids = collect(explode(',', (string) $request->input('ids')))->map(fn ($v) => (int) $v)->filter()->all();
        $items = Item::with('desk')->when($ids, fn ($q) => $q->whereIn('id', $ids))
            ->whereIn('state', [Item::ACTIVE, Item::MAINTENANCE])->orderBy('code')->get();

        return view('admin.equipment.items.stickers', ['title' => __('Equipment stickers'), 'items' => $items]);
    }

    protected function formData(Item $item, string $title): array
    {
        return [
            'title' => $title,
            'item' => $item,
            'desks' => Desk::orderBy('name')->get(),
            'states' => Item::stateLabels(),
            'assets' => class_exists(FixedAsset::class)
                ? FixedAsset::orderBy('asset_code')->get(['id', 'asset_code', 'name'])
                : collect(),
        ];
    }

    protected function validated(Request $request, ?Item $item = null): array
    {
        $request->merge(['code' => Item::normaliseCode($request->input('code'))]);

        $request->validate([
            'code' => [
                'required', 'string', 'min:2', 'max:40', 'regex:/^[A-Z0-9][A-Z0-9\-.]*$/',
                Rule::unique('equipment_items', 'code')->ignore($item?->id),
                function ($attribute, $value, $fail) {
                    if (User::where('staff_id', $value)->exists()
                        || StudentEnroll::where('matricule', $value)->exists()
                        || Student::where('student_id', $value)->exists()) {
                        $fail(__('":code" is somebody\'s ID number. Choose a code like PRJ-003 so scans cannot be confused.', ['code' => $value]));
                    }
                },
            ],
            'name' => 'required|string|max:150',
            'category' => 'nullable|string|max:80',
            'accessories' => 'nullable|string|max:1000',
            'students_may_borrow' => 'nullable|boolean',
            'desk_id' => 'nullable|integer|exists:equipment_desks,id',
            'fixed_asset_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:2000',
            'state' => ['required', Rule::in(Item::STATES)],
        ], [
            'code.regex' => __('Use only capital letters, numbers, dashes and dots, so the sticker\'s barcode can carry it.'),
        ]);

        $accessories = collect(preg_split('/[\r\n,]+/', (string) $request->input('accessories')))
            ->map(fn ($a) => trim($a))->filter()->unique()->take(20)->values()->all();

        return [
            'code' => $request->input('code'),
            'name' => trim($request->input('name')),
            'category' => $request->filled('category') ? trim($request->input('category')) : null,
            'accessories' => $accessories,
            'students_may_borrow' => $request->boolean('students_may_borrow'),
            'desk_id' => $request->input('desk_id') ?: null,
            'fixed_asset_id' => $request->input('fixed_asset_id') ?: null,
            'notes' => $request->input('notes'),
            'state' => $request->input('state'),
        ];
    }
}
