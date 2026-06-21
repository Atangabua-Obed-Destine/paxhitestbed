<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$students = \App\Models\Student::whereHas('studentEnrolls', function($q) {
    $q->where('status', '1');
})->with(['studentEnrolls' => function($q) {
    $q->where('status', '1')->with('semester');
}])->take(5)->get();

foreach($students as $s) {
    $currentEnroll = $s->studentEnrolls->first();
    echo $s->id . " - " . $s->first_name . " " . $s->last_name;
    if ($currentEnroll && $currentEnroll->semester) {
        echo " (Current: " . $currentEnroll->semester->title . ")";
    }
    echo "\n";
}
