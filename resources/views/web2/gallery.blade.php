{{--
    ================================================================
    GALLERY — PAX Higher Institute
    Masonry-style lightbox gallery with Alpine.js
    ================================================================
--}}
@extends('web2.layouts.master')

@section('title', 'Gallery — ' . ($setting->title ?? 'PAXHI'))
@section('description', 'Explore our photo gallery — campus life, academic events, ceremonies, and community moments at PAX Higher Institute.')

@section('content')

{{-- HERO --}}
<section class="relative min-h-[340px] flex items-end overflow-hidden bg-primary-900">
    <div class="absolute inset-0 opacity-[0.04]" style="background-image:url('data:image/svg+xml,%3Csvg width=60 height=60 viewBox=%220 0 60 60%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cg fill=%22none%22 fill-rule=%22evenodd%22%3E%3Cg fill=%22%23ffffff%22 fill-opacity=%221%22%3E%3Cpath d=%22M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z%22/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')"></div>
    <div class="section-container relative z-10 pb-12 pt-32">
        <nav class="text-sm text-white/50 mb-5" data-aos="fade-up">
            <a href="{{ url('/') }}" class="hover:text-accent-400 transition-colors">Home</a>
            <span class="mx-2">/</span>
            <span class="text-white/80">Gallery</span>
        </nav>
        <h1 class="text-3xl md:text-4xl lg:text-5xl font-heading font-bold text-white" data-aos="fade-up" data-aos-delay="100">
            Photo Gallery
        </h1>
        <p class="mt-4 text-lg text-white/70 max-w-2xl" data-aos="fade-up" data-aos-delay="150">
            A visual journey through our campus — capturing moments of learning, faith, and community.
        </p>
    </div>
</section>

{{-- GALLERY GRID --}}
<section class="py-16 lg:py-20 bg-white"
    x-data="{
        lightbox: false,
        current: 0,
        images: [
            @foreach($galleries as $g)
            { src: '{{ asset('uploads/gallery/'.$g->attach) }}', title: '{{ addslashes($g->title) }}' },
            @endforeach
        ],
        open(idx) { this.current = idx; this.lightbox = true; document.body.style.overflow = 'hidden'; },
        close() { this.lightbox = false; document.body.style.overflow = ''; },
        prev() { this.current = this.current > 0 ? this.current - 1 : this.images.length - 1; },
        next() { this.current = this.current < this.images.length - 1 ? this.current + 1 : 0; }
    }"
    @keydown.escape.window="close()"
    @keydown.left.window="if(lightbox) prev()"
    @keydown.right.window="if(lightbox) next()"
>
    <div class="section-container">
        @if($galleries->count())

        {{-- Counter --}}
        <p class="text-surface-400 text-sm mb-6" data-aos="fade-up">{{ $galleries->count() }} photo{{ $galleries->count() != 1 ? 's' : '' }}</p>

        {{-- Masonry-ish grid --}}
        <div class="columns-1 sm:columns-2 lg:columns-3 xl:columns-4 gap-4 space-y-4">
            @foreach($galleries as $idx => $gallery)
            <div class="break-inside-avoid cursor-pointer group" @click="open({{ $idx }})" data-aos="fade-up" data-aos-delay="{{ min($idx * 50, 300) }}">
                <div class="relative rounded-xl overflow-hidden">
                    <img src="{{ asset('uploads/gallery/'.$gallery->attach) }}" alt="{{ $gallery->title }}"
                         class="w-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end p-4">
                        <div>
                            <p class="text-white font-semibold text-sm leading-snug">{{ $gallery->title }}</p>
                            <span class="text-white/70 text-xs flex items-center gap-1 mt-1"><i class="fas fa-expand-alt"></i> Click to enlarge</span>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Lightbox --}}
        <div x-show="lightbox" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/90 backdrop-blur-sm p-4" style="display:none;">
            {{-- Close --}}
            <button @click="close()" class="absolute top-4 right-4 z-10 w-10 h-10 rounded-full bg-white/10 text-white flex items-center justify-center hover:bg-white/20 transition-colors text-xl">
                <i class="fas fa-times"></i>
            </button>
            {{-- Prev --}}
            <button @click="prev()" class="absolute left-4 top-1/2 -translate-y-1/2 z-10 w-12 h-12 rounded-full bg-white/10 text-white flex items-center justify-center hover:bg-white/20 transition-colors">
                <i class="fas fa-chevron-left"></i>
            </button>
            {{-- Next --}}
            <button @click="next()" class="absolute right-4 top-1/2 -translate-y-1/2 z-10 w-12 h-12 rounded-full bg-white/10 text-white flex items-center justify-center hover:bg-white/20 transition-colors">
                <i class="fas fa-chevron-right"></i>
            </button>
            {{-- Image --}}
            <div class="max-w-5xl max-h-[85vh] flex flex-col items-center" @click.outside="close()">
                <img :src="images[current]?.src" :alt="images[current]?.title" class="max-w-full max-h-[75vh] object-contain rounded-lg shadow-2xl">
                <p class="text-white/80 text-sm mt-4 text-center" x-text="images[current]?.title"></p>
                <p class="text-white/40 text-xs mt-1" x-text="(current + 1) + ' / ' + images.length"></p>
            </div>
        </div>

        @else
        <div class="text-center py-20">
            <i class="fas fa-images text-5xl text-surface-200 mb-4"></i>
            <h3 class="text-xl font-heading font-bold text-surface-600 mb-2">No Photos Yet</h3>
            <p class="text-surface-400">Check back soon for new gallery additions.</p>
        </div>
        @endif
    </div>
</section>

@endsection
