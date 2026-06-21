@extends('web.layouts.master')
@section('title', 'Campus Life')

@section('social_meta_tags')
    @if(isset($setting))
    <!-- Open Graph Meta Tags -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $setting->title }}"/>
    <meta property="og:title" content="Campus Life | {{ $setting->title }}"/>
    <meta property="og:description" content="Experience vibrant campus life at PAX Higher Institute. Explore student activities, facilities, clubs, sports, and support services."/>
    <meta property="og:url" content="{{ route('campus-life') }}"/>
    <meta property="og:image" content="{{ asset('/uploads/setting/'.$setting->logo_path) }}"/>

    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:site" content="{!! '@'.str_replace(' ', '', $setting->title) !!}" />
    <meta name="twitter:url" content="{{ route('campus-life') }}" />
    <meta name="twitter:title" content="Campus Life | {{ $setting->title }}" />
    <meta name="twitter:description" content="Experience vibrant campus life at PAX Higher Institute. Explore student activities, facilities, clubs, sports, and support services." />
    <meta name="twitter:image" content="{{ asset('/uploads/setting/'.$setting->logo_path) }}" />
    
    <!-- Page-Specific Meta Tags -->
    <meta name="description" content="Experience vibrant campus life at PAX Higher Institute. Explore student activities, facilities, clubs, sports, and support services.">
    <meta name="keywords" content="campus life, {{ $setting->title }}, student life, campus facilities, student activities, clubs, sports, student services">
    <link rel="canonical" href="{{ route('campus-life') }}">
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
        "name": "Campus Life",
        "item": "{{ route('campus-life') }}"
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
                <h1>Campus Life</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Campus Life</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</section>

<!-- Campus Overview -->
<section class="campus-overview pt-90 pb-60">
    <div class="container">
        <!-- Section Header -->
        <div class="row mb-60">
            <div class="col-12">
                <div class="section-title center" data-aos="fade-up">
                    <h5 style="color: #0066CC; font-weight: 600; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 15px;">
                        <i class="fas fa-university"></i> Campus Life
                    </h5>
                    <h2 style="font-size: 38px; font-weight: 700; color: #003366; margin-bottom: 15px;">
                        Experience Life at PAX
                    </h2>
                    <div style="width: 80px; height: 4px; background: linear-gradient(90deg, #0066CC, #FF6B35); border-radius: 2px; margin: 15px auto 20px;"></div>
                    <p style="color: #666666; font-size: 16px; max-width: 700px; margin: 0 auto;">
                        A vibrant community that fosters learning, growth, and unforgettable experiences
                    </p>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-4 col-md-6 mb-40" data-aos="fade-up" data-aos-delay="100">
                <div class="campus-feature-card">
                    <div class="feature-icon-wrapper">
                        <i class="fas fa-book-reader"></i>
                    </div>
                    <h4 class="feature-title">Modern Library</h4>
                    <p class="feature-text">Extensive collection of books, journals, and digital resources available 24/7</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-40" data-aos="fade-up" data-aos-delay="200">
                <div class="campus-feature-card">
                    <div class="feature-icon-wrapper">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                    <h4 class="feature-title">Sports Facilities</h4>
                    <p class="feature-text">State-of-the-art gymnasium, sports fields, and recreational facilities</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-40" data-aos="fade-up" data-aos-delay="300">
                <div class="campus-feature-card">
                    <div class="feature-icon-wrapper">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <h4 class="feature-title">Dining Services</h4>
                    <p class="feature-text">Multiple cafeterias offering nutritious meals and diverse culinary options</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-40" data-aos="fade-up" data-aos-delay="100">
                <div class="campus-feature-card">
                    <div class="feature-icon-wrapper">
                        <i class="fas fa-bed"></i>
                    </div>
                    <h4 class="feature-title">Hostel Accommodation</h4>
                    <p class="feature-text">Comfortable and secure on-campus housing for students</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-40" data-aos="fade-up" data-aos-delay="200">
                <div class="campus-feature-card">
                    <div class="feature-icon-wrapper">
                        <i class="fas fa-wifi"></i>
                    </div>
                    <h4 class="feature-title">Campus-wide WiFi</h4>
                    <p class="feature-text">High-speed internet connectivity across the entire campus</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-40" data-aos="fade-up" data-aos-delay="300">
                <div class="campus-feature-card">
                    <div class="feature-icon-wrapper">
                        <i class="fas fa-heartbeat"></i>
                    </div>
                    <h4 class="feature-title">Health Center</h4>
                    <p class="feature-text">Professional medical care and counseling services for students</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Support Services -->
