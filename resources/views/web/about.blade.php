@extends('web.layouts.master')
@section('title', 'About Us')

@section('social_meta_tags')
    @if(isset($setting))
    <!-- Open Graph Meta Tags -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $setting->title }}"/>
    <meta property="og:title" content="About Us | {{ $setting->title }}"/>
    <meta property="og:description" content="Learn about PAX Higher Institute's mission, vision, history, and leadership. Discover our commitment to academic excellence and student success."/>
    <meta property="og:url" content="{{ route('about') }}"/>
    <meta property="og:image" content="{{ asset('/uploads/setting/'.$setting->logo_path) }}"/>

    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:site" content="{!! '@'.str_replace(' ', '', $setting->title) !!}" />
    <meta name="twitter:url" content="{{ route('about') }}" />
    <meta name="twitter:title" content="About Us | {{ $setting->title }}" />
    <meta name="twitter:description" content="Learn about PAX Higher Institute's mission, vision, history, and leadership. Discover our commitment to academic excellence and student success." />
    <meta name="twitter:image" content="{{ asset('/uploads/setting/'.$setting->logo_path) }}" />
    
    <!-- Page-Specific Meta Tags -->
    <meta name="description" content="Learn about PAX Higher Institute's mission, vision, history, and leadership. Discover our commitment to academic excellence and student success.">
    <meta name="keywords" content="about {{ $setting->title }}, mission, vision, history, leadership, academic excellence, higher education">
    <link rel="canonical" href="{{ route('about') }}">
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
        "name": "About",
        "item": "{{ route('about') }}"
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
                <h1>About PAX Higher Institute</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">About</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</section>

<!-- About Content -->
<section class="about-content pt-90 pb-60">
    <div class="container">
        @if(isset($about))
        <div class="row align-items-center mb-60">
            <div class="col-lg-6 mb-30" data-aos="fade-right">
                @if($about->attach)
                <div class="about-image-wrapper position-relative">
                    <img src="{{ asset('uploads/about-us/'.$about->attach) }}" alt="About Us" class="img-fluid rounded shadow">
                    <div class="about-experience-badge">
                        <div class="badge-content">
                            <h3 class="mb-0">20+</h3>
                            <p class="mb-0">Years of Excellence</p>
                        </div>
                    </div>
                </div>
                @endif
            </div>
            <div class="col-lg-6 mb-30" data-aos="fade-left">
                <div class="about-content-text">
                    <span class="section-subtitle text-primary text-uppercase font-weight-bold mb-2 d-inline-block">
                        <i class="fas fa-graduation-cap mr-2"></i>{{ $about->label ?? 'About Our Institution' }}
                    </span>
                    <div class="section-title">
                        <h2>{{ $about->title }}</h2>
                    </div>
                  <div style="font-size: 16px; line-height: 1.8; color: #666666; margin-bottom: 25px;">
                                {!! strip_tags($about->description, '<a><b><i><u><strong><p>') !!}
                            </div>
                    <div class="about-features mt-4">
                        <div class="row">
                            <div class="col-6 mb-3">
                                <div class="feature-item d-flex align-items-center">
                                    <i class="fas fa-check-circle text-success mr-2"></i>
                                    <span>Accredited Programs</span>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="feature-item d-flex align-items-center">
                                    <i class="fas fa-check-circle text-success mr-2"></i>
                                    <span>Expert Faculty</span>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="feature-item d-flex align-items-center">
                                    <i class="fas fa-check-circle text-success mr-2"></i>
                                    <span>Modern Facilities</span>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="feature-item d-flex align-items-center">
                                    <i class="fas fa-check-circle text-success mr-2"></i>
                                    <span>Career Support</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Mission & Vision -->
        @if(isset($about) && ($about->mission_title || $about->vision_title))
        <div class="row mb-60">
            @if($about->mission_title)
            <div class="col-lg-6 mb-30" data-aos="fade-up" data-aos-delay="100">
                <div class="card h-100">
                    <div class="card-body p-40">
                        <div class="d-flex align-items-center mb-20">
                            <div class="icon-wrapper">
                                <i class="fas fa-bullseye fa-3x text-primary mr-20"></i>
                            </div>
                            <h3 class="mb-0">{{ $about->mission_title }}</h3>
                        </div>
                        <div class="text-muted mb-0">
                            {!! strip_tags($about->mission_desc, '<a><b><i><u><strong><p><br>') !!}
                        </div>
                    </div>
                </div>
            </div>
            @endif
            @if($about->vision_title)
            <div class="col-lg-6 mb-30" data-aos="fade-up" data-aos-delay="200">
                <div class="card h-100">
                    <div class="card-body p-40">
                        <div class="d-flex align-items-center mb-20">
                            <div class="icon-wrapper">
                                <i class="fas fa-eye fa-3x text-primary mr-20"></i>
                            </div>
                            <h3 class="mb-0">{{ $about->vision_title }}</h3>
                        </div>
                        <div class="text-muted mb-0">
                            {!! strip_tags($about->vision_desc, '<a><b><i><u><strong><p><br>') !!}
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
        @endif

        <!-- Core Values -->
        <div class="row mb-60" data-aos="fade-up">
            <div class="col-12">
                <div class="section-title center mb-50">
                    <span class="section-subtitle text-primary text-uppercase font-weight-bold mb-2 d-inline-block">
                        What We Stand For
                    </span>
                    <h2>Our Core Values</h2>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-30" data-aos="zoom-in" data-aos-delay="100">
                <div class="value-card text-center p-4 h-100">
                    <div class="value-icon mb-3">
                        <i class="fas fa-star fa-3x text-primary"></i>
                    </div>
                    <h5 class="font-weight-bold mb-3">Excellence</h5>
                    <p class="text-muted small mb-0">Striving for the highest standards in all we do</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-30" data-aos="zoom-in" data-aos-delay="200">
                <div class="value-card text-center p-4 h-100">
                    <div class="value-icon mb-3">
                        <i class="fas fa-handshake fa-3x text-primary"></i>
                    </div>
                    <h5 class="font-weight-bold mb-3">Integrity</h5>
                    <p class="text-muted small mb-0">Upholding honesty and ethical principles</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-30" data-aos="zoom-in" data-aos-delay="300">
                <div class="value-card text-center p-4 h-100">
                    <div class="value-icon mb-3">
                        <i class="fas fa-lightbulb fa-3x text-primary"></i>
                    </div>
                    <h5 class="font-weight-bold mb-3">Innovation</h5>
                    <p class="text-muted small mb-0">Embracing creativity and forward thinking</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-30" data-aos="zoom-in" data-aos-delay="400">
                <div class="value-card text-center p-4 h-100">
                    <div class="value-icon mb-3">
                        <i class="fas fa-heart fa-3x text-primary"></i>
                    </div>
                    <h5 class="font-weight-bold mb-3">Community</h5>
                    <p class="text-muted small mb-0">Fostering collaboration and social responsibility</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- History Timeline -->
