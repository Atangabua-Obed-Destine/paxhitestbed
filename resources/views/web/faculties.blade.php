@extends('web.layouts.master')
@section('title', 'Faculties')

@section('social_meta_tags')
    @if(isset($setting))
    <!-- Open Graph Meta Tags -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $setting->title }}"/>
    <meta property="og:title" content="Our Faculties | {{ $setting->title }}"/>
    <meta property="og:description" content="Discover the academic faculties at PAX Higher Institute. Meet our expert faculty members and explore our departments of excellence."/>
    <meta property="og:url" content="{{ route('faculties') }}"/>
    <meta property="og:image" content="{{ asset('/uploads/setting/'.$setting->logo_path) }}"/>

    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:site" content="{!! '@'.str_replace(' ', '', $setting->title) !!}" />
    <meta name="twitter:url" content="{{ route('faculties') }}" />
    <meta name="twitter:title" content="Our Faculties | {{ $setting->title }}" />
    <meta name="twitter:description" content="Discover the academic faculties at PAX Higher Institute. Meet our expert faculty members and explore our departments of excellence." />
    <meta name="twitter:image" content="{{ asset('/uploads/setting/'.$setting->logo_path) }}" />
    
    <!-- Page-Specific Meta Tags -->
    <meta name="description" content="Discover the academic faculties at PAX Higher Institute. Meet our expert faculty members and explore our departments of excellence.">
    <meta name="keywords" content="faculties, departments, {{ $setting->title }}, academic staff, faculty members, professors, departments of excellence">
    <link rel="canonical" href="{{ route('faculties') }}">
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
        "name": "Faculties",
        "item": "{{ route('faculties') }}"
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
                <h1>Our Faculties</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Faculties</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</section>

<!-- Faculties Section -->
<section class="faculties-section pt-90 pb-90">
    <div class="container">
        <!-- Section Header -->
        <div class="row mb-60">
            <div class="col-12">
                <div class="section-title center" data-aos="fade-up">
                    <h5 style="color: #0066CC; font-weight: 600; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 15px;">
                        <i class="fas fa-graduation-cap"></i> Our Faculties
                    </h5>
                    <h2 style="font-size: 38px; font-weight: 700; color: #003366; margin-bottom: 15px;">
                        Academic Excellence Across Disciplines
                    </h2>
                    <div style="width: 80px; height: 4px; background: linear-gradient(90deg, #0066CC, #FF6B35); border-radius: 2px; margin: 15px auto 20px;"></div>
                    <p style="color: #666666; font-size: 16px; max-width: 700px; margin: 0 auto;">
                        Explore our diverse faculties offering comprehensive programs led by experienced faculty members
                    </p>
                </div>
            </div>
        </div>

        @if($faculties->count() > 0)
        <div class="row">
            @foreach($faculties as $index => $faculty)
            <div class="col-lg-6 mb-40" data-aos="fade-up" data-aos-delay="{{ ($index % 2) * 100 + 100 }}">
                <div class="card faculty-card h-100">
                    <div class="card-body">
                        <div class="faculty-header">
                            <div class="faculty-icon-wrapper">
                                <div class="faculty-icon">
                                    <i class="fas fa-university"></i>
                                </div>
                            </div>
                            <div class="faculty-info">
                                <h3 class="faculty-title">{{ $faculty->title }}</h3>
                                <div class="programs-badge">
                                    <i class="fas fa-book"></i>
                                    <span>{{ $faculty->programs_count }} {{ $faculty->programs_count == 1 ? 'Program' : 'Programs' }}</span>
                                </div>
                            </div>
                        </div>

                        @if($faculty->excerpt)
                        <p class="faculty-description">{{ $faculty->excerpt }}</p>
                        @endif

                        <div class="faculty-meta">
                            @if($faculty->dean)
                            <div class="meta-item">
                                <i class="fas fa-user-tie"></i>
                                <div class="meta-content">
                                    <strong>Dean:</strong>
                                    <span>{{ $faculty->dean }}</span>
                                </div>
                            </div>
                            @endif
                            @if($faculty->email)
                            <div class="meta-item">
                                <i class="fas fa-envelope"></i>
                                <div class="meta-content">
                                    <strong>Email:</strong>
                                    <a href="mailto:{{ $faculty->email }}">{{ $faculty->email }}</a>
                                </div>
                            </div>
                            @endif
                            @if($faculty->phone)
                            <div class="meta-item">
                                <i class="fas fa-phone"></i>
                                <div class="meta-content">
                                    <strong>Phone:</strong>
                                    <a href="tel:{{ $faculty->phone }}">{{ $faculty->phone }}</a>
                                </div>
                            </div>
                            @endif
                        </div>

                        <a href="{{ route('programs') }}?faculty={{ $faculty->id }}" class="btn btn-outline-primary btn-view-programs">
                            View Programs <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="row">
            <div class="col-12">
                <div class="alert alert-info text-center" data-aos="fade-up" style="padding: 60px 20px; border-radius: 15px; background: #e8f4fd; border: 2px dashed #0066CC;">
                    <i class="fas fa-info-circle" style="font-size: 48px; color: #0066CC; margin-bottom: 20px;"></i>
                    <h4 style="color: #003366; font-weight: 600;">No faculties available</h4>
                    <p style="color: #666666;">Faculty information will be updated soon. Please check back later.</p>
                </div>
            </div>
        </div>
        @endif
    </div>
