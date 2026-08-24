@extends('web.layouts.master')
@section('title', 'Admissions')

@section('social_meta_tags')
    @if(isset($setting))
    <!-- Open Graph Meta Tags -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $setting->title }}"/>
    <meta property="og:title" content="Admissions | {{ $setting->title }}"/>
    <meta property="og:description" content="Apply to PAX Higher Institute. Learn about our admission process, requirements, important dates, and how to join our academic community."/>
    <meta property="og:url" content="{{ route('admissions') }}"/>
    <meta property="og:image" content="{{ asset('/uploads/setting/'.$setting->logo_path) }}"/>

    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:site" content="{!! '@'.str_replace(' ', '', $setting->title) !!}" />
    <meta name="twitter:url" content="{{ route('admissions') }}" />
    <meta name="twitter:title" content="Admissions | {{ $setting->title }}" />
    <meta name="twitter:description" content="Apply to PAX Higher Institute. Learn about our admission process, requirements, important dates, and how to join our academic community." />
    <meta name="twitter:image" content="{{ asset('/uploads/setting/'.$setting->logo_path) }}" />
    
    <!-- Page-Specific Meta Tags -->
    <meta name="description" content="Apply to PAX Higher Institute. Learn about our admission process, requirements, important dates, and how to join our academic community.">
    <meta name="keywords" content="admissions, apply, {{ $setting->title }}, admission requirements, application process, undergraduate, postgraduate, entry requirements">
    <link rel="canonical" href="{{ route('admissions') }}">
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
        "name": "Admissions",
        "item": "{{ route('admissions') }}"
    }]
}
</script>

@if(isset($faqs) && $faqs->count() > 0)
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "FAQPage",
    "mainEntity": [
        @foreach($faqs as $key => $faq)
        {
            "@type": "Question",
            "name": "{{ $faq->title }}",
            "acceptedAnswer": {
                "@type": "Answer",
                "text": "{!! strip_tags($faq->description) !!}"
            }
        }@if(!$loop->last),@endif
        @endforeach
    ]
}
</script>
@endif
@endsection

@section('content')

<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h1>Admissions</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Admissions</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</section>

<!-- Admissions Process -->
<section class="admissions-process pt-90 pb-60">
    <div class="container">
        <div class="row">
            <div class="col-12 mb-60">
                <div class="section-title center">
                    <h2>Admission Process</h2>
                    <p class="mt-20 text-muted">Your journey to PAX Higher Institute in simple steps</p>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-3 col-md-6 mb-40">
                <div class="process-step text-center">
                    <div class="step-number bg-primary text-white mb-20">1</div>
                    <h5 class="mb-15">Apply Online</h5>
                    <p class="text-muted small">Complete the online application form with required documents</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-40">
                <div class="process-step text-center">
                    <div class="step-number bg-primary text-white mb-20">2</div>
                    <h5 class="mb-15">Document Review</h5>
                    <p class="text-muted small">Our admissions team reviews your application and documents</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-40">
                <div class="process-step text-center">
                    <div class="step-number bg-primary text-white mb-20">3</div>
                    <h5 class="mb-15">Interview</h5>
                    <p class="text-muted small">Attend an interview to assess your suitability for the program</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-40">
                <div class="process-step text-center">
                    <div class="step-number bg-primary text-white mb-20">4</div>
                    <h5 class="mb-15">Get Admitted</h5>
                    <p class="text-muted small">Receive your admission letter and begin your journey with us</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Important Dates -->
@if($admission_dates->count() > 0)
<section class="important-dates pb-90" style="background: #f8f9fa;">
    <div class="container">
        <div class="row">
            <div class="col-12 mb-60">
                <div class="section-title center">
                    <h2>Important Dates</h2>
                    <p class="mt-20 text-muted">Mark your calendar with these key admission dates</p>
                </div>
            </div>
        </div>
        <div class="row">
            @foreach($admission_dates as $date)
            <div class="col-lg-4 col-md-6 mb-30">
                <div class="card date-card h-100">
                    <div class="card-body p-30 text-center">
                        <div class="date-icon mb-20">
                            <i class="fas fa-calendar-alt fa-3x text-primary"></i>
                        </div>
                        <h5 class="mb-15">{{ $date->event_title }}</h5>
                        <p class="text-primary font-weight-bold mb-15">
                            {{ \Carbon\Carbon::parse($date->event_date)->format('F j, Y') }}
                        </p>
                        @if($date->description)
                        <p class="text-muted small mb-0">{{ $date->description }}</p>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<!-- Requirements -->
