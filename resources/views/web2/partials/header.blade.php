{{--
    ================================================================
    HEADER / NAVIGATION — {{ institution_name() }}
    ================================================================
    Structure:
    1. Top Utility Bar (contact info + social icons) — desktop only
    2. Main Navigation Bar (logo + nav links + CTA + search + mobile toggle)
    3. Mobile Slide-out Menu
    ================================================================
--}}
@php
    $applicationOpen = \App\Models\ApplicationSetting::status();
    $currentRoute = Route::currentRouteName();
@endphp

{{-- ================================================================
     TOP UTILITY BAR
     ================================================================ --}}
<div class="hidden lg:block bg-primary-900 text-white/80 border-b border-white/10 relative z-50">
    <div class="section-container">
        <div class="flex items-center justify-between h-10 text-[12px]">
            {{-- Left: Contact --}}
            <div class="flex items-center gap-5">
                @if($topbarSetting && $topbarSetting->phone)
                <a href="tel:{{ $topbarSetting->phone }}" class="flex items-center gap-1.5 hover:text-accent-400 transition-colors">
                    <i class="fas fa-phone-alt text-[10px] text-accent-400"></i>
                    {{ $topbarSetting->phone }}
                </a>
                @endif
                @if($topbarSetting && $topbarSetting->email)
                <a href="mailto:{{ $topbarSetting->email }}" class="flex items-center gap-1.5 hover:text-accent-400 transition-colors">
                    <i class="fas fa-envelope text-[10px] text-accent-400"></i>
                    {{ $topbarSetting->email }}
                </a>
                @endif
                @if($topbarSetting && $topbarSetting->address)
                <span class="flex items-center gap-1.5">
                    <i class="fas fa-map-marker-alt text-[10px] text-accent-400"></i>
                    {{ Str::limit($topbarSetting->address, 50) }}
                </span>
                @endif
            </div>

            {{-- Right: Social + Quick Links --}}
            <div class="flex items-center gap-4">
                {{-- Quick Links --}}
                <a href="{{ url('admin/login') }}" class="hover:text-accent-400 transition-colors">
                    <i class="fas fa-user-tie mr-1"></i>Staff Portal
                </a>
                <span class="w-px h-3 bg-white/20"></span>
                <a href="{{ url('student/login') }}" class="hover:text-accent-400 transition-colors">
                    <i class="fas fa-user-graduate mr-1"></i>Student Portal
                </a>
                <span class="w-px h-3 bg-white/20"></span>

                {{-- Social Icons --}}
                @if($socialSetting)
                <div class="flex items-center gap-2.5">
                    @if($socialSetting->facebook)
                    <a href="{{ $socialSetting->facebook }}" target="_blank" rel="noopener" aria-label="Facebook" class="hover:text-accent-400 transition-colors"><i class="fab fa-facebook-f"></i></a>
                    @endif
                    @if($socialSetting->instagram)
                    <a href="{{ $socialSetting->instagram }}" target="_blank" rel="noopener" aria-label="Instagram" class="hover:text-accent-400 transition-colors"><i class="fab fa-instagram"></i></a>
                    @endif
                    @if($socialSetting->twitter)
                    <a href="{{ $socialSetting->twitter }}" target="_blank" rel="noopener" aria-label="Twitter/X" class="hover:text-accent-400 transition-colors"><i class="fab fa-x-twitter"></i></a>
                    @endif
                    @if($socialSetting->youtube)
                    <a href="{{ $socialSetting->youtube }}" target="_blank" rel="noopener" aria-label="YouTube" class="hover:text-accent-400 transition-colors"><i class="fab fa-youtube"></i></a>
                    @endif
                    @if($socialSetting->linkedin)
                    <a href="{{ $socialSetting->linkedin }}" target="_blank" rel="noopener" aria-label="LinkedIn" class="hover:text-accent-400 transition-colors"><i class="fab fa-linkedin-in"></i></a>
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ================================================================
     MAIN NAVIGATION BAR
     ================================================================ --}}
