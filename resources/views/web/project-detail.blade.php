@extends('web.layouts.master')
@section('title', $project->title)

@section('content')

<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h1>{{ $project->title }}</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('projects') }}">Projects</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ Str::limit($project->title, 50) }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</section>

<!-- Project Detail -->
<section class="project-detail pt-90 pb-90">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mb-40">
                <!-- Project Image -->
                @if($project->attach && upload_exists('project/'.$project->attach))
                <div class="project-featured-image mb-40">
                    <img src="{{ upload_asset('project/'.$project->attach) }}" alt="{{ $project->title }}" class="img-fluid rounded shadow">
                </div>
                @endif

                <!-- Project Info -->
                <div class="project-info mb-40">
                    <div class="project-meta d-flex flex-wrap gap-3 mb-30">
                        <span class="badge badge-{{ $project->status == 'ongoing' ? 'primary' : 'success' }} badge-lg">
                            <i class="fas fa-{{ $project->status == 'ongoing' ? 'spinner' : 'check-circle' }}"></i> 
                            {{ ucfirst($project->status) }}
                        </span>
                        @if($project->featured)
                        <span class="badge badge-warning badge-lg">
                            <i class="fas fa-star"></i> Featured
                        </span>
                        @endif
                        @if($project->faculty)
                        <span class="badge badge-secondary badge-lg">
                            <i class="fas fa-university"></i> {{ $project->faculty->title }}
                        </span>
                        @endif
                        @if($project->theme)
                        <span class="badge badge-info badge-lg">
                            <i class="fas fa-tag"></i> {{ $project->theme }}
                        </span>
                        @endif
                    </div>

                    <h3 class="mb-20">Project Overview</h3>
                    <div class="project-description">
                        {!! nl2br(e($project->description)) !!}
                    </div>
                </div>

                <!-- Project Timeline -->
                @if($project->start_date || $project->end_date)
                <div class="project-timeline mb-40">
                    <h4 class="mb-20">Project Timeline</h4>
                    <div class="timeline-info">
                        @if($project->start_date)
                        <p><strong>Start Date:</strong> {{ \Carbon\Carbon::parse($project->start_date)->format('F j, Y') }}</p>
                        @endif
                        @if($project->end_date)
                        <p><strong>End Date:</strong> {{ \Carbon\Carbon::parse($project->end_date)->format('F j, Y') }}</p>
                        @endif
                    </div>
                </div>
                @endif
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4 mb-40">
                <!-- Project Details Card -->
                <div class="card mb-30">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Project Details</h5>
                    </div>
                    <div class="card-body">
                        @if($project->lead_researcher)
                        <div class="detail-item mb-20">
                            <h6 class="text-muted mb-10">Lead Researcher</h6>
                            <p class="mb-0"><i class="fas fa-user-tie text-primary"></i> {{ $project->lead_researcher }}</p>
                        </div>
                        @endif

                        @if($project->faculty)
                        <div class="detail-item mb-20">
                            <h6 class="text-muted mb-10">Faculty</h6>
                            <p class="mb-0"><i class="fas fa-university text-primary"></i> {{ $project->faculty->title }}</p>
                        </div>
                        @endif

                        @if($project->theme)
                        <div class="detail-item mb-20">
                            <h6 class="text-muted mb-10">Research Theme</h6>
                            <p class="mb-0"><i class="fas fa-lightbulb text-primary"></i> {{ $project->theme }}</p>
                        </div>
                        @endif

                        <div class="detail-item mb-0">
                            <h6 class="text-muted mb-10">Status</h6>
                            <p class="mb-0">
                                <span class="badge badge-{{ $project->status == 'ongoing' ? 'primary' : 'success' }}">
                                    {{ ucfirst($project->status) }}
                                </span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Share Card -->
                <div class="card mb-30">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Share This Project</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="share-buttons">
                            <a href="https://www.facebook.com/sharer/sharer.php?u={{ url()->current() }}" target="_blank" class="btn btn-primary btn-sm mr-10 mb-10">
                                <i class="fab fa-facebook-f"></i> Facebook
                            </a>
                            <a href="https://twitter.com/intent/tweet?url={{ url()->current() }}&text={{ $project->title }}" target="_blank" class="btn btn-info btn-sm mr-10 mb-10">
                                <i class="fab fa-twitter"></i> Twitter
                            </a>
                            <a href="https://www.linkedin.com/shareArticle?mini=true&url={{ url()->current() }}&title={{ $project->title }}" target="_blank" class="btn btn-primary btn-sm mb-10">
                                <i class="fab fa-linkedin-in"></i> LinkedIn
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Contact Card -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Interested in This Research?</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-20">For collaboration opportunities or more information about this project, please contact us.</p>
                        <a href="{{ route('about') }}" class="btn btn-outline-primary btn-block">
                            <i class="fas fa-envelope"></i> Contact Us
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Related Projects -->
        @if($related->count() > 0)
        <div class="related-projects mt-60">
            <h3 class="mb-40">Related Projects</h3>
            <div class="row">
                @foreach($related as $relatedProject)
                <div class="col-lg-4 col-md-6 mb-40">
                    <div class="project-card">
                        @if($relatedProject->featured)
                        <span class="featured-badge">Featured</span>
                        @endif
                        
                        <div class="project-image">
                            @if($relatedProject->attach && upload_exists('project/'.$relatedProject->attach))
                            <img src="{{ upload_asset('project/'.$relatedProject->attach) }}" alt="{{ $relatedProject->title }}">
                            @else
                            <img src="{{ asset('web/img/project-default.jpg') }}" alt="{{ $relatedProject->title }}">
                            @endif
                        </div>
                        
                        <div class="project-content">
                            <div class="project-meta">
                                <span>
                                    <i class="fas fa-university"></i> {{ $relatedProject->faculty->title ?? 'N/A' }}
                                </span>
                            </div>
                            
                            <h5 class="project-title">
                                <a href="{{ route('project.detail', $relatedProject->id) }}">{{ Str::limit($relatedProject->title, 60) }}</a>
                            </h5>
                            
                            <p class="text-muted small mb-20">{{ Str::limit($relatedProject->description, 100) }}</p>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge badge-{{ $relatedProject->status == 'ongoing' ? 'primary' : 'success' }}">
                                    {{ ucfirst($relatedProject->status) }}
                                </span>
                                <a href="{{ route('project.detail', $relatedProject->id) }}" class="btn btn-sm btn-outline-primary">
                                    View <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</section>

<!-- Call to Action -->
<section class="cta-section py-90 bg-primary">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8 mb-30">
                <h2 class="text-white mb-10">Explore More Research Projects</h2>
                <p class="text-white opacity-90 mb-0">Discover the innovative research happening at PAX Higher Institute.</p>
            </div>
            <div class="col-lg-4 mb-30 text-lg-right">
                <a href="{{ route('projects') }}" class="btn btn-secondary btn-lg">View All Projects <i class="fas fa-arrow-right ml-10"></i></a>
            </div>
        </div>
    </div>
</section>

@endsection

@section('styles')
<style>
.project-featured-image img {
    width: 100%;
    height: auto;
    max-height: 500px;
    object-fit: cover;
}

.project-description {
    font-size: 16px;
    line-height: 1.8;
    color: #555;
}

.badge-lg {
    font-size: 14px;
    padding: 8px 15px;
}

.gap-3 {
    gap: 0.75rem !important;
}

.detail-item {
    padding-bottom: 20px;
    border-bottom: 1px solid #f0f0f0;
}

.detail-item:last-child {
    padding-bottom: 0;
    border-bottom: none;
}

.share-buttons a {
    margin: 0 5px;
}

.opacity-90 {
    opacity: 0.9;
}
</style>
@endsection