@if($services->count() > 0)
<section class="support-services-section pb-90">
    <div class="container">
        <div class="row mb-60">
            <div class="col-12">
                <div class="section-title center" data-aos="fade-up">
                    <h5 style="color: #0066CC; font-weight: 600; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 15px;">
                        <i class="fas fa-hands-helping"></i> Support Services
                    </h5>
                    <h2 style="font-size: 38px; font-weight: 700; color: #003366; margin-bottom: 15px;">
                        Student Support Services
                    </h2>
                    <div style="width: 80px; height: 4px; background: linear-gradient(90deg, #0066CC, #FF6B35); border-radius: 2px; margin: 15px auto 20px;"></div>
                    <p style="color: #666666; font-size: 16px; max-width: 700px; margin: 0 auto;">
                        Comprehensive support to ensure your success and well-being
                    </p>
                </div>
            </div>
        </div>
        
        <div class="row">
            @foreach($services as $index => $service)
            <div class="col-lg-4 col-md-6 mb-40" data-aos="fade-up" data-aos-delay="{{ ($index % 3) * 100 + 100 }}">
                <div class="support-service-card h-100">
                    <div class="service-icon-wrapper">
                        @if($service->icon)
                        <i class="{{ $service->icon }}"></i>
                        @else
                        <i class="fas fa-hands-helping"></i>
                        @endif
                    </div>
                    <h5 class="service-title">{{ $service->title }}</h5>
                    <p class="service-description">{{ $service->description }}</p>
                    @if($service->contact_info)
                    <div class="service-contact-info">
                        <i class="fas fa-info-circle"></i>
                        <span>{!! nl2br(e($service->contact_info)) !!}</span>
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<!-- Student Activities -->
<section class="student-activities-section">
    <div class="decorative-circle circle-1"></div>
    <div class="decorative-circle circle-2"></div>
    <div class="decorative-circle circle-3"></div>
    
    <div class="container">
        <div class="row mb-60">
            <div class="col-12">
                <div class="section-title center" data-aos="fade-up">
                    <h5 style="color: #FF6B35; font-weight: 600; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 15px;">
                        <i class="fas fa-users"></i> Get Involved
                    </h5>
                    <h2 style="font-size: 38px; font-weight: 700; color: #ffffff !important; margin-bottom: 15px;">
                        Student Activities & Clubs
                    </h2>
                    <div style="width: 80px; height: 4px; background: linear-gradient(90deg, #FF6B35, #0066CC); border-radius: 2px; margin: 15px auto 20px;"></div>
                    <p style="color: #ffffff !important; font-size: 16px; max-width: 700px; margin: 0 auto; opacity: 0.95;">
                        Join vibrant student organizations and explore your interests
                    </p>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-3 col-md-6 mb-30" data-aos="fade-up" data-aos-delay="100">
                <div class="activity-card-enhanced">
                    <div class="activity-icon">
                        <i class="fas fa-theater-masks"></i>
                    </div>
                    <h6 class="activity-name">Drama & Arts</h6>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-30" data-aos="fade-up" data-aos-delay="150">
                <div class="activity-card-enhanced">
                    <div class="activity-icon">
                        <i class="fas fa-music"></i>
                    </div>
                    <h6 class="activity-name">Music Society</h6>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-30" data-aos="fade-up" data-aos-delay="200">
                <div class="activity-card-enhanced">
                    <div class="activity-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h6 class="activity-name">Debate Club</h6>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-30" data-aos="fade-up" data-aos-delay="250">
                <div class="activity-card-enhanced">
                    <div class="activity-icon">
                        <i class="fas fa-laptop-code"></i>
                    </div>
                    <h6 class="activity-name">Tech Club</h6>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-30" data-aos="fade-up" data-aos-delay="100">
                <div class="activity-card-enhanced">
                    <div class="activity-icon">
                        <i class="fas fa-hand-holding-heart"></i>
                    </div>
                    <h6 class="activity-name">Volunteer Corps</h6>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-30" data-aos="fade-up" data-aos-delay="150">
                <div class="activity-card-enhanced">
                    <div class="activity-icon">
                        <i class="fas fa-camera"></i>
                    </div>
                    <h6 class="activity-name">Photography Club</h6>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-30" data-aos="fade-up" data-aos-delay="200">
                <div class="activity-card-enhanced">
                    <div class="activity-icon">
                        <i class="fas fa-football-ball"></i>
                    </div>
                    <h6 class="activity-name">Sports Teams</h6>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-30" data-aos="fade-up" data-aos-delay="250">
                <div class="activity-card-enhanced">
                    <div class="activity-icon">
                        <i class="fas fa-newspaper"></i>
                    </div>
                    <h6 class="activity-name">Campus Magazine</h6>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials -->
