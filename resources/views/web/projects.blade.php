@extends('web.layouts.master')
@section('title', 'Research Projects')

@section('social_meta_tags')
    @if(isset($setting))
    <!-- Open Graph Meta Tags -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $setting->title }}"/>
    <meta property="og:title" content="Research & Projects | {{ $setting->title }}"/>
    <meta property="og:description" content="Explore cutting-edge research projects and academic initiatives at PAX Higher Institute. Discover our contributions to innovation and knowledge."/>
    <meta property="og:url" content="{{ route('projects') }}"/>
    <meta property="og:image" content="{{ asset('/uploads/setting/'.$setting->logo_path) }}"/>

    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:site" content="{!! '@'.str_replace(' ', '', $setting->title) !!}" />
    <meta name="twitter:url" content="{{ route('projects') }}" />
    <meta name="twitter:title" content="Research & Projects | {{ $setting->title }}" />
    <meta name="twitter:description" content="Explore cutting-edge research projects and academic initiatives at PAX Higher Institute. Discover our contributions to innovation and knowledge." />
    <meta name="twitter:image" content="{{ asset('/uploads/setting/'.$setting->logo_path) }}" />
    
    <!-- Page-Specific Meta Tags -->
    <meta name="description" content="Explore cutting-edge research projects and academic initiatives at PAX Higher Institute. Discover our contributions to innovation and knowledge.">
    <meta name="keywords" content="research, projects, {{ $setting->title }}, academic research, innovation, research projects, scholarly work, academic initiatives">
    <link rel="canonical" href="{{ route('projects') }}">
    @endif
@endsection

@section('structured_data')
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": [{
        "@type": "ListItem",
        "position": 1,
        "name": "Home",
        "item": "{{ route('home') }}"
    },{
        "@type": "ListItem",
        "position": 2,
        "name": "Research & Projects",
        "item": "{{ route('projects') }}"
    }]
}
</script>
@endsection

@section('content')

<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h1>Research & Projects</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Projects</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</section>

