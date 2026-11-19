{{--
    Announcement Ticker — top banner with horizontally scrolling notices
    Uses Alpine.js for show/hide + CSS animation for ticker scroll

    The bar sticks to the top of the window while the page scrolls. Its height
    is published as --notice-h so the sticky header, and the sticky filter bars
    under it, sit just below it rather than sliding underneath; dismissing the
    bar sets it back to 0.
--}}
@php
    $announcements = \App\Models\Web\Announcement::live()->get();
@endphp

@if($announcements->count() > 0)
<div x-data="{ dismissed: false }"
     x-show="!dismissed"
     x-effect="document.documentElement.style.setProperty('--notice-h', dismissed ? '0px' : '36px')"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 -translate-y-0"
     x-transition:leave-end="opacity-0 -translate-y-full"
     class="sticky top-0 z-[60] bg-accent-500 text-white overflow-hidden"
     id="announcement-ticker"
     role="marquee"
     aria-label="Announcements"
>
    <div class="flex items-center h-9">
        {{-- Label --}}
        <div class="flex-shrink-0 bg-primary-500 px-4 h-full flex items-center gap-2 z-10">
            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/>
            </svg>
            <span class="text-[11px] font-bold uppercase tracking-wider hidden sm:inline">Notices</span>
        </div>

        {{-- Scrolling Text --}}
        <div class="flex-1 overflow-hidden relative">
            <div class="flex whitespace-nowrap animate-ticker hover:[animation-play-state:paused]"
                 style="animation-duration: {{ max($announcements->count() * 12, 20) }}s;">
                @foreach($announcements as $a)
                    <span class="inline-flex items-center px-8 text-[13px] font-medium">
                        <span class="w-1.5 h-1.5 rounded-full bg-white/60 mr-3 flex-shrink-0"></span>
                        {{ $a->ticker_text }}
                    </span>
                @endforeach
                {{-- Duplicate for seamless loop --}}
                @foreach($announcements as $a)
                    <span class="inline-flex items-center px-8 text-[13px] font-medium" aria-hidden="true">
                        <span class="w-1.5 h-1.5 rounded-full bg-white/60 mr-3 flex-shrink-0"></span>
                        {{ $a->ticker_text }}
                    </span>
                @endforeach
            </div>
        </div>

        {{-- Dismiss button --}}
        <button @click="dismissed = true"
                class="flex-shrink-0 px-3 h-full flex items-center hover:bg-accent-600 transition-colors cursor-pointer"
                aria-label="Dismiss announcements">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</div>

<style>
    :root { --notice-h: 36px; }
    @keyframes ticker-scroll-anim {
        0% { transform: translateX(0); }
        100% { transform: translateX(-50%); }
    }
    .animate-ticker {
        animation: ticker-scroll-anim linear infinite;
    }
</style>
@endif
