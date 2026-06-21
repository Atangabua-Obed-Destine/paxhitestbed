<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Update programs with academic levels
DB::unprepared("
    UPDATE programs p
    INNER JOIN degree_types dt ON p.degree_type_id = dt.id
    SET p.academic_level = CASE
        WHEN dt.title LIKE '%PhD%' OR dt.title LIKE '%Doctor%' OR dt.level LIKE '%Doctor%' THEN 'D'
        WHEN dt.is_postgraduate = 1 OR dt.level LIKE '%Postgraduate%' OR dt.level LIKE '%Master%' THEN 'M'
        ELSE 'A'
    END
");

echo "Programs updated successfully with academic levels!\n";

$undergrad = DB::table('programs')->where('academic_level', 'A')->count();
$masters = DB::table('programs')->where('academic_level', 'M')->count();
$doctoral = DB::table('programs')->where('academic_level', 'D')->count();

echo "Undergraduate programs (A): $undergrad\n";
echo "Masters programs (M): $masters\n";
echo "Doctoral programs (D): $doctoral\n";
