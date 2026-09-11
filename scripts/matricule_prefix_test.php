<?php
/**
 * Matricules carry the school's own code, not somebody else's.
 *
 * The prefix on every student matricule and staff id was the literal 'PAX',
 * written into three separate generators, so every school running this system
 * issued matricules under one school's name. It now comes from the Academy
 * Code on Settings → General.
 *
 * Two things matter as much as the prefix itself:
 *   - the matricules already issued keep working, including the lookups that
 *     used to ask "does this start with PAX";
 *   - a school that has not set its code is refused, not quietly given PAX,
 *     because a matricule is printed on documents and cannot be recalled.
 *
 * Every write runs inside a transaction that is rolled back.
 *
 * Usage: php scripts/matricule_prefix_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Batch;
use App\Models\Faculty;
use App\Models\Setting;
use App\Models\Student;
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

/** Forget the Setting this request already read, so a changed code is seen. */
function setAcademyCode(?string $code): void
{
    DB::table('settings')->update(['academy_code' => $code]);
}

echo "\n== Nothing is hardcoded any more ==\n";

foreach ([
    'app/Models/Student.php' => "'PAX'",
    'app/User.php' => "'PAX'",
] as $file => $literal) {
    $source = file_get_contents(__DIR__ . '/../' . $file);
    check("{$file} no longer writes " . $literal . " into an id",
        !str_contains($source, $literal . ' . $batchDigits') && !str_contains($source, $literal . ' . str_pad'));
}

$controller = file_get_contents(__DIR__ . '/../app/Http/Controllers/Admin/StudentSingleEnrollController.php');
check('matricule lookups no longer test for a PAX prefix', !str_contains($controller, "'PAX') === 0"));

echo "\n== The code comes from Settings ==\n";

$original = DB::table('settings')->value('academy_code');

check('this installation has a code set', trim((string) $original) !== '',
    'academy_code = ' . var_export($original, true));
check('and it is the one the existing matricules use', strtoupper(trim((string) $original)) === 'PAX',
    'academy_code = ' . var_export($original, true));

$faculty = Faculty::whereNotNull('shortcode')->orWhereNotNull('matric_code')->first();
$batch = Batch::first();

if (!$faculty || !$batch) {
    echo "  SKIP  need a faculty and a batch to generate an id\n";
} else {
    DB::beginTransaction();

    try {
        $issued = Student::generateStudentId($faculty->id, $batch->id);
        check('a new student id starts with the configured code', str_starts_with($issued, 'PAX'), $issued);

        // A different school, same system.
        setAcademyCode('SAH');
        $issued = Student::generateStudentId($faculty->id, $batch->id);
        check('changing the code changes the next matricule', str_starts_with($issued, 'SAH'), $issued);
        check('and it is not the old prefix anywhere in it', !str_contains($issued, 'PAX'), $issued);

        // Staff ids read the digits after the prefix; that offset used to be
        // the number 3, which is only right while the code is three letters.
        $staffId = App\User::generateStaffId();
        check('staff ids use the same code', str_starts_with($staffId, 'SAH'), $staffId);

        setAcademyCode('LONGCODE');
        $staffId = App\User::generateStaffId();
        check('a code longer than three letters still numbers correctly',
            str_starts_with($staffId, 'LONGCODE') && preg_match('/^LONGCODE\d{5}$/', $staffId), $staffId);
    } finally {
        DB::rollBack();
    }
}

echo "\n== A school that has not set its code is refused ==\n";

DB::beginTransaction();

try {
    setAcademyCode(null);

    $refused = false;
    $message = '';

    try {
        Setting::matriculePrefix();
    } catch (\RuntimeException $e) {
        $refused = true;
        $message = $e->getMessage();
    }

    check('asking for the prefix is refused', $refused);
    check('and the message says where to set it', str_contains($message, 'Academy Code') && str_contains($message, 'Settings'), $message);

    if ($faculty && $batch) {
        $blocked = false;

        try {
            Student::generateStudentId($faculty->id, $batch->id);
        } catch (\Throwable $e) {
            $blocked = true;
        }

        check('no student id can be issued until it is set', $blocked);
    }

    setAcademyCode('   ');
    $blankRefused = false;

    try {
        Setting::matriculePrefix();
    } catch (\RuntimeException $e) {
        $blankRefused = true;
    }

    check('a code of only spaces counts as unset', $blankRefused);
} finally {
    DB::rollBack();
}

echo "\n== The matricules already issued still work ==\n";

check('a legacy PAX matricule is recognised', Student::looksLikeMatricule('PAX25AF001'));
check('so is one under another school\'s code', Student::looksLikeMatricule('SAH25AF001'));
check('a numeric record id is not mistaken for one', !Student::looksLikeMatricule('118'));
check('and neither is an empty value', !Student::looksLikeMatricule(''));

$existing = DB::table('students')->whereNotNull('student_id')->value('student_id');

if ($existing) {
    check('the matricules on file are still recognised as matricules', Student::looksLikeMatricule($existing), $existing);
}

echo "\n== The submit button has a label ==\n";

check('btn_submit is translated', __('btn_submit') !== 'btn_submit', __('btn_submit'));

echo "\n== The code is back as it was ==\n";

check('academy_code is unchanged by this test',
    (string) DB::table('settings')->value('academy_code') === (string) $original,
    var_export(DB::table('settings')->value('academy_code'), true) . ' vs ' . var_export($original, true));

echo "\n$passed passed, $failed failed\n";

exit($failed > 0 ? 1 : 0);
