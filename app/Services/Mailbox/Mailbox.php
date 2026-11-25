<?php

namespace App\Services\Mailbox;

use App\Models\Mailbox\Attachment;
use App\Models\Mailbox\Broadcast;
use App\Models\Mailbox\Message;
use App\Models\Mailbox\Office;
use App\Models\Mailbox\Participant;
use App\Models\Mailbox\Thread;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Conversations between staff and students.
 *
 * Every write goes through here so the rules hold everywhere: who may start a
 * conversation (Directory), that only participants can add to one, that
 * attachments are kept privately, and that each new message raises one email
 * alert per person until they have opened the conversation (Alerts).
 */
class Mailbox
{
    public const FOLDERS = ['inbox', 'sent', 'archive'];
    public const MAX_FILES = 5;
    public const MAX_KB = 10240;
    public const MIMES = 'pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,gif,webp,txt,csv';

    public function __construct(protected Members $members, protected Directory $directory, protected Alerts $alerts)
    {
    }

    /**
     * Start a conversation with named people.
     *
     * @param  array<int, array{0:string,1:int}>  $to
     * @param  array<int, UploadedFile>  $files
     */
    public function start(array $from, array $to, string $subject, string $body, array $files = [], ?Office $office = null): Thread
    {
        $to = collect($to)->unique(fn ($m) => Members::key($m))->reject(fn ($m) => Members::same($m, $from))->values()->all();
        if (!$to) {
            throw new \InvalidArgumentException(__('Choose at least one person to write to.'));
        }
        if (!$office) {
            foreach ($to as $m) {
                if (!$this->directory->canStart($from, $m)) {
                    throw new \InvalidArgumentException(__('You cannot start a conversation with :name.', ['name' => $this->members->name($m)]));
                }
            }
        }

        $thread = DB::transaction(function () use ($from, $to, $subject, $body, $files, $office) {
            $thread = Thread::create([
                'subject' => $this->cleanSubject($subject), 'office_id' => $office?->id,
                'created_by_type' => $from[0], 'created_by_id' => $from[1], 'last_message_at' => now(),
            ]);
            foreach (array_merge([$from], $to) as $m) {
                Participant::create(['thread_id' => $thread->id, 'member_type' => $m[0], 'member_id' => $m[1]]);
            }
            $this->addMessage($thread, $from, $body, $this->storeFiles($files));

            return $thread;
        });

        $this->alerts->forThread($thread->fresh('participants'), $from);

        return $thread;
    }

    /** A student writes to an office: everyone who staffs it joins the conversation. */
    public function startWithOffice(array $from, Office $office, string $subject, string $body, array $files = []): Thread
    {
        $members = $office->members()->where('status', '1')->pluck('users.id')->map(fn ($id) => [Members::STAFF, (int) $id])->all();
        if (!$office->status || !$members) {
            throw new \InvalidArgumentException(__('This office is not taking messages at the moment.'));
        }

        return $this->start($from, $members, $subject, $body, $files, $office);
    }

    /** Add a message to a conversation the sender is part of. */
    public function reply(Thread $thread, array $from, string $body, array $files = []): Message
    {
        $me = $thread->participantFor($from);
        if (!$me) {
            throw new \InvalidArgumentException(__('You are not part of this conversation.'));
        }

        $message = DB::transaction(function () use ($thread, $from, $body, $files, $me) {
            $message = $this->addMessage($thread, $from, $body, $this->storeFiles($files));
            $thread->update(['last_message_at' => $message->created_at]);
            // A reply brings the conversation back to everyone's inbox.
            Participant::where('thread_id', $thread->id)->whereNotNull('archived_at')->whereNull('deleted_at')->update(['archived_at' => null]);
            $me->update(['last_read_at' => $message->created_at]);

            return $message;
        });

        $this->alerts->forThread($thread->fresh('participants'), $from);

        return $message;
    }

