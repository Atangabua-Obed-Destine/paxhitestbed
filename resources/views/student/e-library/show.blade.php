@extends('student.layouts.master')

@section('title', $book->title)

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">
            <span class="page-title-icon bg-gradient-primary text-white me-2">
                <i class="mdi mdi-book-open-variant"></i>
            </span> Book Details
        </h3>
        <nav aria-label="breadcrumb">
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('student.e-library.index') }}">E-Library</a></li>
                <li class="breadcrumb-item"><a href="{{ route('student.e-library.browse') }}">Browse</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ Str::limit($book->title, 30) }}</li>
            </ul>
        </nav>
    </div>

    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8 mb-4">
            <!-- Book Information Card -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            @if($book->cover_image)
                                <img src="{{ asset('uploads/e-library/covers/' . $book->cover_image) }}" alt="{{ $book->title }}" 
                                     class="img-fluid rounded shadow-sm w-100" style="max-height: 500px; object-fit: cover;">
                            @else
                                <div class="book-cover-placeholder rounded shadow-sm" 
                                     style="background: linear-gradient(135deg, {{ $book->category->color ?? '#667eea' }}88, {{ $book->category->color ?? '#764ba2' }}AA); height: 500px;">
                                    <i class="mdi mdi-book mdi-72px text-white"></i>
                                </div>
                            @endif
                        </div>
                        <div class="col-md-8">
                            <h2 class="mb-2">{{ $book->title }}</h2>
                            @if($book->subtitle)
                                <h5 class="text-muted mb-3">{{ $book->subtitle }}</h5>
                            @endif

                            <div class="mb-3">
                                @if($book->featured)
                                    <span class="badge badge-gradient-warning me-2">
                                        <i class="mdi mdi-star"></i> Featured
                                    </span>
                                @endif
                                @if($book->source == 'openlibrary')
                                    <span class="badge badge-gradient-info">
                                        <i class="mdi mdi-cloud"></i> OpenLibrary
                                    </span>
                                @endif
                            </div>

                            <table class="table table-borderless mb-4">
                                <tr>
                                    <td width="120"><strong>Author(s):</strong></td>
                                    <td>{{ $book->authors_list }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Category:</strong></td>
                                    <td>
                                        @if($book->category)
                                            <a href="{{ route('student.e-library.category', $book->category->slug) }}" class="text-decoration-none">
                                                <span class="badge" style="background-color: {{ $book->category->color }}">
                                                    <i class="{{ $book->category->icon }}"></i> {{ $book->category->name }}
                                                </span>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                                @if($book->publisher)
                                <tr>
                                    <td><strong>Publisher:</strong></td>
                                    <td>{{ $book->publisher }}</td>
                                </tr>
                                @endif
                                @if($book->publish_date)
                                <tr>
                                    <td><strong>Published:</strong></td>
                                    <td>{{ date('F Y', strtotime($book->publish_date)) }}</td>
                                </tr>
                                @endif
                                @if($book->language)
                                <tr>
                                    <td><strong>Language:</strong></td>
                                    <td>{{ $book->language }}</td>
                                </tr>
                                @endif
                                @if($book->number_of_pages)
                                <tr>
                                    <td><strong>Pages:</strong></td>
                                    <td>{{ $book->number_of_pages }}</td>
                                </tr>
                                @endif
                                @if($book->rating_avg > 0)
                                <tr>
                                    <td><strong>Rating:</strong></td>
                                    <td>
                                        <span class="text-warning h5 mb-0">
                                            @for($i = 1; $i <= 5; $i++)
                                                <i class="mdi mdi-star{{ $i <= round($book->rating_avg) ? '' : '-outline' }}"></i>
                                            @endfor
                                        </span>
                                        <span class="ms-2">{{ number_format($book->rating_avg, 1) }} / 5.0</span>
                                        <br>
                                        <small class="text-muted">Based on {{ $book->rating_count }} {{ Str::plural('review', $book->rating_count) }}</small>
                                    </td>
                                </tr>
                                @endif
                            </table>

                            @if($book->subjects && is_array($book->subjects) && count($book->subjects) > 0)
                            <div class="mb-3">
                                <strong>Subjects:</strong><br>
                                @foreach($book->subjects as $subject)
                                    <span class="badge bg-light text-dark me-1 mb-1">{{ $subject }}</span>
                                @endforeach
                            </div>
                            @endif

                            <!-- Action Buttons -->
                            <div class="d-grid gap-2 d-md-flex mb-2">
                                <a href="{{ route('student.e-library.read', $book->id) }}" class="btn btn-gradient-primary btn-lg">
                                    <i class="mdi mdi-book-open"></i> Read Now
                                </a>
                                
                                @if($book->source == 'local' && $book->is_downloadable && $book->file_path)
                                    <a href="{{ route('student.e-library.download', $book->id) }}" class="btn btn-gradient-success btn-lg">
                                        <i class="mdi mdi-download"></i> Download
                                    </a>
                                @endif
                                
                                @auth
                                <button type="button" class="btn {{ $isFavorited ? 'btn-danger' : 'btn-outline-danger' }} btn-lg" id="favoriteBtn" onclick="toggleFavorite()">
                                    <i class="mdi mdi-heart{{ $isFavorited ? '' : '-outline' }}"></i>
                                    <span>{{ $isFavorited ? 'Remove from' : 'Add to' }} Favorites</span>
                                </button>
                                @else
                                <a href="{{ route('login') }}" class="btn btn-outline-danger btn-lg">
                                    <i class="mdi mdi-heart-outline"></i>
                                    <span>Login to Add to Favorites</span>
                                </a>
                                @endauth
                            </div>
                            
                            <!-- AI Summary Button -->
                            <div class="d-grid">
                                <button type="button" class="btn btn-outline-info" onclick="generateAISummary()">
                                    <i class="mdi mdi-robot"></i> Generate AI Summary
                                </button>
                            </div>

                            <!-- Reading Progress (if started) -->
                            @if($userReading)
                            <div class="alert alert-info mt-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="mdi mdi-book-clock"></i>
                                        <strong>Your Progress:</strong> {{ round($userReading->progress_percentage) }}% Complete
                                    </div>
                                    <a href="{{ route('student.e-library.read', $book->id) }}" class="btn btn-sm btn-info">
                                        Continue Reading
                                    </a>
                                </div>
                                <div class="progress mt-2" style="height: 8px;">
                                    <div class="progress-bar bg-success" role="progressbar" 
                                         style="width: {{ $userReading->progress_percentage }}%"></div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>

                    @if($book->description)
                    <hr class="my-4">
                    <h5>About This Book</h5>
                    <p class="text-justify" style="line-height: 1.8;">{{ $book->description }}</p>
                    @endif
                </div>
            </div>

            <!-- Reviews Section -->
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-4">
                        <i class="mdi mdi-comment-text-multiple"></i> Reviews ({{ $book->reviews->where('status', 1)->count() }})
                    </h4>

                    <!-- Add Review Form -->
                    <div class="card bg-light mb-4">
                        <div class="card-body">
                            <h6 class="mb-3">Write a Review</h6>
                            <form id="reviewForm">
                                <div class="mb-3">
                                    <label class="form-label">Your Rating *</label>
                                    <div class="rating-input">
                                        @for($i = 5; $i >= 1; $i--)
                                            <input type="radio" name="rating" id="star{{ $i }}" value="{{ $i }}" required>
                                            <label for="star{{ $i }}" class="star-label">
                                                <i class="mdi mdi-star"></i>
                                            </label>
                                        @endfor
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="reviewText" class="form-label">Your Review</label>
                                    <textarea class="form-control" id="reviewText" name="review" rows="4" 
                                              placeholder="Share your thoughts about this book..."></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="mdi mdi-send"></i> Submit Review
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Reviews List -->
                    @if($book->reviews->where('status', 1)->count() > 0)
                        <div class="reviews-list">
                            @foreach($book->reviews->where('status', 1)->sortByDesc('created_at') as $review)
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h6 class="mb-1">{{ $review->user->name }}</h6>
                                                <div class="text-warning">
                                                    @for($i = 1; $i <= 5; $i++)
                                                        <i class="mdi mdi-star{{ $i <= $review->rating ? '' : '-outline' }}"></i>
                                                    @endfor
                                                </div>
                                            </div>
                                            <small class="text-muted">{{ $review->created_at->diffForHumans() }}</small>
                                        </div>
                                        @if($review->review)
                                            <p class="mb-0">{{ $review->review }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="mdi mdi-comment-text-outline mdi-48px text-muted"></i>
                            <p class="mt-3 text-muted">No reviews yet. Be the first to review this book!</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Stats Card -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-4">Statistics</h5>
                    <ul class="list-unstyled">
                        <li class="mb-3">
                            <i class="mdi mdi-eye text-info"></i>
                            <strong>{{ number_format($book->views_count) }}</strong> Views
                        </li>
                        @if($book->source == 'local')
                        <li class="mb-3">
                            <i class="mdi mdi-download text-success"></i>
                            <strong>{{ number_format($book->downloads_count) }}</strong> Downloads
                        </li>
                        @endif
                        <li class="mb-3">
                            <i class="mdi mdi-heart text-danger"></i>
                            <strong>{{ number_format($book->favorites_count) }}</strong> Favorites
                        </li>
                        <li class="mb-3">
                            <i class="mdi mdi-account-multiple text-primary"></i>
                            <strong>{{ $book->readings->count() }}</strong> Readers
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Related Books -->
            @if($relatedBooks && count($relatedBooks) > 0)
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-4">
                        <i class="mdi mdi-book-multiple"></i> Related Books
                    </h5>
                    @foreach($relatedBooks as $relatedBook)
                        <div class="d-flex mb-3 pb-3 border-bottom">
                            <div class="flex-shrink-0">
                                @if($relatedBook->cover_image)
                                    <img src="{{ asset($relatedBook->cover_image) }}" alt="{{ $relatedBook->title }}" 
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
                                    <a href="{{ route('student.e-library.show', $relatedBook->id) }}" class="text-decoration-none">
                                        {{ Str::limit($relatedBook->title, 40) }}
                                    </a>
                                </h6>
                                <p class="text-muted small mb-1">{{ Str::limit($relatedBook->authors_list, 30) }}</p>
                                @if($relatedBook->rating_avg > 0)
                                    <span class="text-warning small">
                                        <i class="mdi mdi-star"></i> {{ number_format($relatedBook->rating_avg, 1) }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

@push('styles')
<style>
.rating-input {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
    gap: 5px;
}

.rating-input input[type="radio"] {
    display: none;
}

.star-label {
    cursor: pointer;
    font-size: 30px;
    color: #ddd;
    transition: color 0.2s;
}

.rating-input input[type="radio"]:checked ~ .star-label,
.rating-input input[type="radio"]:hover ~ .star-label,
.rating-input .star-label:hover {
    color: #ffc107;
}

.book-cover-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>
@endpush

@push('scripts')
<script>
const bookId = {{ $book->id }};
let isFavorited = {{ $isFavorited ? 'true' : 'false' }};

console.log('Book ID:', bookId);
console.log('Is Favorited:', isFavorited);
console.log('CSRF Token:', '{{ csrf_token() }}');

function toggleFavorite() {
    @guest
        alert('Please login to add favorites');
        window.location.href = '{{ route('student.login') }}';
        return;
    @endguest
    
    console.log('toggleFavorite called');
    
    const btn = document.getElementById('favoriteBtn');
    const icon = btn.querySelector('i');
    const span = btn.querySelector('span');
    
    // Disable button during request
    btn.disabled = true;
    
    const url = `{{ url('/student/e-library/book') }}/${bookId}/favorite`;
    console.log('Calling URL:', url);
    
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        btn.disabled = false;
        if (data.success) {
            isFavorited = data.favorited;
            if (data.favorited) {
                icon.classList.remove('mdi-heart-outline');
                icon.classList.add('mdi-heart');
                span.textContent = 'Remove from Favorites';
                btn.classList.remove('btn-outline-danger');
                btn.classList.add('btn-danger');
            } else {
                icon.classList.remove('mdi-heart');
                icon.classList.add('mdi-heart-outline');
                span.textContent = 'Add to Favorites';
                btn.classList.remove('btn-danger');
                btn.classList.add('btn-outline-danger');
            }
        } else {
            alert(data.message || 'An error occurred. Please try again.');
        }
    })
    .catch(error => {
        btn.disabled = false;
        console.error('Error toggling favorite:', error);
        alert('An error occurred. Please try again.');
    });
}

// Generate AI Summary
function generateAISummary() {
    console.log('generateAISummary called for book:', bookId);
    
    // Show loading modal
    const modalHtml = `
        <div class="modal fade" id="aiSummaryModal" tabindex="-1" role="dialog" aria-labelledby="aiSummaryModalLabel">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-gradient-info text-white">
                        <h5 class="modal-title" id="aiSummaryModalLabel">
                            <i class="mdi mdi-robot"></i> AI-Generated Summary
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="summaryContent">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary mb-3" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="text-muted">Generating AI summary... This may take a moment.</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('aiSummaryModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal using Bootstrap 5
    const modalElement = document.getElementById('aiSummaryModal');
    const modal = new bootstrap.Modal(modalElement);
    modal.show();
    
    console.log('Fetching AI summary from:', `/student/e-library/ai/book/${bookId}/summary`);
    
    const summaryUrl = `{{ url('/student/e-library/ai/book') }}/${bookId}/summary`;
    console.log('Full Summary URL:', summaryUrl);
    
    // Fetch AI summary
    fetch(summaryUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('AI Summary response:', data);
        const contentDiv = document.getElementById('summaryContent');
        if (data.success && data.summary) {
            const summaryText = data.summary || 'No summary available.';
            contentDiv.innerHTML = `
                <div style="white-space: pre-wrap; line-height: 1.8; font-size: 1.05rem;">
                    ${summaryText.replace(/\n/g, '<br>')}
                </div>
                <div class="alert alert-warning mt-3 mb-0">
                    <i class="mdi mdi-information"></i> 
                    <small>This summary was generated by AI and may not capture all nuances of the book.</small>
                </div>
            `;
        } else {
            contentDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="mdi mdi-alert"></i> 
                    ${data.error || data.message || 'Failed to generate summary. The API may have returned an empty response.'}
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error generating summary:', error);
        const contentDiv = document.getElementById('summaryContent');
        if (contentDiv) {
            contentDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="mdi mdi-alert"></i> 
                    An error occurred while generating the summary. Please check the console for details.
                </div>
            `;
        }
    });
}

// Submit Review
document.getElementById('reviewForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const rating = formData.get('rating');
    const review = formData.get('review');
    
    if (!rating) {
        alert('Please select a rating');
        return;
    }
    
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Submitting...';
    
    fetch(`/student/e-library/book/${bookId}/review`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ rating, review })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Review submitted successfully! It will be visible after approval.');
            this.reset();
            window.location.reload();
        } else {
            alert(data.message || 'Error submitting review');
        }
    })
    .catch(error => {
        console.error('Error submitting review:', error);
        alert('An error occurred. Please try again.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="mdi mdi-send"></i> Submit Review';
    });
});
</script>
@endpush
@endsection
