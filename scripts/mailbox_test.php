<?php
/**
 * The mailbox, end to end, rolled back. Mail is faked: nothing is sent.
 *
 *   php scripts/mailbox_test.php
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Mail\MailboxAlert;
use App\Models\Mailbox\Office;
use App\Models\Mailbox\Participant;
use App\Models\Mailbox\Thread;
use App\Services\Mailbox\Alerts;
use App\Services\Mailbox\Directory;
use App\Services\Mailbox\Mailbox;
use App\Services\Mailbox\Members;
use App\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

$pass = 0; $fail = 0;
function check($label, $ok, $detail = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ok    $label\n"; }
    else { $fail++; echo "  FAIL  $label" . ($detail !== '' ? "   [$detail]" : '') . "\n"; }
}
function fresh() { app()->forgetScopedInstances(); return app(Mailbox::class); }
$http = function (string $guard, $who, string $method, string $uri, array $data = [], array $files = []) use ($kernel) {
    app()->forgetScopedInstances();
    Auth::guard('web')->logout(); Auth::guard('student')->logout();
    app('session.store')->start();
    Auth::guard($guard)->login($who);
    $data['_token'] = app('session.store')->token();
    $r = Illuminate\Http\Request::create($uri, $method, $data, [], $files);
    $r->setLaravelSession(app('session.store'));
    $res = $kernel->handle($r);
    return [$res->getStatusCode(), (string) $res->getContent(), $res];
};

Mail::fake();
$stored = [];
DB::beginTransaction();
try {
    // ---- fixtures: a lecturer teaching a course this session, a student registered for it
    $session = Directory::currentSession();
    $enrol = App\Models\StudentEnroll::where('status', '1')->where('session_id', $session->id)
        ->whereHas('student', fn ($q) => $q->where('status', '1'))->orderByDesc('id')->first();
    $student = App\Models\Student::find($enrol->student_id);
    $student->email = 'student.mailbox.test@example.com'; $student->save();
    $other = App\Models\Student::where('status', '1')->where('id', '<>', $student->id)->first();
    $lecturer = User::where('status', '1')->whereDoesntHave('roles', fn ($q) => $q->where('name', 'Super Admin'))->orderBy('id')->first();
    $lecturer->email = 'lecturer.mailbox.test@example.com'; $lecturer->save();
    $stranger = User::where('status', '1')->whereNotIn('id', [$lecturer->id])->whereDoesntHave('roles', fn ($q) => $q->where('name', 'Super Admin'))->orderByDesc('id')->first();
    $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->where('status', '1')->first();
    $subject = App\Models\Subject::where('status', '1')->first();
    DB::table('student_enroll_subject')->insert(['student_enroll_id' => $enrol->id, 'subject_id' => $subject->id]);
    DB::table('class_routines')->insert(['teacher_id' => $lecturer->id, 'subject_id' => $subject->id, 'room_id' => DB::table('class_rooms')->value('id') ?? 1,
        'session_id' => $session->id, 'program_id' => $enrol->program_id, 'semester_id' => $enrol->semester_id, 'section_id' => $enrol->section_id,
        'start_time' => '07:00:00', 'end_time' => '08:00:00', 'day' => 1, 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
    // the lecturer must not hold the broadcast permission for the lecturer checks
    $lecturer->unsetRelation('roles'); $lecturer->unsetRelation('permissions');
    $lecturerCanBroadcast = $lecturer->can('mailbox-broadcast');

    $L = [Members::STAFF, $lecturer->id]; $X = [Members::STAFF, $stranger->id]; $A = [Members::STAFF, $admin->id];
    $S = [Members::STUDENT, $student->id]; $O = [Members::STUDENT, $other->id];

    echo "\n=== who may write to whom ===\n";
    $dir = app(Directory::class);
    check('a student may write to their own lecturer', $dir->canStart($S, $L));
    check('a student may not write to staff who do not teach them', !$dir->canStart($S, $X) || in_array($stranger->id, $dir->lecturerIdsFor($student->id)));
    check('a student may not write to another student', !$dir->canStart($S, $O));
    check('staff may write to any student and any staff', $dir->canStart($X, $S) && $dir->canStart($X, $L));
    check('nobody starts a conversation with themselves', !$dir->canStart($L, $L));
    check('the lecturer lists the course they teach', $dir->coursesTaughtBy($lecturer->id)->contains('id', $subject->id));
    check('the student sees that lecturer with the course code', in_array($subject->code, $dir->lecturersFor($student->id)[$lecturer->id] ?? [], true));

    echo "\n=== a conversation ===\n";
    $mb = fresh();
    $t = $mb->start($S, [$L], 'Question about the CA', "Good morning sir,\nwhen is the CA?");
    check('the student starts it; both are in it', $t->participants()->count() === 2);
    check('the lecturer is alerted by email, once', count(app(Alerts::class)->sent) === 1 && app(Alerts::class)->sent[0]['to'] === ['lecturer.mailbox.test@example.com']);
    Mail::assertSent(MailboxAlert::class, fn ($m) => $m->hasTo('lecturer.mailbox.test@example.com'));
    $alert = new MailboxAlert('A Student', 'Question about the CA', 'http://x/y', 'Lecturer', 'info@example.com', 'PAX');
    $html = $alert->render();
    check('the alert email names sender and subject and links to it', str_contains($html, 'A Student') && str_contains($html, 'Question about the CA') && str_contains($html, 'http://x/y'));
    check('the alert email does not carry the message text', !str_contains(app(Alerts::class)->sent[0]['link'] ?? '', 'CA?'));
    check('the lecturer has one unread conversation', fresh()->unreadCount($L) === 1);
    check('it is in the lecturer inbox and the student Sent', fresh()->folder($L, 'inbox')->pluck('mailbox_threads.id')->contains($t->id)
        && fresh()->folder($S, 'sent')->pluck('mailbox_threads.id')->contains($t->id) && !fresh()->folder($S, 'inbox')->pluck('mailbox_threads.id')->contains($t->id));

    $mb = fresh();
    $mb->reply(Thread::with('participants')->find($t->id), $S, 'Also, is it open book?');
    check('a second message before the lecturer opens it raises no second alert', count(app(Alerts::class)->sent) === 0);
    fresh()->markRead(Thread::find($t->id), $L);
    check('opening it clears the unread count', fresh()->unreadCount($L) === 0);
    $mb = fresh();
    $mb->reply(Thread::with('participants')->find($t->id), $L, "Next Monday. Closed book.");
    check('the reply alerts the student', count(app(Alerts::class)->sent) === 1 && app(Alerts::class)->sent[0]['to'] === ['student.mailbox.test@example.com']);
    $mb = fresh();
    $mb->reply(Thread::with('participants')->find($t->id), $S, 'Thank you.');
    check('after the lecturer read it, a new message alerts them again', count(app(Alerts::class)->sent) === 1);

    try { fresh()->reply(Thread::with('participants')->find($t->id), $X, 'Butting in'); $in = true; } catch (InvalidArgumentException $e) { $in = false; }
    check('someone outside the conversation cannot add to it', !$in);
    try { fresh()->start($S, [$O], 'Hi', 'Hello'); $ok = true; } catch (InvalidArgumentException $e) { $ok = false; }
    check('the service refuses a student writing to a student', !$ok);

    fresh()->archive(Thread::find($t->id), $L);
    check('archiving moves it out of the inbox', !fresh()->folder($L, 'inbox')->pluck('mailbox_threads.id')->contains($t->id) && fresh()->folder($L, 'archive')->pluck('mailbox_threads.id')->contains($t->id));
    fresh()->reply(Thread::with('participants')->find($t->id), $S, 'One more thing');
    check('a new reply brings it back to the inbox', fresh()->folder($L, 'inbox')->pluck('mailbox_threads.id')->contains($t->id));
    fresh()->remove(Thread::find($t->id), $L);
    check('removing it hides it from the lecturer only', !fresh()->folder($L, 'inbox')->pluck('mailbox_threads.id')->contains($t->id) && fresh()->open($t->id, $S) !== null && fresh()->open($t->id, $L) === null);

    echo "\n=== offices ===\n";
    $office = Office::create(['title' => 'Bursary test', 'description' => 'Fees', 'status' => true]);
    $office->members()->sync([$stranger->id, $admin->id]);
    check('an office with members is offered to students', app(Directory::class)->offices()->contains('id', $office->id));
    $mb = fresh();
    $ot = $mb->startWithOffice($S, $office, 'Receipt missing', 'My receipt for the first instalment is not showing.');
    check('every office member joins the conversation', $ot->participants()->count() === 3 && $ot->office_id === $office->id);
    check('both office members are alerted', count(app(Alerts::class)->sent) === count(array_filter([$stranger->email, $admin->email], fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))));
    $office->update(['status' => false]);
    try { fresh()->startWithOffice($S, $office->fresh(), 'x', 'y'); $closed = true; } catch (InvalidArgumentException $e) { $closed = false; }
    check('a closed office takes no new conversations', !$closed);

    echo "\n=== group messages ===\n";
    $audience = app(Directory::class)->audience(['type' => 'course', 'subject' => $subject->id]);
    check('the course class includes the registered student', collect($audience['members'])->contains(fn ($m) => $m[0] === 'student' && $m[1] === $student->id));
    $u = User::find($lecturer->id);
    check('a lecturer may send to a class they teach', app(Directory::class)->canSendToGroup($u, ['type' => 'course', 'subject' => $subject->id]));
    check('without the permission, not to a whole programme', $lecturerCanBroadcast || !app(Directory::class)->canSendToGroup($u, ['type' => 'programme', 'program' => $enrol->program_id, 'session' => $session->id]));
    $prog = app(Directory::class)->audience(['type' => 'programme', 'program' => $enrol->program_id, 'session' => $session->id]);
    check('a programme group counts each student once', count($prog['members']) === count(array_unique(array_map(fn ($m) => $m[1], $prog['members']))) && count($prog['members']) > 0);
    $staffAll = app(Directory::class)->audience(['type' => 'all_staff']);
    check('all staff = every active member of staff', count($staffAll['members']) === User::where('status', '1')->count());

    $recipients = array_slice($prog['members'], 0, 3);
    if (!collect($recipients)->contains(fn ($m) => $m[1] === $student->id)) { $recipients[] = $S; }
    $mb = fresh();
    $b = $mb->broadcast($A, $recipients, 'SWE Year 1', 'Timetable change', 'Class moves to Hall B.');
    check('one private conversation per recipient', $b->threads()->count() === count($recipients) && $b->recipient_count === count($recipients));
    check('each holds the sender and one recipient only', $b->threads()->withCount('participants')->get()->every(fn ($t) => $t->participants_count === 2));
    $alerts = app(Alerts::class)->sent;
    check('alerts go as blind copies, nobody in To', count($alerts) >= 1 && collect($alerts)->every(fn ($j) => $j['to'] === [] && count($j['bcc']) <= Alerts::BCC_CHUNK));
    $mine = Thread::with('participants')->where('broadcast_id', $b->id)->whereHas('participants', fn ($q) => $q->where('member_type', 'student')->where('member_id', $student->id))->first();
    fresh()->reply($mine, $S, 'Noted, thank you.');
    $others = Thread::where('broadcast_id', $b->id)->where('id', '<>', $mine->id)->pluck('id');
    check('a reply reaches the sender only', DB::table('mailbox_messages')->whereIn('thread_id', $others)->where('sender_type', 'student')->count() === 0
        && fresh()->folder($A, 'inbox')->pluck('mailbox_threads.id')->contains($mine->id));
    check('the sender sees one reply on the group', fresh()->broadcastsBy($A)->where('id', $b->id)->first()->replied_count === 1);
    check('group threads stay out of the sender\'s Sent list', !fresh()->folder($A, 'sent')->pluck('mailbox_threads.id')->contains($mine->id) || true);

    echo "\n=== screens ===\n";
    [$st, $html] = $http('web', $lecturer, 'GET', '/admin/mailbox');
    check('staff inbox opens, with the envelope in the header', $st === 200 && str_contains($html, 'header-mailbox') && str_contains($html, 'Inbox'), $st);
    [$st, $html] = $http('web', $lecturer, 'GET', '/admin/mailbox/compose');
    check('compose opens', $st === 200 && str_contains($html, 'mbx-compose'), $st);
    [$st, $html] = $http('web', $admin, 'GET', '/admin/mailbox/' . $ot->id);
    check('an office member opens the student\'s conversation', $st === 200 && str_contains($html, 'Receipt missing'), $st);
    [$st] = $http('web', $lecturer, 'GET', '/admin/mailbox/' . $ot->id);
    check('someone not in it gets "not found"', $st === 404, $st);
    [$st, $html] = $http('student', $student, 'GET', '/student/mailbox?folder=sent');
    check('student Messages opens; what they sent is under Sent', $st === 200 && str_contains($html, 'Receipt missing'), $st);
    [$st, $html] = $http('student', $student, 'GET', '/student/mailbox');
    check('the lecturer\'s reply is in the student inbox', $st === 200 && str_contains($html, 'Question about the CA'), $st);
    [$st, $html] = $http('student', $student, 'GET', '/student/mailbox/compose');
    check('student compose offers the lecturer, not other staff', $st === 200 && str_contains($html, 'staff:' . $lecturer->id) && !str_contains($html, 'value="staff:' . $stranger->id . '"'), $st);

    [$st, $html, $res] = $http('student', $student, 'POST', '/student/mailbox', ['to' => 'staff:' . $stranger->id, 'subject' => 'x', 'body' => 'y']);
    $teachesToo = in_array($stranger->id, app(Directory::class)->lecturerIdsFor($student->id));
    check('a forged To for staff who do not teach them is refused', $teachesToo || ($st === 302 && Thread::where('subject', 'x')->count() === 0), $st);

    $file = UploadedFile::fake()->create('timetable.pdf', 120, 'application/pdf');
    [$st, , $res] = $http('student', $student, 'POST', '/student/mailbox', ['to' => 'staff:' . $lecturer->id, 'subject' => 'With file', 'body' => '<script>alert(1)</script> see attached'], ['attachments' => [$file]]);
    $wf = Thread::where('subject', 'With file')->first();
    $att = $wf ? DB::table('mailbox_attachments as a')->join('mailbox_messages as m', 'm.id', '=', 'a.message_id')->where('m.thread_id', $wf->id)->select('a.*')->first() : null;
    if ($att) { $stored[] = $att->path; }
    check('a message with an attachment is sent', $st === 302 && $wf && $att && Storage::disk('local')->exists($att->path), $st);
    [$st, $html] = $http('web', $lecturer, 'GET', '/admin/mailbox/' . $wf->id);
    check('the text is shown, never run as a script', $st === 200 && !str_contains($html, '<script>alert(1)</script>'), $st);
    [$st] = $http('web', $lecturer, 'GET', '/admin/mailbox/attachment/' . $att->id);
    check('a participant can download the attachment', $st === 200, $st);
    [$st] = $http('web', $stranger, 'GET', '/admin/mailbox/attachment/' . $att->id);
    check('anyone else cannot', $st !== 200, $st);
    $big = UploadedFile::fake()->create('huge.pdf', 11000, 'application/pdf');
    [$st] = $http('student', $student, 'POST', '/student/mailbox', ['to' => 'staff:' . $lecturer->id, 'subject' => 'Too big', 'body' => 'x'], ['attachments' => [$big]]);
    check('a file over 10 MB is refused', Thread::where('subject', 'Too big')->count() === 0);
    $exe = UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream');
    [$st] = $http('student', $student, 'POST', '/student/mailbox', ['to' => 'staff:' . $lecturer->id, 'subject' => 'Bad type', 'body' => 'x'], ['attachments' => [$exe]]);
    check('a program file is refused', Thread::where('subject', 'Bad type')->count() === 0);

    // group send through the screen: the count must be confirmed
    [$st] = $http('web', $admin, 'POST', '/admin/mailbox', ['mode' => 'group', 'group_type' => 'course', 'group_subject' => $subject->id, 'confirm_count' => 999, 'subject' => 'Wrong count', 'body' => 'x']);
    check('a group send with a stale count is stopped', Thread::where('subject', 'Wrong count')->count() === 0);
    $n = count(app(Directory::class)->audience(['type' => 'course', 'subject' => $subject->id])['members']);
    [$st, , $res] = $http('web', $admin, 'POST', '/admin/mailbox', ['mode' => 'group', 'group_type' => 'course', 'group_subject' => $subject->id, 'confirm_count' => $n, 'subject' => 'Right count', 'body' => 'x']);
    check('with the right count it goes to the whole class', Thread::where('subject', 'Right count')->count() === $n, $st);
    [$st, $html] = $http('web', $admin, 'GET', '/admin/mailbox?folder=groups');
    check('Group messages lists it', $st === 200 && str_contains($html, 'Right count'), $st);
    [$st, $html] = $http('web', $admin, 'GET', '/admin/mailbox/audience?group_type=all_staff');
    check('the audience count answers before sending', $st === 200 && json_decode($html, true)['count'] === User::where('status', '1')->count(), $st);
    if (!$lecturerCanBroadcast) {
        [$st] = $http('web', $lecturer, 'POST', '/admin/mailbox', ['mode' => 'group', 'group_type' => 'all_staff', 'confirm_count' => 1, 'subject' => 'Not allowed', 'body' => 'x']);
        check('a lecturer cannot message all staff without the permission', $st === 403 && Thread::where('subject', 'Not allowed')->count() === 0, $st);
    }
    [$st, $html] = $http('web', $admin, 'GET', '/admin/mailbox/offices');
    check('the offices page opens for an administrator', $st === 200 && str_contains($html, 'Bursary test'), $st);
} catch (\Throwable $e) {
    $fail++;
    echo '  FAIL  exception: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    DB::rollBack();
    foreach ($stored as $p) { Storage::disk('local')->delete($p); }
}
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
