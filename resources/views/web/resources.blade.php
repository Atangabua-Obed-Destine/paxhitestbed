@extends('web.layouts.master')
@section('title', 'Resources & Downloads')

@section('social_meta_tags')
    @if(isset($setting))
    <!-- Open Graph Meta Tags -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $setting->title }}"/>
    <meta property="og:title" content="Resources & Downloads | {{ $setting->title }}"/>
    <meta property="og:description" content="Download essential documents, student guides, academic calendars, and forms from {{ $setting->title }}."/>
    <meta property="og:url" content="{{ route('resources') }}"/>
    <meta property="og:image" content="{{ asset('/uploads/setting/'.$setting->logo_path) }}"/>

    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Resources & Downloads | {{ $setting->title }}" />
    <meta name="twitter:description" content="Download essential documents, student guides, academic calendars, and forms." />
    
    <!-- Page-Specific Meta Tags -->
    <meta name="description" content="Download essential documents, student guides, academic calendars, and forms from {{ $setting->title }}.">
    <meta name="keywords" content="resources, downloads, student guide, academic calendar, forms, {{ $setting->title }}">
    <link rel="canonical" href="{{ route('resources') }}">
    @endif
@endsection

@section('content')

<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h1><i class="fas fa-download me-2"></i> Resources & Downloads</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Downloads</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</section>

