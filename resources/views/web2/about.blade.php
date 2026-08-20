{{--
    ================================================================
    ABOUT US — {{ institution_name() }}
    ================================================================
    Sections:
    1. Page Hero
    2. About / Mission / Vision
    3. History Timeline
    4. Leadership Team
    5. Accreditations
    6. Map / Contact CTA
    ================================================================
--}}
@extends('web2.layouts.master')

@section('title', 'About Us — ' . (institution_code()))
@section('description', $about ? Str::limit(strip_tags($about->short_desc), 160) : 'Learn about ' . institution_name() . (site_subtitle() ? ', ' . site_subtitle() : '') . '.')

@section('content')

{{-- ================================================================
     PAGE HERO
     ================================================================ --}}
<section class="page-hero">
    <div class="section-container relative z-10 text-center">
        @if(site_subtitle() !== '')
        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/10 backdrop-blur border border-white/20 text-white/80 text-xs font-medium mb-5" data-aos="fade-down">
            <i class="fas fa-cross text-accent-400 text-[10px]"></i>
            {{ site_subtitle() }}
        </div>
        @endif
        <h1 class="text-4xl md:text-5xl lg:text-6xl font-heading font-bold text-white mb-4" data-aos="fade-up">About {{ institution_code() }}</h1>
        <p class="text-lg text-white/70 max-w-2xl mx-auto" data-aos="fade-up" data-aos-delay="100">Rooted in faith. Committed to excellence. Dedicated to transforming lives through Catholic higher education.</p>
        <nav class="mt-6 text-sm text-white/50" aria-label="Breadcrumb" data-aos="fade-up" data-aos-delay="150">
            <a href="{{ url('/') }}" class="hover:text-accent-400 transition-colors">Home</a>
            <span class="mx-2">/</span>
            <span class="text-white/80">About</span>
        </nav>
    </div>
</section>


{{-- ================================================================
     ABOUT CONTENT + MISSION / VISION
     ================================================================ --}}
