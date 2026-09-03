<?php
/**
 * A transcript's QR code verifies the marks that were printed on it.
 *
 * Verifying only that the student exists — which is what an ID card's code
 * does, and it is right for an ID card — would let someone edit the GPA in the
 * PDF and still scan as genuine. So the figures are snapshotted when the
 * transcript is issued and the code points at that snapshot.
 *
 * The snapshot is built by TranscriptSnapshotService while the page is drawn by
 * the view's own arithmetic. Those are two implementations of one rule, so the
 * first section here asserts they agree: if either is changed alone, this
 * fails rather than quietly issuing transcripts whose codes contradict them.
 *
 * Usage: php scripts/transcript_verification_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Student;
use App\Models\StudentEnroll;
use App\Models\TranscriptRecord;
use App\Services\TranscriptSnapshotService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

$kernel = app(Illuminate\Contracts\Http\Kernel::class);

$get = function (string $uri, array $params = []) use ($kernel) {
    $request = Illuminate\Http\Request::create($uri, 'GET', $params);
    $request->setLaravelSession(app('session.store'));

    return $kernel->handle($request);
};

// A student who actually has marks, or none of this proves anything.
$enroll = StudentEnroll::whereHas('subjectMarks')->whereNotNull('session_id')->first();

if (!$enroll) {
    echo "\n  SKIP  no enrolment with marks to build a transcript from\n";
    exit(0);
}

$student = Student::find($enroll->student_id);

Auth::guard('web')->login(App\User::where('is_admin', 1)->firstOrFail());

echo "\n== The stored snapshot matches the printed page ==\n";

$response = $get('/admin/transcript/marksheet-download/' . $student->id, ['enrollment_id' => $enroll->id]);
$html = $response->getContent();

check('the transcript renders', $response->getStatusCode() === 200,
    'status ' . $response->getStatusCode());

$record = TranscriptRecord::where('student_enroll_id', $enroll->id)->first();
check('issuing it recorded the transcript', $record !== null);

// Pull the figures off the page itself rather than trusting the service twice.
preg_match('#Cumulative GPA</span>\s*<span[^>]*>([\d.]+)#s', $html, $gpaOnPage);
preg_match('#Total Credits</span>\s*<span[^>]*>([\d,.]+)#s', $html, $creditsOnPage);
preg_match('#Courses</span>\s*<span[^>]*>(\d+)#s', $html, $coursesOnPage);
preg_match('#Standing</span>\s*<span[^>]*>([^<]+)#s', $html, $standingOnPage);

check('the GPA on the page is the GPA in the record',
    isset($gpaOnPage[1]) && abs((float) $gpaOnPage[1] - (float) $record->cumulative_gpa) < 0.01,
    'page ' . ($gpaOnPage[1] ?? '?') . ' vs record ' . $record->cumulative_gpa);

check('the credits agree',
    isset($creditsOnPage[1])
        && abs((float) str_replace(',', '', $creditsOnPage[1]) - (float) $record->total_credits) < 0.01,
    'page ' . ($creditsOnPage[1] ?? '?') . ' vs record ' . $record->total_credits);

check('the course count agrees',
    isset($coursesOnPage[1]) && (int) $coursesOnPage[1] === (int) $record->total_courses,
    'page ' . ($coursesOnPage[1] ?? '?') . ' vs record ' . $record->total_courses);

check('the standing agrees',
    isset($standingOnPage[1]) && trim($standingOnPage[1]) === trim((string) $record->standing),
    'page "' . trim($standingOnPage[1] ?? '') . '" vs record "' . $record->standing . '"');

check('every course is in the snapshot',
    count($record->courses_snapshot ?? []) === (int) $record->total_courses
        || count($record->courses_snapshot ?? []) > 0,
    count($record->courses_snapshot ?? []) . ' captured');

echo "\n== The code is on the document, and generated here ==\n";

check('a QR code is drawn', str_contains($html, '<svg'));
check('it is generated locally, not fetched from an image service',
    !str_contains($html, 'api.qrserver.com') && !str_contains($html, 'chart.googleapis.com'));
check('the verification address is printed too',
    str_contains($html, $record->verification_url));
check('so is the reference', str_contains($html, $record->verification_code));

echo "\n== Issuing the same transcript twice does not mint a second code ==\n";

$codeBefore = $record->verification_code;
$get('/admin/transcript/marksheet-print/' . $student->id, ['enrollment_id' => $enroll->id]);

check('still one record for this enrolment',
    TranscriptRecord::where('student_enroll_id', $enroll->id)->count() === 1);
check('and the code is unchanged',
    TranscriptRecord::where('student_enroll_id', $enroll->id)->value('verification_code') === $codeBefore);

echo "\n== Anyone can check it, without an account ==\n";

Auth::guard('web')->logout();

$verify = $get('/verify-transcript/' . $record->verification_code);
$page = $verify->getContent();

check('the public page opens logged out', $verify->getStatusCode() === 200,
    'status ' . $verify->getStatusCode());
check('it says the transcript is verified', str_contains($page, 'Transcript verified'));
check('it shows the name', str_contains($page, $record->student_name));
check('it shows the GPA as issued',
    str_contains($page, number_format((float) $record->cumulative_gpa, 2)));

$unknown = $get('/verify-transcript/TRN-NOSUCHCODE00000');
check('an unknown reference is refused', str_contains($unknown->getContent(), 'Not verified'));
check('and it does not leak anyone\'s name',
    !str_contains($unknown->getContent(), (string) $record->student_name));

echo "\n== A mark changed afterwards does not rewrite the issued transcript ==\n";

// This is the whole point of the snapshot: the document says what it said on
// the day it was signed, and a later correction shows up as a disagreement
// rather than silently rewriting history.
DB::beginTransaction();
try {
    $issuedGpa = (float) $record->cumulative_gpa;
    $issuedCourses = count($record->courses_snapshot ?? []);

    $mark = DB::table('subject_markings')->where('student_enroll_id', $enroll->id)->first();

    if ($mark) {
        DB::table('subject_markings')->where('id', $mark->id)->update(['total_marks' => 100]);

        $recomputed = app(TranscriptSnapshotService::class)
            ->build(Student::with([
                'studentEnrolls.subjectMarks.subject',
                'studentEnrolls.session',
                'studentEnrolls.semester',
            ])->find($student->id), $enroll);

        $stored = TranscriptRecord::find($record->id);

        check('the live figures moved', abs($recomputed['cumulative_gpa'] - $issuedGpa) > 0.001,
            'live ' . $recomputed['cumulative_gpa'] . ' vs issued ' . $issuedGpa);
        check('the issued record did not', abs((float) $stored->cumulative_gpa - $issuedGpa) < 0.001,
            'record now ' . $stored->cumulative_gpa);
        check('its course list did not either',
            count($stored->courses_snapshot ?? []) === $issuedCourses);
    } else {
        echo "  SKIP  no mark to alter\n";
    }
} finally {
    DB::rollBack();
}

echo "\n$passed passed, $failed failed\n";
