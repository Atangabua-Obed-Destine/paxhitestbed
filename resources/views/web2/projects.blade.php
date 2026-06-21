{{--
    ================================================================
    PROJECTS LISTING — PAX Higher Institute
    ================================================================
--}}
@extends('web2.layouts.master')

@section('title', 'Research & Projects — ' . ($setting->title ?? 'PAXHI'))
@section('description', 'Explore research projects and academic initiatives at PAX Higher Institute — driving innovation and community impact.')

@section('content')

{{-- HERO --}}
<section class="relative min-h-[340px] flex items-end overflow-hidden bg-primary-900">
    <div class="absolute inset-0 opacity-[0.04]" style="background-image:url('data:image/svg+xml,%3Csvg width=60 height=60 viewBox=%220 0 60 60%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cg fill=%22none%22 fill-rule=%22evenodd%22%3E%3Cg fill=%22%23ffffff%22 fill-opacity=%221%22%3E%3Cpath d=%22M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z%22/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')"></div>
    <div class="section-container relative z-10 pb-12 pt-32">
        <nav class="text-sm text-white/50 mb-5" data-aos="fade-up">
            <a href="{{ url('/') }}" class="hover:text-accent-400 transition-colors">Home</a>
            <span class="mx-2">/</span>
            <span class="text-white/80">Projects</span>
        </nav>
        <h1 class="text-3xl md:text-4xl lg:text-5xl font-heading font-bold text-white" data-aos="fade-up" data-aos-delay="100">
            Research & Projects
        </h1>
        <p class="mt-4 text-lg text-white/70 max-w-2xl" data-aos="fade-up" data-aos-delay="150">
            Academic research and community-driven projects that make a real-world difference.
        </p>
    </div>
</section>

{{-- FILTER BAR --}}
<section class="bg-white border-b border-surface-100 sticky top-[60px] z-30 shadow-sm">
    <div class="section-container">
        <form method="GET" action="{{ url('/projects') }}" class="flex flex-wrap items-center gap-3 py-3">
            {{-- Faculty --}}
            <select name="faculty" onchange="this.form.submit()" class="text-sm border border-surface-200 rounded-lg px-3 py-2 bg-white text-surface-700 focus:ring-2 focus:ring-primary-300 focus:border-primary-400 outline-none">
                <option value="">All Faculties</option>
                @foreach($faculties as $f)
                <option value="{{ $f->id }}" {{ request('faculty') == $f->id ? 'selected' : '' }}>{{ $f->title }}</option>
                @endforeach
            </select>
            {{-- Theme --}}
            @if($themes->count())
            <select name="theme" onchange="this.form.submit()" class="text-sm border border-surface-200 rounded-lg px-3 py-2 bg-white text-surface-700 focus:ring-2 focus:ring-primary-300 focus:border-primary-400 outline-none">
                <option value="">All Themes</option>
                @foreach($themes as $t)
                <option value="{{ $t }}" {{ request('theme') == $t ? 'selected' : '' }}>{{ $t }}</option>
                @endforeach
            </select>
            @endif
            {{-- Status --}}
            <select name="status" onchange="this.form.submit()" class="text-sm border border-surface-200 rounded-lg px-3 py-2 bg-white text-surface-700 focus:ring-2 focus:ring-primary-300 focus:border-primary-400 outline-none">
                <option value="">All Status</option>
                <option value="ongoing" {{ request('status') == 'ongoing' ? 'selected' : '' }}>Ongoing</option>
                <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="planned" {{ request('status') == 'planned' ? 'selected' : '' }}>Planned</option>
            </select>
            @if(request('faculty') || request('theme') || request('status'))
            <a href="{{ url('/projects') }}" class="text-sm text-surface-400 hover:text-red-500 transition-colors"><i class="fas fa-times mr-1"></i>Clear</a>
            @endif
            <span class="ml-auto text-xs text-surface-400">{{ $projects->total() }} project{{ $projects->total() != 1 ? 's' : '' }}</span>
        </form>
    </div>
</section>

{{-- PROJECTS GRID --}}
<section class="py-16 lg:py-20 bg-surface-50">
    <div class="section-container">
        @if($projects->count())
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($projects as $idx => $project)
            <a href="{{ url('/projects/'.$project->id) }}"
               class="group bg-white rounded-2xl border border-surface-100 hover:border-primary-200 hover:shadow-xl overflow-hidden transition-all"
               data-aos="fade-up" data-aos-delay="{{ min($idx * 70, 350) }}">
                {{-- Image --}}
                <div class="relative aspect-[16/10] overflow-hidden">
                    @if($project->attach)
                    <img src="{{ asset('uploads/projects/'.$project->attach) }}" alt="{{ $project->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                    @else
                    <div class="w-full h-full bg-gradient-to-br from-primary-50 to-primary-100 flex items-center justify-center"><i class="fas fa-flask text-3xl text-primary-200"></i></div>
                    @endif
                    {{-- Status Badge --}}
                    @if($project->status)
                    <span class="absolute top-3 right-3 px-2.5 py-1 rounded-full text-xs font-bold capitalize
                        {{ $project->status == 'ongoing' ? 'bg-green-100 text-green-700' : ($project->status == 'completed' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700') }}">
                        {{ $project->status }}
                    </span>
                    @endif
                    @if($project->featured)
                    <span class="absolute top-3 left-3 px-2.5 py-1 rounded-full text-xs font-bold bg-accent-100 text-accent-700"><i class="fas fa-star mr-1"></i>Featured</span>
                    @endif
                </div>
                {{-- Content --}}
                <div class="p-5">
                    @if($project->theme)
                    <span class="text-xs font-semibold uppercase tracking-wider text-secondary-500 mb-2 block">{{ $project->theme }}</span>
                    @endif
                    <h3 class="font-heading font-bold text-surface-800 group-hover:text-primary-500 transition-colors leading-snug mb-2">{{ Str::limit($project->title, 80) }}</h3>
                    <p class="text-sm text-surface-500 line-clamp-2 mb-3">{{ Str::limit(strip_tags($project->description), 120) }}</p>
                    <div class="flex flex-wrap gap-3 text-xs text-surface-400">
                        @if($project->lead_researcher)
                        <span><i class="fas fa-user-tie mr-1 text-primary-400"></i>{{ $project->lead_researcher }}</span>
                        @endif
                        @if($project->faculty)
                        <span><i class="fas fa-building-columns mr-1 text-primary-400"></i>{{ Str::limit($project->faculty->title, 30) }}</span>
                        @endif
                    </div>
                    <span class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-500 mt-4 group-hover:gap-2.5 transition-all">View Project <i class="fas fa-arrow-right text-xs"></i></span>
                </div>
            </a>
            @endforeach
        </div>

        @if($projects->hasPages())
        <div class="mt-12 flex justify-center" data-aos="fade-up">
            {{ $projects->appends(request()->query())->links('pagination::tailwind') }}
        </div>
        @endif

        @else
        <div class="text-center py-20 bg-white rounded-2xl border border-surface-100">
            <i class="fas fa-flask text-5xl text-surface-200 mb-4"></i>
            <h3 class="text-xl font-heading font-bold text-surface-600 mb-2">No Projects Found</h3>
            <p class="text-surface-400">Try adjusting your filters or check back soon.</p>
            @if(request('faculty') || request('theme') || request('status'))
            <a href="{{ url('/projects') }}" class="btn-primary mt-6 inline-flex">View All Projects</a>
            @endif
        </div>
        @endif
    </div>
</section>

@endsection
