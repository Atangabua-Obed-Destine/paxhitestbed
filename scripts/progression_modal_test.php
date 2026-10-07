<?php
/**
 * The progression modal raising itself.
 *
 * A student who can move up should be told so when they open their portal,
 * rather than having to notice a button. The modal therefore opens on load for
 * an eligible student — and must stay out of the way of one who cannot progress
 * yet.
 *
 * The script under test is lifted out of the rendered portal page, so this
 * exercises what the browser is actually served rather than a copy of it. It is
 * then run in headless Chrome inside a small harness page, with the eligibility
 * call answered from the real payload: the whole portal page carries widgets
 * that never settle headlessly, and none of them is what is being tested.
 *
 * Nothing here writes.
 *
 * Usage: php scripts/progression_modal_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Student;

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
        echo "  FAIL  $label" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

$chrome = 'C:\Program Files\Google\Chrome\Application\chrome.exe';

if (!is_file($chrome)) {
    echo "  SKIP  headless Chrome is not installed at the expected path\n";
    exit(0);
}

$work = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'progression_modal_test';
if (!is_dir($work)) {
    mkdir($work, 0777, true);
}

$asset = fn (string $path) => 'file:///' . str_replace('\\', '/', __DIR__ . '/../public/' . $path);

/** Render a portal URL as the given student. */
function asStudent(string $path, Student $student, $kernel): string
{
    $request = Request::create($path, 'GET');
    $request->setLaravelSession(app('session.store'));
    Auth::guard('student')->login($student);

    return $kernel->handle($request)->getContent();
}

app('session.store')->start();

/** A student the portal says can progress, and one it says cannot. */
$eligible = null;
$notEligible = null;

foreach (Student::whereHas('enrolls')->limit(60)->get() as $candidate) {
    $verdict = json_decode(asStudent('/student/progression/check-eligibility', $candidate, $kernel), true);

    if (($verdict['eligible'] ?? null) === true && !$eligible) {
        $eligible = [$candidate, $verdict];
    } elseif (($verdict['eligible'] ?? null) === false && !$notEligible) {
        $notEligible = [$candidate, $verdict];
    }

    if ($eligible && $notEligible) {
        break;
    }
}

if (!$eligible) {
    echo "  SKIP  no student in this database is eligible to progress\n";
    exit(0);
}

/** The portal's own progression script, as served. */
$page = asStudent('/student', $eligible[0], $kernel);

if (!preg_match_all('/<script>([\s\S]*?)<\/script>/', $page, $blocks)) {
    check('the portal page carries scripts', false);
    exit(1);
}

$source = null;
foreach ($blocks[1] as $block) {
    if (str_contains($block, 'function checkProgressionEligibility')) {
        $source = $block;
        break;
    }
}

check('the progression script is present in the portal page', $source !== null);

if ($source === null) {
    echo "\n$passed passed, $failed failed\n";
    exit(1);
}

check('it opens the modal itself rather than only styling a button',
    str_contains($source, 'showProgressionModal'), 'no auto-open call found');

/**
 * Run the script in a harness and report what it did.
 *
 * @return array<string, mixed>|null
 */