    /**
     * One message to a group: a private conversation with each recipient, so
     * every reply goes to the sender alone.
     *
     * @param  array<int, array{0:string,1:int}>  $recipients
     */
    public function broadcast(array $from, array $recipients, string $audience, string $subject, string $body, array $files = []): Broadcast
    {
        $recipients = collect($recipients)->unique(fn ($m) => Members::key($m))->reject(fn ($m) => Members::same($m, $from))->values()->all();
        if (!$recipients) {
            throw new \InvalidArgumentException(__('Nobody is in that group.'));
        }
        $subject = $this->cleanSubject($subject);
        $stored = $this->storeFiles($files);

        $broadcast = DB::transaction(function () use ($from, $recipients, $audience, $subject, $body, $stored) {
            $broadcast = Broadcast::create([
                'sender_type' => $from[0], 'sender_id' => $from[1], 'subject' => $subject, 'body' => $body,
                'audience' => mb_substr($audience, 0, 255), 'recipient_count' => count($recipients),
            ]);
            $now = now();
            foreach ($recipients as $to) {
                $thread = Thread::create([
                    'subject' => $subject, 'broadcast_id' => $broadcast->id,
                    'created_by_type' => $from[0], 'created_by_id' => $from[1], 'last_message_at' => $now,
                ]);
                Participant::insert([
                    ['thread_id' => $thread->id, 'member_type' => $from[0], 'member_id' => $from[1], 'last_read_at' => $now, 'created_at' => $now, 'updated_at' => $now],
                    ['thread_id' => $thread->id, 'member_type' => $to[0], 'member_id' => $to[1], 'last_read_at' => null, 'created_at' => $now, 'updated_at' => $now],
                ]);
                // The files are stored once; each message points at them.
                $this->addMessage($thread, $from, $body, $stored, $now);
            }

            return $broadcast;
        });

        $this->alerts->forBroadcast($broadcast, $from, $recipients);

        return $broadcast;
    }

    /** Threads in one folder for a member, newest first. */
    public function folder(array $member, string $folder, ?string $search = null)
    {
        $q = Thread::query()
            ->join('mailbox_participants as me', function ($j) use ($member) {
                $j->on('me.thread_id', '=', 'mailbox_threads.id')
                    ->where('me.member_type', $member[0])->where('me.member_id', $member[1])->whereNull('me.deleted_at');
            })
            ->select('mailbox_threads.*', 'me.last_read_at as my_last_read_at', 'me.archived_at as my_archived_at')
            ->selectRaw('(SELECT COUNT(*) FROM mailbox_messages m WHERE m.thread_id = mailbox_threads.id AND NOT (m.sender_type = ? AND m.sender_id = ?) AND m.created_at > COALESCE(me.last_read_at, \'1970-01-01\')) as unread_count', [$member[0], $member[1]])
            ->with(['latestMessage', 'participants']);

        $fromOthers = 'EXISTS (SELECT 1 FROM mailbox_messages m WHERE m.thread_id = mailbox_threads.id AND NOT (m.sender_type = ? AND m.sender_id = ?))';
        $fromMe = 'EXISTS (SELECT 1 FROM mailbox_messages m WHERE m.thread_id = mailbox_threads.id AND m.sender_type = ? AND m.sender_id = ?)';

        if ($folder === 'sent') {
            $q->whereRaw($fromMe, [$member[0], $member[1]])->whereNull('mailbox_threads.broadcast_id');
        } elseif ($folder === 'archive') {
            $q->whereNotNull('me.archived_at');
        } else {
            $q->whereNull('me.archived_at')->whereRaw($fromOthers, [$member[0], $member[1]]);
        }

        if ($search !== null && trim($search) !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($search)) . '%';
            $q->where(fn ($w) => $w->where('mailbox_threads.subject', 'like', $like)
                ->orWhereExists(fn ($e) => $e->from('mailbox_messages as sm')->whereColumn('sm.thread_id', 'mailbox_threads.id')->where('sm.body', 'like', $like)));
        }

