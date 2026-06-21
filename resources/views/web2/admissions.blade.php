{{--
    ================================================================
    ADMISSIONS — PAX Higher Institute
    ================================================================
--}}
@extends('web2.layouts.master')

@section('title', 'Admissions — ' . ($setting->title ?? 'PAXHI'))
@section('description', 'Begin your journey at PAX Higher Institute. Find admission requirements, key dates, downloadable forms, and answers to frequently asked questions.')

@section('content')

{{-- HERO --}}
<section class="relative min-h-[360px] lg:min-h-[400px] flex items-end overflow-hidden bg-primary-900">
    <div class="absolute inset-0 opacity-10">
        <svg class="absolute top-10 right-10 w-72 h-72 text-white" fill="currentColor" viewBox="0 0 512 512"><path d="M233.4 105.4c12.5-12.5 32.8-12.5 45.3 0l192 192c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L256 173.3 86.6 342.6c-12.5 12.5-32.8 12.5-45.3 0s-12.5-32.8 0-45.3l192-192z"/></svg>
    </div>
    <div class="section-container relative z-10 pb-12 pt-32">
        <nav class="text-sm text-white/50 mb-5" data-aos="fade-up">
            <a href="{{ url('/') }}" class="hover:text-accent-400 transition-colors">Home</a>
            <span class="mx-2">/</span>
            <span class="text-white/80">Admissions</span>
        </nav>
        <h1 class="text-3xl md:text-4xl lg:text-5xl font-heading font-bold text-white" data-aos="fade-up" data-aos-delay="100">
            Admissions
        </h1>
        <p class="mt-4 text-lg text-white/70 max-w-2xl" data-aos="fade-up" data-aos-delay="150">
            Your future begins here. Explore important dates, requirements, and everything you need to apply.
        </p>
    </div>
</section>

{{-- QUICK-ACTION BAR --}}
<section class="bg-white border-b border-surface-100 sticky top-[60px] z-30 shadow-sm">
    <div class="section-container">
        <div class="flex gap-4 overflow-x-auto py-3 text-sm font-medium scrollbar-thin">
            <a href="#dates" class="flex items-center gap-2 px-4 py-2 rounded-full bg-primary-50 text-primary-600 hover:bg-primary-100 whitespace-nowrap transition-colors"><i class="fas fa-calendar-alt"></i> Key Dates</a>
            <a href="#requirements" class="flex items-center gap-2 px-4 py-2 rounded-full bg-surface-50 text-surface-600 hover:bg-primary-50 whitespace-nowrap transition-colors"><i class="fas fa-list-check"></i> Requirements</a>
            <a href="#downloads" class="flex items-center gap-2 px-4 py-2 rounded-full bg-surface-50 text-surface-600 hover:bg-primary-50 whitespace-nowrap transition-colors"><i class="fas fa-download"></i> Downloads</a>
            <a href="#faq" class="flex items-center gap-2 px-4 py-2 rounded-full bg-surface-50 text-surface-600 hover:bg-primary-50 whitespace-nowrap transition-colors"><i class="fas fa-circle-question"></i> FAQ</a>
            <a href="{{ url('/application') }}" class="flex items-center gap-2 px-4 py-2 rounded-full bg-accent-500 text-white hover:bg-accent-600 whitespace-nowrap transition-colors ml-auto"><i class="fas fa-pen-to-square"></i> Apply Online</a>
        </div>
    </div>
</section>

{{-- KEY DATES --}}
<section id="dates" class="py-16 lg:py-20 bg-white scroll-mt-28">
    <div class="section-container">
        <div class="text-center mb-12" data-aos="fade-up">
            <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary-50 text-primary-600 text-xs font-semibold uppercase tracking-wider mb-4">
                <i class="fas fa-calendar-alt"></i> Admission Calendar
            </span>
            <h2 class="text-3xl md:text-4xl font-heading font-bold text-surface-800">Key Dates & Deadlines</h2>
            <p class="mt-3 text-surface-500 max-w-xl mx-auto">Mark these important dates on your calendar to stay on track with your application.</p>
        </div>

        @if($admission_dates->count())
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($admission_dates as $idx => $date)
            <div class="group bg-white rounded-2xl border border-surface-100 hover:border-primary-200 hover:shadow-lg p-6 transition-all"
                 data-aos="fade-up" data-aos-delay="{{ $idx * 60 }}">
                <div class="flex items-start gap-4">
                    <div class="flex flex-col items-center justify-center w-16 h-16 rounded-xl {{ $date->event_date && $date->event_date->isFuture() ? 'bg-accent-50 text-accent-600' : 'bg-surface-100 text-surface-400' }} flex-shrink-0">
                        @if($date->event_date)
                        <span class="text-xs font-bold uppercase leading-none">{{ $date->event_date->format('M') }}</span>
                        <span class="text-xl font-black leading-none mt-0.5">{{ $date->event_date->format('d') }}</span>
                        @else
                        <i class="fas fa-calendar text-xl"></i>
                        @endif
                    </div>
                    <div>
                        <h3 class="font-semibold text-surface-800 group-hover:text-primary-500 transition-colors">{{ $date->event_title }}</h3>
                        @if($date->event_date)
                        <p class="text-sm text-surface-400 mt-1">{{ $date->event_date->format('l, F j, Y') }}</p>
                        @endif
                        @if($date->description)
                        <p class="text-sm text-surface-500 mt-2 leading-relaxed">{{ $date->description }}</p>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="text-center py-12 text-surface-400">
            <i class="fas fa-calendar-xmark text-4xl mb-3"></i>
            <p>No admission dates published yet.</p>
        </div>
        @endif
    </div>
