<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Adding Catholic Students translation...\n\n";

$translations = [
    ['id' => 'module_catholic_student', 'en' => 'Baptism Students', 'created_at' => now(), 'updated_at' => now()],
];

foreach ($translations as $translation) {
    $exists = DB::table('languages')->where('id', $translation['id'])->exists();
    
    if (!$exists) {
        DB::table('languages')->insert($translation);
        echo "✓ Added translation: {$translation['id']} = {$translation['en']}\n";
    } else {
        echo "- Translation already exists: {$translation['id']}\n";
    }
}

echo "\nTranslations added successfully!\n";
