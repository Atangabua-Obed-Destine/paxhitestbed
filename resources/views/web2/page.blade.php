{{--
    ================================================================
    CMS PAGE TEMPLATE — PAX Higher Institute
    Generic page rendered from the Page model (admin CMS)
    ================================================================
--}}
@extends('web2.layouts.master')

@section('title', ($page->meta_title ?? $page->title ?? 'Page') . ' — ' . ($setting->title ?? 'PAXHI'))
@section('description', $page->meta_description ?? Str::limit(strip_tags($page->description ?? ''), 160))

@section('content')

{{-- HERO --}}
<section class="relative min-h-[320px] flex items-end overflow-hidden bg-primary-900">
    @if($page->attach)
    <img src="{{ asset('uploads/pages/'.$page->attach) }}" alt="{{ $page->title }}" class="absolute inset-0 w-full h-full object-cover opacity-30">
    @endif
    <div class="absolute inset-0 bg-gradient-to-t from-primary-900/95 via-primary-900/70 to-primary-900/40"></div>
    <div class="section-container relative z-10 pb-12 pt-32">
        <nav class="text-sm text-white/50 mb-5" data-aos="fade-up">
            <a href="{{ url('/') }}" class="hover:text-accent-400 transition-colors">Home</a>
            <span class="mx-2">/</span>
            <span class="text-white/80">{{ $page->title }}</span>
        </nav>
        <h1 class="text-3xl md:text-4xl lg:text-5xl font-heading font-bold text-white" data-aos="fade-up" data-aos-delay="100">
            {{ $page->title }}
        </h1>
    </div>
</section>

{{-- CONTENT --}}
<section class="py-12 lg:py-16 bg-white">
    <div class="section-container">
        <div class="max-w-4xl mx-auto">
            @if($page->attach)
            <img src="{{ asset('uploads/pages/'.$page->attach) }}" alt="{{ $page->title }}" class="w-full rounded-2xl mb-8 shadow-sm" loading="lazy" data-aos="fade-up">
            @endif

            <div class="prose prose-lg prose-surface max-w-none [&>p]:mb-5 [&>p]:leading-relaxed [&>h2]:font-heading [&>h2]:text-2xl [&>h2]:mt-10 [&>h2]:mb-4 [&>h3]:font-heading [&>h3]:text-xl [&>ul]:my-4 [&>ol]:my-4 [&>img]:rounded-xl [&>img]:shadow-sm [&>blockquote]:border-l-primary-400 [&>blockquote]:bg-primary-50/50 [&>blockquote]:rounded-r-lg [&>blockquote]:py-2" data-aos="fade-up">
                {!! $page->description !!}
            </div>
        </div>
    </div>
</section>

@endsection
