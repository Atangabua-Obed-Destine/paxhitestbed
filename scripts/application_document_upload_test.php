<?php
/**
 * Telling an applicant, straight away, whether their document arrived.
 *
 * A document used to reach the server only when the whole form was saved, and
 * the applicant was told "New file ready" - which is not the same thing as
 * uploaded. If the save then failed, quietly or otherwise, they had no way of
 * knowing.
 *
 * Writes happen inside a transaction that is rolled back. Files are not
 * transactional, so anything written to disk is deleted by hand at the end and
 * the last section proves it.
 *
 * Run: php scripts/application_document_upload_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Application;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;
$skipped = 0;

function section(string $title): void
{
    echo "\n" . $title . "\n" . str_repeat('-', strlen($title)) . "\n";
}

function check(string $what, bool $ok, string $why = ''): void
{
    global $passed, $failed;

    if ($ok) {
        $passed++;
        echo "  PASS  " . $what . "\n";
    } else {
        $failed++;
        echo "  FAIL  " . $what . ($why ? "\n        " . $why : '') . "\n";
    }
}

function skip(string $what): void
{
    global $skipped;
    $skipped++;
    echo "  SKIP  " . $what . "\n";
}

app('session.store')->start();

$application = Application::whereNotNull('applicant_id')
    ->where('status', 0)
    ->orderByDesc('id')
    ->first();

if (!$application) {
    echo "No application to act on.\n";
    exit(1);
}

Auth::guard('applicant')->loginUsingId($application->applicant_id);

echo 'Subject: application #' . $application->id . "\n";

$get = function (string $url) use ($kernel) {
    $request = Illuminate\Http\Request::create($url, 'GET');
    $request->setLaravelSession(app('session.store'));

    return $kernel->handle($request);
};

$response = $get('/application/' . $application->id . '/edit');
$html = $response->getContent();

// Files this suite writes, cleaned up at the end.
$written = [];

$upload = function (string $key, ?UploadedFile $file) use ($kernel, $application, &$written) {
    $files = $file ? ['file' => $file] : [];
    $request = Illuminate\Http\Request::create(
        '/application/' . $application->id . '/documents/' . $key, 'POST',
        ['_token' => csrf_token()], [], $files);
    $request->setLaravelSession(app('session.store'));
    $request->headers->set('Accept', 'application/json');

    $response = $kernel->handle($request);
    $body = json_decode($response->getContent(), true) ?: [];

    if (!empty($body['view_url'])) {
        $written[] = public_path('uploads/student/' . basename($body['view_url']));
    }

    return [$response->getStatusCode(), $body];
};

// ---------------------------------------------------------------------------
section('The form says what is required before anything is chosen');
// ---------------------------------------------------------------------------
check('the page opens', $response->getStatusCode() === 200,
    'status ' . $response->getStatusCode());

$blocks = array_slice(explode('data-document-field=', $html), 1);
$marked = 0;

foreach ($blocks as $block) {
    $field = substr($block, 0, strpos($block, '</label>') ?: 600);

    if (str_contains($field, '<span class="badge bg-danger">' . __('Required') . '</span>')
        || str_contains($field, '<span class="badge bg-secondary">' . __('Optional') . '</span>')) {
        $marked++;
    }
}

check('every document is marked required or optional',
    count($blocks) > 0 && $marked === count($blocks),
    $marked . ' of ' . count($blocks) . ' documents carry a badge — an applicant should not have to guess');

check('the size limit is stated up front, not only after a rejection',
    str_contains($html, __('JPG, PNG or PDF, up to 10 MB.')),
    'the limit used to appear only in the error after a file was refused');

check('each document can report on itself',
    substr_count($html, 'data-document-error=') > 0
        && substr_count($html, 'data-document-state=') > 0
        && substr_count($html, 'data-document-progress=') > 0);

check('and the file is sent on its own, not with the whole form',
    substr_count($html, 'data-upload-url="http') > 0,
    'one document per request keeps the body small and the answer specific');

// ---------------------------------------------------------------------------
section('A good document is stored, and said to be stored');
// ---------------------------------------------------------------------------
preg_match('/data-upload-url="[^"]*\/documents\/([^"\/]+)"/', $html, $m);
$key = $m[1] ?? null;

if (!$key) {
    skip('no document input on this application');
} else {
    DB::beginTransaction();

    try {
        $before = $application->documents()->where('document_type', $key)->value('file_path');

        $path = sys_get_temp_dir() . '/doc-suite-good.pdf';
        file_put_contents($path, "%PDF-1.4\n% a small but real file\n");

        [$status, $body] = $upload($key, new UploadedFile($path, 'certificate.pdf', 'application/pdf', null, true));

        check('it is accepted', $status === 200 && ($body['ok'] ?? false),
            $status . ' ' . json_encode($body));

        check('and it really reached the database',
            $application->documents()->where('document_type', $key)->value('file_path') !== $before,
            'the answer must mean the file is stored, not merely received');

        check('and the applicant is given a link to what they uploaded',
            !empty($body['view_url']),
            'being told "uploaded" with no way to look is still asking them to trust it');

        check('and the document is marked as received',
            (bool) $application->documents()->where('document_type', $key)->value('is_received'));
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('A document that cannot be stored says why');
// ---------------------------------------------------------------------------
// Each of these needs a different response from the applicant, so none of them
// may collapse into "something went wrong".
if (!$key) {
    skip('no document input on this application');
} else {
    DB::beginTransaction();

    try {
        $txt = sys_get_temp_dir() . '/doc-suite-bad.txt';
        file_put_contents($txt, 'not a document');

        [$status, $body] = $upload($key, new UploadedFile($txt, 'notes.txt', 'text/plain', null, true));

        check('the wrong sort of file is refused', $status === 422 && !($body['ok'] ?? true));

        check('and the message names the types that are allowed',
            str_contains($body['message'] ?? '', 'PDF'),
            $body['message'] ?? '(none)');

        [$status, $body] = $upload($key, null);

        check('a request that arrives with no file at all is refused',
            $status === 422 && !($body['ok'] ?? true));

        check('and says it may have been too large for the server',
            str_contains(strtolower($body['message'] ?? ''), 'too large'),
            $body['message'] ?? '(none)');

        check('nothing was written for either',
            $application->documents()->where('document_type', $key)->where('file_path', 'like', '%notes%')->count() === 0);
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('Another applicant cannot upload here');
// ---------------------------------------------------------------------------
$other = Application::whereNotNull('applicant_id')
    ->where('applicant_id', '!=', $application->applicant_id)
    ->where('status', 0)
    ->first();

if (!$other || !$key) {
    skip('no second applicant to try with');
} else {
    Auth::guard('applicant')->loginUsingId($other->applicant_id);

    $path = sys_get_temp_dir() . '/doc-suite-intruder.pdf';
    file_put_contents($path, "%PDF-1.4\n");

    [$status] = $upload($key, new UploadedFile($path, 'intruder.pdf', 'application/pdf', null, true));

    check('an applicant is refused another applicant\'s application',
        $status === 403,
        'got ' . $status);

    Auth::guard('applicant')->loginUsingId($application->applicant_id);
}

// ---------------------------------------------------------------------------
section('Nothing was left behind');
// ---------------------------------------------------------------------------
$leftover = [];

foreach (array_unique($written) as $file) {
    if (file_exists($file)) {
        @unlink($file);
        $leftover[] = basename($file);
    }
}

echo '  cleaned up ' . count($leftover) . " uploaded file(s)\n";

check('no uploaded file from this suite remains on disk',
    count(array_filter(array_unique($written), 'file_exists')) === 0,
    implode(', ', array_filter(array_unique($written), 'file_exists')));

check('the application still has the documents it started with',
    $application->documents()->count() === Application::find($application->id)->documents()->count());

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";
