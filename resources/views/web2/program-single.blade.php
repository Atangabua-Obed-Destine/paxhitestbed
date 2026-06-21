{{--
    ================================================================
    PROGRAM DETAIL — PAX Higher Institute
    ================================================================
--}}
@extends('web2.layouts.master')

@section('title', $program->title . ' — ' . ($setting->title ?? 'PAXHI'))
@section('description', Str::limit(strip_tags($program->excerpt ?? $program->description), 160))

@section('social_meta_tags')
<meta property="og:title" content="{{ $program->title }} — PAXHI">
<meta property="og:description" content="{{ Str::limit(strip_tags($program->excerpt ?? $program->description), 160) }}">
@if($program->featured_image)
<meta property="og:image" content="{{ asset('uploads/programs/'.$program->featured_image) }}">
@endif
@endsection

@section('content')

{{-- HERO BANNER --}}
<section class="relative min-h-[400px] lg:min-h-[450px] flex items-end overflow-hidden">
    @if($program->banner_image)
    <img src="{{ asset('uploads/programs/'.$program->banner_image) }}" alt="{{ $program->title }}" class="absolute inset-0 w-full h-full object-cover">
    @elseif($program->featured_image)
    <img src="{{ asset('uploads/programs/'.$program->featured_image) }}" alt="{{ $program->title }}" class="absolute inset-0 w-full h-full object-cover">
    @endif
    <div class="absolute inset-0 bg-gradient-to-t from-primary-900/95 via-primary-900/60 to-primary-900/30"></div>
    <div class="section-container relative z-10 pb-12 pt-32">
        <nav class="text-sm text-white/50 mb-5" aria-label="Breadcrumb" data-aos="fade-up">
            <a href="{{ url('/') }}" class="hover:text-accent-400 transition-colors">Home</a>
            <span class="mx-2">/</span>
            <a href="{{ url('/programs') }}" class="hover:text-accent-400 transition-colors">Programs</a>
            @if($program->faculty)
            <span class="mx-2">/</span>
            <a href="{{ url('/faculties/'.$program->faculty->slug) }}" class="hover:text-accent-400 transition-colors">{{ $program->faculty->title }}</a>
            @endif
            <span class="mx-2">/</span>
            <span class="text-white/80">{{ Str::limit($program->title, 40) }}</span>
        </nav>

        <div class="flex flex-wrap gap-2 mb-4" data-aos="fade-up" data-aos-delay="50">
            @if($program->academic_level_name)
            <span class="badge badge-accent text-xs">{{ $program->academic_level_name }}</span>
            @endif
            @if($program->faculty)
            <span class="badge bg-white/15 backdrop-blur text-white text-xs">{{ $program->faculty->title }}</span>
            @endif
        </div>

        <h1 class="text-3xl md:text-4xl lg:text-5xl font-heading font-bold text-white mb-4 max-w-4xl leading-tight" data-aos="fade-up" data-aos-delay="100">
            {{ $program->title }}
        </h1>

        {{-- Key Stats --}}
        <div class="flex flex-wrap gap-6 mt-6 text-white/70 text-sm" data-aos="fade-up" data-aos-delay="150">
            @if($program->duration)
            <div class="flex items-center gap-2"><i class="fas fa-clock text-accent-400"></i>{{ $program->duration }}</div>
            @endif
            @if($program->credit)
            <div class="flex items-center gap-2"><i class="fas fa-layer-group text-accent-400"></i>{{ $program->credit }} Credits</div>
            @endif
            @if($program->shortcode)
            <div class="flex items-center gap-2"><i class="fas fa-hashtag text-accent-400"></i>{{ $program->shortcode }}</div>
            @endif
        </div>
    </div>
</section>


