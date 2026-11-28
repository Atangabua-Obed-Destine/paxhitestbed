<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Equipment\Desk;
use App\Models\Equipment\Item;
use App\Services\Equipment\DeskRefusal;
use App\Services\Equipment\LoanDesk;
use App\Services\Equipment\ScanResolver;
use App\Services\Mailbox\Members;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The equipment lending desk: one page, scans in any order, nothing reloads.
 *
 * Scanning a card sets the person; scanning a sticker brings up the item.
 * An item that is in is lent to the person; an item that is out is checked
 * in, with the person as whoever brought it back.
 */
class EquipmentDeskController extends Controller
{
    public function __construct(protected LoanDesk $desk, protected ScanResolver $resolver)
    {
        $this->middleware('permission:equipment-desk');
    }

    public function desk()
    {
        return view('admin.equipment.desk', [
            'title' => __('Equipment desk'),
            'desks' => Desk::active(),
            'lecturers' => User::where('status', '1')->orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'staff_id']),
            'dueChoices' => $this->desk->dueChoices(),
        ]);
    }

    /** Whatever was scanned: a card or a sticker. */
    public function scan(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string|max:300']);
        $hit = $this->resolver->resolve($request->input('code'), ScanResolver::EQUIPMENT);

        if (!$hit) {
            return response()->json(['ok' => false, 'message' => __('Nothing matches ":code". Check the card or sticker and scan again.', ['code' => mb_substr(trim($request->input('code')), 0, 60)])], 404);
        }
        if ($hit['kind'] === 'person') {
            return response()->json(['ok' => true, 'kind' => 'person', 'person' => $this->desk->person($hit['member'])]);
        }

        return response()->json(['ok' => true, 'kind' => 'item', 'item' => $this->desk->item($hit['item'])]);
    }

    public function lend(Request $request): JsonResponse
    {
        $request->validate([
            'person' => 'required|string|max:40',
            'item_id' => 'required|integer',
            'purpose' => 'required|in:self,class,lecturer',
            'purpose_detail' => 'nullable|string|max:190',
            'lecturer_id' => 'nullable|integer',
            'due_at' => 'required|date',
            'desk_id' => 'nullable|integer|exists:equipment_desks,id',
        ]);

        $person = $this->member($request->input('person'));
        $item = Item::find($request->input('item_id'));
        if (!$person || !$item) {
            return $this->refuse(__('Scan the card and the sticker again.'));
        }

        try {
            $loan = $this->desk->lend($person, $item, [
                'purpose' => $request->input('purpose'),
                'purpose_detail' => $request->input('purpose_detail'),
                'lecturer_id' => $request->input('lecturer_id'),
                'due_at' => $request->input('due_at'),
                'desk_id' => $request->input('desk_id'),
            ], Auth::guard('web')->id());
        } catch (DeskRefusal $e) {
            return $this->refuse($e->getMessage());
        }

        return response()->json([
            'ok' => true,
            'message' => __(':code lent to :name, due back :due.', [
                'code' => $item->code, 'name' => $this->desk->describe($person)['name'], 'due' => $this->desk->when($loan->due_at, true),
            ]),
            'item' => $this->desk->item($item->fresh()),
            'person' => $this->desk->person($person),
        ]);
    }

    public function checkIn(Request $request): JsonResponse
    {
        $request->validate([
            'item_id' => 'required|integer',
            'returner' => 'nullable|string|max:40',
            'back' => 'nullable|array',
            'back.*' => 'string|max:120',
            'condition' => 'nullable|in:fine,damaged,missing',
            'note' => 'nullable|string|max:1000',
        ]);

        $item = Item::find($request->input('item_id'));
        if (!$item) {
            return $this->refuse(__('Scan the sticker again.'));
        }

        try {
            $loan = $this->desk->checkIn(
                $item,
                $this->member($request->input('returner')),
                (array) $request->input('back', []),
                $request->input('condition'),
                $request->input('note'),
                Auth::guard('web')->id()
            );
        } catch (DeskRefusal $e) {
            return $this->refuse($e->getMessage());
        }

        $message = $loan->follow_up
            ? __(':code checked in. It is on the follow-up list until the problem is cleared.', ['code' => $item->code])
            : __(':code checked in. It is back on the shelf.', ['code' => $item->code]);

        return response()->json(['ok' => true, 'message' => $message, 'item' => $this->desk->item($item->fresh())]);
    }

    /** "staff:12" or "student:40", and only a person who exists. */
    protected function member(?string $key): ?array
    {
        if (!$key || !preg_match('/^(staff|student):(\d+)$/', $key, $m)) {
            return null;
        }
        $member = [$m[1], (int) $m[2]];

        return app(Members::class)->record($member) ? $member : null;
    }

    protected function refuse(string $message): JsonResponse
    {
        return response()->json(['ok' => false, 'message' => $message], 422);
    }
}
