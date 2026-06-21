<?php
// Simple test to verify OpenLibrary API integration
header('Content-Type: application/json');

// Simulate a search request
$query = $_GET['q'] ?? 'harry potter';

$url = "https://openlibrary.org/search.json?q=" . urlencode($query) . "&limit=5";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    
    if (isset($data['docs'])) {
        $books = array_map(function($book) {
            return [
                'key' => $book['key'] ?? null,
                'title' => $book['title'] ?? 'Unknown',
                'authors' => $book['author_name'] ?? [],
                'first_publish_year' => $book['first_publish_year'] ?? null,
                'isbn' => $book['isbn'][0] ?? null,
                'publisher' => $book['publisher'][0] ?? null,
                'cover_id' => $book['cover_i'] ?? null,
                'number_of_pages' => $book['number_of_pages_median'] ?? null,
            ];
        }, array_slice($data['docs'], 0, 5));
        
        echo json_encode([
            'success' => true,
            'query' => $query,
            'count' => $data['numFound'] ?? 0,
            'books' => $books
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode(['error' => 'No results found', 'response' => $data], JSON_PRETTY_PRINT);
    }
} else {
    echo json_encode(['error' => 'API request failed', 'http_code' => $httpCode], JSON_PRETTY_PRINT);
}
