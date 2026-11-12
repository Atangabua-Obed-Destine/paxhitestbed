<?php
/**
 * A platform fee exemption for a student means "this student, this session".
 *
 * It used to be recorded against one enrolment row, so the form could only
 * offer enrolments that already existed: a student could not be excused for
 * 2026/2027 until they had been enrolled for it, and a student with several
 * enrolments in a year was listed once per enrolment. This suite drives the
 * screen and the store action the way the Bursar does, then asks the access
 * check the question the portal asks, and holds the rules that make the new
 * shape right: granted before enrolment and honoured once enrolled, held
 * across every enrolment of the year, confined to that year, old rows carried
 * across, duplicates refused.
 *
 * Usage: php scripts/platform_fee_exemption_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PlatformFeeExemption;
use App\Models\PlatformFeePayment;
use App\Models\PlatformFeeSetting;
use App\Models\Session;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Services\PlatformFeeAccess;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$passed = 0;
$failed = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  PASS  $label\n";
    } else {
        $failed++;
        echo "  FAIL  $label" . ($detail !== '' ? "\n          $detail" : '') . "\n";
    }
}

$access = app(PlatformFeeAccess::class);
$admin = User::whereHas('roles')->first() ?: User::first();
Auth::guard('web')->login($admin);
$kernel = app(Illuminate\Contracts\Http\Kernel::class);

$http = function (string $method, string $path, array $data = []) use ($kernel) {
    $request = Request::create($path, $method, $data + ($method === 'POST' ? ['_token' => csrf_token()] : []));
    $request->setLaravelSession(app('session.store'));
    return $kernel->handle($request);
};
// What the redirect flashed: the session middleware uses its own store, so it
// is read off the response rather than the container.
$flash = fn ($response, string $key) => method_exists($response, 'getSession') && $response->getSession() ? $response->getSession()->get($key) : null;
// Whether a key was flashed at all. The kernel run here ages flash data
// twice, so a plain message has already gone by the time it can be read; the
// flash bookkeeping still names it.
$flashed = function ($response, string $key) use ($flash): bool {
    if ($flash($response, $key) !== null) {
        return true;
    }
    $session = method_exists($response, 'getSession') ? $response->getSession() : null;
    return $session && (in_array($key, (array) $session->get('_flash.old', []), true) || in_array($key, (array) $session->get('_flash.new', []), true));
};

echo "\n== Ground ==\n";

check('the exemption names the student', Schema::hasColumn('platform_fee_exemptions', 'student_id'));
check('and the session', Schema::hasColumn('platform_fee_exemptions', 'session_id'));

$fk = collect(DB::select("SELECT DELETE_RULE, COLUMN_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS rc
    JOIN information_schema.KEY_COLUMN_USAGE k ON k.CONSTRAINT_NAME = rc.CONSTRAINT_NAME AND k.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
    WHERE rc.CONSTRAINT_SCHEMA = DATABASE() AND rc.TABLE_NAME = 'platform_fee_exemptions'"))->keyBy('COLUMN_NAME');
check('deleting an enrolment no longer deletes the exemption', ($fk['student_enroll_id']->DELETE_RULE ?? '') === 'SET NULL', $fk['student_enroll_id']->DELETE_RULE ?? 'no constraint');
check('deleting the student does', ($fk['student_id']->DELETE_RULE ?? '') === 'CASCADE');

$sessions = Session::where('status', 1)->orderBy('id')->get();
check('at least two active sessions to tell apart', $sessions->count() >= 2, $sessions->pluck('title')->implode(', '));
[$thisYear, $nextYear] = [$sessions->first(), $sessions->last()];

// A student enrolled this year and not next year: the Bursar's case.
$student = Student::where('status', 1)
    ->whereHas('enrolls', fn ($q) => $q->where('session_id', $thisYear->id))
    ->whereDoesntHave('enrolls', fn ($q) => $q->where('session_id', $nextYear->id))
    ->first();
check('a student enrolled for ' . $thisYear->title . ' but not yet for ' . $nextYear->title, $student !== null, $student ? $student->student_id : '');
if (!$student) {
    echo "\n$passed passed, $failed failed\n";
    exit(1);
}

echo "\n== The screen offers every student and every session ==\n";

$html = $http('GET', '/admin/platform-fee/exemptions')->getContent();
$activeStudents = Student::where('status', 1)->count();
check('the page renders', strpos($html, 'id="createExemptionModal"') !== false);
check('the student list carries one line per student', preg_match_all('/<option value="\d+">\s*[^<]*\(' . preg_quote($student->student_id, '/') . '\)/', $html) === 1);
check('and every active student', substr_count($html, 'name="student_id"') === 1 && preg_match_all('/<select[^>]*id="student_id".*?<\/select>/s', $html, $m) === 1
    && substr_count($m[0][0], '<option value="') - 1 === $activeStudents, 'listed vs ' . $activeStudents);
check('the session list offers ' . $nextYear->title, preg_match('/<option value="' . $nextYear->id . '">' . preg_quote($nextYear->title, '/') . '/', $html) === 1);
check('the student is no longer tied to an enrolment', strpos($html, 'student_enroll_id') === false);
check('a student exemption asks for its session', strpos($html, "\$('#session_field').show();\n            \$('#session_id').attr('required', true);\n            \$('#session_label').text('Exempt for session');") !== false);

echo "\n== Excused for next year before being enrolled for it ==\n";

DB::beginTransaction();
try {
    PlatformFeeSetting::first()?->update(['is_enabled' => 1]);
    PlatformFeeExemption::where('exemption_type', 'session')->whereIn('session_id', [$thisYear->id, $nextYear->id])->delete();
    PlatformFeeExemption::where('student_id', $student->id)->delete();
    PlatformFeePayment::whereIn('student_enroll_id', $student->enrolls()->pluck('id'))->delete();

    $response = $http('POST', '/admin/platform-fee/exemptions', [
        'exemption_type' => 'student', 'student_id' => $student->id, 'session_id' => $nextYear->id,
        'reason' => 'ZZ suite: scholarship next year',
    ]);
    check('the form is accepted', $response->getStatusCode() === 302 && $flashed($response, 'success'), 'status ' . $response->getStatusCode() . ' ' . json_encode($flash($response, 'errors')?->all()));

    $row = PlatformFeeExemption::where('student_id', $student->id)->where('session_id', $nextYear->id)->first();
    check('stored as the student and the session', $row && $row->exemption_type === 'student' && (int) $row->session_id === (int) $nextYear->id);
    check('with no enrolment behind it', $row && $row->student_enroll_id === null);
    check('switched on', $row && $row->status);

    $thisYearEnrollment = $student->enrolls()->where('session_id', $thisYear->id)->first();
    check('it does not excuse this year', !$access->isExempt($student, $thisYearEnrollment));

    // The student is now enrolled for next year — twice, semester then
    // resit, as the portal really does it.
    $base = $thisYearEnrollment->only(['program_id', 'semester_id', 'section_id']);
    $next1 = StudentEnroll::create($base + ['student_id' => $student->id, 'session_id' => $nextYear->id, 'status' => 1, 'matricule' => 'ZZ-NEXT-1']);
    $next2 = StudentEnroll::create($base + ['student_id' => $student->id, 'session_id' => $nextYear->id, 'status' => 1, 'matricule' => 'ZZ-NEXT-2']);

    check('once enrolled, the exemption holds', $access->isExempt($student, $next1));
    check('and holds on the next enrolment of the same year', $access->isExempt($student, $next2));
    check('so the portal opens', $access->grantsAccess($student->fresh()));

    $row->update(['status' => 0]);
    check('switched off, it excuses nobody', !$access->isExempt($student, $next1));
    $row->update(['status' => 1]);

    $other = Student::where('status', 1)->where('id', '!=', $student->id)->first();
    $otherEnrollment = StudentEnroll::create($base + ['student_id' => $other->id, 'session_id' => $nextYear->id, 'status' => 1, 'matricule' => 'ZZ-OTHER']);
    check('and it is for this student only', !$access->isExempt($other, $otherEnrollment));

    // Deleting an enrolment must not take a granted exemption with it.
    $next1->delete();
    check('the exemption survives an enrolment being deleted', PlatformFeeExemption::whereKey($row->id)->exists());
    check('and still holds on the enrolment that remains', $access->isExempt($student, $next2));
} finally {
    DB::rollBack();
}

echo "\n== Exemptions granted before the change still work ==\n";

DB::beginTransaction();
try {
    PlatformFeeExemption::where('exemption_type', 'session')->where('session_id', $thisYear->id)->delete();
    PlatformFeeExemption::where('student_id', $student->id)->delete();
    $enrollment = $student->enrolls()->where('session_id', $thisYear->id)->first();

    // The old shape: an enrolment and nothing else, as older code and the
    // older rows have it.
    $legacy = PlatformFeeExemption::create([
        'exemption_type' => 'student', 'student_enroll_id' => $enrollment->id,
        'status' => 1, 'reason' => 'ZZ legacy', 'created_by' => $admin->id,
    ]);
    $legacy->refresh();
    check('an exemption written against an enrolment is read as its student', (int) $legacy->student_id === (int) $student->id);
    check('and its session', (int) $legacy->session_id === (int) $thisYear->id);
    check('and excuses the student this year', $access->isExempt($student, $enrollment));

    // The migration's own backfill, replayed on a row with the columns empty.
    DB::table('platform_fee_exemptions')->where('id', $legacy->id)->update(['student_id' => null, 'session_id' => null]);
    DB::statement('UPDATE platform_fee_exemptions e JOIN student_enrolls se ON se.id = e.student_enroll_id
        SET e.student_id = se.student_id, e.session_id = COALESCE(e.session_id, se.session_id)
        WHERE e.exemption_type = "student" AND e.student_id IS NULL');
    $legacy->refresh();
    check('the migration fills them the same way', (int) $legacy->student_id === (int) $student->id && (int) $legacy->session_id === (int) $thisYear->id);
} finally {
    DB::rollBack();
}

echo "\n== A session exemption still excuses everybody ==\n";

DB::beginTransaction();
try {
    PlatformFeeExemption::where('exemption_type', 'session')->where('session_id', $thisYear->id)->delete();
    PlatformFeeExemption::where('student_id', $student->id)->delete();
    PlatformFeePayment::whereIn('student_enroll_id', $student->enrolls()->pluck('id'))->delete();
    $enrollment = $student->enrolls()->where('session_id', $thisYear->id)->first();
    check('with nothing granted the student is not excused', !$access->isExempt($student, $enrollment));

    $response = $http('POST', '/admin/platform-fee/exemptions', [
        'exemption_type' => 'session', 'session_id' => $thisYear->id, 'reason' => 'ZZ suite: free year',
    ]);
    check('the session form is accepted', $response->getStatusCode() === 302 && $flashed($response, 'success'));
    check('and excuses the student', $access->isExempt($student, $enrollment));
    check('but not next year', !$access->isExempt($student, new StudentEnroll(['student_id' => $student->id, 'session_id' => $nextYear->id])));
} finally {
    DB::rollBack();
}

echo "\n== What must be refused ==\n";

DB::beginTransaction();
try {
    PlatformFeeExemption::where('student_id', $student->id)->delete();
    $before = PlatformFeeExemption::count();

    $http('POST', '/admin/platform-fee/exemptions', ['exemption_type' => 'student', 'student_id' => $student->id, 'session_id' => $nextYear->id]);
    check('a first grant goes through', PlatformFeeExemption::count() === $before + 1);

    $response = $http('POST', '/admin/platform-fee/exemptions', ['exemption_type' => 'student', 'student_id' => $student->id, 'session_id' => $nextYear->id]);
    check('the same grant again is refused', PlatformFeeExemption::count() === $before + 1 && $flashed($response, 'error') && !$flashed($response, 'success'));

    $response = $http('POST', '/admin/platform-fee/exemptions', ['exemption_type' => 'student', 'student_id' => $student->id]);
    check('a student exemption without a session is refused', PlatformFeeExemption::count() === $before + 1 && $flash($response, 'errors')?->has('session_id'));

    $response = $http('POST', '/admin/platform-fee/exemptions', ['exemption_type' => 'student', 'session_id' => $nextYear->id]);
    check('and one without a student', PlatformFeeExemption::count() === $before + 1 && $flash($response, 'errors')?->has('student_id'));

    $response = $http('POST', '/admin/platform-fee/exemptions', ['exemption_type' => 'student', 'student_id' => 999999999, 'session_id' => $nextYear->id]);
    check('and one for a student who does not exist', PlatformFeeExemption::count() === $before + 1);

    $response = $http('POST', '/admin/platform-fee/exemptions', ['exemption_type' => 'session', 'session_id' => 999999999]);
    check('and a session that does not exist', PlatformFeeExemption::count() === $before + 1);

    // The list names the session the student is excused for.
    $html = $http('GET', '/admin/platform-fee/exemptions')->getContent();
    check('the list shows who and for which year', preg_match('/' . preg_quote($student->student_id, '/') . '\)<\/span>\s*<span class="exemption-subtitle">&middot; ' . preg_quote($nextYear->title, '/') . '/', $html) === 1);
} finally {
    DB::rollBack();
}

echo "\n== Nothing left behind ==\n";

check('no suite exemption survived', PlatformFeeExemption::where('reason', 'like', 'ZZ %')->doesntExist());
check('no suite enrolment survived', StudentEnroll::where('matricule', 'like', 'ZZ-%')->doesntExist());
check('no transaction left open', DB::transactionLevel() === 0);

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
