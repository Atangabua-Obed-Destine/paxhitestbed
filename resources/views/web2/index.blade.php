{{--
    ================================================================
    HOMEPAGE — PAX Higher Institute
    ================================================================
    Sections:
    1. Hero Slider
    2. Features Bar
    3. Welcome Message
    4. About Preview + Why Choose Us
    5. Programs Spotlight
    6. Stats Counter
    7. News & Events
    8. Testimonials
    9. Featured Downloads
    10. Call to Action
    ================================================================
--}}
@extends('web2.layouts.master')

@section('title', ($setting->title ?? 'PAX Higher Institute') . ' — Home')
@section('description', 'PAX Higher Institute (PAXHI), a Catholic university in the Archdiocese of Bamenda, dedicated to academic excellence, moral formation, and holistic development.')
@section('keywords', 'PAXHI, PAX Higher Institute, Catholic University, Bamenda, Archdiocese, programs, admissions')

@section('social_meta_tags')
<meta property="og:title" content="{{ $setting->title ?? 'PAX Higher Institute' }}">
<meta property="og:description" content="A Catholic institution of higher learning in the Archdiocese of Bamenda.">
<meta property="og:image" content="{{ asset('uploads/setting/' . ($setting->logo_path ?? '')) }}">
<meta property="og:type" content="website">
<meta property="og:url" content="{{ url('/') }}">
@endsection

@section('content')

{{-- ================================================================
     1. HERO SLIDER
     ================================================================ --}}
<section class="relative h-[85vh] min-h-[600px] max-h-[900px] bg-primary-900 overflow-hidden">
    <div class="swiper hero-swiper absolute inset-0 w-full h-full">
        <div class="swiper-wrapper">
            @forelse($sliders as $slider)
            <div class="swiper-slide relative">
                {{-- Background Image --}}
                @if($slider->attach)
                <img src="{{ asset('uploads/slider/'.$slider->attach) }}"
                     alt="{{ $slider->title }}"
                     class="absolute inset-0 w-full h-full object-cover"
                     loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                >
                @endif
                {{-- Gradient Overlay --}}
                <div class="absolute inset-0 bg-gradient-to-r from-primary-900/90 via-primary-900/60 to-primary-900/30"></div>
                <div class="absolute inset-0 bg-gradient-to-t from-primary-900/80 via-transparent to-transparent"></div>

                {{-- Content --}}
                <div class="relative h-full flex items-center z-10">
                    <div class="section-container">
                        <div class="max-w-2xl">
                            {{-- Catholic accent --}}
                            <div class="inline-flex items-center gap-2 mb-5 px-4 py-1.5 rounded-full bg-white/10 backdrop-blur border border-white/20 text-white/80 text-xs font-medium">
                                <i class="fas fa-cross text-accent-400 text-[10px]"></i>
                                Archdiocese of Bamenda
                            </div>
                            <h1 class="text-4xl md:text-5xl lg:text-6xl font-heading font-bold text-white leading-tight mb-5 [text-shadow:_0_2px_20px_rgba(0,0,0,0.3)]">
                                {!! $slider->title !!}
                            </h1>
                            @if($slider->sub_title)
                            <p class="text-lg md:text-xl text-white/80 mb-8 leading-relaxed max-w-xl">
                                {!! $slider->sub_title !!}
                            </p>
                            @endif
                            @if($slider->button_text && $slider->button_link)
                            <div class="flex flex-wrap gap-3">
                                <a href="{{ $slider->button_link }}"
                                   class="btn-accent text-base !px-7 !py-3.5">
                                    {{ $slider->button_text }}
                                    <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                </a>
                                <a href="{{ url('/about') }}"
                                   class="bg-white/10 backdrop-blur-md border border-white/30 text-white hover:bg-white/20 rounded-xl px-7 py-3.5 text-base font-semibold transition-all inline-flex items-center gap-2">
                                    <i class="fas fa-play-circle"></i>Discover PAXHI
                                </a>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @empty
            {{-- Fallback if no sliders --}}
            <div class="swiper-slide relative bg-gradient-to-br from-primary-500 to-primary-800">
                <div class="relative h-full flex items-center z-10">
                    <div class="section-container text-center">
                        <div class="max-w-2xl mx-auto">
                            <h1 class="text-4xl md:text-5xl font-heading font-bold text-white mb-4">Welcome to {{ $setting->title ?? 'PAX Higher Institute' }}</h1>
                            <p class="text-lg text-white/80 mb-8">Academic Excellence Rooted in Catholic Values</p>
                            <a href="{{ url('/about') }}" class="btn-accent text-base">Learn More</a>
                        </div>
                    </div>
                </div>
            </div>
            @endforelse
        </div>
        {{-- Navigation --}}
        <div class="swiper-button-prev after:!content-[''] !text-white !w-12 !h-12 !rounded-full !bg-white/10 !backdrop-blur-sm hover:!bg-white/20 transition-colors hidden md:flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </div>
        <div class="swiper-button-next after:!content-[''] !text-white !w-12 !h-12 !rounded-full !bg-white/10 !backdrop-blur-sm hover:!bg-white/20 transition-colors hidden md:flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </div>
        <div class="swiper-pagination !bottom-8"></div>
    </div>

    {{-- Bottom wave --}}
    <div class="absolute bottom-0 left-0 right-0 z-20">
        <svg viewBox="0 0 1440 60" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-auto"><path d="M0 60V30C240 5 480 0 720 15C960 30 1200 45 1440 30V60H0Z" fill="white"/></svg>
    </div>
