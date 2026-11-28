<?php

namespace App\Services\Equipment;

use App\Models\Equipment\Item;
use App\Models\Equipment\Loan;
use App\Models\Equipment\Setting;
use App\Models\IssueReturn;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Services\Mailbox\Mailbox;
use App\Services\Mailbox\Members;
use App\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The lending desk's rules, in one place.
 *
 * Every refusal is a DeskRefusal whose message is written for the attendant
 * standing at the desk: what is wrong and what to do about it.
 *
 * The rules agreed with the school:
 *   - an attendant scans; borrowers never scan themselves
 *   - students may borrow only items marked for students
 *   - anyone may bring an item back, but their card is scanned and recorded;
 *     the borrower stays responsible until it is checked in
 *   - someone with an overdue item can borrow nothing else until it is back
 *     (and, when the setting is on, nothing from the library either)
 */
class LoanDesk
{
    public function __construct(protected Members $members)
    {
    }

    public function settings(): Setting
    {
        return Setting::current();
    }

    // ── People ────────────────────────────────────────────────────────────

    /** Who this is, as the desk shows them. */
    public function describe(array $m): array
    {
        $r = $this->members->record($m);
        $student = $m[0] === Members::STUDENT;
        if (!$r) {
            return ['found' => false, 'type' => $m[0], 'id' => (int) $m[1], 'name' => __('Unknown person'), 'role' => '', 'code' => '', 'photo' => null, 'initials' => '?', 'active' => false];
        }

        $code = $student ? (string) $r->student_id : (string) $r->staff_id;
        $role = $this->members->detail($m);
        if ($student) {
            $enrol = StudentEnroll::with('program:id,title,shortcode')->where('student_id', $r->id)
                ->where('status', '<>', '0')->orderByDesc('id')->first();
            $role = trim(__('Student') . ($enrol && $enrol->program ? ', ' . ($enrol->program->shortcode ?: $enrol->program->title) : ''));
            $code = $enrol->matricule ?? $code;
        }

        $folder = $student ? 'uploads/student/' : 'uploads/user/';
        $photo = $r->photo && file_exists(public_path($folder . $r->photo)) ? asset($folder . $r->photo) : null;
        $name = $this->members->name($m);

        return [
            'found' => true,
            'type' => $m[0],
            'id' => (int) $m[1],
            'key' => Members::key($m),
            'name' => $name,
            'role' => $role,
            'code' => $code,
            'photo' => $photo,
            'initials' => $this->initials($name),
            'active' => $this->members->active($m),
        ];
    }

    /** Everything the desk needs to know about a borrower before lending. */
    public function person(array $m): array
    {
        $loans = Loan::with('item')->of($m)->open()->orderBy('due_at')->get();

        return $this->describe($m) + [
            'holding' => $loans->map(fn (Loan $l) => [
                'code' => $l->item->code,
                'name' => $l->item->name,
                'due' => $this->when($l->due_at, true),
                'overdue' => $l->isOverdue(),
            ])->all(),
            'blockers' => $this->blockers($m),
        ];
    }

    /** Why this person may not borrow right now; empty when they may. */
    public function blockers(array $m, bool $forLibrary = false): array
    {
        $out = [];
        if (!$this->members->active($m)) {
            $out[] = __('This card belongs to someone who is no longer active (left or suspended). Nothing can be lent to them.');

            return $out;
        }

        $checkEquipment = !$forLibrary || $this->settings()->block_across_desks;
        $checkLibrary = $forLibrary || $this->settings()->block_across_desks;

        if ($checkEquipment) {
            $late = Loan::with('item')->of($m)->overdue()->orderBy('due_at')->first();
            if ($late) {
                $out[] = __(':code (:name) was due back :due and is still out. Nothing else can be lent until it is returned.', [
                    'code' => $late->item->code, 'name' => $late->item->name, 'due' => $this->when($late->due_at, true),
                ]);
            }
        }

        if ($checkLibrary) {
            $book = $this->overdueLibraryBook($m);
            if ($book) {
                $out[] = __('Has a library book overdue: :title, due :due. It has to be returned first.', ['title' => $book['title'], 'due' => $book['due']]);
            }
        }

        return $out;
    }

