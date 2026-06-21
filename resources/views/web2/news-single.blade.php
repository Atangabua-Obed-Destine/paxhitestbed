{{--
    ================================================================
    NEWS DETAIL — PAX Higher Institute
    ================================================================
--}}
@extends('web2.layouts.master')

@section('title', ($news->title ?? 'News') . ' — ' . ($setting->title ?? 'PAXHI'))
@section('description', Str::limit(strip_tags($news->description ?? ''), 160))

@section('content')

{{-- HERO BANNER --}}
<section class="relative min-h-[360px] lg:min-h-[420px] flex items-end overflow-hidden">
    @if($news->attach)
    <img src="{{ asset('uploads/news/'.$news->attach) }}" alt="{{ $news->title }}" class="absolute inset-0 w-full h-full object-cover">
    @endif
    <div class="absolute inset-0 bg-gradient-to-t from-primary-900/95 via-primary-900/60 to-primary-900/30"></div>
    <div class="section-container relative z-10 pb-12 pt-32 max-w-4xl mx-auto">
        <nav class="text-sm text-white/50 mb-5" data-aos="fade-up">
            <a href="{{ url('/') }}" class="hover:text-accent-400 transition-colors">Home</a>
            <span class="mx-2">/</span>
            <a href="{{ url('/news') }}" class="hover:text-accent-400 transition-colors">News</a>
            <span class="mx-2">/</span>
            <span class="text-white/80">{{ Str::limit($news->title, 50) }}</span>
        </nav>
        @if($news->date)
        <p class="text-accent-400 text-sm font-medium mb-3" data-aos="fade-up" data-aos-delay="50">
            <i class="far fa-calendar-alt mr-1"></i> {{ \Carbon\Carbon::parse($news->date)->format('l, F d, Y') }}
        </p>
        @endif
        <h1 class="text-2xl md:text-3xl lg:text-4xl font-heading font-bold text-white leading-tight" data-aos="fade-up" data-aos-delay="100">
            {{ $news->title }}
        </h1>
    </div>
</section>