<!-- Resources Section -->
<section class="py-5" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%); position: relative; overflow: hidden; min-height: 60vh;">
    <!-- Decorative Background -->
    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-image: url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"60\" height=\"60\"><circle cx=\"30\" cy=\"30\" r=\"1\" fill=\"rgba(255,255,255,0.03)\"/></svg>'); pointer-events: none;"></div>
    
    <div class="container position-relative" style="z-index: 2;">
        
        @if(isset($featured) && $featured->count() > 0)
        <!-- Featured Resources Section -->
        <div class="text-center mb-5" data-aos="fade-up">
            <span style="display: inline-block; background: linear-gradient(135deg, #f59e0b, #ef4444); padding: 8px 24px; border-radius: 30px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: #fff; margin-bottom: 20px;">
                <i class="fas fa-star me-2"></i> Featured Resources
            </span>
            <h2 style="font-size: 36px; font-weight: 800; color: #fff; margin-bottom: 15px;">Essential Documents</h2>
            <p style="font-size: 16px; color: rgba(255,255,255,0.6); max-width: 500px; margin: 0 auto;">The most important resources for students and applicants</p>
        </div>

        <div class="row justify-content-center g-4 mb-5">
            @foreach($featured as $resource)
            <div class="col-lg-6" data-aos="fade-up" data-aos-delay="{{ $loop->index * 100 }}">
                <div style="background: rgba(255,255,255,0.08); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.15); border-radius: 24px; padding: 35px; display: flex; align-items: center; gap: 30px; transition: all 0.4s ease;" 
                     onmouseover="this.style.transform='translateY(-8px)'; this.style.background='rgba(255,255,255,0.12)'; this.style.boxShadow='0 30px 60px rgba(0,102,204,0.25)';" 
                     onmouseout="this.style.transform='translateY(0)'; this.style.background='rgba(255,255,255,0.08)'; this.style.boxShadow='none';">
                    
                    <!-- Icon Box -->
                    <div style="width: 120px; height: 120px; min-width: 120px; background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%); border-radius: 24px; display: flex; align-items: center; justify-content: center; position: relative; box-shadow: 0 15px 40px rgba(245,158,11,0.3);">
                        <i class="{{ $resource->icon ?? $resource->file_icon }}" style="font-size: 50px; color: #fff;"></i>
                        <span style="position: absolute; top: -8px; right: -8px; background: linear-gradient(135deg, #0066cc, #7c3aed); color: #fff; font-size: 10px; font-weight: 800; padding: 5px 12px; border-radius: 12px; letter-spacing: 1px;">
                            {{ strtoupper(pathinfo($resource->file_path, PATHINFO_EXTENSION)) }}
                        </span>
                    </div>

                    <!-- Content -->
                    <div style="flex-grow: 1;">
                        <span style="display: inline-block; background: rgba(245,158,11,0.2); color: #fbbf24; padding: 4px 14px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">
                            {{ $resource->category_label }}
                        </span>
                        <h4 style="color: #fff; font-weight: 700; font-size: 22px; margin-bottom: 8px;">{{ $resource->title }}</h4>
                        @if($resource->description)
                        <p style="color: rgba(255,255,255,0.5); font-size: 14px; margin-bottom: 12px; line-height: 1.5;">{{ Str::limit($resource->description, 100) }}</p>
                        @endif
                        <div style="display: flex; gap: 20px; margin-bottom: 15px;">
                            <span style="color: rgba(255,255,255,0.5); font-size: 13px;">
                                <i class="fas fa-weight-hanging" style="color: #fbbf24; margin-right: 5px;"></i> {{ $resource->formatted_file_size }}
                            </span>
                            <span style="color: rgba(255,255,255,0.5); font-size: 13px;">
                                <i class="fas fa-download" style="color: #fbbf24; margin-right: 5px;"></i> {{ number_format($resource->download_count) }} downloads
                            </span>
                        </div>
                        <a href="{{ route('resource.download', $resource->id) }}" 
                           style="display: inline-flex; align-items: center; gap: 10px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #fff; padding: 12px 28px; border-radius: 14px; font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; text-decoration: none; transition: all 0.3s ease;"
                           onmouseover="this.style.transform='scale(1.05)'; this.style.boxShadow='0 15px 30px rgba(245,158,11,0.4)';"
                           onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='none';">
                            <i class="fas fa-download"></i>
                            <span>Download Now</span>
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        <!-- All Resources by Category -->
        @if(isset($resources) && $resources->count() > 0)
        
        <!-- Section Header -->
        <div class="text-center mb-5 mt-5" data-aos="fade-up">
            <span style="display: inline-block; background: linear-gradient(135deg, #0066cc, #7c3aed); padding: 8px 24px; border-radius: 30px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: #fff; margin-bottom: 20px;">
                <i class="fas fa-folder-open me-2"></i> All Resources
            </span>
            <h2 style="font-size: 36px; font-weight: 800; color: #fff; margin-bottom: 15px;">Browse by Category</h2>
            <p style="font-size: 16px; color: rgba(255,255,255,0.6); max-width: 500px; margin: 0 auto;">Find the documents you need organized by type</p>
        </div>

        @foreach($resources as $categoryKey => $categoryResources)
        <div class="mb-5" data-aos="fade-up">
            <!-- Category Header -->
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 20px 0; border-bottom: 2px solid rgba(255,255,255,0.1); margin-bottom: 30px;">
                <h4 style="margin: 0; font-weight: 700; color: #fff; font-size: 22px; display: flex; align-items: center; gap: 12px;">
                    @switch($categoryKey)
                        @case('student_guide')
                            <span style="width: 45px; height: 45px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); border-radius: 12px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-book" style="color: #fff;"></i></span>
                            @break
                        @case('calendarium')
                            <span style="width: 45px; height: 45px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 12px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-calendar-alt" style="color: #fff;"></i></span>
                            @break
                        @case('academic')
                            <span style="width: 45px; height: 45px; background: linear-gradient(135deg, #06b6d4, #0891b2); border-radius: 12px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-graduation-cap" style="color: #fff;"></i></span>
                            @break
                        @case('forms')
                            <span style="width: 45px; height: 45px; background: linear-gradient(135deg, #f59e0b, #d97706); border-radius: 12px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-file-alt" style="color: #fff;"></i></span>
                            @break
                        @case('policies')
                            <span style="width: 45px; height: 45px; background: linear-gradient(135deg, #ef4444, #dc2626); border-radius: 12px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-gavel" style="color: #fff;"></i></span>
                            @break
                        @case('handbooks')
                            <span style="width: 45px; height: 45px; background: linear-gradient(135deg, #8b5cf6, #7c3aed); border-radius: 12px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-book-open" style="color: #fff;"></i></span>
                            @break
                        @default
                            <span style="width: 45px; height: 45px; background: linear-gradient(135deg, #64748b, #475569); border-radius: 12px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-folder" style="color: #fff;"></i></span>
                    @endswitch
                    {{ $categories[$categoryKey] ?? ucfirst($categoryKey) }}
                </h4>
                <span style="background: rgba(255,255,255,0.1); padding: 8px 20px; border-radius: 25px; font-size: 13px; font-weight: 600; color: rgba(255,255,255,0.7);">
                    {{ $categoryResources->count() }} {{ Str::plural('resource', $categoryResources->count()) }}
                </span>
            </div>

            <!-- Resource Cards -->
            <div class="row g-4">
                @foreach($categoryResources as $resource)
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="{{ $loop->index * 50 }}">
                    <div style="background: rgba(255,255,255,0.05); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); border-radius: 24px; padding: 30px; text-align: center; transition: all 0.4s ease; height: 100%;" 
                         onmouseover="this.style.transform='translateY(-10px)'; this.style.background='rgba(255,255,255,0.1)'; this.style.boxShadow='0 25px 50px rgba(0,102,204,0.3)';" 
                         onmouseout="this.style.transform='translateY(0)'; this.style.background='rgba(255,255,255,0.05)'; this.style.boxShadow='none';">
                        
                        <!-- Icon Box -->
                        <div style="width: 90px; height: 90px; margin: 0 auto 20px; background: linear-gradient(135deg, #0066cc 0%, #7c3aed 100%); border-radius: 20px; display: flex; align-items: center; justify-content: center; position: relative; box-shadow: 0 12px 30px rgba(0,102,204,0.3);">
                            <i class="{{ $resource->icon ?? $resource->file_icon }}" style="font-size: 36px; color: #fff;"></i>
                            <span style="position: absolute; top: -6px; right: -6px; background: linear-gradient(135deg, #f59e0b, #ef4444); color: #fff; font-size: 9px; font-weight: 800; padding: 4px 8px; border-radius: 10px; letter-spacing: 1px;">
                                {{ strtoupper(pathinfo($resource->file_path, PATHINFO_EXTENSION)) }}
                            </span>
                        </div>

                        <!-- Title -->
                        <h5 style="color: #fff; font-weight: 700; font-size: 17px; margin-bottom: 10px; line-height: 1.4;">{{ $resource->title }}</h5>

                        @if($resource->description)
                        <p style="color: rgba(255,255,255,0.5); font-size: 13px; margin-bottom: 15px; line-height: 1.5;">{{ Str::limit($resource->description, 70) }}</p>
                        @endif

                        <!-- File Info -->
                        <div style="display: flex; justify-content: center; gap: 18px; margin-bottom: 18px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.1);">
                            <span style="color: rgba(255,255,255,0.5); font-size: 12px;">
                                <i class="fas fa-weight-hanging" style="color: #60a5fa; margin-right: 4px;"></i> {{ $resource->formatted_file_size }}
                            </span>
                            @if($resource->download_count > 0)
                            <span style="color: rgba(255,255,255,0.5); font-size: 12px;">
                                <i class="fas fa-download" style="color: #60a5fa; margin-right: 4px;"></i> {{ number_format($resource->download_count) }}
                            </span>
                            @endif
                        </div>

                        <!-- Download Button -->
                        <a href="{{ route('resource.download', $resource->id) }}" 
                           style="display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%); color: #fff; padding: 11px 24px; border-radius: 12px; font-weight: 700; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; text-decoration: none; transition: all 0.3s ease;"
                           onmouseover="this.style.background='linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%)'; this.style.transform='scale(1.05)';"
                           onmouseout="this.style.background='linear-gradient(135deg, #0066cc 0%, #0052a3 100%)'; this.style.transform='scale(1)';">
                            <span>Download</span>
                            <i class="fas fa-arrow-down"></i>
                        </a>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
        
        @else
        <!-- No Resources Message -->
        <div class="text-center py-5" data-aos="fade-up">
            <div style="width: 120px; height: 120px; margin: 0 auto 30px; background: rgba(255,255,255,0.05); border-radius: 30px; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-folder-open" style="font-size: 50px; color: rgba(255,255,255,0.3);"></i>
            </div>
            <h4 style="color: rgba(255,255,255,0.7); font-weight: 600; margin-bottom: 10px;">No resources available at the moment</h4>
            <p style="color: rgba(255,255,255,0.4);">Please check back later for downloadable documents and guides.</p>
        </div>
        @endif
    </div>
</section>

@endsection
