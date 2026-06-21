@extends('admin.layouts.master')

@section('title', 'E-Library Statistics')

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">
            <span class="page-title-icon bg-gradient-primary text-white me-2">
                <i class="mdi mdi-chart-bar"></i>
            </span> E-Library Statistics
        </h3>
        <nav aria-label="breadcrumb">
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.e-library.index') }}">E-Library</a></li>
                <li class="breadcrumb-item active" aria-current="page">Statistics</li>
            </ul>
        </nav>
    </div>

    <!-- Overview Cards -->
    <div class="row">
        <div class="col-xl-3 col-sm-6 mb-4">
            <div class="card bg-gradient-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-2">Total Books</h6>
                            <h2 class="mb-0">{{ $stats['total_books'] }}</h2>
                            <small>{{ $stats['active_books'] }} active</small>
                        </div>
                        <div>
                            <i class="mdi mdi-book-multiple mdi-48px"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-sm-6 mb-4">
            <div class="card bg-gradient-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-2">Total Views</h6>
                            <h2 class="mb-0">{{ number_format($stats['total_views']) }}</h2>
                            <small>All time</small>
                        </div>
                        <div>
                            <i class="mdi mdi-eye mdi-48px"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-sm-6 mb-4">
            <div class="card bg-gradient-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-2">Total Downloads</h6>
                            <h2 class="mb-0">{{ number_format($stats['total_downloads']) }}</h2>
                            <small>All time</small>
                        </div>
                        <div>
                            <i class="mdi mdi-download mdi-48px"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-sm-6 mb-4">
            <div class="card bg-gradient-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-2">Active Readers</h6>
                            <h2 class="mb-0">{{ $stats['active_readers'] }}</h2>
                            <small>Last 30 days</small>
                        </div>
                        <div>
                            <i class="mdi mdi-account-multiple mdi-48px"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Stats Row -->
    <div class="row">
        <div class="col-xl-3 col-sm-6 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Favorites</h6>
                            <h3 class="mb-0">{{ number_format($stats['total_favorites']) }}</h3>
                        </div>
                        <i class="mdi mdi-heart text-danger mdi-36px"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-sm-6 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Reviews</h6>
                            <h3 class="mb-0">{{ $stats['total_reviews'] }}</h3>
                        </div>
                        <i class="mdi mdi-comment-text-multiple text-info mdi-36px"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-sm-6 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Avg. Rating</h6>
                            <h3 class="mb-0">{{ number_format($stats['avg_rating'], 1) }}</h3>
                        </div>
                        <i class="mdi mdi-star text-warning mdi-36px"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-sm-6 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Completed Reads</h6>
                            <h3 class="mb-0">{{ $stats['completed_reads'] }}</h3>
                        </div>
                        <i class="mdi mdi-book-check text-success mdi-36px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Top 10 Most Viewed Books -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-4">
                        <i class="mdi mdi-trophy text-warning"></i> Top 10 Most Viewed Books
                    </h4>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Title</th>
                                    <th>Views</th>
                                    <th>Rating</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($topViewedBooks as $index => $book)
                                    <tr>
                                        <td>
                                            <span class="badge badge-gradient-{{ $index < 3 ? 'warning' : 'secondary' }}">
                                                {{ $index + 1 }}
                                            </span>
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.e-library.show', $book->id) }}" class="text-decoration-none">
                                                {{ Str::limit($book->title, 40) }}
                                            </a>
                                            <br>
                                            <small class="text-muted">{{ Str::limit($book->authors_list, 30) }}</small>
                                        </td>
                                        <td>
                                            <strong>{{ number_format($book->views_count) }}</strong>
                                        </td>
                                        <td>
                                            @if($book->rating_avg > 0)
                                                <span class="text-warning">
                                                    <i class="mdi mdi-star"></i> {{ number_format($book->rating_avg, 1) }}
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top 10 Most Downloaded Books -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-4">
                        <i class="mdi mdi-download-multiple text-success"></i> Top 10 Most Downloaded Books
                    </h4>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Title</th>
                                    <th>Downloads</th>
                                    <th>Rating</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($topDownloadedBooks as $index => $book)
                                    <tr>
                                        <td>
                                            <span class="badge badge-gradient-{{ $index < 3 ? 'success' : 'secondary' }}">
                                                {{ $index + 1 }}
                                            </span>
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.e-library.show', $book->id) }}" class="text-decoration-none">
                                                {{ Str::limit($book->title, 40) }}
                                            </a>
                                            <br>
                                            <small class="text-muted">{{ Str::limit($book->authors_list, 30) }}</small>
                                        </td>
                                        <td>
                                            <strong>{{ number_format($book->downloads_count) }}</strong>
                                        </td>
                                        <td>
                                            @if($book->rating_avg > 0)
                                                <span class="text-warning">
                                                    <i class="mdi mdi-star"></i> {{ number_format($book->rating_avg, 1) }}
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Category Distribution Chart -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-4">
                        <i class="mdi mdi-chart-pie text-info"></i> Books by Category
                    </h4>
                    <div style="height: 300px;">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Source Distribution Chart -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-4">
                        <i class="mdi mdi-chart-donut text-primary"></i> Books by Source
                    </h4>
                    <div style="height: 300px;">
                        <canvas id="sourceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Category Performance -->
        <div class="col-lg-12 mb-4">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-4">
                        <i class="mdi mdi-chart-bar text-success"></i> Category Performance
                    </h4>
                    <div style="height: 350px;">
                        <canvas id="categoryPerformanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Activity -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-4">
                        <i class="mdi mdi-history text-primary"></i> Recent Activity
                    </h4>
                    <div class="activity-timeline" style="max-height: 450px; overflow-y: auto;">
                        @foreach($recentActivity as $activity)
                            <div class="activity-item mb-3 pb-3 border-bottom">
                                <div class="d-flex">
                                    <div class="activity-icon me-3">
                                        @if($activity['type'] == 'book_added')
                                            <i class="mdi mdi-book-plus text-success mdi-24px"></i>
                                        @elseif($activity['type'] == 'review')
                                            <i class="mdi mdi-comment-text text-info mdi-24px"></i>
                                        @elseif($activity['type'] == 'reading')
                                            <i class="mdi mdi-book-open text-primary mdi-24px"></i>
                                        @endif
                                    </div>
                                    <div class="flex-grow-1">
                                        <p class="mb-1">{{ $activity['message'] }}</p>
                                        <small class="text-muted">{{ $activity['time'] }}</small>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Rated Books -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-4">
                        <i class="mdi mdi-star-circle text-warning"></i> Top Rated Books
                    </h4>
                    <div class="list-group list-group-flush" style="max-height: 450px; overflow-y: auto;">
                        @foreach($topRatedBooks as $book)
                            <div class="list-group-item px-0">
                                <div class="d-flex align-items-center">
                                    @if($book->cover_image)
                                        <img src="{{ asset($book->cover_image) }}" alt="{{ $book->title }}" 
                                             class="me-3 rounded" style="width: 50px; height: 70px; object-fit: cover;">
                                    @else
                                        <div class="bg-light d-flex align-items-center justify-content-center me-3 rounded" 
                                             style="width: 50px; height: 70px;">
                                            <i class="mdi mdi-book text-muted"></i>
                                        </div>
                                    @endif
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">
                                            <a href="{{ route('admin.e-library.show', $book->id) }}" class="text-decoration-none">
                                                {{ Str::limit($book->title, 35) }}
                                            </a>
                                        </h6>
                                        <small class="text-muted">{{ Str::limit($book->authors_list, 30) }}</small>
                                    </div>
                                    <div class="text-end">
                                        <div class="text-warning h5 mb-0">
                                            <i class="mdi mdi-star"></i> {{ number_format($book->rating_avg, 1) }}
                                        </div>
                                        <small class="text-muted">{{ $book->rating_count }} reviews</small>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reading Trends -->
    <div class="row">
        <div class="col-lg-12 mb-4">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-4">
                        <i class="mdi mdi-chart-line text-primary"></i> Reading Activity (Last 30 Days)
                    </h4>
                    <div style="height: 300px;">
                        <canvas id="readingTrendsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// Category Distribution Chart
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
const categoryChart = new Chart(categoryCtx, {
    type: 'doughnut',
    data: {
        labels: {!! json_encode($categoryStats->pluck('name')) !!},
        datasets: [{
            data: {!! json_encode($categoryStats->pluck('books_count')) !!},
            backgroundColor: {!! json_encode($categoryStats->pluck('color')) !!},
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        aspectRatio: 2,
        plugins: {
            legend: {
                position: 'right',
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': ' + context.parsed + ' books';
                    }
                }
            }
        }
    }
});

