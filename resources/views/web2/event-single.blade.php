{{--
    ================================================================
    EVENT DETAIL — PAX Higher Institute
    ================================================================
--}}
@extends('web2.layouts.master')

@section('title', ($event->title ?? 'Event') . ' — ' . ($setting->title ?? 'PAXHI'))
@section('description', Str::limit(strip_tags($event->description ?? ''), 160))

@section('content')

{{-- HERO --}}
<section class="relative min-h-[380px] lg:min-h-[440px] flex items-end overflow-hidden">
    @if($event->attach)
    <img src="{{ asset('uploads/web-event/'.$event->attach) }}" alt="{{ $event->title }}" class="absolute inset-0 w-full h-full object-cover">
    @endif
    <div class="absolute inset-0 bg-gradient-to-t from-primary-900/95 via-primary-900/60 to-primary-900/30"></div>
    <div class="section-container relative z-10 pb-12 pt-32 max-w-4xl mx-auto">
        <nav class="text-sm text-white/50 mb-5" data-aos="fade-up">
            <a href="{{ url('/') }}" class="hover:text-accent-400 transition-colors">Home</a>
            <span class="mx-2">/</span>
            <a href="{{ url('/events') }}" class="hover:text-accent-400 transition-colors">Events</a>
            <span class="mx-2">/</span>
            <span class="text-white/80">{{ Str::limit($event->title, 50) }}</span>
        </nav>
        <h1 class="text-2xl md:text-3xl lg:text-4xl font-heading font-bold text-white leading-tight mb-5" data-aos="fade-up" data-aos-delay="100">
            {{ $event->title }}
        </h1>
        {{-- Meta pills --}}
        <div class="flex flex-wrap gap-3 text-sm" data-aos="fade-up" data-aos-delay="150">
            @if($event->date)
            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/15 text-white backdrop-blur-sm">
                <i class="far fa-calendar-alt text-accent-400"></i>{{ \Carbon\Carbon::parse($event->date)->format('l, F j, Y') }}
            </span>
            @endif
            @if($event->time)
            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/15 text-white backdrop-blur-sm">
                <i class="far fa-clock text-accent-400"></i>{{ $event->time }}
            </span>
            @endif
            @if($event->address)
            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/15 text-white backdrop-blur-sm">
                <i class="fas fa-map-marker-alt text-accent-400"></i>{{ $event->address }}
            </span>
            @endif
        </div>
    </div>
</section>

