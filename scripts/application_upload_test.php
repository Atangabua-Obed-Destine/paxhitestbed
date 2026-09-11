<?php
/**
 * What an applicant is told when a file cannot be accepted.
 *
 * The faults this guards, all reproduced before they were fixed:
 *
 *   - a file named PHOTO.PNG was "saved" and the stored photo set to NULL. The
 *     uploader's whitelist was matched case-sensitively, it answered null, and
 *     the controller assigned that straight onto the record;
 *   - draft saves did not check checklist documents at all, so a .docx could be
 *     stored as a birth certificate and an unusable file answered with "saved"
 *     while nothing was kept;
 *   - a rejected certificate was reported as "The academic_history.0.certificate_file
 *     must be a file of type..." and the page could not find the field to point
 *     at, because the input is academic_history[0][certificate_file];
 *   - anything that answered with something other than JSON — an expired
 *     sign-in above all — produced "Your answers could not be saved", naming
 *     neither the cause nor the file.
 *
 * Database writes are rolled back; files written during the run are deleted.
 *
 * Usage: php scripts/application_upload_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Application;
use Illuminate\Http\UploadedFile;
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

$application = Application::where('stage', 'draft')->whereNotNull('applicant_id')->orderByDesc('id')->first();

if (!$application) {
    echo "  SKIP  no draft application to test with\n";
    exit(0);
}

Auth::guard('applicant')->loginUsingId($application->applicant_id);

$uploadDir = public_path('uploads/' . 'student');
$existingFiles = is_dir($uploadDir) ? scandir($uploadDir) : [];

/** A real PNG, so only the NAME differs between cases. */
function realPng(string $name): UploadedFile
{
    $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('upl_') . '.png';
    $im = imagecreatetruecolor(90, 90);
    imagefill($im, 0, 0, imagecolorallocate($im, 210, 225, 235));
    imagepng($im, $tmp);
    imagedestroy($im);

    return new UploadedFile($tmp, $name, 'image/png', null, true);
}

function bytesFile(string $name, string $content, string $mime): UploadedFile
{
    $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('upl_') . '.bin';
    file_put_contents($tmp, $content);

    return new UploadedFile($tmp, $name, $mime, null, true);
}

/** Post a draft save and report it the way the page would. */
function saveDraft(Application $application, array $params, array $files): array
{
    $kernel = app(Illuminate\Contracts\Http\Kernel::class);
    $session = app('session.store');
    $session->regenerateToken();
    $params['_token'] = $session->token();

    $request = Illuminate\Http\Request::create("/application/{$application->id}/save-draft", 'POST', $params, [], $files);
    $request->headers->set('X-Requested-With', 'XMLHttpRequest');
    $request->headers->set('Accept', 'application/json');
    $request->setLaravelSession($session);

    $response = $kernel->handle($request);
    $json = json_decode($response->getContent(), true);
    $errors = $json['errors'] ?? [];
    $field = $errors ? array_key_first($errors) : null;

    return [
        'status' => $response->getStatusCode(),
        'saved' => $response->getStatusCode() < 400 && ($json['success'] ?? false) === true,
        'field' => $field,
        'message' => $field ? ((array) $errors[$field])[0] : ($json['message'] ?? ''),
        'json' => $json !== null,
    ];
}

$pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
$docx = 'PK' . str_repeat('x', 300);

echo "\n== A photo is kept, whatever the file is called ==\n";

DB::beginTransaction();

try {
    $result = saveDraft($application, [], ['photo' => realPng('photo.png')]);
    check('an ordinary photo.png is saved', $result['saved'], $result['message']);
    check('and is stored on the application', !empty(Application::find($application->id)->photo));

    // The one that used to answer "saved" and leave the record empty.
    $result = saveDraft($application, [], ['photo' => realPng('PHOTO.PNG')]);
    $stored = Application::find($application->id)->photo;

    check('an uppercase PHOTO.PNG is saved too', $result['saved'], $result['message']);
    check('and it really is stored, not silently dropped', !empty($stored), 'photo = ' . var_export($stored, true));
} finally {
    DB::rollBack();
}

echo "\n== A file we cannot use is refused, and says why ==\n";

DB::beginTransaction();

try {
    // Give the application a photo first, so the next upload has something to lose.
    saveDraft($application, [], ['photo' => realPng('good.png')]);
    $before = Application::find($application->id)->photo;

    $result = saveDraft($application, [], ['photo' => bytesFile('IMG_4021.heic', str_repeat("\x00\x01", 800), 'image/heic')]);

    check('a HEIC photo is refused', !$result['saved'] && $result['status'] === 422);
    check('the message names the formats that work', str_contains($result['message'], 'JPG') && str_contains($result['message'], 'PNG'), $result['message']);
    check('it mentions iPhone/HEIC, which is where these come from', stripos($result['message'], 'HEIC') !== false, $result['message']);
    check('it points at the photo field', $result['field'] === 'photo', (string) $result['field']);
    check('and the photo already stored is untouched', Application::find($application->id)->photo === $before);

    $result = saveDraft($application, [], ['photo' => bytesFile('scan.jpg', $pdf, 'image/jpeg')]);
    check('a PDF renamed .jpg is refused', !$result['saved'] && $result['field'] === 'photo');
    check('and still nothing was lost', Application::find($application->id)->photo === $before);
} finally {
    DB::rollBack();
}

