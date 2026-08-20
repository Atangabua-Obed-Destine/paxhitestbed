{{--
    ================================================================
    FACULTIES LISTING — {{ institution_name() }}
    ================================================================
--}}
@extends('web2.layouts.master')

@section('title', 'Faculties — ' . (institution_code()))
@section('description', 'Explore the faculties at ' . institution_name() . ' — each committed to academic excellence and moral formation.')

@section('content')

{{-- PAGE HERO --}}
<section class="page-hero">
    <div class="section-container relative z-10 text-center">
        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/10 backdrop-blur border border-white/20 text-white/80 text-xs font-medium mb-5" data-aos="fade-down">
            <i class="fas fa-university text-accent-400"></i>
            {{ $faculties->count() }} Facult{{ $faculties->count() != 1 ? 'ies' : 'y' }}
        </div>
        <h1 class="text-4xl md:text-5xl lg:text-6xl font-heading font-bold text-white mb-4" data-aos="fade-up">Our Faculties</h1>
        <p class="text-lg text-white/70 max-w-2xl mx-auto" data-aos="fade-up" data-aos-delay="100">Each faculty brings together passionate educators and cutting-edge programs to shape future leaders.</p>
        <nav class="mt-6 text-sm text-white/50" aria-label="Breadcrumb" data-aos="fade-up" data-aos-delay="150">
            <a href="{{ url('/') }}" class="hover:text-accent-400 transition-colors">Home</a>
            <span class="mx-2">/</span>
            <span class="text-white/80">Faculties</span>
        </nav>
    </div>
</section>

{{-- FACULTIES GRID --}}
<section class="py-16 lg:py-24 bg-surface-50">
    <div class="section-container">
        @if($faculties->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            @foreach($faculties as $faculty)
            <a href="{{ url('/faculties/'.$faculty->slug) }}"
               class="group relative bg-white rounded-2xl shadow-sm border border-surface-100 overflow-hidden hover:shadow-xl hover:-translate-y-1 transition-all duration-300"
               data-aos="fade-up" data-aos-delay="{{ ($loop->index % 2) * 100 }}">

                {{-- Banner Image --}}
                <div class="relative h-52 overflow-hidden">
                    @if($faculty->featured_image)
                    <img src="{{ asset('uploads/faculties/'.$faculty->featured_image) }}"
                         alt="{{ $faculty->title }}"
                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
                         loading="lazy">
                    @else
                    <div class="w-full h-full bg-gradient-to-br from-primary-400 to-primary-600"></div>
                    @endif
                    <div class="absolute inset-0 bg-gradient-to-t from-primary-900/80 via-primary-900/20 to-transparent"></div>

                    {{-- Program count badge --}}
                    <div class="absolute top-4 right-4">
                        <span class="badge badge-accent text-xs flex items-center gap-1">
                            <i class="fas fa-book"></i>
                            {{ $faculty->programs_count ?? 0 }} Program{{ ($faculty->programs_count ?? 0) != 1 ? 's' : '' }}
                        </span>
                    </div>

                    {{-- Title on image --}}
                    <div class="absolute bottom-0 left-0 right-0 p-6">
                        <h3 class="text-xl lg:text-2xl font-heading font-bold text-white leading-tight">{{ $faculty->title }}</h3>
                    </div>
                </div>

                {{-- Content --}}
                <div class="p-6">
                    @if($faculty->excerpt)
                    <p class="text-surface-500 text-sm leading-relaxed mb-4 line-clamp-3">{{ $faculty->excerpt }}</p>
                    @elseif($faculty->description)
                    <p class="text-surface-500 text-sm leading-relaxed mb-4 line-clamp-3">{{ Str::limit(strip_tags($faculty->description), 150) }}</p>
                    @endif

                    {{-- Dean info --}}
                    @if($faculty->dean_name)
                    <div class="flex items-center gap-3 pt-4 border-t border-surface-100">
                        @if($faculty->dean_photo)
                        <img src="{{ asset('uploads/faculties/deans/'.$faculty->dean_photo) }}" alt="{{ $faculty->dean_name }}" class="w-10 h-10 rounded-full object-cover ring-2 ring-primary-100">
                        @else
                        <div class="w-10 h-10 rounded-full bg-primary-50 flex items-center justify-center"><i class="fas fa-user text-primary-300"></i></div>
                        @endif
                        <div>
                            <div class="text-sm font-semibold text-surface-700">{{ $faculty->dean_name }}</div>
                            <div class="text-xs text-surface-400">Dean of Faculty</div>
                        </div>
                        <i class="fas fa-arrow-right text-primary-400 ml-auto group-hover:translate-x-1 group-hover:text-accent-500 transition-all"></i>
                    </div>
                    @else
                    <div class="flex items-center justify-end">
                        <span class="text-sm font-semibold text-primary-500 group-hover:text-accent-500 transition-colors inline-flex items-center gap-1">
                            Explore <i class="fas fa-arrow-right text-xs group-hover:translate-x-1 transition-transform"></i>
                        </span>
                    </div>
                    @endif
                </div>
            </a>
            @endforeach
        </div>
        @else
        <div class="text-center py-20">
            <i class="fas fa-university text-5xl text-surface-200 mb-4"></i>
            <h3 class="text-xl font-heading font-bold text-surface-700 mb-2">No Faculties Available</h3>
            <p class="text-surface-400">Check back soon for updates.</p>
        </div>
        @endif
    </div>
</section>

@endsection
