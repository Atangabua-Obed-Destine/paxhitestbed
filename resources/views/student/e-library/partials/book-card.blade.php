<div class="card book-card h-100">
    <a href="{{ route('student.e-library.show', $book->id) }}" class="text-decoration-none">
        <div class="position-relative">
            @if($book->cover_image)
                <img src="{{ asset('uploads/e-library/covers/' . $book->cover_image) }}" class="card-img-top book-cover" alt="{{ $book->title }}">
            @else
                <div class="book-cover-placeholder" style="background: linear-gradient(135deg, {{ $book->category->color ?? '#667eea' }}88, {{ $book->category->color ?? '#764ba2' }}AA);">
                    <i class="mdi mdi-book mdi-48px text-white"></i>
                </div>
            @endif
            
            @if($book->featured)
                <span class="badge bg-warning position-absolute top-0 start-0 m-2">
                    <i class="mdi mdi-star"></i> Featured
                </span>
            @endif
            
            @if($book->source == 'openlibrary')
                <span class="badge bg-info position-absolute top-0 end-0 m-2">
                    <i class="mdi mdi-cloud"></i>
                </span>
            @endif
        </div>
    </a>
    
    <div class="card-body">
        <h6 class="card-title">
            <a href="{{ route('student.e-library.show', $book->id) }}" class="text-decoration-none text-dark">
                {{ Str::limit($book->title, 35) }}
            </a>
        </h6>
        <p class="text-muted small mb-2">{{ Str::limit($book->authors_list, 25) }}</p>
        
        @if($book->category)
            <span class="badge badge-sm mb-2" style="background-color: {{ $book->category->color }}">
                {{ $book->category->name }}
            </span>
        @endif
        
        <div class="d-flex justify-content-between align-items-center mb-2">
            @if($book->rating_avg > 0)
                <span class="text-warning small">
                    <i class="mdi mdi-star"></i> {{ number_format($book->rating_avg, 1) }}
                </span>
            @else
                <span class="text-muted small">No ratings</span>
            @endif
            <span class="text-muted small">
                <i class="mdi mdi-eye"></i> {{ number_format($book->views_count) }}
            </span>
        </div>
        
        <div class="btn-group w-100">
            <a href="{{ route('student.e-library.read', $book->id) }}" class="btn btn-sm btn-primary flex-grow-1">
                <i class="mdi mdi-book-open"></i> Read
            </a>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="toggleFavorite({{ $book->id }}, this, event)">
                <i class="mdi mdi-heart{{ $book->isFavorited(auth()->id()) ? '' : '-outline' }}"></i>
            </button>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
function toggleFavorite(bookId, btn, event) {
    event.preventDefault();
    event.stopPropagation();
    
    const icon = btn.querySelector('i');
    const wasFavorited = !icon.classList.contains('mdi-heart-outline');
    
    fetch(`/student/e-library/book/${bookId}/favorite`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.favorited) {
                icon.classList.remove('mdi-heart-outline');
                icon.classList.add('mdi-heart');
                btn.classList.remove('btn-outline-danger');
                btn.classList.add('btn-danger');
            } else {
                icon.classList.remove('mdi-heart');
                icon.classList.add('mdi-heart-outline');
                btn.classList.remove('btn-danger');
                btn.classList.add('btn-outline-danger');
            }
        }
    })
    .catch(error => {
        console.error('Error toggling favorite:', error);
    });
}
</script>
@endpush
@endonce