<header x-data="stickyHeader"
        :class="scrolled ? 'bg-white/95 backdrop-blur-lg shadow-[var(--shadow-nav)] py-2' : 'bg-white py-3'"
        class="sticky top-0 z-50 transition-all duration-300 border-b border-surface-100"
        id="main-header"
>
    <div class="section-container">
        <div class="flex items-center justify-between">

            {{-- ===== LOGO ===== --}}
            <a href="{{ url('/') }}" class="flex items-center gap-3 flex-shrink-0 group" aria-label="Home">
                @if($setting->logo_path && file_exists(public_path('uploads/setting/'.$setting->logo_path)))
                    <img src="{{ asset('uploads/setting/'.$setting->logo_path) }}"
                         alt="{{ $setting->title }}"
                         class="h-12 lg:h-14 w-auto transition-transform duration-300 group-hover:scale-105"
                         :class="scrolled ? 'h-10 lg:h-11' : 'h-12 lg:h-14'"
                    >
                @endif
                <div class="hidden sm:block">
                    <div class="text-primary-500 font-heading font-bold text-base lg:text-lg leading-tight"
                         style="max-width: 260px; white-space: normal; word-wrap: break-word;"
                         :class="scrolled ? 'text-sm lg:text-base' : ''">
                        {{ institution_name() }}
                    </div>
                    <div class="text-[10px] text-surface-400 font-medium tracking-wide uppercase">
                        {{ $setting->site_subtitle ?? 'Archdiocese of Bamenda' }}
                    </div>
                </div>
            </a>

            {{-- ===== DESKTOP NAVIGATION ===== --}}
            <nav class="hidden lg:flex items-center gap-1" role="navigation" aria-label="Main navigation">
                @php
                    $navItems = [
                        ['route' => 'home', 'url' => url('/'), 'label' => 'Home'],
                        ['route' => 'about', 'url' => url('/about'), 'label' => 'About'],
                        ['route' => 'programs', 'url' => url('/programs'), 'label' => 'Programs', 'children' => [
                            ['url' => url('/programs'), 'label' => 'All Programs'],
                            ['url' => url('/faculties'), 'label' => 'Faculties'],
                        ]],
                        ['route' => 'admissions', 'url' => url('/admissions'), 'label' => 'Admissions'],
                        ['route' => 'news', 'url' => url('/news'), 'label' => 'News & Events', 'children' => [
                            ['url' => url('/news'), 'label' => 'News'],
                            ['url' => url('/event'), 'label' => 'Events'],
                            ['url' => url('/gallery'), 'label' => 'Gallery'],
                        ]],
                        ['route' => 'projects', 'url' => url('/projects'), 'label' => 'Research'],
                        ['route' => 'campus-life', 'url' => url('/campus-life'), 'label' => 'Campus Life'],
                        ['route' => 'resources', 'url' => url('/downloads'), 'label' => 'Resources'],
                    ];
                @endphp

                @foreach($navItems as $item)
                    @if(isset($item['children']))
                        {{-- Dropdown --}}
                        <div x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false" class="relative">
                            <a href="{{ $item['url'] }}"
                               class="flex items-center gap-1 px-3 py-2 text-[13.5px] font-semibold rounded-lg transition-colors
                                      {{ Str::startsWith($currentRoute, $item['route']) ? 'text-primary-500 bg-primary-50' : 'text-surface-700 hover:text-primary-500 hover:bg-surface-50' }}"
                            >
                                {{ $item['label'] }}
                                <svg class="w-3.5 h-3.5 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </a>
                            <div x-show="open"
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 -translate-y-2"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-150"
                                 x-transition:leave-start="opacity-100"
                                 x-transition:leave-end="opacity-0"
                                 class="absolute top-full left-0 mt-1 w-52 bg-white rounded-xl shadow-xl border border-surface-100 py-2 z-50"
                                 x-cloak
                            >
                                @foreach($item['children'] as $child)
                                <a href="{{ $child['url'] }}"
                                   class="block px-4 py-2.5 text-sm text-surface-600 hover:text-primary-500 hover:bg-primary-50 transition-colors">
                                    {{ $child['label'] }}
                                </a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        {{-- Single Link --}}
                        <a href="{{ $item['url'] }}"
                           class="px-3 py-2 text-[13.5px] font-semibold rounded-lg transition-colors
                                  {{ $currentRoute === $item['route'] ? 'text-primary-500 bg-primary-50' : 'text-surface-700 hover:text-primary-500 hover:bg-surface-50' }}"
                        >
                            {{ $item['label'] }}
                        </a>
                    @endif
                @endforeach
            </nav>

            {{-- ===== RIGHT ACTIONS ===== --}}
            <div class="flex items-center gap-3">

                {{-- Search Toggle --}}
                <button @click="$store.nav.toggleSearch()"
                        class="w-9 h-9 rounded-lg flex items-center justify-center text-surface-500 hover:text-primary-500 hover:bg-surface-50 transition-colors cursor-pointer"
                        aria-label="Search"
                >
                    <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </button>

                {{-- Apply Now CTA --}}
                @if($applicationOpen)
                <a href="{{ url('/admissions') }}" class="hidden md:inline-flex btn-accent text-[13px] !py-2.5 !px-5">
                    Apply Now
                    <svg class="w-4 h-4 ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>
                </a>
                @endif

                {{-- Mobile Menu Toggle --}}
                <button @click="$store.nav.toggle()"
                        class="lg:hidden w-10 h-10 rounded-lg flex items-center justify-center text-surface-600 hover:bg-surface-50 transition-colors cursor-pointer"
                        :aria-expanded="$store.nav.open"
                        aria-label="Toggle menu"
                >
                    <svg x-show="!$store.nav.open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg x-show="$store.nav.open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-cloak>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- ===== SEARCH OVERLAY ===== --}}
    <div x-show="$store.nav.searchOpen"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @keydown.escape.window="$store.nav.closeSearch()"
         class="absolute inset-x-0 top-full bg-white border-t border-surface-100 shadow-xl z-50"
         x-cloak
    >
        <div class="section-container py-6" x-data="liveSearch" @click.outside="$store.nav.closeSearch()">
            <div class="relative max-w-2xl mx-auto">
                <input type="text"
                       x-model="query"
                       @input="search()"
                       @blur="close()"
                       placeholder="Search programs, news, faculty..."
                       class="w-full pl-12 pr-12 py-4 bg-surface-50 border border-surface-200 rounded-xl text-base
                              focus:outline-none focus:ring-2 focus:ring-primary-300 focus:border-primary-300 transition-all"
                       autofocus
                >
                <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-surface-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <button @click="$store.nav.closeSearch()"
                        class="absolute right-4 top-1/2 -translate-y-1/2 text-surface-400 hover:text-surface-600 cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>

                {{-- Results --}}
                <div x-show="showResults" class="absolute top-full left-0 right-0 mt-2 bg-white rounded-xl shadow-xl border border-surface-100 max-h-80 overflow-y-auto" x-cloak>
                    <template x-if="loading">
                        <div class="p-6 text-center text-surface-400">
                            <i class="fas fa-circle-notch fa-spin mr-2"></i>Searching...
                        </div>
                    </template>
                    <template x-if="!loading && results.length === 0 && query.length >= 2">
                        <div class="p-6 text-center text-surface-400">
                            No results found for "<span x-text="query"></span>"
                        </div>
                    </template>
                    <template x-for="result in results" :key="result.url">
                        <a :href="result.url" class="block px-5 py-3 hover:bg-surface-50 border-b border-surface-50 last:border-0 transition-colors">
                            <div class="text-xs font-semibold uppercase tracking-wider text-accent-500 mb-0.5" x-text="result.type"></div>
                            <div class="text-sm font-medium text-surface-800" x-text="result.title"></div>
                        </a>
                    </template>
                </div>
            </div>
        </div>
    </div>
