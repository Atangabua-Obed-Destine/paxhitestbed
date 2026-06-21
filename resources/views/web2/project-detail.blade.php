{{--
    ================================================================
    PROJECT DETAIL — PAX Higher Institute
    ================================================================
--}}
@extends('web2.layouts.master')

@section('title', ($project->title ?? 'Project') . ' — ' . ($setting->title ?? 'PAXHI'))
@section('description', Str::limit(strip_tags($project->description ?? ''), 160))

@section('content')

{{-- HERO --}}
<section class="relative min-h-[380px] lg:min-h-[420px] flex items-end overflow-hidden">
    @if($project->attach)
    <img src="{{ asset('uploads/projects/'.$project->attach) }}" alt="{{ $project->title }}" class="absolute inset-0 w-full h-full object-cover">
    @endif
    <div class="absolute inset-0 bg-gradient-to-t from-primary-900/95 via-primary-900/60 to-primary-900/30"></div>
    <div class="section-container relative z-10 pb-12 pt-32">
        <nav class="text-sm text-white/50 mb-5" data-aos="fade-up">
            <a href="{{ url('/') }}" class="hover:text-accent-400 transition-colors">Home</a>
            <span class="mx-2">/</span>
            <a href="{{ url('/projects') }}" class="hover:text-accent-400 transition-colors">Projects</a>
            <span class="mx-2">/</span>
            <span class="text-white/80">{{ Str::limit($project->title, 50) }}</span>
        </nav>
        <div class="flex flex-wrap gap-2 mb-4" data-aos="fade-up" data-aos-delay="50">
            @if($project->status)
            <span class="px-3 py-1 rounded-full text-xs font-bold capitalize {{ $project->status == 'ongoing' ? 'bg-green-500/20 text-green-300' : ($project->status == 'completed' ? 'bg-blue-500/20 text-blue-300' : 'bg-amber-500/20 text-amber-300') }}">{{ $project->status }}</span>
            @endif
            @if($project->featured)
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-accent-500/20 text-accent-300"><i class="fas fa-star mr-1"></i>Featured</span>
            @endif
            @if($project->theme)
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-white/15 text-white/80">{{ $project->theme }}</span>
            @endif
        </div>
        <h1 class="text-2xl md:text-3xl lg:text-4xl font-heading font-bold text-white leading-tight max-w-4xl" data-aos="fade-up" data-aos-delay="100">
            {{ $project->title }}
        </h1>
        <div class="flex flex-wrap gap-5 mt-5 text-sm text-white/60" data-aos="fade-up" data-aos-delay="150">
            @if($project->lead_researcher)
            <span class="flex items-center gap-2"><i class="fas fa-user-tie text-accent-400"></i>{{ $project->lead_researcher }}</span>
            @endif
            @if($project->faculty)
            <span class="flex items-center gap-2"><i class="fas fa-building-columns text-accent-400"></i>{{ $project->faculty->title }}</span>
            @endif
            @if($project->start_date)
            <span class="flex items-center gap-2"><i class="fas fa-calendar-alt text-accent-400"></i>{{ \Carbon\Carbon::parse($project->start_date)->format('M Y') }}{{ $project->end_date ? ' — ' . \Carbon\Carbon::parse($project->end_date)->format('M Y') : ' — Present' }}</span>
            @endif
        </div>
    </div>
</section>

