{{--
    ================================================================
    FOOTER — PAX Higher Institute
    ================================================================
    Structure:
    1. Pre-footer CTA band
    2. Main footer (4-column grid)
    3. Bottom bar (copyright + links)
    ================================================================
--}}
@php
    $footerResources = \App\Models\Web\Resource::where('status', 1)->orderBy('sort_order', 'asc')->take(4)->get();
    $footerNews = \App\Models\Web\News::where('status', 1)->latest()->take(3)->get();
@endphp

{{-- ================================================================
     PRE-FOOTER CTA
     ================================================================ --}}
<section class="bg-gradient-to-r from-primary-500 via-primary-600 to-primary-700 text-white overflow-hidden relative">
    {{-- Cross pattern --}}
    <div class="absolute inset-0 opacity-5">
        <svg width="100%" height="100%"><defs><pattern id="footer-cross" x="0" y="0" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M20 8v24M8 20h24" stroke="white" stroke-width="1.5" fill="none"/></pattern></defs><rect width="100%" height="100%" fill="url(#footer-cross)"/></svg>
    </div>

    <div class="section-container py-12 relative z-10">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="text-center md:text-left">
                <h3 class="text-xl md:text-2xl font-heading font-bold mb-1 text-white">Begin Your Journey at PAXHI</h3>
                <p class="text-white/70 text-sm md:text-base">Discover academic excellence rooted in Catholic values and tradition.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ url('/admissions') }}" class="bg-accent-500 text-white hover:bg-accent-600 rounded-xl px-6 py-3 text-sm font-semibold transition-colors inline-flex items-center gap-2">
                    <i class="fas fa-book-open"></i>Admissions Info
                </a>
                <a href="{{ url('/application') }}" class="bg-white/15 backdrop-blur border border-white/30 text-white hover:bg-white/25 rounded-xl px-6 py-3 text-sm font-semibold transition-colors inline-flex items-center gap-2">
                    <i class="fas fa-paper-plane"></i>Apply Now
                </a>
            </div>
        </div>
    </div>
</section>

{{-- ================================================================
     MAIN FOOTER
     ================================================================ --}}
