<?php

namespace App\Services\Mailbox;

use App\Mail\MailboxAlert;
use App\Models\Mailbox\Broadcast;
use App\Models\Mailbox\Participant;
use App\Models\Mailbox\Thread;
use App\Models\MailSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The email that tells someone a message is waiting in their PAXHI mailbox.
 *
 * It names the sender and the subject and links to the conversation; the
 * message itself stays in the system. One alert per conversation until the
 * person has opened it, so a busy conversation does not flood their inbox.
 *
 * Sending happens after the response has gone to the browser, in the same
 * request. The server runs no queue worker, so a queued job would never be
 * sent; and a slow mail server must not keep the sender waiting.
 */
class Alerts
{
    /** Recipients per email for a group alert (sent as blind copies). */
    public const BCC_CHUNK = 50;

    /** @var array<int, array> alerts built in this request, for tests and logs */
    public array $sent = [];

    public function __construct(protected Members $members)
    {
    }

    /** Alert everyone in a conversation who has not seen it, except the sender. */
    public function forThread(Thread $thread, array $from): void
    {
        $due = [];
        foreach ($thread->participants as $p) {
            if (($p->member_type === $from[0] && (int) $p->member_id === (int) $from[1]) || $p->deleted_at) {
                continue;
            }
            // Already alerted and not opened since: one alert is enough.
            if ($p->last_notified_at && (!$p->last_read_at || $p->last_read_at->lt($p->last_notified_at))) {
                continue;
            }
            $due[] = $p;
        }
        if (!$due) {
            return;
        }
        Participant::whereIn('id', collect($due)->pluck('id'))->update(['last_notified_at' => now()]);

        $this->members->load(array_merge([$from], collect($due)->map(fn ($p) => [$p->member_type, (int) $p->member_id])->all()));
        $jobs = [];
        foreach ($due as $p) {
            $m = [$p->member_type, (int) $p->member_id];
            if ($email = $this->members->email($m)) {
                $jobs[] = ['to' => [$email], 'bcc' => [], 'link' => $this->link($m, $thread->id), 'name' => $this->members->name($m)];
            }
        }
        $this->dispatch($jobs, $this->members->name($from), $thread->subject);
    }

    /** One alert per recipient of a group send, in blind-copy batches. */
    public function forBroadcast(Broadcast $broadcast, array $from, array $recipients): void
    {
        $this->members->load(array_merge([$from], $recipients));
        $byType = [];
        foreach ($recipients as $m) {
            if ($email = $this->members->email($m)) {
                $byType[$m[0]][] = $email;
            }
        }
        Participant::whereIn('thread_id', $broadcast->threads()->pluck('id'))
            ->where(fn ($q) => $q->where('member_type', '<>', $from[0])->orWhere('member_id', '<>', $from[1]))
            ->update(['last_notified_at' => now()]);

        $jobs = [];
        foreach ($byType as $type => $emails) {
            foreach (array_chunk(array_values(array_unique($emails)), self::BCC_CHUNK) as $chunk) {
                $jobs[] = ['to' => [], 'bcc' => $chunk, 'link' => $this->inboxLink($type), 'name' => null];
            }
        }
        $this->dispatch($jobs, $this->members->name($from), $broadcast->subject);
    }

    public function link(array $member, int $threadId): string
    {
        return $member[0] === Members::STUDENT ? route('student.mailbox.show', $threadId) : route('admin.mailbox.show', $threadId);
    }

    public function inboxLink(string $type): string
    {
        return $type === Members::STUDENT ? route('student.mailbox.index') : route('admin.mailbox.index');
    }

    protected function dispatch(array $jobs, string $sender, string $subject): void
    {
        if (!$jobs) {
            return;
        }
        $this->sent = array_merge($this->sent, $jobs);
        $setting = MailSetting::where('status', '1')->first();
        if (!$setting || !$setting->sender_email) {
            Log::info('Mailbox alert not sent: no active mail setting.');

            return;
        }
        $fromName = $setting->sender_name ?: config('app.name');
        $send = function () use ($jobs, $sender, $subject, $setting, $fromName) {
            foreach ($jobs as $job) {
                try {
                    $mail = new MailboxAlert($sender, $subject, $job['link'], $job['name'], $setting->sender_email, $fromName);
                    // A group alert goes to the school's own address, the
                    // recipients in blind copy, so no one sees the list.
                    $pending = Mail::to($job['to'] ?: [$setting->sender_email]);
                    if ($job['bcc']) {
                        $pending->bcc($job['bcc']);
                    }
                    $pending->send($mail);
                } catch (\Throwable $e) {
                    Log::warning('Mailbox alert failed: ' . $e->getMessage(), ['to' => $job['to'], 'bcc_count' => count($job['bcc'])]);
                }
            }
        };

        if (app()->runningInConsole()) {
            $send();
        } else {
            app()->terminating($send);
        }
    }
}
