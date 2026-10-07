<?php

/**
 * The tap-to-dial shortcode, and the matricule as the payment reference.
 *
 * Paying meant reading a merchant number off the screen and typing a USSD
 * string by hand, which is the commonest way a payment goes to the wrong place
 * or for the wrong amount. The school now stores the pattern once and the
 * portal builds the code and the dial link.
 *
 * Most of this suite is the encoding: a tel: link whose # is not escaped
 * reaches the dialler with its terminator stripped, so the USSD session never
 * starts and the student sees a dialler full of digits and nothing happening.
 *
 * Writes happen inside transactions that are rolled back.
 *
 *   php scripts/platform_fee_ussd_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\PlatformFeeExemption;
use App\Models\PlatformFeePayment;
use App\Models\PlatformFeeSetting;
use App\Models\Student;

$passed = 0;
$failed = 0;
$skipped = 0;

function check(string $what, bool $ok, string $detail = ''): void
{
    global $passed, $failed;

    if ($ok) {
        $passed++;
        echo "  PASS  $what\n";
    } else {
        $failed++;
        echo "  FAIL  $what" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

function skip(string $why): void
{
    global $skipped;
    $skipped++;
    echo "  SKIP  $why\n";
}

function section(string $title): void
{
    echo "\n" . $title . "\n";
}

// ---------------------------------------------------------------------------
section('The code the student is asked to dial');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    $setting = PlatformFeeSetting::first() ?: PlatformFeeSetting::create([]);

    $cases = [
        ['*126*4*123456*{amount}#', 2000, '*126*4*123456*2000#'],
        ['*126*4*123456*{AMOUNT}#', 2000, '*126*4*123456*2000#'],
        ['*126*4*123456*AMOUNT#',   2000, '*126*4*123456*2000#'],
        // A fee stored as 2000.00 must not reach the network as "2000.00".
        ['*126*4*123456*{amount}#', 2000.00, '*126*4*123456*2000#'],
        ['*127*{amount}*9988#',     500,  '*127*500*9988#'],
    ];

    foreach ($cases as [$template, $amount, $expected]) {
        $setting->update(['ussd_template' => $template, 'fee_amount' => $amount]);
        $built = $setting->fresh()->dialCode();

        check("{$template} at {$amount} becomes {$expected}", $built === $expected, (string) $built);
    }

    $setting->update(['ussd_template' => null]);
    check('no pattern means no code', $setting->fresh()->dialCode() === null);
    check('and nothing to tap', !$setting->fresh()->hasDialCode());
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('The link the phone acts on');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    $setting = PlatformFeeSetting::first();
    $setting->update(['ussd_template' => '*126*4*123456*{amount}#', 'fee_amount' => 2000]);
    $link = $setting->fresh()->dialLink();

    check('it is a tel: link', str_starts_with((string) $link, 'tel:'), (string) $link);
    check('the hash is percent-encoded, or the dialler loses the terminator',
        str_contains((string) $link, '%23') && !str_contains((string) $link, '#'), (string) $link);
    check('the asterisks are left alone, or some diallers do not recognise it',
        str_contains((string) $link, '*126*4*123456*2000'), (string) $link);
    check('the whole link is exactly what is expected',
        $link === 'tel:*126*4*123456*2000%23', (string) $link);
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('The admin screen only accepts a pattern that will work');
// ---------------------------------------------------------------------------
$admin = \App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first() ?? \App\User::first();

if (!$admin) {
    skip('no admin user to act as');
} else {
    app('session.store')->start();
    $token = csrf_token();

    $save = function (string $template) use ($kernel, $admin, $token) {
        $request = Request::create('/admin/platform-fee/settings', 'POST', [
            '_token' => $token,
            'title' => 'Platform Access Fee',
            'fee_amount' => 2000,
            'ussd_template' => $template,
            'is_enabled' => '1',
        ]);
        $request->setLaravelSession(app('session.store'));
        Auth::guard('web')->login($admin);

        return $kernel->handle($request);
    };

    foreach ([
        ['*126*4*123456*{amount}#', true,  'a pattern with the amount placeholder is accepted'],
        ['*126*4*123456*2000#',     false, 'a pattern with the amount written in is refused'],
        ['126*4*123456*{amount}#',  false, 'a pattern not starting with * is refused'],
        ['*126*4*123456*{amount}',  false, 'a pattern not ending with # is refused'],
    ] as [$template, $shouldSave, $label]) {
        DB::beginTransaction();

        try {
            PlatformFeeSetting::first()->update(['ussd_template' => null]);
            $save($template);
            $stored = PlatformFeeSetting::first()->fresh()->ussd_template;

            check($label, $shouldSave ? $stored === $template : $stored === null,
                'stored: ' . var_export($stored, true));
        } finally {
            DB::rollBack();
        }
    }
}

// ---------------------------------------------------------------------------
section('What the student actually sees');
// ---------------------------------------------------------------------------
$student = Student::whereHas('enrolls')->whereNotNull('student_id')->first();

if (!$student) {
    skip('no student to render the page for');
} else {
    DB::beginTransaction();

    try {
        PlatformFeeSetting::first()->update([
            'ussd_template' => '*126*4*123456*{amount}#',
            'fee_amount' => 2000,
            'is_enabled' => 1,
            'merchant_name' => 'PAX HIGHER INSTITUTE',
        ]);

        // Nothing paid, nothing excused, so the payment page is what they get.
        $access = app(\App\Services\PlatformFeeAccess::class);
        $enrollment = $access->currentEnrollment($student);
        $theirs = $access->enrollmentsInSameSession($student, $enrollment);
        PlatformFeePayment::whereIn('student_enroll_id', $theirs)->delete();
        PlatformFeeExemption::whereIn('student_enroll_id', $theirs)->delete();
        if ($enrollment->session_id) {
            PlatformFeeExemption::where('exemption_type', 'session')
                ->where('session_id', $enrollment->session_id)->delete();
        }

        app('session.store')->start();
        $request = Request::create('/student/platform-fee/payment', 'GET');
        $request->setLaravelSession(app('session.store'));
        Auth::guard('student')->login($student);
        $response = $kernel->handle($request);
        $body = $response->getContent();

        $matricule = $enrollment->matricule ?? $student->student_id;

        check('the page loads', $response->getStatusCode() === 200, (string) $response->getStatusCode());
        check('it offers a tappable dial link',
            str_contains($body, 'tel:*126*4*123456*2000%23'), 'no dial link found');
        check('it shows the code to dial by hand as well',
            str_contains($body, '*126*4*123456*2000#'), 'no readable code found');
        check('it tells them to use their matricule as the reference',
            stripos($body, 'matricule') !== false && stripos($body, 'reference') !== false);
        check('and shows the matricule itself', str_contains($body, (string) $matricule), $matricule);
        check('it names who they are paying, so they can check before entering a PIN',
            str_contains($body, 'PAX HIGHER INSTITUTE'));
        check('it walks them through it in numbered steps',
            str_contains($body, 'How to pay'));
        check('and says the fee covers the whole year',
            stripos($body, 'academic year') !== false);

        // With no pattern set, the page must still work — just without the button.
        PlatformFeeSetting::first()->update(['ussd_template' => null]);

        $request = Request::create('/student/platform-fee/payment', 'GET');
        $request->setLaravelSession(app('session.store'));
        Auth::guard('student')->login($student);
        $plain = $kernel->handle($request)->getContent();

        check('without a shortcode the page still loads',
            str_contains($plain, 'How to pay'));
        check('and offers no dial link', !str_contains($plain, 'tel:*'));
        check('but still asks for the matricule as the reference',
            str_contains($plain, (string) $matricule));
    } finally {
        DB::rollBack();
    }
}

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";

exit($failed > 0 ? 1 : 0);
