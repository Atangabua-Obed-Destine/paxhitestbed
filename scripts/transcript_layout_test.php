<?php

/**
 * How an official transcript falls onto paper.
 *
 * The page used to break in the middle of a semester: a reader got half its
 * courses on one sheet, its subtotal and GPA on the next, and nothing to say
 * the two belonged together. A short transcript also spilled onto a second
 * sheet for the sake of the signature block alone.
 *
 * These are print-layout rules, so they are checked against the printed
 * artefact rather than the markup: each transcript is rendered through the real
 * route, printed to PDF by headless Chrome, and read back page by page. A
 * severed semester is detected by counting, on each page, semester headers
 * against semester-GPA lines — a header whose GPA landed on another page is one
 * that was cut in half. That test does not consult the stylesheet, so a rule
 * that stopped working could not hide behind it.
 *
 * Nothing is written to the database.
 *
 *   php scripts/transcript_layout_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Student;

$passed = 0;
$failed = 0;
$skipped = 0;

function check(string $what, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  PASS  $what\n";
    } else {
        $failed++;
        echo "  FAIL  $what" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

function skip(string $why): void
{
    global $skipped;
    $skipped++;
    echo "  SKIP  $why\n";
}

function section(string $title): void
{
    echo "\n$title\n";
}

// --- tooling -----------------------------------------------------------------

/** Chrome, wherever this machine keeps it. */
function findChrome(): ?string
{
    $candidates = [
        'C:\Program Files\Google\Chrome\Application\chrome.exe',
        'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
        '/usr/bin/google-chrome',
        '/usr/bin/chromium',
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    ];

    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return null;
}

function haveCommand(string $name): bool
{
    $which = stripos(PHP_OS_FAMILY, 'Windows') === 0 ? 'where' : 'which';
    exec(escapeshellarg($which) . ' ' . escapeshellarg($name) . ' 2>&1', $out, $code);

    return $code === 0;
}

$chrome = findChrome();

if (!$chrome || !haveCommand('pdftotext')) {
    echo "needs headless Chrome and pdftotext to read the printed pages\n";
    echo "  chrome: " . ($chrome ?: 'not found') . "\n";
    echo "  pdftotext: " . (haveCommand('pdftotext') ? 'found' : 'not found') . "\n";
    exit(0);
}

$work = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tp_layout_' . getmypid();
@mkdir($work, 0777, true);