<section hidden class="requirements-section py-90">
    <div class="container">
        <div class="row">
            <div class="col-12 mb-60">
                <div class="section-title center">
                    <h2>Entry Requirements</h2>
                    <p class="mt-20 text-muted">General requirements for admission to PAX Higher Institute</p>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-6 mb-40">
                <div class="card requirements-card h-100">
                    <div class="card-body p-40">
                        <h4 class="mb-30">Undergraduate Programs</h4>
                        <ul class="requirements-list">
                            <li><i class="fas fa-check text-primary"></i> High School Certificate (O-Level or equivalent)</li>
                            <li><i class="fas fa-check text-primary"></i> Minimum of 5 credits including English and Mathematics</li>
                            <li><i class="fas fa-check text-primary"></i> JAMB UTME score (varies by program)</li>
                            <li><i class="fas fa-check text-primary"></i> Valid identification documents</li>
                            <li><i class="fas fa-check text-primary"></i> Passport photographs</li>
                            <li><i class="fas fa-check text-primary"></i> Medical fitness certificate</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-40">
                <div class="card requirements-card h-100">
                    <div class="card-body p-40">
                        <h4 class="mb-30">Postgraduate Programs</h4>
                        <ul class="requirements-list">
                            <li><i class="fas fa-check text-primary"></i> Bachelor's degree from a recognized institution</li>
                            <li><i class="fas fa-check text-primary"></i> Minimum of Second Class Lower or equivalent</li>
                            <li><i class="fas fa-check text-primary"></i> Academic transcripts</li>
                            <li><i class="fas fa-check text-primary"></i> Two letters of recommendation</li>
                            <li><i class="fas fa-check text-primary"></i> Statement of purpose</li>
                            <li><i class="fas fa-check text-primary"></i> CV/Resume</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
@if($faqs->count() > 0)
<section class="faq-section pb-90" style="background: #f8f9fa;">
    <div class="container">
        <div class="row">
            <div class="col-12 mb-60">
                <div class="section-title center">
                    <h2>Frequently Asked Questions</h2>
                    <p class="mt-20 text-muted">Find answers to common questions about admissions</p>
                </div>
            </div>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="accordion" id="faqAccordion">
                    @foreach($faqs as $key => $faq)
                    <div class="accordion-item mb-20">
                        <h2 class="accordion-header" id="faq{{ $key }}">
                            <button class="accordion-button @if($key != 0) collapsed @endif" type="button" data-bs-toggle="collapse" data-bs-target="#collapse{{ $key }}" aria-expanded="{{ $key == 0 ? 'true' : 'false' }}" aria-controls="collapse{{ $key }}">
                                {{ $faq->title }}
                            </button>
                        </h2>
                        <div id="collapse{{ $key }}" class="accordion-collapse collapse @if($key == 0) show @endif" aria-labelledby="faq{{ $key }}" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                {!! $faq->description !!}
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
@endif

