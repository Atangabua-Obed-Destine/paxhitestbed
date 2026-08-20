{{--
    ================================================================
    CAMPUS LIFE — {{ institution_name() }}
    ================================================================
--}}
@extends('web2.layouts.master')

@section('title', 'Campus Life — ' . (institution_code()))
@section('description', 'Experience life at ' . institution_name() . ' — student support services, campus facilities, faith activities, and a vibrant community rooted in Catholic values.')

@section('content')

{{-- HERO --}}
<section class="relative min-h-[380px] lg:min-h-[420px] flex items-end overflow-hidden bg-primary-900">
    <div class="absolute inset-0 opacity-[0.06]" style="background-image:url('data:image/svg+xml,%3Csvg width=60 height=60 viewBox=%220 0 60 60%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cg fill=%22none%22 fill-rule=%22evenodd%22%3E%3Cg fill=%22%23ffffff%22 fill-opacity=%221%22%3E%3Cpath d=%22M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z%22/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')"></div>
    <div class="section-container relative z-10 pb-12 pt-32">
        <nav class="text-sm text-white/50 mb-5" data-aos="fade-up">
            <a href="{{ url('/') }}" class="hover:text-accent-400 transition-colors">Home</a>
            <span class="mx-2">/</span>
            <span class="text-white/80">Campus Life</span>
        </nav>
        <h1 class="text-3xl md:text-4xl lg:text-5xl font-heading font-bold text-white" data-aos="fade-up" data-aos-delay="100">
            Campus Life
        </h1>
        <p class="mt-4 text-lg text-white/70 max-w-2xl" data-aos="fade-up" data-aos-delay="150">
            More than just academics — discover the vibrant community, support systems, and faith-filled experiences that define life at {{ institution_code() }}.
        </p>
    </div>
</section>

{{-- HIGHLIGHTS --}}
<section class="py-16 lg:py-20 bg-white">
    <div class="section-container">
        <div class="text-center mb-12" data-aos="fade-up">
            <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-secondary-50 text-secondary-600 text-xs font-semibold uppercase tracking-wider mb-4">
                <i class="fas fa-heart"></i> What Makes Us Special
            </span>
            <h2 class="text-3xl md:text-4xl font-heading font-bold text-surface-800">The {{ institution_code() }} Experience</h2>
        </div>

        @php
            $highlights = [
                ['icon' => 'fa-church', 'title' => 'Faith & Spirituality', 'desc' => 'Regular Mass, prayer groups, and spiritual formation programs that nurture your relationship with God.', 'color' => 'primary'],
                ['icon' => 'fa-users', 'title' => 'Student Community', 'desc' => 'A welcoming, diverse student body united by shared values and a passion for learning.', 'color' => 'secondary'],
                ['icon' => 'fa-book-open-reader', 'title' => 'Academic Excellence', 'desc' => 'Rigorous academics with personalized mentoring and small class sizes for optimal learning.', 'color' => 'accent'],
                ['icon' => 'fa-hands-holding-child', 'title' => 'Student Support', 'desc' => 'Comprehensive counseling, career guidance, and student welfare services to help you thrive.', 'color' => 'primary'],
                ['icon' => 'fa-futbol', 'title' => 'Sports & Recreation', 'desc' => 'Competitive sports teams, fitness facilities, and recreational activities for a balanced life.', 'color' => 'secondary'],
                ['icon' => 'fa-palette', 'title' => 'Arts & Culture', 'desc' => 'Creative outlets through drama, music, cultural festivals, and artistic expression.', 'color' => 'accent'],
            ];
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($highlights as $idx => $h)
            <div class="group bg-white rounded-2xl border border-surface-100 hover:border-{{ $h['color'] }}-200 p-6 hover:shadow-lg transition-all"
                 data-aos="fade-up" data-aos-delay="{{ $idx * 80 }}">
                <div class="w-14 h-14 rounded-xl bg-{{ $h['color'] }}-50 flex items-center justify-center text-{{ $h['color'] }}-500 mb-4 group-hover:scale-110 transition-transform">
                    <i class="fas {{ $h['icon'] }} text-2xl"></i>
                </div>
                <h3 class="font-heading font-bold text-surface-800 mb-2">{{ $h['title'] }}</h3>
                <p class="text-sm text-surface-500 leading-relaxed">{{ $h['desc'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- SUPPORT SERVICES --}}
@if(isset($services) && $services->count())
<section class="py-16 lg:py-20 bg-surface-50">
    <div class="section-container">
        <div class="text-center mb-12" data-aos="fade-up">
            <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary-50 text-primary-600 text-xs font-semibold uppercase tracking-wider mb-4">
                <i class="fas fa-life-ring"></i> Here For You
            </span>
            <h2 class="text-3xl md:text-4xl font-heading font-bold text-surface-800">Student Support Services</h2>
            <p class="mt-3 text-surface-500 max-w-xl mx-auto">Dedicated services to ensure your well-being and academic success.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($services as $idx => $service)
            <div class="bg-white rounded-xl border border-surface-100 p-6 hover:shadow-md transition-shadow"
                 data-aos="fade-up" data-aos-delay="{{ min($idx * 70, 350) }}">
                <div class="flex items-start gap-4">
                    @if($service->icon)
                    <div class="w-10 h-10 rounded-lg bg-primary-50 flex items-center justify-center text-primary-500 flex-shrink-0">
                        <i class="fas {{ $service->icon }}"></i>
                    </div>
                    @endif
                    <div>
                        <h3 class="font-semibold text-surface-800 mb-1">{{ $service->title }}</h3>
                        @if($service->description)
                        <p class="text-sm text-surface-500 leading-relaxed">{{ Str::limit(strip_tags($service->description), 150) }}</p>
                        @endif
                        @if($service->contact_info)
                        <p class="text-xs text-primary-500 font-medium mt-2"><i class="fas fa-phone-alt mr-1"></i>{{ $service->contact_info }}</p>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- STUDENT RESOURCES --}}