</header>

{{-- ================================================================
     MOBILE MENU (Slide-out drawer)
     ================================================================ --}}
<div x-show="$store.nav.open"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-[100] lg:hidden"
     @keydown.escape.window="$store.nav.close()"
     x-cloak
>
    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="$store.nav.close()"></div>

    {{-- Drawer --}}
    <div x-show="$store.nav.open"
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200 transform"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         class="absolute right-0 top-0 bottom-0 w-[85%] max-w-sm bg-white shadow-2xl overflow-y-auto"
    >
        {{-- Drawer Header --}}
        <div class="flex items-center justify-between p-5 border-b border-surface-100">
            <div class="flex items-center gap-3">
                @if($setting->logo_path && file_exists(public_path('uploads/setting/'.$setting->logo_path)))
                <img src="{{ asset('uploads/setting/'.$setting->logo_path) }}" alt="{{ $setting->title }}" class="h-10">
                @endif
                <span class="font-heading font-bold text-primary-500 text-sm">{{ institution_code() }}</span>
            </div>
            <button @click="$store.nav.close()" class="w-9 h-9 rounded-lg flex items-center justify-center hover:bg-surface-50 cursor-pointer">
                <svg class="w-5 h-5 text-surface-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Mobile Nav Links --}}
        <nav class="p-4 space-y-1" aria-label="Mobile navigation">
            @foreach($navItems as $item)
                @if(isset($item['children']))
                    <div x-data="{ subOpen: false }">
                        <button @click="subOpen = !subOpen"
                                class="w-full flex items-center justify-between px-4 py-3 rounded-lg text-sm font-semibold text-surface-700 hover:bg-surface-50 transition-colors cursor-pointer">
                            {{ $item['label'] }}
                            <svg class="w-4 h-4 transition-transform" :class="subOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="subOpen" x-collapse class="pl-4 space-y-1 mt-1">
                            @foreach($item['children'] as $child)
                            <a href="{{ $child['url'] }}" @click="$store.nav.close()"
                               class="block px-4 py-2.5 rounded-lg text-sm text-surface-500 hover:text-primary-500 hover:bg-primary-50 transition-colors">
                                {{ $child['label'] }}
                            </a>
                            @endforeach
                        </div>
                    </div>
                @else
                    <a href="{{ $item['url'] }}" @click="$store.nav.close()"
                       class="block px-4 py-3 rounded-lg text-sm font-semibold transition-colors
                              {{ $currentRoute === $item['route'] ? 'text-primary-500 bg-primary-50' : 'text-surface-700 hover:bg-surface-50' }}">
                        {{ $item['label'] }}
                    </a>
                @endif
            @endforeach

            {{-- Mobile CTA --}}
            <div class="pt-4 px-4 space-y-3 border-t border-surface-100 mt-4">
                @if($applicationOpen)
                <a href="{{ url('/admissions') }}" class="btn-accent w-full justify-center text-sm">
                    Apply Now <i class="fas fa-arrow-right ml-2"></i>
                </a>
                @endif
                <div class="grid grid-cols-2 gap-2">
                    <a href="{{ url('student/login') }}" class="btn-ghost justify-center text-xs border border-surface-200 !rounded-lg">
                        <i class="fas fa-user-graduate mr-1.5"></i>Student
                    </a>
                    <a href="{{ url('admin/login') }}" class="btn-ghost justify-center text-xs border border-surface-200 !rounded-lg">
                        <i class="fas fa-user-tie mr-1.5"></i>Staff
                    </a>
                </div>
            </div>
        </nav>

        {{-- Mobile Contact --}}
        @if($topbarSetting)
        <div class="p-5 mt-4 border-t border-surface-100 space-y-2 text-sm text-surface-500">
            @if($topbarSetting->phone)
            <a href="tel:{{ $topbarSetting->phone }}" class="flex items-center gap-2 hover:text-primary-500">
                <i class="fas fa-phone-alt text-accent-500 w-4"></i>{{ $topbarSetting->phone }}
            </a>
            @endif
            @if($topbarSetting->email)
            <a href="mailto:{{ $topbarSetting->email }}" class="flex items-center gap-2 hover:text-primary-500">
                <i class="fas fa-envelope text-accent-500 w-4"></i>{{ $topbarSetting->email }}
            </a>
            @endif
        </div>
        @endif
    </div>
</div>