<!-- Projects Section -->
<section class="projects-section pt-90 pb-90">
    <div class="container">
        <!-- Section Header -->
        <div class="row mb-60">
            <div class="col-12">
                <div class="section-title center" data-aos="fade-up">
                    <h5 style="color: #0066CC; font-weight: 600; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 15px;">
                        <i class="fas fa-flask"></i> Research & Innovation
                    </h5>
                    <h2 style="font-size: 38px; font-weight: 700; color: #003366; margin-bottom: 15px;">
                        Our Research Projects
                    </h2>
                    <div style="width: 80px; height: 4px; background: linear-gradient(90deg, #0066CC, #FF6B35); border-radius: 2px; margin: 15px auto 20px;"></div>
                    <p style="color: #666666; font-size: 16px; max-width: 700px; margin: 0 auto;">
                        Discover our groundbreaking research projects advancing knowledge and innovation across various disciplines
                    </p>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
        <div class="row mb-50">
            <div class="col-12">
                <form method="GET" action="{{ route('projects') }}" class="projects-filter-form" data-aos="fade-up" data-aos-delay="100">
                    <div class="row">
                        <div class="col-lg-3 col-md-6 mb-20">
                            <label style="font-weight: 600; color: #003366; margin-bottom: 10px; display: block; font-size: 14px;">
                                <i class="fas fa-university"></i> Faculty
                            </label>
                            <select name="faculty" class="form-control" onchange="this.form.submit()">
                                <option value="">All Faculties</option>
                                @foreach($faculties as $faculty)
                                <option value="{{ $faculty->id }}" {{ request('faculty') == $faculty->id ? 'selected' : '' }}>
                                    {{ $faculty->title }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-20">
                            <label style="font-weight: 600; color: #003366; margin-bottom: 10px; display: block; font-size: 14px;">
                                <i class="fas fa-lightbulb"></i> Theme
                            </label>
                            <select name="theme" class="form-control" onchange="this.form.submit()">
                                <option value="">All Themes</option>
                                @foreach($themes as $theme)
                                <option value="{{ $theme }}" {{ request('theme') == $theme ? 'selected' : '' }}>
                                    {{ $theme }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-20">
                            <label style="font-weight: 600; color: #003366; margin-bottom: 10px; display: block; font-size: 14px;">
                                <i class="fas fa-tasks"></i> Status
                            </label>
                            <select name="status" class="form-control" onchange="this.form.submit()">
                                <option value="">All Status</option>
                                <option value="ongoing" {{ request('status') == 'ongoing' ? 'selected' : '' }}>Ongoing</option>
                                <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-20">
                            @if(request('faculty') || request('theme') || request('status'))
                            <label style="font-weight: 600; color: #003366; margin-bottom: 10px; display: block; font-size: 14px; opacity: 0;">
                                &nbsp;
                            </label>
                            <a href="{{ route('projects') }}" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-times"></i> Clear Filters
                            </a>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Projects Grid -->
        @if($projects->count() > 0)
        <div class="row">
            @foreach($projects as $index => $project)
            <div class="col-lg-4 col-md-6 mb-40" data-aos="fade-up" data-aos-delay="{{ ($index % 3) * 100 + 100 }}">
                <div class="project-card h-100">
                    @if($project->featured)
                    <span class="featured-badge">
                        <i class="fas fa-star"></i> Featured
                    </span>
                    @endif
                    
                    <div class="project-image">
                        @if($project->attach && upload_exists('project/'.$project->attach))
                        <img src="{{ upload_asset('project/'.$project->attach) }}" alt="{{ $project->title }}">
                        @else
                        <img src="{{ asset('web/img/project-default.jpg') }}" alt="{{ $project->title }}">
                        @endif
                        <div class="project-overlay">
                            <a href="{{ route('project.detail', $project->id) }}" class="view-project-btn">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                    </div>
                    
                    <div class="project-content">
                        <div class="project-meta-badges">
                            <span class="meta-badge faculty-badge">
                                <i class="fas fa-university"></i> {{ $project->faculty->title ?? 'N/A' }}
                            </span>
                            @if($project->theme)
                            <span class="meta-badge theme-badge">
                                <i class="fas fa-tag"></i> {{ $project->theme }}
                            </span>
                            @endif
                        </div>
                        
                        <h4 class="project-title">
                            <a href="{{ route('project.detail', $project->id) }}">{{ $project->title }}</a>
                        </h4>
                        
                        <p class="project-description">{{ Str::limit($project->description, 130) }}</p>
                        
                        <div class="project-footer">
                            @if($project->lead_researcher)
                            <div class="researcher-info">
                                <i class="fas fa-user-tie"></i>
                                <span><strong>Lead:</strong> {{ $project->lead_researcher }}</span>
                            </div>
                            @endif
                            
                            <div class="project-actions">
                                <span class="status-badge status-{{ $project->status }}">
                                    <i class="fas fa-{{ $project->status == 'ongoing' ? 'spinner' : 'check-circle' }}"></i>
                                    {{ ucfirst($project->status) }}
                                </span>
                                <a href="{{ route('project.detail', $project->id) }}" class="btn-learn-more">
                                    Learn More <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="row mt-50">
            <div class="col-12">
                <div data-aos="fade-up">
                    {{ $projects->appends(request()->query())->links() }}
                </div>
            </div>
        </div>
        @else
        <div class="row">
            <div class="col-12">
                <div class="alert alert-info text-center" data-aos="fade-up" style="padding: 60px 20px; border-radius: 15px; background: #e8f4fd; border: 2px dashed #0066CC;">
                    <i class="fas fa-info-circle" style="font-size: 48px; color: #0066CC; margin-bottom: 20px;"></i>
                    <h4 style="color: #003366; font-weight: 600;">No projects found</h4>
                    <p style="color: #666666;">Try adjusting your filters or check back later for new research projects.</p>
                    @if(request('faculty') || request('theme') || request('status'))
                    <a href="{{ route('projects') }}" class="btn btn-primary mt-3">
                        <i class="fas fa-redo"></i> View All Projects
                    </a>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>
</section>

<!-- Research Impact Stats -->
<section class="research-stats-section">
    <div class="container">
        <div class="row mb-50">
            <div class="col-12">
                <div class="section-title center" data-aos="fade-up">
                    <h5 style="color: #FF6B35; font-weight: 600; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 15px;">
                        <i class="fas fa-chart-line"></i> Our Impact
                    </h5>
                    <h2 style="font-size: 34px; font-weight: 700; color: #ffffff !important; margin-bottom: 0;">
                        Research Impact & Achievements
                    </h2>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-3 col-md-6 mb-30" data-aos="fade-up" data-aos-delay="100">
                <div class="research-stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-project-diagram"></i>
                    </div>
                    <h3 class="stat-counter" data-target="50">0</h3>
                    <p class="stat-label">Active Projects</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-30" data-aos="fade-up" data-aos-delay="200">
                <div class="research-stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="stat-counter" data-target="100">0</h3>
                    <p class="stat-label">Researchers</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-30" data-aos="fade-up" data-aos-delay="300">
                <div class="research-stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h3 class="stat-counter" data-target="200">0</h3>
                    <p class="stat-label">Publications</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-30" data-aos="fade-up" data-aos-delay="400">
                <div class="research-stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <h3 class="stat-counter" data-target="30">0</h3>
                    <p class="stat-label">Research Partners</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section class="projects-cta-section">
    <div class="decorative-shape shape-1"></div>
    <div class="decorative-shape shape-2"></div>
    
    <div class="container">
        <div class="row align-items-center" data-aos="fade-up">
            <div class="col-lg-8 mb-30">
                <h2 style="color: #ffffff !important; font-size: 34px; font-weight: 700; margin-bottom: 15px;">
                    Interested in Collaborating on Research?
                </h2>
                <p style="color: #ffffff !important; font-size: 16px; margin-bottom: 0; opacity: 0.95;">
                    Contact us to explore partnership opportunities and collaborative research initiatives that drive innovation.
                </p>
            </div>
            <div class="col-lg-4 mb-30 text-lg-right">
                <a href="{{ route('about') }}" class="btn btn-projects-cta">
                    Contact Us <i class="fas fa-envelope"></i>
                </a>
            </div>
        </div>
    </div>
</section>

@endsection

@section('script')
<script>
// Counter Animation for Research Stats
document.addEventListener('DOMContentLoaded', function() {
    const counters = document.querySelectorAll('.stat-counter');
    const speed = 200;
    
    const animateCounter = (counter) => {
        const target = +counter.getAttribute('data-target');
        const count = +counter.innerText;
        const increment = target / speed;
        
        if (count < target) {
            counter.innerText = Math.ceil(count + increment);
            setTimeout(() => animateCounter(counter), 10);
        } else {
            counter.innerText = target + '+';
        }
    };
    
    const observerOptions = {
        threshold: 0.5,
        rootMargin: '0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const counter = entry.target;
                animateCounter(counter);
                observer.unobserve(counter);
            }
        });
    }, observerOptions);
    
    counters.forEach(counter => observer.observe(counter));
});
</script>
@endsection

@section('styles')
<style>
/* Remove old inline styles - all moved to custom.css */
</style>
@endsection
