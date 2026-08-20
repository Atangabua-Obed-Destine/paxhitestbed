{{--
    ================================================================
    {{ institution_name() }} — Master Layout (Redesign)
    Tailwind CSS 4 · Alpine.js · Vite · Laravel 10
    ================================================================
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    {{-- SEO --}}
    <title>@yield('title', $setting->meta_title ?? institution_name())</title>
    <meta name="description" content="@yield('meta_description', $setting->meta_description ?? '' . institution_name() . ' (' . (institution_code()) . ') — A Catholic university in the Archdiocese of Bamenda offering accredited HND and Degree programs.')">
    <meta name="keywords" content="@yield('meta_keywords', $setting->meta_keywords ?? (institution_code() . ', ' . institution_name() . ', Catholic University, Cameroon'))">
    <meta name="author" content="{{ institution_name() }}">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#1e3a5f">
    <meta name="base-url" content="{{ url('/') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Favicon --}}
    @if($setting->favicon_path && is_file('uploads/setting/'.$setting->favicon_path))
        <link rel="icon" href="{{ asset('uploads/setting/'.$setting->favicon_path) }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ asset('uploads/setting/'.$setting->favicon_path) }}">
    @endif

    {{-- Canonical --}}
    <link rel="canonical" href="{{ url()->current() }}">

    {{-- Open Graph --}}
    @yield('social_meta_tags')

    {{-- Structured Data --}}
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "EducationalOrganization",
        "name": "{{ institution_name() }}",
        "alternateName": "{{ institution_code() }}",
        "url": "{{ url('/') }}",
        @if($setting->logo_path)
        "logo": "{{ asset('uploads/setting/'.$setting->logo_path) }}",
        @endif
        "description": "{{ $setting->meta_description ?? 'A Catholic institution of higher learning in the Archdiocese of Bamenda, Cameroon.' }}",
        "address": {
            "@type": "PostalAddress",
            "addressLocality": "Bamunka – Ndop",
            "addressRegion": "North West Region",
            "addressCountry": "CM"
        },
        @php $topbarSetting = \App\Models\Web\TopbarSetting::first(); @endphp
        @if($topbarSetting)
        "telephone": "{{ $topbarSetting->phone }}",
        "email": "{{ $topbarSetting->email }}",
        @endif
        "sameAs": [
            @php $social = \App\Models\Web\SocialSetting::first(); @endphp
            @if($social)
                @if($social->facebook)"{{ $social->facebook }}"@endif
                @if($social->instagram),  "{{ $social->instagram }}"@endif
                @if($social->twitter),  "{{ $social->twitter }}"@endif
                @if($social->youtube),  "{{ $social->youtube }}"@endif
                @if($social->linkedin),  "{{ $social->linkedin }}"@endif
            @endif
        ]
    }
    </script>
    @yield('structured_data')

    {{-- Hreflang tags --}}
    @php $languages = \App\Models\Language::where('status', 1)->get(); @endphp
    @foreach($languages as $lang)
        <link rel="alternate" hreflang="{{ $lang->code }}" href="{{ url()->current() }}?lang={{ $lang->code }}">
    @endforeach

    {{-- Sitemap --}}
    <link rel="sitemap" type="application/xml" title="Sitemap" href="{{ route('sitemap') }}">

    {{-- Vite CSS + JS --}}
    @vite(['resources/css/web/app.css', 'resources/js/web/app.js'])

    {{-- Font Awesome 6 (CDN for icons) --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
          integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
          crossorigin="anonymous" referrerpolicy="no-referrer" />

    {{-- Page-specific head --}}
    @yield('head')
</head>

<body class="font-body antialiased text-surface-800 bg-white overflow-x-hidden"
      x-data
      x-cloak>

    {{-- ============================================================
         ANNOUNCEMENT TICKER (if active announcements exist)
         ============================================================ --}}
    @include('web2.partials.announcement-ticker')

    {{-- ============================================================
         HEADER / NAVIGATION
         ============================================================ --}}
    @include('web2.partials.header')

    {{-- ============================================================
         MAIN CONTENT
         ============================================================ --}}
    <main id="main-content">
        @yield('content')
    </main>

    {{-- ============================================================
         FOOTER
         ============================================================ --}}
    @include('web2.partials.footer')

    {{-- ============================================================
         SCROLL TO TOP BUTTON
         ============================================================ --}}
    <button
        x-data="{ show: false }"
        x-init="window.addEventListener('scroll', () => show = window.scrollY > 500, { passive: true })"
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-4"
        @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
        class="fixed bottom-6 right-6 z-50 w-12 h-12 rounded-full bg-primary-500 text-white shadow-lg
               hover:bg-primary-700 transition-all duration-300 flex items-center justify-center
               hover:-translate-y-1 hover:shadow-xl cursor-pointer no-print"
        aria-label="Back to top"
    >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
        </svg>
    </button>

    {{-- Dynamic Popup --}}
    @if(View::exists('components.dynamic-popup'))
        @include('components.dynamic-popup', ['area' => 'front_web'])
    @endif

    {{-- Page-specific scripts --}}
    @yield('scripts')

    @include('components.chat-widget')
</body>
</html>
