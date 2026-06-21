<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get the Gemini service
$gemini = app(\App\Services\GeminiService::class);

echo "Testing Gemini API...\n";
echo "API Key Set: " . (config('services.gemini.api_key') ? 'Yes' : 'No') . "\n";
echo "API Key Length: " . strlen(config('services.gemini.api_key') ?? '') . "\n\n";

try {
    echo "Generating book summary...\n";
    
    $summary = $gemini->generateBookSummary(
        'The Great Gatsby',
        'F. Scott Fitzgerald',
        'A novel set in the Jazz Age that tells the story of Jay Gatsby and his love for Daisy Buchanan.'
    );
    
    if ($summary) {
        echo "\n✓ SUCCESS! Generated summary:\n";
        echo str_repeat('-', 80) . "\n";
        echo $summary . "\n";
        echo str_repeat('-', 80) . "\n";
    } else {
        echo "\n✗ FAILED: Summary is null\n";
    }
    
} catch (\Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\nCheck storage/logs/laravel.log for detailed logs.\n";
