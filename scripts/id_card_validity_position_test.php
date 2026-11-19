<?php
/**
 * Where the student ID card prints its validity, and how it shows its barcode.
 *
 * The ID Card Setting screen chooses: top right in white, as the card always
 * was, or in black as its own row under Date Issued. The card is drawn in
 * three places — the print page, the download page, and the zip built in the
 * browser on the ID card list — and all three must follow the setting.
 *
 * Driven through the real routes. Everything is rolled back.
 *
 * Usage: php scripts/id_card_validity_position_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\IdCardSetting;
use App\Models\StudentEnroll;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

app('session.store')->start();
Auth::guard('web')->login(User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first());

$http = function (string $method, string $path, array $data = []) use ($kernel) {
    $request = Request::create($path, $method, $data + ($method === 'POST' ? ['_token' => csrf_token()] : []));
    $request->setLaravelSession(app('session.store'));
    return $kernel->handle($request);
};

/** The top-right line and the row under Date Issued, as elements, not CSS. */
$topLine = '/<div class="validity">VALIDITY: ([^<]*)<\/div>/';
$row = '/<div class="validity-row"><span class="validity-label">VALIDITY<\/span><span class="validity-colon">:<\/span><span class="validity-value">([^<]*)<\/span><\/div>/';

echo "\n== Ground ==\n";
check('the setting has a validity position', Schema::hasColumn('id_card_settings', 'validity_position'));
$setting = IdCardSetting::where('slug', 'student-card')->first();
check('a student card setting to work with', $setting !== null);
$enrol = StudentEnroll::whereHas('student')->whereHas('program')->orderByDesc('id')->first();
check('an enrolment to print a card for', $enrol !== null);
if (!$setting || !$enrol) {
    echo "\n$passed passed, $failed failed\n";
    exit(1);
}
$before = $setting->only(['validity_position', 'barcode_position', 'title', 'subtitle', 'validity', 'address', 'prefix', 'updated_at']);
$period = date('Y') . '-' . (date('Y') + ($enrol->program->validity_years ?? 4));

echo "\n== Existing cards stay as they are ==\n";
check('an existing setting is at the top right', IdCardSetting::where('validity_position', '!=', 'top')->doesntExist()
    || $setting->validity_position === 'top', 'student card: ' . $setting->validity_position);
DB::beginTransaction();
try {
    $fresh = IdCardSetting::create(['slug' => 'zz-new-card', 'title' => 'x', 'subtitle' => 'x', 'validity' => 1]);
    check('a new setting starts at the top right', $fresh->fresh()->validity_position === IdCardSetting::VALIDITY_TOP);
} finally {
    DB::rollBack();
}

echo "\n== The settings screen ==\n";
$page = $http('GET', '/admin/admission/id-card-setting')->getContent();
check('offers the choice', str_contains($page, 'name="validity_position"'));
check('with both places', str_contains($page, 'value="top"') && str_contains($page, 'value="below_issued"'));
check('and the current one chosen', (bool) preg_match('/value="' . $setting->validity_position . '"\s+selected/', $page));

$save = function (?string $position) use ($http, $setting) {
    $data = [
        'id' => $setting->id, 'slug' => 'student-card',
        'title' => $setting->title, 'subtitle' => $setting->subtitle, 'validity' => $setting->validity,
        'address' => $setting->address, 'prefix' => $setting->prefix,
        'student_photo' => $setting->student_photo, 'signature' => $setting->signature, 'barcode' => $setting->barcode,
    ];
    if ($position !== null) {
        $data['validity_position'] = $position;
    }
    $http('POST', '/admin/admission/id-card-setting', $data);
    return $setting->fresh()->validity_position;
};

DB::beginTransaction();
try {
    check('saving "under Date Issued" keeps it', $save('below_issued') === 'below_issued');
    check('a position that is not one of the two is refused', $save('sideways') === 'below_issued');
    check('saving "top right" puts it back', $save('top') === 'top');
    check('a form that does not send it means the top right', $save('below_issued') === 'below_issued' && $save(null) === 'top');
    $after = $setting->fresh();
    check('and the other settings are kept', $after->title === $setting->title && $after->prefix === $setting->prefix
        && (string) $after->validity === (string) $setting->validity);
} finally {
    DB::rollBack();
}

