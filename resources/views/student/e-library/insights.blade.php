@extends('student.layouts.master')
@section('content')

<!-- Page Header -->
<div class="page-header py-5" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="text-white mb-2">
                    <i class="fas fa-brain"></i> Reading Insights
                </h2>
                <p class="text-white-50 mb-0">
                    AI-powered analysis of your reading patterns and personalized suggestions
                </p>
            </div>
            <div class="col-md-4 text-md-right">
                <a href="{{ route('student.e-library.index') }}" class="btn btn-light">
                    <i class="fas fa-arrow-left"></i> Back to Library
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container my-5">

    @if(isset($no_data) && $no_data)
        <!-- No Data -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-chart-line fa-4x text-muted mb-3"></i>
                        <h4>No Reading Data Yet</h4>
                        <p class="text-muted">
                            Start reading books to unlock personalized insights about your reading habits!
                        </p>
                        <a href="{{ route('student.e-library.browse') }}" class="btn btn-primary mt-3">
                            <i class="fas fa-book"></i> Browse Books
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @else
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body">
                        <div class="text-primary mb-2">
                            <i class="fas fa-book fa-2x"></i>
                        </div>
                        <h3 class="mb-0">{{ $stats['total_books_read'] }}</h3>
                        <small class="text-muted">Books Read</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body">
                        <div class="text-success mb-2">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                        <h3 class="mb-0">{{ $stats['completed_books'] }}</h3>
                        <small class="text-muted">Completed</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body">
                        <div class="text-warning mb-2">
                            <i class="fas fa-book-open fa-2x"></i>
                        </div>
                        <h3 class="mb-0">{{ $stats['in_progress_books'] }}</h3>
                        <small class="text-muted">In Progress</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body">
                        <div class="text-info mb-2">
                            <i class="fas fa-percentage fa-2x"></i>
                        </div>
                        <h3 class="mb-0">{{ number_format($stats['average_progress'], 1) }}%</h3>
                        <small class="text-muted">Avg Progress</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- AI Insights -->
        @if($ai_insights)
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-gradient-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-robot"></i> AI Analysis of Your Reading Behavior
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="ai-insights" style="white-space: pre-wrap; line-height: 1.8; font-size: 1.05rem;">
                            {!! nl2br(e($ai_insights)) !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Category Breakdown -->
        <div class="row mb-4">
            <div class="col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie"></i> Reading by Category
                        </h5>
                    </div>
                    <div class="card-body">
                        @foreach($category_stats as $stat)
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="font-weight-bold">{{ $stat['name'] }}</span>
                                <span class="text-muted">{{ $stat['count'] }} books</span>
                            </div>
                            <div class="progress" style="height: 25px;">
                                <div class="progress-bar" 
                                     role="progressbar" 
                                     style="width: {{ $stat['avg_progress'] }}%; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);"
                                     aria-valuenow="{{ $stat['avg_progress'] }}" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                    {{ number_format($stat['avg_progress'], 0) }}% avg
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-history"></i> Recent Reading Activity
                        </h5>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        @foreach($recent_activity as $activity)
                        <div class="d-flex mb-3 pb-3 border-bottom">
                            <div class="mr-3">
                                @if($activity->book->cover_image)
                                <img src="{{ asset('uploads/e-library/covers/' . $activity->book->cover_image) }}" 
                                     alt="{{ $activity->book->title }}" 
                                     class="rounded" 
                                     style="width: 50px; height: 70px; object-fit: cover;">
                                @else
                                <div class="rounded d-flex align-items-center justify-content-center" 
                                     style="width: 50px; height: 70px; background: {{ $activity->book->category->color ?? '#6c757d' }};">
                                    <i class="{{ $activity->book->category->icon ?? 'fas fa-book' }} text-white"></i>
                                </div>
                                @endif
                            </div>
                            <div class="flex-fill">
                                <h6 class="mb-1">{{ $activity->book->title }}</h6>
                                <small class="text-muted">{{ $activity->book->authors }}</small>
                                <div class="progress mt-2" style="height: 5px;">
                                    <div class="progress-bar bg-success" 
                                         role="progressbar" 
                                         style="width: {{ $activity->progress_percentage }}%"
                                         aria-valuenow="{{ $activity->progress_percentage }}" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                    </div>
                                </div>
                                <small class="text-muted">
                                    {{ number_format($activity->progress_percentage, 0) }}% • 
                                    {{ $activity->updated_at->diffForHumans() }}
                                </small>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Cards -->
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-center text-white py-4">
                        <i class="fas fa-magic fa-3x mb-3"></i>
                        <h5 class="mb-3">Get Personalized Recommendations</h5>
                        <p class="mb-3">
                            Based on your reading history, our AI can suggest books tailored to your taste.
                        </p>
                        <a href="{{ route('student.e-library.ai.recommendations') }}" class="btn btn-light">
                            <i class="fas fa-sparkles"></i> View Recommendations
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-center text-white py-4">
                        <i class="fas fa-trophy fa-3x mb-3"></i>
                        <h5 class="mb-3">Reading Goals & Challenges</h5>
                        <p class="mb-3">
                            Set reading goals and track your progress. Challenge yourself to read more!
                        </p>
                        <a href="{{ route('student.e-library.history') }}" class="btn btn-light">
                            <i class="fas fa-chart-line"></i> View Reading History
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tips Card -->
        <div class="row">
            <div class="col-12">
                <div class="alert alert-info border-0 shadow-sm">
                    <h5><i class="fas fa-lightbulb"></i> Tips to Improve Your Reading Experience</h5>
                    <ul class="mb-0">
                        <li>Try exploring different genres to broaden your horizons</li>
                        <li>Set a daily reading time to build a consistent habit</li>
                        <li>Use the bookmarks feature to save your favorite passages</li>
                        <li>Write reviews to help other readers discover great books</li>
                        <li>Check back regularly for new AI-powered recommendations</li>
                    </ul>
                </div>
            </div>
        </div>
    @endif

</div>

@endsection

@section('style')
<style>
    .bg-gradient-success {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    }
    
    .ai-insights {
        font-family: 'Georgia', serif;
        color: #333;
    }
    
    .card {
        transition: all 0.3s ease;
    }
    
    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 .5rem 1.5rem rgba(0,0,0,.15)!important;
    }
    
    .progress {
        border-radius: 10px;
    }
    
    .progress-bar {
        border-radius: 10px;
    }
</style>
@endsection