@if($about)
<section class="py-20 lg:py-28 bg-white">
    <div class="section-container">
        {{-- Main About --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16 items-center mb-20 lg:mb-28">
            {{-- Image Column --}}
            <div class="lg:col-span-5 relative" data-aos="fade-right">
                <div class="relative">
                    @if($about->attach)
                    <img src="{{ asset('uploads/about-us/'.$about->attach) }}" alt="About {{ institution_name() }}" class="w-full rounded-2xl shadow-xl object-cover aspect-[3/4]">
                    @else
                    <div class="w-full aspect-[3/4] rounded-2xl bg-gradient-to-br from-primary-100 to-primary-200 flex items-center justify-center">
                        <i class="fas fa-university text-6xl text-primary-300"></i>
                    </div>
                    @endif
                    {{-- Floating Card --}}
                    <div class="absolute -bottom-6 -right-6 bg-white shadow-lg rounded-2xl p-5 border border-surface-100 max-w-[200px]" data-aos="zoom-in" data-aos-delay="300">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-10 h-10 rounded-full bg-accent-500 flex items-center justify-center">
                                <i class="fas fa-cross text-white text-sm"></i>
                            </div>
                            <div class="text-xs text-surface-400 uppercase tracking-wider font-medium">Catholic</div>
                        </div>
                        <div class="text-primary-500 font-heading font-bold text-sm">Faith, Knowledge & Service</div>
                    </div>
                </div>
                {{-- Decorative --}}
                <div class="absolute -top-6 -left-6 w-32 h-32 bg-accent-100/50 rounded-2xl -z-10"></div>
            </div>

            {{-- Text Column --}}
            <div class="lg:col-span-7" data-aos="fade-left">
                @if($about->label)
                <span class="section-label">{{ $about->label }}</span>
                @endif
                <h2 class="text-3xl md:text-4xl font-heading font-bold text-surface-900 mb-6 leading-tight">{{ $about->title }}</h2>
                @if($about->short_desc)
                <p class="text-lg text-surface-600 leading-relaxed mb-6 font-medium">{{ $about->short_desc }}</p>
                @endif
                <div class="prose prose-surface max-w-none text-surface-600 leading-relaxed [&>p]:mb-4">
                    {!! $about->description !!}
                </div>
                @if($about->video_id)
                <div class="mt-8">
                    <a href="https://www.youtube.com/watch?v={{ $about->video_id }}" target="_blank"
                       class="inline-flex items-center gap-3 text-primary-500 font-semibold hover:text-accent-500 transition-colors group">
                        <div class="w-12 h-12 rounded-full bg-primary-50 group-hover:bg-accent-50 flex items-center justify-center transition-colors">
                            <i class="fas fa-play text-sm ml-0.5"></i>
                        </div>
                        Watch Our Story
                    </a>
                </div>
                @endif
            </div>
        </div>

        {{-- Mission & Vision Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            {{-- Mission --}}
            @if($about->mission_title)
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary-500 to-primary-700 text-white p-8 lg:p-10 group" data-aos="fade-up">
                <div class="absolute top-0 right-0 w-40 h-40 bg-white/5 rounded-full -mr-10 -mt-10"></div>
                <div class="absolute bottom-0 left-0 w-32 h-32 bg-white/5 rounded-full -ml-8 -mb-8"></div>
                <div class="relative z-10">
                    <div class="w-14 h-14 rounded-xl bg-white/15 flex items-center justify-center mb-5 group-hover:bg-accent-500 transition-colors">
                        <i class="{{ $about->mission_icon ?? 'fas fa-bullseye' }} text-2xl text-accent-400 group-hover:text-white transition-colors"></i>
                    </div>
                    <h3 class="text-2xl font-heading font-bold mb-4 text-white">{{ $about->mission_title }}</h3>
                    <p class="text-white/80 leading-relaxed text-base">{{ $about->mission_desc }}</p>
                </div>
            </div>
            @endif

            {{-- Vision --}}
            @if($about->vision_title)
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-secondary-500 to-secondary-700 text-white p-8 lg:p-10 group" data-aos="fade-up" data-aos-delay="100">
                <div class="absolute top-0 right-0 w-40 h-40 bg-white/5 rounded-full -mr-10 -mt-10"></div>
                <div class="absolute bottom-0 left-0 w-32 h-32 bg-white/5 rounded-full -ml-8 -mb-8"></div>
                <div class="relative z-10">
                    <div class="w-14 h-14 rounded-xl bg-white/15 flex items-center justify-center mb-5 group-hover:bg-accent-500 transition-colors">
                        <i class="{{ $about->vision_icon ?? 'fas fa-eye' }} text-2xl text-accent-400 group-hover:text-white transition-colors"></i>
                    </div>
                    <h3 class="text-2xl font-heading font-bold mb-4 text-white">{{ $about->vision_title }}</h3>
                    <p class="text-white/80 leading-relaxed text-base">{{ $about->vision_desc }}</p>
                </div>
            </div>
            @endif
        </div>
    </div>
</section>
@endif


{{-- ================================================================
     HISTORY TIMELINE
     ================================================================ --}}
@if($timeline->count() > 0)
<section class="py-20 lg:py-24 bg-surface-50 overflow-hidden">
    <div class="section-container">
        <div class="section-header" data-aos="fade-up">
            <span class="section-label">Our Journey</span>
            <h2 class="section-title">A History of Faith & Learning</h2>
        </div>

        {{-- Desktop Timeline --}}
        <div class="relative hidden md:block">
            {{-- Center line --}}
            <div class="absolute left-1/2 top-0 bottom-0 w-0.5 bg-gradient-to-b from-primary-200 via-accent-300 to-primary-200 -translate-x-1/2"></div>

            @foreach($timeline as $i => $item)
            <div class="relative flex items-center mb-12 last:mb-0 {{ $i % 2 === 0 ? '' : 'flex-row-reverse' }}" data-aos="{{ $i % 2 === 0 ? 'fade-right' : 'fade-left' }}">
                {{-- Content --}}
                <div class="w-[calc(50%-2rem)] {{ $i % 2 === 0 ? 'text-right pr-8' : 'text-left pl-8' }}">
                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-surface-100 hover:shadow-md transition-shadow inline-block {{ $i % 2 === 0 ? 'ml-auto' : '' }}">
                        <span class="inline-block px-3 py-1 bg-accent-50 text-accent-600 rounded-lg text-sm font-bold mb-2">{{ $item->year }}</span>
                        <h4 class="font-heading font-bold text-surface-800 text-lg mb-2">{{ $item->title }}</h4>
                        <p class="text-surface-500 text-sm leading-relaxed">{{ $item->description }}</p>
                    </div>
                </div>
                {{-- Center dot --}}
                <div class="absolute left-1/2 -translate-x-1/2 w-5 h-5 rounded-full bg-accent-500 border-4 border-white shadow-md z-10"></div>
                {{-- Spacer --}}
                <div class="w-[calc(50%-2rem)]"></div>
            </div>
            @endforeach
        </div>

        {{-- Mobile Timeline --}}
        <div class="md:hidden relative pl-8">
            <div class="absolute left-3 top-0 bottom-0 w-0.5 bg-gradient-to-b from-primary-200 via-accent-300 to-primary-200"></div>
            @foreach($timeline as $item)
            <div class="relative mb-8 last:mb-0" data-aos="fade-up">
                <div class="absolute -left-5 w-4 h-4 rounded-full bg-accent-500 border-3 border-white shadow"></div>
                <div class="bg-white rounded-xl p-5 shadow-sm border border-surface-100">
                    <span class="inline-block px-2.5 py-0.5 bg-accent-50 text-accent-600 rounded text-xs font-bold mb-2">{{ $item->year }}</span>
                    <h4 class="font-heading font-bold text-surface-800 text-base mb-1">{{ $item->title }}</h4>
                    <p class="text-surface-500 text-sm">{{ $item->description }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif


{{-- ================================================================
     LEADERSHIP TEAM
     ================================================================ --}}
@if($leadership->count() > 0)
<section class="py-20 lg:py-24 bg-white">
    <div class="section-container">
        <div class="section-header" data-aos="fade-up">
            <span class="section-label">Leadership</span>
            <h2 class="section-title">Meet Our Team</h2>
            <p class="section-desc">Guided by faith and experience, our leadership team steers the mission of the Institute with dedication and vision.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 lg:gap-8">
            @foreach($leadership as $member)
            <div class="group text-center" data-aos="fade-up" data-aos-delay="{{ ($loop->index % 4) * 80 }}">
                <div class="relative mb-5 overflow-hidden rounded-2xl aspect-[3/4] bg-surface-100">
                    @if($member->photo)
                    <img src="{{ asset('uploads/leadership-team/'.$member->photo) }}"
                         alt="{{ $member->name }}"
                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                         loading="lazy">
                    @else
                    <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-primary-50 to-primary-100">
                        <i class="fas fa-user text-5xl text-primary-200"></i>
                    </div>
                    @endif
                    {{-- Hover overlay --}}
                    <div class="absolute inset-0 bg-gradient-to-t from-primary-900/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end justify-center pb-6">
                        <div class="flex gap-2">
                            @if($member->email)
                            <a href="mailto:{{ $member->email }}" class="w-9 h-9 rounded-full bg-white/20 backdrop-blur flex items-center justify-center text-white hover:bg-accent-500 transition-colors" title="Email">
                                <i class="fas fa-envelope text-sm"></i>
                            </a>
                            @endif
                            @if($member->phone)
                            <a href="tel:{{ $member->phone }}" class="w-9 h-9 rounded-full bg-white/20 backdrop-blur flex items-center justify-center text-white hover:bg-accent-500 transition-colors" title="Phone">
                                <i class="fas fa-phone text-sm"></i>
                            </a>
                            @endif
                        </div>
                    </div>
                </div>
                <h4 class="font-heading font-bold text-surface-800 text-base mb-1">{{ $member->name }}</h4>
                <p class="text-sm text-accent-500 font-medium">{{ $member->designation }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif


{{-- ================================================================
     ACCREDITATIONS & PARTNERS
     ================================================================ --}}
@if($accreditations->count() > 0)
<section class="py-16 lg:py-20 bg-surface-50">
    <div class="section-container">
        <div class="section-header" data-aos="fade-up">
            <span class="section-label">Recognition</span>
            <h2 class="section-title">Accreditations & Partners</h2>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-{{ min($accreditations->count(), 5) }} gap-6">
            @foreach($accreditations as $acc)
            <a href="{{ $acc->url ?? '#' }}" target="{{ $acc->url ? '_blank' : '_self' }}" rel="noopener"
               class="bg-white rounded-xl p-6 flex flex-col items-center justify-center text-center hover:shadow-md border border-surface-100 transition-all group" data-aos="zoom-in" data-aos-delay="{{ $loop->index * 60 }}">
                @if($acc->logo)
                <img src="{{ asset('uploads/accreditation/'.$acc->logo) }}" alt="{{ $acc->title }}" class="h-16 w-auto object-contain mb-3 grayscale group-hover:grayscale-0 transition-all" loading="lazy">
                @else
                <div class="w-16 h-16 rounded-full bg-primary-50 flex items-center justify-center mb-3">
                    <i class="fas fa-award text-2xl text-primary-400"></i>
                </div>
                @endif
                <h5 class="text-sm font-semibold text-surface-700">{{ $acc->title }}</h5>
                @if($acc->description)
                <p class="text-xs text-surface-400 mt-1 line-clamp-2">{{ Str::limit($acc->description, 60) }}</p>
                @endif
            </a>
            @endforeach
        </div>
    </div>
</section>
@endif


{{-- ================================================================
     CTA BAND
     ================================================================ --}}
<section class="py-16 lg:py-20 bg-primary-900 text-white relative overflow-hidden">
    <div class="absolute inset-0 opacity-5"><svg width="100%" height="100%"><defs><pattern id="about-cta-cross" x="0" y="0" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M20 8v24M8 20h24" stroke="white" stroke-width="1.5" fill="none"/></pattern></defs><rect width="100%" height="100%" fill="url(#about-cta-cross)"/></svg></div>
    <div class="section-container relative z-10 text-center" data-aos="fade-up">
        <h2 class="text-3xl md:text-4xl font-heading font-bold mb-4 text-white">Ready to Join Our Community?</h2>
        <p class="text-white/60 max-w-xl mx-auto mb-8">Take the first step towards a transformative education grounded in Catholic values and academic excellence.</p>
        <div class="flex flex-wrap justify-center gap-4">
            <a href="{{ url('/admissions') }}" class="btn-accent !px-8 !py-3.5">Admissions <i class="fas fa-arrow-right ml-2"></i></a>
            <a href="{{ url('/programs') }}" class="bg-white/10 backdrop-blur border border-white/20 text-white hover:bg-white/20 rounded-xl px-8 py-3.5 font-semibold transition-colors">Explore Programs</a>
        </div>
    </div>
</section>

@endsection
