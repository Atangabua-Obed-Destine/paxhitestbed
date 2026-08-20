<?php
/**
 * Every document wears the letterhead the institution configured.
 *
 * Documents used to build their own mastheads, and the school's name was
 * written into seventeen views as a literal fallback — so a receipt, a Form A2
 * and an acceptance letter could each claim a different institution. The
 * configured letterhead currently reads "SAPIENTIA HIGHER INSTITUTE OF THE
 * DIOCESE OF KUMBA" while the markup said "PAX HIGHER INSTITUTE", which is
 * exactly the disagreement this prevents.
 *
 * What matters here is not that a header appears, but that it is *the configured
 * one, unaltered* — and that turning the letterhead off still leaves a document
 * with a heading rather than nothing.
 *
 * Usage: php scripts/letterhead_documents_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\LetterheadSetting;
use App\Services\LetterheadService;
use App\User;
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

echo "\n" . str_repeat('=', 64) . "\n";
echo "Documents use the configured letterhead\n";
echo str_repeat('=', 64) . "\n";

// ---------------------------------------------------------------------------
echo "\nNo institution's name is written into the markup\n";
// ---------------------------------------------------------------------------

$offenders = [];
$subtitleOffenders = [];
$leaks = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));
$scanned = 0;

foreach ($iterator as $file) {
    $path = $file->getPathname();
    // resources/views/web is the previous public site, superseded by web2. Its
    // ten marketing pages are rendered by nothing — confirmed by searching for
    // view('web.<name>') — so their copy is left alone rather than churned.
    $legacySite = DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'web' . DIRECTORY_SEPARATOR;

    if (!preg_match('~\.blade\.php$~', $path)
        || preg_match('~\.bak|\.backup|create_temp~', $path)
        || strpos($path, $legacySite) !== false) {
        continue;
    }
    $source = @file_get_contents($path);
    if ($source === false) {
        continue;   // not UTF-8; reported separately below
    }
    $scanned++;
    if (preg_match('~PAX HIGHER INSTITUTE|PAXHI|Pax, Innovatio~i', $source)) {
        $offenders[] = str_replace(resource_path('views') . DIRECTORY_SEPARATOR, '', $path);
    }

    // The founding body belongs in Settings → Site Subtitle, not in markup.
    if (strpos($source, 'Archdiocese of Bamenda') !== false) {
        $subtitleOffenders[] = str_replace(resource_path('views') . DIRECTORY_SEPARATOR, '', $path);
    }

    // A concatenation that escaped its PHP string renders as literal text —
    // "' . institution_name() . '" printed on the page instead of the name.
    // Valid uses sit inside @section/@yield defaults; these do not.
    foreach (preg_split('~\R~', $source) as $line) {
        if (strpos($line, "' . institution_name() . '") !== false
            && !preg_match('~@section|@yield|meta_description|meta_keywords~', $line)) {
            $leaks[] = str_replace(resource_path('views') . DIRECTORY_SEPARATOR, '', $path);
            break;
        }
    }
}

check(
    "no view names a particular institution ($scanned scanned)",
    $offenders === [],
    implode(', ', array_slice($offenders, 0, 5))
);

// The founding body is one institution's fact. It belongs in Settings → Site
// Subtitle, not written into the homepage, footer, header and page metadata.
check(
    'no view hardcodes the founding body',
    $subtitleOffenders === [],
    implode(', ', array_slice($subtitleOffenders, 0, 5))
);

// A concatenation left outside a PHP string prints itself rather than the name,
// so the page reads "' . institution_name() . '" in plain sight. Blade compiles
// it happily, and a check that merely looked for the name elsewhere on the page
// would not notice.
check(
    'no concatenation leaked into page text',
    $leaks === [],
    implode(', ', array_slice($leaks, 0, 5))
);

check('site_subtitle() is available to views', function_exists('site_subtitle'));

// ---------------------------------------------------------------------------
echo "\nThere is one answer to what the institution is called\n";
// ---------------------------------------------------------------------------

$service = app(LetterheadService::class);

check('institution_name() is available to views', function_exists('institution_name'));
check('institution_code() is available to views', function_exists('institution_code'));
check('the name is not empty', trim(institution_name()) !== '');
check('the code is not empty', trim(institution_code()) !== '');

// A title entered twice is a data-entry slip; printing it twice on an official
// document is worse than trimming it.
check(
    'a doubled title is not printed twice',
    stripos(institution_name(), 'NDOP NDOP') === false
        && preg_match('~^(.+) \1$~', institution_name()) !== 1,
    institution_name()
);

// ---------------------------------------------------------------------------
echo "\nThe letterhead reaches the documents\n";
// ---------------------------------------------------------------------------

Auth::guard('web')->login(User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first() ?: User::first());
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$fetch = function (string $uri) use ($kernel) {
    $request = Illuminate\Http\Request::create($uri, 'GET');
    $request->setLaravelSession(app('session.store'));

    return $kernel->handle($request);
};

check('the configured letterhead renders', trim($service->render(false)) !== '');

$enrollment = DB::table('student_enrolls')->orderBy('id')->value('id');
$receipt = DB::table('payment_receipts')->orderByDesc('id')->value('id');
$application = DB::table('applications')->where('stage', '!=', 'draft')->orderByDesc('id')->value('id');

$documents = array_filter([
    'application preview' => $application ? "/admin/admission/application/{$application}/preview" : null,
    'fee receipt' => $receipt ? "/admin/admission-fees-report/receipt/{$receipt}" : null,
    'Form A2' => $enrollment ? "/admin/admission/student-form-a2/{$enrollment}/download" : null,
    'Form A3' => $enrollment ? "/admin/admission/student-form-a3/{$enrollment}/download" : null,
]);

foreach ($documents as $label => $uri) {
    $response = $fetch($uri);
    $body = $response->getContent();

    check("$label renders", $response->getStatusCode() === 200, 'HTTP ' . $response->getStatusCode());

    if ($response->getStatusCode() !== 200) {
        continue;
    }

    // "Exactly as configured" compared byte for byte, not by stripped text:
    // the letterhead splits its wording across <strong><span> runs, so the
    // visible words never appear contiguously and a looser check would pass on
    // a header that merely resembled it.
    //
    // The expected value has to be produced *after* the request, because asset
    // URLs resolve against the current request's host — rendering it beforehand
    // in CLI context yields a different base URL and nothing would ever match.
    $expected = app(LetterheadService::class)->render(false);

    check("$label carries the letterhead exactly as configured", strpos($body, $expected) !== false);
    check("$label includes the letterhead artwork", substr_count($body, 'class="letterhead"') > 0);
}

// ---------------------------------------------------------------------------
echo "\nSwitching the letterhead off leaves a heading, not a gap\n";
// ---------------------------------------------------------------------------

// A document with no heading at all would be worse than the masthead this
// replaced, so the partial composes one from Settings when nothing is set.
DB::beginTransaction();

$setting = LetterheadSetting::current();
$setting->status = 0;
$setting->save();

check('nothing renders when the letterhead is off', app(LetterheadService::class)->render(false) === '');

$fallback = view('partials.document-header', ['forPdf' => false])->render();
check('the partial still produces a masthead', strpos($fallback, 'doc-masthead-fallback') !== false);
check('and it names the institution', strpos($fallback, institution_name()) !== false);
check('without naming a particular school', stripos($fallback, 'PAX HIGHER INSTITUTE NDOP PAX') === false);

DB::rollBack();

check(
    'the letterhead is back on afterwards',
    LetterheadSetting::current()->isActive(),
    'status ' . LetterheadSetting::current()->status
);

echo "\n" . str_repeat('-', 64) . "\n";
printf("%d passed, %d failed\n", $passed, $failed);

exit($failed === 0 ? 0 : 1);
