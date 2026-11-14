<?php
/**
 * Where the student ID card prints its validity.
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
$before = $setting->only(['validity_position', 'title', 'subtitle', 'validity', 'address', 'prefix', 'updated_at']);
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

echo "\n== Nothing was left behind ==\n";
$now = IdCardSetting::find($setting->id)->only(array_keys($before));
check('the setting is as it was', $now == $before, json_encode($now));
check('no suite setting survived', IdCardSetting::where('slug', 'zz-new-card')->doesntExist());
check('no transaction left open', DB::transactionLevel() === 0);

echo "\n$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
