<?php
/**
 * The letterhead must look the same on screen, in print and in a download —
 * and must appear on the first page only.
 *
 * The failure this guards against is silent: dompdf drops an image it cannot
 * resolve without raising anything, so a document still looks finished while
 * the letterhead is missing from every copy that leaves the building.
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\LetterheadSetting;
use App\Services\LetterheadService;
use App\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$req = Request::create(url('/admin'), 'GET');
$req->setLaravelSession(app('session.store'));
app()->instance('request', $req);
view()->share('errors', new Illuminate\Support\ViewErrorBag);
Auth::guard('web')->login(User::query()->first());

$results = [];
function check($n, $p, $d = '') { global $results; $results[] = [$n, $p, $d]; }

$service = app(LetterheadService::class);
$controller = app(App\Http\Controllers\Admin\LetterheadController::class);
$image = 'uploads/editor/editor_1787148003_faAotXWBOj.png';

DB::beginTransaction();
try {
    $settings = LetterheadSetting::current();

    // ---- html mode ------------------------------------------------------
    $settings->update([
        'mode' => 'html',
        'status' => true,
        'html' => '<p style="text-align:center">[institution_name]</p>'
            . '<img src="../../../../' . $image . '">',
    ]);

    $forPdf = $service->render(true);
    $forWeb = $service->render(false);

    check('renders for PDF', $forPdf !== '');
    check('renders for screen', $forWeb !== '');

    preg_match('/src="([^"]+)"/', $forPdf, $p);
    check('the PDF image is a real file on disk', isset($p[1]) && is_file($p[1]), $p[1] ?? 'none');

    preg_match('/src="([^"]+)"/', $forWeb, $w);
    check('the screen image is an absolute URL', isset($w[1]) && str_starts_with($w[1], 'http'), $w[1] ?? 'none');

    check('both point at the same file',
        isset($p[1], $w[1]) && basename($p[1]) === basename($w[1]));

    check('tokens are substituted',
        !str_contains($forPdf, '[institution_name]') && str_contains($forPdf, 'PAX'), 'token left in place');

    // The proof: does it survive into an actual PDF?
    $bytes = Pdf::loadHTML('<html><body>' . $forPdf . '</body></html>')->output();
    check('the image is embedded in the generated PDF', str_contains($bytes, '/Image'),
        number_format(strlen($bytes)) . ' bytes');
    check('the PDF is not a near-empty shell', strlen($bytes) > 100000, number_format(strlen($bytes)));

    // ---- first page only -------------------------------------------------
    check('it is not a fixed page header', !str_contains($service->styles(), 'position:fixed'));
    check('nothing marks it to repeat', !str_contains($forPdf, 'position: fixed')
        && !str_contains($forPdf, 'position:fixed'));

    $preview = $controller->preview();
    $previewBytes = $preview->getContent();
    check('the preview renders as a real PDF', str_starts_with($previewBytes, '%PDF'));
    check('the preview runs past one page', substr_count($previewBytes, '/Type /Page') > 1
        || substr_count($previewBytes, '/Type/Page') > 1, 'single page — cannot prove non-repeat');
    check('the preview embeds the image once', substr_count($previewBytes, '/Image') <= 2,
        substr_count($previewBytes, '/Image') . ' image objects');

    // ---- reserve space ---------------------------------------------------
    $settings->update(['mode' => 'reserve_space', 'reserve_height_mm' => 40]);
    $reserved = $service->render(true);
    check('reserve mode leaves a gap', str_contains($reserved, '40mm'), $reserved);
    check('reserve mode prints no content', !str_contains($reserved, '<img'));

    $settings->update(['reserve_height_mm' => 999]);
    check('an absurd reserve height is capped', str_contains($service->render(true), '150mm'),
        $service->render(true));

    // ---- off -------------------------------------------------------------
    $settings->update(['mode' => 'none', 'reserve_height_mm' => 35]);
    check('none mode renders nothing at all', $service->render(true) === '');

    $settings->update(['mode' => 'html', 'status' => false]);
    check('switching it off renders nothing', $service->render(true) === '');
    check('but the content is kept', LetterheadSetting::current()->html !== null);

    // ---- the screens still work -----------------------------------------
    $settings->update(['status' => true]);
    check('the admin screen renders', strlen($controller->index()->render()) > 10000);

    // ---- the documents that were hardcoded -------------------------------
    foreach (['print', 'download'] as $view) {
        $source = file_get_contents(resource_path("views/admin/marksheet/{$view}.blade.php"));
        check("marksheet {$view} no longer names a file", !str_contains($source, 'paxletterhead.jpg'));
        check("marksheet {$view} uses the shared partial", str_contains($source, "partials.letterhead"));
    }
} finally {
    DB::rollBack();
}

$failed = 0;
foreach ($results as [$n, $p, $d]) {
    if (!$p) { $failed++; }
    printf("%s  %s%s\n", $p ? 'PASS' : 'FAIL', $n, (!$p && $d) ? "   [$d]" : '');
}
printf("\n%d/%d passed  (rolled back)\n", count($results) - $failed, count($results));
exit($failed ? 1 : 0);