    protected function overdueLibraryBook(array $m): ?array
    {
        $model = $m[0] === Members::STUDENT ? Student::find($m[1]) : User::find($m[1]);
        $member = $model ? $model->member()->first() : null;
        if (!$member) {
            return null;
        }
        $issue = IssueReturn::with('book:id,title')->where('member_id', $member->id)->where('status', 1)
            ->whereDate('due_date', '<', today())->orderBy('due_date')->first();

        return $issue ? ['title' => optional($issue->book)->title ?? __('a book'), 'due' => Carbon::parse($issue->due_date)->format('d M')] : null;
    }

    // ── Items ─────────────────────────────────────────────────────────────

    public function item(Item $item): array
    {
        $item->loadMissing('desk');
        $loan = Loan::with('item')->where('equipment_item_id', $item->id)->open()->first();

        return [
            'id' => $item->id,
            'code' => $item->code,
            'name' => $item->name,
            'category' => $item->category,
            'accessories' => $item->accessoryList(),
            'students' => (bool) $item->students_may_borrow,
            'state' => $item->state,
            'state_label' => $item->stateLabel(),
            'desk' => optional($item->desk)->name,
            'lendable' => $item->state === Item::ACTIVE && !$loan,
            'loan' => $loan ? $this->loan($loan) : null,
        ];
    }

    public function loan(Loan $loan): array
    {
        return [
            'id' => $loan->id,
            'borrower' => $this->describe($loan->borrower()),
            'purpose' => $this->purposeText($loan),
            'lent_at' => $this->when($loan->lent_at),
            'due_at' => $this->when($loan->due_at),
            'lent_at_mid' => $this->when($loan->lent_at, true),
            'due_at_mid' => $this->when($loan->due_at, true),
            'overdue' => $loan->isOverdue(),
            'status' => $this->dueStatus($loan),
            'accessories_out' => (array) $loan->accessories_out,
        ];
    }

    // ── Lending and checking in ───────────────────────────────────────────

    /**
     * Lend an item.
     *
     * @param array{purpose?:string, purpose_detail?:?string, lecturer_id?:?int, due_at:mixed, desk_id?:?int} $d
     */
    public function lend(array $m, Item $item, array $d, int $by): Loan
    {
        $purpose = $d['purpose'] ?? 'self';
        $purpose = in_array($purpose, Loan::PURPOSES, true) ? $purpose : 'self';
        $detail = trim((string) ($d['purpose_detail'] ?? ''));
        $due = $d['due_at'] instanceof Carbon ? $d['due_at'] : Carbon::parse($d['due_at']);
        $lecturerId = null;

        if ($due->lte(now())) {
            throw new DeskRefusal(__('The due time has to be later than now.'));
        }
        if ($purpose === 'class' && $detail === '') {
            throw new DeskRefusal(__('Say which class it is for.'));
        }
        if ($purpose === 'lecturer') {
            $lecturer = User::where('id', (int) ($d['lecturer_id'] ?? 0))->where('status', '1')->first();
            if (!$lecturer) {
                throw new DeskRefusal(__('Choose the lecturer it is for.'));
            }
            if ($m[0] === Members::STAFF && (int) $m[1] === (int) $lecturer->id) {
                $purpose = 'self';
            } else {
                $lecturerId = (int) $lecturer->id;
            }
        }

        $loan = DB::transaction(function () use ($m, $item, $purpose, $detail, $lecturerId, $due, $d, $by) {
            // Lock the item so two desks scanning it at once cannot both lend it.
            $item = Item::whereKey($item->id)->lockForUpdate()->first();
            if (!$item) {
                throw new DeskRefusal(__('That item is no longer on the equipment list.'));
            }

            if ($blockers = $this->blockers($m)) {
                throw new DeskRefusal($blockers[0]);
            }
            if ($item->state !== Item::ACTIVE) {
                throw new DeskRefusal(__(':code is marked ":state", so it cannot be lent.', ['code' => $item->code, 'state' => $item->stateLabel()]));
            }
            $open = Loan::where('equipment_item_id', $item->id)->open()->lockForUpdate()->first();
            if ($open) {
                throw new DeskRefusal(__(':code is already out with :name since :when. Check it in first.', [
                    'code' => $item->code, 'name' => $this->members->name($open->borrower()), 'when' => $this->when($open->lent_at, true),
                ]));
            }
            if ($m[0] === Members::STUDENT && !$item->students_may_borrow) {
                throw new DeskRefusal(__(':code is not marked for students. A member of staff has to borrow it.', ['code' => $item->code]));
            }

            return Loan::create([
                'equipment_item_id' => $item->id,
                'borrower_type' => $m[0],
                'borrower_id' => (int) $m[1],
                'purpose' => $purpose,
                'purpose_detail' => $purpose === 'class' ? mb_substr($detail, 0, 190) : null,
                'lecturer_id' => $lecturerId,
                'desk_id' => $d['desk_id'] ?? $item->desk_id,
                'lent_at' => now(),
                'due_at' => $due,
                'lent_by' => $by,
                'accessories_out' => $item->accessoryList(),
                'status' => Loan::OUT,
            ]);
        });

        if ($loan->lecturer_id) {
            $this->tellLecturer($loan);
        }

        return $loan;
    }