function runCase(string $source, array $verdict, string $work, string $chrome, callable $asset, string $name): ?array
{
    $payload = json_encode($verdict, JSON_UNESCAPED_SLASHES);
    $jquery = $asset('dashboard/plugins/jquery/js/jquery.min.js');
    $bootstrap = $asset('dashboard/plugins/bootstrap/js/bootstrap.min.js');

    $html = <<<HTML
<!doctype html><html><head><meta charset="utf-8"><title>harness</title></head>
<body>
<ul><li id="progression-button-container" style="display:none"><a href="#" id="progression-button"></a></li></ul>
<div class="modal fade" id="manualProgressionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <div id="progressionModalHeader"></div>
    <div id="progressionModalBody"></div>
    <div id="progressionModalFooter" style="display:none"><button id="proceedProgressionBtn"></button></div>
  </div></div>
</div>
<script src="{$jquery}"></script>
<script src="{$bootstrap}"></script>
<script>
window.__eligibilityCalls = 0;
window.__modalShown = 0;
(function () {
    var realAjax = jQuery.ajax;
    jQuery.ajax = function (options) {
        if (options && typeof options.url === 'string' && options.url.indexOf('check-eligibility') !== -1) {
            window.__eligibilityCalls++;
            if (typeof options.success === 'function') { options.success({$payload}); }
            return { done: function () { return this; }, fail: function () { return this; } };
        }
        return realAjax.apply(this, arguments);
    };
    document.getElementById('manualProgressionModal')
        .addEventListener('shown.bs.modal', function () { window.__modalShown++; });
})();
</script>
<script>
{$source}
</script>
<script>
window.addEventListener('load', function () {
    setTimeout(function () {
        var modal = document.getElementById('manualProgressionModal');
        document.body.setAttribute('data-probe', JSON.stringify({
            calls: window.__eligibilityCalls,
            shown: window.__modalShown,
            open: modal.classList.contains('show'),
            body: (document.getElementById('progressionModalBody').textContent || '').replace(/\s+/g, ' ').trim()
        }));
    }, 900);
});
</script>
</body></html>
HTML;

    $file = $work . DIRECTORY_SEPARATOR . $name . '.html';
    file_put_contents($file, $html);

    $command = '"' . $chrome . '" --headless=new --disable-gpu --virtual-time-budget=6000 --dump-dom "file:///'
        . str_replace('\\', '/', $file) . '" 2>NUL';
    $dom = shell_exec($command);

    if (!preg_match('/data-probe="([^"]*)"/', (string) $dom, $match)) {
        return null;
    }

    return json_decode(html_entity_decode($match[1], ENT_QUOTES), true);
}

echo "\nA student who is ready to progress is shown the modal on load\n";

$result = runCase($source, $eligible[1], $work, $chrome, $asset, 'eligible');

if ($result === null) {
    check('the browser reported back', false, 'the harness page may have errored');
} else {
    check('the eligibility check runs once on load', $result['calls'] === 1, $result['calls'] . ' call(s)');
    check('the modal opens without being asked for', $result['open'] === true);
    check('exactly once', $result['shown'] === 1, $result['shown'] . ' time(s)');
    check('and it has been filled before it opens', strlen($result['body']) > 0);

    $carryOvers = $eligible[1]['carry_over_courses'] ?? [];

    if (empty($carryOvers)) {
        echo "  SKIP  that student has no carry-over courses to look for\n";
    } else {
        $missing = [];
        foreach ($carryOvers as $course) {
            if (!str_contains($result['body'], $course['subject_code'])) {
                $missing[] = $course['subject_code'];
            }
        }

        check('listing every carry-over course they still owe',
            empty($missing), 'missing: ' . implode(', ', $missing));
        check('and only those — the count matches',
            substr_count($result['body'], 'Carry-Over Courses') === 1);
    }

    // Progressing can move a student into a new academic year. Whether it does
    // is decided by SemesterProgressionService; the modal has to say so.
    $entersNew = $eligible[1]['enters_new_session'] ?? null;
    $targetSession = $eligible[1]['target_session_title'] ?? '';
    $currentSession = $eligible[1]['summary']['current_session'] ?? '';

    check('the payload says which session they are going into',
        $targetSession !== '', 'target_session_title missing');
    check('and whether that is a new one',
        is_bool($entersNew), var_export($entersNew, true));

    if ($targetSession !== '') {
        check('the modal names the session being progressed into',
            str_contains($result['body'], $targetSession), 'body does not mention ' . $targetSession);
    }

    if ($entersNew === true) {
        check('and says plainly that a new academic year is starting',
            str_contains($result['body'], 'new academic year'), 'no new-year notice in the body');
        check('naming both sessions, so the change is unmistakable',
            str_contains($result['body'], $currentSession) && str_contains($result['body'], $targetSession),
            'expected both ' . $currentSession . ' and ' . $targetSession);
    } elseif ($entersNew === false) {
        check('and does not claim a new year when the session is unchanged',
            !str_contains($result['body'], 'new academic year'));
    }
}

