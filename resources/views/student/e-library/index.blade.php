@extends('student.layouts.master')

@section('title', 'E-Library')

@section('content')
<div class="content-wrapper">
    <!-- Hero Section -->
    <div class="page-header bg-gradient-primary text-white mb-4">
        <div class="container-fluid py-5">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-4 mb-3">
                        <i class="mdi mdi-book-open-page-variant"></i> Welcome to E-Library
                    </h1>
                    <p class="lead mb-4">Discover thousands of books, read online, and expand your knowledge</p>
                    
                    <!-- Search Bar -->
                    <form action="{{ route('student.e-library.browse') }}" method="GET" class="search-form">
                        <div class="input-group input-group-lg">
                            <input type="text" name="search" class="form-control" placeholder="Search for books, authors, subjects..." 
                                   value="{{ request('search') }}" autocomplete="off" id="mainSearch">
                            <button class="btn btn-light" type="submit">
                                <i class="mdi mdi-magnify"></i> Search
                            </button>
                        </div>
                        <div id="searchSuggestions" class="search-suggestions"></div>
                    </form>
                </div>
                <div class="col-md-4 text-center d-none d-md-block">
                    <i class="mdi mdi-library mdi-96px"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <!-- AI Features Card -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5 class="mb-2">
                                    <i class="mdi mdi-robot"></i> Discover AI-Powered Features
                                </h5>
                                <p class="mb-0">
                                    Get personalized book recommendations, AI-generated summaries, and insights about your reading habits
                                </p>
                            </div>
                            <div class="col-md-4 text-md-right mt-3 mt-md-0">
                                <div class="btn-group">
                                    <a href="{{ route('student.e-library.ai.recommendations') }}" class="btn btn-light">
                                        <i class="mdi mdi-magic"></i> Recommendations
                                    </a>
                                    <a href="{{ route('student.e-library.ai.insights') }}" class="btn btn-outline-light">
                                        <i class="mdi mdi-chart-line"></i> Insights
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Continue Reading Section -->
        @if($continueReading && count($continueReading) > 0)
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="card-title mb-0">
                                <i class="mdi mdi-book-clock text-primary"></i> Continue Reading
                            </h4>
                            <a href="{{ route('student.e-library.history') }}" class="btn btn-sm btn-outline-primary">
                                View All <i class="mdi mdi-arrow-right"></i>
                            </a>
                        </div>
                        
                        <div class="row">
                            @foreach($continueReading as $reading)
                                @php $book = $reading->book; @endphp
                                <div class="col-md-3 mb-3">
                                    <div class="card book-card h-100">
                                        <div class="position-relative">
                                            @if($book->cover_image)
                                                <img src="{{ asset('uploads/e-library/covers/' . $book->cover_image) }}" class="card-img-top book-cover" alt="{{ $book->title }}">
                                            @else
                                                <div class="book-cover-placeholder">
                                                    <i class="mdi mdi-book mdi-48px"></i>
                                                </div>
                                            @endif
                                            <div class="progress book-progress">
                                                <div class="progress-bar bg-success" style="width: {{ $reading->progress_percentage }}%"></div>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <h6 class="card-title">{{ Str::limit($book->title, 40) }}</h6>
                                            <p class="text-muted small mb-2">{{ Str::limit($book->authors_list, 30) }}</p>
                                            <p class="mb-2">
                                                <small class="text-success">{{ round($reading->progress_percentage) }}% Complete</small>
                                            </p>
                                            <a href="{{ route('student.e-library.read', $book->id) }}" class="btn btn-sm btn-primary w-100">
                                                <i class="mdi mdi-book-open"></i> Continue Reading
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Featured Books -->
        @if(count($featuredBooks) > 0)
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="card-title mb-0">
                                <i class="mdi mdi-star text-warning"></i> Featured Books
                            </h4>
                        </div>
                        
                        <div class="row">
                            @foreach($featuredBooks as $book)
                                <div class="col-md-2 col-sm-4 col-6 mb-4">
                                    @include('student.e-library.partials.book-card', ['book' => $book])
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Categories Grid -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title mb-4">
                            <i class="mdi mdi-folder-multiple text-info"></i> Browse by Category
                        </h4>
                        
                        <div class="row">
                            @foreach($categories as $category)
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <a href="{{ route('student.e-library.category', $category->slug) }}" class="text-decoration-none">
                                        <div class="card category-card h-100 hover-shadow">
                                            <div class="card-body text-center" style="background: linear-gradient(135deg, {{ $category->color }}22, {{ $category->color }}44);">
                                                <i class="{{ $category->icon }} mdi-48px mb-3" style="color: {{ $category->color }}"></i>
                                                <h6 class="mb-2">{{ $category->name }}</h6>
                                                <p class="text-muted mb-0">{{ $category->books()->where('status', 1)->count() }} books</p>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="{{ route('student.e-library.browse') }}" class="btn btn-outline-primary">
                                Browse All Books <i class="mdi mdi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Additions and Popular Books -->
        <div class="row">
            <!-- Recent Additions -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="card-title mb-0">
                                <i class="mdi mdi-new-box text-success"></i> Recent Additions
                            </h4>
                            <a href="{{ route('student.e-library.browse', ['sort' => 'recent']) }}" class="btn btn-sm btn-outline-success">
                                View All
                            </a>
                        </div>
                        
                        @foreach($recentBooks as $book)
                            <div class="d-flex mb-3 pb-3 border-bottom">
                                <div class="flex-shrink-0">
                                    @if($book->cover_image)
                                        <img src="{{ asset('uploads/e-library/covers/' . $book->cover_image) }}" alt="{{ $book->title }}" 
                                             class="rounded" style="width: 60px; height: 80px; object-fit: cover;">
                                    @else
                                        <div class="bg-light d-flex align-items-center justify-content-center rounded" 
                                             style="width: 60px; height: 80px;">
                                            <i class="mdi mdi-book text-muted"></i>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1">
                                        <a href="{{ route('student.e-library.show', $book->id) }}" class="text-decoration-none">
                                            {{ Str::limit($book->title, 40) }}
                                        </a>
                                    </h6>
                                    <p class="text-muted small mb-1">{{ Str::limit($book->authors_list, 30) }}</p>
                                    <div class="d-flex align-items-center">
                                        @if($book->rating_avg > 0)
                                            <span class="text-warning me-2">
                                                <i class="mdi mdi-star"></i> {{ number_format($book->rating_avg, 1) }}
                                            </span>
                                        @endif
                                        <small class="text-muted">{{ $book->created_at->diffForHumans() }}</small>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Popular Books -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="card-title mb-0">
                                <i class="mdi mdi-trending-up text-danger"></i> Popular Books
                            </h4>
                            <a href="{{ route('student.e-library.browse', ['sort' => 'popular']) }}" class="btn btn-sm btn-outline-danger">
                                View All
                            </a>
                        </div>
                        
                        @foreach($popularBooks as $book)
                            <div class="d-flex mb-3 pb-3 border-bottom">
                                <div class="flex-shrink-0">
                                    @if($book->cover_image)
                                        <img src="{{ asset('uploads/e-library/covers/' . $book->cover_image) }}" alt="{{ $book->title }}" 
                                             class="rounded" style="width: 60px; height: 80px; object-fit: cover;">
                                    @else
                                        <div class="bg-light d-flex align-items-center justify-content-center rounded" 
                                             style="width: 60px; height: 80px;">
                                            <i class="mdi mdi-book text-muted"></i>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1">
                                        <a href="{{ route('student.e-library.show', $book->id) }}" class="text-decoration-none">
                                            {{ Str::limit($book->title, 40) }}
                                        </a>
                                    </h6>
                                    <p class="text-muted small mb-1">{{ Str::limit($book->authors_list, 30) }}</p>
                                    <div class="d-flex align-items-center">
                                        @if($book->rating_avg > 0)
                                            <span class="text-warning me-2">
                                                <i class="mdi mdi-star"></i> {{ number_format($book->rating_avg, 1) }}
                                            </span>
                                        @endif
                                        <small class="text-muted">
                                            <i class="mdi mdi-eye"></i> {{ number_format($book->views_count) }} views
                                        </small>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
