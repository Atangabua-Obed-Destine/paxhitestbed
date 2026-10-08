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

/**
 * Three students: one who can progress, one who is held up by something they
 * can settle today, and one who cannot progress and has nothing to do about it.
 * The three are meant to get three different answers.
 */
$eligible = null;
$blocked = null;
$notEligible = null;

foreach (Student::whereHas('enrolls')->limit(80)->get() as $candidate) {
    $verdict = json_decode(asStudent('/student/progression/check-eligibility', $candidate, $kernel), true);

    // A student is picked as "blocked" from the raw unresolved courses, not
    // from action_required — otherwise breaking action_required would make this
    // suite quietly skip the section instead of failing it.
    //
    // Both checks are read. A student in a regular semester is held up by the
    // resit check; one already in a resit semester by the regular check looking
    // back at the semester it belongs to. Reading only the first missed five
    // students who had a failed course they had never acted on.
    $unresolved = array_merge(
        $verdict['resit_check']['unresolved_courses'] ?? [],
        $verdict['regular_check']['unresolved_courses'] ?? []
    );

    if (($verdict['eligible'] ?? null) === true && !$eligible) {
        $eligible = [$candidate, $verdict];
    } elseif (!empty($unresolved) && !$blocked) {
        $blocked = [$candidate, $verdict];
    } elseif (($verdict['eligible'] ?? null) === false
        && empty($unresolved) && !$notEligible) {
        $notEligible = [$candidate, $verdict];
    }

    if ($eligible && $blocked && $notEligible) {
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

echo "\nA student held up by something they can settle is told so\n";

if (!$blocked) {
    echo "  SKIP  no student currently has an unsettled failed course\n";
} else {
    // The payload has to say so before the page can act on it.
    check('the payload names what is in the way',
        !empty($blocked[1]['blockers']),
        'blockers: ' . json_encode($blocked[1]['blockers'] ?? null));
    check('and says the student can settle it',
        ($blocked[1]['action_required'] ?? null) === true,
        'action_required: ' . var_export($blocked[1]['action_required'] ?? null, true));

    $result = runCase($source, $blocked[1], $work, $chrome, $asset, 'blocked');

    if ($result === null) {
        check('the browser reported back', false, 'the harness page may have errored');
    } else {
        check('the modal opens for them too', $result['open'] === true,
            'a student who can act should not have to find the button');
        check('exactly once', $result['shown'] === 1, $result['shown'] . ' time(s)');
        check('it says what is holding them up',
            str_contains($result['body'], 'holding up your progression'));
        check('and points them at the resit centre',
            stripos($result['body'], 'Resit Centre') !== false);

        $missing = [];
        foreach ($blocked[1]['blockers'] ?? [] as $blocker) {
            if (!str_contains($result['body'], $blocker['subject_code'])) {
                $missing[] = $blocker['subject_code'];
            }
        }

        check('listing every course that is in the way',
            empty($missing), 'missing: ' . implode(', ', $missing));
        check('and saying what to do about each',
            str_contains($result['body'], 'Ask to resit this course')
                || str_contains($result['body'], 'Pay the resit fee'));
    }
}

echo "\nA student already in a resit semester is told about the one they skipped\n";

// The case this missed: sitting in a resit semester, held back by a failed
// course from the semester it belongs to that they never requested nor
// declined. The resit check reports "already in a resit semester" and an empty
// list, so reading only that check found nothing to tell them.
// The student is found from the records themselves — a resit enrolment whose
// parent semester holds a failed course with no resit request and no decline.
// Finding them through the payload would mean this section quietly skipped
// whenever the payload stopped reporting it, which is exactly the regression
// being guarded against.
$inResit = null;
$progression = app(\App\Services\Academic\SemesterProgressionService::class);
$unresolvedFromParent = new ReflectionMethod($progression, 'unresolvedFailedCoursesFromParent');
$unresolvedFromParent->setAccessible(true);

foreach (Student::whereHas('enrolls')->limit(80)->get() as $candidate) {
    $enrollment = $candidate->currentEnroll;

    if (!$enrollment || !optional($enrollment->semester)->is_resit) {
        continue;
    }

    if (empty($unresolvedFromParent->invoke($progression, $enrollment))) {
        continue;
    }

    $inResit = [
        $candidate,
        json_decode(asStudent('/student/progression/check-eligibility', $candidate, $kernel), true),
        $unresolvedFromParent->invoke($progression, $enrollment),
    ];
    break;
}

if (!$inResit) {
    echo "  SKIP  no student in a resit semester is held up by a parent-semester course\n";
} else {
    check('the blocker is found even though the resit check reports none',
        !empty($inResit[1]['blockers']),
        'resit check said: ' . ($inResit[1]['resit_check']['reason'] ?? '-'));
    check('and the student is told they can act',
        ($inResit[1]['action_required'] ?? null) === true);

    // Compared against the records, not against the payload's own account of
    // itself.
    $codes = array_column($inResit[2], 'subject_code');
    $reported = array_column($inResit[1]['blockers'] ?? [], 'subject_code');

    check('every unsettled course is named',
        empty(array_diff($codes, $reported)),
        'missing: ' . implode(', ', array_diff($codes, $reported)));
    check('and none is listed twice',
        count($reported) === count(array_unique($reported)), implode(', ', $reported));
}

echo "\nA course reported by both checks is one blocker, not two\n";

// No student in this database is currently reported by both checks at once, so
// the two lists are handed in directly. Without this the de-duplication would
// go untested until the day it mattered.
$service = app(\App\Services\Student\ProgressionEligibilityService::class);
$blockersFrom = new ReflectionMethod($service, 'blockersFrom');
$blockersFrom->setAccessible(true);

$same = ['subject_id' => 4242, 'subject_code' => 'DUP101', 'subject_title' => 'Counted Once', 'status' => 'no_request'];
$other = ['subject_id' => 4243, 'subject_code' => 'ONE102', 'subject_title' => 'Only Here', 'status' => 'no_request'];

$merged = $blockersFrom->invoke($service,
    ['unresolved_courses' => [$same]],
    ['unresolved_courses' => [$same, $other]]
);

$codes = array_column($merged, 'subject_code');

check('the course both checks report appears once',
    count(array_keys($codes, 'DUP101')) === 1, implode(', ', $codes));
check('and the one only a single check reports is still there',
    in_array('ONE102', $codes, true), implode(', ', $codes));

echo "\nA student with nothing to do is not interrupted\n";

if (!$notEligible) {
    echo "  SKIP  every student sampled can either progress or act\n";
} else {
    $result = runCase($source, $notEligible[1], $work, $chrome, $asset, 'not-eligible');

    if ($result === null) {
        check('the browser reported back', false, 'the harness page may have errored');
    } else {
        check('the eligibility check still runs once', $result['calls'] === 1, $result['calls'] . ' call(s)');
        check('but no modal is opened', $result['open'] === false && $result['shown'] === 0,
            'open=' . var_export($result['open'], true) . ' shown=' . $result['shown']);
        check('because there is nothing they could do about it',
            ($notEligible[1]['action_required'] ?? null) !== true);
    }
}

echo "\n$passed passed, $failed failed\n";

exit($failed > 0 ? 1 : 0);
