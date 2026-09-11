<?php
/**
 * An admin can correct an applicant's details and save.
 *
 * The bug this guards: the admin edit validated the email as unique across
 * applications, ignoring only the row being edited. One person legitimately
 * applies more than once — a different intake, a different programme — and the
 * applicants' own form has never enforced uniqueness, which is why such records
 * exist. So the moment somebody had applied twice, every one of their
 * applications became unsaveable from the admin screen, which is the one screen
 * that exists to correct them.
 *
 * The column's index is deliberately non-unique, so the rule contradicted the
 * schema as well as the applicant form.
 *
 * Every save runs inside a transaction that is rolled back.
 *
 * Usage: php scripts/application_edit_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Application;
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

Auth::guard('web')->login(App\User::where('is_admin', 1)->firstOrFail());
$kernel = app(Illuminate\Contracts\Http\Kernel::class);

/** Rebuild the payload the browser would post from the rendered form. */
function payloadFrom(string $html, string $token): array
{
    $payload = [];

    preg_match_all('/<input\b([^>]*)>/i', $html, $inputs);
    foreach ($inputs[1] as $attrs) {
        if (!preg_match('/\bname="([^"]+)"/i', $attrs, $n)) {
            continue;
        }
        $type = preg_match('/\btype="([^"]+)"/i', $attrs, $t) ? strtolower($t[1]) : 'text';
        $value = preg_match('/\bvalue="([^"]*)"/i', $attrs, $v) ? html_entity_decode($v[1]) : '';

        if ($type === 'checkbox' || $type === 'radio') {
            if (stripos($attrs, 'checked') !== false) {
                $payload[$n[1]] = $value !== '' ? $value : '1';
            }
            continue;
        }
        if ($type === 'file') {
            continue;
        }
        $payload[$n[1]] = $value;
    }

    preg_match_all('/<select\b([^>]*)>(.*?)<\/select>/is', $html, $selects, PREG_SET_ORDER);
    foreach ($selects as $sel) {
        if (!preg_match('/\bname="([^"]+)"/i', $sel[1], $n)) {
            continue;
        }
        $payload[$n[1]] = preg_match('/<option\b[^>]*\bselected\b[^>]*\bvalue="([^"]*)"/i', $sel[2], $o)
            || preg_match('/<option\b[^>]*\bvalue="([^"]*)"[^>]*\bselected\b/i', $sel[2], $o)
                ? html_entity_decode($o[1])
                : '';
    }

    preg_match_all('/<textarea\b([^>]*)>(.*?)<\/textarea>/is', $html, $areas, PREG_SET_ORDER);
    foreach ($areas as $area) {
        if (preg_match('/\bname="([^"]+)"/i', $area[1], $n)) {
            $payload[$n[1]] = html_entity_decode(strip_tags($area[2]));
        }
    }

    $payload['_token'] = $token;
    $payload['_method'] = 'PUT';

    return $payload;
}

/**
 * Save an application through the admin screen and report the errors, if any.
 *
 * The session is flushed first: flashed validation errors survive one further
 * request, so without this each application inherits the previous one's
 * failures — which is exactly how an earlier version of this harness reported
 * false refusals.
 */
function saveApplication(int $id): array
{
    $kernel = app(Illuminate\Contracts\Http\Kernel::class);

    app('session.store')->flush();
    app('session.store')->regenerateToken();

    $get = Illuminate\Http\Request::create("/admin/admission/application/{$id}/edit", 'GET');
    $get->setLaravelSession(app('session.store'));
    $html = $kernel->handle($get)->getContent();

    $token = app('session.store')->token();
    app('session.store')->forget('errors');

    $post = Illuminate\Http\Request::create(
        "/admin/admission/application/{$id}",
        'POST',
        payloadFrom($html, $token)
    );
    $post->setLaravelSession(app('session.store'));
    $response = $kernel->handle($post);

    $errors = app('session.store')->get('errors');
    $messages = $errors ? array_keys($errors->getBag('default')->getMessages()) : [];

    app('session.store')->forget('errors');

    return ['status' => $response->getStatusCode(), 'errors' => $messages];
}