// Source Distribution Chart
const sourceCtx = document.getElementById('sourceChart').getContext('2d');
const sourceChart = new Chart(sourceCtx, {
    type: 'pie',
    data: {
        labels: ['Uploaded Books', 'OpenLibrary Books'],
        datasets: [{
            data: [{{ $stats['local_books'] }}, {{ $stats['openlibrary_books'] }}],
            backgroundColor: ['#667eea', '#17a2b8'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        aspectRatio: 2,
        plugins: {
            legend: {
                position: 'bottom',
            }
        }
    }
});

// Category Performance Chart
const categoryPerfCtx = document.getElementById('categoryPerformanceChart').getContext('2d');
const categoryPerfChart = new Chart(categoryPerfCtx, {
    type: 'bar',
    data: {
        labels: {!! json_encode($categoryStats->pluck('name')) !!},
        datasets: [
            {
                label: 'Books',
                data: {!! json_encode($categoryStats->pluck('books_count')) !!},
                backgroundColor: '#667eea',
            },
            {
                label: 'Total Views',
                data: {!! json_encode($categoryStats->pluck('total_views')) !!},
                backgroundColor: '#28a745',
            },
            {
                label: 'Total Downloads',
                data: {!! json_encode($categoryStats->pluck('total_downloads')) !!},
                backgroundColor: '#17a2b8',
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        aspectRatio: 2.5,
        scales: {
            y: {
                beginAtZero: true
            }
        },
        plugins: {
            legend: {
                position: 'top',
            }
        }
    }
});

// Reading Trends Chart
const trendsCtx = document.getElementById('readingTrendsChart').getContext('2d');
const trendsChart = new Chart(trendsCtx, {
    type: 'line',
    data: {
        labels: {!! json_encode($readingTrends->pluck('date')) !!},
        datasets: [
            {
                label: 'Views',
                data: {!! json_encode($readingTrends->pluck('views')) !!},
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4,
                fill: true
            },
            {
                label: 'Downloads',
                data: {!! json_encode($readingTrends->pluck('downloads')) !!},
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4,
                fill: true
            },
            {
                label: 'New Readers',
                data: {!! json_encode($readingTrends->pluck('new_readers')) !!},
                borderColor: '#ffc107',
                backgroundColor: 'rgba(255, 193, 7, 0.1)',
                tension: 0.4,
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        aspectRatio: 3,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        scales: {
            y: {
                beginAtZero: true
            }
        },
        plugins: {
            legend: {
                position: 'top',
            }
        }
    }
});
</script>
@endpush
@endsection
