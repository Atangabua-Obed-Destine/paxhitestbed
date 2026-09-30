<?php
/**
 * The loading screen, and one click meaning one action.
 *
 * A screen that says a click landed is easy; the work is knowing when not to
 * show it. A download, a new tab or a print popup leaves the page exactly where
 * it is, so a screen shown on those clicks would never go away — worse than
 * showing nothing at all. Most of this suite is that list.
 *
 * Behaviour like this only exists in a browser, so the cases are driven in
 * headless Chrome against the real partial, as the layouts include it.
 *
 * Nothing here writes.
 *
 * Usage: php scripts/loading_screen_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

$chrome = 'C:\Program Files\Google\Chrome\Application\chrome.exe';

if (!is_file($chrome)) {
    echo "Chrome is not installed here, and these checks need a browser.\n";
    exit(1);
}

$work = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'loading_screen_test';
@mkdir($work, 0777, true);

// ---------------------------------------------------------------------------

echo "\n== The screen reaches the pages ==\n";

$kernel = app(Illuminate\Contracts\Http\Kernel::class);
$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->where('status', '1')->first()
    ?: App\User::where('is_admin', 1)->where('status', '1')->first();
Auth::guard('web')->login($admin);

$render = function (string $url) use ($kernel) {
    $request = Request::create($url, 'GET');
    $request->setLaravelSession(app('session.store'));
    $response = $kernel->handle($request);

    return [$response->getStatusCode(), (string) $response->getContent()];
};

[$status, $dashboard] = $render('/admin/dashboard');

check('an admin page carries it', $status === 200 && str_contains($dashboard, 'id="app-loading"'), "status $status");
check('it names the product and the school',
    str_contains($dashboard, 'EduTrust') && str_contains($dashboard, institution_name()));
check('and it starts out of the way', !str_contains($dashboard, 'app-loading is-visible'));

$studentLayout = file_get_contents(__DIR__ . '/../resources/views/student/layouts/master.blade.php');
check('the student portal includes it too', str_contains($studentLayout, "@include('partials.loading-screen')"));

// ---------------------------------------------------------------------------

echo "\n== What a click does, in a browser ==\n";

// The real partial, in a page holding one of every case it has to judge.
$screen = view('partials.loading-screen')->render();
$page = <<<HTML
<!DOCTYPE html><html><head><meta charset="utf-8"><title>cases</title></head><body>
<a id="normal" href="/admin/fees-student-report">ordinary</a>
<a id="newtab" href="/admin/dashboard" target="_blank">new tab</a>
<a id="download" href="/admin/transcript/marksheet-download/45?enrollment_id=132">download</a>
<a id="exportlink" href="/admin/application/report/export">export</a>
<a id="hash" href="#">nothing</a>
<a id="modal" href="#pay" data-bs-toggle="modal">dialog</a>
<a id="optout" class="js-no-loading" href="/admin/dashboard">opted out</a>
<form id="goodform" action="/admin/dashboard" method="get"><input name="q" value="x"><button type="submit" id="goodsubmit">Send</button></form>
<form id="badform" action="/admin/dashboard" method="get"><input name="needed" required><button type="submit" id="badsubmit">Send</button></form>
{$screen}
<script>
window.__submits = 0;
document.addEventListener('submit', function (e) { if (!e.defaultPrevented) { window.__submits++; } e.preventDefault(); });
document.addEventListener('click', function (e) { var a = e.target.closest && e.target.closest('a'); if (a) { e.preventDefault(); } });
window.visible = function () { return document.getElementById('app-loading').classList.contains('is-visible'); };
window.runCases = function () {
    var out = {};
    function clickCase(name, id, init) {
        window.appLoading.hide();
        document.getElementById(id).dispatchEvent(new MouseEvent('click', Object.assign({bubbles: true, cancelable: true}, init || {})));
        out[name] = window.visible();
    }
    clickCase('ordinary', 'normal');
    clickCase('newtab', 'newtab');
    clickCase('download', 'download');
    clickCase('export', 'exportlink');
    clickCase('hash', 'hash');
    clickCase('modal', 'modal');
    clickCase('optout', 'optout');
    clickCase('ctrl', 'normal', {ctrlKey: true});
    clickCase('middle', 'normal', {button: 1});
    window.appLoading.hide(); window.__submits = 0;
    document.getElementById('goodsubmit').click();
    out['validShows'] = window.visible();
    out['buttonLocked'] = document.getElementById('goodsubmit').disabled;
    document.getElementById('goodform').dispatchEvent(new Event('submit', {bubbles: true, cancelable: true}));
    out['submitsAfterTwoClicks'] = window.__submits;
    window.appLoading.hide();
    document.getElementById('goodsubmit').disabled = false;
    document.getElementById('goodform').removeAttribute('data-sending');
    window.__submits = 0;
    document.getElementById('badsubmit').click();
    out['invalidShows'] = window.visible();
    out['invalidButtonUsable'] = !document.getElementById('badsubmit').disabled;
    out['invalidSubmits'] = window.__submits;
    window.appLoading.show();
    document.dispatchEvent(new KeyboardEvent('keydown', {key: 'Escape', bubbles: true}));
    out['escapeCloses'] = !window.visible();
    window.appLoading.show();
    window.dispatchEvent(new PageTransitionEvent('pageshow', {persisted: true}));
    out['backLeavesHidden'] = !window.visible();
    window.appLoading.show();
    document.getElementById('goodform').setAttribute('data-sending', '1');
    window.appLoading.hide();
    out['hidingFreesTheForm'] = !document.getElementById('goodform').hasAttribute('data-sending');
    out['says'] = document.querySelector('.app-loading__panel').textContent.replace(/\s+/g, ' ').trim();
    return out;
};
window.addEventListener('load', function () {
    setTimeout(function () { document.body.setAttribute('data-probe', JSON.stringify(window.runCases())); }, 400);
});
</script></body></html>
HTML;

$casesFile = $work . DIRECTORY_SEPARATOR . 'cases.html';
file_put_contents($casesFile, $page);

$command = '"' . $chrome . '" --headless=new --disable-gpu --virtual-time-budget=6000 --dump-dom "file:///'
    . str_replace('\\', '/', $casesFile) . '" 2>NUL';
$dom = shell_exec($command);

if (!preg_match('/data-probe="([^"]*)"/', (string) $dom, $match)) {
    echo "  FAIL  the browser did not report back — the page may have errored\n";
    echo "\n$passed passed, " . ($failed + 1) . " failed\n";
    exit(1);
}

$out = json_decode(html_entity_decode($match[1], ENT_QUOTES), true);

check('an ordinary link shows it', $out['ordinary'] === true);
check('a link that opens a new tab does not', $out['newtab'] === false);
check('a download does not — the page stays where it is', $out['download'] === false);
check('nor does an export', $out['export'] === false);
check('nor a link that goes nowhere (href="#")', $out['hash'] === false);
check('nor a dialog trigger', $out['modal'] === false);
check('nor anything opted out with js-no-loading', $out['optout'] === false);
check('nor a Ctrl-click, which opens a tab', $out['ctrl'] === false);
check('nor a middle-click', $out['middle'] === false);

echo "\n== One click, one action ==\n";

check('submitting a valid form shows it', $out['validShows'] === true);
check('and locks the button that was pressed', $out['buttonLocked'] === true);
check('so two quick clicks send one submission, not two', $out['submitsAfterTwoClicks'] === 1,
    'sent ' . $out['submitsAfterTwoClicks']);

echo "\n== It never traps anybody ==\n";

check('a form the browser refuses shows nothing', $out['invalidShows'] === false);
check('and leaves its button usable, so it can be corrected', $out['invalidButtonUsable'] === true);
check('and sends nothing', $out['invalidSubmits'] === 0);
check('Esc closes it', $out['escapeCloses'] === true);
check('coming back with Back leaves it closed', $out['backLeavesHidden'] === true);
check('and closing it frees the form to be sent again', $out['hidingFreesTheForm'] === true);

echo "\n== What it says ==\n";

check('the product, the school, and what is happening',
    str_contains($out['says'], 'EduTrust') && str_contains($out['says'], institution_name()) && stripos($out['says'], 'Loading') !== false,
    $out['says']);

echo "\n$passed passed, $failed failed\n";

exit($failed > 0 ? 1 : 0);