<!-- Downloadable Resources Section -->
@if(isset($download_resources) && $download_resources->count() > 0)
<section class="py-5" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%); position: relative; overflow: hidden;">
    <!-- Decorative Background -->
    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-image: url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"60\" height=\"60\"><circle cx=\"30\" cy=\"30\" r=\"1\" fill=\"rgba(255,255,255,0.03)\"/></svg>'); pointer-events: none;"></div>
    
    <div class="container position-relative" style="z-index: 2;">
        <!-- Section Header -->
        <div class="text-center mb-5" data-aos="fade-up">
            <span style="display: inline-block; background: linear-gradient(135deg, #0066cc, #7c3aed); padding: 8px 24px; border-radius: 30px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: #fff; margin-bottom: 20px;">
                <i class="fas fa-download me-2"></i> Resources
            </span>
            <h2 style="font-size: 42px; font-weight: 800; color: #fff; margin-bottom: 15px;">Important Downloads</h2>
            <p style="font-size: 18px; color: rgba(255,255,255,0.6); max-width: 600px; margin: 0 auto;">Download essential documents for prospective and current students</p>
        </div>

        <!-- Download Cards Grid -->
        <div class="row justify-content-center g-4">
            @foreach($download_resources as $resource)
            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="{{ $loop->index * 100 }}">
                <div style="background: rgba(255,255,255,0.05); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); border-radius: 24px; padding: 30px; text-align: center; transition: all 0.4s ease; height: 100%;" 
                     onmouseover="this.style.transform='translateY(-10px)'; this.style.background='rgba(255,255,255,0.1)'; this.style.boxShadow='0 25px 50px rgba(0,102,204,0.3)';" 
                     onmouseout="this.style.transform='translateY(0)'; this.style.background='rgba(255,255,255,0.05)'; this.style.boxShadow='none';">
                    
                    <!-- Icon Box -->
                    <div style="width: 100px; height: 100px; margin: 0 auto 25px; background: linear-gradient(135deg, #0066cc 0%, #7c3aed 100%); border-radius: 24px; display: flex; align-items: center; justify-content: center; position: relative; box-shadow: 0 15px 40px rgba(0,102,204,0.3);">
                        <i class="{{ $resource->icon ?? $resource->file_icon }}" style="font-size: 40px; color: #fff;"></i>
                        <span style="position: absolute; top: -8px; right: -8px; background: linear-gradient(135deg, #f59e0b, #ef4444); color: #fff; font-size: 10px; font-weight: 800; padding: 4px 10px; border-radius: 12px; letter-spacing: 1px;">
                            {{ strtoupper(pathinfo($resource->file_path, PATHINFO_EXTENSION)) }}
                        </span>
                    </div>

                    <!-- Category Badge -->
                    <span style="display: inline-block; background: rgba(0,102,204,0.2); color: #60a5fa; padding: 5px 15px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;">
                        {{ $resource->category_label }}
                    </span>

                    <!-- Title -->
                    <h5 style="color: #fff; font-weight: 700; font-size: 18px; margin-bottom: 10px; line-height: 1.4;">{{ $resource->title }}</h5>

                    @if($resource->description)
                    <p style="color: rgba(255,255,255,0.5); font-size: 14px; margin-bottom: 15px; line-height: 1.6;">{{ Str::limit($resource->description, 60) }}</p>
                    @endif

                    <!-- File Info -->
                    <div style="display: flex; justify-content: center; gap: 20px; margin-bottom: 20px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1);">
                        <span style="color: rgba(255,255,255,0.5); font-size: 13px;">
                            <i class="fas fa-weight-hanging" style="color: #60a5fa; margin-right: 5px;"></i> {{ $resource->formatted_file_size }}
                        </span>
                        @if($resource->download_count > 0)
                        <span style="color: rgba(255,255,255,0.5); font-size: 13px;">
                            <i class="fas fa-download" style="color: #60a5fa; margin-right: 5px;"></i> {{ number_format($resource->download_count) }}
                        </span>
                        @endif
                    </div>

                    <!-- Download Button -->
                    <a href="{{ route('resource.download', $resource->id) }}" 
                       style="display: inline-flex; align-items: center; gap: 10px; background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%); color: #fff; padding: 12px 28px; border-radius: 14px; font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; text-decoration: none; transition: all 0.3s ease;"
                       onmouseover="this.style.background='linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%)'; this.style.transform='scale(1.05)';"
                       onmouseout="this.style.background='linear-gradient(135deg, #0066cc 0%, #0052a3 100%)'; this.style.transform='scale(1)';">
                        <span>Download</span>
                        <i class="fas fa-arrow-down"></i>
                    </a>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Browse All Button -->
        <div class="text-center mt-5" data-aos="fade-up" data-aos-delay="300">
            <a href="{{ route('resources') }}" 
               style="display: inline-flex; align-items: center; gap: 12px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #fff; padding: 16px 40px; border-radius: 16px; font-weight: 700; font-size: 15px; text-transform: uppercase; letter-spacing: 1px; text-decoration: none; box-shadow: 0 15px 40px rgba(245,158,11,0.3); transition: all 0.3s ease;"
               onmouseover="this.style.transform='translateY(-3px) scale(1.02)'; this.style.boxShadow='0 20px 50px rgba(245,158,11,0.4)';"
               onmouseout="this.style.transform='translateY(0) scale(1)'; this.style.boxShadow='0 15px 40px rgba(245,158,11,0.3)';">
                <i class="fas fa-folder-open"></i>
                <span>View All Resources</span>
            </a>
        </div>
    </div>
</section>
@endif

<!-- Call to Action -->
<section class="cta-section py-90 bg-primary">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8 mb-30">
                <h2 class="text-white mb-10">Ready to Apply?</h2>
                <p class="text-white opacity-90 mb-0">Start your application today and join the PAX Higher Institute community.</p>
            </div>
            <div class="col-lg-4 mb-30 text-lg-right">
                @php 
                $application = App\Models\ApplicationSetting::status(); 
                @endphp
                @isset($application)
                <a href="{{ route('application.start') }}" class="btn btn-secondary btn-lg">Apply Now <i class="fas fa-arrow-right ml-10"></i></a>
                @else
                <a href="{{ route('about') }}" class="btn btn-secondary btn-lg">Contact Us <i class="fas fa-envelope ml-10"></i></a>
                @endisset
            </div>
        </div>
    </div>
</section>

@endsection

@section('styles')
<style>
.process-step .step-number {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: 700;
}

.date-card {
    border: none;
    box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.date-card:hover {
    box-shadow: 0 5px 25px rgba(0, 102, 204, 0.2);
    transform: translateY(-5px);
}

.requirements-card {
    border: none;
    box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
}

.requirements-list {
    list-style: none;
    padding: 0;
}

.requirements-list li {
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
}

.requirements-list li:last-child {
    border-bottom: none;
}

.requirements-list li i {
    margin-right: 15px;
    font-size: 18px;
}

.accordion-item {
    border: none;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.accordion-button {
    font-weight: 600;
    padding: 20px 30px;
}

.accordion-body {
    padding: 20px 30px;
}

.opacity-90 {
    opacity: 0.9;
}

/* Download Cards */
.download-card {
    background: #fff;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    border: 1px solid #f0f0f0;
}

.download-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 10px 40px rgba(0, 102, 204, 0.15);
    border-color: #0066cc;
}

.download-card .download-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0066cc;
}

.download-card:hover .download-icon {
    background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%);
    color: #fff;
}

.download-card .download-title {
    font-weight: 600;
    color: #333;
}

.resources-download-section {
    background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%);
}
</style>
@endsection