echo "\n== A certificate says which qualification it belongs to ==\n";

DB::beginTransaction();

try {
    $result = saveDraft(
        $application,
        ['academic_history' => [0 => ['institution_name' => 'Test School', 'qualification_key' => 'gce_ol']]],
        ['academic_history' => [0 => ['certificate_file' => bytesFile('scan.docx', $docx, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')]]]
    );

    check('a .docx certificate is refused', !$result['saved'] && $result['status'] === 422);
    check('the message does not show the raw field name',
        !str_contains($result['message'], 'academic_history.0'), $result['message']);
    check('it says which qualification card to go back to',
        stripos($result['message'], 'qualification 1') !== false, $result['message']);
} finally {
    DB::rollBack();
}

echo "\n== Checklist documents are checked on a draft save ==\n";

$requirements = App\Services\DegreeTypeFormConfig::documents($application->degreeType);
$firstKey = array_key_first($requirements);

if (!$firstKey) {
    echo "  SKIP  this degree type has no document checklist\n";
} else {
    $label = $requirements[$firstKey]['label'] ?? $firstKey;

    DB::beginTransaction();

    try {
        $result = saveDraft($application, [], ['documents' => [$firstKey => ['file' => bytesFile('cert.pdf', $pdf, 'application/pdf')]]]);
        check('a PDF document is accepted', $result['saved'], $result['message']);

        // Both of these used to answer "saved".
        $result = saveDraft($application, [], ['documents' => [$firstKey => ['file' => bytesFile('cert.docx', $docx, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')]]]);
        check('a .docx document is refused', !$result['saved'] && $result['status'] === 422, $result['message']);
        check('and it is named the way the applicant sees it on screen',
            str_contains($result['message'], $label), $result['message'] . ' | expected: ' . $label);

        $result = saveDraft($application, [], ['documents' => [$firstKey => ['file' => bytesFile('notes.exe', 'MZ' . str_repeat('x', 300), 'application/octet-stream')]]]);
        check('an .exe document is refused rather than quietly ignored', !$result['saved'], $result['message']);
    } finally {
        DB::rollBack();
    }
}

echo "\n== An expired sign-in is explained ==\n";

DB::beginTransaction();

try {
    $kernel = app(Illuminate\Contracts\Http\Kernel::class);
    $session = app('session.store');
    $session->regenerateToken();

    // No _token: what a form left open past the session lifetime posts.
    $request = Illuminate\Http\Request::create("/application/{$application->id}/save-draft", 'POST', ['first_name' => 'Test']);
    $request->headers->set('X-Requested-With', 'XMLHttpRequest');
    $request->setLaravelSession($session);
    $response = $kernel->handle($request);

    check('it comes back as 419, not a silent failure', $response->getStatusCode() === 419, 'status ' . $response->getStatusCode());
} finally {
    DB::rollBack();
}

echo "\n== The page can explain a reply that is not JSON ==\n";

$page = file_get_contents(__DIR__ . '/../resources/views/application/apply.blade.php');

check('there is one shared explanation for failures', str_contains($page, 'describeSaveFailure'));
check('an expired sign-in is named', str_contains($page, 'Your sign-in has expired'));
check('a too-large upload is named', str_contains($page, 'too large for the server'));
check('the autosave uses the same explanation', substr_count($page, 'describeSaveFailure') >= 3);
check('a dotted field name is translated to the real input name', str_contains($page, "'[' + part + ']'"));
check('the photo input no longer offers every image type', !str_contains($page, 'accept="image/jpeg,image/png,image/*"'));

$uploader = file_get_contents(__DIR__ . '/../app/Traits/FileUploader.php');
check('the uploader compares extensions in lower case', str_contains($uploader, "in_array(strtolower(\$file_ext)"));
check('every whitelist in it does', substr_count($uploader, "in_array(strtolower(\$file_ext)") === 5,
    substr_count($uploader, "in_array(strtolower(\$file_ext)") . ' of 5');

// Files written during the run are not covered by the rollback.
clearstatcache();
$now = is_dir($uploadDir) ? scandir($uploadDir) : [];
$written = array_values(array_diff($now, $existingFiles));

foreach ($written as $file) {
    @unlink($uploadDir . DIRECTORY_SEPARATOR . $file);
}

echo "\n  (cleaned up " . count($written) . " uploaded file(s))\n";

echo "\n$passed passed, $failed failed\n";

exit($failed > 0 ? 1 : 0);
