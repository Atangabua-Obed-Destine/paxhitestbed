<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Http\Kernel');

// Create a fake AJAX request
$request = Illuminate\Http\Request::create(
    '/admin/catholic-student',
    'GET',
    [],
    [], // cookies
    [], // files
    ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
);

try {
    $response = $kernel->handle($request);
    
    echo "Status Code: " . $response->getStatusCode() . "\n";
    echo "Content Type: " . $response->headers->get('Content-Type') . "\n";
    echo "Response Length: " . strlen($response->getContent()) . "\n\n";
    
    if ($response->getStatusCode() !== 200) {
        echo "Error Response:\n";
        echo substr($response->getContent(), 0, 500) . "\n";
    } else {
        echo "Success! Response preview:\n";
        echo substr($response->getContent(), 0, 300) . "\n";
    }
    
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nTrace:\n" . $e->getTraceAsString() . "\n";
}

$kernel->terminate($request, $response ?? null);
