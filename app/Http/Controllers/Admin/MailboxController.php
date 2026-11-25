<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Mailbox\Broadcast;
use App\Models\Program;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Session;
use App\Services\Mailbox\Directory;
use App\Services\Mailbox\Mailbox;
use App\Services\Mailbox\Members;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The staff mailbox. Every member of staff has one; nothing here needs a
 * permission except group sends beyond one's own classes (mailbox-broadcast).
 */
class MailboxController extends Controller
{
    public function __construct(protected Mailbox $mailbox, protected Directory $directory, protected Members $members)
    {
    }

    protected function me(): array
    {
        return [Members::STAFF, (int) auth('web')->id()];
    }

    public function index(Request $request)
    {
        $folder = in_array($request->folder, array_merge(Mailbox::FOLDERS, ['groups']), true) ? $request->folder : 'inbox';
        $me = $this->me();
        $data = [
            'title' => __('Mailbox'),
            'folder' => $folder,
            'search' => (string) $request->q,
            'unread' => $this->mailbox->unreadCount($me),
            'mb' => $this->context(),
        ];
        if ($folder === 'groups') {
            $data['broadcasts'] = $this->mailbox->broadcastsBy($me)->paginate(25)->withQueryString();
        } else {
            $data['threads'] = $this->mailbox->folder($me, $folder, $request->q)->paginate(25)->withQueryString();
            $this->preload($data['threads']);
        }

        return view('admin.mailbox.index', $data);
    }

