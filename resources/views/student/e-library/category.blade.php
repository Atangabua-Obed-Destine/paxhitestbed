@extends('student.layouts.master')

@section('title', $category->name)

@section('content')
<div class="content-wrapper">
    <div class="page-header" style="background: linear-gradient(135deg, {{ $category->color }}22, {{ $category->color }}44); border-radius: 8px; padding: 30px;">
        <div>
            <h3 class="page-title mb-3">
                <span class="page-title-icon text-white me-2" style="background-color: {{ $category->color }}">
                    <i class="{{ $category->icon }}"></i>
                </span> 
                <span style="color: {{ $category->color }}">{{ $category->name }}</span>
            </h3>
            @if($category->description)
                <p class="mb-0">{{ $category->description }}</p>
            @endif
        </div>
        <nav aria-label="breadcrumb">
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('student.e-library.index') }}">E-Library</a></li>
                <li class="breadcrumb-item"><a href="{{ route('student.e-library.browse') }}">Browse</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $category->name }}</li>
            </ul>
        </nav>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0">
                            {{ $books->total() }} {{ Str::plural('Book', $books->total()) }} in {{ $category->name }}
                        </h5>
                        <div class="d-flex align-items-center">
                            <label class="me-2 mb-0">Sort by:</label>
                            <select name="sort" class="form-select form-select-sm" onchange="sortBooks(this.value)" style="width: auto;">
                                <option value="recent" {{ request('sort') == 'recent' ? 'selected' : '' }}>Recent</option>
                                <option value="popular" {{ request('sort') == 'popular' ? 'selected' : '' }}>Popular</option>
                                <option value="rating" {{ request('sort') == 'rating' ? 'selected' : '' }}>Rating</option>
                                <option value="title" {{ request('sort') == 'title' ? 'selected' : '' }}>Title (A-Z)</option>
                            </select>
                        </div>
                    </div>

                    @if($books->count() > 0)
                        <div class="row">
                            @foreach($books as $book)
                                <div class="col-lg-2 col-md-3 col-sm-4 col-6 mb-4">
                                    @include('student.e-library.partials.book-card', ['book' => $book])
                                </div>
                            @endforeach
                        </div>

                        <!-- Pagination -->
                        @if($books->hasPages())
                            <div class="mt-4 d-flex justify-content-center">
                                {{ $books->appends(request()->query())->links() }}
                            </div>
                        @endif
                    @else
                        <div class="text-center py-5">
                            <i class="mdi mdi-book-remove mdi-72px text-muted"></i>
                            <h5 class="mt-3 text-muted">No Books in This Category Yet</h5>
                            <p class="text-muted">Check back later for new additions!</p>
                            <a href="{{ route('student.e-library.browse') }}" class="btn btn-primary mt-3">
                                <i class="mdi mdi-arrow-left"></i> Browse All Books
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function sortBooks(sort) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', sort);
    window.location.href = url.toString();
}
</script>
@endpush
@endsection
