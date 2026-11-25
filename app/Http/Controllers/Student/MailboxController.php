<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Mailbox\Office;
use App\Services\Mailbox\Directory;
use App\Services\Mailbox\Mailbox;
use App\Services\Mailbox\Members;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The student mailbox. A student can write to their lecturers and to the
 * school's offices, and reply in any conversation they are part of; never to
 * other students.
 */
class MailboxController extends Controller
{
    public function __construct(protected Mailbox $mailbox, protected Directory $directory, protected Members $members)
    {
    }

    protected function me(): array
    {
        return [Members::STUDENT, (int) auth('student')->id()];
    }

    public function index(Request $request)
    {
        $folder = in_array($request->folder, Mailbox::FOLDERS, true) ? $request->folder : 'inbox';
        $me = $this->me();
        $threads = $this->mailbox->folder($me, $folder, $request->q)->paginate(25)->withQueryString();
        $this->members->load(collect($threads->items())->flatMap(fn ($t) => $t->participants->map(fn ($p) => [$p->member_type, (int) $p->member_id]))->all());

        return view('student.mailbox.index', [
            'title' => __('Messages'),
            'folder' => $folder,
            'search' => (string) $request->q,
            'threads' => $threads,
            'unread' => $this->mailbox->unreadCount($me),
            'mb' => $this->context(),
        ]);
    }

    public function create(Request $request)
    {
        $lecturers = $this->directory->lecturersFor((int) auth('student')->id());
        $this->members->load(array_map(fn ($id) => [Members::STAFF, $id], array_keys($lecturers)));

        return view('student.mailbox.create', [
            'title' => __('New message'),
            'lecturers' => $lecturers,
            'offices' => $this->directory->offices(),
            'selected' => (string) $request->to,
            'members' => $this->members,
            'mb' => $this->context(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(Mailbox::rules(true) + ['to' => 'required|string']);
        $me = $this->me();
        try {
            if (preg_match('/^office:(\d+)$/', $request->to, $m)) {
                $office = Office::where('status', true)->findOrFail((int) $m[1]);
                $thread = $this->mailbox->startWithOffice($me, $office, $request->subject, $request->body, $request->file('attachments', []));
            } else {
                $to = Directory::parse($request->to);
                if (!$to) {
                    throw new \InvalidArgumentException(__('Choose who to write to.'));
                }
                $thread = $this->mailbox->start($me, [$to], $request->subject, $request->body, $request->file('attachments', []));
            }
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['to' => $e->getMessage()]);
        }
        Flasher::addSuccess(__('Message sent.'), __('msg_success'));

        return redirect()->route('student.mailbox.show', $thread->id);
    }

    public function show(int $thread)
    {
        $me = $this->me();
        $row = $this->mailbox->open($thread, $me);
        abort_unless($row, 404);
        $this->mailbox->markRead($row, $me);
        $this->members->load($row->participants->map(fn ($p) => [$p->member_type, (int) $p->member_id])->all());

        return view('student.mailbox.show', [
            'title' => $row->subject,
            'thread' => $row,
            'me' => $me,
            'others' => $this->mailbox->others($row, $me),
            'archived' => (bool) $row->participantFor($me)->archived_at,
            'members' => $this->members,
            'mb' => $this->context(),
            'unread' => $this->mailbox->unreadCount($me),
        ]);
    }

    public function reply(Request $request, int $thread)
    {
        $request->validate(Mailbox::rules(false));
        $me = $this->me();
        $row = $this->mailbox->open($thread, $me);
        abort_unless($row, 404);
        try {
            $this->mailbox->reply($row, $me, $request->body, $request->file('attachments', []));
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['body' => $e->getMessage()]);
        }
        Flasher::addSuccess(__('Reply sent.'), __('msg_success'));

        return redirect()->to(route('student.mailbox.show', $row->id) . '#latest');
    }

    public function archive(Request $request, int $thread)
    {
        $me = $this->me();
        $row = $this->mailbox->open($thread, $me);
        abort_unless($row, 404);
        $archive = !$request->boolean('restore');
        $this->mailbox->archive($row, $me, $archive);
        Flasher::addSuccess($archive ? __('Moved to Archive.') : __('Moved back to the Inbox.'), __('msg_success'));

        return redirect()->route('student.mailbox.index', ['folder' => $archive ? 'inbox' : 'archive']);
    }

    public function destroy(int $thread)
    {
        $me = $this->me();
        $row = $this->mailbox->open($thread, $me);
        abort_unless($row, 404);
        $this->mailbox->remove($row, $me);
        Flasher::addSuccess(__('Conversation removed from your mailbox.'), __('msg_success'));

        return redirect()->route('student.mailbox.index');
    }

    public function attachment(int $attachment)
    {
        $a = $this->mailbox->attachment($attachment, $this->me());
        abort_unless($a && Storage::disk('local')->exists($a->path), 404);

        return Storage::disk('local')->download($a->path, $a->original_name);
    }

    protected function context(): array
    {
        return ['route' => 'student.mailbox', 'member' => $this->me(), 'members' => $this->members, 'isStaff' => false, 'canOffices' => false];
    }
}