<section hidden class="campus-testimonials-section">
    <div class="container">
        <div class="row mb-60">
            <div class="col-12">
                <div class="section-title center" data-aos="fade-up">
                    <h5 style="color: #0066CC; font-weight: 600; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 15px;">
                        <i class="fas fa-quote-left"></i> Testimonials
                    </h5>
                    <h2 style="font-size: 38px; font-weight: 700; color: #003366; margin-bottom: 15px;">
                        What Students Say
                    </h2>
                    <div style="width: 80px; height: 4px; background: linear-gradient(90deg, #0066CC, #FF6B35); border-radius: 2px; margin: 15px auto 20px;"></div>
                    <p style="color: #666666; font-size: 16px; max-width: 700px; margin: 0 auto;">
                        Hear from our students about their experiences at PAX Higher Institute
                    </p>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-4 mb-30" data-aos="fade-up" data-aos-delay="100">
                <div class="campus-testimonial-card">
                    <div class="quote-icon">
                        <i class="fas fa-quote-right"></i>
                    </div>
                    <div class="stars-rating">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">"The campus life at PAX is amazing! The facilities are top-notch and there are so many opportunities to get involved and make friends."</p>
                    <div class="student-profile">
                        <div class="student-avatar">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="student-details">
                            <h6 class="student-name">Sarah Johnson</h6>
                            <span class="student-program">Computer Science Student</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-30" data-aos="fade-up" data-aos-delay="200">
                <div class="campus-testimonial-card">
                    <div class="quote-icon">
                        <i class="fas fa-quote-right"></i>
                    </div>
                    <div class="stars-rating">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">"The support services are incredible. From academic advising to career counseling, PAX truly cares about student success."</p>
                    <div class="student-profile">
                        <div class="student-avatar">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="student-details">
                            <h6 class="student-name">Michael Chen</h6>
                            <span class="student-program">Business Administration Student</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-30" data-aos="fade-up" data-aos-delay="300">
                <div class="campus-testimonial-card">
                    <div class="quote-icon">
                        <i class="fas fa-quote-right"></i>
                    </div>
                    <div class="stars-rating">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">"Being part of the debate club has been transformative. PAX encourages students to explore their passions and develop new skills."</p>
                    <div class="student-profile">
                        <div class="student-avatar">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="student-details">
                            <h6 class="student-name">Emily Williams</h6>
                            <span class="student-program">Law Student</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Student Resources Section -->