echo "\n== The email is not unique, because applying twice is normal ==\n";

// The schema says so too; a validation rule contradicting it was the bug.
$emailIndexIsUnique = collect(DB::select('SHOW INDEX FROM applications'))
    ->contains(fn ($i) => $i->Column_name === 'email' && $i->Non_unique == 0);

check('the database does not require a unique email', !$emailIndexIsUnique);

$repeated = DB::table('applications')->select('email')
    ->groupBy('email')->havingRaw('COUNT(*) > 1')->pluck('email');

if ($repeated->isEmpty()) {
    echo "  SKIP  nobody in this database has applied twice\n";
} else {
    $shared = Application::where('email', $repeated->first())->orderBy('id')->pluck('id');

    check('at least one applicant has more than one application',
        $shared->count() > 1, $shared->count() . ' applications share an email');

    // Every one of them has to be editable. Before the fix each collided with
    // the others and none could be saved.
    $refused = [];

    foreach ($shared as $id) {
        DB::beginTransaction();

        try {
            $result = saveApplication($id);

            if (in_array('email', $result['errors'], true)) {
                $refused[] = $id;
            }
        } finally {
            DB::rollBack();
        }
    }

    check('none of them is refused for a duplicate email', $refused === [],
        'refused: ' . implode(', ', $refused));
}

echo "\n== A completed application can be corrected and saved ==\n";

$completed = Application::whereIn('stage', ['submitted', 'decision_approved'])
    ->whereNotNull('gender')->whereNotNull('dob')
    ->orderBy('id', 'desc')->limit(8)->pluck('id');

if ($completed->isEmpty()) {
    echo "  SKIP  no completed application to edit\n";
} else {
    $refused = [];

    foreach ($completed as $id) {
        DB::beginTransaction();

        try {
            $result = saveApplication($id);

            if ($result['errors'] !== []) {
                $refused[$id] = $result['errors'];
            }
        } finally {
            DB::rollBack();
        }
    }

    check('every completed application saves', $refused === [],
        collect($refused)->map(fn ($e, $id) => "#{$id}: " . implode(', ', $e))->implode(' | '));
}

echo "\n== A correction actually persists ==\n";

$target = Application::whereIn('stage', ['submitted', 'decision_approved'])
    ->whereNotNull('gender')->orderBy('id', 'desc')->first();

if (!$target) {
    echo "  SKIP  nothing to correct\n";
} else {
    DB::beginTransaction();

    try {
        $was = $target->alternate_phone;

        app('session.store')->flush();
        app('session.store')->regenerateToken();

        $get = Illuminate\Http\Request::create("/admin/admission/application/{$target->id}/edit", 'GET');
        $get->setLaravelSession(app('session.store'));
        $html = $kernel->handle($get)->getContent();

        $payload = payloadFrom($html, app('session.store')->token());
        $payload['alternate_phone'] = '699000111';

        $post = Illuminate\Http\Request::create(
            "/admin/admission/application/{$target->id}",
            'POST',
            $payload
        );
        $post->setLaravelSession(app('session.store'));
        $kernel->handle($post);

        $target->refresh();

        check('the edited value is written', $target->alternate_phone === '699000111',
            'stored: ' . var_export($target->alternate_phone, true));
        check('it was different beforehand', $was !== '699000111');
    } finally {
        DB::rollBack();
    }
}

echo "\n== The admin form matches the applicant form ==\n";

$adminSource = file_get_contents(__DIR__ . '/../app/Http/Controllers/Admin/ApplicationController.php');
$publicSource = file_get_contents(__DIR__ . '/../app/Http/Controllers/Web/ApplicationController.php');

// The applicant form never validated the email as unique. The admin doing so
// was the divergence, and it is the one that has to stay closed.
check('neither form requires a unique application email',
    !str_contains($adminSource, "Rule::unique('applications', 'email')")
        && !str_contains($publicSource, "Rule::unique('applications', 'email')"));

// Both read requiredness from the same degree-type configuration, so a field
// switched off for a degree type is not demanded by either screen.
foreach (['birth_city', 'birth_division', 'birth_region', 'birth_country', 'academic_year'] as $field) {
    check("both forms take {$field} from the form configuration",
        str_contains($adminSource, "\$rules['{$field}']")
            && str_contains($publicSource, "\$rules['{$field}']"));
}

