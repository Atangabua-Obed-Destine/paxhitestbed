@extends('student.layouts.master')

@section('title', 'My Favorites')

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">
            <span class="page-title-icon bg-gradient-danger text-white me-2">
                <i class="mdi mdi-heart"></i>
            </span> My Favorites
        </h3>
        <nav aria-label="breadcrumb">
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('student.e-library.index') }}">E-Library</a></li>
                <li class="breadcrumb-item active" aria-current="page">Favorites</li>
            </ul>
        </nav>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="card-title mb-0">
                            Your Favorite Books ({{ $favorites->total() }})
                        </h4>
                    </div>

                    @if($favorites->count() > 0)
                        <div class="row">
                            @foreach($favorites as $favorite)
                                @php $book = $favorite->book; @endphp
                                <div class="col-lg-2 col-md-3 col-sm-4 col-6 mb-4">
                                    @include('student.e-library.partials.book-card', ['book' => $book])
                                </div>
                            @endforeach
                        </div>

                        <!-- Pagination -->
                        @if($favorites->hasPages())
                            <div class="mt-4 d-flex justify-content-center">
                                {{ $favorites->links() }}
                            </div>
                        @endif
                    @else
                        <div class="text-center py-5">
                            <i class="mdi mdi-heart-broken mdi-72px text-muted"></i>
                            <h5 class="mt-3 text-muted">No Favorites Yet</h5>
                            <p class="text-muted">Start adding books to your favorites!</p>
                            <a href="{{ route('student.e-library.browse') }}" class="btn btn-primary mt-3">
                                <i class="mdi mdi-magnify"></i> Browse Books
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
