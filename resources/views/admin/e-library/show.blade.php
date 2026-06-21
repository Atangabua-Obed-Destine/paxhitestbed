@extends('admin.layouts.master')

@section('title', 'Book Details')

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
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.e-library.index') }}">E-Library</a></li>
                <li class="breadcrumb-item active" aria-current="page">Book Details</li>
            </ul>
        </nav>
    </div>

    <div class="row">
        <!-- Book Information -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            @if($book->cover_image)
                                <img src="{{ asset('uploads/e-library/covers/' . $book->cover_image) }}" alt="{{ $book->title }}" class="img-thumbnail w-100" style="max-height: 400px; object-fit: cover;">
                            @else
                                <div class="bg-light d-flex align-items-center justify-content-center" style="height: 400px;">
                                    <i class="mdi mdi-book mdi-48px text-muted"></i>
                                </div>
                            @endif
                        </div>
                        <div class="col-md-9">
                            <h3 class="mb-2">{{ $book->title }}</h3>
                            @if($book->subtitle)
                                <h5 class="text-muted mb-3">{{ $book->subtitle }}</h5>
                            @endif

                            <div class="mb-3">
                                @if($book->featured)
                                    <span class="badge badge-gradient-success me-2">Featured</span>
                                @endif
                                @if($book->status)
                                    <span class="badge badge-success me-2">Active</span>
                                @else
                                    <span class="badge badge-danger me-2">Inactive</span>
                                @endif
                                @if($book->source == 'local')
                                    <span class="badge badge-gradient-primary">Uploaded</span>
                                @else
                                    <span class="badge badge-gradient-info">OpenLibrary</span>
                                @endif
                            </div>

                            <table class="table table-borderless">
                                <tr>
                                    <td width="150"><strong>Author(s):</strong></td>
                                    <td>{{ $book->authors_list }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Category:</strong></td>
                                    <td>
                                        @if($book->category)
                                            <span class="badge" style="background-color: {{ $book->category->color }}">
                                                <i class="{{ $book->category->icon }}"></i> {{ $book->category->name }}
                                            </span>
                                        @else
                                            <span class="text-muted">Uncategorized</span>
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
                                @if($book->isbn || $book->isbn_13)
                                <tr>
                                    <td><strong>ISBN:</strong></td>
                                    <td>
                                        {{ $book->isbn ?? $book->isbn_13 }}
                                        @if($book->isbn && $book->isbn_13)
                                            <br><small class="text-muted">ISBN-13: {{ $book->isbn_13 }}</small>
                                        @endif
                                    </td>
                                </tr>
                                @endif
                                @if($book->source == 'local' && $book->file_path)
                                <tr>
                                    <td><strong>File:</strong></td>
                                    <td>
                                        {{ $book->file_type }} 
                                        <span class="text-muted">({{ number_format($book->file_size / 1024 / 1024, 2) }} MB)</span>
                                        <br>
                                        @if($book->is_downloadable)
                                            <span class="badge badge-success">Downloadable</span>
                                        @else
                                            <span class="badge badge-warning">Read Online Only</span>
                                        @endif
                                    </td>
                                </tr>
                                @endif
                                <tr>
                                    <td><strong>Uploaded By:</strong></td>
                                    <td>
                                        {{ $book->uploader ? $book->uploader->name : 'System' }}
                                        <br>
                                        <small class="text-muted">{{ $book->created_at->format('F d, Y') }}</small>
                                    </td>
                                </tr>
                            </table>

                            @if($book->subjects && is_array($book->subjects) && count($book->subjects) > 0)
                            <div class="mb-3">
                                <strong>Subjects:</strong><br>
                                @foreach($book->subjects as $subject)
                                    <span class="badge badge-light me-1 mb-1">{{ $subject }}</span>
                                @endforeach
                            </div>
                            @endif

                            <div class="btn-group mt-3">
                                <a href="{{ route('admin.e-library.edit', $book->id) }}" class="btn btn-gradient-warning">
                                    <i class="mdi mdi-pencil"></i> Edit
                                </a>
                                @if($book->source == 'local' && $book->file_path)
                                    <a href="{{ asset('uploads/e-library/books/' . $book->file_path) }}" class="btn btn-gradient-info" target="_blank" download>
                                        <i class="mdi mdi-download"></i> Download
                                    </a>
                                @endif
                                @if($book->read_online_link)
                                    <a href="{{ $book->read_online_link }}" class="btn btn-gradient-success" target="_blank">
                                        <i class="mdi mdi-open-in-new"></i> View on OpenLibrary
                                    </a>
                                @endif
                                <form action="{{ route('admin.e-library.destroy', $book->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this book? This action cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-gradient-danger">
                                        <i class="mdi mdi-delete"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    @if($book->description)
                    <hr class="my-4">
                    <h5>Description</h5>
                    <p class="text-justify">{{ $book->description }}</p>
                    @endif
                </div>
            </div>

            <!-- Reviews Section -->
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Reviews ({{ $book->reviews->count() }})</h4>
                    
                    @if($book->reviews->count() > 0)
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Rating</th>
                                        <th>Review</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($book->reviews as $review)
                                        <tr>
                                            <td>{{ $review->student ? $review->student->name : ($review->user ? $review->user->name : 'Unknown') }}</td>
                                            <td>
                                                @for($i = 1; $i <= 5; $i++)
                                                    <i class="mdi mdi-star{{ $i <= $review->rating ? '' : '-outline' }} text-warning"></i>
                                                @endfor
                                            </td>
                                            <td>{{ Str::limit($review->review, 100) }}</td>
                                            <td>{{ $review->created_at->format('M d, Y') }}</td>
                                            <td>
                                                @if($review->status)
                                                    <span class="badge badge-success">Approved</span>
                                                @else
                                                    <span class="badge badge-warning">Pending</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted text-center py-4">No reviews yet.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Statistics -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-4">Statistics</h5>
                    
                    <div class="row">
                        <div class="col-6 mb-3">
                            <div class="card bg-gradient-info text-white">
                                <div class="card-body text-center">
                                    <i class="mdi mdi-eye mdi-36px mb-2"></i>
                                    <h4 class="mb-0">{{ number_format($book->views_count) }}</h4>
                                    <small>Views</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="card bg-gradient-success text-white">
                                <div class="card-body text-center">
                                    <i class="mdi mdi-download mdi-36px mb-2"></i>
                                    <h4 class="mb-0">{{ number_format($book->downloads_count) }}</h4>
                                    <small>Downloads</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="card bg-gradient-danger text-white">
                                <div class="card-body text-center">
                                    <i class="mdi mdi-heart mdi-36px mb-2"></i>
                                    <h4 class="mb-0">{{ number_format($book->favorites_count) }}</h4>
                                    <small>Favorites</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="card bg-gradient-warning text-white">
                                <div class="card-body text-center">
                                    <i class="mdi mdi-star mdi-36px mb-2"></i>
                                    <h4 class="mb-0">{{ $book->rating_avg > 0 ? number_format($book->rating_avg, 1) : 'N/A' }}</h4>
                                    <small>Rating ({{ $book->rating_count }})</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Readers -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-4">Recent Readers</h5>
                    
                    @if($book->readings->count() > 0)
                        <div class="list-group list-group-flush">
                            @foreach($book->readings->take(5) as $reading)
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>{{ $reading->student ? $reading->student->name : ($reading->user ? $reading->user->name : 'Unknown') }}</strong>
                                            <br>
                                            <small class="text-muted">{{ $reading->last_read_at ? $reading->last_read_at->diffForHumans() : 'N/A' }}</small>
                                        </div>
                                        <div class="text-end">
                                            <div class="progress" style="width: 80px; height: 20px;">
                                                <div class="progress-bar bg-success" role="progressbar" 
                                                     style="width: {{ $reading->progress_percentage }}%" 
                                                     aria-valuenow="{{ $reading->progress_percentage }}" 
                                                     aria-valuemin="0" aria-valuemax="100">
                                                    {{ round($reading->progress_percentage) }}%
                                                </div>
                                            </div>
                                            @if($reading->completed_at)
                                                <small class="text-success"><i class="mdi mdi-check"></i> Completed</small>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        
                        @if($book->readings->count() > 5)
                            <p class="text-muted text-center mt-3 mb-0">
                                And {{ $book->readings->count() - 5 }} more readers
                            </p>
                        @endif
                    @else
                        <p class="text-muted text-center py-3">No readers yet.</p>
                    @endif
                </div>
            </div>

            <!-- Activity Timeline -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-4">Activity Timeline</h5>
                    
                    <ul class="timeline">
                        <li class="timeline-item">
                            <div class="timeline-badge bg-primary">
                                <i class="mdi mdi-upload"></i>
                            </div>
                            <div class="timeline-panel">
                                <div class="timeline-heading">
                                    <h6 class="timeline-title">Book Added</h6>
                                </div>
                                <div class="timeline-body">
                                    <p class="text-muted mb-0">{{ $book->created_at->format('F d, Y \a\t h:i A') }}</p>
                                </div>
                            </div>
                        </li>
                        
                        @if($book->updated_at != $book->created_at)
                        <li class="timeline-item">
                            <div class="timeline-badge bg-warning">
                                <i class="mdi mdi-pencil"></i>
                            </div>
                            <div class="timeline-panel">
                                <div class="timeline-heading">
                                    <h6 class="timeline-title">Last Updated</h6>
                                </div>
                                <div class="timeline-body">
                                    <p class="text-muted mb-0">{{ $book->updated_at->diffForHumans() }}</p>
                                </div>
                            </div>
                        </li>
                        @endif

                        @if($book->readings()->where('completed_at', '!=', null)->count() > 0)
                        <li class="timeline-item">
                            <div class="timeline-badge bg-success">
                                <i class="mdi mdi-check"></i>
                            </div>
                            <div class="timeline-panel">
                                <div class="timeline-heading">
                                    <h6 class="timeline-title">Completions</h6>
                                </div>
                                <div class="timeline-body">
                                    <p class="text-muted mb-0">{{ $book->readings()->where('completed_at', '!=', null)->count() }} users completed</p>
                                </div>
                            </div>
                        </li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
.timeline {
    list-style: none;
    padding: 0;
    position: relative;
}

.timeline:before {
    top: 0;
    bottom: 0;
    position: absolute;
    content: " ";
    width: 2px;
    background-color: #e5e5e5;
    left: 15px;
    margin-right: -1.5px;
}

.timeline-item {
    margin-bottom: 20px;
    position: relative;
}

.timeline-badge {
    width: 30px;
    height: 30px;
    line-height: 30px;
    font-size: 14px;
    text-align: center;
    position: absolute;
    top: 0;
    left: 0;
    border-radius: 50%;
    color: #fff;
}

.timeline-panel {
    margin-left: 45px;
}

.timeline-title {
    margin-bottom: 5px;
    font-size: 14px;
    font-weight: 600;
}
</style>
@endpush
@endsection