echo "\n== An incomplete draft is still refused, deliberately ==\n";

// The admin screen validates a complete application. A draft the applicant
// never finished has genuinely empty required fields, and saving it as though
// it were complete would be worse than refusing.
$emptyDraft = Application::where('stage', 'draft')->whereNull('gender')->first();

if (!$emptyDraft) {
    echo "  SKIP  no incomplete draft in this database\n";
} else {
    DB::beginTransaction();

    try {
        $result = saveApplication($emptyDraft->id);

        check('an unfinished draft is refused rather than half-saved',
            in_array('gender', $result['errors'], true),
            'errors: ' . implode(', ', $result['errors']));
        check('and the refusal names what is missing', $result['errors'] !== []);
    } finally {
        DB::rollBack();
    }
}

echo "\n== Saving keeps the admin on the form, and says so ==\n";

/**
 * Save through the screen and follow where it sends the admin.
 *
 * The session is NOT flushed between the save and the page it lands on: the
 * flash that carries "saved" survives exactly one further request, and that
 * request is the point of this test.
 */
function saveAndFollow(int $id, ?callable $tamper = null): array
{
    $kernel = app(Illuminate\Contracts\Http\Kernel::class);
    $session = app('session.store');

    $session->flush();
    $session->regenerateToken();

    $get = Illuminate\Http\Request::create("/admin/admission/application/{$id}/edit", 'GET');
    $get->setLaravelSession($session);
    $html = $kernel->handle($get)->getContent();

    $payload = payloadFrom($html, $session->token());

    if ($tamper) {
        $payload = $tamper($payload);
    }

    $post = Illuminate\Http\Request::create("/admin/admission/application/{$id}", 'POST', $payload);
    $post->setLaravelSession($session);
    $response = $kernel->handle($post);

    $location = (string) $response->headers->get('location');

    $follow = Illuminate\Http\Request::create($location ?: "/admin/admission/application/{$id}/edit", 'GET');
    $follow->setLaravelSession($session);

    return ['location' => $location, 'page' => $kernel->handle($follow)->getContent()];
}

$target = Application::whereIn('stage', ['submitted', 'decision_approved'])
    ->whereNotNull('gender')->whereNotNull('dob')->orderBy('id', 'desc')->first();

if (!$target) {
    echo "  SKIP  no completed application to save\n";
} else {
    DB::beginTransaction();

    try {
        $result = saveAndFollow($target->id);

        check('a save returns to the edit form, not the read-only screen',
            str_ends_with($result['location'], "/application/{$target->id}/edit"), $result['location']);
        check('and the form says plainly that it saved', str_contains($result['page'], 'Saved.'));
        check('with the time it happened',
            (bool) preg_match('/saved at \d{1,2}:\d{2}\s?(AM|PM)/i', $result['page']));
        check('and a way through to the application itself', str_contains($result['page'], 'View the application'));

        // The toast and the panel do different jobs: the toast catches the eye,
        // the panel is still there a minute later.
        check('the toast appears on the same page', str_contains($result['page'], __('msg_updated_successfully')));
        check('and it is actually wired up, not just text', str_contains($result['page'], 'flasher') && str_contains($result['page'], 'toastr'));
    } finally {
        DB::rollBack();
    }

    echo "\n== A rejected save says what was wrong, on the form ==\n";

    DB::beginTransaction();

    try {
        $before = $target->fresh()->first_name;

        // first_name is required, so an empty one is refused.
        $result = saveAndFollow($target->id, function (array $payload) {
            $payload['first_name'] = '';

            return $payload;
        });

        check('it goes back to the edit form', str_contains($result['location'], "/application/{$target->id}/edit"),
            $result['location']);
        check('the form says nothing was saved', str_contains($result['page'], 'Nothing was saved.'));
        check('and lists what to correct', str_contains($result['page'], 'Please correct the following'));
        check('naming the field', stripos($result['page'], 'first name') !== false);
        check('a toast says so too, as it does on success',
            str_contains($result['page'], 'Nothing was saved. Please check the details marked on the form.'));
        check('and the record is unchanged', $target->fresh()->first_name === $before);
    } finally {
        DB::rollBack();
    }
}