@if($timeline->count() > 0)
<section class="history-section pb-90" style="background: #f8f9fa;">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="section-title center mb-60">
                    <h2>Our Journey</h2>
                    <p class="mt-20">Milestones in our history of academic excellence</p>
                </div>
            </div>
        </div>
        <div class="timeline">
            @foreach($timeline as $index => $item)
            <div class="timeline-item" data-aos="fade-up" data-aos-delay="{{ $index * 100 }}">
                <div class="row {{ $index % 2 == 0 ? '' : 'flex-row-reverse' }}">
                    <div class="col-lg-5 offset-lg-1 {{ $index % 2 == 0 ? 'text-right' : 'text-left' }}">
                        <div class="timeline-year mb-20">{{ $item->year }}</div>
                        <h4 class="mb-15">{{ $item->title }}</h4>
                        <p class="text-muted">{{ $item->description }}</p>
                    </div>
                    <div class="col-lg-1 d-none d-lg-block">
                        <div class="timeline-dot"></div>
                    </div>
                    <div class="col-lg-5"></div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<!-- Leadership Team -->
@if($leadership->count() > 0)
<section class="leadership-section pt-90 pb-90">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="section-title center mb-60">
                    <h2>Our Leadership</h2>
                    <p class="mt-20">Meet the dedicated team leading PAX Higher Institute</p>
                </div>
            </div>
        </div>
        <div class="row">
            @foreach($leadership as $member)
            <div class="col-lg-3 col-md-6 mb-40">
                <div class="team-member">
                    @if($member->photo && upload_exists('leadership-team/'.$member->photo))
                    <img src="{{ upload_asset('leadership-team/'.$member->photo) }}" alt="{{ $member->name }}" class="img-fluid">
                    @else
                    <img src="{{ asset('web/img/default-avatar.png') }}" alt="{{ $member->name }}" class="img-fluid">
                    @endif
                    <h4>{{ $member->name }}</h4>
                    <p class="designation">{{ $member->designation }}</p>
                    @if($member->bio)
                    <p class="text-muted small">{{ Str::limit($member->bio, 100) }}</p>
                    @endif
                    @if($member->email || $member->phone)
                    <div class="team-contact mt-20">
                        @if($member->email)
                        <a href="mailto:{{ $member->email }}" class="text-primary mr-10"><i class="fas fa-envelope"></i></a>
                        @endif
                        @if($member->phone)
                        <a href="tel:{{ $member->phone }}" class="text-primary"><i class="fas fa-phone"></i></a>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<!-- Accreditations -->
