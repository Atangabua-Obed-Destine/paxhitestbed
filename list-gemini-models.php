<?php

$apiKey = 'AIzaSyA8pdDe5nkY0xiq6q9YCQWGZo7Y8T704xc';

echo "Listing available Gemini models...\n\n";

// Try v1 API
$url = "https://generativelanguage.googleapis.com/v1/models?key={$apiKey}";
echo "Calling: {$url}\n\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: {$httpCode}\n";
echo "Response:\n";
echo $response . "\n";
