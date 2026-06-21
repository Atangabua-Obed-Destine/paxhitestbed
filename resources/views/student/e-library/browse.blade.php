@extends('student.layouts.master')

@section('title', 'Browse Books')

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">
            <span class="page-title-icon bg-gradient-primary text-white me-2">
                <i class="mdi mdi-bookshelf"></i>
            </span> Browse Books
        </h3>
        <nav aria-label="breadcrumb">
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('student.e-library.index') }}">E-Library</a></li>
                <li class="breadcrumb-item active" aria-current="page">Browse</li>
            </ul>
        </nav>
    </div>

    <div class="row">
        <!-- Filters Sidebar -->
        <div class="col-lg-3 mb-4">
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-body">
                    <h5 class="card-title mb-4">
                        <i class="mdi mdi-filter-variant"></i> Filters
                    </h5>

                    <form id="filterForm" method="GET" action="{{ route('student.e-library.browse') }}">
                        <!-- Search -->
                        <div class="mb-4">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Title, author, ISBN..." 
                                   value="{{ request('search') }}">
                        </div>

                        <!-- Category -->
                        <div class="mb-4">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select">
                                <option value="">All Categories</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" 
                                            {{ request('category') == $category->id ? 'selected' : '' }}>
                                        {{ $category->name }} ({{ $category->books()->where('status', 1)->count() }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Language -->
                        <div class="mb-4">
                            <label class="form-label">Language</label>
                            <select name="language" class="form-select">
                                <option value="">All Languages</option>
                                <option value="English" {{ request('language') == 'English' ? 'selected' : '' }}>English</option>
                                <option value="Spanish" {{ request('language') == 'Spanish' ? 'selected' : '' }}>Spanish</option>
                                <option value="French" {{ request('language') == 'French' ? 'selected' : '' }}>French</option>
                                <option value="German" {{ request('language') == 'German' ? 'selected' : '' }}>German</option>
                                <option value="Chinese" {{ request('language') == 'Chinese' ? 'selected' : '' }}>Chinese</option>
                                <option value="Other" {{ request('language') == 'Other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>

                        <!-- Source -->
                        <div class="mb-4">
                            <label class="form-label">Source</label>
                            <select name="source" class="form-select">
                                <option value="">All Sources</option>
                                <option value="local" {{ request('source') == 'local' ? 'selected' : '' }}>Uploaded</option>
                                <option value="openlibrary" {{ request('source') == 'openlibrary' ? 'selected' : '' }}>OpenLibrary</option>
                            </select>
                        </div>

                        <!-- Rating -->
                        <div class="mb-4">
                            <label class="form-label">Minimum Rating</label>
                            <select name="rating" class="form-select">
                                <option value="">Any Rating</option>
                                <option value="4" {{ request('rating') == '4' ? 'selected' : '' }}>4+ Stars</option>
                                <option value="3" {{ request('rating') == '3' ? 'selected' : '' }}>3+ Stars</option>
                                <option value="2" {{ request('rating') == '2' ? 'selected' : '' }}>2+ Stars</option>
                            </select>
                        </div>

                        <!-- Availability -->
                        <div class="mb-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="downloadable" 
                                       name="downloadable" value="1" 
                                       {{ request('downloadable') ? 'checked' : '' }}>
                                <label class="form-check-label" for="downloadable">
                                    Downloadable Only
                                </label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="featured" 
                                       name="featured" value="1" 
                                       {{ request('featured') ? 'checked' : '' }}>
                                <label class="form-check-label" for="featured">
                                    Featured Only
                                </label>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <button type="submit" class="btn btn-gradient-primary w-100 mb-2">
                            <i class="mdi mdi-filter"></i> Apply Filters
                        </button>
                        <a href="{{ route('student.e-library.browse') }}" class="btn btn-light w-100">
                            <i class="mdi mdi-refresh"></i> Clear All
                        </a>
                    </form>

                    <!-- Active Filters -->
                    @if(request()->hasAny(['search', 'category', 'language', 'source', 'rating', 'downloadable', 'featured']))
                    <div class="mt-4">
                        <h6 class="mb-3">Active Filters:</h6>
                        <div class="d-flex flex-wrap gap-2">
                            @if(request('search'))
                                <span class="badge bg-primary">
                                    Search: {{ Str::limit(request('search'), 15) }}
                                    <a href="{{ route('student.e-library.browse', array_diff_key(request()->query(), ['search' => ''])) }}" class="text-white ms-1">×</a>
                                </span>
                            @endif
                            @if(request('category'))
                                @php $cat = $categories->find(request('category')); @endphp
                                <span class="badge bg-info">
                                    {{ $cat ? $cat->name : 'Category' }}
                                    <a href="{{ route('student.e-library.browse', array_diff_key(request()->query(), ['category' => ''])) }}" class="text-white ms-1">×</a>
                                </span>
                            @endif
                            @if(request('language'))
                                <span class="badge bg-success">
                                    {{ request('language') }}
                                    <a href="{{ route('student.e-library.browse', array_diff_key(request()->query(), ['language' => ''])) }}" class="text-white ms-1">×</a>
                                </span>
                            @endif
                            @if(request('rating'))
                                <span class="badge bg-warning">
                                    {{ request('rating') }}+ Stars
                                    <a href="{{ route('student.e-library.browse', array_diff_key(request()->query(), ['rating' => ''])) }}" class="text-white ms-1">×</a>
                                </span>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Books Grid -->
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <!-- Results Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h5 class="mb-0">
                                {{ $books->total() }} {{ Str::plural('Book', $books->total()) }} Found
                            </h5>
                            @if(request('search'))
                                <small class="text-muted">Search results for "{{ request('search') }}"</small>
                            @endif
                        </div>
                        <div class="d-flex align-items-center">
                            <label class="me-2 mb-0">Sort by:</label>
                            <select name="sort" class="form-select form-select-sm" onchange="sortBooks(this.value)" style="width: auto;">
                                <option value="recent" {{ request('sort') == 'recent' ? 'selected' : '' }}>Recent</option>
                                <option value="popular" {{ request('sort') == 'popular' ? 'selected' : '' }}>Popular</option>
                                <option value="rating" {{ request('sort') == 'rating' ? 'selected' : '' }}>Rating</option>
                                <option value="title" {{ request('sort') == 'title' ? 'selected' : '' }}>Title (A-Z)</option>
                                <option value="downloads" {{ request('sort') == 'downloads' ? 'selected' : '' }}>Downloads</option>
                            </select>
                        </div>
                    </div>

                    <!-- View Toggle -->
                    <div class="mb-3">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary active" onclick="setView('grid')">
                                <i class="mdi mdi-view-grid"></i> Grid
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setView('list')">
                                <i class="mdi mdi-view-list"></i> List
                            </button>
                        </div>
                    </div>

                    <!-- Books Grid View -->
                    <div id="gridView" class="books-grid">
                        @if($books->count() > 0)
                            <div class="row">
                                @foreach($books as $book)
                                    <div class="col-lg-3 col-md-4 col-sm-6 col-6 mb-4">
                                        @include('student.e-library.partials.book-card', ['book' => $book])
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-5">
                                <i class="mdi mdi-book-search mdi-72px text-muted"></i>
                                <h5 class="mt-3 text-muted">No books found</h5>
                                <p class="text-muted">Try adjusting your filters or search terms</p>
                                <a href="{{ route('student.e-library.browse') }}" class="btn btn-primary mt-3">
                                    <i class="mdi mdi-refresh"></i> Clear Filters
                                </a>
                            </div>
                        @endif
                    </div>

                    <!-- Books List View -->
                    <div id="listView" class="books-list" style="display: none;">
                        @foreach($books as $book)
                            <div class="card mb-3 book-list-item">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-2">
                                            @if($book->cover_image)
                                                <img src="{{ asset('uploads/e-library/covers/' . $book->cover_image) }}" alt="{{ $book->title }}" 
                                                     class="img-fluid rounded" style="max-height: 150px;">
                                            @else
                                                <div class="bg-light d-flex align-items-center justify-content-center rounded" 
                                                     style="height: 150px;">
                                                    <i class="mdi mdi-book mdi-48px text-muted"></i>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="col-md-7">
                                            <h5 class="mb-2">
                                                <a href="{{ route('student.e-library.show', $book->id) }}" class="text-decoration-none">
                                                    {{ $book->title }}
                                                </a>
                                            </h5>
                                            @if($book->subtitle)
                                                <h6 class="text-muted mb-2">{{ $book->subtitle }}</h6>
                                            @endif
                                            <p class="mb-2"><strong>Author(s):</strong> {{ $book->authors_list }}</p>
                                            @if($book->description)
                                                <p class="text-muted mb-2">{{ Str::limit($book->description, 150) }}</p>
                                            @endif
                                            <div class="d-flex flex-wrap gap-2">
                                                @if($book->category)
                                                    <span class="badge" style="background-color: {{ $book->category->color }}">
                                                        {{ $book->category->name }}
                                                    </span>
                                                @endif
                                                @if($book->featured)
                                                    <span class="badge bg-warning">Featured</span>
                                                @endif
                                                @if($book->language)
                                                    <span class="badge bg-info">{{ $book->language }}</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="col-md-3 text-center">
                                            @if($book->rating_avg > 0)
                                                <div class="mb-2">
                                                    <span class="text-warning h5">
                                                        <i class="mdi mdi-star"></i> {{ number_format($book->rating_avg, 1) }}
                                                    </span>
                                                    <br>
                                                    <small class="text-muted">({{ $book->rating_count }} reviews)</small>
                                                </div>
                                            @endif
                                            <div class="mb-2">
                                                <small class="text-muted">
                                                    <i class="mdi mdi-eye"></i> {{ number_format($book->views_count) }} views
                                                </small>
                                            </div>
                                            <div class="d-grid gap-2">
                                                <a href="{{ route('student.e-library.read', $book->id) }}" class="btn btn-primary btn-sm">
                                                    <i class="mdi mdi-book-open"></i> Read
                                                </a>
                                                <button type="button" class="btn btn-outline-danger btn-sm" 
                                                        onclick="toggleFavorite({{ $book->id }}, this, event)">
                                                    <i class="mdi mdi-heart{{ $book->isFavorited(auth()->id()) ? '' : '-outline' }}"></i>
                                                    Favorite
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Pagination -->
                    @if($books->hasPages())
                        <div class="mt-4 d-flex justify-content-center">
                            {{ $books->appends(request()->query())->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
.book-list-item {
    transition: box-shadow 0.3s;
}

.book-list-item:hover {
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.sticky-top {
    position: -webkit-sticky;
    position: sticky;
}

.gap-2 {
    gap: 0.5rem;
}
</style>
@endpush

@push('scripts')
<script>
function sortBooks(sort) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', sort);
    window.location.href = url.toString();
}

function setView(view) {
    const gridView = document.getElementById('gridView');
    const listView = document.getElementById('listView');
    const gridBtn = document.querySelector('[onclick="setView(\'grid\')"]');
    const listBtn = document.querySelector('[onclick="setView(\'list\')"]');
    
    if (view === 'grid') {
        gridView.style.display = 'block';
        listView.style.display = 'none';
        gridBtn.classList.add('active');
        listBtn.classList.remove('active');
        localStorage.setItem('bookView', 'grid');
    } else {
        gridView.style.display = 'none';
        listView.style.display = 'block';
        gridBtn.classList.remove('active');
        listBtn.classList.add('active');
        localStorage.setItem('bookView', 'list');
    }
}

// Restore saved view preference
document.addEventListener('DOMContentLoaded', function() {
    const savedView = localStorage.getItem('bookView');
    if (savedView === 'list') {
        setView('list');
    }
});

// Auto-submit form on filter change
document.querySelectorAll('#filterForm select, #filterForm input[type="checkbox"]').forEach(element => {
    element.addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
});
</script>
@endpush
@endsection
