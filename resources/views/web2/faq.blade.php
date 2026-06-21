{{--
    ================================================================
    FAQ — PAX Higher Institute
    ================================================================
--}}
@extends('web2.layouts.master')

@section('title', 'Frequently Asked Questions — ' . ($setting->title ?? 'PAXHI'))
@section('description', 'Find answers to common questions about admissions, tuition, campus life, and more at PAX Higher Institute.')

@section('content')

{{-- HERO --}}
<section class="relative min-h-[340px] flex items-end overflow-hidden bg-primary-900">
    <div class="absolute inset-0 opacity-[0.04]" style="background-image:url('data:image/svg+xml,%3Csvg width=60 height=60 viewBox=%220 0 60 60%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cg fill=%22none%22 fill-rule=%22evenodd%22%3E%3Cg fill=%22%23ffffff%22 fill-opacity=%221%22%3E%3Cpath d=%22M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z%22/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')"></div>
    <div class="section-container relative z-10 pb-12 pt-32">
        <nav class="text-sm text-white/50 mb-5" data-aos="fade-up">
            <a href="{{ url('/') }}" class="hover:text-accent-400 transition-colors">Home</a>
            <span class="mx-2">/</span>
            <span class="text-white/80">FAQ</span>
        </nav>
        <h1 class="text-3xl md:text-4xl lg:text-5xl font-heading font-bold text-white" data-aos="fade-up" data-aos-delay="100">
            Frequently Asked Questions
        </h1>
        <p class="mt-4 text-lg text-white/70 max-w-2xl" data-aos="fade-up" data-aos-delay="150">
            Got questions? We've got answers. Find everything you need to know about PAXHI.
        </p>
    </div>
</section>

{{-- FAQ LIST --}}
<section class="py-16 lg:py-20 bg-white">
    <div class="section-container">
        @if($faqs->count())

        {{-- Search/filter --}}
        <div class="max-w-3xl mx-auto mb-10" data-aos="fade-up" x-data="{ search: '' }">
            <div class="relative">
                <i class="fas fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-surface-400"></i>
                <input type="text" x-model="search" placeholder="Search questions..."
                       class="w-full pl-11 pr-4 py-3 bg-surface-50 border border-surface-200 rounded-xl text-surface-700 placeholder:text-surface-400 focus:ring-2 focus:ring-primary-300 focus:border-primary-400 outline-none transition-all">
            </div>
        </div>

        <div class="max-w-3xl mx-auto space-y-3" x-data="{ active: 0 }">
            @foreach($faqs as $idx => $faq)
            <div class="bg-white rounded-xl border border-surface-100 overflow-hidden" data-aos="fade-up" data-aos-delay="{{ min($idx * 40, 300) }}">
                <button @click="active = active === {{ $idx }} ? null : {{ $idx }}"
                        class="w-full flex items-center justify-between p-5 text-left hover:bg-surface-50 transition-colors group">
                    <span class="flex items-center gap-3 font-semibold text-surface-800 pr-4">
                        <span class="w-8 h-8 rounded-lg bg-primary-50 text-primary-500 flex items-center justify-center flex-shrink-0 text-sm group-hover:bg-primary-100 transition-colors">
                            @if($faq->icon)<i class="fas {{ $faq->icon }}"></i>@else {{ $idx + 1 }} @endif
                        </span>
                        {{ $faq->title }}
                    </span>
                    <i class="fas fa-chevron-down text-surface-400 transition-transform duration-300 flex-shrink-0" :class="{ 'rotate-180': active === {{ $idx }} }"></i>
                </button>
                <div x-show="active === {{ $idx }}" x-collapse>
                    <div class="px-5 pb-5 text-surface-500 text-sm leading-relaxed border-t border-surface-50 pt-4 ml-11">
                        {!! $faq->description !!}
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        @else
        <div class="text-center py-20">
            <i class="fas fa-circle-question text-5xl text-surface-200 mb-4"></i>
            <h3 class="text-xl font-heading font-bold text-surface-600 mb-2">No FAQs Available</h3>
            <p class="text-surface-400">Check back soon.</p>
        </div>
        @endif

        {{-- Still have questions? --}}
        <div class="max-w-3xl mx-auto mt-14 text-center bg-surface-50 rounded-2xl p-8 border border-surface-100" data-aos="fade-up">
            <i class="fas fa-headset text-3xl text-primary-400 mb-3"></i>
            <h3 class="font-heading font-bold text-surface-800 text-xl mb-2">Still Have Questions?</h3>
            <p class="text-surface-500 mb-5">Our team is here to help. Reach out to us and we'll get back to you as soon as possible.</p>
            <div class="flex flex-wrap justify-center gap-3">
                @if(isset($setting) && $setting->phone)
                <a href="tel:{{ $setting->phone }}" class="btn-primary"><i class="fas fa-phone-alt mr-2"></i>Call Us</a>
                @endif
                @if(isset($setting) && $setting->email)
                <a href="mailto:{{ $setting->email }}" class="border-2 border-primary-200 text-primary-600 hover:bg-primary-50 px-5 py-2.5 rounded-lg font-medium transition-colors"><i class="fas fa-envelope mr-2"></i>Email Us</a>
                @endif
            </div>
        </div>
    </div>
</section>

@endsection
