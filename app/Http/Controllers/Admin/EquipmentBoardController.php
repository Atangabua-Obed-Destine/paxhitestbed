<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Equipment\Desk;
use App\Models\Equipment\Item;
use App\Models\Equipment\Loan;
use App\Services\Equipment\DeskRefusal;
use App\Services\Equipment\LoanDesk;
use App\Services\Mailbox\Members;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * What is out right now, what needs following up, and the history of any
 * item or any person.
 */
class EquipmentBoardController extends Controller
{
    public function __construct(protected LoanDesk $desk)
    {
        $this->middleware('permission:equipment-view|equipment-desk|equipment-manage', ['only' => ['board', 'itemHistory', 'personHistory']]);
        $this->middleware('permission:equipment-desk', ['only' => ['clearFollowUp', 'markLost']]);
    }

    public function board(Request $request)
    {
        $deskId = $request->filled('desk') ? (int) $request->input('desk') : null;

        $open = Loan::with(['item', 'desk'])->open()
            ->when($deskId, fn ($q) => $q->where('desk_id', $deskId))
            ->orderBy('due_at')->get();

        $followUps = Loan::with(['item', 'desk'])->where('follow_up', true)->whereNull('follow_up_cleared_at')
            ->when($deskId, fn ($q) => $q->where('desk_id', $deskId))
            ->orderByDesc('returned_at')->get();

        $items = Item::where('state', Item::ACTIVE)->when($deskId, fn ($q) => $q->where('desk_id', $deskId));
        $itemCount = (clone $items)->count();
        $outIds = Loan::open()->pluck('equipment_item_id')->all();
        $onShelf = (clone $items)->whereNotIn('id', $outIds)->count();

        return view('admin.equipment.board', [
            'title' => __('Out right now'),
            'rows' => $open->map(fn (Loan $l) => ['loan' => $l] + $this->desk->loan($l))->all(),
            'followUps' => $followUps->map(fn (Loan $l) => [
                'loan' => $l,
                'borrower' => $this->desk->describe($l->borrower()),
                'returner' => $l->returner() ? $this->desk->describe($l->returner()) : null,
                'missing' => $l->missingAccessories(),
                'returned' => $this->desk->when($l->returned_at),
            ])->all(),
            'summary' => [
                'items' => $itemCount,
                'out' => $open->count(),
                'overdue' => $open->filter(fn (Loan $l) => $l->isOverdue())->count(),
                'shelf' => $onShelf,
                'follow' => $followUps->count(),
            ],
            'desks' => Desk::orderBy('name')->get(),
            'deskId' => $deskId,
        ]);
    }

    public function itemHistory(Item $item)
    {
        $loans = Loan::with('desk', 'lender', 'receiver')->where('equipment_item_id', $item->id)->orderByDesc('lent_at')->get();

        return view('admin.equipment.history', [
            'title' => __('History of :code', ['code' => $item->code]),
            'subject' => ['kind' => 'item', 'item' => $item, 'card' => $this->desk->item($item)],
            'rows' => $this->historyRows($loans),
        ]);
    }

    public function personHistory(string $type, int $id)
    {
        abort_unless(in_array($type, [Members::STAFF, Members::STUDENT], true), 404);
        $person = $this->desk->describe([$type, $id]);
        abort_unless($person['found'], 404);

        $loans = Loan::with('item', 'desk', 'lender', 'receiver')->of([$type, $id])->orderByDesc('lent_at')->get();

        return view('admin.equipment.history', [
            'title' => __('Equipment borrowed by :name', ['name' => $person['name']]),
            'subject' => ['kind' => 'person', 'person' => $person],
            'rows' => $this->historyRows($loans),
        ]);
    }

    public function clearFollowUp(Request $request, Loan $loan)
    {
        try {
            $this->desk->clearFollowUp($loan, $request->input('note'), Auth::guard('web')->id());
            Flasher::addSuccess(__('Follow-up cleared for :code.', ['code' => $loan->item->code]), __('msg_success'));
        } catch (DeskRefusal $e) {
            Flasher::addError($e->getMessage(), __('msg_error'));
        }

        return back();
    }

    public function markLost(Request $request, Loan $loan)
    {
        try {
            $this->desk->markLost($loan, $request->input('note'), Auth::guard('web')->id());
            Flasher::addSuccess(__(':code marked lost. It stays on the borrower\'s history.', ['code' => $loan->item->code]), __('msg_success'));
        } catch (DeskRefusal $e) {
            Flasher::addError($e->getMessage(), __('msg_error'));
        }

        return back();
    }

    protected function historyRows($loans): array
    {
        return $loans->map(fn (Loan $l) => [
            'loan' => $l,
            'borrower' => $this->desk->describe($l->borrower()),
            'returner' => $l->returner() ? $this->desk->describe($l->returner()) : null,
            'purpose' => $this->desk->purposeText($l),
            'lent' => $this->desk->when($l->lent_at),
            'due' => $this->desk->when($l->due_at),
            'returned' => $this->desk->when($l->returned_at),
            'late' => $l->returned_at ? $l->returned_at->gt($l->due_at) : $l->isOverdue(),
            'status' => $this->desk->dueStatus($l),
            'missing' => $l->missingAccessories(),
        ])->all();
    }
}