{{-- CONTENT --}}
<section class="py-12 lg:py-16 bg-white">
    <div class="section-container">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
            {{-- Main --}}
            <div class="lg:col-span-2" data-aos="fade-up">
                @if($project->attach)
                <img src="{{ asset('uploads/projects/'.$project->attach) }}" alt="{{ $project->title }}" class="w-full rounded-2xl mb-8 shadow-sm" loading="lazy">
                @endif

                <div class="prose prose-lg prose-surface max-w-none [&>p]:mb-5 [&>p]:leading-relaxed [&>h2]:font-heading [&>h2]:text-2xl [&>h2]:mt-10 [&>h2]:mb-4">
                    {!! $project->description !!}
                </div>

                {{-- Share --}}
                <div class="mt-10 pt-8 border-t border-surface-100">
                    <h4 class="text-sm font-semibold text-surface-500 uppercase tracking-wider mb-3">Share This Project</h4>
                    <div class="flex gap-2">
                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(request()->url()) }}" target="_blank" rel="noopener"
                           class="w-10 h-10 rounded-full bg-blue-600 text-white flex items-center justify-center hover:bg-blue-700 transition-colors"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://twitter.com/intent/tweet?url={{ urlencode(request()->url()) }}&text={{ urlencode($project->title) }}" target="_blank" rel="noopener"
                           class="w-10 h-10 rounded-full bg-black text-white flex items-center justify-center hover:bg-gray-800 transition-colors"><i class="fab fa-x-twitter"></i></a>
                        <a href="https://wa.me/?text={{ urlencode($project->title . ' ' . request()->url()) }}" target="_blank" rel="noopener"
                           class="w-10 h-10 rounded-full bg-green-600 text-white flex items-center justify-center hover:bg-green-700 transition-colors"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                <a href="{{ url('/projects') }}" class="flex items-center gap-2 text-sm font-medium text-primary-500 hover:text-accent-500 transition-colors" data-aos="fade-left">
                    <i class="fas fa-arrow-left"></i> All Projects
                </a>

                {{-- Project Details --}}
                <div class="bg-white rounded-2xl border border-surface-100 shadow-sm overflow-hidden" data-aos="fade-left" data-aos-delay="50">
                    <div class="bg-surface-50 px-6 py-4 border-b border-surface-100">
                        <h3 class="font-heading font-bold text-surface-800 text-base">Project Details</h3>
                    </div>
                    <div class="divide-y divide-surface-100">
                        @if($project->status)
                        <div class="px-6 py-3 flex justify-between text-sm">
                            <span class="text-surface-500">Status</span>
                            <span class="font-semibold text-surface-800 capitalize">{{ $project->status }}</span>
                        </div>
                        @endif
                        @if($project->lead_researcher)
                        <div class="px-6 py-3 flex justify-between text-sm">
                            <span class="text-surface-500">Lead Researcher</span>
                            <span class="font-semibold text-surface-800">{{ $project->lead_researcher }}</span>
                        </div>
                        @endif
                        @if($project->faculty)
                        <div class="px-6 py-3 flex justify-between text-sm">
                            <span class="text-surface-500">Faculty</span>
                            <a href="{{ url('/faculties/'.$project->faculty->slug) }}" class="font-semibold text-primary-500 hover:text-accent-500 transition-colors">{{ $project->faculty->title }}</a>
                        </div>
                        @endif
                        @if($project->theme)
                        <div class="px-6 py-3 flex justify-between text-sm">
                            <span class="text-surface-500">Theme</span>
                            <span class="font-semibold text-surface-800">{{ $project->theme }}</span>
                        </div>
                        @endif
                        @if($project->start_date)
                        <div class="px-6 py-3 flex justify-between text-sm">
                            <span class="text-surface-500">Period</span>
                            <span class="font-semibold text-surface-800">{{ \Carbon\Carbon::parse($project->start_date)->format('M Y') }} — {{ $project->end_date ? \Carbon\Carbon::parse($project->end_date)->format('M Y') : 'Present' }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Related Projects --}}
                @if(isset($related) && $related->count())
                <div class="bg-surface-50 rounded-2xl p-6" data-aos="fade-left" data-aos-delay="100">
                    <h3 class="font-heading font-bold text-surface-800 mb-4 text-lg">Related Projects</h3>
                    <div class="space-y-4">
                        @foreach($related as $rel)
                        <a href="{{ url('/projects/'.$rel->id) }}" class="flex gap-3 group">
                            <div class="w-14 h-14 rounded-lg overflow-hidden flex-shrink-0">
                                @if($rel->attach)
                                <img src="{{ asset('uploads/projects/'.$rel->attach) }}" alt="{{ $rel->title }}" class="w-full h-full object-cover" loading="lazy">
                                @else
                                <div class="w-full h-full bg-surface-200 flex items-center justify-center"><i class="fas fa-flask text-surface-400 text-xs"></i></div>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-sm font-semibold text-surface-800 group-hover:text-primary-500 transition-colors line-clamp-2 leading-snug">{{ $rel->title }}</h4>
                                @if($rel->status)
                                <span class="text-xs capitalize text-surface-400">{{ $rel->status }}</span>
                                @endif
                            </div>
                        </a>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</section>

@endsection
