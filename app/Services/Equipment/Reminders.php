<?php

namespace App\Services\Equipment;

use App\Models\Equipment\Loan;
use App\Models\Equipment\Setting;
use App\Models\Mailbox\Thread;
use App\Services\Mailbox\Mailbox;
use App\Services\Mailbox\Members;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Chases items that are late, through the mailbox (which also emails).
 *
 *   first reminder   once the grace period after the due time has passed
 *   then             one a day, at the daily reminder time, never on Sunday
 *
 * The message comes from whoever lent the item, so the borrower can reply to
 * the desk. All of one loan's reminders stay in one conversation, which also
 * holds the lecturer when the item was borrowed for one.
 */
class Reminders
{
    public function __construct(protected Mailbox $mailbox, protected Members $members, protected LoanDesk $desk)
    {
    }

    /** @return array{sent:int, failed:int, checked:int} */
    public function run(?Carbon $now = null): array
    {
        $now = $now ? $now->copy() : now();
        $s = Setting::current();
        [$h, $i] = array_map('intval', explode(':', $s->daily_reminder_time ?: '08:00') + [1 => 0]);
        $dailyAt = $now->copy()->setTime($h, $i, 0);

        $sent = 0;
        $failed = 0;
        $loans = Loan::with('item', 'desk')->open()->where('due_at', '<', $now)->orderBy('due_at')->get();

        foreach ($loans as $loan) {
            if ((int) $loan->reminders_sent === 0) {
                if ($now->lt($loan->due_at->copy()->addMinutes((int) $s->first_reminder_minutes))) {
                    continue;
                }
            } elseif (($loan->last_reminded_at && $loan->last_reminded_at->isSameDay($now)) || $now->isSunday() || $now->lt($dailyAt)) {
                continue;
            }

            try {
                $this->send($loan, $now);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('Equipment reminder could not be sent', ['loan' => $loan->id, 'error' => $e->getMessage()]);
                // Counted as tried today, so a broken address is not retried every five minutes.
                $loan->update(['last_reminded_at' => $now, 'reminders_sent' => max(1, (int) $loan->reminders_sent)]);
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'checked' => $loans->count()];
    }

    protected function send(Loan $loan, Carbon $now): void
    {
        $first = (int) $loan->reminders_sent === 0;
        $from = [Members::STAFF, (int) $loan->lent_by];
        $desk = optional($loan->desk)->name ?? __('lending desk');
        $vars = [
            'code' => $loan->item->code,
            'item' => $loan->item->name,
            'desk' => $desk,
            'due' => $this->desk->when($loan->due_at, true),
        ];
        $body = $first
            ? __(":code (:item) was due back at the :desk :due and has not been checked in yet. Please bring it back as soon as you can.\n\nUntil it is returned, nothing else can be lent to you.", $vars)
            : __("A reminder: :code (:item) is still out. It was due back at the :desk :due.", $vars);

        $thread = $loan->mailbox_thread_id ? Thread::find($loan->mailbox_thread_id) : null;
        if ($thread) {
            $this->mailbox->reply($thread, $from, $body);
        } else {
            $to = [$loan->borrower()];
            if ($loan->lecturer_id) {
                $to[] = [Members::STAFF, (int) $loan->lecturer_id];
            }
            $thread = $this->mailbox->start($from, $to, __(':code is overdue', ['code' => $loan->item->code]), $body);
            $loan->mailbox_thread_id = $thread->id;
        }

        $loan->reminders_sent = (int) $loan->reminders_sent + 1;
        $loan->last_reminded_at = $now;
        $loan->save();
    }
}
