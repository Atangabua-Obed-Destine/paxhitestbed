@extends('admin.layouts.master')

@section('title', 'E-Library Management')

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">
            <span class="page-title-icon bg-gradient-primary text-white me-2">
                <i class="mdi mdi-book-open-variant"></i>
            </span> E-Library Management
        </h3>
        <nav aria-label="breadcrumb">
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">E-Library</li>
            </ul>
        </nav>
    </div>

    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="card-title mb-0">Books Management</h4>
                        <div class="btn-group">
                            <a href="{{ route('admin.e-library.create') }}" class="btn btn-gradient-primary btn-sm">
                                <i class="mdi mdi-plus"></i> Upload Book
                            </a>
                            <button type="button" class="btn btn-gradient-info btn-sm" data-bs-toggle="modal" data-bs-target="#internetArchiveModal">
                                <i class="mdi mdi-archive"></i> Import from Internet Archive
                            </button>
                            <a href="{{ route('admin.e-library.categories') }}" class="btn btn-gradient-success btn-sm">
                                <i class="mdi mdi-folder-multiple"></i> Manage Categories
                            </a>
                            <a href="{{ route('admin.e-library.statistics') }}" class="btn btn-gradient-warning btn-sm">
                                <i class="mdi mdi-chart-bar"></i> Statistics
                            </a>
                        </div>
                    </div>

                    <!-- Search and Filter Section -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <form method="GET" action="{{ route('admin.e-library.index') }}" class="d-flex">
                                <input type="text" name="search" class="form-control" placeholder="Search by title, author, ISBN..." value="{{ request('search') }}">
                                <button type="submit" class="btn btn-primary ms-2">
                                    <i class="mdi mdi-magnify"></i> Search
                                </button>
                            </form>
                        </div>
                        <div class="col-md-3">
                            <select name="category" class="form-select" onchange="filterBooks()">
                                <option value="">All Categories</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ request('category') == $category->id ? 'selected' : '' }}>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="source" class="form-select" onchange="filterBooks()">
                                <option value="">All Sources</option>
                                <option value="local" {{ request('source') == 'local' ? 'selected' : '' }}>Uploaded</option>
                                <option value="openlibrary" {{ request('source') == 'openlibrary' ? 'selected' : '' }}>OpenLibrary</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-select" onchange="filterBooks()">
                                <option value="">All Status</option>
                                <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <a href="{{ route('admin.e-library.index') }}" class="btn btn-secondary w-100" title="Reset Filters">
                                <i class="mdi mdi-refresh"></i> Reset
                            </a>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-0 text-white">Total Books</h6>
                                            <h3 class="mb-0 text-white">{{ $books->total() }}</h3>
                                        </div>
                                        <i class="mdi mdi-book-multiple mdi-36px text-white"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-0 text-white">Active Books</h6>
                                            <h3 class="mb-0 text-white">{{ $books->where('status', 1)->count() }}</h3>
                                        </div>
                                        <i class="mdi mdi-check-circle mdi-36px text-white"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-0 text-white">Total Views</h6>
                                            <h3 class="mb-0 text-white">{{ $books->sum('views_count') }}</h3>
                                        </div>
                                        <i class="mdi mdi-eye mdi-36px text-white"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card" style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-0 text-white" style="text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">Downloads</h6>
                                            <h3 class="mb-0 text-white" style="text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">{{ $books->sum('downloads_count') }}</h3>
                                        </div>
                                        <i class="mdi mdi-download mdi-36px text-white" style="text-shadow: 1px 1px 2px rgba(0,0,0,0.3);"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Books Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="60">Cover</th>
                                    <th>Title</th>
                                    <th>Author(s)</th>
                                    <th>Category</th>
                                    <th>Source</th>
                                    <th>Views</th>
                                    <th>Downloads</th>
                                    <th>Rating</th>
                                    <th>Status</th>
                                    <th width="150">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($books as $book)
                                    <tr>
                                        <td>
                                            @if($book->cover_image)
                                                <img src="{{ asset('uploads/e-library/covers/' . $book->cover_image) }}" alt="{{ $book->title }}" class="img-thumbnail" style="width: 50px; height: 70px; object-fit: cover;">
                                            @else
                                                <div class="bg-light d-flex align-items-center justify-content-center" style="width: 50px; height: 70px;">
                                                    <i class="mdi mdi-book mdi-24px text-muted"></i>
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <strong>{{ Str::limit($book->title, 40) }}</strong>
                                                @if($book->subtitle)
                                                    <small class="text-muted">{{ Str::limit($book->subtitle, 30) }}</small>
                                                @endif
                                                @if($book->featured)
                                                    <span class="badge bg-success text-white badge-sm mt-1">Featured</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>{{ Str::limit($book->authors_list, 30) }}</td>
                                        <td>
                                            @if($book->category)
                                                <span class="badge text-white" style="background-color: {{ $book->category->color }}">
                                                    {{ $book->category->name }}
                                                </span>
                                            @else
                                                <span class="badge bg-secondary text-white">Uncategorized</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($book->source == 'local')
                                                <span class="badge bg-primary text-white">Uploaded</span>
                                            @else
                                                <span class="badge bg-info text-white">Gutenberg</span>
                                            @endif
                                        </td>
                                        <td>{{ number_format($book->views_count) }}</td>
                                        <td>{{ number_format($book->downloads_count) }}</td>
                                        <td>
                                            @if($book->rating_avg > 0)
                                                <div class="d-flex align-items-center">
                                                    <i class="mdi mdi-star text-warning"></i>
                                                    <span class="ms-1">{{ number_format($book->rating_avg, 1) }}</span>
                                                    <small class="text-muted ms-1">({{ $book->rating_count }})</small>
                                                </div>
                                            @else
                                                <span class="text-muted">No ratings</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($book->status)
                                                <span class="badge bg-success text-white">Active</span>
                                            @else
                                                <span class="badge bg-danger text-white">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="{{ route('admin.e-library.show', $book->id) }}" class="btn btn-sm btn-info text-white" title="View">
                                                    <i class="mdi mdi-eye"></i> View
                                                </a>
                                                <a href="{{ route('admin.e-library.edit', $book->id) }}" class="btn btn-sm btn-warning text-white" title="Edit">
                                                    <i class="mdi mdi-pencil"></i> Edit
                                                </a>
                                                <form action="{{ route('admin.e-library.destroy', $book->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this book?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger text-white" title="Delete">
                                                        <i class="mdi mdi-delete"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center py-5">
                                            <i class="mdi mdi-book-open-variant mdi-48px text-muted"></i>
                                            <p class="mt-3 text-muted">No books found. Upload your first book or import from Project Gutenberg.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="mt-4">
                        {{ $books->appends(request()->query())->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Internet Archive Search Modal -->
<div class="modal fade" id="internetArchiveModal" tabindex="-1" aria-labelledby="internetArchiveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="internetArchiveModalLabel">
                    <i class="mdi mdi-archive"></i> Search Internet Archive
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-3">
                    <i class="mdi mdi-information"></i> 
                    <strong>Internet Archive</strong> provides access to millions of free books, texts, and documents. 
                    All books can be read online directly in your e-library!
                </div>
                <div class="row mb-4">
                    <div class="col-md-10">
                        <input type="text" id="archiveSearch" class="form-control" placeholder="Search by title, author, subject (e.g., Shakespeare, Science Fiction)...">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-primary w-100" onclick="searchInternetArchive()">
                            <i class="mdi mdi-magnify"></i> Search
                        </button>
                    </div>
                </div>
                
                <div id="archiveLoading" class="text-center py-5" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3">Searching Internet Archive...</p>
                </div>

                <div id="archiveResults" class="row"></div>

                <div id="archiveEmpty" class="text-center py-5" style="display: none;">
                    <i class="mdi mdi-book-search mdi-48px text-muted"></i>
                    <p class="mt-3 text-muted">No results found. Try a different search term.</p>
                </div>
                
                <div id="archiveError" class="alert alert-danger" style="display: none;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Import Confirmation Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importModalLabel">Import Book from Internet Archive</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="importForm">
                <div class="modal-body">
                    <input type="hidden" id="importBookId" name="archive_id">
                    <div class="alert alert-info mb-3">
                        <strong>Book:</strong> <span id="importBookTitle"></span><br>
                        <small class="text-muted" id="importBookAuthors"></small>
                    </div>
                    <div class="mb-3">
                        <label for="importCategory" class="form-label">Select Category *</label>
                        <select id="importCategory" name="category_id" class="form-select" required>
                            <option value="">Choose a category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="importFeatured" name="featured">
                        <label class="form-check-label" for="importFeatured">
                            Mark as Featured
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="mdi mdi-download"></i> Import Book
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function filterBooks() {
    const category = document.querySelector('select[name="category"]').value;
    const source = document.querySelector('select[name="source"]').value;
    const status = document.querySelector('select[name="status"]').value;
    const search = new URLSearchParams(window.location.search).get('search') || '';
    
    let url = '{{ route("admin.e-library.index") }}?';
    if (search) url += `search=${search}&`;
    if (category) url += `category=${category}&`;
    if (source) url += `source=${source}&`;
    if (status) url += `status=${status}&`;
    
    window.location.href = url;
}

function searchInternetArchive() {
    const query = document.getElementById('archiveSearch').value;
    if (!query.trim()) {
        alert('Please enter a search term');
        return;
    }
    
    const loading = document.getElementById('archiveLoading');
    const results = document.getElementById('archiveResults');
    const empty = document.getElementById('archiveEmpty');
    const error = document.getElementById('archiveError');
    
    loading.style.display = 'block';
    results.innerHTML = '';
    empty.style.display = 'none';
    error.style.display = 'none';
    
    fetch(`{{ route('admin.e-library.internet-archive.search') }}?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            loading.style.display = 'none';
            
            if (data.error) {
                error.textContent = data.error;
                error.style.display = 'block';
                return;
            }
            
            if (data.books && data.books.length > 0) {
                results.innerHTML = data.books.map(book => {
                    const coverUrl = book.cover_image || '/dashboard/images/book-placeholder.png';
                    const authors = Array.isArray(book.authors) ? book.authors.join(', ') : 'Unknown Author';
                    const subjects = Array.isArray(book.subjects) ? book.subjects.slice(0, 2).join(', ') : '';
                    const downloads = book.downloads ? book.downloads.toLocaleString() + ' downloads' : '';
                    const pages = book.pages ? book.pages + ' pages' : '';
                    const publisher = book.publisher || '';
                    
                    return `
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex">
                                    <img src="${coverUrl}" 
                                         alt="${book.title}" 
                                         class="me-3" 
                                         style="width: 80px; height: 120px; object-fit: cover; border-radius: 4px;"
                                         onerror="this.src='/dashboard/images/book-placeholder.png'">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1" title="${book.title}">${book.title.length > 50 ? book.title.substring(0, 50) + '...' : book.title}</h6>
                                        <p class="text-muted small mb-1">${authors}</p>
                                        ${publisher ? `<p class="text-muted small mb-1"><i class="mdi mdi-domain"></i> ${publisher}</p>` : ''}
                                        ${pages ? `<p class="text-muted small mb-1"><i class="mdi mdi-book-open-page-variant"></i> ${pages}</p>` : ''}
                                        ${downloads ? `<p class="text-muted small mb-1"><i class="mdi mdi-download"></i> ${downloads}</p>` : ''}
                                        ${subjects ? `<p class="text-muted small mb-2"><i class="mdi mdi-tag"></i> ${subjects}</p>` : ''}
                                        <button type="button" 
                                                class="btn btn-sm btn-primary" 
                                                onclick="showImportModal('${book.id}', '${book.title.replace(/'/g, "\\'")}', '${authors.replace(/'/g, "\\'")}')">
                                            <i class="mdi mdi-download"></i> Import
                                        </button>
                                        <a href="${book.read_online_link}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                            <i class="mdi mdi-open-in-new"></i> Preview
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                }).join('');
            } else {
                empty.style.display = 'block';
            }
        })
        .catch(err => {
            loading.style.display = 'none';
            error.textContent = 'Error searching Internet Archive. Please try again.';
            error.style.display = 'block';
            console.error(err);
        });
}

