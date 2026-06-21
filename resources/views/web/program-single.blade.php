@extends('web.layouts.master')
@section('title', $program->title)

@section('content')

<!-- Banner Section -->
@if($program->banner_image)
<section class="program-banner" style="background: linear-gradient(rgba(0, 51, 102, 0.7), rgba(0, 51, 102, 0.7)), url('{{ asset('uploads/programs/'.$program->banner_image) }}'); background-size: cover; background-position: center; min-height: 350px; display: flex; align-items: center;">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 text-white">
                <h1 class="display-4 mb-3">{{ $program->title }}</h1>
                @if($program->faculty)
                <p class="lead"><i class="fas fa-building"></i> {{ $program->faculty->title }}</p>
                @endif
                @if($program->duration)
                <p class="mb-0"><i class="fas fa-clock"></i> Duration: {{ $program->duration }}</p>
                @endif
            </div>
        </div>
    </div>
</section>
@else
<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h1>{{ $program->title }}</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('programs') }}">Programs</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ $program->title }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</section>
@endif

<!-- Program Content -->
<section class="program-content pt-90 pb-60">
    <div class="container">
        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8 mb-30">
                @if($program->featured_image)
                <img src="{{ asset('uploads/programs/'.$program->featured_image) }}" alt="{{ $program->title }}" class="img-fluid rounded shadow mb-30">
                @endif

                @if($program->excerpt)
                <div class="program-excerpt mb-40">
                    <div class="alert alert-info border-0 shadow-sm">
                        <h5 class="mb-2"><i class="fas fa-info-circle"></i> Overview</h5>
                        <p class="mb-0">{{ $program->excerpt }}</p>
                    </div>
                </div>
                @endif

                @if($program->description)
                <div class="program-description mb-40">
                    <h3 class="mb-3">About This Program</h3>
                    <div class="text-justify">
                        {!! $program->description !!}
                    </div>
                </div>
                @endif

                @if($program->requirements)
                <div class="program-requirements mb-40">
                    <h3 class="mb-3"><i class="fas fa-clipboard-list text-primary"></i> Entry Requirements</h3>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            {!! $program->requirements !!}
                        </div>
                    </div>
                </div>
                @endif

                @if($program->career_prospects)
                <div class="program-career mb-40">
                    <h3 class="mb-3"><i class="fas fa-briefcase text-success"></i> Career Prospects</h3>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            {!! $program->career_prospects !!}
                        </div>
                    </div>
                </div>
                @endif
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4 mb-30">
                <div class="program-sidebar">
                    <!-- Program Info Card -->
                    <div class="card border-0 shadow-sm mb-30">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-info-circle"></i> Program Information</h5>
                        </div>
                        <div class="card-body">
                            @if($program->faculty)
                            <div class="mb-3">
                                <strong><i class="fas fa-building text-primary"></i> Faculty:</strong>
                                <p class="mb-0">{{ $program->faculty->title }}</p>
                            </div>
                            @endif

                            @if($program->duration)
                            <div class="mb-3">
                                <strong><i class="fas fa-clock text-primary"></i> Duration:</strong>
                                <p class="mb-0">{{ $program->duration }}</p>
                            </div>
                            @endif

                            @if($program->credit)
                            <div class="mb-3">
                                <strong><i class="fas fa-certificate text-primary"></i> Credits:</strong>
                                <p class="mb-0">{{ $program->credit }}</p>
                            </div>
                            @endif

                            @if($program->shortcode)
                            <div class="mb-3">
                                <strong><i class="fas fa-code text-primary"></i> Program Code:</strong>
                                <p class="mb-0">{{ $program->shortcode }}</p>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- Apply Now Card -->
                    <div class="card border-0 shadow-sm bg-primary text-white mb-30">
                        <div class="card-body text-center">
                            <h5 class="mb-3">Ready to Apply?</h5>
                            <p class="mb-3">Start your journey with us today!</p>
                            <a href="{{ route('admissions') }}" class="btn btn-light btn-block">
                                <i class="fas fa-graduation-cap"></i> Apply Now
                            </a>
                        </div>
                    </div>

                    <!-- Contact Card -->
                    <div class="card border-0 shadow-sm mb-30">
                        <div class="card-body">
                            <h5 class="mb-3"><i class="fas fa-envelope text-primary"></i> Need More Info?</h5>
                            <p>Contact our admissions office for more details about this program.</p>
                            <a href="{{ route('admissions') }}" class="btn btn-outline-primary btn-block">
                                <i class="fas fa-phone"></i> Admissions Office
                            </a>
                        </div>
                    </div>

                    <!-- Related Programs -->
                    @if($program->faculty && $program->faculty->programs->count() > 1)
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-th-list text-primary"></i> Related Programs</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                @foreach($program->faculty->programs->where('id', '!=', $program->id)->take(5) as $relatedProgram)
                                <a href="{{ route('program.single', $relatedProgram->slug) }}" class="list-group-item list-group-item-action">
                                    <i class="fas fa-arrow-right text-primary"></i> {{ $relatedProgram->title }}
                                </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Back to Programs -->
        <div class="row">
            <div class="col-12 text-center">
                <a href="{{ route('programs') }}" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Back to All Programs
                </a>
            </div>
        </div>
    </div>
</section>

@endsection