    public function create(Request $request)
    {
        $user = auth('web')->user();
        $prefill = [];
        foreach ((array) $request->to as $key) {
            if (($m = Directory::parse((string) $key)) && $this->directory->canStart($this->me(), $m)) {
                $prefill[] = ['id' => Members::key($m), 'text' => $this->members->name($m) . ' — ' . $this->members->detail($m)];
            }
        }

        return view('admin.mailbox.create', [
            'title' => __('New message'),
            'mb' => $this->context(),
            'prefill' => $prefill,
            'canBroadcast' => $user->can('mailbox-broadcast'),
            'courses' => $this->directory->coursesTaughtBy((int) $user->id),
            'programs' => $user->can('mailbox-broadcast') ? Program::where('status', '1')->orderBy('title')->get(['id', 'title']) : collect(),
            'sessions' => Session::where('status', '1')->orderByDesc('id')->get(['id', 'title', 'current']),
            'semesters' => Semester::where('status', '1')->orderBy('id')->get(['id', 'title']),
            'sections' => Section::where('status', '1')->orderBy('title')->get(['id', 'title']),
            'departments' => $user->can('mailbox-broadcast') ? Department::where('status', '1')->orderBy('title')->get(['id', 'title']) : collect(),
            'allCourses' => $user->can('mailbox-broadcast') ? \App\Models\Subject::where('status', '1')->orderBy('code')->get(['id', 'code', 'title']) : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $mode = $request->input('mode') === 'group' ? 'group' : 'people';
        $request->validate(Mailbox::rules(true) + ($mode === 'people' ? ['to' => 'required|array|min:1|max:200', 'to.*' => 'string'] : ['group_type' => 'required|in:course,programme,department,all_staff']));
        $me = $this->me();

        try {
            if ($mode === 'people') {
                $to = array_values(array_filter(array_map(fn ($k) => Directory::parse((string) $k), (array) $request->to)));
                $thread = $this->mailbox->start($me, $to, $request->subject, $request->body, $request->file('attachments', []));
                Flasher::addSuccess(__('Message sent.'), __('msg_success'));

                return redirect()->route('admin.mailbox.show', $thread->id);
            }

            $spec = $this->groupSpec($request);
            if (!$this->directory->canSendToGroup(auth('web')->user(), $spec)) {
                abort(403, __('You can send to the classes you teach. Other groups need the "Send to groups" permission.'));
            }
            $audience = $this->directory->audience($spec);
            $count = count($audience['members']);
            if ((int) $request->confirm_count !== $count) {
                return back()->withInput()->withErrors(['group_type' => __('The group now has :n people. Check the number and send again.', ['n' => $count])]);
            }
            $broadcast = $this->mailbox->broadcast($me, $audience['members'], $audience['label'], $request->subject, $request->body, $request->file('attachments', []));
            Flasher::addSuccess(__('Message sent to :n people.', ['n' => $broadcast->recipient_count]), __('msg_success'));

            return redirect()->route('admin.mailbox.broadcast', $broadcast->id);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['to' => $e->getMessage()]);
        }
    }

    /** How many people a group reaches, before sending (AJAX). */
    public function audience(Request $request)
    {
        $spec = $this->groupSpec($request);
        if (!$this->directory->canSendToGroup(auth('web')->user(), $spec)) {
            return response()->json(['count' => 0, 'label' => '', 'allowed' => false]);
        }
        $a = $this->directory->audience($spec);

        return response()->json(['count' => count($a['members']), 'label' => $a['label'], 'allowed' => true]);
    }

    /** People for the To box (AJAX). */
    public function search(Request $request)
    {
        return response()->json(['results' => $this->directory->search((string) $request->q)]);
    }

    public function show(int $thread)
    {
        $me = $this->me();
        $row = $this->mailbox->open($thread, $me);
        abort_unless($row, 404);
        $this->mailbox->markRead($row, $me);
        $this->members->load($row->participants->map(fn ($p) => [$p->member_type, (int) $p->member_id])->all());
        $mine = $row->participantFor($me);

        return view('admin.mailbox.show', [
            'title' => $row->subject,
            'thread' => $row,
            'me' => $me,
            'others' => $this->mailbox->others($row, $me),
            'archived' => (bool) $mine->archived_at,
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

        return redirect()->to(route('admin.mailbox.show', $row->id) . '#latest');
    }

    public function archive(Request $request, int $thread)
    {
        $me = $this->me();
        $row = $this->mailbox->open($thread, $me);
        abort_unless($row, 404);
        $archive = !$request->boolean('restore');
        $this->mailbox->archive($row, $me, $archive);
        Flasher::addSuccess($archive ? __('Moved to Archive.') : __('Moved back to the Inbox.'), __('msg_success'));

        return redirect()->route('admin.mailbox.index', ['folder' => $archive ? 'inbox' : 'archive']);
    }

    public function destroy(int $thread)
    {
        $me = $this->me();
        $row = $this->mailbox->open($thread, $me);
        abort_unless($row, 404);
        $this->mailbox->remove($row, $me);
        Flasher::addSuccess(__('Conversation removed from your mailbox.'), __('msg_success'));

        return redirect()->route('admin.mailbox.index');
    }

    /** One group send, as its sender sees it: who it reached and who replied. */
    public function broadcast(int $broadcast)
    {
        $me = $this->me();
        $row = Broadcast::where('id', $broadcast)->where('sender_type', $me[0])->where('sender_id', $me[1])->firstOrFail();
        $threads = $row->threads()->with(['participants', 'messages'])->get();
        $this->members->load($threads->flatMap(fn ($t) => $t->participants->map(fn ($p) => [$p->member_type, (int) $p->member_id]))->all());

        return view('admin.mailbox.broadcast', [
            'title' => $row->subject,
            'broadcast' => $row,
            'threads' => $threads,
            'me' => $me,
            'members' => $this->members,
            'mb' => $this->context(),
            'unread' => $this->mailbox->unreadCount($me),
        ]);
    }

    public function attachment(int $attachment)
    {
        $a = $this->mailbox->attachment($attachment, $this->me());
        abort_unless($a && Storage::disk('local')->exists($a->path), 404);

        return Storage::disk('local')->download($a->path, $a->original_name);
    }

    protected function groupSpec(Request $request): array
    {
        return [
            'type' => (string) $request->group_type,
            'subject' => (int) $request->group_subject,
            'program' => (int) $request->group_program,
            'session' => (int) $request->group_session,
            'semester' => (int) $request->group_semester,
            'section' => (int) $request->group_section,
            'department' => (int) $request->group_department,
        ];
    }

    /** Names for every participant on the page, in two queries. */
    protected function preload($threads): void
    {
        $this->members->load(collect($threads->items())->flatMap(fn ($t) => $t->participants->map(fn ($p) => [$p->member_type, (int) $p->member_id]))->all());
    }

    protected function context(): array
    {
        return [
            'route' => 'admin.mailbox',
            'member' => $this->me(),
            'members' => $this->members,
            'isStaff' => true,
            'canOffices' => auth('web')->user()->can('mailbox-office'),
        ];
    }
}