{{-- CONTENT --}}
<section class="py-12 lg:py-16 bg-white">
    <div class="section-container">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
            {{-- Article --}}
            <article class="lg:col-span-8" data-aos="fade-up">
                @if($event->attach)
                <img src="{{ asset('uploads/web-event/'.$event->attach) }}" alt="{{ $event->title }}" class="w-full rounded-2xl mb-8 shadow-sm" loading="lazy">
                @endif

                <div class="prose prose-lg prose-surface max-w-none [&>p]:mb-5 [&>p]:leading-relaxed [&>h2]:font-heading [&>h2]:text-2xl [&>h2]:mt-10 [&>h2]:mb-4">
                    {!! $event->description !!}
                </div>

                {{-- Share --}}
                <div class="mt-10 pt-8 border-t border-surface-100">
                    <h4 class="text-sm font-semibold text-surface-500 uppercase tracking-wider mb-3">Share This Event</h4>
                    <div class="flex gap-2">
                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(request()->url()) }}" target="_blank" rel="noopener"
                           class="w-10 h-10 rounded-full bg-blue-600 text-white flex items-center justify-center hover:bg-blue-700 transition-colors"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://twitter.com/intent/tweet?url={{ urlencode(request()->url()) }}&text={{ urlencode($event->title) }}" target="_blank" rel="noopener"
                           class="w-10 h-10 rounded-full bg-black text-white flex items-center justify-center hover:bg-gray-800 transition-colors"><i class="fab fa-x-twitter"></i></a>
                        <a href="https://wa.me/?text={{ urlencode($event->title . ' ' . request()->url()) }}" target="_blank" rel="noopener"
                           class="w-10 h-10 rounded-full bg-green-600 text-white flex items-center justify-center hover:bg-green-700 transition-colors"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>
            </article>

            {{-- Sidebar --}}
            <aside class="lg:col-span-4 space-y-6">
                <a href="{{ url('/events') }}" class="flex items-center gap-2 text-sm font-medium text-primary-500 hover:text-accent-500 transition-colors mb-4" data-aos="fade-left">
                    <i class="fas fa-arrow-left"></i> All Events
                </a>

                {{-- Event Details Card --}}
                <div class="bg-white rounded-2xl border border-surface-100 shadow-sm overflow-hidden" data-aos="fade-left" data-aos-delay="50">
                    <div class="bg-surface-50 px-6 py-4 border-b border-surface-100">
                        <h3 class="font-heading font-bold text-surface-800 text-base">Event Details</h3>
                    </div>
                    <div class="divide-y divide-surface-100">
                        @if($event->date)
                        <div class="px-6 py-3 flex items-center gap-3 text-sm">
                            <i class="far fa-calendar-alt text-primary-400 w-5 text-center"></i>
                            <div>
                                <span class="text-surface-400 block text-xs">Date</span>
                                <span class="font-semibold text-surface-800">{{ \Carbon\Carbon::parse($event->date)->format('F j, Y') }}</span>
                            </div>
                        </div>
                        @endif
                        @if($event->time)
                        <div class="px-6 py-3 flex items-center gap-3 text-sm">
                            <i class="far fa-clock text-primary-400 w-5 text-center"></i>
                            <div>
                                <span class="text-surface-400 block text-xs">Time</span>
                                <span class="font-semibold text-surface-800">{{ $event->time }}</span>
                            </div>
                        </div>
                        @endif
                        @if($event->address)
                        <div class="px-6 py-3 flex items-center gap-3 text-sm">
                            <i class="fas fa-map-marker-alt text-primary-400 w-5 text-center"></i>
                            <div>
                                <span class="text-surface-400 block text-xs">Location</span>
                                <span class="font-semibold text-surface-800">{{ $event->address }}</span>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Add to Calendar --}}
                @if($event->date)
                <div class="bg-surface-50 rounded-2xl p-5" data-aos="fade-left" data-aos-delay="100">
                    <h4 class="font-semibold text-surface-800 text-sm mb-3"><i class="fas fa-bell text-accent-500 mr-2"></i>Don't Miss Out</h4>
                    <p class="text-sm text-surface-500 mb-3">Add this event to your calendar so you don't forget.</p>
                    @php
                        $dtStart = \Carbon\Carbon::parse($event->date)->format('Ymd');
                        $gcalUrl = 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' . urlencode($event->title) . '&dates=' . $dtStart . '/' . $dtStart . '&details=' . urlencode(Str::limit(strip_tags($event->description), 200)) . '&location=' . urlencode($event->address ?? '');
                    @endphp
                    <a href="{{ $gcalUrl }}" target="_blank" rel="noopener"
                       class="flex items-center justify-center gap-2 w-full px-4 py-2.5 bg-white border border-surface-200 rounded-lg text-sm font-medium text-surface-700 hover:border-primary-300 hover:text-primary-500 transition-colors">
                        <i class="fab fa-google text-red-500"></i> Add to Google Calendar
                    </a>
                </div>
                @endif

                {{-- More Events --}}
                @php
                    $moreEvents = \App\Models\Web\WebEvent::where('status', '1')
                        ->where('id', '!=', $event->id)
                        ->where('language_id', $event->language_id)
                        ->orderBy('date', 'desc')
                        ->take(3)
                        ->get();
                @endphp
                @if($moreEvents->count())
                <div class="bg-surface-50 rounded-2xl p-6" data-aos="fade-left" data-aos-delay="150">
                    <h3 class="font-heading font-bold text-surface-800 mb-4 text-lg">More Events</h3>
                    <div class="space-y-4">
                        @foreach($moreEvents as $more)
                        <a href="{{ route('event.single', ['id' => $more->id, 'slug' => $more->slug]) }}" class="flex gap-3 group">
                            <div class="w-14 h-14 rounded-lg overflow-hidden flex-shrink-0">
                                @if($more->attach)
                                <img src="{{ asset('uploads/web-event/'.$more->attach) }}" alt="{{ $more->title }}" class="w-full h-full object-cover" loading="lazy">
                                @else
                                <div class="w-full h-full bg-surface-200 flex items-center justify-center"><i class="fas fa-calendar-day text-surface-400 text-xs"></i></div>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-sm font-semibold text-surface-800 group-hover:text-primary-500 transition-colors line-clamp-2 leading-snug">{{ $more->title }}</h4>
                                @if($more->date)
                                <p class="text-xs text-surface-400 mt-1">{{ \Carbon\Carbon::parse($more->date)->format('M d, Y') }}</p>
                                @endif
                            </div>
                        </a>
                        @endforeach
                    </div>
                </div>
                @endif
            </aside>
        </div>
    </div>
</section>

@endsection
