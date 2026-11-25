@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <!-- Page Header -->
            <div class="col-sm-12">
                <div class="page-header">
                    <div class="page-header-left">
                        <h4 class="page-title"><i class="fas fa-landmark"></i> {{ $title }}</h4>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}"><i class="ti ti-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="#">{{ trans_choice('module_examination', 2) }}</a></li>
                            <li class="breadcrumb-item active">{{ __('Senate Deliberation') }}</li>
                        </ol>
                    </div>
                    <div class="page-header-right">
                        <div class="btn-group">
                            <a href="{{ route($route.'.academic-standings', request()->query()) }}" class="btn btn-info">
                                <i class="fas fa-graduation-cap"></i> {{ __('Academic Standings') }}
                            </a>
                            <a href="{{ route($route.'.deliberations', request()->query()) }}" class="btn btn-primary">
                                <i class="fas fa-gavel"></i> {{ __('Deliberations') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Card -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-filter"></i> {{ __('Session & Semester Selection') }}</h5>
                    </div>
                    <div class="card-block">
                        <form method="get" action="{{ route($route.'.index') }}">
                            <div class="row gx-2 align-items-end">
                                <div class="form-group col-md-3">
                                    <label for="session">{{ __('Academic Session') }} <span class="text-danger">*</span></label>
                                    <select class="form-control" name="session" id="session" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($sessions as $sess)
                                        <option value="{{ $sess->id }}" @if($selected_session == $sess->id) selected @endif>{{ $sess->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @include('common.inc.semester_type_field', ['type_col' => 'col-md-3', 'semester_col' => 'col-md-4'])
                                <div class="form-group col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search"></i> {{ __('Load Dashboard') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @if(count($semester_blocks) > 0)
            @foreach($semester_blocks as $block)
            @include('admin.senate-deliberation.partials.dashboard-semester', $block + [
                'selected_semester' => (string) $block['semester']->id,
                'bk' => count($semester_blocks) > 1 ? '-s' . $block['semester']->id : '',
                'show_heading' => count($semester_blocks) > 1,
            ])
            @endforeach
            @include('admin.senate-deliberation.partials.skipped-semesters')

            @else
            @include('admin.senate-deliberation.partials.skipped-semesters')
            {{-- No session/semester selected --}}
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-block text-center py-5">
                        <i class="fas fa-landmark fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">{{ __('Select a Session and Semester to view the Senate Deliberation Dashboard') }}</h5>
                        <p class="text-muted">{{ __('The dashboard provides a comprehensive overview of academic performance, publishing readiness, and student standings for senate review.') }}</p>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

@endsection

@section('page-js')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    @foreach($semester_blocks as $block)
    @php $bk = count($semester_blocks) > 1 ? '-s' . $block['semester']->id : ''; $publishing = $block['publishing']; $standing_distribution = $block['standing_distribution']; @endphp
    // Publishing Readiness Donut Chart
    var pubCtx = document.getElementById('publishingChart{{ $bk }}');
    if (pubCtx) {
        new Chart(pubCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Published', 'Approved', 'Checked', 'Submitted', 'Draft'],
                datasets: [{
                    data: [
                        {{ $publishing['published'] ?? 0 }},
                        {{ $publishing['approved'] ?? 0 }},
                        {{ $publishing['checked'] ?? 0 }},
                        {{ $publishing['submitted'] ?? 0 }},
                        {{ $publishing['draft'] ?? 0 }}
                    ],
                    backgroundColor: ['#28a745', '#007bff', '#17a2b8', '#ffc107', '#6c757d'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 15, usePointStyle: true } }
                }
            }
        });
    }

    // Academic Standing Distribution Bar Chart
    var standCtx = document.getElementById('standingChart{{ $bk }}');
    if (standCtx) {
        new Chart(standCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ["Dean's List", 'Good Standing', 'Warning', 'Probation', 'Dismissal'],
                datasets: [{
                    label: 'Students',
                    data: [
                        {{ $standing_distribution['deans_list'] ?? 0 }},
                        {{ $standing_distribution['good_standing'] ?? 0 }},
                        {{ $standing_distribution['academic_warning'] ?? 0 }},
                        {{ $standing_distribution['academic_probation'] ?? 0 }},
                        {{ $standing_distribution['recommended_dismissal'] ?? 0 }}
                    ],
                    backgroundColor: ['#4e73df', '#28a745', '#ffc107', '#fd7e14', '#dc3545'],
                    borderRadius: 6,
                    barPercentage: 0.6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    }
    @endforeach
});
</script>
@endsection