function showImportModal(archiveId, bookTitle, bookAuthors) {
    document.getElementById('importBookId').value = archiveId;
    document.getElementById('importBookTitle').textContent = bookTitle;
    document.getElementById('importBookAuthors').textContent = bookAuthors;
    const importModal = new bootstrap.Modal(document.getElementById('importModal'));
    importModal.show();
}

document.getElementById('importForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const archiveId = document.getElementById('importBookId').value;
    const categoryId = document.getElementById('importCategory').value;
    const featured = document.getElementById('importFeatured').checked;
    
    if (!categoryId) {
        alert('Please select a category');
        return;
    }
    
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Importing...';
    
    fetch('{{ route("admin.e-library.internet-archive.import") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            archive_id: archiveId,
            category_id: categoryId,
            featured: featured
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Book imported successfully from Internet Archive!');
            window.location.reload();
        } else {
            alert(data.error || 'Error importing book');
            btn.disabled = false;
            btn.innerHTML = '<i class="mdi mdi-download"></i> Import Book';
        }
    })
    .catch(err => {
        alert('Error importing book. Please try again.');
        console.error(err);
        btn.disabled = false;
        btn.innerHTML = '<i class="mdi mdi-download"></i> Import Book';
    });
});

// Allow search on Enter key
document.getElementById('archiveSearch').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        searchInternetArchive();
    }
});
</script>
@endpush
@endsection
