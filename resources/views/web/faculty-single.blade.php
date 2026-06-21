@extends('web.layouts.master')
@section('title', $faculty->title)

@section('content')

<!-- Banner Section -->
@if($faculty->banner_image)
<section class="faculty-banner" style="background: linear-gradient(rgba(0, 51, 102, 0.7), rgba(0, 51, 102, 0.7)), url('{{ asset('uploads/faculties/'.$faculty->banner_image) }}'); background-size: cover; background-position: center; min-height: 350px; display: flex; align-items: center;">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 text-white">
                <h1 class="display-4 mb-3">{{ $faculty->title }}</h1>
                @if($faculty->excerpt)
                <p class="lead">{{ $faculty->excerpt }}</p>
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
                <h1>{{ $faculty->title }}</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('faculties') }}">Faculties</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ $faculty->title }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</section>
@endif

<!-- Faculty Content -->
<section class="faculty-content pt-90 pb-60">
    <div class="container">
        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8 mb-30">
                @if($faculty->featured_image)
                <img src="{{ asset('uploads/faculties/'.$faculty->featured_image) }}" alt="{{ $faculty->title }}" class="img-fluid rounded shadow mb-30">
                @endif

                @if($faculty->description)
                <div class="faculty-description mb-40">
                    <h3 class="mb-3">About the Faculty</h3>
                    <div class="text-justify">
                        {!! $faculty->description !!}
                    </div>
                </div>
                @endif

                <!-- Dean Information -->
                @if($faculty->dean_name || $faculty->dean_photo)
                <div class="dean-info mb-40">
                    <h3 class="mb-4"><i class="fas fa-user-tie text-primary"></i> Dean's Message</h3>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="row align-items-center">
                                @if($faculty->dean_photo)
                                <div class="col-md-3 text-center mb-3 mb-md-0">
                                    <img src="{{ asset('uploads/faculties/deans/'.$faculty->dean_photo) }}" alt="{{ $faculty->dean_name }}" class="img-fluid rounded-circle shadow" style="max-width: 150px;">
                                </div>
                                @endif
                                <div class="col-md-9">
                                    @if($faculty->dean_name)
                                    <h5 class="mb-2">{{ $faculty->dean_name }}</h5>
                                    <p class="text-muted mb-3">Dean, {{ $faculty->title }}</p>
                                    @endif
                                    @if($faculty->email)
                                    <p class="mb-1"><i class="fas fa-envelope text-primary"></i> <a href="mailto:{{ $faculty->email }}">{{ $faculty->email }}</a></p>
                                    @endif
                                    @if($faculty->phone)
                                    <p class="mb-1"><i class="fas fa-phone text-primary"></i> <a href="tel:{{ $faculty->phone }}">{{ $faculty->phone }}</a></p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Programs Offered -->
                @if($faculty->programs && $faculty->programs->count() > 0)
                <div class="faculty-programs mb-40">
                    <h3 class="mb-4"><i class="fas fa-book text-primary"></i> Programs Offered</h3>
                    <div class="row">
                        @foreach($faculty->programs as $program)
                        <div class="col-md-6 mb-30">
                            <div class="card h-100 border-0 shadow-sm">
                                @if($program->featured_image)
                                <img src="{{ asset('uploads/programs/'.$program->featured_image) }}" class="card-img-top" alt="{{ $program->title }}">
                                @endif
                                <div class="card-body">
                                    <h5 class="card-title">{{ $program->title }}</h5>
                                    @if($program->excerpt)
                                    <p class="card-text">{{ Str::limit($program->excerpt, 100) }}</p>
                                    @endif
                                    <div class="program-meta mb-3">
                                        @if($program->duration)
                                        <span class="badge badge-primary"><i class="fas fa-clock"></i> {{ $program->duration }}</span>
                                        @endif
                                        @if($program->credit)
                                        <span class="badge badge-info"><i class="fas fa-certificate"></i> {{ $program->credit }}</span>
                                        @endif
                                        @if($program->shortcode)
                                        <span class="badge badge-secondary"><i class="fas fa-code"></i> {{ $program->shortcode }}</span>
                                        @endif
                                    </div>
                                    <a href="{{ route('program.single', $program->slug) }}" class="btn btn-outline-primary btn-block">
                                        View Program <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4 mb-30">
                <div class="faculty-sidebar">
                    <!-- Faculty Info Card -->
                    <div class="card border-0 shadow-sm mb-30">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-info-circle"></i> Faculty Information</h5>
                        </div>
                        <div class="card-body">
                            @if($faculty->shortcode)
                            <div class="mb-3">
                                <strong><i class="fas fa-code text-primary"></i> Faculty Code:</strong>
                                <p class="mb-0">{{ $faculty->shortcode }}</p>
                            </div>
                            @endif

                            <div class="mb-3">
                                <strong><i class="fas fa-book text-primary"></i> Programs Offered:</strong>
                                <p class="mb-0">{{ $faculty->programs->count() }} Program(s)</p>
                            </div>

                            @if($faculty->website)
                            <div class="mb-3">
                                <strong><i class="fas fa-globe text-primary"></i> Website:</strong>
                                <p class="mb-0"><a href="{{ $faculty->website }}" target="_blank">Visit Website</a></p>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- Contact Card -->
                    @if($faculty->email || $faculty->phone)
                    <div class="card border-0 shadow-sm mb-30">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-phone text-primary"></i> Contact Faculty</h5>
                        </div>
                        <div class="card-body">
                            @if($faculty->email)
                            <p class="mb-2">
                                <i class="fas fa-envelope text-primary"></i> 
                                <a href="mailto:{{ $faculty->email }}">{{ $faculty->email }}</a>
                            </p>
                            @endif
                            @if($faculty->phone)
                            <p class="mb-0">
                                <i class="fas fa-phone text-primary"></i> 
                                <a href="tel:{{ $faculty->phone }}">{{ $faculty->phone }}</a>
                            </p>
                            @endif
                        </div>
                    </div>
                    @endif

                    <!-- Apply Now Card -->
                    <div class="card border-0 shadow-sm bg-primary text-white mb-30">
                        <div class="card-body text-center">
                            <h5 class="mb-3">Interested in Our Programs?</h5>
                            <p class="mb-3">Explore admission requirements and apply today!</p>
                            <a href="{{ route('admissions') }}" class="btn btn-light btn-block">
                                <i class="fas fa-graduation-cap"></i> View Admissions
                            </a>
                        </div>
                    </div>

                    <!-- Quick Links -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-link text-primary"></i> Quick Links</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <a href="{{ route('programs') }}" class="list-group-item list-group-item-action">
                                    <i class="fas fa-book"></i> All Programs
                                </a>
                                <a href="{{ route('faculties') }}" class="list-group-item list-group-item-action">
                                    <i class="fas fa-building"></i> All Faculties
                                </a>
                                <a href="{{ route('admissions') }}" class="list-group-item list-group-item-action">
                                    <i class="fas fa-graduation-cap"></i> Admissions
                                </a>
                                <a href="{{ route('about') }}" class="list-group-item list-group-item-action">
                                    <i class="fas fa-info-circle"></i> About Us
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Back to Faculties -->
        <div class="row">
            <div class="col-12 text-center">
                <a href="{{ route('faculties') }}" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Back to All Faculties
                </a>
            </div>
        </div>
    </div>
</section>

@endsection
