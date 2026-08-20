<?php
/**
 * Render one ID card surface and print the CSS that governs the card.
 *
 * Runs a single surface per process on purpose: compiled Blade views in this
 * project declare a global panel() helper, so two renders in one process fatal
 * on the redeclaration. The suite in id_card_test.php shells out per surface.
 *
 * Usage: php scripts/id_card_render.php <surface> [--full]
 *   surfaces: student-print | student-download | student-index
 *             staff-print   | staff-download
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\StudentEnroll;
use App\User;
use Illuminate\Support\Facades\Auth;

$surface = $argv[1] ?? 'student-print';
$full = in_array('--full', $argv, true);

// Every one of these screens is permission-guarded; sign in as an admin who
// holds them all rather than stubbing the middleware.
$admin = User::whereHas('roles', function ($q) {
    $q->where('name', 'Super Admin');
})->first() ?: User::first();
Auth::guard('web')->login($admin);

$routes = [
    'student-print' => function () {
        $enroll = StudentEnroll::whereHas('student')->orderBy('id')->first();
        return '/admin/admission/id-card-print/' . $enroll->id;
    },
    'student-download' => function () {
        $enroll = StudentEnroll::whereHas('student')->orderBy('id')->first();
        return '/admin/admission/id-card-download/' . $enroll->id;
    },
    'student-index' => function () {
        return '/admin/admission/id-card';
    },
    'staff-print' => function () {
        $staff = User::orderBy('id')->first();
        return '/admin/staff/staff-id-card-print/' . $staff->id;
    },
    'staff-download' => function () {
        $staff = User::orderBy('id')->first();
        return '/admin/staff/staff-id-card-download/' . $staff->id;
    },
];

if (!isset($routes[$surface])) {
    fwrite(STDERR, "unknown surface: $surface\n");
    exit(2);
}

$uri = $routes[$surface]();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create($uri, 'GET');
$request->setLaravelSession(app('session.store'));
$response = $kernel->handle($request);
$html = $response->getContent();

if ($response->getStatusCode() !== 200) {
    fwrite(STDERR, "HTTP " . $response->getStatusCode() . " for $uri\n");
    if (preg_match('~<title>(.*?)</title>~s', $html, $m)) {
        fwrite(STDERR, trim(html_entity_decode(strip_tags($m[1]))) . "\n");
    }
    exit(1);
}

// The card rule and everything positioned against it. Normalising whitespace
// keeps the comparison about declarations rather than indentation.
$class = $surface === 'student-index' ? 'zip-id-card' : 'id-card';
// The print view declares .id-card twice — once inside @media print to force
// background printing, once for the card itself. Take the one that paints it.
preg_match_all('~\.' . $class . '\s*\{(.*?)\}~s', $html, $all, PREG_SET_ORDER);
$rule = null;
foreach ($all as $candidate) {
    if (strpos($candidate[1], 'background:') !== false) {
        $rule = preg_replace('~\s+~', ' ', trim($candidate[1]));
        break;
    }
}
if ($rule === null) {
    fwrite(STDERR, "no .$class rule painting a background found in $surface\n");
    exit(1);
}
echo "surface: $surface\n";
echo "uri:     $uri\n";
echo "rule:    $rule\n";

if ($full) {
    // Every rule scoped to the card, so a positional regression is visible.
    preg_match_all('~\.' . $class . '\s+\.[a-z-]+\s*\{(.*?)\}~s', $html, $all, PREG_SET_ORDER);
    foreach ($all as $r) {
        echo "  child: " . preg_replace('~\s+~', ' ', trim($r[0])) . "\n";
    }
}
