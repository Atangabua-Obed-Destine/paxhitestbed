<?php
/**
 * Backfill verification_code for existing FormA3Record entries.
 * Run: php backfill_form_a3_verification_codes.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\FormA3Record;
use Illuminate\Support\Str;

$records = FormA3Record::whereNull('verification_code')->get();
$count = 0;

foreach ($records as $record) {
    $record->verification_code = 'FA3-' . strtoupper(Str::random(16));
    $record->save();
    $count++;
}

echo "Backfilled verification codes for {$count} Form A3 records.\n";
