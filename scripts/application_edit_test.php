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

echo "\n$passed passed, $failed failed\n";
