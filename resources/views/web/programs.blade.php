@extends('web.layouts.master')
@section('title', 'Programs')

@section('social_meta_tags')
    @if(isset($setting))
    <!-- Open Graph Meta Tags -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $setting->title }}"/>
    <meta property="og:title" content="Academic Programs | {{ $setting->title }}"/>
    <meta property="og:description" content="Explore our diverse range of undergraduate and postgraduate programs at PAX Higher Institute. Find the perfect program to launch your career."/>
    <meta property="og:url" content="{{ route('programs') }}"/>
    <meta property="og:image" content="{{ asset('/uploads/setting/'.$setting->logo_path) }}"/>

    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:site" content="{!! '@'.str_replace(' ', '', $setting->title) !!}" />
    <meta name="twitter:url" content="{{ route('programs') }}" />
    <meta name="twitter:title" content="Academic Programs | {{ $setting->title }}" />
    <meta name="twitter:description" content="Explore our diverse range of undergraduate and postgraduate programs at PAX Higher Institute. Find the perfect program to launch your career." />
    <meta name="twitter:image" content="{{ asset('/uploads/setting/'.$setting->logo_path) }}" />
    
    <!-- Page-Specific Meta Tags -->
    <meta name="description" content="Explore our diverse range of undergraduate and postgraduate programs at PAX Higher Institute. Find the perfect program to launch your career.">
    <meta name="keywords" content="programs, courses, {{ $setting->title }}, undergraduate programs, postgraduate programs, degrees, academic programs, study programs">
    <link rel="canonical" href="{{ route('programs') }}">
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
        "name": "Programs",
        "item": "{{ route('programs') }}"
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
                <h1>Our Programs</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Programs</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</section>

<!-- Programs Section -->
<section class="programs-section pt-90 pb-90">
    <div class="container">
        <!-- Section Header -->
        <div class="row mb-50">
            <div class="col-12">
                <div class="section-title center" data-aos="fade-up">
                    <h5 style="color: #0066CC; font-weight: 600; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 15px;">
                        <i class="fas fa-book-reader"></i> Academic Programs
                    </h5>
                    <h2 style="font-size: 38px; font-weight: 700; color: #003366; margin-bottom: 15px;">
                        Explore Our Degree Programs
                    </h2>
                    <div style="width: 80px; height: 4px; background: linear-gradient(90deg, #0066CC, #FF6B35); border-radius: 2px; margin: 15px auto 20px;"></div>
                    <p style="color: #666666; font-size: 16px; max-width: 700px; margin: 0 auto;">
                        Choose from our diverse range of accredited programs designed to prepare you for success in your chosen field.
                    </p>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
        <div class="row mb-50">
            <div class="col-12">
                <form method="GET" action="{{ route('programs') }}" class="filter-form" data-aos="fade-up" data-aos-delay="100">
                    <div class="row align-items-center">
                        <div class="col-lg-6 col-md-8 mb-20">
                            <label style="font-weight: 600; color: #003366; margin-bottom: 10px; display: block;">
                                <i class="fas fa-filter"></i> Filter by Faculty
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
                        <div class="col-lg-6 col-md-4 mb-20">
                            @if(request('faculty'))
                            <label style="font-weight: 600; color: #003366; margin-bottom: 10px; display: block; opacity: 0;">
                                &nbsp;
                            </label>
                            <a href="{{ route('programs') }}" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-times"></i> Clear Filters
                            </a>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Programs Grid -->
        @if($programs->count() > 0)
        <div class="row">
            @foreach($programs as $index => $program)
            <div class="col-lg-4 col-md-6 mb-40" data-aos="fade-up" data-aos-delay="{{ ($index % 3) * 100 + 100 }}">
                <div class="card program-card h-100">
                    <div class="card-body">
                        <div class="program-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <h4 class="program-title">{{ $program->title }}</h4>
                        <div class="program-meta">
                            <span class="badge badge-primary">
                                <i class="fas fa-university"></i> {{ $program->faculty->title ?? 'N/A' }}
                            </span>
                            @if($program->shortcode)
                            <span class="badge badge-secondary">
                                <i class="fas fa-tag"></i> {{ $program->shortcode }}
                            </span>
                            @endif
                        </div>
                        @if($program->excerpt)
                        <p class="text-muted small">{{ Str::limit($program->excerpt, 130) }}</p>
                        @endif
                        <div class="program-details">
                            @if($program->duration)
                            <p>
                                <i class="fas fa-clock"></i> 
                                <strong>Duration:</strong> {{ $program->duration }}
                            </p>
                            @endif
                            @if($program->credit)
                            <p>
                                <i class="fas fa-certificate"></i> 
                                <strong>Credits:</strong> {{ $program->credit }}
                            </p>
                            @endif
                        </div>
                        @if($program->slug)
                        <a href="{{ route('program.single', $program->slug) }}" class="btn btn-outline-primary btn-block">
                            View Details <i class="fas fa-arrow-right"></i>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="row mt-50">
            <div class="col-12">
                <div data-aos="fade-up">
                    {{ $programs->links() }}
                </div>
            </div>
        </div>
        @else
        <div class="row">
            <div class="col-12">
                <div class="alert alert-info text-center" data-aos="fade-up">
                    <i class="fas fa-info-circle fa-3x"></i>
                    <h4 style="margin-top: 20px;">No programs found</h4>
                    <p>Try adjusting your filters or check back later for new programs.</p>
                    @if(request('faculty'))
                    <a href="{{ route('programs') }}" class="btn btn-primary mt-3">
                        <i class="fas fa-redo"></i> View All Programs
                    </a>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>
