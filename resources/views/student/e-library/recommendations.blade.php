@extends('student.layouts.master')
@section('content')

<!-- Page Header -->
<div class="page-header py-5" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="text-white mb-2">
                    <i class="fas fa-magic"></i> AI Book Recommendations
                </h2>
                <p class="text-white-50 mb-0">
                    Personalized suggestions powered by artificial intelligence
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

    @if(isset($no_history) && $no_history)
        <!-- No Reading History -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-book-reader fa-4x text-muted mb-3"></i>
                        <h4>Start Your Reading Journey</h4>
                        <p class="text-muted">
                            We need to learn about your reading preferences first. Start reading some books to get personalized recommendations!
                        </p>
                        
                        <h5 class="mt-5 mb-3">Popular Books to Get Started</h5>
                        <div class="row">
                            @foreach($popular_books as $book)
                            <div class="col-md-3 col-sm-6 mb-4">
                                @include('student.e-library.partials.book-card', ['book' => $book])
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <!-- AI Recommendations Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-info border-0 shadow-sm">
                    <i class="fas fa-info-circle"></i> 
                    <strong>How it works:</strong> Our AI analyzes your reading history, favorite books, and preferences to suggest books you'll love. The more you read, the better the recommendations!
                </div>
            </div>
        </div>

        @if($recommendations)
            @if(isset($recommendations['parsed']) && !$recommendations['parsed'])
                <!-- AI Text Response -->
                <div class="row mb-5">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-gradient-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-robot"></i> AI Recommendations
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="ai-response" style="white-space: pre-wrap; line-height: 1.8;">
                                    {{ $recommendations['raw_text'] }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <!-- Structured AI Recommendations -->
                <div class="row mb-5">
                    <div class="col-12">
                        <h4 class="mb-4">
                            <i class="fas fa-sparkles text-warning"></i> 
                            Recommended Just For You
                        </h4>
                    </div>
                    
                    @foreach($recommendations as $index => $recommendation)
                    <div class="col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-start">
                                    <div class="recommendation-number mr-3">
                                        <span class="badge badge-lg" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); font-size: 1.2rem; width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center;">
                                            {{ $index + 1 }}
                                        </span>
                                    </div>
                                    <div class="flex-fill">
                                        <h5 class="mb-1">{{ $recommendation['title'] ?? 'N/A' }}</h5>
                                        <p class="text-muted mb-2">
                                            <i class="fas fa-user"></i> {{ $recommendation['author'] ?? 'Unknown Author' }}
                                        </p>
                                        
                                        @if(isset($recommendation['category']))
                                        <span class="badge badge-primary mb-2">{{ $recommendation['category'] }}</span>
                                        @endif
                                        
                                        <p class="mb-3" style="font-size: 0.95rem;">
                                            <strong>Why we recommend this:</strong><br>
                                            {{ $recommendation['reason'] ?? 'Based on your reading preferences' }}
                                        </p>
                                        
                                        <a href="{{ route('student.e-library.browse') }}?q={{ urlencode($recommendation['title']) }}" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-search"></i> Find This Book
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        @endif

        <!-- Similar Books from Database -->
        @if($similar_books->count() > 0)
        <div class="row mt-5">
            <div class="col-12">
                <h4 class="mb-4">
                    <i class="fas fa-book"></i> 
                    More Books You Might Like
                </h4>
                <p class="text-muted mb-4">Based on your favorite categories</p>
            </div>
            
            @foreach($similar_books as $book)
            <div class="col-md-3 col-sm-6 mb-4">
                @include('student.e-library.partials.book-card', ['book' => $book])
            </div>
            @endforeach
        </div>
        @endif

        <!-- Reading Stats Card -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-center text-white py-4">
                        <h5 class="mb-3">Want Even Better Recommendations?</h5>
                        <p class="mb-3">
                            The more you read and interact with books, the smarter our AI becomes at understanding your preferences!
                        </p>
                        <div class="btn-group">
                            <a href="{{ route('student.e-library.browse') }}" class="btn btn-light">
                                <i class="fas fa-book"></i> Browse Library
                            </a>
                            <a href="{{ route('student.e-library.ai.insights') }}" class="btn btn-outline-light">
                                <i class="fas fa-chart-line"></i> View Reading Insights
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>

@endsection

@section('style')
<style>
    .bg-gradient-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .badge-lg {
        border-radius: 50%;
    }
    
    .ai-response {
        font-family: 'Georgia', serif;
        font-size: 1.05rem;
        color: #333;
    }
    
    .recommendation-number {
        flex-shrink: 0;
    }
    
    .card {
        transition: all 0.3s ease;
    }
    
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 .5rem 1.5rem rgba(0,0,0,.15)!important;
    }
</style>
@endsection
