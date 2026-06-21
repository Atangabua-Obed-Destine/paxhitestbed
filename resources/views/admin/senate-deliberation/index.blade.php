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
                                <div class="form-group col-md-4">
                                    <label for="session">{{ __('Academic Session') }} <span class="text-danger">*</span></label>
                                    <select class="form-control" name="session" id="session" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($sessions as $sess)
                                        <option value="{{ $sess->id }}" @if($selected_session == $sess->id) selected @endif>{{ $sess->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="semester">{{ __('Semester') }} <span class="text-danger">*</span></label>
                                    <select class="form-control" name="semester" id="semester" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($semesters as $sem)
                                        <option value="{{ $sem->id }}" @if($selected_semester == $sem->id) selected @endif>{{ $sem->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search"></i> {{ __('Load Dashboard') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @if($selected_session !== '0' && $selected_semester !== '0')

            {{-- ══════════════════════════════════════════════════════════════════
                 KPI CARDS ROW
            ══════════════════════════════════════════════════════════════════ --}}
            <div class="col-md-3 col-sm-6">
                <div class="card bg-primary text-white">
                    <div class="card-block text-center">
                        <div class="mb-1"><i class="fas fa-users fa-2x"></i></div>
                        <h3 class="mb-0">{{ number_format($total_students ?? 0) }}</h3>
                        <p class="mb-0">{{ __('Total Students') }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card bg-info text-white">
                    <div class="card-block text-center">
                        <div class="mb-1"><i class="fas fa-book-open fa-2x"></i></div>
                        <h3 class="mb-0">{{ $total_programs ?? 0 }}</h3>
                        <p class="mb-0">{{ __('Programs') }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card bg-success text-white">
                    <div class="card-block text-center">
                        <div class="mb-1"><i class="fas fa-chart-line fa-2x"></i></div>
                        <h3 class="mb-0">{{ $avg_gpa ?? '0.00' }}</h3>
                        <p class="mb-0">{{ __('Average GPA') }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card {{ ($flagged_count ?? 0) > 0 ? 'bg-danger' : 'bg-secondary' }} text-white">
                    <div class="card-block text-center">
                        <div class="mb-1"><i class="fas fa-exclamation-triangle fa-2x"></i></div>
                        <h3 class="mb-0">{{ $flagged_count ?? 0 }}</h3>
                        <p class="mb-0">{{ __('Flagged Students') }}</p>
                    </div>
                </div>
            </div>

            {{-- ══════════════════════════════════════════════════════════════════
                 PUBLISHING READINESS + ACADEMIC STANDING DISTRIBUTION
            ══════════════════════════════════════════════════════════════════ --}}
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-broadcast-tower"></i> {{ __('Publishing Readiness') }}</h5>
                        <span class="badge bg-{{ ($publishing['readiness'] ?? 0) >= 100 ? 'success' : (($publishing['readiness'] ?? 0) >= 50 ? 'warning' : 'danger') }} float-end" style="font-size:0.9rem">
                            {{ $publishing['readiness'] ?? 0 }}%
                        </span>
                    </div>
                    <div class="card-block">
                        <!-- Readiness Progress Bar -->
                        <div class="progress mb-3" style="height: 24px;">
                            @php
                                $total = $publishing['total'] ?? 1;
                                $pPct = $total > 0 ? round(($publishing['published'] ?? 0)/$total*100,1) : 0;
                                $aPct = $total > 0 ? round(($publishing['approved'] ?? 0)/$total*100,1) : 0;
                                $cPct = $total > 0 ? round(($publishing['checked'] ?? 0)/$total*100,1) : 0;
                                $sPct = $total > 0 ? round(($publishing['submitted'] ?? 0)/$total*100,1) : 0;
                                $dPct = $total > 0 ? round(($publishing['draft'] ?? 0)/$total*100,1) : 0;
                            @endphp
                            <div class="progress-bar bg-success" style="width:{{ $pPct }}%" title="Published {{ $pPct }}%">{{ $publishing['published'] ?? 0 }}</div>
                            <div class="progress-bar bg-primary" style="width:{{ $aPct }}%" title="Approved {{ $aPct }}%">{{ $publishing['approved'] ?? 0 }}</div>
                            <div class="progress-bar bg-info" style="width:{{ $cPct }}%" title="Checked {{ $cPct }}%">{{ $publishing['checked'] ?? 0 }}</div>
                            <div class="progress-bar bg-warning" style="width:{{ $sPct }}%" title="Submitted {{ $sPct }}%">{{ $publishing['submitted'] ?? 0 }}</div>
                            <div class="progress-bar bg-secondary" style="width:{{ $dPct }}%" title="Draft {{ $dPct }}%">{{ $publishing['draft'] ?? 0 }}</div>
                        </div>

                        <div class="row text-center small">
                            <div class="col"><span class="badge bg-success">●</span> Published: <strong>{{ $publishing['published'] ?? 0 }}</strong></div>
                            <div class="col"><span class="badge bg-primary">●</span> Approved: <strong>{{ $publishing['approved'] ?? 0 }}</strong></div>
                            <div class="col"><span class="badge bg-info">●</span> Checked: <strong>{{ $publishing['checked'] ?? 0 }}</strong></div>
                            <div class="col"><span class="badge bg-warning">●</span> Submitted: <strong>{{ $publishing['submitted'] ?? 0 }}</strong></div>
                            <div class="col"><span class="badge bg-secondary">●</span> Draft: <strong>{{ $publishing['draft'] ?? 0 }}</strong></div>
                        </div>

                        <canvas id="publishingChart" height="220" class="mt-3"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-md-7">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-graduation-cap"></i> {{ __('Academic Standing Distribution') }}</h5>
                        @if(!($standings_computed ?? false))
                            <span class="float-end">
                                <form method="post" action="{{ route($route.'.classify') }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="session" value="{{ $selected_session }}">
                                    <input type="hidden" name="semester" value="{{ $selected_semester }}">
                                    <button type="submit" class="btn btn-sm btn-outline-primary"
                                        onclick="return confirm('This will compute academic standings for all students in this session/semester. Continue?')">
                                        <i class="fas fa-cog"></i> {{ __('Generate Standings') }}
                                    </button>
                                </form>
                            </span>
                        @endif
                    </div>
                    <div class="card-block">
                        @if($standings_computed ?? false)
                            <canvas id="standingChart" height="200"></canvas>

                            <div class="row text-center mt-3 g-2">
                                <div class="col">
                                    <div class="p-2 rounded bg-light">
                                        <i class="fas fa-trophy text-primary"></i><br>
                                        <strong>{{ $standing_distribution['deans_list'] ?? 0 }}</strong><br>
                                        <small>Dean's List</small>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="p-2 rounded bg-light">
                                        <i class="fas fa-check-circle text-success"></i><br>
                                        <strong>{{ $standing_distribution['good_standing'] ?? 0 }}</strong><br>
                                        <small>Good Standing</small>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="p-2 rounded bg-light">
                                        <i class="fas fa-exclamation-triangle text-warning"></i><br>
                                        <strong>{{ $standing_distribution['academic_warning'] ?? 0 }}</strong><br>
                                        <small>Warning</small>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="p-2 rounded bg-light">
                                        <i class="fas fa-exclamation-circle text-orange"></i><br>
                                        <strong>{{ $standing_distribution['academic_probation'] ?? 0 }}</strong><br>
                                        <small>Probation</small>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="p-2 rounded bg-light">
                                        <i class="fas fa-times-circle text-danger"></i><br>
                                        <strong>{{ $standing_distribution['recommended_dismissal'] ?? 0 }}</strong><br>
                                        <small>Dismissal</small>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-info-circle fa-3x mb-3"></i>
                                <p class="mb-0">{{ __('Academic standings have not been computed for this session/semester yet.') }}</p>
                                <p class="small">{{ __('Click "Generate Standings" to compute GPA and classify all students.') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ══════════════════════════════════════════════════════════════════
                 ADDITIONAL STATS ROW (Pass Rate + GPA Range + Quick Actions)
            ══════════════════════════════════════════════════════════════════ --}}
            <div class="col-md-4">
                <div class="card">
                    <div class="card-block text-center">
                        <h6 class="text-muted mb-1">{{ __('Overall Pass Rate') }}</h6>
                        <h2 class="text-{{ ($overall_pass_rate ?? 0) >= 70 ? 'success' : (($overall_pass_rate ?? 0) >= 50 ? 'warning' : 'danger') }}">
                            {{ $overall_pass_rate ?? 0 }}%
                        </h2>
                        <small class="text-muted">(GPA ≥ 2.00)</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-block text-center">
                        <h6 class="text-muted mb-1">{{ __('GPA Range') }}</h6>
                        <div class="d-flex justify-content-around">
                            <div>
                                <span class="text-success fw-bold" style="font-size:1.2rem">{{ number_format($highest_gpa ?? 0, 2) }}</span><br>
                                <small class="text-muted">Highest</small>
                            </div>
                            <div class="border-start border-end px-3">
                                <span class="fw-bold" style="font-size:1.2rem">{{ number_format($avg_gpa ?? 0, 2) }}</span><br>
                                <small class="text-muted">Average</small>
                            </div>
                            <div>
                                <span class="text-danger fw-bold" style="font-size:1.2rem">{{ number_format($lowest_gpa ?? 0, 2) }}</span><br>
                                <small class="text-muted">Lowest</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-block">
                        <h6 class="text-muted mb-2"><i class="fas fa-bolt"></i> {{ __('Quick Actions') }}</h6>
                        <div class="d-grid gap-2">
                            @if($standings_computed ?? false)
                                <form method="post" action="{{ route($route.'.classify') }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="session" value="{{ $selected_session }}">
                                    <input type="hidden" name="semester" value="{{ $selected_semester }}">
                                    <button type="submit" class="btn btn-sm btn-outline-warning w-100"
                                        onclick="return confirm('Re-classify all standings? Previous data will be overwritten.')">
                                        <i class="fas fa-sync-alt"></i> {{ __('Re-classify Standings') }}
                                    </button>
                                </form>
                            @endif
                            @if($deliberation ?? null)
                                <a href="{{ route($route.'.show', $deliberation->id) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i> {{ __('View Deliberation') }} ({{ $deliberation->meeting_number }})
                                </a>
                            @else
                                <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#createDelibModal">
                                    <i class="fas fa-plus"></i> {{ __('Create Deliberation Session') }}
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════════════════════════════════════════════════════════════
                 FACULTY OVERVIEW CARDS
            ══════════════════════════════════════════════════════════════════ --}}
            @if(isset($faculty_stats) && $faculty_stats->count() > 0)
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-university"></i> {{ __('Faculty Overview') }}</h5>
                    </div>
                    <div class="card-block">
                        <div class="row g-3">
                            @foreach($faculty_stats as $fStat)
                            <div class="col-lg-4 col-md-6">
                                <div class="card border shadow-sm mb-0">
                                    <div class="card-header bg-light py-2 d-flex align-items-center justify-content-between">
                                        <h6 class="mb-0">
                                            <i class="fas fa-building text-primary"></i> {{ $fStat['title'] }}
                                            @if($fStat['shortcode'])
                                                <small class="text-muted">({{ $fStat['shortcode'] }})</small>
                                            @endif
                                        </h6>
                                        @php
                                            $fReadiness = $fStat['total_courses'] > 0 ? round(($fStat['published']/$fStat['total_courses'])*100) : 0;
                                        @endphp
                                        <span class="badge bg-{{ $fReadiness >= 100 ? 'success' : ($fReadiness >= 50 ? 'warning' : 'secondary') }}">
                                            {{ $fReadiness }}% Published
                                        </span>
                                    </div>
                                    <div class="card-block py-2">
                                        <div class="row text-center small">
                                            <div class="col-3">
                                                <strong class="d-block text-primary">{{ $fStat['program_count'] }}</strong>
                                                Programs
                                            </div>
                                            <div class="col-3">
                                                <strong class="d-block text-info">{{ $fStat['student_count'] }}</strong>
                                                Students
                                            </div>
                                            <div class="col-3">
                                                <strong class="d-block {{ $fStat['avg_gpa'] >= 2 ? 'text-success' : 'text-danger' }}">{{ number_format($fStat['avg_gpa'], 2) }}</strong>
                                                Avg GPA
                                            </div>
                                            <div class="col-3">
                                                <strong class="d-block {{ $fStat['pass_rate'] >= 70 ? 'text-success' : ($fStat['pass_rate'] >= 50 ? 'text-warning' : 'text-danger') }}">{{ $fStat['pass_rate'] }}%</strong>
                                                Pass Rate
                                            </div>
                                        </div>
                                        <div class="progress mt-2" style="height: 4px;">
                                            <div class="progress-bar bg-success" style="width:{{ $fStat['total_courses'] > 0 ? round($fStat['published']/$fStat['total_courses']*100) : 0 }}%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- ══════════════════════════════════════════════════════════════════
                 FLAGGED STUDENTS PANEL
            ══════════════════════════════════════════════════════════════════ --}}
            @if(($flagged_count ?? 0) > 0 && isset($flagged_students) && $flagged_students->count() > 0)
            <div class="col-sm-12">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> {{ __('Flagged Students — Requires Senate Attention') }}
                            <span class="badge bg-white text-danger float-end">{{ $flagged_count }}</span>
                        </h5>
                    </div>
                    <div class="card-block p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('S/N') }}</th>
                                        <th>{{ __('Matricule') }}</th>
                                        <th>{{ __('Student Name') }}</th>
                                        <th>{{ __('Program') }}</th>
                                        <th>{{ __('GPA') }}</th>
                                        <th>{{ __('Standing') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($flagged_students as $idx => $fs)
                                    <tr>
                                        <td>{{ $idx + 1 }}</td>
                                        <td><strong>{{ $fs->enrollment->matricule ?? $fs->student->student_id ?? 'N/A' }}</strong></td>
                                        <td>{{ trim(($fs->student->first_name ?? '') . ' ' . ($fs->student->last_name ?? '')) }}</td>
                                        <td>{{ $fs->program->title ?? '' }}</td>
                                        <td><strong class="text-danger">{{ number_format($fs->gpa, 2) }}</strong></td>
                                        <td>
                                            <span class="badge {{ \App\Models\AcademicStanding::standingBadgeClass($fs->standing) }}">
                                                <i class="{{ \App\Models\AcademicStanding::standingIcon($fs->standing) }}"></i>
                                                {{ $fs->standing_label }}
                                            </span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($flagged_count > 20)
                        <div class="card-footer text-center">
                            <a href="{{ route($route.'.academic-standings', ['session' => $selected_session, 'semester' => $selected_semester, 'standing' => 'academic_warning']) }}" class="btn btn-sm btn-outline-danger">
                                {{ __('View All Flagged Students') }} →
                            </a>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            {{-- ══════════════════════════════════════════════════════════════════
                 EXISTING DELIBERATION STATUS (if one exists)
            ══════════════════════════════════════════════════════════════════ --}}
            @if($deliberation ?? null)
            <div class="col-sm-12">
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-gavel"></i> {{ __('Active Deliberation') }}: {{ $deliberation->meeting_number }}
                            <span class="badge bg-white text-primary float-end">
                                {{ \App\Models\SenateDeliberation::statusLabels()[$deliberation->status] ?? ucfirst($deliberation->status) }}
                            </span>
                        </h5>
                    </div>
                    <div class="card-block">
                        <div class="row">
                            <div class="col-md-3">
                                <strong>{{ __('Meeting Date') }}:</strong><br>
                                {{ $deliberation->meeting_date ? $deliberation->meeting_date->format('d M Y') : 'Not set' }}
                            </div>
                            <div class="col-md-3">
                                <strong>{{ __('Venue') }}:</strong><br>
                                {{ $deliberation->venue ?? 'Not set' }}
                            </div>
                            <div class="col-md-3">
                                <strong>{{ __('Chairperson') }}:</strong><br>
                                {{ $deliberation->chairperson ?? 'Not set' }}
                            </div>
                            <div class="col-md-3">
                                <strong>{{ __('Decision') }}:</strong><br>
                                <span class="badge {{ \App\Models\SenateDeliberation::decisionBadgeClass($deliberation->overall_decision) }}">
                                    {{ \App\Models\SenateDeliberation::decisionLabels()[$deliberation->overall_decision] ?? 'Pending' }}
                                </span>
                            </div>
                        </div>
                        <div class="text-end mt-3">
                            <a href="{{ route($route.'.show', $deliberation->id) }}" class="btn btn-primary">
                                <i class="fas fa-arrow-right"></i> {{ __('Open Deliberation') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @else
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

{{-- ══════════════════════════════════════════════════════════════════
     CREATE DELIBERATION MODAL
══════════════════════════════════════════════════════════════════ --}}
@if($selected_session !== '0' && $selected_semester !== '0')
<div class="modal fade" id="createDelibModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="{{ route($route.'.store') }}">
                @csrf
                <input type="hidden" name="session_id" value="{{ $selected_session }}">
                <input type="hidden" name="semester_id" value="{{ $selected_semester }}">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> {{ __('Create Deliberation Session') }}</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>{{ __('Meeting Date') }}</label>
                        <input type="date" name="meeting_date" class="form-control" value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="mb-3">
                        <label>{{ __('Venue') }}</label>
                        <input type="text" name="venue" class="form-control" placeholder="e.g. Senate Hall, Main Campus">
                    </div>
                    <div class="mb-3">
                        <label>{{ __('Chairperson') }}</label>
                        <input type="text" name="chairperson" class="form-control" placeholder="e.g. Prof. John Doe (Vice Chancellor)">
                    </div>
                    <div class="mb-3">
                        <label>{{ __('Registrar') }}</label>
                        <input type="text" name="registrar" class="form-control" placeholder="e.g. Dr. Jane Smith">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> {{ __('Create Session') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection

@section('page-js')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Publishing Readiness Donut Chart
    var pubCtx = document.getElementById('publishingChart');
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
    var standCtx = document.getElementById('standingChart');
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
});
</script>
@endsection
