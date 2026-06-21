<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EBook;
use App\Models\EBookCategory;
use App\Models\EBookReading;
use App\Models\EBookFavorite;
use App\Models\EBookReview;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Services\GeminiService;

class ELibraryController extends Controller
{
    /**
     * Get the current student ID
     */
    protected function getStudentId()
    {
        return Auth::guard('student')->id();
    }
    
    /**
     * Check if student is authenticated
     */
    protected function isStudentAuthenticated()
    {
        return Auth::guard('student')->check();
    }

    /**
     * Display the library home page
     */
    public function index(Request $request)
    {
        $data['title'] = 'E-Library';
        
        // Featured books
        $data['featuredBooks'] = EBook::where('status', 1)
                                       ->where('featured', 1)
                                       ->with('category')
                                       ->take(6)
                                       ->get();
        
        // Recent books
        $data['recentBooks'] = EBook::where('status', 1)
                                     ->with('category')
                                     ->orderBy('created_at', 'desc')
                                     ->take(12)
                                     ->get();
        
        // Popular books (most viewed)
        $data['popularBooks'] = EBook::where('status', 1)
                                      ->with('category')
                                      ->orderBy('views_count', 'desc')
                                      ->take(8)
                                      ->get();
        
        // Categories
        $data['categories'] = EBookCategory::where('status', 1)
                                          ->withCount('activeBooks')
                                          ->orderBy('sort_order')
                                          ->get();
        
        // User's reading progress
        if ($this->isStudentAuthenticated()) {
            $data['continueReading'] = EBookReading::where('student_id', $this->getStudentId())
                                                    ->where('completed_at', null)
                                                    ->with('book')
                                                    ->orderBy('last_read_at', 'desc')
                                                    ->take(4)
                                                    ->get();
        } else {
            $data['continueReading'] = collect();
        }
        
        return view('student.e-library.index', $data);
    }

