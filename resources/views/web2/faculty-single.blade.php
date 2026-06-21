{{--
    ================================================================
    FACULTY DETAIL — PAX Higher Institute
    ================================================================
--}}
@extends('web2.layouts.master')

@section('title', $faculty->title . ' — ' . ($setting->title ?? 'PAXHI'))
@section('description', Str::limit(strip_tags($faculty->excerpt ?? $faculty->description), 160))

@section('content')

{{-- HERO BANNER --}}
<section class="relative min-h-[400px] lg:min-h-[450px] flex items-end overflow-hidden">
    @if($faculty->banner_image)
    <img src="{{ asset('uploads/faculties/'.$faculty->banner_image) }}" alt="{{ $faculty->title }}" class="absolute inset-0 w-full h-full object-cover">
    @elseif($faculty->featured_image)
    <img src="{{ asset('uploads/faculties/'.$faculty->featured_image) }}" alt="{{ $faculty->title }}" class="absolute inset-0 w-full h-full object-cover">
    @endif
    <div class="absolute inset-0 bg-gradient-to-t from-primary-900/95 via-primary-900/60 to-primary-900/30"></div>
    <div class="section-container relative z-10 pb-12 pt-32">
        <nav class="text-sm text-white/50 mb-5" aria-label="Breadcrumb" data-aos="fade-up">
            <a href="{{ url('/') }}" class="hover:text-accent-400 transition-colors">Home</a>
            <span class="mx-2">/</span>
            <a href="{{ url('/faculties') }}" class="hover:text-accent-400 transition-colors">Faculties</a>
            <span class="mx-2">/</span>
            <span class="text-white/80">{{ Str::limit($faculty->title, 50) }}</span>
        </nav>
        <h1 class="text-3xl md:text-4xl lg:text-5xl font-heading font-bold text-white mb-4 max-w-4xl leading-tight" data-aos="fade-up" data-aos-delay="100">
            {{ $faculty->title }}
        </h1>
        <div class="flex flex-wrap gap-4 mt-4 text-white/70 text-sm" data-aos="fade-up" data-aos-delay="150">
            <span class="flex items-center gap-2"><i class="fas fa-book text-accent-400"></i>{{ $faculty->programs->count() }} Program{{ $faculty->programs->count() != 1 ? 's' : '' }}</span>
            @if($faculty->email)
            <a href="mailto:{{ $faculty->email }}" class="flex items-center gap-2 hover:text-accent-400 transition-colors"><i class="fas fa-envelope text-accent-400"></i>{{ $faculty->email }}</a>
            @endif
        </div>
    </div>
</section>

{{-- MAIN CONTENT --}}
<section class="py-16 lg:py-20 bg-white">
    <div class="section-container">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10 lg:gap-14">
            {{-- Main --}}
            <div class="lg:col-span-2 space-y-10">
                {{-- Description --}}
                @if($faculty->description)
                <div data-aos="fade-up">
                    <h2 class="text-2xl font-heading font-bold text-surface-800 mb-5 flex items-center gap-3">
                        <span class="w-1 h-7 bg-primary-500 rounded-full"></span>About This Faculty
                    </h2>
                    <div class="prose prose-surface max-w-none text-surface-600 leading-relaxed [&>p]:mb-4">
                        {!! $faculty->description !!}
                    </div>
                </div>
                @endif

                {{-- Programs in this Faculty --}}
                @if($faculty->programs->count() > 0)
                <div data-aos="fade-up">
                    <h2 class="text-2xl font-heading font-bold text-surface-800 mb-5 flex items-center gap-3">
                        <span class="w-1 h-7 bg-accent-500 rounded-full"></span>Programs Offered
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach($faculty->programs as $program)
                        <a href="{{ url('/programs/'.$program->slug) }}"
                           class="bg-surface-50 hover:bg-white rounded-xl p-5 border border-surface-100 hover:shadow-md hover:border-primary-200 transition-all group">
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 rounded-lg bg-primary-100 flex items-center justify-center flex-shrink-0 group-hover:bg-accent-100 transition-colors">
                                    <i class="fas fa-book-open text-primary-500 group-hover:text-accent-500 transition-colors"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-surface-800 text-sm group-hover:text-primary-500 transition-colors leading-snug">{{ $program->title }}</h4>
                                    <div class="flex gap-3 mt-1.5 text-xs text-surface-400">
                                        @if($program->academic_level_name)
                                        <span>{{ $program->academic_level_name }}</span>
                                        @endif
                                        @if($program->duration)
                                        <span>{{ $program->duration }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </a>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                {{-- Dean Card --}}
                @if($faculty->dean_name)
                <div class="bg-white rounded-2xl border border-surface-100 shadow-sm overflow-hidden" data-aos="fade-left">
                    @if($faculty->dean_photo)
                    <div class="p-4 pb-0">
                        <img src="{{ asset('uploads/faculties/deans/'.$faculty->dean_photo) }}"
                             alt="{{ $faculty->dean_name }}"
                             class="w-full rounded-xl aspect-[4/3] object-cover" loading="lazy">
                    </div>
                    @endif
                    <div class="p-5 text-center">
                        <h3 class="font-heading font-bold text-surface-800">{{ $faculty->dean_name }}</h3>
                        <p class="text-sm text-accent-500 font-medium">Dean of Faculty</p>
                    </div>
                </div>
                @endif

                {{-- Faculty Info --}}
                <div class="bg-white rounded-2xl border border-surface-100 shadow-sm overflow-hidden" data-aos="fade-left" data-aos-delay="100">
                    <div class="bg-surface-50 px-6 py-4 border-b border-surface-100">
                        <h3 class="font-heading font-bold text-surface-800 text-base">Faculty Information</h3>
                    </div>
                    <div class="divide-y divide-surface-100">
                        @if($faculty->shortcode)
                        <div class="px-6 py-3 flex justify-between text-sm">
                            <span class="text-surface-500">Code</span>
                            <span class="font-semibold text-surface-800">{{ $faculty->shortcode }}</span>
                        </div>
                        @endif
                        <div class="px-6 py-3 flex justify-between text-sm">
                            <span class="text-surface-500">Programs</span>
                            <span class="font-semibold text-surface-800">{{ $faculty->programs->count() }}</span>
                        </div>
                        @if($faculty->email)
                        <div class="px-6 py-3 flex justify-between text-sm">
                            <span class="text-surface-500">Email</span>
                            <a href="mailto:{{ $faculty->email }}" class="font-semibold text-primary-500 hover:text-accent-500 transition-colors">{{ $faculty->email }}</a>
                        </div>
                        @endif
                        @if($faculty->phone)
                        <div class="px-6 py-3 flex justify-between text-sm">
                            <span class="text-surface-500">Phone</span>
                            <a href="tel:{{ $faculty->phone }}" class="font-semibold text-primary-500 hover:text-accent-500 transition-colors">{{ $faculty->phone }}</a>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Apply --}}
                <div class="bg-gradient-to-br from-primary-500 to-primary-700 rounded-2xl p-6 text-white" data-aos="fade-left" data-aos-delay="150">
                    <h3 class="font-heading font-bold text-lg mb-2 text-white">Join This Faculty</h3>
                    <p class="text-white/70 text-sm mb-5">Start your academic journey in {{ $faculty->title }}.</p>
                    <a href="{{ url('/application') }}" class="btn-accent w-full justify-center text-sm">Apply Now <i class="fas fa-arrow-right ml-2"></i></a>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