</section>


{{-- ================================================================
     2. FEATURES BAR
     ================================================================ --}}
@if($features->count() > 0)
<section class="relative z-10 -mt-8" data-aos="fade-up">
    <div class="section-container">
        <div class="grid grid-cols-1 md:grid-cols-{{ min($features->count(), 3) }} gap-4 lg:gap-6">
            @foreach($features->take(3) as $feature)
            <div class="card group flex items-start gap-4 !p-6 hover:!shadow-[var(--shadow-card-hover)] hover:-translate-y-1 transition-all duration-300 border-l-4 border-transparent hover:border-accent-500">
                <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-primary-50 to-primary-100 flex items-center justify-center flex-shrink-0 group-hover:from-accent-50 group-hover:to-accent-100 transition-colors">
                    @if($feature->icon)
                    <i class="{{ $feature->icon }} text-xl text-primary-500 group-hover:text-accent-500 transition-colors"></i>
                    @else
                    <i class="fas fa-graduation-cap text-xl text-primary-500 group-hover:text-accent-500 transition-colors"></i>
                    @endif
                </div>
                <div>
                    <h3 class="font-heading font-bold text-surface-800 text-base mb-1">{{ $feature->title }}</h3>
                    <p class="text-surface-500 text-sm leading-relaxed line-clamp-2">{{ $feature->description }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif


{{-- ================================================================
     3. WELCOME MESSAGE
     ================================================================ --}}
@if($welcomeMessage)
<section class="py-20 lg:py-28 bg-white" id="welcome">
    <div class="section-container">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
            {{-- Image Side --}}
            <div class="relative" data-aos="fade-right">
                <div class="relative rounded-2xl overflow-hidden shadow-xl">
                    @if($welcomeMessage->image)
                    <img src="{{ asset('uploads/welcome-message/'.$welcomeMessage->image) }}"
                         alt="{{ $welcomeMessage->title }}"
                         class="w-full h-auto object-cover aspect-[4/3]"
                    >
                    @else
                    <div class="w-full aspect-[4/3] bg-gradient-to-br from-primary-100 to-primary-200 flex items-center justify-center">
                        <i class="fas fa-university text-6xl text-primary-300"></i>
                    </div>
                    @endif
                </div>
                {{-- Decorative elements --}}
                <div class="absolute -bottom-4 -right-4 w-32 h-32 bg-accent-100 rounded-2xl -z-10"></div>
                <div class="absolute -top-4 -left-4 w-24 h-24 bg-primary-100 rounded-2xl -z-10"></div>
                {{-- Experience badge --}}
                <div class="absolute -bottom-6 left-8 bg-white shadow-lg rounded-xl px-5 py-3 flex items-center gap-3 border border-surface-100">
                    <div class="w-10 h-10 rounded-full bg-accent-500 flex items-center justify-center">
                        <i class="fas fa-cross text-white text-sm"></i>
                    </div>
                    <div>
                        <div class="text-xs text-surface-400 uppercase tracking-wider font-medium">Catholic Higher Education</div>
                        <div class="text-primary-500 font-heading font-bold text-sm">Faith & Knowledge</div>
                    </div>
                </div>
            </div>

            {{-- Text Side --}}
            <div data-aos="fade-left">
                <div class="section-label">Welcome to PAXHI</div>
                <h2 class="section-title !text-left">{{ $welcomeMessage->title }}</h2>
                @if($welcomeMessage->designation)
                <div class="inline-flex items-center gap-2 px-3 py-1 bg-accent-50 rounded-lg text-accent-600 text-sm font-medium mb-5">
                    <i class="fas fa-user-tie text-xs"></i>{{ $welcomeMessage->designation }}
                </div>
                @endif
                <div class="text-surface-600 leading-relaxed space-y-4 text-base">
                    {!! Str::limit(strip_tags($welcomeMessage->message), 600) !!}
                </div>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ url('/about') }}" class="btn-primary">
                        Read More <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                    <a href="{{ url('/admissions') }}" class="btn-outline">
                        Admissions Info
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
@endif


