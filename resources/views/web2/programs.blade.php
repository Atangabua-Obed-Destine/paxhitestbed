{{--
    ================================================================
    PROGRAMS LISTING — {{ institution_name() }}
    ================================================================
--}}
@extends('web2.layouts.master')

@section('title', 'Academic Programs — ' . (institution_code()))
@section('description', 'Explore our diverse range of accredited academic programs at ' . institution_name() . '.')

@section('content')

{{-- PAGE HERO --}}
<section class="page-hero">
    <div class="section-container relative z-10 text-center">
        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/10 backdrop-blur border border-white/20 text-white/80 text-xs font-medium mb-5" data-aos="fade-down">
            <i class="fas fa-graduation-cap text-accent-400"></i>
            {{ $programs->total() }} Program{{ $programs->total() != 1 ? 's' : '' }} Available
        </div>
        <h1 class="text-4xl md:text-5xl lg:text-6xl font-heading font-bold text-white mb-4" data-aos="fade-up">Academic Programs</h1>
        <p class="text-lg text-white/70 max-w-2xl mx-auto" data-aos="fade-up" data-aos-delay="100">Discover programs designed to equip you with professional skills, ethical values, and a global perspective.</p>
        <nav class="mt-6 text-sm text-white/50" aria-label="Breadcrumb" data-aos="fade-up" data-aos-delay="150">
            <a href="{{ url('/') }}" class="hover:text-accent-400 transition-colors">Home</a>
            <span class="mx-2">/</span>
            <span class="text-white/80">Programs</span>
        </nav>
    </div>
</section>


{{-- FILTER BAR --}}
<section class="bg-white border-b border-surface-100 sticky top-[60px] z-30 shadow-sm">
    <div class="section-container py-4">
        <form method="GET" action="{{ url('/programs') }}" class="flex flex-col sm:flex-row items-center gap-3">
            <div class="flex items-center gap-2 text-sm text-surface-500 flex-shrink-0">
                <i class="fas fa-filter text-primary-400"></i>Filter by Faculty:
            </div>
            <select name="faculty" onchange="this.form.submit()"
                    class="px-4 py-2.5 bg-surface-50 border border-surface-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary-300 min-w-[200px]">
                <option value="">All Faculties</option>
                @foreach($faculties as $faculty)
                <option value="{{ $faculty->id }}" {{ request('faculty') == $faculty->id ? 'selected' : '' }}>
                    {{ $faculty->title }}
                </option>
                @endforeach
            </select>
            @if(request('faculty'))
            <a href="{{ url('/programs') }}" class="text-sm text-accent-500 hover:text-accent-600 font-medium">
                <i class="fas fa-times mr-1"></i>Clear
            </a>
            @endif
            <div class="ml-auto text-sm text-surface-400 hidden sm:block">
                Showing {{ $programs->firstItem() ?? 0 }}–{{ $programs->lastItem() ?? 0 }} of {{ $programs->total() }}
            </div>
        </form>
    </div>
</section>


{{-- PROGRAMS GRID --}}
<section class="py-16 lg:py-20 bg-surface-50">
    <div class="section-container">
        @if($programs->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
            @foreach($programs as $program)
            <a href="{{ url('/programs/'.$program->slug) }}"
               class="card group !p-0 overflow-hidden hover:!shadow-[var(--shadow-card-hover)] hover:-translate-y-1 transition-all duration-300"
               data-aos="fade-up" data-aos-delay="{{ ($loop->index % 3) * 80 }}">
                {{-- Image --}}
                <div class="relative overflow-hidden aspect-[16/10]">
                    @if($program->featured_image)
                    <img src="{{ asset('uploads/programs/'.$program->featured_image) }}"
                         alt="{{ $program->title }}"
                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                         loading="lazy">
                    @else
                    <div class="w-full h-full bg-gradient-to-br from-primary-100 to-primary-200 flex items-center justify-center">
                        <i class="fas fa-book-open text-4xl text-primary-300"></i>
                    </div>
                    @endif
                    {{-- Top badges --}}
                    <div class="absolute top-3 left-3 flex gap-2">
                        @if($program->academic_level_name)
                        <span class="badge badge-primary text-[10px]">{{ $program->academic_level_name }}</span>
                        @endif
                        @if($program->duration)
                        <span class="badge text-[10px] bg-white/90 text-surface-700">{{ $program->duration }}</span>
                        @endif
                    </div>
                    {{-- Accent bar --}}
                    <div class="absolute bottom-0 left-0 w-full h-1 bg-gradient-to-r from-primary-500 to-accent-500 transform origin-left scale-x-0 group-hover:scale-x-100 transition-transform duration-500"></div>
                </div>
                {{-- Content --}}
                <div class="p-5">
                    @if($program->faculty)
                    <span class="text-xs font-medium text-accent-500 uppercase tracking-wider mb-1 block">{{ $program->faculty->title }}</span>
                    @endif
                    <h3 class="font-heading font-bold text-surface-800 text-base mb-2 group-hover:text-primary-500 transition-colors leading-snug line-clamp-2">
                        {{ $program->title }}
                    </h3>
                    @if($program->excerpt)
                    <p class="text-surface-500 text-sm line-clamp-2 leading-relaxed">{{ $program->excerpt }}</p>
                    @endif
                    <div class="mt-4 pt-3 border-t border-surface-100 flex items-center justify-between">
                        <div class="flex items-center gap-3 text-xs text-surface-400">
                            @if($program->credit)
                            <span><i class="fas fa-layer-group mr-1 text-primary-300"></i>{{ $program->credit }} Credits</span>
                            @endif
                        </div>
                        <span class="text-sm font-semibold text-primary-500 group-hover:text-accent-500 transition-colors inline-flex items-center gap-1">
                            Details <i class="fas fa-arrow-right text-xs group-hover:translate-x-1 transition-transform"></i>
                        </span>
                    </div>
                </div>
            </a>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($programs->hasPages())
        <div class="mt-12 flex justify-center">
            <div class="[&_.pagination]:flex [&_.pagination]:gap-2 [&_.page-item_.page-link]:px-4 [&_.page-item_.page-link]:py-2.5 [&_.page-item_.page-link]:rounded-xl [&_.page-item_.page-link]:text-sm [&_.page-item_.page-link]:border [&_.page-item_.page-link]:border-surface-200 [&_.page-item_.page-link]:bg-white [&_.page-item_.page-link]:text-surface-600 [&_.page-item.active_.page-link]:!bg-primary-500 [&_.page-item.active_.page-link]:!text-white [&_.page-item.active_.page-link]:!border-primary-500 [&_.page-item_.page-link:hover]:bg-primary-50 [&_.page-item_.page-link:hover]:text-primary-500 [&_.page-item_.page-link:hover]:border-primary-300 [&_.page-item.disabled_.page-link]:opacity-40 [&_.page-item.disabled_.page-link]:cursor-not-allowed">
                {{ $programs->withQueryString()->links() }}
            </div>
        </div>
        @endif

        @else
        <div class="text-center py-20">
            <div class="w-20 h-20 rounded-2xl bg-surface-100 flex items-center justify-center mx-auto mb-5">
                <i class="fas fa-search text-3xl text-surface-300"></i>
            </div>
            <h3 class="text-xl font-heading font-bold text-surface-700 mb-2">No Programs Found</h3>
            <p class="text-surface-400 mb-6">Try adjusting your filter to find what you're looking for.</p>
            <a href="{{ url('/programs') }}" class="btn-primary">View All Programs</a>
        </div>
        @endif
    </div>
</section>

@endsection
