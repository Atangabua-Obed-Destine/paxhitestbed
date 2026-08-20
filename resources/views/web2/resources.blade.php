{{--
    ================================================================
    DOWNLOADS / RESOURCES — {{ institution_name() }}
    ================================================================
--}}
@extends('web2.layouts.master')

@section('title', 'Downloads & Resources — ' . (institution_code()))
@section('description', 'Download student guides, academic calendars, forms, policies and more from ' . institution_name() . '.')

@section('content')

{{-- HERO --}}
<section class="relative min-h-[340px] flex items-end overflow-hidden bg-primary-900">
    <div class="absolute inset-0 opacity-[0.04]" style="background-image:url('data:image/svg+xml,%3Csvg width=60 height=60 viewBox=%220 0 60 60%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cg fill=%22none%22 fill-rule=%22evenodd%22%3E%3Cg fill=%22%23ffffff%22 fill-opacity=%221%22%3E%3Cpath d=%22M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z%22/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')"></div>
    <div class="section-container relative z-10 pb-12 pt-32">
        <nav class="text-sm text-white/50 mb-5" data-aos="fade-up">
            <a href="{{ url('/') }}" class="hover:text-accent-400 transition-colors">Home</a>
            <span class="mx-2">/</span>
            <span class="text-white/80">Downloads</span>
        </nav>
        <h1 class="text-3xl md:text-4xl lg:text-5xl font-heading font-bold text-white" data-aos="fade-up" data-aos-delay="100">
            Downloads & Resources
        </h1>
        <p class="mt-4 text-lg text-white/70 max-w-2xl" data-aos="fade-up" data-aos-delay="150">
            Access student guides, academic calendars, application forms, and institutional policies.
        </p>
    </div>
</section>

{{-- FEATURED RESOURCES --}}
@if(isset($featured_resources) && $featured_resources->count())
<section class="py-12 bg-white border-b border-surface-100">
    <div class="section-container">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-{{ min($featured_resources->count(), 3) }} gap-5 max-w-4xl mx-auto">
            @foreach($featured_resources as $idx => $fr)
            <a href="{{ route('resource.download', $fr) }}"
               class="group flex items-center gap-4 bg-gradient-to-br from-primary-50 to-primary-100 rounded-xl p-5 border border-primary-100 hover:shadow-lg transition-all"
               data-aos="fade-up" data-aos-delay="{{ $idx * 80 }}">
                <div class="w-14 h-14 rounded-xl bg-white shadow-sm flex items-center justify-center flex-shrink-0">
                    <i class="fas {{ $fr->file_type == 'pdf' ? 'fa-file-pdf text-red-500' : 'fa-file-alt text-primary-500' }} text-2xl"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="font-heading font-bold text-surface-800 text-sm group-hover:text-primary-600 transition-colors">{{ $fr->title }}</h3>
                    <p class="text-xs text-surface-400 mt-0.5">{{ strtoupper($fr->file_type ?? 'FILE') }} @if($fr->formatted_file_size) &middot; {{ $fr->formatted_file_size }} @endif</p>
                </div>
                <i class="fas fa-download text-primary-300 group-hover:text-primary-500 transition-colors flex-shrink-0"></i>
            </a>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ALL RESOURCES BY CATEGORY --}}
<section class="py-16 lg:py-20 bg-surface-50">
    <div class="section-container">
        @if(isset($grouped_resources) && $grouped_resources->count())
        @php
            $categoryLabels = [
                'student_guide' => ['label' => 'Student Guides', 'icon' => 'fa-book-open', 'color' => 'primary'],
                'calendarium' => ['label' => 'Academic Calendar', 'icon' => 'fa-calendar-alt', 'color' => 'accent'],
                'academic' => ['label' => 'Academic Documents', 'icon' => 'fa-graduation-cap', 'color' => 'secondary'],
                'forms' => ['label' => 'Forms & Applications', 'icon' => 'fa-file-lines', 'color' => 'primary'],
                'policies' => ['label' => 'Policies & Regulations', 'icon' => 'fa-shield-halved', 'color' => 'accent'],
                'handbook' => ['label' => 'Handbooks', 'icon' => 'fa-book', 'color' => 'secondary'],
                'other' => ['label' => 'Other Resources', 'icon' => 'fa-folder', 'color' => 'primary'],
            ];
        @endphp

        @foreach($grouped_resources as $category => $resources)
        @php $cat = $categoryLabels[$category] ?? $categoryLabels['other']; @endphp
        <div class="mb-12 last:mb-0" data-aos="fade-up">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 rounded-lg bg-{{ $cat['color'] }}-50 flex items-center justify-center text-{{ $cat['color'] }}-500">
                    <i class="fas {{ $cat['icon'] }}"></i>
                </div>
                <h2 class="text-xl font-heading font-bold text-surface-800">{{ $cat['label'] }}</h2>
                <span class="text-xs text-surface-400 bg-surface-100 px-2 py-0.5 rounded-full">{{ $resources->count() }}</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($resources as $resource)
                <div class="group bg-white rounded-xl border border-surface-100 hover:border-primary-200 hover:shadow-md p-5 transition-all">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-lg {{ $resource->file_type == 'pdf' ? 'bg-red-50 text-red-500' : ($resource->file_type == 'doc' || $resource->file_type == 'docx' ? 'bg-blue-50 text-blue-500' : 'bg-surface-100 text-surface-500') }} flex items-center justify-center flex-shrink-0">
                            <i class="fas {{ $resource->file_type == 'pdf' ? 'fa-file-pdf' : ($resource->file_type == 'doc' || $resource->file_type == 'docx' ? 'fa-file-word' : 'fa-file') }}"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="font-semibold text-surface-800 text-sm group-hover:text-primary-500 transition-colors">{{ $resource->title }}</h4>
                            <div class="flex items-center gap-2 text-xs text-surface-400 mt-1">
                                <span>{{ strtoupper($resource->file_type ?? 'FILE') }}</span>
                                @if($resource->formatted_file_size) <span>&middot;</span> <span>{{ $resource->formatted_file_size }}</span> @endif
                                @if($resource->download_count) <span>&middot;</span> <span>{{ $resource->download_count }} downloads</span> @endif
                            </div>
                            @if($resource->description)
                            <p class="text-xs text-surface-500 mt-2 line-clamp-2">{{ $resource->description }}</p>
                            @endif
                        </div>
                    </div>
                    <a href="{{ route('resource.download', $resource) }}"
                       class="mt-4 flex items-center justify-center gap-2 text-sm font-medium text-primary-500 bg-primary-50 hover:bg-primary-100 rounded-lg py-2 transition-colors">
                        <i class="fas fa-download"></i> Download
                    </a>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach

        @else
        <div class="text-center py-20 bg-white rounded-2xl border border-surface-100">
            <i class="fas fa-folder-open text-5xl text-surface-200 mb-4"></i>
            <h3 class="text-xl font-heading font-bold text-surface-600 mb-2">No Resources Available</h3>
            <p class="text-surface-400">Check back soon for downloadable documents.</p>
        </div>
        @endif
    </div>
</section>

@endsection
