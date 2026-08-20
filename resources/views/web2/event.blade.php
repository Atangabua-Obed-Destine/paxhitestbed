{{--
    ================================================================
    EVENTS LISTING — {{ institution_name() }}
    ================================================================
--}}
@extends('web2.layouts.master')

@section('title', 'Events — ' . (institution_code()))
@section('description', 'Discover upcoming and past events at ' . institution_name() . ' — academic conferences, cultural celebrations, community gatherings, and more.')

@section('content')

{{-- HERO --}}
<section class="relative min-h-[340px] flex items-end overflow-hidden bg-primary-900">
    <div class="absolute inset-0 opacity-[0.04]" style="background-image:url('data:image/svg+xml,%3Csvg width=60 height=60 viewBox=%220 0 60 60%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cg fill=%22none%22 fill-rule=%22evenodd%22%3E%3Cg fill=%22%23ffffff%22 fill-opacity=%221%22%3E%3Cpath d=%22M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z%22/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')"></div>
    <div class="section-container relative z-10 pb-12 pt-32">
        <nav class="text-sm text-white/50 mb-5" data-aos="fade-up">
            <a href="{{ url('/') }}" class="hover:text-accent-400 transition-colors">Home</a>
            <span class="mx-2">/</span>
            <span class="text-white/80">Events</span>
        </nav>
        <h1 class="text-3xl md:text-4xl lg:text-5xl font-heading font-bold text-white" data-aos="fade-up" data-aos-delay="100">
            Events
        </h1>
        <p class="mt-4 text-lg text-white/70 max-w-2xl" data-aos="fade-up" data-aos-delay="150">
            Academic, cultural, and community events that bring our campus to life.
        </p>
    </div>
</section>

{{-- EVENTS --}}
<section class="py-16 lg:py-20 bg-white">
    <div class="section-container">
        @if($events->count())
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($events as $idx => $event)
            <a href="{{ route('event.single', ['id' => $event->id, 'slug' => $event->slug]) }}"
               class="group bg-white rounded-2xl border border-surface-100 hover:border-primary-200 hover:shadow-xl overflow-hidden transition-all"
               data-aos="fade-up" data-aos-delay="{{ min($idx * 80, 320) }}">
                {{-- Image --}}
                <div class="relative aspect-[16/10] overflow-hidden">
                    @if($event->attach)
                    <img src="{{ asset('uploads/web-event/'.$event->attach) }}" alt="{{ $event->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                    @else
                    <div class="w-full h-full bg-gradient-to-br from-secondary-100 to-secondary-200 flex items-center justify-center"><i class="fas fa-calendar-day text-3xl text-secondary-300"></i></div>
                    @endif
                    {{-- Date badge --}}
                    @if($event->date)
                    <div class="absolute top-4 left-4 bg-white rounded-xl px-3 py-2 shadow-lg text-center leading-none">
                        <span class="block text-xs font-bold text-accent-500 uppercase">{{ \Carbon\Carbon::parse($event->date)->format('M') }}</span>
                        <span class="block text-2xl font-black text-surface-800 -mt-0.5">{{ \Carbon\Carbon::parse($event->date)->format('d') }}</span>
                    </div>
                    @endif
                </div>
                {{-- Content --}}
                <div class="p-5">
                    <h3 class="font-heading font-bold text-surface-800 group-hover:text-primary-500 transition-colors leading-snug mb-3">{{ Str::limit($event->title, 80) }}</h3>
                    <div class="space-y-1.5 text-sm text-surface-400">
                        @if($event->date)
                        <p class="flex items-center gap-2"><i class="fas fa-calendar-alt text-primary-400 w-4 text-center"></i>{{ \Carbon\Carbon::parse($event->date)->format('l, F j, Y') }}</p>
                        @endif
                        @if($event->time)
                        <p class="flex items-center gap-2"><i class="fas fa-clock text-primary-400 w-4 text-center"></i>{{ $event->time }}</p>
                        @endif
                        @if($event->address)
                        <p class="flex items-center gap-2"><i class="fas fa-map-marker-alt text-primary-400 w-4 text-center"></i>{{ Str::limit($event->address, 50) }}</p>
                        @endif
                    </div>
                    <p class="text-sm text-surface-500 mt-3 line-clamp-2">{{ Str::limit(strip_tags($event->description), 120) }}</p>
                    <span class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-500 mt-4 group-hover:gap-2.5 transition-all">View Details <i class="fas fa-arrow-right text-xs"></i></span>
                </div>
            </a>
            @endforeach
        </div>

        @if($events->hasPages())
        <div class="mt-12 flex justify-center" data-aos="fade-up">
            {{ $events->links('pagination::tailwind') }}
        </div>
        @endif

        @else
        <div class="text-center py-20">
            <i class="fas fa-calendar-xmark text-5xl text-surface-200 mb-4"></i>
            <h3 class="text-xl font-heading font-bold text-surface-600 mb-2">No Events Yet</h3>
            <p class="text-surface-400">Check back soon for upcoming events.</p>
        </div>
        @endif
    </div>
</section>

@endsection