echo "\n== The conversion modal picks the obvious semester and section ==\n";

// An admin admitting a student was choosing "FIRST SEMESTER - Y1" and the only
// section there is, by hand, every time. Both selects now carry a default that
// applies when nothing has been chosen. The selection itself happens in the
// browser, so what is asserted here is that the ingredients are right: the
// attributes are on the selects, the shared script honours them only when
// asked, and the rule lands on the intended rows for a real programme.

$modal = file_get_contents(__DIR__ . '/../resources/views/admin/application/edit.blade.php');

check('the semester select asks for the first teaching semester',
    preg_match('/id="convert_semester"[^>]*data-default="first-non-resit"/', $modal) === 1);
check('the section select asks for the All section',
    preg_match('/id="convert_section"[^>]*data-default="all"/', $modal) === 1);
check('an explicit choice still wins, because data-selected is kept',
    preg_match('/id="convert_semester"[^>]*data-selected=/', $modal) === 1
    && preg_match('/id="convert_section"[^>]*data-selected=/', $modal) === 1);

$shared = file_get_contents(__DIR__ . '/../resources/views/common/js/batch_filter.blade.php');

check('the shared filter script applies a default only when one is asked for',
    str_contains($shared, "data('default') === 'first-non-resit'")
    && str_contains($shared, "data('default') === 'all'"));
check('and only when nothing was already selected',
    // Both defaults sit in the else branch of the data-selected test.
    substr_count($shared, 'else if($semester.data(\'default\')') === 1
    && substr_count($shared, 'else if($section.data(\'default\')') === 1);

// No other screen opted in, so none of them changes behaviour. glob() does not
// recurse on '**', so this walks the tree — scanning a handful of views and
// finding nothing would prove nothing.
$views = [];

foreach (new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__ . '/../resources/views', RecursiveDirectoryIterator::SKIP_DOTS)
) as $file) {
    if (str_ends_with($file->getFilename(), '.blade.php')) {
        $views[] = $file->getPathname();
    }
}

check('every view was searched', count($views) > 500, count($views) . ' views');

$optedIn = [];

foreach ($views as $file) {
    // On a real select, not merely mentioned in a comment.
    if (preg_match('/<select[^>]*data-default="/', (string) file_get_contents($file))) {
        $optedIn[] = str_replace('\\', '/', substr($file, strpos($file, 'views') + 6));
    }
}

check('only the conversion modal opts in',
    $optedIn === ['admin/application/edit.blade.php'], implode(', ', $optedIn));

// The rule itself, run against the data the browser is given.
$application = Application::whereNotNull('program_id')->orderBy('id', 'desc')->first();

if ($application) {
    $session = app('session.store');
    $session->start();

    $ask = function (string $url, array $payload) use ($kernel, $session) {
        $payload['_token'] = $session->token();
        $request = Illuminate\Http\Request::create($url, 'POST', $payload);
        $request->setLaravelSession($session);

        return json_decode($kernel->handle($request)->getContent(), true);
    };

    $semesters = (array) $ask('/filter-semester', ['program' => $application->program_id]);

    check('the semester list tells the browser which are resits',
        $semesters !== [] && array_key_exists('is_resit', $semesters[0]),
        implode(',', array_keys($semesters[0] ?? [])));

    $teaching = array_values(array_filter($semesters, fn ($s) => empty($s['is_resit'])));
    usort($teaching, fn ($a, $b) => ((int) $a['year'] <=> (int) $b['year']) ?: ($a['id'] <=> $b['id']));

    check('the rule lands on a teaching semester, not a resit',
        $teaching !== [] && empty($teaching[0]['is_resit']),
        $teaching[0]['title'] ?? 'none');
    check('and it is the first one',
        ($teaching[0]['title'] ?? '') === 'FIRST SEMESTER - Y1',
        $teaching[0]['title'] ?? 'none');

    if ($teaching) {
        $sections = (array) $ask('/filter-section', [
            'program' => $application->program_id,
            'semester' => $teaching[0]['id'],
        ]);

        $all = array_values(array_filter($sections, fn ($s) => strtolower(trim($s['title'])) === 'all'));

        check('the rule finds the All section',
            $all !== [] || count($sections) === 1,
            implode(', ', array_column($sections, 'title')));
    }
}