.book-card {
    transition: transform 0.3s, box-shadow 0.3s;
}

.book-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.book-cover {
    width: 100%;
    height: 250px;
    object-fit: cover;
}

.book-cover-placeholder {
    width: 100%;
    height: 250px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.book-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 5px;
    background-color: rgba(255,255,255,0.3);
}

.category-card {
    transition: transform 0.3s, box-shadow 0.3s;
    cursor: pointer;
}

.category-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.15);
}

.hover-shadow:hover {
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.search-form {
    position: relative;
}

.search-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border-radius: 0 0 8px 8px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    max-height: 400px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
}

.search-suggestions.show {
    display: block;
}

.suggestion-item {
    padding: 12px 16px;
    cursor: pointer;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.2s;
}

.suggestion-item:hover {
    background: #f8f9fa;
}

.suggestion-item:last-child {
    border-bottom: none;
}
</style>
@endpush

@push('scripts')
<script>
// Live search suggestions
let searchTimeout;
const searchInput = document.getElementById('mainSearch');
const suggestionsDiv = document.getElementById('searchSuggestions');

searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const query = this.value.trim();
    
    if (query.length < 2) {
        suggestionsDiv.classList.remove('show');
        return;
    }
    
    searchTimeout = setTimeout(() => {
        fetch(`{{ route('student.e-library.search') }}?query=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                if (data.books && data.books.length > 0) {
                    suggestionsDiv.innerHTML = data.books.map(book => `
                        <div class="suggestion-item" onclick="window.location.href='/student/e-library/book/${book.id}'">
                            <div class="d-flex align-items-center">
                                <img src="${book.cover || '/images/book-placeholder.png'}" 
                                     alt="${book.title}" 
                                     class="me-3 rounded"
                                     style="width: 40px; height: 55px; object-fit: cover;">
                                <div>
                                    <strong>${book.title}</strong>
                                    <br>
                                    <small class="text-muted">${book.authors}</small>
                                </div>
                            </div>
                        </div>
                    `).join('');
                    suggestionsDiv.classList.add('show');
                } else {
                    suggestionsDiv.classList.remove('show');
                }
            })
            .catch(error => {
                console.error('Search error:', error);
            });
    }, 300);
});

// Close suggestions when clicking outside
document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
        suggestionsDiv.classList.remove('show');
    }
});
</script>
@endpush
@endsection
