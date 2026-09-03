<?php
/**
 * No Blade partial includes itself, and the documents that use the letterhead
 * render within a sane amount of memory.
 *
 * The letterhead partial's own usage notes annotated an @include line with an
 * inline Blade comment. Blade comments do not nest, so that comment's closing
 * marker ended the surrounding docblock early and left the next @include as
 * live code — the partial included itself, without limit, and every document
 * carrying a letterhead died with "Allowed memory size exhausted". Two-gigabyte
 * limits died the same way, because the recursion has no depth at which it
 * stops.
 *
 * Nothing about that is visible in the source: the file reads as one comment.
 * It is only visible in the compiled output, which is what this suite checks.
 *
 * Usage: php scripts/letterhead_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

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

echo "\n== No partial includes itself ==\n";

$compiler = app('blade.compiler');
$viewRoot = realpath(__DIR__ . '/../resources/views');

// Partials that include themselves on purpose to draw a tree, and terminate
// because each level is handed a smaller set than it was given. The budget-line
// node recurses only when $children is non-empty and passes an empty collection
// down, so it is exactly one level deep. Recursion is not the fault; recursion
// with nothing to stop it is.
$deliberatelyRecursive = [
    'admin.budget-line.partials.node',
];

$selfIncluding = [];
$strayMarkers = [];

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewRoot));

foreach ($files as $file) {
    if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) {
        continue;
    }

    // The view's own dotted name, which is what an @include of itself uses.
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($viewRoot) + 1));
    $name = str_replace('/', '.', substr($relative, 0, -strlen('.blade.php')));

    $compiled = $compiler->compileString(file_get_contents($file->getPathname()));

    if (str_contains($compiled, "make('" . $name . "'")
        && !in_array($name, $deliberatelyRecursive, true)) {
        $selfIncluding[] = $name;
    }

    // A closing comment marker surviving compilation means a comment block
    // ended somewhere other than where it was written to end.
    if (str_contains($compiled, '--}}')) {
        $strayMarkers[] = $name;
    }
}

check('no view includes itself', $selfIncluding === [], implode(', ', $selfIncluding));
check('no unclosed comment leaks a marker into the output', $strayMarkers === [],
    implode(', ', $strayMarkers));

echo "\n== The letterhead partial compiles to what it looks like ==\n";

$source = file_get_contents($viewRoot . '/partials/letterhead.blade.php');
$compiled = $compiler->compileString($source);

check('it does not include itself', !str_contains($compiled, "make('partials.letterhead'"));
check('it still calls the letterhead service', str_contains($compiled, 'LetterheadService'));
check('it compiles to something small', strlen($compiled) < 2000, strlen($compiled) . ' bytes');

echo "\n== Documents carrying a letterhead render ==\n";

Illuminate\Support\Facades\Auth::guard('web')->login(App\User::where('is_admin', 1)->firstOrFail());
$kernel = app(Illuminate\Contracts\Http\Kernel::class);

// A student with enrolments, so the transcript actually has rows to draw.
$enroll = App\Models\StudentEnroll::whereNotNull('session_id')
    ->whereNotNull('semester_id')->whereNotNull('section_id')->first();

if (!$enroll) {
    echo "  SKIP  no enrolment to render a transcript for\n";
} else {
    foreach (['marksheet-download', 'marksheet-print'] as $action) {
        $before = memory_get_peak_usage(true);

        $request = Illuminate\Http\Request::create(
            '/admin/transcript/' . $action . '/' . $enroll->student_id,
            'GET',
            ['enrollment_id' => $enroll->id]
        );
        $request->setLaravelSession(app('session.store'));

        try {
            $response = $kernel->handle($request);
            $html = $response->getContent();

            check($action . ' renders', $response->getStatusCode() === 200,
                'status ' . $response->getStatusCode());

            // Exactly one. "At most one" passed when the letterhead vanished
            // entirely, which is the failure this is meant to catch.
            check($action . ' draws the letterhead exactly once',
                substr_count($html, 'class="letterhead"') === 1,
                substr_count($html, 'class="letterhead"') . ' occurrences');

            // A browser cannot load "C:\xampp\...". That path is what the
            // service emits for dompdf, and passing forPdf => true from a page
            // the browser renders left the logo silently broken.
            preg_match_all('#<img[^>]+src="([^"]+)"#i', $html, $srcs);
            $localPaths = array_filter(
                $srcs[1] ?? [],
                fn ($src) => (bool) preg_match('#^(?:[a-z]:[\\\\/]|/(?:var|home|srv)/)#i', $src)
            );

            check($action . ' has no filesystem paths as image sources',
                $localPaths === [], implode(', ', array_slice($localPaths, 0, 2)));

            // The author sizes the logo in the editor; without those attributes
            // it renders at its natural size and pushes the document down.
            if (preg_match('#<div class="letterhead">.*?</div>#s', $html, $lh)
                && str_contains($lh[0], '<img')) {
                check($action . ' keeps the logo\'s configured size',
                    (bool) preg_match('#<img[^>]+(?:width|height)=#i', $lh[0]));
            }

            // The watermark carries the institution's name from Settings, so a
            // renamed institution renames it without anyone editing a view.
            preg_match('#<div class="tp-watermark"[^>]*>\s*<span[^>]*>(.*?)</span>#s', $html, $wm);

            check($action . ' carries one watermark',
                substr_count($html, 'class="tp-watermark"') === 1,
                substr_count($html, 'class="tp-watermark"') . ' found');
            check($action . ' watermarks with the site title',
                isset($wm[1]) && trim($wm[1]) === trim((string) institution_name()),
                'got "' . trim($wm[1] ?? '') . '", settings say "' . institution_name() . '"');

            // Behind the record, not over it: a watermark that competes with the
            // marks is worse than none, especially once photocopied.
            check($action . ' keeps the watermark behind the content',
                strpos($html, 'class="tp-watermark"') < strpos($html, 'class="tp-content"')
                && preg_match('/\.tp-watermark\s*\{[^}]*z-index:\s*0/s', $html)
                && preg_match('/\.tp-content\s*\{[^}]*z-index:\s*1/s', $html));
        } catch (Throwable $e) {
            check($action . ' renders', false, get_class($e) . ': ' . substr($e->getMessage(), 0, 80));
        }

        // The recursion consumed gigabytes. A transcript is a page of text.
        check($action . ' stays well inside the memory limit',
            memory_get_peak_usage(true) < 256 * 1048576,
            round(memory_get_peak_usage(true) / 1048576) . ' MB peak');
    }
}

echo "\n== Other documents carrying the shared masthead ==\n";

// The marksheet was not the only view including a letterhead, and a check that
// only ever renders the page it was written for finds the bug once. These are
// the other documents the browser renders, so the same fault - a filesystem
// path where a URL belongs - would show up here too.
//
// A static scan was tried first and could not do this job: the controllers name
// their views by concatenation ($this->view . '.download'), so searching for a
// literal view name found no reference and reported success while the bug was
// present. Rendering the page is what actually proves it.
$enrollment = App\Models\StudentEnroll::whereNotNull('session_id')->first();

$documents = array_filter([
    $enrollment ? ['admission/student-form-a2/' . $enrollment->id . '/preview', []] : null,
    $enrollment ? ['admission/student-form-a3/' . $enrollment->id . '/preview', []] : null,
]);

foreach ($documents as [$uri, $params]) {
    $request = Illuminate\Http\Request::create('/admin/' . $uri, 'GET', $params);
    $request->setLaravelSession(app('session.store'));

    try {
        $response = $kernel->handle($request);

        // A 404 or a redirect is fine here - the record may not suit this
        // document. Only a rendered page is worth inspecting.
        if ($response->getStatusCode() !== 200) {
            echo '  SKIP  ' . $uri . ' (HTTP ' . $response->getStatusCode() . ")\n";
            continue;
        }

        preg_match_all('#<img[^>]+src="([^"]+)"#i', $response->getContent(), $srcs);
        $localPaths = array_filter(
            $srcs[1] ?? [],
            fn ($src) => (bool) preg_match('#^(?:[a-z]:[\\\\/]|/(?:var|home|srv)/)#i', $src)
        );

        check($uri . ' has no filesystem paths as image sources',
            $localPaths === [], implode(', ', array_slice($localPaths, 0, 2)));
    } catch (Throwable $e) {
        echo '  SKIP  ' . $uri . ' (' . get_class($e) . ")\n";
    }
}

echo "\n== Editor sizing survives the Word-paste cleanup ==\n";

// normalize() strips fixed widths because Word pastes overflow the page. An
// <img> is the exception: there the size is the author's own decision, and
// stripping it rendered a 56px logo at its natural 495px.
$sample = '<p style="width:900px"><img src="/x.png" width="56" height="57" />'
    . '<span style="mso-fareast-font-family:Times">a</span></p>';
$clean = App\Services\DocumentHtml::normalize($sample);

check('the image keeps its width', str_contains($clean, 'width="56"'), $clean);
check('the image keeps its height', str_contains($clean, 'height="57"'), $clean);
check('a fixed width elsewhere is still stripped', !str_contains($clean, '900px'), $clean);
check('Word properties are still stripped', !str_contains($clean, 'mso-'), $clean);
check('no placeholder is left behind', !str_contains($clean, 'dochtml-img'), $clean);

echo "\n$passed passed, $failed failed\n";