</section>

{{-- HOW TO APPLY (Step Process) --}}
<section id="requirements" class="py-16 lg:py-20 bg-surface-50 scroll-mt-28">
    <div class="section-container">
        <div class="text-center mb-12" data-aos="fade-up">
            <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-secondary-50 text-secondary-600 text-xs font-semibold uppercase tracking-wider mb-4">
                <i class="fas fa-list-ol"></i> Application Guide
            </span>
            <h2 class="text-3xl md:text-4xl font-heading font-bold text-surface-800">How to Apply</h2>
            <p class="mt-3 text-surface-500 max-w-xl mx-auto">Follow these simple steps to complete your application to PAX Higher Institute.</p>
        </div>

        <div class="max-w-3xl mx-auto relative">
            {{-- Vertical Line --}}
            <div class="hidden md:block absolute left-8 top-0 bottom-0 w-0.5 bg-surface-200"></div>

            @php
                $steps = [
                    ['icon' => 'fa-magnifying-glass', 'title' => 'Explore Programs', 'desc' => 'Browse our academic programs to find the right fit for your career goals and interests.'],
                    ['icon' => 'fa-file-lines', 'title' => 'Gather Documents', 'desc' => 'Prepare your academic transcripts, birth certificate, passport photos, and any other required documents.'],
                    ['icon' => 'fa-pen-to-square', 'title' => 'Complete Application', 'desc' => 'Fill out the online application form or download and submit the paper form at our admissions office.'],
                    ['icon' => 'fa-money-bill-wave', 'title' => 'Pay Application Fee', 'desc' => 'Submit the non-refundable application processing fee via bank transfer or mobile money.'],
                    ['icon' => 'fa-clock', 'title' => 'Await Decision', 'desc' => 'Our admissions committee will review your application and notify you of the decision.'],
                    ['icon' => 'fa-user-check', 'title' => 'Enroll & Register', 'desc' => 'Once admitted, complete your enrollment and register for your first semester courses.'],
                ];
            @endphp

            @foreach($steps as $idx => $step)
            <div class="flex items-start gap-4 md:gap-6 mb-8 last:mb-0 relative" data-aos="fade-up" data-aos-delay="{{ $idx * 80 }}">
                <div class="w-16 h-16 rounded-full bg-white border-2 border-primary-200 flex items-center justify-center flex-shrink-0 text-primary-500 font-bold text-xl z-10 shadow-sm">
                    {{ $idx + 1 }}
                </div>
                <div class="bg-white rounded-xl p-5 border border-surface-100 shadow-sm flex-1 hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3 mb-2">
                        <i class="fas {{ $step['icon'] }} text-accent-500"></i>
                        <h3 class="font-heading font-bold text-surface-800">{{ $step['title'] }}</h3>
                    </div>
                    <p class="text-sm text-surface-500 leading-relaxed">{{ $step['desc'] }}</p>
                </div>
            </div>
            @endforeach
        </div>

        <div class="text-center mt-12" data-aos="fade-up">
            <a href="{{ url('/application') }}" class="btn-primary">
                <i class="fas fa-pen-to-square mr-2"></i> Start Your Application
            </a>
        </div>
    </div>
</section>

