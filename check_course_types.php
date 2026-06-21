<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== enroll_subjects columns ===\n";
$cols = DB::select("SHOW COLUMNS FROM enroll_subjects");
foreach ($cols as $c) {
    echo "{$c->Field} | Type: {$c->Type} | Default: {$c->Default}\n";
}

echo "\n=== enroll_subject_subject columns ===\n";
$cols2 = DB::select("SHOW COLUMNS FROM enroll_subject_subject");
foreach ($cols2 as $c) {
    echo "{$c->Field} | Type: {$c->Type} | Default: {$c->Default}\n";
}

echo "\n=== enroll_subject_subject type distribution ===\n";
try {
    $dist = DB::table('enroll_subject_subject')->select('subject_type', DB::raw('count(*) as cnt'))
        ->groupBy('subject_type')->get();
    foreach ($dist as $d) {
        echo "subject_type={$d->subject_type} => count={$d->cnt}\n";
    }
} catch(\Exception $e) {
    echo "No subject_type in enroll_subject_subject\n";
}

echo "\n=== Check subjects with specific codes that should be UR ===\n";
// Look for typical UR subject patterns
$urs = DB::table('subjects')->select('id', 'code', 'title', 'subject_type')
    ->where(function($q) {
        $q->where('code', 'like', 'GEN%')
          ->orWhere('code', 'like', 'UNI%')
          ->orWhere('code', 'like', 'GNS%')
          ->orWhere('code', 'like', 'GST%')
          ->orWhere('title', 'like', '%University%')
          ->orWhere('title', 'like', '%General%');
    })->get();
foreach ($urs as $u) {
    echo "id={$u->id} | code={$u->code} | type={$u->subject_type} | {$u->title}\n";
}

echo "\n=== ALL subjects with their types ===\n";
$all = DB::table('subjects')->select('id', 'code', 'title', 'subject_type')->orderBy('subject_type')->orderBy('code')->get();
foreach ($all as $s) {
    $lbl = match($s->subject_type) { 1 => 'Core', 2 => 'Elective', 3 => 'UR', default => "Unknown({$s->subject_type})" };
    echo "id={$s->id} | type={$s->subject_type}({$lbl}) | {$s->code} | {$s->title}\n";
}


