@extends('student.layouts.master')

@section('title', 'Reading History')

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">
            <span class="page-title-icon bg-gradient-info text-white me-2">
                <i class="mdi mdi-history"></i>
            </span> Reading History
        </h3>
        <nav aria-label="breadcrumb">
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('student.e-library.index') }}">E-Library</a></li>
                <li class="breadcrumb-item active" aria-current="page">History</li>
            </ul>
        </nav>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="card-title mb-0">
                            Your Reading History ({{ $readings->total() }})
                        </h4>
                    </div>

                    @if($readings->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="80">Cover</th>
                                        <th>Book Title</th>
                                        <th>Author(s)</th>
                                        <th>Progress</th>
                                        <th>Last Read</th>
                                        <th>Status</th>
                                        <th width="150">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($readings as $reading)
                                        @php $book = $reading->book; @endphp
                                        <tr>
                                            <td>
                                                @if($book->cover_image)
                                                    <img src="{{ asset('uploads/e-library/covers/' . $book->cover_image) }}" alt="{{ $book->title }}" 
                                                         class="img-thumbnail" style="width: 60px; height: 80px; object-fit: cover;">
                                                @else
                                                    <div class="bg-light d-flex align-items-center justify-content-center" 
                                                         style="width: 60px; height: 80px;">
                                                        <i class="mdi mdi-book text-muted"></i>
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('student.e-library.show', $book->id) }}" class="text-decoration-none">
                                                    <strong>{{ $book->title }}</strong>
                                                </a>
                                                @if($book->subtitle)
                                                    <br><small class="text-muted">{{ Str::limit($book->subtitle, 40) }}</small>
                                                @endif
                                            </td>
                                            <td>{{ Str::limit($book->authors_list, 30) }}</td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="progress flex-grow-1 me-2" style="height: 20px; max-width: 150px;">
                                                        <div class="progress-bar {{ $reading->completed_at ? 'bg-success' : 'bg-primary' }}" 
                                                             role="progressbar" 
                                                             style="width: {{ $reading->progress_percentage }}%">
                                                            {{ round($reading->progress_percentage) }}%
                                                        </div>
                                                    </div>
                                                </div>
                                                @if($reading->current_page && $reading->total_pages)
                                                    <small class="text-muted">
                                                        Page {{ $reading->current_page }} of {{ $reading->total_pages }}
                                                    </small>
                                                @endif
                                            </td>
                                            <td>
                                                {{ $reading->last_read_at->diffForHumans() }}
                                                <br>
                                                <small class="text-muted">
                                                    Started: {{ $reading->started_at->format('M d, Y') }}
                                                </small>
                                            </td>
                                            <td>
                                                @if($reading->completed_at)
                                                    <span class="badge badge-success">
                                                        <i class="mdi mdi-check-circle"></i> Completed
                                                    </span>
                                                    <br>
                                                    <small class="text-muted">{{ $reading->completed_at->format('M d, Y') }}</small>
                                                @else
                                                    <span class="badge badge-warning">
                                                        <i class="mdi mdi-book-open"></i> In Progress
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="btn-group-vertical w-100">
                                                    <a href="{{ route('student.e-library.read', $book->id) }}" 
                                                       class="btn btn-sm btn-primary mb-1">
                                                        <i class="mdi mdi-book-open"></i> 
                                                        {{ $reading->completed_at ? 'Read Again' : 'Continue' }}
                                                    </a>
                                                    <a href="{{ route('student.e-library.show', $book->id) }}" 
                                                       class="btn btn-sm btn-outline-info">
                                                        <i class="mdi mdi-information"></i> Details
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Statistics -->
                        <div class="row mt-4">
                            <div class="col-md-3">
                                <div class="card bg-gradient-primary text-white">
                                    <div class="card-body text-center">
                                        <i class="mdi mdi-book-multiple mdi-36px mb-2"></i>
                                        <h4>{{ $readings->total() }}</h4>
                                        <p class="mb-0">Books Read</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-gradient-success text-white">
                                    <div class="card-body text-center">
                                        <i class="mdi mdi-check-circle mdi-36px mb-2"></i>
                                        <h4>{{ $readings->filter(fn($r) => $r->completed_at)->count() }}</h4>
                                        <p class="mb-0">Completed</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-gradient-warning text-white">
                                    <div class="card-body text-center">
                                        <i class="mdi mdi-book-open mdi-36px mb-2"></i>
                                        <h4>{{ $readings->filter(fn($r) => !$r->completed_at)->count() }}</h4>
                                        <p class="mb-0">In Progress</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-gradient-info text-white">
                                    <div class="card-body text-center">
                                        <i class="mdi mdi-clock mdi-36px mb-2"></i>
                                        <h4>{{ gmdate('H:i', $readings->sum('time_spent')) }}</h4>
                                        <p class="mb-0">Time Spent</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pagination -->
                        @if($readings->hasPages())
                            <div class="mt-4 d-flex justify-content-center">
                                {{ $readings->links() }}
                            </div>
                        @endif
                    @else
                        <div class="text-center py-5">
                            <i class="mdi mdi-book-search mdi-72px text-muted"></i>
                            <h5 class="mt-3 text-muted">No Reading History</h5>
                            <p class="text-muted">Start reading books to track your progress!</p>
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