{{-- CONTENT --}}
<section class="py-12 lg:py-16 bg-white">
    <div class="section-container">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
            {{-- Article --}}
            <article class="lg:col-span-8" data-aos="fade-up">
                @if($news->attach)
                <img src="{{ asset('uploads/news/'.$news->attach) }}" alt="{{ $news->title }}" class="w-full rounded-2xl mb-8 shadow-sm" loading="lazy">
                @endif

                <div class="prose prose-lg prose-surface max-w-none [&>p]:mb-5 [&>p]:leading-relaxed [&>h2]:font-heading [&>h2]:text-2xl [&>h2]:mt-10 [&>h2]:mb-4 [&>h3]:font-heading [&>h3]:text-xl [&>ul]:my-4 [&>ol]:my-4">
                    {!! $news->description !!}
                </div>

                {{-- Share --}}
                <div class="mt-10 pt-8 border-t border-surface-100">
                    <h4 class="text-sm font-semibold text-surface-500 uppercase tracking-wider mb-3">Share This Article</h4>
                    <div class="flex gap-2">
                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(request()->url()) }}" target="_blank" rel="noopener"
                           class="w-10 h-10 rounded-full bg-blue-600 text-white flex items-center justify-center hover:bg-blue-700 transition-colors"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://twitter.com/intent/tweet?url={{ urlencode(request()->url()) }}&text={{ urlencode($news->title) }}" target="_blank" rel="noopener"
                           class="w-10 h-10 rounded-full bg-black text-white flex items-center justify-center hover:bg-gray-800 transition-colors"><i class="fab fa-x-twitter"></i></a>
                        <a href="https://wa.me/?text={{ urlencode($news->title . ' ' . request()->url()) }}" target="_blank" rel="noopener"
                           class="w-10 h-10 rounded-full bg-green-600 text-white flex items-center justify-center hover:bg-green-700 transition-colors"><i class="fab fa-whatsapp"></i></a>
                        <a href="mailto:?subject={{ urlencode($news->title) }}&body={{ urlencode(request()->url()) }}"
                           class="w-10 h-10 rounded-full bg-surface-600 text-white flex items-center justify-center hover:bg-surface-700 transition-colors"><i class="fas fa-envelope"></i></a>
                    </div>
                </div>
            </article>

            {{-- Sidebar --}}
            <aside class="lg:col-span-4 space-y-6">
                {{-- Back to News --}}
                <a href="{{ url('/news') }}" class="flex items-center gap-2 text-sm font-medium text-primary-500 hover:text-accent-500 transition-colors mb-4" data-aos="fade-left">
                    <i class="fas fa-arrow-left"></i> Back to All News
                </a>

                {{-- Related / Latest News --}}
                <div class="bg-surface-50 rounded-2xl p-6" data-aos="fade-left" data-aos-delay="100">
                    <h3 class="font-heading font-bold text-surface-800 mb-4 text-lg">Latest News</h3>
                    @php
                        $latestNews = \App\Models\Web\News::where('status', '1')
                            ->where('id', '!=', $news->id)
                            ->where('language_id', $news->language_id)
                            ->orderBy('date', 'desc')
                            ->take(4)
                            ->get();
                    @endphp
                    @if($latestNews->count())
                    <div class="space-y-4">
                        @foreach($latestNews as $latest)
                        <a href="{{ route('news.single', ['id' => $latest->id, 'slug' => $latest->slug]) }}" class="flex gap-3 group">
                            <div class="w-16 h-16 rounded-lg overflow-hidden flex-shrink-0">
                                @if($latest->attach)
                                <img src="{{ asset('uploads/news/'.$latest->attach) }}" alt="{{ $latest->title }}" class="w-full h-full object-cover" loading="lazy">
                                @else
                                <div class="w-full h-full bg-surface-200 flex items-center justify-center"><i class="fas fa-newspaper text-surface-400 text-xs"></i></div>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-sm font-semibold text-surface-800 group-hover:text-primary-500 transition-colors line-clamp-2 leading-snug">{{ $latest->title }}</h4>
                                @if($latest->date)
                                <p class="text-xs text-surface-400 mt-1">{{ \Carbon\Carbon::parse($latest->date)->format('M d, Y') }}</p>
                                @endif
                            </div>
                        </a>
                        @endforeach
                    </div>
                    @endif
                </div>

                {{-- CTA --}}
                <div class="bg-gradient-to-br from-primary-500 to-primary-700 rounded-2xl p-6 text-white" data-aos="fade-left" data-aos-delay="150">
                    <h3 class="font-heading font-bold text-lg mb-2 text-white">Stay Connected</h3>
                    <p class="text-white/70 text-sm mb-4">Follow us on social media for the latest updates from PAXHI.</p>
                    @if(isset($socialSetting))
                    <div class="flex gap-2">
                        @if($socialSetting->facebook)<a href="{{ $socialSetting->facebook }}" target="_blank" class="w-9 h-9 rounded-lg bg-white/20 text-white flex items-center justify-center hover:bg-white/30 transition-colors"><i class="fab fa-facebook-f text-sm"></i></a>@endif
                        @if($socialSetting->twitter)<a href="{{ $socialSetting->twitter }}" target="_blank" class="w-9 h-9 rounded-lg bg-white/20 text-white flex items-center justify-center hover:bg-white/30 transition-colors"><i class="fab fa-x-twitter text-sm"></i></a>@endif
                        @if($socialSetting->youtube)<a href="{{ $socialSetting->youtube }}" target="_blank" class="w-9 h-9 rounded-lg bg-white/20 text-white flex items-center justify-center hover:bg-white/30 transition-colors"><i class="fab fa-youtube text-sm"></i></a>@endif
                    </div>
                    @endif
                </div>
            </aside>
        </div>
    </div>
</section>

@endsection