<footer class="bg-primary-900 text-white/70 relative overflow-hidden" role="contentinfo">
    {{-- Decorative arch --}}
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[600px] h-[300px] rounded-b-full bg-primary-800/30 -mt-48 pointer-events-none"></div>

    <div class="section-container pt-16 pb-10 relative z-10">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10 lg:gap-8">

            {{-- Column 1: About --}}
            <div class="lg:col-span-1">
                <div class="flex items-center gap-3 mb-5">
                    @if($setting->logo_path && file_exists(public_path('uploads/setting/'.$setting->logo_path)))
                    <img src="{{ asset('uploads/setting/'.$setting->logo_path) }}" alt="{{ $setting->title }}" class="h-12">
                    @endif
                    <div>
                        <div class="text-white font-heading font-bold text-sm">PAX Higher Institute</div>
                        <div class="text-[10px] text-white/40 uppercase tracking-wider">Archdiocese of Bamenda</div>
                    </div>
                </div>
                <p class="text-sm leading-relaxed mb-6">
                    A Catholic institution of higher learning dedicated to academic excellence, moral formation,
                    and the holistic development of students in the service of Church and society.
                </p>

                {{-- Social --}}
                @if($socialSetting)
                <div class="flex items-center gap-2">
                    @foreach([
                        'facebook' => 'fab fa-facebook-f',
                        'instagram' => 'fab fa-instagram',
                        'twitter' => 'fab fa-x-twitter',
                        'youtube' => 'fab fa-youtube',
                        'linkedin' => 'fab fa-linkedin-in',
                        'tiktok' => 'fab fa-tiktok',
                    ] as $platform => $icon)
                        @if($socialSetting->$platform)
                        <a href="{{ $socialSetting->$platform }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($platform) }}"
                           class="w-9 h-9 rounded-lg bg-white/10 hover:bg-accent-500 flex items-center justify-center text-sm text-white/70 hover:text-white transition-all">
                            <i class="{{ $icon }}"></i>
                        </a>
                        @endif
                    @endforeach
                </div>
                @endif
            </div>

            {{-- Column 2: Quick Links --}}
            <div>
                <h4 class="text-white font-heading font-semibold text-base mb-5 flex items-center gap-2">
                    <span class="w-6 h-0.5 bg-accent-500 inline-block"></span>Quick Links
                </h4>
                <ul class="space-y-2.5">
                    @foreach([
                        'About Us' => '/about',
                        'Academic Programs' => '/programs',
                        'Faculties' => '/faculties',
                        'Admissions' => '/admissions',
                        'Research & Projects' => '/projects',
                        'Campus Life' => '/campus-life',
                        'News & Events' => '/news',
                        'FAQs' => '/faq',
                    ] as $label => $path)
                    <li>
                        <a href="{{ url($path) }}" class="text-sm hover:text-accent-400 hover:pl-1 transition-all inline-flex items-center gap-2 group">
                            <i class="fas fa-chevron-right text-[8px] text-accent-500/70 group-hover:text-accent-400 transition-colors"></i>
                            {{ $label }}
                        </a>
                    </li>
                    @endforeach
                </ul>
            </div>

            {{-- Column 3: Latest News --}}
            <div>
                <h4 class="text-white font-heading font-semibold text-base mb-5 flex items-center gap-2">
                    <span class="w-6 h-0.5 bg-accent-500 inline-block"></span>Latest News
                </h4>
                <div class="space-y-4">
                    @forelse($footerNews as $newsItem)
                    <a href="{{ route('news.single', ['id' => $newsItem->id, 'slug' => $newsItem->slug]) }}" class="group flex gap-3">
                        @if($newsItem->attach)
                        <img src="{{ asset('uploads/news/'.$newsItem->attach) }}" alt="" class="w-16 h-12 rounded-lg object-cover flex-shrink-0 ring-1 ring-white/10">
                        @else
                        <div class="w-16 h-12 rounded-lg bg-white/10 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-newspaper text-white/30"></i>
                        </div>
                        @endif
                        <div class="min-w-0">
                            <h5 class="text-sm text-white/80 group-hover:text-accent-400 transition-colors line-clamp-2 leading-snug">
                                {{ Str::limit($newsItem->title, 50) }}
                            </h5>
                            <span class="text-[11px] text-white/40 mt-1 block">
                                {{ $newsItem->created_at->format('M d, Y') }}
                            </span>
                        </div>
                    </a>
                    @empty
                    <p class="text-sm text-white/40">No news available.</p>
                    @endforelse
                </div>
            </div>

            {{-- Column 4: Contact / Resources --}}
            <div>
                <h4 class="text-white font-heading font-semibold text-base mb-5 flex items-center gap-2">
                    <span class="w-6 h-0.5 bg-accent-500 inline-block"></span>Contact Us
                </h4>
                <ul class="space-y-3 text-sm">
                    @if($topbarSetting && $topbarSetting->address)
                    <li class="flex gap-3">
                        <i class="fas fa-map-marker-alt text-accent-500 mt-0.5 flex-shrink-0"></i>
                        <span>{{ $topbarSetting->address }}</span>
                    </li>
                    @endif
                    @if($topbarSetting && $topbarSetting->phone)
                    <li class="flex gap-3">
                        <i class="fas fa-phone-alt text-accent-500 mt-0.5 flex-shrink-0"></i>
                        <a href="tel:{{ $topbarSetting->phone }}" class="hover:text-accent-400 transition-colors">{{ $topbarSetting->phone }}</a>
                    </li>
                    @endif
                    @if($topbarSetting && $topbarSetting->email)
                    <li class="flex gap-3">
                        <i class="fas fa-envelope text-accent-500 mt-0.5 flex-shrink-0"></i>
                        <a href="mailto:{{ $topbarSetting->email }}" class="hover:text-accent-400 transition-colors">{{ $topbarSetting->email }}</a>
                    </li>
                    @endif
                    @if($topbarSetting && $topbarSetting->working_hour)
                    <li class="flex gap-3">
                        <i class="fas fa-clock text-accent-500 mt-0.5 flex-shrink-0"></i>
                        <span>{{ $topbarSetting->working_hour }}</span>
                    </li>
                    @endif
                </ul>

                {{-- Quick Downloads --}}
                @if($footerResources->count() > 0)
                <h4 class="text-white font-heading font-semibold text-base mt-6 mb-3 flex items-center gap-2">
                    <span class="w-6 h-0.5 bg-accent-500 inline-block"></span>Downloads
                </h4>
                <ul class="space-y-2">
                    @foreach($footerResources as $resource)
                    <li>
                        <a href="{{ asset('uploads/resources/'.$resource->file) }}" target="_blank"
                           class="text-sm hover:text-accent-400 transition-colors inline-flex items-center gap-2">
                            <i class="fas fa-file-pdf text-accent-500/70"></i>
                            {{ Str::limit($resource->title, 30) }}
                        </a>
                    </li>
                    @endforeach
                </ul>
                @endif
            </div>
        </div>
    </div>

    {{-- ================================================================
         BOTTOM BAR
         ================================================================ --}}
    <div class="border-t border-white/10">
        <div class="section-container py-5">
            <div class="flex flex-col md:flex-row items-center justify-between gap-3 text-[12px] text-white/40">
                <div class="flex items-center gap-1.5">
                    <i class="fas fa-cross text-accent-500/60 text-[10px]"></i>
                    <span>&copy; {{ date('Y') }} {{ $setting->title ?? 'PAX Higher Institute' }}. All rights reserved.</span>
                </div>
                <div class="flex items-center gap-4">
                    @foreach($footer_pages ?? [] as $page)
                    <a href="{{ url('/page/'.$page->slug) }}" class="hover:text-white/70 transition-colors">{{ $page->title }}</a>
                    @endforeach
                    <a href="{{ url('/faq') }}" class="hover:text-white/70 transition-colors">FAQs</a>
                    <a href="{{ url('/sitemap.xml') }}" class="hover:text-white/70 transition-colors">Sitemap</a>
                </div>
            </div>
        </div>
    </div>
</footer>
