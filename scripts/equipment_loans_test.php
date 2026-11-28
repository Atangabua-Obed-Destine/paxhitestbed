<?php
/**
 * Equipment loans and the library's scan desk: what a scan resolves to, every
 * lending rule, check-in with condition and accessories, follow-ups, lost
 * items, reminders, the pages, permissions, and the library desk issuing to a
 * person who becomes a member on the spot. Everything is rolled back.
 *
 *   php scripts/equipment_loans_test.php
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Book;
use App\Models\Equipment\Item;
use App\Models\Equipment\Loan;
use App\Models\Equipment\Setting;
use App\Models\IssueReturn;
use App\Models\LibraryMember;
use App\Models\Mailbox\Message;
use App\Models\Mailbox\Participant;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Services\Equipment\DeskRefusal;
use App\Services\Equipment\LoanDesk;
use App\Services\Equipment\Reminders;
use App\Services\Equipment\ScanResolver;
use App\Services\Library\ScanDesk;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

config(['mail.default' => 'array']);

$pass = 0; $fail = 0;
function check($label, $ok, $detail = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ok    $label\n"; }
    else { $fail++; echo "  FAIL  $label" . ($detail !== '' ? "   [$detail]" : '') . "\n"; }
}
function refused(callable $f, string $needle = ''): string {
    try { $f(); return 'NOT REFUSED'; }
    catch (DeskRefusal $e) { return $needle === '' || stripos($e->getMessage(), $needle) !== false ? 'ok' : 'wrong message: ' . $e->getMessage(); }
}
$http = function ($who, string $method, string $uri, array $data = []) use ($kernel) {
    app()->forgetScopedInstances();
    Auth::guard('web')->logout();
    app('session.store')->flush(); app('session.store')->start();
    if ($who) { Auth::guard('web')->login($who); }
    $data['_token'] = app('session.store')->token();
    $r = Illuminate\Http\Request::create($uri, $method, $data);
    $r->headers->set('Accept', $method === 'GET' ? 'text/html' : 'application/json');
    $r->setLaravelSession(app('session.store'));
    $res = $kernel->handle($r);
    return [$res->getStatusCode(), (string) $res->getContent()];
};
$fresh = fn () => [app()->forgetScopedInstances(), app(LoanDesk::class)][1];

DB::beginTransaction();
try {
    // People with nothing out, so loans already on this database cannot block them.
    $busyStaff = Loan::open()->where('borrower_type', 'staff')->pluck('borrower_id')->all();
    $busyStudents = Loan::open()->where('borrower_type', 'student')->pluck('borrower_id')->all();
    $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->where('status', '1')->first();
    $staff = User::where('status', '1')->whereNotNull('staff_id')->where('staff_id', '<>', '')->where('id', '<>', $admin->id)->whereNotIn('id', $busyStaff)->orderBy('id')->first();
    $lecturer = User::where('status', '1')->whereNotIn('id', [$admin->id, $staff->id])->orderBy('id')->first();
    $enrol = StudentEnroll::whereNotNull('matricule')->where('matricule', '<>', '')->where('status', '1')->whereNotIn('student_id', $busyStudents)
        ->whereHas('student', fn ($q) => $q->where('status', '1'))->orderByDesc('id')->first();
    $student = Student::find($enrol->student_id);
    $other = Student::where('status', '1')->where('id', '<>', $student->id)->whereNotIn('id', $busyStudents)->orderByDesc('id')->first();
    $S = ['student', (int) $student->id];
    $O = ['student', (int) $other->id];
    $F = ['staff', (int) $staff->id];
    Setting::current()->update(['block_across_desks' => false, 'first_reminder_minutes' => 30, 'daily_reminder_time' => '08:00', 'default_due_time' => '17:00', 'library_loan_days' => 14]);

    $prj = Item::create(['code' => 'ZZPRJ-01', 'name' => 'Test projector', 'accessories' => ['Power cable', 'Remote'], 'students_may_borrow' => true, 'desk_id' => DB::table('equipment_desks')->value('id'), 'state' => 'active']);
    $lap = Item::create(['code' => 'ZZLAP-02', 'name' => 'Test laptop', 'accessories' => ['Charger'], 'students_may_borrow' => false, 'state' => 'active']);
    $spk = Item::create(['code' => 'ZZSPK-03', 'name' => 'Test speaker', 'accessories' => [], 'students_may_borrow' => true, 'state' => 'active']);
    $by = $admin->id;
    $future = now()->addHours(3);

    echo "\n=== what a scan resolves to ===\n";
    $r = app(ScanResolver::class);
    $hit = $r->resolve($enrol->matricule);
    check('student card barcode (matricule) finds the student', $hit && $hit['member'] === $S, json_encode($hit['member'] ?? null));
    $hit = $r->resolve('https://paxhi.org/verify-student/' . $enrol->matricule);
    check('student card QR link finds the same student', $hit && $hit['member'] === $S);
    $hit = $r->resolve($staff->staff_id);
    check('staff card barcode (staff ID) finds the staff member', $hit && $hit['member'] === $F);
    $hit = $r->resolve(url('verify-staff/' . $staff->id));
    check('staff card QR link (user id) finds the staff member', $hit && $hit['member'] === $F);
    $hit = $r->resolve('  zzprj-01 ');
    check('a sticker code typed in lower case finds the item', $hit && $hit['kind'] === 'item' && $hit['item']->id === $prj->id);
    check('nonsense finds nothing', $r->resolve('NO-SUCH-THING-9') === null);
    check('a sticker is not a person at the library desk', ($r->resolve('ZZPRJ-01', ScanResolver::LIBRARY)['kind'] ?? null) !== 'item');

    echo "\n=== lending rules ===\n";
    $desk = $fresh();
    $loan = $desk->lend($S, $prj, ['purpose' => 'class', 'purpose_detail' => 'Year 2, room B4', 'due_at' => $future], $by);
    check('a student borrows an item marked for students, for a class', $loan->status === 'out' && $loan->purpose === 'class' && $loan->purpose_detail === 'Year 2, room B4');
    check('what goes with it is recorded as going out', $loan->accessories_out === ['Power cable', 'Remote'], json_encode($loan->accessories_out));
    check('the attendant is recorded', (int) $loan->lent_by === $by);
    check('the same item cannot go out twice', refused(fn () => $desk->lend($F, $prj, ['due_at' => $future], $by), 'already out') === 'ok');
    check('a student cannot borrow a staff-only item', refused(fn () => $desk->lend($S, $lap, ['due_at' => $future], $by), 'not marked for students') === 'ok');
    check('"a class" needs the class named', refused(fn () => $desk->lend($F, $lap, ['purpose' => 'class', 'due_at' => $future], $by), 'which class') === 'ok');
    check('the due time cannot be in the past', refused(fn () => $desk->lend($F, $lap, ['due_at' => now()->subMinute()], $by), 'later than now') === 'ok');
    $lap->update(['state' => 'maintenance']);
    check('an item being repaired cannot be lent', refused(fn () => $desk->lend($F, $lap, ['due_at' => $future], $by), 'cannot be lent') === 'ok');
    $lap->update(['state' => 'active']);
    DB::table('students')->where('id', $other->id)->update(['status' => '0']);
    $desk = $fresh();
    check('someone no longer active is refused', refused(fn () => $desk->lend($O, $spk, ['due_at' => $future], $by), 'no longer active') === 'ok');
    DB::table('students')->where('id', $other->id)->update(['status' => '1']);
    $desk = $fresh();

    $threadsBefore = DB::table('mailbox_threads')->count();
    $lecLoan = $desk->lend($F, $lap, ['purpose' => 'lecturer', 'lecturer_id' => $lecturer->id, 'due_at' => $future], $by);
    check('borrowing for a lecturer names the lecturer', (int) $lecLoan->lecturer_id === (int) $lecturer->id);
    $lecLoan->refresh();
    $parts = $lecLoan->mailbox_thread_id ? Participant::where('thread_id', $lecLoan->mailbox_thread_id)->get()->map(fn ($p) => $p->member_type . ':' . $p->member_id)->all() : [];
    check('the lecturer gets a message, in a conversation with the borrower', in_array('staff:' . $lecturer->id, $parts) && in_array('staff:' . $staff->id, $parts), json_encode($parts));
    check('and only one conversation was opened', DB::table('mailbox_threads')->count() === $threadsBefore + 1);

    echo "\n=== overdue stops further borrowing ===\n";
    $loan->update(['due_at' => now()->subHour()]);
    $desk = $fresh();
    check('someone with an overdue item is blocked', count($desk->blockers($S)) === 1 && str_contains($desk->blockers($S)[0], 'ZZPRJ-01'));
    check('and the desk refuses them another item', refused(fn () => $desk->lend($S, $spk, ['due_at' => $future], $by), 'still out') === 'ok');
    check('the board status says overdue', $desk->dueStatus($loan->fresh())['tone'] === 'bad');

    $book = Book::create(['category_id' => DB::table('book_categories')->value('id') ?? DB::table('book_categories')->insertGetId(['title' => 'Test', 'created_at' => now(), 'updated_at' => now()]), 'title' => 'Test Book of Nursing', 'isbn' => '978-0-00-000001-1', 'quantity' => 1, 'status' => '1']);
    $lib = app(ScanDesk::class);
    check('with the cross-desk switch off, the library still lends to them', ($lib->person($S)['blockers'] ?? ['x']) === [], json_encode($lib->person($S)['blockers']));
    Setting::current()->update(['block_across_desks' => true]);
    $lib = app(ScanDesk::class);
    check('with it on, the library refuses them too', refused(fn () => $lib->issue($S, $book, today()->addDays(7), $by), 'still out') === 'ok');
    Setting::current()->update(['block_across_desks' => false]);

    echo "\n=== checking in ===\n";
    $desk = $fresh();
    check('a returner must be scanned', refused(fn () => $desk->checkIn($prj, null, ['Power cable', 'Remote'], 'fine', null, $by), 'whoever brought it back') === 'ok');
    check('a missing accessory needs a note', refused(fn () => $desk->checkIn($prj, $O, ['Power cable'], 'fine', '', $by), 'short note') === 'ok');
    $back = $desk->checkIn($prj, $O, ['Power cable'], 'fine', 'Remote left in B4', $by);
    check('checked in by someone else: both are kept', $back->status === 'returned' && $back->borrower() === $S && $back->returner() === $O);
    check('an unticked accessory turns "fine" into "something missing"', $back->return_condition === 'missing', $back->return_condition);
    check('and puts it on the follow-up list', $back->follow_up === true && $back->missingAccessories() === ['Remote']);
    check('the item is back on the shelf', $desk->item($prj->fresh())['lendable'] === true);
    check('checking in an item that is in is refused', refused(fn () => $desk->checkIn($prj, $O, [], 'fine', null, $by), 'not out') === 'ok');
    check('a follow-up needs a note to clear', refused(fn () => $desk->clearFollowUp($back, '', $by), 'how it was settled') === 'ok');
    $desk->clearFollowUp($back, 'Remote found', $by);
    check('cleared with a note', $back->fresh()->follow_up_cleared_at !== null && $back->fresh()->follow_up_note === 'Remote found');
    $lost = $desk->lend($F, $spk, ['due_at' => $future], $by);
    $desk->markLost($lost, 'Left on the bus', $by);
    check('an item marked lost: the loan says lost and the item cannot be lent', $lost->fresh()->status === 'lost' && $spk->fresh()->state === 'lost');

    echo "\n=== reminders ===\n";
    $rem = $desk->lend($S, $prj, ['due_at' => now()->addMinutes(5)], $by);
    $due = Carbon::parse('next monday')->setTime(16, 0);
    $rem->update(['due_at' => $due, 'lent_at' => $due->copy()->subHours(4), 'reminders_sent' => 0, 'last_reminded_at' => null, 'mailbox_thread_id' => null]);
    $loanIdsBefore = Loan::open()->where('id', '<>', $rem->id)->pluck('id');
    Loan::whereIn('id', $loanIdsBefore)->update(['due_at' => now()->addYear()]);
    $run = fn ($at) => [app()->forgetScopedInstances(), app(Reminders::class)->run(Carbon::parse($at))][1];
    $run($due->copy()->addMinutes(10));
    check('no reminder inside the grace period', (int) $rem->fresh()->reminders_sent === 0);
    $run($due->copy()->addMinutes(35));
    $rem->refresh();
    check('the first reminder goes after the grace period', (int) $rem->reminders_sent === 1 && $rem->mailbox_thread_id);
    $msgs = Message::where('thread_id', $rem->mailbox_thread_id)->count();
    $run($due->copy()->addHours(2));
    check('no second reminder the same day', (int) $rem->fresh()->reminders_sent === 1);
    $run($due->copy()->addDay()->setTime(7, 30));
    check('none the next day before the daily time', (int) $rem->fresh()->reminders_sent === 1);
    $run($due->copy()->addDay()->setTime(8, 5));
    check('one the next day after the daily time', (int) $rem->fresh()->reminders_sent === 2);
    check('in the same conversation', Message::where('thread_id', $rem->mailbox_thread_id)->count() === $msgs + 1);
    $run($due->copy()->next(Carbon::SUNDAY)->setTime(9, 0));
    check('never on a Sunday', (int) $rem->fresh()->reminders_sent === 2);
    $desk->checkIn($prj, $S, ['Power cable', 'Remote'], 'fine', null, $by);
    $run($due->copy()->addDays(2)->setTime(9, 0));
    check('nothing once it is back', (int) $rem->fresh()->reminders_sent === 2);

    echo "\n=== the pages ===\n";
    [$st, $html] = $http($admin, 'GET', '/admin/equipment/desk');
    check('the desk opens', $st === 200 && str_contains($html, 'eq-scan-form') && str_contains($html, 'html5-qrcode'), $st);
    [$st, $json] = $http($admin, 'POST', '/admin/equipment/desk/scan', ['code' => 'ZZLAP-02']);
    $j = json_decode($json, true);
    check('scanning a sticker over HTTP returns the item', $st === 200 && ($j['kind'] ?? '') === 'item' && $j['item']['code'] === 'ZZLAP-02', $st);
    [$st, $json] = $http($admin, 'POST', '/admin/equipment/desk/scan', ['code' => $enrol->matricule]);
    $j = json_decode($json, true);
    check('scanning a card over HTTP returns the person, with their photo slot and blockers', $st === 200 && ($j['person']['key'] ?? '') === 'student:' . $student->id && array_key_exists('blockers', $j['person']));
    $lecLoan->update(['status' => 'returned', 'returned_at' => now()]);
    [$st, $json] = $http($admin, 'POST', '/admin/equipment/desk/lend', ['person' => 'staff:' . $staff->id, 'item_id' => $lap->id, 'purpose' => 'self', 'due_at' => now()->addHours(2)->format('Y-m-d\TH:i')]);
    $j = json_decode($json, true);
    check('lending over HTTP', $st === 200 && ($j['ok'] ?? false) && $j['item']['loan']['borrower']['key'] === 'staff:' . $staff->id, $st . ' ' . substr($json, 0, 200));
    [$st, $json] = $http($admin, 'POST', '/admin/equipment/desk/lend', ['person' => 'staff:' . $staff->id, 'item_id' => $lap->id, 'purpose' => 'self', 'due_at' => now()->addHours(2)->format('Y-m-d\TH:i')]);
    check('a refusal over HTTP is a 422 with the reason', $st === 422 && str_contains($json, 'already out'), $st);
    [$st, $html] = $http($admin, 'GET', '/admin/equipment/out-now');
    check('the Out-now board lists it', $st === 200 && str_contains($html, 'ZZLAP-02'), $st);
    [$st, $json] = $http($admin, 'POST', '/admin/equipment/desk/check-in', ['item_id' => $lap->id, 'returner' => 'staff:' . $staff->id, 'back' => ['Charger'], 'condition' => 'fine']);
    check('checking in over HTTP', $st === 200 && str_contains($json, 'back on the shelf'), $st . ' ' . substr($json, 0, 160));
    foreach (['/admin/equipment/items' => 'ZZPRJ-01', '/admin/equipment/items/create' => 'Sticker code', '/admin/equipment/items/' . $prj->id . '/edit' => 'ZZPRJ-01',
              '/admin/equipment/settings' => 'Lending desks', '/admin/equipment/history/item/' . $prj->id => 'Test projector',
              '/admin/equipment/history/student/' . $student->id => 'ZZPRJ-01', '/admin/library/scan-desk' => 'Library scan desk'] as $uri => $needle) {
        [$st, $html] = $http($admin, 'GET', $uri);
        check("$uri opens", $st === 200 && str_contains($html, $needle), $st);
    }
    [$st, $html] = $http($admin, 'GET', '/admin/equipment/items/stickers?ids=' . $prj->id);
    check('the sticker sheet carries a QR code and a barcode for the item', $st === 200 && str_contains($html, 'ZZPRJ-01') && substr_count($html, '<svg') >= 2 && !str_contains($html, '<?xml'), $st);
    [$st, $html] = $http($admin, 'POST', '/admin/equipment/items', ['code' => strtolower($enrol->matricule), 'name' => 'Clash', 'state' => 'active']);
    check('a sticker code that is somebody\'s matricule is refused', !Item::where('name', 'Clash')->exists());
    $http($admin, 'POST', '/admin/equipment/items', ['code' => 'zzcam-04', 'name' => 'Test camera', 'accessories' => "Lens cap\nStrap, Strap", 'state' => 'active', 'students_may_borrow' => '1']);
    $cam = Item::where('code', 'ZZCAM-04')->first();
    check('a new item: code upper-cased, accessories one each', $cam && $cam->accessories === ['Lens cap', 'Strap'] && $cam->students_may_borrow);

    $nobody = User::where('status', '1')->whereDoesntHave('roles', fn ($q) => $q->whereHas('permissions', fn ($p) => $p->where('name', 'like', 'equipment-%')))->first();
    if ($nobody) {
        [$st] = $http($nobody, 'GET', '/admin/equipment/desk');
        check('someone without the permission cannot open the desk', in_array($st, [403, 302], true), $st);
    }

    echo "\n=== the library scan desk ===\n";
    $member = LibraryMember::where('memberable_type', get_class($other))->where('memberable_id', $other->id)->first();
    if ($member) { IssueReturn::where('member_id', $member->id)->delete(); $member->delete(); }
    [$st, $json] = $http($admin, 'POST', '/admin/library/scan-desk/scan', ['code' => '9780000000011']);
    $j = json_decode($json, true);
    check('an ISBN without its dashes finds the book', $st === 200 && ($j['kind'] ?? '') === 'book' && $j['book']['id'] === $book->id, $st . ' ' . substr($json, 0, 120));
    [$st, $json] = $http($admin, 'POST', '/admin/library/scan-desk/issue', ['person' => 'student:' . $other->id, 'book_id' => $book->id, 'due_date' => today()->addDays(14)->format('Y-m-d')]);
    $j = json_decode($json, true);
    check('issuing to a non-member makes them a member', $st === 200 && ($j['joined'] ?? false) === true, $st . ' ' . substr($json, 0, 200));
    $m = LibraryMember::where('memberable_id', $other->id)->where('memberable_type', get_class($other))->first();
    check('their library ID is their own ID', $m && str_starts_with($m->library_id, preg_replace('/[^A-Za-z0-9\-]/', '', $fresh()->describe($O)['code'])), optional($m)->library_id);
    $issue = IssueReturn::where('book_id', $book->id)->where('status', 1)->first();
    check('the issue is a normal library issue row', $issue && (int) $issue->member_id === (int) $m->id && (int) $issue->issued_by === (int) $admin->id);
    [$st, $json] = $http($admin, 'POST', '/admin/library/scan-desk/issue', ['person' => 'student:' . $student->id, 'book_id' => $book->id, 'due_date' => today()->addDays(14)->format('Y-m-d')]);
    check('with its only copy out, the book is refused', $st === 422 && str_contains($json, 'only copy'), $st);
    $info = app(ScanDesk::class)->book($book->fresh());
    check('the book shows who has it', $info['on_shelf'] === 0 && count($info['holders']) === 1 && $info['holders'][0]['issue_id'] === $issue->id);
    [$st, $json] = $http($admin, 'POST', '/admin/library/scan-desk/return', ['issue_id' => $issue->id]);
    check('returning it', $st === 200 && (int) $issue->fresh()->status === 2 && $issue->fresh()->return_date !== null, $st);
    [$st, $json] = $http($admin, 'POST', '/admin/library/scan-desk/return', ['issue_id' => $issue->id]);
    check('returning it twice is refused', $st === 422, $st);
} catch (\Throwable $e) {
    $fail++;
    echo '  FAIL  exception: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    DB::rollBack();
}
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