    /**
     * Check an item back in.
     *
     * @param array|null $returner whoever brought it back: [type, id]
     * @param string[]   $back     the accessories that came back
     */
    public function checkIn(Item $item, ?array $returner, array $back, ?string $condition, ?string $note, int $by): Loan
    {
        if (!$returner) {
            throw new DeskRefusal(__('Scan the card of whoever brought it back.'));
        }
        $note = trim((string) $note);

        return DB::transaction(function () use ($item, $returner, $back, $condition, $note, $by) {
            Item::whereKey($item->id)->lockForUpdate()->first();
            $loan = Loan::where('equipment_item_id', $item->id)->open()->lockForUpdate()->first();
            if (!$loan) {
                throw new DeskRefusal(__(':code is not out, so there is nothing to check in.', ['code' => $item->code]));
            }

            $out = (array) $loan->accessories_out;
            $back = array_values(array_intersect($out, array_map('strval', $back)));
            $missing = array_diff($out, $back);
            $condition = in_array($condition, [Loan::FINE, Loan::DAMAGED, Loan::MISSING], true) ? $condition : Loan::FINE;
            if ($missing && $condition === Loan::FINE) {
                $condition = Loan::MISSING;
            }
            if ($condition !== Loan::FINE && $note === '') {
                throw new DeskRefusal(__('Add a short note saying what is damaged or missing.'));
            }

            $loan->update([
                'returned_at' => now(),
                'returned_by_type' => $returner[0],
                'returned_by_id' => (int) $returner[1],
                'received_by' => $by,
                'return_condition' => $condition,
                'accessories_back' => $back,
                'return_note' => $note !== '' ? $note : null,
                'status' => Loan::RETURNED,
                'follow_up' => $condition !== Loan::FINE,
            ]);

            return $loan;
        });
    }

    /** Close a damage or missing-part follow-up once it is dealt with. */
    public function clearFollowUp(Loan $loan, ?string $note, int $by): Loan
    {
        if (!$loan->follow_up || $loan->follow_up_cleared_at) {
            throw new DeskRefusal(__('There is nothing to follow up on this loan.'));
        }
        $note = trim((string) $note);
        if ($note === '') {
            throw new DeskRefusal(__('Say how it was settled, for example "remote found" or "repaired".'));
        }
        $loan->update(['follow_up_cleared_at' => now(), 'follow_up_cleared_by' => $by, 'follow_up_note' => $note]);

        return $loan;
    }

    /** An item that is not coming back. */
    public function markLost(Loan $loan, ?string $note, int $by): Loan
    {
        if ($loan->status !== Loan::OUT) {
            throw new DeskRefusal(__('Only an item that is out can be marked lost.'));
        }
        $note = trim((string) $note);
        if ($note === '') {
            throw new DeskRefusal(__('Add a note saying what happened.'));
        }

        return DB::transaction(function () use ($loan, $note, $by) {
            $loan->update(['status' => Loan::LOST, 'received_by' => $by, 'return_note' => $note]);
            Item::whereKey($loan->equipment_item_id)->update(['state' => Item::LOST, 'updated_by' => $by]);

            return $loan;
        });
    }

    // ── Due times ─────────────────────────────────────────────────────────

    /** The end of the school day; the next one when that has (nearly) passed. Sundays are skipped. */
    public function defaultDueAt(): Carbon
    {
        [$h, $i] = array_map('intval', explode(':', $this->settings()->default_due_time ?: '17:00') + [1 => 0]);
        $due = now()->setTime($h, $i, 0);
        if ($due->lte(now()->addMinutes(15))) {
            $due = $this->nextSchoolDay($due);
        }

        return $due;
    }