@if(isset($student_resources) && $student_resources->count())
<section class="py-16 lg:py-20 bg-white">
    <div class="section-container">
        <div class="text-center mb-12" data-aos="fade-up">
            <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-accent-50 text-accent-600 text-xs font-semibold uppercase tracking-wider mb-4">
                <i class="fas fa-folder-open"></i> Resources
            </span>
            <h2 class="text-3xl md:text-4xl font-heading font-bold text-surface-800">Student Resources</h2>
            <p class="mt-3 text-surface-500 max-w-xl mx-auto">Handbooks, guides, and policies to help you navigate student life.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 max-w-4xl mx-auto">
            @foreach($student_resources as $idx => $resource)
            <a href="{{ route('resource.download', $resource) }}"
               class="group flex items-center gap-4 bg-surface-50 hover:bg-white rounded-xl border border-surface-100 hover:border-primary-200 hover:shadow-md p-5 transition-all"
               data-aos="fade-up" data-aos-delay="{{ $idx * 60 }}">
                <div class="w-12 h-12 rounded-lg {{ $resource->file_type == 'pdf' ? 'bg-red-50 text-red-500' : 'bg-blue-50 text-blue-500' }} flex items-center justify-center flex-shrink-0">
                    <i class="fas {{ $resource->file_type == 'pdf' ? 'fa-file-pdf' : 'fa-file-alt' }} text-xl"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <h4 class="font-semibold text-surface-800 text-sm group-hover:text-primary-500 transition-colors truncate">{{ $resource->title }}</h4>
                    <p class="text-xs text-surface-400 mt-0.5">{{ strtoupper($resource->file_type ?? 'FILE') }} @if($resource->formatted_file_size) &middot; {{ $resource->formatted_file_size }} @endif</p>
                </div>
                <i class="fas fa-download text-surface-300 group-hover:text-primary-400 transition-colors flex-shrink-0"></i>
            </a>
            @endforeach
        </div>

        <div class="text-center mt-8" data-aos="fade-up">
            <a href="{{ url('/downloads') }}" class="text-primary-500 hover:text-accent-500 font-medium text-sm transition-colors">
                View All Downloads <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
    </div>
</section>
@endif

{{-- CTA --}}
<section class="py-16 bg-gradient-to-r from-secondary-600 to-secondary-800 relative overflow-hidden">
    <div class="absolute inset-0 opacity-5">
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-[400px] text-white"><i class="fas fa-cross"></i></div>
    </div>
    <div class="section-container text-center relative z-10">
        <h2 class="text-3xl font-heading font-bold text-white mb-4" data-aos="fade-up">Come Experience {{ institution_code() }}</h2>
        <p class="text-lg text-white/70 max-w-xl mx-auto mb-8" data-aos="fade-up" data-aos-delay="100">
            Visit our campus and see for yourself what makes ' . institution_name() . ' a special place to study and grow.
        </p>
        <div class="flex flex-wrap justify-center gap-4" data-aos="fade-up" data-aos-delay="150">
            <a href="{{ url('/application') }}" class="btn-accent"><i class="fas fa-pen-to-square mr-2"></i> Apply Now</a>
            <a href="{{ url('/about') }}" class="border-2 border-white/30 text-white hover:bg-white/10 px-6 py-3 rounded-lg font-medium transition-colors">Learn More About Us</a>
        </div>
    </div>
</section>

@endsection