{{-- ================================================================
     4. ABOUT / WHY CHOOSE US
     ================================================================ --}}
<section class="py-20 lg:py-24 bg-surface-50 overflow-hidden">
    <div class="section-container">
        <div class="section-header" data-aos="fade-up">
            <span class="section-label">Why Choose PAXHI</span>
            <h2 class="section-title">An Education that Transforms</h2>
            <p class="section-desc">We combine academic rigor with Catholic moral formation, preparing graduates to serve with competence and character.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
            @php
                $whyCards = [
                    ['icon' => 'fas fa-book-open', 'title' => 'Academic Excellence', 'desc' => 'Rigorous curricula designed to meet international standards, with qualified faculty dedicated to student success.', 'color' => 'primary'],
                    ['icon' => 'fas fa-cross', 'title' => 'Catholic Foundation', 'desc' => 'Rooted in the teachings of the Church and the values of the Archdiocese of Bamenda, fostering faith alongside learning.', 'color' => 'accent'],
                    ['icon' => 'fas fa-users', 'title' => 'Holistic Formation', 'desc' => 'Nurturing intellectual, spiritual, moral, and social growth for well-rounded graduates.', 'color' => 'secondary'],
                    ['icon' => 'fas fa-flask', 'title' => 'Research & Innovation', 'desc' => 'Engaging students in research projects that address real-world challenges in the community and beyond.', 'color' => 'primary'],
                    ['icon' => 'fas fa-hands-helping', 'title' => 'Community Service', 'desc' => 'Building a culture of service that extends learning beyond the classroom into the lives of others.', 'color' => 'accent'],
                    ['icon' => 'fas fa-globe-africa', 'title' => 'Global Perspective', 'desc' => 'Preparing students for a globalized world while staying grounded in African identity and heritage.', 'color' => 'secondary'],
                ];
            @endphp

            @foreach($whyCards as $i => $card)
            <div class="card group hover:!shadow-[var(--shadow-card-hover)] hover:-translate-y-1 transition-all duration-300 !p-7"
                 data-aos="fade-up" data-aos-delay="{{ $i * 80 }}">
                <div class="w-12 h-12 rounded-xl bg-{{ $card['color'] }}-50 flex items-center justify-center mb-4 group-hover:bg-{{ $card['color'] }}-100 transition-colors">
                    <i class="{{ $card['icon'] }} text-lg text-{{ $card['color'] }}-500"></i>
                </div>
                <h3 class="font-heading font-bold text-surface-800 text-lg mb-2">{{ $card['title'] }}</h3>
                <p class="text-surface-500 text-sm leading-relaxed">{{ $card['desc'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>


{{-- ================================================================
     5. PROGRAMS SPOTLIGHT
     ================================================================ --}}
@php
    $spotlightPrograms = \App\Models\Program::where('status', 1)->orderBy('id', 'asc')->take(6)->get();
    $faculties = \App\Models\Faculty::where('status', 1)->withCount('programs')->orderBy('id', 'asc')->get();
@endphp

@if($spotlightPrograms->count() > 0 || $faculties->count() > 0)
<section class="py-20 lg:py-24 bg-white">
    <div class="section-container">
        <div class="section-header" data-aos="fade-up">
            <span class="section-label">Academic Programs</span>
            <h2 class="section-title">Shape Your Future</h2>
            <p class="section-desc">Explore our diverse range of programs across multiple faculties, designed to equip you for professional excellence.</p>
        </div>

        {{-- Faculties Tabs --}}
        @if($faculties->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-{{ min($faculties->count(), 3) }} gap-6 mb-10" data-aos="fade-up">
            @foreach($faculties->take(3) as $faculty)
            <a href="{{ url('/faculties/'.$faculty->slug) }}"
               class="card group !p-6 hover:!shadow-[var(--shadow-card-hover)] hover:-translate-y-1 transition-all duration-300 relative overflow-hidden">
                {{-- Accent bar --}}
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-primary-500 to-accent-500 transform origin-left scale-x-0 group-hover:scale-x-100 transition-transform duration-500"></div>
                <div class="flex items-start justify-between mb-3">
                    <div class="w-11 h-11 rounded-lg bg-primary-50 flex items-center justify-center group-hover:bg-primary-100 transition-colors">
                        <i class="fas fa-university text-primary-500"></i>
                    </div>
                    <span class="text-xs font-semibold text-accent-500 bg-accent-50 px-2 py-1 rounded-md">
                        {{ $faculty->programs_count ?? 0 }} Program{{ ($faculty->programs_count ?? 0) != 1 ? 's' : '' }}
                    </span>
                </div>
                <h3 class="font-heading font-bold text-surface-800 text-base mb-1 group-hover:text-primary-500 transition-colors">{{ $faculty->title }}</h3>
                <p class="text-surface-500 text-sm line-clamp-2">{{ Str::limit(strip_tags($faculty->description ?: $faculty->excerpt ?: 'Explore programs offered under '.$faculty->title.'.'), 100) }}</p>
            </a>
            @endforeach
        </div>
        @endif

        <div class="text-center" data-aos="fade-up">
            <a href="{{ url('/programs') }}" class="btn-primary">
                View All Programs <i class="fas fa-arrow-right ml-2"></i>
            </a>
        </div>
    </div>
</section>
@endif


{{-- ================================================================
     6. STATS COUNTER
     ================================================================ --}}
<section class="relative py-20 lg:py-24 bg-primary-900 text-white overflow-hidden">
    {{-- Cross pattern --}}
    <div class="absolute inset-0 opacity-5">
        <svg width="100%" height="100%"><defs><pattern id="stats-cross" x="0" y="0" width="50" height="50" patternUnits="userSpaceOnUse"><path d="M25 10v30M10 25h30" stroke="white" stroke-width="1" fill="none"/></pattern></defs><rect width="100%" height="100%" fill="url(#stats-cross)"/></svg>
    </div>

    <div class="section-container relative z-10">
        <div class="text-center mb-14" data-aos="fade-up">
            <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/10 text-accent-400 text-xs font-medium uppercase tracking-wider mb-4">
                <i class="fas fa-chart-line"></i>PAXHI at a Glance
            </span>
            <h2 class="text-3xl md:text-4xl font-heading font-bold text-white">Numbers That Speak</h2>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-8 lg:gap-10">
            @php
                $stats = [
                    ['target' => \App\Models\Student::count() ?: 1200, 'label' => 'Students', 'icon' => 'fas fa-user-graduate', 'suffix' => '+'],
                    ['target' => \App\Models\Program::where('status', 1)->count() ?: 25, 'label' => 'Programs', 'icon' => 'fas fa-book', 'suffix' => '+'],
                    ['target' => \App\Models\StaffAssignment::distinct('user_id')->count('user_id') ?: 100, 'label' => 'Faculty & Staff', 'icon' => 'fas fa-chalkboard-teacher', 'suffix' => '+'],
                    ['target' => \App\Models\Faculty::where('status', 1)->count() ?: 5, 'label' => 'Faculties', 'icon' => 'fas fa-university', 'suffix' => ''],
                ];
            @endphp

            @foreach($stats as $stat)
            <div class="text-center" data-aos="zoom-in" data-aos-delay="{{ $loop->index * 100 }}">
                <div class="w-16 h-16 rounded-2xl bg-white/10 flex items-center justify-center mx-auto mb-4">
                    <i class="{{ $stat['icon'] }} text-2xl text-accent-400"></i>
                </div>
                <div class="flex items-center justify-center gap-1">
                    <span class="text-4xl lg:text-5xl font-heading font-bold text-white"
                          x-data="counter({{ $stat['target'] }}, 2000)"
                          x-text="current">0</span>
                    @if($stat['suffix'])
                    <span class="text-2xl lg:text-3xl font-bold text-accent-400">{{ $stat['suffix'] }}</span>
                    @endif
                </div>
                <div class="text-white/60 text-sm mt-2 font-medium">{{ $stat['label'] }}</div>
            </div>
            @endforeach
        </div>
    </div>
</section>


{{-- ================================================================
     7. NEWS & EVENTS
     ================================================================ --}}
@if($newses->count() > 0)
<section class="py-20 lg:py-24 bg-white">
    <div class="section-container">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-12" data-aos="fade-up">
            <div>
                <span class="section-label">Latest Updates</span>
                <h2 class="section-title !text-left !mb-0">News & Events</h2>
            </div>
            <a href="{{ url('/news') }}" class="btn-outline text-sm self-start md:self-auto">
                View All News <i class="fas fa-arrow-right ml-2"></i>
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
            @foreach($newses->take(3) as $news)
            <article class="card group !p-0 overflow-hidden hover:!shadow-[var(--shadow-card-hover)] hover:-translate-y-1 transition-all duration-300"
                     data-aos="fade-up" data-aos-delay="{{ $loop->index * 100 }}">
                {{-- Image --}}
                <div class="relative overflow-hidden aspect-[16/10]">
                    @if($news->attach)
                    <img src="{{ asset('uploads/news/'.$news->attach) }}"
                         alt="{{ $news->title }}"
                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                         loading="lazy"
                    >
                    @else
                    <div class="w-full h-full bg-gradient-to-br from-primary-100 to-primary-200 flex items-center justify-center">
                        <i class="fas fa-newspaper text-4xl text-primary-300"></i>
                    </div>
                    @endif
                    <div class="absolute top-3 left-3">
                        <span class="badge badge-primary text-[11px]">
                            <i class="far fa-calendar-alt mr-1"></i>
                            {{ \Carbon\Carbon::parse($news->date)->format('M d, Y') }}
                        </span>
                    </div>
                </div>
                {{-- Content --}}
                <div class="p-5">
                    <h3 class="font-heading font-bold text-surface-800 text-base mb-2 line-clamp-2 group-hover:text-primary-500 transition-colors leading-snug">
                        <a href="{{ route('news.single', ['id' => $news->id, 'slug' => $news->slug]) }}">{{ $news->title }}</a>
                    </h3>
                    <p class="text-surface-500 text-sm leading-relaxed line-clamp-3 mb-4">
                        {{ Str::limit(strip_tags($news->description), 120) }}
                    </p>
                    <a href="{{ route('news.single', ['id' => $news->id, 'slug' => $news->slug]) }}"
                       class="text-sm font-semibold text-primary-500 hover:text-accent-500 inline-flex items-center gap-1 group/link transition-colors">
                        Read More
                        <svg class="w-4 h-4 group-hover/link:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </a>
                </div>
            </article>
            @endforeach
        </div>
    </div>
</section>
@endif


{{-- ================================================================
     8. TESTIMONIALS
     ================================================================ --}}
@if($testimonials->count() > 0)
<section class="py-20 lg:py-24 bg-surface-50 overflow-hidden">
    <div class="section-container">
        <div class="section-header" data-aos="fade-up">
            <span class="section-label">Testimonials</span>
            <h2 class="section-title">Voices of Our Community</h2>
            <p class="section-desc">Hear from students, alumni, and faculty about their experience at {{ $setting->title ?? 'PAX Higher Institute' }}.</p>
        </div>

        <div class="swiper testimonial-swiper" data-aos="fade-up">
            <div class="swiper-wrapper pb-12">
                @foreach($testimonials as $testimonial)
                <div class="swiper-slide">
                    <div class="bg-white rounded-2xl p-7 shadow-sm border border-surface-100 h-full flex flex-col">
                        {{-- Quote icon --}}
                        <div class="w-10 h-10 rounded-xl bg-accent-50 flex items-center justify-center mb-4 flex-shrink-0">
                            <i class="fas fa-quote-left text-accent-500"></i>
                        </div>
                        {{-- Rating --}}
                        @if($testimonial->rating)
                        <div class="flex gap-0.5 mb-3">
                            @for($i = 1; $i <= 5; $i++)
                            <i class="fas fa-star text-sm {{ $i <= $testimonial->rating ? 'text-accent-400' : 'text-surface-200' }}"></i>
                            @endfor
                        </div>
                        @endif
                        {{-- Text --}}
                        <p class="text-surface-600 text-sm leading-relaxed flex-grow mb-5">
                            "{{ Str::limit(strip_tags($testimonial->description), 200) }}"
                        </p>
                        {{-- Author --}}
                        <div class="flex items-center gap-3 pt-4 border-t border-surface-100">
                            @if($testimonial->attach)
                            <img src="{{ asset('uploads/testimonial/'.$testimonial->attach) }}"
                                 alt="{{ $testimonial->name }}"
                                 class="w-11 h-11 rounded-full object-cover ring-2 ring-primary-100"
                                 loading="lazy"
                            >
                            @else
                            <div class="w-11 h-11 rounded-full bg-primary-100 flex items-center justify-center">
                                <i class="fas fa-user text-primary-400"></i>
                            </div>
                            @endif
                            <div>
                                <div class="font-semibold text-surface-800 text-sm">{{ $testimonial->name }}</div>
                                @if($testimonial->designation)
                                <div class="text-xs text-surface-400">{{ $testimonial->designation }}</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            <div class="swiper-pagination"></div>
        </div>
    </div>
</section>
@endif


{{-- ================================================================
     9. FEATURED DOWNLOADS
     ================================================================ --}}
@if(isset($featured_resources) && $featured_resources->count() > 0)
<section class="py-16 lg:py-20 bg-white">
    <div class="section-container">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-10" data-aos="fade-up">
            <div>
                <span class="section-label">Resources</span>
                <h2 class="section-title !text-left !mb-0">Essential Documents</h2>
            </div>
            <a href="{{ url('/downloads') }}" class="btn-outline text-sm self-start md:self-auto">
                All Resources <i class="fas fa-arrow-right ml-2"></i>
            </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5" data-aos="fade-up">
            @foreach($featured_resources as $resource)
            <a href="{{ $resource->file_url ?? asset('uploads/resources/'.$resource->file_path) }}"
               target="_blank"
               class="card group !p-5 hover:!shadow-[var(--shadow-card-hover)] hover:-translate-y-1 transition-all duration-300 flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl bg-red-50 flex items-center justify-center flex-shrink-0 group-hover:bg-red-100 transition-colors">
                    <i class="{{ $resource->file_icon ?? 'fas fa-file-pdf' }} text-lg text-red-500"></i>
                </div>
                <div class="min-w-0 flex-grow">
                    <h4 class="text-sm font-semibold text-surface-800 line-clamp-2 group-hover:text-primary-500 transition-colors">{{ $resource->title }}</h4>
                    @if($resource->formatted_file_size)
                    <span class="text-xs text-surface-400 mt-1 block">{{ $resource->formatted_file_size }}</span>
                    @endif
                </div>
                <i class="fas fa-download text-surface-300 group-hover:text-accent-500 transition-colors mt-1"></i>
            </a>
            @endforeach
        </div>
    </div>
</section>
@endif


{{-- ================================================================
     10. CALL TO ACTION
     ================================================================ --}}
@if($callToAction)
<section class="relative py-24 lg:py-32 overflow-hidden">
    {{-- Background --}}
    @if($callToAction->bg_image)
    <img src="{{ asset('uploads/call-to-action/'.$callToAction->bg_image) }}"
         alt="" class="absolute inset-0 w-full h-full object-cover" loading="lazy">
    @endif
    <div class="absolute inset-0 bg-gradient-to-r from-primary-900/90 via-primary-900/80 to-primary-800/90"></div>

    {{-- Cross pattern --}}
    <div class="absolute inset-0 opacity-5">
        <svg width="100%" height="100%"><defs><pattern id="cta-cross" x="0" y="0" width="60" height="60" patternUnits="userSpaceOnUse"><path d="M30 10v40M10 30h40" stroke="white" stroke-width="1.5" fill="none"/></pattern></defs><rect width="100%" height="100%" fill="url(#cta-cross)"/></svg>
    </div>

    <div class="section-container relative z-10">
        <div class="max-w-3xl mx-auto text-center" data-aos="zoom-in">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/10 backdrop-blur text-accent-400 text-xs font-medium uppercase tracking-wider mb-6">
                <i class="fas fa-cross text-[10px]"></i>Join Our Community
            </div>
            <h2 class="text-3xl md:text-4xl lg:text-5xl font-heading font-bold text-white mb-5 leading-tight">
                {{ $callToAction->title }}
            </h2>
            @if($callToAction->sub_title)
            <p class="text-lg text-white/70 mb-10 max-w-2xl mx-auto leading-relaxed">
                {{ $callToAction->sub_title }}
            </p>
            @endif
            <div class="flex flex-wrap justify-center gap-4">
                @if($callToAction->button_text && $callToAction->button_link)
                <a href="{{ $callToAction->button_link }}" class="btn-accent text-base !px-8 !py-4">
                    {{ $callToAction->button_text }}
                    <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </a>
                @endif
                @if($callToAction->video_id)
                <button onclick="window.open('https://www.youtube.com/watch?v={{ $callToAction->video_id }}', '_blank')"
                        class="bg-white/15 backdrop-blur border border-white/30 text-white hover:bg-white/25 rounded-xl px-8 py-4 text-base font-semibold transition-all inline-flex items-center gap-3 cursor-pointer">
                    <i class="fas fa-play-circle text-accent-400 text-lg"></i>Watch Video
                </button>
                @endif
            </div>
        </div>
    </div>
</section>
@endif


{{-- ================================================================
     VIRTUAL CAMPUS TOUR PLACEHOLDER
     ================================================================ --}}
<section class="py-16 lg:py-20 bg-surface-50">
    <div class="section-container">
        <div class="bg-gradient-to-br from-primary-50 to-surface-100 rounded-2xl p-10 lg:p-14 flex flex-col lg:flex-row items-center gap-8 border border-surface-200"
             data-aos="fade-up">
            <div class="flex-shrink-0">
                <div class="w-20 h-20 rounded-2xl bg-white shadow-md flex items-center justify-center">
                    <i class="fas fa-vr-cardboard text-3xl text-primary-500"></i>
                </div>
            </div>
            <div class="flex-grow text-center lg:text-left">
                <h3 class="text-xl md:text-2xl font-heading font-bold text-surface-800 mb-2">Virtual Campus Tour</h3>
                <p class="text-surface-500 max-w-xl">Explore our beautiful campus from anywhere in the world. Take a virtual walk through our lecture halls, chapel, library, and student facilities.</p>
            </div>
            <a href="{{ url('/campus-life') }}" class="btn-primary flex-shrink-0">
                Explore Campus <i class="fas fa-arrow-right ml-2"></i>
            </a>
        </div>
    </div>
</section>

@endsection


@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Hero Slider
    if (document.querySelector('.hero-swiper')) {
        new Swiper('.hero-swiper', {
            modules: [
                window.SwiperModules.Navigation,
                window.SwiperModules.Pagination,
                window.SwiperModules.Autoplay,
                window.SwiperModules.EffectFade
            ],
            effect: 'fade',
            fadeEffect: { crossFade: true },
            speed: 1000,
            autoplay: {
                delay: 6000,
                disableOnInteraction: false,
                pauseOnMouseEnter: true,
            },
            loop: true,
            pagination: {
                el: '.hero-swiper .swiper-pagination',
                clickable: true,
            },
            navigation: {
                nextEl: '.hero-swiper .swiper-button-next',
                prevEl: '.hero-swiper .swiper-button-prev',
            },
        });
    }

    // Testimonials Slider
    if (document.querySelector('.testimonial-swiper')) {
        new Swiper('.testimonial-swiper', {
            modules: [
                window.SwiperModules.Pagination,
                window.SwiperModules.Autoplay,
            ],
            slidesPerView: 1,
            spaceBetween: 24,
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
            },
            pagination: {
                el: '.testimonial-swiper .swiper-pagination',
                clickable: true,
            },
            breakpoints: {
                640: { slidesPerView: 2 },
                1024: { slidesPerView: 3 },
            },
        });
    }
});
</script>
@endsection
