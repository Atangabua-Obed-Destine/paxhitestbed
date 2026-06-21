<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EBook;
use App\Models\EBookCategory;
use App\Models\EBookReview;
use App\Models\EBookReading;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Flasher\Prime\FlasherInterface;

class ELibraryController extends Controller
{
    protected $path = 'e-library';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:e-library-view', ['only' => ['index', 'show', 'categories', 'searchInternetArchive', 'statistics']]);
        $this->middleware('permission:e-library-create', ['only' => ['create', 'store', 'storeCategory', 'importFromInternetArchive']]);
        $this->middleware('permission:e-library-edit', ['only' => ['edit', 'update', 'updateCategory']]);
        $this->middleware('permission:e-library-delete', ['only' => ['destroy', 'destroyCategory']]);
    }

    /**
     * Display a listing of the e-books
     */
    public function index(Request $request)
    {
        $data['title'] = 'E-Library Management';
        
        $query = EBook::with(['category', 'uploader']);
        
        // Search filters
        if ($request->has('search') && $request->search != '') {
            $query->where(function($q) use ($request) {
                $q->where('title', 'like', '%'.$request->search.'%')
                  ->orWhere('isbn', 'like', '%'.$request->search.'%')
                  ->orWhere('publisher', 'like', '%'.$request->search.'%');
            });
        }
        
        if ($request->has('category') && $request->category != '') {
            $query->where('category_id', $request->category);
        }
        
        if ($request->has('source') && $request->source != '') {
            $query->where('source', $request->source);
        }
        
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }
        
        $data['books'] = $query->orderBy('created_at', 'desc')->paginate(20);
        $data['categories'] = EBookCategory::where('status', 1)->orderBy('sort_order')->get();
        
        return view('admin.e-library.index', $data);
    }

    /**
     * Show the form for creating a new e-book
     */
    public function create()
    {
        $data['title'] = 'Add New Book';
        $data['categories'] = EBookCategory::where('status', 1)->orderBy('sort_order')->get();
        
        return view('admin.e-library.create', $data);
    }

    /**
     * Store a newly created e-book
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'category_id' => 'required|exists:e_book_categories,id',
            'file' => 'required|file|mimes:pdf,epub|max:51200', // Max 50MB
            'cover_image' => 'nullable|image|max:5120', // Max 5MB
            'isbn' => 'nullable|string',
            'publisher' => 'nullable|string',
            'publish_date' => 'nullable|string',
            'number_of_pages' => 'nullable|integer',
            'description' => 'nullable|string',
            'language' => 'nullable|string',
            'is_downloadable' => 'nullable|boolean',
        ]);

        try {
            $book = new EBook();
            $book->source = 'local';
            $book->title = $request->title;
            $book->subtitle = $request->subtitle;
            $book->isbn = $request->isbn;
            $book->isbn_13 = $request->isbn_13;
            $book->publisher = $request->publisher;
            $book->publish_date = $request->publish_date;
            $book->number_of_pages = $request->number_of_pages;
            $book->description = $request->description;
            $book->category_id = $request->category_id;
            $book->language = $request->language ?? 'en';
            $book->is_downloadable = $request->has('is_downloadable') ? 1 : 0;
            $book->featured = $request->has('featured') ? 1 : 0;
            $book->status = 1;
            $book->uploaded_by = Auth::guard('web')->user()->id;
            
            // Handle authors
            if ($request->has('authors')) {
                $authors = array_filter(array_map('trim', explode(',', $request->authors)));
                $book->authors = json_encode($authors);
            }
            
            // Handle subjects
            if ($request->has('subjects')) {
                $subjects = array_filter(array_map('trim', explode(',', $request->subjects)));
                $book->subjects = json_encode($subjects);
            }
            
            // Upload file
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $fileName = time() . '_' . Str::slug($request->title) . '.' . $file->getClientOriginalExtension();
                
                // Get file info BEFORE moving
                $fileSize = $file->getSize();
                $fileType = $file->getClientOriginalExtension();
                
                $file->move(public_path('uploads/'.$this->path.'/books'), $fileName);
                
                $book->file_path = $fileName;
                $book->file_type = $fileType;
                $book->file_size = $fileSize;
            }
            
            // Upload cover image
            if ($request->hasFile('cover_image')) {
                $image = $request->file('cover_image');
                $imageName = time() . '_cover_' . Str::slug($request->title) . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('uploads/'.$this->path.'/covers'), $imageName);
                
                $book->cover_image = $imageName;
            }
            
            $book->save();
            
            flash()->addSuccess('Book uploaded successfully!');
            return redirect()->route('admin.e-library.index');
            
        } catch (\Exception $e) {
            flash()->addError('Error uploading book: ' . $e->getMessage());
            return redirect()->back()->withInput();
        }
    }

    /**
     * Display the specified e-book
     */
    public function show($id)
    {
        $data['title'] = 'Book Details';
        $data['book'] = EBook::with(['category', 'uploader', 'reviews.user', 'readings'])->findOrFail($id);
        
        return view('admin.e-library.show', $data);
    }

    /**
     * Show the form for editing the specified e-book
     */
    public function edit($id)
    {
        $data['title'] = 'Edit Book';
        $data['book'] = EBook::findOrFail($id);
        $data['categories'] = EBookCategory::where('status', 1)->orderBy('sort_order')->get();
        
        return view('admin.e-library.edit', $data);
    }

    /**
     * Update the specified e-book
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'category_id' => 'required|exists:e_book_categories,id',
            'file' => 'nullable|file|mimes:pdf,epub|max:51200',
            'cover_image' => 'nullable|image|max:5120',
        ]);

        try {
            $book = EBook::findOrFail($id);
            $book->title = $request->title;
            $book->subtitle = $request->subtitle;
            $book->isbn = $request->isbn;
            $book->isbn_13 = $request->isbn_13;
            $book->publisher = $request->publisher;
            $book->publish_date = $request->publish_date;
            $book->number_of_pages = $request->number_of_pages;
            $book->description = $request->description;
            $book->category_id = $request->category_id;
            $book->language = $request->language ?? 'en';
            $book->is_downloadable = $request->has('is_downloadable') ? 1 : 0;
            $book->featured = $request->has('featured') ? 1 : 0;
            
            // Handle authors
            if ($request->has('authors')) {
                $authors = array_filter(array_map('trim', explode(',', $request->authors)));
                $book->authors = json_encode($authors);
            }
            
            // Handle subjects
            if ($request->has('subjects')) {
                $subjects = array_filter(array_map('trim', explode(',', $request->subjects)));
                $book->subjects = json_encode($subjects);
            }
            
            // Upload new file if provided
            if ($request->hasFile('file')) {
                // Delete old file
                if ($book->file_path && file_exists(public_path('uploads/'.$this->path.'/books/'.$book->file_path))) {
                    unlink(public_path('uploads/'.$this->path.'/books/'.$book->file_path));
                }
                
                $file = $request->file('file');
                $fileName = time() . '_' . Str::slug($request->title) . '.' . $file->getClientOriginalExtension();
                
                // Get file info BEFORE moving
                $fileSize = $file->getSize();
                $fileType = $file->getClientOriginalExtension();
                
                $file->move(public_path('uploads/'.$this->path.'/books'), $fileName);
                
                $book->file_path = $fileName;
                $book->file_type = $fileType;
                $book->file_size = $fileSize;
            }
            
            // Upload new cover image if provided
            if ($request->hasFile('cover_image')) {
                // Delete old image
                if ($book->cover_image && file_exists(public_path('uploads/'.$this->path.'/covers/'.$book->cover_image))) {
                    unlink(public_path('uploads/'.$this->path.'/covers/'.$book->cover_image));
                }
                
                $image = $request->file('cover_image');
                $imageName = time() . '_cover_' . Str::slug($request->title) . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('uploads/'.$this->path.'/covers'), $imageName);
                
                $book->cover_image = $imageName;
            }
            
            $book->save();
            
            flash()->addSuccess('Book updated successfully!');
            return redirect()->route('admin.e-library.index');
            
        } catch (\Exception $e) {
            flash()->addError('Error updating book: ' . $e->getMessage());
            return redirect()->back()->withInput();
        }
    }

    /**
     * Remove the specified e-book
     */
    public function destroy($id)
    {
        try {
            $book = EBook::findOrFail($id);
            
            // Delete files
            if ($book->file_path && file_exists(public_path('uploads/'.$this->path.'/books/'.$book->file_path))) {
                unlink(public_path('uploads/'.$this->path.'/books/'.$book->file_path));
            }
            
            if ($book->cover_image && file_exists(public_path('uploads/'.$this->path.'/covers/'.$book->cover_image))) {
                unlink(public_path('uploads/'.$this->path.'/covers/'.$book->cover_image));
            }
            
            $book->delete();
            
            flash()->addSuccess('Book deleted successfully!');
            return redirect()->route('admin.e-library.index');
            
        } catch (\Exception $e) {
            flash()->addError('Error deleting book: ' . $e->getMessage());
            return redirect()->back();
        }
    }

    /**
     * Search Internet Archive API
     */
    public function searchInternetArchive(Request $request)
    {
        $query = $request->input('q');
        
        if (!$query) {
            return response()->json(['error' => 'Search query is required'], 400);
        }
        
        try {
            // Internet Archive Advanced Search API
            // Search for PUBLIC DOMAIN texts that are freely accessible (not lending library)
            // collection:opensource = freely available, not borrowing required
            $url = 'https://archive.org/advancedsearch.php';
            $params = [
                'q' => $query . ' AND mediatype:texts AND collection:(opensource OR gutenberg OR million_books) AND NOT collection:inlibrary',
                'fl' => 'identifier,title,creator,description,date,publisher,language,subject,downloads,imagecount,avg_rating,num_reviews,collection',
                'sort' => 'downloads desc',
                'rows' => 20,
                'page' => 1,
                'output' => 'json',
            ];
            
            $response = Http::timeout(15)->get($url, $params);
            
            if ($response->successful()) {
                $data = $response->json();
                
                // Format the results
                $books = collect($data['response']['docs'] ?? [])->map(function($doc) {
                    $identifier = $doc['identifier'] ?? '';
                    
                    // Build cover image URL
                    $coverImage = $identifier ? "https://archive.org/services/img/{$identifier}" : null;
                    
                    // Handle creator (can be string or array)
                    $authors = [];
                    if (isset($doc['creator'])) {
                        $authors = is_array($doc['creator']) ? $doc['creator'] : [$doc['creator']];
                    }
                    
                    // Handle subject (can be string or array)
                    $subjects = [];
                    if (isset($doc['subject'])) {
                        $subjects = is_array($doc['subject']) ? $doc['subject'] : [$doc['subject']];
                    }
                    
                    return [
                        'id' => $identifier,
                        'title' => $doc['title'] ?? 'Unknown',
                        'authors' => $authors,
                        'description' => is_array($doc['description'] ?? null) ? ($doc['description'][0] ?? '') : ($doc['description'] ?? ''),
                        'publisher' => is_array($doc['publisher'] ?? null) ? ($doc['publisher'][0] ?? '') : ($doc['publisher'] ?? ''),
                        'publishedDate' => $doc['date'] ?? null,
                        'subjects' => array_slice($subjects, 0, 5),
                        'language' => is_array($doc['language'] ?? null) ? ($doc['language'][0] ?? 'en') : ($doc['language'] ?? 'en'),
                        'cover_image' => $coverImage,
                        'downloads' => $doc['downloads'] ?? 0,
                        'pages' => $doc['imagecount'] ?? null,
                        'average_rating' => $doc['avg_rating'] ?? null,
                        'reviews_count' => $doc['num_reviews'] ?? 0,
                        'read_online_link' => "https://archive.org/details/{$identifier}",
                        'embed_link' => "https://archive.org/embed/{$identifier}",
                    ];
                });
                
                return response()->json([
                    'success' => true, 
                    'books' => $books, 
                    'totalItems' => $data['response']['numFound'] ?? 0
                ]);
            }
            
            return response()->json(['error' => 'Failed to fetch from Internet Archive. Status: ' . $response->status()], 500);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Import book from Internet Archive
     */
    public function importFromInternetArchive(Request $request)
    {
        $request->validate([
            'archive_id' => 'required|string',
            'category_id' => 'required|exists:e_book_categories,id',
        ]);
        
        try {
            $archiveId = $request->archive_id;
            
            // Check if already imported
            $existingBook = EBook::where('archive_id', $archiveId)->first();
            if ($existingBook) {
                return response()->json(['error' => 'This book has already been imported'], 400);
            }
            
            // Fetch book metadata from Internet Archive
            $metadataUrl = "https://archive.org/metadata/{$archiveId}";
            $response = Http::timeout(15)->get($metadataUrl);
            
            if (!$response->successful()) {
                return response()->json(['error' => 'Failed to fetch book details from Internet Archive'], 500);
            }
            
            $data = $response->json();
            $metadata = $data['metadata'] ?? [];
            
            // Create new book
            $book = new EBook();
            $book->source = 'internet_archive';
            $book->archive_id = $archiveId;
            $book->title = is_array($metadata['title'] ?? null) ? ($metadata['title'][0] ?? 'Unknown') : ($metadata['title'] ?? 'Unknown');
            $book->category_id = $request->category_id;
            $book->is_downloadable = true; // Internet Archive books are often downloadable
            $book->featured = $request->has('featured') ? 1 : 0;
            $book->status = 1;
            $book->uploaded_by = Auth::guard('web')->user()->id;
            
            // Handle language
            $lang = $metadata['language'] ?? 'en';
            $book->language = is_array($lang) ? ($lang[0] ?? 'en') : $lang;
            
            // Handle authors/creator
            if (isset($metadata['creator'])) {
                $creators = is_array($metadata['creator']) ? $metadata['creator'] : [$metadata['creator']];
                $book->authors = json_encode($creators);
            }
            
            // Handle publisher
            $publisher = $metadata['publisher'] ?? null;
            $book->publisher = is_array($publisher) ? ($publisher[0] ?? null) : $publisher;
            
            // Handle publish date
            $book->publish_date = $metadata['date'] ?? $metadata['year'] ?? null;
            
            // Handle page count
            $book->number_of_pages = $metadata['imagecount'] ?? null;
            
            // Handle subjects
            if (isset($metadata['subject'])) {
                $subjects = is_array($metadata['subject']) ? $metadata['subject'] : [$metadata['subject']];
                $book->subjects = json_encode(array_slice($subjects, 0, 10));
            }
            
            // Handle cover image
            $book->cover_image = "https://archive.org/services/img/{$archiveId}";
            $book->cover_image_large = "https://archive.org/services/img/{$archiveId}";
            
            // Handle description
            $desc = $metadata['description'] ?? null;
            $book->description = is_array($desc) ? implode("\n\n", $desc) : $desc;
            
            // Set Internet Archive links - these allow embedding!
            $book->preview_link = "https://archive.org/details/{$archiveId}";
            $book->read_online_link = "https://archive.org/embed/{$archiveId}";
            
            // Set initial views from downloads count
            $book->views_count = $metadata['downloads'] ?? 0;
            
            $book->save();
            
            return response()->json(['success' => true, 'message' => 'Book imported successfully from Internet Archive']);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Category Management
     */
    public function categories()
    {
        $data['title'] = 'Book Categories';
        $data['categories'] = EBookCategory::withCount('books')->orderBy('sort_order')->get();
        
        return view('admin.e-library.categories', $data);
    }

    /**
     * Store a new category
     */
    public function storeCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:e_book_categories,slug',
            'description' => 'nullable|string',
            'icon' => 'required|string|max:255',
            'color' => 'required|string|max:7',
            'sort_order' => 'nullable|integer',
            'status' => 'required|in:0,1',
        ]);

        EBookCategory::create([
            'name' => $request->name,
            'slug' => $request->slug,
            'description' => $request->description,
            'icon' => $request->icon,
            'color' => $request->color,
            'sort_order' => $request->sort_order ?? 0,
            'status' => (int) $request->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully!'
        ]);
    }

    /**
     * Update a category
     */
    public function updateCategory(Request $request, $id)
    {
        $category = EBookCategory::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:e_book_categories,slug,' . $id,
            'description' => 'nullable|string',
            'icon' => 'required|string|max:255',
            'color' => 'required|string|max:7',
            'sort_order' => 'nullable|integer',
            'status' => 'required|in:0,1',
        ]);

        $category->update([
            'name' => $request->name,
            'slug' => $request->slug,
            'description' => $request->description,
            'icon' => $request->icon,
            'color' => $request->color,
            'sort_order' => $request->sort_order ?? 0,
            'status' => (int) $request->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully!'
        ]);
    }

    /**
     * Delete a category
     */
    public function destroyCategory($id)
    {
        $category = EBookCategory::findOrFail($id);
        
        // Check if category has books
        if ($category->books()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with existing books. Please reassign the books first.'
            ], 400);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully!'
        ]);
    }

    /**
     * Statistics Dashboard
     */
    public function statistics()
    {
        $data['title'] = 'E-Library Statistics';
        
        // Overview Statistics
        $data['stats'] = [
            'total_books' => EBook::count(),
            'active_books' => EBook::where('status', 1)->count(),
            'local_books' => EBook::where('source', 'local')->count(),
            'openlibrary_books' => EBook::where('source', 'openlibrary')->count(),
            'total_views' => EBook::sum('views_count'),
            'total_downloads' => EBook::sum('downloads_count'),
            'total_favorites' => EBook::sum('favorites_count'),
            'active_readers' => EBookReading::where('last_read_at', '>=', now()->subDays(30))
                                            ->distinct('user_id')
                                            ->count('user_id'),
            'total_reviews' => EBookReview::count(),
            'avg_rating' => EBook::where('rating_avg', '>', 0)->avg('rating_avg') ?? 0,
            'completed_reads' => EBookReading::whereNotNull('completed_at')->count(),
        ];
        
        // Top 10 Most Viewed Books
        $data['topViewedBooks'] = EBook::with('category')
                                       ->orderBy('views_count', 'desc')
                                       ->take(10)
                                       ->get();
        
        // Top 10 Most Downloaded Books
        $data['topDownloadedBooks'] = EBook::with('category')
                                           ->where('is_downloadable', 1)
                                           ->orderBy('downloads_count', 'desc')
                                           ->take(10)
                                           ->get();
        
        // Top Rated Books
        $data['topRatedBooks'] = EBook::with('category')
                                      ->where('rating_count', '>', 0)
                                      ->orderBy('rating_avg', 'desc')
                                      ->take(10)
                                      ->get();
        
        // Category Statistics with additional metrics
        $data['categoryStats'] = EBookCategory::withCount(['books' => function($query) {
                                                   $query->where('status', 1);
                                               }])
                                               ->get()
                                               ->map(function($category) {
                                                   $category->total_views = $category->books()->sum('views_count');
                                                   $category->total_downloads = $category->books()->sum('downloads_count');
                                                   return $category;
                                               });
        
        // Reading Trends (Last 30 Days)
        $data['readingTrends'] = collect();
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $data['readingTrends']->push([
                'date' => now()->subDays($i)->format('M d'),
                'views' => 0, // In production, track daily views
                'downloads' => 0, // In production, track daily downloads
                'new_readers' => EBookReading::whereDate('started_at', $date)->distinct('user_id')->count('user_id'),
            ]);
        }
        
        // Recent Activity
        $data['recentActivity'] = collect();
        
        // Recent books added
        $recentBooks = EBook::orderBy('created_at', 'desc')->take(5)->get();
        foreach ($recentBooks as $book) {
            $data['recentActivity']->push([
                'type' => 'book_added',
                'message' => "New book added: {$book->title}",
                'time' => $book->created_at->diffForHumans(),
            ]);
        }
        
        // Recent reviews
        $recentReviews = EBookReview::with(['book', 'user'])
                                    ->orderBy('created_at', 'desc')
                                    ->take(5)
                                    ->get();
        foreach ($recentReviews as $review) {
            $data['recentActivity']->push([
                'type' => 'review',
                'message' => "{$review->user->name} reviewed '{$review->book->title}'",
                'time' => $review->created_at->diffForHumans(),
            ]);
        }
        
        // Recent reading activity
        $recentReadings = EBookReading::with(['book', 'user'])
                                      ->orderBy('last_read_at', 'desc')
                                      ->take(5)
                                      ->get();
        foreach ($recentReadings as $reading) {
            $data['recentActivity']->push([
                'type' => 'reading',
                'message' => "{$reading->user->name} is reading '{$reading->book->title}' ({$reading->progress_percentage}%)",
                'time' => $reading->last_read_at->diffForHumans(),
            ]);
        }
        
        // Sort activity by time
        $data['recentActivity'] = $data['recentActivity']->sortByDesc('time')->take(15);
        
        return view('admin.e-library.statistics', $data);
    }
}
