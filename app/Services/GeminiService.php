<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected $apiKey;
    protected $model;
    // v1beta: the v1 endpoint no longer serves the current flash models.
    protected $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = config('services.gemini.model', 'gemini-3.5-flash');
    }

    /**
     * Generate personalized book recommendations based on user's reading history
     */
    public function generateRecommendations($readingHistory, $favorites = [], $limit = 5)
    {
        try {
            // Build context from user's reading history
            $historyText = $this->buildHistoryContext($readingHistory, $favorites);
            
            $prompt = "Based on this user's reading history and preferences:\n\n{$historyText}\n\n" .
                     "Please recommend {$limit} books that this user would likely enjoy. " .
                     "For each recommendation, provide:\n" .
                     "1. Book title\n" .
                     "2. Author(s)\n" .
                     "3. Brief reason why it matches their interests (1-2 sentences)\n" .
                     "4. Category/genre\n\n" .
                     "Format your response as JSON array with keys: title, author, reason, category";

            $response = $this->generateContent($prompt);
            
            return $this->parseRecommendations($response);
        } catch (\Exception $e) {
            Log::error('Gemini recommendations error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate AI summary of a book
     */
    public function generateBookSummary($bookTitle, $bookAuthors, $bookDescription = null)
    {
        try {
            Log::info('GeminiService: Starting book summary generation', [
                'title' => $bookTitle,
                'authors' => $bookAuthors,
                'has_description' => !empty($bookDescription),
                'api_key_set' => !empty($this->apiKey)
            ]);
            
            $prompt = "Generate a concise, engaging summary of the book:\n\n" .
                     "Title: {$bookTitle}\n" .
                     "Author(s): {$bookAuthors}\n";
            
            if ($bookDescription) {
                $prompt .= "Book Description: {$bookDescription}\n\n";
            }
            
            $prompt .= "Please provide:\n" .
                      "1. A 2-3 paragraph summary of the book's main themes and content\n" .
                      "2. Key topics covered\n" .
                      "3. Who would benefit from reading this book\n\n" .
                      "Keep the tone informative and engaging.";

            Log::info('GeminiService: Calling API with prompt', [
                'prompt_length' => strlen($prompt)
            ]);
            
            $response = $this->generateContent($prompt);
            
            Log::info('GeminiService: Received response', [
                'response_length' => strlen($response ?? ''),
                'response_preview' => substr($response ?? '', 0, 100)
            ]);
            
            return $response;
        } catch (\Exception $e) {
            Log::error('Gemini summary error: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Process natural language search query
     */
    public function processNaturalSearch($query, $availableBooks)
    {
        try {
            // Build context of available books (limit to prevent token overflow)
            $booksContext = $this->buildBooksContext($availableBooks);
            
            $prompt = "A user is searching for books with this query: \"{$query}\"\n\n" .
                     "Available books in the library:\n{$booksContext}\n\n" .
                     "Based on the user's query, identify which books best match their intent. " .
                     "Consider:\n" .
                     "- Keywords and topics mentioned\n" .
                     "- Genre preferences implied\n" .
                     "- Reading level or complexity hints\n" .
                     "- Any specific requirements\n\n" .
                     "Return a JSON array of book IDs that match, ordered by relevance (best match first). " .
                     "Include a 'reason' field explaining why each book matches.\n" .
                     "Format: [{\"id\": 1, \"relevance_score\": 95, \"reason\": \"explanation\"}]";

            $response = $this->generateContent($prompt);
            
            return $this->parseSearchResults($response);
        } catch (\Exception $e) {
            Log::error('Gemini natural search error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate reading insights and suggestions
     */
    public function generateReadingInsights($readingStats, $readingHistory)
    {
        try {
            $statsText = $this->buildStatsContext($readingStats, $readingHistory);
            
            $prompt = "Analyze this user's reading behavior and provide insights:\n\n{$statsText}\n\n" .
                     "Please provide:\n" .
                     "1. Reading pattern analysis (preferred genres, reading speed, engagement level)\n" .
                     "2. Strengths (what they're doing well)\n" .
                     "3. Suggestions for improvement or exploration\n" .
                     "4. Personalized reading goals\n" .
                     "5. Genre diversity recommendations\n\n" .
                     "Keep the tone encouraging and constructive. Format as structured sections.";

            $response = $this->generateContent($prompt);
            
            return $response;
        } catch (\Exception $e) {
            Log::error('Gemini insights error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Answer questions about a book
     */
    public function answerBookQuestion($bookTitle, $bookAuthors, $bookDescription, $question)
    {
        try {
            $prompt = "Book Information:\n" .
                     "Title: {$bookTitle}\n" .
                     "Author(s): {$bookAuthors}\n" .
                     "Description: {$bookDescription}\n\n" .
                     "User Question: {$question}\n\n" .
                     "Please provide a helpful, accurate answer based on the book information provided. " .
                     "If you don't have enough information to answer confidently, say so.";

            $response = $this->generateContent($prompt);
            
            return $response;
        } catch (\Exception $e) {
            Log::error('Gemini Q&A error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Core method to call Gemini API
     */
    protected function generateContent($prompt)
    {
        Log::info('GeminiService: generateContent called', [
            'api_key_set' => !empty($this->apiKey),
            'api_key_length' => strlen($this->apiKey ?? ''),
            'base_url' => $this->baseUrl
        ]);
        
        $url = "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";
        
        Log::info('GeminiService: Making API request', [
            'url' => substr($url, 0, 100) . '...'
        ]);
        
        $response = Http::timeout(30)->post($url, [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 2048,
            ]
        ]);

        Log::info('GeminiService: API response received', [
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body_length' => strlen($response->body())
        ]);

        if ($response->failed()) {
            Log::error('GeminiService: API request failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new \Exception('Gemini API request failed: ' . $response->body());
        }

        $data = $response->json();
        
        Log::info('GeminiService: Parsed JSON response', [
            'has_candidates' => isset($data['candidates']),
            'candidates_count' => isset($data['candidates']) ? count($data['candidates']) : 0
        ]);
        
        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            Log::error('GeminiService: Invalid response format', [
                'data_keys' => array_keys($data),
                'full_response' => json_encode($data)
            ]);
            throw new \Exception('Invalid response format from Gemini API');
        }

        $text = $data['candidates'][0]['content']['parts'][0]['text'];
        
        Log::info('GeminiService: Successfully extracted text', [
            'text_length' => strlen($text)
        ]);

        return $text;
    }

    /**
     * Helper: Build reading history context
     */
    protected function buildHistoryContext($readingHistory, $favorites)
    {
        $context = "Books read or currently reading:\n";
        
        foreach ($readingHistory->take(15) as $reading) {
            $book = $reading->book;
            $progress = $reading->progress_percentage;
            $context .= "- \"{$book->title}\" by {$book->authors} (Category: {$book->category->name}, Progress: {$progress}%)\n";
        }
        
        if ($favorites->count() > 0) {
            $context .= "\nFavorite books:\n";
            foreach ($favorites->take(10) as $favorite) {
                $book = $favorite->book;
                $context .= "- \"{$book->title}\" by {$book->authors} (Category: {$book->category->name})\n";
            }
        }
        
        return $context;
    }

    /**
     * Helper: Build books context for search
     */
    protected function buildBooksContext($books)
    {
        $context = "";
        
        // Limit to prevent token overflow (max 50 books)
        foreach ($books->take(50) as $book) {
            $context .= "ID: {$book->id} | \"{$book->title}\" by {$book->authors} | " .
                       "Category: {$book->category->name} | " .
                       "Description: " . substr($book->description ?? 'No description', 0, 100) . "...\n";
        }
        
        return $context;
    }

    /**
     * Helper: Build stats context
     */
    protected function buildStatsContext($stats, $readingHistory)
    {
        $context = "Reading Statistics:\n";
        $context .= "- Total books read: {$stats['total_books_read']}\n";
        $context .= "- Books completed: {$stats['completed_books']}\n";
        $context .= "- Books in progress: {$stats['in_progress_books']}\n";
        $context .= "- Total reading time: {$stats['total_time_spent']}\n";
        $context .= "- Average progress: {$stats['average_progress']}%\n\n";
        
        // Category breakdown
        $categoryStats = $readingHistory->groupBy('book.category.name')->map(function ($items) {
            return $items->count();
        })->sortDesc();
        
        $context .= "Books by Category:\n";
        foreach ($categoryStats->take(5) as $category => $count) {
            $context .= "- {$category}: {$count} books\n";
        }
        
        return $context;
    }

    /**
     * Helper: Parse recommendations from AI response
     */
    protected function parseRecommendations($response)
    {
        // Try to extract JSON from response
        if (preg_match('/\[[\s\S]*\]/', $response, $matches)) {
            try {
                $recommendations = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $recommendations;
                }
            } catch (\Exception $e) {
                // Fall through to text parsing
            }
        }
        
        // Fallback: Return formatted response as-is
        return [
            'raw_text' => $response,
            'parsed' => false
        ];
    }

    /**
     * Helper: Parse search results from AI response
     */
    protected function parseSearchResults($response)
    {
        // Try to extract JSON from response
        if (preg_match('/\[[\s\S]*\]/', $response, $matches)) {
            try {
                $results = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $results;
                }
            } catch (\Exception $e) {
                // Fall through
            }
        }
        
        return null;
    }
}