{{-- MAIN CONTENT --}}
<section class="py-16 lg:py-20 bg-white">
    <div class="section-container">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10 lg:gap-14">

            {{-- LEFT: Main Content --}}
            <div class="lg:col-span-2 space-y-10">
                {{-- Featured Image --}}
                @if($program->featured_image)
                <div class="rounded-2xl overflow-hidden shadow-md" data-aos="fade-up">
                    <img src="{{ asset('uploads/programs/'.$program->featured_image) }}" alt="{{ $program->title }}" class="w-full h-auto object-cover">
                </div>
                @endif

                {{-- Description --}}
                @if($program->description)
                <div data-aos="fade-up">
                    <h2 class="text-2xl font-heading font-bold text-surface-800 mb-5 flex items-center gap-3">
                        <span class="w-1 h-7 bg-primary-500 rounded-full"></span>Program Overview
                    </h2>
                    <div class="prose prose-surface max-w-none text-surface-600 leading-relaxed [&>p]:mb-4 [&>ul]:list-disc [&>ul]:pl-5 [&>ol]:list-decimal [&>ol]:pl-5 [&>h3]:font-heading [&>h3]:font-bold [&>h3]:text-surface-800 [&>h3]:mt-6 [&>h3]:mb-3">
                        {!! $program->description !!}
                    </div>
                </div>
                @endif

                {{-- Requirements --}}
                @if($program->requirements)
                <div data-aos="fade-up">
                    <h2 class="text-2xl font-heading font-bold text-surface-800 mb-5 flex items-center gap-3">
                        <span class="w-1 h-7 bg-accent-500 rounded-full"></span>Admission Requirements
                    </h2>
                    <div class="bg-accent-50/50 rounded-2xl p-6 border border-accent-100">
                        <div class="prose prose-surface max-w-none text-surface-600 [&>ul]:list-disc [&>ul]:pl-5 [&>li]:mb-2">
                            {!! $program->requirements !!}
                        </div>
                    </div>
                </div>
                @endif

                {{-- Career Prospects --}}
                @if($program->career_prospects)
                <div data-aos="fade-up">
                    <h2 class="text-2xl font-heading font-bold text-surface-800 mb-5 flex items-center gap-3">
                        <span class="w-1 h-7 bg-secondary-500 rounded-full"></span>Career Prospects
                    </h2>
                    <div class="prose prose-surface max-w-none text-surface-600 [&>ul]:list-disc [&>ul]:pl-5 [&>li]:mb-2">
                        {!! $program->career_prospects !!}
                    </div>
                </div>
                @endif
            </div>

            {{-- RIGHT: Sidebar --}}
            <div class="space-y-6">
                {{-- Apply CTA Card --}}
                <div class="bg-gradient-to-br from-primary-500 to-primary-700 rounded-2xl p-6 text-white" data-aos="fade-left">
                    <h3 class="font-heading font-bold text-lg mb-2 text-white">Interested in this Program?</h3>
                    <p class="text-white/70 text-sm mb-5">Applications are now open. Take the first step towards your future.</p>
                    <a href="{{ url('/application') }}" class="btn-accent w-full justify-center text-sm">
                        Apply Now <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                    <a href="{{ url('/admissions') }}" class="block text-center mt-3 text-white/60 text-sm hover:text-white/80 transition-colors">
                        View Admission Requirements
                    </a>
                </div>

                {{-- Program Details Card --}}
                <div class="bg-white rounded-2xl border border-surface-100 shadow-sm overflow-hidden" data-aos="fade-left" data-aos-delay="100">
                    <div class="bg-surface-50 px-6 py-4 border-b border-surface-100">
                        <h3 class="font-heading font-bold text-surface-800 text-base">Program Details</h3>
                    </div>
                    <div class="divide-y divide-surface-100">
                        @if($program->academic_level_name)
                        <div class="px-6 py-3 flex justify-between text-sm">
                            <span class="text-surface-500">Level</span>
                            <span class="font-semibold text-surface-800">{{ $program->academic_level_name }}</span>
                        </div>
                        @endif
                        @if($program->duration)
                        <div class="px-6 py-3 flex justify-between text-sm">
                            <span class="text-surface-500">Duration</span>
                            <span class="font-semibold text-surface-800">{{ $program->duration }}</span>
                        </div>
                        @endif
                        @if($program->credit)
                        <div class="px-6 py-3 flex justify-between text-sm">
                            <span class="text-surface-500">Credits</span>
                            <span class="font-semibold text-surface-800">{{ $program->credit }}</span>
                        </div>
                        @endif
                        @if($program->faculty)
                        <div class="px-6 py-3 flex justify-between text-sm">
                            <span class="text-surface-500">Faculty</span>
                            <a href="{{ url('/faculties/'.$program->faculty->slug) }}" class="font-semibold text-primary-500 hover:text-accent-500 transition-colors">{{ $program->faculty->title }}</a>
                        </div>
                        @endif
                        @if($program->shortcode)
                        <div class="px-6 py-3 flex justify-between text-sm">
                            <span class="text-surface-500">Code</span>
                            <span class="font-semibold text-surface-800">{{ $program->shortcode }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Contact Card --}}
                <div class="bg-surface-50 rounded-2xl p-6 border border-surface-100" data-aos="fade-left" data-aos-delay="150">
                    <h3 class="font-heading font-bold text-surface-800 text-base mb-3">Need More Information?</h3>
                    <p class="text-sm text-surface-500 mb-4">Our admissions office is ready to help you with any questions.</p>
                    @if($topbarSetting && $topbarSetting->phone)
                    <a href="tel:{{ $topbarSetting->phone }}" class="flex items-center gap-3 text-sm text-surface-600 hover:text-primary-500 transition-colors mb-2">
                        <i class="fas fa-phone-alt text-accent-500 w-4"></i>{{ $topbarSetting->phone }}
                    </a>
                    @endif
                    @if($topbarSetting && $topbarSetting->email)
                    <a href="mailto:{{ $topbarSetting->email }}" class="flex items-center gap-3 text-sm text-surface-600 hover:text-primary-500 transition-colors">
                        <i class="fas fa-envelope text-accent-500 w-4"></i>{{ $topbarSetting->email }}
                    </a>
                    @endif
                </div>

                {{-- Share --}}
                <div class="bg-white rounded-2xl p-6 border border-surface-100">
                    <h3 class="font-heading font-bold text-surface-800 text-sm mb-3">Share This Program</h3>
                    <div class="flex gap-2">
                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(url()->current()) }}" target="_blank" rel="noopener"
                           class="w-9 h-9 rounded-lg bg-surface-100 hover:bg-blue-500 hover:text-white flex items-center justify-center text-surface-500 transition-all">
                            <i class="fab fa-facebook-f text-sm"></i>
                        </a>
                        <a href="https://twitter.com/intent/tweet?url={{ urlencode(url()->current()) }}&text={{ urlencode($program->title) }}" target="_blank" rel="noopener"
                           class="w-9 h-9 rounded-lg bg-surface-100 hover:bg-black hover:text-white flex items-center justify-center text-surface-500 transition-all">
                            <i class="fab fa-x-twitter text-sm"></i>
                        </a>
                        <a href="https://wa.me/?text={{ urlencode($program->title . ' — ' . url()->current()) }}" target="_blank" rel="noopener"
                           class="w-9 h-9 rounded-lg bg-surface-100 hover:bg-green-500 hover:text-white flex items-center justify-center text-surface-500 transition-all">
                            <i class="fab fa-whatsapp text-sm"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