    /** Quick choices for the desk: two hours, the default, and a day later. */
    public function dueChoices(): array
    {
        $default = $this->defaultDueAt();
        $soon = now()->addHours(2)->second(0);
        if ($soon->minute % 5) {
            $soon->addMinutes(5 - $soon->minute % 5);
        }
        $later = $this->nextSchoolDay($default->copy());

        return collect([$soon, $default, $later])
            ->unique(fn (Carbon $c) => $c->format('Y-m-d H:i'))
            ->map(fn (Carbon $c) => ['at' => $c->format('Y-m-d\TH:i'), 'label' => $this->when($c), 'default' => $c->equalTo($default)])
            ->values()->all();
    }

    protected function nextSchoolDay(Carbon $c): Carbon
    {
        $c = $c->copy()->addDay();

        return $c->isSunday() ? $c->addDay() : $c;
    }

    // ── Wording ───────────────────────────────────────────────────────────

    public function purposeText(Loan $loan): string
    {
        return match ($loan->purpose) {
            'class' => $loan->purpose_detail ?: __('A class'),
            'lecturer' => __('For :name', ['name' => $this->members->name([Members::STAFF, (int) $loan->lecturer_id])]),
            default => __('Own use'),
        };
    }

    /**
     * "Today 10:05", "Yesterday 14:20", "Tomorrow 17:00", otherwise "Mon 24 Nov 14:20".
     * Inside a sentence ($inSentence) the first three start in lower case.
     */
    public function when(?Carbon $c, bool $inSentence = false): string
    {
        if (!$c) {
            return '';
        }
        $time = ['time' => $c->format('H:i')];
        if ($c->isToday()) {
            return $inSentence ? __('today :time', $time) : __('Today :time', $time);
        }
        if ($c->isYesterday()) {
            return $inSentence ? __('yesterday :time', $time) : __('Yesterday :time', $time);
        }
        if ($c->isTomorrow()) {
            return $inSentence ? __('tomorrow :time', $time) : __('Tomorrow :time', $time);
        }

        return $c->format('D j M H:i');
    }

    /** On time, due soon, or overdue by how long. */
    public function dueStatus(Loan $loan): array
    {
        if ($loan->status !== Loan::OUT) {
            return ['tone' => 'muted', 'text' => $loan->status === Loan::LOST ? __('Lost') : __('Returned')];
        }
        if ($loan->due_at->isPast()) {
            $late = $loan->due_at->diffForHumans(now(), ['syntax' => CarbonInterface::DIFF_ABSOLUTE, 'short' => true, 'parts' => 1]);

            return ['tone' => 'bad', 'text' => __('Overdue :for', ['for' => $late])];
        }
        if ($loan->due_at->lte(now()->addHour())) {
            return ['tone' => 'warn', 'text' => __('Due soon')];
        }

        return ['tone' => 'good', 'text' => __('On time')];
    }

    protected function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim(preg_replace('/^(rev\.?|dr\.?|prof\.?|mr\.?|mrs\.?|ms\.?|sr\.?|fr\.?)\s+/i', '', $name)));
        $first = mb_substr($parts[0] ?? '?', 0, 1);
        $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';

        return mb_strtoupper($first . $last);
    }

    // ── Messages ──────────────────────────────────────────────────────────

    /** Borrowed for a lecturer: tell the lecturer, with the borrower in the same conversation. */
    protected function tellLecturer(Loan $loan): void
    {
        try {
            $loan->loadMissing('item', 'desk');
            $borrower = $loan->borrower();
            $lecturer = [Members::STAFF, (int) $loan->lecturer_id];
            $body = __(":borrower borrowed :code (:item) from the :desk for you at :time. It is due back :due.\n\nUntil it is checked in, you share responsibility for it.", [
                'borrower' => $this->members->name($borrower),
                'code' => $loan->item->code,
                'item' => $loan->item->name,
                'desk' => optional($loan->desk)->name ?? __('lending desk'),
                'time' => $loan->lent_at->format('H:i'),
                'due' => $this->when($loan->due_at, true),
            ]);
            $thread = app(Mailbox::class)->start(
                [Members::STAFF, (int) $loan->lent_by],
                [$lecturer, $borrower],
                __(':code lent for you', ['code' => $loan->item->code]),
                $body
            );
            $loan->update(['mailbox_thread_id' => $thread->id]);
        } catch (\Throwable $e) {
            Log::warning('Equipment loan: could not message the lecturer', ['loan' => $loan->id, 'error' => $e->getMessage()]);
        }
    }
}