</section>

<!-- Why Choose Section -->
<section class="why-choose-faculty-section">
    <div class="decorative-circle circle-1"></div>
    <div class="decorative-circle circle-2"></div>
    <div class="decorative-circle circle-3"></div>
    
    <div class="container">
        <div class="row mb-60">
            <div class="col-12">
                <div class="section-title center" data-aos="fade-up">
                    <h5 style="color: #FF6B35; font-weight: 600; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 15px;">
                        <i class="fas fa-star"></i> Why Choose Us
                    </h5>
                    <h2 style="font-size: 38px; font-weight: 700; color: #ffffff !important; margin-bottom: 15px;">
                        Why Choose PAX Higher Institute?
                    </h2>
                    <div style="width: 80px; height: 4px; background: linear-gradient(90deg, #FF6B35, #0066CC); border-radius: 2px; margin: 15px auto 20px;"></div>
                    <p style="color: #ffffff !important; font-size: 16px; max-width: 700px; margin: 0 auto; opacity: 0.95;">
                        What sets our faculties apart from the rest
                    </p>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-3 col-md-6 mb-40" data-aos="fade-up" data-aos-delay="100">
                <div class="faculty-feature-box">
                    <div class="feature-icon">
                        <i class="fas fa-award"></i>
                    </div>
                    <h5 class="feature-title">Accredited Programs</h5>
                    <p class="feature-description">All programs are nationally and internationally recognized with industry-standard certifications</p>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-40" data-aos="fade-up" data-aos-delay="200">
                <div class="faculty-feature-box">
                    <div class="feature-icon">
                        <i class="fas fa-microscope"></i>
                    </div>
                    <h5 class="feature-title">Research Focus</h5>
                    <p class="feature-description">Cutting-edge research facilities and opportunities for groundbreaking discoveries</p>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-40" data-aos="fade-up" data-aos-delay="300">
                <div class="faculty-feature-box">
                    <div class="feature-icon">
                        <i class="fas fa-hands-helping"></i>
                    </div>
                    <h5 class="feature-title">Industry Links</h5>
                    <p class="feature-description">Strong partnerships with leading industry players for internships and employment</p>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-40" data-aos="fade-up" data-aos-delay="400">
                <div class="faculty-feature-box">
                    <div class="feature-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <h5 class="feature-title">Career Success</h5>
                    <p class="feature-description">High graduate employment and career advancement rates in competitive job markets</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section class="faculty-cta-section">
    <div class="decorative-shape shape-1"></div>
    <div class="decorative-shape shape-2"></div>
    
    <div class="container">
        <div class="row align-items-center" data-aos="fade-up">
            <div class="col-lg-8 mb-30">
                <h2 style="color: #ffffff !important; font-size: 34px; font-weight: 700; margin-bottom: 15px;">
                    Find Your Path to Success
                </h2>
                <p style="color: #ffffff !important; font-size: 16px; margin-bottom: 0; opacity: 0.95;">
                    Explore our programs and start your journey towards academic excellence and professional success.
                </p>
            </div>
            <div class="col-lg-4 mb-30 text-lg-right">
                <a href="{{ route('programs') }}" class="btn btn-cta-secondary">
                    View All Programs <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</section>

@endsection

@section('styles')
<style>
/* Remove old inline styles - all moved to custom.css */
</style>
@endsection