@if($accreditations->count() > 0)
<section class="accreditations-section pb-90" style="background: #f8f9fa;">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="section-title center mb-60">
                    <h2>Accreditations & Partnerships</h2>
                    <p class="mt-20">Recognized and accredited by leading educational bodies</p>
                </div>
            </div>
        </div>
        <div class="row align-items-center justify-content-center">
            @foreach($accreditations as $accreditation)
            <div class="col-lg-3 col-md-4 col-6 mb-40 text-center">
                <div class="accreditation-item p-30 bg-white rounded shadow-sm h-100">
                    @if($accreditation->logo && upload_exists('accreditation/'.$accreditation->logo))
                    <img src="{{ upload_asset('accreditation/'.$accreditation->logo) }}" 
                         alt="{{ $accreditation->title }}" 
                         style="max-height: 100px; max-width: 100%; object-fit: contain;" 
                         class="mb-20">
                    @endif
                    <h6 class="mb-10">{{ $accreditation->title }}</h6>
                    @if($accreditation->description)
                    <p class="small text-muted">{{ Str::limit($accreditation->description, 80) }}</p>
                    @endif
                    @if($accreditation->url)
                    <a href="{{ $accreditation->url }}" target="_blank" class="btn btn-sm btn-outline-primary mt-10">
                        Learn More <i class="fas fa-external-link-alt"></i>
                    </a>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<!-- Statistics Section -->
<section class="stats-section">
    <div class="container">
        <div class="row">
            <div class="col-lg-3 col-md-6 mb-30" data-aos="fade-up" data-aos-delay="100">
                <div class="stat-item">
                    <i class="fas fa-users"></i>
                    <h3 class="counter">5000</h3>
                    <p>Students Enrolled</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-30" data-aos="fade-up" data-aos-delay="200">
                <div class="stat-item">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <h3 class="counter">150</h3>
                    <p>Expert Faculty</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-30" data-aos="fade-up" data-aos-delay="300">
                <div class="stat-item">
                    <i class="fas fa-graduation-cap"></i>
                    <h3 class="counter">10000</h3>
                    <p>Graduates</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-30" data-aos="fade-up" data-aos-delay="400">
                <div class="stat-item">
                    <i class="fas fa-award"></i>
                    <h3 class="counter">50</h3>
                    <p>Programs Offered</p>
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
                <h2 class="text-white mb-10">Ready to Join PAX Higher Institute?</h2>
                <p class="text-white opacity-90 mb-0">Start your journey towards academic excellence and personal growth with us.</p>
            </div>
            <div class="col-lg-4 mb-30 text-lg-right">
                <a href="{{ route('admissions') }}" class="btn btn-secondary btn-lg">Apply Now <i class="fas fa-arrow-right ml-10"></i></a>
            </div>
        </div>
    </div>
</section>

@endsection

@section('script')
<script>
    // Counter Animation
    document.addEventListener('DOMContentLoaded', function() {
        const counters = document.querySelectorAll('.counter');
        const speed = 200; // The lower the slower

        const countUp = (counter) => {
            const target = +counter.innerText;
            const count = +counter.getAttribute('data-count') || 0;
            const increment = target / speed;

            if (count < target) {
                counter.setAttribute('data-count', Math.ceil(count + increment));
                counter.innerText = Math.ceil(count + increment);
                setTimeout(() => countUp(counter), 10);
            } else {
                counter.innerText = target;
            }
        };

        // Intersection Observer for triggering animation when in viewport
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !entry.target.classList.contains('counted')) {
                    entry.target.classList.add('counted');
                    countUp(entry.target);
                }
            });
        }, { threshold: 0.5 });

        counters.forEach(counter => {
            observer.observe(counter);
        });
    });
</script>
@endsection