echo "\nWhat the student is promised is what progression does\n";

// The strongest form of the check: run the progression and see where the
// student actually lands. Rolled back, so nothing is kept.
\Illuminate\Support\Facades\DB::beginTransaction();

try {
    $enrollment = $eligible[0]->currentEnroll;
    $promisedSession = (int) ($eligible[1]['target_session_id'] ?? 0);
    $promisedSemester = (int) ($eligible[1]['target_semester_id'] ?? 0);

    $progression = app(\App\Services\Academic\SemesterProgressionService::class);
    $nextSemester = \App\Models\Semester::find($promisedSemester);

    // The rule itself, stated independently: where the institution has opened a
    // current academic session, that is the one a progressing student joins.
    // Sharing targetSessionFor() keeps the preview and the action agreeing with
    // each other; this is what stops them agreeing on the wrong answer.
    $institutionSession = \App\Models\Session::where('current', 1)->where('status', 1)->first();

    if ($institutionSession) {
        check('the session promised is the institution\'s current one',
            $promisedSession === (int) $institutionSession->id,
            'promised ' . $promisedSession . ', current session is ' . $institutionSession->id);
        check('and it is flagged as new when they are not already in it',
            ($eligible[1]['enters_new_session'] ?? null)
                === ((int) $institutionSession->id !== (int) $enrollment->session_id),
            'flag says ' . var_export($eligible[1]['enters_new_session'] ?? null, true));
    } else {
        echo "  SKIP  no current academic session is marked, so there is no rule to check against\n";
    }

    if (!$nextSemester) {
        echo "  SKIP  the promised semester could not be loaded\n";
    } else {
        $created = $progression->progressToNextSemester($enrollment, $nextSemester);

        if (!$created instanceof \App\Models\StudentEnroll) {
            check('progression produced an enrolment', false, 'it returned ' . gettype($created));
        } else {
            check('the student lands in the session the modal named',
                (int) $created->session_id === $promisedSession,
                'landed in ' . $created->session_id . ', was promised ' . $promisedSession);
            check('and in the semester it named',
                (int) $created->semester_id === $promisedSemester,
                'landed in ' . $created->semester_id . ', was promised ' . $promisedSemester);
        }
    }
} finally {
    \Illuminate\Support\Facades\DB::rollBack();
}

echo "\nProgressing within the same academic year says nothing about a new one\n";

// Every eligible student in this database happens to be moving into a new
// session, so the other branch is exercised by presenting the same student as
// staying put. The payload is what the modal reads; nothing else changes.
$sameSession = $eligible[1];
$sameSession['enters_new_session'] = false;
$sameSession['target_session_title'] = $sameSession['summary']['current_session'] ?? '2025/2026';
$sameSession['summary']['enters_new_session'] = false;
$sameSession['summary']['target_session'] = $sameSession['target_session_title'];

$result = runCase($source, $sameSession, $work, $chrome, $asset, 'same-session');

if ($result === null) {
    check('the browser reported back', false, 'the harness page may have errored');
} else {
    check('the modal still opens', $result['open'] === true);
    check('it still names the session', str_contains($result['body'], $sameSession['target_session_title']));
    check('but claims no new academic year',
        !str_contains($result['body'], 'new academic year'), 'the notice appeared anyway');
}

echo "\nA student who cannot progress yet is not interrupted\n";

if (!$notEligible) {
    echo "  SKIP  every student sampled is eligible, so there is no case to run\n";
} else {
    $result = runCase($source, $notEligible[1], $work, $chrome, $asset, 'not-eligible');

    if ($result === null) {
        check('the browser reported back', false, 'the harness page may have errored');
    } else {
        check('the eligibility check still runs once', $result['calls'] === 1, $result['calls'] . ' call(s)');
        check('but no modal is opened', $result['open'] === false && $result['shown'] === 0,
            'open=' . var_export($result['open'], true) . ' shown=' . $result['shown']);
    }
}

echo "\n$passed passed, $failed failed\n";

exit($failed > 0 ? 1 : 0);