echo "\n== The conversion modal shows what the applicant asked for ==\n";

// An admin converting an application sees one programme in the modal — the
// first choice. Whether to admit to a second or third choice instead was a
// decision they had to leave the screen to inform themselves about.

$render = function (int $id) use ($kernel) {
    $request = Illuminate\Http\Request::create('/admin/admission/application/' . $id . '/edit', 'GET');
    $request->setLaravelSession(app('session.store'));

    return $kernel->handle($request)->getContent();
};

$choiceRows = function (string $html) {
    preg_match_all('/<li class="choice-row[^>]*data-program="(\d+)"[\s\S]*?<\/li>/', $html, $m);

    return $m;
};

$threeChoices = DB::table('applications')->whereNotNull('third_program_choice_id')->orderBy('id', 'desc')->first();

if ($threeChoices) {
    $html = $render($threeChoices->id);
    $rows = $choiceRows($html);

    check('all three choices are listed', count($rows[0]) === 3, count($rows[0]) . ' rows');
    check('and they are the applicant\'s own choices, in order',
        $rows[1] === [
            (string) $threeChoices->first_program_choice_id,
            (string) $threeChoices->second_program_choice_id,
            (string) $threeChoices->third_program_choice_id,
        ],
        implode(',', $rows[1]));

    $text = preg_replace('/\s+/', ' ', strip_tags($rows[0][1] ?? ''));

    check('the second choice is labelled as such', str_contains($text, '2nd choice'), $text);
    check('and names its faculty, since the programme list is filtered by it',
        preg_match('/data-faculty="\d+"/', $rows[0][1] ?? '') === 1);

    // The programme titles must be real, not ids.
    $second = DB::table('programs')->where('id', $threeChoices->second_program_choice_id)->first();
    check('the second choice is named, not numbered',
        $second && str_contains($rows[0][1], $second->title), $second->title ?? '?');
}

$firstOnly = DB::table('applications')->whereNotNull('first_program_choice_id')
    ->whereNull('second_program_choice_id')->orderBy('id', 'desc')->first();

if ($firstOnly) {
    $html = $render($firstOnly->id);

    check('an applicant with one choice shows one row',
        count($choiceRows($html)[0]) === 1);
    check('and is told there were no others',
        str_contains($html, 'The applicant gave no other choices.'));
}

$noChoices = DB::table('applications')->whereNull('first_program_choice_id')
    ->whereNull('second_program_choice_id')->whereNull('third_program_choice_id')
    ->orderBy('id', 'desc')->first();

if ($noChoices) {
    $html = $render($noChoices->id);

    check('an application with no choices recorded shows no panel',
        count($choiceRows($html)[0]) === 0 && !str_contains($html, 'applicant-choices'));
    check('and the modal still renders', str_contains($html, 'convertApplicationModal'));
}

// Switching across faculties is a real case, not a hypothetical.
$crossFaculty = DB::table('applications as a')
    ->join('programs as p1', 'p1.id', '=', 'a.first_program_choice_id')
    ->join('programs as p2', 'p2.id', '=', 'a.second_program_choice_id')
    ->whereColumn('p1.faculty_id', '<>', 'p2.faculty_id')
    ->select('a.id')
    ->first();

check('choices spanning two faculties exist on file, so switching must handle it',
    $crossFaculty !== null);

if ($crossFaculty) {
    $html = $render($crossFaculty->id);

    check('each choice carries the faculty needed to switch to it',
        substr_count($html, 'class="btn btn-link btn-sm p-0 choice-switch"') >= 2);
    check('the switch reloads the list when the option is not in it',
        str_contains($html, "\$program.data('selected', programId);")
        && str_contains($html, "\$faculty.val(facultyId).trigger('change');"));
}

echo "\n$passed passed, $failed failed\n";