    /**
     * Browse books with filters
     */
    public function browse(Request $request)
    {
        $data['title'] = 'Browse Books';
        
        $query = EBook::where('status', 1)->with('category');
        
        // Search
        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', '%'.$search.'%')
                  ->orWhere('description', 'like', '%'.$search.'%')
                  ->orWhere('authors', 'like', '%'.$search.'%')
                  ->orWhere('publisher', 'like', '%'.$search.'%');
            });
        }
        
        // Filter by category
        if ($request->has('category') && $request->category != '') {
            $query->where('category_id', $request->category);
        }
        
        // Filter by language
        if ($request->has('language') && $request->language != '') {
            $query->where('language', $request->language);
        }
        
        // Filter by source
        if ($request->has('source') && $request->source != '') {
            $query->where('source', $request->source);
        }
        
        // Sort
        $sort = $request->input('sort', 'recent');
        switch ($sort) {
            case 'popular':
                $query->orderBy('views_count', 'desc');
                break;
            case 'rating':
                $query->orderBy('rating_avg', 'desc');
                break;
            case 'title':
                $query->orderBy('title', 'asc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
        }
        
        $data['books'] = $query->paginate(24);
        $data['categories'] = EBookCategory::where('status', 1)->orderBy('sort_order')->get();
        
        return view('student.e-library.browse', $data);
    }

    /**
     * Show book details
     */
    public function show($id)
    {
        $book = EBook::with(['category', 'reviews.student'])->findOrFail($id);
        
        // Increment views
        $book->incrementViews();
        
        $data['title'] = $book->title;
        $data['book'] = $book;
        
        // Check if student has favorited
        if ($this->isStudentAuthenticated()) {
            $studentId = $this->getStudentId();
            $data['isFavorited'] = EBookFavorite::where('student_id', $studentId)->where('e_book_id', $book->id)->exists();
            $data['userReading'] = EBookReading::where('student_id', $studentId)->where('e_book_id', $book->id)->first();
            $data['userReview'] = EBookReview::where('e_book_id', $book->id)
                                              ->where('student_id', $studentId)
                                              ->first();
        } else {
            $data['isFavorited'] = false;
            $data['userReading'] = null;
            $data['userReview'] = null;
        }
        
        // Related books (same category)
        $data['relatedBooks'] = EBook::where('status', 1)
                                      ->where('category_id', $book->category_id)
                                      ->where('id', '!=', $book->id)
                                      ->take(6)
                                      ->get();
        
        return view('student.e-library.show', $data);
    }

    /**
     * Read book online
     */
    public function read($id)
    {
        $book = EBook::findOrFail($id);
        
        $data['title'] = 'Reading: ' . $book->title;
        $data['book'] = $book;
        
        // Create or update reading record
        if ($this->isStudentAuthenticated()) {
            $reading = EBookReading::firstOrCreate(
                ['student_id' => $this->getStudentId(), 'e_book_id' => $book->id],
                ['started_at' => now(), 'last_read_at' => now()]
            );
            
            $data['reading'] = $reading;
        }
        
        return view('student.e-library.read', $data);
    }

    /**
     * Download book
     */
    public function download($id)
    {
        $book = EBook::findOrFail($id);
        
        if (!$book->is_downloadable || $book->source != 'local') {
            flash()->addError('This book is not available for download.');
            return redirect()->back();
        }
        
        $filePath = public_path('uploads/e-library/books/' . $book->file_path);
        
        if (!file_exists($filePath)) {
            flash()->addError('Book file not found.');
            return redirect()->back();
        }
        
        // Increment downloads
        $book->incrementDownloads();
        
        return response()->download($filePath, $book->title . '.' . $book->file_type);
    }

    /**
     * Toggle favorite
     */
    public function toggleFavorite($id)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Please login to add favorites'], 401);
        }
        
        $book = EBook::findOrFail($id);
        $userId = Auth::id();
        
        $favorite = EBookFavorite::where('user_id', $userId)
                                 ->where('e_book_id', $id)
                                 ->first();
        
        if ($favorite) {
            $favorite->delete();
            $book->decrement('favorites_count');
            $message = 'Removed from favorites';
            $favorited = false;
        } else {
            EBookFavorite::create([
                'user_id' => $userId,
                'e_book_id' => $id
            ]);
            $book->increment('favorites_count');
            $message = 'Added to favorites';
            $favorited = true;
        }
        
        return response()->json([
            'success' => true,
            'message' => $message,
            'favorited' => $favorited,
            'favorites_count' => $book->favorites_count
        ]);
    }

    /**
     * User's favorites
     */
    public function favorites()
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }
        
        $data['title'] = 'My Favorites';
        $data['favorites'] = EBookFavorite::where('user_id', Auth::id())
                                          ->with('book.category')
                                          ->orderBy('created_at', 'desc')
                                          ->paginate(24);
        
        return view('student.e-library.favorites', $data);
    }

    /**
     * User's reading history
     */
    public function history()
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }
        
        $data['title'] = 'Reading History';
        $data['readings'] = EBookReading::where('user_id', Auth::id())
                                        ->with('book.category')
                                        ->orderBy('last_read_at', 'desc')
                                        ->paginate(24);
        
        return view('student.e-library.history', $data);
    }

    /**
     * Submit or update review
     */
    public function submitReview(Request $request, $id)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Please login to submit a review'], 401);
        }
        
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:1000'
        ]);
        
        try {
            $review = EBookReview::updateOrCreate(
                ['user_id' => Auth::id(), 'e_book_id' => $id],
                [
                    'rating' => $request->rating,
                    'review' => $request->review,
                    'status' => 1
                ]
            );
            
            // Update book rating average
            $book = EBook::findOrFail($id);
            $avgRating = EBookReview::where('e_book_id', $id)
                                    ->where('status', 1)
                                    ->avg('rating');
            $ratingCount = EBookReview::where('e_book_id', $id)
                                      ->where('status', 1)
                                      ->count();
            
            $book->rating_avg = round($avgRating, 2);
            $book->rating_count = $ratingCount;
            $book->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Review submitted successfully',
                'rating_avg' => $book->rating_avg,
                'rating_count' => $book->rating_count
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update reading progress
     */
    public function updateProgress(Request $request, $id)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        $request->validate([
            'current_page' => 'required|integer|min:1',
            'total_pages' => 'nullable|integer'
        ]);
        
        try {
            $reading = EBookReading::where('user_id', Auth::id())
                                   ->where('e_book_id', $id)
                                   ->firstOrFail();
            
            $reading->updateProgress($request->current_page, $request->total_pages);
            
            return response()->json([
                'success' => true,
                'progress_percentage' => $reading->progress_percentage
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Search books (AJAX)
     */
    public function search(Request $request)
    {
        $query = $request->input('q');
        
        if (!$query) {
            return response()->json([]);
        }
        
        $books = EBook::where('status', 1)
                     ->where(function($q) use ($query) {
                         $q->where('title', 'like', '%'.$query.'%')
                           ->orWhere('authors', 'like', '%'.$query.'%');
                     })
                     ->with('category')
                     ->take(10)
                     ->get();
        
        return response()->json($books);
    }

    /**
     * Category books
     */
    public function category($slug)
    {
        $category = EBookCategory::where('slug', $slug)->firstOrFail();
        
        $data['title'] = $category->name;
        $data['category'] = $category;
        $data['books'] = EBook::where('status', 1)
                             ->where('category_id', $category->id)
                             ->with('category')
                             ->orderBy('created_at', 'desc')
                             ->paginate(24);
        
        return view('student.e-library.category', $data);
    }

    /**
     * Get AI-powered book recommendations
     */
    public function recommendations(Request $request, GeminiService $gemini)
    {
        $data['title'] = 'AI Book Recommendations';
        
        $userId = Auth::id();
        
        // Get user's reading history
        $readingHistory = EBookReading::where('user_id', $userId)
                                      ->with(['book.category'])
                                      ->orderBy('updated_at', 'desc')
                                      ->get();
        
        // Get user's favorites
        $favorites = EBookFavorite::where('user_id', $userId)
                                  ->with(['book.category'])
                                  ->get();
        
        if ($readingHistory->isEmpty() && $favorites->isEmpty()) {
            $data['no_history'] = true;
            $data['popular_books'] = EBook::where('status', 1)
                                         ->with('category')
                                         ->orderBy('views_count', 'desc')
                                         ->take(12)
                                         ->get();
            return view('student.e-library.recommendations', $data);
        }
        
        // Generate AI recommendations
        $data['loading'] = true;
        $data['recommendations'] = $gemini->generateRecommendations($readingHistory, $favorites, 8);
        
        // Also get similar books from database for backup
        $userCategories = $readingHistory->pluck('book.category_id')->unique();
        $data['similar_books'] = EBook::where('status', 1)
                                     ->whereIn('category_id', $userCategories)
                                     ->whereNotIn('id', $readingHistory->pluck('book_id'))
                                     ->with('category')
                                     ->inRandomOrder()
                                     ->take(8)
                                     ->get();
        
        return view('student.e-library.recommendations', $data);
    }

    /**
     * Generate AI summary for a book (AJAX)
     */
    public function generateSummary(Request $request, $id, GeminiService $gemini)
    {
        try {
            $book = EBook::findOrFail($id);
            
            \Log::info('Generating AI summary for book', [
                'book_id' => $book->id,
                'title' => $book->title,
                'authors' => $book->authors
            ]);
            
            $summary = $gemini->generateBookSummary(
                $book->title,
                $book->authors,
                $book->description
            );
            
            \Log::info('AI summary generated', [
                'summary_length' => strlen($summary ?? ''),
                'summary_preview' => substr($summary ?? '', 0, 100)
            ]);
            
            return response()->json([
                'success' => true,
                'summary' => $summary
            ]);
            
        } catch (\Exception $e) {
            \Log::error('AI summary generation failed', [
                'book_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate summary. Please try again.'
            ], 500);
        }
    }

    /**
     * AI-powered natural language search
     */
    public function aiSearch(Request $request, GeminiService $gemini)
    {
        $query = $request->input('q');
        
        if (!$query) {
            return response()->json([]);
        }
        
        try {
            // Get sample of available books
            $availableBooks = EBook::where('status', 1)
                                   ->with('category')
                                   ->get();
            
            // Process with AI
            $aiResults = $gemini->processNaturalSearch($query, $availableBooks);
            
            if ($aiResults && is_array($aiResults)) {
                // Get book IDs from AI results
                $bookIds = collect($aiResults)->pluck('id')->toArray();
                
                // Fetch actual books in the order suggested by AI
                $books = EBook::whereIn('id', $bookIds)
                             ->with('category')
                             ->get()
                             ->sortBy(function($book) use ($bookIds) {
                                 return array_search($book->id, $bookIds);
                             })
                             ->values();
                
                return response()->json([
                    'success' => true,
                    'books' => $books,
                    'ai_insights' => $aiResults
                ]);
            }
            
            // Fallback to regular search
            $books = EBook::where('status', 1)
                         ->where(function($q) use ($query) {
                             $q->where('title', 'like', '%'.$query.'%')
                               ->orWhere('authors', 'like', '%'.$query.'%')
                               ->orWhere('description', 'like', '%'.$query.'%');
                         })
                         ->with('category')
                         ->take(10)
                         ->get();
            
            return response()->json([
                'success' => true,
                'books' => $books,
                'fallback' => true
            ]);
            
        } catch (\Exception $e) {
            // Fallback to regular search on error
            $books = EBook::where('status', 1)
                         ->where(function($q) use ($query) {
                             $q->where('title', 'like', '%'.$query.'%')
                               ->orWhere('authors', 'like', '%'.$query.'%');
                         })
                         ->with('category')
                         ->take(10)
                         ->get();
            
            return response()->json([
                'success' => true,
                'books' => $books,
                'fallback' => true
            ]);
        }
    }

    /**
     * Get AI reading insights
     */
    public function insights(Request $request, GeminiService $gemini)
    {
        $data['title'] = 'Reading Insights';
        
        $userId = Auth::id();
        
        // Get reading statistics
        $readingHistory = EBookReading::where('user_id', $userId)
                                      ->with(['book.category'])
                                      ->get();
        
        $data['stats'] = [
            'total_books_read' => $readingHistory->count(),
            'completed_books' => $readingHistory->where('progress_percentage', 100)->count(),
            'in_progress_books' => $readingHistory->where('progress_percentage', '>', 0)
                                                  ->where('progress_percentage', '<', 100)->count(),
            'total_time_spent' => $readingHistory->sum('time_spent') . ' minutes',
            'average_progress' => $readingHistory->avg('progress_percentage') ?? 0,
        ];
        
        if ($readingHistory->isEmpty()) {
            $data['no_data'] = true;
            return view('student.e-library.insights', $data);
        }
        
        // Generate AI insights
        $data['ai_insights'] = $gemini->generateReadingInsights($data['stats'], $readingHistory);
        
        // Category breakdown
        $data['category_stats'] = $readingHistory->groupBy('book.category.name')
                                                 ->map(function($items, $category) {
                                                     return [
                                                         'name' => $category,
                                                         'count' => $items->count(),
                                                         'avg_progress' => $items->avg('progress_percentage')
                                                     ];
                                                 })
                                                 ->sortByDesc('count')
                                                 ->values();
        
        // Reading streaks and patterns
        $data['recent_activity'] = $readingHistory->sortByDesc('updated_at')->take(10);
        
        return view('student.e-library.insights', $data);
    }
}