        return $q->orderByDesc('mailbox_threads.last_message_at')->orderByDesc('mailbox_threads.id');
    }

    /** Group sends by this member, newest first, with how many replied. */
    public function broadcastsBy(array $member)
    {
        return Broadcast::where('sender_type', $member[0])->where('sender_id', $member[1])
            ->withCount(['threads as replied_count' => fn ($q) => $q->whereExists(fn ($e) => $e->from('mailbox_messages as m')
                ->whereColumn('m.thread_id', 'mailbox_threads.id')->where(fn ($w) => $w->where('m.sender_type', '<>', $member[0])->orWhere('m.sender_id', '<>', $member[1])))])
            ->orderByDesc('id');
    }

    /** Conversations with unread messages, for the badge beside the bell. */
    public function unreadCount(array $member): int
    {
        return (int) DB::table('mailbox_participants as me')
            ->where('me.member_type', $member[0])->where('me.member_id', $member[1])->whereNull('me.deleted_at')
            ->whereExists(fn ($e) => $e->from('mailbox_messages as m')->whereColumn('m.thread_id', 'me.thread_id')
                ->where(fn ($w) => $w->where('m.sender_type', '<>', $member[0])->orWhere('m.sender_id', '<>', $member[1]))
                ->whereRaw("m.created_at > COALESCE(me.last_read_at, '1970-01-01')"))
            ->count();
    }

    /** A thread the member may open, with its messages; null when they may not. */
    public function open(int $threadId, array $member): ?Thread
    {
        $thread = Thread::with(['participants', 'messages.attachments', 'broadcast', 'office'])->find($threadId);
        if (!$thread || !$thread->participantFor($member)) {
            return null;
        }

        return $thread;
    }

    public function markRead(Thread $thread, array $member): void
    {
        Participant::where('thread_id', $thread->id)->where('member_type', $member[0])->where('member_id', $member[1])
            ->update(['last_read_at' => now()]);
    }

    public function archive(Thread $thread, array $member, bool $archived = true): void
    {
        Participant::where('thread_id', $thread->id)->where('member_type', $member[0])->where('member_id', $member[1])
            ->update(['archived_at' => $archived ? now() : null]);
    }

    /** Remove a conversation from one member's mailbox; the others keep it. */
    public function remove(Thread $thread, array $member): void
    {
        Participant::where('thread_id', $thread->id)->where('member_type', $member[0])->where('member_id', $member[1])
            ->update(['deleted_at' => now()]);
    }

    /** An attachment the member may download, or null. */
    public function attachment(int $id, array $member): ?Attachment
    {
        $a = Attachment::with('message.thread.participants')->find($id);

        return $a && $a->message && $a->message->thread->participantFor($member) ? $a : null;
    }

    /** Everyone in a conversation but the given member. */
    public function others(Thread $thread, array $member): Collection
    {
        return $thread->participants->reject(fn ($p) => $p->member_type === $member[0] && (int) $p->member_id === (int) $member[1])
            ->map(fn ($p) => [$p->member_type, (int) $p->member_id])->values();
    }

    /** Validation rules for a compose or reply form. */
    public static function rules(bool $withSubject): array
    {
        return array_filter([
            'subject' => $withSubject ? 'required|string|max:190' : null,
            'body' => 'required|string|max:20000',
            'attachments' => 'nullable|array|max:' . self::MAX_FILES,
            'attachments.*' => 'file|max:' . self::MAX_KB . '|mimes:' . self::MIMES,
        ]);
    }

    protected function addMessage(Thread $thread, array $from, string $body, array $stored, $at = null): Message
    {
        $message = new Message(['thread_id' => $thread->id, 'sender_type' => $from[0], 'sender_id' => $from[1], 'body' => trim($body)]);
        if ($at) {
            $message->created_at = $at;
            $message->updated_at = $at;
        }
        $message->save();
        foreach ($stored as $file) {
            Attachment::create(['message_id' => $message->id] + $file);
        }

        return $message;
    }

    /** @return array<int, array{path:string, original_name:string, mime:?string, size:int}> */
    protected function storeFiles(array $files): array
    {
        $out = [];
        foreach (array_slice(array_filter($files), 0, self::MAX_FILES) as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }
            $name = Str::random(40) . '.' . strtolower($file->getClientOriginalExtension() ?: 'bin');
            $path = $file->storeAs('mailbox/' . now()->format('Y/m'), $name, 'local');
            $out[] = [
                'path' => $path,
                'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 250),
                'mime' => $file->getClientMimeType(),
                'size' => (int) $file->getSize(),
            ];
        }

        return $out;
    }

    protected function cleanSubject(string $subject): string
    {
        $subject = trim(preg_replace('/\s+/', ' ', $subject));

        return mb_substr($subject !== '' ? $subject : __('(no subject)'), 0, 190);
    }
}
