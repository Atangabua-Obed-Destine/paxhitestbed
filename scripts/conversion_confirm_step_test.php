<?php

/**
 * The confirmation step before an application becomes a student.
 *
 * "Create student record" spends a matricule, enrols the student on whatever
 * six dropdowns happened to be showing, raises the programme's fees and emails
 * the student their login — all on one click, and none of it recoverable from
 * that screen. The details that decide it all default to something plausible,
 * which is exactly how a wrong batch or a wrong programme gets through.
 *
 * So the choices are read back in words, and the administrator has to agree to
 * them. These checks drive the real page in a real browser, because the whole
 * feature is browser behaviour: asserting that the markup exists would pass
 * even if the button submitted straight through.
 *
 * Nothing is submitted and nothing is written.
 *
 *   php scripts/conversion_confirm_step_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Application;
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

function findChrome(): ?string
{
    foreach ([
        'C:\Program Files\Google\Chrome\Application\chrome.exe',
        'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
        '/usr/bin/google-chrome',
        '/usr/bin/chromium',
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    ] as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return null;
}

$chrome = findChrome();

if (!$chrome) {
    echo "needs headless Chrome: this is browser behaviour, and markup alone proves nothing\n";
    exit(0);
}

app('session.store')->start();
$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();

if (!$admin) {
    echo "no Super Admin to render as\n";
    exit(0);
}

Auth::guard('web')->login($admin);

// An application that has not been converted, so the modal is the real one an
// administrator would meet rather than a page already showing a student.
$application = Application::get()->first(
    fn ($candidate) => !Student::where('registration_no', $candidate->registration_no)->exists()
) ?? Application::first();

if (!$application) {
    echo "no applications to work with\n";
    exit(0);
}

echo 'application ' . $application->id . ' — '
    . trim($application->first_name . ' ' . $application->last_name) . "\n";

$request = Request::create('/admin/admission/application/' . $application->id . '/edit', 'GET');
$request->setLaravelSession(app('session.store'));
$response = $kernel->handle($request);

if ($response->getStatusCode() !== 200) {
    echo "the application page did not render (status " . $response->getStatusCode() . ")\n";
    exit(1);
}

// Served from the application's own public directory so its stylesheets and
// scripts resolve exactly as they do for a real administrator. Removed again
// however this script ends: a stray page in a live web root is nobody's idea
// of a test artefact.
$probeName = '_conversion_confirm_probe_' . getmypid() . '.html';
$probePath = public_path($probeName);

register_shutdown_function(function () use ($probePath) {
    @unlink($probePath);
});

$harness = <<<'HTML'
<script>
window.addEventListener('load', function () {
  setTimeout(function () {
    var out = [];
    function note(k, v) { out.push(k + '=' + v); }
    function finish() {
      document.title = 'PROBE ' + out.join('|');
      document.body.insertAdjacentHTML('afterbegin', '<div>' + document.title + '</div>');
    }

    var review  = document.getElementById('convert-review-btn');
    var confirm = document.getElementById('convert-confirm-btn');
    var back    = document.getElementById('convert-back-btn');
    var step    = document.getElementById('convert-confirm-step');
    var form    = document.getElementById('convert-application-form');
    var body    = form ? form.querySelector('.modal-body') : null;

    if (!review || !confirm || !step || !form) { document.title = 'PROBE missing=1'; return; }

    note('submitButtonsInForm', form.querySelectorAll('button[type="submit"]').length);
    note('startsDisabled', confirm.disabled);
    note('startsHidden', step.classList.contains('d-none'));

    // The gate: a required field left empty must not reach the confirmation.
    var semester = document.getElementById('convert_semester');
    semester.innerHTML = '<option value="">select</option>';
    review.click();
    note('refusesWhileIncomplete', step.classList.contains('d-none'));

    function fill(id, value, text) {
      var field = document.getElementById(id);
      if (!field) { return; }
      if (field.tagName === 'SELECT') {
        if (!field.querySelector('option[value="' + value + '"]')) {
          var option = document.createElement('option');
          option.value = value; option.text = text;
          field.appendChild(option);
        }
        field.value = value;
      } else { field.value = value; }
    }

    fill('convert_session', '__SESSION__', 'PROBE-SESSION');
    fill('convert_semester', '__SEMESTER__', 'PROBE-SEMESTER');
    fill('convert_section', '__SECTION__', 'PROBE-SECTION');

    review.click();

    setTimeout(function () {
      note('confirmShown', !step.classList.contains('d-none'));
      var tick = document.getElementById('convert-confirm-check');
      note('hasTick', !!tick);
      note('tickStartsUnticked', !!tick && !tick.checked);
      note('disabledUntilTicked', confirm.disabled);
      if (tick) {
        tick.checked = true;
        tick.dispatchEvent(new Event('change'));
      }
      note('enabledAfterTick', !confirm.disabled);
      note('formHidden', body ? body.classList.contains('d-none') : 'na');
      note('confirmEnabled', !confirm.disabled);
      note('backShown', !back.classList.contains('d-none'));
      note('reviewHidden', review.classList.contains('d-none'));
      note('summaryRows', step.querySelectorAll('#convert-confirm-table tbody tr').length);

      var text = step.textContent.replace(/\s+/g, ' ');
      note('showsSession', /PROBE-SESSION/.test(text));
      note('showsSemester', /PROBE-SEMESTER/.test(text));
      note('showsSection', /PROBE-SECTION/.test(text));
      note('saysIrreversible', /cannot be undone/i.test(text));
      note('saysMatricule', /spent for good/i.test(text));
      note('saysEmails', /cannot be recalled/i.test(text));
      note('saysFees', /fees/i.test(text));

      back.click();
      setTimeout(function () {
        note('backReturns', body ? !body.classList.contains('d-none') : 'na');
        note('disabledAgain', confirm.disabled);

        // A programme the applicant never applied for is the mistake this
        // screen exists to catch, so it must be called out by name.
        fill('convert_program', '99999123', 'PROBE-UNREQUESTED-PROGRAMME');
        review.click();

        setTimeout(function () {
          var warn = document.getElementById('convert-confirm-warnings');
          note('warnsUnrequested', !!(warn && /not one the applicant applied for/i.test(warn.textContent || '')));
          finish();
        }, 300);
      }, 250);
    }, 400);
  }, 1500);
});
</script>
HTML;

$sessionId = optional(App\Models\Session::where('status', 1)->latest('id')->first())->id ?: 1;
$semesterId = optional(App\Models\Semester::where('is_resit', 0)->orderBy('id')->first())->id ?: 1;
$sectionId = optional(App\Models\Section::first())->id ?: 1;

$harness = str_replace(
    ['__SESSION__', '__SEMESTER__', '__SECTION__'],
    [$sessionId, $semesterId, $sectionId],
    $harness
);

file_put_contents($probePath, str_replace('</body>', $harness . '</body>', $response->getContent()));

$url = rtrim(config('app.url'), '/') . '/' . $probeName;
$cmd = escapeshellarg($chrome) . ' --headless=new --disable-gpu --virtual-time-budget=12000 --dump-dom '
    . escapeshellarg($url) . ' 2>&1';
exec($cmd, $lines);
$dom = implode("\n", $lines);

if (!preg_match('/PROBE ([^<]*)/', $dom, $match)) {
    // Two causes, worth telling apart: either the page is not being served,
    // or the submit button went straight through and navigated away before the
    // harness could report - which is the very failure this suite exists to
    // catch, so it is named first.
    echo "the browser never reported back.
";
    echo "  Either the form submitted without stopping at the confirmation,
";
    echo "  or the site is not served at " . config('app.url') . "
";
    exit(1);
}

$result = [];
foreach (explode('|', trim($match[1])) as $pair) {
    [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
    $result[trim($key)] = trim($value);
}

$is = fn (string $key, string $expected = 'true') => ($result[$key] ?? null) === $expected;

// ---------------------------------------------------------------------------
section('Nothing can be submitted without passing the confirmation');
// ---------------------------------------------------------------------------
check('the form has exactly one submit button',
    ($result['submitButtonsInForm'] ?? null) === '1',
    'found ' . ($result['submitButtonsInForm'] ?? '?') . '; a second one would be a way past the warning');

check('it is disabled before the details are confirmed',
    $is('startsDisabled'),
    'a disabled submit is also what stops Enter in a field submitting the form');

check('the confirmation panel starts hidden', $is('startsHidden'));

check('an incomplete form does not reach the confirmation',
    $is('refusesWhileIncomplete'),
    'confirming details that will be refused on submit wastes the admin\'s time');

// ---------------------------------------------------------------------------
section('The lists are ready before the modal is opened');
// ---------------------------------------------------------------------------
// Session, semester and section are filled by the shared filter script, which
// only reacts when the programme select CHANGES. The programme is already
// chosen server-side, so nothing changed and the lists stayed empty: the modal
// opened blank the first time and was only populated by the time it was opened
// again. They are now asked for while the page loads.
check('the page defines a loader for the dependent lists',
    str_contains($response->getContent(), 'window.loadConversionLists'),
    'without one, nothing ever pokes the shared filter script');

check('and calls it on page load, not on modal open',
    (bool) preg_match('/\$\(function \(\) \{\s*if \(typeof window\.loadConversionLists/', $response->getContent()),
    'waiting for the modal is what made the first open blank');

check('the loader is placed after the shared filter script',
    strpos($response->getContent(), 'window.loadConversionLists();')
        > strpos($response->getContent(), 'filter-session'),
    'its change handler has to be bound before anything triggers it');

// Scoped to this view's own source: the rendered page carries shared scripts
// with timers of their own, and failing on those would say nothing about this.
$conversionView = file_get_contents(resource_path('views/admin/application/edit.blade.php'));

check('the modal no longer defers the cascade behind a timer',
    !str_contains($conversionView, "\$program.trigger('change');
        }, 300);"),
    'whether the lists appeared depended on how fast three requests came back');

// ---------------------------------------------------------------------------
section('The confirmation reads the choices back');
// ---------------------------------------------------------------------------
check('pressing submit shows the confirmation instead of saving', $is('confirmShown'));
check('and hides the form behind it', $is('formHidden'));
check('the review button gives way to the confirm button', $is('reviewHidden') && $is('backShown'));
check('the confirmation asks for a deliberate tick',
    $is('hasTick') && $is('tickStartsUnticked'),
    'the money screens ask the same way; this one let a second click through');

check('the confirm button is dead until it is ticked',
    $is('disabledUntilTicked'),
    'otherwise it is just a second click in the same place as the first');

check('and live once it is', $is('enabledAfterTick'));

check('every enrolment detail is listed',
    (int) ($result['summaryRows'] ?? 0) >= 10,
    ($result['summaryRows'] ?? 0) . ' rows — batch, faculty, programme, session, semester, section and the rest');

check('the session chosen is shown back', $is('showsSession'));
check('the semester chosen is shown back', $is('showsSemester'));
check('the section chosen is shown back', $is('showsSection'));

// ---------------------------------------------------------------------------
section('It says what cannot be taken back');
// ---------------------------------------------------------------------------
check('it warns the step cannot be undone here', $is('saysIrreversible'));
check('that the matricule is spent for good', $is('saysMatricule'),
    'deleting the student afterwards does not give the number back');
check('that the emails cannot be recalled', $is('saysEmails'));
check('and that fees are raised', $is('saysFees'));

// ---------------------------------------------------------------------------
section('Second thoughts are allowed');
// ---------------------------------------------------------------------------
check('going back returns to the details', $is('backReturns'));
check('and disarms the confirm button again',
    $is('disabledAgain'),
    'leaving it live behind the form is a way to submit without having read anything');

// ---------------------------------------------------------------------------
section('It questions a programme the applicant never asked for');
// ---------------------------------------------------------------------------
check('choosing a programme outside the applicant\'s choices is called out',
    $is('warnsUnrequested'),
    'admitting someone to a course they did not apply for is the mistake worth catching');

// ---------------------------------------------------------------------------
section('Nothing was left behind');
// ---------------------------------------------------------------------------
check('no student was created by looking at the page',
    !Student::where('registration_no', $application->registration_no)->exists()
        || $application->wasRecentlyCreated === false,
    'this suite must never convert anybody');

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";

exit($failed > 0 ? 1 : 0);