{{-- DOWNLOADABLE RESOURCES --}}
<section id="downloads" class="py-16 lg:py-20 bg-white scroll-mt-28">
    <div class="section-container">
        <div class="text-center mb-12" data-aos="fade-up">
            <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-accent-50 text-accent-600 text-xs font-semibold uppercase tracking-wider mb-4">
                <i class="fas fa-folder-open"></i> Resources
            </span>
            <h2 class="text-3xl md:text-4xl font-heading font-bold text-surface-800">Downloadable Forms & Guides</h2>
            <p class="mt-3 text-surface-500 max-w-xl mx-auto">Everything you need to prepare and complete your application.</p>
        </div>

        @if(isset($download_resources) && $download_resources->count())
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 max-w-5xl mx-auto">
            @foreach($download_resources as $idx => $resource)
            <div class="group bg-surface-50 hover:bg-white rounded-xl border border-surface-100 hover:border-primary-200 hover:shadow-md p-5 transition-all"
                 data-aos="fade-up" data-aos-delay="{{ $idx * 60 }}">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-lg {{ $resource->file_type == 'pdf' ? 'bg-red-50 text-red-500' : ($resource->file_type == 'doc' || $resource->file_type == 'docx' ? 'bg-blue-50 text-blue-500' : 'bg-surface-100 text-surface-500') }} flex items-center justify-center flex-shrink-0">
                        <i class="fas {{ $resource->file_type == 'pdf' ? 'fa-file-pdf' : ($resource->file_type == 'doc' || $resource->file_type == 'docx' ? 'fa-file-word' : 'fa-file') }} text-xl"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="font-semibold text-surface-800 text-sm group-hover:text-primary-500 transition-colors truncate">{{ $resource->title }}</h4>
                        <p class="text-xs text-surface-400 mt-1">
                            {{ strtoupper($resource->file_type ?? 'FILE') }}
                            @if($resource->formatted_file_size) &middot; {{ $resource->formatted_file_size }} @endif
                        </p>
                        @if($resource->description)
                        <p class="text-xs text-surface-500 mt-1 line-clamp-2">{{ $resource->description }}</p>
                        @endif
                    </div>
                </div>
                <a href="{{ route('resource.download', $resource) }}" class="mt-4 flex items-center justify-center gap-2 text-sm font-medium text-primary-500 hover:text-accent-500 bg-white border border-surface-200 rounded-lg py-2 transition-colors">
                    <i class="fas fa-download"></i> Download
                </a>
            </div>
            @endforeach
        </div>
        @else
        <div class="text-center py-8 text-surface-400">
            <i class="fas fa-folder-open text-3xl mb-3"></i>
            <p>No downloadable resources available at this time.</p>
        </div>
        @endif
    </div>
</section>

{{-- FAQ --}}
<section id="faq" class="py-16 lg:py-20 bg-surface-50 scroll-mt-28">
    <div class="section-container">
        <div class="text-center mb-12" data-aos="fade-up">
            <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary-50 text-primary-600 text-xs font-semibold uppercase tracking-wider mb-4">
                <i class="fas fa-circle-question"></i> FAQ
            </span>
            <h2 class="text-3xl md:text-4xl font-heading font-bold text-surface-800">Frequently Asked Questions</h2>
            <p class="mt-3 text-surface-500 max-w-xl mx-auto">Find quick answers to common questions about admissions, fees, and student life.</p>
        </div>

        @if($faqs->count())
        <div class="max-w-3xl mx-auto space-y-3" x-data="{ active: null }">
            @foreach($faqs as $idx => $faq)
            <div class="bg-white rounded-xl border border-surface-100 overflow-hidden" data-aos="fade-up" data-aos-delay="{{ min($idx * 50, 300) }}">
                <button @click="active = active === {{ $idx }} ? null : {{ $idx }}"
                        class="w-full flex items-center justify-between p-5 text-left hover:bg-surface-50 transition-colors">
                    <span class="flex items-center gap-3 font-semibold text-surface-800 pr-4">
                        @if($faq->icon)<i class="fas {{ $faq->icon }} text-accent-500 w-5 text-center"></i>@endif
                        {{ $faq->title }}
                    </span>
                    <i class="fas fa-chevron-down text-surface-400 transition-transform duration-300" :class="{ 'rotate-180': active === {{ $idx }} }"></i>
                </button>
                <div x-show="active === {{ $idx }}" x-collapse>
                    <div class="px-5 pb-5 text-surface-500 text-sm leading-relaxed border-t border-surface-50 pt-4">
                        {!! $faq->description !!}
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="text-center py-8 text-surface-400">
            <i class="fas fa-circle-question text-3xl mb-3"></i>
            <p>No FAQs available at this time.</p>
        </div>
        @endif
    </div>
</section>

{{-- CTA --}}
<section class="py-16 bg-gradient-to-r from-primary-600 to-primary-800 relative overflow-hidden">
    <div class="absolute inset-0 opacity-5">
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-[400px] text-white"><i class="fas fa-cross"></i></div>
    </div>
    <div class="section-container text-center relative z-10">
        <h2 class="text-3xl font-heading font-bold text-white mb-4" data-aos="fade-up">Ready to Begin Your Journey?</h2>
        <p class="text-lg text-white/70 max-w-xl mx-auto mb-8" data-aos="fade-up" data-aos-delay="100">
            Take the first step towards a transformative education rooted in faith and excellence.
        </p>
        <div class="flex flex-wrap justify-center gap-4" data-aos="fade-up" data-aos-delay="150">
            <a href="{{ url('/application') }}" class="btn-accent"><i class="fas fa-pen-to-square mr-2"></i> Apply Now</a>
            <a href="{{ url('/programs') }}" class="border-2 border-white/30 text-white hover:bg-white/10 px-6 py-3 rounded-lg font-medium transition-colors"><i class="fas fa-book-open mr-2"></i> Browse Programs</a>
        </div>
    </div>
</section>

@endsection