</section>

<!-- Why Choose Us -->
<section class="why-choose-section py-90 bg-dark-blue">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="section-title center mb-60">
                    <h2 class="text-white">Why Choose Our Programs?</h2>
                    <p class="text-white opacity-90 mt-20">Discover what makes PAX Higher Institute programs exceptional</p>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-3 col-md-6 mb-40">
                <div class="feature-box text-center text-white">
                    <i class="fas fa-chalkboard-teacher fa-3x text-secondary mb-20"></i>
                    <h5 class="mb-15">Expert Faculty</h5>
                    <p class="opacity-90 small">Learn from experienced professionals and academic leaders</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-40">
                <div class="feature-box text-center text-white">
                    <i class="fas fa-flask fa-3x text-secondary mb-20"></i>
                    <h5 class="mb-15">Modern Facilities</h5>
                    <p class="opacity-90 small">State-of-the-art laboratories and learning resources</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-40">
                <div class="feature-box text-center text-white">
                    <i class="fas fa-globe fa-3x text-secondary mb-20"></i>
                    <h5 class="mb-15">Global Recognition</h5>
                    <p class="opacity-90 small">Internationally accredited programs and qualifications</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-40">
                <div class="feature-box text-center text-white">
                    <i class="fas fa-briefcase fa-3x text-secondary mb-20"></i>
                    <h5 class="mb-15">Career Support</h5>
                    <p class="opacity-90 small">Comprehensive career guidance and job placement assistance</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section class="cta-section py-90 bg-primary">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8 mb-30">
                <h2 class="text-white mb-10">Ready to Start Your Academic Journey?</h2>
                <p class="text-white opacity-90 mb-0">Explore our admissions process and take the first step towards your future.</p>
            </div>
            <div class="col-lg-4 mb-30 text-lg-right">
                <a href="{{ route('admissions') }}" class="btn btn-secondary btn-lg">Apply Now <i class="fas fa-arrow-right ml-10"></i></a>
            </div>
        </div>
    </div>
</section>

@endsection

@section('styles')
<style>
.program-card {
    border: none;
    box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
}

.program-card:hover {
    box-shadow: 0 5px 25px rgba(0, 102, 204, 0.2);
    transform: translateY(-5px);
}

.program-card .card-body {
    display: flex;
    flex-direction: column;
}

.program-card .program-details {
    margin-top: auto;
}

.filter-form select {
    height: 45px;
    border: 2px solid #e0e0e0;
    border-radius: 5px;
}

.filter-form select:focus {
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
}

.feature-box {
    padding: 30px 20px;
}

.opacity-90 {
    opacity: 0.9;
}
</style>
@endsection
