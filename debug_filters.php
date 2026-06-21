<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$programs = App\Models\Program::where('status', 1)->get(['id', 'title', 'faculty_id']);
echo "Active programs:\n";
foreach ($programs as $p) {
    echo "  ID={$p->id}, Faculty={$p->faculty_id}, {$p->title}\n";
}

foreach ($programs as $p) {
    $sessCount = App\Models\Session::where('status', 1)
        ->whereHas('programs', function($q) use ($p) { $q->where('program_id', $p->id); })
        ->count();
    $semCount = App\Models\Semester::where('status', 1)
        ->whereHas('programs', function($q) use ($p) { $q->where('program_id', $p->id); })
        ->count();
    echo "Program {$p->id}: Sessions={$sessCount}, Semesters={$semCount}\n";
}