echo "\n== The cards follow it ==\n";
foreach (['top', 'below_issued'] as $position) {
    DB::beginTransaction();
    try {
        IdCardSetting::whereKey($setting->id)->update(['validity_position' => $position]);
        $under = $position === 'below_issued';

        foreach (['print' => '/admin/admission/id-card-print/', 'download' => '/admin/admission/id-card-download/'] as $kind => $path) {
            $res = $http('GET', $path . $enrol->id);
            $html = $res->getContent();
            preg_match($topLine, $html, $t);
            preg_match($row, $html, $r);
            check("$position, $kind page opens", $res->getStatusCode() === 200, 'status ' . $res->getStatusCode());
            if ($under) {
                check("$position, $kind: the row under Date Issued, with the period", ($r[1] ?? null) === $period, $r[1] ?? 'no row');
                check("$position, $kind: and nothing at the top right", !$t);
                check("$position, $kind: the row comes after Date Issued and before the QR code",
                    strpos($html, 'class="date-issued"') < strpos($html, 'class="validity-row"')
                    && strpos($html, 'class="validity-row"') < strpos($html, 'class="qr-code"'));
                check("$position, $kind: the row is black", (bool) preg_match('/\.validity-row \{[^}]*color: #000;/', $html));
            } else {
                check("$position, $kind: the top-right line, with the period", ($t[1] ?? null) === $period, $t[1] ?? 'no line');
                check("$position, $kind: and no row under Date Issued", !$r);
                check("$position, $kind: still white", (bool) preg_match('/\.validity \{[^}]*color: #fff;/', $html));
            }
        }

        // The list's zip download builds each card in the browser from a template.
        $list = $http('GET', '/admin/admission/id-card')->getContent();
        $template = '${student.validity}';
        preg_match($topLine, $list, $t);
        preg_match($row, $list, $r);
        if ($under) {
            check("$position, zip: the card template has the row", ($r[1] ?? null) === $template, $r[1] ?? 'no row');
            check("$position, zip: and no top-right line", !$t);
            check("$position, zip: and its style", str_contains($list, '.zip-id-card .validity-row {'));
        } else {
            check("$position, zip: the card template has the top-right line", ($t[1] ?? null) === $template, $t[1] ?? 'no line');
            check("$position, zip: and no row", !$r);
        }
    } finally {
        DB::rollBack();
    }
}

echo "\n== The barcode ==\n";
check('the setting has a barcode position', Schema::hasColumn('id_card_settings', 'barcode_position'));
check('an existing card keeps the framed barcode at the left', $setting->barcode_position === IdCardSetting::BARCODE_LEFT, (string) $setting->barcode_position);
$page = $http('GET', '/admin/admission/id-card-setting')->getContent();
check('the settings screen offers both', str_contains($page, 'name="barcode_position"')
    && str_contains($page, 'value="left"') && str_contains($page, 'value="beside_qr"'));

$saveBarcode = function (?string $position) use ($http, $setting) {
    $data = [
        'id' => $setting->id, 'slug' => 'student-card',
        'title' => $setting->title, 'subtitle' => $setting->subtitle, 'validity' => $setting->validity,
        'address' => $setting->address, 'prefix' => $setting->prefix, 'validity_position' => $setting->validity_position,
    ];
    if ($position !== null) {
        $data['barcode_position'] = $position;
    }
    $http('POST', '/admin/admission/id-card-setting', $data);
    return $setting->fresh()->barcode_position;
};
DB::beginTransaction();
try {
    check('saving "beside the QR code" keeps it', $saveBarcode('beside_qr') === 'beside_qr');
    check('a position that is not one of the two is refused', $saveBarcode('upside-down') === 'beside_qr');
    check('a form that does not send it means framed at the left', $saveBarcode(null) === 'left');
    check('and saving it leaves the validity where it was', $setting->fresh()->validity_position === $setting->validity_position);
} finally {
    DB::rollBack();
}

$panel = '/<div class="barcode([^"]*)">/';
foreach (['left' => '', 'beside_qr' => ' barcode-beside'] as $position => $class) {
    DB::beginTransaction();
    try {
        IdCardSetting::whereKey($setting->id)->update(['barcode_position' => $position]);
        foreach (['print' => '/admin/admission/id-card-print/', 'download' => '/admin/admission/id-card-download/'] as $kind => $path) {
            $html = $http('GET', $path . $enrol->id)->getContent();
            preg_match($panel, $html, $m);
            check("$position, $kind: the barcode panel is " . ($class ? 'beside the QR code' : 'framed at the left'),
                ($m[1] ?? null) === $class, 'class "' . ($m[1] ?? 'no barcode') . '"');
            check("$position, $kind: the style that moves it is on the page",
                (bool) preg_match('/\.id-card \.barcode\.barcode-beside \{[^}]*right: 343px;[^}]*border-color: transparent;/', $html));
        }
        // The zip is built in the browser from barcodes the server renders.
        $zip = $http('POST', '/admin/admission/id-card-download-zip', ['students' => (string) $enrol->id])->getData(true);
        preg_match($panel, $zip['students'][0]['barcode'] ?? '', $m);
        check("$position, zip: the barcode it is sent matches", ($m[1] ?? null) === $class, 'class "' . ($m[1] ?? 'no barcode') . '"');
        check("$position, zip: and the list page carries the style",
            str_contains($http('GET', '/admin/admission/id-card')->getContent(), '.zip-id-card .barcode.barcode-beside {'));
    } finally {
        DB::rollBack();
    }
}

echo "\n== Nothing was left behind ==\n";
$now = IdCardSetting::find($setting->id)->only(array_keys($before));
check('the setting is as it was', $now == $before, json_encode($now));
check('no suite setting survived', IdCardSetting::where('slug', 'zz-new-card')->doesntExist());
check('no transaction left open', DB::transactionLevel() === 0);

echo "\n$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
