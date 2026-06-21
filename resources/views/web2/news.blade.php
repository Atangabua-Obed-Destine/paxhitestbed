{{--
    ================================================================
    NEWS LISTING — PAX Higher Institute
    ================================================================
--}}
@extends('web2.layouts.master')

@section('title', 'News & Updates — ' . ($setting->title ?? 'PAXHI'))
@section('description', 'Stay updated with the latest news, announcements, and stories from PAX Higher Institute.')

@section('content')

{{-- HERO --}}
<section class="relative min-h-[340px] flex items-end overflow-hidden bg-primary-900">
    <div class="absolute inset-0 opacity-[0.04]" style="background-image:url('data:image/svg+xml,%3Csvg width=60 height=60 viewBox=%220 0 60 60%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cg fill=%22none%22 fill-rule=%22evenodd%22%3E%3Cg fill=%22%23ffffff%22 fill-opacity=%221%22%3E%3Cpath d=%22M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z%22/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')"></div>
    <div class="section-container relative z-10 pb-12 pt-32">
        <nav class="text-sm text-white/50 mb-5" data-aos="fade-up">
            <a href="{{ url('/') }}" class="hover:text-accent-400 transition-colors">Home</a>
            <span class="mx-2">/</span>
            <span class="text-white/80">News</span>
        </nav>
        <h1 class="text-3xl md:text-4xl lg:text-5xl font-heading font-bold text-white" data-aos="fade-up" data-aos-delay="100">
            News & Updates
        </h1>
        <p class="mt-4 text-lg text-white/70 max-w-2xl" data-aos="fade-up" data-aos-delay="150">
            The latest from our campus — academic milestones, community events, and institutional highlights.
        </p>
    </div>
</section>

{{-- NEWS GRID --}}
<section class="py-16 lg:py-20 bg-white">
    <div class="section-container">
        @if($newses->count())

        {{-- Featured (first item) --}}
        @php $featured = $newses->first(); @endphp
        <div class="mb-12" data-aos="fade-up">
            <a href="{{ route('news.single', ['id' => $featured->id, 'slug' => $featured->slug]) }}" class="group grid grid-cols-1 lg:grid-cols-2 gap-8 bg-surface-50 rounded-2xl overflow-hidden border border-surface-100 hover:shadow-xl transition-all">
                <div class="aspect-[16/10] lg:aspect-auto lg:min-h-[320px] overflow-hidden">
                    @if($featured->attach)
                    <img src="{{ asset('uploads/news/'.$featured->attach) }}" alt="{{ $featured->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                    @else
                    <div class="w-full h-full bg-gradient-to-br from-primary-100 to-primary-200 flex items-center justify-center"><i class="fas fa-newspaper text-5xl text-primary-300"></i></div>
                    @endif
                </div>
                <div class="p-6 lg:p-8 flex flex-col justify-center">
                    <span class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-accent-500 mb-3">
                        <i class="fas fa-star"></i> Latest
                    </span>
                    <h2 class="text-2xl lg:text-3xl font-heading font-bold text-surface-800 group-hover:text-primary-500 transition-colors mb-3 leading-tight">{{ $featured->title }}</h2>
                    <p class="text-surface-500 mb-4 line-clamp-3">{{ Str::limit(strip_tags($featured->description), 200) }}</p>
                    <div class="flex items-center gap-4 text-sm text-surface-400">
                        @if($featured->date)<span><i class="far fa-calendar mr-1"></i>{{ \Carbon\Carbon::parse($featured->date)->format('M d, Y') }}</span>@endif
                    </div>
                </div>
            </a>
        </div>

        {{-- Rest of news --}}
        @if($newses->count() > 1)
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($newses->skip(1) as $idx => $news)
            <a href="{{ route('news.single', ['id' => $news->id, 'slug' => $news->slug]) }}"
               class="group bg-white rounded-xl border border-surface-100 hover:border-primary-200 hover:shadow-lg overflow-hidden transition-all"
               data-aos="fade-up" data-aos-delay="{{ min($idx * 80, 320) }}">
                <div class="aspect-[16/10] overflow-hidden">
                    @if($news->attach)
                    <img src="{{ asset('uploads/news/'.$news->attach) }}" alt="{{ $news->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                    @else
                    <div class="w-full h-full bg-gradient-to-br from-surface-100 to-surface-200 flex items-center justify-center"><i class="fas fa-newspaper text-3xl text-surface-300"></i></div>
                    @endif
                </div>
                <div class="p-5">
                    @if($news->date)
                    <p class="text-xs font-medium text-accent-500 mb-2">{{ \Carbon\Carbon::parse($news->date)->format('F d, Y') }}</p>
                    @endif
                    <h3 class="font-heading font-bold text-surface-800 group-hover:text-primary-500 transition-colors leading-snug mb-2">{{ Str::limit($news->title, 80) }}</h3>
                    <p class="text-sm text-surface-500 line-clamp-2">{{ Str::limit(strip_tags($news->description), 120) }}</p>
                    <span class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-500 mt-4 group-hover:gap-2.5 transition-all">Read More <i class="fas fa-arrow-right text-xs"></i></span>
                </div>
            </a>
            @endforeach
        </div>
        @endif

        {{-- Pagination --}}
        @if($newses->hasPages())
        <div class="mt-12 flex justify-center" data-aos="fade-up">
            {{ $newses->links('pagination::tailwind') }}
        </div>
        @endif

        @else
        <div class="text-center py-20">
            <i class="fas fa-newspaper text-5xl text-surface-200 mb-4"></i>
            <h3 class="text-xl font-heading font-bold text-surface-600 mb-2">No News Yet</h3>
            <p class="text-surface-400">Check back soon for the latest updates.</p>
        </div>
        @endif
    </div>
</section>

@endsection