register_shutdown_function(function () use ($work) {
    foreach (glob($work . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($work);
});

app('session.store')->start();
$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();

if (!$admin) {
    echo "no Super Admin to render as\n";
    exit(0);
}

Auth::guard('web')->login($admin);

/** The transcript as the browser would print it: one entry per page of text. */
function printedPages(string $chrome, string $work, int $studentId, int $enrollId, string $tag): ?array
{
    global $kernel;

    $request = Request::create("/admin/transcript/marksheet-download/$studentId", 'GET', ['enrollment_id' => $enrollId]);
    $request->setLaravelSession(app('session.store'));
    $response = $kernel->handle($request);

    if ($response->getStatusCode() !== 200) {
        return null;
    }

    $html = $work . DIRECTORY_SEPARATOR . $tag . '.html';
    $pdf = $work . DIRECTORY_SEPARATOR . $tag . '.pdf';
    file_put_contents($html, $response->getContent());

    $cmd = escapeshellarg($chrome) . ' --headless=new --disable-gpu --no-pdf-header-footer'
        . ' --print-to-pdf=' . escapeshellarg($pdf)
        . ' ' . escapeshellarg('file:///' . str_replace('\\', '/', $html)) . ' 2>&1';
    exec($cmd, $out, $code);

    if (!is_file($pdf)) {
        return null;
    }

    $pages = [];
    for ($i = 1; $i <= 10; $i++) {
        $txt = $work . DIRECTORY_SEPARATOR . $tag . ".p$i.txt";
        exec('pdftotext -layout -f ' . $i . ' -l ' . $i . ' '
            . escapeshellarg($pdf) . ' ' . escapeshellarg($txt) . ' 2>&1', $o, $c);

        if ($c !== 0 || !is_file($txt)) {
            break;
        }

        $content = (string) file_get_contents($txt);

        if (trim($content) === '') {
            break;
        }

        $pages[] = $content;
    }

    return $pages;
}

/**
 * Pages on which a semester was cut in half.
 *
 * Counted from the printed text, not from the CSS: each semester prints one
 * header and one "Semester GPA" line, so a page where those two counts differ
 * is a page holding part of a semester whose rest is elsewhere.
 */
function severedSemesters(array $pages): int
{
    $bad = 0;

    foreach ($pages as $text) {
        $headers = preg_match_all('/\(SECTION:/i', $text);
        $gpas = preg_match_all('/Semester\s*GPA\s*:/i', $text);

        if ($headers !== $gpas) {
            $bad++;
        }
    }

    return $bad;
}

/** Real transcripts, smallest and largest, found from the records. */
$candidates = [];

foreach (Student::with(['studentEnrolls.semester', 'studentEnrolls.subjects'])->limit(400)->get() as $student) {
    foreach ($student->studentEnrolls->groupBy('matricule') as $enrolls) {
        $courses = $enrolls->sum(fn ($e) => $e->subjects->count());

        if ($courses > 0) {
            $candidates[] = [
                'student' => $student->id,
                'enroll' => $enrolls->first()->id,
                'label' => $student->student_id,
                'semesters' => $enrolls->count(),
                'courses' => $courses,
            ];
        }
    }
}

if (count($candidates) < 2) {
    echo "not enough transcripts with marks to test a layout\n";
    exit(0);
}

usort($candidates, fn ($a, $b) => $a['courses'] <=> $b['courses']);

$smallest = $candidates[0];
$largest = $candidates[count($candidates) - 1];

// A mid-sized one too: two semesters, enough courses that they used to straddle
// the page break. This is the case the work was done for.
$mid = null;
foreach ($candidates as $c) {
    if ($c['semesters'] === 2 && $c['courses'] >= 25) {
        $mid = $c;
        break;
    }
}

echo 'smallest: ' . $smallest['label'] . ' (' . $smallest['semesters'] . ' sem, ' . $smallest['courses'] . " courses)\n";
if ($mid) {
    echo 'mid: ' . $mid['label'] . ' (' . $mid['semesters'] . ' sem, ' . $mid['courses'] . " courses)\n";
}
echo 'largest: ' . $largest['label'] . ' (' . $largest['semesters'] . ' sem, ' . $largest['courses'] . " courses)\n";

// ---------------------------------------------------------------------------
section('No semester is cut across a page break');
// ---------------------------------------------------------------------------
// Checked on the largest transcript above all: it is the one that must span
// pages, so it is the only one where keeping a semester intact can be observed
// doing any work.
$pages = printedPages($chrome, $work, $largest['student'], $largest['enroll'], 'largest');

if ($pages === null || $pages === []) {
    skip('could not print the largest transcript');
} else {
    check('the largest transcript does span more than one page',
        count($pages) > 1,
        'with only one page, keeping a semester whole proves nothing — '
            . count($pages) . ' page(s)');

    check('and no page holds a severed semester',
        severedSemesters($pages) === 0,
        severedSemesters($pages) . ' of ' . count($pages)
            . ' page(s) hold a semester whose GPA is on another sheet');
}

if ($mid) {
    $midPages = printedPages($chrome, $work, $mid['student'], $mid['enroll'], 'mid');

    if ($midPages === null || $midPages === []) {
        skip('could not print the mid-sized transcript');
    } else {
        check('a two-semester transcript keeps both semesters whole',
            severedSemesters($midPages) === 0,
            severedSemesters($midPages) . ' page(s) severed');

        // Both semesters and the totals they add up to belong on the sheet the
        // reader is looking at.
        check('and carries its marks and cumulative GPA on the first page',
            preg_match('/Cumulative\s*GPA/i', $midPages[0]) === 1
                && preg_match_all('/\(SECTION:/i', $midPages[0]) === $mid['semesters'],
            'page 1 shows ' . preg_match_all('/\(SECTION:/i', $midPages[0])
                . ' of ' . $mid['semesters'] . ' semesters, cumulative GPA '
                . (preg_match('/Cumulative\s*GPA/i', $midPages[0]) ? 'present' : 'absent'));
    }
} else {
    skip('no two-semester transcript with 25+ courses to check');
}

// ---------------------------------------------------------------------------
section('A short transcript is one sheet of paper');
// ---------------------------------------------------------------------------
$smallPages = printedPages($chrome, $work, $smallest['student'], $smallest['enroll'], 'smallest');

if ($smallPages === null || $smallPages === []) {
    skip('could not print the smallest transcript');
} else {
    check('it prints on a single page',
        count($smallPages) === 1,
        count($smallPages) . ' page(s) for ' . $smallest['courses'] . ' courses');

    // One page is only the right answer if the whole document is on it — a
    // transcript that fits by losing its signatures or its QR code is not a
    // transcript.
    check('with everything on it',
        count($smallPages) >= 1
            && preg_match('/Cumulative\s*GPA/i', $smallPages[0]) === 1
            && preg_match('/KEY TO THIS/i', $smallPages[0]) === 1
            && preg_match('/Verify this transcript/i', $smallPages[0]) === 1
            && preg_match('/END OF TRANSCRIPT/i', $smallPages[0]) === 1,
        'summary, key, verification and end marker must all still be there');
}

// ---------------------------------------------------------------------------
section('The record itself is unchanged');
// ---------------------------------------------------------------------------
// Density was bought by trimming margins and padding, never by dropping
// content: every course the student has marks for is still printed.
if (!$mid) {
    skip('no mid-sized transcript to count courses on');
} elseif (!isset($midPages) || !$midPages) {
    skip('the mid-sized transcript did not print');
} else {
    $allText = implode("\n", $midPages);
    $student = Student::with('studentEnrolls.subjects')->find($mid['student']);
    $codes = [];

    foreach ($student->studentEnrolls as $enroll) {
        if ($enroll->matricule !== optional(App\Models\StudentEnroll::find($mid['enroll']))->matricule) {
            continue;
        }
        foreach ($enroll->subjects as $subject) {
            $codes[] = $subject->code;
        }
    }

    $codes = array_values(array_unique(array_filter($codes)));

    if (!$codes) {
        skip('no course codes to look for');
    } else {
        $missing = [];
        foreach ($codes as $code) {
            // Spaces are collapsed: the print lays codes out in columns and the
            // watermark can land between characters.
            $needle = preg_quote(preg_replace('/\s+/', '', $code), '/');
            $haystack = preg_replace('/\s+/', '', $allText);

            if (!preg_match('/' . $needle . '/i', $haystack)) {
                $missing[] = $code;
            }
        }

        check('every course still appears on the printed transcript',
            $missing === [],
            count($missing) . ' missing: ' . implode(', ', array_slice($missing, 0, 6)));
    }
}

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";

exit($failed > 0 ? 1 : 0);