@if(isset($student_resources) && $student_resources->count() > 0)
<section class="py-5" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%); position: relative; overflow: hidden;">
    <!-- Decorative Background -->
    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-image: url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"60\" height=\"60\"><circle cx=\"30\" cy=\"30\" r=\"1\" fill=\"rgba(255,255,255,0.03)\"/></svg>'); pointer-events: none;"></div>
    
    <div class="container position-relative" style="z-index: 2;">
        <!-- Section Header -->
        <div class="text-center mb-5" data-aos="fade-up">
            <span style="display: inline-block; background: linear-gradient(135deg, #0066cc, #7c3aed); padding: 8px 24px; border-radius: 30px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: #fff; margin-bottom: 20px;">
                <i class="fas fa-book-open me-2"></i> Student Resources
            </span>
            <h2 style="font-size: 42px; font-weight: 800; color: #fff; margin-bottom: 15px;">Essential Documents & Guides</h2>
            <p style="font-size: 18px; color: rgba(255,255,255,0.6); max-width: 600px; margin: 0 auto;">Access important handbooks, policies, and guidelines to help you succeed at PAX</p>
        </div>

        <!-- Download Cards Grid -->
        <div class="row justify-content-center g-4">
            @foreach($student_resources as $resource)
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="{{ $loop->index * 100 }}">
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
                    <p style="color: rgba(255,255,255,0.5); font-size: 14px; margin-bottom: 15px; line-height: 1.6;">{{ Str::limit($resource->description, 80) }}</p>
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
                <span>Browse All Resources</span>
            </a>
        </div>
    </div>
</section>
@endif

<!-- Call to Action -->
<section class="campus-cta-section">
    <div class="decorative-shape shape-1"></div>
    <div class="decorative-shape shape-2"></div>
    
    <div class="container">
        <div class="row align-items-center" data-aos="fade-up">
            <div class="col-lg-8 mb-30">
                <h2 style="color: #ffffff !important; font-size: 34px; font-weight: 700; margin-bottom: 15px;">
                    Experience Campus Life Yourself!
                </h2>
                <p style="color: #ffffff !important; font-size: 16px; margin-bottom: 0; opacity: 0.95;">
                    Visit our campus and see what makes PAX Higher Institute special. Schedule your campus tour today!
                </p>
            </div>
            <div class="col-lg-4 mb-30 text-lg-right">
                <a href="{{ route('about') }}" class="btn btn-campus-cta">
                    Contact Us <i class="fas fa-envelope"></i>
                </a>
            </div>
        </div>
    </div>
</section>

@endsection

@section('styles')
<style>
/* Student Resources Section */
.resource-download-item {
    background: #fff;
    border-radius: 16px;
    padding: 25px;
    box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    border: 1px solid #f0f0f0;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.resource-download-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0, 102, 204, 0.15);
    border-color: #0066cc;
}

.resource-icon-wrapper {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #e8f4fd 0%, #d0e8f9 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
}

.resource-icon-wrapper i {
    font-size: 24px;
    color: #0066cc;
}

.resource-download-item:hover .resource-icon-wrapper {
    background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%);
}

.resource-download-item:hover .resource-icon-wrapper i {
    color: #fff;
}

.resource-content h5 {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
}

.resource-category {
    display: inline-block;
    background: #e8f4fd;
    color: #0066cc;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    margin-bottom: 10px;
}

.resource-desc {
    font-size: 14px;
    color: #666;
    margin-bottom: 15px;
    flex-grow: 1;
}

.resource-meta {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.resource-meta span {
    font-size: 12px;
    color: #888;
}

.resource-meta span i {
    margin-right: 4px;
}

.resource-download-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 20px;
    background: #0066cc;
    color: #fff;
    border-radius: 8px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.3s ease;
    margin-top: auto;
}

.resource-download-btn:hover {
    background: #0052a3;
    color: #fff;
    transform: scale(1.02);
}

.resource-download-btn i {
    margin-right: 8px;
}
</style>
@endsection
